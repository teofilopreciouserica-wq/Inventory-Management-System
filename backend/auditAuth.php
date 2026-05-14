<?php
// backend/auditAuth.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit; }

$action    = $_GET['action']    ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');

// Whitelist action filter
$allowed = ['STOCK_IN','STOCK_OUT','QUANTITY_CHANGE',''];
$action  = in_array($action, $allowed) ? $action : '';

$where  = 'WHERE DATE(al.logged_at) BETWEEN ? AND ?';
$params = [$date_from, $date_to];
$types  = 'ss';

if (!empty($action)) {
    $where   .= ' AND al.action = ?';
    $params[] = $action;
    $types   .= 's';
}

$stmt = $conn->prepare("
    SELECT al.id, al.action, al.table_name, al.record_id,
           al.old_value, al.new_value, al.ip_address, al.logged_at,
           COALESCE(u.username, 'system') AS username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $where
    ORDER BY al.logged_at DESC
    LIMIT 500
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success'=>true,'logs'=>$logs]);