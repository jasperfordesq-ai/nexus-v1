<?php
/**
 * GOV.UK Button Component
 * Reusable button following GOV.UK Design System v5.14.0
 *
 * @param string $text - Button text
 * @param string $type - Button type: 'start' (green), 'secondary' (grey), 'warning' (red)
 * @param string $href - Link URL (optional, creates <a> tag)
 * @param string $onclick - JavaScript onclick handler (optional)
 * @param bool $disabled - Whether button is disabled
 * @param string $class - Additional CSS classes
 * @param string $id - Element ID
 * @param string $ariaLabel - Accessible label (optional)
 *
 * Usage:
 * <?php include __DIR__ . '/button.php'; echo civicone_govuk_button(['text' => 'Save', 'type' => 'start']); ?>
 */

function civicone_govuk_button($args = []) {
    $defaults = [
        'text' => 'Button',
        'type' => 'start', // 'start', 'secondary', 'warning'
        'href' => null,
        'onclick' => null,
        'disabled' => false,
        'class' => '',
        'id' => '',
        'ariaLabel' => null
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-button'];

    // Add type modifier class
    if ($args['type'] === 'secondary') {
        $classes[] = 'govuk-button--secondary';
    } elseif ($args['type'] === 'warning') {
        $classes[] = 'govuk-button--warning';
    }
    // 'start' uses default green styling

    // Add custom classes
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $classStr = implode(' ', $classes);

    // Build attributes
    $attrs = [];
    $attrs[] = 'class="' . htmlspecialchars($classStr) . '"';

    if (!empty($args['id'])) {
        $attrs[] = 'id="' . htmlspecialchars($args['id']) . '"';
    }

    if ($args['ariaLabel']) {
        $attrs[] = 'aria-label="' . htmlspecialchars($args['ariaLabel']) . '"';
    }

    if ($args['disabled']) {
        $attrs[] = 'disabled';
        $attrs[] = 'aria-disabled="true"';
    }

    if ($args['onclick']) {
        $attrs[] = 'onclick="' . htmlspecialchars($args['onclick']) . '"';
    }

    $attrsStr = implode(' ', $attrs);

    // Render as link or button
    if ($args['href'] && !$args['disabled']) {
        return '<a href="' . htmlspecialchars($args['href']) . '" ' . $attrsStr . ' role="button">' .
               htmlspecialchars($args['text']) .
               '</a>';
    } else {
        return '<button type="button" ' . $attrsStr . '>' .
               htmlspecialchars($args['text']) .
               '</button>';
    }
}
