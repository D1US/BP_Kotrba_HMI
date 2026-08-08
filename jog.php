<?php
require 'config.php';

$axis = $_GET['axis'] ?? '';
$dir = $_GET['dir'] ?? '';
$state = $_GET['state'] ?? '';

$validAxes = ['x', 'y', 'z'];
$validDirs = ['plus', 'minus'];

if (!in_array($axis, $validAxes) || !in_array($dir, $validDirs) || ($state !== '0' && $state !== '1')) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

// Enforce mode lock: jog only allowed in Manual mode
$currentMode = call_bridge('/mode', 'GET');

if ($currentMode === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

if (trim($currentMode) !== '0') {
    http_response_code(403);
    echo 'Jog only allowed in Manual mode';
    exit;
}

$result = call_bridge('/jog', 'POST', ['axis' => $axis, 'dir' => $dir, 'state' => $state]);

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

echo $result;
