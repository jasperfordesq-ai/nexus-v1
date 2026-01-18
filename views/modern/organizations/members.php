<?php
// Phoenix View: Organization Members (Glassmorphism)
// Path: views/modern/organizations/members.php

$hTitle = $org['name'] . ' - Members';
$hSubtitle = 'Organization Membership';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Organization';
$hideHero = true;

// Set variables for the shared utility bar
$activeTab = 'members';
$isMember = $isMember ?? true;
$isOwner = $isOwner ?? false;
$role = $role ?? 'member';

// Get pending count for requests badge
$pendingCount = 0;
if ($isAdmin && class_exists('\Nexus\Models\OrgTransferRequest')) {
    try {
        $pendingCount = \Nexus\Models\OrgTransferRequest::countPending($org['id']);
    } catch (\Exception $e) {
        $pendingCount = 0;
    }
}

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<!-- ORG MEMBERS GLASSMORPHISM -->
<style>
/* Reuse org wallet background */
.org-members-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 25%, #dbeafe 50%, #eff6ff 75%, #f8fafc 100%);
}

.org-members-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 20% 30%, rgba(59, 130, 246, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(99, 102, 241, 0.1) 0%, transparent 45%);
    animation: membersFloat 20s ease-in-out infinite;
}

[data-theme="dark"] .org-members-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
}

[data-theme="dark"] .org-members-bg::before {
    background:
        radial-gradient(ellipse at 20% 30%, rgba(59, 130, 246, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 45%);
}

@keyframes membersFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-1%, 1%) scale(1.02); }
}

.org-members-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 120px 24px 40px 24px;
    position: relative;
    z-index: 10;
}

/* Header */
.org-members-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.org-members-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.org-members-title h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 800;
    color: #1f2937;
}

[data-theme="dark"] .org-members-title h1 {
    color: #f1f5f9;
}

.org-members-nav {
    display: flex;
    gap: 8px;
}

.org-members-nav-link {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    color: #374151;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.org-members-nav-link:hover {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.4);
}

.org-members-nav-link.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border-color: transparent;
}

[data-theme="dark"] .org-members-nav-link {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(59, 130, 246, 0.3);
    color: #e2e8f0;
}

/* Glass Card */
.members-glass-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.9) 0%,
        rgba(255, 255, 255, 0.75) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    overflow: hidden;
    margin-bottom: 24px;
}

[data-theme="dark"] .members-glass-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.9) 0%,
        rgba(30, 41, 59, 0.75) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.members-section-title {
    margin: 0;
    padding: 24px 24px 0 24px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .members-section-title {
    color: #f1f5f9;
}

/* Members Grid */
.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    padding: 24px;
}

/* Member Card */
.member-card {
    display: flex;
    align-items: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 16px;
    gap: 14px;
    transition: all 0.2s;
}

.member-card:hover {
    background: rgba(255, 255, 255, 0.8);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .member-card {
    background: rgba(30, 41, 59, 0.5);
}

[data-theme="dark"] .member-card:hover {
    background: rgba(30, 41, 59, 0.8);
}

.member-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
    overflow: hidden;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.member-avatar.owner {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.member-avatar.admin {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.member-info {
    flex: 1;
    min-width: 0;
}

.member-name {
    font-weight: 700;
    color: #1f2937;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

[data-theme="dark"] .member-name {
    color: #f1f5f9;
}

.member-email {
    font-size: 0.8rem;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .member-email {
    color: #94a3b8;
}

.member-role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 4px;
}

.member-role-badge.owner {
    background: rgba(251, 191, 36, 0.2);
    color: #b45309;
}

.member-role-badge.admin {
    background: rgba(139, 92, 246, 0.2);
    color: #7c3aed;
}

.member-role-badge.member {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
}

[data-theme="dark"] .member-role-badge.owner {
    background: rgba(251, 191, 36, 0.3);
    color: #fbbf24;
}

[data-theme="dark"] .member-role-badge.admin {
    background: rgba(139, 92, 246, 0.3);
    color: #a78bfa;
}

[data-theme="dark"] .member-role-badge.member {
    background: rgba(107, 114, 128, 0.3);
    color: #9ca3af;
}

.member-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.member-action-btn {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
}

.member-action-btn.promote {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
}

.member-action-btn.promote:hover {
    background: rgba(139, 92, 246, 0.2);
}

.member-action-btn.demote {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.member-action-btn.demote:hover {
    background: rgba(107, 114, 128, 0.2);
}

.member-action-btn.remove {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.member-action-btn.remove:hover {
    background: rgba(239, 68, 68, 0.2);
}

.member-action-btn.pay {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.member-action-btn.pay:hover {
    background: rgba(16, 185, 129, 0.2);
}

/* Pay Modal */
.pay-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.pay-modal-overlay.active {
    display: flex;
}

.pay-modal {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: payModalIn 0.2s ease-out;
}

@keyframes payModalIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

[data-theme="dark"] .pay-modal {
    background: #1e293b;
}

.pay-modal-header {
    padding: 20px 24px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pay-modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pay-modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.2s;
}

.pay-modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.pay-modal-body {
    padding: 24px;
}

.pay-recipient-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: rgba(16, 185, 129, 0.08);
    border-radius: 12px;
    margin-bottom: 20px;
}

.pay-recipient-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.pay-recipient-name {
    font-weight: 700;
    color: #1f2937;
    font-size: 1rem;
}

[data-theme="dark"] .pay-recipient-name {
    color: #f1f5f9;
}

.pay-recipient-email {
    font-size: 0.85rem;
    color: #6b7280;
}

[data-theme="dark"] .pay-recipient-email {
    color: #94a3b8;
}

.pay-form-group {
    margin-bottom: 16px;
}

.pay-form-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

[data-theme="dark"] .pay-form-group label {
    color: #e2e8f0;
}

.pay-form-group input,
.pay-form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.2s;
    background: white;
    color: #1f2937;
}

[data-theme="dark"] .pay-form-group input,
[data-theme="dark"] .pay-form-group textarea {
    background: #0f172a;
    border-color: #334155;
    color: #f1f5f9;
}

.pay-form-group input:focus,
.pay-form-group textarea:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.pay-form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.pay-balance-info {
    padding: 12px 16px;
    background: rgba(59, 130, 246, 0.08);
    border-radius: 10px;
    font-size: 0.85rem;
    color: #3b82f6;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pay-modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.pay-btn-cancel {
    flex: 1;
    padding: 14px;
    border: 2px solid #e5e7eb;
    background: transparent;
    color: #6b7280;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.pay-btn-cancel:hover {
    background: rgba(107, 114, 128, 0.1);
}

[data-theme="dark"] .pay-btn-cancel {
    border-color: #334155;
    color: #94a3b8;
}

.pay-btn-submit {
    flex: 2;
    padding: 14px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.pay-btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
}

.pay-btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Pending Members */
.pending-member-card {
    display: flex;
    align-items: center;
    padding: 16px;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 16px;
    gap: 14px;
}

[data-theme="dark"] .pending-member-card {
    background: rgba(251, 191, 36, 0.15);
    border-color: rgba(251, 191, 36, 0.4);
}

.pending-actions {
    display: flex;
    gap: 8px;
}

.pending-btn {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.pending-btn.approve {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.pending-btn.approve:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.pending-btn.reject {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.pending-btn.reject:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* Stats Bar */
.members-stats {
    display: flex;
    gap: 24px;
    padding: 16px 24px;
    background: rgba(59, 130, 246, 0.05);
    border-bottom: 1px solid rgba(59, 130, 246, 0.1);
}

.member-stat {
    display: flex;
    align-items: center;
    gap: 8px;
}

.member-stat-value {
    font-weight: 700;
    font-size: 1.25rem;
    color: #3b82f6;
}

.member-stat-label {
    font-size: 0.85rem;
    color: #6b7280;
}

[data-theme="dark"] .members-stats {
    background: rgba(59, 130, 246, 0.1);
    border-bottom-color: rgba(59, 130, 246, 0.2);
}

[data-theme="dark"] .member-stat-label {
    color: #94a3b8;
}

/* Empty State */
.members-empty {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.members-empty-icon {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 16px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .org-members-container {
        padding: 100px 16px 100px 16px;
    }

    .org-members-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .org-members-nav {
        width: 100%;
        overflow-x: auto;
    }

    .members-grid {
        grid-template-columns: 1fr;
    }

    .member-card {
        flex-wrap: wrap;
    }

    .member-actions {
        width: 100%;
        flex-direction: row;
        margin-top: 8px;
        padding-top: 12px;
        border-top: 1px solid rgba(229, 231, 235, 0.3);
    }

    .member-action-btn {
        flex: 1;
        justify-content: center;
    }

    .members-stats {
        flex-wrap: wrap;
        gap: 16px;
    }
}
</style>

<div class="org-members-bg"></div>

<div class="org-members-container">
    <!-- Shared Organization Utility Bar -->
    <?php include __DIR__ . '/_org-utility-bar.php'; ?>

    <?php if ($isAdmin): ?>
    <!-- Add Member Form -->
    <div class="members-glass-card" style="margin-bottom: 24px;">
        <div style="padding: 24px;">
            <h3 class="members-section-title" style="padding: 0; margin-bottom: 16px;">
                <i class="fa-solid fa-user-plus" style="color: #10b981;"></i>
                Add Member
            </h3>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/invite" method="POST" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="email" name="email" placeholder="Enter member's email address" required
                    style="flex: 1; min-width: 250px; padding: 12px 16px; border: 2px solid rgba(59, 130, 246, 0.2); border-radius: 12px; font-size: 1rem; background: rgba(255,255,255,0.8);">
                <button type="submit" style="padding: 12px 24px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-plus"></i> Add Member
                </button>
            </form>
            <p style="margin: 12px 0 0; font-size: 0.85rem; color: #6b7280;">
                Enter the email of an existing platform member to add them to your organization.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isOwner ?? false): ?>
    <!-- Transfer Ownership Section (Owner Only) -->
    <div class="members-glass-card" style="margin-bottom: 24px; border: 2px solid rgba(251, 191, 36, 0.3);">
        <div style="padding: 24px;">
            <h3 class="members-section-title" style="padding: 0; margin-bottom: 16px;">
                <i class="fa-solid fa-crown" style="color: #f59e0b;"></i>
                Transfer Ownership
            </h3>
            <p style="margin: 0 0 16px; font-size: 0.9rem; color: #6b7280;">
                Transfer organization ownership to another member. You will become an admin after the transfer.
            </p>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/transfer-ownership" method="POST"
                  onsubmit="return confirm('Are you sure you want to transfer ownership? This action cannot be undone. You will become an admin of this organization.');"
                  style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <?= \Nexus\Core\Csrf::input() ?>
                <div style="flex: 1; min-width: 250px;">
                    <label for="new_owner_id" style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 0.9rem;">
                        New Owner
                    </label>
                    <select name="new_owner_id" id="new_owner_id" required
                            style="width: 100%; padding: 12px 16px; border: 2px solid rgba(251, 191, 36, 0.3); border-radius: 12px; font-size: 1rem; background: rgba(255,255,255,0.8); cursor: pointer;">
                        <option value="">Select a member...</option>
                        <?php foreach ($members as $m): ?>
                            <?php if ($m['role'] !== 'owner'): ?>
                                <option value="<?= $m['user_id'] ?>">
                                    <?= htmlspecialchars($m['display_name']) ?>
                                    (<?= ucfirst($m['role']) ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" style="padding: 12px 24px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                    <i class="fa-solid fa-crown"></i> Transfer Ownership
                </button>
            </form>
            <p style="margin: 12px 0 0; font-size: 0.8rem; color: #ef4444; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Warning: This action is irreversible. Make sure you trust the new owner.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && !empty($pendingMembers)): ?>
    <!-- Pending Membership Requests -->
    <div class="members-glass-card">
        <h3 class="members-section-title">
            <i class="fa-solid fa-user-clock" style="color: #f59e0b;"></i>
            Pending Requests
            <span style="background: #f59e0b; color: white; padding: 2px 10px; border-radius: 10px; font-size: 0.75rem; margin-left: auto;">
                <?= count($pendingMembers) ?>
            </span>
        </h3>
        <div class="members-grid">
            <?php foreach ($pendingMembers as $pending): ?>
            <div class="pending-member-card">
                <div class="member-avatar">
                    <?php if (!empty($pending['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($pending['avatar_url']) ?>" loading="lazy" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($pending['display_name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="member-info">
                    <div class="member-name"><?= htmlspecialchars($pending['display_name']) ?></div>
                    <div class="member-email"><?= htmlspecialchars($pending['email']) ?></div>
                    <div style="font-size: 0.75rem; color: #f59e0b; margin-top: 4px;">
                        <i class="fa-solid fa-clock"></i> Requested <?= date('M d', strtotime($pending['created_at'])) ?>
                    </div>
                </div>
                <div class="pending-actions">
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/approve" method="POST" style="display: inline;">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="member_id" value="<?= $pending['user_id'] ?>">
                        <button type="submit" class="pending-btn approve">
                            <i class="fa-solid fa-check"></i> Approve
                        </button>
                    </form>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/reject" method="POST" style="display: inline;">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="member_id" value="<?= $pending['user_id'] ?>">
                        <button type="submit" class="pending-btn reject">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Members List -->
    <div class="members-glass-card">
        <h3 class="members-section-title">
            <i class="fa-solid fa-user-group" style="color: #3b82f6;"></i>
            Active Members
        </h3>

        <!-- Stats Bar -->
        <div class="members-stats">
            <?php
            $ownerCount = count(array_filter($members, fn($m) => $m['role'] === 'owner'));
            $adminCount = count(array_filter($members, fn($m) => $m['role'] === 'admin'));
            $memberCount = count(array_filter($members, fn($m) => $m['role'] === 'member'));
            ?>
            <div class="member-stat">
                <span class="member-stat-value"><?= count($members) ?></span>
                <span class="member-stat-label">Total</span>
            </div>
            <div class="member-stat">
                <span class="member-stat-value" style="color: #f59e0b;"><?= $ownerCount ?></span>
                <span class="member-stat-label">Owner</span>
            </div>
            <div class="member-stat">
                <span class="member-stat-value" style="color: #8b5cf6;"><?= $adminCount ?></span>
                <span class="member-stat-label">Admins</span>
            </div>
            <div class="member-stat">
                <span class="member-stat-value" style="color: #6b7280;"><?= $memberCount ?></span>
                <span class="member-stat-label">Members</span>
            </div>
        </div>

        <?php if (empty($members)): ?>
        <div class="members-empty">
            <div class="members-empty-icon">
                <i class="fa-solid fa-users-slash"></i>
            </div>
            <p>No members yet.</p>
        </div>
        <?php else: ?>
        <div class="members-grid">
            <?php foreach ($members as $member): ?>
            <div class="member-card">
                <div class="member-avatar <?= $member['role'] ?>">
                    <?php if (!empty($member['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($member['avatar_url']) ?>" loading="lazy" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($member['display_name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="member-info">
                    <div class="member-name"><?= htmlspecialchars($member['display_name']) ?></div>
                    <div class="member-email"><?= htmlspecialchars($member['email']) ?></div>
                    <span class="member-role-badge <?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                </div>

                <?php if ($isAdmin): ?>
                <div class="member-actions">
                    <!-- Pay Member Button -->
                    <button type="button" class="member-action-btn pay" title="Pay Member"
                            onclick="openPayModal(<?= $member['user_id'] ?>, '<?= htmlspecialchars(addslashes($member['display_name'])) ?>', '<?= htmlspecialchars(addslashes($member['email'])) ?>', '<?= strtoupper(substr($member['display_name'] ?? 'U', 0, 1)) ?>')">
                        <i class="fa-solid fa-coins"></i> Pay
                    </button>

                    <?php if ($member['role'] !== 'owner'): ?>
                    <?php if ($member['role'] === 'member'): ?>
                    <!-- Promote to Admin -->
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/role" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="member_id" value="<?= $member['user_id'] ?>">
                        <input type="hidden" name="role" value="admin">
                        <button type="submit" class="member-action-btn promote" title="Promote to Admin">
                            <i class="fa-solid fa-arrow-up"></i> Admin
                        </button>
                    </form>
                    <?php elseif ($member['role'] === 'admin'): ?>
                    <!-- Demote to Member -->
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/role" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="member_id" value="<?= $member['user_id'] ?>">
                        <input type="hidden" name="role" value="member">
                        <button type="submit" class="member-action-btn demote" title="Demote to Member">
                            <i class="fa-solid fa-arrow-down"></i> Member
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Remove -->
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/remove" method="POST"
                          onsubmit="return confirm('Remove this member from the organization?');">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="member_id" value="<?= $member['user_id'] ?>">
                        <button type="submit" class="member-action-btn remove" title="Remove Member">
                            <i class="fa-solid fa-user-minus"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Pay Member Modal -->
<div id="payMemberModal" class="pay-modal-overlay" onclick="if(event.target === this) closePayModal();">
    <div class="pay-modal">
        <div class="pay-modal-header">
            <h3><i class="fa-solid fa-coins"></i> Pay Member</h3>
            <button type="button" class="pay-modal-close" onclick="closePayModal();">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="pay-modal-body">
            <form id="payMemberForm" action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/direct-transfer" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="recipient_id" id="payRecipientId" value="">

                <!-- Recipient Info -->
                <div class="pay-recipient-info">
                    <div class="pay-recipient-avatar" id="payRecipientAvatar">U</div>
                    <div>
                        <div class="pay-recipient-name" id="payRecipientName">-</div>
                        <div class="pay-recipient-email" id="payRecipientEmail">-</div>
                    </div>
                </div>

                <!-- Wallet Balance -->
                <div class="pay-balance-info">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Organization Balance: <strong><?= number_format($walletBalance ?? 0, 2) ?></strong> credits</span>
                </div>

                <!-- Amount -->
                <div class="pay-form-group">
                    <label for="payAmount">Amount (Credits) *</label>
                    <input type="number" name="amount" id="payAmount" min="0.25" max="<?= $walletBalance ?? 0 ?>" step="0.25" required placeholder="Enter amount">
                </div>

                <!-- Description -->
                <div class="pay-form-group">
                    <label for="payDescription">Description (Optional)</label>
                    <textarea name="description" id="payDescription" placeholder="e.g., Payment for volunteer work"></textarea>
                </div>

                <!-- Actions -->
                <div class="pay-modal-actions">
                    <button type="button" class="pay-btn-cancel" onclick="closePayModal();">Cancel</button>
                    <button type="submit" class="pay-btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Send Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPayModal(userId, name, email, initial) {
    document.getElementById('payRecipientId').value = userId;
    document.getElementById('payRecipientName').textContent = name;
    document.getElementById('payRecipientEmail').textContent = email;
    document.getElementById('payRecipientAvatar').textContent = initial;
    document.getElementById('payAmount').value = '';
    document.getElementById('payDescription').value = '';
    document.getElementById('payMemberModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    // Focus on amount field
    setTimeout(() => document.getElementById('payAmount').focus(), 100);
}

function closePayModal() {
    document.getElementById('payMemberModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('payMemberModal').classList.contains('active')) {
        closePayModal();
    }
});

// Form validation
document.getElementById('payMemberForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.getElementById('payAmount').value);
    const maxBalance = <?= $walletBalance ?? 0 ?>;

    if (isNaN(amount) || amount <= 0) {
        e.preventDefault();
        alert('Please enter a valid amount');
        return;
    }

    if (amount > maxBalance) {
        e.preventDefault();
        alert('Amount exceeds organization wallet balance (' + maxBalance.toFixed(2) + ' credits)');
        return;
    }
});
</script>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
