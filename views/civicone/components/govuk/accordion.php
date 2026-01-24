<?php
/**
 * GOV.UK Accordion Component
 * Reusable accordion following GOV.UK Design System v5.14.0
 *
 * @param array $sections - Array of sections with 'heading', 'summary' (optional), and 'content'
 * @param string $id - Unique ID for the accordion
 * @param bool $rememberExpanded - Whether to remember expanded state (default false)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/accordion.php'; echo civicone_govuk_accordion([
 *     'id' => 'my-accordion',
 *     'sections' => [
 *         ['heading' => 'Section 1', 'content' => 'Content for section 1'],
 *         ['heading' => 'Section 2', 'summary' => 'Optional summary', 'content' => 'Content 2'],
 *     ]
 * ]); ?>
 */

function civicone_govuk_accordion($args = []) {
    $defaults = [
        'id' => 'accordion-' . uniqid(),
        'sections' => [],
        'rememberExpanded' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['sections'])) {
        return '';
    }

    $classes = ['govuk-accordion'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '" data-module="govuk-accordion" id="' . htmlspecialchars($args['id']) . '">';

    foreach ($args['sections'] as $index => $section) {
        $sectionId = $args['id'] . '-section-' . ($index + 1);
        $headingId = $sectionId . '-heading';
        $contentId = $sectionId . '-content';
        $expanded = !empty($section['expanded']);

        $sectionClasses = ['govuk-accordion__section'];
        if ($expanded) {
            $sectionClasses[] = 'govuk-accordion__section--expanded';
        }

        $html .= '<div class="' . implode(' ', $sectionClasses) . '">';

        // Section header
        $html .= '<div class="govuk-accordion__section-header">';
        $html .= '<h2 class="govuk-accordion__section-heading">';
        $html .= '<button type="button" class="govuk-accordion__section-button" ';
        $html .= 'id="' . htmlspecialchars($headingId) . '" ';
        $html .= 'aria-controls="' . htmlspecialchars($contentId) . '" ';
        $html .= 'aria-expanded="' . ($expanded ? 'true' : 'false') . '">';
        $html .= '<span class="govuk-accordion__section-heading-text">';
        $html .= '<span class="govuk-accordion__section-heading-text-focus">';
        $html .= htmlspecialchars($section['heading'] ?? '');
        $html .= '</span></span>';
        $html .= '<span class="govuk-accordion__icon" aria-hidden="true"></span>';
        $html .= '</button>';
        $html .= '</h2>';

        // Optional summary
        if (!empty($section['summary'])) {
            $html .= '<div class="govuk-accordion__section-summary govuk-body" id="' . htmlspecialchars($sectionId) . '-summary">';
            $html .= htmlspecialchars($section['summary']);
            $html .= '</div>';
        }

        $html .= '</div>'; // section-header

        // Section content
        $html .= '<div class="govuk-accordion__section-content" id="' . htmlspecialchars($contentId) . '" ';
        $html .= 'aria-labelledby="' . htmlspecialchars($headingId) . '">';
        $html .= '<div class="govuk-body">' . ($section['content'] ?? '') . '</div>';
        $html .= '</div>';

        $html .= '</div>'; // section
    }

    $html .= '</div>';

    return $html;
}
