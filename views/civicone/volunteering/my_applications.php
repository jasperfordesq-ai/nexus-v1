<?php
/**
 * Template A: List Page - My Volunteering Applications
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Track volunteer applications and log hours
 * Features: Application cards, hour logging modal, badges, certificates
 */

$pageTitle = "My Applications";
\Nexus\Core\SEO::setTitle('My Volunteering Applications');
\Nexus\Core\SEO::setDescription('Track your volunteer applications, log hours, and view achievements.');

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/volunteering" class="govuk-back-link">Back to volunteering</a>

    <main class="govuk-main-wrapper">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-clipboard-list govuk-!-margin-right-2" aria-hidden="true"></i>
                    My Applications
                </h1>
                <p class="govuk-body-l">Track your volunteering journey and log hours</p>
            </div>
            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                <a href="<?= $basePath ?>/volunteering/certificate/print" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-file-lines govuk-!-margin-right-2" aria-hidden="true"></i>
                    Get Certificate
                </a>
            </div>
        </div>

        <?php if (empty($applications)): ?>
            <!-- Empty State -->
            <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-border-left-blue">
                <p class="govuk-body govuk-!-margin-bottom-4">
                    <i class="fa-solid fa-clipboard fa-3x civicone-icon-blue" aria-hidden="true"></i>
                </p>
                <h2 class="govuk-heading-l">No applications yet</h2>
                <p class="govuk-body govuk-!-margin-bottom-6">
                    You haven't applied to any volunteer opportunities yet.
                </p>
                <a href="<?= $basePath ?>/volunteering" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-search govuk-!-margin-right-2" aria-hidden="true"></i>
                    Find Opportunities
                </a>
            </div>
        <?php else: ?>

            <!-- Badges Section -->
            <?php if (!empty($badges)): ?>
                <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg civicone-border-left-orange">
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-award govuk-!-margin-right-2 civicone-icon-orange" aria-hidden="true"></i>
                        Achievements
                    </h2>
                    <div class="govuk-grid-row">
                        <?php foreach ($badges as $badge): ?>
                            <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-3">
                                <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-badge-card" title="<?= htmlspecialchars($badge['name']) ?> (<?= date('M Y', strtotime($badge['awarded_at'])) ?>)">
                                    <span class="civicone-badge-icon"><?= $badge['icon'] ?></span>
                                    <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0">
                                        <?= htmlspecialchars($badge['name']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Applications List -->
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-clipboard-list govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                My Applications (<?= count($applications) ?>)
            </h2>

            <?php foreach ($applications as $app): ?>
                <?php
                $statusBorderClass = match($app['status']) {
                    'approved' => 'civicone-border-left-green',
                    'declined' => 'civicone-border-left-red',
                    default => 'civicone-border-left-blue'
                };
                ?>
                <div class="govuk-!-margin-bottom-4 govuk-!-padding-4 civicone-application-card <?= $statusBorderClass ?>">
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-two-thirds">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                                <?= htmlspecialchars($app['opp_title']) ?>
                            </h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-2">
                                <i class="fa-solid fa-building govuk-!-margin-right-1 civicone-icon-grey" aria-hidden="true"></i>
                                <?= htmlspecialchars($app['org_name']) ?>
                            </p>

                            <?php if (!empty($app['shift_start'])): ?>
                                <p class="govuk-body-s govuk-!-margin-bottom-2">
                                    <i class="fa-solid fa-calendar govuk-!-margin-right-1 civicone-icon-grey" aria-hidden="true"></i>
                                    <?= date('M d, Y \a\t g:i A', strtotime($app['shift_start'])) ?>
                                    <a href="<?= $basePath ?>/volunteering/ics/<?= $app['id'] ?>" class="govuk-link govuk-!-margin-left-2">
                                        Add to Calendar
                                    </a>
                                </p>
                            <?php endif; ?>

                            <p class="govuk-hint govuk-!-margin-bottom-0">
                                Applied on <?= date('M j, Y', strtotime($app['created_at'])) ?>
                            </p>
                        </div>
                        <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                            <?php
                            $statusTagClass = match($app['status']) {
                                'approved' => 'govuk-tag--green',
                                'declined' => 'govuk-tag--red',
                                default => 'govuk-tag--blue'
                            };
                            ?>
                            <strong class="govuk-tag <?= $statusTagClass ?>">
                                <?= ucfirst($app['status']) ?>
                            </strong>

                            <?php if ($app['status'] == 'approved'): ?>
                                <div class="govuk-!-margin-top-3">
                                    <button type="button"
                                            onclick="openLogModal(<?= $app['organization_id'] ?>, <?= $app['opportunity_id'] ?>, '<?= htmlspecialchars($app['org_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($app['opp_title'], ENT_QUOTES) ?>')"
                                            class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0"
                                            data-module="govuk-button">
                                        <i class="fa-solid fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                                        Log Hours
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </main>
</div>

<!-- Log Hours Modal -->
<div id="logHoursModal" class="govuk-!-display-none civicone-modal-overlay" role="dialog" aria-labelledby="modal-title" aria-modal="true">
    <div onclick="closeLogModal()" class="civicone-modal-backdrop"></div>
    <div class="civicone-modal-dialog">
        <div class="govuk-!-padding-4 civicone-modal-header-blue">
            <h3 id="modal-title" class="govuk-heading-m civicone-modal-title">
                <i class="fa-solid fa-clock govuk-!-margin-right-2" aria-hidden="true"></i>
                Log Volunteer Hours
            </h3>
        </div>

        <form action="<?= $basePath ?>/volunteering/log-hours" method="POST" class="govuk-!-padding-4">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="org_id" id="log_org_id">
            <input type="hidden" name="opp_id" id="log_opp_id">

            <div class="govuk-inset-text govuk-!-margin-top-0">
                <p class="govuk-body-s govuk-!-margin-bottom-1"><strong>Organization:</strong> <span id="log_org_name"></span></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0"><strong>Role:</strong> <span id="log_opp_title"></span></p>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="log_date">Date</label>
                <input type="date" name="date" id="log_date" required value="<?= date('Y-m-d') ?>" class="govuk-input govuk-input--width-10">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="log_hours">Hours Worked</label>
                <input type="number" step="0.5" name="hours" id="log_hours" required placeholder="e.g. 2.5" class="govuk-input govuk-input--width-5">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="log_description">
                    Description
                    <span class="govuk-hint">Optional - briefly describe what you did</span>
                </label>
                <textarea name="description" id="log_description" rows="3" class="govuk-textarea"></textarea>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                    Submit Hours
                </button>
                <button type="button" onclick="closeLogModal()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openLogModal(orgId, oppId, orgName, oppTitle) {
    document.getElementById('log_org_id').value = orgId;
    document.getElementById('log_opp_id').value = oppId;
    document.getElementById('log_org_name').textContent = orgName;
    document.getElementById('log_opp_title').textContent = oppTitle;
    document.getElementById('logHoursModal').classList.remove('govuk-!-display-none');
    document.body.style.overflow = 'hidden';
}

function closeLogModal() {
    document.getElementById('logHoursModal').classList.add('govuk-!-display-none');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLogModal();
});
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
