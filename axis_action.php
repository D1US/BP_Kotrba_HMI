<?php
require 'config.php';

$axis = $_GET['axis'] ?? '';
$action = $_GET['action'] ?? '';
$state = $_GET['state'] ?? '';

$validAxes = ['x', 'y', 'z'];
$validActions = ['reset', 'stop'];

if (!in_array($axis, $validAxes) || !in_array($action, $validActions) || ($state !== '0' && $state !== '1')) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

// Enforce mode lock: axis reset/stop only allowed in Manual mode, same as jog
$currentMode = call_bridge('/mode', 'GET');

if ($currentMode === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

if (trim($currentMode) !== '0') {
    http_response_code(403);
    echo 'Axis reset/stop only allowed in Manual mode';
    exit;
}

$result = call_bridge('/axis_action', 'POST', ['axis' => $axis, 'action' => $action, 'state' => $state]);

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

echo $result;
?>
