<?php
// clear_log.php
// Empties the log file. Called when the user presses the "Clear" button
// (e.g. before starting a new machine run).

require 'config.php';

header('Content-Type: text/plain; charset=utf-8');

// file_put_contents with an empty string overwrites the file with nothing.
// If the file doesn't exist yet, this creates it empty.
$result = file_put_contents($LOG_FILE_PATH, '');

if ($result !== false) {
    echo 'OK';
} else {
    // Most common cause: folder/file permissions don't allow PHP to write.
    http_response_code(500);
    echo 'ERROR: could not clear log file';
}
