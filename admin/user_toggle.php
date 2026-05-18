<?php
require_once '../config/app.php';
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

$userId        = (int)($_POST['user_id'] ?? 0);
$currentStatus = $_POST['current_status'] ?? '';

if (!$userId || !in_array($currentStatus, ['active', 'inactive'])) {
    header('Location: users.php?error=failed');
    exit;
}

// Prevent admin from disabling their own account
if ($userId === (int)$_SESSION['user_id']) {
    header('Location: users.php?error=failed');
    exit;
}

$pdo       = getDB();
$newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
$action    = $newStatus === 'inactive' ? 'user_disabled' : 'user_enabled';

// Get username for audit log
$user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$user->execute([$userId]);
$target = $user->fetch();

if (!$target) {
    header('Location: users.php?error=failed');
    exit;
}

// Update status
$update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
$update->execute([$newStatus, $userId]);

// Audit log
$log = $pdo->prepare("
    INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
    VALUES (?, ?, 'user', ?, ?, ?)
");
$log->execute([
    $_SESSION['user_id'],
    $action,
    $userId,
    'Admin ' . $action . ': ' . $target['username'],
    $_SERVER['REMOTE_ADDR']
]);

header('Location: users.php?success=toggled');
exit;