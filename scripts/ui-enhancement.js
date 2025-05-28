document.addEventListener('DOMContentLoaded', function() {
  // 添加页面过渡动画
  const mainContent = document.querySelector('.main-content');
  if (mainContent) {
    mainContent.style.opacity = 0;
    mainContent.style.transition = 'opacity 0.5s ease';
    setTimeout(() => {
      mainContent.style.opacity = 1;
    }, 100);
  }
  
  // 为所有卡片添加动画效果
  const cards = document.querySelectorAll('.card, .blog-post, .post-container, .stat-card');
  if (cards.length) {
    cards.forEach((card, index) => {
      card.style.opacity = 0;
      card.style.transform = 'translateY(20px)';
      card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      setTimeout(() => {
        card.style.opacity = 1;
        card.style.transform = 'translateY(0)';
      }, 100 + (index * 50));
    });
  }
});