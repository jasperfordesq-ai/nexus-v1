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

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Load page-specific CSS
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/purged/civicone-organizations-members.min.css">

<div class="org-members-bg" data-wallet-balance="<?= $walletBalance ?? 0 ?>"></div>

<div class="org-members-container">
    <!-- Shared Organization Utility Bar -->
    <?php include __DIR__ . '/_org-utility-bar.php'; ?>

    <?php if ($isAdmin): ?>
    <!-- Add Member Form -->
    <div class="members-glass-card">
        <div class="card-inner-padding">
            <h3 class="members-section-title section-title-inline">
                <i class="fa-solid fa-user-plus icon-emerald"></i>
                Add Member
            </h3>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/invite" method="POST" class="add-member-form">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="email" name="email" placeholder="Enter member's email address" required class="add-member-input">
                <button type="submit" class="add-member-btn">
                    <i class="fa-solid fa-plus"></i> Add Member
                </button>
            </form>
            <p class="form-help-text">
                Enter the email of an existing platform member to add them to your organization.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isOwner ?? false): ?>
    <!-- Transfer Ownership Section (Owner Only) -->
    <div class="members-glass-card transfer-ownership-card">
        <div class="card-inner-padding">
            <h3 class="members-section-title section-title-inline">
                <i class="fa-solid fa-crown icon-amber"></i>
                Transfer Ownership
            </h3>
            <p class="form-help-text">
                Transfer organization ownership to another member. You will become an admin after the transfer.
            </p>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/transfer-ownership" method="POST"
                  onsubmit="return confirm('Are you sure you want to transfer ownership? This action cannot be undone. You will become an admin of this organization.');"
                  class="transfer-ownership-form">
                <?= \Nexus\Core\Csrf::input() ?>
                <div class="transfer-select-wrapper">
                    <label for="new_owner_id" class="transfer-select-label">
                        New Owner
                    </label>
                    <select name="new_owner_id" id="new_owner_id" required class="transfer-select">
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
                <button type="submit" class="transfer-btn">
                    <i class="fa-solid fa-crown"></i> Transfer Ownership
                </button>
            </form>
            <p class="transfer-warning">
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
            <i class="fa-solid fa-user-clock icon-amber"></i>
            Pending Requests
            <span class="badge-pill-amber">
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
                    <div class="pending-request-meta">
                        <i class="fa-solid fa-clock"></i> Requested <?= date('M d', strtotime($pending['created_at'])) ?>
                    </div>
                </div>
                <div class="pending-actions">
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/approve" method="POST" class="form-inline">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="member_id" value="<?= $pending['user_id'] ?>">
                        <button type="submit" class="pending-btn approve">
                            <i class="fa-solid fa-check"></i> Approve
                        </button>
                    </form>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members/reject" method="POST" class="form-inline">
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
            <i class="fa-solid fa-user-group icon-blue"></i>
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
                <span class="member-stat-value stat-value-amber"><?= $ownerCount ?></span>
                <span class="member-stat-label">Owner</span>
            </div>
            <div class="member-stat">
                <span class="member-stat-value stat-value-purple"><?= $adminCount ?></span>
                <span class="member-stat-label">Admins</span>
            </div>
            <div class="member-stat">
                <span class="member-stat-value stat-value-gray"><?= $memberCount ?></span>
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

<script src="/assets/js/civicone-organizations-members.min.js" defer></script>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
