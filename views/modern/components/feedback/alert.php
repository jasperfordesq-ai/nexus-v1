<?php

/**
 * Component: Alert
 *
 * Alert/notice box for displaying messages.
 *
 * @param string $message Alert message (required)
 * @param string $type Alert type: 'info', 'success', 'warning', 'danger' (default: 'info')
 * @param string $title Optional title/heading
 * @param bool $dismissible Allow user to dismiss (default: false)
 * @param string $icon Custom icon (auto-set based on type if not provided)
 * @param string $class Additional CSS classes
 * @param string $id Alert ID (required if dismissible)
 */

$message = $message ?? '';
$type = $type ?? 'info';
$title = $title ?? '';
$dismissible = $dismissible ?? false;
$icon = $icon ?? '';
$class = $class ?? '';
$id = $id ?? ($dismissible ? 'alert-' . md5(microtime()) : '');

// Auto-set icon based on type if not provided
$typeIcons = [
    'info' => 'info-circle',
    'success' => 'check-circle',
    'warning' => 'exclamation-triangle',
    'danger' => 'exclamation-circle',
];
$alertIcon = $icon ?: ($typeIcons[$type] ?? 'info-circle');

$cssClass = trim('alert alert-' . $type . ' ' . $class);
?>

<div
    class="<?= e($cssClass) ?>"
    role="alert"
    <?php if ($id): ?>id="<?= e($id) ?>"<?php endif; ?>
>
    <div class="alert-content">
        <i class="fa-solid fa-<?= e($alertIcon) ?> alert-icon"></i>
        <div class="alert-body">
            <?php if ($title): ?>
                <strong class="alert-title"><?= e($title) ?></strong>
            <?php endif; ?>
            <span class="alert-message"><?= e($message) ?></span>
        </div>
    </div>
    <?php if ($dismissible): ?>
        <button
            type="button"
            class="alert-dismiss"
            aria-label="Dismiss"
            onclick="document.getElementById('<?= e($id) ?>').remove()"
        >
            <i class="fa-solid fa-times"></i>
        </button>
    <?php endif; ?>
</div>
