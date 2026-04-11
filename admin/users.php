<?php
require_once '../includes/functions.php';

// Проверка прав доступа (только администраторы)
if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

// Обработка действий
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
        // Нельзя удалить самого себя
        if ($userId == $_SESSION['user_id']) {
            $error = 'Нельзя удалить свой аккаунт';
        } else {
            // Получаем данные пользователя для удаления аватара
            $userStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            
            if ($user) {
                // Удаляем аватар
                if ($user['avatar'] && file_exists(__DIR__ . '/../uploads/avatars/' . $user['avatar'])) {
                    unlink(__DIR__ . '/../uploads/avatars/' . $user['avatar']);
                }
                
                // Удаляем пользователя (новости и комментарии удалятся каскадно)
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

// Пагинация
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Получаем общее количество пользователей
$totalStmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $totalStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// Получаем пользователей
$stmt = $pdo->prepare("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
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
    
    <?php if (empty($users)): ?>
        <p style="color: #999;">Пользователей пока нет</p>
    <?php else: ?>
        <table class="admin-table users-table">
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
            <div class="pagination" style="margin-top: 30px; display: flex; justify-content: center; gap: 10px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn">← Назад</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="btn btn-accent"><?= $i ?></span>
                    <?php elseif ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                        <a href="?page=<?= $i ?>" class="btn"><?= $i ?></a>
                    <?php elseif (abs($i - $page) == 3): ?>
                        <span style="padding: 10px;">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn">Вперед →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>
