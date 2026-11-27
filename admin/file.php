<?php
// Streams a customer's uploaded file to a logged-in admin (the uploads folder itself is not public).
require __DIR__ . '/_inc.php';
require_admin();

$stmt = ic_db()->prepare('SELECT * FROM order_files WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$file = $stmt->fetch();
$path = $file ? ic_uploads_dir() . '/' . basename($file['stored_name']) : '';
if (!$file || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

// Only plain image types are ever shown inline; everything else downloads
$inlineTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$ext = strtolower(pathinfo($file['stored_name'], PATHINFO_EXTENSION));
$inline = !empty($_GET['inline']) && isset($inlineTypes[$ext]);

ic_discard_output();

header('Content-Type: ' . ($inline ? $inlineTypes[$ext] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($file['original_name']));
header('Cache-Control: private, max-age=3600');
readfile($path);
