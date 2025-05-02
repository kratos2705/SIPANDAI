<?php
session_start();

// Include necessary files
require_once '../includes/functions.php';

// Clear all session variables
$_SESSION = array();

// If session uses cookies, clear them
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear any remember me tokens if they exist
if (isset($_COOKIE['remember_token'])) {
    require_once '../config/koneksi.php';
    
    $token = $_COOKIE['remember_token'];
    $query = "DELETE FROM remember_tokens WHERE token = '$token'";
    mysqli_query($koneksi, $query);
    
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to home page with success message
$_SESSION['logout_success'] = 'Anda berhasil logout';
redirect('../index.php?logout=success');
exit;
?>