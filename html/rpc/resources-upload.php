<?php
header('Content-Type: application/json; charset=utf-8');

$destDir = '/var/www/prospecta.cc/data/pdfs';
if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }

try {
    if (!isset($_FILES['file'])) {
        throw new RuntimeException('No file field in request');
    }
    $err = (int)$_FILES['file']['error'];
    if ($err !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL    => 'Partial upload',
            UPLOAD_ERR_NO_FILE    => 'No file sent',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp dir',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
        ];
        $msg = $map[$err] ?? ('Upload error code ' . $err);
        // Also include post_max_size, which can truncate silently
        $msg .= ' (post_max_size ' . ini_get('post_max_size') . ')';
        throw new RuntimeException($msg);
    }
    $tmp = $_FILES['file']['tmp_name'];
    $orig = basename($_FILES['file']['name']);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { throw new RuntimeException('Only PDF allowed'); }

    // Compute SHA256 to detect content duplicates
    $sha256 = hash_file('sha256', $tmp);
    $name = $orig;
    $dest = $destDir . '/' . $name;
    $renamed = false;
    if (file_exists($dest)) {
        // If same content, accept but force a unique name; else just unique name
        $base = pathinfo($orig, PATHINFO_FILENAME);
        $i = 1;
        do {
            $name = $base . '-' . $i . '.pdf';
            $dest = $destDir . '/' . $name;
            $i++;
        } while (file_exists($dest));
        $renamed = true;
    }
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Failed to store file');
    }
    echo json_encode([
        'filename' => $name,
        'sha256' => $sha256,
        'renamed' => $renamed
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}


