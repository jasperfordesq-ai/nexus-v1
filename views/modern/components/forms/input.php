<?php

/**
 * Component: Input
 *
 * Text input field.
 *
 * @param string $type Input type: 'text', 'email', 'password', 'number', 'tel', 'url' (default: 'text')
 * @param string $name Input name attribute
 * @param string $value Input value
 * @param string $placeholder Placeholder text
 * @param bool $required Whether field is required
 * @param bool $disabled Whether field is disabled
 * @param bool $readonly Whether field is readonly
 * @param string $id Input ID (auto-generated from name if not provided)
 * @param string $class Additional CSS classes
 * @param string $autocomplete Autocomplete attribute
 * @param int $maxlength Maximum length
 * @param int $minlength Minimum length
 * @param string $pattern Regex pattern for validation
 * @param string $icon Optional icon to show inside input (left side)
 * @param array $attributes Additional HTML attributes
 */

$type = $type ?? 'text';
$name = $name ?? '';
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$required = $required ?? false;
$disabled = $disabled ?? false;
$readonly = $readonly ?? false;
$id = $id ?? ($name ? 'field-' . $name : '');
$class = $class ?? '';
$autocomplete = $autocomplete ?? null;
$maxlength = $maxlength ?? null;
$minlength = $minlength ?? null;
$pattern = $pattern ?? null;
$icon = $icon ?? '';
$attributes = $attributes ?? [];

$cssClass = trim('form-input glass-input ' . $class);
$hasIcon = !empty($icon);

// Build additional attributes
$attrs = [];
if ($autocomplete) $attrs['autocomplete'] = $autocomplete;
if ($maxlength) $attrs['maxlength'] = $maxlength;
if ($minlength) $attrs['minlength'] = $minlength;
if ($pattern) $attrs['pattern'] = $pattern;
$attrs = array_merge($attrs, $attributes);

$attrString = '';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . e($key) . '="' . e($val) . '"';
}
?>

<?php if ($hasIcon): ?>
<div class="input-with-icon">
    <i class="fa-solid fa-<?= e($icon) ?> input-icon"></i>
<?php endif; ?>

<input
    type="<?= e($type) ?>"
    <?php if ($name): ?>name="<?= e($name) ?>"<?php endif; ?>
    <?php if ($id): ?>id="<?= e($id) ?>"<?php endif; ?>
    class="<?= e($cssClass) ?>"
    value="<?= e($value) ?>"
    <?php if ($placeholder): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
    <?php if ($required): ?>required aria-required="true"<?php endif; ?>
    <?php if ($disabled): ?>disabled<?php endif; ?>
    <?php if ($readonly): ?>readonly<?php endif; ?>
    <?= $attrString ?>
>

<?php if ($hasIcon): ?>
</div>
<?php endif; ?>
