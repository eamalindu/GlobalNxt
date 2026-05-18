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
    <title>Re-upload Document — GlobalNxt</title>
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
        .nav-logout {
            font-size: 12px;
            color: #888;
            text-decoration: none;
            padding: 5px 12px;
            border: 0.5px solid #ddd;
            border-radius: 6px;
        }
        .nav-logout:hover { background: #f5f5f3; }

        .page { max-width: 620px; margin: 0 auto; padding: 2rem 1.5rem; }

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

        /* Rejection banner */
        .rejection-banner {
            background: #fdf0f0;
            border: 0.5px solid #f5c6c8;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }
        .rejection-banner-title {
            font-size: 12px;
            font-weight: 500;
            color: #911f2a;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .rejection-banner-reason {
            font-size: 13px;
            color: #911f2a;
            opacity: 0.85;
        }

        /* Original doc info */
        .original-info {
            background: #f5f5f3;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }
        .original-info-title {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.75rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .info-item-label { font-size: 11px; color: #aaa; margin-bottom: 2px; }
        .info-item-value { font-size: 13px; font-weight: 500; color: #1a1a1a; }

        /* Card */
        .card {
            background: #fff;
            border: 0.5px solid #e8e8e8;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .section-label {
            font-size: 11px;
            font-weight: 500;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 0.5px solid #f0f0f0;
        }

        /* File upload */
        .file-drop {
            border: 1px dashed #ddd;
            border-radius: 10px;
            padding: 2rem 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
            position: relative;
        }
        .file-drop:hover { border-color: #aaa; background: #fafafa; }
        .file-drop.has-file { border-color: #2a7a4b; background: #f0faf4; }
        .file-drop input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .file-drop p { font-size: 13px; color: #aaa; }
        .file-drop p strong { color: #555; }
        .file-name { font-size: 12px; color: #2a7a4b; margin-top: 6px; font-weight: 500; }
        .file-hint { font-size: 11px; color: #bbb; margin-top: 5px; }

        /* Alert */
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .alert-error { background: #fdf0f0; color: #911f2a; border: 0.5px solid #f5c6c8; }

        /* Actions */
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; }
        .btn-cancel {
            padding: 9px 20px;
            font-size: 13px;
            border: 0.5px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #555;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-cancel:hover { background: #f5f5f3; }
        .btn-submit {
            padding: 9px 24px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            background: #1a1a1a;
            color: #fff;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .btn-submit:hover { opacity: 0.85; }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; }
            .nav-user { display: none; }
            .page { padding: 1.25rem 1rem; }
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .navbar-brand span { display: none; }
            .form-actions { flex-direction: column; }
            .btn-cancel, .btn-submit { width: 100%; justify-content: center; text-align: center; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">GlobalNxt <span>/ Staff</span></a>
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