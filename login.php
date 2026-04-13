<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $errors[] = 'Введите имя пользователя';
    }

    if (empty($password)) {
        $errors[] = 'Введите пароль';
    }

    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Неверное имя пользователя или пароль';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            $redirect = $_GET['redirect'] ?? 'index.php';
            redirect($redirect);
        }
    }
}

$pageTitle = 'Вход - BipNews';
$pageDescription = 'Войдите в аккаунт на портале BipNews для доступа к комментариям и обсуждению новостей техникума Бизнес и Право.';
require 'includes/header.php';
?>

<div class="auth-container">
    <h1 class="auth-title">Вход</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="form-error">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Имя пользователя</label>
            <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn btn-accent" style="width: 100%;">Войти</button>
    </form>
    
    <div class="auth-link">
        <p>Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
