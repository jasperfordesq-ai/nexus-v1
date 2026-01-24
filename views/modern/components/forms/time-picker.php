<?php

/**
 * Component: Time Picker
 *
 * Time input with clock picker.
 * Used on: settings, compose, events, polls, volunteering, newsletter scheduling
 *
 * @param string $name Input name attribute (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Label text
 * @param string $value Current time value (HH:MM format)
 * @param string $min Minimum time (HH:MM format)
 * @param string $max Maximum time (HH:MM format)
 * @param int $step Step in seconds (default: 60 for 1 minute)
 * @param bool $required Required field (default: false)
 * @param bool $disabled Disabled state (default: false)
 * @param string $placeholder Placeholder text
 * @param string $helpText Help text below input
 * @param string $error Error message
 * @param string $class Additional CSS classes
 * @param bool $show12Hour Show 12-hour format indicator (default: true)
 */

$name = $name ?? '';
$id = $id ?? 'time-' . md5($name . microtime());
$label = $label ?? '';
$value = $value ?? '';
$min = $min ?? '';
$max = $max ?? '';
$step = $step ?? 60;
$required = $required ?? false;
$disabled = $disabled ?? false;
$placeholder = $placeholder ?? 'Select time';
$helpText = $helpText ?? '';
$error = $error ?? '';
$class = $class ?? '';
$show12Hour = $show12Hour ?? true;

$wrapperClass = trim('component-time-picker ' . $class);
$inputClass = 'component-time-picker__input';
if ($error) {
    $inputClass .= ' component-time-picker__input--error';
}
if ($disabled) {
    $inputClass .= ' component-time-picker__input--disabled';
}

// Convert 24hr to 12hr for display
$time12hr = '';
if ($value && $show12Hour) {
    $timestamp = strtotime($value);
    $time12hr = date('g:i A', $timestamp);
}
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>">
    <?php if ($label): ?>
        <label for="<?= htmlspecialchars($id) ?>" class="component-time-picker__label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="component-time-picker__required">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <div class="component-time-picker__input-wrapper">
        <input
            type="time"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($id) ?>"
            value="<?= htmlspecialchars($value) ?>"
            <?php if ($min): ?>min="<?= htmlspecialchars($min) ?>"<?php endif; ?>
            <?php if ($max): ?>max="<?= htmlspecialchars($max) ?>"<?php endif; ?>
            step="<?= (int)$step ?>"
            <?= $required ? 'required' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>
            placeholder="<?= htmlspecialchars($placeholder) ?>"
            class="<?= htmlspecialchars($inputClass) ?>"
        >
        <span class="component-time-picker__icon">
            <i class="fa-solid fa-clock"></i>
        </span>
    </div>

    <?php if ($show12Hour): ?>
        <p class="component-time-picker__12hr <?= $time12hr ? '' : 'component-hidden' ?>" id="<?= htmlspecialchars($id) ?>-12hr">
            <?= htmlspecialchars($time12hr) ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="component-time-picker__error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </p>
    <?php elseif ($helpText): ?>
        <p class="component-time-picker__help">
            <?= htmlspecialchars($helpText) ?>
        </p>
    <?php endif; ?>
</div>

<?php if ($show12Hour): ?>
<script>
(function() {
    const input = document.getElementById('<?= htmlspecialchars($id) ?>');
    const display12hr = document.getElementById('<?= htmlspecialchars($id) ?>-12hr');

    if (input && display12hr) {
        input.addEventListener('change', function() {
            if (this.value) {
                const [hours, minutes] = this.value.split(':');
                let h = parseInt(hours, 10);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                display12hr.textContent = h + ':' + minutes + ' ' + ampm;
                display12hr.classList.remove('component-hidden');
            } else {
                display12hr.classList.add('component-hidden');
            }
        });
    }
})();
</script>
<?php endif; ?>
