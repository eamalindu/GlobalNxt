<?php
require_once 'config/app.php';
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: index.php?error=empty');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: index.php?error=invalid');
    exit;
}

// Audit log — login
$log = $pdo->prepare("
    INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
    VALUES (?, 'login', 'user', ?, ?, ?)
");
$log->execute([
    $user['id'],
    $user['id'],
    'User logged in: ' . $user['username'],
    $_SERVER['REMOTE_ADDR']
]);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['type'];

header('Location: ' . $user['type'] . '/index.php');
exit;
