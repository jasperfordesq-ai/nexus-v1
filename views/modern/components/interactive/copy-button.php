<?php

/**
 * Component: Copy Button
 *
 * Copy-to-clipboard button.
 * Used on: post cards, blog, admin settings, pages builder, auth login
 *
 * @param string $text Text to copy (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Button label (default: 'Copy')
 * @param string $copiedLabel Label after copy (default: 'Copied!')
 * @param string $variant Variant: 'button', 'icon', 'link' (default: 'button')
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param string $icon Icon name (default: 'copy')
 * @param string $copiedIcon Icon after copy (default: 'check')
 * @param int $resetDelay Reset delay in ms (default: 2000)
 * @param string $class Additional CSS classes
 * @param bool $showToast Show toast notification (default: false)
 * @param string $toastMessage Toast message (default: 'Copied to clipboard')
 */

$text = $text ?? '';
$id = $id ?? 'copy-' . md5($text . microtime());
$label = $label ?? 'Copy';
$copiedLabel = $copiedLabel ?? 'Copied!';
$variant = $variant ?? 'button';
$size = $size ?? 'md';
$icon = $icon ?? 'copy';
$copiedIcon = $copiedIcon ?? 'check';
$resetDelay = $resetDelay ?? 2000;
$class = $class ?? '';
$showToast = $showToast ?? false;
$toastMessage = $toastMessage ?? 'Copied to clipboard';

// Size classes
$sizeClasses = [
    'sm' => 'component-copy--sm',
    'md' => 'component-copy--md',
    'lg' => 'component-copy--lg',
];
$sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

// Variant classes
$variantClasses = [
    'button' => 'component-copy--button',
    'icon' => 'component-copy--icon',
    'link' => 'component-copy--link',
];
$variantClass = $variantClasses[$variant] ?? $variantClasses['button'];

$cssClass = trim('component-copy ' . $sizeClass . ' ' . $variantClass . ' ' . $class);
?>

<?php if ($variant === 'button'): ?>
    <button
        type="button"
        class="<?= htmlspecialchars($cssClass) ?> nexus-smart-btn nexus-smart-btn-outline"
        id="<?= htmlspecialchars($id) ?>"
        onclick="copyToClipboard('<?= htmlspecialchars($id) ?>', <?= htmlspecialchars(json_encode($text)) ?>)"
    >
        <i class="fa-regular fa-<?= htmlspecialchars($icon) ?> component-copy__icon" id="<?= htmlspecialchars($id) ?>-icon"></i>
        <span id="<?= htmlspecialchars($id) ?>-label"><?= htmlspecialchars($label) ?></span>
    </button>

<?php elseif ($variant === 'icon'): ?>
    <button
        type="button"
        class="<?= htmlspecialchars($cssClass) ?>"
        id="<?= htmlspecialchars($id) ?>"
        onclick="copyToClipboard('<?= htmlspecialchars($id) ?>', <?= htmlspecialchars(json_encode($text)) ?>)"
        title="<?= htmlspecialchars($label) ?>"
    >
        <i class="fa-regular fa-<?= htmlspecialchars($icon) ?> component-copy__icon" id="<?= htmlspecialchars($id) ?>-icon"></i>
    </button>

<?php else: ?>
    <button
        type="button"
        class="<?= htmlspecialchars($cssClass) ?>"
        id="<?= htmlspecialchars($id) ?>"
        onclick="copyToClipboard('<?= htmlspecialchars($id) ?>', <?= htmlspecialchars(json_encode($text)) ?>)"
    >
        <span id="<?= htmlspecialchars($id) ?>-label"><?= htmlspecialchars($label) ?></span>
    </button>
<?php endif; ?>

<script>
function copyToClipboard(id, text) {
    const btn = document.getElementById(id);
    const icon = document.getElementById(id + '-icon');
    const label = document.getElementById(id + '-label');

    navigator.clipboard.writeText(text).then(function() {
        // Update UI
        btn.classList.add('component-copy--copied');
        if (icon) {
            icon.className = 'fa-solid fa-<?= htmlspecialchars($copiedIcon) ?> component-copy__icon';
        }
        if (label) {
            label.textContent = '<?= htmlspecialchars($copiedLabel) ?>';
        }

        <?php if ($showToast): ?>
        if (typeof showToast === 'function') {
            showToast('<?= htmlspecialchars($toastMessage) ?>', 'success');
        }
        <?php endif; ?>

        // Reset after delay
        setTimeout(function() {
            btn.classList.remove('component-copy--copied');
            if (icon) {
                icon.className = 'fa-regular fa-<?= htmlspecialchars($icon) ?> component-copy__icon';
            }
            if (label) {
                label.textContent = '<?= htmlspecialchars($label) ?>';
            }
        }, <?= (int)$resetDelay ?>);
    }).catch(function(err) {
        console.error('Failed to copy:', err);
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.className = 'visually-hidden';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            btn.classList.add('component-copy--copied');
            if (icon) icon.className = 'fa-solid fa-<?= htmlspecialchars($copiedIcon) ?> component-copy__icon';
            if (label) label.textContent = '<?= htmlspecialchars($copiedLabel) ?>';
            setTimeout(function() {
                btn.classList.remove('component-copy--copied');
                if (icon) icon.className = 'fa-regular fa-<?= htmlspecialchars($icon) ?> component-copy__icon';
                if (label) label.textContent = '<?= htmlspecialchars($label) ?>';
            }, <?= (int)$resetDelay ?>);
        } catch (e) {
            alert('Failed to copy. Please copy manually.');
        }
        document.body.removeChild(textarea);
    });
}
</script>
