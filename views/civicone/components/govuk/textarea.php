<?php
/**
 * GOV.UK Textarea Component
 * Reusable textarea following GOV.UK Design System v5.14.0
 *
 * @param string $name - Input name attribute
 * @param string $id - Input ID (defaults to name)
 * @param string $value - Current value
 * @param string $label - Label text
 * @param string $hint - Hint text (optional)
 * @param string $error - Error message (optional)
 * @param int $rows - Number of visible rows (default: 5)
 * @param bool $required - Whether input is required
 * @param int $maxlength - Maximum character length (optional)
 * @param int $threshold - Character count warning threshold percentage (optional, requires maxlength)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/textarea.php'; echo civicone_govuk_textarea([
 *     'name' => 'description',
 *     'label' => 'Describe your issue',
 *     'hint' => 'Do not include personal information',
 *     'rows' => 8,
 *     'maxlength' => 500
 * ]); ?>
 */

function civicone_govuk_textarea($args = []) {
    $defaults = [
        'name' => '',
        'id' => '',
        'value' => '',
        'label' => '',
        'hint' => '',
        'error' => '',
        'rows' => 5,
        'required' => false,
        'maxlength' => null,
        'threshold' => null,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    // Generate ID if not provided
    if (empty($args['id'])) {
        $args['id'] = $args['name'];
    }

    $hasError = !empty($args['error']);
    $hasCharacterCount = !empty($args['maxlength']);

    // Build form group classes
    $formGroupClasses = ['govuk-form-group'];
    if ($hasError) {
        $formGroupClasses[] = 'govuk-form-group--error';
    }

    // Build textarea classes
    $textareaClasses = ['govuk-textarea'];
    if ($hasError) {
        $textareaClasses[] = 'govuk-textarea--error';
    }
    if ($hasCharacterCount) {
        $textareaClasses[] = 'govuk-js-character-count';
    }
    if (!empty($args['class'])) {
        $textareaClasses[] = $args['class'];
    }

    // Start output
    $html = '';

    // Wrap in character count div if maxlength specified
    if ($hasCharacterCount) {
        $html .= '<div class="govuk-character-count" data-module="govuk-character-count" data-maxlength="' . intval($args['maxlength']) . '"';
        if (!empty($args['threshold'])) {
            $html .= ' data-threshold="' . intval($args['threshold']) . '"';
        }
        $html .= '>';
    }

    $html .= '<div class="' . implode(' ', $formGroupClasses) . '">';

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
        $html .= '<span class="govuk-visually-hidden">Error:</span> ';
        $html .= htmlspecialchars($args['error']);
        $html .= '</p>';
    }

    // Textarea
    $html .= '<textarea class="' . implode(' ', $textareaClasses) . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '" ';
    $html .= 'rows="' . intval($args['rows']) . '"';

    if ($args['required']) {
        $html .= ' required';
    }

    if ($hasCharacterCount) {
        $html .= ' aria-describedby="' . htmlspecialchars($args['id']) . '-info';
        if (!empty($args['hint'])) {
            $html .= ' ' . htmlspecialchars($args['id']) . '-hint';
        }
        if ($hasError) {
            $html .= ' ' . htmlspecialchars($args['id']) . '-error';
        }
        $html .= '"';
    } else {
        // Add aria-describedby for hint and error
        $describedBy = [];
        if (!empty($args['hint'])) {
            $describedBy[] = htmlspecialchars($args['id']) . '-hint';
        }
        if ($hasError) {
            $describedBy[] = htmlspecialchars($args['id']) . '-error';
        }
        if (!empty($describedBy)) {
            $html .= ' aria-describedby="' . implode(' ', $describedBy) . '"';
        }
    }

    $html .= '>';
    $html .= htmlspecialchars($args['value']);
    $html .= '</textarea>';

    $html .= '</div>'; // form-group

    // Character count message
    if ($hasCharacterCount) {
        $html .= '<div id="' . htmlspecialchars($args['id']) . '-info" class="govuk-hint govuk-character-count__message">';
        $html .= 'You can enter up to ' . intval($args['maxlength']) . ' characters';
        $html .= '</div>';
        $html .= '</div>'; // character-count
    }

    return $html;
}
