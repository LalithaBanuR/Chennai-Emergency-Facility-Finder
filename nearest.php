
<?php
header('Content-Type: application/json; charset=utf-8');

function send_json($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    require_once __DIR__ . '/db.php';

    if (!isset($_GET['lon'], $_GET['lat'])) {
        send_json(['error' => 'Longitude and latitude are required.'], 400);
    }

    $lon = filter_var($_GET['lon'], FILTER_VALIDATE_FLOAT);
    $lat = filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT);

    if ($lon === false || $lat === false) {
        send_json(['error' => 'Invalid coordinates.'], 400);
    }

    if ($lon < 79 || $lon > 81 || $lat < 12 || $lat > 14) {
        send_json(['error' => 'Location is outside the Chennai study area.'], 400);
    }

    $radiusKm = filter_var($_GET['radius'] ?? '10', FILTER_VALIDATE_FLOAT);
    if ($radiusKm === false || !is_finite((float)$radiusKm)) {
        send_json(['error' => 'Invalid search radius.'], 400);
    }

    $radiusKm = max(0.1, min(100.0, (float)$radiusKm));
    $type = trim((string)($_GET['type'] ?? ''));

    $validTypes = ['', 'Hospital', 'Police', 'Fire Station', 'Ambulance', 'Pharmacy'];
    if (!in_array($type, $validTypes, true)) {
        send_json(['error' => 'Invalid facility type.'], 400);
    }

    $connection = db();

    if (!$connection) {
        send_json(['error' => 'Database connection failed. Check db.php.'], 500);
    }

    $sql = "
        WITH selected_point AS (
            SELECT ST_SetSRID(ST_MakePoint($1, $2), 4326) AS geom
        )
        SELECT
            f.id,
            f.name,
            f.service_type,
            f.address,
            f.phone,
            ST_Distance(f.geom::geography, p.geom::geography) AS distance_m,
            ST_AsGeoJSON(f.geom) AS geometry
        FROM public.emergency_facilities AS f
        CROSS JOIN selected_point AS p
        WHERE f.geom IS NOT NULL
          AND ($4 = '' OR f.service_type = $4)
          AND ST_DWithin(f.geom::geography, p.geom::geography, $3)
        ORDER BY distance_m ASC
    ";

    $result = @pg_query_params($connection, $sql, [
        (string)$lon,
        (string)$lat,
        (string)($radiusKm * 1000),
        $type
    ]);

    if ($result === false) {
        error_log('nearest.php SQL error: ' . pg_last_error($connection));
        send_json(['error' => 'Database query failed. Check the PHP error log and database column names.'], 500);
    }

    $features = [];

    while ($row = pg_fetch_assoc($result)) {
        $geometry = json_decode($row['geometry'] ?? '');

        if (!$geometry || !isset($geometry->coordinates)) {
            continue;
        }

        $distance = (float)$row['distance_m'];

        $features[] = [
            'type' => 'Feature',
            'id' => (int)$row['id'],
            'geometry' => $geometry,
            'properties' => [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'service_type' => $row['service_type'],
                'address' => $row['address'],
                'phone' => $row['phone'],
                'distance_m' => round($distance),
                'distance_km' => round($distance / 1000, 2)
            ]
        ];
    }

    pg_free_result($result);

    send_json([
        'type' => 'FeatureCollection',
        'features' => $features
    ]);

} catch (Throwable $e) {
    error_log('nearest.php error: ' . $e->getMessage());
    send_json(['error' => 'PHP or database error. Check the PHP error log.'], 500);
}
