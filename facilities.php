<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$minx = isset($_GET['minx']) ? (float) $_GET['minx'] : 80.05;
$miny = isset($_GET['miny']) ? (float) $_GET['miny'] : 12.85;
$maxx = isset($_GET['maxx']) ? (float) $_GET['maxx'] : 80.35;
$maxy = isset($_GET['maxy']) ? (float) $_GET['maxy'] : 13.25;

$search = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$validTypes = [
    '',
    'Hospital',
    'Police',
    'Fire Station',
    'Ambulance',
    'Pharmacy'
];

$validStatuses = [
    '',
    'Open',
    'Busy',
    'Closed'
];

if (!in_array($type, $validTypes, true)) {
    json_output([
        'error' => 'Invalid service type.'
    ], 400);
}

if (!in_array($status, $validStatuses, true)) {
    json_output([
        'error' => 'Invalid facility status.'
    ], 400);
}

if ($minx >= $maxx || $miny >= $maxy) {
    json_output([
        'error' => 'Invalid map extent.'
    ], 400);
}

$sql = <<<SQL
SELECT
    id,
    name,
    service_type,
    address,
    phone,
    status,
    longitude,
    latitude,
    ST_AsGeoJSON(geom)::json AS geometry
FROM public.emergency_facilities
WHERE geom && ST_MakeEnvelope($1, $2, $3, $4, 4326)
  AND (
        $5 = ''
        OR service_type = $5
      )
  AND (
        $6 = ''
        OR status = $6
      )
  AND (
        $7 = ''
        OR name ILIKE '%' || $7 || '%'
        OR address ILIKE '%' || $7 || '%'
      )
ORDER BY service_type, name
LIMIT 300
SQL;

$result = pg_query_params(db(), $sql, [
    $minx,
    $miny,
    $maxx,
    $maxy,
    $type,
    $status,
    $search
]);

if (!$result) {
    json_output([
        'error' => pg_last_error(db())
    ], 500);
}

$features = [];

while ($row = pg_fetch_assoc($result)) {
    $features[] = [
        'type' => 'Feature',
        'id' => (int) $row['id'],
        'geometry' => json_decode($row['geometry']),
        'properties' => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'service_type' => $row['service_type'],
            'address' => $row['address'],
            'phone' => $row['phone'],
            'status' => $row['status'],
            'longitude' => (float) $row['longitude'],
            'latitude' => (float) $row['latitude']
        ]
    ];
}

json_output([
    'type' => 'FeatureCollection',
    'features' => $features
]);
