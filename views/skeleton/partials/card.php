<?php
/**
 * Reusable Card Component
 *
 * Usage:
 * $title = 'Card Title';
 * $content = '<p>Card content here</p>';
 * include __DIR__ . '/partials/card.php';
 */

$title = $title ?? '';
$content = $content ?? '';
$footer = $footer ?? '';
?>

<div class="sk-card">
    <?php if ($title): ?>
        <div class="sk-card-title"><?= htmlspecialchars($title) ?></div>
    <?php endif; ?>

    <div class="sk-card-body">
        <?= $content ?>
    </div>

    <?php if ($footer): ?>
        <div class="sk-card-footer" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--sk-border);">
            <?= $footer ?>
        </div>
    <?php endif; ?>
</div>
