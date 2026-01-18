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

<style>
/* ============================================
   HELP CENTER - MODERN GLASSMORPHIC DESIGN
   ============================================ */

.help-center-page {
    min-height: 100vh;
    padding: 160px 20px 80px;
    position: relative;
}

@media (max-width: 900px) {
    .help-center-page {
        padding: 100px 16px 120px;
    }
}

/* Ambient Background */
.help-center-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 70%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 90%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
}

[data-theme="dark"] .help-center-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.18) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 70%, rgba(16, 185, 129, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 90%, rgba(139, 92, 246, 0.12) 0%, transparent 50%);
}

.help-center-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Hero Section */
.help-hero {
    text-align: center;
    margin-bottom: 48px;
    padding: 40px 20px;
}

.help-hero-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3);
}

.help-hero-icon i {
    font-size: 2rem;
    color: white;
}

.help-hero h1 {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 12px;
    background: linear-gradient(135deg, #1f2937, #374151);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

[data-theme="dark"] .help-hero h1 {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    -webkit-background-clip: text;
    background-clip: text;
}

.help-hero p {
    font-size: 1.15rem;
    color: #64748b;
    margin: 0;
}

[data-theme="dark"] .help-hero p {
    color: #94a3b8;
}

/* Search Bar */
.help-search-wrapper {
    max-width: 600px;
    margin: 32px auto 0;
}

.help-search-form {
    position: relative;
    display: flex;
    gap: 12px;
}

.help-search-input {
    flex: 1;
    padding: 16px 20px 16px 52px;
    font-size: 1rem;
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    outline: none;
    transition: all 0.3s ease;
}

[data-theme="dark"] .help-search-input {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(99, 102, 241, 0.3);
    color: #f1f5f9;
}

.help-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.help-search-icon {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.1rem;
}

.help-search-btn {
    padding: 16px 28px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.help-search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

/* Popular Articles */
.help-popular {
    margin-bottom: 48px;
}

.help-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .help-section-title {
    color: #f1f5f9;
}

.help-section-title i {
    color: #6366f1;
}

.help-popular-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.help-popular-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.3s ease;
}

[data-theme="dark"] .help-popular-card {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(99, 102, 241, 0.2);
}

.help-popular-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
}

.help-popular-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.help-popular-icon i {
    font-size: 1.25rem;
    color: white;
}

.help-popular-content h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 4px;
    line-height: 1.4;
}

[data-theme="dark"] .help-popular-content h3 {
    color: #f1f5f9;
}

.help-popular-content span {
    font-size: 0.85rem;
    color: #64748b;
}

[data-theme="dark"] .help-popular-content span {
    color: #94a3b8;
}

/* Category Grid */
.help-categories {
    margin-bottom: 48px;
}

.help-category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.help-category-card {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 20px;
    padding: 28px;
    transition: all 0.3s ease;
}

[data-theme="dark"] .help-category-card {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(99, 102, 241, 0.2);
}

.help-category-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(99, 102, 241, 0.12);
}

.help-category-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .help-category-header {
    border-bottom-color: rgba(99, 102, 241, 0.2);
}

.help-category-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.help-category-icon i {
    font-size: 1.4rem;
    color: white;
}

.help-category-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 4px;
}

[data-theme="dark"] .help-category-title {
    color: #f1f5f9;
}

.help-category-count {
    font-size: 0.85rem;
    color: #64748b;
}

[data-theme="dark"] .help-category-count {
    color: #94a3b8;
}

.help-category-articles {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.help-article-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(99, 102, 241, 0.05);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="dark"] .help-article-link {
    background: rgba(99, 102, 241, 0.1);
}

.help-article-link:hover {
    background: rgba(99, 102, 241, 0.12);
    transform: translateX(4px);
}

[data-theme="dark"] .help-article-link:hover {
    background: rgba(99, 102, 241, 0.2);
}

.help-article-link i {
    color: #6366f1;
    font-size: 0.9rem;
}

.help-article-link span {
    font-size: 0.95rem;
    font-weight: 500;
    color: #374151;
    flex: 1;
}

[data-theme="dark"] .help-article-link span {
    color: #e2e8f0;
}

.help-article-link .arrow {
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.help-article-link:hover .arrow {
    transform: translateX(4px);
    color: #6366f1;
}

/* Empty State */
.help-empty {
    text-align: center;
    padding: 80px 20px;
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    border-radius: 24px;
    border: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .help-empty {
    background: rgba(30, 41, 59, 0.6);
}

.help-empty-icon {
    width: 80px;
    height: 80px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
}

.help-empty-icon i {
    font-size: 2rem;
    color: #6366f1;
}

.help-empty h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 12px;
}

[data-theme="dark"] .help-empty h2 {
    color: #f1f5f9;
}

.help-empty p {
    color: #64748b;
    margin: 0;
}

/* Contact CTA */
.help-contact-cta {
    text-align: center;
    padding: 48px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 24px;
    margin-top: 48px;
}

[data-theme="dark"] .help-contact-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
}

.help-contact-cta h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 12px;
}

[data-theme="dark"] .help-contact-cta h3 {
    color: #f1f5f9;
}

.help-contact-cta p {
    color: #64748b;
    margin: 0 0 24px;
    font-size: 1.05rem;
}

[data-theme="dark"] .help-contact-cta p {
    color: #94a3b8;
}

.help-contact-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    text-decoration: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.help-contact-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.4);
}

/* Mobile Responsive */
@media (max-width: 640px) {
    .help-hero h1 {
        font-size: 1.75rem;
    }

    .help-search-form {
        flex-direction: column;
    }

    .help-search-btn {
        width: 100%;
    }

    .help-category-grid {
        grid-template-columns: 1fr;
    }

    .help-popular-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
                    <i class="fa-solid fa-magnifying-glass help-search-icon"></i>
                    <input
                        type="text"
                        name="q"
                        class="help-search-input"
                        placeholder="Search for answers..."
                        autocomplete="off"
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
