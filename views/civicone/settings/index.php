<?php
// CivicOne View: Settings
$pageTitle = 'Account Settings';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$section = $_GET['section'] ?? 'general';
?>

<div class="civic-container">

    <div class="civic-card" style="margin-bottom: 30px;">
        <h1 style="text-transform: uppercase; font-size: 2rem; color: var(--skin-primary); margin: 0;">Settings</h1>
    </div>

    <div class="civic-settings-grid" style="display: grid; grid-template-columns: 250px 1fr; gap: 30px;">

        <!-- Sidebar -->
        <div class="civic-card" style="padding: 0; overflow: hidden; height: fit-content;">
            <a href="?section=general" style="display: block; padding: 15px 20px; text-decoration: none; color: <?= $section === 'general' ? 'white' : '#333' ?>; background: <?= $section === 'general' ? 'var(--skin-primary)' : 'transparent' ?>; border-bottom: 1px solid #eee;">General</a>
            <a href="?section=profile" style="display: block; padding: 15px 20px; text-decoration: none; color: <?= $section === 'profile' ? 'white' : '#333' ?>; background: <?= $section === 'profile' ? 'var(--skin-primary)' : 'transparent' ?>; border-bottom: 1px solid #eee;">Profile & Bio</a>
            <a href="?section=security" style="display: block; padding: 15px 20px; text-decoration: none; color: <?= $section === 'security' ? 'white' : '#333' ?>; background: <?= $section === 'security' ? 'var(--skin-primary)' : 'transparent' ?>; border-bottom: 1px solid #eee;">Security</a>
            <a href="?section=privacy" style="display: block; padding: 15px 20px; text-decoration: none; color: <?= $section === 'privacy' ? 'white' : '#333' ?>; background: <?= $section === 'privacy' ? 'var(--skin-primary)' : 'transparent' ?>;">Privacy</a>
        </div>

        <!-- Content Area -->
        <div class="civic-card">

            <?php if (isset($_GET['success'])): ?>
                <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    Settings updated successfully.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    An error occurred. Please check your inputs.
                </div>
            <?php endif; ?>

            <?php if ($section === 'general' || $section === 'profile'): ?>
                <h2 style="margin-top: 0; color: var(--skin-primary); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Profile Information</h2>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings/profile" method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Full Name</label>
                        <input type="text" name="name" class="civic-input" style="width: 100%;" value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">About Me (Bio)</label>
                        <textarea name="bio" class="civic-input" style="width: 100%;" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Profile Picture</label>
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= $user['avatar'] ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; display: block;">
                        <?php endif; ?>
                        <input type="file" name="avatar" class="civic-input" style="width: 100%;">
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="civic-btn">Save Profile</button>
                    </div>
                </form>

            <?php elseif ($section === 'security'): ?>
                <h2 style="margin-top: 0; color: var(--skin-primary); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Change Password</h2>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings/password" method="POST">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Current Password</label>
                        <input type="password" name="current_password" class="civic-input" style="width: 100%;" required>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">New Password</label>
                        <input type="password" name="new_password" class="civic-input" style="width: 100%;" required>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="civic-input" style="width: 100%;" required>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="civic-btn">Update Password</button>
                    </div>
                </form>

            <?php elseif ($section === 'privacy'): ?>
                <h2 style="margin-top: 0; color: var(--skin-primary); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Privacy Settings</h2>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings/privacy" method="POST">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Who can see my profile?</label>
                        <select name="privacy_profile" class="civic-input" style="width: 100%;">
                            <option value="public" <?= ($user['privacy_profile'] ?? 'public') === 'public' ? 'selected' : '' ?>>Everyone (Public)</option>
                            <option value="members" <?= ($user['privacy_profile'] ?? 'public') === 'members' ? 'selected' : '' ?>>Members Only</option>
                            <option value="connections" <?= ($user['privacy_profile'] ?? 'public') === 'connections' ? 'selected' : '' ?>>My Connections Only</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 20px; display: flex; align-items: center;">
                        <input type="checkbox" name="privacy_search" value="1" id="p_search" <?= !empty($user['privacy_search']) ? 'checked' : '' ?> style="width: 20px; height: 20px; margin-right: 10px;">
                        <label for="p_search">Allow me to be found in search results</label>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="civic-btn">Save Privacy</button>
                    </div>
                </form>
            <?php endif; ?>

        </div>

    </div>

</div>

<style>
    /* Settings page mobile responsive */
    @media (max-width: 768px) {
        .civic-settings-grid {
            grid-template-columns: 1fr !important;
            gap: 20px !important;
        }
        .civic-settings-grid .civic-card:first-child {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
        }
        .civic-settings-grid .civic-card:first-child a {
            flex: 1;
            min-width: 50%;
            text-align: center;
            padding: 12px 10px !important;
            font-size: 0.9rem;
        }
    }
    @media (max-width: 400px) {
        .civic-settings-grid .civic-card:first-child a {
            min-width: 100%;
        }
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>