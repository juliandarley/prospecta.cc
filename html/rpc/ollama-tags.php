<?php
header('Content-Type: application/json; charset=utf-8');
$base = 'http://delphi.lan:11434';
try{
    $ch = curl_init(rtrim($base,'/').'/api/tags');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
    $resp = curl_exec($ch); $http = curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if($resp===false){ http_response_code(502); echo json_encode(['error'=>'curl','detail'=>$err]); exit; }
    if($http>=400){ http_response_code($http); echo $resp; exit; }
    echo $resp;
}catch(Throwable $e){ http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }


