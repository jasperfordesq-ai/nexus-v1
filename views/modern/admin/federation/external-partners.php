<?php
/**
 * External Federation Partners - List View
 * Admin interface for managing external federation server connections
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'External Partners';
$adminPageSubtitle = 'External Federation Connections';
$adminPageIcon = 'fa-globe';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../partials/admin-header.php';

$partners = $partners ?? [];

// Status badge colors
$statusColors = [
    'pending' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
    'active' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'color' => '#10b981', 'icon' => 'fa-circle-check'],
    'suspended' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-pause'],
    'failed' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-triangle-exclamation'],
];
?>

<style>
.external-partners-dashboard {
    display: grid;
    gap: 1.5rem;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.page-header h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-text, #fff);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.add-partner-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.add-partner-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

/* Partner Cards */
.partners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}

.partner-card {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.2s;
}

.partner-card:hover {
    border-color: rgba(139, 92, 246, 0.3);
    transform: translateY(-2px);
}

.partner-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.partner-info h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin: 0 0 0.25rem;
}

.partner-url {
    font-size: 0.85rem;
    color: #8b5cf6;
    font-family: monospace;
    word-break: break-all;
}

.partner-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.partner-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.meta-label {
    font-size: 0.75rem;
    color: var(--admin-text-secondary, #94a3b8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.meta-value {
    font-size: 0.9rem;
    color: var(--admin-text, #fff);
}

.partner-permissions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 1rem;
}

.perm-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    border-radius: 4px;
}

.perm-badge.disabled {
    background: rgba(100, 116, 139, 0.15);
    color: #64748b;
    text-decoration: line-through;
}

.partner-actions {
    display: flex;
    gap: 0.5rem;
}

.partner-action-btn {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.partner-action-btn.view {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.partner-action-btn.test {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.partner-action-btn:hover {
    transform: translateY(-1px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
    color: #8b5cf6;
}

.empty-state h3 {
    color: var(--admin-text, #fff);
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
}

.empty-state p {
    color: var(--admin-text-secondary, #94a3b8);
    margin: 0 0 1.5rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Info Box */
.info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}

.info-box h4 {
    color: #3b82f6;
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box p {
    color: var(--admin-text-secondary, #94a3b8);
    font-size: 0.9rem;
    margin: 0;
    line-height: 1.5;
}

/* Flash Messages */
.flash-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.flash-message.success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.flash-message.error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .partners-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php if ($flashSuccess): ?>
<div class="flash-message success">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="flash-message error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<div class="external-partners-dashboard">
    <div class="info-box">
        <h4><i class="fa-solid fa-circle-info"></i> External Federation Partners</h4>
        <p>
            Connect to timebanks running on different servers. Add their API URL and credentials to enable
            cross-platform member search, messaging, and time credit transfers.
        </p>
    </div>

    <div class="page-header">
        <h2><i class="fa-solid fa-globe"></i> External Partners</h2>
        <a href="/admin/federation/external-partners/create" class="add-partner-btn">
            <i class="fa-solid fa-plus"></i> Add External Partner
        </a>
    </div>

    <?php if (empty($partners)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-globe"></i>
        <h3>No External Partners Yet</h3>
        <p>
            Connect to other timebank platforms to expand your federation network.
            You'll need their API URL and an API key they provide.
        </p>
        <a href="/admin/federation/external-partners/create" class="add-partner-btn">
            <i class="fa-solid fa-plus"></i> Add Your First Partner
        </a>
    </div>
    <?php else: ?>
    <div class="partners-grid">
        <?php foreach ($partners as $partner): ?>
        <?php $status = $statusColors[$partner['status']] ?? $statusColors['pending']; ?>
        <div class="partner-card">
            <div class="partner-header">
                <div class="partner-info">
                    <h3><?= htmlspecialchars($partner['name']) ?></h3>
                    <div class="partner-url"><?= htmlspecialchars($partner['base_url']) ?></div>
                </div>
                <span class="partner-status" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>;">
                    <i class="fa-solid <?= $status['icon'] ?>"></i>
                    <?= ucfirst($partner['status']) ?>
                </span>
            </div>

            <div class="partner-meta">
                <div class="meta-item">
                    <span class="meta-label">Auth Method</span>
                    <span class="meta-value"><?= strtoupper($partner['auth_method'] ?? 'API Key') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Last Verified</span>
                    <span class="meta-value">
                        <?= $partner['verified_at'] ? date('M j, Y', strtotime($partner['verified_at'])) : 'Never' ?>
                    </span>
                </div>
                <?php if ($partner['partner_name']): ?>
                <div class="meta-item">
                    <span class="meta-label">Partner Name</span>
                    <span class="meta-value"><?= htmlspecialchars($partner['partner_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($partner['partner_version']): ?>
                <div class="meta-item">
                    <span class="meta-label">API Version</span>
                    <span class="meta-value"><?= htmlspecialchars($partner['partner_version']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="partner-permissions">
                <span class="perm-badge <?= $partner['allow_member_search'] ? '' : 'disabled' ?>">Members</span>
                <span class="perm-badge <?= $partner['allow_listing_search'] ? '' : 'disabled' ?>">Listings</span>
                <span class="perm-badge <?= $partner['allow_messaging'] ? '' : 'disabled' ?>">Messaging</span>
                <span class="perm-badge <?= $partner['allow_transactions'] ? '' : 'disabled' ?>">Transactions</span>
                <span class="perm-badge <?= $partner['allow_events'] ? '' : 'disabled' ?>">Events</span>
                <span class="perm-badge <?= $partner['allow_groups'] ? '' : 'disabled' ?>">Groups</span>
            </div>

            <div class="partner-actions">
                <a href="/admin/federation/external-partners/<?= $partner['id'] ?>" class="partner-action-btn view">
                    <i class="fa-solid fa-eye"></i> View Details
                </a>
                <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/test" style="flex:1;">
                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit" class="partner-action-btn test" style="width:100%;">
                        <i class="fa-solid fa-plug"></i> Test
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
