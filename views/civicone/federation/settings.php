<?php
/**
 * Federation Settings
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federation Settings";
$pageSubtitle = "Manage your federation preferences";
$hideHero = true;
$bodyClass = 'civicone--federation';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation" class="civic-fed-back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federation Hub
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>Federation Settings</h1>
    </header>

    <p class="civic-fed-intro">
        Control your privacy, visibility, and how you appear to members from partner timebanks.
    </p>

    <?php $currentPage = 'settings'; $userOptedIn = $isOptedIn; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

    <!-- Status Banner -->
    <div class="civic-fed-status-banner <?= $isOptedIn ? 'civic-fed-status-banner--enabled' : 'civic-fed-status-banner--disabled' ?>" role="status" aria-live="polite">
        <div class="civic-fed-status-info">
            <div class="civic-fed-status-icon">
                <i class="fa-solid <?= $isOptedIn ? 'fa-check' : 'fa-eye-slash' ?>" aria-hidden="true"></i>
            </div>
            <div class="civic-fed-status-text">
                <h2>Federation is <?= $isOptedIn ? 'Enabled' : 'Disabled' ?></h2>
                <p><?= $isOptedIn
                    ? 'Your profile is visible to ' . $partnerCount . ' partner timebank' . ($partnerCount !== 1 ? 's' : '')
                    : 'Your profile is hidden from partner timebanks' ?></p>
            </div>
        </div>
        <button type="button" class="civic-fed-btn <?= $isOptedIn ? 'civic-fed-btn--danger' : 'civic-fed-btn--primary' ?>" id="statusToggle">
            <?= $isOptedIn ? 'Disable Federation' : 'Enable Federation' ?>
        </button>
    </div>

    <form id="settingsForm" aria-label="Federation settings form">
        <!-- Privacy Level -->
        <section class="civic-fed-card" aria-labelledby="privacy-heading">
            <div class="civic-fed-card-header">
                <h2 id="privacy-heading">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    Privacy Level
                </h2>
            </div>
            <div class="civic-fed-card-body">
                <p class="civic-fed-card-desc">Choose how much of your profile to share with partner timebanks</p>

                <fieldset class="civic-fed-radio-group" role="radiogroup" aria-labelledby="privacy-heading">
                    <legend class="visually-hidden">Privacy level options</legend>

                    <label class="civic-fed-radio-option <?= $privacyLevel === 'discovery' ? 'civic-fed-radio-option--selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="discovery" <?= $privacyLevel === 'discovery' ? 'checked' : '' ?>>
                        <span class="civic-fed-radio-indicator" aria-hidden="true"></span>
                        <div class="civic-fed-radio-content">
                            <h4>Discovery Only</h4>
                            <p>Only your name and avatar are visible. Good for browsing without sharing details.</p>
                        </div>
                    </label>

                    <label class="civic-fed-radio-option <?= $privacyLevel === 'social' ? 'civic-fed-radio-option--selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="social" <?= $privacyLevel === 'social' ? 'checked' : '' ?>>
                        <span class="civic-fed-radio-indicator" aria-hidden="true"></span>
                        <div class="civic-fed-radio-content">
                            <h4>Social <span class="civic-fed-tag civic-fed-tag--success">Recommended</span></h4>
                            <p>Share your skills, bio, and location. Enables messaging with partner members.</p>
                        </div>
                    </label>

                    <label class="civic-fed-radio-option <?= $privacyLevel === 'economic' ? 'civic-fed-radio-option--selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="economic" <?= $privacyLevel === 'economic' ? 'checked' : '' ?>>
                        <span class="civic-fed-radio-indicator" aria-hidden="true"></span>
                        <div class="civic-fed-radio-content">
                            <h4>Economic</h4>
                            <p>Full profile sharing plus ability to send/receive time credits across timebanks.</p>
                        </div>
                    </label>
                </fieldset>
            </div>
        </section>

        <!-- Visibility Options -->
        <section class="civic-fed-card" aria-labelledby="visibility-heading">
            <div class="civic-fed-card-header">
                <h2 id="visibility-heading">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    Visibility Options
                </h2>
            </div>
            <div class="civic-fed-card-body">
                <p class="civic-fed-card-desc">Fine-tune what information is shared</p>

                <div class="civic-fed-toggle-list" role="group" aria-labelledby="visibility-heading">
                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="search-toggle-label">Show in Federated Search</h4>
                            <p id="search-toggle-desc">Appear in search results for partner timebank members</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="appear_in_search" <?= !empty($userSettings['appear_in_federated_search']) ? 'checked' : '' ?> aria-labelledby="search-toggle-label" aria-describedby="search-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="profile-toggle-label">Profile Visible</h4>
                            <p id="profile-toggle-desc">Allow partner members to view your full profile</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="profile_visible" <?= !empty($userSettings['profile_visible_federated']) ? 'checked' : '' ?> aria-labelledby="profile-toggle-label" aria-describedby="profile-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="location-toggle-label">Show Location</h4>
                            <p id="location-toggle-desc">Display your city/region to partner members</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="show_location" <?= !empty($userSettings['show_location_federated']) ? 'checked' : '' ?> aria-labelledby="location-toggle-label" aria-describedby="location-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="skills-toggle-label">Show Skills</h4>
                            <p id="skills-toggle-desc">Display your skills and services to partner members</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="show_skills" <?= !empty($userSettings['show_skills_federated']) ? 'checked' : '' ?> aria-labelledby="skills-toggle-label" aria-describedby="skills-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="messaging-toggle-label">Receive Messages</h4>
                            <p id="messaging-toggle-desc">Allow partner members to send you messages</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="messaging_enabled" <?= !empty($userSettings['messaging_enabled_federated']) ? 'checked' : '' ?> aria-labelledby="messaging-toggle-label" aria-describedby="messaging-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="transactions-toggle-label">Accept Transactions</h4>
                            <p id="transactions-toggle-desc">Allow receiving time credits from partner members</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="transactions_enabled" <?= !empty($userSettings['transactions_enabled_federated']) ? 'checked' : '' ?> aria-labelledby="transactions-toggle-label" aria-describedby="transactions-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <!-- AI Assistant Options -->
        <section class="civic-fed-card" aria-labelledby="ai-heading">
            <div class="civic-fed-card-header">
                <h2 id="ai-heading">
                    <i class="fa-solid fa-robot" aria-hidden="true"></i>
                    AI Assistant
                </h2>
            </div>
            <div class="civic-fed-card-body">
                <p class="civic-fed-card-desc">Customize the AI assistant appearance</p>

                <div class="civic-fed-toggle-list" role="group" aria-labelledby="ai-heading">
                    <div class="civic-fed-toggle-item">
                        <div class="civic-fed-toggle-info">
                            <h4 id="ai-pulse-toggle-label">AI Button Animation</h4>
                            <p id="ai-pulse-toggle-desc">Show pulsing animation on the AI assistant button</p>
                        </div>
                        <label class="civic-fed-toggle">
                            <input type="checkbox" name="ai_pulse_enabled" id="ai_pulse_enabled" <?= !empty($userSettings['ai_pulse_enabled']) ? 'checked' : '' ?> aria-labelledby="ai-pulse-toggle-label" aria-describedby="ai-pulse-toggle-desc">
                            <span class="civic-fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <!-- Service Reach -->
        <section class="civic-fed-card" aria-labelledby="reach-heading">
            <div class="civic-fed-card-header">
                <h2 id="reach-heading">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    Service Reach
                </h2>
            </div>
            <div class="civic-fed-card-body">
                <p class="civic-fed-card-desc">How far are you willing to travel for exchanges?</p>

                <fieldset class="civic-fed-reach-options" role="radiogroup" aria-labelledby="reach-heading">
                    <legend class="visually-hidden">Service reach options</legend>

                    <label class="civic-fed-reach-option <?= $serviceReach === 'local_only' ? 'civic-fed-reach-option--selected' : '' ?>">
                        <input type="radio" name="service_reach" value="local_only" <?= $serviceReach === 'local_only' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <span>Local Only</span>
                    </label>

                    <label class="civic-fed-reach-option <?= $serviceReach === 'will_travel' ? 'civic-fed-reach-option--selected' : '' ?>">
                        <input type="radio" name="service_reach" value="will_travel" <?= $serviceReach === 'will_travel' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-car" aria-hidden="true"></i>
                        <span>Will Travel</span>
                    </label>

                    <label class="civic-fed-reach-option <?= $serviceReach === 'remote_ok' ? 'civic-fed-reach-option--selected' : '' ?>">
                        <input type="radio" name="service_reach" value="remote_ok" <?= $serviceReach === 'remote_ok' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-laptop" aria-hidden="true"></i>
                        <span>Remote OK</span>
                    </label>
                </fieldset>
            </div>
        </section>

        <!-- Activity Summary -->
        <section class="civic-fed-card" aria-labelledby="activity-heading">
            <div class="civic-fed-card-header">
                <h2 id="activity-heading">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    Your Federation Activity
                </h2>
            </div>
            <div class="civic-fed-card-body">
                <p class="civic-fed-card-desc">Summary of your cross-timebank interactions</p>

                <div class="civic-fed-stats-grid civic-fed-stats-grid--compact" role="region" aria-label="Activity statistics">
                    <div class="civic-fed-stat-card">
                        <span class="civic-fed-stat-value"><?= number_format(($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0)) ?></span>
                        <span class="civic-fed-stat-label">Messages Exchanged</span>
                    </div>
                    <div class="civic-fed-stat-card">
                        <span class="civic-fed-stat-value"><?= number_format($stats['transactions_count'] ?? 0) ?></span>
                        <span class="civic-fed-stat-label">Transactions</span>
                    </div>
                    <div class="civic-fed-stat-card">
                        <span class="civic-fed-stat-value"><?= number_format($stats['hours_exchanged'] ?? 0, 1) ?></span>
                        <span class="civic-fed-stat-label">Hours Exchanged</span>
                    </div>
                    <div class="civic-fed-stat-card">
                        <span class="civic-fed-stat-value"><?= $partnerCount ?></span>
                        <span class="civic-fed-stat-label">Partner Timebanks</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Save Actions -->
        <div class="civic-fed-form-actions">
            <a href="<?= $basePath ?>/federation/dashboard" class="civic-fed-btn civic-fed-btn--secondary">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back
            </a>
            <button type="submit" class="civic-fed-btn civic-fed-btn--primary" id="saveBtn">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                Save Settings
            </button>
        </div>
    </form>

    <!-- Toast notification -->
    <div class="civic-fed-toast" id="toast" role="status" aria-live="polite"></div>
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
