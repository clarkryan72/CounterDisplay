<?php
// unit-of-day.php
// Returns JSON for "Unit of the Day"

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

function respond_error($message) {
    echo json_encode([
        'error'   => true,
        'message' => $message,
    ]);
    exit;
}

$feedUrl = 'https://www.rvonedata.com/feed/export/data?accountId=625&token=d-8Pb-Vc14iENe1yCkdP2w&version=2&format=json';
$cacheFile = __DIR__ . '/unit-of-day-cache.json';
$today = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');

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

function is_new_active_unit($unit)
{
    $conditionRaw = strtolower(trim($unit['condition'] ?? $unit['newUsed'] ?? $unit['inventoryType'] ?? ''));
    $condition    = preg_replace('/\s+/', ' ', $conditionRaw);

    // Reject any unit explicitly marked as used/pre-owned
    $usedIndicators = ['used', 'pre-owned', 'preowned', 'pre owned'];
    foreach ($usedIndicators as $needle) {
        if ($condition && strpos($condition, $needle) !== false) {
            return false;
        }
    }

    // Require a positive indicator that the unit is new
    $hasNewIndicator = false;

    $newFlags = [
        $unit['isNew'] ?? null,
        $unit['new']   ?? null,
    ];
    foreach ($newFlags as $flag) {
        if (is_bool($flag)) {
            $hasNewIndicator = $hasNewIndicator || $flag === true;
        } elseif (is_numeric($flag)) {
            $hasNewIndicator = $hasNewIndicator || intval($flag) === 1;
        } elseif (is_string($flag)) {
            $flagLower = strtolower(trim($flag));
            $hasNewIndicator = $hasNewIndicator || in_array($flagLower, ['y', 'yes', 'true', '1', 'new'], true);
        }
    }

    if (!$hasNewIndicator && $condition) {
        // Common strings such as "New", "New Inventory", or shorthand "N"
        $hasNewIndicator = strpos($condition, 'new') !== false || $condition === 'n';
    }

    if (!$hasNewIndicator) {
        return false;
    }

    $status = strtolower(trim($unit['status'] ?? $unit['inventoryStatus'] ?? ''));
    if ($status && !in_array($status, ['active', 'available'], true)) {
        return false;
    }

    $isSold = $unit['isSold'] ?? $unit['sold'] ?? null;
    if (is_bool($isSold) && $isSold === true) {
        return false;
    }

    return true;
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

// Prefer new, active units only
$newActiveUnits = array_values(array_filter($units, 'is_new_active_unit'));
if (empty($newActiveUnits)) {
    respond_error('No new active units found in feed');
}

// Prefer units with a price
$pricedUnits = array_filter($newActiveUnits, function ($u) {
    $prices = $u['prices'] ?? [];
    return !empty($prices['sales']) || !empty($prices['msrp']);
});
$pool = array_values(!empty($pricedUnits) ? $pricedUnits : $newActiveUnits);

if (empty($pool)) {
    respond_error('No units available after filtering');
}

// Stable ordering so rotation is predictable day-to-day
usort($pool, function ($a, $b) {
    $aStock = strtolower(trim($a['stockNumber'] ?? ''));
    $bStock = strtolower(trim($b['stockNumber'] ?? ''));

    if ($aStock !== '' && $bStock !== '') {
        return $aStock <=> $bStock;
    }

    $aTitle = strtolower(trim(($a['year'] ?? '') . ' ' . ($a['make'] ?? '') . ' ' . ($a['model'] ?? '')));
    $bTitle = strtolower(trim(($b['year'] ?? '') . ' ' . ($b['make'] ?? '') . ' ' . ($b['model'] ?? '')));
    return $aTitle <=> $bTitle;
});

function unit_identifier($unit)
{
    if (!empty($unit['stockNumber'])) {
        return (string) $unit['stockNumber'];
    }
    if (!empty($unit['itemDetailUrl'])) {
        return (string) $unit['itemDetailUrl'];
    }

    $title = trim(($unit['year'] ?? '') . ' ' . ($unit['make'] ?? '') . ' ' . ($unit['model'] ?? ''));
    return $title !== '' ? $title : md5(json_encode($unit));
}

$unitIndexById = [];
foreach ($pool as $idx => $unit) {
    $unitIndexById[unit_identifier($unit)] = $idx;
}

// Load cache to keep one unit per day and rotate sequentially
$cache = null;
if (file_exists($cacheFile)) {
    $json = file_get_contents($cacheFile);
    if ($json !== false) {
        $cache = json_decode($json, true);
    }
}

// If we already picked a unit today, ensure it is still in the pool and new
if (is_array($cache) && ($cache['date'] ?? '') === $today) {
    $cachedId = $cache['meta']['id'] ?? ($cache['unit']['stock'] ?? null);
    if ($cachedId && isset($unitIndexById[$cachedId])) {
        $cachedUnit = $pool[$unitIndexById[$cachedId]];
        if (is_new_active_unit($cachedUnit)) {
            $unit = $cachedUnit;
            $nextIndex = $unitIndexById[$cachedId];
            goto render_and_cache;
        }
    }
}

// Pick next unit in sequence, wrapping around when we reach the end
$lastIndex = -1;
if (isset($cache['meta']['id']) && isset($unitIndexById[$cache['meta']['id']])) {
    $lastIndex = $unitIndexById[$cache['meta']['id']];
} elseif (isset($cache['index'])) {
    $lastIndex = intval($cache['index']);
}

$nextIndex = $lastIndex + 1;
if ($nextIndex < 0 || $nextIndex >= count($pool)) {
    $nextIndex = 0;
}

$unit = $pool[$nextIndex];

render_and_cache:

$unitId = unit_identifier($unit);

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

// Single image: prefer tech drawing, fallback to first available asset
$image1 = $floorplan ?: $firstAsset;

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
    'detail_url'      => $detailUrl,
];

// Persist selection so the same unit is shown all day and the next day advances
$cacheData = [
    'date'  => $today,
    'index' => $nextIndex,
    'unit'  => $output,
    'meta'  => [
        'is_new_active' => is_new_active_unit($unit),
        'id'            => $unitId,
    ],
];
@file_put_contents($cacheFile, json_encode($cacheData));

echo json_encode($output);
