<?php
// Server-side proxy to avoid CORS when calling Ollama on delphi.lan
header('Content-Type: application/json; charset=utf-8');
try {
    $base = 'http://delphi.lan:11434';
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!isset($in['model']) || !isset($in['prompt'])) {
        http_response_code(400);
        echo json_encode(['error' => 'model and prompt required']);
        exit;
    }
    $in['stream'] = false; // force non-stream for simplicity
    if (!isset($in['format'])) { $in['format'] = 'json'; }
    $ch = curl_init(rtrim($base,'/').'/api/generate');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($in),
        CURLOPT_TIMEOUT=>30
    ]);
    $resp = curl_exec($ch); $http = curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if ($resp === false) {
        http_response_code(502);
        echo json_encode(['error'=>'curl','detail'=>$err]);
        exit;
    }
    http_response_code($http);
    echo $resp;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}


