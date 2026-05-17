<?php
require_once 'config/app.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare("SELECT file_path, document_name FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit;
}

$fullPath = __DIR__ . '/' . $doc['file_path'];

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($doc['document_name']) . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;