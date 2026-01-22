<?php
/**
 * GOV.UK Tag Component
 * Status indicators and phase banners
 * Source: https://design-system.service.gov.uk/components/tag/
 *
 * @param string $text - Tag text
 * @param string $color - Color variant: 'grey', 'green', 'red', 'yellow', or default blue
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/tag.php'; echo civicone_govuk_tag(['text' => 'Active', 'color' => 'green']); ?>
 */

function civicone_govuk_tag($args = []) {
    $defaults = [
        'text' => '',
        'color' => '', // '', 'grey', 'green', 'red', 'yellow'
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-tag'];

    // Add color modifier class
    if (!empty($args['color'])) {
        $classes[] = 'govuk-tag--' . $args['color'];
    }

    // Add custom classes
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $classStr = implode(' ', $classes);

    return '<span class="' . htmlspecialchars($classStr) . '">' .
           htmlspecialchars($args['text']) .
           '</span>';
}
