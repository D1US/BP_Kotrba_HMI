<?php
// test_ftp.php
//
// Standalone diagnostic - NOT part of the app's normal flow. Uses the
// SAME ftp_connect_and_login() helper config.php's real upload/download
// functions use, so this test reflects actual app behavior (passive
// mode setting, timeout, credentials) instead of a separate hardcoded
// copy that can drift out of sync.
//
// Run it directly in a browser: http://<pi-ip>:8080/test_ftp.php
//
// DELETE THIS FILE once you've confirmed the correct paths - it's a
// debugging aid only (it reveals your FTP server's folder structure to
// anyone who can reach this URL).

require __DIR__ . '/config.php';

header('Content-Type: text/plain');

global $FTP_HOST, $FTP_PORT, $FTP_USERNAME, $FTP_PASSIVE_MODE;

echo "Connecting to $FTP_HOST:$FTP_PORT as $FTP_USERNAME ";
echo "(passive mode: " . ($FTP_PASSIVE_MODE ? 'ON' : 'OFF') . ") ...\n\n";

$conn = ftp_connect_and_login();

if (is_string($conn)) {
    // ftp_connect_and_login() returns a description string on failure,
    // an actual connection resource on success.
    echo "FAILED: $conn\n";
    exit;
}

echo "Connected and logged in OK.\n\n";

$pwd = @ftp_pwd($conn);
echo "Initial working directory after login: ";
echo ($pwd !== false ? $pwd : '(could not determine / timed out)') . "\n";
echo "(This tells you whether the FTP account is 'jailed' to a subfolder -\n";
echo " if this already shows something like /ftp-hmi, then \$FTP_REMOTE_DIR\n";
echo " should be relative to THIS, e.g. just '/', not the full Hard Disk path.)\n\n";

echo "Contents of that directory:\n";
$list = @ftp_nlist($conn, '.');
if ($list === false || empty($list)) {
    echo "  (empty, or could not list - try browsing manually with an FTP client\n";
    echo "   like FileZilla or WinSCP using the same credentials to compare)\n";
} else {
    foreach ($list as $entry) {
        echo "  $entry\n";
    }
}

echo "\nIf you see 'ftp-hmi' in that listing, your remote dir is likely:\n";
echo "  " . rtrim($pwd, '/') . "/ftp-hmi\n";
echo "\nIf you see 'Hard Disk' in that listing instead, that's the raw device\n";
echo "root - the path would be /Hard Disk/ftp-hmi (forward slashes, keep the space).\n";

@ftp_close($conn);
echo "\nDone.\n";
?>
