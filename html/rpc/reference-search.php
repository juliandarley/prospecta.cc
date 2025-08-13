<?php
// Stub search endpoint returning canned data until backend is wired
header('Content-Type: application/json; charset=utf-8');
try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $query = trim($input['query'] ?? '');
    if ($query === '') {
        http_response_code(400);
        echo json_encode(['error' => 'query required']);
        exit;
    }
    $hits = [];
    // Example canned hit
    $hits[] = [
        'file' => 'example.pdf',
        'page' => 3,
        'snippet' => '... this is a sample snippet containing <mark>' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '</mark> ...'
    ];
    echo json_encode(['hits' => $hits]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

