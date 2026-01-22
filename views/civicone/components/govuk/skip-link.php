<?php
/**
 * GOV.UK Skip Link Component
 * WCAG 2.4.1 Bypass Blocks (Level A) - CRITICAL REQUIREMENT
 * Source: https://design-system.service.gov.uk/components/skip-link/
 *
 * @param string $href - Target element ID (default: #main-content)
 * @param string $text - Link text (default: "Skip to main content")
 *
 * Usage:
 * <?php include __DIR__ . '/skip-link.php';
 * echo civicone_govuk_skip_link();
 * ?>
 *
 * IMPORTANT: Must be the first focusable element on the page (after <body>)
 * Target element should have tabindex="-1" for Firefox compatibility
 */

function civicone_govuk_skip_link($args = []) {
    $defaults = [
        'href' => '#main-content',
        'text' => 'Skip to main content'
    ];

    $args = array_merge($defaults, $args);

    return '<a href="' . htmlspecialchars($args['href']) . '" class="govuk-skip-link" data-module="govuk-skip-link">' .
           htmlspecialchars($args['text']) .
           '</a>';
}
