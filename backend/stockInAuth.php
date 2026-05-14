<?php

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/generateRef.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = (int)$_SESSION['user_id'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'generate_ref') {
        echo json_encode([
            'success'      => true,
            'reference_no' => generateRefNumber($conn, 'SI')
        ]);
        exit;
    }

    if ($action === 'list') {
        $stmt = $conn->prepare("
            SELECT si.id, p.name AS product, p.sku, si.quantity,
                   si.unit_cost, si.supplier, si.reference_no,
                   si.notes, u.username, si.transaction_at
            FROM stock_in si
            JOIN products p ON p.id = si.product_id
            JOIN users    u ON u.id = si.user_id
            ORDER BY si.transaction_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'records' => $records]);
        exit;
    }
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$product_id   = (int)($_POST['product_id'] ?? 0);
$quantity     = (int)($_POST['quantity']   ?? 0);
$unit_cost    = round((float)($_POST['unit_cost']   ?? 0), 2);
$supplier     = htmlspecialchars(trim($_POST['supplier']     ?? ''), ENT_QUOTES, 'UTF-8');
$notes        = htmlspecialchars(trim($_POST['notes']        ?? ''), ENT_QUOTES, 'UTF-8');

$reference_no = htmlspecialchars(trim($_POST['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8');
if (empty($reference_no)) {
    $reference_no = generateRefNumber($conn, 'SI');
}

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a product.']); exit;
}
if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero.']); exit;
}
if ($unit_cost < 0) {
    echo json_encode(['success' => false, 'message' => 'Unit cost cannot be negative.']); exit;
}


$chk = $conn->prepare('SELECT id, name FROM products WHERE id = ?');
$chk->bind_param('i', $product_id);
$chk->execute();
$product = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']); exit;
}


$stmt = $conn->prepare('CALL sp_stock_in(?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('iiidsss', $product_id, $user_id, $quantity, $unit_cost, $supplier, $reference_no, $notes);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'success'      => true,
        'message'      => "Stock In recorded: +{$quantity} unit(s) added to \"{$product['name']}\".",
        'reference_no' => $reference_no,
        'next_ref'     => generateRefNumber($conn, 'SI') 
    ]);
} else {
    $err = $conn->error; $stmt->close();
    echo json_encode(['success' => false, 'message' => "Failed to record stock in. $err"]);
}