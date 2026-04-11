<?php
require_once 'includes/functions.php';

// Уничтожаем сессию
session_unset();
session_destroy();

// Перенаправляем на главную
redirect('index.php');
