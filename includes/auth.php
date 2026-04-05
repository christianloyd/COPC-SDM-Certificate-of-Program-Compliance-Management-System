<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_start();

/**
 * Ensures the user is logged in, redirecting to login if not
 */
function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Last request was more than X time ago
        session_unset();     // unset $_SESSION variable for the run-time 
        session_destroy();   // destroy session data in storage
        header('Location: ' . BASE_URL . '/login.php?error=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time(); // update last activity time stamp
}

/**
 * Generates a CSRF token for forms
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforces CSRF check on state-changing requests (POST, PUT, DELETE)
 */
function requireCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}
