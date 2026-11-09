<?php

declare(strict_types=1);

if (!extension_loaded('MapScript') && !extension_loaded('mapscript')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('PHP MapScript is not enabled in MS4W PHP.');
}

$mapFile = __DIR__ . DIRECTORY_SEPARATOR . 'emergency.map';

if (!file_exists($mapFile)) {
    http_response_code(500);
    exit('emergency.map was not found.');
}

$bboxText = $_GET['BBOX'] ?? $_GET['bbox'] ?? '80.05,12.85,80.35,13.25';
$parts = array_map('trim', explode(',', $bboxText));

if (count($parts) !== 4) {
    http_response_code(400);
    exit('BBOX must be minx,miny,maxx,maxy.');
}

foreach ($parts as $part) {
    if (!is_numeric($part)) {
        http_response_code(400);
        exit('BBOX values must be numeric.');
    }
}

$minx = (float) $parts[0];
$miny = (float) $parts[1];
$maxx = (float) $parts[2];
$maxy = (float) $parts[3];
$width = max(300, min(1600, (int) ($_GET['WIDTH'] ?? 900)));
$height = max(300, min(1200, (int) ($_GET['HEIGHT'] ?? 650)));

if ($minx >= $maxx || $miny >= $maxy) {
    http_response_code(400);
    exit('Invalid map extent.');
}

try {
    $map = new mapObj($mapFile);
    $map->setSize($width, $height);
    $map->setExtent($minx, $miny, $maxx, $maxy);

    $image = $map->draw();

    header('Content-Type: image/png');
    echo $image->getBytes();
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'MapScript error: ' . $error->getMessage();
}
