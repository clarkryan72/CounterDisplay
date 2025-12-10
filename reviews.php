<?php
// reviews.php
//
// This script is used by the dashboard to read from master_reviews.json
// and return the data needed for the rotator card. Google is not called
// directly here; reviews-refresh.php maintains the master list.

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$masterFile = __DIR__ . '/master_reviews.json';

if (!file_exists($masterFile)) {
    echo json_encode([
        'reviews' => [],
        'error'   => 'master_reviews.json not found. Run reviews-refresh.php first.',
    ]);
    exit;
}

$json = file_get_contents($masterFile);
if ($json === false) {
    echo json_encode([
        'reviews' => [],
        'error'   => 'Unable to read master_reviews.json',
    ]);
    exit;
}

$rawData = json_decode($json, true);
if ($rawData === null) {
    echo json_encode([
        'reviews' => [],
        'error'   => 'Invalid JSON in master_reviews.json',
    ]);
    exit;
}

$allReviews = $rawData['reviews'] ?? $rawData;
if (!is_array($allReviews)) {
    $allReviews = [];
}

$fiveStar = array_values(array_filter($allReviews, function ($r) {
    if (!isset($r['rating'])) {
        return false;
    }

    $rating = (int) $r['rating'];
    return $rating >= 4 && $rating <= 5;
}));

usort($fiveStar, function ($a, $b) {
    $aTime = strtotime($a['review_date'] ?? '') ?: 0;
    $bTime = strtotime($b['review_date'] ?? '') ?: 0;
    return $bTime <=> $aTime; // newest first
});

$totalCount = count($allReviews);
$averageRating = null;
if ($totalCount > 0) {
    $sum = 0;
    $countWithRatings = 0;
    foreach ($allReviews as $r) {
        if (isset($r['rating']) && is_numeric($r['rating'])) {
            $sum += (float) $r['rating'];
            $countWithRatings++;
        }
    }
    if ($countWithRatings > 0) {
        $averageRating = $sum / $countWithRatings;
    }
}

function format_date($dateStr)
{
    $ts = strtotime($dateStr ?: '');
    if ($ts === false) {
        return $dateStr ?? '';
    }
    return date('M j, Y', $ts);
}

$formattedReviews = array_map(function ($r) use ($averageRating) {
    return [
        'author_name' => $r['customer_name'] ?? '',
        'text' => $r['review'] ?? '',
        'review_date' => $r['review_date'] ?? null,
        'relative_time_description' => format_date($r['review_date'] ?? null),
        '_overall_rating' => $averageRating,
    ];
}, $fiveStar);

echo json_encode([
    'generated_at' => $rawData['updated_at'] ?? ($rawData['generated_at'] ?? null),
    'rating'       => $averageRating,
    'total'        => $totalCount,
    'reviews'      => $formattedReviews,
]);
