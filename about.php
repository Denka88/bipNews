<?php
require_once 'includes/functions.php';

$pageTitle = 'О нас - BipNews';
$pageDescription = 'Узнайте больше о новостном портале BipNews — информационной платформе техникума Бизнес и Право для студентов и преподавателей.';
require 'includes/header.php';
?>

<div class="wide-container">
    <h1 class="page-title">О нас</h1>
    
    <div class="about-page">
        <div class="about-intro">
            <h2>Добро пожаловать в BipNews!</h2>
            <p>BipNews — это официальный информационный портал техникума «Бизнес и Право». Мы собираем все самые актуальные новости, события и достижения нашего учебного заведения в одном месте.</p>
        </div>
        
        <div class="about-features">
            <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-newspaper"></i></div>
                <h3>Актуальные новости</h3>
                <p>Следите за последними событиями, мероприятиями и изменениями в жизни техникума. Мы публикуем новости сразу после их появления.</p>
            </div>
            
            <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-comments"></i></div>
                <h3>Живое общение</h3>
                <p>Обсуждайте новости с однокурсниками и преподавателями. Оставляйте комментарии, делитесь мнением и задавайте вопросы.</p>
            </div>
            
            <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3>Наше сообщество</h3>
                <p>Присоединяйтесь к сообществу студентов и выпускников техникума. Узнавайте первым о важных событиях и изменениях.</p>
            </div>
        </div>
        
        <div class="about-contact">
            <h2>Контакты</h2>
            <p>Если у вас есть вопросы, предложения или вы хотите поделиться новостью — свяжитесь с нами через <a href="contact.php">форму обратной связи</a>.</p>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
