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
        <div style="display: flex; gap: 28px; align-items: flex-start; flex-wrap: wrap;">
            <!-- Avatar with Online Status -->
            <div style="position: relative;">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                     alt="<?= htmlspecialchars($displayName) ?>"
                     class="glass-avatar"
                     width="120"
                     height="120"
                     loading="lazy">
                <?php if ($profileIsOnline): ?>
                    <span class="profile-online-indicator"
                          style="position:absolute;bottom:8px;right:8px;width:24px;height:24px;background:#10b981;border:3px solid var(--htb-card-bg, #1e293b);border-radius:50%;box-shadow:0 0 12px rgba(16,185,129,0.6);animation:pulse-online 2s infinite;"
                          title="Active now"
                          aria-label="User is online now"></span>
                <?php elseif ($profileIsRecentlyActive): ?>
                    <span class="profile-online-indicator"
                          style="position:absolute;bottom:8px;right:8px;width:24px;height:24px;background:#f59e0b;border:3px solid var(--htb-card-bg, #1e293b);border-radius:50%;box-shadow:0 0 12px rgba(245,158,11,0.6);"
                          title="Active today"
                          aria-label="User was active recently"></span>
                <?php endif; ?>
            </div>

            <!-- User Info and Badges -->
            <div style="flex: 1; min-width: 300px;">
                <h2 class="profile-display-name"
                    style="margin: 0 0 12px 0; font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #1f2937, #4f46e5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <?= htmlspecialchars($displayName) ?>
                </h2>

                <!-- Info Badges -->
                <div style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px;" role="list" aria-label="User information badges">
                    <!-- Online Status Badge -->
                    <?php if ($profileIsOnline): ?>
                        <span class="glass-info-badge"
                              style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1)); border-color: rgba(16, 185, 129, 0.3);"
                              role="listitem">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulse-online 2s infinite;" aria-hidden="true"></span>
                            <strong style="color: #059669;">Online now</strong>
                        </span>
                    <?php elseif ($profileIsRecentlyActive): ?>
                        <span class="glass-info-badge"
                              style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1)); border-color: rgba(245, 158, 11, 0.3);"
                              role="listitem">
                            <i class="fa-solid fa-circle" style="color: #f59e0b; font-size: 8px;" aria-hidden="true"></i>
                            <span style="color: #b45309;"><?= htmlspecialchars($profileStatusText) ?></span>
                        </span>
                    <?php endif; ?>

                    <!-- Location Badge -->
                    <?php if (!empty($user['location'])): ?>
                        <span class="glass-info-badge" role="listitem">
                            <i class="fa-solid fa-location-dot" style="color: #6366f1;" aria-hidden="true"></i>
                            <span aria-label="Location"><?= htmlspecialchars($user['location']) ?></span>
                        </span>
                    <?php endif; ?>

                    <!-- Joined Date Badge -->
                    <span class="glass-info-badge" role="listitem">
                        <i class="fa-solid fa-clock" style="color: #8b5cf6;" aria-hidden="true"></i>
                        <span aria-label="Member since">Joined <?= date('F Y', strtotime($user['created_at'])) ?></span>
                    </span>

                    <!-- Credits Badge -->
                    <span class="glass-info-badge"
                          style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1)); border-color: rgba(16, 185, 129, 0.3);"
                          role="listitem">
                        <i class="fa-solid fa-coins" style="color: #10b981;" aria-hidden="true"></i>
                        <strong aria-label="Credit balance"><?= number_format($user['balance'] ?? 0) ?> Credits</strong>
                    </span>

                    <!-- Organization Roles -->
                    <?php if (!empty($userOrganizations)): ?>
                        <?php foreach ($userOrganizations as $org): ?>
                            <?php if ($org['member_role'] === 'owner'): ?>
                            <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                               class="glass-info-badge"
                               style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.15)); border-color: rgba(251, 191, 36, 0.4); text-decoration: none;"
                               role="listitem"
                               aria-label="Organization owner: <?= htmlspecialchars($org['name']) ?>">
                                <i class="fa-solid fa-crown" style="color: #f59e0b;" aria-hidden="true"></i>
                                <span style="color: #b45309;">Owner: <?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php elseif ($org['member_role'] === 'admin'): ?>
                            <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                               class="glass-info-badge"
                               style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.15)); border-color: rgba(139, 92, 246, 0.4); text-decoration: none;"
                               role="listitem"
                               aria-label="Organization admin: <?= htmlspecialchars($org['name']) ?>">
                                <i class="fa-solid fa-shield" style="color: #8b5cf6;" aria-hidden="true"></i>
                                <span style="color: #7c3aed;">Admin: <?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php else: ?>
                            <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet"
                               class="glass-info-badge"
                               style="text-decoration: none;"
                               role="listitem"
                               aria-label="Organization member: <?= htmlspecialchars($org['name']) ?>">
                                <i class="fa-solid fa-building" style="color: #6366f1;" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Rating Badge -->
                    <?php if ($headerTotalReviews > 0): ?>
                    <a href="#reviews-section"
                       class="glass-info-badge"
                       style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.1)); border-color: rgba(245, 158, 11, 0.3); text-decoration: none; cursor: pointer;"
                       onclick="event.preventDefault(); document.getElementById('reviews-section').scrollIntoView({behavior: 'smooth'})"
                       role="listitem"
                       aria-label="Average rating: <?= $headerAvgRating ?> stars from <?= $headerTotalReviews ?> reviews">
                        <i class="fa-solid fa-star" style="color: #f59e0b;" aria-hidden="true"></i>
                        <strong style="color: #b45309;"><?= $headerAvgRating ?></strong>
                        <span style="color: #92400e; font-size: 0.8rem;">(<?= $headerTotalReviews ?>)</span>
                    </a>
                    <?php else: ?>
                    <span class="glass-info-badge" style="opacity: 0.6;" role="listitem">
                        <i class="fa-regular fa-star" style="color: #9ca3af;" aria-hidden="true"></i>
                        <span style="color: #6b7280;">No reviews</span>
                    </span>
                    <?php endif; ?>

                    <!-- Admin-only Phone Badge -->
                    <?php if (!empty($user['phone']) && (($_SESSION['user_role'] ?? '') === 'admin' || !empty($_SESSION['is_super_admin']))): ?>
                        <span class="glass-info-badge"
                              style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1)); border-color: rgba(239, 68, 68, 0.3);"
                              role="listitem"
                              aria-label="Admin view: Phone number">
                            <i class="fa-solid fa-shield-halved" style="color: #ef4444;" aria-hidden="true"></i>
                            <strong>Admin: <?= htmlspecialchars($user['phone']) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;" role="group" aria-label="Profile actions">
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
                              style="display:inline;">
                            <?= Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit"
                                    class="glass-btn"
                                    style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.9), rgba(217, 119, 6, 0.9)); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);"
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
                           class="glass-btn"
                           style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(124, 58, 237, 0.9)); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);"
                           aria-label="View your wallet insights">
                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i> My Insights
                        </a>
                        <?php endif; ?>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <!-- Other User Profile Actions -->
                        <?php if (!$connection): ?>
                            <!-- Not friends - show Add Friend button -->
                            <form action="<?= $basePath ?>/connections/add" method="POST" style="display: inline;">
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
                                    class="glass-btn glass-btn-secondary"
                                    style="cursor: not-allowed; opacity: 0.5;"
                                    aria-label="Friend request already sent">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i> Request Sent
                            </button>
                        <?php elseif ($connection['status'] === 'pending' && $connection['receiver_id'] == $_SESSION['user_id']): ?>
                            <!-- Accept friend request -->
                            <form action="<?= $basePath ?>/connections/accept" method="POST" style="display: inline;">
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
                            <span class="glass-btn glass-btn-success"
                                  style="cursor: default;"
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
                                class="glass-btn"
                                style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.9), rgba(217, 119, 6, 0.9)); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);"
                                aria-label="Leave a review for <?= htmlspecialchars($displayName) ?>">
                            <i class="fa-solid fa-star" aria-hidden="true"></i> Leave Review
                        </button>
                    <?php endif; ?>

                    <?php if ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                        <a href="<?= $basePath ?>/admin/users/<?= $user['id'] ?>/edit"
                           class="glass-btn"
                           style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9)); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);"
                           aria-label="Admin: Edit user settings">
                            <i class="fa-solid fa-shield" aria-hidden="true"></i> Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
