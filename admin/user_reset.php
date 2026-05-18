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
    <title>Reset Password — GlobalNxt Admin</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f3;
            color: #1a1a1a;
            min-height: 100vh;
        }

        .navbar {
            background: #fff;
            border-bottom: 0.5px solid #e0e0e0;
            padding: 0 2rem;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand { font-size: 14px; font-weight: 500; color: #1a1a1a; text-decoration: none; }
        .navbar-brand span { color: #888; font-weight: 400; }
        .navbar-right { display: flex; align-items: center; gap: 1rem; }
        .nav-user { font-size: 13px; color: #555; }
        .nav-links { display: flex; align-items: center; gap: 0.25rem; }
        .nav-link {
            font-size: 12px;
            color: #888;
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 6px;
            transition: background 0.15s;
        }
        .nav-link:hover { background: #f5f5f3; color: #333; }
        .nav-link.active { background: #f0f0ee; color: #1a1a1a; font-weight: 500; }
        .nav-logout {
            font-size: 12px;
            color: #888;
            text-decoration: none;
            padding: 5px 12px;
            border: 0.5px solid #ddd;
            border-radius: 6px;
        }
        .nav-logout:hover { background: #f5f5f3; }

        .page { max-width: 480px; margin: 0 auto; padding: 2rem 1.5rem; }

        .page-header { margin-bottom: 1.75rem; }
        .page-header a {
            font-size: 12px;
            color: #aaa;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 0.75rem;
        }
        .page-header a:hover { color: #555; }
        .page-header h2 { font-size: 18px; font-weight: 500; }
        .page-header p  { font-size: 13px; color: #888; margin-top: 3px; }

        .user-info {
            background: #f5f5f3;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #1a1a1a;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 500;
            flex-shrink: 0;
        }
        .user-detail-name  { font-size: 13px; font-weight: 500; }
        .user-detail-email { font-size: 12px; color: #aaa; margin-top: 2px; }
        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 100px;
            margin-left: 6px;
        }
        .badge-staff { background: #ede9fe; color: #5b21b6; }
        .badge-agent { background: #d1ecf1; color: #0c5a6e; }
        .badge-admin { background: #fef3cd; color: #a07000; }

        .card {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .section-label {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 0.5px solid #f0f0f0;
        }

        .form-group { margin-bottom: 1.1rem; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #555;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 9px 12px;
            font-size: 13px;
            border: 0.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #1a1a1a;
            outline: none;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .form-group input:focus { border-color: #aaa; }
        .hint { font-size: 11px; color: #bbb; margin-top: 4px; }

        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .alert-error { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }

        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; }
        .btn-cancel {
            padding: 9px 20px;
            font-size: 13px;
            border: 0.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #555;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-cancel:hover { background: #f5f5f3; }
        .btn-submit {
            padding: 9px 24px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            background: #1a1a1a;
            color: #fff;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .btn-submit:hover { opacity: 0.85; }

        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user, .nav-links { display: none; }
            .page { padding: 1.25rem 1rem; }
        }
        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            .form-actions { flex-direction: column; }
            .btn-cancel, .btn-submit { width: 100%; justify-content: center; text-align: center; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">GlobalNxt <span>/ Admin</span></a>
    <div class="navbar-right">
        <div class="nav-links">
            <a href="index.php" class="nav-link">Dashboard</a>
            <a href="documents.php" class="nav-link">Documents</a>
            <a href="users.php" class="nav-link active">Users</a>
            <a href="audit.php" class="nav-link">Audit log</a>
        </div>
        <span class="nav-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="page">

    <div class="page-header">
        <a href="users.php">
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