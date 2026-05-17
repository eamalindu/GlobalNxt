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

$pdo   = getDB();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $type     = $_POST['type'] ?? '';

    // Validate
    if (empty($username) || empty($email) || empty($password) || empty($type)) {
        $error = 'Please fill in all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';

    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';

    } elseif (!in_array($type, ['staff', 'agent', 'admin'])) {
        $error = 'Invalid user role selected.';

    } else {
        // Check username/email taken
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);

        if ($check->fetch()) {
            $error = 'Username or email is already taken.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, type, status)
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$username, $email, $hashed, $type]);

            $newUserId = $pdo->lastInsertId();

            // Audit log
            $log = $pdo->prepare("
                INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
                VALUES (?, 'user_created', 'user', ?, ?, ?)
            ");
            $log->execute([
                $_SESSION['user_id'],
                $newUserId,
                'Admin created user: ' . $username . ' (' . $type . ')',
                $_SERVER['REMOTE_ADDR']
            ]);

            header('Location: users.php?success=created');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User — GlobalNxt Admin</title>
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

        .page { max-width: 560px; margin: 0 auto; padding: 2rem 1.5rem; }

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
        .form-group input,
        .form-group select {
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
        .form-group input:focus,
        .form-group select:focus { border-color: #aaa; }

        .hint { font-size: 11px; color: #bbb; margin-top: 4px; }

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .divider { height: 0.5px; background: #f0f0f0; margin: 1.5rem 0; }

        /* Role selector */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 1.1rem;
        }
        .role-option { display: none; }
        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 10px;
            border: 0.5px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }
        .role-label:hover { border-color: #aaa; background: #fafafa; }
        .role-option:checked + .role-label {
            border-color: #1a1a1a;
            background: #f5f5f3;
        }
        .role-icon { font-size: 20px; }
        .role-name { font-size: 12px; font-weight: 500; color: #1a1a1a; }
        .role-desc { font-size: 11px; color: #aaa; }

        /* Alert */
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .alert-error { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }

        /* Actions */
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

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user, .nav-links { display: none; }
            .page { padding: 1.25rem 1rem; }
            .row-2 { grid-template-columns: 1fr; }
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
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Admin</span></a>
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
        <a href="users.php" class="text-decoration-underline">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to users
        </a>
        <h2>Create user</h2>
        <p>Add a new staff, agent or admin account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">

            <div class="section-label">Account details</div>

            <div class="row-2">
                <div class="form-group">
                    <label>Username <span style="color:#c00">*</span></label>
                    <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="e.g. john_doe" required>
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#c00">*</span></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="e.g. john@metro.lk" required>
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Password <span style="color:#c00">*</span></label>
                    <input type="password" name="password" placeholder="Min. 8 characters" required>
                    <div class="hint">At least 8 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm password <span style="color:#c00">*</span></label>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>

            <div class="divider"></div>
            <div class="section-label">Role</div>

            <div class="role-grid">
                <div>
                    <input class="role-option" type="radio" name="type" id="role_staff" value="staff"
                        <?= ($_POST['type'] ?? '') === 'staff' ? 'checked' : '' ?> required>
                    <label class="role-label" for="role_staff">
                        <svg class="role-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="role-name">Staff</span>
                        <span class="role-desc">Upload documents</span>
                    </label>
                </div>
                <div>
                    <input class="role-option" type="radio" name="type" id="role_agent" value="agent"
                        <?= ($_POST['type'] ?? '') === 'agent' ? 'checked' : '' ?>>
                    <label class="role-label" for="role_agent">
                        <svg class="role-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span class="role-name">Agent</span>
                        <span class="role-desc">Review documents</span>
                    </label>
                </div>
                <div>
                    <input class="role-option" type="radio" name="type" id="role_admin" value="admin"
                        <?= ($_POST['type'] ?? '') === 'admin' ? 'checked' : '' ?>>
                    <label class="role-label" for="role_admin">
                        <svg class="role-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                        <span class="role-name">Admin</span>
                        <span class="role-desc">Full access</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <a href="users.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Create user</button>
            </div>

        </form>
    </div>
</div>

</body>
</html>