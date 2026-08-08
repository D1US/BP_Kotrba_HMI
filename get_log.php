<?php
// get_log.php
// Returns the current contents of the log file as plain text.
// Called repeatedly (every 1s) by the frontend via fetch().

require 'config.php';

header('Content-Type: text/plain; charset=utf-8');
// Prevent any caching - we always want the freshest content.
header('Cache-Control: no-store, no-cache, must-revalidate');

if (file_exists($LOG_FILE_PATH)) {
    // file_get_contents reads the whole file in one go.
    // Fine for small/medium logs. If logs ever get huge, this would
    // need to change to reading only the tail (last N bytes).
    echo file_get_contents($LOG_FILE_PATH);
} else {
    // No log file yet (e.g. before the PLC program has run) -
    // just return empty, not an error, so the frontend doesn't break.
    echo '';
}
