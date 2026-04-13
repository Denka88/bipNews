<?php

$dbHost = 'localhost';
$dbName = 'bipnews';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к MySQL: " . $e->getMessage());
}

$stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
$dbExists = $stmt->rowCount() > 0;

if ($dbExists) {
    echo "База данных '$dbName' уже существует. Удалите её вручную, если хотите переустановить.<br>";
    echo "<a href='index.php'>Перейти на сайт</a>";
    exit;
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");

$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    die("Файл database.sql не найден!");
}

$sql = file_get_contents($sqlFile);

$lines = explode("\n", $sql);
$sqlFiltered = [];
$useNext = false;
foreach ($lines as $line) {
    if (strpos(strtoupper($line), 'USE BIPNEWS') === 0 || strpos(strtoupper($line), 'USE `bipnews`') === 0) {
        $useNext = true;
        continue;
    }
    if (strpos(strtoupper($line), 'CREATE DATABASE') === 0) {
        continue;
    }
    if ($useNext || strpos(strtoupper($line), 'CREATE DATABASE') === false) {
        $sqlFiltered[] = $line;
    }
}

$queries = array_filter(array_map('trim', explode(";", implode("\n", $sqlFiltered))));

foreach ($queries as $query) {
    if (empty($query)) continue;
    try {
        $pdo->exec($query);
    } catch (PDOException $e) {
        echo "Ошибка выполнения запроса: " . $e->getMessage() . "<br>";
    }
}

$uploadDirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/avatars',
    __DIR__ . '/uploads/news',
];

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

$htaccessContent = "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh\n";
foreach ([$uploadDirs[1], $uploadDirs[2]] as $dir) {
    file_put_contents($dir . '/.htaccess', $htaccessContent);
}

echo "<!DOCTYPE html>
<html lang='ru'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Установка BipNews завершена</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            color: #000;
        }
        h1 {
            color: #FFB841;
        }
        .success {
            background: #f0f0f0;
            padding: 15px;
            border-left: 4px solid #FFB841;
        }
        .info {
            background: #fff3e0;
            padding: 15px;
            margin: 15px 0;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #FFB841;
            color: #000;
            text-decoration: none;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
        }
    </style>
</head>
<body>
    <h1>Установка BipNews завершена!</h1>
    <div class='success'>
        <p>База данных успешно создана.</p>
        <p>Таблицы созданы и данные добавлены.</p>
    </div>
    <div class='info'>
        <h3>Данные для входа администратора:</h3>
        <p><strong>Имя пользователя:</strong> <code>admin</code></p>
        <p><strong>Пароль:</strong> <code>admin123</code></p>
        <p><em>Рекомендуется сменить пароль после первого входа!</em></p>
    </div>
    <p>Теперь удалите файл <code>install.php</code> по соображениям безопасности.</p>
    <a href='index.php'>Перейти на сайт</a>
</body>
</html>";
