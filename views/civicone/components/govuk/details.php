<?php
/**
 * GOV.UK Details Component
 * Expandable section for progressive disclosure
 * Source: https://design-system.service.gov.uk/components/details/
 *
 * @param string $summary - Summary text (always visible)
 * @param string $text - Content text (revealed when expanded)
 * @param bool $open - Whether section starts open
 * @param string $id - Optional ID
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/details.php';
 * echo civicone_govuk_details([
 *     'summary' => 'Help with event dates',
 *     'text' => 'Events can be scheduled up to 6 months in advance.'
 * ]);
 * ?>
 */

function civicone_govuk_details($args = []) {
    $defaults = [
        'summary' => '',
        'text' => '',
        'open' => false,
        'id' => '',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-details'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<details class="' . implode(' ', $classes) . '"';
    if (!empty($args['id'])) {
        $html .= ' id="' . htmlspecialchars($args['id']) . '"';
    }
    if ($args['open']) {
        $html .= ' open';
    }
    $html .= '>';

    // Summary (always visible)
    $html .= '<summary class="govuk-details__summary">';
    $html .= '<span class="govuk-details__summary-text">';
    $html .= htmlspecialchars($args['summary']);
    $html .= '</span>';
    $html .= '</summary>';

    // Content (revealed when expanded)
    $html .= '<div class="govuk-details__text">';
    $html .= htmlspecialchars($args['text']);
    $html .= '</div>';

    $html .= '</details>';

    return $html;
}
