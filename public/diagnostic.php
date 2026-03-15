<?php
/**
 * ตรวจสอบ tools ที่มีอยู่บน server
 * ลบไฟล์นี้หลังจาก check เสร็จ (ห้ามปล่อยไว้ใน production)
 */

if (!isset($_GET['key']) || $_GET['key'] !== 'tt_diag_2026') {
    die('Forbidden');
}

$tools = [
    'pdftoppm' => [
        '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm',
        '/opt/local/bin/pdftoppm', 'pdftoppm',
    ],
    'gs (Ghostscript)' => [
        '/usr/bin/gs', '/usr/local/bin/gs', 'gs',
    ],
    'mutool (MuPDF)' => [
        '/usr/bin/mutool', '/usr/local/bin/mutool', 'mutool',
    ],
    'python3' => [
        '/usr/bin/python3', '/usr/local/bin/python3', 'python3',
    ],
    'python' => [
        '/usr/bin/python', '/usr/local/bin/python', 'python',
    ],
];

echo "<pre style='font-family:monospace;line-height:1.8'>";
echo "<b>== Server Diagnostic ==</b>\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
echo "exec() available: " . (function_exists('exec') ? 'YES' : 'NO') . "\n";
echo "Imagick extension: " . (extension_loaded('imagick') ? 'YES' : 'NO') . "\n\n";

echo "<b>== Binary Tools ==</b>\n";
foreach ($tools as $name => $paths) {
    $found = false;
    foreach ($paths as $path) {
        exec("which {$path} 2>/dev/null", $out, $code);
        if ($code === 0 && !empty($out[0])) {
            echo "✓ {$name}: {$out[0]}\n";
            $found = true;
            break;
        }
        // ลอง file_exists ด้วย
        if (strpos($path, '/') === 0 && file_exists($path) && is_executable($path)) {
            echo "✓ {$name}: {$path}\n";
            $found = true;
            break;
        }
        $out = [];
    }
    if (!$found) {
        echo "✗ {$name}: not found\n";
    }
}

// Check PyMuPDF separately
echo "\n<b>== PyMuPDF (recommended for PDF render) ==</b>\n";
$python3s = ['/usr/bin/python3', '/usr/local/bin/python3', 'python3'];
foreach ($python3s as $py) {
    exec(escapeshellarg($py) . " -c 'import fitz; print(fitz.version[0])' 2>/dev/null", $pyout, $pycode);
    if ($pycode === 0 && !empty($pyout[0])) {
        echo "✓ PyMuPDF {$pyout[0]} via {$py}\n";
        break;
    }
    $pyout = [];
}
if ($pycode !== 0) {
    echo "✗ PyMuPDF not found — install with: pip3 install pymupdf\n";
}

echo "\n<b>== PHP disabled_functions ==</b>\n";
$disabled = ini_get('disable_functions');
echo $disabled ?: "(none)\n";

echo "\n<b>== Memory / Limits ==</b>\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "</pre>";
