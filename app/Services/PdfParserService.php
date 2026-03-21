<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Config;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * อ่าน PDF Label จาก TikTok Shop (J&T Express)
 * แยกข้อมูล: Barcode, ผู้ส่ง, ผู้รับ, ที่อยู่, Order ID, Product
 *
 * ใช้ library: smalot/pdfparser (composer require smalot/pdfparser)
 */
class PdfParserService
{
    protected Parser $parser;

    public function __construct()
    {
        // ปิด image content เพื่อลด memory — สำคัญมากสำหรับ PDF ขนาดใหญ่
        $config = new Config();
        $config->setRetainImageContent(false);

        $this->parser = new Parser([], $config);
    }

    /**
     * อ่าน PDF ทั้งไฟล์ แยกข้อมูลทีละหน้า
     *
     * @return array<int, array> array ของ parsed orders
     */
    public function parseFile(string $filePath): array
    {
        // เพิ่ม memory ชั่วคราวสำหรับ PDF ขนาดใหญ่ (409 หน้า)
        $prevMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            // ดึง product info + key fields ด้วย PyMuPDF ก่อน (ครบทุกหน้า)
            $productInfoByPage = $this->extractProductInfoWithPython($filePath);

            // Build smalot text map เฉพาะหน้าที่ smalot อ่านได้ (อาจได้ไม่ครบทุกหน้า)
            $smalotTexts = [];
            try {
                $pdf   = $this->parser->parseFile($filePath);
                $pages = $pdf->getPages();
                foreach ($pages as $idx => $page) {
                    try {
                        $smalotTexts[$idx + 1] = $page->getText();
                    } catch (\Throwable) {
                        $smalotTexts[$idx + 1] = '';
                    }
                }
                unset($pdf, $pages);
            } catch (\Throwable) {
                // smalot ล้มเหลวทั้งไฟล์ → ยังใช้ Python ได้
            }

            // จำนวนหน้าจริง: ใช้ Python เป็น source of truth (ครบกว่า smalot)
            $totalPages = !empty($productInfoByPage)
                ? max(array_map('intval', array_keys($productInfoByPage)))
                : count($smalotTexts);

            $orders       = [];
            $lastOrderKey = null;

            for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++) {
                $text   = $smalotTexts[$pageNum] ?? '';
                $parsed = $this->parseLabelText($text);
                $pyInfo = $productInfoByPage[(string)$pageNum] ?? null;

                // ============================================================
                // Fallback: smalot อ่าน tracking ไม่ได้ → ใช้ PyMuPDF
                // สาเหตุ: TikTok PDF 1.5+ compressed object streams
                // ============================================================
                if (empty($parsed['tracking_number']) && !empty($pyInfo['tracking_number'])) {
                    $parsed['tracking_number'] = $pyInfo['tracking_number'];
                }
                if (empty($parsed['order_id']) && !empty($pyInfo['order_id'])) {
                    $parsed['order_id'] = $pyInfo['order_id'];
                }
                if (empty($parsed['carrier']) && !empty($pyInfo['carrier'])) {
                    $parsed['carrier'] = $pyInfo['carrier'];
                }
                if (empty($parsed['service_type']) && !empty($pyInfo['service_type'])) {
                    $parsed['service_type'] = $pyInfo['service_type'];
                }
                if (empty($parsed['shipping_date']) && !empty($pyInfo['shipping_date'])) {
                    $parsed['shipping_date'] = $pyInfo['shipping_date'];
                }
                if (($parsed['payment_type'] ?? 'PREPAID') === 'PREPAID' && !empty($pyInfo['payment_type'])) {
                    $parsed['payment_type'] = $pyInfo['payment_type'];
                }

                if ($parsed && !empty($parsed['tracking_number'])) {
                    // ======================================================
                    // หน้าปกติ: มี tracking number → สร้าง order entry ใหม่
                    // ======================================================
                    $parsed['page_number'] = $pageNum;

                    // Merge product info จาก PyMuPDF (แม่นยำกว่า smalot — รู้ column x-position)
                    if ($pyInfo) {
                        if (!empty($pyInfo['product_name']))    $parsed['product_name']    = $pyInfo['product_name'];
                        if (!empty($pyInfo['product_sku']))     $parsed['product_sku']     = $pyInfo['product_sku'];
                        if (!empty($pyInfo['seller_sku']))      $parsed['seller_sku']      = $pyInfo['seller_sku'];
                        if (isset($pyInfo['item_quantities']))  $parsed['item_quantities'] = $pyInfo['item_quantities'];
                        // quantity: ไม่ override — ใช้ smalot (Qty Total ถูกต้องกว่า)
                        // address: PyMuPDF decode Thai font ได้ถูกต้องกว่า smalot
                        if (!empty($pyInfo['recipient_address'])) {
                            $parsed['recipient_address'] = $pyInfo['recipient_address'];
                            // แยก district/province/zipcode จาก address string
                            [$d, $p, $z] = $this->extractAddressComponents($pyInfo['recipient_address']);
                            if ($d) $parsed['recipient_district']  = $d;
                            if ($p) $parsed['recipient_province']  = $p;
                            if ($z) $parsed['recipient_zipcode']   = $z;
                        }
                    }

                    $orders[]     = $parsed;
                    $lastOrderKey = array_key_last($orders);

                } elseif ($lastOrderKey !== null && $pyInfo !== null) {
                    // ======================================================
                    // Continuation page: ไม่มี tracking number แต่มีข้อมูลสินค้า
                    // → append เข้า order ก่อนหน้า
                    // ======================================================
                    if (!empty($pyInfo['product_name'])) {
                        $prev = $orders[$lastOrderKey]['product_name'] ?? '';
                        $orders[$lastOrderKey]['product_name'] = $prev
                            ? $prev . ' | ' . $pyInfo['product_name']
                            : $pyInfo['product_name'];
                    }
                    if (!empty($pyInfo['product_sku'])) {
                        $prev = $orders[$lastOrderKey]['product_sku'] ?? '';
                        $orders[$lastOrderKey]['product_sku'] = $prev
                            ? $prev . ' | ' . $pyInfo['product_sku']
                            : $pyInfo['product_sku'];
                    }
                    if (!empty($pyInfo['seller_sku'])) {
                        $prev = $orders[$lastOrderKey]['seller_sku'] ?? '';
                        $orders[$lastOrderKey]['seller_sku'] = $prev
                            ? $prev . ' | ' . $pyInfo['seller_sku']
                            : $pyInfo['seller_sku'];
                    }
                    if (isset($pyInfo['item_quantities'])) {
                        $prev = $orders[$lastOrderKey]['item_quantities'] ?? '';
                        $orders[$lastOrderKey]['item_quantities'] = $prev
                            ? $prev . ' | ' . $pyInfo['item_quantities']
                            : $pyInfo['item_quantities'];
                    }
                    // ใช้ Qty Total จาก smalot ของ continuation page (มีค่ารวมทั้งหมด)
                    if (!empty($parsed['quantity']) && $parsed['quantity'] > ($orders[$lastOrderKey]['quantity'] ?? 1)) {
                        $orders[$lastOrderKey]['quantity'] = $parsed['quantity'];
                    }
                }

                // คืน memory หลังอ่านแต่ละหน้า
                unset($text, $parsed);
            }

        } finally {
            ini_set('memory_limit', $prevMemoryLimit);
        }

        return $orders;
    }

    /**
     * ใช้ PyMuPDF extract product info ทุกหน้าพร้อมกัน (1 process call)
     * คืน array keyed by page number (1-based string)
     */
    protected function extractProductInfoWithPython(string $filePath): array
    {
        $python3 = $this->findPython3();
        if (!$python3) {
            return [];
        }

        $script = base_path('scripts/extract_product_info.py');
        if (!file_exists($script)) {
            return [];
        }

        $cmd    = escapeshellarg($python3) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($filePath) . ' 2>/dev/null';
        $output = shell_exec($cmd);

        if (!$output) {
            return [];
        }

        $decoded = json_decode($output, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * หา Python3 binary
     */
    protected function findPython3(): ?string
    {
        $candidates = ['/usr/bin/python3', '/usr/local/bin/python3', 'python3'];
        foreach ($candidates as $path) {
            $out = [];
            exec('which ' . escapeshellarg($path) . ' 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                return $out[0];
            }
            if (str_starts_with($path, '/') && file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * อ่านจาก UploadedFile
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $path = $file->store('uploads', 'public_files');
        $fullPath = public_path($path);

        return $this->parseFile($fullPath);
    }

    /**
     * แยกข้อมูลจาก text ของ 1 หน้า (1 Label)
     */
    protected function parseLabelText(string $text): ?array
    {
        $data = [
            'carrier' => null,
            'service_type' => null,
            'platform' => 'TIKTOK',
            'tracking_number' => null,
            'order_id' => null,
            'sorting_code' => null,
            'sorting_code_2' => null,
            'route_code' => null,
            'sender_name' => null,
            'sender_address' => null,
            'recipient_name' => null,
            'recipient_phone' => null,
            'recipient_address' => null,
            'recipient_district' => null,
            'recipient_province' => null,
            'recipient_zipcode' => null,
            'payment_type' => 'PREPAID',
            'delivery_type' => 'DROP-OFF',
            'shipping_date' => null,
            'product_name' => null,
            'product_sku' => null,
            'seller_sku' => null,
            'quantity' => 1,
            'item_quantities' => null,
        ];

        // ============================================================
        // Carrier Detection
        // ============================================================
        if (str_contains($text, 'FLASH')) {
            $data['carrier'] = 'FLASH';
        } elseif (str_contains($text, 'J&T') || str_contains($text, 'J&amp;T')) {
            $data['carrier'] = 'JT';
        }
        if (preg_match('/\b(EZ|NDD)\b/', $text, $m)) {
            $data['service_type'] = $m[1];
        }

        // ============================================================
        // Tracking Number — J&T: 79xxxxxxxxxx (12 หลัก), Flash: THT... (alphanumeric)
        // ============================================================
        if (preg_match('/\b(79\d{10})\b/', $text, $m)) {
            $data['tracking_number'] = $m[1];
            if (empty($data['carrier'])) $data['carrier'] = 'JT';
        } elseif (preg_match('/\b(THT[A-Z0-9]{8,16})\b/', $text, $m)) {
            $data['tracking_number'] = $m[1];
            if (empty($data['carrier'])) $data['carrier'] = 'FLASH';
        }

        // ============================================================
        // Order ID (15-20 หลัก)
        // ============================================================
        if (preg_match('/Order\s*ID:\s*(\d{15,20})/', $text, $m)) {
            $data['order_id'] = $m[1];
        }

        // ============================================================
        // Sorting Codes — J&T: "H1 G10-01", Flash: "17B-16449-03"
        // ============================================================
        if (preg_match('/([A-Z]\d+\s+[A-Z]\d+-\d+)/', $text, $m)) {
            $data['sorting_code'] = trim($m[1]);
        } elseif (preg_match('/\b(\d+[A-Z]-\d+-\d+)\b/', $text, $m)) {
            $data['sorting_code'] = trim($m[1]);
        }

        // J&T: "006A", Flash: "C13", "C05"
        if (preg_match('/\b(\d{3}[A-Z])\b/', $text, $m)) {
            $data['sorting_code_2'] = $m[1];
        } elseif (preg_match('/\b([A-Z]\d{2,3})\b/', $text, $m)) {
            $data['sorting_code_2'] = $m[1];
        }

        // ============================================================
        // Route Code — J&T: 3 หลัก เช่น 698, Flash zone: "4TAI_BSP-ทาอิ"
        // ============================================================
        if (preg_match('/\b(698|[0-9]{3})\b/', $text, $m)) {
            $data['route_code'] = $m[1];
        }
        if (empty($data['route_code']) && preg_match('/\b([A-Z0-9]+_[A-Z]+-[\p{Thai}A-Za-z]+)/u', $text, $m)) {
            $data['route_code'] = $m[1];
        }

        // ============================================================
        // ผู้ส่ง (จาก ... ถึง)
        // ============================================================
        if (preg_match('/จาก\s+(.+?)(?=ถึง)/su', $text, $m)) {
            $senderBlock = trim($m[1]);
            $lines = preg_split('/\n/', $senderBlock);
            if (count($lines) > 0) {
                $data['sender_name'] = trim($lines[0]);
            }
            if (count($lines) > 1) {
                $data['sender_address'] = trim(implode(' ', array_slice($lines, 1)));
            }
        }

        // ============================================================
        // ชื่อผู้รับ — อยู่ระหว่าง zipcode (standalone) กับ Shipping Date
        // กรณีปกติ:  {zipcode}\n{ชื่อ}\n{dd-mm-yyyy}
        // กรณีพิเศษ: {zipcode}\n{ชื่อ} {บ้านเลขที่} {ที่อยู่...}\n{dd-mm-yyyy}
        //            (ชื่อกับที่อยู่อยู่บรรทัดเดียวกัน เช่น "น.ส.มนทิรา พิมพา 205 หมู...")
        // ใช้ [^\d\n] แทน [\p{Thai}] เพราะ PDF บางไฟล์ใช้ PUA chars
        // ============================================================
        if (preg_match('/\b[1-9]\d{4}\b[ \t]*\n(.+?)\d{2}-\d{2}-\d{4}/su', $text, $m)) {
            $lines = array_filter(array_map('trim', preg_split('/\n/', $m[1])));
            $nameParts = [];

            foreach (array_values($lines) as $line) {
                if (empty($line)) continue;
                // ตัดส่วนบ้านเลขที่ + ที่อยู่ออก (เลขบ้านขึ้นต้นด้วยตัวเลข หรือมี เลข ตามหลัง space)
                $part = preg_replace('/\s+\d[\d\/]*(?:\s|$).*/u', '', $line);
                $part = trim($part);

                if (empty($part)) break; // บรรทัดเป็นตัวเลขล้วน = เข้าส่วนที่อยู่แล้ว

                // ตัดอักขระสั้นมากที่เป็นเศษจาก PDF rendering (เช่น "ู" ต่อจากบรรทัดก่อน)
                if (mb_strlen($part) < 2) continue;

                $nameParts[] = $part;

                // ถ้า line เดิมมีบ้านเลขที่ปน → ชื่อจบแค่ line นี้
                if (preg_match('/\s+\d[\d\/]*/u', $line)) break;

                if (count($nameParts) >= 2) break;
            }

            $name = preg_replace('/\s+/', ' ', implode(' ', $nameParts));
            if (mb_strlen($name) > 1 && mb_strlen($name) < 80) {
                $data['recipient_name'] = $name;
            }
        }

        // ============================================================
        // เบอร์โทร
        // ============================================================
        if (preg_match('/\(\+66\)(\d[\d\*]+)/', $text, $m)) {
            $data['recipient_phone'] = '(+66)' . $m[1];
        }

        // ============================================================
        // รหัสไปรษณีย์ (5 หลัก)
        // ============================================================
        if (preg_match('/\b([1-9]\d{4})\b/', $text, $m)) {
            $data['recipient_zipcode'] = $m[1];
        }

        // ============================================================
        // จังหวัด + อำเภอ (pattern: อำเภอ , จังหวัด)
        // ============================================================
        if (preg_match('/([\p{Thai}]+)\s*,\s*([\p{Thai}]+)\s*\n?\s*\d{5}/u', $text, $m)) {
            $data['recipient_district'] = trim($m[1]);
            $data['recipient_province'] = trim($m[2]);
        }

        // ============================================================
        // ที่อยู่ผู้รับ — อยู่ตอนต้นของ label ก่อน tracking number (79xxxxxxxxxx)
        // โครงสร้าง PDF: V\n{ที่อยู่ผู้รับ}\n{tracking}\nจากM**y...
        // ไม่ใช้ block หลัง "ถึง" เพราะนั่นคือเบอร์โทร + sorting code
        // ============================================================
        if (preg_match('/^V?\s*\n(.*?)\n79\d{10}/su', $text, $m)) {
            $addressBlock = preg_replace('/\s+/', ' ', trim($m[1]));
            $addressBlock = rtrim($addressBlock, ' ,');
            if (!empty($addressBlock)) {
                $data['recipient_address'] = $addressBlock;
            }
        }

        // ============================================================
        // ประเภทการจ่ายเงิน — Flash ไม่มี COD
        // ============================================================
        if (preg_match('/\bCOD\b/', $text)) {
            $data['payment_type'] = 'COD';
        } elseif (($data['carrier'] ?? '') === 'FLASH') {
            $data['payment_type'] = 'PREPAID';
        }

        // ============================================================
        // ประเภทการจัดส่ง
        // ============================================================
        if (preg_match('/DROP-OFF/i', $text)) {
            $data['delivery_type'] = 'DROP-OFF';
        } elseif (preg_match('/PICKUP/i', $text)) {
            $data['delivery_type'] = 'PICKUP';
        }

        // ============================================================
        // Shipping Date
        // ============================================================
        // J&T (PyMuPDF): "09-03-2026\nShipping Date:" | J&T (smalot): "09-03-2026Shipping Date:"
        // Flash: "Shipping Date:\n21-03-2026"
        if (preg_match('/(\d{2}-\d{2}-\d{4})\s*\n?\s*Shipping\s*Date:/s', $text, $m)) {
            $data['shipping_date'] = $m[1];
        } elseif (preg_match('/Shipping\s*Date:\s*\n?\s*(\d{2}-\d{2}-\d{4})/s', $text, $m)) {
            $data['shipping_date'] = $m[1];
        }

        // ============================================================
        // Product Info (จาก table ด้านล่าง)
        // ============================================================
        if (preg_match('/Product\s*Name\s+SKU\s+Seller\s*SKU\s+Qty\s*\n(.+?)(?=Qty\s*Total)/su', $text, $m)) {
            $productBlock = trim($m[1]);

            // แยก product name (ทุกอย่างก่อน SKU column)
            $data['product_name'] = trim(preg_replace('/\s+/', ' ', $productBlock));

            // SKU
            if (preg_match('/(?:ค่าเริ่มต้น|[\w\/\-]+)\s+([\w\/\-]+)\s+(\d+)\s*$/', $productBlock, $pm)) {
                $data['product_sku'] = trim($pm[0]);
                $data['seller_sku'] = trim($pm[1]);
                $data['quantity'] = (int) $pm[2];
            }
        }

        // fallback: ค้นหา product name จาก pattern -, -
        if (empty($data['product_name']) || $data['product_name'] === '-, -') {
            if (preg_match('/-, -\s+([\w\/]+)\s+(\d+)/', $text, $m)) {
                $data['product_name'] = '-, -'; // ไม่มีชื่อสินค้าจริง (PDF ที่ซ่อนแล้ว)
                $data['product_sku'] = $m[1];
                $data['quantity'] = (int) $m[2];
            }
        }

        // ============================================================
        // Quantity
        // ============================================================
        if (preg_match('/Qty\s*Total:\s*(\d+)/', $text, $m)) {
            $data['quantity'] = (int) $m[1];
        }

        return $data;
    }

    /**
     * แยก district / province / zipcode จาก recipient_address
     * Format ที่ TikTok ใช้: "[ที่อยู่] , [อำเภอ] , [จังหวัด] [zipcode]"
     * @return array [district, province, zipcode]
     */
    protected function extractAddressComponents(string $address): array
    {
        $parts = array_map('trim', explode(',', $address));
        $count = count($parts);
        if ($count < 2) return [null, null, null];

        // ส่วนท้ายสุด: "จังหวัด 5หลัก"
        $last = $parts[$count - 1];
        if (!preg_match('/^([\p{Thai}\s]+?)\s+(\d{5})\s*$/u', $last, $m)) {
            return [null, null, null];
        }
        $province = trim($m[1]);
        $zipcode  = $m[2];

        // ส่วนก่อนท้ายสุด: "อำเภอ" — รับเฉพาะ Thai text ล้วน ความยาวไม่เกิน 40 ตัว
        $district = null;
        if ($count >= 3) {
            $prev = $parts[$count - 2];
            if (mb_strlen($prev) <= 40 && preg_match('/^[\p{Thai}\s]+$/u', $prev)) {
                $district = $prev;
            }
        }

        return [$district, $province, $zipcode];
    }

    /**
     * นับจำนวนหน้าใน PDF
     */
    public function getPageCount(string $filePath): int
    {
        $pdf = $this->parser->parseFile($filePath);
        return count($pdf->getPages());
    }
}
