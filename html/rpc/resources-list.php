<?php
header('Content-Type: application/json; charset=utf-8');

$dir = '/var/www/prospecta.cc/data/pdfs';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

function human_size($bytes){
    $u=['B','KB','MB','GB','TB']; $i=0; while($bytes>=1024 && $i<count($u)-1){$bytes/=1024;$i++;}
    return sprintf('%.1f %s',$bytes,$u[$i]);
}

function get_pages($path){
    $out = @shell_exec('pdfinfo ' . escapeshellarg($path) . ' 2>/dev/null');
    if ($out && preg_match('/^Pages:\s*(\d+)/mi', $out, $m)) return (int)$m[1];
    return null;
}

$items=[]; $byName=[]; $bySha=[];
foreach (glob($dir.'/*.pdf') as $f){
    if(!is_file($f)) continue;
    $st = @stat($f); if(!$st) continue;
    $sha = @hash_file('sha256',$f) ?: '';
    $name = basename($f);
    $pages = get_pages($f);
    $items[] = [
        'filename'=>$name,
        'sha256'=>$sha,
        'size'=>$st['size'],
        'size_human'=>human_size($st['size']),
        'mtime'=>$st['mtime'],
        'mtime_human'=>date('Y-m-d H:i', $st['mtime']),
        'pages'=>$pages,
    ];
    $byName[$name] = ($byName[$name] ?? 0) + 1;
    $bySha[$sha]   = ($bySha[$sha] ?? 0) + 1;
}

// Flag dups
foreach ($items as &$it){
    $it['dup_name'] = ($byName[$it['filename']] ?? 0) > 1;
    $it['dup_sha']  = ($bySha[$it['sha256']] ?? 0) > 1;
}
unset($it);

echo json_encode([
    'items'=>$items,
    'dups'=>[ 'name'=>count(array_filter($byName, fn($c)=>$c>1)), 'content'=>count(array_filter($bySha, fn($c)=>$c>1)) ]
]);


