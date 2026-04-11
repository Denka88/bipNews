<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_news'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imagesRaw = $_POST['images_data'] ?? '';

    if (empty($title)) $errors[] = 'Заголовок обязателен';
    if (empty($content)) $errors[] = 'Содержание обязательно';

    if (empty($errors)) {
        // Санитизация контента (удаляем опасные теги и атрибуты)
        $content = sanitizeNewsContent($content);

        // Парсим список картинок
        $images = [];
        if (!empty($imagesRaw)) {
            $images = array_filter(array_map('trim', explode(',', $imagesRaw)));
        }
        $imagesJson = !empty($images) ? json_encode($images) : null;

        $slug = generateSlug($title);
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE slug = ?");
        $checkStmt->execute([$slug]);
        if ($checkStmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        $insertStmt = $pdo->prepare("INSERT INTO news (title, slug, content, images, author_id) VALUES (?, ?, ?, ?, ?)");
        try {
            $insertStmt->execute([$title, $slug, $content, $imagesJson, $_SESSION['user_id']]);
            redirect('../news.php?slug=' . $slug);
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при создании новости';
        }
    }

    $savedTitle = $_POST['title'] ?? '';
    $savedContent = $_POST['content'] ?? '';
}

$pageTitle = 'Создать новость - Админ-панель';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Создать новость</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php" class="active">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="form-error">
            <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="news-editor">

        <!-- Инструкция -->
        <div class="news-help">
            <button type="button" class="news-help-toggle" onclick="toggleNewsHelp()">Как создать новость?</button>
            <div class="news-help-content" id="news-help-content" style="display:none;">
                <h3><i class="fas fa-lightbulb"></i> Советы по созданию новости</h3>

                <div class="help-section">
                    <h4><i class="fas fa-heading"></i> Заголовок</h4>
                    <p>Краткий и информативный, например: <strong>«День открытых дверей в техникуме»</strong></p>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-align-left"></i> Содержание</h4>
                    <p>Пишите текст обычным способом — абзацы разделяются пустой строкой (Enter дважды).</p>
                    <p>Можно использовать <strong>HTML-теги</strong> для форматирования:</p>
                    <ul>
                        <li><code>&lt;p&gt;...&lt;/p&gt;</code> — абзац</li>
                        <li><code>&lt;b&gt;</code> / <code>&lt;strong&gt;</code> — <strong>жирный текст</strong></li>
                        <li><code>&lt;i&gt;</code> / <code>&lt;em&gt;</code> — <em>курсив</em></li>
                        <li><code>&lt;u&gt;</code> — <u>подчёркивание</u></li>
                        <li><code>&lt;ul&gt;&lt;li&gt;...&lt;/li&gt;&lt;/ul&gt;</code> — маркированный список</li>
                        <li><code>&lt;ol&gt;&lt;li&gt;...&lt;/li&gt;&lt;/ol&gt;</code> — нумерованный список</li>
                        <li><code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code>, <code>&lt;h4&gt;</code> — подзаголовки</li>
                        <li><code>&lt;blockquote&gt;...&lt;/blockquote&gt;</code> — цитата</li>
                        <li><code>&lt;a href="..."&gt;...&lt;/a&gt;</code> — ссылка (откроется в новой вкладке)</li>
                        <li><code>&lt;br&gt;</code> — перенос строки</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-image"></i> Изображения</h4>
                    <p><strong>Загрузить с компьютера:</strong> нажмите кнопку «Загрузить изображение», выберите файл. Картинка появится в панели ниже — нажмите «Вставить в текст» в нужном месте.</p>
                    <p><strong>Вставить по URL:</strong> нажмите «Вставить по URL», вставьте ссылку на изображение из интернета (должна начинаться с <code>https://</code>).</p>
                    <p>Поддерживаемые форматы: <strong>JPG, PNG, GIF, WebP</strong> (макс. 5 МБ).</p>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-exclamation-triangle"></i> Что нельзя использовать</h4>
                    <p>В целях безопасности запрещены: <code>&lt;script&gt;</code>, <code>&lt;iframe&gt;</code>, <code>&lt;object&gt;</code>, <code>&lt;embed&gt;</code>, <code>&lt;form&gt;</code>, атрибуты <code>on*</code> (onclick, onerror и др.), <code>style</code>, ссылки вида <code>javascript:</code>.</p>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-magic"></i> Пример быстрой новости</h4>
                    <div class="help-example">
&lt;h2&gt;Внимание, расписание экзаменов!&lt;/h2&gt;
&lt;p&gt;Уважаемые студенты, публикую расписание экзаменационной сессии.&lt;/p&gt;
&lt;ul&gt;
&lt;li&gt;15 мая — Математика&lt;/li&gt;
&lt;li&gt;17 мая — Русский язык&lt;/li&gt;
&lt;li&gt;19 мая — Информатика&lt;/li&gt;
&lt;/ul&gt;
&lt;p&gt;Подробнее — &lt;a href="#"&gt;на сайте техникума&lt;/a&gt;&lt;/p&gt;
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="news-form">
            <input type="hidden" name="create_news" value="1">
            <input type="hidden" name="images_data" id="images-data" value="">

            <div class="form-group">
                <label for="title">Заголовок новости *</label>
                <input type="text" id="title" name="title" required value="<?= e($savedTitle ?? '') ?>" placeholder="Введите заголовок">
            </div>

            <div class="form-group">
                <label for="content">Содержание новости *</label>
                <div style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="btn-upload-image" class="btn btn-small">Загрузить изображение</button>
                    <button type="button" id="btn-insert-url" class="btn btn-small">Вставить по URL</button>
                </div>
                <input type="file" id="image-file-input" accept="image/*" style="display:none;">
                <textarea id="news-content" name="content" required placeholder="Введите текст новости" style="min-height: 400px;"><?= e($savedContent ?? '') ?></textarea>
            </div>

            <!-- Загруженные картинки -->
            <div id="uploaded-images-container" class="uploaded-images"></div>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-accent">Создать новость</button>
                <a href="index.php" class="btn">Отмена</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contentEl = document.getElementById('news-content');
    const fileInput = document.getElementById('image-file-input');
    const uploadBtn = document.getElementById('btn-upload-image');
    const urlBtn = document.getElementById('btn-insert-url');
    const imagesContainer = document.getElementById('uploaded-images-container');
    const imagesDataInput = document.getElementById('images-data');

    // Массив загруженных файлов
    let uploadedFiles = [];

    // Нажатие на кнопку загрузки
    uploadBtn.addEventListener('click', function() {
        fileInput.click();
    });

    // Выбор файла
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        // Превью
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewUrl = e.target.result;

            // Отправка на сервер
            const formData = new FormData();
            formData.append('image', file);

            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    // Добавляем в массив
                    uploadedFiles.push(data.filename);
                    updateImagesData();

                    // Добавляем превью
                    addImagePreview(data.filename, previewUrl);
                } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная'));
                }
            })
            .catch(function() {
                alert('Ошибка загрузки');
            });
        };
        reader.readAsDataURL(file);

        this.value = '';
    });

    // Добавить превью в контейнер
    function addImagePreview(filename, previewUrl) {
        var item = document.createElement('div');
        item.className = 'uploaded-image-item';
        item.setAttribute('data-filename', filename);

        item.innerHTML =
            '<img src="' + previewUrl + '" alt="Превью">' +
            '<div>' +
                '<p>' + filename + '</p>' +
                '<div style="display:flex; gap:8px; margin-top:5px;">' +
                    '<button type="button" class="btn btn-small btn-accent btn-insert" data-filename="' + filename + '">Вставить в текст</button>' +
                    '<button type="button" class="btn btn-small btn-danger btn-remove" data-filename="' + filename + '">Удалить</button>' +
                '</div>' +
            '</div>';

        imagesContainer.appendChild(item);
    }

    // Обновить скрытое поле
    function updateImagesData() {
        imagesDataInput.value = uploadedFiles.join(',');
    }

    // Клик по кнопкам (делегирование)
    imagesContainer.addEventListener('click', function(e) {
        // Вставить
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

        // Удалить
        if (e.target.classList.contains('btn-remove')) {
            var filename = e.target.getAttribute('data-filename');
            if (!confirm('Удалить изображение?')) return;

            // Удаляем с сервера
            fetch('upload_image.php?action=delete&filename=' + encodeURIComponent(filename), {
                method: 'POST'
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    // Удаляем из массива
                    var idx = uploadedFiles.indexOf(filename);
                    if (idx > -1) uploadedFiles.splice(idx, 1);
                    updateImagesData();
                }
            })
            .catch(function() {});

            // Удаляем превью
            var item = e.target.closest('.uploaded-image-item');
            if (item) item.remove();
        }
    });

    // Вставка по URL
    urlBtn.addEventListener('click', function() {
        var url = prompt('Введите URL изображения:');
        if (url && contentEl) {
            // Проверяем, что это допустимый URL картинки
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

// Переключение блока помощи
function toggleNewsHelp() {
    var content = document.getElementById('news-help-content');
    var btn = document.querySelector('.news-help-toggle');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        btn.textContent = 'Скрыть подсказку';
    } else {
        content.style.display = 'none';
        btn.textContent = 'Как создать новость?';
    }
}
</script>

<?php require '../includes/footer.php'; ?>
