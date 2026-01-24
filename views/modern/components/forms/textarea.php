<?php

/**
 * Component: Textarea
 *
 * Multi-line text input field.
 *
 * @param string $name Textarea name attribute
 * @param string $value Textarea value/content
 * @param string $placeholder Placeholder text
 * @param int $rows Number of visible rows (default: 4)
 * @param bool $required Whether field is required
 * @param bool $disabled Whether field is disabled
 * @param bool $readonly Whether field is readonly
 * @param string $id Textarea ID (auto-generated from name if not provided)
 * @param string $class Additional CSS classes
 * @param int $maxlength Maximum character length
 * @param bool $autoResize Enable auto-resize (default: false)
 * @param array $attributes Additional HTML attributes
 */

$name = $name ?? '';
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$rows = $rows ?? 4;
$required = $required ?? false;
$disabled = $disabled ?? false;
$readonly = $readonly ?? false;
$id = $id ?? ($name ? 'field-' . $name : '');
$class = $class ?? '';
$maxlength = $maxlength ?? null;
$autoResize = $autoResize ?? false;
$attributes = $attributes ?? [];

$cssClass = trim('form-textarea glass-input ' . $class . ($autoResize ? ' auto-resize' : ''));

// Build additional attributes
$attrs = [];
if ($maxlength) $attrs['maxlength'] = $maxlength;
$attrs = array_merge($attrs, $attributes);

$attrString = '';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . e($key) . '="' . e($val) . '"';
}
?>

<textarea
    <?php if ($name): ?>name="<?= e($name) ?>"<?php endif; ?>
    <?php if ($id): ?>id="<?= e($id) ?>"<?php endif; ?>
    class="<?= e($cssClass) ?>"
    rows="<?= (int)$rows ?>"
    <?php if ($placeholder): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
    <?php if ($required): ?>required aria-required="true"<?php endif; ?>
    <?php if ($disabled): ?>disabled<?php endif; ?>
    <?php if ($readonly): ?>readonly<?php endif; ?>
    <?php if ($autoResize): ?>oninput="this.style.height = 'auto'; this.style.height = this.scrollHeight + 'px';"<?php endif; ?>
    <?= $attrString ?>
><?= e($value) ?></textarea>
