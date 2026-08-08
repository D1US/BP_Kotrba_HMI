<?php
// save_log.php
// Receives a file uploaded from the frontend (drag-drop or file picker)
// and saves it to a FIXED path/filename, overwriting whatever was there
// before. The PLC always loads from this same fixed path.

require 'config.php';

header('Content-Type: text/plain; charset=utf-8');

// The frontend must send the file under the form field name "uploadedFile".
if (!isset($_FILES['uploadedFile']) || $_FILES['uploadedFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'ERROR: no file received';
    exit;
}

$tmpPath = $_FILES['uploadedFile']['tmp_name'];

// move_uploaded_file both moves AND renames in one step -
// the destination path/filename is entirely controlled by us,
// the original uploaded filename is ignored on purpose.
if (move_uploaded_file($tmpPath, $SAVE_FILE_PATH)) {
    echo 'OK';
} else {
    // Common causes: destination folder doesn't exist yet,
    // or PHP doesn't have write permission on it.
    http_response_code(500);
    echo 'ERROR: could not save file';
}
