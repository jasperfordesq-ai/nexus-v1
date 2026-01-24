<?php
/**
 * Organization Members - GOV.UK Design System
 * WCAG 2.1 AA Compliant
 */

$pageTitle = $org['name'] . ' - Members';
\Nexus\Core\SEO::setTitle($org['name'] . ' - Members');
\Nexus\Core\SEO::setDescription('View and manage members of ' . $org['name']);

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
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">
        <!-- Shared Organization Utility Bar -->
        <?php include __DIR__ . '/_org-utility-bar.php'; ?>

        <?php if ($isAdmin): ?>
            <!-- Add Member Form -->
            <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #00703c;">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-3">
                    <i class="fa-solid fa-user-plus govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
                    Add Member
                </h3>
                <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/invite" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-two-thirds">
                            <div class="govuk-form-group govuk-!-margin-bottom-0">
                                <input type="email" name="email" required
                                       placeholder="Enter member's email address"
                                       class="govuk-input">
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-third">
                            <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" style="background: #00703c;">
                                <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                Add Member
                            </button>
                        </div>
                    </div>
                    <p class="govuk-hint govuk-!-margin-top-2 govuk-!-margin-bottom-0">
                        Enter the email of an existing platform member to add them to your organization.
                    </p>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($isOwner ?? false): ?>
            <!-- Transfer Ownership Section (Owner Only) -->
            <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #f47738;">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-3">
                    <i class="fa-solid fa-crown govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
                    Transfer Ownership
                </h3>
                <p class="govuk-hint govuk-!-margin-bottom-3">
                    Transfer organization ownership to another member. You will become an admin after the transfer.
                </p>
                <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/transfer-ownership" method="POST"
                      onsubmit="return confirm('Are you sure you want to transfer ownership? This action cannot be undone. You will become an admin of this organization.');">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-two-thirds">
                            <div class="govuk-form-group govuk-!-margin-bottom-0">
                                <label class="govuk-label govuk-visually-hidden" for="new_owner_id">New Owner</label>
                                <select name="new_owner_id" id="new_owner_id" required class="govuk-select">
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
                        </div>
                        <div class="govuk-grid-column-one-third">
                            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                <i class="fa-solid fa-crown govuk-!-margin-right-2" aria-hidden="true"></i>
                                Transfer Ownership
                            </button>
                        </div>
                    </div>
                </form>
                <div class="govuk-warning-text govuk-!-margin-top-3 govuk-!-margin-bottom-0">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-warning-text__assistive">Warning</span>
                        This action is irreversible. Make sure you trust the new owner.
                    </strong>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin && !empty($pendingMembers)): ?>
            <!-- Pending Membership Requests -->
            <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #f47738;">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-3">
                    <i class="fa-solid fa-user-clock govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
                    Pending Requests
                    <strong class="govuk-tag govuk-!-margin-left-2" style="background: #f47738;"><?= count($pendingMembers) ?></strong>
                </h3>
                <div class="govuk-grid-row">
                    <?php foreach ($pendingMembers as $pending): ?>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="govuk-!-padding-3" style="background: white; border: 1px solid #b1b4b6;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: #f47738; color: white; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                                        <?php if (!empty($pending['avatar_url'])): ?>
                                            <img src="<?= htmlspecialchars($pending['avatar_url']) ?>" loading="lazy" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($pending['display_name'] ?? 'U', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= htmlspecialchars($pending['display_name']) ?></p>
                                        <p class="govuk-hint govuk-!-margin-bottom-0"><?= htmlspecialchars($pending['email']) ?></p>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                                            <i class="fa-solid fa-clock govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                                            Requested <?= date('M d', strtotime($pending['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="govuk-button-group" style="flex-shrink: 0;">
                                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/approve" method="POST" style="display: inline;">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="member_id" value="<?= $pending['user_id'] ?>">
                                            <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" style="background: #00703c;">
                                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/reject" method="POST" style="display: inline;">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="member_id" value="<?= $pending['user_id'] ?>">
                                            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                                <i class="fa-solid fa-times" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Members List -->
        <div class="govuk-!-margin-bottom-6">
            <h3 class="govuk-heading-m">
                <i class="fa-solid fa-user-group govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                Active Members
            </h3>

            <!-- Stats Bar -->
            <?php
            $ownerCount = count(array_filter($members, fn($m) => $m['role'] === 'owner'));
            $adminCount = count(array_filter($members, fn($m) => $m['role'] === 'admin'));
            $memberCount = count(array_filter($members, fn($m) => $m['role'] === 'member'));
            ?>
            <div class="govuk-grid-row govuk-!-margin-bottom-4">
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg" style="border-left: 3px solid #1d70b8;">
                        <p class="govuk-heading-m govuk-!-margin-bottom-0" style="color: #1d70b8;"><?= count($members) ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Total</p>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg" style="border-left: 3px solid #f47738;">
                        <p class="govuk-heading-m govuk-!-margin-bottom-0" style="color: #f47738;"><?= $ownerCount ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Owner</p>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg" style="border-left: 3px solid #912b88;">
                        <p class="govuk-heading-m govuk-!-margin-bottom-0" style="color: #912b88;"><?= $adminCount ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Admins</p>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg" style="border-left: 3px solid #505a5f;">
                        <p class="govuk-heading-m govuk-!-margin-bottom-0" style="color: #505a5f;"><?= $memberCount ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Members</p>
                    </div>
                </div>
            </div>

            <?php if (empty($members)): ?>
                <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                    <p class="govuk-body govuk-!-margin-bottom-4">
                        <i class="fa-solid fa-users-slash fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
                    </p>
                    <h3 class="govuk-heading-m">No members yet</h3>
                </div>
            <?php else: ?>
                <div class="govuk-grid-row">
                    <?php foreach ($members as $member): ?>
                        <?php
                        $roleColors = [
                            'owner' => '#f47738',
                            'admin' => '#912b88',
                            'member' => '#1d70b8'
                        ];
                        $roleColor = $roleColors[$member['role']] ?? '#1d70b8';
                        ?>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="govuk-!-padding-4" style="background: white; border: 1px solid #b1b4b6; border-left: 5px solid <?= $roleColor ?>;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <!-- Avatar -->
                                    <div style="width: 56px; height: 56px; border-radius: 50%; background: <?= $roleColor ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                                        <?php if (!empty($member['avatar_url'])): ?>
                                            <img src="<?= htmlspecialchars($member['avatar_url']) ?>" loading="lazy" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($member['display_name'] ?? 'U', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div style="flex: 1; min-width: 0;">
                                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1"><?= htmlspecialchars($member['display_name']) ?></p>
                                        <p class="govuk-hint govuk-!-margin-bottom-2"><?= htmlspecialchars($member['email']) ?></p>
                                        <strong class="govuk-tag" style="background: <?= $roleColor ?>;">
                                            <?= ucfirst($member['role']) ?>
                                        </strong>
                                    </div>

                                    <?php if ($isAdmin): ?>
                                        <!-- Actions -->
                                        <div style="flex-shrink: 0;">
                                            <!-- Pay Member Button -->
                                            <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-2"
                                                    data-module="govuk-button"
                                                    onclick="openPayModal(<?= $member['user_id'] ?>, '<?= htmlspecialchars(addslashes($member['display_name'])) ?>', '<?= htmlspecialchars(addslashes($member['email'])) ?>', '<?= strtoupper(substr($member['display_name'] ?? 'U', 0, 1)) ?>')"
                                                    title="Pay Member">
                                                <i class="fa-solid fa-coins" aria-hidden="true"></i>
                                            </button>

                                            <?php if ($member['role'] !== 'owner'): ?>
                                                <?php if ($member['role'] === 'member'): ?>
                                                    <!-- Promote to Admin -->
                                                    <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/role" method="POST" style="display: inline;">
                                                        <?= \Nexus\Core\Csrf::input() ?>
                                                        <input type="hidden" name="member_id" value="<?= $member['user_id'] ?>">
                                                        <input type="hidden" name="role" value="admin">
                                                        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-2" data-module="govuk-button" title="Promote to Admin">
                                                            <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($member['role'] === 'admin'): ?>
                                                    <!-- Demote to Member -->
                                                    <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/role" method="POST" style="display: inline;">
                                                        <?= \Nexus\Core\Csrf::input() ?>
                                                        <input type="hidden" name="member_id" value="<?= $member['user_id'] ?>">
                                                        <input type="hidden" name="role" value="member">
                                                        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-2" data-module="govuk-button" title="Demote to Member">
                                                            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Remove -->
                                                <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members/remove" method="POST" style="display: inline;"
                                                      onsubmit="return confirm('Remove this member from the organization?');">
                                                    <?= \Nexus\Core\Csrf::input() ?>
                                                    <input type="hidden" name="member_id" value="<?= $member['user_id'] ?>">
                                                    <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-2" data-module="govuk-button" title="Remove Member">
                                                        <i class="fa-solid fa-user-minus" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($isAdmin): ?>
<!-- Pay Member Modal -->
<div id="payMemberModal" class="govuk-!-display-none" role="dialog" aria-labelledby="pay-modal-title" aria-modal="true" style="position: fixed; inset: 0; z-index: 1000;">
    <div onclick="closePayModal()" style="position: absolute; inset: 0; background: rgba(0,0,0,0.5);"></div>
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div class="govuk-!-padding-4" style="background: #1d70b8; color: white;">
            <h3 id="pay-modal-title" class="govuk-heading-m" style="color: white; margin-bottom: 0;">
                <i class="fa-solid fa-coins govuk-!-margin-right-2" aria-hidden="true"></i>
                Pay Member
            </h3>
        </div>
        <form id="payMemberForm" action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/direct-transfer" method="POST" class="govuk-!-padding-4">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="recipient_id" id="payRecipientId" value="">

            <!-- Recipient Info -->
            <div class="govuk-inset-text govuk-!-margin-top-0">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div id="payRecipientAvatar" style="width: 48px; height: 48px; border-radius: 50%; background: #1d70b8; color: white; display: flex; align-items: center; justify-content: center; font-size: 18px;">U</div>
                    <div>
                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-0" id="payRecipientName">-</p>
                        <p class="govuk-hint govuk-!-margin-bottom-0" id="payRecipientEmail">-</p>
                    </div>
                </div>
            </div>

            <!-- Wallet Balance -->
            <div class="govuk-!-margin-bottom-4 govuk-!-padding-3 civicone-panel-bg" style="border-left: 3px solid #1d70b8;">
                <p class="govuk-body-s govuk-!-margin-bottom-0">
                    <i class="fa-solid fa-wallet govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Organization Balance: <strong><?= number_format($walletBalance ?? 0, 2) ?></strong> credits
                </p>
            </div>

            <!-- Amount -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="payAmount">Amount (Credits) *</label>
                <input type="number" name="amount" id="payAmount" min="0.25" max="<?= $walletBalance ?? 0 ?>" step="0.25" required placeholder="Enter amount" class="govuk-input govuk-input--width-10">
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="payDescription">
                    Description
                    <span class="govuk-hint">Optional</span>
                </label>
                <textarea name="description" id="payDescription" class="govuk-textarea" rows="3" placeholder="e.g., Payment for volunteer work"></textarea>
            </div>

            <!-- Actions -->
            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                    Send Payment
                </button>
                <button type="button" onclick="closePayModal()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayModal(userId, name, email, initial) {
    document.getElementById('payRecipientId').value = userId;
    document.getElementById('payRecipientName').textContent = name;
    document.getElementById('payRecipientEmail').textContent = email;
    document.getElementById('payRecipientAvatar').textContent = initial;
    document.getElementById('payMemberModal').classList.remove('govuk-!-display-none');
    document.body.style.overflow = 'hidden';
}

function closePayModal() {
    document.getElementById('payMemberModal').classList.add('govuk-!-display-none');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePayModal();
});
</script>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
