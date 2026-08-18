<?php
$LOG_FILE_PATH  = __DIR__ . '/data/current.log';
$SAVE_FILE_PATH = __DIR__ . '/data/saved/plc_upload.nc';

// =====================================================================
// TESTING / ENVIRONMENT SWITCHES
// Flip these two while developing - nothing else in the codebase needs
// to change. All the *.php endpoint files just call call_bridge() and
// don't care which mode it's actually running in.
// =====================================================================

// BACKEND_MODE:
//   'bridge' -> talk to the real Python ADS bridge (production, needs
//               bridge.py running and a PLC reachable)
//   'mock'   -> no bridge/PLC needed at all. Mode, position, and jog
//               state are read from and written to a local XML file
//               (see $MOCK_STATE_FILE below), so you can click around
//               the whole frontend on its own.
$BACKEND_MODE = 'mock';   // 'bridge' | 'mock'

// BRIDGE_ENV: only matters when BACKEND_MODE = 'bridge'. Picks which
// host to reach the bridge on.
//   'docker' -> web app running in its Docker container, bridge reachable
//               via host.docker.internal (see extra_hosts in docker-compose.yml)
//   'local'  -> everything (this web app AND bridge.py) running directly
//               on the same machine, no Docker involved -> use localhost
$BRIDGE_ENV = 'local';     // 'docker' | 'local'

// Where the mock state file lives when BACKEND_MODE = 'mock'.
// Auto-created with sane defaults (mode=manual, position=0,0,0) the
// first time it's needed, so you don't have to create it by hand.
$MOCK_STATE_FILE = __DIR__ . '/data/mock_state.xml';

$BRIDGE_HOST = ($BRIDGE_ENV === 'docker') ? 'host.docker.internal' : 'localhost';
$BRIDGE_URL  = 'http://' . $BRIDGE_HOST . ':5000';

/**
 * Calls the local Python ADS bridge and returns the raw response body.
 * Returns null on failure (connection refused, timeout, etc.) so callers
 * can distinguish "bridge unreachable" from "bridge returned an error".
 *
 * When $BACKEND_MODE = 'mock', this transparently delegates to
 * mock_bridge() instead of making a real HTTP call, using the exact
 * same $path / $method / $params contract as the real bridge routes.
 */
function call_bridge($path, $method = 'GET', $params = []) {
    global $BRIDGE_URL, $BACKEND_MODE;

    if ($BACKEND_MODE === 'mock') {
        return mock_bridge($path, $method, $params);
    }

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

// =====================================================================
// MOCK BACKEND (BACKEND_MODE = 'mock')
// Simulates the same /mode, /position, /command, /jog routes that
// bridge.py exposes, but backed by a small XML file instead of a real
// PLC. Good enough to click through Start/Stop, Mode, and jogging
// (jogging nudges the mock position so movement is visible) without
// any hardware or Docker container running.
// =====================================================================

function mock_bridge($path, $method, $params) {
    global $MOCK_STATE_FILE;

    $state = mock_state_load($MOCK_STATE_FILE);

    switch ($path) {
        case '/mode':
            if ($method === 'GET') {
                return (string)$state->mode;
            }
            if ($method === 'POST') {
                $value = $params['value'] ?? '';
                if ($value !== '0' && $value !== '1') {
                    return null;
                }
                $state->mode = $value;
                mock_state_save($MOCK_STATE_FILE, $state);
                return 'OK';
            }
            break;

        case '/position':
            if ($method === 'GET') {
                return json_encode([
                    'x' => (float)$state->position['x'],
                    'y' => (float)$state->position['y'],
                    'z' => (float)$state->position['z'],
                ]);
            }
            break;

        case '/command':
            if ($method === 'POST') {
                // No real motion to drive in mock mode - just acknowledge it,
                // same as the PLC would after writing BSTART/BSTOP.
                return 'OK';
            }
            break;

        case '/jog':
            if ($method === 'POST') {
                $axis  = $params['axis'] ?? '';
                $dir   = $params['dir'] ?? '';
                $held  = $params['state'] ?? '';
                $validAxes = ['x', 'y', 'z'];
                $validDirs = ['plus', 'minus'];

                if (!in_array($axis, $validAxes) || !in_array($dir, $validDirs) || ($held !== '0' && $held !== '1')) {
                    return null;
                }

                // Nudge the mock position on press so you can see movement
                // in the UI while testing (release does nothing, matching
                // the "hold to move" behaviour of the real jog buttons).
                if ($held === '1') {
                    $step = ($dir === 'plus') ? 0.1 : -0.1;
                    $state->position[$axis] = (float)$state->position[$axis] + $step;
                    mock_state_save($MOCK_STATE_FILE, $state);
                }
                return 'OK';
            }
            break;

        case '/axis':
            if ($method === 'GET') {
                return json_encode([
                    'x' => (string)$state->axis['x'],
                    'y' => (string)$state->axis['y'],
                    'z' => (string)$state->axis['z'],
                ]);
            }
            if ($method === 'POST') {
                $axis = $params['axis'] ?? '';
                $value = $params['value'] ?? '';
                if (!in_array($axis, ['x', 'y', 'z']) || ($value !== '0' && $value !== '1')) {
                    return null;
                }
                $state->axis[$axis] = $value;
                mock_state_save($MOCK_STATE_FILE, $state);
                return 'OK';
            }
            break;

        case '/axis_action':
            if ($method === 'POST') {
                $axis = $params['axis'] ?? '';
                $action = $params['action'] ?? '';
                $held = $params['state'] ?? '';
                $validAxes = ['x', 'y', 'z'];
                $validActions = ['reset', 'stop'];

                if (!in_array($axis, $validAxes) || !in_array($action, $validActions) || ($held !== '0' && $held !== '1')) {
                    return null;
                }

                // For testing convenience: pressing Reset zeroes that axis's
                // mock position. Stop has no visible effect in mock mode
                // since there's no real motion to interrupt.
                if ($held === '1' && $action === 'reset') {
                    $state->position[$axis] = '0.000';
                    mock_state_save($MOCK_STATE_FILE, $state);
                }
                return 'OK';
            }
            break;
    }

    return null;
}

function mock_state_load($path) {
    if (!file_exists($path)) {
        $default = new SimpleXMLElement(
            '<state><mode>0</mode><position x="0.000" y="0.000" z="0.000"/><axis x="0" y="0" z="0"/></state>'
        );
        mock_state_save($path, $default);
        return $default;
    }

    $state = simplexml_load_file($path);

    // Backward-compat: older mock_state.xml files (from before axis toggles
    // existed) won't have an <axis> node yet - add one with defaults so
    // reads/writes below don't fail.
    if (!isset($state->axis)) {
        $axisNode = $state->addChild('axis');
        $axisNode->addAttribute('x', '0');
        $axisNode->addAttribute('y', '0');
        $axisNode->addAttribute('z', '0');
        mock_state_save($path, $state);
    }

    return $state;
}

function mock_state_save($path, $state) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $state->asXML($path);
}
?>
