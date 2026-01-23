<?php
/**
 * GOV.UK Tabs Component
 * Reusable tabs following GOV.UK Design System v5.14.0
 *
 * @param string $id - Unique ID for the tabs component
 * @param string $title - Title displayed above tabs (for screen readers)
 * @param array $items - Array of tab items with 'id', 'label', and 'panel' (content)
 * @param string $class - Additional CSS classes
 *
 * Usage:
 * <?php include __DIR__ . '/tabs.php'; echo civicone_govuk_tabs([
 *     'id' => 'my-tabs',
 *     'title' => 'Contents',
 *     'items' => [
 *         ['id' => 'past-day', 'label' => 'Past day', 'panel' => '<p>Content for past day</p>'],
 *         ['id' => 'past-week', 'label' => 'Past week', 'panel' => '<p>Content for past week</p>'],
 *     ]
 * ]); ?>
 */

function civicone_govuk_tabs($args = []) {
    $defaults = [
        'id' => 'tabs-' . uniqid(),
        'title' => 'Contents',
        'items' => [],
        'class' => ''
    ];

    $args = array_merge($defaults, $args);

    if (empty($args['items'])) {
        return '';
    }

    $classes = ['govuk-tabs'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $html = '<div class="' . implode(' ', $classes) . '" data-module="govuk-tabs" id="' . htmlspecialchars($args['id']) . '">';

    // Title
    $html .= '<h2 class="govuk-tabs__title">' . htmlspecialchars($args['title']) . '</h2>';

    // Tab list
    $html .= '<ul class="govuk-tabs__list" role="tablist">';
    foreach ($args['items'] as $index => $item) {
        $isFirst = $index === 0;
        $tabId = $args['id'] . '-' . ($item['id'] ?? $index);
        $panelId = $tabId . '-panel';

        $html .= '<li class="govuk-tabs__list-item';
        if ($isFirst) {
            $html .= ' govuk-tabs__list-item--selected';
        }
        $html .= '" role="presentation">';

        $html .= '<a class="govuk-tabs__tab" ';
        $html .= 'href="#' . htmlspecialchars($panelId) . '" ';
        $html .= 'id="' . htmlspecialchars($tabId) . '" ';
        $html .= 'role="tab" ';
        $html .= 'aria-controls="' . htmlspecialchars($panelId) . '" ';
        $html .= 'aria-selected="' . ($isFirst ? 'true' : 'false') . '" ';
        $html .= 'tabindex="' . ($isFirst ? '0' : '-1') . '">';
        $html .= htmlspecialchars($item['label'] ?? '');
        $html .= '</a>';

        $html .= '</li>';
    }
    $html .= '</ul>';

    // Tab panels
    foreach ($args['items'] as $index => $item) {
        $isFirst = $index === 0;
        $tabId = $args['id'] . '-' . ($item['id'] ?? $index);
        $panelId = $tabId . '-panel';

        $panelClasses = ['govuk-tabs__panel'];
        if (!$isFirst) {
            $panelClasses[] = 'govuk-tabs__panel--hidden';
        }

        $html .= '<div class="' . implode(' ', $panelClasses) . '" ';
        $html .= 'id="' . htmlspecialchars($panelId) . '" ';
        $html .= 'role="tabpanel" ';
        $html .= 'aria-labelledby="' . htmlspecialchars($tabId) . '"';
        if (!$isFirst) {
            $html .= ' hidden';
        }
        $html .= '>';
        $html .= $item['panel'] ?? '';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
