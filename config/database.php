<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bipnews');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('RECAPTCHA_SITE_KEY', '6LcCW7IsAAAAAN2Gnijy082Zwyyjp6Li5zdGpMtA');
define('RECAPTCHA_SECRET_KEY', '6LcCW7IsAAAAAAFdq6AYUgjBXv5IKbTfJ4lw5a_w');

function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
