<?php
require 'config.php';

$axis = $_GET['axis'] ?? '';
$value = $_GET['value'] ?? '';

$validAxes = ['x', 'y', 'z'];

if (!in_array($axis, $validAxes) || ($value !== '0' && $value !== '1')) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

// Enforce mode lock: axis toggle only allowed in Manual mode, same as jog
$currentMode = call_bridge('/mode', 'GET');

if ($currentMode === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

if (trim($currentMode) !== '0') {
    http_response_code(403);
    echo 'Axis toggle only allowed in Manual mode';
    exit;
}

$result = call_bridge('/axis', 'POST', ['axis' => $axis, 'value' => $value]);

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

echo $result;
?>
