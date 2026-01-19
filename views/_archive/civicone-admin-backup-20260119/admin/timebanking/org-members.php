<?php
/**
 * Admin Organization Members - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Organization Members';
$adminPageSubtitle = 'TimeBanking';
$adminPageIcon = 'fa-users-cog';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/timebanking/org-wallets" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= htmlspecialchars($org['name']) ?>
        </h1>
        <p class="admin-page-subtitle">Manage organization members</p>
    </div>
</div>

<div class="org-members-grid">
    <!-- Add Member Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Add Member</h3>
                <p class="admin-card-subtitle">Add an existing user to this organization</p>
            </div>
        </div>
        <div class="admin-card-body">
            <form action="<?= $basePath ?>/admin/timebanking/org-members/add" method="POST" class="add-member-form">
                <?= Csrf::input() ?>
                <input type="hidden" name="org_id" value="<?= $org['id'] ?>">

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label for="email">User Email</label>
                        <input type="email" id="email" name="email" placeholder="user@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="form-group form-submit">
                        <button type="submit" class="admin-btn admin-btn-success">
                            <i class="fa-solid fa-plus"></i> Add Member
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Members List -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Current Members</h3>
                <p class="admin-card-subtitle"><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <?php if (empty($members)): ?>
        <div class="members-empty">
            <div class="members-empty-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <h3>No Members Yet</h3>
            <p>Add the first member using the form above.</p>
        </div>
        <?php else: ?>
        <div class="members-list">
            <?php foreach ($members as $member): ?>
            <div class="member-row">
                <div class="member-avatar">
                    <?php if (!empty($member['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($member['avatar_url']) ?>" loading="lazy" alt="">
                    <?php else: ?>
                    <?= strtoupper(substr($member['first_name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>

                <div class="member-info">
                    <div class="member-name"><?= htmlspecialchars($member['display_name']) ?></div>
                    <div class="member-email"><?= htmlspecialchars($member['email']) ?></div>
                </div>

                <span class="member-role-badge <?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                <span class="member-status-badge <?= $member['status'] ?>"><?= ucfirst($member['status']) ?></span>

                <div class="member-actions">
                    <?php if ($member['role'] !== 'owner'): ?>
                    <!-- Change Role -->
                    <form action="<?= $basePath ?>/admin/timebanking/org-members/update-role" method="POST" class="inline-form">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $member['user_id'] ?>">
                        <select name="role" onchange="this.form.submit()" class="role-select">
                            <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                            <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="owner" <?= $member['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                        </select>
                    </form>

                    <!-- Remove -->
                    <form action="<?= $basePath ?>/admin/timebanking/org-members/remove" method="POST" class="inline-form" onsubmit="return confirm('Remove this member from the organization?');">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $member['user_id'] ?>">
                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="owner-note">Owner cannot be removed</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

.org-members-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-width: 900px;
}

/* Add Member Form */
.form-row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.form-group {
    margin-bottom: 0;
}

.form-group.flex-1 {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.form-group input,
.form-group select {
    padding: 0.65rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 0.9rem;
    transition: all 0.2s;
    width: 100%;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.form-group input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-group select option {
    background: #1e293b;
    color: #f1f5f9;
}

.form-submit {
    flex-shrink: 0;
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #34d399, #10b981);
}

/* Members List */
.members-list {
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.member-row {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    gap: 1rem;
}

.member-row:last-child {
    border-bottom: none;
}

.member-row:hover {
    background: rgba(255, 255, 255, 0.02);
}

.member-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
    overflow: hidden;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.member-info {
    flex: 1;
    min-width: 0;
}

.member-name {
    font-weight: 600;
    color: #f1f5f9;
    font-size: 0.95rem;
}

.member-email {
    font-size: 0.8rem;
    color: #64748b;
}

/* Role Badge */
.member-role-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.member-role-badge.owner {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.member-role-badge.admin {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

.member-role-badge.member {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

/* Status Badge */
.member-status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.member-status-badge.active {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.member-status-badge.pending {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.member-status-badge.removed {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

/* Member Actions */
.member-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.inline-form {
    display: inline;
}

.role-select {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid rgba(139, 92, 246, 0.3);
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
}

.role-select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.3);
}

.owner-note {
    font-size: 0.75rem;
    color: #64748b;
    padding: 6px 12px;
}

/* Empty State */
.members-empty {
    text-align: center;
    padding: 3rem 2rem;
}

.members-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(59, 130, 246, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: rgba(59, 130, 246, 0.5);
}

.members-empty h3 {
    color: #fff;
    margin: 0 0 0.5rem;
}

.members-empty p {
    color: rgba(255, 255, 255, 0.5);
    max-width: 300px;
    margin: 0 auto;
}

/* Mobile */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        align-items: stretch;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-submit .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .member-row {
        flex-wrap: wrap;
    }

    .member-actions {
        width: 100%;
        justify-content: flex-end;
        margin-top: 0.5rem;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
