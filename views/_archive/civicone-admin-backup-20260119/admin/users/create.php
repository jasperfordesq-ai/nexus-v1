<?php
/**
 * Admin Create User - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Check if current user is super admin (can grant super admin privileges)
$currentUserIsSuperAdmin = !empty($_SESSION['is_super_admin']);

// Admin header configuration
$adminPageTitle = 'Create User';
$adminPageSubtitle = 'User Management';
$adminPageIcon = 'fa-user-plus';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Get old form values for repopulation on error
$old = $old ?? [];
$errors = $errors ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-plus"></i>
            Create New User
        </h1>
        <p class="admin-page-subtitle">Add a new member to the community</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/users" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <div>
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 1rem; padding: 0;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Create User Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">New User Details</h3>
            <p class="admin-card-subtitle">Enter the account information for the new user</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/users/store" method="POST">
            <?= Csrf::input() ?>

            <!-- Personal Details Section -->
            <div class="admin-form-section">
                <h4 class="admin-form-section-title">
                    <i class="fa-solid fa-id-card"></i> Personal Details
                </h4>
                <div class="admin-form-grid">
                    <div class="admin-form-group">
                        <label class="admin-label">First Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($old['first_name'] ?? '') ?>" required class="admin-input" placeholder="John">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Last Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($old['last_name'] ?? '') ?>" required class="admin-input" placeholder="Doe">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Location / Area</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($old['location'] ?? '') ?>" placeholder="Start typing your town or city..." class="admin-input mapbox-location-input-v2">
                    <input type="hidden" name="latitude" value="">
                    <input type="hidden" name="longitude" value="">
                </div>
            </div>

            <!-- Contact & Access Section -->
            <div class="admin-form-section">
                <h4 class="admin-form-section-title">
                    <i class="fa-solid fa-envelope"></i> Contact & Access
                </h4>
                <div class="admin-form-grid">
                    <div class="admin-form-group">
                        <label class="admin-label">Email Address <span style="color: #ef4444;">*</span></label>
                        <input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required class="admin-input" placeholder="user@example.com">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($old['phone'] ?? '') ?>" class="admin-input" placeholder="+1 234 567 8900">
                    </div>
                </div>
            </div>

            <!-- Password Section -->
            <div class="admin-form-section">
                <h4 class="admin-form-section-title">
                    <i class="fa-solid fa-lock"></i> Password
                </h4>
                <div class="admin-form-grid">
                    <div class="admin-form-group">
                        <label class="admin-label">Password <span style="color: #ef4444;">*</span></label>
                        <div style="position: relative;">
                            <input type="password" name="password" id="password-input" required class="admin-input" placeholder="Minimum 8 characters" minlength="8" style="padding-right: 80px;">
                            <button type="button" onclick="togglePassword()" class="password-toggle-btn" title="Show/Hide Password">
                                <i class="fa-solid fa-eye" id="password-toggle-icon"></i>
                            </button>
                            <button type="button" onclick="generatePassword()" class="password-generate-btn" title="Generate Password">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </button>
                        </div>
                        <p class="admin-form-hint">Minimum 8 characters. Click the wand icon to generate a secure password.</p>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">&nbsp;</label>
                        <div class="checkbox-wrapper" style="margin-top: 8px;">
                            <label class="admin-checkbox-label">
                                <input type="checkbox" name="send_welcome_email" value="1" checked>
                                <span class="admin-checkbox-text">
                                    <i class="fa-solid fa-envelope"></i>
                                    Send welcome email with login credentials
                                </span>
                            </label>
                        </div>
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
                            <option value="member" <?= ($old['role'] ?? 'member') === 'member' ? 'selected' : '' ?>>Member (Standard)</option>
                            <option value="newsletter_admin" <?= ($old['role'] ?? '') === 'newsletter_admin' ? 'selected' : '' ?>>Newsletter Admin</option>
                            <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                        <p class="admin-form-hint">Newsletter Admins can only access the newsletter module. Full Admins have complete access.</p>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Account Status</label>
                        <select name="status" class="admin-select">
                            <option value="1" <?= ($old['status'] ?? '1') == '1' ? 'selected' : '' ?>>Active / Approved</option>
                            <option value="0" <?= ($old['status'] ?? '1') == '0' ? 'selected' : '' ?>>Pending Approval</option>
                        </select>
                        <p class="admin-form-hint">Pending users cannot log in until approved.</p>
                    </div>
                </div>
                <?php if (!empty($_SESSION['is_god'])): ?>
                <!-- Super Admin Grant (only visible to god users) -->
                <div class="admin-super-admin-grant" id="super-admin-grant" style="display: <?= ($old['role'] ?? '') === 'admin' ? 'block' : 'none' ?>;">
                    <div class="super-admin-grant-box">
                        <div class="super-admin-grant-header">
                            <i class="fa-solid fa-crown"></i>
                            <div>
                                <strong>Platform Super Admin</strong>
                                <span>Grant cross-tenant administrative access</span>
                            </div>
                        </div>
                        <label class="admin-checkbox-label super-admin-checkbox">
                            <input type="checkbox" name="is_super_admin" value="1" <?= !empty($old['is_super_admin']) ? 'checked' : '' ?>>
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
                <a href="<?= $basePath ?>/admin/users" class="admin-btn admin-btn-secondary">Cancel</a>
                <button type="submit" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-user-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Create User Page Specific Styles */

/* Alerts */
.admin-alert {
    display: flex;
    align-items: flex-start;
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
    margin-top: 2px;
}

.admin-alert-error {
    border-left: 3px solid #ef4444;
}
.admin-alert-error i { color: #ef4444; }

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

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border: 1px solid rgba(34, 197, 94, 0.5);
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
    transform: translateY(-1px);
}

/* Password Field Buttons */
.password-toggle-btn,
.password-generate-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    padding: 0.5rem;
    transition: color 0.2s;
}

.password-toggle-btn:hover,
.password-generate-btn:hover {
    color: #818cf8;
}

.password-toggle-btn {
    right: 40px;
}

.password-generate-btn {
    right: 10px;
}

/* Checkbox */
.admin-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.8);
}

.admin-checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #6366f1;
    cursor: pointer;
}

.admin-checkbox-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.admin-checkbox-text i {
    color: #818cf8;
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
}

.super-admin-checkbox input[type="checkbox"] {
    accent-color: #a855f7;
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

/* Responsive */
@media (max-width: 768px) {
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
}
</style>

<script>
function togglePassword() {
    const input = document.getElementById('password-input');
    const icon = document.getElementById('password-toggle-icon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';

    // Ensure at least one of each type
    password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
    password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)];
    password += '0123456789'[Math.floor(Math.random() * 10)];
    password += '!@#$%^&*'[Math.floor(Math.random() * 8)];

    // Fill the rest
    for (let i = 0; i < 8; i++) {
        password += chars[Math.floor(Math.random() * chars.length)];
    }

    // Shuffle the password
    password = password.split('').sort(() => Math.random() - 0.5).join('');

    const input = document.getElementById('password-input');
    input.value = password;
    input.type = 'text'; // Show the generated password

    document.getElementById('password-toggle-icon').classList.remove('fa-eye');
    document.getElementById('password-toggle-icon').classList.add('fa-eye-slash');
}

// Show/hide super admin grant based on role selection
(function() {
    const roleSelect = document.getElementById('role-select');
    const superAdminGrant = document.getElementById('super-admin-grant');

    if (roleSelect && superAdminGrant) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'admin') {
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
