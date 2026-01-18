<?php
// CivicOne View: Volunteering Dashboard - MadeOpen Style
$hTitle = 'Volunteering Dashboard';
$hSubtitle = 'Manage your organization and opportunities';
$hType = 'Dashboard';

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Action Bar -->
<div class="civic-action-bar" style="margin-bottom: 32px;">
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
                    <span class="dashicons dashicons-megaphone" style="font-size: 48px;"></span>
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
            <span class="dashicons dashicons-building" style="font-size: 48px;"></span>
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

<style>
    /* Action Bar */
    .civic-action-bar {
        display: flex;
        gap: 12px;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    /* Dashboard Sections */
    .civic-dashboard-section {
        margin-bottom: 40px;
    }

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

    /* Organization Grid */
    .civic-org-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .civic-org-card {
        background: var(--civic-bg-card);
        border: 1px solid var(--civic-border);
        border-radius: 12px;
        padding: 24px;
        text-align: center;
    }

    .civic-org-icon {
        width: 56px;
        height: 56px;
        background: var(--civic-brand);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        color: white;
    }

    .civic-org-icon .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
    }

    .civic-org-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 8px 0;
    }

    .civic-org-description {
        color: var(--civic-text-muted);
        font-size: 14px;
        margin: 0 0 16px 0;
        line-height: 1.5;
    }

    /* Table Styles */
    .civic-table-card {
        background: var(--civic-bg-card);
        border: 1px solid var(--civic-border);
        border-radius: 12px;
        overflow: hidden;
    }

    .civic-table {
        width: 100%;
        border-collapse: collapse;
    }

    .civic-table th,
    .civic-table td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid var(--civic-border);
    }

    .civic-table th {
        background: var(--civic-bg-page);
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--civic-text-muted);
    }

    .civic-table tbody tr:last-child td {
        border-bottom: none;
    }

    .civic-table tbody tr:hover {
        background: var(--civic-bg-page);
    }

    /* Status Badges */
    .civic-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
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

    /* Action Buttons */
    .civic-action-buttons {
        display: flex;
        gap: 8px;
    }

    .civic-btn--success {
        background: #ECFDF5;
        color: #047857;
        border: 1px solid #A7F3D0;
    }

    .civic-btn--success:hover {
        background: #D1FAE5;
    }

    .civic-btn--danger {
        background: #FEF2F2;
        color: #B91C1C;
        border: 1px solid #FECACA;
    }

    .civic-btn--danger:hover {
        background: #FEE2E2;
    }

    /* Opportunity List */
    .civic-opp-list {
        background: var(--civic-bg-card);
        border: 1px solid var(--civic-border);
        border-radius: 12px;
        overflow: hidden;
    }

    .civic-opp-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid var(--civic-border);
        gap: 16px;
        flex-wrap: wrap;
    }

    .civic-opp-item:last-child {
        border-bottom: none;
    }

    .civic-opp-item:hover {
        background: var(--civic-bg-page);
    }

    .civic-opp-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 4px 0;
    }

    .civic-opp-title a {
        color: var(--civic-text-main);
        text-decoration: none;
    }

    .civic-opp-title a:hover {
        color: var(--civic-brand);
    }

    .civic-opp-meta {
        color: var(--civic-text-muted);
        font-size: 14px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .civic-opp-meta .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }

    .civic-opp-date {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .civic-text-muted {
        color: var(--civic-text-muted);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .civic-table-card {
            overflow-x: auto;
        }

        .civic-table {
            min-width: 600px;
        }

        .civic-opp-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .civic-opp-actions {
            width: 100%;
        }

        .civic-opp-actions .civic-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
