<?php
/**
 * CivicOne Home - GOV.UK Enhanced Landing Page
 *
 * REFACTORED: 2026-01-22
 * - Uses pure GOV.UK Frontend v5.14.0 components (WCAG 2.2 AA)
 * - Notification Banner for success/info messages
 * - Warning Text for important notices
 * - Inset Text for highlighted information
 * - All components from GOV.UK Design System
 *
 * Source of Truth: docs/GOVUK-ONLY-COMPONENTS.md
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
<div class="civicone-notification-banner civicone-notification-banner--success" role="alert" aria-labelledby="success-banner-title">
    <div class="civicone-notification-banner__header">
        <h2 class="civicone-notification-banner__title" id="success-banner-title">Success</h2>
    </div>
    <div class="civicone-notification-banner__content">
        <p class="civicone-notification-banner__heading"><?= htmlspecialchars($successMessage) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- GOV.UK Important Information Banner -->
<?php if ($showInfoBanner): ?>
<div class="civicone-notification-banner" role="region" aria-labelledby="info-banner-title">
    <div class="civicone-notification-banner__header">
        <h2 class="civicone-notification-banner__title" id="info-banner-title">Important</h2>
    </div>
    <div class="civicone-notification-banner__content">
        <p class="civicone-notification-banner__heading"><?= htmlspecialchars($infoMessage) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- GOV.UK Warning Text -->
<?php if ($showWarning): ?>
<div class="civicone-warning-text">
    <span class="civicone-warning-text__icon" aria-hidden="true">!</span>
    <strong class="civicone-warning-text__text">
        <span class="civicone-warning-text__assistive">Warning</span>
        <?= htmlspecialchars($warningMessage) ?>
    </strong>
</div>
<?php endif; ?>

<!-- GOV.UK Inset Text - Community Guidelines (Example) -->
<?php if (!empty($_SESSION['user_id']) && empty($_SESSION['seen_guidelines'])): ?>
<div class="civicone-inset-text">
    <p><strong>Community Guidelines:</strong> Be respectful, supportive, and inclusive. Your contributions help build a stronger community for everyone.</p>
</div>
<?php endif; ?>

<?php
// Include the full CivicOne feed as the home page content
require __DIR__ . '/feed/index.php';
?>
