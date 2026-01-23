<?php
/**
 * CivicOne Groups Invite - Member Selection Page
 * Template D: Form/Flow (Section 10.6)
 * Select and invite members to join a group
 * WCAG 2.1 AA Compliant
 */
$hero_title = "Invite Members";
$hero_subtitle = "Grow your hub by inviting community members.";
$hero_gradient = 'htb-hero-gradient-hub';
$hero_type = 'Community';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/purged/civicone-groups-invite.min.css?v=<?= time() ?>">

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper">

        <!-- Offline Banner -->
        <div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
            <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
            <span>No internet connection</span>
        </div>

        <div class="htb-container-focused invite-wrapper">

            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=settings" class="back-link">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to <?= htmlspecialchars($group['name']) ?>
            </a>

            <div class="htb-header-box">
                <h1>Invite Members</h1>
                <p>Select members to invite to <strong><?= htmlspecialchars($group['name']) ?></strong></p>
            </div>

            <?php if (isset($_GET['err']) && $_GET['err'] === 'no_users'): ?>
                <div class="error-message" role="alert">Please select at least one member to invite.</div>
            <?php endif; ?>

            <div class="htb-card">
                <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/invite" method="POST" id="inviteForm" aria-label="Invite members to group">
                    <?= Nexus\Core\Csrf::input() ?>

                    <input type="text" class="invite-search" id="userSearch" placeholder="Search members by name..." aria-label="Search members">

                    <?php if (empty($availableUsers)): ?>
                        <div class="no-users">
                            <p>All community members are already in this hub!</p>
                        </div>
                    <?php else: ?>
                        <div class="user-list" id="userList" role="list">
                            <?php foreach ($availableUsers as $user): ?>
                                <label class="user-item" data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>" role="listitem">
                                    <input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>" aria-label="Select <?= htmlspecialchars($user['name']) ?>">
                                    <?php
                                        $avatarSrc = $user['avatar_url'] ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 128 128'%3E%3Ccircle cx='64' cy='64' r='64' fill='%23e2e8f0'/%3E%3Ccircle cx='64' cy='48' r='20' fill='%2394a3b8'/%3E%3Cellipse cx='64' cy='96' rx='32' ry='24' fill='%2394a3b8'/%3E%3C/svg%3E";
                                    ?>
                                    <img src="<?= htmlspecialchars($avatarSrc) ?>" loading="lazy"
                                         alt="<?= htmlspecialchars($user['name']) ?>" class="user-avatar">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <?php if (!empty($user['email'])): ?>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="selected-count" id="selectedCount" aria-live="polite">0 members selected</div>

                        <!-- Add Directly Option -->
                        <div class="add-directly-box">
                            <label>
                                <input type="checkbox" name="add_directly" value="1" id="addDirectlyCheckbox">
                                <div>
                                    <div class="add-directly-title">Add directly to hub</div>
                                    <div class="add-directly-desc">
                                        Skip the invitation step and add selected members immediately. They'll receive a notification that they've been added.
                                    </div>
                                </div>
                            </label>
                        </div>

                        <button type="submit" class="invite-submit" id="submitBtn" disabled aria-live="polite">Send Invitations</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </main>
</div><!-- /civicone-width-container -->

<script src="/assets/js/civicone-groups-invite.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
