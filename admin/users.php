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
    <title>Users | GlobalNxt Admin</title>
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

        .page { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
        }
        .page-header h2 { font-size: 18px; font-weight: 500; }
        .page-header p  { font-size: 13px; color: #888; margin-top: 3px; }

        .btn-primary-dark {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .btn-primary-dark:hover { opacity: 0.85; color: #fff; }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 1.75rem;
        }
        .stat-card {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        .stat-label { font-size: 11px; color: #999; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-value { font-size: 24px; font-weight: 500; color: #1a1a1a; }

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .filter-tab {
            font-size: 12px;
            font-weight: 500;
            padding: 5px 14px;
            border-radius: 100px;
            border: 0.5px solid #ddd;
            background: #fff;
            color: #888;
            text-decoration: none;
            transition: all 0.15s;
        }
        .filter-tab:hover { border-color: #aaa; color: #333; }
        .filter-tab.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }

        /* Filter bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .filter-bar input,
        .filter-bar select {
            padding: 8px 12px;
            font-size: 13px;
            border: 0.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #1a1a1a;
            outline: none;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .filter-bar input { flex: 1; min-width: 200px; }
        .filter-bar input:focus,
        .filter-bar select:focus { border-color: #aaa; }
        .btn-filter {
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            background: #1a1a1a;
            color: #fff;
            cursor: pointer;
        }
        .btn-filter:hover { opacity: 0.85; }
        .btn-reset {
            padding: 8px 14px;
            font-size: 13px;
            border: 0.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #888;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-reset:hover { background: #f5f5f3; }

        /* Table */
        .section {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 12px;
            overflow: hidden;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 0.5px solid #efefef;
        }
        .section-title { font-size: 13px; font-weight: 500; }
        .section-meta  { font-size: 12px; color: #aaa; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-align: left;
            padding: 10px 1.25rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 0.5px solid #efefef;
        }
        tbody td {
            font-size: 13px;
            padding: 12px 1.25rem;
            border-bottom: 0.5px solid #f5f5f5;
            vertical-align: middle;
            color: #1a1a1a;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #fafafa; }

        .user-name  { font-weight: 500; font-size: 13px; }
        .user-email { font-size: 12px; color: #aaa; margin-top: 2px; }
        .date       { font-size: 12px; color: #bbb; }

        /* Badges */
        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 10px;
            border-radius: 100px;
        }
        .badge-staff    { background: #ede9fe; color: #5b21b6; }
        .badge-agent    { background: #d1ecf1; color: #0c5a6e; }
        .badge-admin    { background: #fef3cd; color: #a07000; }
        .badge-active   { background: #d4edda; color: #1e6b3a; }
        .badge-inactive { background: #f8d7da; color: #911f2a; }

        /* Action buttons */
        .actions { display: flex; gap: 6px; align-items: center; }
        .btn-action {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 6px;
            border: 0.5px solid #ddd;
            background: #fff;
            color: #555;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .btn-action:hover { background: #f5f5f3; color: #1a1a1a; }
        .btn-action.danger { color: #911f2a; border-color: #f5c6c8; }
        .btn-action.danger:hover { background: #fdf0f0; }
        .btn-action.success { color: #1e6b3a; border-color: #b8dfc8; }
        .btn-action.success:hover { background: #f0faf4; }

        /* Alerts */
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .alert-success { background: #f0faf4; color: #1e6b3a; border: 0.5px solid #b8dfc8; }
        .alert-error   { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }

        .empty-state {
            text-align: center;
            padding: 3rem 1.25rem;
            color: #bbb;
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user, .nav-links { display: none; }
            .page { padding: 1.25rem 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .btn-primary-dark { width: 100%; justify-content: center; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            thead th:nth-child(3),
            tbody td:nth-child(3),
            thead th:nth-child(4),
            tbody td:nth-child(4) { display: none; }
        }
        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            .stat-value { font-size: 20px; }
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