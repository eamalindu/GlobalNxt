<?php
require_once 'config/app.php';
require_once 'config/db.php';
require_once 'config/mail.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['role'] . '/index.php');
    exit;
}

$success = false;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, username, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success even if email not found — prevents user enumeration
        if ($user && $user['status'] === 'active') {

            // Invalidate any existing unused tokens for this user
            $pdo->prepare("
                UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0
            ")->execute([$user['id']]);

            // Generate token
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ")->execute([$user['id'], $token, $expiresAt]);

            $resetUrl =  'http://10.20.30.156/globalnxt/reset_password.php?token=' . $token;

            sendPasswordResetEmail($email, $user['username'], $resetUrl);
        }

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Document Verification Platform</title>
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
        .brand h1 {
            font-size: 16px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .brand p {
            font-size: 12px;
            color: #aaa;
            margin-top: 4px;
        }

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

        .success-icon {
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.25rem;
            font-size: 12px;
            color: #aaa!important;
            text-decoration: none;
        }
        .back-link:hover { color: #555!important; }
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

        <?php if ($success): ?>
            <div class="success-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1e6b3a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z"/>
                    <path d="M22 6L12 13L2 6"/>
                </svg>
            </div>
            <div class="card-title" style="text-align:center;">Check your email</div>
            <p class="card-desc" style="text-align:center;">
                If an account exists for that email address, we've sent a password reset link.
                The link expires in 30 minutes.
            </p>
            <a href="index.php" class="back-link text-decoration-underline mt-0">Back to login</a>

        <?php else: ?>
            <div class="card-title">Forgot your password?</div>
            <p class="card-desc">Enter your email address and we'll send you a link to reset your password.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email address</label>
                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="your@email.com"
                        required
                        autofocus>
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">Send reset link</button>
            </form>

            <a href="index.php" class="back-link text-decoration-underline text-white">Back to login</a>
        <?php endif; ?>

    </div>
</div>

<script>
    document.querySelector('form')?.addEventListener('submit', function(e) {
        if (!this.checkValidity()) return;
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Sending...';
    });
</script>

</body>
</html>