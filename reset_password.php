<?php
require_once 'config/app.php';
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['role'] . '/index.php');
    exit;
}

$pdo   = getDB();
$token = trim($_GET['token'] ?? '');
$error = null;
$done  = false;

if (empty($token)) {
    header('Location: index.php');
    exit;
}

// Validate token
$stmt = $pdo->prepare("
    SELECT pr.*, u.username, u.email
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ?
      AND pr.used = 0
      AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = 'invalid';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        // Update password
        $pdo->prepare("
            UPDATE users SET password = ? WHERE id = ?
        ")->execute([$hashed, $reset['user_id']]);

        // Invalidate token
        $pdo->prepare("
            UPDATE password_resets SET used = 1 WHERE token = ?
        ")->execute([$token]);

        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Document Verification Platform</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" type="image/ico" href="favicon.ico"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100dvh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;

            background: #f5f5f3;
        }

        .container {
            width: 100%;
            max-width: 400px;
        }

        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand h1 { font-size: 16px; font-weight: 500; color: #1a1a1a; }
        .brand p   { font-size: 12px; color: #aaa; margin-top: 4px; }

        .card {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 12px;
            padding: 2rem;
        }

        .card-title {
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .card-desc {
            font-size: 13px;
            color: #888;
            margin-bottom: 1.5rem;
            line-height: 1.6;
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

        .btn-submit {
            width: 100%;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            background: #1a1a1a;
            color: #fff;
            cursor: pointer;
            transition: opacity 0.15s;
            margin-top: 0.5rem;
        }
        .btn-submit:hover { opacity: 0.85; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .alert-error   { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }
        .alert-success { background: #f0faf4; color: #1e6b3a; border: 0.5px solid #b8dfc8; }

        .invalid-box {
            text-align: center;
            padding: 0.5rem 0;
        }
        .invalid-box svg { margin-bottom: 1rem; }
        .invalid-box p {
            font-size: 13px;
            color: #888;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.25rem;
            font-size: 12px;
            color: #aaa;
            text-decoration: none;
        }
        .back-link:hover { color: #555; }

        .btn-login {
            display: block;
            text-align: center;
            background: #1a1a1a;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 10px;
            border-radius: 8px;
            margin-top: 1rem;
            transition: opacity 0.15s;
        }
        .btn-login:hover { opacity: 0.85; color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <img src="images/logo_new.png" width="60%" class="d-block mx-auto mb-4" alt="logo">
    <div class="brand">
        <h1>Document Verification Platform</h1>
        <p>Metropolitan College × GlobalNxt University</p>
    </div>

    <div class="card">

        <?php if ($error === 'invalid'): ?>
            <div class="invalid-box">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#911f2a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <div class="card-title">Link invalid or expired</div>
                <p>This password reset link has already been used or has expired. Please request a new one.</p>
                <a href="forgot_password.php" class="btn-login">Request new link</a>
            </div>

        <?php elseif ($done): ?>
            <div class="invalid-box">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1e6b3a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <div class="card-title">Password reset</div>
                <p>Your password has been updated successfully. You can now log in with your new password.</p>
                <a href="index.php" class="btn-login">Back to login</a>
            </div>

        <?php else: ?>
            <div class="card-title">Set new password</div>
            <p class="card-desc">Hi <strong><?= htmlspecialchars($reset['username']) ?></strong>, enter your new password below.</p>

            <?php if ($error && $error !== 'invalid'): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>New password</label>
                    <input type="password" name="password" placeholder="Min. 8 characters" required autofocus>
                    <div class="hint">At least 8 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm new password</label>
                    <input type="password" name="confirm_password" placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">Update password</button>
            </form>

            <a href="index.php" class="back-link">Back to login</a>
        <?php endif; ?>

    </div>
</div>

<script>
    document.querySelector('form')?.addEventListener('submit', function(e) {
        if (!this.checkValidity()) return;
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Updating...';
    });
</script>

</body>
</html>