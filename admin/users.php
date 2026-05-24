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

$pdo = getDB();

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

// Filters
$typeFilter   = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

if (!empty($typeFilter)) {
    $where[]  = 'type = ?';
    $params[] = $typeFilter;
}

if (!empty($statusFilter)) {
    $where[]  = 'status = ?';
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $where[]  = '(username LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT id, username, email, type, status, created_at
    FROM users
    $whereClause
    ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$counts = $pdo->query("
    SELECT
        COUNT(*) as total,
        COALESCE(SUM(type = 'staff'), 0) as staff,
        COALESCE(SUM(type = 'agent'), 0) as agents,
        COALESCE(SUM(type = 'admin'), 0) as admins,
        COALESCE(SUM(status = 'active'), 0) as active,
        COALESCE(SUM(status = 'inactive'), 0) as inactive
    FROM users
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/users.css">
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
        <div>
            <h2>Users</h2>
            <p>Manage staff, agent and admin accounts</p>
        </div>
        <a href="user_create.php" class="btn-primary-dark">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create user
        </a>
    </div>

    <?php if ($success === 'created'): ?>
        <div class="alert alert-success">User created successfully.</div>
    <?php elseif ($success === 'toggled'): ?>
        <div class="alert alert-success">User status updated successfully.</div>
    <?php elseif ($success === 'reset'): ?>
        <div class="alert alert-success">Password reset successfully.</div>
    <?php elseif ($error === 'failed'): ?>
        <div class="alert alert-error">Something went wrong. Please try again.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total users</div>
            <div class="stat-value"><?= $counts['total'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Staff</div>
            <div class="stat-value"><?= $counts['staff'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Agents</div>
            <div class="stat-value"><?= $counts['agents'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Inactive</div>
            <div class="stat-value"><?= $counts['inactive'] ?></div>
        </div>
    </div>

    <!-- Type filter tabs -->
    <div class="filter-tabs">
        <a href="users.php" class="filter-tab <?= empty($typeFilter) ? 'active' : '' ?>">All</a>
        <a href="users.php?type=staff" class="filter-tab <?= $typeFilter === 'staff' ? 'active' : '' ?>">Staff</a>
        <a href="users.php?type=agent" class="filter-tab <?= $typeFilter === 'agent' ? 'active' : '' ?>">Agents</a>
        <a href="users.php?type=admin" class="filter-tab <?= $typeFilter === 'admin' ? 'active' : '' ?>">Admins</a>
    </div>

    <!-- Search + status filter -->
    <form method="GET" class="filter-bar">
        <?php if (!empty($typeFilter)): ?>
            <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
        <?php endif; ?>
        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Search username or email...">
        <select name="status">
            <option value="">All status</option>
            <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn-filter">Search</button>
        <?php if (!empty($search) || !empty($statusFilter)): ?>
            <a href="users.php<?= !empty($typeFilter) ? '?type=' . $typeFilter : '' ?>" class="btn-reset">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Users table -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">Results</span>
            <span class="section-meta"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> found</span>
        </div>
        <table>
            <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">No users found.</div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                        </td>
                        <td>
                                <span class="badge badge-<?= $user['type'] ?>">
                                    <?= ucfirst($user['type']) ?>
                                </span>
                        </td>
                        <td>
                                <span class="badge badge-<?= $user['status'] ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                        </td>
                        <td>
                            <span class="date"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                        </td>
                        <td>
                            <div class="actions">
                                <!-- Toggle enable/disable -->
                                <form method="POST" action="user_toggle.php">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $user['status'] ?>">
                                    <button type="submit" class="btn-action <?= $user['status'] === 'active' ? 'danger' : 'success' ?>">
                                        <?= $user['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <!-- Reset password -->
                                <a href="user_reset.php?id=<?= $user['id'] ?>" class="btn-action">Reset password</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>