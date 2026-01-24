<?php

/**
 * Component: Date Picker
 *
 * Date input with calendar picker.
 * Used on: compose, events, polls, goals, volunteering, newsletters
 *
 * @param string $name Input name attribute (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Label text
 * @param string $value Current date value (Y-m-d format)
 * @param string $min Minimum date (Y-m-d format)
 * @param string $max Maximum date (Y-m-d format)
 * @param bool $required Required field (default: false)
 * @param bool $disabled Disabled state (default: false)
 * @param string $placeholder Placeholder text
 * @param string $helpText Help text below input
 * @param string $error Error message
 * @param string $class Additional CSS classes
 * @param string $format Display format: 'default', 'friendly' (default: 'default')
 */

$name = $name ?? '';
$id = $id ?? 'date-' . md5($name . microtime());
$label = $label ?? '';
$value = $value ?? '';
$min = $min ?? '';
$max = $max ?? '';
$required = $required ?? false;
$disabled = $disabled ?? false;
$placeholder = $placeholder ?? 'Select date';
$helpText = $helpText ?? '';
$error = $error ?? '';
$class = $class ?? '';
$format = $format ?? 'default';

$wrapperClass = trim('component-date-picker ' . $class);
$inputClass = 'component-date-picker__input';
if ($error) {
    $inputClass .= ' component-date-picker__input--error';
}
if ($disabled) {
    $inputClass .= ' component-date-picker__input--disabled';
}
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>">
    <?php if ($label): ?>
        <label for="<?= htmlspecialchars($id) ?>" class="component-date-picker__label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="component-date-picker__required">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <div class="component-date-picker__input-wrapper">
        <input
            type="date"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($id) ?>"
            value="<?= htmlspecialchars($value) ?>"
            <?php if ($min): ?>min="<?= htmlspecialchars($min) ?>"<?php endif; ?>
            <?php if ($max): ?>max="<?= htmlspecialchars($max) ?>"<?php endif; ?>
            <?= $required ? 'required' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>
            placeholder="<?= htmlspecialchars($placeholder) ?>"
            class="<?= htmlspecialchars($inputClass) ?>"
        >
        <span class="component-date-picker__icon">
            <i class="fa-solid fa-calendar"></i>
        </span>
    </div>

    <?php if ($format === 'friendly' && $value): ?>
        <p class="component-date-picker__friendly" id="<?= htmlspecialchars($id) ?>-friendly">
            <?php
            $timestamp = strtotime($value);
            echo date('l, F j, Y', $timestamp);
            ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="component-date-picker__error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </p>
    <?php elseif ($helpText): ?>
        <p class="component-date-picker__help">
            <?= htmlspecialchars($helpText) ?>
        </p>
    <?php endif; ?>
</div>

<?php if ($format === 'friendly'): ?>
<script>
(function() {
    const input = document.getElementById('<?= htmlspecialchars($id) ?>');
    const friendly = document.getElementById('<?= htmlspecialchars($id) ?>-friendly');

    if (input && friendly) {
        input.addEventListener('change', function() {
            if (this.value) {
                const date = new Date(this.value + 'T00:00:00');
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                friendly.textContent = date.toLocaleDateString('en-US', options);
                friendly.classList.remove('component-hidden');
            } else {
                friendly.classList.add('component-hidden');
            }
        });
    }
})();
</script>
<?php endif; ?>
