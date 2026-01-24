<?php

/**
 * Component: Checkbox
 *
 * Checkbox input with label.
 *
 * @param string $name Checkbox name attribute
 * @param string $label Checkbox label text
 * @param string $value Checkbox value (default: '1')
 * @param bool $checked Whether checkbox is checked
 * @param bool $required Whether field is required
 * @param bool $disabled Whether field is disabled
 * @param string $id Checkbox ID (auto-generated from name if not provided)
 * @param string $class Additional CSS classes
 * @param string $description Additional description text below label
 */

$name = $name ?? '';
$label = $label ?? '';
$value = $value ?? '1';
$checked = $checked ?? false;
$required = $required ?? false;
$disabled = $disabled ?? false;
$id = $id ?? ($name ? 'field-' . $name : 'checkbox-' . md5(microtime()));
$class = $class ?? '';
$description = $description ?? '';

$cssClass = trim('form-checkbox-wrapper ' . $class);
?>

<div class="<?= e($cssClass) ?>">
    <label class="form-checkbox-label">
        <input
            type="checkbox"
            class="form-checkbox"
            <?php if ($name): ?>name="<?= e($name) ?>"<?php endif; ?>
            <?php if ($id): ?>id="<?= e($id) ?>"<?php endif; ?>
            value="<?= e($value) ?>"
            <?php if ($checked): ?>checked<?php endif; ?>
            <?php if ($required): ?>required aria-required="true"<?php endif; ?>
            <?php if ($disabled): ?>disabled<?php endif; ?>
        >
        <span class="form-checkbox-custom"></span>
        <span class="form-checkbox-text">
            <?= e($label) ?>
            <?php if ($required): ?>
                <span class="form-required" aria-hidden="true">*</span>
            <?php endif; ?>
        </span>
    </label>
    <?php if ($description): ?>
        <p class="form-checkbox-description"><?= e($description) ?></p>
    <?php endif; ?>
</div>
