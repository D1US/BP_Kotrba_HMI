<?php
require 'config.php';

$value = $_GET['value'] ?? '';

if ($value !== '0' && $value !== '1') {
    http_response_code(400);
    echo 'Invalid value';
    exit;
}

$result = call_bridge('/mode', 'POST', ['value' => $value]);

if ($result === null) {
    http_response_code(502);
    echo 'Bridge unreachable';
    exit;
}

echo $result;
