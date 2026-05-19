<?php
require_once '../config/app.php';
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit;
}

$successMsg = $_GET['success'] ?? '';

$pdo = getDB();
$userId = $_SESSION['user_id'];

$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(status = 'pending'), 0) as pending,
        COALESCE(SUM(status = 'approved'), 0) as approved,
        COALESCE(SUM(status = 'rejected'), 0) as rejected
    FROM documents
    WHERE uploaded_by = ?
");
$stats->execute([$userId]);
$counts = $stats->fetch();


$recent = $pdo->prepare("
    SELECT 
        d.id,
        d.student_name,
        d.document_name,
        d.programme_name,
        d.status,
        d.remarks,
        d.created_at,
        (SELECT COUNT(*) FROM documents WHERE parent_id = d.id) as has_reupload
    FROM documents d
    WHERE uploaded_by = ?
    ORDER BY d.created_at DESC
    LIMIT 10
");
$recent->execute([$userId]);
$submissions = $recent->fetchAll();

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!doctype html>
<html lang="en">
<head>
    <?php include_once("../includes/header.php");
    ?>
    <title>Staff Dashboard | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/staff.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Staff</span></a>
    <div class="navbar-right">
        <span class="nav-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="dash">

    <div class="topbar">
        <div class="topbar-left">
            <h2><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?></h2>
            <p>Staff — Metropolitan College</p>
        </div>
        <a href="upload.php" class="upload-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Upload document
        </a>
    </div>

    <?php if ($successMsg === 'reuploaded'): ?>
        <div class="alert alert-success">Document re-uploaded successfully and is pending review.</div>
    <?php endif; ?>

    <?php if ($successMsg === 'already_reuploaded' || ($_GET['error'] ?? '') === 'already_reuploaded'): ?>
        <div class="alert alert-error">
            This document has already been re-uploaded and is awaiting review.
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total uploaded</div>
            <div class="stat-value"><?= $counts['total'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value warn"><?= $counts['pending'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value ok"><?= $counts['approved'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected</div>
            <div class="stat-value bad"><?= $counts['rejected'] ?></div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <span class="section-title">Recent submissions</span>
            <span class="section-meta">Last 10 documents</span>
        </div>
        <table>
            <thead>
            <tr>
                <th>Document</th>
                <th>Programme</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Action</th>

            </tr>
            </thead>
            <tbody>
            <?php if (empty($submissions)): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <p>No documents uploaded yet</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php
                $badges = ['pending' => 'badge-pending', 'under_review' => 'badge-review', 'approved' => 'badge-approved', 'rejected' => 'badge-rejected',];
                ?>
                <?php foreach ($submissions as $row): ?>
                    <tr>
                        <td>
                            <div class="doc-name"><?= htmlspecialchars($row['document_name']) ?></div>
                            <div class="doc-student"><?= htmlspecialchars($row['student_name']) ?></div>
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
                        <td>
                            <span class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></span>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'rejected'): ?>
                                <?php if ($row['has_reupload']): ?>
                                    <span style="font-size:12px; color:#bbb;">Re-uploaded</span>
                                <?php else: ?>
                                    <a href="reupload.php?id=<?= $row['id'] ?>" class="btn-reupload">Re-upload</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size:12px; color:#bbb;">—</span>
                            <?php endif; ?>
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