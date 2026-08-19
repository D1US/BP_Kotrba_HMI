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
$BACKEND_MODE = 'bridge';   // 'bridge' | 'mock'

// BRIDGE_ENV: only matters when BACKEND_MODE = 'bridge'. Picks which
// host to reach the bridge on.
//   'docker' -> web app running in its Docker container, bridge reachable
//               via host.docker.internal (see extra_hosts in docker-compose.yml)
//   'local'  -> everything (this web app AND bridge.py) running directly
//               on the same machine, no Docker involved -> use localhost
$BRIDGE_ENV = 'docker';     // 'docker' | 'local'

// Where the mock state file lives when BACKEND_MODE = 'mock'.
// Auto-created with sane defaults (mode=manual, position=0,0,0) the
// first time it's needed, so you don't have to create it by hand.
$MOCK_STATE_FILE = __DIR__ . '/data/mock_state.xml';

$BRIDGE_HOST = ($BRIDGE_ENV === 'docker') ? 'host.docker.internal' : 'localhost';
$BRIDGE_URL  = 'http://' . $BRIDGE_HOST . ':5000';

// =====================================================================
// FTP (machine controller file transfer)
// TODO: replace these with the real FTP host/credentials once known.
// =====================================================================
$FTP_HOST            = 'ftp.machine.local'; // TODO: real FTP host/IP of the machine controller
$FTP_PORT             = 21;
$FTP_USERNAME         = 'ftpuser';          // TODO: real FTP username
$FTP_PASSWORD         = 'ftppassword';      // TODO: real FTP password

// Where the .nc program gets uploaded TO on the controller (see save_file.php)
$FTP_REMOTE_DIR       = '/';                // TODO: remote directory to upload into
$FTP_REMOTE_FILENAME  = 'plc_upload.nc';    // filename the machine controller expects

// Where the machine log gets pulled FROM on the controller (see sync_log.php).
// The controller (Windows CE 6.0 IPC) writes its own log there; this app
// doesn't write to it, only reads it periodically via FTP.
$FTP_LOG_REMOTE_DIR      = '/';             // TODO: remote directory the log file lives in
$FTP_LOG_REMOTE_FILENAME = 'machine.log';   // TODO: real log filename on the IPC

/**
 * Opens an FTP connection and logs in. Returns the connection resource,
 * or false on failure. Shared by the upload and download helpers below
 * so the connect/login/passive-mode logic only lives in one place.
 */
function ftp_connect_and_login() {
    global $FTP_HOST, $FTP_PORT, $FTP_USERNAME, $FTP_PASSWORD;

    $conn = @ftp_connect($FTP_HOST, $FTP_PORT, 5);
    if ($conn === false) {
        return false;
    }

    if (!@ftp_login($conn, $FTP_USERNAME, $FTP_PASSWORD)) {
        ftp_close($conn);
        return false;
    }

    // Most machine controllers sit behind NAT/firewalls that only allow
    // the client to initiate the data connection too, so passive mode.
    ftp_pasv($conn, true);

    return $conn;
}

/**
 * Uploads a local file to the machine controller's FTP server.
 * Returns true on success, false on failure.
 *
 * In mock mode (BACKEND_MODE = 'mock') this is skipped entirely and
 * always returns true, since there's no real controller/FTP server to
 * talk to while testing.
 */
function ftp_upload_file($localPath) {
    global $FTP_REMOTE_DIR, $FTP_REMOTE_FILENAME, $BACKEND_MODE;

    if ($BACKEND_MODE === 'mock') {
        return true;
    }

    $conn = ftp_connect_and_login();
    if ($conn === false) {
        return false;
    }

    $remotePath = rtrim($FTP_REMOTE_DIR, '/') . '/' . $FTP_REMOTE_FILENAME;
    $ok = @ftp_put($conn, $remotePath, $localPath, FTP_BINARY);

    ftp_close($conn);

    return $ok;
}

/**
 * Downloads the machine log from the controller's FTP server and writes
 * it to $LOG_FILE_PATH, overwriting whatever was there before. This is
 * meant to be called periodically by sync_log.php (a background process),
 * NOT on every request - get_log.php just reads the local copy this
 * leaves behind, so the frontend's 1-second polling never touches the
 * network itself.
 *
 * Returns true on success, false on failure. Skipped (returns true) in
 * mock mode, same as ftp_upload_file().
 */
function ftp_download_log_file() {
    global $FTP_LOG_REMOTE_DIR, $FTP_LOG_REMOTE_FILENAME, $LOG_FILE_PATH, $BACKEND_MODE;

    if ($BACKEND_MODE === 'mock') {
        return true;
    }

    $conn = ftp_connect_and_login();
    if ($conn === false) {
        return false;
    }

    $remotePath = rtrim($FTP_LOG_REMOTE_DIR, '/') . '/' . $FTP_LOG_REMOTE_FILENAME;

    $dir = dirname($LOG_FILE_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    // Log is plain text, so ASCII mode (handles line-ending translation
    // correctly, unlike the binary .nc transfer above).
    $ok = @ftp_get($conn, $LOG_FILE_PATH, $remotePath, FTP_ASCII);

    ftp_close($conn);

    return $ok;
}

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

        case '/clear_log':
            if ($method === 'POST') {
                // Nothing to pulse in mock mode - just acknowledge it.
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

        case '/settings':
            if ($method === 'GET') {
                return json_encode([
                    'thickness' => (float)$state->settings['thickness'],
                    'speed' => (float)$state->settings['speed'],
                ]);
            }
            if ($method === 'POST') {
                $key = $params['key'] ?? '';
                $value = $params['value'] ?? '';
                if (!in_array($key, ['thickness', 'speed']) || !is_numeric($value) || (float)$value < 0) {
                    return null;
                }
                $state->settings[$key] = $value;
                mock_state_save($MOCK_STATE_FILE, $state);
                return 'OK';
            }
            break;
    }

    return null;
}

function mock_state_load($path) {
    if (!file_exists($path)) {
        $default = new SimpleXMLElement(
            '<state><mode>0</mode><position x="0.000" y="0.000" z="0.000"/><axis x="0" y="0" z="0"/><settings thickness="0.0" speed="0.0"/></state>'
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

    // Backward-compat: older mock_state.xml files won't have a <settings>
    // node yet either - add one with defaults.
    if (!isset($state->settings)) {
        $settingsNode = $state->addChild('settings');
        $settingsNode->addAttribute('thickness', '0.0');
        $settingsNode->addAttribute('speed', '0.0');
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
