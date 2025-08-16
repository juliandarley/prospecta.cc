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

// Cache directory for lightweight metadata (e.g., extracted title)
$titleCacheDir = '/var/www/prospecta.cc/tmp/titlecache';
if (!is_dir($titleCacheDir)) { @mkdir($titleCacheDir, 0775, true); }

function get_pdf_title($path, $sha, $titleCacheDir){
    // 1) pdfinfo Title
    $info = @shell_exec('pdfinfo ' . escapeshellarg($path) . ' 2>/dev/null');
    if ($info && preg_match('/^Title:\s*(.+)$/mi', $info, $m)) {
        $t = trim($m[1]);
        if ($t !== '' && strcasecmp($t, '(null)') !== 0) {
            return [$t, 'pdfinfo'];
        }
    }
    // 2) cached heuristic
    $cacheFile = $titleCacheDir . '/' . $sha . '.title.txt';
    if (is_file($cacheFile)) {
        $t = trim((string)@file_get_contents($cacheFile));
        if ($t !== '') return [$t, 'cache'];
    }
    // 3) heuristics on first page text
    $txt = (string)@shell_exec('pdftotext -enc UTF-8 -layout -f 1 -l 1 ' . escapeshellarg($path) . ' - 2>/dev/null');
    $t = '';
    if ($txt !== '') {
        $lines = preg_split('/\R/u', $txt);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Skip boilerplate
            $low = mb_strtolower($line, 'UTF-8');
            if (preg_match('/^(abstract|introduction|contents|table of contents)\b/i', $low)) continue;
            if (preg_match('/^www\.|http(s)?:\/\//i', $low)) continue;
            // Reasonable length & composition
            $len = mb_strlen($line, 'UTF-8');
            $letters = preg_match_all('/\p{L}/u', $line, $dummy);
            if ($len < 5 || $len > 150) continue;
            if ($letters < 4) continue;
            // Avoid lines that look like issues/codes only
            if (preg_match('/\bISSN\b|\bISBN\b|\d{4}-\d{2}/i', $line)) continue;
            $t = $line; break;
        }
    }
    if ($t !== '') {
        @file_put_contents($cacheFile, $t);
        return [$t, 'heuristic'];
    }
    return ['', ''];
}
foreach (glob($dir.'/*.pdf') as $f){
    if(!is_file($f)) continue;
    $st = @stat($f); if(!$st) continue;
    $sha = @hash_file('sha256',$f) ?: '';
    $name = basename($f);
    $pages = get_pages($f);
    list($title, $titleSrc) = get_pdf_title($f, $sha, $titleCacheDir);
    // Load sentinel suggestion if cached
    $sentinelCache = '/var/www/prospecta.cc/tmp/titlecache/' . $sha . '.sentinel.json';
    $sentinel = null;
    if (is_file($sentinelCache)) {
        $j = @file_get_contents($sentinelCache);
        if ($j !== false) { $sentinel = @json_decode($j, true); }
    }

    $items[] = [
        'filename'=>$name,
        'sha256'=>$sha,
        'size'=>$st['size'],
        'size_human'=>human_size($st['size']),
        'mtime'=>$st['mtime'],
        'mtime_human'=>date('Y-m-d H:i', $st['mtime']),
        'pages'=>$pages,
        'title'=>$title,
        'title_source'=>$titleSrc,
        'sentinel'=> is_array($sentinel) ? [
            'canonical_title' => $sentinel['canonical_title'] ?? '',
            'confidence' => $sentinel['confidence'] ?? null,
            'doc_type' => $sentinel['doc_type'] ?? '',
            'publication' => $sentinel['publication'] ?? '',
            'year' => $sentinel['year'] ?? null,
            'date' => $sentinel['date'] ?? '',
            'authors' => $sentinel['authors'] ?? [],
            'editors' => $sentinel['editors'] ?? []
        ] : null,
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


