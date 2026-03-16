<?php

return [
    'show_warnings'   => false,
    'orientation'     => 'portrait',
    'defines'         => [
        'DOMPDF_ENABLE_REMOTE'  => true,   // อนุญาตอ่าน font จาก local file path
        'DOMPDF_CHROOT'         => realpath(base_path()),
        'DOMPDF_FONT_DIR'       => storage_path('fonts/'),
        'DOMPDF_FONT_CACHE'     => storage_path('fonts/'),
        'DOMPDF_TEMP_DIR'       => sys_get_temp_dir(),
        'DOMPDF_DPI'            => 150,
        'DOMPDF_ENABLE_FONTSUBSETTING' => false,
    ],
    'options' => [
        'font_dir'          => storage_path('fonts/'),
        'font_cache'        => storage_path('fonts/'),
        'temp_dir'          => sys_get_temp_dir(),
        'chroot'            => realpath(base_path()),
        'allowed_protocols' => [
            'file://' => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],
        'isRemoteEnabled'   => true,
        'isHtml5ParserEnabled' => true,
        'isFontSubsettingEnabled' => false,
        'defaultMediaType'  => 'print',
        'defaultPaperSize'  => 'A4',
        'defaultFont'       => 'TlwgTypo',
        'dpi'               => 150,
        'debugPng'          => false,
        'debugKeepTemp'     => false,
        'debugCss'          => false,
        'debugLayout'       => false,
    ],
];
