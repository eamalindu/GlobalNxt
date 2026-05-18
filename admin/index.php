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
    <title>Admin Dashboard | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/admin.css">

</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Admin</span></a>
    <div class="navbar-right">
        <div class="nav-links">
            <a href="index.php" class="nav-link active">Dashboard</a>
            <a href="documents.php" class="nav-link">Documents</a>
            <a href="users.php" class="nav-link">Users</a>
            <a href="audit.php" class="nav-link">Audit log</a>
        </div>
        <span class="nav-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="dash">

    <div class="topbar">
        <div class="topbar-left">
            <h2><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?></h2>
            <p>Administrator</p>
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
                <a href="documents.php" class="section-link">View all →</a>
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
                    <tr><td colspan="3"><div class="empty-state">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <p>No documents uploaded yet</p>
                            </div></td></tr>
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
                <div class="empty-state">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <p>No activity logged yet</p>
                    </div>
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