<?php
require 'config.php';

// Single endpoint for every momentary/hold control on the page: Start,
// Stop, and all six Jog directions. Mirrors bridge.py's WRITE_SYMBOLS -
// each key here must exist there too, and vice versa.
//
// Mode-lock rule: keys starting with "jog_" require Manual mode;
// everything else (start/stop) requires Auto mode.
$ALLOWED_KEYS = [
    'start', 'stop',
    'jog_x_plus', 'jog_x_minus',
    'jog_y_plus', 'jog_y_minus',
    'jog_z_plus', 'jog_z_minus',
];

$key = $_GET['key'] ?? '';
$state = $_GET['state'] ?? '';

if (!in_array($key, $ALLOWED_KEYS) || ($state !== '0' && $state !== '1')) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

$requiredMode = (strpos($key, 'jog_') === 0) ? '0' : '1'; // 0 = Manual, 1 = Auto

$currentMode = call_bridge('/mode', 'GET');

if ($currentMode === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

if (trim($currentMode) !== $requiredMode) {
    http_response_code(403);
    $modeName = $requiredMode === '1' ? 'Auto' : 'Manual';
    echo "This control only works in $modeName mode";
    exit;
}

$result = call_bridge('/write', 'POST', ['key' => $key, 'state' => $state]);

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

echo $result;
