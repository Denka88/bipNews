<?php
require_once '../includes/functions.php';

// Проверка прав доступа (только администраторы)
if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();
$newsId = (int)($_GET['id'] ?? 0);

// Получаем новость
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$newsId]);
$news = $stmt->fetch();

if (!$news) {
    http_response_code(404);
    echo "Новость не найдена";
    exit;
}

// Удаляем изображения
if (!empty($news['images'])) {
    $images = json_decode($news['images'], true);
    foreach ($images as $image) {
        $imagePath = __DIR__ . '/../uploads/news/' . $image;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
}

// Удаляем новость (комментарии удалятся каскадно)
$deleteStmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
$deleteStmt->execute([$newsId]);

// Перенаправляем в админ-панель
redirect('index.php');
