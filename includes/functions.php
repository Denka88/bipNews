<?php
require_once __DIR__ . '/../config/database.php';

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Проверка авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Проверка роли
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

// Проверка минимальной роли
function hasMinimumRole($role) {
    if (!isLoggedIn()) return false;
    
    $roles = ['user' => 1, 'moderator' => 2, 'admin' => 3];
    $userRole = $_SESSION['user_role'] ?? 'user';
    
    return ($roles[$userRole] ?? 1) >= ($roles[$role] ?? 1);
}

// Проверка блокировки
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

// Перенаправление
function redirect($url) {
    header("Location: $url");
    exit;
}

// Перенаправление на страницу 404
function redirect404() {
    header("Location: /404.php");
    exit;
}

// Проверка возраста (минимум 14 лет)
function checkAge($birthDate) {
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    return $age >= 14;
}

// Рассчитать возраст по дате рождения
function getAge($birthDate) {
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

// Безопасный вывод
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Генерация slug из заголовка
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-zа-яё0-9\s-]/u', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = preg_replace('/^-+|-+$/', '', $slug);
    
    // Добавляем уникальный суффикс
    $slug = $slug . '-' . time();
    
    return $slug;
}

// Форматирование даты
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

// Получение аватара пользователя
function getUserAvatar($user) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $isInAdmin = (strpos($scriptDir, 'admin') !== false);
    $prefix = $isInAdmin ? '../' : '';
    
    if (!empty($user['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $user['avatar'])) {
        return $prefix . 'uploads/avatars/' . $user['avatar'];
    }
    return $prefix . 'assets/images/default-avatar.png';
}

// Получение данных текущего пользователя
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Получение статистики лайков/дизлайков комментария
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

// Проверка, голосовал ли пользователь за комментарий
function hasUserVoted($commentId, $userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $userId]);
    return $stmt->fetchColumn();
}

// Получение количества комментариев к новости
function getNewsCommentsCount($newsId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE news_id = ?");
    $stmt->execute([$newsId]);
    return (int)$stmt->fetchColumn();
}

// Обрезка текста
function truncateText($text, $length = 200) {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}

// Проверка, является ли текущий пользователь автором комментария
function isCommentAuthor($commentUserId) {
    return isLoggedIn() && $_SESSION['user_id'] == $commentUserId;
}

// Получение роли пользователя (текстовое представление)
function getRoleName($role) {
    $roles = [
        'user' => 'Пользователь',
        'moderator' => 'Модератор',
        'admin' => 'Администратор'
    ];
    return $roles[$role] ?? 'Пользователь';
}

/**
 * Санитизация HTML-контента новости.
 * Разрешает только безопасные теги и атрибуты, удаляет <script>, <iframe>, on* и другие опасные элементы.
 */
function sanitizeNewsContent($html) {
    // Разрешённые теги (только форматирование текста и картинки)
    $allowedTags = '<p><br><b><strong><i><em><u><a><ul><ol><li><h2><h3><h4><h5><blockquote><pre><code><img><hr>';
    
    // Strip_tags для удаления всех неразрешённых тегов
    $html = strip_tags($html, $allowedTags);
    
    // Загружаем через DOMDocument для обработки атрибутов
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->encoding = 'UTF-8';
    
    // Оборачиваем в обёртку для корректного парсинга
    $wrapped = '<?xml encoding="UTF-8"><div id="wrapper">' . $html . '</div>';
    
    // Подавляем предупреждения
    libxml_use_internal_errors(true);
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $wrapper = $dom->getElementById('wrapper');
    if (!$wrapper) {
        return $html;
    }
    
    // Разрешённые атрибуты для каждого тега
    $allowedAttrs = [
        'a'   => ['href', 'title'],
        'img' => ['src', 'alt', 'title'],
    ];
    
    // Обходим все элементы
    $allElements = $wrapper->getElementsByTagName('*');
    // Собираем в массив, т.к. live-коллекция меняется при удалении
    $elements = [];
    foreach ($allElements as $el) {
        $elements[] = $el;
    }
    
    foreach ($elements as $el) {
        if (!($el instanceof DOMElement)) continue;
        
        $tagName = strtolower($el->tagName);
        
        // Удаляем все атрибуты, кроме разрешённых
        $attrsToRemove = [];
        foreach ($el->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $allowed = $allowedAttrs[$tagName] ?? [];
            
            // Удаляем все on* атрибуты (onclick, onerror, onload и т.д.)
            if (strpos($attrName, 'on') === 0) {
                $attrsToRemove[] = $attr->name;
                continue;
            }
            
            // Удаляем javascript: и data: в href
            if ($attrName === 'href' || $attrName === 'src') {
                $value = strtolower(trim($attr->value));
                if (strpos($value, 'javascript:') === 0 || strpos($value, 'data:') === 0 || strpos($value, 'vbscript:') === 0) {
                    $attrsToRemove[] = $attr->name;
                    continue;
                }
            }
            
            // Удаляем style (может содержать expression())
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
        
        // Для <a> добавляем rel="nofollow noopener" для безопасности
        if ($tagName === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'nofollow noopener');
            $el->setAttribute('target', '_blank');
        }
    }
    
    // Извлекаем содержимое wrapper
    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }
    
    return $result;
}
