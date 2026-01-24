<?php

/**
 * Component: Hero
 *
 * Welcome hero banner with title, subtitle, badge, and action buttons.
 * Replaces 10+ duplicate implementations across the codebase.
 *
 * @param string $title Main heading (required)
 * @param string $subtitle Subheading text
 * @param string $icon FontAwesome icon name (without fa- prefix)
 * @param array $badge Feature badge ['icon' => '', 'text' => '', 'gradient' => '']
 * @param array $buttons Array of button configs ['label' => '', 'href' => '', 'variant' => '', 'icon' => '']
 * @param string $class Additional CSS classes
 */

$title = $title ?? '';
$subtitle = $subtitle ?? '';
$icon = $icon ?? '';
$badge = $badge ?? [];
$buttons = $buttons ?? [];
$class = $class ?? '';

$cssClass = trim('nexus-welcome-hero ' . $class);

// Default badge gradient if not specified
$badgeGradient = $badge['gradient'] ?? 'linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1))';
$badgeBorder = $badge['border'] ?? 'rgba(99, 102, 241, 0.2)';
?>

<div class="<?= e($cssClass) ?>">
    <h1 class="nexus-welcome-title">
        <?php if ($icon): ?>
            <i class="fa-solid fa-<?= e($icon) ?>"></i>
        <?php endif; ?>
        <?= e($title) ?>
    </h1>

    <?php if (!empty($badge['text'])): ?>
        <div class="nexus-hero-badge">
            <?php if (!empty($badge['icon'])): ?>
                <i class="fa-solid fa-<?= e($badge['icon']) ?>"></i>
            <?php endif; ?>
            <span><?= e($badge['text']) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($subtitle): ?>
        <p class="nexus-welcome-subtitle"><?= e($subtitle) ?></p>
    <?php endif; ?>

    <?php if ($buttons): ?>
        <div class="nexus-smart-buttons">
            <?php foreach ($buttons as $button): ?>
                <?php
                $variant = $button['variant'] ?? 'primary';
                $btnClass = 'nexus-smart-btn';
                if ($variant === 'primary') {
                    $btnClass .= ' nexus-smart-btn-primary';
                } elseif ($variant === 'secondary') {
                    $btnClass .= ' nexus-smart-btn-secondary';
                } elseif ($variant === 'outline') {
                    $btnClass .= ' nexus-smart-btn-outline';
                } elseif ($variant === 'ghost') {
                    $btnClass .= ' nexus-smart-btn-ghost';
                }
                ?>
                <a href="<?= e($button['href'] ?? '#') ?>" class="<?= e($btnClass) ?>">
                    <?php if (!empty($button['icon'])): ?>
                        <i class="fa-solid fa-<?= e($button['icon']) ?>"></i>
                    <?php endif; ?>
                    <?= e($button['label'] ?? '') ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
