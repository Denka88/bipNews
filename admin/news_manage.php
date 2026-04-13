<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

$search   = trim($_GET['search'] ?? '');
$authorId = (int)($_GET['author'] ?? 0);
$sortBy   = in_array($_GET['sort'] ?? '', ['created_at', 'views', 'title']) ? $_GET['sort'] : 'created_at';
$sortDir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(n.title LIKE ? OR n.content LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($authorId > 0) {
    $where[] = 'n.author_id = ?';
    $params[] = $authorId;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM news n $whereSQL");
$countStmt->execute($params);
$totalNews = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalNews / $perPage));

$newsStmt = $pdo->prepare("
    SELECT n.*, u.username
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    $whereSQL
    ORDER BY n.$sortBy $sortDir
    LIMIT ? OFFSET ?
");
$newsParams = array_merge($params, [$perPage, $offset]);
$newsStmt->execute($newsParams);
$newsList = $newsStmt->fetchAll();

$authorsStmt = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$authors = $authorsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];

    $imgStmt = $pdo->prepare("SELECT images FROM news WHERE id = ?");
    $imgStmt->execute([$deleteId]);
    $newsRow = $imgStmt->fetch();

    if ($newsRow && !empty($newsRow['images'])) {
        $images = json_decode($newsRow['images'], true);
        if (is_array($images)) {
            foreach ($images as $img) {
                $path = __DIR__ . '/../uploads/news/' . $img;
                if (file_exists($path)) unlink($path);
            }
        }
    }

    $pdo->prepare("DELETE FROM comment_votes WHERE comment_id IN (SELECT id FROM comments WHERE news_id = ?)")->execute([$deleteId]);
    $pdo->prepare("DELETE FROM comments WHERE news_id = ?")->execute([$deleteId]);
    $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([$deleteId]);

    header("Location: news_manage.php?search=" . urlencode($search) . "&author=$authorId&sort=$sortBy&dir=$sortDir&page=$page&deleted=1");
    exit;
}

$successMsg = $_GET['deleted'] ?? '';

$pageTitle = 'Управление новостями - Админ-панель';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Управление новостями</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php" class="active">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="feedback.php">Обратная связь</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if ($successMsg): ?>
        <div class="form-success">Новость удалена</div>
    <?php endif; ?>

    <!-- Поиск и фильтрация -->
    <div class="admin-filters">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" placeholder="Заголовок или текст..." value="<?= e($search) ?>">
                </div>

                <div class="filter-group">
                    <label for="author">Автор</label>
                    <select id="author" name="author">
                        <option value="0">Все авторы</option>
                        <?php foreach ($authors as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $authorId == $a['id'] ? 'selected' : '' ?>><?= e($a['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="sort">Сортировка</label>
                    <select id="sort" name="sort">
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>По дате</option>
                        <option value="views" <?= $sortBy === 'views' ? 'selected' : '' ?>>По просмотрам</option>
                        <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>По заголовку</option>
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
                    <a href="news_manage.php" class="btn btn-small">Сбросить</a>
                </div>
            </div>
        </form>
    </div>

    <div class="admin-section">
        <h2 class="section-title">Новости (<?= $totalNews ?>)</h2>

        <?php if (empty($newsList)): ?>
            <p style="color: #999;">Новостей не найдено</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Заголовок</th>
                        <th>Автор</th>
                        <th>Просмотры</th>
                        <th>Комментарии</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newsList as $newsItem): ?>
                        <?php
                        $commentsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id = ?");
                        $commentsCountStmt->execute([$newsItem['id']]);
                        $commentsCount = (int)$commentsCountStmt->fetchColumn();
                        ?>
                        <tr>
                            <td><?= $newsItem['id'] ?></td>
                            <td><a href="../news.php?slug=<?= e($newsItem['slug']) ?>"><?= e(truncateText($newsItem['title'], 60)) ?></a></td>
                            <td><?= e($newsItem['username']) ?></td>
                            <td><?= (int)$newsItem['views'] ?></td>
                            <td><?= $commentsCount ?></td>
                            <td><?= formatDate($newsItem['created_at']) ?></td>
                            <td class="admin-actions">
                                <a href="news_edit.php?id=<?= $newsItem['id'] ?>" class="btn btn-small">Редактировать</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить эту новость?');">
                                    <input type="hidden" name="delete_id" value="<?= $newsItem['id'] ?>">
                                    <input type="hidden" name="search" value="<?= e($search) ?>">
                                    <input type="hidden" name="author" value="<?= $authorId ?>">
                                    <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
                                    <input type="hidden" name="dir" value="<?= e($sortDir) ?>">
                                    <input type="hidden" name="page" value="<?= $page ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
                <div class="admin-pagination">
                    <?php
                    $queryParams = http_build_query(array_filter([
                        'search' => $search,
                        'author' => $authorId > 0 ? $authorId : null,
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

<?php require '../includes/footer.php'; ?>
