<?php
/**
 * Federation Settings
 * CivicOne Theme - GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federation Settings";
$pageSubtitle = "Manage your federation preferences";
$hideHero = true;
$bodyClass = 'civicone--federation';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Extract data
$userSettings = $userSettings ?? [];
$userProfile = $userProfile ?? [];
$partnerCount = $partnerCount ?? 0;
$stats = $stats ?? [];

$isOptedIn = !empty($userSettings['federation_optin']);
$privacyLevel = $userSettings['privacy_level'] ?? 'discovery';
$serviceReach = $userSettings['service_reach'] ?? 'local_only';
?>

<!-- Offline Banner -->
<div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-margin-bottom-4 hidden" id="offlineBanner" role="alert" aria-live="polite">
    <div class="govuk-notification-banner__content">
        <p class="govuk-body">
            <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
            No internet connection
        </p>
    </div>
</div>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Federation', 'href' => $basePath . '/federation'],
        ['text' => 'Settings']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
            <i class="fa-solid fa-cog govuk-!-margin-right-2" aria-hidden="true"></i>
            Federation Settings
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            Control your privacy, visibility, and how you appear to members from partner timebanks.
        </p>
    </div>
</div>

<?php $currentPage = 'settings'; $userOptedIn = $isOptedIn; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

<!-- Status Banner -->
<div class="govuk-notification-banner <?= $isOptedIn ? 'govuk-notification-banner--success' : '' ?> govuk-!-margin-bottom-6" role="status" aria-live="polite">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title">Federation Status</h2>
    </div>
    <div class="govuk-notification-banner__content">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h3 class="govuk-notification-banner__heading">
                    <i class="fa-solid <?= $isOptedIn ? 'fa-check' : 'fa-eye-slash' ?> govuk-!-margin-right-2" aria-hidden="true"></i>
                    Federation is <?= $isOptedIn ? 'Enabled' : 'Disabled' ?>
                </h3>
                <p class="govuk-body"><?= $isOptedIn
                    ? 'Your profile is visible to ' . $partnerCount . ' partner timebank' . ($partnerCount !== 1 ? 's' : '')
                    : 'Your profile is hidden from partner timebanks' ?></p>
            </div>
            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                <button type="button" class="govuk-button <?= $isOptedIn ? 'govuk-button--warning' : '' ?>" data-module="govuk-button" id="statusToggle">
                    <?= $isOptedIn ? 'Disable Federation' : 'Enable Federation' ?>
                </button>
            </div>
        </div>
    </div>
</div>

    <form id="settingsForm" aria-label="Federation settings form">
        <!-- Privacy Level -->
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-settings-card">
            <h2 class="govuk-heading-m" id="privacy-heading">
                <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" aria-hidden="true"></i>
                Privacy Level
            </h2>
            <p class="govuk-hint govuk-!-margin-bottom-4">Choose how much of your profile to share with partner timebanks</p>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-labelledby="privacy-heading">
                    <legend class="govuk-visually-hidden">Privacy level options</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="privacy-discovery" name="privacy_level" type="radio" value="discovery" <?= $privacyLevel === 'discovery' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="privacy-discovery">
                                <strong>Discovery Only</strong>
                            </label>
                            <div class="govuk-hint govuk-radios__hint">
                                Only your name and avatar are visible. Good for browsing without sharing details.
                            </div>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="privacy-social" name="privacy_level" type="radio" value="social" <?= $privacyLevel === 'social' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="privacy-social">
                                <strong>Social</strong> <span class="govuk-tag govuk-tag--green">Recommended</span>
                            </label>
                            <div class="govuk-hint govuk-radios__hint">
                                Share your skills, bio, and location. Enables messaging with partner members.
                            </div>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="privacy-economic" name="privacy_level" type="radio" value="economic" <?= $privacyLevel === 'economic' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="privacy-economic">
                                <strong>Economic</strong>
                            </label>
                            <div class="govuk-hint govuk-radios__hint">
                                Full profile sharing plus ability to send/receive time credits across timebanks.
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <!-- Visibility Options -->
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-settings-card">
            <h2 class="govuk-heading-m" id="visibility-heading">
                <i class="fa-solid fa-sliders govuk-!-margin-right-2" aria-hidden="true"></i>
                Visibility Options
            </h2>
            <p class="govuk-hint govuk-!-margin-bottom-4">Fine-tune what information is shared</p>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-labelledby="visibility-heading">
                    <legend class="govuk-visually-hidden">Visibility options</legend>
                    <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="appear_in_search" name="appear_in_search" type="checkbox" <?= !empty($userSettings['appear_in_federated_search']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="appear_in_search">
                                <strong>Show in Federated Search</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Appear in search results for partner timebank members
                            </div>
                        </div>
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="profile_visible" name="profile_visible" type="checkbox" <?= !empty($userSettings['profile_visible_federated']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="profile_visible">
                                <strong>Profile Visible</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Allow partner members to view your full profile
                            </div>
                        </div>
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="show_location" name="show_location" type="checkbox" <?= !empty($userSettings['show_location_federated']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="show_location">
                                <strong>Show Location</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Display your city/region to partner members
                            </div>
                        </div>
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="show_skills" name="show_skills" type="checkbox" <?= !empty($userSettings['show_skills_federated']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="show_skills">
                                <strong>Show Skills</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Display your skills and services to partner members
                            </div>
                        </div>
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="messaging_enabled" name="messaging_enabled" type="checkbox" <?= !empty($userSettings['messaging_enabled_federated']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="messaging_enabled">
                                <strong>Receive Messages</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Allow partner members to send you messages
                            </div>
                        </div>
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="transactions_enabled" name="transactions_enabled" type="checkbox" <?= !empty($userSettings['transactions_enabled_federated']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="transactions_enabled">
                                <strong>Accept Transactions</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Allow receiving time credits from partner members
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <!-- AI Assistant Options -->
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-settings-card">
            <h2 class="govuk-heading-m" id="ai-heading">
                <i class="fa-solid fa-robot govuk-!-margin-right-2" aria-hidden="true"></i>
                AI Assistant
            </h2>
            <p class="govuk-hint govuk-!-margin-bottom-4">Customize the AI assistant appearance</p>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-labelledby="ai-heading">
                    <legend class="govuk-visually-hidden">AI assistant options</legend>
                    <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="ai_pulse_enabled" name="ai_pulse_enabled" type="checkbox" <?= !empty($userSettings['ai_pulse_enabled']) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="ai_pulse_enabled">
                                <strong>AI Button Animation</strong>
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Show pulsing animation on the AI assistant button
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <!-- Service Reach -->
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-settings-card">
            <h2 class="govuk-heading-m" id="reach-heading">
                <i class="fa-solid fa-location-dot govuk-!-margin-right-2" aria-hidden="true"></i>
                Service Reach
            </h2>
            <p class="govuk-hint govuk-!-margin-bottom-4">How far are you willing to travel for exchanges?</p>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-labelledby="reach-heading">
                    <legend class="govuk-visually-hidden">Service reach options</legend>
                    <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="reach-local" name="service_reach" type="radio" value="local_only" <?= $serviceReach === 'local_only' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="reach-local">
                                <i class="fa-solid fa-house govuk-!-margin-right-1" aria-hidden="true"></i>
                                Local Only
                            </label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="reach-travel" name="service_reach" type="radio" value="will_travel" <?= $serviceReach === 'will_travel' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="reach-travel">
                                <i class="fa-solid fa-car govuk-!-margin-right-1" aria-hidden="true"></i>
                                Will Travel
                            </label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="reach-remote" name="service_reach" type="radio" value="remote_ok" <?= $serviceReach === 'remote_ok' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="reach-remote">
                                <i class="fa-solid fa-laptop govuk-!-margin-right-1" aria-hidden="true"></i>
                                Remote OK
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <!-- Activity Summary -->
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-settings-card">
            <h2 class="govuk-heading-m" id="activity-heading">
                <i class="fa-solid fa-chart-simple govuk-!-margin-right-2" aria-hidden="true"></i>
                Your Federation Activity
            </h2>
            <p class="govuk-hint govuk-!-margin-bottom-4">Summary of your cross-timebank interactions</p>

            <div class="govuk-grid-row" role="region" aria-label="Activity statistics">
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                        <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= number_format(($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0)) ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Messages Exchanged</p>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                        <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= number_format($stats['transactions_count'] ?? 0) ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Transactions</p>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                        <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= number_format($stats['hours_exchanged'] ?? 0, 1) ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Hours Exchanged</p>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                        <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $partnerCount ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Partner Timebanks</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Actions -->
        <div class="govuk-button-group govuk-!-margin-bottom-6">
            <a href="<?= $basePath ?>/federation/dashboard" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i>
                Back
            </a>
            <button type="submit" class="govuk-button" data-module="govuk-button" id="saveBtn">
                <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                Save Settings
            </button>
        </div>
    </form>

    <!-- Toast notification -->
    <div class="govuk-notification-banner hidden" id="toast" role="status" aria-live="polite">
        <div class="govuk-notification-banner__content">
            <p class="govuk-body" id="toastMessage"></p>
        </div>
    </div>
</div>

<script src="/assets/js/federation-settings.js?v=<?= time() ?>"></script>
<script>
    window.federationSettingsConfig = {
        basePath: '<?= $basePath ?>',
        csrfToken: '<?= \Nexus\Core\Csrf::token() ?>',
        isOptedIn: <?= $isOptedIn ? 'true' : 'false' ?>
    };
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
