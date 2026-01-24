<?php

/**
 * Component: Form Group
 *
 * Wrapper for form field with label, input, help text, and error message.
 *
 * @param string $label Field label
 * @param string $name Field name (used for ID and error association)
 * @param string $content The form input element (HTML)
 * @param string $error Error message (if validation failed)
 * @param string $help Help/hint text
 * @param bool $required Whether field is required
 * @param string $class Additional CSS classes
 */

$label = $label ?? '';
$name = $name ?? '';
$content = $content ?? '';
$error = $error ?? '';
$help = $help ?? '';
$required = $required ?? false;
$class = $class ?? '';

$cssClass = trim('form-group ' . $class . ($error ? ' has-error' : ''));
$inputId = $name ? 'field-' . e($name) : '';
?>

<div class="<?= e($cssClass) ?>">
    <?php if ($label): ?>
        <label class="form-label" <?php if ($inputId): ?>for="<?= $inputId ?>"<?php endif; ?>>
            <?= e($label) ?>
            <?php if ($required): ?>
                <span class="form-required" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <?php if ($help): ?>
        <p class="form-hint" id="<?= e($name) ?>-hint"><?= e($help) ?></p>
    <?php endif; ?>

    <div class="form-input-wrapper">
        <?= $content ?>
    </div>

    <?php if ($error): ?>
        <p class="form-error-message" role="alert" id="<?= e($name) ?>-error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?= e($error) ?>
        </p>
    <?php endif; ?>
</div>
