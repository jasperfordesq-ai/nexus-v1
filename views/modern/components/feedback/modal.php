<?php

/**
 * Component: Modal
 *
 * Modal dialog component.
 *
 * @param string $id Modal ID (required for open/close)
 * @param string $title Modal title
 * @param string $content Modal body content (HTML allowed)
 * @param string $footer Modal footer content (HTML allowed, typically buttons)
 * @param string $icon Header icon
 * @param string $size Size: 'sm', 'md', 'lg', 'xl', 'full' (default: 'md')
 * @param bool $closeOnBackdrop Close when clicking backdrop (default: true)
 * @param bool $showCloseButton Show X close button (default: true)
 * @param string $class Additional CSS classes for modal content
 */

$id = $id ?? 'modal-' . md5(microtime());
$title = $title ?? '';
$content = $content ?? '';
$footer = $footer ?? '';
$icon = $icon ?? '';
$size = $size ?? 'md';
$closeOnBackdrop = $closeOnBackdrop ?? true;
$showCloseButton = $showCloseButton ?? true;
$class = $class ?? '';

$sizeClasses = [
    'sm' => 'modal-sm',
    'md' => '',
    'lg' => 'modal-lg',
    'xl' => 'modal-xl',
    'full' => 'modal-fullscreen',
];
$sizeClass = $sizeClasses[$size] ?? '';

$cssClass = trim('modal-content glass-modal-content ' . $sizeClass . ' ' . $class);
?>

<div
    id="<?= e($id) ?>"
    class="modal-overlay glass-modal-overlay component-modal component-hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="<?= e($id) ?>-title"
    <?php if ($closeOnBackdrop): ?>onclick="if(event.target === this) closeModal('<?= e($id) ?>')"<?php endif; ?>
>
    <div class="<?= e($cssClass) ?>">
        <?php if ($title || $showCloseButton): ?>
            <div class="modal-header glass-modal-header">
                <?php if ($icon): ?>
                    <div class="modal-icon">
                        <i class="fa-solid fa-<?= e($icon) ?>"></i>
                    </div>
                <?php endif; ?>
                <?php if ($title): ?>
                    <h2 class="modal-title" id="<?= e($id) ?>-title"><?= e($title) ?></h2>
                <?php endif; ?>
                <?php if ($showCloseButton): ?>
                    <button
                        type="button"
                        class="modal-close"
                        aria-label="Close modal"
                        onclick="closeModal('<?= e($id) ?>')"
                    >
                        <i class="fa-solid fa-times"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($content): ?>
            <div class="modal-body glass-modal-body">
                <?= $content ?>
            </div>
        <?php endif; ?>

        <?php if ($footer): ?>
            <div class="modal-footer glass-modal-footer">
                <?= $footer ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
/**
 * Open a modal by ID
 */
function openModal(modalId, options = {}) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove('component-hidden');
    modal.classList.add('component-modal--open');
    document.body.classList.add('component-body--modal-open');

    // Focus trap
    const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length) {
        focusable[0].focus();
    }

    // Escape key handler
    modal._escHandler = (e) => {
        if (e.key === 'Escape') closeModal(modalId);
    };
    document.addEventListener('keydown', modal._escHandler);

    if (options.onOpen) options.onOpen(modal);
}

/**
 * Close a modal by ID
 */
function closeModal(modalId, options = {}) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.add('component-hidden');
    modal.classList.remove('component-modal--open');
    document.body.classList.remove('component-body--modal-open');

    // Remove escape handler
    if (modal._escHandler) {
        document.removeEventListener('keydown', modal._escHandler);
        delete modal._escHandler;
    }

    if (options.onClose) options.onClose(modal);
}
</script>
