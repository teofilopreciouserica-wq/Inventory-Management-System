<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$type       = $_GET['type']       ?? 'all';   // 'all', 'in', 'out'
$product_id = (int)($_GET['product_id'] ?? 0);
$date_from  = $_GET['date_from']  ?? date('Y-m-01');
$date_to    = $_GET['date_to']    ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');

if (!in_array($type, ['all', 'in', 'out'])) $type = 'all';

$records = [];

if ($type === 'all' || $type === 'in') {
    $where  = 'WHERE DATE(si.transaction_at) BETWEEN ? AND ?';
    $params = [$date_from, $date_to];
    $types  = 'ss';

    if ($product_id > 0) {
        $where   .= ' AND si.product_id = ?';
        $params[] = $product_id;
        $types   .= 'i';
    }

    $stmt = $conn->prepare("
        SELECT 'Stock In' AS type,
               p.name AS product, p.sku,
               si.quantity, si.unit_cost,
               si.supplier, '' AS reason,
               si.reference_no, si.notes,
               u.username, si.transaction_at
        FROM stock_in si
        JOIN products p ON p.id = si.product_id
        JOIN users    u ON u.id = si.user_id
        $where
        ORDER BY si.transaction_at DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $records = array_merge($records, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

if ($type === 'all' || $type === 'out') {
    $where  = 'WHERE DATE(so.transaction_at) BETWEEN ? AND ?';
    $params = [$date_from, $date_to];
    $types  = 'ss';

    if ($product_id > 0) {
        $where   .= ' AND so.product_id = ?';
        $params[] = $product_id;
        $types   .= 'i';
    }

    $stmt = $conn->prepare("
        SELECT 'Stock Out' AS type,
               p.name AS product, p.sku,
               so.quantity, 0 AS unit_cost,
               '' AS supplier, so.reason,
               so.reference_no, so.notes,
               u.username, so.transaction_at
        FROM stock_out so
        JOIN products p ON p.id = so.product_id
        JOIN users    u ON u.id = so.user_id
        $where
        ORDER BY so.transaction_at DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $records = array_merge($records, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

usort($records, fn($a, $b) => strtotime($b['transaction_at']) - strtotime($a['transaction_at']));

echo json_encode(['success' => true, 'records' => $records]);