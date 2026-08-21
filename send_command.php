<?php
require 'config.php';
 
$button = $_GET['button'] ?? '';
$state = $_GET['state'] ?? '';
$allowed = ['start', 'stop'];
 
if (!in_array($button, $allowed) || ($state !== '0' && $state !== '1')) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}
 
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
 
$result = call_bridge('/command', 'POST', ['button' => $button, 'state' => $state]);
 
if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}
 
echo $result;
?>
