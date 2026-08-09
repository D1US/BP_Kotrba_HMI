<?php
require 'config.php';
 
header('Content-Type: application/json');
 
$result = call_bridge('/position', 'GET');
 
if ($result === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Bridge unreachable']);
    exit;
}
 
echo $result;
?>
