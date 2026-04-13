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

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_news'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imagesRaw = $_POST['images_data'] ?? '';
    $removedImages = $_POST['removed_images'] ?? '';

    if (empty($title)) $errors[] = 'Заголовок обязателен';
    if (empty($content)) $errors[] = 'Содержание обязательно';

    if (empty($errors)) {
        $content = sanitizeNewsContent($content);

        $existingImages = !empty($news['images']) ? json_decode($news['images'], true) : [];
        if (!empty($removedImages)) {
            $toRemove = array_filter(array_map('trim', explode(',', $removedImages)));
            foreach ($toRemove as $rm) {
                $key = array_search($rm, $existingImages);
                if ($key !== false) {
                    unset($existingImages[$key]);
                    $imgPath = __DIR__ . '/../uploads/news/' . $rm;
                    if (file_exists($imgPath)) unlink($imgPath);
                }
            }
            $existingImages = array_values($existingImages);
        }

        if (!empty($imagesRaw)) {
            $newImages = array_filter(array_map('trim', explode(',', $imagesRaw)));
            foreach ($newImages as $ni) {
                if (!in_array($ni, $existingImages)) {
                    $existingImages[] = $ni;
                }
            }
        }

        $imagesJson = !empty($existingImages) ? json_encode($existingImages) : null;

        $updateStmt = $pdo->prepare("UPDATE news SET title = ?, content = ?, images = ? WHERE id = ?");
        try {
            $updateStmt->execute([$title, $content, $imagesJson, $newsId]);
            $success = 'Новость обновлена';

            $stmt->execute([$newsId]);
            $news = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при обновлении';
        }
    }
}

$existingImages = !empty($news['images']) ? json_decode($news['images'], true) : [];

$pageTitle = 'Редактировать новость - Админ-панель';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Редактировать новость</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="feedback.php">Обратная связь</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="form-error">
            <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="form-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="news-editor">
        <form method="POST" id="news-form">
            <input type="hidden" name="edit_news" value="1">
            <input type="hidden" name="images_data" id="images-data" value="">
            <input type="hidden" name="removed_images" id="removed-images" value="">

            <div class="form-group">
                <label for="title">Заголовок новости *</label>
                <input type="text" id="title" name="title" required value="<?= e($news['title']) ?>" placeholder="Введите заголовок">
            </div>

            <div class="form-group">
                <label for="content">Содержание новости *</label>
                <div style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="btn-upload-image" class="btn btn-small">Загрузить изображение</button>
                    <button type="button" id="btn-insert-url" class="btn btn-small">Вставить по URL</button>
                </div>
                <input type="file" id="image-file-input" accept="image/*" style="display:none;">
                <textarea id="news-content" name="content" required style="min-height: 400px;"><?= e(str_replace('../uploads/news/', 'uploads/news/', $news['content'])) ?></textarea>
            </div>

            <!-- Существующие картинки -->
            <div id="uploaded-images-container" class="uploaded-images">
                <?php foreach ($existingImages as $img): ?>
                    <?php if (file_exists(__DIR__ . '/../uploads/news/' . $img)): ?>
                        <div class="uploaded-image-item" data-filename="<?= e($img) ?>">
                            <img src="../uploads/news/<?= e($img) ?>" alt="Превью">
                            <div>
                                <p><?= e($img) ?></p>
                                <div style="display:flex; gap:8px; margin-top:5px;">
                                    <button type="button" class="btn btn-small btn-accent btn-insert" data-filename="<?= e($img) ?>">Вставить в текст</button>
                                    <button type="button" class="btn btn-small btn-danger btn-remove" data-filename="<?= e($img) ?>">Удалить</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-accent">Сохранить</button>
                <a href="index.php" class="btn">Отмена</a>
                <a href="../news.php?slug=<?= e($news['slug']) ?>" class="btn" target="_blank">Просмотр</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var contentEl = document.getElementById('news-content');
    var fileInput = document.getElementById('image-file-input');
    var uploadBtn = document.getElementById('btn-upload-image');
    var urlBtn = document.getElementById('btn-insert-url');
    var imagesContainer = document.getElementById('uploaded-images-container');
    var imagesDataInput = document.getElementById('images-data');
    var removedInput = document.getElementById('removed-images');

    var newFiles = [];
    var removedFiles = [];

    uploadBtn.addEventListener('click', function() { fileInput.click(); });

    fileInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = e.target.result;
            var formData = new FormData();
            formData.append('image', file);

            fetch('upload_image.php', { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        newFiles.push(data.filename);
                        updateData();
                        addPreview(data.filename, preview, false);
                    } else {
                        alert('Ошибка: ' + (data.message || 'Неизвестная'));
                    }
                })
                .catch(function() { alert('Ошибка загрузки'); });
        };
        reader.readAsDataURL(file);
        this.value = '';
    });

    function addPreview(filename, url, isExisting) {
        var item = document.createElement('div');
        item.className = 'uploaded-image-item';
        item.setAttribute('data-filename', filename);
        item.innerHTML =
            '<img src="' + url + '" alt="Превью">' +
            '<div>' +
                '<p>' + filename + '</p>' +
                '<div style="display:flex; gap:8px; margin-top:5px;">' +
                    '<button type="button" class="btn btn-small btn-accent btn-insert" data-filename="' + filename + '">Вставить в текст</button>' +
                    '<button type="button" class="btn btn-small btn-danger btn-remove" data-filename="' + filename + '">Удалить</button>' +
                '</div>' +
            '</div>';
        imagesContainer.appendChild(item);
    }

    function updateData() {
        imagesDataInput.value = newFiles.join(',');
        removedInput.value = removedFiles.join(',');
    }

    imagesContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-insert') && contentEl) {
            var fn = e.target.getAttribute('data-filename');
            var html = '\n<img src="uploads/news/' + fn + '" alt="Изображение">\n';
            var start = contentEl.selectionStart;
            var text = contentEl.value;
            var insert = '\n' + html + '\n';
            contentEl.value = text.substring(0, start) + insert + text.substring(start);
            contentEl.focus();
            contentEl.selectionStart = contentEl.selectionEnd = start + insert.length;
        }

        if (e.target.classList.contains('btn-remove')) {
            var filename = e.target.getAttribute('data-filename');
            if (!confirm('Удалить изображение?')) return;

            var isNew = newFiles.indexOf(filename) !== -1;
            if (!isNew) {
                removedFiles.push(filename);
                updateData();
                fetch('upload_image.php?action=delete&filename=' + encodeURIComponent(filename), { method: 'POST' });
            } else {
                var idx = newFiles.indexOf(filename);
                if (idx > -1) newFiles.splice(idx, 1);
                updateData();
                fetch('upload_image.php?action=delete&filename=' + encodeURIComponent(filename), { method: 'POST' });
            }

            var item = e.target.closest('.uploaded-image-item');
            if (item) item.remove();
        }
    });

    urlBtn.addEventListener('click', function() {
        var url = prompt('Введите URL изображения:');
        if (url && contentEl) {
            url = url.trim();
            if (!url.match(/^https?:\/\/[^\s"'<>]+$/i)) {
                alert('Введите корректный URL, начинающийся с http:// или https://');
                return;
            }
            var html = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="Изображение">';
            var start = contentEl.selectionStart;
            var text = contentEl.value;
            var insert = '\n' + html + '\n';
            contentEl.value = text.substring(0, start) + insert + text.substring(start);
            contentEl.focus();
            contentEl.selectionStart = contentEl.selectionEnd = start + insert.length;
        }
    });
});
</script>

<?php require '../includes/footer.php'; ?>
