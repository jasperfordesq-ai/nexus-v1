<?php
// Federation Settings - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Federation Settings";
$pageSubtitle = "Manage your federation preferences";
$hideHero = true;

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
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-settings-wrapper">

        <!-- Hero Section -->
        <header class="fed-hero" role="banner">
            <div class="fed-hero-icon" aria-hidden="true">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <h1>Federation Settings</h1>
            <p class="fed-hero-subtitle">
                Control your privacy, visibility, and how you appear to members from partner timebanks.
            </p>
        </header>

        <?php $currentPage = 'settings'; $userOptedIn = $isOptedIn; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Status Banner -->
        <div class="fed-status-banner <?= $isOptedIn ? 'enabled' : 'disabled' ?>" role="status" aria-live="polite">
            <div class="fed-status-info">
                <div class="fed-status-icon" aria-hidden="true">
                    <i class="fa-solid <?= $isOptedIn ? 'fa-check' : 'fa-eye-slash' ?>"></i>
                </div>
                <div class="fed-status-text">
                    <h2>Federation is <?= $isOptedIn ? 'Enabled' : 'Disabled' ?></h2>
                    <p><?= $isOptedIn
                        ? 'Your profile is visible to ' . $partnerCount . ' partner timebank' . ($partnerCount !== 1 ? 's' : '')
                        : 'Your profile is hidden from partner timebanks' ?></p>
                </div>
            </div>
            <button type="button" class="fed-status-toggle" id="statusToggle" aria-describedby="status-help">
                <?= $isOptedIn ? 'Disable Federation' : 'Enable Federation' ?>
            </button>
            <span id="status-help" class="visually-hidden">
                <?= $isOptedIn ? 'Click to hide your profile from partner timebanks' : 'Click to make your profile visible to partner timebanks' ?>
            </span>
        </div>

        <form id="settingsForm" aria-label="Federation settings form">
            <!-- Privacy Level -->
            <section class="fed-settings-card" aria-labelledby="privacy-heading">
                <h2 id="privacy-heading"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Privacy Level</h2>
                <p>Choose how much of your profile to share with partner timebanks</p>

                <fieldset class="fed-privacy-options" role="radiogroup" aria-labelledby="privacy-heading">
                    <legend class="visually-hidden">Privacy level options</legend>

                    <label class="fed-privacy-option <?= $privacyLevel === 'discovery' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="discovery" <?= $privacyLevel === 'discovery' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio" aria-hidden="true"></span>
                        <div class="fed-privacy-content">
                            <h4>Discovery Only</h4>
                            <p>Only your name and avatar are visible. Good for browsing without sharing details.</p>
                        </div>
                    </label>

                    <label class="fed-privacy-option <?= $privacyLevel === 'social' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="social" <?= $privacyLevel === 'social' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio" aria-hidden="true"></span>
                        <div class="fed-privacy-content">
                            <h4>Social <span class="fed-privacy-badge">Recommended</span></h4>
                            <p>Share your skills, bio, and location. Enables messaging with partner members.</p>
                        </div>
                    </label>

                    <label class="fed-privacy-option <?= $privacyLevel === 'economic' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="economic" <?= $privacyLevel === 'economic' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio" aria-hidden="true"></span>
                        <div class="fed-privacy-content">
                            <h4>Economic</h4>
                            <p>Full profile sharing plus ability to send/receive time credits across timebanks.</p>
                        </div>
                    </label>
                </fieldset>
            </section>

            <!-- Fine-tune Settings -->
            <section class="fed-settings-card" aria-labelledby="visibility-heading">
                <h2 id="visibility-heading"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Visibility Options</h2>
                <p>Fine-tune what information is shared</p>

                <div class="fed-toggle-list" role="group" aria-labelledby="visibility-heading">
                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4 id="search-toggle-label">Show in Federated Search</h4>
                            <p id="search-toggle-desc">Appear in search results for partner timebank members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="appear_in_search" <?= !empty($userSettings['appear_in_federated_search']) ? 'checked' : '' ?> aria-labelledby="search-toggle-label" aria-describedby="search-toggle-desc">
                            <span class="fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4 id="profile-toggle-label">Profile Visible</h4>
                            <p id="profile-toggle-desc">Allow partner members to view your full profile</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="profile_visible" <?= !empty($userSettings['profile_visible_federated']) ? 'checked' : '' ?> aria-labelledby="profile-toggle-label" aria-describedby="profile-toggle-desc">
                            <span class="fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4 id="location-toggle-label">Show Location</h4>
                            <p id="location-toggle-desc">Display your city/region to partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="show_location" <?= !empty($userSettings['show_location_federated']) ? 'checked' : '' ?> aria-labelledby="location-toggle-label" aria-describedby="location-toggle-desc">
                            <span class="fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4 id="skills-toggle-label">Show Skills</h4>
                            <p id="skills-toggle-desc">Display your skills and services to partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="show_skills" <?= !empty($userSettings['show_skills_federated']) ? 'checked' : '' ?> aria-labelledby="skills-toggle-label" aria-describedby="skills-toggle-desc">
                            <span class="fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4 id="messaging-toggle-label">Receive Messages</h4>
                            <p id="messaging-toggle-desc">Allow partner members to send you messages</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="messaging_enabled" <?= !empty($userSettings['messaging_enabled_federated']) ? 'checked' : '' ?> aria-labelledby="messaging-toggle-label" aria-describedby="messaging-toggle-desc">
                            <span class="fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4 id="transactions-toggle-label">Accept Transactions</h4>
                            <p id="transactions-toggle-desc">Allow receiving time credits from partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="transactions_enabled" <?= !empty($userSettings['transactions_enabled_federated']) ? 'checked' : '' ?> aria-labelledby="transactions-toggle-label" aria-describedby="transactions-toggle-desc">
                            <span class="fed-toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                </div>
            </section>

            <!-- Service Reach -->
            <section class="fed-settings-card" aria-labelledby="reach-heading">
                <h2 id="reach-heading"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Service Reach</h2>
                <p>How far are you willing to travel for exchanges?</p>

                <fieldset class="fed-reach-options" role="radiogroup" aria-labelledby="reach-heading">
                    <legend class="visually-hidden">Service reach options</legend>

                    <label class="fed-reach-option <?= $serviceReach === 'local_only' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="local_only" <?= $serviceReach === 'local_only' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <span>Local Only</span>
                    </label>

                    <label class="fed-reach-option <?= $serviceReach === 'will_travel' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="will_travel" <?= $serviceReach === 'will_travel' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-car" aria-hidden="true"></i>
                        <span>Will Travel</span>
                    </label>

                    <label class="fed-reach-option <?= $serviceReach === 'remote_ok' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="remote_ok" <?= $serviceReach === 'remote_ok' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-laptop" aria-hidden="true"></i>
                        <span>Remote OK</span>
                    </label>
                </fieldset>
            </section>

            <!-- Activity Summary -->
            <section class="fed-settings-card" aria-labelledby="activity-heading">
                <h2 id="activity-heading"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Your Federation Activity</h2>
                <p>Summary of your cross-timebank interactions</p>

                <div class="fed-stats-summary" role="region" aria-label="Activity statistics">
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= number_format(($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0)) ?></div>
                        <div class="fed-stat-label">Messages Exchanged</div>
                    </div>
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= number_format($stats['transactions_count'] ?? 0) ?></div>
                        <div class="fed-stat-label">Transactions</div>
                    </div>
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= number_format($stats['hours_exchanged'] ?? 0, 1) ?></div>
                        <div class="fed-stat-label">Hours Exchanged</div>
                    </div>
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= $partnerCount ?></div>
                        <div class="fed-stat-label">Partner Timebanks</div>
                    </div>
                </div>
            </section>

            <!-- Save Button -->
            <div class="fed-save-section">
                <a href="<?= $basePath ?>/federation/dashboard" class="fed-back-btn" aria-label="Go back to dashboard">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back
                </a>
                <button type="submit" class="fed-save-btn" id="saveBtn">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Save Settings
                </button>
            </div>
        </form>

        <!-- Toast notification -->
        <div class="fed-toast" id="toast" role="status" aria-live="polite"></div>

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
