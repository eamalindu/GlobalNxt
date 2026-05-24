<?php
require_once '../config/app.php';
require_once '../config/db.php';
require_once '../config/constants.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit;
}

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch original document
$stmt = $pdo->prepare("
    SELECT * FROM documents 
    WHERE id = ? AND uploaded_by = ? AND status = 'rejected'
");
$stmt->execute([$id, $_SESSION['user_id']]);
$original = $stmt->fetch();

// Must exist, belong to this staff, and be rejected
if (!$original) {
    header('Location: index.php');
    exit;
}

// Check if this document has already been re-uploaded
$reuploadCheck = $pdo->prepare("
    SELECT id FROM documents WHERE parent_id = ? AND uploaded_by = ?
");
$reuploadCheck->execute([$id, $_SESSION['user_id']]);
$alreadyReuploaded = $reuploadCheck->fetch();

if ($alreadyReuploaded) {
    header('Location: index.php?error=already_reuploaded');
    exit;
}

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate file
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a PDF file to upload.';
    } else {
        $file     = $_FILES['document'];
        $fileMime = mime_content_type($file['tmp_name']);
        $fileSize = $file['size'];

        if ($fileMime !== 'application/pdf') {
            $error = 'Only PDF files are allowed.';
        } elseif ($fileSize > MAX_FILE_SIZE) {
            $error = 'File size must not exceed 5MB.';
        } else {

            // Generate unique filename
            $uniqueName   = uniqid('doc_', true) . '.pdf';
            $yearMonth    = date('Y/m');
            $uploadDir    = UPLOAD_DIR . $yearMonth . '/';
            $filePath     = $uploadDir . $uniqueName;
            $relativePath = 'uploads/' . $yearMonth . '/' . $uniqueName;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileHash = hash_file('sha256', $file['tmp_name']);

            if (move_uploaded_file($file['tmp_name'], $filePath)) {

                // Insert new record with parent_id pointing to original
                $insert = $pdo->prepare("
                    INSERT INTO documents 
                        (uploaded_by, student_name, student_id, programme_name, document_type, document_name, file_path, file_hash, notes, status, parent_id)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $insert->execute([
                    $_SESSION['user_id'],
                    $original['student_name'],
                    $original['student_id'],
                    $original['programme_name'],
                    $original['document_type'],
                    $file['name'],
                    $relativePath,
                    $fileHash,
                    $original['notes'],
                    $original['id']
                ]);

                $newDocId = $pdo->lastInsertId();

                // Audit log
                $log = $pdo->prepare("
                    INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
                    VALUES (?, 'document_uploaded', 'document', ?, ?, ?)
                ");
                $log->execute([
                    $_SESSION['user_id'],
                    $newDocId,
                    'Staff re-uploaded document for: ' . $original['student_name'] . ' (replacing doc #' . $original['id'] . ')',
                    $_SERVER['REMOTE_ADDR']
                ]);

                header('Location: index.php?success=reuploaded');
                exit;

            } else {
                $error = 'Failed to save file. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-upload Document | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/reupload.css">
    <link rel="icon" type="image/ico" href="../favicon.ico"/>

</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">Document Verification <span>/ Staff</span></a>
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
        <h2>Re-upload document</h2>
        <p>Upload a corrected PDF for <?= htmlspecialchars($original['student_name']) ?></p>
    </div>

    <!-- Rejection reason -->
    <?php if (!empty($original['remarks'])): ?>
        <div class="rejection-banner">
            <div class="rejection-banner-title">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Rejection reason
            </div>
            <div class="rejection-banner-reason"><?= htmlspecialchars($original['remarks']) ?></div>
        </div>
    <?php endif; ?>

    <!-- Original document details -->
    <div class="original-info">
        <div class="original-info-title">Original submission details</div>
        <div class="info-grid">
            <div>
                <div class="info-item-label">Student name</div>
                <div class="info-item-value"><?= htmlspecialchars($original['student_name']) ?></div>
            </div>
            <div>
                <div class="info-item-label">Student ID</div>
                <div class="info-item-value"><?= htmlspecialchars($original['student_id']) ?></div>
            </div>
            <div>
                <div class="info-item-label">Programme</div>
                <div class="info-item-value"><?= htmlspecialchars($original['programme_name']) ?></div>
            </div>
            <div>
                <div class="info-item-label">Document type</div>
                <div class="info-item-value"><?= htmlspecialchars($original['document_type']) ?></div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">

            <div class="section-label">Upload corrected PDF</div>

            <div class="file-drop" id="fileDrop">
                <input type="file" name="document" accept=".pdf" id="fileInput" required>
                <div style="margin-bottom:0.5rem;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                </div>
                <p><strong>Click to choose</strong> or drag and drop</p>
                <div class="file-name" id="fileName"></div>
                <div class="file-hint">PDF only — max 5MB</div>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Submit for review</button>
            </div>

        </form>
    </div>
</div>

<script>
    const input    = document.getElementById('fileInput');
    const drop     = document.getElementById('fileDrop');
    const fileName = document.getElementById('fileName');

    input.addEventListener('change', function () {
        if (this.files.length > 0) {
            fileName.textContent = this.files[0].name;
            drop.classList.add('has-file');
        } else {
            fileName.textContent = '';
            drop.classList.remove('has-file');
        }
    });
</script>

</body>
</html>