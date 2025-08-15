<?php
// Server-side proxy to Ollama /api/chat to avoid CORS and to test chat-style models
header('Content-Type: application/json; charset=utf-8');
try {
    $base = 'http://delphi.lan:11434';
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $model = $in['model'] ?? '';
    $prompt = $in['prompt'] ?? '';
    $system = $in['system'] ?? 'You are a helpful assistant.';
    if ($model==='' || $prompt==='') { http_response_code(400); echo json_encode(['error'=>'model and prompt required']); exit; }
    $body = [
        'model' => $model,
        'messages' => [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>$prompt]
        ],
        'stream' => false,
        'options'=> ['temperature'=>0.2]
    ];
    $ch = curl_init(rtrim($base,'/').'/api/chat');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_TIMEOUT=>30]);
    $resp = curl_exec($ch); $http = curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if ($resp===false) { http_response_code(502); echo json_encode(['error'=>'curl','detail'=>$err]); exit; }
    http_response_code($http); echo $resp;
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }


