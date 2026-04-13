<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();
$newsId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$newsId]);
$news = $stmt->fetch();

if (!$news) {
    redirect404();
}

if (!empty($news['images'])) {
    $images = json_decode($news['images'], true);
    foreach ($images as $image) {
        $imagePath = __DIR__ . '/../uploads/news/' . $image;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
}

$deleteStmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
$deleteStmt->execute([$newsId]);

redirect('index.php');
