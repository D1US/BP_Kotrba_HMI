<?php
require 'config.php';

$value = $_GET['value'] ?? '';

if($value !== '0' && $value !== '1') {
    http_response_code(400);
    echo 'Invalid value';
    exit;
}

$xml = simplexml_load_file($COMMAND_XML_FILE_PATH);
$xml->mode = $value;
$xml->asXML($COMMAND_XML_FILE_PATH);

echo ('OK');
?>