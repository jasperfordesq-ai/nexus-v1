<?php
/**
 * Skeleton Layout - Settings Page
 * User account settings
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('Location: ' . $basePath . '/login');
    exit;
}

// Fetch user data
try {
    $user = \Nexus\Models\User::find($userId);
} catch (\Exception $e) {
    $user = null;
}
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Account Settings</h1>
<p style="color: #888; margin-bottom: 2rem;">Manage your profile and preferences</p>

<?php if (isset($_GET['success'])): ?>
    <div class="sk-alert sk-alert-success">
        Settings updated successfully!
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="sk-alert sk-alert-error">
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Sidebar Navigation -->
    <div class="sk-card">
        <nav style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="#profile" class="sk-btn sk-btn-outline" style="text-align: left; justify-content: flex-start;">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="#account" class="sk-btn sk-btn-outline" style="text-align: left; justify-content: flex-start;">
                <i class="fas fa-lock"></i> Account
            </a>
            <a href="#notifications" class="sk-btn sk-btn-outline" style="text-align: left; justify-content: flex-start;">
                <i class="fas fa-bell"></i> Notifications
            </a>
            <a href="#privacy" class="sk-btn sk-btn-outline" style="text-align: left; justify-content: flex-start;">
                <i class="fas fa-shield-alt"></i> Privacy
            </a>
        </nav>
    </div>

    <!-- Settings Content -->
    <div>
        <!-- Profile Settings -->
        <div id="profile" class="sk-card" style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Profile Information</h2>

            <form action="<?= $basePath ?>/settings" method="POST" enctype="multipart/form-data">
                <?= Csrf::input() ?>
                <input type="hidden" name="section" value="profile">

                <div class="sk-form-group">
                    <label class="sk-form-label">Profile Picture</label>
                    <div class="sk-flex" style="margin-bottom: 1rem;">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="sk-avatar" style="width: 80px; height: 80px;">
                        <?php else: ?>
                            <div class="sk-avatar" style="width: 80px; height: 80px; background: #ddd; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <input type="file" name="avatar" class="sk-form-input" accept="image/*">
                            <small style="color: #888;">JPG, PNG or GIF (max 5MB)</small>
                        </div>
                    </div>
                </div>

                <div class="sk-form-group">
                    <label for="name" class="sk-form-label">Full Name</label>
                    <input type="text" id="name" name="name" class="sk-form-input"
                           value="<?= htmlspecialchars($user['name'] ?? '') ?>">
                </div>

                <div class="sk-form-group">
                    <label for="bio" class="sk-form-label">Bio</label>
                    <textarea id="bio" name="bio" class="sk-form-textarea"
                              placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>

                <div class="sk-form-group">
                    <label for="location" class="sk-form-label">Location</label>
                    <input type="text" id="location" name="location" class="sk-form-input"
                           placeholder="City, State/Country"
                           value="<?= htmlspecialchars($user['location'] ?? '') ?>">
                </div>

                <button type="submit" class="sk-btn">Save Profile</button>
            </form>
        </div>

        <!-- Account Settings -->
        <div id="account" class="sk-card" style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Account Security</h2>

            <form action="<?= $basePath ?>/settings" method="POST">
                <?= Csrf::input() ?>
                <input type="hidden" name="section" value="account">

                <div class="sk-form-group">
                    <label for="email" class="sk-form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="sk-form-input"
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>

                <div class="sk-form-group">
                    <label for="current_password" class="sk-form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="sk-form-input"
                           placeholder="Enter current password to change">
                </div>

                <div class="sk-form-group">
                    <label for="new_password" class="sk-form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="sk-form-input"
                           placeholder="Leave blank to keep current">
                </div>

                <div class="sk-form-group">
                    <label for="confirm_password" class="sk-form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="sk-form-input"
                           placeholder="Confirm new password">
                </div>

                <button type="submit" class="sk-btn">Update Account</button>
            </form>
        </div>

        <!-- Notification Settings -->
        <div id="notifications" class="sk-card" style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Notification Preferences</h2>

            <form action="<?= $basePath ?>/settings" method="POST">
                <?= Csrf::input() ?>
                <input type="hidden" name="section" value="notifications">

                <div class="sk-form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="email_notifications" value="1" checked>
                        <span>Email notifications</span>
                    </label>
                </div>

                <div class="sk-form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="push_notifications" value="1" checked>
                        <span>Push notifications</span>
                    </label>
                </div>

                <div class="sk-form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="message_notifications" value="1" checked>
                        <span>New message notifications</span>
                    </label>
                </div>

                <button type="submit" class="sk-btn">Save Preferences</button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="sk-card" style="border-color: #f87171;">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: #dc2626;">Danger Zone</h2>
            <p style="color: #888; margin-bottom: 1rem;">Once you delete your account, there is no going back.</p>
            <button type="button" class="sk-btn" style="background: #dc2626;" onclick="confirm('Are you sure? This action cannot be undone.') && (window.location.href='<?= $basePath ?>/settings/delete-account')">
                Delete Account
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
