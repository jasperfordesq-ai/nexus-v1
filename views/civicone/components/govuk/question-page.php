<?php
/**
 * GOV.UK Question Page Pattern
 * Reusable question page wrapper following GOV.UK Design System v5.14.0
 *
 * Pattern: Single question per page for step-by-step forms
 * Reference: https://design-system.service.gov.uk/patterns/question-pages/
 *
 * @param string $backLink - URL for back link (optional, default: javascript:history.back())
 * @param string $backText - Text for back link (optional, default: 'Back')
 * @param string $pageTitle - H1 page title / question (required)
 * @param string $hint - Hint text below title (optional)
 * @param string $content - Form content (HTML)
 * @param string $buttonText - Submit button text (default: 'Continue')
 * @param string $buttonHref - If set, renders button as link instead of submit
 * @param string $formAction - Form action URL (optional)
 * @param string $formMethod - Form method (default: 'post')
 * @param array $errors - Array of errors for error summary (optional)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/question-page.php'; echo civicone_govuk_question_page([
 *     'backLink' => '/previous-step',
 *     'pageTitle' => 'What is your name?',
 *     'hint' => 'Enter your full name as shown on your passport',
 *     'content' => $formFieldsHtml,
 *     'buttonText' => 'Continue',
 *     'formAction' => '/submit-name'
 * ]); ?>
 */

function civicone_govuk_question_page($args = []) {
    $defaults = [
        'backLink' => 'javascript:history.back()',
        'backText' => 'Back',
        'pageTitle' => '',
        'hint' => '',
        'content' => '',
        'buttonText' => 'Continue',
        'buttonHref' => null,
        'formAction' => '',
        'formMethod' => 'post',
        'errors' => [],
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-question-page'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '">';

    // Back link
    $html .= '<a href="' . htmlspecialchars($args['backLink']) . '" class="govuk-back-link">';
    $html .= htmlspecialchars($args['backText']);
    $html .= '</a>';

    // Main content wrapper
    $html .= '<main class="govuk-main-wrapper" id="main-content" role="main">';
    $html .= '<div class="govuk-grid-row">';
    $html .= '<div class="govuk-grid-column-two-thirds">';

    // Error summary (if errors exist)
    if (!empty($args['errors'])) {
        $html .= '<div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1" role="alert" aria-labelledby="error-summary-title">';
        $html .= '<h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>';
        $html .= '<div class="govuk-error-summary__body">';
        $html .= '<ul class="govuk-error-summary__list">';
        foreach ($args['errors'] as $error) {
            $errorId = $error['id'] ?? '#';
            $errorText = $error['text'] ?? $error;
            $html .= '<li><a href="#' . htmlspecialchars($errorId) . '">' . htmlspecialchars($errorText) . '</a></li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
    }

    // Form wrapper (if action provided)
    if (!empty($args['formAction'])) {
        $html .= '<form action="' . htmlspecialchars($args['formAction']) . '" method="' . htmlspecialchars($args['formMethod']) . '" novalidate>';
    }

    // Page title (the question)
    $html .= '<h1 class="govuk-heading-xl">' . htmlspecialchars($args['pageTitle']) . '</h1>';

    // Hint text
    if (!empty($args['hint'])) {
        $html .= '<p class="govuk-body-l">' . htmlspecialchars($args['hint']) . '</p>';
    }

    // Form content
    $html .= $args['content'];

    // Submit button
    if (!empty($args['buttonHref'])) {
        $html .= '<a href="' . htmlspecialchars($args['buttonHref']) . '" class="govuk-button" data-module="govuk-button">';
        $html .= htmlspecialchars($args['buttonText']);
        $html .= '</a>';
    } else {
        $html .= '<button type="submit" class="govuk-button" data-module="govuk-button">';
        $html .= htmlspecialchars($args['buttonText']);
        $html .= '</button>';
    }

    // Close form
    if (!empty($args['formAction'])) {
        $html .= '</form>';
    }

    $html .= '</div>'; // grid-column
    $html .= '</div>'; // grid-row
    $html .= '</main>';
    $html .= '</div>'; // question-page

    return $html;
}
