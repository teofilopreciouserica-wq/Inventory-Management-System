<?php

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../backend/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$username = trim($conn->real_escape_string($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

if (strlen($username) > 100 || strlen($password) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input length.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, username, email, password, role FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role']     = $user['role'];

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'success'  => true,
    'message'  => 'Login successful.',
    'redirect' => './frontend/dashboard.php',
]);