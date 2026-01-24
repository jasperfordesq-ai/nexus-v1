<?php
/**
 * GOV.UK Back Link Component
 * Reusable back link following GOV.UK Design System v5.14.0
 *
 * @param string $href - Link URL (default: javascript:history.back())
 * @param string $text - Link text (default: 'Back')
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/back-link.php'; echo civicone_govuk_back_link(['href' => '/previous-page']); ?>
 */

function civicone_govuk_back_link($args = []) {
    $defaults = [
        'href' => 'javascript:history.back()',
        'text' => 'Back',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-back-link'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<a href="' . htmlspecialchars($args['href']) . '" ';
    $html .= 'class="' . implode(' ', $classes) . '">';
    $html .= htmlspecialchars($args['text']);
    $html .= '</a>';

    return $html;
}
