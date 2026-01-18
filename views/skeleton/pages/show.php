<?php
/**
 * Skeleton Layout - Generic Page Template
 * Used for custom pages created by admins
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$page = $page ?? null;

if (!$page) {
    echo '<div class="sk-alert sk-alert-error">Page not found</div>';
    include __DIR__ . '/../../layouts/skeleton/footer.php';
    exit;
}
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<!-- Breadcrumb -->
<div style="margin-bottom: 1rem;">
    <a href="<?= $basePath ?>/" style="color: var(--sk-link);">Home</a>
    <span style="color: #888;"> / </span>
    <span style="color: #888;"><?= htmlspecialchars($page['title'] ?? 'Page') ?></span>
</div>

<article class="sk-card" style="max-width: 900px; margin: 0 auto;">
    <!-- Page Header -->
    <header style="margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">
            <?= htmlspecialchars($page['title'] ?? 'Untitled Page') ?>
        </h1>

        <?php if (!empty($page['subtitle'])): ?>
            <p style="font-size: 1.25rem; color: #888; margin-bottom: 1rem;">
                <?= htmlspecialchars($page['subtitle']) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($page['updated_at']) || !empty($page['author'])): ?>
            <div style="color: #888; font-size: 0.875rem;">
                <?php if (!empty($page['author'])): ?>
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($page['author']) ?></span>
                <?php endif; ?>
                <?php if (!empty($page['updated_at'])): ?>
                    <span style="margin-left: 1rem;">
                        <i class="far fa-clock"></i>
                        <?php
                        $date = new DateTime($page['updated_at']);
                        echo 'Updated ' . $date->format('F j, Y');
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <!-- Page Banner -->
    <?php if (!empty($page['banner_image'])): ?>
        <img src="<?= htmlspecialchars($page['banner_image']) ?>" alt="Banner"
             style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 2rem;">
    <?php endif; ?>

    <!-- Page Content -->
    <div style="line-height: 1.8; color: var(--sk-text);">
        <?php if (!empty($page['content'])): ?>
            <?php
            // Render HTML content safely
            // Note: In production, you should sanitize this content
            echo $page['content'];
            ?>
        <?php else: ?>
            <div class="sk-empty-state">
                <p>This page has no content yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Page Footer/Meta -->
    <?php if (!empty($page['tags'])): ?>
        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--sk-border);">
            <strong style="color: #888; font-size: 0.875rem;">Tags:</strong>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                <?php
                $tags = explode(',', $page['tags']);
                foreach ($tags as $tag):
                    $tag = trim($tag);
                    if ($tag):
                ?>
                    <span class="sk-badge" style="background: #e0e0e0; color: #333;">
                        <?= htmlspecialchars($tag) ?>
                    </span>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>
    <?php endif; ?>
</article>

<!-- Related Pages or CTA -->
<?php if (!empty($relatedPages) && is_array($relatedPages)): ?>
    <section style="margin-top: 3rem;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; text-align: center;">Related Pages</h2>
        <div class="sk-grid">
            <?php foreach ($relatedPages as $related): ?>
                <div class="sk-card">
                    <div class="sk-card-title">
                        <a href="<?= $basePath ?>/<?= htmlspecialchars($related['slug'] ?? '') ?>"
                           style="color: var(--sk-text); text-decoration: none;">
                            <?= htmlspecialchars($related['title'] ?? 'Page') ?>
                        </a>
                    </div>
                    <?php if (!empty($related['excerpt'])): ?>
                        <p style="color: #666; font-size: 0.875rem;">
                            <?= htmlspecialchars($related['excerpt']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
