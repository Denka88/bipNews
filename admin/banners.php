<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

// Загрузка баннера
if (isset($_POST['upload_banner'])) {
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;

        if (!in_array($_FILES['banner_image']['type'], $allowedTypes)) {
            $error = 'Допустимые форматы: JPEG, PNG, GIF, WebP';
        } elseif ($_FILES['banner_image']['size'] > $maxSize) {
            $error = 'Размер не должен превышать 5 МБ';
        } else {
            $extension = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
            $filename = 'banner_' . time() . '.' . $extension;
            $uploadPath = __DIR__ . '/../uploads/banners/' . $filename;

            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadPath)) {
                $sortOrder = (int)($_POST['sort_order'] ?? 0);

                $stmt = $pdo->prepare("INSERT INTO banners (image, sort_order) VALUES (?, ?)");
                $stmt->execute([$filename, $sortOrder]);
                $success = 'Баннер успешно загружен';
            } else {
                $error = 'Ошибка при загрузке файла';
            }
        }
    }
}

// Удаление баннера
if (isset($_POST['delete_banner'])) {
    $bannerId = (int)$_POST['banner_id'];
    $stmt = $pdo->prepare("SELECT image FROM banners WHERE id = ?");
    $stmt->execute([$bannerId]);
    $banner = $stmt->fetch();

    if ($banner && file_exists(__DIR__ . '/../uploads/banners/' . $banner['image'])) {
        unlink(__DIR__ . '/../uploads/banners/' . $banner['image']);
    }

    $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$bannerId]);
    $success = 'Баннер удалён';
}

// Переключение активности
if (isset($_POST['toggle_active'])) {
    $bannerId = (int)$_POST['banner_id'];
    $pdo->prepare("UPDATE banners SET active = NOT active WHERE id = ?")->execute([$bannerId]);
}

// Получаем все баннеры
$banners = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll();

$pageTitle = 'Управление баннерами - BipNews';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Управление баннерами</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php" class="active">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="form-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- Форма загрузки -->
    <div style="margin-bottom: 40px; padding: 25px; border: 1px solid #000;">
        <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Загрузить баннер</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="banner-image">Изображение *</label>
                <input type="file" id="banner-image" name="banner_image" accept="image/*" required>
                <small>Рекомендуемый размер: 1920x600px. Максимум 5 МБ</small>
            </div>

            <div class="form-group">
                <label for="sort-order">Порядок отображения</label>
                <input type="number" id="sort-order" name="sort_order" value="0" style="max-width: 150px;">
            </div>

            <button type="submit" name="upload_banner" class="btn btn-accent">Загрузить баннер</button>
        </form>
    </div>

    <!-- Список баннеров -->
    <?php if (empty($banners)): ?>
        <p style="color: #999;">Баннеров пока нет. Загрузите первый баннер выше.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Превью</th>
                    <th>Порядок</th>
                    <th>Активен</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banners as $banner): ?>
                    <tr>
                        <td>
                            <img src="../uploads/banners/<?= e($banner['image']) ?>" alt="" style="width: 160px; height: 50px; object-fit: cover;">
                        </td>
                        <td><?= $banner['sort_order'] ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                                <input type="hidden" name="toggle_active" value="1">
                                <button type="submit" class="btn btn-small <?= $banner['active'] ? 'btn-accent' : '' ?>">
                                    <?= $banner['active'] ? 'Да' : 'Нет' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                                <input type="hidden" name="delete_banner" value="1">
                                <button type="submit" class="btn btn-danger btn-small" data-confirm-delete="Удалить баннер?">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>
