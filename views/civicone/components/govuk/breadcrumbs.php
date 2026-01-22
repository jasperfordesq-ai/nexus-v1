<?php
/**
 * GOV.UK Breadcrumbs Component
 * Navigational hierarchy showing current page location
 * Source: https://design-system.service.gov.uk/components/breadcrumbs/
 *
 * @param array $items - Array of breadcrumb items, each with 'text' and optional 'href'
 * @param bool $collapseOnMobile - Whether to show only last item on mobile
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/breadcrumbs.php';
 * echo civicone_govuk_breadcrumbs([
 *     'items' => [
 *         ['text' => 'Home', 'href' => '/'],
 *         ['text' => 'Events', 'href' => '/events'],
 *         ['text' => 'Community Cleanup'] // Current page (no href)
 *     ]
 * ]);
 * ?>
 */

function civicone_govuk_breadcrumbs($args = []) {
    $defaults = [
        'items' => [],
        'collapseOnMobile' => false,
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['items'])) {
        return '';
    }

    $classes = ['govuk-breadcrumbs'];
    if ($args['collapseOnMobile']) {
        $classes[] = 'govuk-breadcrumbs--collapse-on-mobile';
    }
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<nav class="' . implode(' ', $classes) . '" aria-label="Breadcrumb">';
    $html .= '<ol class="govuk-breadcrumbs__list">';

    foreach ($args['items'] as $item) {
        $html .= '<li class="govuk-breadcrumbs__list-item">';

        if (!empty($item['href'])) {
            $html .= '<a class="govuk-breadcrumbs__link" href="' . htmlspecialchars($item['href']) . '">';
            $html .= htmlspecialchars($item['text']);
            $html .= '</a>';
        } else {
            // Current page - add aria-current for screen readers
            $html .= '<span aria-current="page">' . htmlspecialchars($item['text']) . '</span>';
        }

        $html .= '</li>';
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}
