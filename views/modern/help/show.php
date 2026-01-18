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

<style>
/* ============================================
   HELP ARTICLE - MODERN GLASSMORPHIC DESIGN
   ============================================ */

.help-article-page {
    min-height: 100vh;
    padding: 160px 20px 80px;
    position: relative;
}

@media (max-width: 900px) {
    .help-article-page {
        padding: 100px 16px 120px;
    }
}

/* Ambient Background */
.help-article-page::before {
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

[data-theme="dark"] .help-article-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 70%, rgba(16, 185, 129, 0.12) 0%, transparent 50%);
}

.help-article-container {
    max-width: 900px;
    margin: 0 auto;
}

/* Breadcrumbs */
.help-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
    font-size: 0.9rem;
    flex-wrap: wrap;
}

.help-breadcrumbs a {
    color: #6366f1;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.help-breadcrumbs a:hover {
    color: #4f46e5;
}

.help-breadcrumbs span {
    color: #94a3b8;
}

.help-breadcrumbs .separator {
    color: #cbd5e1;
}

[data-theme="dark"] .help-breadcrumbs .separator {
    color: #475569;
}

/* Article Card */
.help-article-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 32px;
}

[data-theme="dark"] .help-article-card {
    background: rgba(30, 41, 59, 0.85);
    border-color: rgba(99, 102, 241, 0.2);
}

@media (max-width: 640px) {
    .help-article-card {
        padding: 24px 20px;
        border-radius: 20px;
    }
}

/* Article Header */
.help-article-header {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .help-article-header {
    border-bottom-color: rgba(99, 102, 241, 0.2);
}

.help-article-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    margin-bottom: 16px;
}

.help-article-badge i {
    font-size: 0.9rem;
}

.help-article-title {
    font-size: 2rem;
    font-weight: 800;
    color: #1f2937;
    margin: 0 0 12px;
    line-height: 1.3;
}

[data-theme="dark"] .help-article-title {
    color: #f1f5f9;
}

@media (max-width: 640px) {
    .help-article-title {
        font-size: 1.5rem;
    }
}

.help-article-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    color: #64748b;
    font-size: 0.9rem;
}

[data-theme="dark"] .help-article-meta {
    color: #94a3b8;
}

.help-article-meta i {
    margin-right: 6px;
    color: #94a3b8;
}

/* Article Content */
.help-article-content {
    font-size: 1.05rem;
    line-height: 1.8;
    color: #374151;
}

[data-theme="dark"] .help-article-content {
    color: #e2e8f0;
}

.help-article-content h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 32px 0 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] .help-article-content h2 {
    color: #f1f5f9;
    border-bottom-color: rgba(99, 102, 241, 0.3);
}

.help-article-content h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #374151;
    margin: 28px 0 12px;
}

[data-theme="dark"] .help-article-content h3 {
    color: #e2e8f0;
}

.help-article-content h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #4b5563;
    margin: 24px 0 10px;
}

[data-theme="dark"] .help-article-content h4 {
    color: #cbd5e1;
}

.help-article-content p {
    margin: 16px 0;
}

.help-article-content ul,
.help-article-content ol {
    margin: 16px 0;
    padding-left: 24px;
}

.help-article-content li {
    margin: 10px 0;
}

.help-article-content strong {
    color: #1f2937;
    font-weight: 600;
}

[data-theme="dark"] .help-article-content strong {
    color: #f1f5f9;
}

.help-article-content a {
    color: #6366f1;
    text-decoration: none;
    font-weight: 500;
    border-bottom: 1px solid rgba(99, 102, 241, 0.3);
    transition: all 0.2s;
}

.help-article-content a:hover {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
}

.help-article-content code {
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 8px;
    border-radius: 6px;
    font-family: 'SF Mono', Monaco, monospace;
    font-size: 0.9em;
    color: #6366f1;
}

[data-theme="dark"] .help-article-content code {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.help-article-content blockquote {
    margin: 24px 0;
    padding: 20px 24px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08));
    border-left: 4px solid #6366f1;
    border-radius: 0 12px 12px 0;
    font-style: italic;
    color: #4b5563;
}

[data-theme="dark"] .help-article-content blockquote {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
    color: #cbd5e1;
}

.help-article-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 24px 0;
    font-size: 0.95rem;
}

.help-article-content th,
.help-article-content td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .help-article-content th,
[data-theme="dark"] .help-article-content td {
    border-bottom-color: rgba(99, 102, 241, 0.2);
}

.help-article-content th {
    background: rgba(99, 102, 241, 0.05);
    font-weight: 600;
    color: #1f2937;
}

[data-theme="dark"] .help-article-content th {
    background: rgba(99, 102, 241, 0.1);
    color: #f1f5f9;
}

/* Feedback Section */
.help-feedback {
    margin-top: 40px;
    padding-top: 32px;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
    text-align: center;
}

[data-theme="dark"] .help-feedback {
    border-top-color: rgba(99, 102, 241, 0.2);
}

.help-feedback-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 16px;
}

[data-theme="dark"] .help-feedback-title {
    color: #e2e8f0;
}

.help-feedback-buttons {
    display: flex;
    justify-content: center;
    gap: 16px;
}

.help-feedback-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    background: transparent;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.help-feedback-btn.yes {
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.3);
}

.help-feedback-btn.yes:hover {
    background: rgba(16, 185, 129, 0.1);
    border-color: #10b981;
}

.help-feedback-btn.no {
    color: #f43f5e;
    border-color: rgba(244, 63, 94, 0.3);
}

.help-feedback-btn.no:hover {
    background: rgba(244, 63, 94, 0.1);
    border-color: #f43f5e;
}

.help-feedback-btn.active {
    transform: scale(0.95);
}

.help-feedback-btn.yes.selected {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.help-feedback-btn.no.selected {
    background: #f43f5e;
    color: white;
    border-color: #f43f5e;
}

.help-feedback-thanks {
    display: none;
    color: #10b981;
    font-weight: 600;
    margin-top: 16px;
}

.help-feedback-thanks.show {
    display: block;
}

/* Related Articles */
.help-related {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 20px;
    padding: 28px;
}

[data-theme="dark"] .help-related {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(99, 102, 241, 0.2);
}

.help-related-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .help-related-title {
    color: #f1f5f9;
}

.help-related-title i {
    color: #6366f1;
}

.help-related-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.help-related-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: rgba(99, 102, 241, 0.05);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="dark"] .help-related-link {
    background: rgba(99, 102, 241, 0.1);
}

.help-related-link:hover {
    background: rgba(99, 102, 241, 0.12);
    transform: translateX(4px);
}

[data-theme="dark"] .help-related-link:hover {
    background: rgba(99, 102, 241, 0.2);
}

.help-related-link i {
    color: #6366f1;
    font-size: 0.9rem;
}

.help-related-link span {
    font-size: 0.95rem;
    font-weight: 500;
    color: #374151;
    flex: 1;
}

[data-theme="dark"] .help-related-link span {
    color: #e2e8f0;
}

.help-related-link .arrow {
    color: #94a3b8;
    transition: transform 0.2s ease;
}

.help-related-link:hover .arrow {
    transform: translateX(4px);
    color: #6366f1;
}

/* Back Button */
.help-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 32px;
    padding: 12px 20px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    color: #6366f1;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.help-back-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateX(-4px);
}

[data-theme="dark"] .help-back-btn {
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
}
</style>

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
