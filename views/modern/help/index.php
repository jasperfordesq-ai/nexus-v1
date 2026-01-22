<?php
/**
 * Modern Help Center Index
 * Glassmorphic design with search, categories, and popular articles
 */
$hero_title = "Help Center";
$hero_subtitle = "Find answers to your questions";
$hero_type = "Support";
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
?>


<div class="help-center-page">
    <div class="help-center-container">

        <!-- Hero Section -->
        <div class="help-hero">
            <div class="help-hero-icon">
                <i class="fa-solid fa-life-ring"></i>
            </div>
            <h1>How can we help?</h1>
            <p>Search our knowledge base or browse categories below</p>

            <!-- Search Bar -->
            <div class="help-search-wrapper">
                <form action="<?= $basePath ?>/help/search" method="GET" class="help-search-form">
                    <i class="fa-solid fa-magnifying-glass help-search-icon" aria-hidden="true"></i>
                    <label for="help-search" class="visually-hidden">Search help articles</label>
                    <input
                        type="text"
                        id="help-search"
                        name="q"
                        class="help-search-input"
                        placeholder="Search for answers..."
                        autocomplete="off"
                        aria-label="Search help articles"
                    >
                    <button type="submit" class="help-search-btn">
                        Search
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($popularArticles)): ?>
        <!-- Popular Articles -->
        <div class="help-popular">
            <h2 class="help-section-title">
                <i class="fa-solid fa-fire"></i>
                Popular Articles
            </h2>
            <div class="help-popular-grid">
                <?php foreach (array_slice($popularArticles, 0, 4) as $article):
                    $config = $moduleConfig[$article['module_tag']] ?? ['name' => ucfirst($article['module_tag']), 'icon' => 'fa-file-lines', 'color' => '#6366f1'];
                ?>
                <a href="<?= $basePath ?>/help/<?= htmlspecialchars($article['slug']) ?>" class="help-popular-card">
                    <div class="help-popular-icon" style="background: <?= $config['color'] ?>;">
                        <i class="fa-solid <?= $config['icon'] ?>"></i>
                    </div>
                    <div class="help-popular-content">
                        <h3><?= htmlspecialchars($article['title']) ?></h3>
                        <span><?= $config['name'] ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($groupedArticles)): ?>
        <!-- Empty State -->
        <div class="help-empty">
            <div class="help-empty-icon">
                <i class="fa-solid fa-book-open"></i>
            </div>
            <h2>No articles found</h2>
            <p>We're working on adding help content. Check back soon!</p>
        </div>
        <?php else: ?>
        <!-- Categories Grid -->
        <div class="help-categories">
            <h2 class="help-section-title">
                <i class="fa-solid fa-folder-open"></i>
                Browse by Category
            </h2>
            <div class="help-category-grid">
                <?php foreach ($groupedArticles as $module => $articles):
                    $config = $moduleConfig[$module] ?? ['name' => ucfirst(str_replace('_', ' ', $module)), 'icon' => 'fa-file-lines', 'color' => '#6366f1'];
                ?>
                <div class="help-category-card">
                    <div class="help-category-header">
                        <div class="help-category-icon" style="background: <?= $config['color'] ?>;">
                            <i class="fa-solid <?= $config['icon'] ?>"></i>
                        </div>
                        <div>
                            <h3 class="help-category-title"><?= $config['name'] ?></h3>
                            <span class="help-category-count"><?= count($articles) ?> article<?= count($articles) !== 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                    <div class="help-category-articles">
                        <?php foreach ($articles as $article): ?>
                        <a href="<?= $basePath ?>/help/<?= htmlspecialchars($article['slug']) ?>" class="help-article-link">
                            <i class="fa-solid fa-file-lines"></i>
                            <span><?= htmlspecialchars($article['title']) ?></span>
                            <i class="fa-solid fa-chevron-right arrow"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contact CTA -->
        <div class="help-contact-cta">
            <h3>Still need help?</h3>
            <p>Can't find what you're looking for? Our team is here to assist you.</p>
            <a href="<?= $basePath ?>/contact" class="help-contact-btn">
                <i class="fa-solid fa-envelope"></i>
                Contact Support
            </a>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
