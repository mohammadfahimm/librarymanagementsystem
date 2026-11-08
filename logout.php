<?php
/**
 * Premier University Library — Logout Handler
 */
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header('Location: index.php');
exit;
