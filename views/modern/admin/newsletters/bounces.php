<?php
/**
 * Admin Newsletter Bounces - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$stats = $stats ?? ['hard' => 0, 'soft' => 0, 'complaint' => 0];
$items = $items ?? [];
$currentTab = $currentTab ?? 'suppressed';
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;

// For backward compatibility, also support old variable names
$suppressionList = $suppressionList ?? ($currentTab === 'suppressed' ? $items : []);
$recentBounces = $recentBounces ?? ($currentTab === 'recent' ? $items : []);
$suppressionCount = $suppressionCount ?? $total;

// Admin header configuration
$adminPageTitle = 'Bounces';
$adminPageSubtitle = 'Newsletters';
$adminPageIcon = 'fa-shield-halved';

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
            <i class="fa-solid fa-shield-halved"></i>
            Bounce Management
        </h1>
        <p class="admin-page-subtitle">Monitor bounces and manage email suppression list</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-line"></i>
            Analytics
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-red">
        <div class="admin-stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['hard'] ?? 0) ?></div>
            <div class="admin-stat-label">Hard Bounces</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['soft'] ?? 0) ?></div>
            <div class="admin-stat-label">Soft Bounces</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon"><i class="fa-solid fa-flag"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['complaint'] ?? 0) ?></div>
            <div class="admin-stat-label">Complaints</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-indigo">
        <div class="admin-stat-icon"><i class="fa-solid fa-ban"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($suppressionCount) ?></div>
            <div class="admin-stat-label">Suppressed</div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="admin-tabs" style="margin-bottom: 1.5rem;">
    <a href="?tab=suppressed" class="admin-tab <?= $currentTab === 'suppressed' ? 'active' : '' ?>">
        <i class="fa-solid fa-ban"></i> Suppression List
    </a>
    <a href="?tab=recent" class="admin-tab <?= $currentTab === 'recent' ? 'active' : '' ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> Recent Bounces
    </a>
</div>

<!-- Content Based on Tab -->
<?php if ($currentTab === 'suppressed'): ?>
<!-- Suppression List -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Suppression List</h3>
            <p class="admin-card-subtitle"><?= number_format($total) ?> emails</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($items)): ?>
        <div class="bounce-table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th class="hide-mobile">Reason</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <?php
                    $reasonColors = [
                        'hard_bounce' => '#ef4444',
                        'repeated_soft_bounce' => '#f59e0b',
                        'complaint' => '#ec4899',
                        'unsubscribe' => '#6366f1',
                        'manual' => '#64748b',
                    ];
                    $reasonColor = $reasonColors[$item['reason'] ?? 'manual'] ?? '#64748b';
                    ?>
                    <tr>
                        <td>
                            <div class="bounce-email"><?= htmlspecialchars($item['email']) ?></div>
                            <div class="bounce-date"><?= date('M j, Y', strtotime($item['suppressed_at'] ?? $item['created_at'] ?? 'now')) ?></div>
                        </td>
                        <td class="hide-mobile">
                            <span class="bounce-reason" style="--reason-color: <?= $reasonColor ?>;">
                                <?= ucwords(str_replace('_', ' ', $item['reason'] ?? 'Unknown')) ?>
                            </span>
                            <?php if (($item['bounce_count'] ?? 1) > 1): ?>
                            <span class="bounce-count">(<?= $item['bounce_count'] ?>x)</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <form action="<?= $basePath ?>/admin-legacy/newsletters/bounces/unsuppress" method="POST" style="display: inline;">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="email" value="<?= htmlspecialchars($item['email']) ?>">
                                <button type="submit" onclick="return confirm('Remove from suppression list?')" class="bounce-restore-btn">
                                    <i class="fa-solid fa-rotate-left"></i> Restore
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="admin-pagination" style="padding: 1rem;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?tab=suppressed&page=<?= $i ?>" class="admin-page-btn <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="bounce-empty">
            <div class="bounce-empty-icon bounce-empty-icon-success">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 class="bounce-empty-title">No Suppressed Emails</h3>
            <p class="bounce-empty-text">Your email list is clean!</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<!-- Recent Bounces -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-amber">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Recent Bounces</h3>
            <p class="admin-card-subtitle">Latest delivery failures</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($items)): ?>
        <div class="bounce-table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th class="hide-mobile">Type</th>
                        <th class="hide-tablet">Newsletter</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $bounce): ?>
                    <?php
                    $typeConfig = [
                        'hard' => ['color' => '#ef4444', 'icon' => 'fa-circle-xmark'],
                        'soft' => ['color' => '#f59e0b', 'icon' => 'fa-triangle-exclamation'],
                        'complaint' => ['color' => '#ec4899', 'icon' => 'fa-flag'],
                    ];
                    $tc = $typeConfig[$bounce['bounce_type'] ?? 'hard'] ?? ['color' => '#64748b', 'icon' => 'fa-question'];
                    ?>
                    <tr>
                        <td>
                            <div class="bounce-email"><?= htmlspecialchars($bounce['email']) ?></div>
                            <div class="bounce-date"><?= date('M j, Y g:i A', strtotime($bounce['bounced_at'] ?? $bounce['created_at'] ?? 'now')) ?></div>
                        </td>
                        <td class="hide-mobile">
                            <span class="bounce-type" style="--type-color: <?= $tc['color'] ?>;">
                                <i class="fa-solid <?= $tc['icon'] ?>"></i>
                                <?= ucfirst($bounce['bounce_type'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td class="hide-tablet">
                            <?php if (!empty($bounce['newsletter_subject'])): ?>
                            <span class="bounce-newsletter"><?= htmlspecialchars(substr($bounce['newsletter_subject'], 0, 30)) ?><?= strlen($bounce['newsletter_subject']) > 30 ? '...' : '' ?></span>
                            <?php else: ?>
                            <span class="bounce-na">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="bounce-empty">
            <div class="bounce-empty-icon bounce-empty-icon-success">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
            <h3 class="bounce-empty-title">No Recent Bounces</h3>
            <p class="bounce-empty-text">All emails delivered successfully!</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Manual Suppression -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-red">
            <i class="fa-solid fa-user-slash"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Manually Suppress Email</h3>
            <p class="admin-card-subtitle">Add an email to the suppression list</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/newsletters/bounces/suppress" method="POST" class="suppress-form">
            <?= Csrf::input() ?>
            <input type="email" name="email" required placeholder="email@example.com" class="admin-input">
            <select name="reason" class="admin-select">
                <option value="manual">Manual Suppression</option>
                <option value="hard_bounce">Hard Bounce</option>
                <option value="complaint">Complaint</option>
                <option value="unsubscribe">Unsubscribe Request</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-plus"></i> Add to Suppression
            </button>
        </form>
    </div>
</div>

<!-- Info Box -->
<div class="admin-glass-card info-card" style="margin-top: 1.5rem;">
    <div class="info-card-header">
        <i class="fa-solid fa-info-circle"></i>
        About Bounce Types
    </div>
    <div class="info-card-grid">
        <div class="info-card-item">
            <strong>Hard Bounces:</strong> Permanent delivery failures (invalid email, domain doesn't exist). Automatically suppressed.
        </div>
        <div class="info-card-item">
            <strong>Soft Bounces:</strong> Temporary issues (mailbox full, server down). Suppressed after 3 occurrences.
        </div>
        <div class="info-card-item">
            <strong>Complaints:</strong> Recipient marked email as spam. Automatically suppressed and should not be re-added.
        </div>
    </div>
</div>

<style>
/* Tabs */
.admin-tabs {
    display: flex;
    gap: 0.5rem;
    background: rgba(15, 23, 42, 0.5);
    padding: 0.5rem;
    border-radius: 12px;
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.6);
    transition: all 0.2s;
}

.admin-tab:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.8);
}

.admin-tab.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

/* Pagination */
.admin-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.admin-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
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
    .admin-stats-grid { grid-template-columns: 1fr 1fr; }
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

.admin-stat-red { --stat-color: #ef4444; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-pink { --stat-color: #ec4899; }
.admin-stat-indigo { --stat-color: #6366f1; }

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

/* Two Column Layout */
.bounce-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .bounce-columns { grid-template-columns: 1fr; }
}

/* Card Header Icons */
.admin-card-header-icon-indigo {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.admin-card-header-icon-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.admin-card-header-icon-red {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

/* Bounce Table */
.bounce-table-scroll {
    max-height: 400px;
    overflow-y: auto;
}

.bounce-email {
    font-size: 0.9rem;
    color: #fff;
    font-weight: 500;
}

.bounce-date {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 2px;
}

.bounce-reason {
    display: inline-block;
    padding: 4px 10px;
    background: color-mix(in srgb, var(--reason-color) 15%, transparent);
    color: var(--reason-color);
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.bounce-count {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.75rem;
    margin-left: 4px;
}

.bounce-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: color-mix(in srgb, var(--type-color) 15%, transparent);
    color: var(--type-color);
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.bounce-newsletter {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

.bounce-na {
    color: rgba(255, 255, 255, 0.3);
}

.bounce-restore-btn {
    background: none;
    border: none;
    color: #818cf8;
    cursor: pointer;
    font-size: 0.8rem;
    padding: 4px 8px;
    transition: color 0.2s;
}

.bounce-restore-btn:hover {
    color: #a5b4fc;
}

/* Empty State */
.bounce-empty {
    padding: 3rem 1.5rem;
    text-align: center;
}

.bounce-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
}

.bounce-empty-icon-success {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.bounce-empty-title {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
}

.bounce-empty-text {
    margin: 0;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* Suppress Form */
.suppress-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.admin-input {
    flex: 1;
    min-width: 250px;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
}

.admin-input:focus {
    outline: none;
    border-color: #6366f1;
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.admin-select {
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
}

.admin-select:focus {
    outline: none;
    border-color: #6366f1;
}

.admin-select option {
    background: #1e293b;
    color: #fff;
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.05)) !important;
    border-color: rgba(59, 130, 246, 0.2) !important;
}

.info-card-header {
    font-size: 1rem;
    font-weight: 700;
    color: #60a5fa;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.info-card-item strong {
    color: #60a5fa;
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

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
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

/* Table Styles */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 0.875rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 0.875rem 1.25rem;
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

    .admin-page-header-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .suppress-form {
        flex-direction: column;
    }

    .admin-input,
    .admin-select {
        min-width: 100%;
    }

    .info-card-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
