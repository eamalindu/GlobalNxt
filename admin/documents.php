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
    <title>Documents — GlobalNxt Admin</title>
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

        /* Status filter tabs */
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

        /* Search + filter bar */
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
        .btn-reset:hover { background: #f5f5f3; color: #333; }

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

        .btn-view {
            font-size: 12px;
            font-weight: 500;
            padding: 5px 14px;
            border-radius: 6px;
            border: 0.5px solid #ddd;
            background: #fff;
            color: #1a1a1a;
            text-decoration: none;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .btn-view:hover { background: #f5f5f3; color: #1a1a1a; }

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
            .page-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            thead th:nth-child(3),
            tbody td:nth-child(3),
            thead th:nth-child(4),
            tbody td:nth-child(4) { display: none; }
        }
        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            thead th:nth-child(5),
            tbody td:nth-child(5) { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">GlobalNxt <span>/ Admin</span></a>
    <div class="navbar-right">
        <div class="nav-links">
            <a href="index.php" class="nav-link">Dashboard</a>
            <a href="documents.php" class="nav-link active">Documents</a>
            <a href="users.php" class="nav-link">Users</a>
            <a href="audit.php" class="nav-link">Audit log</a>
        </div>
        <span class="nav-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
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