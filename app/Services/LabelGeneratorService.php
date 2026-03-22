<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

/**
 * สร้าง PDF Label — ซ่อนเฉพาะส่วน Product Name, รักษาส่วนอื่น 100%
 *
 * Strategy (ลองตามลำดับ):
 *   1. Pre-rendered PNG — render ตอน upload ไว้ล่วงหน้า (ไม่ต้องการ gs ตอนพิมพ์)
 *                          ✓ เร็วที่สุด, ใช้ได้กับทุก PDF format
 *   2. FPDI overlay     — อ่าน original PDF vector → ทับส่วน product
 *                          ✗ ไม่ทำงานถ้า PDF ใช้ cross-reference streams (PDF 1.5+)
 *   3. Imagick overlay  — render PDF เป็นภาพ → วาดทับ → ห่อด้วย TCPDF
 *                          ✓ ใช้ได้กับทุก PDF format (ต้องการ imagick + ghostscript)
 *   4. DomPDF template  — สร้าง label ใหม่จากข้อมูลใน database
 *                          ✓ ใช้ได้เสมอ แต่ layout อาจต่างจาก original เล็กน้อย
 */
class LabelGeneratorService
{
    // DPI สำหรับ render PNG (150 = พอสำหรับพิมพ์ A6, เร็วกว่า 200 ~40%)
    private const IMAGICK_DPI = 150;

    // Cache สำหรับ Python path และ PyMuPDF availability
    private ?string $python3Path    = null;
    private ?bool   $hasPymuPdfCache = null;

    // Cache สำหรับ font paths
    private ?string $fontRegular = null;
    private ?string $fontBold    = null;

    // ============================================================
    // Upload-time PNG Rendering
    // ============================================================

    /**
     * Render PDF แต่ละหน้าเป็น PNG ไว้ล่วงหน้า — เรียกตอน upload
     * เก็บที่ public/uploads/pages/{tracking_number}.png
     * ทำงานแบบ best-effort: ถ้า render ไม่ได้ ก็ข้ามไป (จะใช้ fallback ตอนพิมพ์)
     */
    public function renderPagesFromPdf(string $pdfPath, Collection $orders): void
    {
        if (!file_exists($pdfPath)) return;

        $pagesDir = public_path('uploads/pages');
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0755, true);
        }

        // สร้าง .htaccess ป้องกัน direct access
        $htaccess = $pagesDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        // กรองเฉพาะ order ที่ยังไม่มี PNG
        $missing = $orders->filter(fn($o) =>
            $o->tracking_number
            && $o->pdf_page_number
            && !file_exists($pagesDir . '/' . $o->tracking_number . '.png')
        );

        if ($missing->isEmpty()) return;

        // ขยาย execution time สำหรับงาน render หนัก
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        // ลอง Python+PyMuPDF ก่อน (ไม่ต้องการ Ghostscript, เร็ว, คุณภาพดี)
        if ($this->renderAllPagesWithPython($pdfPath, $missing, $pagesDir)) {
            // Python render สำเร็จ (อาจ render ไม่ครบทุก page — ไม่เป็นไร)
            // ตรวจ missing ที่เหลือ ถ้ายังไม่มี PNG จะ fallback ด้านล่าง
        }

        // กรอง missing ที่ยังไม่มี PNG (Python อาจ fail บาง page)
        $stillMissing = $missing->filter(fn($o) =>
            !file_exists($pagesDir . '/' . $o->tracking_number . '.png')
        );

        if ($stillMissing->isNotEmpty()) {
            // fallback: pdftoppm + gs สำหรับ page ที่ยังไม่มี PNG
            $tempDir = sys_get_temp_dir() . '/tt_pages_' . uniqid();
            @mkdir($tempDir, 0755, true);

            $renderedMap = [];

            if ($this->renderAllPagesWithPdftoppm($pdfPath, $tempDir)) {
                foreach (glob($tempDir . '/*.png') ?: [] as $file) {
                    if (preg_match('/page-0*(\d+)\.png$/i', basename($file), $m)) {
                        $renderedMap[(int)$m[1]] = $file;
                    }
                }
            }

            if (empty($renderedMap) && $this->renderAllPagesWithGs($pdfPath, $tempDir)) {
                foreach (glob($tempDir . '/*.png') ?: [] as $file) {
                    if (preg_match('/page_0*(\d+)\.png$/i', basename($file), $m)) {
                        $renderedMap[(int)$m[1]] = $file;
                    }
                }
            }

            if (!empty($renderedMap)) {
                foreach ($stillMissing as $order) {
                    if (isset($renderedMap[$order->pdf_page_number])) {
                        rename($renderedMap[$order->pdf_page_number],
                               $pagesDir . '/' . $order->tracking_number . '.png');
                    }
                }
            } else {
                // last resort: render ทีละหน้าด้วย Python (ช้ากว่า batch แต่ลอง)
                foreach ($stillMissing as $order) {
                    $outPath = $pagesDir . '/' . $order->tracking_number . '.png';
                    $this->renderPageToPng($pdfPath, $order->pdf_page_number, $outPath);
                }
            }

            foreach (glob($tempDir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($tempDir);
        }
    }

    // ============================================================
    // Font Detection (cross-platform: macOS + Linux)
    // ============================================================

    /**
     * วาดกล่องสีแดง + Seller SKU + Qty ใหญ่ ทับ product area ของ Shopee label
     * footer ต้นฉบับ (Shopee Order No. + จำนวนรวม) ยังมองเห็น
     */
    protected function drawShopeeProductOverlay(\Imagick $img, Order $order): void
    {
        $width  = $img->getImageWidth();
        $height = $img->getImageHeight();

        $sellerSku = trim($order->seller_sku ?? $order->product_sku ?? '');
        $qty       = $order->quantity ?? 1;
        $fontBold  = $this->findFont(true);

        // ===== กล่องขาว: เริ่มหลัง column header row (y≈52-53%) ก่อน data row (y≈54%) =====
        // pixel analysis: header row=52%, data row=54% (อาจ wrap 2 บรรทัด ≈56-60%), footer=93%
        // y1=54% → เริ่มทับ data row พอดี (column header "# ชื่อสินค้า..." ยังมองเห็น)
        // y2=92% → ก่อน footer border ที่ 93%
        $y1          = (int)($height * 0.530);
        $y2          = (int)($height * 0.905);
        $boxH        = $y2 - $y1;
        $leftMargin  = (int)($width * 0.035);   // ซ้าย
        $rightMargin = (int)($width * 0.045);   // ขวา — เข้ามาเพิ่มให้พอดีกรอบตาราง

        // fill ขาว
        $whiteBox = new \ImagickDraw();
        $whiteBox->setFillColor(new \ImagickPixel('#ffffff'));
        $whiteBox->setStrokeWidth(0);
        $whiteBox->rectangle($leftMargin, $y1, $width - $rightMargin, $y2);
        $img->drawImage($whiteBox);


        // ===== SKU + Qty — บรรทัดเดียวกัน ด้านบนกล่อง =====
        $fontSize = max(24, (int)($boxH * 0.13));           // 13% ของ boxH
        $textY    = $y1 + (int)($fontSize * 1.3);           // baseline ≈ 1 line จาก top

        // SKU ซ้าย
        $skuText = mb_substr(trim($sellerSku !== '' ? $sellerSku : '-'), 0, 12);
        $skuDraw = new \ImagickDraw();
        $skuDraw->setFillColor(new \ImagickPixel('#000000'));
        $skuDraw->setGravity(\Imagick::GRAVITY_NORTHWEST);
        if ($fontBold) $skuDraw->setFont($fontBold);
        $skuDraw->setFontSize($fontSize);
        $skuDraw->setTextAlignment(\Imagick::ALIGN_LEFT);
        $img->annotateImage($skuDraw, $leftMargin + (int)($width * 0.020), $textY, 0, $skuText);

        // Qty ขวา — ชิดกรอบขวา (เผื่อ ~1 ตัวอักษร padding)
        $qtyDraw = new \ImagickDraw();
        $qtyDraw->setFillColor(new \ImagickPixel('#000000'));
        $qtyDraw->setGravity(\Imagick::GRAVITY_NORTHWEST);
        if ($fontBold) $qtyDraw->setFont($fontBold);
        $qtyDraw->setFontSize($fontSize);
        $qtyDraw->setTextAlignment(\Imagick::ALIGN_LEFT);
        $qtyX = $width - $rightMargin - (int)($fontSize * 0.9);  // ชิดกรอบขวา
        $img->annotateImage($qtyDraw, $qtyX, $textY, 0, (string)$qty);
    }

    /**
     * หา font file ที่ใช้ได้จริงบน OS ปัจจุบัน
     * Imagick ต้องการ absolute path บน macOS (ไม่รับ font name เหมือน Linux)
     */
    protected function findFont(bool $bold = false): string
    {
        if ($bold && $this->fontBold !== null)    return $this->fontBold;
        if (!$bold && $this->fontRegular !== null) return $this->fontRegular;

        $candidates = $bold ? [
            // macOS
            '/System/Library/Fonts/HelveticaNeue.ttc',
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/ArialHB.ttc',
            '/Library/Fonts/Arial Bold.ttf',
            // Linux — Liberation, FreeSans, DejaVu (ติดตั้งมาส่วนใหญ่)
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu-sans/DejaVuSans-Bold.ttf',
        ] : [
            // macOS
            '/System/Library/Fonts/HelveticaNeue.ttc',
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/ArialHB.ttc',
            '/Library/Fonts/Arial.ttf',
            // Linux
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu-sans/DejaVuSans.ttf',
        ];

        $found = '';
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $found = $path;
                break;
            }
        }

        if ($bold) $this->fontBold    = $found;
        else       $this->fontRegular = $found;

        return $found;
    }

    // ============================================================
    // Python + PyMuPDF Rendering (ไม่ต้องการ Ghostscript)
    // ============================================================

    /**
     * หา Python3 binary — cache ผลไว้
     */
    protected function findPython3(): ?string
    {
        if ($this->python3Path !== null) {
            return $this->python3Path ?: null;
        }

        if (!function_exists('exec')) {
            $this->python3Path = '';
            return null;
        }

        $paths = [
            '/usr/bin/python3', '/usr/local/bin/python3',
            '/opt/homebrew/bin/python3', 'python3',
        ];

        foreach ($paths as $path) {
            exec(escapeshellarg($path) . ' --version 2>/dev/null', $out, $code);
            if ($code === 0) {
                $this->python3Path = $path;
                return $path;
            }
            $out = [];
        }

        $this->python3Path = '';
        return null;
    }

    /**
     * Check ว่า PyMuPDF (fitz) ติดตั้งอยู่หรือเปล่า — cache ผลไว้
     */
    protected function hasPymuPdf(): bool
    {
        if ($this->hasPymuPdfCache !== null) {
            return $this->hasPymuPdfCache;
        }

        $python = $this->findPython3();
        if (!$python) {
            return $this->hasPymuPdfCache = false;
        }

        exec(escapeshellarg($python) . " -c 'import fitz' 2>/dev/null", $out, $code);
        return $this->hasPymuPdfCache = ($code === 0);
    }

    /**
     * Render หลายหน้าด้วย Python+PyMuPDF — ไม่ต้องการ Ghostscript
     * เขียน job JSON → เรียก scripts/render_pdf.py → ตรวจสอบผล
     */
    protected function renderAllPagesWithPython(string $pdfPath, Collection $orders, string $pagesDir): bool
    {
        if (!$this->hasPymuPdf()) return false;

        $scriptPath = base_path('scripts/render_pdf.py');
        if (!file_exists($scriptPath)) return false;

        // กรองเฉพาะ order ที่ยังไม่มี PNG
        $toRender = $orders->filter(fn($o) =>
            $o->tracking_number
            && $o->pdf_page_number
            && !file_exists($pagesDir . '/' . $o->tracking_number . '.png')
        );

        if ($toRender->isEmpty()) return true;

        // สร้าง job JSON
        $job = [
            'pdf'        => $pdfPath,
            'output_dir' => $pagesDir,
            'dpi'        => self::IMAGICK_DPI,
            'pages'      => $toRender
                ->mapWithKeys(fn($o) => [$o->pdf_page_number => $o->tracking_number])
                ->toArray(),
        ];

        $jobFile = sys_get_temp_dir() . '/tt_job_' . uniqid() . '.json';
        file_put_contents($jobFile, json_encode($job));

        $safePy     = escapeshellarg($this->findPython3());
        $safeScript = escapeshellarg($scriptPath);
        $safeJob    = escapeshellarg($jobFile);

        exec("{$safePy} {$safeScript} {$safeJob} 2>/dev/null", $out, $code);

        if (file_exists($jobFile)) @unlink($jobFile);

        // ตรวจสอบว่ามี PNG ถูกสร้างขึ้นมาบ้างไหม
        return $toRender->filter(fn($o) =>
            file_exists($pagesDir . '/' . $o->tracking_number . '.png')
        )->isNotEmpty();
    }

    /**
     * Render หน้าเดียวด้วย Python+PyMuPDF
     */
    protected function renderSinglePageWithPython(string $pdfPath, int $pageNumber, string $outputPath): bool
    {
        if (!$this->hasPymuPdf()) return false;

        $scriptPath = base_path('scripts/render_pdf.py');
        if (!file_exists($scriptPath)) return false;

        // ใช้ temp tracking เพื่อให้ script เขียนลง tempDir ก่อน แล้วค่อย rename
        $tempTracking = 'tmp_' . uniqid();
        $tempDir      = sys_get_temp_dir();

        $job = [
            'pdf'        => $pdfPath,
            'output_dir' => $tempDir,
            'dpi'        => self::IMAGICK_DPI,
            'pages'      => [$pageNumber => $tempTracking],
        ];

        $jobFile = $tempDir . '/tt_job_' . uniqid() . '.json';
        file_put_contents($jobFile, json_encode($job));

        $safePy     = escapeshellarg($this->findPython3());
        $safeScript = escapeshellarg($scriptPath);
        $safeJob    = escapeshellarg($jobFile);

        exec("{$safePy} {$safeScript} {$safeJob} 2>/dev/null", $out, $code);

        if (file_exists($jobFile)) @unlink($jobFile);

        $tempOutput = $tempDir . '/' . $tempTracking . '.png';
        if (file_exists($tempOutput)) {
            rename($tempOutput, $outputPath);
            return true;
        }

        return false;
    }

    /**
     * Batch render ทั้งไฟล์ด้วย pdftoppm (poppler-utils)
     * สร้างไฟล์ page-1.png, page-2.png, ... หรือ page-000001.png, ...
     * pdftoppm มักมีอยู่แล้วใน Linux shared hosting โดยไม่ต้องติดตั้งเพิ่ม
     */
    protected function renderAllPagesWithPdftoppm(string $pdfPath, string $outputDir): bool
    {
        if (!function_exists('exec')) return false;

        $bins = [
            '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm',
            '/opt/local/bin/pdftoppm', '/opt/homebrew/bin/pdftoppm', 'pdftoppm',
        ];

        $safePdf    = escapeshellarg($pdfPath);
        $safePrefix = escapeshellarg($outputDir . '/page');
        $dpi        = self::IMAGICK_DPI;

        foreach ($bins as $bin) {
            $safeBin = escapeshellarg($bin);
            $cmd = "{$safeBin} -r {$dpi} -png {$safePdf} {$safePrefix} 2>/dev/null";

            exec($cmd, $out, $code);
            if ($code === 0 && count(glob($outputDir . '/*.png') ?: []) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render หน้าเดียวด้วย pdftoppm
     * pdftoppm ตั้งชื่อ output: {prefix}-{pageNum}.png หรือ {prefix}-{zeroPadded}.png
     */
    protected function renderWithPdftoppm(string $pdfPath, int $pageNumber, string $outputPath): bool
    {
        if (!function_exists('exec')) return false;

        $bins = [
            '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm',
            '/opt/local/bin/pdftoppm', '/opt/homebrew/bin/pdftoppm', 'pdftoppm',
        ];

        $tempPrefix = sys_get_temp_dir() . '/tt_' . uniqid() . '_p';
        $safePdf    = escapeshellarg($pdfPath);
        $safePrefix = escapeshellarg($tempPrefix);
        $dpi        = self::IMAGICK_DPI;

        foreach ($bins as $bin) {
            $safeBin = escapeshellarg($bin);
            $cmd = "{$safeBin} -r {$dpi} -f {$pageNumber} -l {$pageNumber}"
                 . " -png {$safePdf} {$safePrefix} 2>/dev/null";

            exec($cmd, $out, $code);
            if ($code === 0) {
                // pdftoppm อาจสร้าง prefix-1.png หรือ prefix-000001.png
                $files = glob($tempPrefix . '-*.png') ?: [];
                if (!empty($files)) {
                    rename($files[0], $outputPath);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Batch render ทั้งไฟล์ด้วย Ghostscript ใน 1 call
     * สร้างไฟล์ page_0001.png, page_0002.png, ... ใน $outputDir
     */
    protected function renderAllPagesWithGs(string $pdfPath, string $outputDir): bool
    {
        if (!function_exists('exec')) return false;

        $gsPaths = [
            '/usr/bin/gs', '/usr/local/bin/gs', '/opt/local/bin/gs',
            '/opt/homebrew/bin/gs', '/usr/local/share/ghostscript/bin/gs', 'gs',
        ];

        $safePdf    = escapeshellarg($pdfPath);
        $safeOutput = escapeshellarg($outputDir . '/page_%04d.png');
        $dpi        = self::IMAGICK_DPI;

        foreach ($gsPaths as $gs) {
            $safeGs = escapeshellarg($gs);
            $cmd = "{$safeGs} -dBATCH -dNOPAUSE -dSAFER -sDEVICE=png16m"
                 . " -r{$dpi} -sOutputFile={$safeOutput} {$safePdf} 2>/dev/null";

            exec($cmd, $out, $code);
            if ($code === 0 && count(glob($outputDir . '/*.png') ?: []) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render หน้า PDF หน้าเดียวเป็น PNG
     * ลองตามลำดับ: Python+PyMuPDF → pdftoppm → gs → Imagick
     */
    protected function renderPageToPng(string $pdfPath, int $pageNumber, string $outputPath): bool
    {
        if ($this->renderSinglePageWithPython($pdfPath, $pageNumber, $outputPath)) {
            return true;
        }

        if ($this->renderWithPdftoppm($pdfPath, $pageNumber, $outputPath)) {
            return true;
        }

        if ($this->renderWithGhostscript($pdfPath, $pageNumber, $outputPath)) {
            return true;
        }

        if (extension_loaded('imagick')) {
            $this->fixGhostscriptPath();
            try {
                $img = new \Imagick();
                $img->setResolution(self::IMAGICK_DPI, self::IMAGICK_DPI);
                $img->readImage($pdfPath . '[' . ($pageNumber - 1) . ']');
                $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $img->setImageBackgroundColor('white');
                $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $img->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                $img->setImageFormat('png');
                $img->writeImage($outputPath);
                $img->destroy();
                return true;
            } catch (\ImagickException $e) {
                // gs ไม่พบ
            }
        }

        return false;
    }

    /**
     * Render หน้าเดียวด้วย gs exec()
     */
    protected function renderWithGhostscript(string $pdfPath, int $pageNumber, string $outputPath): bool
    {
        if (!function_exists('exec')) return false;

        $gsPaths = [
            '/usr/bin/gs', '/usr/local/bin/gs', '/opt/local/bin/gs',
            '/opt/homebrew/bin/gs', '/usr/local/share/ghostscript/bin/gs', 'gs',
        ];

        $safePdf = escapeshellarg($pdfPath);
        $safeOut = escapeshellarg($outputPath);
        $dpi     = self::IMAGICK_DPI;

        foreach ($gsPaths as $gs) {
            $safeGs = escapeshellarg($gs);
            $cmd = "{$safeGs} -dBATCH -dNOPAUSE -dSAFER -sDEVICE=png16m"
                 . " -r{$dpi} -dFirstPage={$pageNumber} -dLastPage={$pageNumber}"
                 . " -sOutputFile={$safeOut} {$safePdf} 2>/dev/null";

            exec($cmd, $out, $code);
            if ($code === 0 && file_exists($outputPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * สร้าง PDF label จาก pre-rendered PNG (ไม่ต้องการ Ghostscript!)
     */
    protected function generateFromPng(string $pngPath, Order $order, string $outputPath): void
    {
        $img = new \Imagick($pngPath);
        $img->setImageFormat('png');
        $this->drawProductOverlayOnImage($img, $order);
        $this->saveImageAsPdf($img, $outputPath);
        $img->destroy();
    }

    // ============================================================
    // Public API
    // ============================================================

    /**
     * พิมพ์ Label เดี่ยว
     * Priority: PNG (pre-rendered) → PDF overlay (FPDI/Imagick) → DomPDF template
     */
    public function generateSingleLabel(Order $order): string
    {
        $filename = "label_{$order->tracking_number}.pdf";
        $path     = public_path("labels/{$filename}");
        @mkdir(public_path('labels'), 0755, true);

        $pngPath      = public_path("uploads/pages/{$order->tracking_number}.png");
        $originalPath = $order->original_pdf_path
            ? public_path($order->original_pdf_path)
            : null;

        if (file_exists($pngPath) && extension_loaded('imagick')) {
            $this->generateFromPng($pngPath, $order, $path);
        } elseif ($originalPath && file_exists($originalPath) && $order->pdf_page_number) {
            $this->overlayProductSection($originalPath, $order->pdf_page_number, $order, $path);
        } else {
            $this->generateWithTemplate($order, $path);
        }

        $order->update([
            'clean_pdf_path' => "labels/{$filename}",
            'label_printed'  => true,
            'printed_at'     => now(),
            'status'         => 'printed',
        ]);

        return $path;
    }

    /**
     * พิมพ์ Label หลายรายการ (Batch) — รวมเป็นไฟล์เดียว
     *
     * Strategy ใหม่ (เร็วขึ้น):
     *   1. Generate individual label ต่อ order (มี cache ที่ clean_pdf_path)
     *   2. Merge PDF ที่ cache ไว้ด้วย FPDI (เร็วมาก, ไม่ต้อง Imagick ซ้ำ)
     *   ผลลัพธ์: ครั้งแรก = เดิม, ครั้งต่อไปพิมพ์ซ้ำ = เร็วมาก
     */
    public function generateBatchLabels(Collection $orders): string
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $filename = 'batch_labels_' . now()->format('Ymd_His') . '.pdf';
        @mkdir(public_path('labels'), 0755, true);
        $path = public_path("labels/{$filename}");

        // Step 1: generate individual label สำหรับ order ที่ยังไม่มี cache
        foreach ($orders as $order) {
            $cached = $order->clean_pdf_path
                && file_exists(public_path($order->clean_pdf_path));
            if (!$cached) {
                $this->generateSingleLabel($order);
                $order->refresh();
            }
        }

        // Step 2: รวบรวม PDF paths ที่พร้อมแล้ว
        $pdfPaths = $orders
            ->filter(fn($o) => $o->clean_pdf_path && file_exists(public_path($o->clean_pdf_path)))
            ->map(fn($o) => public_path($o->clean_pdf_path))
            ->values()
            ->all();

        // Step 3: merge หรือ fallback
        if (empty($pdfPaths)) {
            $this->batchWithTemplate($orders, $path);
        } elseif (count($pdfPaths) === 1) {
            copy($pdfPaths[0], $path);
        } else {
            $this->mergePdfChunks($pdfPaths, $path);
        }

        $orders->each(fn($o) => $o->update([
            'label_printed' => true,
            'printed_at'    => now(),
            'status'        => 'printed',
        ]));

        return $path;
    }

    /**
     * Overlay PDF ต้นฉบับทั้งไฟล์ — ใช้กับ printOverlay endpoint
     */
    public function overlayOriginalPdf(string $originalPdfPath, Collection $orders): string
    {
        $filename = 'clean_labels_' . now()->format('Ymd_His') . '.pdf';
        $path     = public_path("labels/{$filename}");

        $strategy = $this->detectStrategy($originalPdfPath);

        if ($strategy === 'fpdi') {
            $this->batchOverlayWithFpdi($originalPdfPath, $orders, $path);
        } elseif ($strategy === 'imagick') {
            $this->batchOverlayWithImagick($originalPdfPath, $orders, $path);
        } else {
            $this->batchWithTemplate($orders, $path);
        }

        return $path;
    }

    // ============================================================
    // PDF Chunk Merge Helper
    // ============================================================

    /**
     * Merge หลาย PDF chunk (ที่ generate จาก TCPDF) เป็นไฟล์เดียว
     * ใช้ FPDI Tcpdf variant — รองรับ PDF ที่ TCPDF สร้าง (PDF 1.4)
     */
    protected function mergePdfChunks(array $chunkPaths, string $outputPath): void
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', [105.13, 148.17]);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        foreach ($chunkPaths as $file) {
            if (!file_exists($file)) continue;
            $pageCount = $pdf->setSourceFile($file);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size  = $pdf->getTemplateSize($tplId);
                $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    // ============================================================
    // Strategy Detection
    // ============================================================

    /** Cache ผล detectStrategy ต่อ path เดียวกัน เพื่อไม่ต้อง test ซ้ำ */
    private array $strategyCache = [];

    /**
     * ทดสอบจริงว่า PDF ไฟล์นี้ใช้ strategy ไหนได้
     * คืนค่า: 'fpdi' | 'imagick' | 'template'
     */
    protected function detectStrategy(string $pdfPath): string
    {
        if (isset($this->strategyCache[$pdfPath])) {
            return $this->strategyCache[$pdfPath];
        }

        // --- ทดสอบ FPDI ---
        try {
            $test = new \setasign\Fpdi\Tcpdf\Fpdi();
            $test->setSourceFile($pdfPath);
            $test->importPage(1);
            return $this->strategyCache[$pdfPath] = 'fpdi';
        } catch (CrossReferenceException $e) {
            // PDF 1.5+ cross-reference streams — FPDI free ไม่รองรับ
        } catch (\Exception $e) {
            // error อื่น (file not found ฯลฯ)
        }

        // --- ทดสอบ Imagick + Ghostscript ---
        if (extension_loaded('imagick')) {
            $this->fixGhostscriptPath();
            try {
                $test = new \Imagick();
                $test->setResolution(72, 72); // low-res เพื่อทดสอบเร็ว
                $test->readImage($pdfPath . '[0]');
                $test->destroy();
                return $this->strategyCache[$pdfPath] = 'imagick';
            } catch (\ImagickException $e) {
                // Ghostscript ไม่พบ หรือ PDF อ่านไม่ได้
            }
        }

        return $this->strategyCache[$pdfPath] = 'template';
    }

    /**
     * เพิ่ม path ของ Ghostscript เข้า PATH เพื่อให้ Imagick เรียก gs ได้
     */
    protected function fixGhostscriptPath(): void
    {
        $commonDirs = [
            '/usr/bin', '/usr/local/bin', '/opt/local/bin',
            '/opt/homebrew/bin', '/usr/local/ghostscript/bin',
            '/usr/local/share/ghostscript/bin',
        ];
        $current = getenv('PATH') ?: '';
        $merged  = implode(':', array_unique([
            ...explode(':', $current),
            ...$commonDirs,
        ]));
        putenv("PATH={$merged}");
    }

    // ============================================================
    // Single Label Overlay (PDF-based)
    // ============================================================

    protected function overlayProductSection(
        string $originalPdfPath,
        int    $pageNumber,
        Order  $order,
        string $outputPath
    ): void {
        $strategy = $this->detectStrategy($originalPdfPath);

        match ($strategy) {
            'fpdi'    => $this->overlayWithFpdi($originalPdfPath, $pageNumber, $order, $outputPath),
            'imagick' => $this->overlayWithImagick($originalPdfPath, $pageNumber, $order, $outputPath),
            default   => $this->generateWithTemplate($order, $outputPath),
        };
    }

    protected function overlayWithFpdi(
        string $originalPdfPath,
        int    $pageNumber,
        Order  $order,
        string $outputPath
    ): void {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->setSourceFile($originalPdfPath);
        $tplId = $pdf->importPage($pageNumber);
        $size  = $pdf->getTemplateSize($tplId);

        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

        $this->writeProductOverlayFpdi($pdf, $size, $order);
        $pdf->Output($outputPath, 'F');
    }

    protected function overlayWithImagick(
        string $originalPdfPath,
        int    $pageNumber,
        Order  $order,
        string $outputPath
    ): void {
        $img = $this->renderPdfPageAsImage($originalPdfPath, $pageNumber);
        $this->drawProductOverlayOnImage($img, $order);
        $this->saveImageAsPdf($img, $outputPath);
        $img->destroy();
    }

    // ============================================================
    // Batch — PNG (เร็วที่สุด, ไม่ต้องการ gs)
    // ============================================================

    /**
     * Batch label จาก pre-rendered PNG — ต้องการ Imagick เท่านั้น (ไม่ต้องการ gs)
     */
    protected function batchWithPng(Collection $orders, string $outputPath): void
    {
        $chunks = $orders->chunk(10);

        if ($chunks->count() === 1) {
            $this->_renderPngChunk($orders, $outputPath);
            return;
        }

        $tempFiles = [];
        foreach ($chunks as $chunk) {
            $tmp = sys_get_temp_dir() . '/tt_chunk_' . uniqid() . '.pdf';
            $this->_renderPngChunk(collect($chunk), $tmp);
            $tempFiles[] = $tmp;
        }

        $this->mergePdfChunks($tempFiles, $outputPath);
        foreach ($tempFiles as $f) @unlink($f);
    }

    protected function _renderPngChunk(Collection $orders, string $outputPath): void
    {
        $pdf = new \TCPDF('P', 'mm', [105.13, 148.17], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        foreach ($orders as $order) {
            $pngPath = public_path("uploads/pages/{$order->tracking_number}.png");
            if (!file_exists($pngPath)) continue;

            $img = new \Imagick($pngPath);
            $img->setImageFormat('png');
            $this->drawProductOverlayOnImage($img, $order);
            $pngBlob = $img->getImageBlob();
            $img->destroy();

            $pdf->AddPage('P', [105.13, 148.17]);
            $pdf->Image('@' . $pngBlob, 0, 0, 105.13, 148.17, 'PNG');
            unset($pngBlob);
        }

        $pdf->Output($outputPath, 'F');
    }

    /**
     * Batch แบบผสม: บาง order มี PNG, บางไม่มี
     */
    protected function batchMixed(Collection $orders, string $outputPath): void
    {
        $chunks = $orders->chunk(10);

        if ($chunks->count() === 1) {
            $this->_renderMixedChunk($orders, $outputPath);
            return;
        }

        $tempFiles = [];
        foreach ($chunks as $chunk) {
            $tmp = sys_get_temp_dir() . '/tt_chunk_' . uniqid() . '.pdf';
            $this->_renderMixedChunk(collect($chunk), $tmp);
            $tempFiles[] = $tmp;
        }

        $this->mergePdfChunks($tempFiles, $outputPath);
        foreach ($tempFiles as $f) @unlink($f);
    }

    protected function _renderMixedChunk(Collection $orders, string $outputPath): void
    {
        $pdf = new \TCPDF('P', 'mm', [105.13, 148.17], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        foreach ($orders as $order) {
            $pngPath = public_path("uploads/pages/{$order->tracking_number}.png");
            $pngBlob = null;

            if (file_exists($pngPath)) {
                $img = new \Imagick($pngPath);
                $img->setImageFormat('png');
                $this->drawProductOverlayOnImage($img, $order);
                $pngBlob = $img->getImageBlob();
                $img->destroy();
            } elseif ($order->original_pdf_path && $order->pdf_page_number) {
                $origPath = public_path($order->original_pdf_path);
                if (file_exists($origPath) && $this->detectStrategy($origPath) === 'imagick') {
                    $img = $this->renderPdfPageAsImage($origPath, $order->pdf_page_number);
                    $this->drawProductOverlayOnImage($img, $order);
                    $pngBlob = $img->getImageBlob();
                    $img->destroy();
                }
            }

            $pdf->AddPage('P', [105.13, 148.17]);
            if ($pngBlob) {
                $pdf->Image('@' . $pngBlob, 0, 0, 105.13, 148.17, 'PNG');
                unset($pngBlob);
            } else {
                $this->writeTemplateFallbackOnPage($pdf, $order);
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    // ============================================================
    // Batch — FPDI
    // ============================================================

    protected function batchWithFpdi(Collection $orders, string $outputPath): void
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        foreach ($orders as $order) {
            $originalPath = public_path($order->original_pdf_path);
            $pdf->setSourceFile($originalPath);
            $tplId = $pdf->importPage($order->pdf_page_number);
            $size  = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
            $this->writeProductOverlayFpdi($pdf, $size, $order);
        }

        $pdf->Output($outputPath, 'F');
    }

    protected function batchOverlayWithFpdi(
        string $originalPdfPath,
        Collection $orders,
        string $outputPath
    ): void {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pageCount = $pdf->setSourceFile($originalPdfPath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $pdf->importPage($i);
            $size  = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            $order = $orders->firstWhere('pdf_page_number', $i) ?? $orders->get($i - 1);
            if ($order) {
                $this->writeProductOverlayFpdi($pdf, $size, $order);
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    // ============================================================
    // Batch — Imagick
    // ============================================================

    protected function batchWithImagick(Collection $orders, string $outputPath): void
    {
        $pages = new \Imagick();

        foreach ($orders as $order) {
            $originalPath = public_path($order->original_pdf_path);
            $img = $this->renderPdfPageAsImage($originalPath, $order->pdf_page_number);
            $this->drawProductOverlayOnImage($img, $order);
            $img->setImageFormat('pdf');
            $pages->addImage($img);
            $img->destroy();
        }

        $pages->writeImages($outputPath, true);
        $pages->destroy();
    }

    protected function batchOverlayWithImagick(
        string $originalPdfPath,
        Collection $orders,
        string $outputPath
    ): void {
        $pages     = new \Imagick();
        $pageCount = $this->getPdfPageCount($originalPdfPath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $img = $this->renderPdfPageAsImage($originalPdfPath, $i);

            $order = $orders->firstWhere('pdf_page_number', $i) ?? $orders->get($i - 1);
            if ($order) {
                $this->drawProductOverlayOnImage($img, $order);
            }

            $img->setImageFormat('pdf');
            $pages->addImage($img);
            $img->destroy();
        }

        $pages->writeImages($outputPath, true);
        $pages->destroy();
    }

    // ============================================================
    // Imagick Helpers
    // ============================================================

    /**
     * Render หน้า PDF เป็น Imagick object (ต้องการ Ghostscript)
     */
    protected function renderPdfPageAsImage(string $pdfPath, int $pageNumber): \Imagick
    {
        $this->fixGhostscriptPath();

        $img = new \Imagick();
        $img->setResolution(self::IMAGICK_DPI, self::IMAGICK_DPI);
        $img->readImage($pdfPath . '[' . ($pageNumber - 1) . ']');

        $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $img->setImageBackgroundColor('white');
        $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $img->setImageColorspace(\Imagick::COLORSPACE_SRGB);
        $img->setImageFormat('png');

        return $img;
    }

    /**
     * ตรวจจับตำแหน่ง Y ของเส้นคั่นเหนือ TikTok footer โดยสแกนจากล่างขึ้นบน
     * Return: Y pixel ของเส้นคั่น, หรือ fallback 88.2% ถ้าหาไม่เจอ
     */
    protected function detectFooterSeparatorY(\Imagick $img): int
    {
        $width    = $img->getImageWidth();
        $height   = $img->getImageHeight();
        $fallback = (int)($height * 0.882);

        // สแกนช่วง 70%–96% ของความสูง (จากล่างขึ้นบน)
        $scanFrom = (int)($height * 0.70);
        $scanTo   = (int)($height * 0.96);

        // Sample 15 จุดต่อแถวเพื่อ performance
        $xStep    = max(1, (int)($width / 15));
        $xSamples = range(0, $width - 1, $xStep);
        $darkThreshold = (int)(count($xSamples) * 0.55);

        for ($y = $scanTo; $y >= $scanFrom; $y--) {
            $darkCount = 0;
            foreach ($xSamples as $x) {
                $c = $img->getImagePixelColor((int)$x, $y)->getColor();
                if ($c['r'] < 80 && $c['g'] < 80 && $c['b'] < 80) {
                    $darkCount++;
                }
            }
            if ($darkCount >= $darkThreshold) {
                return $y;
            }
        }

        return $fallback;
    }

    /**
     * ย้าย TikTok footer ไปชิดล่างสุดของ label
     * Return: Y pixel ใหม่ของเส้นคั่นเหนือ footer (= height - footerHeight)
     */
    protected function pinFooterToBottom(\Imagick $img, int $footerSepY): int
    {
        $width        = $img->getImageWidth();
        $height       = $img->getImageHeight();
        $footerHeight = $height - $footerSepY;

        // Sanity check: footer ต้องสูง 50–25% ของ image
        if ($footerHeight < 50 || $footerHeight > (int)($height * 0.25)) {
            return $footerSepY;
        }

        $targetY = $height - $footerHeight;

        // footer อยู่ล่างสุดแล้ว (within 3px)
        if (abs($footerSepY - $targetY) <= 3) {
            return $targetY;
        }

        // Crop ส่วน footer
        $footerImg = clone $img;
        $footerImg->cropImage($width, $footerHeight, 0, $footerSepY);
        $footerImg->setImagePage($width, $footerHeight, 0, 0);

        // White-out บริเวณ footer เดิม (footerSepY → ล่างสุด)
        $clear = new \ImagickDraw();
        $clear->setFillColor(new \ImagickPixel('#ffffff'));
        $clear->setStrokeWidth(0);
        $clear->rectangle(0, $footerSepY, $width - 1, $height - 1);
        $img->drawImage($clear);

        // วาง footer ชิดล่างสุด
        $img->compositeImage($footerImg, \Imagick::COMPOSITE_OVER, 0, $targetY);
        $footerImg->destroy();

        return $targetY;
    }

    /**
     * วาดกล่องขาว + "-, -" + Lot Number ทับส่วน Product บน Imagick image
     * Footer: ใช้ footer.png วางชิดล่างสุดเสมอ + แสดง Order ID ขวามือ
     */
    protected function drawProductOverlayOnImage(\Imagick $img, Order $order): void
    {
        // Shopee — กล่องสีแดงทับ product area แสดง SKU + Qty ใหญ่
        if (($order->platform ?? 'TIKTOK') === 'SHOPEE') {
            $this->drawShopeeProductOverlay($img, $order);
            return;
        }

        $width  = $img->getImageWidth();
        $height = $img->getImageHeight();

        // ===== 1. โหลด footer.png และคำนวณขนาด (เฉพาะ TikTok — footer.png เป็นของ TikTok) =====
        $footerPngPath = public_path('footer.png');
        $footerImg     = null;
        $footerH       = 0;

        if (($order->platform ?? 'TIKTOK') !== 'SHOPEE' && file_exists($footerPngPath)) {
            $footerImg = new \Imagick($footerPngPath);
            $origW     = $footerImg->getImageWidth();
            $origH     = $footerImg->getImageHeight();
            // Scale กว้างเต็ม label รักษาอัตราส่วน (ไม่บีบ)
            $footerH   = (int)round($origH * $width / $origW);
            $footerImg->resizeImage($width, $footerH, \Imagick::FILTER_LANCZOS, 1);
        }

        // ===== 2. กำหนดขอบ overlay =====
        $y1 = (int)($height * 0.720);   // เริ่มที่ Product Name header (~72%)
        $y2 = $height - $footerH;       // footer อยู่ล่างสุดของ PNG (ที่เดิม)
        $y2 = max($y2, $y1 + 50);

        $qty         = $order->quantity ?? 1;
        $fontRegular = $this->findFont(false);

        // ---------------------------------------------------------------
        // Parse รายการสินค้า (คั่นด้วย " | " สำหรับ order ที่มีหลายรายการ)
        // ---------------------------------------------------------------
        $sellerSkuItems = array_values(array_filter(
            array_map('trim', explode(' | ', $order->seller_sku ?? ''))
        ));
        $rawSkuItems = array_values(array_filter(
            array_map('trim', explode(' | ', $order->product_sku ?? ''))
        ));
        $qtyItems = array_values(array_filter(
            array_map('trim', explode(' | ', $order->item_quantities ?? ''))
        ));

        if (empty($sellerSkuItems)) $sellerSkuItems = [''];
        $itemCount = count($sellerSkuItems);

        // Pad arrays ให้ตรงกับจำนวน seller sku
        while (count($rawSkuItems) < $itemCount) $rawSkuItems[] = '';
        while (count($qtyItems)    < $itemCount) $qtyItems[]    = '';

        // กรอง SKU ที่ไม่ใช่ ASCII (Helvetica ไม่รองรับภาษาไทย)
        $skuItems = array_map(
            fn($s) => mb_detect_encoding($s, 'ASCII', true) ? $s : '',
            $rawSkuItems
        );

        // เรียง items: ไม่มีตัวเลขท้ายก่อน → เรียงตามตัวเลขท้าย (น้อย→มาก) → เรียงตาม prefix ตัวอักษร
        if ($itemCount > 1) {
            $combined = [];
            for ($i = 0; $i < $itemCount; $i++) {
                $combined[] = [
                    'sellerSku' => $sellerSkuItems[$i],
                    'rawSku'    => $rawSkuItems[$i],
                    'qty'       => $qtyItems[$i],
                    'sku'       => $skuItems[$i],
                ];
            }
            usort($combined, function ($a, $b) {
                preg_match('/(\d+)$/', $a['sellerSku'], $aM);
                preg_match('/(\d+)$/', $b['sellerSku'], $bM);
                $aNum = isset($aM[1]) ? (int)$aM[1] : 0;
                $bNum = isset($bM[1]) ? (int)$bM[1] : 0;
                // 1) เรียงตามตัวเลขท้าย SKU (น้อย→มาก)
                if ($aNum !== $bNum) return $aNum - $bNum;
                // 2) เรียง prefix ตัวอักษร
                $aPrefix = isset($aM[0]) ? substr($a['sellerSku'], 0, -strlen($aM[0])) : $a['sellerSku'];
                $bPrefix = isset($bM[0]) ? substr($b['sellerSku'], 0, -strlen($bM[0])) : $b['sellerSku'];
                $cmp = strcasecmp($aPrefix, $bPrefix);
                if ($cmp !== 0) return $cmp;
                // 3) ถ้า SKU เหมือนกัน → คำสั่งซื้อมากไปอยู่ล่าง (qty น้อย→มาก)
                return (int)$a['qty'] - (int)$b['qty'];
            });
            $sellerSkuItems = array_column($combined, 'sellerSku');
            $rawSkuItems    = array_column($combined, 'rawSku');
            $qtyItems       = array_column($combined, 'qty');
            $skuItems       = array_column($combined, 'sku');
        }

        // ---------------------------------------------------------------
        // Font size: auto-shrink ถ้ามีหลายรายการ
        // ---------------------------------------------------------------
        $sectionH = $y2 - $y1;

        $baseFont = max(14, min(20, (int)round($sectionH / 10.0)));

        // auto-shrink (header 30px + Qty Total 25px = 55px overhead)
        $rowsAvailable  = $sectionH - 55;
        $maxFontForRows = ($itemCount > 0) ? (int)($rowsAvailable / ($itemCount * 1.5)) : $baseFont;
        $tableFontSize    = max(13, min($baseFont, $maxFontForRows));
        $qtyTotalFontSize = max(12, (int)($tableFontSize * 0.95));
        $lineH            = (int)($tableFontSize * 1.5);

        // Column x-positions
        $colProductName = (int)($width * 0.019);
        $colSku         = (int)($width * 0.435);
        $colSellerSku   = (int)($width * 0.578);
        $colQty         = (int)($width * 0.888);
        $colQtyTotalLbl = (int)($width * 0.846);

        // Vertical positions
        $headerY    = $y1 + (int)($tableFontSize * 1.2);
        $separatorY = $headerY + (int)($tableFontSize * 0.6);
        $firstDataY = $separatorY + (int)($tableFontSize * 1.1);
        $qtyTotalY  = $y2 - (int)($tableFontSize * 0.8);

        // ===== 3. White-out จาก y1 ถึงล่างสุด =====
        $clear = new \ImagickDraw();
        $clear->setFillColor(new \ImagickPixel('#ffffff'));
        $clear->setStrokeWidth(0);
        $clear->rectangle(0, $y1, $width, $height - 1);
        $img->drawImage($clear);

        // -- เส้นบน --
        $border = new \ImagickDraw();
        $border->setStrokeColor(new \ImagickPixel('#000000'));
        $border->setStrokeWidth(1);
        $border->line(0, $y1, $width, $y1);
        $img->drawImage($border);

        // -- Base draw (header + data rows) --
        $baseDraw = new \ImagickDraw();
        $baseDraw->setFillColor(new \ImagickPixel('#000000'));
        $baseDraw->setGravity(\Imagick::GRAVITY_NORTHWEST);
        if ($fontRegular) $baseDraw->setFont($fontRegular);
        $baseDraw->setFontSize($tableFontSize);
        $baseDraw->setTextAlignment(\Imagick::ALIGN_LEFT);

        // -- Header row --
        $img->annotateImage($baseDraw, $colProductName, $headerY, 0, 'Product Name');
        $img->annotateImage($baseDraw, $colSku,         $headerY, 0, 'SKU');
        $img->annotateImage($baseDraw, $colSellerSku,   $headerY, 0, 'Seller SKU');
        $img->annotateImage($baseDraw, $colQty,         $headerY, 0, 'Qty');

        // -- เส้นใต้ header --
        $line2 = new \ImagickDraw();
        $line2->setStrokeColor(new \ImagickPixel('#cccccc'));
        $line2->setStrokeWidth(1);
        $line2->line(0, $separatorY, $width, $separatorY);
        $img->drawImage($line2);

        // -- Data rows --
        for ($i = 0; $i < $itemCount; $i++) {
            $rowY = $firstDataY + $i * $lineH;
            if ($rowY >= $qtyTotalY - $lineH) break;

            if ($skuItems[$i] !== '') {
                $img->annotateImage($baseDraw, $colSku, $rowY, 0, $skuItems[$i]);
            }
            $img->annotateImage($baseDraw, $colSellerSku, $rowY, 0, $sellerSkuItems[$i]);
            $rowQtyText = $qtyItems[$i] !== '' ? $qtyItems[$i] : (($itemCount === 1) ? (string)$qty : '');
            if ($rowQtyText !== '') {
                $img->annotateImage($baseDraw, $colQty, $rowY, 0, $rowQtyText);
            }
        }

        // -- Qty Total --
        $qtyTotalDraw = new \ImagickDraw();
        $qtyTotalDraw->setFillColor(new \ImagickPixel('#000000'));
        $qtyTotalDraw->setGravity(\Imagick::GRAVITY_NORTHWEST);
        if ($fontRegular) $qtyTotalDraw->setFont($fontRegular);
        $qtyTotalDraw->setFontSize($qtyTotalFontSize);
        $qtyTotalDraw->setTextAlignment(\Imagick::ALIGN_LEFT);
        $img->annotateImage($qtyTotalDraw, $colQtyTotalLbl, $qtyTotalY, 0, "Qty Total: {$qty}");

        // ===== 4. เส้น top-border ของ footer (กรอบ) =====
        // วาดเส้นคั่น ณ $y2 — product overlay ชนพอดีกับเส้นนี้
        $footerTopBorder = new \ImagickDraw();
        $footerTopBorder->setStrokeColor(new \ImagickPixel('#000000'));
        $footerTopBorder->setStrokeWidth(1);
        $footerTopBorder->line(0, $y2, $width, $y2);
        $img->drawImage($footerTopBorder);

        // ===== 4. Composite footer.png ล่างสุด =====
        if ($footerImg) {
            $img->compositeImage($footerImg, \Imagick::COMPOSITE_OVER, 0, $height - $footerH);
            $footerImg->destroy();
        }

        // ===== 5. Order ID ขวามือ: ไม่มี background — วาดข้อความกึ่งกลางแนวตั้ง ชิดขวา =====
        if ($order->order_id && $footerH > 0) {
            $footerTopY    = $height - $footerH;
            $orderFontSize = max(16, (int)($footerH * 0.38));

            // ขยับขึ้นจากกึ่งกลาง: baseline ที่ 46% ของ footer height
            $textBaselineY = $footerTopY + (int)($footerH * 0.46);

            $orderDraw = new \ImagickDraw();
            $orderDraw->setFillColor(new \ImagickPixel('#000000'));
            $orderDraw->setGravity(\Imagick::GRAVITY_NORTHEAST);
            if ($fontRegular) $orderDraw->setFont($fontRegular);
            $orderDraw->setFontSize($orderFontSize);
            $img->annotateImage($orderDraw, 10, $textBaselineY, 0, 'Order ID: ' . $order->order_id);
        }
    }

    /**
     * ห่อ Imagick image เป็น PDF 105.13×148.17mm (A6) ด้วย TCPDF
     */
    protected function saveImageAsPdf(\Imagick $img, string $outputPath): void
    {
        $png = $img->getImageBlob();

        $pdf = new \TCPDF('P', 'mm', [105.13, 148.17], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('P', [105.13, 148.17]);
        $pdf->Image('@' . $png, 0, 0, 105.13, 148.17, 'PNG');
        $pdf->Output($outputPath, 'F');
    }

    protected function getPdfPageCount(string $pdfPath): int
    {
        $img = new \Imagick();
        $img->pingImage($pdfPath);
        $count = $img->getNumberImages();
        $img->destroy();
        return $count;
    }

    // ============================================================
    // DomPDF Template (Fallback)
    // ============================================================

    protected function generateWithTemplate(Order $order, string $outputPath): void
    {
        $view = $order->platform === 'SHOPEE' ? 'labels.shopee-label' : 'labels.shipping-label';
        $pdf  = Pdf::loadView($view, [
            'order'       => $order,
            'hideProduct' => true,
        ]);
        $pdf->setPaper([0, 0, 283.46, 396.85], 'portrait');
        $pdf->save($outputPath);
    }

    protected function batchWithTemplate(Collection $orders, string $outputPath): void
    {
        $pdf = Pdf::loadView('labels.batch-labels', [
            'orders'      => $orders,
            'hideProduct' => true,
        ]);
        $pdf->setPaper([0, 0, 283.46, 396.85], 'portrait');
        $pdf->save($outputPath);
    }

    /**
     * Template fallback สำหรับ batchMixed — เขียนข้อมูลสำคัญลงตรงๆ บน TCPDF page
     */
    protected function writeTemplateFallbackOnPage(\TCPDF $pdf, Order $order): void
    {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(3, 5);
        $pdf->Cell(94, 4, 'TikTok Shop', 0, 0, 'L');
        $pdf->Cell(0, 4, 'J&T Express  EZ', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY(3, 12);
        $pdf->Cell(0, 4, $order->tracking_number, 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(3, 22);
        $pdf->Cell(0, 5, $order->recipient_name, 0, 1);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 4, $order->recipient_phone ?? '', 0, 1);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(0, 3.5,
            ($order->recipient_address ?? '') . ', '
            . ($order->recipient_district ?? '') . ', '
            . ($order->recipient_province ?? '') . ' '
            . ($order->recipient_zipcode ?? ''),
            0, 'L'
        );

        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetXY(3, 105);
        $pdf->Cell(94, 10, $order->assigned_lot ?? '', 0, 0, 'C');
    }

    // ============================================================
    // FPDI Overlay Helper
    // ============================================================

    /**
     * เขียน overlay ส่วน Product ลงบน FPDI instance ที่ AddPage แล้ว
     * ครอบคลุม 78%–96% ของความสูง (18%) เพื่อรองรับ SKU 1–3 แถว
     */
    protected function writeProductOverlayFpdi(
        \setasign\Fpdi\Tcpdf\Fpdi $pdf,
        array $size,
        Order $order
    ): void {
        $productY      = $size['height'] * 0.78;
        $productHeight = $size['height'] * 0.18;
        $lotNumber     = $order->assigned_lot ?? '';
        $qty           = $order->quantity ?? 1;

        // กล่องสีขาว
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, $productY, $size['width'], $productHeight, 'F');

        // Header row
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(3, $productY + 1);
        $pdf->Cell($size['width'] * 0.50, 3, 'Product Name', 0, 0, 'L');
        $pdf->Cell($size['width'] * 0.18, 3, 'SKU',          0, 0, 'L');
        $pdf->Cell($size['width'] * 0.18, 3, 'Seller SKU',   0, 0, 'L');
        $pdf->Cell($size['width'] * 0.12, 3, 'Qty',          0, 1, 'R');

        // เส้นใต้ header
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(3, $productY + 4.5, $size['width'] - 3, $productY + 4.5);

        // Data row: "-, -" + qty
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(3, $productY + 5.5);
        $pdf->Cell($size['width'] * 0.5, 4, '-, -', 0, 0, 'L');
        $pdf->Cell($size['width'] * 0.46, 4, (string)$qty, 0, 0, 'R');

        // Lot Number ขนาดใหญ่ตรงกลาง
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetXY(3, $productY + 4);
        $pdf->Cell($size['width'] - 6, 10, $lotNumber, 0, 0, 'C');

        // Qty Total มุมขวาล่าง
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY(3, $productY + $productHeight - 5);
        $pdf->Cell($size['width'] - 6, 4, "Qty Total: {$qty}", 0, 0, 'R');
    }
}
