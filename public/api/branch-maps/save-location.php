<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || empty($payload['branch_id']) || empty($payload['branch_name'])) {
        throw new RuntimeException('branch_id and branch_name are required.');
    }

    $lat = (string)($payload['latitude'] ?? '');
    $lng = (string)($payload['longitude'] ?? '');

    $mapsUrl = null;
    $embedUrl = null;

    if ($lat !== '' && $lng !== '') {
        $mapsUrl = 'https://www.google.com/maps?q=' . $lat . ',' . $lng;

        $embedUrl = 'https://www.google.com/maps/embed/v1/place?key='
            . urlencode((string)getenv('GOOGLE_MAPS_API_KEY'))
            . '&q=' . $lat . ',' . $lng;
    }

    $pdo = Database::getInstance();

    $stmt = $pdo->prepare(
        'INSERT INTO branch_maps_locations
         (branch_id, branch_code, branch_name, address, latitude, longitude, google_place_id, google_maps_url, google_embed_url, contact_phone, is_active, created_at, updated_at)
         VALUES
         (:branch_id, :branch_code, :branch_name, :address, :latitude, :longitude, :google_place_id, :google_maps_url, :google_embed_url, :contact_phone, :is_active, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            branch_code = VALUES(branch_code),
            branch_name = VALUES(branch_name),
            address = VALUES(address),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            google_place_id = VALUES(google_place_id),
            google_maps_url = VALUES(google_maps_url),
            google_embed_url = VALUES(google_embed_url),
            contact_phone = VALUES(contact_phone),
            is_active = VALUES(is_active),
            updated_at = NOW()'
    );

    $stmt->execute([
        ':branch_id' => $payload['branch_id'],
        ':branch_code' => $payload['branch_code'] ?? null,
        ':branch_name' => $payload['branch_name'],
        ':address' => $payload['address'] ?? null,
        ':latitude' => $payload['latitude'] ?? null,
        ':longitude' => $payload['longitude'] ?? null,
        ':google_place_id' => $payload['google_place_id'] ?? null,
        ':google_maps_url' => $mapsUrl,
        ':google_embed_url' => $embedUrl,
        ':contact_phone' => $payload['contact_phone'] ?? null,
        ':is_active' => $payload['is_active'] ?? 1,
    ]);

    echo json_encode([
        'success' => true,
        'google_maps_url' => $mapsUrl,
        'google_embed_url' => $embedUrl,
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
