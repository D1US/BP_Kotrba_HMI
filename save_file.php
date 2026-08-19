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

if (!move_uploaded_file($uploaded, $SAVE_FILE_PATH)) {
    http_response_code(500);
    echo 'Save failed';
    exit;
}

if (!ftp_upload_file($SAVE_FILE_PATH)) {
    http_response_code(502);
    echo 'ERROR: saved locally but FTP upload to machine controller failed';
    exit;
}

echo 'OK';
?>