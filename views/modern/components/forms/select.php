<?php

/**
 * Component: Select
 *
 * Dropdown select field.
 *
 * @param string $name Select name attribute
 * @param array $options Array of options: ['value' => '', 'label' => '', 'disabled' => false] or simple ['value' => 'label']
 * @param string $selected Currently selected value
 * @param string $placeholder Placeholder/default option text
 * @param bool $required Whether field is required
 * @param bool $disabled Whether field is disabled
 * @param bool $multiple Allow multiple selections
 * @param string $id Select ID (auto-generated from name if not provided)
 * @param string $class Additional CSS classes
 * @param array $attributes Additional HTML attributes
 */

$name = $name ?? '';
$options = $options ?? [];
$selected = $selected ?? '';
$placeholder = $placeholder ?? '';
$required = $required ?? false;
$disabled = $disabled ?? false;
$multiple = $multiple ?? false;
$id = $id ?? ($name ? 'field-' . $name : '');
$class = $class ?? '';
$attributes = $attributes ?? [];

$cssClass = trim('form-input glass-select ' . $class);

// Build additional attributes
$attrString = '';
foreach ($attributes as $key => $val) {
    $attrString .= ' ' . e($key) . '="' . e($val) . '"';
}

// Normalize options to consistent format
$normalizedOptions = [];
foreach ($options as $key => $option) {
    if (is_array($option)) {
        $normalizedOptions[] = [
            'value' => $option['value'] ?? $key,
            'label' => $option['label'] ?? $option['value'] ?? $key,
            'disabled' => $option['disabled'] ?? false,
        ];
    } else {
        // Simple value => label format
        $normalizedOptions[] = [
            'value' => $key,
            'label' => $option,
            'disabled' => false,
        ];
    }
}
?>

<select
    <?php if ($name): ?>name="<?= e($name) ?><?= $multiple ? '[]' : '' ?>"<?php endif; ?>
    <?php if ($id): ?>id="<?= e($id) ?>"<?php endif; ?>
    class="<?= e($cssClass) ?>"
    <?php if ($required): ?>required aria-required="true"<?php endif; ?>
    <?php if ($disabled): ?>disabled<?php endif; ?>
    <?php if ($multiple): ?>multiple<?php endif; ?>
    <?= $attrString ?>
>
    <?php if ($placeholder): ?>
        <option value="" <?= empty($selected) ? 'selected' : '' ?> disabled>
            <?= e($placeholder) ?>
        </option>
    <?php endif; ?>

    <?php foreach ($normalizedOptions as $option): ?>
        <?php
        $isSelected = $multiple
            ? (is_array($selected) && in_array($option['value'], $selected))
            : ($option['value'] == $selected);
        ?>
        <option
            value="<?= e($option['value']) ?>"
            <?php if ($isSelected): ?>selected<?php endif; ?>
            <?php if ($option['disabled']): ?>disabled<?php endif; ?>
        >
            <?= e($option['label']) ?>
        </option>
    <?php endforeach; ?>
</select>
