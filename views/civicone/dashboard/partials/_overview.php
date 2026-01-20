<?php
/**
 * Dashboard Overview Partial
 * Extracted from dashboard.php (no output change)
 */
?>
<!-- OVERVIEW TAB -->
<div class="civic-dash-grid">
    <!-- Main Column -->
    <div>
        <!-- Balance Summary (GOV.UK Summary List - WCAG 2.1 AA) -->
        <h2 class="civicone-heading-l">Wallet</h2>
        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Current balance</dt>
                <dd class="govuk-summary-list__value">
                    <strong class="civicone-wallet-balance"><?= htmlspecialchars($user['balance']) ?> Hours</strong>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/wallet">
                        Manage wallet<span class="civicone-visually-hidden"> and view transaction history</span>
                    </a>
                </dd>
            </div>
        </dl>

        <!-- Recent Notifications Preview -->
        <section class="civic-dash-card" aria-labelledby="notif-preview-heading">
            <div class="civic-dash-card-header">
                <h2 id="notif-preview-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                    Recent Notifications
                </h2>
                <a href="?tab=notifications" class="civic-dash-view-all">
                    View All <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <?php
            $previewNotifs = [];
            if (isset($_SESSION['user_id'])) {
                $previewNotifs = \Nexus\Models\Notification::getLatest($_SESSION['user_id'], 5);
            }
            ?>
            <?php if (empty($previewNotifs)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon"><i class="fa-regular fa-bell-slash" aria-hidden="true"></i></div>
                    <p class="civic-empty-text">No new notifications.</p>
                </div>
            <?php else: ?>
                <ul role="list" class="civic-notif-list">
                <?php foreach ($previewNotifs as $n): ?>
                    <li>
                        <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>" class="civic-notif-item">
                            <div class="civic-notif-dot <?= $n['is_read'] ? 'read' : 'unread' ?>" aria-hidden="true"></div>
                            <div>
                                <div class="civic-notif-message <?= $n['is_read'] ? '' : 'unread' ?>">
                                    <?= htmlspecialchars($n['message']) ?>
                                </div>
                                <div class="civic-notif-time">
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <?= date('M j, g:i a', strtotime($n['created_at'])) ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Recent Activity -->
        <section class="civic-dash-card" aria-labelledby="activity-heading">
            <div class="civic-dash-card-header">
                <h2 id="activity-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    Recent Activity
                </h2>
            </div>
            <?php if (empty($activity_feed)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></div>
                    <p class="civic-empty-text">No recent activity.</p>
                </div>
            <?php else: ?>
                <ul role="list" class="civic-activity-list">
                <?php foreach ($activity_feed as $log): ?>
                    <li class="civic-activity-item">
                        <div>
                            <div class="civic-activity-action"><?= htmlspecialchars($log['action']) ?></div>
                            <div class="civic-activity-details"><?= htmlspecialchars($log['details'] ?? '') ?></div>
                        </div>
                        <div class="civic-activity-date"><?= date('M j', strtotime($log['created_at'])) ?></div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <!-- Sidebar Column -->
    <div>
        <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
            <!-- Upcoming Events -->
            <section class="civic-dash-card" aria-labelledby="events-sidebar-heading">
                <div class="civic-dash-card-header">
                    <h2 id="events-sidebar-heading" class="civic-dash-card-title">
                        <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                        Upcoming Events
                    </h2>
                </div>
                <?php if (empty($myEvents)): ?>
                    <div class="civic-empty-state">
                        <div class="civic-empty-icon"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i></div>
                        <p class="civic-empty-text">No upcoming events.</p>
                        <a href="<?= $basePath ?>/events" class="civic-button civic-button--start" role="button">Explore Events</a>
                    </div>
                <?php else: ?>
                    <ul role="list">
                    <?php foreach (array_slice($myEvents, 0, 3) as $ev): ?>
                        <li>
                            <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="civic-event-item">
                                <div class="civic-event-date-box">
                                    <div class="civic-event-month"><?= date('M', strtotime($ev['start_time'])) ?></div>
                                    <div class="civic-event-day"><?= date('d', strtotime($ev['start_time'])) ?></div>
                                </div>
                                <div>
                                    <div class="civic-event-title"><?= htmlspecialchars($ev['title']) ?></div>
                                    <div class="civic-event-location">
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                        <?= htmlspecialchars($ev['location'] ?? 'TBA') ?>
                                    </div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Smart Matches Widget -->
        <?php
        $dashboardMatches = [];
        try {
            $dashboardMatches = \Nexus\Services\MatchingService::getHotMatches($user['id'], 3);
        } catch (\Exception $e) {
            // Silently fail - matches not critical for dashboard
        }
        ?>
        <section class="civic-dash-card civic-matches-widget" aria-labelledby="matches-heading">
            <div class="civic-dash-card-header civic-matches-header">
                <h2 id="matches-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-fire" aria-hidden="true"></i>
                    Smart Matches
                </h2>
                <a href="<?= $basePath ?>/matches" class="civic-dash-view-all">View All <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php if (empty($dashboardMatches)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon">🔥</div>
                    <p class="civic-empty-text">No hot matches yet.</p>
                    <p class="civic-empty-subtext">Create listings to find compatible members nearby.</p>
                    <a href="<?= $basePath ?>/listings/create" class="civic-button civic-button--start" role="button">Create a Listing</a>
                </div>
            <?php else: ?>
                <ul role="list" class="civic-matches-list">
                <?php foreach ($dashboardMatches as $match): ?>
                    <?php
                    $matchScore = (int)($match['match_score'] ?? 0);
                    $scoreClass = $matchScore >= 85 ? 'hot' : ($matchScore >= 70 ? 'warm' : 'cool');
                    $distanceKm = $match['distance_km'] ?? null;
                    ?>
                    <li>
                        <a href="<?= $basePath ?>/listings/<?= $match['id'] ?>" class="civic-match-item">
                            <div class="civic-match-avatar">
                                <?php if (!empty($match['user_avatar'])): ?>
                                    <img src="<?= htmlspecialchars($match['user_avatar']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span><?= strtoupper(substr($match['user_name'] ?? 'U', 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="civic-match-info">
                                <div class="civic-match-title"><?= htmlspecialchars($match['title'] ?? 'Listing') ?></div>
                                <div class="civic-match-user"><?= htmlspecialchars($match['user_name'] ?? 'Unknown') ?></div>
                                <div class="civic-match-meta">
                                    <span class="civic-match-score <?= $scoreClass ?>"><?= $matchScore ?>%</span>
                                    <?php if ($distanceKm !== null): ?>
                                        <span class="civic-match-distance">
                                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                            <?= number_format($distanceKm, 1) ?>km
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
                <div class="civic-card-footer">
                    <a href="<?= $basePath ?>/matches" class="civic-footer-link">
                        <i class="fa-solid fa-fire-flame-curved" aria-hidden="true"></i> See All Matches
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- My Hubs -->
        <section class="civic-dash-card" aria-labelledby="hubs-sidebar-heading">
            <div class="civic-dash-card-header">
                <h2 id="hubs-sidebar-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    My Hubs
                </h2>
            </div>
            <?php if (empty($myGroups)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                    <p class="civic-empty-text">You haven't joined any hubs yet.</p>
                    <a href="<?= $basePath ?>/groups" class="civic-button civic-button--start" role="button">Join a Hub</a>
                </div>
            <?php else: ?>
                <ul role="list">
                <?php foreach (array_slice($myGroups, 0, 4) as $grp): ?>
                    <li>
                        <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="civic-hub-item">
                            <div class="civic-hub-name"><?= htmlspecialchars($grp['name']) ?></div>
                            <div class="civic-hub-members">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <?= $grp['member_count'] ?? '0' ?> members
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Quick Actions -->
        <div class="civic-dash-card">
            <div class="civic-quick-actions">
                <a href="<?= $basePath ?>/achievements" class="civic-button civic-button--secondary" role="button">
                    <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                    View Achievements
                </a>
                <a href="<?= $basePath ?>/listings/create" class="civic-button" role="button">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Post Offer or Request
                </a>
                <a href="<?= $basePath ?>/groups" class="civic-button civic-button--secondary" role="button">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    Browse Hubs
                </a>
            </div>
        </div>
    </div>
</div>
