<?php
/**
 * Template G: Account Hub - Volunteering Dashboard
 *
 * Purpose: Manage organizations, opportunities, and applicants
 * Features: Organization cards, applicant management, opportunity listings
 * WCAG 2.1 AA: Semantic HTML, ARIA labels, keyboard navigation
 */

// CivicOne View: Volunteering Dashboard - MadeOpen Style
$hTitle = 'Volunteering Dashboard';
$hSubtitle = 'Manage your organization and opportunities';
$hType = 'Dashboard';

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/purged/civicone-volunteering-dashboard.min.css">


<!-- Action Bar -->
<div class="civic-action-bar civic-action-bar--spaced">
    <a href="<?= $basePath ?>/volunteering" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Opportunities
    </a>
    <?php if (empty($myOrgs)): ?>
        <a href="<?= $basePath ?>/volunteering/import-from-profile" class="civic-btn"
           onclick="return confirm('Create organization from your profile details?');">
            <span class="dashicons dashicons-plus" aria-hidden="true"></span>
            Register Organization
        </a>
    <?php else: ?>
        <a href="<?= $basePath ?>/volunteering/opp/create" class="civic-btn">
            <span class="dashicons dashicons-plus" aria-hidden="true"></span>
            Post Opportunity
        </a>
    <?php endif; ?>
</div>

<!-- Organizations Section -->
<?php if (!empty($myOrgs)): ?>
    <section class="civic-dashboard-section">
        <h2 class="civic-section-title">
            <span class="dashicons dashicons-building" aria-hidden="true"></span>
            My Organizations
        </h2>
        <div class="civic-org-grid">
            <?php foreach ($myOrgs as $org): ?>
                <article class="civic-org-card">
                    <div class="civic-org-icon">
                        <span class="dashicons dashicons-building" aria-hidden="true"></span>
                    </div>
                    <h3 class="civic-org-name"><?= htmlspecialchars($org['name']) ?></h3>
                    <p class="civic-org-description">
                        <?= htmlspecialchars(substr($org['description'] ?? '', 0, 100)) ?><?= strlen($org['description'] ?? '') > 100 ? '...' : '' ?>
                    </p>
                    <a href="<?= $basePath ?>/volunteering/org/edit/<?= $org['id'] ?>" class="civic-btn civic-btn--outline civic-btn--sm">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                        Edit Organization
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- Applicants Section -->
<?php if (!empty($myApplications)): ?>
    <section class="civic-dashboard-section">
        <h2 class="civic-section-title">
            <span class="dashicons dashicons-groups" aria-hidden="true"></span>
            Applicants
        </h2>
        <div class="civic-table-card">
            <table class="civic-table">
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Applicant</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myApplications as $app): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($app['opp_title']) ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($app['applicant_name'] ?? 'User #' . $app['user_id']) ?>
                            </td>
                            <td>
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
                            </td>
                            <td>
                                <?php if ($app['status'] === 'pending'): ?>
                                    <form action="<?= $basePath ?>/volunteering/application/update" method="POST" class="civic-action-buttons">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                        <button type="submit" name="status" value="approved" class="civic-btn civic-btn--sm civic-btn--success">
                                            Approve
                                        </button>
                                        <button type="submit" name="status" value="declined" class="civic-btn civic-btn--sm civic-btn--danger">
                                            Decline
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="civic-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<!-- Manage Opportunities Section -->
<?php if (!empty($myOrgs)): ?>
    <section class="civic-dashboard-section">
        <h2 class="civic-section-title">
            <span class="dashicons dashicons-heart" aria-hidden="true"></span>
            Manage Opportunities
        </h2>

        <?php if (empty($myOpps)): ?>
            <div class="civic-empty-state">
                <div class="civic-empty-state-icon">
                    <span class="dashicons dashicons-megaphone civic-empty-icon-lg" aria-hidden="true"></span>
                </div>
                <h3 class="civic-empty-state-title">No opportunities yet</h3>
                <p class="civic-empty-state-text">You haven't posted any volunteer opportunities yet.</p>
                <a href="<?= $basePath ?>/volunteering/opp/create" class="civic-btn">
                    <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                    Post Your First Opportunity
                </a>
            </div>
        <?php else: ?>
            <div class="civic-opp-list">
                <?php foreach ($myOpps as $opp): ?>
                    <article class="civic-opp-item">
                        <div class="civic-opp-info">
                            <h3 class="civic-opp-title">
                                <a href="<?= $basePath ?>/volunteering/<?= $opp['id'] ?>">
                                    <?= htmlspecialchars($opp['title']) ?>
                                </a>
                            </h3>
                            <p class="civic-opp-meta">
                                <span class="dashicons dashicons-building" aria-hidden="true"></span>
                                <?= htmlspecialchars($opp['org_name'] ?? '') ?>
                                <?php if (!empty($opp['start_date'])): ?>
                                    <span class="civic-opp-date">
                                        <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                        <?= date('M j, Y', strtotime($opp['start_date'])) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="civic-opp-actions">
                            <a href="<?= $basePath ?>/volunteering/opp/edit/<?= $opp['id'] ?>" class="civic-btn civic-btn--outline civic-btn--sm">
                                <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                Edit
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Empty State for New Users -->
<?php if (empty($myOrgs)): ?>
    <div class="civic-empty-state">
        <div class="civic-empty-state-icon">
            <span class="dashicons dashicons-building civic-empty-icon-lg" aria-hidden="true"></span>
        </div>
        <h3 class="civic-empty-state-title">Get Started</h3>
        <p class="civic-empty-state-text">
            Register your organization to start posting volunteer opportunities and connecting with community members.
        </p>
        <a href="<?= $basePath ?>/volunteering/import-from-profile" class="civic-btn"
           onclick="return confirm('Create organization from your profile details?');">
            <span class="dashicons dashicons-plus" aria-hidden="true"></span>
            Register Organization
        </a>
    </div>
<?php endif; ?>


<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
