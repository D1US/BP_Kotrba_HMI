<?php
// test_ftp.php
//
// Standalone diagnostic - NOT part of the app's normal flow. Connects to
// the IPC's FTP server using config.php's current credentials and reports
// the actual working directory + listing after login, so you can see
// exactly what path to put in $FTP_REMOTE_DIR / $FTP_LOG_REMOTE_DIR
// instead of guessing how "\Hard Disk\ftp-hmi" translates over FTP.
//
// Run it directly in a browser: http://<pi-ip>:8080/test_ftp.php
//
// DELETE THIS FILE once you've confirmed the correct paths - it's a
// debugging aid only, not meant to stay in a production deployment
// (it reveals your FTP server's folder structure to anyone who can
// reach this URL).

require __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "Connecting to $FTP_HOST:$FTP_PORT as $FTP_USERNAME ...\n\n";

$conn = @ftp_connect($FTP_HOST, $FTP_PORT, 5);
if ($conn === false) {
    echo "FAILED to connect.\n";
    echo "Check \$FTP_HOST / \$FTP_PORT in config.php, and that the Pi can\n";
    echo "actually reach the IPC on the network (try: ping $FTP_HOST).\n";
    exit;
}

if (!@ftp_login($conn, $FTP_USERNAME, $FTP_PASSWORD)) {
    echo "Connected, but FAILED to log in.\n";
    echo "Check \$FTP_USERNAME / \$FTP_PASSWORD in config.php.\n";
    ftp_close($conn);
    exit;
}

echo "Connected and logged in OK.\n\n";

ftp_pasv($conn, true);

$pwd = ftp_pwd($conn);
echo "Initial working directory after login: " . ($pwd !== false ? $pwd : '(could not determine)') . "\n";
echo "(This tells you whether the FTP account is 'jailed' to a subfolder -\n";
echo " if this already shows something like /ftp-hmi, then \$FTP_REMOTE_DIR\n";
echo " should be relative to THIS, e.g. just '/', not the full Hard Disk path.)\n\n";

echo "Contents of that directory:\n";
$list = ftp_nlist($conn, '.');
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
echo "\nIf you see 'Hard Disk' in that listing instead, try browsing INTO it\n";
echo "by editing this script's ftp_nlist('.') call to ftp_nlist('Hard Disk')\n";
echo "and reloading, to find ftp-hmi one level deeper.\n";

ftp_close($conn);
?>
