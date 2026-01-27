<?php
/**
 * Component: Profile Header
 *
 * Extracted from: views/modern/profile/show.php (Lines 2159-2327)
 * Date: 2026-01-11
 *
 * Features:
 * - Avatar with online status indicator
 * - User name and display badges (location, joined date, credits, organization roles, rating)
 * - Action buttons (Edit Profile, Add Friend, Message, Send Credits, Leave Review, Admin)
 * - All friendship states (Add Friend, Request Sent, Accept Request, Already Friends)
 * - Admin impersonation button
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

<div class="htb-container">
    <div class="glass-profile-card">
        <div class="mte-profile-header--layout">
            <!-- Avatar with Online Status -->
            <div class="mte-profile-header--avatar-wrap">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                     alt="<?= htmlspecialchars($displayName) ?>"
                     class="glass-avatar"
                     width="120"
                     height="120"
                     loading="lazy">
                <?php if ($profileIsOnline): ?>
                    <span class="profile-online-indicator mte-profile-header--online-green"
                          title="Active now"
                          aria-label="User is online now"></span>
                <?php elseif ($profileIsRecentlyActive): ?>
                    <span class="profile-online-indicator mte-profile-header--online-yellow"
                          title="Active today"
                          aria-label="User was active recently"></span>
                <?php endif; ?>
            </div>

            <!-- User Info and Badges -->
            <div class="mte-profile-header--info">
                <h2 class="profile-display-name mte-profile-header--name">
                    <?= htmlspecialchars($displayName) ?>
                </h2>

                <!-- Info Badges -->
                <div class="mte-profile-header--badges" role="list" aria-label="User information badges">
                    <!-- Online Status Badge -->
                    <?php if ($profileIsOnline): ?>
                        <span class="glass-info-badge mte-profile-header--badge-online" role="listitem">
                            <span class="mte-profile-header--pulse-dot" aria-hidden="true"></span>
                            <strong class="mte-profile-header--text-green">Online now</strong>
                        </span>
                    <?php elseif ($profileIsRecentlyActive): ?>
                        <span class="glass-info-badge mte-profile-header--badge-active" role="listitem">
                            <i class="fa-solid fa-circle mte-profile-header--icon-yellow mte-profile-header--icon-small" aria-hidden="true"></i>
                            <span class="mte-profile-header--text-amber"><?= htmlspecialchars($profileStatusText) ?></span>
                        </span>
                    <?php endif; ?>

                    <!-- Location Badge -->
                    <?php if (!empty($user['location'])): ?>
                        <span class="glass-info-badge" role="listitem">
                            <i class="fa-solid fa-location-dot mte-profile-header--icon-indigo" aria-hidden="true"></i>
                            <span aria-label="Location"><?= htmlspecialchars($user['location']) ?></span>
                        </span>
                    <?php endif; ?>

                    <!-- Joined Date Badge -->
                    <span class="glass-info-badge" role="listitem">
                        <i class="fa-solid fa-clock mte-profile-header--icon-purple" aria-hidden="true"></i>
                        <span aria-label="Member since">Joined <?= date('F Y', strtotime($user['created_at'])) ?></span>
                    </span>

                    <!-- Credits Badge -->
                    <span class="glass-info-badge mte-profile-header--badge-credits" role="listitem">
                        <i class="fa-solid fa-coins mte-profile-header--icon-green" aria-hidden="true"></i>
                        <strong aria-label="Credit balance"><?= number_format($user['balance'] ?? 0) ?> Credits</strong>
                    </span>

                    <!-- Organization Roles -->
                    <?php if (!empty($userOrganizations)): ?>
                        <?php foreach ($userOrganizations as $org): ?>
                            <?php if ($org['member_role'] === 'owner'): ?>
                            <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                               class="glass-info-badge mte-profile-header--badge-org-owner"
                               role="listitem"
                               aria-label="Organization owner: <?= htmlspecialchars($org['name']) ?>">
                                <i class="fa-solid fa-crown mte-profile-header--icon-yellow" aria-hidden="true"></i>
                                <span class="mte-profile-header--text-amber">Owner: <?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php elseif ($org['member_role'] === 'admin'): ?>
                            <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                               class="glass-info-badge mte-profile-header--badge-org-admin"
                               role="listitem"
                               aria-label="Organization admin: <?= htmlspecialchars($org['name']) ?>">
                                <i class="fa-solid fa-shield mte-profile-header--icon-purple" aria-hidden="true"></i>
                                <span class="mte-profile-header--text-purple">Admin: <?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php else: ?>
                            <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                               class="glass-info-badge mte-profile-header--badge-org-member"
                               role="listitem"
                               aria-label="Organization member: <?= htmlspecialchars($org['name']) ?>">
                                <i class="fa-solid fa-building mte-profile-header--icon-indigo" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Rating Badge -->
                    <?php if ($headerTotalReviews > 0): ?>
                    <a href="#reviews-section"
                       class="glass-info-badge mte-profile-header--badge-rating"
                       onclick="event.preventDefault(); document.getElementById('reviews-section').scrollIntoView({behavior: 'smooth'})"
                       role="listitem"
                       aria-label="Average rating: <?= $headerAvgRating ?> stars from <?= $headerTotalReviews ?> reviews">
                        <i class="fa-solid fa-star mte-profile-header--icon-yellow" aria-hidden="true"></i>
                        <strong class="mte-profile-header--text-amber"><?= $headerAvgRating ?></strong>
                        <span class="mte-profile-header--text-amber-dark mte-profile-header--text-small">(<?= $headerTotalReviews ?>)</span>
                    </a>
                    <?php else: ?>
                    <span class="glass-info-badge mte-profile-header--badge-no-reviews" role="listitem">
                        <i class="fa-regular fa-star mte-profile-header--icon-gray" aria-hidden="true"></i>
                        <span class="mte-profile-header--text-gray">No reviews</span>
                    </span>
                    <?php endif; ?>

                    <!-- Admin-only Phone Badge -->
                    <?php if (!empty($user['phone']) && (($_SESSION['user_role'] ?? '') === 'admin' || !empty($_SESSION['is_super_admin']))): ?>
                        <span class="glass-info-badge mte-profile-header--badge-admin"
                              role="listitem"
                              aria-label="Admin view: Phone number">
                            <i class="fa-solid fa-shield-halved mte-profile-header--icon-red" aria-hidden="true"></i>
                            <strong>Admin: <?= htmlspecialchars($user['phone']) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="mte-profile-header--actions" role="group" aria-label="Profile actions">
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
                              class="mte-profile-header--form-inline">
                            <?= Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit"
                                    class="glass-btn mte-profile-header--btn-impersonate"
                                    aria-label="Login as this user (admin action)">
                                <i class="fa-solid fa-user-secret" aria-hidden="true"></i> Login As User
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']): ?>
                        <!-- Own Profile Actions -->
                        <a href="<?= $basePath ?>/profile/edit"
                           class="glass-btn glass-btn-secondary"
                           aria-label="Edit your profile">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit Profile
                        </a>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                        <a href="<?= $basePath ?>/wallet/insights"
                           class="glass-btn mte-profile-header--btn-insights"
                           aria-label="View your wallet insights">
                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i> My Insights
                        </a>
                        <?php endif; ?>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <!-- Other User Profile Actions -->
                        <?php if (!$connection): ?>
                            <!-- Not friends - show Add Friend button -->
                            <form action="<?= $basePath ?>/connections/add" method="POST" class="mte-profile-header--form-inline">
                                <?= Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="receiver_id" value="<?= $user['id'] ?>">
                                <button type="submit"
                                        class="glass-btn"
                                        aria-label="Send friend request to <?= htmlspecialchars($displayName) ?>">
                                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Friend
                                </button>
                            </form>
                        <?php elseif ($connection['status'] === 'pending' && $connection['requester_id'] == $_SESSION['user_id']): ?>
                            <!-- Friend request sent -->
                            <button disabled
                                    class="glass-btn glass-btn-secondary mte-profile-header--btn-disabled"
                                    aria-label="Friend request already sent">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i> Request Sent
                            </button>
                        <?php elseif ($connection['status'] === 'pending' && $connection['receiver_id'] == $_SESSION['user_id']): ?>
                            <!-- Accept friend request -->
                            <form action="<?= $basePath ?>/connections/accept" method="POST" class="mte-profile-header--form-inline">
                                <?= Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="connection_id" value="<?= $connection['id'] ?>">
                                <button type="submit"
                                        class="glass-btn glass-btn-success"
                                        aria-label="Accept friend request from <?= htmlspecialchars($displayName) ?>">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i> Accept Request
                                </button>
                            </form>
                        <?php elseif ($connection['status'] === 'accepted'): ?>
                            <!-- Already friends -->
                            <span class="glass-btn glass-btn-success mte-profile-header--btn-friends"
                                  role="status"
                                  aria-label="You are friends with <?= htmlspecialchars($displayName) ?>">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Friends
                            </span>
                        <?php endif; ?>

                        <a href="<?= $basePath ?>/messages/<?= $user['id'] ?>"
                           class="glass-btn"
                           aria-label="Send message to <?= htmlspecialchars($displayName) ?>">
                            <i class="fa-solid fa-message" aria-hidden="true"></i> Message
                        </a>
                        <a href="<?= $basePath ?>/wallet?to=<?= $user['id'] ?>"
                           class="glass-btn glass-btn-secondary"
                           aria-label="Send credits to <?= htmlspecialchars($displayName) ?>">
                            <i class="fa-solid fa-coins" aria-hidden="true"></i> Send Credits
                        </a>
                        <button type="button"
                                onclick="openReviewModal()"
                                class="glass-btn mte-profile-header--btn-review"
                                aria-label="Leave a review for <?= htmlspecialchars($displayName) ?>">
                            <i class="fa-solid fa-star" aria-hidden="true"></i> Leave Review
                        </button>
                    <?php endif; ?>

                    <?php if ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                        <a href="<?= $basePath ?>/admin/users/<?= $user['id'] ?>/edit"
                           class="glass-btn mte-profile-header--btn-admin"
                           aria-label="Admin: Edit user settings">
                            <i class="fa-solid fa-shield" aria-hidden="true"></i> Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
