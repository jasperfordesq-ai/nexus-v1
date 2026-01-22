<?php
/**
 * GOV.UK File Upload Component
 * Source: https://design-system.service.gov.uk/components/file-upload/
 *
 * @param string $name - Input name attribute
 * @param string $id - Input ID
 * @param string $label - Label text
 * @param string $hint - Hint text
 * @param string $error - Error message
 * @param bool $required - Whether field is required
 * @param string $accept - Accepted file types (e.g., "image/*", ".pdf,.doc")
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/file-upload.php';
 * echo civicone_govuk_file_upload([
 *     'name' => 'avatar',
 *     'id' => 'avatar-upload',
 *     'label' => 'Upload your profile photo',
 *     'hint' => 'Your photo must be a JPG or PNG and less than 5MB',
 *     'accept' => 'image/jpeg,image/png'
 * ]);
 * ?>
 */

function civicone_govuk_file_upload($args = []) {
    $defaults = [
        'name' => '',
        'id' => '',
        'label' => '',
        'hint' => '',
        'error' => '',
        'required' => false,
        'accept' => '',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $hasError = !empty($args['error']);
    $formGroupClasses = ['govuk-form-group'];
    if ($hasError) {
        $formGroupClasses[] = 'govuk-form-group--error';
    }

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

    // File input
    $inputClasses = ['govuk-file-upload'];
    if ($hasError) {
        $inputClasses[] = 'govuk-file-upload--error';
    }
    if (!empty($args['class'])) {
        $inputClasses[] = $args['class'];
    }

    $describedBy = [];
    if (!empty($args['hint'])) {
        $describedBy[] = $args['id'] . '-hint';
    }
    if ($hasError) {
        $describedBy[] = $args['id'] . '-error';
    }

    $html .= '<input class="' . implode(' ', $inputClasses) . '" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '" ';
    $html .= 'name="' . htmlspecialchars($args['name']) . '" ';
    $html .= 'type="file"';

    if (!empty($describedBy)) {
        $html .= ' aria-describedby="' . implode(' ', $describedBy) . '"';
    }

    if (!empty($args['accept'])) {
        $html .= ' accept="' . htmlspecialchars($args['accept']) . '"';
    }

    if ($args['required']) {
        $html .= ' required';
    }

    $html .= '>';

    $html .= '</div>'; // .govuk-form-group

    return $html;
}
