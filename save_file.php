<?php
require 'config.php';

if(!isset($_FILES['file'])){
    http_response_code(400);
    echo 'No file recieved';
    exit;
}

$originalName = $_FILES['file']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($extension !== 'nc') {
    http_response_code(400);
    echo 'Only .nc files are allowed';
    exit;
}

$uploaded = $_FILES['file']['tmp_name'];

// The destination folder isn't created automatically by move_uploaded_file(),
// so make sure it exists first (same pattern used elsewhere in config.php
// for the mock state file and the synced log file).
$saveDir = dirname($SAVE_FILE_PATH);
if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true) && !is_dir($saveDir)) {
    http_response_code(500);
    echo 'Save failed: could not create destination folder';
    exit;
}

if (!@move_uploaded_file($uploaded, $SAVE_FILE_PATH)) {
    $error = error_get_last();
    $reason = $error['message'] ?? 'unknown reason';
    http_response_code(500);
    echo 'Save failed: ' . $reason;
    exit;
}

if (!ftp_upload_file($SAVE_FILE_PATH)) {
    http_response_code(502);
    echo 'ERROR: saved locally but FTP upload to machine controller failed';
    exit;
}

echo 'OK';
?>