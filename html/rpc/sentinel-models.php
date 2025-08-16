<?php
header('Content-Type: application/json; charset=utf-8');
try {
    $base = dirname(__DIR__, 2); // /var/www/prospecta.cc
    $path = $base . '/config/sentinel-models.json';
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['error' => 'models config not found']);
        exit;
    }
    $json = file_get_contents($path);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['error' => 'failed to read models config']);
        exit;
    }
    echo $json;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}





