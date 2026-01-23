<?php
// CivicOne View: Blog Index
$hTitle = "Latest News";
$hSubtitle = "Updates from our community.";
$hType = "News";

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <?php if (empty($posts)): ?>
        <div class="civic-card" style="text-align:center; padding: 40px;">
            <h3>No updates yet</h3>
            <p>Check back soon for the latest news.</p>
        </div>
    <?php else: ?>
        <div class="civic-grid">
            <?php foreach ($posts as $post): ?>
                <div class="civic-card" style="border-top: 4px solid var(--civic-brand-primary, #0B0C0C);">
                    <?php if ($post['featured_image']): ?>
                        <div style="margin-bottom:20px; border-radius:4px; overflow:hidden;">
                            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" style="width:100%; height:auto; display:block;">
                        </div>
                    <?php endif; ?>

                    <div style="color:var(--civic-text-secondary, #4B5563); font-size:0.9rem; margin-bottom:5px;">
                        <?= date('M j, Y', strtotime($post['created_at'])) ?>
                    </div>

                    <h3 style="margin-top:0; margin-bottom:10px;">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>" style="text-decoration:none; color:inherit;">
                            <?= htmlspecialchars($post['title']) ?>
                        </a>
                    </h3>

                    <p class="civic-text-dark" style="margin-bottom:20px; line-height:1.5;">
                        <?= htmlspecialchars(substr($post['excerpt'] ?: strip_tags($post['content']), 0, 150)) ?>...
                    </p>

                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>"
                       aria-label="Read full article: <?= htmlspecialchars($post['title']) ?>"
                       style="font-weight:700; text-decoration:underline; color:var(--civic-brand-primary, #0B0C0C);">
                        Read: <?= htmlspecialchars(mb_strimwidth($post['title'], 0, 30, '...')) ?> &rarr;
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>