<?php

/**
 * Component: Draggable List
 *
 * Sortable/draggable list for reordering items.
 * Used on: admin menus builder, pages create, newsletters, blog builder
 *
 * @param array $items Array of items: ['id' => '', 'content' => '', 'data' => []]
 * @param string $name Hidden input name for order (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Label text
 * @param bool $showHandle Show drag handle (default: true)
 * @param bool $showRemove Show remove button (default: false)
 * @param string $emptyText Text when list is empty
 * @param string $class Additional CSS classes
 * @param string $variant Variant: 'default', 'cards', 'compact' (default: 'default')
 * @param callable $itemTemplate Custom item template callback
 */

$items = $items ?? [];
$name = $name ?? 'order';
$id = $id ?? 'draggable-' . md5($name . microtime());
$label = $label ?? '';
$showHandle = $showHandle ?? true;
$showRemove = $showRemove ?? false;
$emptyText = $emptyText ?? 'No items to display';
$class = $class ?? '';
$variant = $variant ?? 'default';
$itemTemplate = $itemTemplate ?? null;

// Variant classes
$variantClasses = [
    'default' => 'component-draggable--default',
    'cards' => 'component-draggable--cards',
    'compact' => 'component-draggable--compact',
];
$variantClass = $variantClasses[$variant] ?? $variantClasses['default'];

$wrapperClass = trim('component-draggable ' . $variantClass . ' ' . $class);
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper">
    <?php if ($label): ?>
        <label class="component-draggable__label">
            <?= htmlspecialchars($label) ?>
        </label>
    <?php endif; ?>

    <!-- Hidden input for order -->
    <input type="hidden" name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($id) ?>-order" value="<?= htmlspecialchars(implode(',', array_column($items, 'id'))) ?>">

    <div class="component-draggable__list" id="<?= htmlspecialchars($id) ?>">
        <?php if (empty($items)): ?>
            <div class="component-draggable__empty" id="<?= htmlspecialchars($id) ?>-empty">
                <i class="fa-solid fa-layer-group component-draggable__empty-icon"></i>
                <p class="component-draggable__empty-text"><?= htmlspecialchars($emptyText) ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $index => $item): ?>
                <?php
                $itemId = $item['id'] ?? $index;
                $itemContent = $item['content'] ?? $item['title'] ?? $item['label'] ?? '';
                $itemData = $item['data'] ?? [];
                ?>
                <div
                    class="component-draggable__item"
                    data-id="<?= htmlspecialchars($itemId) ?>"
                    draggable="true"
                >
                    <?php if ($showHandle): ?>
                        <span class="component-draggable__handle">
                            <i class="fa-solid fa-grip-vertical"></i>
                        </span>
                    <?php endif; ?>

                    <div class="component-draggable__content">
                        <?php if ($itemTemplate && is_callable($itemTemplate)): ?>
                            <?= $itemTemplate($item) ?>
                        <?php else: ?>
                            <span class="component-draggable__text"><?= htmlspecialchars($itemContent) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($showRemove): ?>
                        <button
                            type="button"
                            class="component-draggable__remove"
                            onclick="removeDraggableItem('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($itemId) ?>')"
                            title="Remove"
                        >
                            <i class="fa-solid fa-times"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const list = document.getElementById('<?= htmlspecialchars($id) ?>');
    const orderInput = document.getElementById('<?= htmlspecialchars($id) ?>-order');
    let draggedItem = null;

    list.addEventListener('dragstart', function(e) {
        if (e.target.classList.contains('component-draggable__item')) {
            draggedItem = e.target;
            draggedItem.classList.add('component-draggable__item--dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    list.addEventListener('dragend', function(e) {
        if (draggedItem) {
            draggedItem.classList.remove('component-draggable__item--dragging');
            draggedItem = null;
            updateOrder();
        }
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        const target = e.target.closest('.component-draggable__item');
        if (target && target !== draggedItem) {
            const rect = target.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                target.parentNode.insertBefore(draggedItem, target);
            } else {
                target.parentNode.insertBefore(draggedItem, target.nextSibling);
            }
        }
    });

    list.addEventListener('dragenter', function(e) {
        const target = e.target.closest('.component-draggable__item');
        if (target && target !== draggedItem) {
            target.classList.add('component-draggable__item--drag-over');
        }
    });

    list.addEventListener('dragleave', function(e) {
        const target = e.target.closest('.component-draggable__item');
        if (target) {
            target.classList.remove('component-draggable__item--drag-over');
        }
    });

    list.addEventListener('drop', function(e) {
        e.preventDefault();
        list.querySelectorAll('.component-draggable__item--drag-over').forEach(el => {
            el.classList.remove('component-draggable__item--drag-over');
        });
    });

    function updateOrder() {
        const items = list.querySelectorAll('.component-draggable__item');
        const order = Array.from(items).map(item => item.dataset.id);
        orderInput.value = order.join(',');

        // Dispatch custom event
        list.dispatchEvent(new CustomEvent('orderChanged', { detail: { order } }));
    }

    // Expose globally
    window['draggable_' + '<?= htmlspecialchars($id) ?>'] = { updateOrder };
})();

function removeDraggableItem(listId, itemId) {
    const list = document.getElementById(listId);
    const item = list.querySelector('[data-id="' + itemId + '"]');
    if (item) {
        item.remove();
        window['draggable_' + listId].updateOrder();

        // Show empty state if no items left
        if (list.querySelectorAll('.component-draggable__item').length === 0) {
            const empty = document.getElementById(listId + '-empty');
            if (empty) empty.classList.remove('component-hidden');
        }
    }
}
</script>
