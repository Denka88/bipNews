        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-inner">
                <div class="footer-info">
                    <p>&copy; <?= date('Y') ?> BipNews - Новости техникума "Бизнес и Право"</p>
                </div>
                <div class="footer-links">
                    <a href="<?= $pathPrefix ?>index.php">Главная</a>
                    <a href="<?= $pathPrefix ?>about.php">О нас</a>
                    <a href="<?= $pathPrefix ?>contact.php">Обратная связь</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= $pathPrefix ?>profile.php?id=<?= $currentUser['id'] ?>">Профиль</a>
                    <?php else: ?>
                        <a href="<?= $pathPrefix ?>login.php">Вход</a>
                        <a href="<?= $pathPrefix ?>register.php">Регистрация</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="<?= $pathPrefix ?>assets/js/main.js"></script>
</body>
</html>
