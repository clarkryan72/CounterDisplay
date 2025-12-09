<?php
// unit-of-day.php
// Returns JSON for "Unit of the Day"

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header('Content-Type: application/json');

function respond_error($message) {
    echo json_encode([
        'error'   => true,
        'message' => $message,
    ]);
    exit;
}

$feedUrl = 'https://www.rvonedata.com/feed/export/data?accountId=625&token=d-8Pb-Vc14iENe1yCkdP2w&version=2&format=json';

/**
 * Fetch URL via cURL (more reliable than file_get_contents on many hosts)
 */
function fetch_url($url, $timeout = 10) {
    $ch = curl_init($url);
    if ($ch === false) {
        return [null, 'Unable to init cURL'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'NormanCampersDashboard/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return [null, 'cURL error: ' . $err];
    }
    if ($code >= 400) {
        return [null, 'HTTP ' . $code];
    }
    return [$body, null];
}

// ---- Fetch feed ----
list($json, $fetchError) = fetch_url($feedUrl);
if ($json === null) {
    respond_error('Unable to fetch feed: ' . $fetchError);
}

$data = json_decode($json, true);
if (!$data || !isset($data['locations']) || !is_array($data['locations'])) {
    respond_error('Invalid feed structure');
}

// Flatten units from all locations
$units = [];
foreach ($data['locations'] as $loc) {
    if (!empty($loc['units']) && is_array($loc['units'])) {
        $units = array_merge($units, $loc['units']);
    }
}

if (empty($units)) {
    respond_error('No units found in feed');
}

// Helper to find first asset by type
function find_asset_url($assets, $type) {
    if (!is_array($assets)) return null;
    foreach ($assets as $asset) {
        if (
            isset($asset['assetType']) &&
            $asset['assetType'] === $type &&
            !empty($asset['url'])
        ) {
            return $asset['url'];
        }
    }
    return null;
}

// First available asset with a URL, regardless of type
function find_first_asset_url($assets) {
    if (!is_array($assets)) return null;
    foreach ($assets as $asset) {
        if (!empty($asset['url'])) {
            return $asset['url'];
        }
    }
    return null;
}

// Prefer units with a price
$pricedUnits = array_filter($units, function ($u) {
    $prices = $u['prices'] ?? [];
    return !empty($prices['sales']) || !empty($prices['msrp']);
});
$pool = !empty($pricedUnits) ? $pricedUnits : $units;

// Pick random unit from pool
$unit = $pool[array_rand($pool)];

$prices = $unit['prices'] ?? [];
$sales  = isset($prices['sales']) ? floatval($prices['sales']) : 0;
$msrp   = isset($prices['msrp'])  ? floatval($prices['msrp'])  : 0;
$salePrice = $sales > 0 ? $sales : $msrp;

$props  = $unit['properties'] ?? [];
$length = $props['length']     ?? null;
$slides = $props['slideCount'] ?? null;
$sleeps = $props['sleeps']     ?? null;

$assets    = $unit['assets'] ?? [];
$floorplan  = find_asset_url($assets, 'Unit Technical Drawing');
$firstAsset = find_first_asset_url($assets);

// Image 1: tech drawing if available, otherwise first asset
// Image 2: first asset in the list, or fallback to tech drawing
$image1 = $floorplan ?: $firstAsset;
$image2 = $firstAsset ?: $floorplan;

// Title: "YEAR Make Model" if possible, else description
$year  = $unit['year']  ?? '';
$make  = $unit['make']  ?? '';
$model = $unit['model'] ?? '';
$titleParts = array_filter([$year, $make, $model]);
$title = !empty($titleParts)
    ? implode(' ', $titleParts)
    : ($unit['description'] ?? 'Unit of the Day');

// Link on NormanCampers.com (for QR)
$detailUrl = $unit['itemDetailUrl'] ?? '';

$output = [
    'error'           => false,
    'title'           => $title,
    'stock'           => $unit['stockNumber'] ?? '',
    'year'            => $year,
    'make'            => $make,
    'model'           => $model,
    'price'           => $salePrice,
    'price_formatted' => $salePrice ? ('$' . number_format($salePrice, 0)) : '',
    'length'          => $length,
    'slides'          => $slides,
    'sleeps'          => $sleeps,
    'image1'          => $image1,
    'image2'          => $image2,
    'detail_url'      => $detailUrl,
];

echo json_encode($output);
