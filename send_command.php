<?php
require 'config.php';

$button = $_GET['button'] ?? '';
$allowed = ['start', 'stop'];

if (!in_array($button, $allowed)) {
    http_response_code(400);
    echo 'Invalid button';
    exit;
}

$xml = simplexml_load_file($COMMAND_XML_FILE_PATH);

if ((string) $xml->mode !== '1') {
    http_response_code(403);
    echo 'Start/Stop only allowed in Auto mode';
    exit;
}

$xml->$button = 1;
$xml->asXML($COMMAND_XML_FILE_PATH);

echo 'OK';
?>