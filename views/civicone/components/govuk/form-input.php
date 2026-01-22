<?php
/**
 * GOV.UK Form Input Component
 * Reusable text input following GOV.UK Design System v5.14.0
 *
 * @param string $name - Input name attribute
 * @param string $id - Input ID (defaults to name)
 * @param string $value - Input value
 * @param string $label - Label text
 * @param string $hint - Hint text (optional)
 * @param string $error - Error message (optional)
 * @param string $type - Input type (text, email, password, tel, etc.)
 * @param string $width - Width class: '30', '20', '10', '5', '4', '3', '2'
 * @param bool $required - Whether input is required
 * @param string $autocomplete - Autocomplete attribute
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/form-input.php'; echo civicone_govuk_input(['name' => 'email', 'label' => 'Email address', 'type' => 'email']); ?>
 */

function civicone_govuk_input($args = []) {
    $defaults = [
        'name' => '',
        'id' => '',
        'value' => '',
        'label' => '',
        'hint' => '',
        'error' => '',
        'type' => 'text',
        'width' => null,
        'required' => false,
        'autocomplete' => '',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    // Generate ID if not provided
    if (empty($args['id'])) {
        $args['id'] = $args['name'];
    }

    $hasError = !empty($args['error']);

    // Build form group classes
    $formGroupClasses = ['govuk-form-group'];
    if ($hasError) {
        $formGroupClasses[] = 'govuk-form-group--error';
    }

    // Build input classes
    $inputClasses = ['govuk-input'];
    if ($hasError) {
        $inputClasses[] = 'govuk-input--error';
    }
    if ($args['width']) {
        $inputClasses[] = 'govuk-input--width-' . $args['width'];
    }
    if (!empty($args['class'])) {
        $inputClasses[] = $args['class'];
    }

    // Build output HTML
    $html = '<div class="' . implode(' ', $formGroupClasses) . '">';

    // Label
    if (!empty($args['label'])) {
        $html .= '<label class="govuk-label" for="' . htmlspecialchars($args['id']) . '">';
        $html .= htmlspecialchars($args['label']);
        if ($args['required']) {
            $html .= ' <span class="govuk-visually-hidden">(required)</span>';
        }
        $html .= '</label>';
    }

    // Hint
    if (!empty($args['hint'])) {
        $html .= '<div class="govuk-hint" id="' . htmlspecialchars($args['id']) . '-hint">';
        $html .= htmlspecialchars($args['hint']);
        $html .= '</div>';
    }

    // Error message
    if ($hasError) {
        $html .= '<p class="govuk-error-message" id="' . htmlspecialchars($args['id']) . '-error">';
        $html .= htmlspecialchars($args['error']);
        $html .= '</p>';
    }

    // Input
    $html .= '<input class="' . implode(' ', $inputClasses) . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '" ';
    $html .= 'type="' . htmlspecialchars($args['type']) . '" ';

    if (!empty($args['value'])) {
        $html .= 'value="' . htmlspecialchars($args['value']) . '" ';
    }

    if ($args['required']) {
        $html .= 'required ';
    }

    if (!empty($args['autocomplete'])) {
        $html .= 'autocomplete="' . htmlspecialchars($args['autocomplete']) . '" ';
    }

    // Add aria-describedby for hint and error
    $describedBy = [];
    if (!empty($args['hint'])) {
        $describedBy[] = htmlspecialchars($args['id']) . '-hint';
    }
    if ($hasError) {
        $describedBy[] = htmlspecialchars($args['id']) . '-error';
    }
    if (!empty($describedBy)) {
        $html .= 'aria-describedby="' . implode(' ', $describedBy) . '" ';
    }

    $html .= '/>';

    $html .= '</div>';

    return $html;
}
