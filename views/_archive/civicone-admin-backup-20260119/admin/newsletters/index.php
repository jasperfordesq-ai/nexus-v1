<?php
/**
 * Admin Newsletter Hub - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Calculate stats
$newsletters = $newsletters ?? [];
$totalNewsletters = count($newsletters);
$sentCount = 0;
$draftCount = 0;
$scheduledCount = 0;
foreach ($newsletters as $n) {
    if ($n['status'] === 'sent') $sentCount++;
    elseif ($n['status'] === 'draft') $draftCount++;
    elseif ($n['status'] === 'scheduled') $scheduledCount++;
}

// Admin header configuration
$adminPageTitle = 'Newsletters';
$adminPageSubtitle = 'Communications';
$adminPageIcon = 'fa-envelope';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon"><i class="fa-solid fa-check-circle"></i></div>
    <div class="admin-alert-content"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <div class="admin-alert-icon"><i class="fa-solid fa-times-circle"></i></div>
    <div class="admin-alert-content"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-envelope"></i>
            Newsletter Hub
        </h1>
        <p class="admin-page-subtitle">Create, manage, and send email campaigns</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/newsletters/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Create Newsletter
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon"><i class="fa-solid fa-envelope"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $totalNewsletters ?></div>
            <div class="admin-stat-label">Total Newsletters</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $sentCount ?></div>
            <div class="admin-stat-label">Sent</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon"><i class="fa-solid fa-file-lines"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $draftCount ?></div>
            <div class="admin-stat-label">Drafts</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $scheduledCount ?></div>
            <div class="admin-stat-label">Scheduled</div>
        </div>
    </div>
</div>

<!-- Quick Actions Card -->
<div class="admin-glass-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-body">
        <div class="admin-quick-actions">
            <a href="<?= $basePath ?>/admin-legacy/newsletters/analytics" class="admin-quick-action">
                <i class="fa-solid fa-chart-line"></i>
                <span>Analytics</span>
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/templates" class="admin-quick-action">
                <i class="fa-solid fa-palette"></i>
                <span>Templates</span>
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/segments" class="admin-quick-action">
                <i class="fa-solid fa-filter"></i>
                <span>Segments</span>
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/subscribers" class="admin-quick-action">
                <i class="fa-solid fa-address-book"></i>
                <span>Subscribers</span>
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/bounces" class="admin-quick-action">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Bounces</span>
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/diagnostics" class="admin-quick-action">
                <i class="fa-solid fa-wrench"></i>
                <span>Diagnostics</span>
            </a>
        </div>
    </div>
</div>

<!-- Newsletters Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-amber">
            <i class="fa-solid fa-inbox"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">All Newsletters</h3>
            <p class="admin-card-subtitle"><?= $totalNewsletters ?> campaigns</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($newsletters)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-envelope-open-text"></i>
            </div>
            <h3 class="admin-empty-title">No newsletters yet</h3>
            <p class="admin-empty-text">Create your first newsletter to start engaging with your community.</p>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i>
                Create Your First Newsletter
            </a>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Newsletter</th>
                        <th class="hide-mobile">Status</th>
                        <th class="hide-tablet">Audience</th>
                        <th class="hide-mobile" style="text-align: center;">Performance</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newsletters as $newsletter): ?>
                    <?php
                    $statusConfig = [
                        'draft' => ['color' => '#94a3b8', 'icon' => 'fa-file-lines'],
                        'scheduled' => ['color' => '#f59e0b', 'icon' => 'fa-clock'],
                        'sending' => ['color' => '#3b82f6', 'icon' => 'fa-paper-plane'],
                        'sent' => ['color' => '#22c55e', 'icon' => 'fa-check-circle'],
                        'failed' => ['color' => '#ef4444', 'icon' => 'fa-exclamation-circle']
                    ];
                    $sc = $statusConfig[$newsletter['status']] ?? $statusConfig['draft'];

                    $audienceLabels = [
                        'all_members' => 'Members',
                        'subscribers_only' => 'Subscribers',
                        'both' => 'All'
                    ];
                    $audienceLabel = $audienceLabels[$newsletter['target_audience'] ?? 'all_members'] ?? 'All';
                    ?>
                    <tr>
                        <td>
                            <div class="newsletter-cell">
                                <div class="newsletter-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                                <div class="newsletter-info">
                                    <div class="newsletter-subject"><?= htmlspecialchars($newsletter['subject']) ?></div>
                                    <div class="newsletter-meta">
                                        <?= date('M j, Y', strtotime($newsletter['created_at'])) ?>
                                        <?php if (!empty($newsletter['author_name'])): ?>
                                            &middot; <?= htmlspecialchars($newsletter['author_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($newsletter['ab_test_enabled'])): ?>
                                            <span class="newsletter-ab-badge">A/B</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <div class="newsletter-status" style="--status-color: <?= $sc['color'] ?>;">
                                <i class="fa-solid <?= $sc['icon'] ?>"></i>
                                <?= ucfirst($newsletter['status']) ?>
                            </div>
                            <?php if ($newsletter['status'] === 'scheduled' && !empty($newsletter['scheduled_at'])): ?>
                                <div class="newsletter-scheduled">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= date('M j, g:ia', strtotime($newsletter['scheduled_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet">
                            <span class="newsletter-audience"><?= $audienceLabel ?></span>
                            <?php if (!empty($newsletter['segment_id'])): ?>
                                <div class="newsletter-segment">
                                    <i class="fa-solid fa-filter"></i> Segmented
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile" style="text-align: center;">
                            <?php if ($newsletter['status'] === 'sent'): ?>
                                <?php
                                $totalSent = $newsletter['total_sent'] ?? 0;
                                $openRate = $totalSent > 0 ? round(($newsletter['unique_opens'] / $totalSent) * 100, 1) : 0;
                                $clickRate = $totalSent > 0 ? round(($newsletter['unique_clicks'] / $totalSent) * 100, 1) : 0;
                                ?>
                                <div class="newsletter-stats">
                                    <div class="newsletter-stat">
                                        <div class="newsletter-stat-value"><?= number_format($totalSent) ?></div>
                                        <div class="newsletter-stat-label">Sent</div>
                                    </div>
                                    <div class="newsletter-stat">
                                        <div class="newsletter-stat-value" style="color: #818cf8;"><?= $openRate ?>%</div>
                                        <div class="newsletter-stat-label">Opens</div>
                                    </div>
                                    <div class="newsletter-stat">
                                        <div class="newsletter-stat-value" style="color: #22c55e;"><?= $clickRate ?>%</div>
                                        <div class="newsletter-stat-label">Clicks</div>
                                    </div>
                                </div>
                            <?php elseif (!empty($newsletter['total_recipients'])): ?>
                                <span class="newsletter-queued"><?= number_format($newsletter['total_recipients']) ?> queued</span>
                            <?php else: ?>
                                <span class="newsletter-na">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <?php if ($newsletter['status'] !== 'sent'): ?>
                                    <a href="<?= $basePath ?>/admin-legacy/newsletters/edit/<?= $newsletter['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $basePath ?>/admin-legacy/newsletters/preview/<?= $newsletter['id'] ?>" target="_blank" class="admin-btn admin-btn-secondary admin-btn-sm" title="Preview">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if ($newsletter['status'] === 'sent'): ?>
                                    <a href="<?= $basePath ?>/admin-legacy/newsletters/stats/<?= $newsletter['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                        <i class="fa-solid fa-chart-bar"></i> Stats
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $basePath ?>/admin-legacy/newsletters/duplicate/<?= $newsletter['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Duplicate">
                                    <i class="fa-solid fa-copy"></i>
                                </a>
                                <?php if ($newsletter['status'] !== 'sent'): ?>
                                    <form action="<?= $basePath ?>/admin-legacy/newsletters/delete" method="POST" style="display: inline;" onsubmit="return confirm('Delete this newsletter?');">
                                        <?= Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= $newsletter['id'] ?>">
                                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if (($totalPages ?? 1) > 1): ?>
<div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="admin-page-btn <?= $i == ($page ?? 1) ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .admin-stats-grid { grid-template-columns: 1fr; }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-orange { --stat-color: #f59e0b; }

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
}

.admin-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
}

.admin-stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Quick Actions */
.admin-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.admin-quick-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.admin-quick-action:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.4);
    color: #fff;
}

.admin-quick-action i {
    color: #818cf8;
}

/* Card Header Icon Amber */
.admin-card-header-icon-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-alert-icon {
    font-size: 1.25rem;
}

/* Newsletter Cell */
.newsletter-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.newsletter-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.newsletter-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.newsletter-subject {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.newsletter-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.newsletter-ab-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 6px;
    background: rgba(245, 158, 11, 0.2);
    border: 1px solid rgba(245, 158, 11, 0.4);
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #f59e0b;
}

/* Newsletter Status */
.newsletter-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: color-mix(in srgb, var(--status-color) 15%, transparent);
    color: var(--status-color);
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.newsletter-status i {
    font-size: 0.7rem;
}

.newsletter-scheduled {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 4px;
}

/* Audience */
.newsletter-audience {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.newsletter-segment {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 4px;
}

/* Newsletter Stats */
.newsletter-stats {
    display: flex;
    justify-content: center;
    gap: 1.25rem;
}

.newsletter-stat {
    text-align: center;
}

.newsletter-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
}

.newsletter-stat-label {
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
}

.newsletter-queued {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

.newsletter-na {
    color: rgba(255, 255, 255, 0.3);
}

/* Action Buttons */
.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #f59e0b;
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Pagination */
.admin-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.admin-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 0.75rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.admin-page-btn:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
    color: #fff;
}

.admin-page-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-action-buttons {
        flex-direction: column;
    }

    .admin-quick-actions {
        justify-content: center;
    }

    .newsletter-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
