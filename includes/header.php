<?php
require_once __DIR__ . '/functions.php';
$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'BipNews - Новости техникума Бизнес и Право';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$pathPrefix = (strpos($scriptDir, 'admin') !== false) ? '../' : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' https: data:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self' https://www.google.com; frame-src https://www.google.com https://www.recaptcha.net; object-src 'none'; base-uri 'self';">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $pathPrefix ?>assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="<?= $pathPrefix ?>index.php" class="logo">
                    <span class="logo-text">Bip<span class="logo-accent">News</span></span>
                </a>
                
                <nav class="main-nav">
                    <a href="<?= $pathPrefix ?>index.php">Главная</a>
                    <a href="<?= $pathPrefix ?>about.php">О нас</a>
                    <a href="<?= $pathPrefix ?>contact.php">Обратная связь</a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= $pathPrefix ?>profile.php?id=<?= $currentUser['id'] ?>">Мой профиль</a>
                        
                        <?php if (hasMinimumRole('admin')): ?>
                            <a href="<?= $pathPrefix ?>admin/index.php" class="nav-highlight">Админ-панель</a>
                        <?php endif; ?>
                        
                        <div class="user-menu">
                            <a href="<?= $pathPrefix ?>profile.php?id=<?= $currentUser['id'] ?>" class="user-avatar-link">
                                <img src="<?= getUserAvatar($currentUser) ?>" alt="Аватар" class="user-avatar-small">
                            </a>
                            <span class="username"><?= e($currentUser['username']) ?></span>
                            <a href="<?= $pathPrefix ?>logout.php" class="btn-logout">Выход</a>
                        </div>
                    <?php else: ?>
                        <a href="<?= $pathPrefix ?>login.php">Вход</a>
                        <a href="<?= $pathPrefix ?>register.php" class="btn-accent">Регистрация</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
