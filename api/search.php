<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $category = isset($_GET['cat']) ? trim($_GET['cat']) : '';
    $program = isset($_GET['prog']) ? trim($_GET['prog']) : '';
    $year = isset($_GET['yr']) ? (int) $_GET['yr'] : 0;

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $whereParams = [];

    if ($search !== '') {
        $tokens = preg_split('/\s+/', $search);

        foreach ($tokens as $token) {
            $token = trim($token, " \t\n\r\0\x0B,.;:-");
            if ($token === '') {
                continue;
            }
            $likeTerm = '%' . $token . '%';
            $where[] = '(
                school_name LIKE ?
                OR program LIKE ?
                OR region LIKE ?
                OR student_list LIKE ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(student_list, ",", " "), ".", " "), CHAR(13), " "), CHAR(10), " ") LIKE ?
                OR (
                    category != "COPC Exemption" 
                    AND (
                        extracted_text LIKE ? 
                        OR REPLACE(REPLACE(REPLACE(REPLACE(extracted_text, ",", " "), ".", " "), CHAR(13), " "), CHAR(10), " ") LIKE ?
                    )
                )
            )';
            array_push($whereParams, $likeTerm, $likeTerm, $likeTerm, $likeTerm, $likeTerm, $likeTerm, $likeTerm);
        }
    }

    if ($category !== '') {
        $where[] = 'category = ?';
        $whereParams[] = $category;
    }

    if ($program !== '') {
        $where[] = 'program = ?';
        $whereParams[] = $program;
    }

    if ($year > 0) {
        $where[] = 'YEAR(date_approved) = ?';
        $whereParams[] = $year;
    }

    $relevanceSql = '0';
    if ($search !== '') {
        $relevanceCases = [];
        foreach ($tokens as $token) {
            $token = trim($token, " \t\n\r\0\x0B,.;:-");
            if ($token === '') {
                continue;
            }
            $quotedToken = $pdo->quote('%' . $token . '%');
            $relevanceCases[] = "IF(student_list LIKE $quotedToken, 100, 0)";
            $relevanceCases[] = "IF(school_name LIKE $quotedToken, 50, 0)";
            $relevanceCases[] = "IF(program LIKE $quotedToken, 30, 0)";
        }
        if (!empty($relevanceCases)) {
            $relevanceSql = implode(' + ', $relevanceCases);
        }
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM copc_documents WHERE $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($whereParams);
    $totalCount = (int) $countStmt->fetchColumn();

    $sql = "SELECT id, school_name, program, region, category, date_approved, status, student_list, extracted_text, entry_type, file_type, file_size_kb
            FROM copc_documents
            WHERE $whereSql
            ORDER BY ($relevanceSql) DESC, date_approved DESC, id DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);

    $paramIndex = 1;
    foreach ($whereParams as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $records = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $records,
        'total' => $totalCount,
        'page' => $page,
        'has_more' => ($offset + $limit) < $totalCount,
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching results.',
        'details' => $e->getMessage(),
    ]);
}
