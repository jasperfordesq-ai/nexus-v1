<?php
/**
 * GOV.UK Date Input Component
 * Three text inputs for day, month, year
 * Source: https://design-system.service.gov.uk/components/date-input/
 *
 * @param string $name - Base name for inputs (e.g., "dob" creates dob[day], dob[month], dob[year])
 * @param string $id - Base ID for inputs
 * @param array $value - Associative array with 'day', 'month', 'year' keys
 * @param string $label - Label text
 * @param string $hint - Hint text
 * @param string $error - Error message
 * @param bool $required - Whether field is required
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/date-input.php';
 * echo civicone_govuk_date_input([
 *     'name' => 'event_date',
 *     'id' => 'event-date',
 *     'label' => 'Event date',
 *     'hint' => 'For example, 27 3 2026',
 *     'value' => ['day' => '', 'month' => '', 'year' => '']
 * ]);
 * ?>
 */

function civicone_govuk_date_input($args = []) {
    $defaults = [
        'name' => '',
        'id' => '',
        'value' => ['day' => '', 'month' => '', 'year' => ''],
        'label' => '',
        'hint' => '',
        'error' => '',
        'required' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $hasError = !empty($args['error']);
    $formGroupClasses = ['govuk-form-group'];
    if ($hasError) {
        $formGroupClasses[] = 'govuk-form-group--error';
    }

    $html = '<div class="' . implode(' ', $formGroupClasses) . '">';

    // Fieldset with legend
    $html .= '<fieldset class="govuk-fieldset" role="group"';

    $describedBy = [];
    if (!empty($args['hint'])) {
        $describedBy[] = $args['id'] . '-hint';
    }
    if ($hasError) {
        $describedBy[] = $args['id'] . '-error';
    }
    if (!empty($describedBy)) {
        $html .= ' aria-describedby="' . implode(' ', $describedBy) . '"';
    }
    $html .= '>';

    // Legend (label)
    if (!empty($args['label'])) {
        $html .= '<legend class="govuk-fieldset__legend govuk-fieldset__legend--m">';
        $html .= htmlspecialchars($args['label']);
        if ($args['required']) {
            $html .= ' <span class="govuk-visually-hidden">(required)</span>';
        }
        $html .= '</legend>';
    }

    // Hint text
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

    // Date input container
    $html .= '<div class="govuk-date-input" id="' . htmlspecialchars($args['id']) . '">';

    // Day input
    $html .= '<div class="govuk-date-input__item">';
    $html .= '<div class="govuk-form-group">';
    $html .= '<label class="govuk-label govuk-date-input__label" for="' . htmlspecialchars($args['id']) . '-day">Day</label>';
    $html .= '<input class="govuk-input govuk-date-input__input govuk-input--width-2' . ($hasError ? ' govuk-input--error' : '') . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '-day" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '[day]" ';
    $html .= 'type="text" inputmode="numeric" pattern="[0-9]*" ';
    $html .= 'value="' . htmlspecialchars($args['value']['day'] ?? '') . '">';
    $html .= '</div>';
    $html .= '</div>';

    // Month input
    $html .= '<div class="govuk-date-input__item">';
    $html .= '<div class="govuk-form-group">';
    $html .= '<label class="govuk-label govuk-date-input__label" for="' . htmlspecialchars($args['id']) . '-month">Month</label>';
    $html .= '<input class="govuk-input govuk-date-input__input govuk-input--width-2' . ($hasError ? ' govuk-input--error' : '') . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '-month" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '[month]" ';
    $html .= 'type="text" inputmode="numeric" pattern="[0-9]*" ';
    $html .= 'value="' . htmlspecialchars($args['value']['month'] ?? '') . '">';
    $html .= '</div>';
    $html .= '</div>';

    // Year input
    $html .= '<div class="govuk-date-input__item">';
    $html .= '<div class="govuk-form-group">';
    $html .= '<label class="govuk-label govuk-date-input__label" for="' . htmlspecialchars($args['id']) . '-year">Year</label>';
    $html .= '<input class="govuk-input govuk-date-input__input govuk-input--width-4' . ($hasError ? ' govuk-input--error' : '') . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '-year" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '[year]" ';
    $html .= 'type="text" inputmode="numeric" pattern="[0-9]*" ';
    $html .= 'value="' . htmlspecialchars($args['value']['year'] ?? '') . '">';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // .govuk-date-input
    $html .= '</fieldset>';
    $html .= '</div>'; // .govuk-form-group

    return $html;
}
