<?php
/**
 * Reusable Breadcrumb Component
 *
 * Usage:
 * $breadcrumbs = [
 *     ['label' => 'Home', 'url' => '/'],
 *     ['label' => 'Listings', 'url' => '/listings'],
 *     ['label' => 'Item Title', 'url' => null]  // Current page (no URL)
 * ];
 * include __DIR__ . '/partials/breadcrumb.php';
 */

if (empty($breadcrumbs) || !is_array($breadcrumbs)) return;
?>

<nav style="margin-bottom: 1rem; font-size: 0.875rem;">
    <?php foreach ($breadcrumbs as $index => $crumb): ?>
        <?php if ($index > 0): ?>
            <span style="color: #888; margin: 0 0.5rem;">/</span>
        <?php endif; ?>

        <?php if (!empty($crumb['url'])): ?>
            <a href="<?= htmlspecialchars($crumb['url']) ?>" style="color: var(--sk-link); text-decoration: none;">
                <?= htmlspecialchars($crumb['label']) ?>
            </a>
        <?php else: ?>
            <span style="color: #888;"><?= htmlspecialchars($crumb['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
