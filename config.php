<?php
$LOG_FILE_PATH  = __DIR__ . '/data/current.log';
$SAVE_FILE_PATH = __DIR__ . '/data/saved/plc_upload.nc';

// The bridge container uses network_mode: host (required for stable ADS
// networking), so it does NOT sit on the compose network - it can't be
// reached by service name. Instead, reach it via the Pi's host network
// through Docker's host.docker.internal alias (see extra_hosts in
// docker-compose.yml for the 'web' service).
$BRIDGE_URL = 'http://host.docker.internal:5000';

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
