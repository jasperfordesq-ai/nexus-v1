<?php

/**
 * Component: Toggle Switch
 *
 * On/off toggle switch input (iOS-style).
 * Used on: settings, profile, admin configs, feature toggles
 *
 * @param string $name Input name attribute (required)
 * @param string $id Element ID (default: auto-generated)
 * @param bool $checked Whether toggle is on (default: false)
 * @param string $label Label text
 * @param string $labelPosition Label position: 'left', 'right' (default: 'right')
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param bool $disabled Disabled state (default: false)
 * @param string $onLabel Text when on (default: '')
 * @param string $offLabel Text when off (default: '')
 * @param string $value Value when checked (default: '1')
 * @param string $class Additional CSS classes
 * @param string $helpText Help text below toggle
 * @param array $attributes Additional HTML attributes
 */

$name = $name ?? '';
$id = $id ?? 'toggle-' . md5($name . microtime());
$checked = $checked ?? false;
$label = $label ?? '';
$labelPosition = $labelPosition ?? 'right';
$size = $size ?? 'md';
$disabled = $disabled ?? false;
$onLabel = $onLabel ?? '';
$offLabel = $offLabel ?? '';
$value = $value ?? '1';
$class = $class ?? '';
$helpText = $helpText ?? '';
$attributes = $attributes ?? [];

$wrapperClass = trim('toggle-switch-wrapper ' . $class);
$labelClass = 'component-toggle';
if ($disabled) $labelClass .= ' component-toggle--disabled';

$trackClass = 'toggle-track component-toggle__track';
if ($size === 'sm') $trackClass .= ' component-toggle__track--sm';
elseif ($size === 'lg') $trackClass .= ' component-toggle__track--lg';

// Build additional attributes string
$attrString = '';
foreach ($attributes as $key => $val) {
    if ($val === true) {
        $attrString .= ' ' . htmlspecialchars($key);
    } elseif ($val !== false && $val !== null) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    }
}
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>">
    <label class="<?= htmlspecialchars($labelClass) ?>">
        <?php if ($label && $labelPosition === 'left'): ?>
            <span class="component-toggle__label"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>

        <input
            type="checkbox"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($id) ?>"
            value="<?= htmlspecialchars($value) ?>"
            <?= $checked ? 'checked' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>
            class="toggle-input component-toggle__input"
            <?= $attrString ?>
        >
        <span class="<?= htmlspecialchars($trackClass) ?>">
            <span class="toggle-thumb component-toggle__thumb"></span>
        </span>

        <?php if ($label && $labelPosition === 'right'): ?>
            <span class="component-toggle__label"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>

        <?php if ($onLabel || $offLabel): ?>
            <span class="component-toggle__status" id="<?= htmlspecialchars($id) ?>-status">
                <?= $checked ? htmlspecialchars($onLabel) : htmlspecialchars($offLabel) ?>
            </span>
        <?php endif; ?>
    </label>

    <?php if ($helpText): ?>
        <p class="component-form-group__help">
            <?= htmlspecialchars($helpText) ?>
        </p>
    <?php endif; ?>
</div>

<?php if ($onLabel || $offLabel): ?>
<script>
(function() {
    const toggle = document.getElementById('<?= htmlspecialchars($id) ?>');
    const stateLabel = document.getElementById('<?= htmlspecialchars($id) ?>-status');
    if (toggle && stateLabel) {
        toggle.addEventListener('change', function() {
            stateLabel.textContent = this.checked ? '<?= htmlspecialchars($onLabel) ?>' : '<?= htmlspecialchars($offLabel) ?>';
        });
    }
})();
</script>
<?php endif; ?>
