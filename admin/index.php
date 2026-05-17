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

$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM documents) as total_docs,
        (SELECT COUNT(*) FROM documents WHERE status = 'pending') as pending_docs,
        (SELECT COUNT(*) FROM users WHERE type = 'staff' AND status = 'active') as total_staff,
        (SELECT COUNT(*) FROM users WHERE type = 'agent' AND status = 'active') as total_agents
")->fetch();

$recent_docs = $pdo->query("
    SELECT 
        d.id,
        d.student_name,
        d.document_name,
        d.document_type,
        d.status,
        d.created_at,
        u.username as uploaded_by
    FROM documents d
    JOIN users u ON d.uploaded_by = u.id
    ORDER BY d.created_at DESC
    LIMIT 8
")->fetchAll();

$recent_logs = $pdo->query("
    SELECT 
        a.action,
        a.description,
        a.created_at,
        u.username
    FROM audit_log a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 8
")->fetchAll();

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — GlobalNxt</title>
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

        .dash { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .topbar-left h2 { font-size: 18px; font-weight: 500; }
        .topbar-left p  { font-size: 13px; color: #888; margin-top: 3px; }

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
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 10px;
            padding: 1.1rem 1.25rem;
            text-decoration: none;
            display: block;
            transition: border-color 0.15s;
        }
        .stat-card:hover { border-color: #bbb; }
        .stat-label { font-size: 11px; color: #999; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-value { font-size: 26px; font-weight: 500; color: #1a1a1a; }
        .stat-value.warn { color: #c47f00; }
        .stat-value.info { color: #0c5a6e; }

        /* Grid layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

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
        .section-title { font-size: 13px; font-weight: 500; color: #1a1a1a; }
        .section-link { font-size: 12px; color: #aaa; text-decoration: none; }
        .section-link:hover { color: #555; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-align: left;
            padding: 9px 1.25rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 0.5px solid #efefef;
        }
        tbody td {
            font-size: 13px;
            padding: 11px 1.25rem;
            border-bottom: 0.5px solid #f5f5f5;
            vertical-align: middle;
            color: #1a1a1a;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #fafafa; }

        .doc-name { font-weight: 500; font-size: 13px; }
        .doc-sub  { font-size: 12px; color: #aaa; margin-top: 2px; }
        .date     { font-size: 12px; color: #bbb; }

        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 10px;
            border-radius: 100px;
        }
        .badge-pending  { background: #fef3cd; color: #a07000; }
        .badge-approved { background: #d4edda; color: #1e6b3a; }
        .badge-rejected { background: #f8d7da; color: #911f2a; }
        .badge-review   { background: #d1ecf1; color: #0c5a6e; }

        /* Audit log */
        .log-item {
            padding: 11px 1.25rem;
            border-bottom: 0.5px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .log-item:last-child { border-bottom: none; }
        .log-action { font-size: 13px; color: #1a1a1a; }
        .log-desc   { font-size: 12px; color: #aaa; margin-top: 2px; }
        .log-time   { font-size: 11px; color: #bbb; white-space: nowrap; }

        .empty-state {
            text-align: center;
            padding: 2.5rem 1.25rem;
            color: #bbb;
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user, .nav-links { display: none; }
            .dash { padding: 1.25rem 1rem; }
            .topbar { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .btn-primary-dark { width: 100%; justify-content: center; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            thead th:last-child, tbody td:last-child { display: none; }
        }
        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            .stat-value { font-size: 22px; }
            .stat-label { font-size: 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">GlobalNxt <span>/ Admin</span></a>
    <div class="navbar-right">
        <div class="nav-links">
            <a href="index.php" class="nav-link active">Dashboard</a>
            <a href="users.php" class="nav-link">Users</a>
            <a href="audit.php" class="nav-link">Audit log</a>
        </div>
        <span class="nav-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="dash">

    <div class="topbar">
        <div class="topbar-left">
            <h2><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?></h2>
            <p>Administrator — GlobalNxt</p>
        </div>
        <a href="user_create.php" class="btn-primary-dark">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create user
        </a>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total documents</div>
            <div class="stat-value"><?= $stats['total_docs'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending review</div>
            <div class="stat-value warn"><?= $stats['pending_docs'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active staff</div>
            <div class="stat-value info"><?= $stats['total_staff'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active agents</div>
            <div class="stat-value info"><?= $stats['total_agents'] ?></div>
        </div>
    </div>

    <!-- Two column grid -->
    <div class="grid-2">

        <!-- Recent Documents -->
        <div class="section">
            <div class="section-header">
                <span class="section-title">Recent documents</span>
                <a href="../agent/index.php" class="section-link">View all →</a>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($recent_docs)): ?>
                    <tr><td colspan="3"><div class="empty-state">No documents yet.</div></td></tr>
                <?php else: ?>
                    <?php
                    $badges = [
                        'pending'      => 'badge-pending',
                        'under_review' => 'badge-review',
                        'approved'     => 'badge-approved',
                        'rejected'     => 'badge-rejected',
                    ];
                    ?>
                    <?php foreach ($recent_docs as $row): ?>
                        <tr>
                            <td>
                                <div class="doc-name"><?= htmlspecialchars($row['document_name']) ?></div>
                                <div class="doc-sub"><?= htmlspecialchars($row['student_name']) ?></div>
                            </td>
                            <td>
                                    <span class="badge <?= $badges[$row['status']] ?? 'badge-pending' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                    </span>
                            </td>
                            <td><span class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Audit Log -->
        <div class="section">
            <div class="section-header">
                <span class="section-title">Recent activity</span>
                <a href="audit.php" class="section-link">View all →</a>
            </div>
            <?php if (empty($recent_logs)): ?>
                <div class="empty-state">No activity logged yet.</div>
            <?php else: ?>
                <?php foreach ($recent_logs as $log): ?>
                    <div class="log-item">
                        <div>
                            <div class="log-action"><?= htmlspecialchars($log['username']) ?></div>
                            <div class="log-desc"><?= htmlspecialchars($log['description']) ?></div>
                        </div>
                        <div class="log-time"><?= date('d M, h:i A', strtotime($log['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>