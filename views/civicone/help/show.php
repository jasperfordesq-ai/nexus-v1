<?php
// CivicOne View: Help Article
$pageTitle = $article['title'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="margin-bottom: 20px;">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help" style="color: #555; text-decoration: none;">&larr; Back to Help Center</a>
    </div>

    <div class="civic-card">
        <div style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 30px;">
            <span style="background: var(--skin-primary); color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <?= htmlspecialchars(str_replace('_', ' ', $article['module_tag'])) ?>
            </span>
            <h1 style="color: var(--skin-primary); margin-top: 15px; margin-bottom: 0;"><?= htmlspecialchars($article['title']) ?></h1>
        </div>

        <div style="font-size: 1.1rem; line-height: 1.7; color: #333;">
            <?= $article['content'] ?>
            <!-- Assuming content is trusted HTML from admin; typically you'd want some sanitation if user-gen, 
                 but HelpArticles are admin-authored. -->
        </div>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>