<?php
require_once 'includes/functions.php';

$pdo = getDB();

$userId = (int)($_GET['id'] ?? 0);

if (!$userId) {
    redirect('index.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    redirect404();
}

$isOwnProfile = isLoggedIn() && $_SESSION['user_id'] == $userId;
$isAdmin = hasMinimumRole('admin');
$canEdit = $isOwnProfile;

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_action'])) {
    if (hasMinimumRole('moderator')) {
        $banUserId = (int)($_POST['ban_user_id'] ?? 0);
        if ($banUserId && $banUserId != $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_banned = NOT is_banned WHERE id = ?")->execute([$banUserId]);
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $banUserId);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $aboutMe = trim($_POST['about_me'] ?? '');

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Неверный формат email';
    }

    if (empty($errors) && !empty($email) && $email !== $profileUser['email']) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $userId]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = 'Этот email уже используется';
        }
    }

    $avatarFileName = $profileUser['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            $errors[] = 'Допустимые форматы аватара: JPEG, PNG, GIF, WebP';
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $errors[] = 'Размер аватара не должен превышать 2 МБ';
        } else {
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarFileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = __DIR__ . '/uploads/avatars/' . $avatarFileName;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                if ($profileUser['avatar'] && file_exists(__DIR__ . '/uploads/avatars/' . $profileUser['avatar'])) {
                    unlink(__DIR__ . '/uploads/avatars/' . $profileUser['avatar']);
                }
            } else {
                $errors[] = 'Ошибка при загрузке аватара';
                $avatarFileName = $profileUser['avatar'];
            }
        }
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, email = ?, about_me = ?, avatar = ?
            WHERE id = ?
        ");

        try {
            $updateStmt->execute([
                empty($fullName) ? null : $fullName,
                empty($email) ? null : $email,
                empty($aboutMe) ? null : $aboutMe,
                $avatarFileName,
                $userId
            ]);

            $success = 'Профиль успешно обновлен';

            $stmt->execute([$userId]);
            $profileUser = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при обновлении профиля';
        }
    }
}

$profilePage = max(1, (int)($_GET['page'] ?? 1));
$profilePerPage = 10;
$profileOffset = ($profilePage - 1) * $profilePerPage;

$totalCommentsStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$totalCommentsStmt->execute([$userId]);
$totalCommentsCount = (int)$totalCommentsStmt->fetchColumn();
$totalProfilePages = ceil($totalCommentsCount / $profilePerPage);

$commentsStmt = $pdo->prepare("
    SELECT c.*, n.title as news_title, n.slug as news_slug
    FROM comments c
    LEFT JOIN news n ON c.news_id = n.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$commentsStmt->execute([$userId, $profilePerPage, $profileOffset]);
$userComments = $commentsStmt->fetchAll();

$pageTitle = e($profileUser['username']) . ' - Профиль - BipNews';
$pageDescription = 'Профиль пользователя ' . e($profileUser['username']) . ' на портале BipNews. Информация о пользователе и его комментарии.';
require 'includes/header.php';
?>

<div class="profile-container">
    <div class="profile-header">
        <img src="<?= getUserAvatar($profileUser) ?>" alt="<?= e($profileUser['username']) ?>" class="profile-avatar" id="avatar-preview">
        
        <div class="profile-info">
            <h1 class="profile-username"><?= e($profileUser['username']) ?></h1>
            <span class="profile-role"><?= getRoleName($profileUser['role']) ?></span>
            <?php if ($profileUser['is_banned']): ?>
                <span class="profile-role" style="background: #dc3545; color: #fff;">Заблокирован</span>
            <?php endif; ?>

            <div class="profile-details">
                <?php if (!empty($profileUser['full_name'])): ?>
                    <div class="profile-detail">
                        <span class="profile-detail-label">ФИО:</span>
                        <span><?= e($profileUser['full_name']) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($profileUser['email'])): ?>
                    <div class="profile-detail">
                        <span class="profile-detail-label">Email:</span>
                        <span><?= e($profileUser['email']) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="profile-detail">
                    <span class="profile-detail-label">Дата рождения:</span>
                    <span><?= formatDate($profileUser['birth_date']) ?> (<?= getAge($profileUser['birth_date']) ?> лет)</span>
                </div>
                
                <?php if (!empty($profileUser['about_me'])): ?>
                    <div class="profile-detail">
                        <span class="profile-detail-label">О себе:</span>
                        <span><?= nl2br(e($profileUser['about_me'])) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="profile-detail">
                    <span class="profile-detail-label">На сайте с:</span>
                    <span><?= formatDate($profileUser['created_at']) ?></span>
                </div>
            </div>
            
            <?php if ($canEdit): ?>
                <a href="#" class="profile-edit-link btn btn-accent btn-small" onclick="document.getElementById('edit-profile-form').style.display='block'; this.style.display='none'; return false;">Редактировать профиль</a>
            <?php endif; ?>
            
            <?php if (hasMinimumRole('moderator') && $userId != $_SESSION['user_id']): ?>
                <form method="POST" style="display: inline; margin-top: 10px;" id="ban-form-<?= $userId ?>">
                    <input type="hidden" name="ban_action" value="toggle">
                    <input type="hidden" name="ban_user_id" value="<?= $userId ?>">
                    <?php if ($profileUser['is_banned']): ?>
                        <button type="submit" class="btn btn-accent btn-small" data-confirm-delete="Разблокировать пользователя?">Разблокировать</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-danger btn-small" data-confirm-delete="Заблокировать пользователя?">Заблокировать</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Форма редактирования -->
    <?php if ($canEdit): ?>
        <div id="edit-profile-form" style="display: none; margin-bottom: 40px; padding: 30px; border: 1px solid #000;">
            <h2 style="margin-bottom: 20px;">Редактировать профиль</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="form-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="form-success"><?= e($success) ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="avatar">Аватар</label>
                    <input type="file" id="avatar" name="avatar" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="full-name">ФИО</label>
                    <input type="text" id="full-name" name="full_name" value="<?= e($profileUser['full_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($profileUser['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="about-me">О себе</label>
                    <textarea id="about-me" name="about_me"><?= e($profileUser['about_me'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-accent">Сохранить изменения</button>
                <button type="button" class="btn" onclick="document.getElementById('edit-profile-form').style.display='none'; document.querySelector('.profile-edit-link').style.display='inline-block';">Отмена</button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- Комментарии пользователя -->
    <div class="profile-section">
        <h2 class="profile-section-title">Комментарии (<?= $totalCommentsCount ?>)</h2>

        <?php if (empty($userComments)): ?>
            <p style="color: #999;">Пользователь еще не оставлял комментариев</p>
        <?php else: ?>
            <div class="profile-comments-list">
                <?php foreach ($userComments as $comment): ?>
                    <div class="profile-comment-item">
                        <div class="profile-comment-news">
                            К новости: <a href="news.php?slug=<?= e($comment['news_slug']) ?>"><?= e($comment['news_title']) ?></a>
                        </div>
                        <div class="profile-comment-content">
                            <?= nl2br(e($comment['content'])) ?>
                        </div>
                        <div class="profile-comment-date">
                            <?= formatDate($comment['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalProfilePages > 1): ?>
                <div class="comment-pagination">
                    <?php if ($profilePage > 1): ?>
                        <a href="?id=<?= $userId ?>&page=<?= $profilePage - 1 ?>" class="btn btn-small">← Назад</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalProfilePages; $i++): ?>
                        <?php if ($i == $profilePage): ?>
                            <span class="btn btn-accent btn-small"><?= $i ?></span>
                        <?php elseif ($i == 1 || $i == $totalProfilePages || abs($i - $profilePage) <= 2): ?>
                            <a href="?id=<?= $userId ?>&page=<?= $i ?>" class="btn btn-small"><?= $i ?></a>
                        <?php elseif (abs($i - $profilePage) == 3): ?>
                            <span style="padding: 5px 10px;">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($profilePage < $totalProfilePages): ?>
                        <a href="?id=<?= $userId ?>&page=<?= $profilePage + 1 ?>" class="btn btn-small">Вперед →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
