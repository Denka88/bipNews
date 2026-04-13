<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

$stats = [];
$stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['news'] = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$stats['comments'] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();

$latestNewsStmt = $pdo->query("
    SELECT n.*, u.username
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 10
");
$latestNews = $latestNewsStmt->fetchAll();

$latestCommentsStmt = $pdo->query("
    SELECT c.*, u.username, n.title as news_title, n.slug as news_slug
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN news n ON c.news_id = n.id
    ORDER BY c.created_at DESC
    LIMIT 10
");
$latestComments = $latestCommentsStmt->fetchAll();

$pageTitle = 'Админ-панель - BipNews';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Админ-панель</h1>

    <!-- Навигация -->
    <div class="admin-nav">
        <a href="index.php" class="active">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="feedback.php">Обратная связь</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['users'] ?></div>
            <div class="stat-label">Пользователей</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['news'] ?></div>
            <div class="stat-label">Новостей</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['comments'] ?></div>
            <div class="stat-label">Комментариев</div>
        </div>
    </div>

    <!-- Последние новости -->
    <div class="admin-section">
        <h2 class="section-title">Последние новости</h2>

        <?php if (empty($latestNews)): ?>
            <p style="color: #999;">Новостей пока нет</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Заголовок</th>
                        <th>Автор</th>
                        <th>Просмотры</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestNews as $newsItem): ?>
                        <tr>
                            <td><?= $newsItem['id'] ?></td>
                            <td><a href="../news.php?slug=<?= e($newsItem['slug']) ?>"><?= e(truncateText($newsItem['title'], 50)) ?></a></td>
                            <td><?= e($newsItem['username']) ?></td>
                            <td><?= $newsItem['views'] ?></td>
                            <td><?= formatDate($newsItem['created_at']) ?></td>
                            <td class="admin-actions">
                                <a href="news_edit.php?id=<?= $newsItem['id'] ?>" class="btn btn-small">Редактировать</a>
                                <a href="news_delete.php?id=<?= $newsItem['id'] ?>" class="btn btn-danger btn-small" data-confirm-delete="Удалить новость?">Удалить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Последние комментарии -->
    <div class="admin-section">
        <h2 class="section-title">Последние комментарии</h2>

        <?php if (empty($latestComments)): ?>
            <p style="color: #999;">Комментариев пока нет</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Пользователь</th>
                        <th>Новость</th>
                        <th>Комментарий</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestComments as $comment): ?>
                        <tr>
                            <td><?= $comment['id'] ?></td>
                            <td><a href="../profile.php?id=<?= $comment['user_id'] ?>"><?= e($comment['username']) ?></a></td>
                            <td><a href="../news.php?slug=<?= e($comment['news_slug'] ?? '') ?>"><?= e(truncateText($comment['news_title'], 30)) ?></a></td>
                            <td><?= e(truncateText($comment['content'], 50)) ?></td>
                            <td><?= formatDate($comment['created_at']) ?></td>
                            <td class="admin-actions">
                                <a href="../news.php?slug=<?= e($comment['news_slug']) ?>#comment-<?= $comment['id'] ?>" class="btn btn-small">Перейти</a>
                                <button class="btn btn-danger btn-small" onclick="deleteCommentFromAdmin(<?= $comment['id'] ?>)">Удалить</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteCommentFromAdmin(commentId) {
    if (confirm('Вы уверены, что хотите удалить этот комментарий?')) {
        fetch('/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&comment_id=${commentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Ошибка при удалении комментария');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            alert('Произошла ошибка при удалении комментария');
        });
    }
}
</script>

<?php require '../includes/footer.php'; ?>
