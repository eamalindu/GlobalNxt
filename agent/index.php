<?php
require_once '../config/app.php';
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['role'] !== 'agent') {
    header('Location: ../index.php');
    exit;
}

$pdo = getDB();

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(status = 'pending'), 0) as pending,
        COALESCE(SUM(status = 'approved'), 0) as approved,
        COALESCE(SUM(status = 'rejected'), 0) as rejected
    FROM documents
")->fetch();

$recent = $pdo->query("
    SELECT 
        d.id,
        d.student_name,
        d.student_id,
        d.programme_name,
        d.document_type,
        d.document_name,
        d.status,
        d.created_at,
        d.remarks,
        u.username as uploaded_by
    FROM documents d
    JOIN users u ON d.uploaded_by = u.id
    ORDER BY d.created_at DESC
    LIMIT 10
")->fetchAll();

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!doctype html>
<html lang="en">
<head>
    <?php include_once("../includes/header.php");
    ?>
    <title>Agent Login | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/agent.css">
    <link rel="icon" type="image/ico" href="../favicon.ico"/>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Agent</span></a>
    <div class="navbar-right">
        <span class="nav-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="dash">

    <div class="topbar">
        <div class="topbar-left">
            <h2><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?></h2>
            <p>Agent — GlobalNxt University</p>
        </div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total received</div>
            <div class="stat-value"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value warn"><?= $stats['pending'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value ok"><?= $stats['approved'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected</div>
            <div class="stat-value bad"><?= $stats['rejected'] ?></div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <span class="section-title">Recent documents</span>
            <span class="section-meta">Last 10 submissions</span>
        </div>
        <table>
            <thead>
            <tr>
                <th>Document</th>
                <th>Programme</th>
                <th>Status</th>
                <th>Submitted by</th>
                <th>Date</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($recent)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <p>No documents uploaded yet</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php
                $badges = [
                    'pending'      => 'badge-pending',
                    'under_review' => 'badge-review',
                    'approved'     => 'badge-approved',
                    'rejected'     => 'badge-rejected',
                ];
                ?>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td>
                            <div class="doc-name"><?= htmlspecialchars($row['document_name']) ?></div>
                            <div class="doc-sub"><?= htmlspecialchars($row['student_name']) ?> &middot; <?= htmlspecialchars($row['programme_name']) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-programme"><?= htmlspecialchars($row['programme_name']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $badges[$row['status']] ?? 'badge-pending' ?>">
                                <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                            </span>
                            <?php if ($row['status'] === 'rejected' && !empty($row['remarks'])): ?>
                                <div style="font-size:11px; color:#911f2a; margin-top:4px;">
                                    <?= htmlspecialchars($row['remarks']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="date"><?= htmlspecialchars($row['uploaded_by']) ?></span></td>
                        <td><span class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></span></td>
                        <td>
                            <a href="review.php?id=<?= $row['id'] ?>" class="btn-review">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
<p class="text-center text-muted small mb-0 credits"><small>Developed & Maintained by the Metropolitan IT
        Department</small></p>
</body>
</html>