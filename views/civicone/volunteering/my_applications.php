<?php
// CivicOne View: My Volunteering Applications - MadeOpen Style
$hTitle = "My Applications";
$hSubtitle = "Track your volunteering journey and log hours";
$hType = "Volunteering";

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Action Bar -->
<div class="civic-action-bar" style="margin-bottom: 24px;">
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
            <span class="dashicons dashicons-clipboard" style="font-size: 48px;"></span>
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

<style>
    /* Action Bar */
    .civic-action-bar {
        display: flex;
        gap: 12px;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    /* Section Titles */
    .civic-section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 20px 0;
        display: flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--civic-brand);
    }

    .civic-section-title .dashicons {
        color: var(--civic-brand);
    }

    /* Badges */
    .civic-badges-section {
        margin-bottom: 32px;
    }

    .civic-badges-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .civic-badge-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: var(--civic-brand);
        color: white;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
        cursor: help;
    }

    .civic-badge-icon {
        font-size: 16px;
    }

    /* Applications List */
    .civic-applications-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .civic-application-card {
        background: var(--civic-bg-card);
        border: 1px solid var(--civic-border);
        border-radius: 12px;
        padding: 20px;
    }

    .civic-application-main {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        flex-wrap: wrap;
    }

    .civic-application-info {
        flex: 1;
        min-width: 250px;
    }

    .civic-application-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 8px 0;
    }

    .civic-application-org,
    .civic-application-shift,
    .civic-application-date {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--civic-text-muted);
        font-size: 14px;
        margin: 4px 0;
    }

    .civic-application-org .dashicons,
    .civic-application-shift .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }

    .civic-calendar-link {
        margin-left: 8px;
        color: var(--civic-brand);
        text-decoration: none;
        font-size: 13px;
    }

    .civic-calendar-link:hover {
        text-decoration: underline;
    }

    .civic-application-status-area {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 12px;
    }

    /* Status Badge */
    .civic-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .civic-status--approved {
        background: #ECFDF5;
        color: #047857;
    }

    .civic-status--declined {
        background: #FEF2F2;
        color: #B91C1C;
    }

    .civic-status--pending {
        background: #FFFBEB;
        color: #B45309;
    }

    /* Modal */
    .civic-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .civic-modal.active {
        display: flex;
    }

    .civic-modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
    }

    .civic-modal-content {
        position: relative;
        background: var(--civic-bg-card);
        max-width: 500px;
        width: 90%;
        border-radius: 12px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        overflow: hidden;
    }

    .civic-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid var(--civic-border);
        background: var(--civic-bg-page);
    }

    .civic-modal-title {
        margin: 0;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--civic-text-main);
    }

    .civic-modal-title .dashicons {
        color: var(--civic-brand);
    }

    .civic-modal-close {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--civic-text-muted);
        padding: 4px;
    }

    .civic-modal-close:hover {
        color: var(--civic-text-main);
    }

    .civic-modal-form {
        padding: 20px;
    }

    .civic-form-info {
        background: var(--civic-bg-page);
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .civic-form-info p {
        margin: 4px 0;
        font-size: 14px;
        color: var(--civic-text-secondary);
    }

    .civic-form-group {
        margin-bottom: 16px;
    }

    .civic-label {
        display: block;
        font-weight: 600;
        color: var(--civic-text-main);
        margin-bottom: 6px;
    }

    .civic-input,
    .civic-textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--civic-border);
        border-radius: 8px;
        font-size: 16px;
        font-family: inherit;
        background: var(--civic-bg-page);
        color: var(--civic-text-main);
        box-sizing: border-box;
    }

    .civic-input:focus,
    .civic-textarea:focus {
        outline: none;
        border-color: var(--civic-brand);
    }

    .civic-modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }

    .civic-modal-actions .civic-btn {
        flex: 1;
        justify-content: center;
    }

    /* Responsive */
    @media (max-width: 600px) {
        .civic-application-main {
            flex-direction: column;
        }

        .civic-application-status-area {
            align-items: flex-start;
            width: 100%;
        }

        .civic-application-status-area .civic-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<script>
    function openLogModal(orgId, oppId, orgName, oppTitle) {
        document.getElementById('log_org_id').value = orgId;
        document.getElementById('log_opp_id').value = oppId;
        document.getElementById('log_org_name').innerText = orgName;
        document.getElementById('log_opp_title').innerText = oppTitle;
        document.getElementById('logHoursModal').classList.add('active');
        document.getElementById('logHoursModal').setAttribute('aria-hidden', 'false');
    }

    function closeLogModal() {
        document.getElementById('logHoursModal').classList.remove('active');
        document.getElementById('logHoursModal').setAttribute('aria-hidden', 'true');
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLogModal();
        }
    });
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
