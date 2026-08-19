<?php
require 'config.php';

// The log is authored by the IPC and just mirrored locally by
// sync_log.php, so clearing it has to happen on the PLC side - it
// wipes its own log file when it sees this rising-edge pulse.
$result = call_bridge('/clear_log', 'POST');

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

if (trim($result) !== 'OK') {
    http_response_code(502);
    echo 'Clear failed: ' . $result;
    exit;
}

// Also clear the local mirrored copy right away, so the UI reflects the
// clear immediately instead of waiting for the next sync_log.php cycle.
if (file_exists($LOG_FILE_PATH)) {
    file_put_contents($LOG_FILE_PATH, '');
}

echo 'OK';
?>
