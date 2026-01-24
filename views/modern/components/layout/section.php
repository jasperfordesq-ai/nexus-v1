<?php

/**
 * Component: Section
 *
 * Content section with header, optional icon, and action buttons.
 *
 * @param string $title Section title (required)
 * @param string $icon FontAwesome icon name (without fa- prefix)
 * @param string $subtitle Optional subtitle text
 * @param array $actions Array of action buttons ['label' => '', 'href' => '', 'icon' => '']
 * @param string $content Section body content
 * @param string $class Additional CSS classes
 * @param bool $collapsible Whether section can collapse
 * @param bool $collapsed Start collapsed (only if collapsible)
 */

$title = $title ?? '';
$icon = $icon ?? '';
$subtitle = $subtitle ?? '';
$actions = $actions ?? [];
$content = $content ?? '';
$class = $class ?? '';
$collapsible = $collapsible ?? false;
$collapsed = $collapsed ?? false;

$sectionId = 'section-' . md5($title . microtime());
$cssClass = trim('section-card ' . $class);
?>

<div class="<?= e($cssClass) ?>"<?php if ($collapsible): ?> id="<?= e($sectionId) ?>"<?php endif; ?>>
    <div class="section-header">
        <div class="section-title-group">
            <?php if ($icon): ?>
                <i class="fa-solid fa-<?= e($icon) ?> section-icon"></i>
            <?php endif; ?>
            <div>
                <h2 class="section-title"><?= e($title) ?></h2>
                <?php if ($subtitle): ?>
                    <p class="section-subtitle"><?= e($subtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($actions || $collapsible): ?>
            <div class="section-actions">
                <?php foreach ($actions as $action): ?>
                    <a href="<?= e($action['href'] ?? '#') ?>" class="section-action-btn">
                        <?php if (!empty($action['icon'])): ?>
                            <i class="fa-solid fa-<?= e($action['icon']) ?>"></i>
                        <?php endif; ?>
                        <?= e($action['label'] ?? '') ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($collapsible): ?>
                    <button type="button" class="section-collapse-btn" onclick="toggleSection('<?= e($sectionId) ?>')">
                        <i class="fa-solid fa-chevron-<?= $collapsed ? 'down' : 'up' ?>"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="section-body<?php if ($collapsed): ?> component-hidden<?php endif; ?>">
        <?= $content ?>
    </div>
</div>
