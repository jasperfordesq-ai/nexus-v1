<?php
/**
 * CivicOne Notification Settings Edit - GOV.UK Frontend v5.14.0 Compliant
 * WCAG 2.1 AA Compliant
 */

use Nexus\Core\Csrf;

$pageTitle = 'Notification Settings';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = Nexus\Core\TenantContext::getBasePath();

// Get current notification preferences
try {
    $notifPrefs = \Nexus\Models\User::getNotificationPreferences($_SESSION['user_id']);
} catch (\Exception $e) {
    $notifPrefs = [];
}
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Settings', 'href' => $basePath . '/settings'],
        ['text' => 'Notifications']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<h1 class="govuk-heading-xl">Notification settings</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">Your notification settings have been updated.</p>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $basePath ?>/settings/notifications">
    <?= Csrf::input() ?>

    <!-- Email Notifications -->
    <h2 class="govuk-heading-l">Email notifications</h2>

    <div class="govuk-form-group">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                General notifications
            </legend>
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_messages" name="email_messages" type="checkbox" value="1" <?= ($notifPrefs['email_messages'] ?? 1) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="email_messages">
                        New messages
                    </label>
                    <div class="govuk-hint govuk-checkboxes__hint">
                        When someone sends you a message
                    </div>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_connections" name="email_connections" type="checkbox" value="1" <?= ($notifPrefs['email_connections'] ?? 1) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="email_connections">
                        Connection requests
                    </label>
                    <div class="govuk-hint govuk-checkboxes__hint">
                        When someone wants to connect with you
                    </div>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_transactions" name="email_transactions" type="checkbox" value="1" <?= ($notifPrefs['email_transactions'] ?? 1) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="email_transactions">
                        Transaction updates
                    </label>
                    <div class="govuk-hint govuk-checkboxes__hint">
                        Credit transfers and exchange confirmations
                    </div>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_reviews" name="email_reviews" type="checkbox" value="1" <?= ($notifPrefs['email_reviews'] ?? 1) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="email_reviews">
                        New reviews
                    </label>
                    <div class="govuk-hint govuk-checkboxes__hint">
                        When someone leaves you a review
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Gamification Notifications -->
    <div class="govuk-form-group">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                Achievements and progress
            </legend>
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_gamification_digest" name="email_gamification_digest" type="checkbox" value="1" <?= ($notifPrefs['email_gamification_digest'] ?? 1) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="email_gamification_digest">
                        Weekly progress digest
                    </label>
                    <div class="govuk-hint govuk-checkboxes__hint">
                        A weekly summary of your XP, badges, and achievements
                    </div>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_gamification_milestones" name="email_gamification_milestones" type="checkbox" value="1" <?= ($notifPrefs['email_gamification_milestones'] ?? 1) ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-checkboxes__label" for="email_gamification_milestones">
                        Achievement milestones
                    </label>
                    <div class="govuk-hint govuk-checkboxes__hint">
                        When you earn badges, level up, or hit streaks
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Push Notifications -->
    <h2 class="govuk-heading-l govuk-!-margin-top-8">Push notifications</h2>

    <div class="govuk-form-group">
        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input" id="push_enabled" name="push_enabled" type="checkbox" value="1" <?= ($notifPrefs['push_enabled'] ?? 0) ? 'checked' : '' ?>>
                <label class="govuk-label govuk-checkboxes__label" for="push_enabled">
                    Enable push notifications
                </label>
                <div class="govuk-hint govuk-checkboxes__hint">
                    Receive browser notifications for important updates
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="govuk-button" data-module="govuk-button">
        Save changes
    </button>
</form>

<p class="govuk-body govuk-!-margin-top-6">
    <a href="<?= $basePath ?>/settings" class="govuk-link">
        <span aria-hidden="true">&larr;</span> Back to settings
    </a>
</p>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
