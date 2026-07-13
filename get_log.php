<?php
require 'config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!file_exists($LOG_FILE_PATH)){
    echo '';
    exit;
    }

echo file_get_contents($LOG_FILE_PATH)
?>