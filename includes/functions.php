<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

function hasMinimumRole($role) {
    if (!isLoggedIn()) return false;
    
    $roles = ['user' => 1, 'moderator' => 2, 'admin' => 3];
    $userRole = $_SESSION['user_role'] ?? 'user';
    
    return ($roles[$userRole] ?? 1) >= ($roles[$role] ?? 1);
}

function isBanned($userId = null) {
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? 0;
    }
    if (!$userId) return false;
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return (bool)($result['is_banned'] ?? false);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function redirect404() {
    header("Location: /404.php");
    exit;
}

function checkAge($birthDate) {
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    return $age >= 14;
}

function getAge($birthDate) {
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-zа-яё0-9\s-]/u', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = preg_replace('/^-+|-+$/', '', $slug);

    $slug = $slug . '-' . time();
    
    return $slug;
}

function formatDate($date) {
    $months = [
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

function getUserAvatar($user) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $isInAdmin = (strpos($scriptDir, 'admin') !== false);
    $prefix = $isInAdmin ? '../' : '';
    
    if (!empty($user['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $user['avatar'])) {
        return $prefix . 'uploads/avatars/' . $user['avatar'];
    }
    return $prefix . 'assets/images/default-avatar.png';
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function getCommentVotes($commentId) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) as likes,
            SUM(CASE WHEN vote_type = 'dislike' THEN 1 ELSE 0 END) as dislikes
        FROM comment_votes 
        WHERE comment_id = ?
    ");
    $stmt->execute([$commentId]);
    $result = $stmt->fetch();
    
    return [
        'likes' => (int)($result['likes'] ?? 0),
        'dislikes' => (int)($result['dislikes'] ?? 0)
    ];
}

function hasUserVoted($commentId, $userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $userId]);
    return $stmt->fetchColumn();
}

function getNewsCommentsCount($newsId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id = ?");
    $stmt->execute([$newsId]);
    return (int)$stmt->fetchColumn();
}

function truncateText($text, $length = 200) {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}

function isCommentAuthor($commentUserId) {
    return isLoggedIn() && $_SESSION['user_id'] == $commentUserId;
}

function getRoleName($role) {
    $roles = [
        'user' => 'Пользователь',
        'moderator' => 'Модератор',
        'admin' => 'Администратор'
    ];
    return $roles[$role] ?? 'Пользователь';
}

function sanitizeNewsContent($html) {
    $allowedTags = '<p><br><b><strong><i><em><u><a><ul><ol><li><h2><h3><h4><h5><blockquote><pre><code><img><hr>';

    $html = strip_tags($html, $allowedTags);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->encoding = 'UTF-8';

    $wrapped = '<?xml encoding="UTF-8"><div id="wrapper">' . $html . '</div>';

    libxml_use_internal_errors(true);
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $wrapper = $dom->getElementById('wrapper');
    if (!$wrapper) {
        return $html;
    }

    $allowedAttrs = [
        'a'   => ['href', 'title'],
        'img' => ['src', 'alt', 'title'],
    ];

    $allElements = $wrapper->getElementsByTagName('*');
    $elements = [];
    foreach ($allElements as $el) {
        $elements[] = $el;
    }
    
    foreach ($elements as $el) {
        if (!($el instanceof DOMElement)) continue;
        
        $tagName = strtolower($el->tagName);

        $attrsToRemove = [];
        foreach ($el->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $allowed = $allowedAttrs[$tagName] ?? [];

            if (strpos($attrName, 'on') === 0) {
                $attrsToRemove[] = $attr->name;
                continue;
            }

            if ($attrName === 'href' || $attrName === 'src') {
                $value = strtolower(trim($attr->value));
                if (strpos($value, 'javascript:') === 0 || strpos($value, 'data:') === 0 || strpos($value, 'vbscript:') === 0) {
                    $attrsToRemove[] = $attr->name;
                    continue;
                }
            }

            if ($attrName === 'style') {
                $attrsToRemove[] = $attr->name;
                continue;
            }
            
            if (!in_array($attrName, $allowed)) {
                $attrsToRemove[] = $attr->name;
            }
        }
        
        foreach ($attrsToRemove as $attrName) {
            $el->removeAttribute($attrName);
        }

        if ($tagName === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'nofollow noopener');
            $el->setAttribute('target', '_blank');
        }
    }

    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }
    
    return $result;
}
