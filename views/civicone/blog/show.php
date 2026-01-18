<?php
// CivicOne View: Blog Show
$hTitle = $post['title'];
$hSubtitle = "Published on " . date('M j, Y', strtotime($post['created_at']));
$hType = "News";

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">
    <div style="background:white; padding:40px; border:1px solid #E5E7EB; max-width:800px; margin:0 auto;">

        <div style="margin-bottom:20px; color:var(--civic-text-secondary, #4B5563); font-size:0.95rem; border-bottom:1px solid #eee; padding-bottom:15px;">
            By <strong><?= htmlspecialchars($post['author_name']) ?></strong>
        </div>

        <?php if ($post['featured_image']): ?>
            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="Featured" style="width:100%; height:auto; margin-bottom:30px; border:1px solid #eee;">
        <?php endif; ?>

        <div class="article-body" style="font-family:'Inter', sans-serif; line-height:1.7; color:#333; font-size:1.05rem;">
            <?= $post['content'] ?>
        </div>

        <div style="margin-top:40px; padding-top:20px; border-top:1px solid #eee;">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog" style="display:inline-block; text-decoration:none; font-weight:600; color:#555;">
                &larr; Back to News
            </a>

            <a href="mailto:?subject=<?= urlencode($post['title']) ?>&body=<?= urlencode($post['title'] . ' - ' . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>"
                style="float:right; text-decoration:none; font-weight:600; color:#555;">
                Share via Email ✉️
            </a>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>