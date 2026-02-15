<?php
/**
 * Admin Newsletter Subscribers - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Env;

$basePath = TenantContext::getBasePath();

$stats = $stats ?? ['total' => 0, 'active' => 0, 'pending' => 0, 'unsubscribed' => 0];
$subscribers = $subscribers ?? [];
$currentStatus = $currentStatus ?? null;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;

// Admin header configuration
$adminPageTitle = 'Subscribers';
$adminPageSubtitle = 'Newsletters';
$adminPageIcon = 'fa-address-book';

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
            <i class="fa-solid fa-address-book"></i>
            Newsletter Subscribers
        </h1>
        <p class="admin-page-subtitle">Manage your newsletter mailing list</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/subscribers/export" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-download"></i>
            Export
        </a>
        <button onclick="document.getElementById('import-modal').style.display='flex'" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-upload"></i>
            Import
        </button>
        <button onclick="document.getElementById('add-modal').style.display='flex'" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Add Subscriber
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <a href="<?= $basePath ?>/admin-legacy/newsletters/subscribers" class="admin-stat-card admin-stat-indigo <?= !$currentStatus ? 'active' : '' ?>">
        <div class="admin-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="admin-stat-label">Total Subscribers</div>
        </div>
    </a>
    <a href="?status=active" class="admin-stat-card admin-stat-green <?= $currentStatus === 'active' ? 'active' : '' ?>">
        <div class="admin-stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['active'] ?? 0) ?></div>
            <div class="admin-stat-label">Active</div>
        </div>
    </a>
    <a href="?status=pending" class="admin-stat-card admin-stat-orange <?= $currentStatus === 'pending' ? 'active' : '' ?>">
        <div class="admin-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
            <div class="admin-stat-label">Pending</div>
        </div>
    </a>
    <a href="?status=unsubscribed" class="admin-stat-card admin-stat-red <?= $currentStatus === 'unsubscribed' ? 'active' : '' ?>">
        <div class="admin-stat-icon"><i class="fa-solid fa-times-circle"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['unsubscribed'] ?? 0) ?></div>
            <div class="admin-stat-label">Unsubscribed</div>
        </div>
    </a>
</div>

<!-- Sync Members Card -->
<div class="admin-glass-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-body">
        <div class="sync-members-row">
            <div class="sync-members-info">
                <div class="sync-members-icon">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <div class="sync-members-title">Sync Platform Members</div>
                    <div class="sync-members-desc">Add all existing platform members to your subscriber list</div>
                </div>
            </div>
            <form action="<?= $basePath ?>/admin-legacy/newsletters/subscribers/sync" method="POST" style="margin: 0;">
                <?= Csrf::input() ?>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-sync"></i> Sync Now
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Subscribers Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-amber">
            <i class="fa-solid fa-address-book"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">
                <?= $currentStatus ? ucfirst($currentStatus) . ' Subscribers' : 'All Subscribers' ?>
            </h3>
            <p class="admin-card-subtitle"><?= count($subscribers) ?> found</p>
        </div>
        <div class="admin-filter-pills">
            <a href="<?= $basePath ?>/admin-legacy/newsletters/subscribers" class="admin-filter-pill <?= !$currentStatus ? 'active' : '' ?>">All</a>
            <a href="?status=active" class="admin-filter-pill <?= $currentStatus === 'active' ? 'active-green' : '' ?>">Active</a>
            <a href="?status=pending" class="admin-filter-pill <?= $currentStatus === 'pending' ? 'active-orange' : '' ?>">Pending</a>
            <a href="?status=unsubscribed" class="admin-filter-pill <?= $currentStatus === 'unsubscribed' ? 'active-red' : '' ?>">Unsubscribed</a>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($subscribers)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th class="hide-tablet">Name</th>
                        <th class="hide-mobile">Status</th>
                        <th class="hide-tablet">Source</th>
                        <th class="hide-mobile">Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $sub): ?>
                    <?php
                    $statusStyles = [
                        'active' => ['color' => '#22c55e', 'icon' => 'fa-check-circle'],
                        'pending' => ['color' => '#f59e0b', 'icon' => 'fa-clock'],
                        'unsubscribed' => ['color' => '#ef4444', 'icon' => 'fa-times-circle']
                    ];
                    $style = $statusStyles[$sub['status']] ?? $statusStyles['pending'];

                    $name = trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? ''));
                    if (!$name && $sub['user_id']) {
                        $name = trim(($sub['member_first_name'] ?? '') . ' ' . ($sub['member_last_name'] ?? ''));
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="subscriber-cell">
                                <div class="subscriber-avatar">
                                    <?= strtoupper(substr($sub['email'], 0, 1)) ?>
                                </div>
                                <div class="subscriber-info">
                                    <div class="subscriber-email"><?= htmlspecialchars($sub['email']) ?></div>
                                    <?php if ($sub['user_id']): ?>
                                        <span class="subscriber-member-badge">Member</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="hide-tablet">
                            <span class="subscriber-name"><?= htmlspecialchars($name ?: '—') ?></span>
                        </td>
                        <td class="hide-mobile">
                            <div class="subscriber-status" style="--status-color: <?= $style['color'] ?>;">
                                <i class="fa-solid <?= $style['icon'] ?>"></i>
                                <?= ucfirst($sub['status']) ?>
                            </div>
                        </td>
                        <td class="hide-tablet">
                            <span class="subscriber-source"><?= str_replace('_', ' ', ucfirst($sub['source'] ?? 'signup')) ?></span>
                        </td>
                        <td class="hide-mobile">
                            <span class="subscriber-date"><?= date('M j, Y', strtotime($sub['created_at'])) ?></span>
                        </td>
                        <td style="text-align: right;">
                            <form action="<?= $basePath ?>/admin-legacy/newsletters/subscribers/delete" method="POST" style="display: inline;" onsubmit="return confirm('Remove this subscriber?');">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h3 class="admin-empty-title">No subscribers found</h3>
            <p class="admin-empty-text">Add subscribers manually or sync your existing platform members.</p>
            <button onclick="document.getElementById('add-modal').style.display='flex'" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i>
                Add Your First Subscriber
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?><?= $currentStatus ? '&status=' . $currentStatus : '' ?>" class="admin-page-btn <?= $i == $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Public Subscribe Link Card -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-body">
        <div class="subscribe-link-row">
            <div class="subscribe-link-icon">
                <i class="fa-solid fa-link"></i>
            </div>
            <div class="subscribe-link-content">
                <h3 class="subscribe-link-title">Public Subscribe Page</h3>
                <p class="subscribe-link-desc">Share this link to let people subscribe to your newsletter:</p>
                <div class="subscribe-link-input-row">
                    <input type="text" readonly
                           value="<?= htmlspecialchars((Env::get('APP_URL') ?? '') . $basePath . '/newsletter/subscribe') ?>"
                           class="subscribe-link-input" onclick="this.select()" id="subscribe-link">
                    <button onclick="copySubscribeLink()" class="admin-btn admin-btn-primary">
                        <i class="fa-solid fa-copy"></i> Copy
                    </button>
                    <a href="<?= $basePath ?>/newsletter/subscribe" target="_blank" class="admin-btn admin-btn-secondary">
                        <i class="fa-solid fa-external-link"></i> Preview
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Subscriber Modal -->
<div id="add-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <div class="admin-modal-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div>
                <h3 class="admin-modal-title">Add Subscriber</h3>
                <p class="admin-modal-subtitle">Add a new person to your mailing list</p>
            </div>
        </div>

        <form action="<?= $basePath ?>/admin-legacy/newsletters/subscribers/add" method="POST">
            <?= Csrf::input() ?>

            <div class="admin-form-group">
                <label class="admin-label">Email Address *</label>
                <input type="email" name="email" required class="admin-input" placeholder="email@example.com">
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">First Name</label>
                    <input type="text" name="first_name" class="admin-input" placeholder="John">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Last Name</label>
                    <input type="text" name="last_name" class="admin-input" placeholder="Doe">
                </div>
            </div>

            <div class="admin-modal-actions">
                <button type="button" onclick="document.getElementById('add-modal').style.display='none'" class="admin-btn admin-btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-plus"></i> Add Subscriber
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="import-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <div class="admin-modal-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fa-solid fa-file-import"></i>
            </div>
            <div>
                <h3 class="admin-modal-title">Import Subscribers</h3>
                <p class="admin-modal-subtitle">Upload a CSV file to bulk import</p>
            </div>
        </div>

        <form action="<?= $basePath ?>/admin-legacy/newsletters/subscribers/import" method="POST" enctype="multipart/form-data">
            <?= Csrf::input() ?>

            <div class="admin-form-group">
                <label class="admin-label">CSV File</label>
                <div class="admin-file-upload">
                    <input type="file" name="csv_file" accept=".csv" required id="csv-file-input">
                    <label for="csv-file-input">
                        <div class="admin-file-upload-icon">
                            <i class="fa-solid fa-cloud-upload"></i>
                        </div>
                        <div class="admin-file-upload-text">Click to upload</div>
                        <div class="admin-file-upload-hint">or drag and drop</div>
                    </label>
                </div>
            </div>

            <div class="admin-info-box">
                <div class="admin-info-box-title">
                    <i class="fa-solid fa-info-circle"></i> CSV Format
                </div>
                <div class="admin-info-box-code">email, first_name, last_name</div>
                <div class="admin-info-box-hint">First row should be the header</div>
            </div>

            <div class="admin-modal-actions">
                <button type="button" onclick="document.getElementById('import-modal').style.display='none'" class="admin-btn admin-btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-upload"></i> Import
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function copySubscribeLink() {
    const input = document.getElementById('subscribe-link');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Link copied to clipboard!');
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('add-modal').style.display = 'none';
        document.getElementById('import-modal').style.display = 'none';
    }
});

['add-modal', 'import-modal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>

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
    text-decoration: none;
    transition: all 0.2s;
}

.admin-stat-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    transform: translateY(-2px);
}

.admin-stat-card.active {
    border-color: var(--stat-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--stat-color) 20%, transparent);
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

.admin-stat-indigo { --stat-color: #6366f1; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-orange { --stat-color: #f59e0b; }
.admin-stat-red { --stat-color: #ef4444; }

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

/* Sync Members */
.sync-members-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.sync-members-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.sync-members-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.sync-members-title {
    font-weight: 700;
    color: #fff;
    font-size: 1rem;
}

.sync-members-desc {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

/* Filter Pills */
.admin-filter-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-left: auto;
}

.admin-filter-pill {
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.2s;
}

.admin-filter-pill:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.admin-filter-pill.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
}

.admin-filter-pill.active-green {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border-color: transparent;
}

.admin-filter-pill.active-orange {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-color: transparent;
}

.admin-filter-pill.active-red {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-color: transparent;
}

/* Card Header Icon Amber */
.admin-card-header-icon-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

/* Subscriber Cell */
.subscriber-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.subscriber-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.subscriber-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.subscriber-email {
    font-weight: 600;
    color: #fff;
}

.subscriber-member-badge {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.4);
    color: #60a5fa;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 600;
}

.subscriber-name {
    color: rgba(255, 255, 255, 0.7);
}

.subscriber-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: color-mix(in srgb, var(--status-color) 15%, transparent);
    color: var(--status-color);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.subscriber-status i {
    font-size: 0.65rem;
}

.subscriber-source {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    text-transform: capitalize;
}

.subscriber-date {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Subscribe Link */
.subscribe-link-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.subscribe-link-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.subscribe-link-content {
    flex: 1;
}

.subscribe-link-title {
    font-weight: 700;
    color: #fff;
    font-size: 1.1rem;
    margin: 0 0 0.5rem 0;
}

.subscribe-link-desc {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    margin: 0 0 1rem 0;
}

.subscribe-link-input-row {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.subscribe-link-input {
    flex: 1;
    min-width: 280px;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: #a5b4fc;
}

.subscribe-link-input:focus {
    outline: none;
    border-color: #6366f1;
}

/* Modal */
.admin-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.admin-modal-content {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 2rem;
    border-radius: 16px;
    max-width: 480px;
    width: 90%;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.admin-modal-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.admin-modal-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.admin-modal-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
}

.admin-modal-subtitle {
    margin: 4px 0 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.admin-modal-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

/* Form Elements */
.admin-form-group {
    margin-bottom: 1.25rem;
}

.admin-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.admin-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.admin-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
}

.admin-input:focus {
    outline: none;
    border-color: #6366f1;
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* File Upload */
.admin-file-upload {
    border: 2px dashed rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    text-align: center;
    background: rgba(15, 23, 42, 0.5);
    transition: all 0.2s;
}

.admin-file-upload:hover {
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(99, 102, 241, 0.05);
}

.admin-file-upload input {
    display: none;
}

.admin-file-upload label {
    display: block;
    padding: 2rem;
    cursor: pointer;
}

.admin-file-upload-icon {
    width: 48px;
    height: 48px;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    color: #818cf8;
    font-size: 1.25rem;
}

.admin-file-upload-text {
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.25rem;
}

.admin-file-upload-hint {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Info Box */
.admin-info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    padding: 1rem;
}

.admin-info-box-title {
    font-weight: 600;
    color: #60a5fa;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-info-box-code {
    color: #a5b4fc;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
}

.admin-info-box-hint {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin-top: 0.5rem;
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
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
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

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
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
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
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

    .admin-card-header {
        flex-wrap: wrap;
    }

    .admin-filter-pills {
        width: 100%;
        margin-left: 0;
        margin-top: 1rem;
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

    .admin-page-header-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .admin-form-row {
        grid-template-columns: 1fr;
    }

    .subscribe-link-row {
        flex-direction: column;
    }

    .subscribe-link-input-row {
        flex-direction: column;
    }

    .subscribe-link-input {
        min-width: 100%;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
