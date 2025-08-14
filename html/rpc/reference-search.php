<?php
// PDF reference search (per-page), using poppler-utils (pdfinfo, pdftotext)
// Searches PDFs in /var/www/prospecta.cc/data/pdfs and caches extracted page text
// in /var/www/prospecta.cc/tmp/textcache

header('Content-Type: application/json; charset=utf-8');

// Config
const PDF_DIR   = '/var/www/prospecta.cc/data/pdfs';
const CACHE_DIR = '/var/www/prospecta.cc/tmp/textcache';
const MAX_HITS  = 50;            // return at most
const SNIPPET_CTX = 100;         // characters left/right of match

function respond($payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $query = isset($input['query']) ? trim((string)$input['query']) : '';
    $limit = isset($input['limit']) ? max(1, (int)$input['limit']) : MAX_HITS;

    if ($query === '') {
        respond(['error' => 'query required'], 400);
    }

    // Sanity checks
    if (!is_dir(PDF_DIR)) {
        respond(['error' => 'PDF directory missing', 'dir' => PDF_DIR], 500);
    }
    if (!is_dir(CACHE_DIR)) {
        // best-effort create
        @mkdir(CACHE_DIR, 0775, true);
        if (!is_dir(CACHE_DIR)) {
            respond(['error' => 'Cache directory missing and could not be created', 'dir' => CACHE_DIR], 500);
        }
    }

    // Ensure tools exist
    $hasPdfInfo   = trim((string)@shell_exec('command -v pdfinfo 2>/dev/null')) !== '';
    $hasPdfToText = trim((string)@shell_exec('command -v pdftotext 2>/dev/null')) !== '';
    if (!$hasPdfInfo || !$hasPdfToText) {
        respond([
            'error' => 'Missing dependencies',
            'details' => 'Install poppler-utils (pdfinfo, pdftotext)'
        ], 500);
    }

    $hits = [];
    $queryLower = mb_strtolower($query, 'UTF-8');

    // Iterate PDFs
    $pdfFiles = glob(PDF_DIR . '/*.pdf');
    foreach ($pdfFiles as $pdfPath) {
        if (!is_file($pdfPath)) { continue; }

        // Hash to identify cache (file path + size + mtime)
        $stat = @stat($pdfPath);
        if (!$stat) { continue; }
        $sig = sha1($pdfPath . '|' . $stat['size'] . '|' . $stat['mtime']);

        // Get page count
        $infoOut = @shell_exec('pdfinfo ' . escapeshellarg($pdfPath) . ' 2>/dev/null');
        if (!$infoOut) { continue; }
        $pages = 0;
        if (preg_match('/^Pages:\s*(\d+)/mi', $infoOut, $m)) {
            $pages = (int)$m[1];
        }
        if ($pages <= 0) { continue; }

        for ($p = 1; $p <= $pages && count($hits) < $limit; $p++) {
            $cacheFile = CACHE_DIR . '/' . $sig . '.p' . $p . '.txt';
            $text = '';
            if (is_file($cacheFile)) {
                $text = (string)@file_get_contents($cacheFile);
            } else {
                // Extract single page text
                $cmd = 'pdftotext -enc UTF-8 -layout -f ' . (int)$p . ' -l ' . (int)$p . ' ' . escapeshellarg($pdfPath) . ' -';
                $text = (string)@shell_exec($cmd . ' 2>/dev/null');
                // Normalize
                $text = preg_replace("/\x{00AD}/u", '', $text); // soft hyphen
                $text = str_replace(["\r"], '', $text);
                // Cache
                if ($text !== '') {
                    @file_put_contents($cacheFile, $text);
                }
            }
            if ($text === '' ) { continue; }

            $lower = mb_strtolower($text, 'UTF-8');
            $pos = mb_strpos($lower, $queryLower, 0, 'UTF-8');
            if ($pos === false) { continue; }

            // Build snippet around first match
            $start = max(0, $pos - SNIPPET_CTX);
            $len   = min(mb_strlen($text, 'UTF-8') - $start, (2*SNIPPET_CTX) + mb_strlen($query, 'UTF-8'));
            $snippet = mb_substr($text, $start, $len, 'UTF-8');

            // Escape HTML and highlight query (case-insensitive)
            $safe = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
            $safe = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<mark>$1</mark>', $safe);

            $hits[] = [
                'file' => basename($pdfPath),
                'page' => $p,
                'snippet' => ( ($start > 0 ? '... ' : '') . $safe . ' ...' )
            ];
        }

        if (count($hits) >= $limit) { break; }
    }

    respond(['hits' => $hits]);
} catch (Throwable $e) {
    respond(['error' => $e->getMessage()], 500);
}

