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

$error   = null;
$success = null;

// PRG — pick up success flag from redirect
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Document uploaded successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentName    = trim($_POST['student_name'] ?? '');
    $studentId      = trim($_POST['student_id'] ?? '');
    $programmeName  = trim($_POST['programme_name'] ?? '');
    $documentType   = trim($_POST['document_type'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    // Validate text fields
    if (empty($studentName) || empty($studentId) || empty($programmeName) || empty($documentType)) {
        $error = 'Please fill in all required fields.';
    }

    // Validate file
    elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a PDF file to upload.';
    }

    else {
        $file     = $_FILES['document'];
        $fileSize = $file['size'];
        $fileMime = mime_content_type($file['tmp_name']);

        if ($fileMime !== 'application/pdf') {
            $error = 'Only PDF files are allowed.';
        } elseif ($fileSize > MAX_FILE_SIZE) {
            $error = 'File size must not exceed 5MB.';
        } else {

            // Generate unique filename
            $ext          = 'pdf';
            $uniqueName   = uniqid('doc_', true) . '.' . $ext;
            $yearMonth    = date('Y/m');
            $uploadDir    = UPLOAD_DIR . $yearMonth . '/';
            $filePath     = $uploadDir . $uniqueName;
            $relativePath = 'uploads/' . $yearMonth . '/' . $uniqueName;

            // Create directory if not exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate SHA-256 hash
            $fileHash = hash_file('sha256', $file['tmp_name']);

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $pdo  = getDB();
                $stmt = $pdo->prepare("
                    INSERT INTO documents 
                        (uploaded_by, student_name, student_id, programme_name, document_type, document_name, file_path, file_hash, notes, status)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                        $_SESSION['user_id'],
                        $studentName,
                        $studentId,
                        $programmeName,
                        $documentType,
                        $file['name'],
                        $relativePath,
                        $fileHash,
                        $notes
                ]);

                // Audit log — document uploaded
                $docId = $pdo->lastInsertId();
                $log   = $pdo->prepare("
                    INSERT INTO audit_log (user_id, action, target_type, target_id, description, ip_address)
                    VALUES (?, 'document_uploaded', 'document', ?, ?, ?)
                ");
                $log->execute([
                        $_SESSION['user_id'],
                        $docId,
                        'Staff uploaded document: ' . $file['name'] . ' for ' . $studentName,
                        $_SERVER['REMOTE_ADDR']
                ]);

                // PRG — redirect to prevent duplicate submission on refresh
                header('Location: upload.php?success=1');
                exit;

            } else {
                $error = 'Failed to save the file. Please try again.';
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
    <title>Upload Document | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/upload.css">
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
        <h2>Upload document</h2>
        <p>Submit a student document for GlobalNxt agent review</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data" id="frmUpload">

            <div class="section-label">Student information</div>

            <div class="row-2">
                <div class="form-group">
                    <label>Student name <span style="color:#c00">*</span></label>
                    <input type="text" name="student_name" value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Student ID <span style="color:#c00">*</span></label>
                    <input type="text" name="student_id" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" placeholder="e.g. MC2024001" required>
                </div>
            </div>

            <div class="form-group">
                <label>Programme name <span style="color:#c00">*</span></label>
                <input type="text" name="programme_name" value="<?= htmlspecialchars($_POST['programme_name'] ?? '') ?>" placeholder="e.g. BSc Software Engineering" required>
            </div>

            <div class="divider"></div>
            <div class="section-label">Document details</div>

            <div class="form-group">
                <label>Document type <span style="color:#c00">*</span></label>
                <select name="document_type" required>
                    <option value="" disabled selected>Select type</option>
                    <option value="Transcript"     <?= ($_POST['document_type'] ?? '') === 'Transcript'     ? 'selected' : '' ?>>Transcript</option>
                    <option value="Certificate"    <?= ($_POST['document_type'] ?? '') === 'Certificate'    ? 'selected' : '' ?>>Certificate</option>
                    <option value="Identity"       <?= ($_POST['document_type'] ?? '') === 'Identity'       ? 'selected' : '' ?>>Identity</option>
                    <option value="Medical"        <?= ($_POST['document_type'] ?? '') === 'Medical'        ? 'selected' : '' ?>>Medical</option>
                    <option value="Other"          <?= ($_POST['document_type'] ?? '') === 'Other'          ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>PDF file <span style="color:#c00">*</span></label>
                <div class="file-drop" id="fileDrop">
                    <input type="file" name="document" accept=".pdf" id="fileInput" required>
                    <div class="file-drop-icon" id="fileIcon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    </div>
                    <p><strong>Click to choose</strong> or drag and drop</p>
                    <div class="file-name" id="fileName"></div>
                    <div class="file-hint">PDF only — max 5MB</div>
                </div>
            </div>

            <div class="form-group">
                <label>Notes <span style="color:#bbb;font-weight:400">(optional)</span></label>
                <textarea name="notes" placeholder="Any additional notes for the agent..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Submit document</button>
            </div>

        </form>
    </div>
</div>

<script>
    const input    = document.getElementById('fileInput');
    const drop     = document.getElementById('fileDrop');
    const fileName = document.getElementById('fileName');
    const fileIcon = document.getElementById('fileIcon');

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