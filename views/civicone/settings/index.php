<?php
// CivicOne View: Settings - WCAG 2.1 AA Compliant
// CSS extracted to civicone-help.css
$pageTitle = 'Account Settings';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$section = $_GET['section'] ?? 'general';
?>

<div class="civic-container">

    <div class="civic-card civic-settings-header">
        <h1 class="civic-settings-title">Settings</h1>
    </div>

    <div class="civic-settings-grid">

        <!-- Sidebar Navigation -->
        <nav class="civic-card civic-settings-sidebar" aria-label="Settings sections">
            <a href="?section=general"
               class="civic-settings-nav-link <?= $section === 'general' ? 'civic-settings-nav-link--active' : '' ?>"
               <?= $section === 'general' ? 'aria-current="page"' : '' ?>>
                General
            </a>
            <a href="?section=profile"
               class="civic-settings-nav-link <?= $section === 'profile' ? 'civic-settings-nav-link--active' : '' ?>"
               <?= $section === 'profile' ? 'aria-current="page"' : '' ?>>
                Profile &amp; Bio
            </a>
            <a href="?section=security"
               class="civic-settings-nav-link <?= $section === 'security' ? 'civic-settings-nav-link--active' : '' ?>"
               <?= $section === 'security' ? 'aria-current="page"' : '' ?>>
                Security
            </a>
            <a href="?section=privacy"
               class="civic-settings-nav-link <?= $section === 'privacy' ? 'civic-settings-nav-link--active' : '' ?>"
               <?= $section === 'privacy' ? 'aria-current="page"' : '' ?>>
                Privacy
            </a>
        </nav>

        <!-- Content Area -->
        <div class="civic-card civic-settings-content">

            <?php if (isset($_GET['success'])): ?>
                <div class="civic-settings-alert civic-settings-alert--success" role="status">
                    Settings updated successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="civic-settings-alert civic-settings-alert--error" role="alert">
                    An error occurred. Please check your inputs.
                </div>
            <?php endif; ?>

            <?php if ($section === 'general' || $section === 'profile'): ?>
                <h2 class="civic-settings-section-title">Profile Information</h2>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings/profile" method="POST" enctype="multipart/form-data">
                    <div class="civic-settings-field">
                        <label for="settings-name" class="civic-settings-label">Full Name</label>
                        <input type="text"
                               name="name"
                               id="settings-name"
                               class="civic-input civic-settings-input"
                               value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                    </div>

                    <div class="civic-settings-field">
                        <label for="settings-bio" class="civic-settings-label">About Me (Bio)</label>
                        <textarea name="bio"
                                  id="settings-bio"
                                  class="civic-input civic-settings-textarea"
                                  rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="civic-settings-field">
                        <label for="settings-avatar" class="civic-settings-label">Profile Picture</label>
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= $user['avatar'] ?>"
                                 alt="Current profile picture"
                                 class="civic-settings-avatar-preview">
                        <?php endif; ?>
                        <input type="file"
                               name="avatar"
                               id="settings-avatar"
                               class="civic-input civic-settings-input"
                               accept="image/*">
                    </div>

                    <div class="civic-settings-actions">
                        <button type="submit" class="civic-btn">Save Profile</button>
                    </div>
                </form>

            <?php elseif ($section === 'security'): ?>
                <h2 class="civic-settings-section-title">Change Password</h2>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings/password" method="POST">
                    <div class="civic-settings-field">
                        <label for="current-password" class="civic-settings-label">Current Password</label>
                        <input type="password"
                               name="current_password"
                               id="current-password"
                               class="civic-input civic-settings-input"
                               required
                               autocomplete="current-password">
                    </div>

                    <div class="civic-settings-field">
                        <label for="new-password" class="civic-settings-label">New Password</label>
                        <input type="password"
                               name="new_password"
                               id="new-password"
                               class="civic-input civic-settings-input"
                               required
                               autocomplete="new-password">
                    </div>

                    <div class="civic-settings-field">
                        <label for="confirm-password" class="civic-settings-label">Confirm New Password</label>
                        <input type="password"
                               name="confirm_password"
                               id="confirm-password"
                               class="civic-input civic-settings-input"
                               required
                               autocomplete="new-password">
                    </div>

                    <div class="civic-settings-actions">
                        <button type="submit" class="civic-btn">Update Password</button>
                    </div>
                </form>

            <?php elseif ($section === 'privacy'): ?>
                <h2 class="civic-settings-section-title">Privacy Settings</h2>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings/privacy" method="POST">
                    <div class="civic-settings-field">
                        <label for="privacy-profile" class="civic-settings-label">Who can see my profile?</label>
                        <select name="privacy_profile" id="privacy-profile" class="civic-input civic-settings-select">
                            <option value="public" <?= ($user['privacy_profile'] ?? 'public') === 'public' ? 'selected' : '' ?>>Everyone (Public)</option>
                            <option value="members" <?= ($user['privacy_profile'] ?? 'public') === 'members' ? 'selected' : '' ?>>Members Only</option>
                            <option value="connections" <?= ($user['privacy_profile'] ?? 'public') === 'connections' ? 'selected' : '' ?>>My Connections Only</option>
                        </select>
                    </div>

                    <div class="civic-settings-checkbox-field">
                        <input type="checkbox"
                               name="privacy_search"
                               value="1"
                               id="privacy-search"
                               class="civic-settings-checkbox"
                               <?= !empty($user['privacy_search']) ? 'checked' : '' ?>>
                        <label for="privacy-search" class="civic-settings-checkbox-label">
                            Allow me to be found in search results
                        </label>
                    </div>

                    <div class="civic-settings-actions">
                        <button type="submit" class="civic-btn">Save Privacy</button>
                    </div>
                </form>
            <?php endif; ?>

        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
