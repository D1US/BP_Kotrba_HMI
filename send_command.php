<?php
require 'config.php';
 
$button = $_GET['button'] ?? '';
$allowed = ['start', 'stop'];
 
if (!in_array($button, $allowed)) {
    http_response_code(400);
    echo 'Invalid button';
    exit;
}
 
// Enforce mode lock: Start/Stop only allowed in Auto mode
$currentMode = call_bridge('/mode', 'GET');
 
if ($currentMode === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}
 
if (trim($currentMode) !== '1') {
    http_response_code(403);
    echo 'Start/Stop only allowed in Auto mode';
    exit;
}
 
$result = call_bridge('/command', 'POST', ['button' => $button]);
 
if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}
 
echo $result;
?>