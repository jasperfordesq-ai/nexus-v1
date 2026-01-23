<?php
/**
 * GOV.UK Checkboxes Component
 * Reusable checkboxes following GOV.UK Design System v5.14.0
 *
 * @param string $name - Input name attribute
 * @param array $items - Array of checkbox items with 'value', 'label', 'hint' (optional), 'checked' (optional)
 * @param string $legend - Fieldset legend text
 * @param string $legendSize - Legend size: 'xl', 'l', 'm', 's' (default: 'm')
 * @param string $hint - Overall hint text for the group
 * @param string $error - Error message
 * @param bool $small - Use small variant
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/checkboxes.php'; echo civicone_govuk_checkboxes([
 *     'name' => 'contact',
 *     'legend' => 'How would you like to be contacted?',
 *     'items' => [
 *         ['value' => 'email', 'label' => 'Email'],
 *         ['value' => 'phone', 'label' => 'Phone', 'hint' => 'We will call between 9am and 5pm'],
 *     ]
 * ]); ?>
 */

function civicone_govuk_checkboxes($args = []) {
    $defaults = [
        'name' => '',
        'items' => [],
        'legend' => '',
        'legendSize' => 'm',
        'hint' => '',
        'error' => '',
        'small' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['items'])) {
        return '';
    }

    $hasError = !empty($args['error']);
    $groupId = $args['name'] . '-group';
    $hintId = $args['name'] . '-hint';
    $errorId = $args['name'] . '-error';

    // Form group classes
    $formGroupClasses = ['govuk-form-group'];
    if ($hasError) {
        $formGroupClasses[] = 'govuk-form-group--error';
    }

    // Checkboxes container classes
    $checkboxesClasses = ['govuk-checkboxes'];
    if ($args['small']) {
        $checkboxesClasses[] = 'govuk-checkboxes--small';
    }
    if (!empty($args['class'])) {
        $checkboxesClasses[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $formGroupClasses) . '">';

    // Fieldset
    $html .= '<fieldset class="govuk-fieldset" aria-describedby="';
    $describedBy = [];
    if (!empty($args['hint'])) {
        $describedBy[] = $hintId;
    }
    if ($hasError) {
        $describedBy[] = $errorId;
    }
    $html .= implode(' ', $describedBy) . '">';

    // Legend
    if (!empty($args['legend'])) {
        $legendClasses = ['govuk-fieldset__legend'];
        if (in_array($args['legendSize'], ['xl', 'l', 'm', 's'])) {
            $legendClasses[] = 'govuk-fieldset__legend--' . $args['legendSize'];
        }
        $html .= '<legend class="' . implode(' ', $legendClasses) . '">';
        $html .= htmlspecialchars($args['legend']);
        $html .= '</legend>';
    }

    // Hint
    if (!empty($args['hint'])) {
        $html .= '<div id="' . htmlspecialchars($hintId) . '" class="govuk-hint">';
        $html .= htmlspecialchars($args['hint']);
        $html .= '</div>';
    }

    // Error message
    if ($hasError) {
        $html .= '<p id="' . htmlspecialchars($errorId) . '" class="govuk-error-message">';
        $html .= '<span class="govuk-visually-hidden">Error:</span> ';
        $html .= htmlspecialchars($args['error']);
        $html .= '</p>';
    }

    // Checkboxes
    $html .= '<div class="' . implode(' ', $checkboxesClasses) . '" data-module="govuk-checkboxes">';

    foreach ($args['items'] as $index => $item) {
        $itemId = $args['name'] . '-' . ($index + 1);
        $itemHintId = $itemId . '-hint';

        $html .= '<div class="govuk-checkboxes__item">';

        // Input
        $html .= '<input class="govuk-checkboxes__input" ';
        $html .= 'id="' . htmlspecialchars($itemId) . '" ';
        $html .= 'name="' . htmlspecialchars($args['name']) . '[]" ';
        $html .= 'type="checkbox" ';
        $html .= 'value="' . htmlspecialchars($item['value'] ?? '') . '"';

        if (!empty($item['checked'])) {
            $html .= ' checked';
        }

        if (!empty($item['hint'])) {
            $html .= ' aria-describedby="' . htmlspecialchars($itemHintId) . '"';
        }

        $html .= '>';

        // Label
        $html .= '<label class="govuk-label govuk-checkboxes__label" for="' . htmlspecialchars($itemId) . '">';
        $html .= htmlspecialchars($item['label'] ?? '');
        $html .= '</label>';

        // Item hint
        if (!empty($item['hint'])) {
            $html .= '<div id="' . htmlspecialchars($itemHintId) . '" class="govuk-hint govuk-checkboxes__hint">';
            $html .= htmlspecialchars($item['hint']);
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>'; // checkboxes
    $html .= '</fieldset>';
    $html .= '</div>'; // form-group

    return $html;
}
