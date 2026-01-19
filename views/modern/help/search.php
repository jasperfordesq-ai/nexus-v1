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
