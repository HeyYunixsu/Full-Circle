<?php

require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';

function getQRCodeUrl($data, $size = 300) {

    $tmp = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    QRcode::png($data, $tmp, QR_ECLEVEL_L, 8, 2);
    $resized = resizeQRImage($tmp, $size);
    $contents = file_get_contents($resized);
    @unlink($tmp);
    @unlink($resized);
    return 'data:image/png;base64,' . base64_encode($contents);
}

function resizeQRImage($srcPath, $size) {
    $src = imagecreatefrompng($srcPath);
    $dst = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $srcW, $srcH);

    $outPath = tempnam(sys_get_temp_dir(), 'qrresized_') . '.png';
    imagepng($dst, $outPath);
    imagedestroy($src);
    imagedestroy($dst);
    return $outPath;
}

function saveQRCode($data, $filename, $size = 300) {
    $filepath = QRCODES_PATH . '/' . $filename . '.png';

    try {
        
        $tmp = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
        QRcode::png($data, $tmp, QR_ECLEVEL_L, 8, 2);

        
        $resized = resizeQRImage($tmp, $size);
        @unlink($tmp);

        if (!rename($resized, $filepath)) {
            
            copy($resized, $filepath);
            @unlink($resized);
        }

        return file_exists($filepath) ? 'assets/qrcodes/' . $filename . '.png' : false;
    } catch (Throwable $e) {
        return false;
    }
}

function getQRCodeImageUrl($qr_data, $local_path = null) {
    
    $file = qrFilePath($local_path);
    if ($file) return QRCODES_URL . '/' . basename($file);

    return getQRCodeUrl($qr_data);
}
// Real QR code as a lightweight inline SVG (no image file). Used on the login ticket.
function qrSvg($text, $label = 'QR code') {
    $rows = QRcode::text($text, false, QR_ECLEVEL_L, 1, 0);
    $d = '';
    foreach ($rows as $y => $row) {
        for ($x = 0, $n = strlen($row); $x < $n; $x++) {
            if ($row[$x] === '1') $d .= "M{$x} {$y}h1v1h-1z";
        }
    }
    $size = count($rows);
    return '<svg viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="' . htmlspecialchars($label) . '" shape-rendering="crispEdges"><path d="' . $d . '"/></svg>';
}
