<?php
/**
 * GOV.UK Summary List Component
 * Reusable summary list following GOV.UK Design System v5.14.0
 *
 * @param array $rows - Array of rows with 'key', 'value', and 'actions' (optional array of links)
 * @param bool $noBorder - Remove borders from rows
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/summary-list.php'; echo civicone_govuk_summary_list([
 *     'rows' => [
 *         [
 *             'key' => 'Name',
 *             'value' => 'John Smith',
 *             'actions' => [
 *                 ['href' => '/edit/name', 'text' => 'Change', 'visuallyHiddenText' => 'name']
 *             ]
 *         ],
 *         ['key' => 'Date of birth', 'value' => '5 January 1978'],
 *         ['key' => 'Address', 'value' => "72 Guild Street<br>London<br>SE23 6FH"],
 *     ]
 * ]); ?>
 */

function civicone_govuk_summary_list($args = []) {
    $defaults = [
        'rows' => [],
        'noBorder' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['rows'])) {
        return '';
    }

    $classes = ['govuk-summary-list'];
    if ($args['noBorder']) {
        $classes[] = 'govuk-summary-list--no-border';
    }
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<dl class="' . implode(' ', $classes) . '">';

    foreach ($args['rows'] as $row) {
        $html .= '<div class="govuk-summary-list__row">';

        // Key
        $html .= '<dt class="govuk-summary-list__key">';
        $html .= htmlspecialchars($row['key'] ?? '');
        $html .= '</dt>';

        // Value (allows HTML for multi-line content)
        $html .= '<dd class="govuk-summary-list__value">';
        $html .= $row['value'] ?? '';
        $html .= '</dd>';

        // Actions
        if (!empty($row['actions'])) {
            $html .= '<dd class="govuk-summary-list__actions">';

            if (count($row['actions']) === 1) {
                // Single action
                $action = $row['actions'][0];
                $html .= '<a class="govuk-link" href="' . htmlspecialchars($action['href'] ?? '#') . '">';
                $html .= htmlspecialchars($action['text'] ?? 'Change');
                if (!empty($action['visuallyHiddenText'])) {
                    $html .= '<span class="govuk-visually-hidden"> ' . htmlspecialchars($action['visuallyHiddenText']) . '</span>';
                }
                $html .= '</a>';
            } else {
                // Multiple actions
                $html .= '<ul class="govuk-summary-list__actions-list">';
                foreach ($row['actions'] as $action) {
                    $html .= '<li class="govuk-summary-list__actions-list-item">';
                    $html .= '<a class="govuk-link" href="' . htmlspecialchars($action['href'] ?? '#') . '">';
                    $html .= htmlspecialchars($action['text'] ?? 'Change');
                    if (!empty($action['visuallyHiddenText'])) {
                        $html .= '<span class="govuk-visually-hidden"> ' . htmlspecialchars($action['visuallyHiddenText']) . '</span>';
                    }
                    $html .= '</a>';
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</dd>';
        }

        $html .= '</div>';
    }

    $html .= '</dl>';

    return $html;
}
