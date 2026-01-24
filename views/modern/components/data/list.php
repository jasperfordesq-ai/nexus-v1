<?php

/**
 * Component: List
 *
 * Generic list component for rendering items.
 *
 * @param array $items Array of item data
 * @param string $itemTemplate Path to item template (relative to components/)
 * @param string $itemKey Key name to use when passing item to template (default: 'item')
 * @param string $class Additional CSS classes
 * @param string $emptyIcon Empty state icon
 * @param string $emptyTitle Empty state title
 * @param string $emptyMessage Empty state message
 * @param bool $divided Show dividers between items (default: true)
 */

$items = $items ?? [];
$itemTemplate = $itemTemplate ?? '';
$itemKey = $itemKey ?? 'item';
$class = $class ?? '';
$emptyIcon = $emptyIcon ?? '📭';
$emptyTitle = $emptyTitle ?? 'No items';
$emptyMessage = $emptyMessage ?? '';
$divided = $divided ?? true;

$cssClass = trim(implode(' ', array_filter([
    'component-list',
    $divided ? 'component-list--divided' : '',
    $class
])));
?>

<?php if (empty($items)): ?>
    <div class="glass-empty-state">
        <div class="empty-icon"><?= $emptyIcon ?></div>
        <h3 class="empty-title"><?= e($emptyTitle) ?></h3>
        <?php if ($emptyMessage): ?>
            <p class="empty-message"><?= e($emptyMessage) ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <ul class="<?= e($cssClass) ?>">
        <?php foreach ($items as $index => $item): ?>
            <li class="component-list__item">
                <?php if ($itemTemplate): ?>
                    <?php
                    // Pass item to template
                    $$itemKey = $item;
                    $itemIndex = $index;
                    include COMPONENTS_PATH . '/' . $itemTemplate . '.php';
                    ?>
                <?php else: ?>
                    <?php
                    // Default rendering
                    if (is_array($item)):
                    ?>
                        <div class="component-list__item-content">
                            <?php if (isset($item['icon']) || isset($item['image'])): ?>
                                <div class="component-list__item-media">
                                    <?php if (isset($item['image'])): ?>
                                        <?= webp_avatar($item['image'], $item['title'] ?? '', 40) ?>
                                    <?php elseif (isset($item['icon'])): ?>
                                        <i class="fa-solid fa-<?= e($item['icon']) ?>"></i>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="component-list__item-body">
                                <?php if (isset($item['title'])): ?>
                                    <div class="component-list__item-title"><?= e($item['title']) ?></div>
                                <?php endif; ?>
                                <?php if (isset($item['subtitle'])): ?>
                                    <div class="component-list__item-subtitle"><?= e($item['subtitle']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($item['action'])): ?>
                                <div class="component-list__item-action">
                                    <?= $item['action'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="component-list__item-text"><?= e($item) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
