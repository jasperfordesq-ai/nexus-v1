<?php
/**
 * GOV.UK Table Component
 * Reusable table following GOV.UK Design System v5.14.0
 *
 * @param array $head - Array of header cells with 'text', 'format' (optional: 'numeric')
 * @param array $rows - Array of rows, each row is an array of cells with 'text' or 'html', 'format' (optional)
 * @param string $caption - Optional table caption
 * @param string $captionSize - Caption size: 'xl', 'l', 'm', 's' (default: 'm')
 * @param bool $firstCellIsHeader - First cell of each row is a header
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/table.php'; echo civicone_govuk_table([
 *     'caption' => 'Dates and amounts',
 *     'head' => [
 *         ['text' => 'Date'],
 *         ['text' => 'Amount', 'format' => 'numeric']
 *     ],
 *     'rows' => [
 *         [['text' => 'First 6 weeks'], ['text' => '£109.80 per week', 'format' => 'numeric']],
 *         [['text' => 'Next 33 weeks'], ['text' => '£109.80 per week', 'format' => 'numeric']],
 *     ]
 * ]); ?>
 */

function civicone_govuk_table($args = []) {
    $defaults = [
        'head' => [],
        'rows' => [],
        'caption' => '',
        'captionSize' => 'm',
        'firstCellIsHeader' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-table'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<table class="' . implode(' ', $classes) . '">';

    // Caption
    if (!empty($args['caption'])) {
        $captionClasses = ['govuk-table__caption'];
        if (in_array($args['captionSize'], ['xl', 'l', 'm', 's'])) {
            $captionClasses[] = 'govuk-table__caption--' . $args['captionSize'];
        }
        $html .= '<caption class="' . implode(' ', $captionClasses) . '">';
        $html .= htmlspecialchars($args['caption']);
        $html .= '</caption>';
    }

    // Header
    if (!empty($args['head'])) {
        $html .= '<thead class="govuk-table__head">';
        $html .= '<tr class="govuk-table__row">';
        foreach ($args['head'] as $cell) {
            $cellClasses = ['govuk-table__header'];
            if (!empty($cell['format']) && $cell['format'] === 'numeric') {
                $cellClasses[] = 'govuk-table__header--numeric';
            }
            $scope = 'col';
            $html .= '<th scope="' . $scope . '" class="' . implode(' ', $cellClasses) . '">';
            $html .= isset($cell['html']) ? $cell['html'] : htmlspecialchars($cell['text'] ?? '');
            $html .= '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
    }

    // Body
    if (!empty($args['rows'])) {
        $html .= '<tbody class="govuk-table__body">';
        foreach ($args['rows'] as $row) {
            $html .= '<tr class="govuk-table__row">';
            foreach ($row as $index => $cell) {
                $isHeader = $args['firstCellIsHeader'] && $index === 0;

                if ($isHeader) {
                    $cellClasses = ['govuk-table__header'];
                    if (!empty($cell['format']) && $cell['format'] === 'numeric') {
                        $cellClasses[] = 'govuk-table__header--numeric';
                    }
                    $html .= '<th scope="row" class="' . implode(' ', $cellClasses) . '">';
                    $html .= isset($cell['html']) ? $cell['html'] : htmlspecialchars($cell['text'] ?? '');
                    $html .= '</th>';
                } else {
                    $cellClasses = ['govuk-table__cell'];
                    if (!empty($cell['format']) && $cell['format'] === 'numeric') {
                        $cellClasses[] = 'govuk-table__cell--numeric';
                    }
                    $html .= '<td class="' . implode(' ', $cellClasses) . '">';
                    $html .= isset($cell['html']) ? $cell['html'] : htmlspecialchars($cell['text'] ?? '');
                    $html .= '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
    }

    $html .= '</table>';

    return $html;
}
