<?php
/**
 * GOV.UK Error Summary Component
 * WCAG 3.3.1 Error Identification (Level A) - CRITICAL REQUIREMENT
 * Source: https://design-system.service.gov.uk/components/error-summary/
 *
 * @param string $title - Summary title (default: "There is a problem")
 * @param string $description - Optional description text
 * @param array $errors - Array of errors, each with 'text' and 'href' to error field
 * @param string $id - Element ID (default: "error-summary")
 *
 * Usage:
 * <?php include __DIR__ . '/error-summary.php';
 * echo civicone_govuk_error_summary([
 *     'errors' => [
 *         ['text' => 'Enter your email address', 'href' => '#email-input'],
 *         ['text' => 'Enter a password', 'href' => '#password-input']
 *     ]
 * ]);
 * ?>
 *
 * IMPORTANT: Must be placed at top of <main> and receive focus on page load if errors exist
 */

function civicone_govuk_error_summary($args = []) {
    $defaults = [
        'title' => 'There is a problem',
        'description' => '',
        'errors' => [],
        'id' => 'error-summary'
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['errors'])) {
        return '';
    }

    $html = '<div class="govuk-error-summary" data-module="govuk-error-summary" role="alert" ';
    $html .= 'tabindex="-1" aria-labelledby="error-summary-title" id="' . htmlspecialchars($args['id']) . '">';

    // Title
    $html .= '<h2 class="govuk-error-summary__title" id="error-summary-title">';
    $html .= htmlspecialchars($args['title']);
    $html .= '</h2>';

    // Body
    $html .= '<div class="govuk-error-summary__body">';

    // Optional description
    if (!empty($args['description'])) {
        $html .= '<p>' . htmlspecialchars($args['description']) . '</p>';
    }

    // Error list
    $html .= '<ul class="govuk-error-summary__list">';
    foreach ($args['errors'] as $error) {
        $html .= '<li>';
        if (!empty($error['href'])) {
            $html .= '<a href="' . htmlspecialchars($error['href']) . '">';
            $html .= htmlspecialchars($error['text']);
            $html .= '</a>';
        } else {
            $html .= htmlspecialchars($error['text']);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';

    $html .= '</div>'; // .govuk-error-summary__body
    $html .= '</div>'; // .govuk-error-summary

    return $html;
}
