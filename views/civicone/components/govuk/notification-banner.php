<?php
/**
 * GOV.UK Notification Banner Component
 * WCAG 4.1.3 Status Messages (Level AA)
 * Source: https://design-system.service.gov.uk/components/notification-banner/
 *
 * @param string $type - 'neutral' (default), 'success', or 'important'
 * @param string $title - Banner title (default varies by type)
 * @param string $content - Main content (HTML allowed)
 * @param string $heading - Optional heading text for content
 * @param bool $disableAutoFocus - Disable auto-focus for success banners (default: false)
 * @param string $id - Element ID (default: "notification-banner")
 *
 * Usage:
 * <?php include __DIR__ . '/notification-banner.php';
 * echo civicone_govuk_notification_banner([
 *     'type' => 'success',
 *     'heading' => 'Your profile has been updated',
 *     'content' => '<a href="/profile">View your profile</a>'
 * ]);
 * ?>
 */

function civicone_govuk_notification_banner($args = []) {
    $defaults = [
        'type' => 'neutral',
        'title' => null,
        'heading' => '',
        'content' => '',
        'disableAutoFocus' => false,
        'id' => 'notification-banner'
    ];

    $args = array_merge($defaults, $args);

    // Set default title based on type
    if ($args['title'] === null) {
        switch ($args['type']) {
            case 'success':
                $args['title'] = 'Success';
                break;
            case 'important':
                $args['title'] = 'Important';
                break;
            default:
                $args['title'] = 'Important';
        }
    }

    // Build classes
    $classes = ['govuk-notification-banner'];
    if ($args['type'] === 'success') {
        $classes[] = 'govuk-notification-banner--success';
    }

    // Role and aria attributes
    $role = ($args['type'] === 'success') ? 'alert' : 'region';
    $ariaLabel = htmlspecialchars($args['title']);

    // Tabindex for focus (success banners should be focusable)
    $tabindex = ($args['type'] === 'success' && !$args['disableAutoFocus']) ? 'tabindex="-1"' : '';

    $html = '<div class="' . implode(' ', $classes) . '" role="' . $role . '" ';
    $html .= 'aria-labelledby="' . $args['id'] . '-title" ';
    if ($tabindex) {
        $html .= $tabindex . ' ';
    }
    $html .= 'data-module="govuk-notification-banner" ';
    $html .= 'id="' . htmlspecialchars($args['id']) . '">';

    // Header
    $html .= '<div class="govuk-notification-banner__header">';
    $html .= '<h2 class="govuk-notification-banner__title" id="' . $args['id'] . '-title">';
    $html .= htmlspecialchars($args['title']);
    $html .= '</h2>';
    $html .= '</div>';

    // Content
    $html .= '<div class="govuk-notification-banner__content">';

    if (!empty($args['heading'])) {
        $html .= '<p class="govuk-notification-banner__heading">';
        $html .= htmlspecialchars($args['heading']);
        $html .= '</p>';
    }

    if (!empty($args['content'])) {
        // Content can contain HTML (links, etc.)
        $html .= '<p>' . $args['content'] . '</p>';
    }

    $html .= '</div>';
    $html .= '</div>';

    // Add focus script for success banners
    if ($args['type'] === 'success' && !$args['disableAutoFocus']) {
        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= 'var banner = document.getElementById("' . $args['id'] . '");';
        $html .= 'if (banner) banner.focus();';
        $html .= '});';
        $html .= '</script>';
    }

    return $html;
}
