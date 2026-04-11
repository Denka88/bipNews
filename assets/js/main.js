/**
 * BipNews - Основной JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Голосование за комментарии (лайки/дизлайки)
    // ========================================
    document.addEventListener('click', function(e) {
        const voteBtn = e.target.closest('.vote-btn');
        if (!voteBtn) return;
        
        e.preventDefault();

        const commentId = voteBtn.dataset.commentId;
        const voteType = voteBtn.dataset.voteType;

        fetch('comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=vote&comment_id=${commentId}&vote_type=${voteType}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const likesCount = voteBtn.closest('.comment-votes').querySelector('[data-vote-type="like"] .vote-count');
                const dislikesCount = voteBtn.closest('.comment-votes').querySelector('[data-vote-type="dislike"] .vote-count');

                if (likesCount) likesCount.textContent = data.likes;
                if (dislikesCount) dislikesCount.textContent = data.dislikes;

                const likeBtn = voteBtn.closest('.comment-votes').querySelector('[data-vote-type="like"]');
                const dislikeBtn = voteBtn.closest('.comment-votes').querySelector('[data-vote-type="dislike"]');

                if (likeBtn) likeBtn.classList.remove('active-like');
                if (dislikeBtn) dislikeBtn.classList.remove('active-dislike');

                if (data.userVote === 'like' && likeBtn) {
                    likeBtn.classList.add('active-like');
                } else if (data.userVote === 'dislike' && dislikeBtn) {
                    dislikeBtn.classList.add('active-dislike');
                }
            } else {
                if (data.message) {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Ошибка голосования:', error);
        });
    });
    
    // ========================================
    // Загрузка аватара профиля
    // ========================================
    const avatarUpload = document.getElementById('avatar-upload');
    const avatarPreview = document.getElementById('avatar-preview');
    
    if (avatarUpload && avatarPreview) {
        avatarUpload.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Проверка типа файла
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Допустимые форматы: JPEG, PNG, GIF, WebP');
                    this.value = '';
                    return;
                }
                
                // Проверка размера (макс 2МБ)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Размер файла не должен превышать 2 МБ');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // ========================================
    // Подтверждение удаления
    // ========================================
    const deleteLinks = document.querySelectorAll('[data-confirm-delete]');
    
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const message = this.dataset.confirmDelete || 'Вы уверены, что хотите удалить?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // ========================================
    // Проверка возраста при регистрации
    // ========================================
    const registerForm = document.getElementById('register-form');
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const birthDate = document.getElementById('birth-date').value;
            
            if (birthDate) {
                const birth = new Date(birthDate);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const monthDiff = today.getMonth() - birth.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                
                if (age < 14) {
                    e.preventDefault();
                    alert('Регистрация доступна только для пользователей старше 14 лет!');
                }
            }
        });
    }

});
