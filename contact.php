<?php
require_once 'includes/functions.php';

// Проверяем, настроена ли reCAPTCHA
$recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
$recaptchaSecretKey = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
$isRecaptchaConfigured = $recaptchaSiteKey !== '' && $recaptchaSiteKey !== 'ВАШ_SITE_KEY'
    && $recaptchaSecretKey !== '' && $recaptchaSecretKey !== 'ВАШ_SECRET_KEY';

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

    // Проверка reCAPTCHA
    if (empty($error) && $isRecaptchaConfigured) {
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptchaResponse)) {
            $error = 'Подтвердите, что вы не робот';
        } else {
            $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
            $data = http_build_query([
                'secret'   => $recaptchaSecretKey,
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $data,
                    'timeout' => 10,
                ],
            ]);

            $response = @file_get_contents($verifyUrl, false, $context);
            if ($response === false) {
                $error = 'Ошибка проверки reCAPTCHA';
            } else {
                $result = json_decode($response, true);
                if (!($result['success'] ?? false)) {
                    $error = 'Не пройдена проверка reCAPTCHA';
                }
            }
        }
    }

    if (empty($error)) {
        $pdo = getDB();

        // Создаём таблицу, если ещё не существует
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

        $stmt = $pdo->prepare("INSERT INTO feedback_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $subject, $message]);
            $success = 'Ваше сообщение успешно отправлено! Мы ответим вам в ближайшее время.';
            $_POST = [];
        } catch (PDOException $e) {
            $error = 'Ошибка при отправке сообщения. Попробуйте позже.';
        }
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

            <?php if ($isRecaptchaConfigured): ?>
            <div class="form-group">
                <div class="recaptcha-wrapper">
                    <div class="g-recaptcha" data-sitekey="<?= e($recaptchaSiteKey) ?>"></div>
                </div>
            </div>
            <?php endif; ?>

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

<?php if ($isRecaptchaConfigured): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
