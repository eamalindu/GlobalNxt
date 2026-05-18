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

// Filters
$userFilter   = $_GET['user_id'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';

$where  = [];
$params = [];

if (!empty($userFilter)) {
    $where[]  = 'a.user_id = ?';
    $params[] = $userFilter;
}

if (!empty($actionFilter)) {
    $where[]  = 'a.action = ?';
    $params[] = $actionFilter;
}

if (!empty($dateFrom)) {
    $where[]  = 'DATE(a.created_at) >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[]  = 'DATE(a.created_at) <= ?';
    $params[] = $dateTo;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$logs = $pdo->prepare("
    SELECT
        a.id,
        a.action,
        a.description,
        a.target_type,
        a.target_id,
        a.ip_address,
        a.created_at,
        u.username,
        u.type as user_type
    FROM audit_log a
    JOIN users u ON a.user_id = u.id
    $whereClause
    ORDER BY a.created_at DESC
    LIMIT 200
");
$logs->execute($params);
$entries = $logs->fetchAll();

// Users for filter dropdown
$users = $pdo->query("
    SELECT id, username, type FROM users ORDER BY username
")->fetchAll();

// Distinct actions for filter dropdown
$actions = $pdo->query("
    SELECT DISTINCT action FROM audit_log ORDER BY action
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — GlobalNxt Admin</title>
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
        .filter-bar input[type="date"] { color: #555; }
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
            transition: opacity 0.15s;
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

        .user-name { font-weight: 500; font-size: 13px; }
        .user-role { font-size: 11px; color: #aaa; margin-top: 2px; }
        .log-desc  { font-size: 13px; color: #1a1a1a; }
        .log-ip    { font-size: 11px; color: #bbb; margin-top: 2px; font-family: monospace; }
        .date      { font-size: 12px; color: #bbb; white-space: nowrap; }

        /* Action badge */
        .action-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 10px;
            border-radius: 100px;
            white-space: nowrap;
        }
        .action-login           { background: #e8f4fd; color: #1a5276; }
        .action-document_uploaded { background: #ede9fe; color: #5b21b6; }
        .action-document_approved { background: #d4edda; color: #1e6b3a; }
        .action-document_rejected { background: #f8d7da; color: #911f2a; }
        .action-user_created    { background: #d1ecf1; color: #0c5a6e; }
        .action-user_disabled   { background: #f8d7da; color: #911f2a; }
        .action-user_enabled    { background: #d4edda; color: #1e6b3a; }
        .action-password_reset  { background: #fef3cd; color: #a07000; }

        .empty-state {
            text-align: center;
            padding: 3rem 1.25rem;
            color: #bbb;
            font-size: 13px;
        }
        .empty-state svg { margin-bottom: 0.75rem; opacity: 0.3; }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user, .nav-links { display: none; }
            .page { padding: 1.25rem 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            thead th:nth-child(3),
            tbody td:nth-child(3),
            thead th:nth-child(4),
            tbody td:nth-child(4) { display: none; }
        }
        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            thead th:nth-child(2),
            tbody td:nth-child(2) { display: none; }
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
            <a href="users.php" class="nav-link">Users</a>
            <a href="audit.php" class="nav-link active">Audit log</a>
        </div>
        <span class="nav-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="page">

    <div class="page-header">
        <div>
            <h2>Audit log</h2>
            <p>Full history of all actions taken in the system</p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <select name="user_id">
            <option value="">All users</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $userFilter == $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['username']) ?> (<?= $u['type'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <select name="action">
            <option value="">All actions</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= $action ?>" <?= $actionFilter === $action ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_', ' ', $action)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
        <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">

        <button type="submit" class="btn-filter">Filter</button>

        <?php if (!empty($userFilter) || !empty($actionFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
            <a href="audit.php" class="btn-reset">Clear</a>
        <?php endif; ?>
    </form>

    <div class="section">
        <div class="section-header">
            <span class="section-title">Activity</span>
            <span class="section-meta"><?= count($entries) ?> entr<?= count($entries) !== 1 ? 'ies' : 'y' ?> found</span>
        </div>
        <table>
            <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP address</th>
                <th>Date & time</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <p>No activity logged yet.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($entries as $log): ?>
                    <tr>
                        <td>
                            <div class="user-name"><?= htmlspecialchars($log['username']) ?></div>
                            <div class="user-role"><?= ucfirst($log['user_type']) ?></div>
                        </td>
                        <td>
                                <span class="action-badge action-<?= $log['action'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $log['action'])) ?>
                                </span>
                        </td>
                        <td>
                            <div class="log-desc"><?= htmlspecialchars($log['description']) ?></div>
                        </td>
                        <td>
                            <div class="log-ip"><?= htmlspecialchars($log['ip_address']) ?></div>
                        </td>
                        <td>
                            <span class="date"><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></span>
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