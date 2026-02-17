<?php
/**
 * Admin Edit User - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Check if current user is super admin (can grant super admin privileges)
$currentUserIsSuperAdmin = !empty($_SESSION['is_super_admin']);

// Check if the user being edited is already a super admin
$userIsSuperAdmin = !empty($user['is_super_admin']);

// Admin header configuration
$adminPageTitle = 'Edit User';
$adminPageSubtitle = 'User Management';
$adminPageIcon = 'fa-user-pen';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-pen"></i>
            Edit User
        </h1>
        <p class="admin-page-subtitle">Update account details and permissions</p>
    </div>
    <div class="admin-page-header-actions">
        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
        <form action="<?= $basePath ?>/admin-legacy/impersonate" method="POST" onsubmit="return confirm('You are about to login as <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>. Continue?');" style="display:inline;">
            <?= Csrf::input() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-warning">
                <i class="fa-solid fa-user-secret"></i> Login As User
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= $basePath ?>/admin-legacy/users/<?= $user['id'] ?>/permissions" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-key"></i> Permissions
        </a>
        <a href="<?= $basePath ?>/admin-legacy/users" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['badge_added'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>
        <strong>Success!</strong> Badge has been awarded to the user.
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['badges_rechecked'])): ?>
<div class="admin-alert admin-alert-info">
    <i class="fa-solid fa-rotate"></i>
    <div>
        <strong>Done!</strong> All badge eligibility has been rechecked. Any newly qualified badges have been awarded.
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['badge_removed'])): ?>
<div class="admin-alert admin-alert-warning">
    <i class="fa-solid fa-trash"></i>
    <div>
        <strong>Done.</strong> Badge has been removed.
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <div>
        <strong>Error:</strong>
        <?php if ($_GET['error'] === 'already_has_badge'): ?>
            User already has this badge.
        <?php else: ?>
            <?= htmlspecialchars($_GET['msg'] ?? 'An unknown error occurred.') ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- User Profile Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-user-gear"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">User Profile</h3>
            <p class="admin-card-subtitle">Manage account settings and details</p>
        </div>
    </div>
    <div class="admin-card-body">
        <!-- User Header -->
        <div class="user-profile-header">
            <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" class="user-profile-avatar" alt="">
            <?php else: ?>
                <div class="user-profile-avatar-placeholder">
                    <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="user-profile-info">
                <h2 class="user-profile-name"><?= htmlspecialchars($user['name'] ?? $user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <div class="user-profile-email"><?= htmlspecialchars($user['email']) ?></div>
                <div class="user-profile-badges">
                    <?php if ($userIsSuperAdmin): ?>
                        <span class="admin-badge admin-badge-super-admin"><i class="fa-solid fa-crown"></i> Super Admin</span>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="admin-badge admin-badge-danger">Administrator</span>
                    <?php elseif ($user['role'] === 'newsletter_admin'): ?>
                        <span class="admin-badge admin-badge-warning">Newsletter Admin</span>
                    <?php else: ?>
                        <span class="admin-badge admin-badge-primary">Member</span>
                    <?php endif; ?>
                    <?php if ($user['is_approved']): ?>
                        <span class="admin-badge admin-badge-success">Active</span>
                    <?php else: ?>
                        <span class="admin-badge admin-badge-warning">Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form action="<?= $basePath ?>/admin-legacy/users/update" method="POST">
            <?= Csrf::input() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

            <!-- Personal Details Section -->
            <div class="admin-form-section">
                <h4 class="admin-form-section-title">
                    <i class="fa-solid fa-id-card"></i> Personal Details
                </h4>
                <div class="admin-form-grid">
                    <div class="admin-form-group">
                        <label class="admin-label">First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required class="admin-input">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required class="admin-input">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Location / Area</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="Start typing your town or city..." class="admin-input location-input">
                    <input type="hidden" name="latitude" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                    <input type="hidden" name="longitude" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
                </div>
            </div>

            <!-- Contact & Access Section -->
            <div class="admin-form-section">
                <h4 class="admin-form-section-title">
                    <i class="fa-solid fa-envelope"></i> Contact & Access
                </h4>
                <div class="admin-form-grid">
                    <div class="admin-form-group">
                        <label class="admin-label">Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="admin-input">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="admin-input">
                    </div>
                </div>
            </div>

            <!-- Administrative Controls Section -->
            <div class="admin-form-section admin-form-section-highlight">
                <h4 class="admin-form-section-title">
                    <i class="fa-solid fa-shield-halved"></i> Administrative Controls
                </h4>
                <div class="admin-form-grid">
                    <div class="admin-form-group">
                        <label class="admin-label">System Role</label>
                        <select name="role" class="admin-select" id="role-select">
                            <option value="member" <?= $user['role'] === 'member' ? 'selected' : '' ?>>Member (Standard)</option>
                            <option value="newsletter_admin" <?= $user['role'] === 'newsletter_admin' ? 'selected' : '' ?>>Newsletter Admin</option>
                            <option value="tenant_admin" <?= $user['role'] === 'tenant_admin' && !$userIsSuperAdmin ? 'selected' : '' ?>>Tenant Admin</option>
                            <option value="admin" <?= $user['role'] === 'admin' && !$userIsSuperAdmin ? 'selected' : '' ?>>Administrator</option>
                            <?php if ($currentUserIsSuperAdmin): ?>
                            <option value="super_admin" <?= (($user['role'] === 'admin' || $user['role'] === 'tenant_admin') && $userIsSuperAdmin) ? 'selected' : '' ?>>Super Admin (Platform-Wide)</option>
                            <?php endif; ?>
                        </select>
                        <p class="admin-form-hint">Newsletter Admins can only access the newsletter module. Tenant Admins manage their tenant. Full Admins have complete access. Super Admins can access all tenants.</p>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Account Status</label>
                        <select name="status" class="admin-select">
                            <option value="0" <?= !$user['is_approved'] ? 'selected' : '' ?>>Pending Approval</option>
                            <option value="1" <?= $user['is_approved'] ? 'selected' : '' ?>>Active / Approved</option>
                        </select>
                        <p class="admin-form-hint">Pending users cannot log in.</p>
                    </div>
                </div>
                <?php if (!empty($_SESSION['is_god'])): ?>
                <!-- Super Admin Grant (only visible to god users) -->
                <div class="admin-super-admin-grant" id="super-admin-grant" style="display: <?= in_array($user['role'], ['admin', 'tenant_admin']) ? 'block' : 'none' ?>;">
                    <div class="super-admin-grant-box">
                        <div class="super-admin-grant-header">
                            <i class="fa-solid fa-crown"></i>
                            <div>
                                <strong>Platform Super Admin</strong>
                                <span>Grant cross-tenant administrative access</span>
                            </div>
                        </div>
                        <label class="admin-checkbox-label super-admin-checkbox">
                            <input type="checkbox" name="is_super_admin" value="1" <?= $userIsSuperAdmin ? 'checked' : '' ?>>
                            <span class="admin-checkbox-text">
                                Make this user a <strong>Super Admin</strong>
                            </span>
                        </label>
                        <p class="super-admin-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            Super Admins can access ALL tenants and manage the entire platform. Only grant this to trusted personnel.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Form Actions -->
            <div class="admin-form-actions">
                <a href="<?= $basePath ?>/admin-legacy/users" class="admin-btn admin-btn-secondary">Cancel</a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Achievements & Badges Card -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #a855f7, #ec4899);">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Achievements & Badges</h3>
            <p class="admin-card-subtitle">Manage user recognition and rewards</p>
        </div>
    </div>
    <div class="admin-card-body">
        <!-- Current Badges -->
        <div class="badges-section">
            <h4 class="badges-section-title">Current Badges</h4>
            <div class="badges-list">
                <?php if (!empty($badges)): ?>
                    <?php foreach ($badges as $badge): ?>
                        <div class="badge-item">
                            <span class="badge-icon"><?= $badge['icon'] ?></span>
                            <span class="badge-name"><?= htmlspecialchars($badge['name']) ?></span>
                            <form action="<?= $basePath ?>/admin-legacy/users/badges/remove" method="POST" style="margin: 0;">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="badge_key" value="<?= $badge['badge_key'] ?>">
                                <button type="submit" class="badge-remove-btn" onclick="return confirm('Remove this badge?');" title="Remove Badge">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="badges-empty">No badges awarded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Award Section -->
        <?php if (!empty($availableBadges)): ?>
        <div class="badges-section">
            <h4 class="badges-section-title">Quick Award (Standard Badges)</h4>
            <div class="badges-grid">
                <?php foreach ($availableBadges as $def): ?>
                    <?php
                    $hasBadge = false;
                    if (!empty($badges)) {
                        foreach ($badges as $b) {
                            if ($b['badge_key'] === $def['key']) $hasBadge = true;
                        }
                    }
                    ?>
                    <form action="<?= $basePath ?>/admin-legacy/users/badges/add" method="POST">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="badge_name" value="<?= $def['name'] ?>">
                        <input type="hidden" name="badge_icon" value="<?= $def['icon'] ?>">
                        <input type="hidden" name="badge_key" value="<?= $def['key'] ?>">
                        <button type="submit" class="quick-badge-btn <?= $hasBadge ? 'has-badge' : '' ?>" <?= $hasBadge ? 'disabled' : '' ?>>
                            <span class="quick-badge-icon"><?= $def['icon'] ?></span>
                            <span class="quick-badge-name"><?= htmlspecialchars($def['name']) ?></span>
                            <?php if ($hasBadge): ?>
                                <i class="fa-solid fa-check quick-badge-check"></i>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Manual Badge Award -->
        <div class="badges-section">
            <h4 class="badges-section-title">Award Manual Badge</h4>
            <form action="<?= $basePath ?>/admin-legacy/users/badges/add" method="POST" class="manual-badge-form">
                <?= Csrf::input() ?>
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <div class="manual-badge-inputs">
                    <input type="text" name="badge_icon" placeholder="🏆" required class="admin-input badge-icon-input" maxlength="4" title="Emoji Icon">
                    <input type="text" name="badge_name" placeholder="Badge Name (e.g. Event Superstar)" required class="admin-input badge-name-input">
                    <button type="submit" class="admin-btn admin-btn-purple">
                        <i class="fa-solid fa-award"></i> Award Badge
                    </button>
                </div>
            </form>
        </div>

        <!-- Auto-Award Section -->
        <div class="badges-section">
            <h4 class="badges-section-title">Auto-Award Badges</h4>
            <p class="badges-section-desc">Scan this user's activity and automatically award any badges they qualify for but haven't received yet.</p>
            <form action="<?= $basePath ?>/admin-legacy/users/badges/recheck" method="POST">
                <?= Csrf::input() ?>
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <button type="submit" class="admin-btn admin-btn-cyan">
                    <i class="fa-solid fa-rotate"></i> Recheck All Badges
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* User Edit Page Specific Styles */

/* Alerts */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-alert i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-alert-success {
    border-left: 3px solid #22c55e;
}
.admin-alert-success i { color: #22c55e; }

.admin-alert-info {
    border-left: 3px solid #06b6d4;
}
.admin-alert-info i { color: #06b6d4; }

.admin-alert-warning {
    border-left: 3px solid #f59e0b;
}
.admin-alert-warning i { color: #f59e0b; }

.admin-alert-error {
    border-left: 3px solid #ef4444;
}
.admin-alert-error i { color: #ef4444; }

/* User Profile Header */
.user-profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.user-profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    object-fit: cover;
    border: 2px solid rgba(99, 102, 241, 0.3);
}

.user-profile-avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 2rem;
    flex-shrink: 0;
}

.user-profile-info {
    flex: 1;
}

.user-profile-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 4px 0;
}

.user-profile-email {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    margin-bottom: 0.75rem;
}

.user-profile-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Form Sections */
.admin-form-section {
    margin-bottom: 2rem;
}

.admin-form-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-form-section-title i {
    color: #818cf8;
}

.admin-form-section-highlight {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 1.5rem;
}

.admin-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.admin-form-group {
    margin-bottom: 1rem;
}

.admin-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 0.5rem;
}

.admin-input,
.admin-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.admin-input:focus,
.admin-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.admin-select {
    cursor: pointer;
}

.admin-select option {
    background: #1e293b;
    color: #f1f5f9;
}

.admin-form-hint {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.5rem;
    margin-bottom: 0;
}

.admin-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-purple {
    background: linear-gradient(135deg, #a855f7, #ec4899);
    color: white;
    border: 1px solid rgba(168, 85, 247, 0.5);
}

.admin-btn-purple:hover {
    background: linear-gradient(135deg, #9333ea, #db2777);
}

.admin-btn-cyan {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
    border: 1px solid rgba(6, 182, 212, 0.5);
}

.admin-btn-cyan:hover {
    background: linear-gradient(135deg, #0891b2, #0e7490);
}

/* Badges */
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.admin-badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.admin-badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-badge-success {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-badge-super-admin {
    background: linear-gradient(135deg, rgba(147, 51, 234, 0.3), rgba(236, 72, 153, 0.2));
    color: #fbbf24;
    border: 1px solid rgba(147, 51, 234, 0.4);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.admin-badge-super-admin i {
    font-size: 0.7rem;
    filter: drop-shadow(0 0 2px rgba(251, 191, 36, 0.5));
}

/* Badges Section */
.badges-section {
    padding: 1.5rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.badges-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.badges-section:first-child {
    padding-top: 0;
}

.badges-section-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #c4b5fd;
    margin: 0 0 1rem 0;
}

.badges-section-desc {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
    margin: 0 0 1rem 0;
}

.badges-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.badges-empty {
    color: rgba(255, 255, 255, 0.5);
    font-style: italic;
    margin: 0;
}

.badge-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(168, 85, 247, 0.15);
    border: 1px solid rgba(168, 85, 247, 0.3);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
}

.badge-icon {
    font-size: 1.2rem;
}

.badge-name {
    font-weight: 600;
    color: #c4b5fd;
    font-size: 0.875rem;
}

.badge-remove-btn {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 0 0 0 0.25rem;
    font-size: 0.875rem;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.badge-remove-btn:hover {
    opacity: 1;
}

/* Badges Grid */
.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.75rem;
}

.quick-badge-btn {
    width: 100%;
    text-align: left;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(168, 85, 247, 0.3);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.quick-badge-btn:hover:not(:disabled) {
    background: rgba(168, 85, 247, 0.2);
    border-color: rgba(168, 85, 247, 0.5);
}

.quick-badge-btn.has-badge {
    background: rgba(30, 41, 59, 0.3);
    border-color: rgba(255, 255, 255, 0.1);
    cursor: not-allowed;
}

.quick-badge-icon {
    font-size: 1.1rem;
}

.quick-badge-name {
    font-size: 0.8rem;
    font-weight: 500;
    color: #c4b5fd;
    flex: 1;
}

.quick-badge-btn.has-badge .quick-badge-name {
    color: rgba(255, 255, 255, 0.4);
}

.quick-badge-check {
    color: #22c55e;
    font-size: 0.85rem;
}

/* Manual Badge Form */
.manual-badge-form {
    margin-top: 0.5rem;
}

.manual-badge-inputs {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.badge-icon-input {
    width: 70px;
    text-align: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.badge-name-input {
    flex: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .user-profile-header {
        flex-direction: column;
        text-align: center;
    }

    .user-profile-badges {
        justify-content: center;
    }

    .admin-form-grid {
        grid-template-columns: 1fr;
    }

    .admin-form-actions {
        flex-direction: column;
    }

    .admin-form-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .badges-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .manual-badge-inputs {
        flex-direction: column;
    }

    .badge-icon-input {
        width: 100%;
    }

    .manual-badge-inputs .admin-btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .badges-grid {
        grid-template-columns: 1fr;
    }
}

/* Super Admin Grant Section */
.admin-super-admin-grant {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(147, 51, 234, 0.3);
}

.super-admin-grant-box {
    background: linear-gradient(135deg, rgba(147, 51, 234, 0.15), rgba(236, 72, 153, 0.1));
    border: 1px solid rgba(147, 51, 234, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
}

.super-admin-grant-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.super-admin-grant-header i {
    font-size: 1.5rem;
    color: #fbbf24;
    filter: drop-shadow(0 0 4px rgba(251, 191, 36, 0.5));
}

.super-admin-grant-header strong {
    display: block;
    color: #c4b5fd;
    font-size: 1rem;
}

.super-admin-grant-header span {
    display: block;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
}

.super-admin-checkbox {
    background: rgba(0, 0, 0, 0.2);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.super-admin-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #a855f7;
    cursor: pointer;
}

.super-admin-warning {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #fbbf24;
    margin: 0;
    padding: 0.75rem;
    background: rgba(251, 191, 36, 0.1);
    border-radius: 8px;
    border: 1px solid rgba(251, 191, 36, 0.2);
}

.super-admin-warning i {
    flex-shrink: 0;
    margin-top: 2px;
}
</style>

<script>
// Show/hide super admin grant based on role selection
(function() {
    const roleSelect = document.getElementById('role-select');
    const superAdminGrant = document.getElementById('super-admin-grant');

    if (roleSelect && superAdminGrant) {
        roleSelect.addEventListener('change', function() {
            // Show checkbox only for regular admin/tenant_admin role, not for super_admin option
            if (this.value === 'admin' || this.value === 'tenant_admin') {
                superAdminGrant.style.display = 'block';
            } else {
                superAdminGrant.style.display = 'none';
                // Uncheck super admin if role is not admin
                const checkbox = superAdminGrant.querySelector('input[name="is_super_admin"]');
                if (checkbox) checkbox.checked = false;
            }
        });
    }
})();
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
