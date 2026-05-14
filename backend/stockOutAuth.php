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
            'reference_no' => generateRefNumber($conn, 'SO')
        ]);
        exit;
    }

    if ($action === 'list') {
        $stmt = $conn->prepare("
            SELECT so.id, p.name AS product, p.sku, so.quantity,
                   so.reason, so.reference_no, so.notes,
                   u.username, so.transaction_at
            FROM stock_out so
            JOIN products p ON p.id = so.product_id
            JOIN users    u ON u.id = so.user_id
            ORDER BY so.transaction_at DESC
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
$notes        = htmlspecialchars(trim($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8');

$allowed_reasons = ['sold', 'damaged', 'returned', 'transfer', 'other'];
$reason = in_array($_POST['reason'] ?? '', $allowed_reasons) ? $_POST['reason'] : 'other';

$reference_no = htmlspecialchars(trim($_POST['reference_no'] ?? ''), ENT_QUOTES, 'UTF-8');
if (empty($reference_no)) {
    $reference_no = generateRefNumber($conn, 'SO');
}

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a product.']); exit;
}
if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be greater than zero.']); exit;
}

$chk = $conn->prepare('SELECT id, name, quantity FROM products WHERE id = ?');
$chk->bind_param('i', $product_id);
$chk->execute();
$product = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']); exit;
}
if ($product['quantity'] === 0) {
    echo json_encode(['success' => false, 'message' => "Product \"{$product['name']}\" is out of stock."]); exit;
}
if ($product['quantity'] < $quantity) {
    echo json_encode(['success' => false, 'message' => "Insufficient stock! Available: {$product['quantity']} unit(s). Requested: {$quantity}."]); exit;
}

$stmt = $conn->prepare('CALL sp_stock_out(?, ?, ?, ?, ?, ?)');
$stmt->bind_param('iiisss', $product_id, $user_id, $quantity, $reason, $reference_no, $notes);

if ($stmt->execute()) {
    $stmt->close();
    $remaining = $product['quantity'] - $quantity;
    echo json_encode([
        'success'      => true,
        'message'      => "Stock Out recorded: -{$quantity} unit(s) from \"{$product['name']}\". Remaining: {$remaining}.",
        'reference_no' => $reference_no,
        'next_ref'     => generateRefNumber($conn, 'SO')
    ]);
} else {
    $err = $conn->error; $stmt->close();
    echo json_encode(['success' => false, 'message' => "Failed to record stock out. $err"]);
}