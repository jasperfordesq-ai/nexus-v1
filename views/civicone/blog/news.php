<?php
// CivicOne View: Blog Index
$hTitle = "Latest News";
$hSubtitle = "Updates from our community.";
$hType = "News";

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-blog.css">

<div class="civic-container">

    <?php if (empty($posts)): ?>
        <div class="civic-card news-simple-empty">
            <h3>No updates yet</h3>
            <p>Check back soon for the latest news.</p>
        </div>
    <?php else: ?>
        <div class="civic-grid">
            <?php foreach ($posts as $post): ?>
                <div class="civic-card news-simple-card">
                    <?php if ($post['featured_image']): ?>
                        <div class="news-simple-img-container">
                            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="news-simple-img">
                        </div>
                    <?php endif; ?>

                    <div class="news-simple-date">
                        <?= date('M j, Y', strtotime($post['created_at'])) ?>
                    </div>

                    <h3 class="news-simple-title">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>">
                            <?= htmlspecialchars($post['title']) ?>
                        </a>
                    </h3>

                    <p class="news-simple-excerpt">
                        <?= htmlspecialchars(substr($post['excerpt'] ?: strip_tags($post['content']), 0, 150)) ?>...
                    </p>

                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog/<?= $post['slug'] ?>"
                       aria-label="Read full article: <?= htmlspecialchars($post['title']) ?>"
                       class="news-simple-read-link">
                        Read: <?= htmlspecialchars(mb_strimwidth($post['title'], 0, 30, '...')) ?> &rarr;
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>