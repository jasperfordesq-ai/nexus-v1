<?php

/**
 * Component: Accordion
 *
 * Collapsible accordion sections.
 * Used on: FAQ pages, settings, badge showcases, form sections
 *
 * @param array $items Array of accordion items: ['id' => '', 'title' => '', 'content' => '', 'icon' => '', 'expanded' => false]
 * @param bool $allowMultiple Allow multiple sections open (default: false)
 * @param string $class Additional CSS classes
 * @param string $id Accordion container ID
 * @param string $variant Style variant: 'default', 'bordered', 'separated' (default: 'default')
 */

$items = $items ?? [];
$allowMultiple = $allowMultiple ?? false;
$class = $class ?? '';
$id = $id ?? 'accordion-' . md5(microtime());
$variant = $variant ?? 'default';

$variantClasses = [
    'default' => 'component-accordion--default',
    'bordered' => 'component-accordion--bordered',
    'separated' => 'component-accordion--separated',
];
$variantClass = $variantClasses[$variant] ?? $variantClasses['default'];

$cssClass = trim('component-accordion ' . $variantClass . ' ' . $class);
?>

<div class="<?= e($cssClass) ?>" id="<?= e($id) ?>" data-allow-multiple="<?= $allowMultiple ? 'true' : 'false' ?>">
    <?php foreach ($items as $index => $item): ?>
        <?php
        $itemId = $item['id'] ?? $id . '-item-' . $index;
        $itemTitle = $item['title'] ?? '';
        $itemContent = $item['content'] ?? '';
        $itemIcon = $item['icon'] ?? '';
        $isExpanded = $item['expanded'] ?? false;

        $itemClass = 'component-accordion__item';
        if ($variant === 'separated') $itemClass .= ' component-accordion__item--separated';

        $chevronClass = 'component-accordion__chevron fa-solid fa-chevron-down';
        if ($isExpanded) $chevronClass .= ' component-accordion__chevron--expanded';

        $contentClass = 'component-accordion__content';
        if (!$isExpanded) $contentClass .= ' component-hidden';
        ?>
        <div class="<?= e($itemClass) ?>" id="<?= e($itemId) ?>">
            <button
                type="button"
                class="component-accordion__header"
                aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                aria-controls="<?= e($itemId) ?>-content"
                onclick="toggleAccordion('<?= e($itemId) ?>', '<?= e($id) ?>')"
            >
                <span class="component-accordion__title">
                    <?php if ($itemIcon): ?>
                        <i class="fa-solid fa-<?= e($itemIcon) ?> component-accordion__icon"></i>
                    <?php endif; ?>
                    <?= e($itemTitle) ?>
                </span>
                <i class="<?= e($chevronClass) ?>"></i>
            </button>
            <div
                class="<?= e($contentClass) ?>"
                id="<?= e($itemId) ?>-content"
                role="region"
                aria-labelledby="<?= e($itemId) ?>"
            >
                <div class="component-accordion__body">
                    <?= $itemContent ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function toggleAccordion(itemId, accordionId) {
    const accordion = document.getElementById(accordionId);
    const item = document.getElementById(itemId);
    const header = item.querySelector('.component-accordion__header');
    const content = item.querySelector('.component-accordion__content');
    const chevron = item.querySelector('.component-accordion__chevron');
    const isExpanded = header.getAttribute('aria-expanded') === 'true';
    const allowMultiple = accordion.dataset.allowMultiple === 'true';

    // Close other items if not allowing multiple
    if (!allowMultiple && !isExpanded) {
        accordion.querySelectorAll('.component-accordion__item').forEach(otherItem => {
            if (otherItem.id !== itemId) {
                const otherHeader = otherItem.querySelector('.component-accordion__header');
                const otherContent = otherItem.querySelector('.component-accordion__content');
                const otherChevron = otherItem.querySelector('.component-accordion__chevron');
                otherHeader.setAttribute('aria-expanded', 'false');
                otherContent.classList.add('component-hidden');
                otherChevron.classList.remove('component-accordion__chevron--expanded');
            }
        });
    }

    // Toggle current item
    header.setAttribute('aria-expanded', !isExpanded);
    content.classList.toggle('component-hidden');
    chevron.classList.toggle('component-accordion__chevron--expanded');
}
</script>
