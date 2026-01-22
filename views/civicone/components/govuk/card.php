<?php
/**
 * GOV.UK Card Component
 * Based on MOJ/DfE Card Pattern
 * Source: https://design-patterns.service.justice.gov.uk/components/card/
 *
 * @param string $title - Card title (required)
 * @param string $description - Card description text
 * @param string $meta - Meta information (date, author, etc.)
 * @param string $href - Link URL (makes entire card clickable)
 * @param string $class - Additional CSS classes
 * @param string $id - Element ID
 * @param array $customContent - Raw HTML content to insert (optional)
 *
 * Usage:
 * <?php include __DIR__ . '/card.php'; echo civicone_govuk_card([
 *   'title' => 'Member Name',
 *   'description' => 'Bio text here',
 *   'meta' => 'Joined Jan 2024',
 *   'href' => '/profile/123'
 * ]); ?>
 */

function civicone_govuk_card($args = []) {
    $defaults = [
        'title' => '',
        'description' => '',
        'meta' => '',
        'href' => null,
        'class' => '',
        'id' => '',
        'customContent' => ''
    ];

    $args = array_merge($defaults, $args);

    $classes = ['govuk-card'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }

    $classStr = implode(' ', $classes);

    // Start building HTML
    $tag = $args['href'] ? 'a' : 'div';
    $html = '<' . $tag;

    if (!empty($args['id'])) {
        $html .= ' id="' . htmlspecialchars($args['id']) . '"';
    }

    $html .= ' class="' . htmlspecialchars($classStr) . '"';

    if ($args['href']) {
        $html .= ' href="' . htmlspecialchars($args['href']) . '"';
    }

    $html .= '>';

    // Card title
    if (!empty($args['title'])) {
        $html .= '<h3 class="govuk-card__title">';
        $html .= htmlspecialchars($args['title']);
        $html .= '</h3>';
    }

    // Card description
    if (!empty($args['description'])) {
        $html .= '<p class="govuk-card__description">';
        $html .= htmlspecialchars($args['description']);
        $html .= '</p>';
    }

    // Custom content (for complex cards)
    if (!empty($args['customContent'])) {
        $html .= $args['customContent'];
    }

    // Card meta
    if (!empty($args['meta'])) {
        $html .= '<div class="govuk-card__meta">';
        $html .= htmlspecialchars($args['meta']);
        $html .= '</div>';
    }

    $html .= '</' . $tag . '>';

    return $html;
}
