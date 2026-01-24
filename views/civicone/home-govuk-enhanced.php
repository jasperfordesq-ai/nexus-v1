<?php
/**
 * CivicOne Home - GOV.UK Enhanced Landing Page
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * REFACTORED: 2026-01-22
 * - Uses pure GOV.UK Frontend v5.14.0 components
 * - Notification Banner for success/info messages
 * - Warning Text for important notices
 * - Inset Text for highlighted information
 */

// Override hero for homepage with GOV.UK Start Button pattern
$heroOverrides = [
    'variant' => 'banner',
    'title' => 'Welcome to Your Community',
    'lead' => 'Connect, collaborate, and make a difference in your local area.',
    'cta' => [
        'text' => 'Get started',
        'url' => '/join',
        'style' => 'start' // GOV.UK Start Button
    ],
];

// Check for success/info messages from session
$showSuccessBanner = !empty($_SESSION['success_message']);
$successMessage = $_SESSION['success_message'] ?? '';
if ($showSuccessBanner) {
    unset($_SESSION['success_message']); // Clear after displaying
}

$showInfoBanner = !empty($_SESSION['info_message']);
$infoMessage = $_SESSION['info_message'] ?? '';
if ($showInfoBanner) {
    unset($_SESSION['info_message']);
}

// Check for important warnings
$showWarning = !empty($_SESSION['warning_message']);
$warningMessage = $_SESSION['warning_message'] ?? '';
if ($showWarning) {
    unset($_SESSION['warning_message']);
}

?>

<!-- GOV.UK Success Notification Banner -->
<?php if ($showSuccessBanner): ?>
<div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="success-banner-title" data-module="govuk-notification-banner">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="success-banner-title">Success</h2>
    </div>
    <div class="govuk-notification-banner__content">
        <p class="govuk-notification-banner__heading"><?= htmlspecialchars($successMessage) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- GOV.UK Important Information Banner -->
<?php if ($showInfoBanner): ?>
<div class="govuk-notification-banner" role="region" aria-labelledby="info-banner-title" data-module="govuk-notification-banner">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="info-banner-title">Important</h2>
    </div>
    <div class="govuk-notification-banner__content">
        <p class="govuk-notification-banner__heading"><?= htmlspecialchars($infoMessage) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- GOV.UK Warning Text -->
<?php if ($showWarning): ?>
<div class="govuk-warning-text">
    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
    <strong class="govuk-warning-text__text">
        <span class="govuk-visually-hidden">Warning</span>
        <?= htmlspecialchars($warningMessage) ?>
    </strong>
</div>
<?php endif; ?>

<!-- GOV.UK Inset Text - Community Guidelines -->
<?php if (!empty($_SESSION['user_id']) && empty($_SESSION['seen_guidelines'])): ?>
<div class="govuk-inset-text">
    <p class="govuk-body"><strong>Community Guidelines:</strong> Be respectful, supportive, and inclusive. Your contributions help build a stronger community for everyone.</p>
</div>
<?php endif; ?>

<?php
// Include the full CivicOne feed as the home page content
require __DIR__ . '/feed/index.php';
?>
