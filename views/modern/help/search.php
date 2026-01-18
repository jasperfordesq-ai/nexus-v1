<?php
/**
 * Modern Help Center Search Results
 * Search results with query highlighting
 */
$hero_title = "Search Help";
$hero_subtitle = "Find answers quickly";
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

// Helper function to create excerpt with highlighted search terms
function highlightExcerpt($content, $query, $maxLength = 200) {
    // Strip HTML tags
    $text = strip_tags($content);

    // Find position of query
    $pos = stripos($text, $query);
    if ($pos !== false) {
        // Get surrounding context
        $start = max(0, $pos - 50);
        $excerpt = substr($text, $start, $maxLength);
        if ($start > 0) $excerpt = '...' . $excerpt;
        if (strlen($text) > $start + $maxLength) $excerpt .= '...';
    } else {
        // Just get beginning
        $excerpt = substr($text, 0, $maxLength);
        if (strlen($text) > $maxLength) $excerpt .= '...';
    }

    // Highlight query terms
    if (!empty($query)) {
        $excerpt = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $excerpt);
    }

    return $excerpt;
}
?>

<style>
/* ============================================
   HELP SEARCH - MODERN GLASSMORPHIC DESIGN
   ============================================ */

.help-search-page {
    min-height: 100vh;
    padding: 160px 20px 80px;
    position: relative;
}

@media (max-width: 900px) {
    .help-search-page {
        padding: 100px 16px 120px;
    }
}

/* Ambient Background */
.help-search-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 70%, rgba(16, 185, 129, 0.08) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
}

[data-theme="dark"] .help-search-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 70%, rgba(16, 185, 129, 0.12) 0%, transparent 50%);
}

.help-search-container {
    max-width: 900px;
    margin: 0 auto;
}

/* Back Link */
.help-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #6366f1;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 24px;
    transition: all 0.2s ease;
}

.help-back-link:hover {
    transform: translateX(-4px);
}

/* Search Header */
.help-search-header {
    text-align: center;
    margin-bottom: 40px;
}

.help-search-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: #1f2937;
    margin: 0 0 8px;
}

[data-theme="dark"] .help-search-header h1 {
    color: #f1f5f9;
}

.help-search-query {
    color: #6366f1;
    font-weight: 600;
}

.help-search-count {
    color: #64748b;
    font-size: 1rem;
}

[data-theme="dark"] .help-search-count {
    color: #94a3b8;
}

/* Search Form */
.help-search-form-wrapper {
    max-width: 600px;
    margin: 24px auto 0;
}

.help-search-form {
    position: relative;
    display: flex;
    gap: 12px;
}

.help-search-input-wrapper {
    flex: 1;
    position: relative;
}

.help-search-page .help-search-input {
    width: 100% !important;
    padding: 14px 20px 14px 48px !important;
    font-size: 1rem !important;
    border: 2px solid rgba(99, 102, 241, 0.2) !important;
    border-radius: 14px !important;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    outline: none;
    transition: all 0.3s ease;
}

[data-theme="dark"] .help-search-page .help-search-input {
    background: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(99, 102, 241, 0.3) !important;
    color: #f1f5f9 !important;
}

.help-search-page .help-search-input:focus {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;
}

.help-search-icon {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1rem;
    z-index: 2;
}

.help-search-btn {
    padding: 14px 24px !important;
    background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
    color: white !important;
    border: none !important;
    border-radius: 14px !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    height: auto !important;
    line-height: normal !important;
}

.help-search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

/* Results */
.search-results {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.search-result-card {
    display: block;
    padding: 24px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.3s ease;
}

[data-theme="dark"] .search-result-card {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(99, 102, 241, 0.2);
}

.search-result-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
}

.search-result-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 12px;
}

.search-result-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.search-result-icon i {
    font-size: 1.1rem;
    color: white;
}

.search-result-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 4px;
    line-height: 1.4;
}

[data-theme="dark"] .search-result-title {
    color: #f1f5f9;
}

.search-result-category {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

[data-theme="dark"] .search-result-category {
    color: #94a3b8;
}

.search-result-excerpt {
    font-size: 0.95rem;
    color: #64748b;
    line-height: 1.6;
    margin: 0;
}

[data-theme="dark"] .search-result-excerpt {
    color: #94a3b8;
}

.search-result-excerpt mark {
    background: rgba(99, 102, 241, 0.2);
    color: #6366f1;
    padding: 1px 4px;
    border-radius: 4px;
}

[data-theme="dark"] .search-result-excerpt mark {
    background: rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

/* No Results */
.no-results {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    border: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .no-results {
    background: rgba(30, 41, 59, 0.6);
}

.no-results-icon {
    width: 72px;
    height: 72px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.no-results-icon i {
    font-size: 1.75rem;
    color: #6366f1;
}

.no-results h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 8px;
}

[data-theme="dark"] .no-results h2 {
    color: #f1f5f9;
}

.no-results p {
    color: #64748b;
    margin: 0 0 24px;
}

[data-theme="dark"] .no-results p {
    color: #94a3b8;
}

.no-results-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.no-results-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
}

/* Mobile */
@media (max-width: 640px) {
    .help-search-header h1 {
        font-size: 1.5rem;
    }

    .help-search-form {
        flex-direction: column;
    }

    .help-search-btn {
        width: 100%;
    }

    .search-result-header {
        flex-direction: column;
        gap: 12px;
    }
}
</style>

<div class="help-search-page">
    <div class="help-search-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/help" class="help-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Help Center
        </a>

        <!-- Search Header -->
        <div class="help-search-header">
            <?php if (!empty($query)): ?>
                <h1>Results for <span class="help-search-query">"<?= htmlspecialchars($query) ?>"</span></h1>
                <p class="help-search-count"><?= count($results) ?> article<?= count($results) !== 1 ? 's' : '' ?> found</p>
            <?php else: ?>
                <h1>Search Help Center</h1>
                <p class="help-search-count">Enter a search term to find articles</p>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="help-search-form-wrapper">
                <form action="<?= $basePath ?>/help/search" method="GET" class="help-search-form">
                    <div class="help-search-input-wrapper">
                        <i class="fa-solid fa-magnifying-glass help-search-icon"></i>
                        <input
                            type="text"
                            name="q"
                            class="help-search-input"
                            placeholder="Search for help..."
                            value="<?= htmlspecialchars($query ?? '') ?>"
                            autocomplete="off"
                            autofocus
                        >
                    </div>
                    <button type="submit" class="help-search-btn">Search</button>
                </form>
            </div>
        </div>

        <?php if (!empty($query)): ?>
            <?php if (empty($results)): ?>
                <!-- No Results -->
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <h2>No articles found</h2>
                    <p>Try different keywords or browse our help categories</p>
                    <a href="<?= $basePath ?>/help" class="no-results-link">
                        <i class="fa-solid fa-folder-open"></i>
                        Browse All Articles
                    </a>
                </div>
            <?php else: ?>
                <!-- Results List -->
                <div class="search-results">
                    <?php foreach ($results as $article):
                        $config = $moduleConfig[$article['module_tag']] ?? ['name' => ucfirst($article['module_tag']), 'icon' => 'fa-file-lines', 'color' => '#6366f1'];
                        $excerpt = highlightExcerpt($article['content'], $query);
                    ?>
                    <a href="<?= $basePath ?>/help/<?= htmlspecialchars($article['slug']) ?>" class="search-result-card">
                        <div class="search-result-header">
                            <div class="search-result-icon" style="background: <?= $config['color'] ?>;">
                                <i class="fa-solid <?= $config['icon'] ?>"></i>
                            </div>
                            <div>
                                <h2 class="search-result-title"><?= htmlspecialchars($article['title']) ?></h2>
                                <span class="search-result-category"><?= $config['name'] ?></span>
                            </div>
                        </div>
                        <p class="search-result-excerpt"><?= $excerpt ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
