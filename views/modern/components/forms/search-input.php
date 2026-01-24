<?php

/**
 * Component: Search Input
 *
 * Styled search input with icon.
 *
 * @param string $name Input name attribute (default: 'q')
 * @param string $value Current search value
 * @param string $placeholder Placeholder text (default: 'Search...')
 * @param string $id Input ID
 * @param string $class Additional CSS classes
 * @param bool $autoSubmit Auto-submit on Enter (default: true)
 * @param string $formAction Form action URL (if wrapping in form)
 */

$name = $name ?? 'q';
$value = $value ?? '';
$placeholder = $placeholder ?? 'Search...';
$id = $id ?? 'search-' . $name;
$class = $class ?? '';
$autoSubmit = $autoSubmit ?? true;
$formAction = $formAction ?? '';

$cssClass = trim('component-search-input ' . $class);
?>

<div class="<?= e($cssClass) ?>">
    <i class="fa-solid fa-search component-search-input__icon"></i>
    <input
        type="search"
        name="<?= e($name) ?>"
        id="<?= e($id) ?>"
        class="glass-search-input component-search-input__field"
        value="<?= e($value) ?>"
        placeholder="<?= e($placeholder) ?>"
        <?php if ($autoSubmit): ?>
        onkeypress="if(event.key === 'Enter') this.form.submit();"
        <?php endif; ?>
    >
</div>
