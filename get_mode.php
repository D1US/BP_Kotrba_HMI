<?php
require 'config.php';

header('Content-Type: text/plain; charset=utf-8');

$xml = simplexml_load_file($COMMAND_XML_FILE_PATH);

echo (string) $xml->mode;