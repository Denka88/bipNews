<?php
require_once 'includes/functions.php';

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name)) {
        $error = 'Укажите ваше имя';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Укажите корректный email';
    } elseif (empty($subject)) {
        $error = 'Укажите тему сообщения';
    } elseif (empty($message)) {
        $error = 'Введите сообщение';
    } elseif (strlen($message) < 10) {
        $error = 'Сообщение слишком короткое (минимум 10 символов)';
    }
    
    if (empty($error)) {
        // Здесь можно добавить сохранение в БД или отправку на почту
        // Для примера просто показываем успех
        $success = 'Ваше сообщение успешно отправлено! Мы ответим вам в ближайшее время.';
        $_POST = [];
    }
}

$pageTitle = 'Обратная связь - BipNews';
require 'includes/header.php';
?>

<div class="wide-container">
    <h1 class="page-title">Обратная связь</h1>
    
    <div class="contact-page">
        <div class="contact-intro">
            <p>Есть вопрос, предложение или хотите поделиться новостью? Заполните форму ниже, и мы обязательно ответим вам.</p>
        </div>
        
        <?php if ($success): ?>
            <div class="form-success"><?= e($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="form-error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="contact-form">
            <div class="form-group">
                <label for="name">Ваше имя *</label>
                <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="Как к вам обращаться">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="email@example.com">
            </div>
            
            <div class="form-group">
                <label for="subject">Тема *</label>
                <input type="text" id="subject" name="subject" required value="<?= e($_POST['subject'] ?? '') ?>" placeholder="О чём хотите написать">
            </div>
            
            <div class="form-group">
                <label for="message">Сообщение *</label>
                <textarea id="message" name="message" required placeholder="Введите ваше сообщение..." style="min-height: 200px;"><?= e($_POST['message'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-accent">Отправить сообщение</button>
        </form>
        
        <div class="contact-info">
            <h2>Контактная информация</h2>
            <div class="contact-info-grid">
                <div class="contact-info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Адрес</strong>
                        <p>352632, г. Белореченск, ул. Чапаева, д.48</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <strong>Телефон</strong>
                        <p>+7 (861) 553-3912 <br> +7 (988) 520-7869</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Email</strong>
                        <p>bip_bel@mail.ru</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Время работы</strong>
                        <p>Пн-Пт: 8:00 — 17:00 <br> Сб-Вс: Выходной</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
