<?php
// reviews.php
//
// This script is used by the dashboard.
// It DOES NOT call Google. It only reads reviews-cache.json
// that is written by reviews-refresh.php once per day.

header('Content-Type: application/json');

$cacheFile = __DIR__ . '/reviews-cache.json';

if (!file_exists($cacheFile)) {
    echo json_encode([
        'reviews' => [],
        'error'   => 'reviews-cache.json not found. Run reviews-refresh.php first.',
    ]);
    exit;
}

$json = file_get_contents($cacheFile);
if ($json === false) {
    echo json_encode([
        'reviews' => [],
        'error'   => 'Unable to read reviews-cache.json',
    ]);
    exit;
}

$data = json_decode($json, true);
if ($data === null || !isset($data['reviews']) || !is_array($data['reviews'])) {
    echo json_encode([
        'reviews' => [],
        'error'   => 'Invalid data in reviews-cache.json',
    ]);
    exit;
}

// Pass through the cached data
echo json_encode([
    'generated_at' => $data['generated_at'] ?? null,
    'rating'       => $data['rating']       ?? null,
    'total'        => $data['total']        ?? null,
    'reviews'      => $data['reviews'],
]);
