<?php
// FORCE MODERN LAYOUT - Prevent layout switching on news page
$currentLayout = \Nexus\Services\LayoutHelper::get();

// Modern Layout (Default)
if ($currentLayout === 'modern') {
    if (file_exists(__DIR__ . '/../modern/blog/index.php')) {
        require __DIR__ . '/../modern/blog/index.php';
        return;
    }
}

// Nexus Social Bridge (only if explicitly using Nexus Social)


// Legacy View Content (fallback - rarely used)
$pageTitle = 'Latest News';
$hideHero = true; // Disable default hero
require_once __DIR__ . '/../layouts/modern/header.php';
?>
<!-- DEBUG MARKER: BLOG INDEX LOADED (Legacy Fallback) -->

<div class="blog-section-wrapper">

    <!-- Hero Section -->
    <div class="glass-hero-text">
        <div>
            <h1>Latest News</h1>
            <p>Updates, stories, and announcements from the holographic future.</p>
        </div>
    </div>

    <?php if (empty($posts)): ?>
        <div class="glass-card">
            <h3>No updates yet</h3>
            <p>Check back soon for the latest news.</p>
        </div>
    <?php else: ?>
        <main class="blog-grid-container">
            <?php foreach ($posts as $post): ?>
                <article class="glass-card">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>" style="display:block; text-decoration:none;">
                        <?php if ($post['featured_image']): ?>
                            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="glass-card-img">
                        <?php else: ?>
                            <div class="glass-card-img-placeholder">
                                📰
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="glass-card-body">
                        <div class="glass-meta">
                            <?= date('M j, Y', strtotime($post['created_at'])) ?>
                        </div>

                        <h3 class="glass-title">
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </h3>

                        <p class="glass-desc">
                            <?= htmlspecialchars(substr($post['excerpt'] ?: strip_tags($post['content']), 0, 100)) ?>...
                        </p>

                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>" class="glass-btn">
                            READ ARTICLE &rarr;
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </main>
    <?php endif; ?>

    <!-- Pagination -->
    <div style="text-align:center; margin: 40px 0;">
        <div style="margin-bottom: 15px; color:rgba(255,255,255,0.7);">
            Page <?= $page ?> of <?= $totalPages ?>
        </div>

        <?php if ($page > 1): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog?page=<?= $page - 1 ?>" class="glass-btn" style="margin-right:10px;">
                &larr; Prev
            </a>
        <?php else: ?>
            <span class="glass-btn" style="opacity:0.5; cursor:not-allowed; margin-right:10px;">&larr; Prev</span>
        <?php endif; ?>

        <!-- Numbered Links -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog?page=<?= $i ?>"
                class="glass-btn <?= $i == $page ? 'active' : '' ?>"
                style="<?= $i == $page ? 'background:rgba(255,255,255,0.3); border-color:#fff;' : '' ?> padding: 8px 12px; margin: 0 2px;">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog?page=<?= $page + 1 ?>" class="glass-btn" style="margin-left:10px;">
                Next &rarr;
            </a>
        <?php else: ?>
            <span class="glass-btn" style="opacity:0.5; cursor:not-allowed; margin-left:10px;">Next &rarr;</span>
        <?php endif; ?>
    </div>

</div>

<?php
// Use appropriate footer for current layout
require_once __DIR__ . '/../layouts/modern/footer.php';
?>