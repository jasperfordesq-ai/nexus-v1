<?php
/**
 * GOV.UK Warning Text Component
 * Important notices and warnings
 * Source: https://design-system.service.gov.uk/components/warning-text/
 *
 * @param string $text - Warning message text
 * @param string $iconFallback - Fallback text for icon (default: "Warning")
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/warning-text.php';
 * echo civicone_govuk_warning_text([
 *     'text' => 'You must register at least 7 days before the event.'
 * ]);
 * ?>
 */

function civicone_govuk_warning_text($args = []) {
    $defaults = [
        'text' => '',
        'iconFallback' => 'Warning',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-warning-text'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '">';

    // Warning icon
    $html .= '<span class="govuk-warning-text__icon" aria-hidden="true">!</span>';

    // Warning text
    $html .= '<strong class="govuk-warning-text__text">';
    $html .= '<span class="govuk-warning-text__assistive">' . htmlspecialchars($args['iconFallback']) . '</span>';
    $html .= htmlspecialchars($args['text']);
    $html .= '</strong>';

    $html .= '</div>';

    return $html;
}
