<?php
require 'config.php';

$key = $_GET['key'] ?? '';
$value = $_GET['value'] ?? '';

$validKeys = ['thickness', 'speed'];

if (!in_array($key, $validKeys) || !is_numeric($value) || (float)$value < 0) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

$result = call_bridge('/settings', 'POST', ['key' => $key, 'value' => $value]);

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

echo $result;
?>
