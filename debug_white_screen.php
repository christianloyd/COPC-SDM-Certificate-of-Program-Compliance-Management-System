<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['full_name'] = 'System Administrator';
$_SESSION['last_activity'] = time();

ob_start();
try {
    require_once __DIR__ . '/admin/dashboard.php';
    $out = ob_get_clean();
    echo "NO FATAL ERROR. Output was " . strlen($out) . " bytes.\n";
    if (strlen($out) < 100) {
        echo "Output was suspiciously small: " . $out;
    }
} catch (Throwable $t) {
    echo "CAUGHT FATAL ERROR: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine();
}
