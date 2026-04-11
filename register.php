<?php
require_once 'includes/functions.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isLoggedIn()) {
    redirect('index.php');
}

// Проверяем, настроена ли reCAPTCHA
$recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
$recaptchaSecretKey = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
$isRecaptchaConfigured = $recaptchaSiteKey !== '' && $recaptchaSiteKey !== 'ВАШ_SITE_KEY'
    && $recaptchaSecretKey !== '' && $recaptchaSecretKey !== 'ВАШ_SECRET_KEY';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthDate = $_POST['birth_date'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Валидация
    if (empty($username)) {
        $errors[] = 'Имя пользователя обязательно';
    } elseif (preg_match('/[а-яёА-ЯЁ]/u', $username)) {
        $errors[] = 'Имя пользователя должно содержать только латинские буквы, цифры и подчеркивание (кириллица не поддерживается)';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Имя пользователя должно содержать от 3 до 50 символов (латинские буквы, цифры, подчеркивание)';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Неверный формат email';
    }
    
    if (empty($birthDate)) {
        $errors[] = 'Дата рождения обязательна';
    } elseif (!checkAge($birthDate)) {
        $errors[] = 'Регистрация доступна только для пользователей старше 14 лет';
    }
    
    if (empty($password)) {
        $errors[] = 'Пароль обязателен';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }
    
    if ($password !== $passwordConfirm) {
        $errors[] = 'Пароли не совпадают';
    }

    // Проверка reCAPTCHA
    if (empty($errors) && $isRecaptchaConfigured) {
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptchaResponse)) {
            $errors[] = 'Подтвердите, что вы не робот';
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
                $errors[] = 'Ошибка проверки reCAPTCHA';
            } else {
                $result = json_decode($response, true);
                if (!($result['success'] ?? false)) {
                    $errors[] = 'Не пройдена проверка reCAPTCHA';
                }
            }
        }
    }

    // Проверка уникальности username
    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Имя пользователя уже занято';
        }
    }
    
    // Проверка уникальности email
    if (empty($errors) && !empty($email)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email уже зарегистрирован';
        }
    }
    
    // Регистрация
    if (empty($errors)) {
        $pdo = getDB();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, email, password, birth_date, role)
            VALUES (?, ?, ?, ?, ?, 'user')
        ");
        
        try {
            $stmt->execute([
                empty($fullName) ? null : $fullName,
                $username,
                empty($email) ? null : $email,
                $hashedPassword,
                $birthDate
            ]);
            
            // Автоматическая авторизация
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_role'] = 'user';
            $_SESSION['username'] = $username;
            
            redirect('index.php');
        } catch (PDOException $e) {
            $errors[] = 'Ошибка регистрации. Попробуйте позже.';
        }
    }
}

$pageTitle = 'Регистрация - BipNews';
require 'includes/header.php';
?>

<div class="auth-container">
    <h1 class="auth-title">Регистрация</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="form-error">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form id="register-form" method="POST" action="">
        <div class="form-group">
            <label for="full-name">ФИО (необязательно)</label>
            <input type="text" id="full-name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="username">Имя пользователя *</label>
            <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>" placeholder="Только латинские буквы (a-z), цифры и _">
            <small>Разрешены только латинские буквы, цифры и знак подчеркивания</small>
        </div>
        
        <div class="form-group">
            <label for="email">Email (необязательно)</label>
            <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="birth-date">Дата рождения *</label>
            <input type="date" id="birth-date" name="birth_date" required max="<?= date('Y-m-d', strtotime('-14 years')) ?>">
            <small>Регистрация доступна только для пользователей старше 14 лет</small>
        </div>
        
        <div class="form-group">
            <label for="password">Пароль *</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="password-confirm">Подтверждение пароля *</label>
            <input type="password" id="password-confirm" name="password_confirm" required minlength="6">
        </div>

        <?php
        $recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
        $isRecaptchaConfigured = $recaptchaSiteKey !== '' && $recaptchaSiteKey !== 'ВАШ_SITE_KEY';
        ?>

        <?php if ($isRecaptchaConfigured): ?>
        <div class="form-group">
            <div class="recaptcha-wrapper">
                <div class="g-recaptcha" data-sitekey="<?= e($recaptchaSiteKey) ?>"></div>
            </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-accent" style="width: 100%;">Зарегистрироваться</button>
    </form>
    
    <div class="auth-link">
        <p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
    </div>
</div>

<?php if ($isRecaptchaConfigured): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
