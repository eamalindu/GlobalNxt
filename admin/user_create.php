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
    <title>Create User | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/user-create.css">
    <link rel="icon" type="image/ico" href="../favicon.ico"/>
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