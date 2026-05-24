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

$pdo    = getDB();
$id     = (int)($_GET['id'] ?? 0);
$error  = null;

if (!$id) {
    header('Location: users.php');
    exit;
}

// Fetch user
$stmt = $pdo->prepare("SELECT id, username, email, type FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$hashed, $id]);

        // Audit log
        $log = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
            VALUES (?, 'password_reset', 'user', ?, ?, ?)
        ");
        $log->execute([
            $_SESSION['user_id'],
            $id,
            'Admin reset password for: ' . $user['username'],
            $_SERVER['REMOTE_ADDR']
        ]);

        header('Location: users.php?success=reset');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/user-reset.css">
    <link rel="icon" type="image/ico" href="../favicon.ico"/>
    <style>

    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Admin</span></a>
    <div class="navbar-right">
        <div class="nav-links">
            <a href="index.php" class="nav-link">Dashboard</a>
            <a href="documents.php" class="nav-link">Documents</a>
            <a href="users.php" class="nav-link active">Users</a>
            <a href="audit.php" class="nav-link">Audit log</a>
        </div>
        <span class="nav-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="page">

    <div class="page-header">
        <a href="users.php" class="text-decoration-underline">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to users
        </a>
        <h2>Reset password</h2>
        <p>Set a new password for this user</p>
    </div>

    <!-- User info -->
    <div class="user-info">
        <div class="user-avatar">
            <?= strtoupper(substr($user['username'], 0, 2)) ?>
        </div>
        <div>
            <div class="user-detail-name">
                <?= htmlspecialchars($user['username']) ?>
                <span class="badge badge-<?= $user['type'] ?>"><?= ucfirst($user['type']) ?></span>
            </div>
            <div class="user-detail-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">

            <div class="section-label">New password</div>

            <div class="form-group">
                <label>New password <span style="color:#c00">*</span></label>
                <input type="password" name="password" placeholder="Min. 8 characters" required>
                <div class="hint">At least 8 characters</div>
            </div>

            <div class="form-group">
                <label>Confirm new password <span style="color:#c00">*</span></label>
                <input type="password" name="confirm_password" placeholder="Repeat new password" required>
            </div>

            <div class="form-actions">
                <a href="users.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Reset password</button>
            </div>

        </form>
    </div>
</div>

</body>
</html>