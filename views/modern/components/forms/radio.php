<?php

/**
 * Component: Radio
 *
 * Radio button group.
 *
 * @param string $name Radio group name attribute
 * @param array $options Array of options: ['value' => '', 'label' => '', 'description' => '', 'disabled' => false]
 * @param string $selected Currently selected value
 * @param bool $required Whether field is required
 * @param bool $disabled Disable entire group
 * @param string $class Additional CSS classes
 * @param string $layout 'vertical' or 'horizontal' (default: 'vertical')
 */

$name = $name ?? '';
$options = $options ?? [];
$selected = $selected ?? '';
$required = $required ?? false;
$disabled = $disabled ?? false;
$class = $class ?? '';
$layout = $layout ?? 'vertical';

$cssClass = trim('form-radio-group radio-' . $layout . ' ' . $class);
?>

<div class="<?= e($cssClass) ?>" role="radiogroup">
    <?php foreach ($options as $index => $option): ?>
        <?php
        $optionValue = is_array($option) ? ($option['value'] ?? $index) : $index;
        $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
        $optionDescription = is_array($option) ? ($option['description'] ?? '') : '';
        $optionDisabled = $disabled || (is_array($option) && ($option['disabled'] ?? false));
        $isSelected = $optionValue == $selected;
        $optionId = $name . '-' . $index;
        ?>
        <div class="form-radio-wrapper">
            <label class="form-radio-label <?= $optionDisabled ? 'disabled' : '' ?>">
                <input
                    type="radio"
                    class="form-radio"
                    name="<?= e($name) ?>"
                    id="<?= e($optionId) ?>"
                    value="<?= e($optionValue) ?>"
                    <?php if ($isSelected): ?>checked<?php endif; ?>
                    <?php if ($required && $index === 0): ?>required aria-required="true"<?php endif; ?>
                    <?php if ($optionDisabled): ?>disabled<?php endif; ?>
                >
                <span class="form-radio-custom"></span>
                <span class="form-radio-text"><?= e($optionLabel) ?></span>
            </label>
            <?php if ($optionDescription): ?>
                <p class="form-radio-description"><?= e($optionDescription) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
