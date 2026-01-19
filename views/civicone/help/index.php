<?php
// CivicOne View: Help Center - WCAG 2.1 AA Compliant
// CSS extracted to civicone-help.css
$pageTitle = 'Help Center';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-card civic-help-header">
        <h1 class="civic-help-title">Help Center</h1>
        <hr class="civic-help-divider" aria-hidden="true">
        <p class="civic-help-subtitle">
            Guides and documentation for the platform.
        </p>
    </div>

    <?php if (empty($groupedArticles)): ?>
        <div class="civic-card civic-help-empty">
            <p class="civic-help-empty-text">No help articles found.</p>
        </div>
    <?php else: ?>

        <?php foreach ($groupedArticles as $module => $articles): ?>
            <div class="civic-card civic-help-category">
                <h2 class="civic-help-category-title">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $module))) ?>
                </h2>

                <div class="civic-help-grid" role="list">
                    <?php foreach ($articles as $article): ?>
                        <article class="civic-help-article-card" role="listitem">
                            <h3 class="civic-help-article-title">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help/<?= $article['slug'] ?>">
                                    <?= htmlspecialchars($article['title']) ?>
                                </a>
                            </h3>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help/<?= $article['slug'] ?>"
                               class="civic-help-read-link"
                               aria-label="Read guide: <?= htmlspecialchars($article['title']) ?>">
                                Read Guide <span aria-hidden="true">&rarr;</span>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
