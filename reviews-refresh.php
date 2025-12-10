<?php
// reviews-refresh.php
//
// This script should be run by a CRON job once per day (e.g., at midnight).
// It calls Google Places API ONE TIME, updates reviews-cache.json, and
// appends any NEW reviews to master_reviews.json without overwriting the
// existing collection.

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

function fetch_url($url)
{
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

function load_master_reviews($path)
{
    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false) {
        throw new Exception('Unable to read existing master_reviews.json');
    }

    $decoded = json_decode($json, true);
    if ($decoded === null) {
        throw new Exception('Invalid JSON in master_reviews.json; refusing to overwrite');
    }

    $reviews = $decoded['reviews'] ?? $decoded;
    if (!is_array($reviews)) {
        throw new Exception('master_reviews.json does not contain a reviews array');
    }

    return $reviews;
}

function save_master_reviews($path, array $reviews)
{
    usort($reviews, function ($a, $b) {
        $aTime = strtotime($a['review_date'] ?? '') ?: 0;
        $bTime = strtotime($b['review_date'] ?? '') ?: 0;
        return $bTime <=> $aTime; // newest first
    });

    $payload = [
        'updated_at' => date('c'),
        'reviews'    => array_values($reviews),
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    // Make a simple backup before overwriting
    if (file_exists($path)) {
        @copy($path, $path . '.bak');
    }

    $tmpPath = $path . '.tmp';
    if (file_put_contents($tmpPath, $json) === false) {
        return false;
    }

    return rename($tmpPath, $path);
}

function normalize_rating($rating)
{
    if (is_numeric($rating)) {
        return (float) $rating;
    }

    if (is_string($rating)) {
        $map = [
            'one'   => 1,
            'two'   => 2,
            'three' => 3,
            'four'  => 4,
            'five'  => 5,
        ];
        $key = strtolower(trim($rating));
        if (isset($map[$key])) {
            return $map[$key];
        }
    }

    return null;
}

function normalize_google_reviews(array $googleReviews, int $cutoffTimestamp)
{
    $normalized = [];

    foreach ($googleReviews as $r) {
        $timestamp = null;

        if (isset($r['time']) && is_numeric($r['time'])) {
            $timestamp = (int) $r['time'];
        } elseif (isset($r['createTime'])) {
            $parsed = strtotime($r['createTime']);
            $timestamp = $parsed !== false ? $parsed : null;
        } elseif (isset($r['updateTime'])) {
            $parsed = strtotime($r['updateTime']);
            $timestamp = $parsed !== false ? $parsed : null;
        }

        if ($timestamp === null || $timestamp < $cutoffTimestamp) {
            continue; // ignore reviews older than 24 hours or without a timestamp
        }

        $rating = normalize_rating($r['rating'] ?? null);
        $reviewDate = date('Y-m-d H:i:s', $timestamp);

        $reviewId = null;
        if (!empty($r['review_id'])) {
            $reviewId = 'google-' . $r['review_id'];
        } elseif (!empty($r['id'])) {
            $reviewId = 'google-' . $r['id'];
        } elseif (!empty($r['name'])) {
            $reviewId = 'google-' . $r['name'];
        }

        if ($reviewId === null) {
            $hash = md5(strtolower(($r['author_name'] ?? '') . '|' . ($r['text'] ?? '') . '|' . $reviewDate));
            $reviewId = 'google-' . $hash;
        }

        $normalized[] = [
            'id'            => $reviewId,
            'source'        => 'google',
            'customer_name' => $r['author_name'] ?? 'Google Reviewer',
            'review'        => $r['text'] ?? '',
            'review_date'   => $reviewDate,
            'rating'        => $rating,
        ];
    }

    return $normalized;
}

function append_new_google_reviews(array $googleReviews, $masterPath)
{
    $masterReviews = load_master_reviews($masterPath);

    $existingKeys = [];
    foreach ($masterReviews as $review) {
        if (!empty($review['id'])) {
            $existingKeys['id:' . $review['id']] = true;
        }

        $dedupeHash = md5(strtolower(($review['customer_name'] ?? '') . '|' . ($review['review'] ?? '') . '|' . ($review['review_date'] ?? '')));
        $existingKeys['hash:' . $dedupeHash] = true;
    }

    $added = 0;
    foreach ($googleReviews as $entry) {
        $idKey = 'id:' . ($entry['id'] ?? '');
        $hashKey = 'hash:' . md5(strtolower(($entry['customer_name'] ?? '') . '|' . ($entry['review'] ?? '') . '|' . ($entry['review_date'] ?? '')));

        if (($entry['id'] ?? null) && isset($existingKeys[$idKey])) {
            continue; // duplicate by explicit ID
        }
        if (isset($existingKeys[$hashKey])) {
            continue; // duplicate by content hash
        }

        $masterReviews[] = $entry;
        $existingKeys[$idKey] = true;
        $existingKeys[$hashKey] = true;
        $added++;
    }

    if ($added === 0) {
        return [false, 0];
    }

    $saved = save_master_reviews($masterPath, $masterReviews);
    return [$saved, $added];
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

    if (empty($reviews)) {
        throw new Exception('No reviews returned from Google; master_reviews.json unchanged.');
    }

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

    // Append any new Google reviews (from last 24 hours) to master_reviews.json (deduped)
    $masterFile = __DIR__ . '/master_reviews.json';
    $cutoff     = time() - 24 * 60 * 60;
    $recentNormalized = normalize_google_reviews($reviews, $cutoff);

    [$masterSaved, $added] = append_new_google_reviews($recentNormalized, $masterFile);

    $message = 'reviews-cache.json updated';
    if ($added > 0 && !$masterSaved) {
        throw new Exception('Failed to update master_reviews.json');
    }

    if ($added > 0) {
        $message .= " and $added new review(s) appended to master_reviews.json";
    } else {
        $message .= '; no new reviews to append (only keeping last 24 hours)';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'count'   => count($reviews),
        'added'   => $added,
    ]);
} catch (Exception $e) {
    error_log('reviews-refresh.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
