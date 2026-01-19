<?php
/**
 * Modern Help Article View
 * Single article with related articles, breadcrumbs, and feedback
 */
$hero_title = $article['title'];
$hero_subtitle = "Help Center";
$hero_type = ucfirst($article['module_tag']);
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Module display names and icons
$moduleConfig = [
    'getting_started' => ['name' => 'Getting Started', 'icon' => 'fa-rocket', 'color' => '#10b981'],
    'core' => ['name' => 'Platform Basics', 'icon' => 'fa-cube', 'color' => '#6366f1'],
    'wallet' => ['name' => 'Wallet & Credits', 'icon' => 'fa-wallet', 'color' => '#f59e0b'],
    'listings' => ['name' => 'Marketplace', 'icon' => 'fa-store', 'color' => '#ec4899'],
    'groups' => ['name' => 'Community Hubs', 'icon' => 'fa-users', 'color' => '#8b5cf6'],
    'events' => ['name' => 'Events', 'icon' => 'fa-calendar-days', 'color' => '#06b6d4'],
    'volunteering' => ['name' => 'Volunteering', 'icon' => 'fa-hand-holding-heart', 'color' => '#f43f5e'],
    'blog' => ['name' => 'News & Updates', 'icon' => 'fa-newspaper', 'color' => '#64748b'],
    'polls' => ['name' => 'Polls & Voting', 'icon' => 'fa-square-poll-vertical', 'color' => '#14b8a6'],
    'goals' => ['name' => 'Goals & Buddies', 'icon' => 'fa-bullseye', 'color' => '#f97316'],
    'governance' => ['name' => 'Governance', 'icon' => 'fa-landmark', 'color' => '#7c3aed'],
    'gamification' => ['name' => 'Badges & Rewards', 'icon' => 'fa-trophy', 'color' => '#eab308'],
    'ai_assistant' => ['name' => 'AI Assistant', 'icon' => 'fa-sparkles', 'color' => '#a855f7'],
    'sustainability' => ['name' => 'Impact & SDGs', 'icon' => 'fa-leaf', 'color' => '#22c55e'],
    'offline' => ['name' => 'Offline Mode', 'icon' => 'fa-wifi-slash', 'color' => '#94a3b8'],
    'mobile' => ['name' => 'Mobile App', 'icon' => 'fa-mobile-screen', 'color' => '#3b82f6'],
    'insights' => ['name' => 'Your Stats', 'icon' => 'fa-chart-line', 'color' => '#06b6d4'],
    'security' => ['name' => 'Privacy & Security', 'icon' => 'fa-shield-halved', 'color' => '#ef4444'],
    'resources' => ['name' => 'Resource Library', 'icon' => 'fa-book-open', 'color' => '#84cc16'],
    'reviews' => ['name' => 'Reviews & Ratings', 'icon' => 'fa-star', 'color' => '#fbbf24'],
];

$config = $moduleConfig[$article['module_tag']] ?? ['name' => $moduleName, 'icon' => 'fa-file-lines', 'color' => '#6366f1'];
?>


<div class="help-article-page">
    <div class="help-article-container">

        <!-- Breadcrumbs -->
        <nav class="help-breadcrumbs">
            <a href="<?= $basePath ?>/">Home</a>
            <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
            <a href="<?= $basePath ?>/help">Help Center</a>
            <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
            <span><?= htmlspecialchars($article['title']) ?></span>
        </nav>

        <!-- Article Card -->
        <article class="help-article-card">
            <header class="help-article-header">
                <div class="help-article-badge" style="background: <?= $config['color'] ?>;">
                    <i class="fa-solid <?= $config['icon'] ?>"></i>
                    <?= $config['name'] ?>
                </div>
                <h1 class="help-article-title"><?= htmlspecialchars($article['title']) ?></h1>
                <div class="help-article-meta">
                    <?php if (!empty($article['view_count'])): ?>
                    <span><i class="fa-solid fa-eye"></i> <?= number_format($article['view_count']) ?> views</span>
                    <?php endif; ?>
                    <?php if (!empty($article['updated_at'])): ?>
                    <span><i class="fa-solid fa-clock"></i> Updated <?= date('M j, Y', strtotime($article['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="help-article-content">
                <?= $article['content'] ?>
            </div>

            <!-- Feedback Section -->
            <div class="help-feedback">
                <p class="help-feedback-title">Was this article helpful?</p>
                <div class="help-feedback-buttons">
                    <button class="help-feedback-btn yes" onclick="submitFeedback(true, this)">
                        <i class="fa-solid fa-thumbs-up"></i>
                        Yes, helpful
                    </button>
                    <button class="help-feedback-btn no" onclick="submitFeedback(false, this)">
                        <i class="fa-solid fa-thumbs-down"></i>
                        Not helpful
                    </button>
                </div>
                <p class="help-feedback-thanks" id="feedback-thanks">
                    <i class="fa-solid fa-check-circle"></i> Thank you for your feedback!
                </p>
            </div>
        </article>

        <?php if (!empty($relatedArticles)): ?>
        <!-- Related Articles -->
        <aside class="help-related">
            <h2 class="help-related-title">
                <i class="fa-solid fa-lightbulb"></i>
                Related Articles
            </h2>
            <div class="help-related-list">
                <?php foreach ($relatedArticles as $related): ?>
                <a href="<?= $basePath ?>/help/<?= htmlspecialchars($related['slug']) ?>" class="help-related-link">
                    <i class="fa-solid fa-file-lines"></i>
                    <span><?= htmlspecialchars($related['title']) ?></span>
                    <i class="fa-solid fa-chevron-right arrow"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php endif; ?>

        <!-- Back Button -->
        <a href="<?= $basePath ?>/help" class="help-back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Help Center
        </a>

    </div>
</div>

<script>
function submitFeedback(helpful, btn) {
    const buttons = document.querySelectorAll('.help-feedback-btn');
    buttons.forEach(b => {
        b.classList.remove('selected');
        b.disabled = true;
    });
    btn.classList.add('selected');

    fetch('<?= $basePath ?>/api/help/feedback', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({
            article_slug: '<?= htmlspecialchars($article['slug']) ?>',
            helpful: helpful
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('feedback-thanks').classList.add('show');
    })
    .catch(err => {
        console.error('Feedback error:', err);
        document.getElementById('feedback-thanks').classList.add('show');
    });
}
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
