<?php

/**
 * Sanitize output for HTML context (XSS prevention)
 */
function h($string) {
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Safely access an array variable
 */
function e($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Generate a safe unique filename avoiding directory traversal
 */
function getSafeFileName($originalName, $ext) {
    // Keep only alphanumeric, dash, dot, and underscore
    $sanitized = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $originalName);
    // Remove the extension to avoid duplicate extensions
    $sanitized = pathinfo($sanitized, PATHINFO_FILENAME);
    
    // Format: YYYYMMDD_HEX_Sanitized.ext
    $dateStr = date('Ymd');
    $hex = bin2hex(random_bytes(4)); // 8 random characters
    
    return sprintf('%s_%s_%s.%s', $dateStr, $hex, $sanitized, strtolower($ext));
}

/**
 * Formats file size nicely
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Store a flash notification for the next request.
 */
function setFlashMessage($type, $message, $title = '') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['flash_messages'][] = [
        'type' => $type ?: 'info',
        'title' => $title,
        'message' => $message,
    ];
}

/**
 * Return and clear all flash notifications.
 */
function getFlashMessages() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

/**
 * Standard list of Philippine Regions
 */
function getPHRegions() {
    return [
        'NCR', 'CAR', 'Region I', 'Region II', 'Region III', 'Region IV-A', 
        'Region IV-B', 'Region V', 'Region VI', 'Region VII', 'Region VIII', 
        'Region IX', 'Region X', 'Region XI', 'Region XII', 'Region XIII', 
        'Region XVIII (NIR)', 'BARMM'
    ];
}
