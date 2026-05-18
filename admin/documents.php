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
$status     = $_GET['status'] ?? '';
$uploadedBy = $_GET['uploaded_by'] ?? '';
$search     = trim($_GET['search'] ?? '');

// Build query
$where  = [];
$params = [];

if (!empty($status)) {
    $where[]  = 'd.status = ?';
    $params[] = $status;
}

if (!empty($uploadedBy)) {
    $where[]  = 'd.uploaded_by = ?';
    $params[] = $uploadedBy;
}

if (!empty($search)) {
    $where[]  = '(d.student_name LIKE ? OR d.student_id LIKE ? OR d.programme_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$docs = $pdo->prepare("
    SELECT 
        d.id,
        d.student_name,
        d.student_id,
        d.programme_name,
        d.document_name,
        d.document_type,
        d.status,
        d.created_at,
        u.username as uploaded_by,
        u.id as uploaded_by_id
    FROM documents d
    JOIN users u ON d.uploaded_by = u.id
    $whereClause
    ORDER BY d.created_at DESC
");
$docs->execute($params);
$documents = $docs->fetchAll();

// Staff list for filter dropdown
$staffList = $pdo->query("
    SELECT id, username FROM users WHERE type = 'staff' ORDER BY username
")->fetchAll();

// Count by status
$counts = $pdo->query("
    SELECT
        COUNT(*) as total,
        COALESCE(SUM(status = 'pending'), 0) as pending,
        COALESCE(SUM(status = 'under_review'), 0) as under_review,
        COALESCE(SUM(status = 'approved'), 0) as approved,
        COALESCE(SUM(status = 'rejected'), 0) as rejected
    FROM documents
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/documents.css">

</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Admin</span></a>
    <div class="navbar-right">
        <div class="nav-links">
            <a href="index.php" class="nav-link">Dashboard</a>
            <a href="documents.php" class="nav-link active">Documents</a>
            <a href="users.php" class="nav-link">Users</a>
            <a href="audit.php" class="nav-link">Audit log</a>
        </div>
        <span class="nav-user"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="page">

    <div class="page-header">
        <div>
            <h2>Documents</h2>
            <p>All student documents submitted through the system</p>
        </div>
    </div>

    <!-- Status filter tabs -->
    <div class="filter-tabs">
        <a href="documents.php" class="filter-tab <?= empty($status) ? 'active' : '' ?>">
            All (<?= $counts['total'] ?>)
        </a>
        <a href="documents.php?status=pending" class="filter-tab <?= $status === 'pending' ? 'active' : '' ?>">
            Pending (<?= $counts['pending'] ?>)
        </a>
        <a href="documents.php?status=under_review" class="filter-tab <?= $status === 'under_review' ? 'active' : '' ?>">
            Under review (<?= $counts['under_review'] ?>)
        </a>
        <a href="documents.php?status=approved" class="filter-tab <?= $status === 'approved' ? 'active' : '' ?>">
            Approved (<?= $counts['approved'] ?>)
        </a>
        <a href="documents.php?status=rejected" class="filter-tab <?= $status === 'rejected' ? 'active' : '' ?>">
            Rejected (<?= $counts['rejected'] ?>)
        </a>
    </div>

    <!-- Search + staff filter -->
    <form method="GET" class="filter-bar">
        <?php if (!empty($status)): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <?php endif; ?>
        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Search student name, ID or programme...">
        <select name="uploaded_by">
            <option value="">All staff</option>
            <?php foreach ($staffList as $staff): ?>
                <option value="<?= $staff['id'] ?>" <?= $uploadedBy == $staff['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($staff['username']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-filter">Search</button>
        <?php if (!empty($search) || !empty($uploadedBy)): ?>
            <a href="documents.php<?= !empty($status) ? '?status=' . $status : '' ?>" class="btn-reset">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Documents table -->
    <div class="section">
        <div class="section-header">
            <span class="section-title">Results</span>
            <span class="section-meta"><?= count($documents) ?> document<?= count($documents) !== 1 ? 's' : '' ?> found</span>
        </div>
        <table>
            <thead>
            <tr>
                <th>Document</th>
                <th>Type</th>
                <th>Uploaded by</th>
                <th>Date</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($documents)): ?>
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
                <?php foreach ($documents as $row): ?>
                    <tr>
                        <td>
                            <div class="doc-name"><?= htmlspecialchars($row['document_name']) ?></div>
                            <div class="doc-sub"><?= htmlspecialchars($row['student_name']) ?> &middot; <?= htmlspecialchars($row['programme_name']) ?></div>
                        </td>
                        <td><span class="date"><?= htmlspecialchars($row['document_type']) ?></span></td>
                        <td><span class="date"><?= htmlspecialchars($row['uploaded_by']) ?></span></td>
                        <td><span class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></span></td>
                        <td>
                                <span class="badge <?= $badges[$row['status']] ?? 'badge-pending' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                </span>
                        </td>
                        <td>
                            <a href="../agent/review.php?id=<?= $row['id'] ?>" class="btn-view">View</a>
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