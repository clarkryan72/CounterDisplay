<?php
// reviews-refresh.php
//
// This script should be run by a CRON job once per day (e.g., at midnight).
// It calls Google Places API ONE TIME, then writes reviews-cache.json.
// Your dashboard will ONLY read from reviews-cache.json, never Google directly.

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// TODO: replace with your real key + place id
$apiKey  = 'AIzaSyCowEs3HsgEfSI9cvt0Oxqwj4uDxhNHZKU';
$placeId = 'ChIJnV1dlvAW9YgRxf49hStyIo8';

if ($apiKey === 'YOUR_GOOGLE_API_KEY_HERE' || $placeId === 'YOUR_PLACE_ID_HERE') {
    echo json_encode([
        'success' => false,
        'error'   => 'Google API key or Place ID not configured in reviews-refresh.php',
    ]);
    exit;
}

// Google Places Details endpoint
$url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
    'place_id' => $placeId,
    'fields'   => 'rating,user_ratings_total,reviews',
    'key'      => $apiKey,
]);

function fetch_url($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'NormanCampersDashboard/1.0',
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new Exception('cURL error while calling Google: ' . $curlErr);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        $preview = substr($body, 0, 300);
        throw new Exception("Google returned HTTP $httpCode. Body preview: " . $preview);
    }
    return $body;
}

try {
    $json = fetch_url($url);
    $data = json_decode($json, true);

    if (!isset($data['status'])) {
        throw new Exception('No status field in Google response. Raw preview: ' . substr($json, 0, 300));
    }

    if ($data['status'] !== 'OK') {
        $msg = 'Google Places status: ' . $data['status'];
        if (!empty($data['error_message'])) {
            $msg .= ' | error_message: ' . $data['error_message'];
        }
        throw new Exception($msg);
    }

    if (!isset($data['result'])) {
        throw new Exception('Missing result field in Google response.');
    }

    $result  = $data['result'];
    $rating  = $result['rating'] ?? null;
    $total   = $result['user_ratings_total'] ?? null;
    $reviews = $result['reviews'] ?? [];

    // Attach overall rating info to each review so the front-end can use it
    foreach ($reviews as &$r) {
        $r['_overall_rating']     = $rating;
        $r['_user_ratings_total'] = $total;
    }

    $payload = [
        'generated_at' => date('c'),
        'rating'       => $rating,
        'total'        => $total,
        'reviews'      => $reviews,
    ];

    // Write to local JSON file in same folder
    $targetFile = __DIR__ . '/reviews-cache.json';
    $ok = file_put_contents($targetFile, json_encode($payload, JSON_PRETTY_PRINT));

    if ($ok === false) {
        throw new Exception('Failed to write reviews-cache.json');
    }

    echo json_encode([
        'success' => true,
        'message' => 'reviews-cache.json updated',
        'count'   => count($reviews),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
