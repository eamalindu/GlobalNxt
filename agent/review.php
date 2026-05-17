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
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        d.*,
        u.username as staff_name
    FROM documents d
    JOIN users u ON d.uploaded_by = u.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    header('Location: index.php');
    exit;
}

$error   = null;
$success = null;

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Block re-review
    if ($doc['status'] !== 'pending' && $doc['status'] !== 'under_review') {
        $error = 'This document has already been reviewed and cannot be changed.';
    } else {
        $action  = $_POST['action'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if (!in_array($action, ['approved', 'rejected'])) {
            $error = 'Invalid action.';
        } elseif ($action === 'rejected' && empty($remarks)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            $update = $pdo->prepare("
                UPDATE documents
                SET status      = ?,
                    remarks     = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$action, $remarks, $_SESSION['user_id'], $id]);

            // Refresh doc data
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            $success = 'Document has been ' . $action . ' successfully.';
        }
    }
}

$isReviewed = in_array($doc['status'], ['approved', 'rejected']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Document — GlobalNxt</title>
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
        .navbar-brand {
            font-size: 14px;
            font-weight: 500;
            color: #1a1a1a;
            text-decoration: none;
        }
        .navbar-brand span { color: #888; font-weight: 400; }
        .navbar-right { display: flex; align-items: center; gap: 1rem; }
        .nav-user { font-size: 13px; color: #555; }
        .nav-logout {
            font-size: 12px;
            color: #888;
            text-decoration: none;
            padding: 5px 12px;
            border: 0.5px solid #ddd;
            border-radius: 6px;
        }
        .nav-logout:hover { background: #f5f5f3; }

        .page { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem; }

        .page-header { margin-bottom: 1.75rem; }
        .page-header a {
            font-size: 12px;
            color: #aaa;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 0.75rem;
        }
        .page-header a:hover { color: #555; }
        .page-header h2 { font-size: 18px; font-weight: 500; }
        .page-header p  { font-size: 13px; color: #888; margin-top: 3px; }

        .layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.25rem;
            align-items: start;
        }

        .card {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 0.5px solid #f0f0f0;
            font-size: 12px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .card-body { padding: 1.25rem; }

        /* PDF Preview */
        .pdf-preview {
            width: 100%;
            height: 600px;
            border: none;
            display: block;
            background: #f5f5f3;
        }
        .pdf-fallback {
            padding: 3rem 1.25rem;
            text-align: center;
            color: #aaa;
            font-size: 13px;
        }
        .pdf-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-bottom: 0.5px solid #f0f0f0;
            background: #fafafa;
        }
        .pdf-filename { font-size: 12px; color: #888; }
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 500;
            padding: 5px 14px;
            border-radius: 6px;
            border: 0.5px solid #ddd;
            background: #fff;
            color: #1a1a1a;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn-download:hover { background: #f5f5f3; color: #1a1a1a; }

        /* Meta info */
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 0.5px solid #f5f5f5;
            font-size: 13px;
        }
        .meta-row:last-child { border-bottom: none; }
        .meta-label { color: #aaa; font-size: 12px; }
        .meta-value { color: #1a1a1a; font-weight: 500; text-align: right; max-width: 60%; }

        /* Badges */
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

        /* Action form */
        .action-section { margin-top: 1.25rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #555;
            margin-bottom: 5px;
        }
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            font-size: 13px;
            border: 0.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #1a1a1a;
            outline: none;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .form-group textarea:focus { border-color: #aaa; }

        .action-buttons { display: flex; gap: 10px; }
        .btn-approve {
            flex: 1;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            background: #1e6b3a;
            color: #fff;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .btn-approve:hover { opacity: 0.85; }
        .btn-reject {
            flex: 1;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            background: #fff;
            color: #911f2a;
            border: 0.5px solid #f5c6c8;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-reject:hover { background: #fdf0f0; }

        /* Reviewed state */
        .reviewed-banner {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 1.25rem;
        }
        .reviewed-banner.approved { background: #f0faf4; color: #1e6b3a; border: 0.5px solid #b8dfc8; }
        .reviewed-banner.rejected { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }
        .reviewed-banner p { margin-top: 5px; font-size: 12px; opacity: 0.8; }

        /* Alerts */
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .alert-error   { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }
        .alert-success { background: #f0faf4; color: #1e6b3a; border: 0.5px solid #b8dfc8; }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user { display: none; }
            .page { padding: 1.25rem 1rem; }
            .layout { grid-template-columns: 1fr; }
            .pdf-preview { height: 400px; }
        }

        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            .action-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">GlobalNxt <span>/ Agent</span></a>
    <div class="navbar-right">
        <span class="nav-user"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../logout.php" class="nav-logout">Log out</a>
    </div>
</nav>

<div class="page">

    <div class="page-header">
        <a href="index.php">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to dashboard
        </a>
        <h2>Review document</h2>
        <p>Submitted on <?= date('d M Y, h:i A', strtotime($doc['created_at'])) ?> by <?= htmlspecialchars($doc['staff_name']) ?></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="layout">

        <!-- Left: PDF Preview -->
        <div class="card">
            <div class="pdf-toolbar">
                <span class="pdf-filename"><?= htmlspecialchars($doc['document_name']) ?></span>
                <a href="../serve_pdf.php?id=<?= $doc['id'] ?>" download class="btn-download">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download
                </a>
            </div>
            <iframe
                src="../serve_pdf.php?id=<?= $doc['id'] ?>"
                class="pdf-preview"
                type="application/pdf">
                <div class="pdf-fallback">
                    <p>PDF preview not supported in your browser.</p>
                    <a href="../serve_pdf.php?id=<?= $doc['id'] ?>" download class="btn-download" style="margin-top:1rem; display:inline-flex;">Download PDF</a>
                </div>
            </iframe>
        </div>

        <!-- Right: Details + Actions -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            <!-- Student Info -->
            <div class="card">
                <div class="card-header">Student information</div>
                <div class="card-body">
                    <div class="meta-row">
                        <span class="meta-label">Student name</span>
                        <span class="meta-value"><?= htmlspecialchars($doc['student_name']) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Student ID</span>
                        <span class="meta-value"><?= htmlspecialchars($doc['student_id']) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Programme</span>
                        <span class="meta-value"><?= htmlspecialchars($doc['programme_name']) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Document type</span>
                        <span class="meta-value"><?= htmlspecialchars($doc['document_type']) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Status</span>
                        <span class="meta-value">
                            <?php
                            $badges = [
                                'pending'      => 'badge-pending',
                                'under_review' => 'badge-review',
                                'approved'     => 'badge-approved',
                                'rejected'     => 'badge-rejected',
                            ];
                            ?>
                            <span class="badge <?= $badges[$doc['status']] ?? 'badge-pending' ?>">
                                <?= ucfirst(str_replace('_', ' ', $doc['status'])) ?>
                            </span>
                        </span>
                    </div>
                    <?php if (!empty($doc['notes'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">Staff notes</span>
                            <span class="meta-value"><?= htmlspecialchars($doc['notes']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action -->
            <div class="card">
                <div class="card-header">Decision</div>
                <div class="card-body">
                    <?php if ($isReviewed): ?>
                        <div class="reviewed-banner <?= $doc['status'] ?>">
                            <strong><?= ucfirst($doc['status']) ?></strong>
                            <?php if (!empty($doc['remarks'])): ?>
                                <p><?= htmlspecialchars($doc['remarks']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label>Remarks <span style="color:#bbb;font-weight:400">(required for rejection)</span></label>
                                <textarea name="remarks" placeholder="Add remarks or reason for rejection..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                            </div>
                            <div class="action-buttons">
                                <button type="submit" name="action" value="approved" class="btn-approve">Approve</button>
                                <button type="submit" name="action" value="rejected" class="btn-reject">Reject</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>