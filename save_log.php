<?php
require 'config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_FILES['uploadedFile']) || $_FILES['uploadedFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'ERROR: no file received';
    exit;
}

$tmpPath = $_FILES['uploadedFile']['tmp_name'];

if (move_uploaded_file($tmpPath, $SAVE_FILE_PATH)) {
    echo 'OK';
} else {
    http_response_code(500);
    echo 'ERROR: could not save file';
}
