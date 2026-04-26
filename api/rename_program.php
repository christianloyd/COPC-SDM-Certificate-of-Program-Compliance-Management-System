<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
    exit;
}

$newName   = trim($_POST['new_name'] ?? '');
// Support both single rename and multi-merge
$oldNames  = [];
if (!empty($_POST['old_names']) && is_array($_POST['old_names'])) {
    $oldNames = array_values(array_filter(array_map('trim', $_POST['old_names'])));
} elseif (!empty($_POST['old_name'])) {
    $oldNames = [trim($_POST['old_name'])];
}

if (empty($oldNames) || $newName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Program name(s) and new name are required.']);
    exit;
}

// Remove the target from old list (no-op rename)
$oldNames = array_values(array_filter($oldNames, fn($n) => $n !== $newName));

if (empty($oldNames)) {
    echo json_encode(['success' => false, 'error' => 'Nothing to rename — old and new names are identical.']);
    exit;
}

try {
    $pdo = getDBConnection();

    $placeholders = implode(',', array_fill(0, count($oldNames), '?'));

    // Count how many records will be touched
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM copc_documents WHERE program IN ($placeholders)");
    $countStmt->execute($oldNames);
    $total = (int) $countStmt->fetchColumn();

    if ($total === 0) {
        echo json_encode(['success' => false, 'error' => 'No records found with the specified program name(s).']);
        exit;
    }

    // Perform the bulk rename
    $params = array_merge([$newName], $oldNames);
    $stmt = $pdo->prepare("UPDATE copc_documents SET program = ? WHERE program IN ($placeholders)");
    $stmt->execute($params);
    $affected = $stmt->rowCount();

    $renamed = count($oldNames) === 1
        ? "'{$oldNames[0]}'"
        : count($oldNames) . ' programs';

    echo json_encode([
        'success'  => true,
        'message'  => "Renamed $renamed → '$newName' across $affected record(s).",
        'affected' => $affected,
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
