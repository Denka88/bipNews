<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

$search    = trim($_GET['search'] ?? '');
$userId    = (int)($_GET['user'] ?? 0);
$newsId    = (int)($_GET['news'] ?? 0);
$sortBy    = in_array($_GET['sort'] ?? '', ['created_at', 'id']) ? $_GET['sort'] : 'created_at';
$sortDir   = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(c.content LIKE ? OR u.username LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($userId > 0) {
    $where[] = 'c.user_id = ?';
    $params[] = $userId;
}

if ($newsId > 0) {
    $where[] = 'c.news_id = ?';
    $params[] = $newsId;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments c LEFT JOIN users u ON c.user_id = u.id $whereSQL");
$countStmt->execute($params);
$totalComments = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalComments / $perPage));

$commentsStmt = $pdo->prepare("
    SELECT c.*, u.username, u.avatar, u.role, n.title as news_title, n.slug as news_slug
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN news n ON c.news_id = n.id
    $whereSQL
    ORDER BY c.$sortBy $sortDir
    LIMIT ? OFFSET ?
");
$commentsParams = array_merge($params, [$perPage, $offset]);
$commentsStmt->execute($commentsParams);
$commentsList = $commentsStmt->fetchAll();

$usersStmt = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$users = $usersStmt->fetchAll();

$newsStmt = $pdo->query("SELECT id, title FROM news ORDER BY created_at DESC LIMIT 200");
$newsItems = $newsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];

    $pdo->prepare("DELETE FROM comment_votes WHERE comment_id = ?")->execute([$deleteId]);
    $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$deleteId]);

    header("Location: comments_manage.php?search=" . urlencode($search) . "&user=$userId&news=$newsId&sort=$sortBy&dir=$sortDir&page=$page&deleted=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['comment_ids'])) {
    $ids = array_map('intval', $_POST['comment_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM comment_votes WHERE comment_id IN ($placeholders)")->execute($ids);
    $pdo->prepare("DELETE FROM comments WHERE id IN ($placeholders)")->execute($ids);

    header("Location: comments_manage.php?search=" . urlencode($search) . "&user=$userId&news=$newsId&sort=$sortBy&dir=$sortDir&page=$page&bulk_deleted=1");
    exit;
}

$successMsg    = $_GET['deleted'] ?? '';
$bulkSuccessMsg = $_GET['bulk_deleted'] ?? '';

$pageTitle = 'Управление комментариями - Админ-панель';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Управление комментариями</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php" class="active">Все комментарии</a>
        <a href="feedback.php">Обратная связь</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if ($successMsg): ?>
        <div class="form-success">Комментарий удалён</div>
    <?php endif; ?>
    <?php if ($bulkSuccessMsg): ?>
        <div class="form-success">Выбранные комментарии удалены</div>
    <?php endif; ?>

    <!-- Поиск и фильтрация -->
    <div class="admin-filters">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" placeholder="Текст или имя пользователя..." value="<?= e($search) ?>">
                </div>

                <div class="filter-group">
                    <label for="user">Пользователь</label>
                    <select id="user" name="user">
                        <option value="0">Все пользователи</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="news">Новость</label>
                    <select id="news" name="news">
                        <option value="0">Все новости</option>
                        <?php foreach ($newsItems as $n): ?>
                            <option value="<?= $n['id'] ?>" <?= $newsId == $n['id'] ? 'selected' : '' ?>><?= e(truncateText($n['title'], 40)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="sort">Сортировка</label>
                    <select id="sort" name="sort">
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>По дате</option>
                        <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>По ID</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="dir">Направление</label>
                    <select id="dir" name="dir">
                        <option value="desc" <?= $sortDir === 'DESC' ? 'selected' : '' ?>>По убыванию</option>
                        <option value="asc" <?= $sortDir === 'ASC' ? 'selected' : '' ?>>По возрастанию</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-accent btn-small">Применить</button>
                    <a href="comments_manage.php" class="btn btn-small">Сбросить</a>
                </div>
            </div>
        </form>
    </div>

    <div class="admin-section">
        <div class="comments-manage-header">
            <h2 class="section-title">Комментарии (<?= $totalComments ?>)</h2>
            <form method="POST" id="bulk-delete-form" style="display:inline;">
                <button type="button" id="select-all-btn" class="btn btn-small">Выбрать все</button>
                <button type="button" id="bulk-delete-btn" class="btn btn-danger btn-small" disabled>Удалить выбранные</button>
            </form>
        </div>

        <?php if (empty($commentsList)): ?>
            <p style="color: #999;">Комментариев не найдено</p>
        <?php else: ?>
            <form method="POST" id="comments-table-form">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all-checkbox"></th>
                            <th>ID</th>
                            <th>Пользователь</th>
                            <th>Новость</th>
                            <th>Комментарий</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commentsList as $comment): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="comment_ids[]" value="<?= $comment['id'] ?>" class="comment-checkbox">
                                </td>
                                <td><?= $comment['id'] ?></td>
                                <td>
                                    <a href="../profile.php?id=<?= $comment['user_id'] ?>"><?= e($comment['username']) ?></a>
                                    <?php if (!empty($comment['role']) && $comment['role'] === 'moderator'): ?>
                                        <span class="comment-role-badge moderator"><i class="fas fa-shield-alt"></i> Модератор</span>
                                    <?php elseif (!empty($comment['role']) && $comment['role'] === 'admin'): ?>
                                        <span class="comment-role-badge admin"><i class="fas fa-crown"></i> Администратор</span>
                                    <?php endif; ?>
                                </td>
                                <td><a href="../news.php?slug=<?= e($comment['news_slug'] ?? '') ?>"><?= e(truncateText($comment['news_title'] ?? 'Удалённая новость', 35)) ?></a></td>
                                <td><?= e(truncateText(strip_tags($comment['content']), 80)) ?></td>
                                <td><?= formatDate($comment['created_at']) ?></td>
                                <td class="admin-actions">
                                    <a href="../news.php?slug=<?= e($comment['news_slug']) ?>#comment-<?= $comment['id'] ?>" class="btn btn-small" target="_blank">Перейти</a>
                                    <button type="button" class="btn btn-danger btn-small" onclick="deleteComment(<?= $comment['id'] ?>)">Удалить</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
                <div class="admin-pagination">
                    <?php
                    $queryParams = http_build_query(array_filter([
                        'search' => $search,
                        'user'   => $userId > 0 ? $userId : null,
                        'news'   => $newsId > 0 ? $newsId : null,
                        'sort'   => $sortBy,
                        'dir'    => $sortDir,
                    ]));
                    $qs = $queryParams ? '&' . $queryParams : '';
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $qs ?>" class="btn btn-small">← Назад</a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1) echo '<span style="padding: 5px 10px;">...</span>';
                    ?>
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="btn btn-accent btn-small"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $qs ?>" class="btn btn-small"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($endPage < $totalPages) echo '<span style="padding: 5px 10px;">...</span>'; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $qs ?>" class="btn btn-small">Вперед →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const selectAllCheckbox = document.getElementById('select-all-checkbox');
const checkboxes = document.querySelectorAll('.comment-checkbox');
const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
const selectAllBtn = document.getElementById('select-all-btn');

function updateBulkButton() {
    const checked = document.querySelectorAll('.comment-checkbox:checked');
    bulkDeleteBtn.disabled = checked.length === 0;
    bulkDeleteBtn.textContent = checked.length > 0 ? `Удалить выбранные (${checked.length})` : 'Удалить выбранные';
}

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkButton();
    });
}

if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function() {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        selectAllCheckbox.checked = !allChecked;
        updateBulkButton();
    });
}

checkboxes.forEach(cb => cb.addEventListener('change', updateBulkButton));

bulkDeleteBtn.addEventListener('click', function() {
    if (!confirm('Удалить выбранные комментарии?')) return;

    const form = document.getElementById('comments-table-form');
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'bulk_delete';
    hidden.value = '1';
    form.appendChild(hidden);
    form.submit();
});

function deleteComment(commentId) {
    if (!confirm('Удалить этот комментарий?')) return;

    fetch('../comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&comment_id=${commentId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('comment-' + commentId);
            if (row) {
                row.remove();
            } else {
                location.reload();
            }
        } else {
            alert(data.message || 'Ошибка при удалении');
        }
    })
    .catch(() => alert('Произошла ошибка'));
}
</script>

<?php require '../includes/footer.php'; ?>
