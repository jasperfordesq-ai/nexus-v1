<?php
// Federation Settings - User Preferences Page
$pageTitle = $pageTitle ?? "Federation Settings";
$pageSubtitle = "Manage your federation preferences";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
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

<div class="htb-container-full">
    <div id="federation-settings-wrapper">

<!-- Hero Section -->
        <div class="fed-hero">
            <div class="fed-hero-icon">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <h1>Federation Settings</h1>
            <p class="fed-hero-subtitle">
                Control your privacy, visibility, and how you appear to members from partner timebanks.
            </p>
        </div>

        <?php $currentPage = 'settings'; $userOptedIn = $isOptedIn; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Status Banner -->
        <div class="fed-status-banner <?= $isOptedIn ? 'enabled' : 'disabled' ?>">
            <div class="fed-status-info">
                <div class="fed-status-icon">
                    <i class="fa-solid <?= $isOptedIn ? 'fa-check' : 'fa-eye-slash' ?>"></i>
                </div>
                <div class="fed-status-text">
                    <h3>Federation is <?= $isOptedIn ? 'Enabled' : 'Disabled' ?></h3>
                    <p><?= $isOptedIn
                        ? 'Your profile is visible to ' . $partnerCount . ' partner timebank' . ($partnerCount !== 1 ? 's' : '')
                        : 'Your profile is hidden from partner timebanks' ?></p>
                </div>
            </div>
            <button class="fed-status-toggle" id="statusToggle">
                <?= $isOptedIn ? 'Disable Federation' : 'Enable Federation' ?>
            </button>
        </div>

        <form id="settingsForm">
            <!-- Privacy Level -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-shield-halved"></i> Privacy Level</h2>
                <p>Choose how much of your profile to share with partner timebanks</p>

                <div class="fed-privacy-options">
                    <label class="fed-privacy-option <?= $privacyLevel === 'discovery' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="discovery" <?= $privacyLevel === 'discovery' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio"></span>
                        <div class="fed-privacy-content">
                            <h4>Discovery Only</h4>
                            <p>Only your name and avatar are visible. Good for browsing without sharing details.</p>
                        </div>
                    </label>

                    <label class="fed-privacy-option <?= $privacyLevel === 'social' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="social" <?= $privacyLevel === 'social' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio"></span>
                        <div class="fed-privacy-content">
                            <h4>Social <span class="fed-privacy-badge">Recommended</span></h4>
                            <p>Share your skills, bio, and location. Enables messaging with partner members.</p>
                        </div>
                    </label>

                    <label class="fed-privacy-option <?= $privacyLevel === 'economic' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="economic" <?= $privacyLevel === 'economic' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio"></span>
                        <div class="fed-privacy-content">
                            <h4>Economic</h4>
                            <p>Full profile sharing plus ability to send/receive time credits across timebanks.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Fine-tune Settings -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-sliders"></i> Visibility Options</h2>
                <p>Fine-tune what information is shared</p>

                <div class="fed-toggle-list">
                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Show in Federated Search</h4>
                            <p>Appear in search results for partner timebank members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="appear_in_search" <?= !empty($userSettings['appear_in_federated_search']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Profile Visible</h4>
                            <p>Allow partner members to view your full profile</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="profile_visible" <?= !empty($userSettings['profile_visible_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Show Location</h4>
                            <p>Display your city/region to partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="show_location" <?= !empty($userSettings['show_location_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Show Skills</h4>
                            <p>Display your skills and services to partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="show_skills" <?= !empty($userSettings['show_skills_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Receive Messages</h4>
                            <p>Allow partner members to send you messages</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="messaging_enabled" <?= !empty($userSettings['messaging_enabled_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Accept Transactions</h4>
                            <p>Allow receiving time credits from partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="transactions_enabled" <?= !empty($userSettings['transactions_enabled_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- AI Assistant Options -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-robot"></i> AI Assistant</h2>
                <p>Customize the AI assistant appearance</p>

                <div class="fed-toggle-list">
                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>AI Button Animation</h4>
                            <p>Show pulsing animation on the AI assistant button</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="ai_pulse_enabled" id="ai_pulse_enabled" <?= !empty($userSettings['ai_pulse_enabled']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Service Reach -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-location-dot"></i> Service Reach</h2>
                <p>How far are you willing to travel for exchanges?</p>

                <div class="fed-reach-options">
                    <label class="fed-reach-option <?= $serviceReach === 'local_only' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="local_only" <?= $serviceReach === 'local_only' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-house"></i>
                        <span>Local Only</span>
                    </label>

                    <label class="fed-reach-option <?= $serviceReach === 'will_travel' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="will_travel" <?= $serviceReach === 'will_travel' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-car"></i>
                        <span>Will Travel</span>
                    </label>

                    <label class="fed-reach-option <?= $serviceReach === 'remote_ok' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="remote_ok" <?= $serviceReach === 'remote_ok' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-laptop"></i>
                        <span>Remote OK</span>
                    </label>
                </div>
            </div>

            <!-- Activity Summary -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-chart-simple"></i> Your Federation Activity</h2>
                <p>Summary of your cross-timebank interactions</p>

                <div class="fed-stats-summary">
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
            </div>

            <!-- Save Button -->
            <div class="fed-save-section">
                <a href="<?= $basePath ?>/federation/dashboard" class="fed-back-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back
                </a>
                <button type="submit" class="fed-save-btn" id="saveBtn">
                    <i class="fa-solid fa-check"></i>
                    Save Settings
                </button>
            </div>
        </form>

        <!-- Toast notification -->
        <div class="fed-toast" id="toast"></div>

    </div>
</div>

<script>
(function() {
    const form = document.getElementById('settingsForm');
    const saveBtn = document.getElementById('saveBtn');
    const statusToggle = document.getElementById('statusToggle');
    const toast = document.getElementById('toast');
    const csrfToken = '<?= \Nexus\Core\Csrf::token() ?>';
    let isOptedIn = <?= $isOptedIn ? 'true' : 'false' ?>;

    // Privacy level selection
    document.querySelectorAll('.fed-privacy-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.fed-privacy-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
        });
    });

    // Service reach selection
    document.querySelectorAll('.fed-reach-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.fed-reach-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
        });
    });

    // Show toast
    function showToast(message, type = 'success') {
        toast.textContent = message;
        toast.className = 'fed-toast ' + type + ' visible';
        setTimeout(() => {
            toast.classList.remove('visible');
        }, 3000);
    }

    // Status toggle (enable/disable federation)
    statusToggle.addEventListener('click', async function() {
        const action = isOptedIn ? 'disable' : 'enable';
        const confirmMsg = isOptedIn
            ? 'Are you sure you want to disable federation? Your profile will be hidden from all partner timebanks.'
            : 'Enable federation to make your profile visible to partner timebanks?';

        if (!confirm(confirmMsg)) return;

        this.disabled = true;
        this.textContent = 'Processing...';

        try {
            const response = await fetch('<?= $basePath ?>/federation/settings/' + action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({})
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1000);
                } else {
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                showToast(data.error || 'Failed to update', 'error');
                this.disabled = false;
                this.textContent = isOptedIn ? 'Disable Federation' : 'Enable Federation';
            }
        } catch (error) {
            showToast('Network error. Please try again.', 'error');
            this.disabled = false;
            this.textContent = isOptedIn ? 'Disable Federation' : 'Enable Federation';
        }
    });

    // Save settings
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        const formData = {
            federation_optin: isOptedIn,
            privacy_level: form.querySelector('input[name="privacy_level"]:checked')?.value || 'discovery',
            service_reach: form.querySelector('input[name="service_reach"]:checked')?.value || 'local_only',
            appear_in_search: form.querySelector('input[name="appear_in_search"]').checked,
            profile_visible: form.querySelector('input[name="profile_visible"]').checked,
            show_location: form.querySelector('input[name="show_location"]').checked,
            show_skills: form.querySelector('input[name="show_skills"]').checked,
            messaging_enabled: form.querySelector('input[name="messaging_enabled"]').checked,
            transactions_enabled: form.querySelector('input[name="transactions_enabled"]').checked,
            ai_pulse_enabled: form.querySelector('input[name="ai_pulse_enabled"]').checked
        };

        try {
            const response = await fetch('<?= $basePath ?>/federation/settings/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.error || 'Failed to save settings', 'error');
            }
        } catch (error) {
            showToast('Network error. Please try again.', 'error');
        }

        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Save Settings';
    });
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
