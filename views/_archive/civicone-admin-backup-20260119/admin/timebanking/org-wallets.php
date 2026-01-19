<?php
/**
 * Admin Organization Wallets - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Organization Wallets';
$adminPageSubtitle = 'TimeBanking';
$adminPageIcon = 'fa-building-columns';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/timebanking" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Organization Wallets
        </h1>
        <p class="admin-page-subtitle">Manage organization time banking wallets</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/timebanking/create-org" class="admin-btn admin-btn-success">
            <i class="fa-solid fa-plus"></i> Create Organization
        </a>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $summary['org_count'] ?></div>
        <div class="stat-label">Total Organizations</div>
    </div>
    <div class="stat-card">
        <div class="stat-value green"><?= number_format($summary['total_balance'], 1) ?></div>
        <div class="stat-label">Total Balance (HRS)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value blue"><?= number_format($summary['avg_balance'], 1) ?></div>
        <div class="stat-label">Average Balance</div>
    </div>
</div>

<!-- Organizations Without Wallets -->
<?php if (!empty($orgsWithoutWallets)): ?>
<div class="admin-glass-card warning-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fa-solid fa-exclamation-triangle"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Organizations Without Wallets</h3>
            <p class="admin-card-subtitle"><?= count($orgsWithoutWallets) ?> organization<?= count($orgsWithoutWallets) !== 1 ? 's' : '' ?> need wallet initialization</p>
        </div>
        <form action="<?= $basePath ?>/admin/timebanking/org-wallets/initialize-all" method="POST" style="margin-left: auto;">
            <?= Csrf::input() ?>
            <button type="submit" class="admin-btn admin-btn-warning">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Initialize All
            </button>
        </form>
    </div>
    <div class="admin-card-body">
        <div class="orgs-without-wallets-grid">
            <?php foreach ($orgsWithoutWallets as $org): ?>
            <div class="org-without-wallet">
                <div class="org-info">
                    <div class="org-name"><?= htmlspecialchars($org['name']) ?></div>
                    <div class="org-owner"><?= htmlspecialchars($org['owner_name']) ?></div>
                </div>
                <form action="<?= $basePath ?>/admin/timebanking/org-wallets/initialize" method="POST">
                    <?= Csrf::input() ?>
                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-warning-outline">
                        <i class="fa-solid fa-plus"></i> Init
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Wallets Grid -->
<div class="admin-glass-card">
    <?php if (empty($wallets)): ?>
    <div class="wallets-empty">
        <div class="wallets-empty-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <h3>No Organization Wallets</h3>
        <p>Organizations will appear here when they create wallets.</p>
    </div>
    <?php else: ?>
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Active Wallets</h3>
            <p class="admin-card-subtitle"><?= count($wallets) ?> wallet<?= count($wallets) !== 1 ? 's' : '' ?></p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="wallets-grid">
            <?php foreach ($wallets as $wallet): ?>
            <div class="wallet-card">
                <?php if (!empty($pendingCounts[$wallet['organization_id']])): ?>
                <div class="wallet-pending-badge">
                    <i class="fa-solid fa-clock"></i> <?= $pendingCounts[$wallet['organization_id']] ?> pending
                </div>
                <?php endif; ?>

                <div class="wallet-card-header">
                    <div class="wallet-org-icon">
                        <?= strtoupper(substr($wallet['org_name'] ?? 'O', 0, 1)) ?>
                    </div>
                    <div class="wallet-org-info">
                        <div class="wallet-org-name"><?= htmlspecialchars($wallet['org_name']) ?></div>
                        <span class="wallet-org-status <?= $wallet['org_status'] ?>"><?= ucfirst($wallet['org_status']) ?></span>
                    </div>
                </div>

                <div class="wallet-balance">
                    <?= number_format($wallet['balance'], 1) ?> <span>HRS</span>
                </div>

                <div class="wallet-meta">
                    <div class="wallet-meta-item">
                        <div class="wallet-meta-value"><?= $wallet['member_count'] ?? 0 ?></div>
                        <div class="wallet-meta-label">Members</div>
                    </div>
                    <div class="wallet-meta-item">
                        <div class="wallet-meta-value"><?= date('M d, Y', strtotime($wallet['created_at'])) ?></div>
                        <div class="wallet-meta-label">Created</div>
                    </div>
                </div>

                <div class="wallet-actions">
                    <a href="<?= $basePath ?>/organizations/<?= $wallet['organization_id'] ?>/wallet" class="admin-btn admin-btn-sm admin-btn-secondary">
                        <i class="fa-solid fa-external-link-alt"></i> View Wallet
                    </a>
                    <a href="<?= $basePath ?>/admin/timebanking/org-members/<?= $wallet['organization_id'] ?>" class="admin-btn admin-btn-sm admin-btn-primary">
                        <i class="fa-solid fa-users-cog"></i> Members
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(30, 41, 59, 0.75));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    margin-bottom: 0.25rem;
}

.stat-value.green { color: #34d399; }
.stat-value.blue { color: #60a5fa; }

.stat-label {
    font-size: 0.85rem;
    color: #94a3b8;
}

/* Warning Card */
.warning-card {
    border-color: rgba(245, 158, 11, 0.3);
    margin-bottom: 1.5rem;
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border: none;
}

.admin-btn-warning:hover {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.admin-btn-warning-outline {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-btn-warning-outline:hover {
    background: rgba(245, 158, 11, 0.3);
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #34d399, #10b981);
}

/* Orgs Without Wallets */
.orgs-without-wallets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.75rem;
}

.org-without-wallet {
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.2);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.org-info {
    min-width: 0;
}

.org-name {
    font-weight: 600;
    color: #f1f5f9;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.org-owner {
    font-size: 0.8rem;
    color: #94a3b8;
}

/* Wallets Grid */
.wallets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}

.wallet-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1rem;
    padding: 1.25rem;
    transition: all 0.2s;
    position: relative;
}

.wallet-card:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateY(-2px);
}

.wallet-pending-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 10px;
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

.wallet-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
}

.wallet-org-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
}

.wallet-org-info {
    flex: 1;
    min-width: 0;
}

.wallet-org-name {
    font-weight: 700;
    color: #f1f5f9;
    font-size: 1rem;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wallet-org-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.wallet-org-status.approved,
.wallet-org-status.active {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.wallet-org-status.inactive,
.wallet-org-status.pending {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}

.wallet-balance {
    font-size: 1.75rem;
    font-weight: 800;
    color: #34d399;
    margin-bottom: 1rem;
}

.wallet-balance span {
    font-size: 0.9rem;
    font-weight: 400;
    color: #94a3b8;
}

.wallet-meta {
    display: flex;
    justify-content: space-between;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.wallet-meta-item {
    text-align: center;
}

.wallet-meta-value {
    font-weight: 700;
    color: #e2e8f0;
    font-size: 0.95rem;
}

.wallet-meta-label {
    font-size: 0.75rem;
    color: #64748b;
}

.wallet-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
}

.wallet-actions .admin-btn {
    flex: 1;
    justify-content: center;
}

/* Empty State */
.wallets-empty {
    text-align: center;
    padding: 4rem 2rem;
}

.wallets-empty-icon {
    font-size: 4rem;
    opacity: 0.2;
    margin-bottom: 1rem;
    color: #10b981;
}

.wallets-empty h3 {
    margin: 0 0 0.5rem;
    color: #94a3b8;
    font-weight: 600;
}

.wallets-empty p {
    margin: 0;
    color: rgba(255, 255, 255, 0.5);
}

/* Mobile */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .wallets-grid {
        grid-template-columns: 1fr;
    }

    .orgs-without-wallets-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
