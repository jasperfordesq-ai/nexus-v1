<?php
/**
 * Component: Profile Header - MOJ Identity Bar Pattern
 *
 * Refactored: 2026-01-20
 * Pattern: MOJ Identity Bar + GOV.UK Page Header Actions
 * WCAG 2.1 AA Compliant
 *
 * Features:
 * - MOJ Identity bar with member name + 2-4 reference facts
 * - GOV.UK Page header actions for profile buttons
 * - Keyboard accessible (Tab, Enter, Space)
 * - All friendship states (Add Friend, Request Sent, Accept Request, Already Friends)
 * - Admin impersonation button
 * - Proper focus indicators (GOV.UK yellow #ffdd00)
 *
 * Required Variables:
 * @var array $user - The user being viewed
 * @var string $displayName - User's display name
 * @var bool $profileIsOnline - Whether user is currently online
 * @var bool $profileIsRecentlyActive - Whether user was recently active
 * @var string $profileStatusText - User status text (e.g., "Active 2 hours ago")
 * @var array|null $connection - Connection status with current user
 * @var array $userOrganizations - User's organization memberships
 * @var array $headerReviewStats - Review statistics for header badge
 * @var int $headerAvgRating - Average rating
 * @var int $headerTotalReviews - Total review count
 */

// Validate required variables
$requiredVars = ['user', 'displayName', 'profileIsOnline', 'profileIsRecentlyActive', 'profileStatusText'];
foreach ($requiredVars as $var) {
    if (!isset($$var)) {
        throw new Exception("profile-header component requires \${$var} variable");
    }
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- MOJ Identity Bar -->
<aside class="civicone-identity-bar" aria-label="Profile summary">
    <div class="govuk-width-container">
        <div class="civicone-identity-bar__container">
            <!-- Avatar with Online Status -->
            <div class="civicone-identity-bar__avatar">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&background=random&color=fff&size=400') ?>"
                     alt="Profile picture of <?= htmlspecialchars($displayName) ?>"
                     class="civicone-identity-bar__avatar-img"
                     onerror="this.src='/assets/img/defaults/default_avatar.webp'">
                <?php if ($profileIsOnline): ?>
                    <span class="civicone-identity-bar__status-indicator civicone-identity-bar__status-indicator--online"
                          role="status"
                          aria-label="User is online now"></span>
                <?php elseif ($profileIsRecentlyActive): ?>
                    <span class="civicone-identity-bar__status-indicator civicone-identity-bar__status-indicator--recent"
                          role="status"
                          aria-label="User was active recently"></span>
                <?php endif; ?>
            </div>

            <!-- User Info and Reference Facts -->
            <div class="civicone-identity-bar__info">
                <h1 class="civicone-identity-bar__heading">
                    <?= htmlspecialchars($displayName) ?>
                </h1>

                <!-- Reference Facts (2-4 key facts) -->
                <ul class="civicone-identity-bar__meta" role="list">
                    <!-- Online Status Badge -->
                    <?php if ($profileIsOnline): ?>
                        <li class="civicone-identity-bar__meta-item civicone-identity-bar__badge civicone-identity-bar__badge--status-online">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i>
                            <strong>Online now</strong>
                        </li>
                    <?php elseif ($profileIsRecentlyActive): ?>
                        <li class="civicone-identity-bar__meta-item civicone-identity-bar__badge civicone-identity-bar__badge--status-recent">
                            <i class="fa-solid fa-circle" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($profileStatusText) ?></span>
                        </li>
                    <?php endif; ?>

                    <!-- Location -->
                    <?php if (!empty($user['location'])): ?>
                        <li class="civicone-identity-bar__meta-item">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($user['location']) ?></span>
                        </li>
                    <?php endif; ?>

                    <!-- Joined Date -->
                    <li class="civicone-identity-bar__meta-item">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        <span>Joined <?= date('F Y', strtotime($user['created_at'])) ?></span>
                    </li>

                    <!-- Credits -->
                    <li class="civicone-identity-bar__meta-item civicone-identity-bar__badge civicone-identity-bar__badge--credits">
                        <i class="fa-solid fa-coins" aria-hidden="true"></i>
                        <strong><data value="<?= $user['balance'] ?? 0 ?>"><?= number_format($user['balance'] ?? 0) ?> Credits</data></strong>
                    </li>

                    <!-- Organization Roles (if owner/admin) -->
                    <?php if (!empty($userOrganizations)): ?>
                        <?php foreach ($userOrganizations as $org): ?>
                            <?php if ($org['member_role'] === 'owner'): ?>
                            <li>
                                <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                                   class="civicone-identity-bar__badge civicone-identity-bar__badge--org-owner"
                                   aria-label="Organization owner: <?= htmlspecialchars($org['name']) ?>">
                                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                                    <span>Owner: <?= htmlspecialchars($org['name']) ?></span>
                                </a>
                            </li>
                            <?php elseif ($org['member_role'] === 'admin'): ?>
                            <li>
                                <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                                   class="civicone-identity-bar__badge civicone-identity-bar__badge--org-admin"
                                   aria-label="Organization admin: <?= htmlspecialchars($org['name']) ?>">
                                    <i class="fa-solid fa-shield" aria-hidden="true"></i>
                                    <span>Admin: <?= htmlspecialchars($org['name']) ?></span>
                                </a>
                            </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Rating Badge -->
                    <?php if ($headerTotalReviews > 0): ?>
                    <li>
                        <a href="#reviews-section"
                           class="civicone-identity-bar__badge civicone-identity-bar__badge--rating"
                           onclick="event.preventDefault(); document.getElementById('reviews-section')?.scrollIntoView({behavior: 'smooth'})"
                           aria-label="Average rating: <?= $headerAvgRating ?> stars from <?= $headerTotalReviews ?> reviews">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            <strong><?= $headerAvgRating ?></strong>
                            <span>(<?= $headerTotalReviews ?>)</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Admin-only Phone Badge -->
                    <?php if (!empty($user['phone']) && (($_SESSION['user_role'] ?? '') === 'admin' || !empty($_SESSION['is_super_admin']))): ?>
                        <li class="civicone-identity-bar__meta-item civicone-identity-bar__badge civicone-identity-bar__badge--admin">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <strong>Admin</strong>
                            <button type="button"
                                    class="civicone-identity-bar__reveal-phone"
                                    aria-label="Reveal phone number"
                                    data-phone="<?= htmlspecialchars($user['phone']) ?>"
                                    onclick="revealPhone(this)">
                                Show phone
                            </button>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</aside>

<!-- JavaScript for phone reveal (progressive enhancement) -->
<script>
function revealPhone(button) {
    const phone = button.getAttribute('data-phone');
    if (phone) {
        button.textContent = phone;
        button.setAttribute('aria-label', 'Phone number: ' + phone);
        button.disabled = true;
    }
}
</script>

<!-- GOV.UK Page Header Actions -->
<div class="civicone-page-header-actions">
    <div class="govuk-width-container">
        <div class="civicone-page-header-actions__container">
            <?php
            // Admin Impersonation Button
            $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
            $isSuperAdmin = !empty($_SESSION['is_super_admin']);
            $canImpersonate = ($isAdmin || $isSuperAdmin) && isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id'];

            if ($canImpersonate):
            ?>
                <form action="<?= $basePath ?>/admin/impersonate"
                      method="POST"
                      onsubmit="return confirm('You are about to login as <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>. Continue?');"
                      >
                    <?= Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <button type="submit"
                            class="civicone-page-header-actions__btn civicone-page-header-actions__btn--warning"
                            aria-label="Login as this user (admin action)">
                        <i class="fa-solid fa-user-secret" aria-hidden="true"></i>
                        <span>Login As User</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']): ?>
                <!-- Own Profile Actions -->
                <a href="<?= $basePath ?>/profile/edit"
                   class="civicone-page-header-actions__btn civicone-page-header-actions__btn--secondary"
                   aria-label="Edit your profile">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    <span>Edit Profile</span>
                </a>
                <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                <a href="<?= $basePath ?>/wallet/insights"
                   class="civicone-page-header-actions__btn civicone-page-header-actions__btn--primary"
                   aria-label="View your wallet insights">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    <span>My Insights</span>
                </a>
                <?php endif; ?>
            <?php elseif (isset($_SESSION['user_id'])): ?>
                <!-- Other User Profile Actions -->
                <?php if (!$connection): ?>
                    <!-- Not friends - show Add Friend button -->
                    <form action="<?= $basePath ?>/connections/add" method="POST" >
                        <?= Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="receiver_id" value="<?= $user['id'] ?>">
                        <button type="submit"
                                class="civicone-page-header-actions__btn civicone-page-header-actions__btn--primary"
                                aria-label="Send friend request to <?= htmlspecialchars($displayName) ?>">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span>Add Friend</span>
                        </button>
                    </form>
                <?php elseif ($connection['status'] === 'pending' && $connection['requester_id'] == $_SESSION['user_id']): ?>
                    <!-- Friend request sent -->
                    <button disabled
                            class="civicone-page-header-actions__btn"
                            aria-label="Friend request already sent">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        <span>Request Sent</span>
                    </button>
                <?php elseif ($connection['status'] === 'pending' && $connection['receiver_id'] == $_SESSION['user_id']): ?>
                    <!-- Accept friend request -->
                    <form action="<?= $basePath ?>/connections/accept" method="POST" >
                        <?= Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="connection_id" value="<?= $connection['id'] ?>">
                        <button type="submit"
                                class="civicone-page-header-actions__btn civicone-page-header-actions__btn--primary"
                                aria-label="Accept friend request from <?= htmlspecialchars($displayName) ?>">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            <span>Accept Request</span>
                        </button>
                    </form>
                <?php elseif ($connection['status'] === 'accepted'): ?>
                    <!-- Already friends -->
                    <span class="civicone-page-header-actions__btn civicone-page-header-actions__btn--primary"
                          class="civicone-page-header-actions__btn--status"
                          role="status"
                          aria-label="You are friends with <?= htmlspecialchars($displayName) ?>">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        <span>Friends</span>
                    </span>
                <?php endif; ?>

                <a href="<?= $basePath ?>/messages/<?= $user['id'] ?>"
                   class="civicone-page-header-actions__btn civicone-page-header-actions__btn--secondary"
                   aria-label="Send message to <?= htmlspecialchars($displayName) ?>">
                    <i class="fa-solid fa-message" aria-hidden="true"></i>
                    <span>Message</span>
                </a>
                <a href="<?= $basePath ?>/wallet?to=<?= $user['id'] ?>"
                   class="civicone-page-header-actions__btn civicone-page-header-actions__btn--secondary"
                   aria-label="Send credits to <?= htmlspecialchars($displayName) ?>">
                    <i class="fa-solid fa-coins" aria-hidden="true"></i>
                    <span>Send Credits</span>
                </a>
                <button type="button"
                        onclick="openReviewModal()"
                        class="civicone-page-header-actions__btn civicone-page-header-actions__btn--warning"
                        aria-label="Leave a review for <?= htmlspecialchars($displayName) ?>">
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    <span>Leave Review</span>
                </button>
            <?php endif; ?>

            <?php if ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                <a href="<?= $basePath ?>/admin/users/<?= $user['id'] ?>/edit"
                   class="civicone-page-header-actions__btn civicone-page-header-actions__btn--danger"
                   aria-label="Admin: Edit user settings">
                    <i class="fa-solid fa-shield" aria-hidden="true"></i>
                    <span>Admin</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
