<?php
require 'config.php';

header('Content-Type: application/json');

$xml = simplexml_load_file($COMMAND_XML_FILE_PATH);

echo json_encode([
    'x' => (string) $xml->pos_x,
    'y' => (string) $xml->pos_y,
    'z' => (string) $xml->pos_z,
]);
?>