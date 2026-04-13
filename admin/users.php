<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_ban' && $userId) {
        if ($userId == $_SESSION['user_id']) {
            $error = 'Нельзя заблокировать свой аккаунт';
        } else {
            $pdo->prepare("UPDATE users SET is_banned = NOT is_banned WHERE id = ?")->execute([$userId]);
            $message = 'Статус пользователя изменён';
        }
    } elseif ($action === 'delete' && $userId) {
        if ($userId == $_SESSION['user_id']) {
            $error = 'Нельзя удалить свой аккаунт';
        } else {
            $userStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            if ($user) {
                if ($user['avatar'] && file_exists(__DIR__ . '/../uploads/avatars/' . $user['avatar'])) {
                    unlink(__DIR__ . '/../uploads/avatars/' . $user['avatar']);
                }

                $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $deleteStmt->execute([$userId]);
                $message = 'Пользователь успешно удален';
            }
        }
    } elseif ($action === 'change_role' && $userId) {
        $newRole = $_POST['role'] ?? '';

        if (!in_array($newRole, ['user', 'moderator', 'admin'])) {
            $error = 'Неверная роль';
        } elseif ($userId == $_SESSION['user_id']) {
            $error = 'Нельзя изменить свою роль';
        } else {
            $roleStmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $roleStmt->execute([$newRole, $userId]);
            $message = 'Роль пользователя успешно изменена';
        }
    }
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$search   = trim($_GET['search'] ?? '');
$role     = $_GET['role'] ?? '';
$status   = $_GET['status'] ?? '';

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
}

if ($status === 'active') {
    $where[] = 'is_banned = 0';
} elseif ($status === 'banned') {
    $where[] = 'is_banned = 1';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSQL");
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalUsers / $perPage));

$stmt = $pdo->prepare("
    SELECT * FROM users
    $whereSQL
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

$pageTitle = 'Управление пользователями - Админ-панель';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Управление пользователями</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="feedback.php">Обратная связь</a>
        <a href="users.php" class="active">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if ($message): ?>
        <div class="form-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Фильтры -->
    <div class="admin-filters">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" placeholder="Логин, имя, email..." value="<?= e($search) ?>">
                </div>

                <div class="filter-group">
                    <label for="role">Роль</label>
                    <select id="role" name="role">
                        <option value="">Все роли</option>
                        <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Пользователь</option>
                        <option value="moderator" <?= $role === 'moderator' ? 'selected' : '' ?>>Модератор</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Администратор</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <option value="">Все</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Заблокированные</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-accent btn-small">Применить</button>
                    <a href="users.php" class="btn btn-small">Сбросить</a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($users)): ?>
        <p style="color: #999;">Пользователей не найдено</p>
    <?php else: ?>
        <table class="admin-table users-table">
            <caption style="text-align:left; padding:10px 16px; font-size:14px; color:#666;">Найдено: <strong><?= $totalUsers ?></strong></caption>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Аватар</th>
                    <th>Имя пользователя</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Дата регистрации</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <img src="<?= getUserAvatar($user) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover;">
                        </td>
                        <td><a href="../profile.php?id=<?= $user['id'] ?>" target="_blank"><?= e($user['username']) ?></a></td>
                        <td><?= e($user['full_name'] ?? '-') ?></td>
                        <td><?= e($user['email'] ?? '-') ?></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="role" onchange="this.form.submit()" style="padding: 4px;">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Пользователь</option>
                                        <option value="moderator" <?= $user['role'] === 'moderator' ? 'selected' : '' ?>>Модератор</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="admin-badge badge-admin"><?= getRoleName($user['role']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['is_banned']): ?>
                                <span class="admin-badge" style="background: #dc3545; color: #fff;">Заблокирован</span>
                            <?php else: ?>
                                <span class="admin-badge" style="background: #28a745; color: #fff;">Активен</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($user['created_at']) ?></td>
                        <td class="admin-actions">
                            <a href="../profile.php?id=<?= $user['id'] ?>" class="btn btn-small" target="_blank">Профиль</a>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="admin-action-form">
                                    <input type="hidden" name="action" value="toggle_ban">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-small <?= $user['is_banned'] ? 'btn-accent' : 'btn-danger' ?>" data-confirm-delete="<?= $user['is_banned'] ? 'Разблокировать пользователя?' : 'Заблокировать пользователя?' ?>">
                                        <?= $user['is_banned'] ? 'Разблокировать' : 'Заблокировать' ?>
                                    </button>
                                </form>
                                <form method="POST" class="admin-action-form">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small" data-confirm-delete="Удалить пользователя <?= e($user['username']) ?>?">Удалить</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php
                $queryParams = http_build_query(array_filter([
                    'search' => $search,
                    'role'   => $role,
                    'status' => $status,
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

<?php require '../includes/footer.php'; ?>
