<?php
require_once '../config/app.php';
require_once '../config/db.php';
require_once '../config/mail.php';

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

            // Fetch staff email for notification
            $staffQuery = $pdo->prepare("SELECT u.email, u.username FROM users u WHERE u.id = ?");
            $staffQuery->execute([$doc['uploaded_by']]);
            $staffUser = $staffQuery->fetch();

            $reviewedDate = date('d M Y, h:i A');

            if ($action === 'approved') {
                sendApprovedEmail(
                        $staffUser['username'],
                        $staffUser['email'],
                        $_SESSION['username'],
                        $doc['student_name'],
                        $doc['programme_name'],
                        $reviewedDate
                );
            } else {
                sendRejectedEmail(
                        $staffUser['username'],
                        $staffUser['email'],
                        $_SESSION['username'],
                        $doc['student_name'],
                        $doc['programme_name'],
                        $reviewedDate,
                        $remarks
                );
            }

            // Audit log — document reviewed
            $log = $pdo->prepare("
    INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
    VALUES (?, ?, 'document', ?, ?, ?)
");
            $log->execute([
                    $_SESSION['user_id'],
                    'document_' . $action,
                    $id,
                    'Agent ' . $action . ' document: ' . $doc['document_name'] . ' for ' . $doc['student_name'],
                    $_SERVER['REMOTE_ADDR']
            ]);
        }
    }
}

$isReviewed = in_array($doc['status'], ['approved', 'rejected']);
?>
<!doctype html>
<html lang="en">
<head>
    <?php include_once("../includes/header.php");
    ?>
    <title>Review Document | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/review.css">
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

<div class="page">

    <div class="page-header">
        <a href="index.php" class="text-decoration-underline">
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
                            <input type="hidden" name="action" id="actionInput" value="">
                            <div class="form-group">
                                <label>Remarks <span style="color:#bbb;font-weight:400">(required for rejection)</span></label>
                                <textarea name="remarks" placeholder="Add remarks or reason for rejection..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                            </div>
                            <div class="action-buttons">
                                <button type="submit" onclick="setAction('approved')" class="btn-approve">Approve</button>
                                <button type="submit" onclick="setAction('rejected')" class="btn-reject">Reject</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    function setAction(action) {
        document.getElementById('actionInput').value = action;
    }

    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                return;
            }

            let clickedBtn = document.activeElement;

            this.querySelectorAll('button[type="submit"]').forEach(b => {
                b.disabled = true;
                b.style.opacity = '0.5';
                b.style.cursor = 'not-allowed';
            });

            if (clickedBtn && clickedBtn.type === 'submit') {
                clickedBtn.textContent = 'Please wait...';
            }
        });
    });
</script>
</body>
</html>