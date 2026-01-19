<?php
// CivicOne View: Blog Show - WCAG 2.1 AA Compliant
// CSS extracted to civicone-blog.css
$hTitle = $post['title'];
$hSubtitle = "Published on " . date('M j, Y', strtotime($post['created_at']));
$hType = "News";

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">
    <article class="civic-article-wrapper">

        <div class="civic-article-meta">
            By <strong><?= htmlspecialchars($post['author_name']) ?></strong>
        </div>

        <?php if ($post['featured_image']): ?>
            <img src="<?= htmlspecialchars($post['featured_image']) ?>"
                 alt="Featured image for <?= htmlspecialchars($post['title']) ?>"
                 class="civic-article-image">
        <?php endif; ?>

        <div class="civic-article-body">
            <?= $post['content'] ?>
        </div>

        <footer class="civic-article-footer">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog" class="civic-article-back">
                &larr; Back to News
            </a>

            <a href="mailto:?subject=<?= urlencode($post['title']) ?>&body=<?= urlencode($post['title'] . ' - ' . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>"
               class="civic-article-share"
               aria-label="Share this article via email">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                Share via Email
            </a>
        </footer>

    </article>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
