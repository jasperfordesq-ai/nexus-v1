<?php
/**
 * GOV.UK Confirmation Page Pattern
 * Reusable confirmation page wrapper following GOV.UK Design System v5.14.0
 *
 * Pattern: Success/completion page with reference number
 * Reference: https://design-system.service.gov.uk/patterns/confirmation-pages/
 *
 * @param string $title - Panel title (e.g., "Application complete")
 * @param string $reference - Reference number/code to display
 * @param string $referenceLabel - Label for reference (default: "Your reference number")
 * @param string $emailConfirmation - Email confirmation message (optional)
 * @param string $whatHappensNextTitle - Title for next steps section (default: "What happens next")
 * @param string $whatHappensNextContent - HTML content for what happens next
 * @param array $nextSteps - Array of next step links with 'text' and 'href'
 * @param string $feedbackLink - Link to feedback form (optional)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/confirmation-page.php'; echo civicone_govuk_confirmation_page([
 *     'title' => 'Application complete',
 *     'reference' => 'HDJ2123F',
 *     'whatHappensNextContent' => '<p>We have sent you a confirmation email.</p><p>We will contact you within 5 working days.</p>',
 *     'nextSteps' => [
 *         ['text' => 'Return to homepage', 'href' => '/'],
 *         ['text' => 'Give feedback', 'href' => '/feedback']
 *     ]
 * ]); ?>
 */

function civicone_govuk_confirmation_page($args = []) {
    $defaults = [
        'title' => 'Complete',
        'reference' => '',
        'referenceLabel' => 'Your reference number',
        'emailConfirmation' => '',
        'whatHappensNextTitle' => 'What happens next',
        'whatHappensNextContent' => '',
        'nextSteps' => [],
        'feedbackLink' => '',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-confirmation-page'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '">';

    // Main content wrapper
    $html .= '<main class="govuk-main-wrapper" id="main-content" role="main">';
    $html .= '<div class="govuk-grid-row">';
    $html .= '<div class="govuk-grid-column-two-thirds">';

    // Success panel
    $html .= '<div class="govuk-panel govuk-panel--confirmation">';
    $html .= '<h1 class="govuk-panel__title">' . htmlspecialchars($args['title']) . '</h1>';

    if (!empty($args['reference'])) {
        $html .= '<div class="govuk-panel__body">';
        $html .= htmlspecialchars($args['referenceLabel']) . '<br>';
        $html .= '<strong>' . htmlspecialchars($args['reference']) . '</strong>';
        $html .= '</div>';
    }

    $html .= '</div>';

    // Email confirmation message
    if (!empty($args['emailConfirmation'])) {
        $html .= '<p class="govuk-body">' . htmlspecialchars($args['emailConfirmation']) . '</p>';
    }

    // What happens next section
    if (!empty($args['whatHappensNextContent'])) {
        $html .= '<h2 class="govuk-heading-m">' . htmlspecialchars($args['whatHappensNextTitle']) . '</h2>';
        $html .= '<div class="govuk-body">' . $args['whatHappensNextContent'] . '</div>';
    }

    // Next steps links
    if (!empty($args['nextSteps'])) {
        $html .= '<h2 class="govuk-heading-m">What you can do now</h2>';
        $html .= '<ul class="govuk-list">';
        foreach ($args['nextSteps'] as $step) {
            $html .= '<li>';
            $html .= '<a class="govuk-link" href="' . htmlspecialchars($step['href'] ?? '#') . '">';
            $html .= htmlspecialchars($step['text'] ?? '');
            $html .= '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    }

    // Feedback link
    if (!empty($args['feedbackLink'])) {
        $html .= '<p class="govuk-body">';
        $html .= '<a class="govuk-link" href="' . htmlspecialchars($args['feedbackLink']) . '">';
        $html .= 'What did you think of this service?';
        $html .= '</a> (takes 30 seconds)';
        $html .= '</p>';
    }

    $html .= '</div>'; // grid-column
    $html .= '</div>'; // grid-row
    $html .= '</main>';
    $html .= '</div>'; // confirmation-page

    return $html;
}
