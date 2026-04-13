<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'vote') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
        exit;
    }

    $commentId = (int)($_POST['comment_id'] ?? 0);
    $voteType = $_POST['vote_type'] ?? '';

    if (!$commentId || !in_array($voteType, ['like', 'dislike'])) {
        echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
        exit;
    }

    $pdo = getDB();
    $userId = $_SESSION['user_id'];

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE id = ?");
    $checkStmt->execute([$commentId]);
    if ($checkStmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Комментарий не найден']);
        exit;
    }

    $existingVote = hasUserVoted($commentId, $userId);

    if ($existingVote === $voteType) {
        $deleteStmt = $pdo->prepare("DELETE FROM comment_votes WHERE comment_id = ? AND user_id = ?");
        $deleteStmt->execute([$commentId, $userId]);
        $userVote = null;
    } else {
        $upsertStmt = $pdo->prepare("
            INSERT INTO comment_votes (comment_id, user_id, vote_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type)
        ");
        $upsertStmt->execute([$commentId, $userId, $voteType]);
        $userVote = $voteType;
    }

    $votes = getCommentVotes($commentId);

    echo json_encode([
        'success' => true,
        'likes' => $votes['likes'],
        'dislikes' => $votes['dislikes'],
        'userVote' => $userVote
    ]);
    exit;
}

if ($action === 'delete') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
        exit;
    }

    $commentId = (int)($_POST['comment_id'] ?? 0);

    if (!$commentId) {
        echo json_encode(['success' => false, 'message' => 'Неверный ID комментария']);
        exit;
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT * FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Комментарий не найден']);
        exit;
    }

    if (!hasMinimumRole('moderator') && !isCommentAuthor($comment['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Недостаточно прав']);
        exit;
    }

    $deleteStmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $deleteStmt->execute([$commentId]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'add') {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
        exit;
    }

    if (isBanned()) {
        echo json_encode(['success' => false, 'message' => 'Ваш аккаунт заблокирован']);
        exit;
    }

    $newsId = (int)($_POST['news_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$newsId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
        exit;
    }

    if (strlen($content) < 2) {
        echo json_encode(['success' => false, 'message' => 'Комментарий слишком короткий']);
        exit;
    }

    $pdo = getDB();

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE id = ?");
    $checkStmt->execute([$newsId]);
    if ($checkStmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Новость не найдена']);
        exit;
    }

    $insertStmt = $pdo->prepare("INSERT INTO comments (news_id, user_id, content) VALUES (?, ?, ?)");
    try {
        $insertStmt->execute([$newsId, $_SESSION['user_id'], $content]);
        $newId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            SELECT c.*, u.username, u.avatar, u.role
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$newId]);
        $newComment = $stmt->fetch();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id = ?");
        $countStmt->execute([$newsId]);
        $totalCount = (int)$countStmt->fetchColumn();

        $votes = getCommentVotes($newId);

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'username' => $newComment['username'],
            'avatar' => getUserAvatar($newComment),
            'role' => $newComment['role'] ?? '',
            'content' => nl2br(e($newComment['content'])),
            'created_at' => formatDate($newComment['created_at']),
            'likes' => $votes['likes'],
            'dislikes' => $votes['dislikes'],
            'totalCount' => $totalCount
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении комментария']);
    }
    exit;
}

if ($action === 'list') {
    $newsId = (int)($_POST['news_id'] ?? 0);
    $sort = $_POST['sort'] ?? 'new';
    $page = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    if (!$newsId) {
        echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
        exit;
    }

    $pdo = getDB();

    $orderBy = ($sort === 'popular')
        ? "(SELECT COALESCE(SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE -1 END), 0) FROM comment_votes cv WHERE cv.comment_id = c.id) DESC, c.created_at DESC"
        : "c.created_at DESC";

    $commentsStmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar, u.role
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.news_id = ?
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ");
    $commentsStmt->execute([$newsId, $perPage, $offset]);
    $comments = $commentsStmt->fetchAll();

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id = ?");
    $totalStmt->execute([$newsId]);
    $totalCount = (int)$totalStmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    $html = '';
    $currentUser = getCurrentUser();

    if (!empty($comments)) {
        $html = '<div class="comments-list">';
        foreach ($comments as $c) {
            $votes = getCommentVotes($c['id']);
            $userVote = isLoggedIn() ? hasUserVoted($c['id'], $_SESSION['user_id']) : null;

            $canDelete = hasMinimumRole('moderator') || ($currentUser && $currentUser['id'] == $c['user_id']);

            $html .= '<div class="comment-item" id="comment-' . $c['id'] . '">';
            $html .= '<div class="comment-header">';
            $html .= '<div class="comment-author">';
            $html .= '<a href="profile.php?id=' . $c['user_id'] . '">';
            $html .= '<img src="' . getUserAvatar($c) . '" alt="' . e($c['username']) . '" class="comment-author-avatar">';
            $html .= '</a>';
            $html .= '<div class="comment-author-info">';
            $html .= '<a href="profile.php?id=' . $c['user_id'] . '" class="comment-author-name">' . e($c['username']) . '</a>';
            if (!empty($c['role']) && $c['role'] === 'moderator') {
                $html .= '<span class="comment-role-badge moderator"><i class="fas fa-shield-alt"></i> Модератор</span>';
            } elseif (!empty($c['role']) && $c['role'] === 'admin') {
                $html .= '<span class="comment-role-badge admin"><i class="fas fa-crown"></i> Администратор</span>';
            }
            $html .= '<span class="comment-date">' . formatDate($c['created_at']) . '</span>';
            $html .= '</div></div>';

            $html .= '<div class="comment-votes">';
            $html .= '<button class="vote-btn' . ($userVote === 'like' ? ' active-like' : '') . '" data-comment-id="' . $c['id'] . '" data-vote-type="like">';
            $html .= '<span>+</span><span class="vote-count">' . $votes['likes'] . '</span></button>';
            $html .= '<button class="vote-btn' . ($userVote === 'dislike' ? ' active-dislike' : '') . '" data-comment-id="' . $c['id'] . '" data-vote-type="dislike">';
            $html .= '<span>-</span><span class="vote-count">' . $votes['dislikes'] . '</span></button>';
            $html .= '</div></div>';

            $html .= '<div class="comment-content">' . nl2br(e($c['content'])) . '</div>';

            if ($canDelete) {
                $html .= '<div class="comment-actions">';
                $html .= '<button class="btn btn-danger btn-small" onclick="deleteComment(' . $c['id'] . ')">Удалить</button>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }
        $html .= '</div>';
    } else {
        $html = '';
    }

    $pagination = '';
    if ($totalPages > 1) {
        $slug = $_POST['news_slug'] ?? '';
        $pagination = '<div class="comment-pagination">';
        if ($page > 1) {
            $pagination .= '<a href="?slug=' . e($slug) . '&comment_sort=' . e($sort) . '&comment_page=' . ($page - 1) . '" class="btn btn-small">← Назад</a>';
        }
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == $page) {
                $pagination .= '<span class="btn btn-accent btn-small">' . $i . '</span>';
            } elseif ($i == 1 || $i == $totalPages || abs($i - $page) <= 1) {
                $pagination .= '<a href="?slug=' . e($slug) . '&comment_sort=' . e($sort) . '&comment_page=' . $i . '" class="btn btn-small">' . $i . '</a>';
            } elseif (abs($i - $page) == 2) {
                $pagination .= '<span style="padding: 5px 10px;">...</span>';
            }
        }
        if ($page < $totalPages) {
            $pagination .= '<a href="?slug=' . e($slug) . '&comment_sort=' . e($sort) . '&comment_page=' . ($page + 1) . '" class="btn btn-small">Вперед →</a>';
        }
        $pagination .= '</div>';
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'pagination' => $pagination,
        'totalCount' => $totalCount
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Неверное действие']);
