<?php
require_once 'includes/functions.php';

$pdo = getDB();

$search = trim($_GET['search'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where  = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE (n.title LIKE ? OR n.content LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM news n $where");
$totalStmt->execute($params);
$totalNews = $totalStmt->fetchColumn();
$totalPages = ceil($totalNews / $perPage);

$stmt = $pdo->prepare("
    SELECT n.*, u.username, u.avatar
    FROM news n
    LEFT JOIN users u ON n.author_id = u.id
    $where
    ORDER BY n.published_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$newsList = $stmt->fetchAll();

$pageTitle = 'BipNews - Новости техникума Бизнес и Право';
$pageDescription = 'Актуальные новости и события техникума Бизнес и Право. Читайте новости, обсужайте с другими студентами и будьте в курсе жизни учебного заведения.';

$bannersStmt = $pdo->query("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC, id ASC");
$banners = $bannersStmt->fetchAll();

require 'includes/header.php';
?>

<!-- Hero Slider -->
<section class="hero-slider">
    <!-- Слайдер с картинками (задний план) -->
    <?php if (!empty($banners)): ?>
        <div class="slider-track">
            <?php foreach ($banners as $index => $banner): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                    <img src="uploads/banners/<?= e($banner['image']) ?>" alt="">
                    <div class="slide-overlay"></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="slider-track">
            <div class="slide active">
                <div class="slide-default-bg"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Приветствие поверх слайдера -->
    <div class="hero-overlay">
        <div class="hero-content-inner">
            <h1 class="hero-title">Добро пожаловать в <span>BipNews</span></h1>
            <p class="hero-slogan">Все новости техникума «Бизнес и Право» в одном месте</p>
            <div class="hero-features">
                <div class="hero-feature">
                    <span class="feature-icon"><i class="fas fa-newspaper"></i></span>
                    <span>Актуальные новости</span>
                </div>
                <div class="hero-feature">
                    <span class="feature-icon"><i class="fas fa-comments"></i></span>
                    <span>Обсуждения</span>
                </div>
                <div class="hero-feature">
                    <span class="feature-icon"><i class="fas fa-graduation-cap"></i></span>
                    <span>Наш техникум</span>
                </div>
            </div>
            <?php if (!isLoggedIn()): ?>
                <div class="hero-actions">
                    <a href="register.php" class="btn btn-accent">Присоединиться</a>
                    <a href="login.php" class="btn">Войти</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($banners) > 1): ?>
        <button class="slider-btn slider-prev" onclick="changeSlide(-1)">&#10094;</button>
        <button class="slider-btn slider-next" onclick="changeSlide(1)">&#10095;</button>

        <div class="slider-dots">
            <?php foreach ($banners as $index => $banner): ?>
                <span class="dot <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)"></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="wide-container">

<!-- Поиск новостей -->
<div class="news-search">
    <form method="GET" class="search-form">
        <div class="search-input-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" placeholder="Поиск новостей..." value="<?= e($search) ?>" class="search-input">
            <?php if ($search !== ''): ?>
                <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="search-clear" title="Сбросить"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-accent btn-small search-btn">Найти</button>
    </form>
</div>

<?php if ($search !== ''): ?>
    <h1 class="page-title">Результаты поиска: <?= e($search) ?></h1>
<?php else: ?>
    <h1 class="page-title">Последние новости</h1>
<?php endif; ?>

<?php if (empty($newsList)): ?>
    <div class="text-center" style="padding: 60px 20px;">
        <p style="font-size: 18px; color: #999;">Новостей пока нет</p>
        <?php if (hasMinimumRole('admin')): ?>
            <a href="admin/news_create.php" class="btn btn-accent mt-20">Создать первую новость</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="news-grid">
        <?php foreach ($newsList as $idx => $newsItem): ?>
            <?php
            $firstImage = '';
            if (!empty($newsItem['images'])) {
                $images = json_decode($newsItem['images'], true);
                if (!empty($images) && is_array($images)) {
                    $firstImage = $images[0];
                }
            }

            if (empty($firstImage) && !empty($newsItem['content'])) {
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $newsItem['content'], $matches)) {
                    $firstImage = $matches[1];
                }
            }

            $isExternalImage = !empty($firstImage) && (strpos($firstImage, 'http://') === 0 || strpos($firstImage, 'https://') === 0 || strpos($firstImage, '//') === 0);
            ?>
            <article class="news-card">
                <a href="news.php?slug=<?= e($newsItem['slug']) ?>" class="news-card-link">
                    <?php if ($firstImage): ?>
                        <div class="news-card-image">
                            <?php if ($isExternalImage): ?>
                                <img src="<?= e($firstImage) ?>" alt="<?= e($newsItem['title']) ?>">
                            <?php else: ?>
                                <img src="uploads/news/<?= e($firstImage) ?>" alt="<?= e($newsItem['title']) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="news-card-body">
                        <h2 class="news-card-title"><?= e($newsItem['title']) ?></h2>
                        <?php
                        $plainContent = strip_tags($newsItem['content']);
                        $excerpt = truncateText($plainContent, 150);
                        ?>
                        <?php if ($excerpt): ?>
                            <p class="news-card-excerpt"><?= e($excerpt) ?></p>
                        <?php endif; ?>
                        <div class="news-card-meta">
                            <span><i class="far fa-calendar-alt"></i> <?= formatDate($newsItem['published_at']) ?></span>
                            <span><i class="far fa-eye"></i> <?= (int)$newsItem['views'] ?></span>
                            <span><i class="far fa-comment"></i> <?= getNewsCommentsCount($newsItem['id']) ?></span>
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 40px; display: flex; justify-content: center; gap: 10px;">
            <?php
            $searchQS = $search !== '' ? '&search=' . urlencode($search) : '';
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $searchQS ?>" class="btn">← Назад</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="btn btn-accent"><?= $i ?></span>
                <?php elseif ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                    <a href="?page=<?= $i ?><?= $searchQS ?>" class="btn"><?= $i ?></a>
                <?php elseif (abs($i - $page) == 3): ?>
                    <span style="padding: 10px;">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?><?= $searchQS ?>" class="btn">Вперед →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');
let slideInterval;

function showSlide(index) {
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));

    currentSlide = index >= slides.length ? 0 : index < 0 ? slides.length - 1 : index;
    slides[currentSlide].classList.add('active');
    dots[currentSlide]?.classList.add('active');
}

function changeSlide(direction) {
    showSlide(currentSlide + direction);
    resetInterval();
}

function goToSlide(index) {
    showSlide(index);
    resetInterval();
}

function resetInterval() {
    clearInterval(slideInterval);
    slideInterval = setInterval(() => changeSlide(1), 5000);
}

slideInterval = setInterval(() => changeSlide(1), 5000);
</script>

<?php require 'includes/footer.php'; ?>