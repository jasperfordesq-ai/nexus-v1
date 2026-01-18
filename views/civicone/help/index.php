<?php
// CivicOne View: Help Center
$pageTitle = 'Help Center';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="text-transform: uppercase; margin-bottom: 15px; font-size: 2.5rem; color: var(--skin-primary);">Help Center</h1>
        <div style="width: 80px; height: 4px; background: var(--skin-primary); margin: 0 auto 20px;"></div>
        <p style="font-size: 1.3rem; max-width: 800px; margin: 0 auto; color: #555; line-height: 1.6;">
            Guides and documentation for the platform.
        </p>
    </div>

    <?php if (empty($groupedArticles)): ?>
        <div class="civic-card" style="text-align: center; padding: 40px;">
            <p style="color: #555; font-size: 1.1rem;">No help articles found.</p>
        </div>
    <?php else: ?>

        <?php foreach ($groupedArticles as $module => $articles): ?>
            <div class="civic-card" style="margin-bottom: 30px;">
                <h2 style="color: var(--skin-primary); margin-top: 0; text-transform: capitalize; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $module))) ?>
                </h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($articles as $article): ?>
                        <div style="background: #f9f9f9; padding: 20px; border-radius: 6px;">
                            <h3 style="margin-top: 0; font-size: 1.1rem; margin-bottom: 10px;">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help/<?= $article['slug'] ?>" style="color: #333; text-decoration: none;">
                                    <?= htmlspecialchars($article['title']) ?>
                                </a>
                            </h3>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/help/<?= $article['slug'] ?>" style="color: var(--skin-primary); font-size: 0.9rem; font-weight: bold;">Read Guide &rarr;</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>