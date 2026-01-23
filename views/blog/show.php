<?php
// Nexus Social Bridge


// Fallback to Modern View if not Social
if (file_exists(__DIR__ . '/../modern/blog/show.php')) {
    require __DIR__ . '/../modern/blog/show.php';
    return;
}

// Legacy View Content (fallback - rarely used)
$pageTitle = $post['title'];
$hideHero = true; // Disable default hero

?>

<div class="blog-section-wrapper">

    <div style="max-width: 900px; margin: 0 auto; text-align:center; margin-bottom:40px; position:relative; z-index:10;">
        <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 20px; color:white; text-shadow: 0 2px 10px rgba(0,0,0,0.2);"><?= htmlspecialchars($post['title']) ?></h1>
        <div style="display:flex; justify-content:center; gap:15px; font-weight:600; opacity:0.9; color:rgba(255,255,255,0.9);">
            <span>📅 <?= date('M j, Y', strtotime($post['created_at'])) ?></span>
            <span>✍️ <?= htmlspecialchars($post['author_name']) ?></span>
        </div>
    </div>

    <div class="glass-article-wrapper">

        <?php if ($post['featured_image']): ?>
            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="Featured" style="width:100%; border-radius:12px; margin-bottom:30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <?php endif; ?>

        <div class="article-body">
            <?= $post['content'] ?>
        </div>

        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.2); margin:40px 0;">

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/blog" class="glass-btn-back">&larr; Back to News</a>

            <a href="mailto:?subject=<?= urlencode($post['title']) ?>&body=<?= urlencode($post['title'] . ' - ' . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>"
                style="color:rgba(255,255,255,0.8); text-decoration:none; font-weight:600; display:flex; align-items:center;">
                Share <span style="margin-left:8px; font-size:1.2rem;">✉️</span>
            </a>
        </div>
    </div>

</div>

<?php  ?>