<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized.']); exit; }
if ($_SESSION['role'] !== 'admin') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admins only.']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && ($_GET['action']??'') === 'list') {
    $stmt = $conn->prepare('SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC');
    $stmt->execute();
    echo json_encode(['success'=>true,'users'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    $stmt->close(); exit;
}

if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit; }

$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token.']); exit;
}

$action   = $_POST['action']   ?? '';
$username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$role     = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';
$password = $_POST['password'] ?? '';

if ($action === 'create') {
    if (empty($username) || !$email)    { echo json_encode(['success'=>false,'message'=>'Valid username and email are required.']); exit; }
    if (strlen($password) < 8)          { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit; }

    // Check duplicate username/email
    $chk = $conn->prepare('SELECT id FROM users WHERE username=? OR email=?');
    $chk->bind_param('ss',$username,$email); $chk->execute();
    if ($chk->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Username or email already exists.']); exit; }
    $chk->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)');
    $stmt->bind_param('ssss',$username,$email,$hash,$role);
    echo json_encode($stmt->execute()
        ? ['success'=>true,'message'=>"User \"$username\" created successfully."]
        : ['success'=>false,'message'=>'Failed to create user.']);
    $stmt->close(); exit;
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id || empty($username) || !$email) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }

    $chk = $conn->prepare('SELECT id FROM users WHERE (username=? OR email=?) AND id!=?');
    $chk->bind_param('ssi',$username,$email,$id); $chk->execute();
    if ($chk->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Username or email already used.']); exit; }
    $chk->close();

    if (!empty($password)) {
        if (strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit; }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET username=?, email=?, password=?, role=? WHERE id=?');
        $stmt->bind_param('ssssi',$username,$email,$hash,$role,$id);
    } else {
        $stmt = $conn->prepare('UPDATE users SET username=?, email=?, role=? WHERE id=?');
        $stmt->bind_param('sssi',$username,$email,$role,$id);
    }
    echo json_encode($stmt->execute()
        ? ['success'=>true,'message'=>"User \"$username\" updated."]
        : ['success'=>false,'message'=>'Failed to update user.']);
    $stmt->close(); exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'You cannot delete your own account.']); exit; }
    $stmt = $conn->prepare('DELETE FROM users WHERE id=?');
    $stmt->bind_param('i',$id);
    echo json_encode($stmt->execute()
        ? ['success'=>true,'message'=>'User deleted.']
        : ['success'=>false,'message'=>'Failed to delete user.']);
    $stmt->close(); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);