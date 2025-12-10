<?php
// reviews-progress.php
// Simple endpoint to persist and retrieve review rotation progress across reloads.

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$progressFile = __DIR__ . '/reviews-progress.json';

function read_progress($path)
{
    if (!file_exists($path)) {
        return [
            'version' => null,
            'index'   => 0,
        ];
    }

    $json = file_get_contents($path);
    if ($json === false) {
        throw new Exception('Unable to read review progress file.');
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new Exception('Invalid data in review progress file.');
    }

    return [
        'version' => $decoded['version'] ?? null,
        'index'   => isset($decoded['index']) ? (int) $decoded['index'] : 0,
        'updated' => $decoded['updated_at'] ?? null,
    ];
}

function write_progress($path, $version, $index, $total = null)
{
    $payload = [
        'version'    => $version,
        'index'      => $index,
        'total'      => $total,
        'updated_at' => date('c'),
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new Exception('Unable to encode progress payload.');
    }

    $tmpPath = $path . '.tmp';
    if (file_put_contents($tmpPath, $json) === false) {
        throw new Exception('Unable to persist review progress.');
    }

    if (!rename($tmpPath, $path)) {
        throw new Exception('Unable to finalize review progress write.');
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON payload.');
        }

        $version = isset($data['version']) ? (string) $data['version'] : '';
        $index   = isset($data['index']) ? (int) $data['index'] : 0;
        $total   = isset($data['total']) ? (int) $data['total'] : null;

        if ($version === '') {
            throw new Exception('version is required');
        }
        if ($index < 0) {
            $index = 0;
        }

        write_progress($progressFile, $version, $index, $total);

        echo json_encode([
            'success' => true,
            'version' => $version,
            'index'   => $index,
        ]);
        exit;
    }

    $progress = read_progress($progressFile);
    echo json_encode([
        'success' => true,
        'version' => $progress['version'],
        'index'   => $progress['index'],
        'updated' => $progress['updated'] ?? null,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
