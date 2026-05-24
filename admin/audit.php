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

// Pagination
$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Total count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM audit_log a
    JOIN users u ON a.user_id = u.id
    $whereClause
");
$countStmt->execute($params);
$totalEntries = (int)$countStmt->fetchColumn();
$totalPages   = (int)ceil($totalEntries / $perPage);

// Paginated results
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
    LIMIT $perPage OFFSET $offset
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
    <link rel="icon" type="image/ico" href="../favicon.ico"/>
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
            <span class="section-meta">
        <?= $totalEntries ?> entr<?= $totalEntries !== 1 ? 'ies' : 'y' ?> —
        page <?= $currentPage ?> of <?= max(1, $totalPages) ?>
    </span>
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
        <?php if ($totalPages > 1): ?>
            <?php
            // Build query string preserving filters
            $queryParams = array_filter([
                    'user_id'   => $userFilter,
                    'action'    => $actionFilter,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
            ]);
            ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) ?>" class="page-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        Previous
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Previous
            </span>
                <?php endif; ?>

                <div class="page-numbers">
                    <?php
                    $start = max(1, $currentPage - 2);
                    $end   = min($totalPages, $currentPage + 2);
                    ?>
                    <?php if ($start > 1): ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['page' => 1])) ?>" class="page-num">1</a>
                        <?php if ($start > 2): ?>
                            <span class="page-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i === $currentPage): ?>
                            <span class="page-num active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>" class="page-num"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <span class="page-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $totalPages])) ?>" class="page-num"><?= $totalPages ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) ?>" class="page-btn">
                        Next
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                Next
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>