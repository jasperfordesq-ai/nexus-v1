<?php
/**
 * GOV.UK Phase Banner Component
 * Identifies the current phase of service development (alpha, beta, live)
 * Source: https://design-system.service.gov.uk/components/phase-banner/
 *
 * @param string $phase - 'alpha', 'beta', or custom text
 * @param string $text - Descriptive text with feedback link
 * @param string $feedbackHref - URL for feedback link (optional)
 * @param string $feedbackText - Text for feedback link (default: 'feedback')
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/phase-banner.php';
 * echo civicone_govuk_phase_banner([
 *     'phase' => 'beta',
 *     'feedbackHref' => '/feedback'
 * ]);
 * ?>
 *
 * Or with custom text:
 * echo civicone_govuk_phase_banner([
 *     'phase' => 'beta',
 *     'text' => 'This is a new service. Help us improve it by providing <a href="/feedback">your feedback</a>.'
 * ]);
 */

function civicone_govuk_phase_banner($args = []) {
    $defaults = [
        'phase' => 'beta',
        'text' => null,
        'feedbackHref' => null,
        'feedbackText' => 'feedback',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    // Build default text if not provided
    if ($args['text'] === null) {
        $args['text'] = 'This is a new service – your ';
        if ($args['feedbackHref']) {
            $args['text'] .= '<a class="govuk-link" href="' . htmlspecialchars($args['feedbackHref']) . '">';
            $args['text'] .= htmlspecialchars($args['feedbackText']);
            $args['text'] .= '</a>';
        } else {
            $args['text'] .= htmlspecialchars($args['feedbackText']);
        }
        $args['text'] .= ' will help us to improve it.';
    }

    $classes = ['govuk-phase-banner'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '">';
    $html .= '<p class="govuk-phase-banner__content">';

    // Phase tag
    $html .= '<strong class="govuk-tag govuk-phase-banner__content__tag">';
    $html .= htmlspecialchars(strtoupper($args['phase']));
    $html .= '</strong>';

    // Text (can contain HTML for links)
    $html .= '<span class="govuk-phase-banner__text">';
    $html .= $args['text'];
    $html .= '</span>';

    $html .= '</p>';
    $html .= '</div>';

    return $html;
}

/**
 * Helper to generate standard beta banner for Project Nexus
 */
function civicone_govuk_beta_banner($feedbackHref = '/feedback') {
    return civicone_govuk_phase_banner([
        'phase' => 'beta',
        'feedbackHref' => $feedbackHref
    ]);
}
