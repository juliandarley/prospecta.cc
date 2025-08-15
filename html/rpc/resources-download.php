<?php
// Secure-ish PDF streaming proxy (no direct disk exposure under web root)
// TODO: integrate real auth/ACL before production use

// Base directory for stored PDFs (outside web root)
$baseDir = '/var/www/prospecta.cc/data/pdfs';

// Simple allowlist for filename: basename only, .pdf extension
function safe_name(string $name): ?string {
    $name = trim($name);
    if ($name === '') return null;
    if ($name !== basename($name)) return null; // no path traversal
    if (!preg_match('/\.pdf\z/i', $name)) return null;
    return $name;
}

$name = isset($_GET['name']) ? safe_name($_GET['name']) : null;
if ($name === null) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'bad or missing name']);
    exit;
}

$path = $baseDir . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not found']);
    exit;
}

// Optional: force download with dl=1
$download = isset($_GET['dl']) && $_GET['dl'] === '1';

// Serve with range support for pdf viewers
$size = filesize($path);
$start = 0; $end = $size - 1; $status = 200;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
    $start = (int)$m[1];
    if ($m[2] !== '') { $end = (int)$m[2]; }
    if ($end >= $size) { $end = $size - 1; }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

// Headers
if ($status === 206) {
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Type: application/pdf');
header('Accept-Ranges: bytes');
header('Content-Length: ' . (($end - $start) + 1));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode($name) . '"');

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
if ($start > 0) { fseek($fp, $start); }

$chunk = 8192;
$left = ($end - $start) + 1;
while ($left > 0 && !feof($fp)) {
    $read = ($left > $chunk) ? $chunk : $left;
    $buf = fread($fp, $read);
    if ($buf === false) break;
    echo $buf;
    $left -= strlen($buf);
    // Optional: flush
    if (function_exists('fastcgi_finish_request')) { /* noop here */ }
}
fclose($fp);
exit;


