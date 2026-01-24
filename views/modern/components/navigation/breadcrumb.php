<?php

/**
 * Component: Breadcrumb
 *
 * Navigation breadcrumb trail.
 *
 * @param array $items Array of breadcrumb items ['label' => '', 'href' => '']
 * @param string $separator Separator character (default: '/')
 * @param string $class Additional CSS classes
 */

$items = $items ?? [];
$separator = $separator ?? '/';
$class = $class ?? '';

$cssClass = trim('breadcrumb ' . $class);
$lastIndex = count($items) - 1;
?>

<nav class="<?= e($cssClass) ?> component-breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list component-breadcrumb">
        <?php foreach ($items as $index => $item): ?>
            <li class="breadcrumb-item">
                <?php if ($index === $lastIndex): ?>
                    <span class="breadcrumb-current component-breadcrumb__current" aria-current="page">
                        <?= e($item['label']) ?>
                    </span>
                <?php else: ?>
                    <a href="<?= e($item['href'] ?? '#') ?>" class="breadcrumb-link component-breadcrumb__link">
                        <?= e($item['label']) ?>
                    </a>
                    <span class="breadcrumb-separator component-breadcrumb__separator">
                        <?= e($separator) ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
