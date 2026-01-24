<?php
/**
 * Dashboard Overview Partial - GOV.UK Frontend v5.14.0 Compliant
 * Uses GOV.UK grid, summary lists, and inset text patterns
 */
?>
<!-- Dashboard Overview (Two Column Layout) -->
<div class="govuk-grid-row">
    <!-- Main Column (2/3) -->
    <div class="govuk-grid-column-two-thirds">

        <!-- Wallet Balance Summary (GOV.UK Summary List) -->
        <h2 class="govuk-heading-l">Wallet</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Current balance</dt>
                <dd class="govuk-summary-list__value">
                    <strong class="govuk-!-font-size-36"><?= htmlspecialchars($user['balance']) ?> Hours</strong>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/wallet">
                        Manage wallet<span class="govuk-visually-hidden"> and view transaction history</span>
                    </a>
                </dd>
            </div>
        </dl>

        <!-- Recent Notifications -->
        <section aria-labelledby="notif-preview-heading" class="govuk-!-margin-bottom-6">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 id="notif-preview-heading" class="govuk-heading-m govuk-!-margin-bottom-0">Recent Notifications</h2>
                <a href="<?= $basePath ?>/dashboard/notifications" class="govuk-link">View all</a>
            </div>
            <?php
            $previewNotifs = [];
            if (isset($_SESSION['user_id'])) {
                $previewNotifs = \Nexus\Models\Notification::getLatest($_SESSION['user_id'], 5);
            }
            ?>
            <?php if (empty($previewNotifs)): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body">No new notifications.</p>
                </div>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach ($previewNotifs as $n): ?>
                    <li class="govuk-!-padding-bottom-2 govuk-!-margin-bottom-2" style="border-bottom: 1px solid #b1b4b6;">
                        <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>" class="govuk-link govuk-link--no-underline" style="display: block;">
                            <p class="govuk-body govuk-!-margin-bottom-1 <?= $n['is_read'] ? '' : 'govuk-!-font-weight-bold' ?>">
                                <?= $n['is_read'] ? '' : '<strong class="govuk-tag govuk-tag--blue" style="font-size: 10px; margin-right: 5px;">NEW</strong>' ?>
                                <?= htmlspecialchars($n['message']) ?>
                            </p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                <?= date('j M Y, g:i a', strtotime($n['created_at'])) ?>
                            </p>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Recent Activity -->
        <section aria-labelledby="activity-heading" class="govuk-!-margin-bottom-6">
            <h2 id="activity-heading" class="govuk-heading-m">Recent Activity</h2>
            <?php if (empty($activity_feed)): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body">No recent activity.</p>
                </div>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach ($activity_feed as $log): ?>
                    <li class="govuk-!-padding-bottom-2 govuk-!-margin-bottom-2" style="border-bottom: 1px solid #b1b4b6; display: flex; justify-content: space-between;">
                        <div>
                            <p class="govuk-body govuk-!-margin-bottom-0"><?= htmlspecialchars($log['action']) ?></p>
                            <?php if (!empty($log['details'])): ?>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= htmlspecialchars($log['details']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="govuk-body-s" style="color: #505a5f; white-space: nowrap;"><?= date('j M', strtotime($log['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

    <!-- Sidebar Column (1/3) -->
    <div class="govuk-grid-column-one-third">

        <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
        <!-- Upcoming Events -->
        <section aria-labelledby="events-sidebar-heading" class="govuk-!-padding-4 govuk-!-margin-bottom-4" style="background: #f3f2f1;">
            <h2 id="events-sidebar-heading" class="govuk-heading-s">Upcoming Events</h2>
            <?php if (empty($myEvents)): ?>
                <p class="govuk-body-s">No upcoming events.</p>
                <a href="<?= $basePath ?>/events" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0">Explore Events</a>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach (array_slice($myEvents, 0, 3) as $ev): ?>
                    <li class="govuk-!-margin-bottom-2">
                        <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="govuk-link govuk-link--no-underline" style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: #1d70b8; color: white; padding: 5px 10px; border-radius: 4px; text-align: center; min-width: 50px;">
                                <div style="font-size: 10px; text-transform: uppercase;"><?= date('M', strtotime($ev['start_time'])) ?></div>
                                <div style="font-size: 18px; font-weight: bold;"><?= date('d', strtotime($ev['start_time'])) ?></div>
                            </div>
                            <div>
                                <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= htmlspecialchars($ev['title']) ?></p>
                                <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= htmlspecialchars($ev['location'] ?? 'TBA') ?></p>
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
        <section aria-labelledby="matches-heading" class="govuk-!-padding-4 govuk-!-margin-bottom-4" style="background: #f3f2f1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h2 id="matches-heading" class="govuk-heading-s govuk-!-margin-bottom-0">Smart Matches</h2>
                <a href="<?= $basePath ?>/matches" class="govuk-link govuk-body-s">View all</a>
            </div>
            <?php if (empty($dashboardMatches)): ?>
                <p class="govuk-body-s">No hot matches yet.</p>
                <p class="govuk-body-s" style="color: #505a5f;">Create listings to find compatible members nearby.</p>
                <a href="<?= $basePath ?>/listings/create" class="govuk-button govuk-!-margin-bottom-0">Create a Listing</a>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach ($dashboardMatches as $match): ?>
                    <?php
                    $matchScore = (int)($match['match_score'] ?? 0);
                    $tagColor = $matchScore >= 85 ? 'govuk-tag--green' : ($matchScore >= 70 ? 'govuk-tag--yellow' : 'govuk-tag--grey');
                    $distanceKm = $match['distance_km'] ?? null;
                    ?>
                    <li class="govuk-!-margin-bottom-2 govuk-!-padding-bottom-2" style="border-bottom: 1px solid #b1b4b6;">
                        <a href="<?= $basePath ?>/listings/<?= $match['id'] ?>" class="govuk-link govuk-link--no-underline" style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #b1b4b6; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                                <?php if (!empty($match['user_avatar'])): ?>
                                    <img src="<?= htmlspecialchars($match['user_avatar']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span style="color: white; font-weight: bold;"><?= strtoupper(substr($match['user_name'] ?? 'U', 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($match['title'] ?? 'Listing') ?></p>
                                <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                    <?= htmlspecialchars($match['user_name'] ?? 'Unknown') ?>
                                    <?php if ($distanceKm !== null): ?>
                                        &middot; <?= number_format($distanceKm, 1) ?>km
                                    <?php endif; ?>
                                </p>
                            </div>
                            <strong class="govuk-tag <?= $tagColor ?>" style="font-size: 11px;"><?= $matchScore ?>%</strong>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- My Hubs -->
        <section aria-labelledby="hubs-sidebar-heading" class="govuk-!-padding-4 govuk-!-margin-bottom-4" style="background: #f3f2f1;">
            <h2 id="hubs-sidebar-heading" class="govuk-heading-s">My Hubs</h2>
            <?php if (empty($myGroups)): ?>
                <p class="govuk-body-s">You haven't joined any hubs yet.</p>
                <a href="<?= $basePath ?>/groups" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0">Join a Hub</a>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach (array_slice($myGroups, 0, 4) as $grp): ?>
                    <li class="govuk-!-margin-bottom-2">
                        <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="govuk-link">
                            <?= htmlspecialchars($grp['name']) ?>
                        </a>
                        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= $grp['member_count'] ?? '0' ?> members</p>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Quick Actions -->
        <section class="govuk-!-padding-4" style="background: #f3f2f1;">
            <h2 class="govuk-heading-s">Quick Actions</h2>
            <p class="govuk-!-margin-bottom-2">
                <a href="<?= $basePath ?>/achievements" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-2" style="width: 100%;">View Achievements</a>
            </p>
            <p class="govuk-!-margin-bottom-2">
                <a href="<?= $basePath ?>/listings/create" class="govuk-button govuk-!-margin-bottom-2" style="width: 100%;">Post Offer or Request</a>
            </p>
            <p class="govuk-!-margin-bottom-0">
                <a href="<?= $basePath ?>/groups" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" style="width: 100%;">Browse Hubs</a>
            </p>
        </section>

    </div>
</div>
