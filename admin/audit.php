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
    <title>Audit Log | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/audits.css">
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
        <span class="nav-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
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