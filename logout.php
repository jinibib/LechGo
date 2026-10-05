<?php
/**
 * Nuclear Logout - Destroys EVERYTHING
 */

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// FIRST - Clear the error message before anything else
unset($_SESSION['error']);
unset($_SESSION['warning']);
unset($_SESSION['info']);

// Get session ID before destroying
$session_id = session_id();

// Clear all session variables
$_SESSION = array();

// If using cookies for session, expire the cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
    setcookie(session_name(), '', time() - 3600, '/', '', false, true);
}

// Destroy the session
@session_destroy();

// Also try to delete PHPSESSID cookie explicitly
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
    unset($_COOKIE['PHPSESSID']);
}

// Clear any other cookies that might exist
foreach ($_COOKIE as $cookie_name => $cookie_value) {
    if (strpos($cookie_name, 'sess') !== false || strpos($cookie_name, 'PHPSESS') !== false) {
        setcookie($cookie_name, '', time() - 3600, '/');
        unset($_COOKIE[$cookie_name]);
    }
}

// Force browser to not cache this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to ROOT (which will load landing page without the /landing route)
header('Location: /?loggedout=' . time());
exit;
