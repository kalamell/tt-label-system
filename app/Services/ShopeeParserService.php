<?php

namespace App\Services;

/**
 * อ่าน PDF Label จาก Shopee (Flash Speed Express, SPX)
 * ใช้ PyMuPDF (Python) แทน smalot/pdfparser เพราะ smalot อ่าน Shopee PDF ไม่ได้
 *
 * โครงสร้าง Shopee Label (text จาก PyMuPDF):
 *   Flash: SPEED\nTH46018GA5JT9D\nผู้ส่ง (FROM) {shop}\n...\nผู้รับ (TO) {name}\n...
 *   SPX:   MP\nTH267145528515S\nผู้ส่ง (FROM) {shop} HOME\n...\nผู้รับ (TO) {name}\n...
 */
class ShopeeParserService
{
    /**
     * อ่าน PDF ทั้งไฟล์ แยกข้อมูลทีละหน้า
     *
     * @return array<int, array> array ของ parsed orders
     */
    public function parseFile(string $filePath): array
    {
        $pageTexts = $this->extractPageTexts($filePath);

        if (empty($pageTexts)) {
            return [];
        }

        $orders = [];
        foreach ($pageTexts as $pageNum => $text) {
            $parsed = $this->parseLabelText($text);
            if ($parsed && !empty($parsed['tracking_number'])) {
                $parsed['page_number'] = (int) $pageNum;
                $orders[] = $parsed;
            }
        }

        return $orders;
    }

    /**
     * อ่านจาก UploadedFile
     */
    public function parseUploadedFile(\Illuminate\Http\UploadedFile $file): array
    {
        $path     = $file->store('uploads/originals', 'public_files');
        $fullPath = public_path($path);

        return $this->parseFile($fullPath);
    }

    // ============================================================
    // Python text extraction
    // ============================================================

    /**
     * เรียก Python script extract_page_texts.py คืน { "1": "text", "2": "text", ... }
     */
    protected function extractPageTexts(string $filePath): array
    {
        $python3 = $this->findPython3();
        if (!$python3) {
            return [];
        }

        $script = base_path('scripts/extract_page_texts.py');
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

    // ============================================================
    // Label Parser
    // ============================================================

    /**
     * แยกข้อมูลจาก text ของ 1 หน้า (Shopee label)
     * text มาจาก PyMuPDF dict mode + PUA normalization (ภาษาไทยถูกต้อง)
     */
    protected function parseLabelText(string $text): ?array
    {
        $data = [
            'platform'           => 'SHOPEE',
            'carrier'            => null,
            'service_type'       => null,
            'tracking_number'    => null,
            'order_id'           => null,
            'sorting_code'       => null,
            'sorting_code_2'     => null,
            'route_code'         => null,
            'sender_name'        => null,
            'sender_address'     => null,
            'recipient_name'     => null,
            'recipient_phone'    => null,
            'recipient_address'  => null,
            'recipient_district' => null,
            'recipient_province' => null,
            'recipient_zipcode'  => null,
            'payment_type'       => 'PREPAID',
            'delivery_type'      => 'DROP-OFF',
            'shipping_date'      => null,
            'product_name'       => null,
            'product_sku'        => null,
            'seller_sku'         => null,
            'quantity'           => 1,
            'item_quantities'    => null,
        ];

        // ============================================================
        // Carrier Detection
        // Flash Speed Express มีคำว่า SPEED ใน label
        // SPX มีคำว่า HOME (SPX Home delivery) ใน sender line
        // ============================================================
        if (str_contains($text, 'SPEED') || str_contains($text, 'FLASH')) {
            $data['carrier'] = 'FLASH';
        } elseif (str_contains($text, 'HOME') || str_contains($text, 'SPX')) {
            $data['carrier'] = 'SPX';
        }

        // ============================================================
        // Tracking Number — TH + 12-13 alphanumeric
        // Flash:  TH46018GA5JT9D  (TH + 12 chars)
        // SPX:    TH267145528515S  (TH + 13 chars)
        // ============================================================
        if (preg_match('/\b(TH[A-Z0-9]{12,13})\b/', $text, $m)) {
            $data['tracking_number'] = $m[1];
        }

        // ============================================================
        // Order ID — "Shopee Order No. XXXXXXXXX"
        // ============================================================
        if (preg_match('/Shopee\s+Order\s+No\.?\s*([A-Z0-9]{10,20})/i', $text, $m)) {
            $data['order_id'] = $m[1];
        }

        // ============================================================
        // Sorting Code 1
        // Flash: "17NE-15028-07"  (digits + 2+ letters + -digits-digits)
        // SPX:   "B2-(HOU.4)"     (letter + digit + -(LETTERS.digit))
        // ============================================================
        if (preg_match('/\b(\d+[A-Z]{2,}-\d+-\d+)\b/', $text, $m)) {
            // Flash format: 17NE-15028-07
            $data['sorting_code'] = $m[1];
        } elseif (preg_match('/\b([A-Z]\d+-\([A-Z]+\.\d+\))/', $text, $m)) {
            // SPX format: B2-(HOU.4)
            $data['sorting_code'] = $m[1];
        }

        // ============================================================
        // Sorting Code 2 — Flash: "J02" (letter + 2 digits)
        // ============================================================
        if (preg_match('/\b([A-Z]\d{2})\b/', $text, $m)) {
            $data['sorting_code_2'] = $m[1];
        }

        // ============================================================
        // Zone / Route Code
        // Flash: "BSY_SP- บางทรายใหญ่"  (CODE_XX- thaitext)
        // SPX:   "ASRCH-D - ศรีราชา"   (CODE-X - thaitext)
        // ต้องจบด้วย Thai chars ([\p{Thai}]+) ไม่ใช่ Latin ป้องกัน false match
        // ============================================================
        if (preg_match('/([A-Z][A-Z0-9]*_[A-Z]+-\s*[\p{Thai}]+)/u', $text, $m)) {
            $data['route_code'] = trim($m[1]);
        } elseif (preg_match('/([A-Z]{2,}-[A-Z]\s+-\s+[\p{Thai}]+)/u', $text, $m)) {
            $data['route_code'] = trim($m[1]);
        }

        // ============================================================
        // Recipient Name — อยู่บรรทัดเดียวกับ "ผู้รับ (TO) {ชื่อ}"
        // ============================================================
        if (preg_match('/ผู้รับ\s*\(TO\)\s+(.+)/u', $text, $m)) {
            $data['recipient_name'] = trim($m[1]);
        }

        // ============================================================
        // Sender Name — อยู่บรรทัดเดียวกับ "ผู้ส่ง (FROM) {ชื่อร้าน}"
        // ============================================================
        if (preg_match('/ผู้ส่ง\s*\(FROM\)\s+(.+)/u', $text, $m)) {
            // ตัด HOME / SPEED ออกจากท้าย (service type ที่ติดมา)
            $senderLine = preg_replace('/\s+(HOME|SPEED|SPX|FLASH)\s*$/u', '', trim($m[1]));
            $data['sender_name'] = trim($senderLine);
        }

        // ============================================================
        // Recipient Address
        // Flash: "ที่อยู่ {address}" — zipcode อยู่บรรทัดถัดไปแยก
        // SPX:   "เลขที่ {address with zipcode at end}"
        // ============================================================
        if (preg_match('/ที่อยู่\s+(.+)/u', $text, $m)) {
            $data['recipient_address'] = trim($m[1]);
            // zipcode บรรทัดถัดไปจาก ที่อยู่ (Flash)
            if (preg_match('/ที่อยู่.+\n(\d{5})\b/su', $text, $m2)) {
                $data['recipient_zipcode'] = $m2[1];
            }
        } elseif (preg_match('/(?:เลขที่|บ้านเลขที่)\s+(.+)/u', $text, $m)) {
            $addrLine = trim($m[1]);
            $data['recipient_address'] = $addrLine;
            // zipcode ปิดท้ายใน address line (SPX)
            if (preg_match('/(\d{5})\s*$/u', $addrLine, $m2)) {
                $data['recipient_zipcode'] = $m2[1];
            }
        }

        // ============================================================
        // Zipcode fallback (ถ้ายังไม่ได้)
        // ============================================================
        if (empty($data['recipient_zipcode']) && preg_match('/\b([1-9]\d{4})\b/', $text, $m)) {
            $data['recipient_zipcode'] = $m[1];
        }

        // ============================================================
        // District + Province จาก address string (comma-separated)
        // ============================================================
        if (!empty($data['recipient_address'])) {
            [$district, $province, $zip] = $this->extractAddressComponents($data['recipient_address']);
            if ($district) $data['recipient_district'] = $district;
            if ($province) $data['recipient_province'] = $province;
            if ($zip && empty($data['recipient_zipcode'])) $data['recipient_zipcode'] = $zip;
        }

        // ============================================================
        // เบอร์โทร (format เดียวกับ TikTok)
        // ============================================================
        if (preg_match('/\(\+66\)(\d[\d\*]+)/', $text, $m)) {
            $data['recipient_phone'] = '(+66)' . $m[1];
        }

        // ============================================================
        // ประเภทการจ่ายเงิน
        // ============================================================
        if (str_contains($text, 'เก็บเงินปลายทาง') || preg_match('/\bCOD\b/', $text)) {
            $data['payment_type'] = 'COD';
        }
        // ไม่ต้องเก็บเงิน → PREPAID (default แล้ว ไม่ต้อง else)

        // ============================================================
        // ประเภทการจัดส่ง
        // ============================================================
        if (str_contains($text, 'DROP')) {
            $data['delivery_type'] = 'DROP-OFF';
        } elseif (str_contains($text, 'PICKUP') && !str_contains($text, 'PICKUP DATE')) {
            $data['delivery_type'] = 'PICKUP';
        }

        // ============================================================
        // Shipping Date — "SHIP BY DATE DD-MM-YYYY"
        // ============================================================
        if (preg_match('/SHIP\s+BY\s+DATE\s+(\d{2}-\d{2}-\d{4})/i', $text, $m)) {
            $data['shipping_date'] = $m[1];
        }

        // ============================================================
        // Quantity — "จำนวนรวม N" (ท้ายหน้า)
        // ============================================================
        if (preg_match('/นวนรวม\s+(\d+)/u', $text, $m)) {
            $data['quantity'] = (int) $m[1];
        }

        // ============================================================
        // Product Table — ระหว่าง "ชื่อสินค้า" header กับ "Shopee Order No."
        // extract_page_texts.py เรียง spans ตาม X แล้ว join ด้วย space
        // ดังนั้นแต่ละบรรทัดคือ spans ทุก column รวมกัน
        // ============================================================
        if (preg_match('/ชื่อสินค้า[^\n]*\n(.*?)(?=Shopee\s+Order\s+No)/su', $text, $m)) {
            $prodSection = trim($m[1]);

            // กรองเอาเฉพาะบรรทัดที่มีข้อความ (ไม่ใช่แค่ตัวเลข row# + qty)
            $prodLines = array_values(array_filter(
                array_map('trim', explode("\n", $prodSection)),
                fn($l) => $l !== '' && !preg_match('/^\d+(\s+\d+)?$/', $l)
            ));

            if (!empty($prodLines)) {
                $data['product_name'] = $prodLines[0];

                // Seller SKU logic:
                //   token แรกเป็นตัวอักษรล้วน AND < 4 ตัว → เอา token ถัดไปมาต่อ (ไม่มี space)
                //   เช่น "1 LH 50 ชิ้น..."   → "LH"(2) < 4 → "LH" + "50" = "LH50"
                //        "1 ABCD 4 ชิ้น..."  → "ABCD"(4) ≥ 4 → stop → "ABCD"
                //        "1 HCG10 ยาทา..."   → "HCG10" มีตัวเลข → stop → "HCG10"
                $lineForSku = preg_replace('/^\d+\s+/', '', $prodLines[0]);
                $tokens     = preg_split('/\s+/', trim($lineForSku));
                $sku        = $tokens[0] ?? '';
                if (preg_match('/^[A-Za-z]+$/', $sku) && mb_strlen($sku) < 4 && isset($tokens[1])) {
                    $sku .= $tokens[1];
                }
                $sku = trim($sku);
                if ($sku !== '' && mb_strlen($sku) <= 20) {
                    $data['seller_sku'] = $sku;
                }
            }
        }

        return $data;
    }

    /**
     * แยก district / province / zipcode จาก address string (comma-separated)
     * รองรับทั้ง format ที่มีและไม่มี zipcode ท้าย
     *
     * @return array [district, province, zipcode]
     */
    protected function extractAddressComponents(string $address): array
    {
        $parts = array_map('trim', explode(',', $address));
        $count = count($parts);
        if ($count < 2) return [null, null, null];

        $lastPart = $parts[$count - 1];
        $zipcode  = null;
        $province = null;

        // ท้าย: "จังหวัดXXX 20110" หรือ "จังหวัดXXX"
        if (preg_match('/([\p{Thai}\s]+?)\s+(\d{5})\s*$/u', $lastPart, $m)) {
            $province = trim($m[1]);
            $zipcode  = $m[2];
        } else {
            $province = trim($lastPart);
        }

        // ก่อนท้าย: district
        $district = null;
        if ($count >= 3) {
            $prev = trim($parts[$count - 2]);
            if (mb_strlen($prev) <= 60 && preg_match('/[\p{Thai}]/u', $prev)) {
                $district = $prev;
            }
        }

        return [$district, $province, $zipcode];
    }
}
