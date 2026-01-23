<?php
/**
 * GOV.UK Inset Text Component
 * Reusable inset text following GOV.UK Design System v5.14.0
 * Used to differentiate a block of text from surrounding content
 *
 * @param string $text - The inset text content (can contain HTML)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/inset-text.php'; echo civicone_govuk_inset_text([
 *     'text' => 'It can take up to 8 weeks to register a lasting power of attorney if there are no mistakes in the application.'
 * ]); ?>
 */

function civicone_govuk_inset_text($args = []) {
    $defaults = [
        'text' => '',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['text'])) {
        return '';
    }

    $classes = ['govuk-inset-text'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '">';
    $html .= $args['text']; // Allows HTML content
    $html .= '</div>';

    return $html;
}
