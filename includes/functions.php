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
