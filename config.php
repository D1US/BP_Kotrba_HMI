<?php
$LOG_FILE_PATH  = __DIR__ . '/data/current.log';
$SAVE_FILE_PATH = __DIR__ . '/data/saved/plc_upload.nc';
 
// Uses the Docker Compose service name, not localhost — containers on the
// same Compose network reach each other by service name, not 127.0.0.1.
// Change 'bridge' below if your service is named differently in docker-compose.yml.
$BRIDGE_URL = 'http://bridge:5000';
 
/**
 * Calls the local Python ADS bridge and returns the raw response body.
 * Returns null on failure (connection refused, timeout, etc.) so callers
 * can distinguish "bridge unreachable" from "bridge returned an error".
 */
function call_bridge($path, $method = 'GET', $params = []) {
    global $BRIDGE_URL;
 
    $url = $BRIDGE_URL . $path;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
 
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // fail fast if the bridge/PLC is unreachable
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }
 
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
 
    if ($response === false || $httpCode >= 400) {
        return null;
    }
 
    return $response;
}
?>