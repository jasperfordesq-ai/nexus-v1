<?php
/**
 * GOV.UK Select Component
 * Reusable select dropdown following GOV.UK Design System v5.14.0
 *
 * @param string $name - Input name attribute
 * @param string $id - Input ID (defaults to name)
 * @param array $items - Array of options with 'value', 'text', 'selected' (optional), 'disabled' (optional)
 * @param string $label - Label text
 * @param string $hint - Hint text (optional)
 * @param string $error - Error message (optional)
 * @param string $value - Selected value
 * @param bool $required - Whether input is required
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/select.php'; echo civicone_govuk_select([
 *     'name' => 'sort',
 *     'label' => 'Sort by',
 *     'items' => [
 *         ['value' => '', 'text' => 'Select an option'],
 *         ['value' => 'date', 'text' => 'Date'],
 *         ['value' => 'name', 'text' => 'Name'],
 *     ]
 * ]); ?>
 */

function civicone_govuk_select($args = []) {
    $defaults = [
        'name' => '',
        'id' => '',
        'items' => [],
        'label' => '',
        'hint' => '',
        'error' => '',
        'value' => '',
        'required' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    // Generate ID if not provided
    if (empty($args['id'])) {
        $args['id'] = $args['name'];
    }

    $hasError = !empty($args['error']);
    $hintId = $args['id'] . '-hint';
    $errorId = $args['id'] . '-error';

    // Build form group classes
    $formGroupClasses = ['govuk-form-group'];
    if ($hasError) {
        $formGroupClasses[] = 'govuk-form-group--error';
    }

    // Build select classes
    $selectClasses = ['govuk-select'];
    if ($hasError) {
        $selectClasses[] = 'govuk-select--error';
    }
    if (!empty($args['class'])) {
        $selectClasses[] = $args['class'];
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
        $html .= '<div class="govuk-hint" id="' . htmlspecialchars($hintId) . '">';
        $html .= htmlspecialchars($args['hint']);
        $html .= '</div>';
    }

    // Error message
    if ($hasError) {
        $html .= '<p class="govuk-error-message" id="' . htmlspecialchars($errorId) . '">';
        $html .= '<span class="govuk-visually-hidden">Error:</span> ';
        $html .= htmlspecialchars($args['error']);
        $html .= '</p>';
    }

    // Select
    $html .= '<select class="' . implode(' ', $selectClasses) . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '"';

    if ($args['required']) {
        $html .= ' required';
    }

    // Add aria-describedby for hint and error
    $describedBy = [];
    if (!empty($args['hint'])) {
        $describedBy[] = $hintId;
    }
    if ($hasError) {
        $describedBy[] = $errorId;
    }
    if (!empty($describedBy)) {
        $html .= ' aria-describedby="' . implode(' ', $describedBy) . '"';
    }

    $html .= '>';

    // Options
    foreach ($args['items'] as $item) {
        $html .= '<option value="' . htmlspecialchars($item['value'] ?? '') . '"';

        // Check if selected
        $isSelected = !empty($item['selected']) || ($args['value'] !== '' && $args['value'] === ($item['value'] ?? ''));
        if ($isSelected) {
            $html .= ' selected';
        }

        if (!empty($item['disabled'])) {
            $html .= ' disabled';
        }

        $html .= '>' . htmlspecialchars($item['text'] ?? $item['value'] ?? '') . '</option>';
    }

    $html .= '</select>';
    $html .= '</div>';

    return $html;
}
