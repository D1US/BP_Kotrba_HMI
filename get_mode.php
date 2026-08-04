<?php
require 'config.php';
 
header('Content-Type: text/plain; charset=utf-8');
 
$result = call_bridge('/mode', 'GET');
 
if ($result === null) {
    http_response_code(502);
    echo '0'; // safe fallback: report Manual if we can't reach the PLC
    exit;
}
 
echo $result;
?>