<?php
/**
 * GOV.UK Pagination Component
 * Reusable pagination following GOV.UK Design System v5.14.0
 *
 * @param int $currentPage - Current page number
 * @param int $totalPages - Total number of pages
 * @param string $baseUrl - Base URL for pagination links (page number will be appended)
 * @param string $previousText - Text for previous link (default: 'Previous')
 * @param string $nextText - Text for next link (default: 'Next')
 * @param string $labelText - Pagination aria-label (default: 'Pagination')
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/pagination.php'; echo civicone_govuk_pagination([
 *     'currentPage' => 3,
 *     'totalPages' => 10,
 *     'baseUrl' => '/results?page='
 * ]); ?>
 */

function civicone_govuk_pagination($args = []) {
    $defaults = [
        'currentPage' => 1,
        'totalPages' => 1,
        'baseUrl' => '?page=',
        'previousText' => 'Previous',
        'nextText' => 'Next',
        'labelText' => 'Pagination',
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if ($args['totalPages'] <= 1) {
        return '';
    }

    $current = max(1, min($args['currentPage'], $args['totalPages']));
    $total = $args['totalPages'];

    $classes = ['govuk-pagination'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<nav class="' . implode(' ', $classes) . '" aria-label="' . htmlspecialchars($args['labelText']) . '">';

    // Previous link
    if ($current > 1) {
        $html .= '<div class="govuk-pagination__prev">';
        $html .= '<a class="govuk-link govuk-pagination__link" href="' . htmlspecialchars($args['baseUrl'] . ($current - 1)) . '" rel="prev">';
        $html .= '<svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">';
        $html .= '<path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>';
        $html .= '</svg>';
        $html .= '<span class="govuk-pagination__link-title">' . htmlspecialchars($args['previousText']) . '</span>';
        $html .= '</a>';
        $html .= '</div>';
    }

    // Page numbers
    $html .= '<ul class="govuk-pagination__list">';

    // Calculate which pages to show
    $pagesToShow = [];

    // Always show first page
    $pagesToShow[] = 1;

    // Add ellipsis indicator after first if needed
    if ($current > 4) {
        $pagesToShow[] = '...';
    }

    // Pages around current
    for ($i = max(2, $current - 1); $i <= min($total - 1, $current + 1); $i++) {
        if (!in_array($i, $pagesToShow) && $i !== '...') {
            $pagesToShow[] = $i;
        }
    }

    // Add ellipsis before last if needed
    if ($current < $total - 3) {
        $pagesToShow[] = '...';
    }

    // Always show last page
    if ($total > 1) {
        $pagesToShow[] = $total;
    }

    // Remove duplicates and sort (keeping ellipsis in position)
    $pagesToShow = array_unique($pagesToShow);

    foreach ($pagesToShow as $page) {
        if ($page === '...') {
            $html .= '<li class="govuk-pagination__item govuk-pagination__item--ellipses">&ctdot;</li>';
        } else {
            $isCurrent = $page === $current;
            $itemClasses = ['govuk-pagination__item'];
            if ($isCurrent) {
                $itemClasses[] = 'govuk-pagination__item--current';
            }

            $html .= '<li class="' . implode(' ', $itemClasses) . '">';

            if ($isCurrent) {
                $html .= '<a class="govuk-link govuk-pagination__link" href="' . htmlspecialchars($args['baseUrl'] . $page) . '" aria-current="page">';
            } else {
                $html .= '<a class="govuk-link govuk-pagination__link" href="' . htmlspecialchars($args['baseUrl'] . $page) . '">';
            }

            $html .= $page;
            $html .= '</a>';
            $html .= '</li>';
        }
    }

    $html .= '</ul>';

    // Next link
    if ($current < $total) {
        $html .= '<div class="govuk-pagination__next">';
        $html .= '<a class="govuk-link govuk-pagination__link" href="' . htmlspecialchars($args['baseUrl'] . ($current + 1)) . '" rel="next">';
        $html .= '<span class="govuk-pagination__link-title">' . htmlspecialchars($args['nextText']) . '</span>';
        $html .= '<svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">';
        $html .= '<path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>';
        $html .= '</svg>';
        $html .= '</a>';
        $html .= '</div>';
    }

    $html .= '</nav>';

    return $html;
}
