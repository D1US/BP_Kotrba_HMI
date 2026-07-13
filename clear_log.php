<?php
require 'config.php'

if (file_exists($LOG_FILE_PATH)){
    file_put_contents($LOG_FILE_PATH, '');

}

echo 'OK'
?>
