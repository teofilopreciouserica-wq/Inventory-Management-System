<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized.']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && ($_GET['action']??'') === 'list') {
    $stmt = $conn->prepare("
        SELECT c.id, c.name, c.description, c.created_at,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id ORDER BY c.name
    ");
    $stmt->execute();
    echo json_encode(['success'=>true,'categories'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    $stmt->close(); exit;
}

if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit; }

$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token.']); exit;
}

$action = $_POST['action'] ?? '';
$name   = htmlspecialchars(trim($_POST['name']??''), ENT_QUOTES, 'UTF-8');
$desc   = htmlspecialchars(trim($_POST['description']??''), ENT_QUOTES, 'UTF-8');

if ($action === 'create') {
    if (empty($name)) { echo json_encode(['success'=>false,'message'=>'Category name is required.']); exit; }
    $chk = $conn->prepare('SELECT id FROM categories WHERE name = ?');
    $chk->bind_param('s',$name); $chk->execute();
    if ($chk->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Category already exists.']); exit; }
    $chk->close();
    $stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
    $stmt->bind_param('ss',$name,$desc);
    echo json_encode($stmt->execute()
        ? ['success'=>true,'message'=>"Category \"$name\" added."]
        : ['success'=>false,'message'=>'Failed to add category.']);
    $stmt->close(); exit;
}

if ($action === 'update') {
    $id = (int)($_POST['id']??0);
    if (!$id || empty($name)) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }
    $chk = $conn->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
    $chk->bind_param('si',$name,$id); $chk->execute();
    if ($chk->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Category name already used.']); exit; }
    $chk->close();
    $stmt = $conn->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
    $stmt->bind_param('ssi',$name,$desc,$id);
    echo json_encode($stmt->execute()
        ? ['success'=>true,'message'=>"Category \"$name\" updated."]
        : ['success'=>false,'message'=>'Failed to update.']);
    $stmt->close(); exit;
}

if ($action === 'delete') {
    if ($_SESSION['role'] !== 'admin') { echo json_encode(['success'=>false,'message'=>'Admins only.']); exit; }
    $id = (int)($_POST['id']??0);
    $chk = $conn->prepare('SELECT COUNT(*) AS cnt FROM products WHERE category_id=?');
    $chk->bind_param('i',$id); $chk->execute();
    if ($chk->get_result()->fetch_assoc()['cnt'] > 0) { echo json_encode(['success'=>false,'message'=>'Cannot delete — category has products.']); exit; }
    $chk->close();
    $stmt = $conn->prepare('DELETE FROM categories WHERE id=?');
    $stmt->bind_param('i',$id);
    echo json_encode($stmt->execute()
        ? ['success'=>true,'message'=>'Category deleted.']
        : ['success'=>false,'message'=>'Failed to delete.']);
    $stmt->close(); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);