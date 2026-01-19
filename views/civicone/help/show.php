<?php
// CivicOne View: Help Article - WCAG 2.1 AA Compliant
// CSS extracted to civicone-help.css
$pageTitle = $article['title'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <nav class="civic-help-back" aria-label="Breadcrumb">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help" class="civic-help-back-link">
            <span aria-hidden="true">&larr;</span> Back to Help Center
        </a>
    </nav>

    <article class="civic-card">
        <header class="civic-help-article-header">
            <span class="civic-help-module-tag">
                <?= htmlspecialchars(str_replace('_', ' ', $article['module_tag'])) ?>
            </span>
            <h1 class="civic-help-article-heading"><?= htmlspecialchars($article['title']) ?></h1>
        </header>

        <div class="civic-help-article-content">
            <?= $article['content'] ?>
            <!-- Content is admin-authored trusted HTML -->
        </div>
    </article>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
