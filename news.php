<?php
require_once 'includes/functions.php';

$pdo = getDB();

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    redirect('index.php');
}

$stmt = $pdo->prepare("
    SELECT n.*, u.username, u.avatar
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    WHERE n.slug = ?
");
$stmt->execute([$slug]);
$news = $stmt->fetch();

if (!$news) {
    redirect404();
}

$updateStmt = $pdo->prepare("UPDATE news SET views = views + 1 WHERE id = ?");
$updateStmt->execute([$news['id']]);
$news['views']++;

$commentSort = $_GET['comment_sort'] ?? 'new';
$commentPage = max(1, (int)($_GET['comment_page'] ?? 1));
$commentsPerPage = 10;
$commentOffset = ($commentPage - 1) * $commentsPerPage;

$totalCommentsStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id = ?");
$totalCommentsStmt->execute([$news['id']]);
$totalCommentsCount = (int)$totalCommentsStmt->fetchColumn();
$totalCommentPages = ceil($totalCommentsCount / $commentsPerPage);

$orderBy = ($commentSort === 'popular')
    ? "(SELECT SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) - SUM(CASE WHEN vote_type = 'dislike' THEN 1 ELSE 0 END) FROM comment_votes cv WHERE cv.comment_id = c.id) DESC, c.created_at DESC"
    : "c.created_at DESC";

$commentsStmt = $pdo->prepare("
    SELECT c.*, u.username, u.avatar, u.role
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.news_id = ?
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$commentsStmt->execute([$news['id'], $commentsPerPage, $commentOffset]);
$comments = $commentsStmt->fetchAll();

$commentError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    if (!isLoggedIn()) {
        $commentError = 'Для добавления комментария необходимо войти в аккаунт';
    } elseif (isBanned()) {
        $commentError = 'Ваш аккаунт заблокирован. Вы не можете оставлять комментарии.';
    } else {
        $commentContent = trim($_POST['comment_content'] ?? '');
        
        if (empty($commentContent)) {
            $commentError = 'Комментарий не может быть пустым';
        } elseif (strlen($commentContent) < 2) {
            $commentError = 'Комментарий слишком короткий';
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO comments (news_id, user_id, content) VALUES (?, ?, ?)");
            try {
                $insertStmt->execute([$news['id'], $_SESSION['user_id'], $commentContent]);

                $redirectUrl = $_SERVER['PHP_SELF'] . '?slug=' . urlencode($news['slug']) . '&comment_sort=' . $commentSort;
                header("Location: $redirectUrl");
                exit;
            } catch (PDOException $e) {
                $commentError = 'Ошибка при добавлении комментария';
            }
        }
    }
}

$pageTitle = e($news['title']) . ' - BipNews';
$pageDescription = mb_substr(strip_tags($news['content'] ?? ''), 0, 200) . '...';
require 'includes/header.php';
?>

<article class="news-full">
    <div class="news-full-header">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Назад к новостям</a>
        <h1 class="news-full-title"><?= e($news['title']) ?></h1>
        <div class="news-full-meta">
            <span><i class="far fa-calendar-alt"></i> <?= formatDate($news['published_at']) ?></span>
            <span><i class="far fa-eye"></i> <?= (int)$news['views'] ?> просмотров</span>
            <span><i class="far fa-comment"></i> <?= count($comments) ?> комментариев</span>
        </div>
    </div>
    
    <div class="news-full-content">
        <?= str_replace('../uploads/news/', 'uploads/news/', $news['content']) ?>
    </div>
</article>

<!-- Секция комментариев -->
<section class="comments-section">
    <div class="comments-header">
        <h2 class="comments-title">Комментарии (<?= $totalCommentsCount ?>)</h2>
        
        <!-- Фильтры -->
        <div class="comment-filters">
            <button type="button" class="filter-btn <?= $commentSort === 'new' ? 'active' : '' ?>" data-sort="new">Новые</button>
            <button type="button" class="filter-btn <?= $commentSort === 'popular' ? 'active' : '' ?>" data-sort="popular">Популярные</button>
        </div>
    </div>
    
    <!-- Форма добавления комментария -->
    <input type="hidden" id="comment-news-id" value="<?= $news['id'] ?>">
    
    <?php if ($commentError): ?>
        <div class="form-error"><?= e($commentError) ?></div>
    <?php endif; ?>
    
    <?php if (isLoggedIn()): ?>
        <?php if (isBanned()): ?>
            <div class="comment-form text-center">
                <p style="color: #dc3545; font-weight: 600;"><i class="fas fa-ban"></i> Ваш аккаунт заблокирован. Вы не можете оставлять комментарии.</p>
            </div>
        <?php else: ?>
            <div class="comment-form">
                <form method="POST" action="javascript:void(0)" id="comment-form">
                    <div class="form-group">
                        <textarea name="comment_content" id="comment-content" placeholder="Напишите комментарий..." required></textarea>
                    </div>
                    <button type="submit" name="add_comment" id="comment-submit" class="btn btn-accent">Отправить комментарий</button>
                </form>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="comment-form text-center">
            <p>Для добавления комментариев необходимо <a href="login.php?redirect=news.php?slug=<?= e($news['slug']) ?>">войти</a> или <a href="register.php">зарегистрироваться</a></p>
        </div>
    <?php endif; ?>
    
    <!-- Список комментариев -->
    <?php if (empty($comments)): ?>
        <div class="no-comments">
            <p>Комментариев пока нет. Будьте первым!</p>
        </div>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <?php
                $votes = getCommentVotes($comment['id']);
                $userVote = isLoggedIn() ? hasUserVoted($comment['id'], $_SESSION['user_id']) : null;
                ?>
                <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                    <div class="comment-header">
                        <div class="comment-author">
                            <a href="profile.php?id=<?= $comment['user_id'] ?>">
                                <img src="<?= getUserAvatar($comment) ?>" alt="<?= e($comment['username']) ?>" class="comment-author-avatar">
                            </a>
                            <div class="comment-author-info">
                                <a href="profile.php?id=<?= $comment['user_id'] ?>" class="comment-author-name"><?= e($comment['username']) ?></a>
                                <?php if (!empty($comment['role']) && $comment['role'] === 'moderator'): ?>
                                    <span class="comment-role-badge moderator"><i class="fas fa-shield-alt"></i> Модератор</span>
                                <?php elseif (!empty($comment['role']) && $comment['role'] === 'admin'): ?>
                                    <span class="comment-role-badge admin"><i class="fas fa-crown"></i> Администратор</span>
                                <?php endif; ?>
                                <span class="comment-date"><?= formatDate($comment['created_at']) ?></span>
                            </div>
                        </div>

                        <div class="comment-votes">
                            <button class="vote-btn <?= $userVote === 'like' ? 'active-like' : '' ?>" data-comment-id="<?= $comment['id'] ?>" data-vote-type="like">
                                <span>+</span>
                                <span class="vote-count"><?= $votes['likes'] ?></span>
                            </button>
                            <button class="vote-btn <?= $userVote === 'dislike' ? 'active-dislike' : '' ?>" data-comment-id="<?= $comment['id'] ?>" data-vote-type="dislike">
                                <span>-</span>
                                <span class="vote-count"><?= $votes['dislikes'] ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="comment-content">
                        <?= nl2br(e($comment['content'])) ?>
                    </div>

                    <?php if (hasMinimumRole('moderator') || isCommentAuthor($comment['user_id'])): ?>
                        <div class="comment-actions">
                            <button class="btn btn-danger btn-small" onclick="deleteComment(<?= $comment['id'] ?>)">Удалить</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Пагинация комментариев -->
    <?php if ($totalCommentPages > 1): ?>
        <div class="comment-pagination">
            <?php if ($commentPage > 1): ?>
                <a href="?slug=<?= e($news['slug']) ?>&comment_sort=<?= e($commentSort) ?>&comment_page=<?= $commentPage - 1 ?>" class="btn btn-small">← Назад</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalCommentPages; $i++): ?>
                <?php if ($i == $commentPage): ?>
                    <span class="btn btn-accent btn-small"><?= $i ?></span>
                <?php elseif ($i == 1 || $i == $totalCommentPages || abs($i - $commentPage) <= 1): ?>
                    <a href="?slug=<?= e($news['slug']) ?>&comment_sort=<?= e($commentSort) ?>&comment_page=<?= $i ?>" class="btn btn-small"><?= $i ?></a>
                <?php elseif (abs($i - $commentPage) == 2): ?>
                    <span style="padding: 5px 10px;">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($commentPage < $totalCommentPages): ?>
                <a href="?slug=<?= e($news['slug']) ?>&comment_sort=<?= e($commentSort) ?>&comment_page=<?= $commentPage + 1 ?>" class="btn btn-small">Вперед →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script>
const commentForm = document.getElementById('comment-form');
if (commentForm) {
    commentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const textarea = document.getElementById('comment-content');
        const content = textarea.value.trim();
        if (!content) return;
        
        const newsId = document.getElementById('comment-news-id').value;
        const submitBtn = document.getElementById('comment-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('news_id', newsId);
        formData.append('content', content);
        
        fetch('comments.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                textarea.value = '';

                const noComments = document.querySelector('.no-comments');
                if (noComments) {
                    noComments.remove();
                }

                let list = document.querySelector('.comments-list');
                if (!list) {
                    list = document.createElement('div');
                    list.className = 'comments-list';
                    const noComments = document.querySelector('.no-comments');
                    if (noComments) {
                        noComments.replaceWith(list);
                    } else {
                        const section = document.querySelector('.comments-section');
                        section.insertBefore(list, section.querySelector('.comment-pagination'));
                    }
                }

                const roleBadge = data.role === 'admin'
                    ? '<span class="comment-role-badge admin"><i class="fas fa-crown"></i> Администратор</span>'
                    : data.role === 'moderator'
                        ? '<span class="comment-role-badge moderator"><i class="fas fa-shield-alt"></i> Модератор</span>'
                        : '';
                const commentHTML = `
                    <div class="comment-item" id="comment-${data.id}" style="opacity:0; transform: translateY(-10px); transition: all 0.3s;">
                        <div class="comment-header">
                            <div class="comment-author">
                                <a href="profile.php?id=<?= $_SESSION['user_id'] ?? 0 ?>">
                                    <img src="${data.avatar}" alt="${data.username}" class="comment-author-avatar">
                                </a>
                                <div class="comment-author-info">
                                    <a href="profile.php?id=<?= $_SESSION['user_id'] ?? 0 ?>" class="comment-author-name">${data.username}</a>
                                    ${roleBadge}
                                    <span class="comment-date">${data.created_at}</span>
                                </div>
                            </div>
                            <div class="comment-votes">
                                <button class="vote-btn" data-comment-id="${data.id}" data-vote-type="like">
                                    <span>+</span>
                                    <span class="vote-count">${data.likes}</span>
                                </button>
                                <button class="vote-btn" data-comment-id="${data.id}" data-vote-type="dislike">
                                    <span>-</span>
                                    <span class="vote-count">${data.dislikes}</span>
                                </button>
                            </div>
                        </div>
                        <div class="comment-content">${data.content}</div>
                        <div class="comment-actions">
                            <button class="btn btn-danger btn-small" onclick="deleteComment(${data.id})">Удалить</button>
                        </div>
                    </div>
                `;

                list.insertAdjacentHTML('afterbegin', commentHTML);

                const title = document.querySelector('.comments-title');
                if (title) title.textContent = 'Комментарии (' + data.totalCount + ')';

                const newEl = document.getElementById('comment-' + data.id);
                if (newEl) {
                    requestAnimationFrame(() => {
                        newEl.style.opacity = '1';
                        newEl.style.transform = 'translateY(0)';
                    });
                    newEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            } else {
                alert(data.message || 'Ошибка при отправке комментария');
            }
        })
        .catch(() => {
            alert('Ошибка при отправке комментария');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Отправить комментарий';
        });
    });
}

document.querySelectorAll('.comment-filters .filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const sort = this.dataset.sort;
        loadComments(sort, 1);
    });
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.comment-pagination a')) {
        e.preventDefault();
        const link = e.target.closest('a');
        const url = new URL(link.href, window.location.origin);
        const page = url.searchParams.get('comment_page') || 1;
        const sort = url.searchParams.get('comment_sort') || '<?= e($commentSort) ?>';
        loadComments(sort, parseInt(page));
    }
});

function loadComments(sort, page) {
    const newsId = document.getElementById('comment-news-id').value;
    const formData = new FormData();
    formData.append('action', 'list');
    formData.append('news_id', newsId);
    formData.append('sort', sort);
    formData.append('page', page);
    formData.append('news_slug', '<?= e($news['slug']) ?>');
    
    fetch('comments.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const section = document.querySelector('.comments-section');
                const oldList = section.querySelector('.comments-list');
                const oldPagination = section.querySelector('.comment-pagination');
                const oldNoComments = section.querySelector('.no-comments');
                
                if (oldList) oldList.remove();
                if (oldPagination) oldPagination.remove();
                if (oldNoComments) oldNoComments.remove();
                
                if (data.html) {
                    section.insertAdjacentHTML('beforeend', data.html);
                } else {
                    section.insertAdjacentHTML('beforeend', '<div class="no-comments"><p>Комментариев пока нет. Будьте первым!</p></div>');
                }
                
                if (data.pagination) {
                    section.insertAdjacentHTML('beforeend', data.pagination);
                }

                const title = section.querySelector('.comments-title');
                if (title) title.textContent = 'Комментарии (' + data.totalCount + ')';

                section.querySelectorAll('.comment-filters .filter-btn').forEach(b => b.classList.remove('active'));
                const activeBtn = section.querySelector(`.filter-btn[data-sort="${sort}"]`);
                if (activeBtn) activeBtn.classList.add('active');
            }
        });
}

function deleteComment(commentId) {
    if (confirm('Вы уверены, что хотите удалить этот комментарий?')) {
        fetch('comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&comment_id=${commentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const commentElement = document.getElementById('comment-' + commentId);
                if (commentElement) {
                    commentElement.remove();
                }
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

<?php require 'includes/footer.php'; ?>
