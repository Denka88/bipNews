<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!hasMinimumRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Нет доступа']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $filename = basename($_GET['filename'] ?? '');
    if ($filename) {
        $path = __DIR__ . '/../uploads/news/' . $filename;
        if (file_exists($path)) {
            unlink($path);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Файл не найден']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Ошибка загрузки']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($_FILES['image']['type'], $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Недопустимый формат']);
    exit;
}

if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Максимум 5 МБ']);
    exit;
}

$ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filename = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
$path = __DIR__ . '/../uploads/news/' . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка сохранения']);
}
