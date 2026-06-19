<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/config.php';

// Destroy all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Set success message
setFlash('success', 'You have been successfully logged out.');

// Redirect to home page
header('Location: ' . BASE_URL . 'index.php');
exit;
?>