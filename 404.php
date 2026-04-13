<?php
require_once 'includes/functions.php';

$pageTitle = '404 - Страница не найдена | BipNews';
require 'includes/header.php';
?>

<div class="error-404-container">
    <div class="error-404-content">
        <h1 class="error-code">404</h1>
        <h2 class="error-title">Страница не найдена</h2>
        <p class="error-message">
            К сожалению, запрашиваемая страница не существует или была перемещена.
        </p>
        <div class="error-actions">
            <a href="index.php" class="btn btn-accent">
                <i class="fas fa-home"></i> На главную
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Вернуться назад
            </a>
        </div>
    </div>
</div>

<style>
.error-404-container {
    max-width: 600px;
    margin: 60px auto;
    text-align: center;
    padding: 40px 20px;
}

.error-code {
    font-size: 120px;
    font-weight: 900;
    color: var(--accent-color, #e74c3c);
    margin: 0;
    line-height: 1;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.error-title {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-color, #333);
    margin: 20px 0 10px;
}

.error-message {
    font-size: 18px;
    color: var(--text-secondary, #666);
    margin: 20px 0 40px;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-actions .btn {
    padding: 12px 30px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.error-actions .btn-accent {
    background: var(--accent-color, #e74c3c);
    color: #fff;
}

.error-actions .btn-accent:hover {
    background: var(--accent-color-hover, #c0392b);
}

.error-actions .btn-secondary {
    background: #95a5a6;
    color: #fff;
}

.error-actions .btn-secondary:hover {
    background: #7f8c8d;
}

@media (max-width: 768px) {
    .error-code {
        font-size: 80px;
    }

    .error-title {
        font-size: 24px;
    }

    .error-message {
        font-size: 16px;
    }

    .error-actions {
        flex-direction: column;
    }

    .error-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require 'includes/footer.php'; ?>
