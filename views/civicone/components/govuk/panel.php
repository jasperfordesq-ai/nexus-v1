<?php
/**
 * GOV.UK Panel Component
 * Reusable panel following GOV.UK Design System v5.14.0
 * Used to highlight important information, often on confirmation pages
 *
 * @param string $title - Panel title (required)
 * @param string $body - Panel body text (optional)
 * @param string $headingLevel - Heading level: 1, 2, 3 (default: 1)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/panel.php'; echo civicone_govuk_panel([
 *     'title' => 'Application complete',
 *     'body' => 'Your reference number<br><strong>HDJ2123F</strong>'
 * ]); ?>
 */

function civicone_govuk_panel($args = []) {
    $defaults = [
        'title' => '',
        'body' => '',
        'headingLevel' => 1,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['title'])) {
        return '';
    }

    $classes = ['govuk-panel', 'govuk-panel--confirmation'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $headingTag = 'h' . max(1, min(6, intval($args['headingLevel'])));

    $html = '<div class="' . implode(' ', $classes) . '">';

    // Title
    $html .= '<' . $headingTag . ' class="govuk-panel__title">';
    $html .= htmlspecialchars($args['title']);
    $html .= '</' . $headingTag . '>';

    // Body (allows HTML)
    if (!empty($args['body'])) {
        $html .= '<div class="govuk-panel__body">';
        $html .= $args['body'];
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
