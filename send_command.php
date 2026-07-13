<?php
require 'config.php'

$button = $_GET['button'] ?? '';
$allowed = ['start','stop'];

if(!in_array($button, $allowed)) {
    http_response_code(400);
    echo 'Invlid button';
    exit;
    }

$xml = simplexml_load_file($COMMAND_XML_FILE_PATH);
$xml->$button = 1;
$xml->asXML($COMMAND_XML_FILE_PATH);

echo 'OK'

?>