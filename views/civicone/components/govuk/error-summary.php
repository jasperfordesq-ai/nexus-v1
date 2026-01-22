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

    // Include error summary JS (once per page)
    static $jsIncluded = false;
    if (!$jsIncluded) {
        $html .= '<script src="/assets/js/civicone-error-summary.js" defer></script>';
        $jsIncluded = true;
    }

    return $html;
}

/**
 * Helper function to convert simple field => message array to GOV.UK error format
 *
 * @param array $errors - Simple array like ['email' => 'Enter your email']
 * @param string $fieldPrefix - Prefix for field IDs (default: empty)
 * @return array - Formatted for civicone_govuk_error_summary
 *
 * Usage:
 * $errors = ['email' => 'Enter your email', 'password' => 'Enter a password'];
 * echo civicone_govuk_error_summary([
 *     'errors' => civicone_format_errors($errors)
 * ]);
 */
function civicone_format_errors($errors, $fieldPrefix = '') {
    $formatted = [];
    foreach ($errors as $field => $message) {
        $formatted[] = [
            'text' => $message,
            'href' => '#' . $fieldPrefix . $field
        ];
    }
    return $formatted;
}

/**
 * Shorthand helper for quick error summary from validation errors
 *
 * @param array $errors - Simple array like ['email' => 'Enter your email']
 * @return string - HTML for error summary (empty string if no errors)
 *
 * Usage in PHP templates:
 * <?= civicone_error_summary_from_array($errors ?? []) ?>
 */
function civicone_error_summary_from_array($errors) {
    if (empty($errors)) {
        return '';
    }
    return civicone_govuk_error_summary([
        'errors' => civicone_format_errors($errors)
    ]);
}
