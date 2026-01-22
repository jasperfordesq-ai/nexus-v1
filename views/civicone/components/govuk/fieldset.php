<?php
/**
 * GOV.UK Fieldset Component
 * Groups related form fields with a legend
 * Source: https://design-system.service.gov.uk/components/fieldset/
 *
 * @param string $legend - Legend text
 * @param string $legendSize - Legend size: 'xl', 'l', 'm', 's' (default: '')
 * @param bool $legendIsPageHeading - Whether legend contains page H1
 * @param string $content - HTML content inside fieldset
 * @param string $describedBy - Space-separated IDs for aria-describedby
 * @param string $role - Optional role attribute (e.g., "group")
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/fieldset.php';
 * echo civicone_govuk_fieldset([
 *     'legend' => 'What is your address?',
 *     'legendSize' => 'l',
 *     'content' => '<!-- form fields here -->'
 * ]);
 * ?>
 */

function civicone_govuk_fieldset($args = []) {
    $defaults = [
        'legend' => '',
        'legendSize' => '',
        'legendIsPageHeading' => false,
        'content' => '',
        'describedBy' => '',
        'role' => 'group',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-fieldset'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<fieldset class="' . implode(' ', $classes) . '"';

    if (!empty($args['role'])) {
        $html .= ' role="' . htmlspecialchars($args['role']) . '"';
    }

    if (!empty($args['describedBy'])) {
        $html .= ' aria-describedby="' . htmlspecialchars($args['describedBy']) . '"';
    }

    $html .= '>';

    // Legend
    if (!empty($args['legend'])) {
        $legendClasses = ['govuk-fieldset__legend'];

        if (!empty($args['legendSize'])) {
            $legendClasses[] = 'govuk-fieldset__legend--' . $args['legendSize'];
        }

        $html .= '<legend class="' . implode(' ', $legendClasses) . '">';

        if ($args['legendIsPageHeading']) {
            $html .= '<h1 class="govuk-fieldset__heading">';
            $html .= htmlspecialchars($args['legend']);
            $html .= '</h1>';
        } else {
            $html .= htmlspecialchars($args['legend']);
        }

        $html .= '</legend>';
    }

    // Content
    $html .= $args['content'];

    $html .= '</fieldset>';

    return $html;
}
