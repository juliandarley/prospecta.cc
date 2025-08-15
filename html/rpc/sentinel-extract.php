<?php
header('Content-Type: application/json; charset=utf-8');

function respond($p, $s=200){ http_response_code($s); echo json_encode($p); exit; }

$baseDir = '/var/www/prospecta.cc';
$pdfDir  = $baseDir . '/data/pdfs';
$cacheDir= $baseDir . '/tmp/titlecache';
@mkdir($cacheDir, 0775, true);

// Logging helpers (rule 14: centralised logs, no alerts)
$logDir = $baseDir . '/logs';
@mkdir($logDir, 0775, true);
function slog($level, $msg){
    global $logDir; $ts = date('c');
    $line = "[$ts] [$level] $msg\n";
    $file = ($level==='ERROR' ? $logDir.'/sentinel.error.log' : $logDir.'/sentinel.log');
    @file_put_contents($file, $line, FILE_APPEND);
}

// Read configs
$modelsCfg = @json_decode(@file_get_contents($baseDir.'/config/sentinel-models.json'), true) ?: [];
$endptsCfg = @json_decode(@file_get_contents($baseDir.'/config/sentinel-endpoints.json'), true) ?: [];

try{
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $filename = isset($in['filename']) ? basename($in['filename']) : '';
    $modelId  = trim((string)($in['modelId'] ?? 'local-text'));
    if ($filename==='') respond(['error'=>'missing filename'],400);
    $pdfPath = $pdfDir . '/' . $filename;
    if (!is_file($pdfPath)) respond(['error'=>'file not found'],404);

    // Find model and endpoint
    $model = null; foreach($modelsCfg as $m){ if(($m['id']??'')===$modelId){ $model=$m; break; } }
    if(!$model){ slog('ERROR','unknown modelId='.$modelId); respond(['error'=>'unknown model'],400); }
    $epRef = $model['endpointRef'] ?? '';
    $ep = $endptsCfg[$epRef] ?? null; if(!$ep){ slog('ERROR','endpoint not configured: '.$epRef); respond(['error'=>'endpoint not configured'],500); }

    // Gather context
    $info = @shell_exec('pdfinfo '.escapeshellarg($pdfPath).' 2>/dev/null');
    $txt1 = @shell_exec('pdftotext -enc UTF-8 -layout -f 1 -l 1 '.escapeshellarg($pdfPath).' - 2>/dev/null');
    $txt2 = @shell_exec('pdftotext -enc UTF-8 -layout -f 2 -l 2 '.escapeshellarg($pdfPath).' - 2>/dev/null');
    $txt3 = @shell_exec('pdftotext -enc UTF-8 -layout -f 3 -l 3 '.escapeshellarg($pdfPath).' - 2>/dev/null');

    // For vision, render first 3 pages to PNG under cache
    $images = [];
    if (($model['modality'] ?? 'text') === 'vision') {
        $prefix = $cacheDir . '/img_' . sha1($pdfPath) . '_p';
        // Render if not present
        for($p=1;$p<=3;$p++){
            $png = $prefix.$p.'.png';
            if (!is_file($png)) {
                @shell_exec('pdftoppm -f '.$p.' -l '.$p.' -png '.escapeshellarg($pdfPath).' '.escapeshellarg($prefix).' 2>/dev/null');
            }
            if (is_file($png)) { $images[] = $png; }
        }
    }

    // Build prompt
    $prompt = [
        'instruction' => 'Extract canonical bibliographic metadata for citation. Return JSON with keys: canonical_title, doc_type(one of book,chapter,article,issue,magazine,newsletter,paper), publication, issue, volume, year, date(YYYY-MM-DD if known), authors[{name}], editors[{name}], confidence[0..1], evidence[{page,reason}]. IMPORTANT: Keep evidence array SHORT (max 5 items). Do not repeat entries. Prefer explicit page evidence; avoid guessing beyond reasonable inferences. If uncertain, lower confidence and add notes.',
        'pdfinfo' => $info,
        'page_text' => [trim((string)$txt1), trim((string)$txt2), trim((string)$txt3)],
        'filename' => $filename
    ];

    // Call model
    $result = null;
    if (($ep['type'] ?? '') === 'ollama') {
        $base = rtrim($ep['baseUrl'],'/');
        $modelName = ($model['modality']==='vision') ? ($ep['visionModel'] ?? ($ep['textModel'] ?? '')) : ($ep['textModel'] ?? '');
        if ($modelName===''){ slog('ERROR','ollama model missing for modality '.$model['modality']); respond(['error'=>'ollama model name missing'],500); }

        // Preflight reachability & list tags
        $ch = curl_init($base.'/api/tags');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>8,
            CURLOPT_CONNECTTIMEOUT=>3
        ]);
        $tagsResp = curl_exec($ch); $tagsHttp = curl_getinfo($ch,CURLINFO_HTTP_CODE); $tagsErr = curl_error($ch); curl_close($ch);
        if ($tagsResp===false || $tagsHttp>=400){
            slog('ERROR','ollama tags http='.$tagsHttp.' err='.$tagsErr.' base='.$base);
            respond(['error'=>'ollama not reachable','http'=>$tagsHttp],502);
        }
        slog('INFO','sentinel start model='.$modelName.' modality='.$model['modality'].' file='.$filename.' endpoint='.$base);
        // Prefer /api/generate with format=json (forces strict JSON)
        $gen = [
            'model'  => $modelName,
            'prompt' => json_encode($prompt, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'options'=> ['temperature'=>0.1],
            'format' => 'json',
            'stream' => false
        ];
        if ($model['modality']==='vision' && !empty($images)) {
            // Attach base64 images as per Ollama vision API
            $b64 = [];
            foreach ($images as $img) {
                $data = @file_get_contents($img);
                if ($data !== false) { $b64[] = base64_encode($data); }
            }
            if (!empty($b64)) { $gen['images'] = $b64; }
        }
        $ch = curl_init($base.'/api/generate');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode($gen),
            CURLOPT_TIMEOUT=>25,
            CURLOPT_CONNECTTIMEOUT=>3
        ]);
        $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($resp===false){ slog('ERROR','ollama generate curl error='.$err); respond(['error'=>'ollama error: '.$err],502); }
        $j = json_decode($resp, true);
        $txt = $j['response'] ?? '';
        $result = json_decode($txt, true);
        if (!is_array($result)){
            // Save raw for debugging
            $rawPath = $cacheDir.'/'.sha1($pdfPath).'.sentinel.raw.txt';
            @file_put_contents($rawPath, $txt ?: $resp);
            slog('ERROR','bad JSON http='.$http.' rawSaved='.basename($rawPath));
            respond(['error'=>'bad sentinel JSON','http'=>$http,'raw_saved'=>basename($rawPath)],502);
        }
    } elseif (($ep['type'] ?? '') === 'openai') {
        // Placeholder for cloud; you can fill with your key in env
        $apiKey = getenv($ep['apiKeyEnv'] ?? '') ?: '';
        if ($apiKey==='') respond(['error'=>'cloud API key not set'],500);
        // Minimal text-only example
        $ch = curl_init(($ep['baseUrl'] ?? 'https://api.openai.com/v1').'/chat/completions');
        $body = [ 'model'=>($ep['model'] ?? 'gpt-4o-mini'), 'temperature'=>0.1,
            'messages'=>[
                ['role'=>'system','content'=>'You are a meticulous bibliographic metadata extractor. Respond ONLY with strict JSON.'],
                ['role'=>'user','content'=>json_encode($prompt, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]
            ]
        ];
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body)]);
        $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($resp===false) respond(['error'=>'cloud error: '.$err],502);
        $j = json_decode($resp, true);
        $txt = $j['choices'][0]['message']['content'] ?? '';
        $result = json_decode($txt, true);
        if (!is_array($result)) respond(['error'=>'bad sentinel JSON','raw'=>$txt],502);
    } else {
        respond(['error'=>'unsupported endpoint type'],500);
    }

    // Persist cache
    $sha = @hash_file('sha256',$pdfPath) ?: sha1($pdfPath);
    @file_put_contents($cacheDir.'/'.$sha.'.sentinel.json', json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    respond(['ok'=>true,'result'=>$result]);
}
catch(Throwable $e){ respond(['error'=>$e->getMessage()],500); }


