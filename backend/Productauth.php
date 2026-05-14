<?php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && ($_GET['action'] ?? '') === 'list') {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.sku, p.unit, p.description,
               p.quantity, p.reorder_level, p.unit_price,
               p.category_id, c.name AS category
        FROM products p
        JOIN categories c ON c.id = p.category_id
        ORDER BY p.name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'products' => $products]);
    exit;
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

$action = $_POST['action'] ?? '';

function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

if ($action === 'create') {
    $name        = clean($_POST['name']        ?? '');
    $sku         = clean($_POST['sku']         ?? '');
    $unit        = clean($_POST['unit']        ?? 'pcs');
    $description = clean($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $reorder     = (int)($_POST['reorder_level'] ?? 10);
    $price       = round((float)($_POST['unit_price'] ?? 0), 2);

  
    if (empty($name) || empty($sku) || $category_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Name, SKU, and Category are required.']);
        exit;
    }
    if (strlen($name) > 150 || strlen($sku) > 80) {
        echo json_encode(['success' => false, 'message' => 'Input too long.']);
        exit;
    }
    if ($price < 0 || $reorder < 0) {
        echo json_encode(['success' => false, 'message' => 'Price and reorder level must be non-negative.']);
        exit;
    }

    $chk = $conn->prepare('SELECT id FROM products WHERE sku = ?');
    $chk->bind_param('s', $sku);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'SKU already exists. Please use a unique SKU.']);
        exit;
    }
    $chk->close();

    $stmt = $conn->prepare("
        INSERT INTO products (category_id, name, sku, description, unit, reorder_level, unit_price)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issssid', $category_id, $name, $sku, $description, $unit, $reorder, $price);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Product \"$name\" added successfully."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add product. Try again.']);
    }
    $stmt->close();
    exit;
}

if ($action === 'update') {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = clean($_POST['name']        ?? '');
    $sku         = clean($_POST['sku']         ?? '');
    $unit        = clean($_POST['unit']        ?? 'pcs');
    $description = clean($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $reorder     = (int)($_POST['reorder_level'] ?? 10);
    $price       = round((float)($_POST['unit_price'] ?? 0), 2);

    if ($id === 0 || empty($name) || empty($sku) || $category_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data. Please fill all required fields.']);
        exit;
    }

    $chk = $conn->prepare('SELECT id FROM products WHERE sku = ? AND id != ?');
    $chk->bind_param('si', $sku, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'SKU already used by another product.']);
        exit;
    }
    $chk->close();

    $stmt = $conn->prepare("
        UPDATE products
        SET category_id = ?, name = ?, sku = ?, description = ?,
            unit = ?, reorder_level = ?, unit_price = ?
        WHERE id = ?
    ");
    $stmt->bind_param('issssidi', $category_id, $name, $sku, $description, $unit, $reorder, $price, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Product \"$name\" updated successfully."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update product.']);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete') {
 
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only admins can delete products.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }

    $nameQ = $conn->prepare('SELECT name FROM products WHERE id = ?');
    $nameQ->bind_param('i', $id);
    $nameQ->execute();
    $row = $nameQ->get_result()->fetch_assoc();
    $nameQ->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Product \"{$row['name']}\" deleted."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete product.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);