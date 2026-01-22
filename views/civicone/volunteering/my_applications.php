<?php
/**
 * Template A: List Page - My Volunteering Applications
 * 
 * Purpose: Track volunteer applications and log hours
 * Features: Application cards, hour logging modal, badges, certificates
 * WCAG 2.1 AA: Semantic HTML, ARIA labels, keyboard navigation
 */

// CivicOne View: My Volunteering Applications - MadeOpen Style
$hTitle = "My Applications";
$hSubtitle = "Track your volunteering journey and log hours";
$hType = "Volunteering";

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/purged/civicone-volunteering-my-applications.min.css">


<!-- Action Bar -->
<div class="civic-action-bar mb-24">
    <a href="<?= $basePath ?>/volunteering" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Browse Opportunities
    </a>
    <a href="<?= $basePath ?>/volunteering/certificate/print" target="_blank" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
        Get Certificate
    </a>
</div>

<?php if (empty($applications)): ?>
    <div class="civic-empty-state">
        <div class="civic-empty-state-icon">
            <span class="dashicons dashicons-clipboard icon-size-48"></span>
        </div>
        <h3 class="civic-empty-state-title">No applications yet</h3>
        <p class="civic-empty-state-text">You haven't applied to any volunteer opportunities yet.</p>
        <a href="<?= $basePath ?>/volunteering" class="civic-btn">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            Find Opportunities
        </a>
    </div>
<?php else: ?>

    <!-- Badges Section -->
    <?php if (!empty($badges)): ?>
        <section class="civic-badges-section">
            <h2 class="civic-section-title">
                <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                Achievements
            </h2>
            <div class="civic-badges-grid">
                <?php foreach ($badges as $badge): ?>
                    <div class="civic-badge-item" title="<?= htmlspecialchars($badge['name']) ?> (<?= date('M Y', strtotime($badge['awarded_at'])) ?>)">
                        <span class="civic-badge-icon"><?= $badge['icon'] ?></span>
                        <span class="civic-badge-name"><?= htmlspecialchars($badge['name']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Applications List -->
    <section class="civic-applications-section">
        <h2 class="civic-section-title">
            <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
            My Applications
        </h2>

        <div class="civic-applications-list">
            <?php foreach ($applications as $app): ?>
                <article class="civic-application-card">
                    <div class="civic-application-main">
                        <div class="civic-application-info">
                            <h3 class="civic-application-title">
                                <?= htmlspecialchars($app['opp_title']) ?>
                            </h3>
                            <p class="civic-application-org">
                                <span class="dashicons dashicons-building" aria-hidden="true"></span>
                                <?= htmlspecialchars($app['org_name']) ?>
                            </p>

                            <?php if (!empty($app['shift_start'])): ?>
                                <p class="civic-application-shift">
                                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <?= date('M d, Y \a\t g:i A', strtotime($app['shift_start'])) ?>
                                    <a href="<?= $basePath ?>/volunteering/ics/<?= $app['id'] ?>" class="civic-calendar-link">
                                        Add to Calendar
                                    </a>
                                </p>
                            <?php endif; ?>

                            <p class="civic-application-date">
                                Applied on <?= date('M j, Y', strtotime($app['created_at'])) ?>
                            </p>
                        </div>

                        <div class="civic-application-status-area">
                            <?php
                            $statusClass = match ($app['status']) {
                                'approved' => 'civic-status--approved',
                                'declined' => 'civic-status--declined',
                                default => 'civic-status--pending'
                            };
                            ?>
                            <span class="civic-status <?= $statusClass ?>">
                                <?= ucfirst($app['status']) ?>
                            </span>

                            <?php if ($app['status'] == 'approved'): ?>
                                <button type="button"
                                        onclick="openLogModal(<?= $app['organization_id'] ?>, <?= $app['opportunity_id'] ?>, '<?= htmlspecialchars($app['org_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['opp_title'], ENT_QUOTES) ?>')"
                                        class="civic-btn civic-btn--sm civic-btn--outline">
                                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                    Log Hours
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

<?php endif; ?>

<!-- Log Hours Modal -->
<div id="logHoursModal" class="civic-modal" role="dialog" aria-labelledby="modal-title" aria-hidden="true">
    <div class="civic-modal-backdrop" onclick="closeLogModal()"></div>
    <div class="civic-modal-content">
        <div class="civic-modal-header">
            <h3 id="modal-title" class="civic-modal-title">
                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                Log Volunteer Hours
            </h3>
            <button type="button" class="civic-modal-close" onclick="closeLogModal()" aria-label="Close modal">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
        </div>

        <form action="<?= $basePath ?>/volunteering/log-hours" method="POST" class="civic-modal-form">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="org_id" id="log_org_id">
            <input type="hidden" name="opp_id" id="log_opp_id">

            <div class="civic-form-info">
                <p><strong>Organization:</strong> <span id="log_org_name"></span></p>
                <p><strong>Role:</strong> <span id="log_opp_title"></span></p>
            </div>

            <div class="civic-form-group">
                <label for="log_date" class="civic-label">Date</label>
                <input type="date" name="date" id="log_date" required value="<?= date('Y-m-d') ?>" class="civic-input">
            </div>

            <div class="civic-form-group">
                <label for="log_hours" class="civic-label">Hours Worked</label>
                <input type="number" step="0.5" name="hours" id="log_hours" required placeholder="e.g. 2.5" class="civic-input">
            </div>

            <div class="civic-form-group">
                <label for="log_description" class="civic-label">Description (Optional)</label>
                <textarea name="description" id="log_description" rows="3" placeholder="Briefly describe what you did..." class="civic-textarea"></textarea>
            </div>

            <div class="civic-modal-actions">
                <button type="submit" class="civic-btn">
                    <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                    Submit Hours
                </button>
                <button type="button" onclick="closeLogModal()" class="civic-btn civic-btn--outline">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>



<script src="/assets/js/civicone-volunteering-my-applications.js"></script>
<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
