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

$xml = simplexml_load_file($COMMAND_XML_FILE_PATH);

if ((string) $xml->mode !== '0') {
    http_response_code(403);
    echo 'Jog only allowed in Manual mode';
    exit;
}

$field = "jog_{$axis}_{$dir}";
$xml->$field = $state;
$xml->asXML($COMMAND_XML_FILE_PATH);

echo 'OK';
?>