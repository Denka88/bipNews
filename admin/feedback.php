<?php
require_once '../includes/functions.php';

if (!hasMinimumRole('admin')) {
    redirect('index.php');
}

$pdo = getDB();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS feedback_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$search    = trim($_GET['search'] ?? '');
$status    = $_GET['status'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($status === 'unread') {
    $where[] = 'is_read = 0';
} elseif ($status === 'read') {
    $where[] = 'is_read = 1';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM feedback_messages $whereSQL");
$countStmt->execute($params);
$totalMessages = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalMessages / $perPage));

$msgStmt = $pdo->prepare("
    SELECT * FROM feedback_messages
    $whereSQL
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$msgParams = array_merge($params, [$perPage, $offset]);
$msgStmt->execute($msgParams);
$messages = $msgStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare("DELETE FROM feedback_messages WHERE id = ?")->execute([(int)$_POST['delete_id']]);
        $successMsg = 'Сообщение удалено';
    } elseif (isset($_POST['mark_read']) && is_numeric($_POST['mark_read'])) {
        $pdo->prepare("UPDATE feedback_messages SET is_read = 1 WHERE id = ?")->execute([(int)$_POST['mark_read']]);
    } elseif (isset($_POST['mark_unread']) && is_numeric($_POST['mark_unread'])) {
        $pdo->prepare("UPDATE feedback_messages SET is_read = 0 WHERE id = ?")->execute([(int)$_POST['mark_unread']]);
    } elseif (isset($_POST['bulk_delete']) && !empty($_POST['msg_ids'])) {
        $ids = array_map('intval', $_POST['msg_ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM feedback_messages WHERE id IN ($placeholders)")->execute($ids);
        $successMsg = 'Выбранные сообщения удалены';
    }
}

$successMsg = $successMsg ?? null;

$pageTitle = 'Обратная связь - Админ-панель';
require '../includes/header.php';
?>

<div class="admin-container">
    <h1 class="admin-title">Обратная связь</h1>

    <div class="admin-nav">
        <a href="index.php">Обзор</a>
        <a href="news_create.php">Создать новость</a>
        <a href="news_manage.php">Все новости</a>
        <a href="comments_manage.php">Все комментарии</a>
        <a href="feedback.php" class="active">Обратная связь</a>
        <a href="users.php">Пользователи</a>
        <a href="banners.php">Баннеры</a>
        <a href="../index.php" class="nav-site-link">На сайт <i class="fas fa-external-link-alt"></i></a>
    </div>

    <?php if ($successMsg): ?>
        <div class="form-success"><?= e($successMsg) ?></div>
    <?php endif; ?>

    <!-- Фильтры -->
    <div class="admin-filters">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" placeholder="Имя, email, тема, текст..." value="<?= e($search) ?>">
                </div>

                <div class="filter-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <option value="">Все</option>
                        <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Непрочитанные</option>
                        <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Прочитанные</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-accent btn-small">Применить</button>
                    <a href="feedback.php" class="btn btn-small">Сбросить</a>
                </div>
            </div>
        </form>
    </div>

    <div class="admin-section">
        <div class="comments-manage-header">
            <h2 class="section-title">Сообщения (<?= $totalMessages ?>)</h2>
            <form method="POST" id="bulk-delete-form" style="display:inline;">
                <button type="button" id="select-all-btn" class="btn btn-small">Выбрать все</button>
                <button type="button" id="bulk-delete-btn" class="btn btn-danger btn-small" disabled>Удалить выбранные</button>
            </form>
        </div>

        <?php if (empty($messages)): ?>
            <p style="color: #999;">Сообщений не найдено</p>
        <?php else: ?>
            <form method="POST" id="messages-table-form">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all-checkbox"></th>
                            <th>ID</th>
                            <th>Имя / Email</th>
                            <th>Тема</th>
                            <th>Сообщение</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr style="<?= !$msg['is_read'] ? 'background:#fffde7;' : '' ?>">
                                <td>
                                    <input type="checkbox" name="msg_ids[]" value="<?= $msg['id'] ?>" class="msg-checkbox">
                                </td>
                                <td><?= $msg['id'] ?></td>
                                <td>
                                    <strong><?= e($msg['name']) ?></strong><br>
                                    <a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a>
                                </td>
                                <td>
                                    <?= e(truncateText($msg['subject'], 40)) ?>
                                    <?php if (!$msg['is_read']): ?>
                                        <span style="display:inline-block; width:8px; height:8px; background:#FFB841; border-radius:50%; margin-left:4px;" title="Непрочитанное"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(truncateText($msg['message'], 60)) ?></td>
                                <td><?= formatDate($msg['created_at']) ?></td>
                                <td class="admin-actions">
                                    <button type="button" class="btn btn-small" onclick="viewMessage(<?= $msg['id'] ?>, '<?= e($msg['name']) ?>', '<?= e($msg['email']) ?>', '<?= e(addslashes($msg['subject'])) ?>', '<?= e(addslashes(nl2br($msg['message']))) ?>', '<?= $msg['is_read'] ?>')">Просмотр</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_id" value="<?= $msg['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Удалить сообщение?')">Удалить</button>
                                    </form>
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
</div>

<!-- Модальное окно просмотра -->
<div id="msg-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; max-width:700px; width:90%; max-height:80vh; overflow-y:auto; border-radius:8px; padding:30px; position:relative;">
        <button type="button" onclick="closeModal()" style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:24px; cursor:pointer; color:#999;">&times;</button>
        <h2 id="modal-subject" style="margin-bottom:15px; font-size:22px;"></h2>
        <div style="margin-bottom:15px; color:#666; font-size:14px;">
            <strong id="modal-name"></strong> &middot; <a id="modal-email" href=""></a>
        </div>
        <div id="modal-message" style="line-height:1.7; font-size:16px; margin-bottom:25px;"></div>
        <div style="display:flex; gap:10px;">
            <button type="button" id="modal-mark-read" class="btn btn-accent btn-small" onclick="markRead()">Отметить прочитанным</button>
            <button type="button" id="modal-mark-unread" class="btn btn-small" onclick="markUnread()">Отметить непрочитанным</button>
            <button type="button" class="btn btn-danger btn-small" onclick="deleteFromModal()">Удалить</button>
        </div>
    </div>
</div>

<script>
let currentMsgId = null;

function viewMessage(id, name, email, subject, message, isRead) {
    currentMsgId = id;
    document.getElementById('modal-subject').textContent = subject;
    document.getElementById('modal-name').textContent = name;
    document.getElementById('modal-email').textContent = email;
    document.getElementById('modal-email').href = 'mailto:' + email;
    document.getElementById('modal-message').innerHTML = message;

    var markBtn = document.getElementById('modal-mark-read');
    var unmarkBtn = document.getElementById('modal-mark-unread');
    if (isRead == 1) {
        markBtn.style.display = 'none';
        unmarkBtn.style.display = '';
    } else {
        markBtn.style.display = '';
        unmarkBtn.style.display = 'none';
    }

    document.getElementById('msg-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('msg-modal').style.display = 'none';
    currentMsgId = null;
}

function markRead() {
    if (!currentMsgId) return;
    var fd = new FormData();
    fd.append('mark_read', '1');
    fd.append('mark_read_id', currentMsgId);
    sendMarkRead();
}

function markUnread() {
    if (!currentMsgId) return;
    sendMarkUnread();
}

function deleteFromModal() {
    if (!currentMsgId) return;
    if (!confirm('Удалить сообщение?')) return;
    var fd = new FormData();
    fd.append('delete_id', currentMsgId);
    submitForm(fd);
}

function sendMarkRead() {
    var fd = new FormData();
    fd.append('mark_read', currentMsgId);
    submitForm(fd);
}

function sendMarkUnread() {
    var fd = new FormData();
    fd.append('mark_unread', currentMsgId);
    submitForm(fd);
}

function submitForm(fd) {
    fetch('feedback.php', { method: 'POST', body: fd })
    .then(function() { location.reload(); });
}

var selectAllCheckbox = document.getElementById('select-all-checkbox');
var checkboxes = document.querySelectorAll('.msg-checkbox');
var bulkDeleteBtn = document.getElementById('bulk-delete-btn');
var selectAllBtn = document.getElementById('select-all-btn');

function updateBulkButton() {
    var checked = document.querySelectorAll('.msg-checkbox:checked');
    bulkDeleteBtn.disabled = checked.length === 0;
    bulkDeleteBtn.textContent = checked.length > 0 ? 'Удалить выбранные (' + checked.length + ')' : 'Удалить выбранные';
}

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(function(cb) { cb.checked = this.checked; }.bind(this));
        updateBulkButton();
    });
}

if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function() {
        var allChecked = Array.from(checkboxes).every(function(cb) { return cb.checked; });
        checkboxes.forEach(function(cb) { cb.checked = !allChecked; });
        selectAllCheckbox.checked = !allChecked;
        updateBulkButton();
    });
}

checkboxes.forEach(function(cb) { cb.addEventListener('change', updateBulkButton); });

bulkDeleteBtn.addEventListener('click', function() {
    if (!confirm('Удалить выбранные сообщения?')) return;
    var form = document.getElementById('messages-table-form');
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'bulk_delete';
    hidden.value = '1';
    form.appendChild(hidden);
    form.submit();
});

document.getElementById('msg-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require '../includes/footer.php'; ?>
