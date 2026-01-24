<?php

/**
 * Component: Range Slider
 *
 * Range/slider input for numeric values.
 * Used on: members list, listings, matches preferences, admin configuration
 *
 * @param string $name Input name attribute (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Label text
 * @param int|float $value Current value
 * @param int|float $min Minimum value (default: 0)
 * @param int|float $max Maximum value (default: 100)
 * @param int|float $step Step increment (default: 1)
 * @param bool $showValue Show current value display (default: true)
 * @param string $valuePrefix Prefix for displayed value (e.g., '$')
 * @param string $valueSuffix Suffix for displayed value (e.g., 'km')
 * @param bool $showMinMax Show min/max labels (default: true)
 * @param bool $disabled Disabled state (default: false)
 * @param string $helpText Help text below slider
 * @param string $class Additional CSS classes
 * @param string $color Track color: 'primary', 'success', 'warning', 'danger' (default: 'primary')
 */

$name = $name ?? '';
$id = $id ?? 'range-' . md5($name . microtime());
$label = $label ?? '';
$value = $value ?? 50;
$min = $min ?? 0;
$max = $max ?? 100;
$step = $step ?? 1;
$showValue = $showValue ?? true;
$valuePrefix = $valuePrefix ?? '';
$valueSuffix = $valueSuffix ?? '';
$showMinMax = $showMinMax ?? true;
$disabled = $disabled ?? false;
$helpText = $helpText ?? '';
$class = $class ?? '';
$color = $color ?? 'primary';

// Calculate fill percentage - this is truly dynamic
$fillPercent = (($value - $min) / ($max - $min)) * 100;

$wrapperClass = trim('component-range-slider ' . $class);
$inputClass = 'component-range-slider__input';
if ($disabled) {
    $inputClass .= ' component-range-slider__input--disabled';
}

// Color class for the slider
$colorClass = 'component-range-slider--' . $color;
?>

<div class="<?= htmlspecialchars($wrapperClass) ?> <?= htmlspecialchars($colorClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper" data-color="<?= htmlspecialchars($color) ?>">
    <?php if ($label || $showValue): ?>
        <div class="component-range-slider__header">
            <?php if ($label): ?>
                <label for="<?= htmlspecialchars($id) ?>" class="component-range-slider__label">
                    <?= htmlspecialchars($label) ?>
                </label>
            <?php endif; ?>
            <?php if ($showValue): ?>
                <span class="component-range-slider__value" id="<?= htmlspecialchars($id) ?>-display">
                    <?= htmlspecialchars($valuePrefix) ?><?= htmlspecialchars($value) ?><?= htmlspecialchars($valueSuffix) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="component-range-slider__track">
        <div class="component-range-slider__fill" id="<?= htmlspecialchars($id) ?>-fill" style="width: <?= $fillPercent ?>%;"></div>
        <input
            type="range"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($id) ?>"
            value="<?= htmlspecialchars($value) ?>"
            min="<?= htmlspecialchars($min) ?>"
            max="<?= htmlspecialchars($max) ?>"
            step="<?= htmlspecialchars($step) ?>"
            <?= $disabled ? 'disabled' : '' ?>
            class="<?= htmlspecialchars($inputClass) ?>"
        >
    </div>

    <?php if ($showMinMax): ?>
        <div class="component-range-slider__minmax">
            <span class="component-range-slider__min"><?= htmlspecialchars($valuePrefix) ?><?= htmlspecialchars($min) ?><?= htmlspecialchars($valueSuffix) ?></span>
            <span class="component-range-slider__max"><?= htmlspecialchars($valuePrefix) ?><?= htmlspecialchars($max) ?><?= htmlspecialchars($valueSuffix) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($helpText): ?>
        <p class="component-range-slider__help">
            <?= htmlspecialchars($helpText) ?>
        </p>
    <?php endif; ?>
</div>

<script>
(function() {
    const input = document.getElementById('<?= htmlspecialchars($id) ?>');
    const fill = document.getElementById('<?= htmlspecialchars($id) ?>-fill');
    const display = document.getElementById('<?= htmlspecialchars($id) ?>-display');
    const min = <?= json_encode($min) ?>;
    const max = <?= json_encode($max) ?>;
    const prefix = '<?= htmlspecialchars($valuePrefix) ?>';
    const suffix = '<?= htmlspecialchars($valueSuffix) ?>';

    if (input) {
        input.addEventListener('input', function() {
            const percent = ((this.value - min) / (max - min)) * 100;
            if (fill) fill.style.width = percent + '%';
            if (display) display.textContent = prefix + this.value + suffix;
        });
    }
})();
</script>
