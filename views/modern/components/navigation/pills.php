<?php

/**
 * Component: Pills
 *
 * Pill-style navigation for filtering or category selection.
 *
 * @param array $items Array of pill items ['id' => '', 'label' => '', 'href' => '', 'icon' => '', 'count' => 0]
 * @param string $active ID of the active pill
 * @param string $class Additional CSS classes
 * @param bool $asLinks Render as links instead of buttons (default: false)
 */

$items = $items ?? [];
$active = $active ?? '';
$class = $class ?? '';
$asLinks = $asLinks ?? false;

$cssClass = trim('nav-pills ' . $class);
?>

<nav class="<?= e($cssClass) ?> component-pills">
    <?php foreach ($items as $item): ?>
        <?php
        $isActive = ($item['id'] ?? '') === $active;
        $pillClass = 'nav-pill' . ($isActive ? ' active' : '');
        ?>
        <?php if ($asLinks && !empty($item['href'])): ?>
            <a href="<?= e($item['href']) ?>" class="<?= e($pillClass) ?>">
                <?php if (!empty($item['icon'])): ?>
                    <i class="fa-solid fa-<?= e($item['icon']) ?>"></i>
                <?php endif; ?>
                <span><?= e($item['label'] ?? '') ?></span>
                <?php if (isset($item['count'])): ?>
                    <span class="pill-count">(<?= (int)$item['count'] ?>)</span>
                <?php endif; ?>
            </a>
        <?php else: ?>
            <button
                type="button"
                class="<?= e($pillClass) ?>"
                data-pill="<?= e($item['id'] ?? '') ?>"
                <?php if ($isActive): ?>aria-pressed="true"<?php endif; ?>
            >
                <?php if (!empty($item['icon'])): ?>
                    <i class="fa-solid fa-<?= e($item['icon']) ?>"></i>
                <?php endif; ?>
                <span><?= e($item['label'] ?? '') ?></span>
                <?php if (isset($item['count'])): ?>
                    <span class="pill-count">(<?= (int)$item['count'] ?>)</span>
                <?php endif; ?>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
