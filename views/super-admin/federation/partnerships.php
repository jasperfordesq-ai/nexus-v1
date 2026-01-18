<?php
/**
 * Super Admin Federation Partnerships Overview
 * View and manage all partnerships across the platform
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Federation Partnerships';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-handshake"></i>
            Federation Partnerships
        </h1>
        <p class="super-page-subtitle">
            Overview of all tenant partnerships across the platform
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/federation" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Overview
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="super-stats-grid">
    <div class="super-stat-card">
        <div class="super-stat-icon green">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $stats['active'] ?? 0 ?></div>
            <div class="super-stat-label">Active</div>
        </div>
    </div>
    <div class="super-stat-card">
        <div class="super-stat-icon amber">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $stats['pending'] ?? 0 ?></div>
            <div class="super-stat-label">Pending</div>
        </div>
    </div>
    <div class="super-stat-card">
        <div class="super-stat-icon red">
            <i class="fa-solid fa-pause-circle"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $stats['suspended'] ?? 0 ?></div>
            <div class="super-stat-label">Suspended</div>
        </div>
    </div>
    <div class="super-stat-card">
        <div class="super-stat-icon purple">
            <i class="fa-solid fa-ban"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $stats['terminated'] ?? 0 ?></div>
            <div class="super-stat-label">Terminated</div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="super-card" style="margin-top: 1.5rem;">
    <div class="super-card-header">
        <div style="display: flex; gap: 0.5rem;">
            <button class="super-btn super-btn-sm filter-btn active" data-filter="all">All</button>
            <button class="super-btn super-btn-sm super-btn-secondary filter-btn" data-filter="active">Active</button>
            <button class="super-btn super-btn-sm super-btn-secondary filter-btn" data-filter="pending">Pending</button>
            <button class="super-btn super-btn-sm super-btn-secondary filter-btn" data-filter="suspended">Suspended</button>
            <button class="super-btn super-btn-sm super-btn-secondary filter-btn" data-filter="terminated">Terminated</button>
        </div>
    </div>
    <table class="super-table">
        <thead>
            <tr>
                <th>Tenant A</th>
                <th></th>
                <th>Tenant B</th>
                <th>Level</th>
                <th>Features</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="partnershipsTable">
            <?php if (empty($partnerships)): ?>
            <tr class="no-data-row">
                <td colspan="8" style="text-align: center; padding: 3rem; color: var(--super-text-muted);">
                    <i class="fa-solid fa-handshake" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No partnerships found
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($partnerships as $p): ?>
            <tr class="partnership-row" data-status="<?= $p['status'] ?>" id="partnership-row-<?= $p['id'] ?>">
                <td>
                    <a href="/super-admin/federation/tenant/<?= $p['tenant_id'] ?>" class="super-table-link">
                        <?= htmlspecialchars($p['tenant_name'] ?? 'Unknown') ?>
                    </a>
                </td>
                <td style="text-align: center; color: var(--super-text-muted);">
                    <i class="fa-solid fa-arrows-left-right"></i>
                </td>
                <td>
                    <a href="/super-admin/federation/tenant/<?= $p['partner_tenant_id'] ?>" class="super-table-link">
                        <?= htmlspecialchars($p['partner_name'] ?? 'Unknown') ?>
                    </a>
                </td>
                <td>
                    <?php
                    $levelNames = ['', 'Discovery', 'Social', 'Economic', 'Integrated'];
                    $levelColors = ['', 'info', 'primary', 'warning', 'success'];
                    $level = $p['federation_level'] ?? 1;
                    ?>
                    <span class="super-badge super-badge-<?= $levelColors[$level] ?? 'info' ?>">
                        L<?= $level ?> <?= $levelNames[$level] ?? '' ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                        <?php if ($p['profiles_enabled']): ?>
                        <span class="super-badge super-badge-secondary" title="Profiles"><i class="fa-solid fa-user"></i></span>
                        <?php endif; ?>
                        <?php if ($p['messaging_enabled']): ?>
                        <span class="super-badge super-badge-secondary" title="Messaging"><i class="fa-solid fa-envelope"></i></span>
                        <?php endif; ?>
                        <?php if ($p['transactions_enabled']): ?>
                        <span class="super-badge super-badge-secondary" title="Transactions"><i class="fa-solid fa-exchange-alt"></i></span>
                        <?php endif; ?>
                        <?php if ($p['listings_enabled']): ?>
                        <span class="super-badge super-badge-secondary" title="Listings"><i class="fa-solid fa-list"></i></span>
                        <?php endif; ?>
                        <?php if ($p['events_enabled']): ?>
                        <span class="super-badge super-badge-secondary" title="Events"><i class="fa-solid fa-calendar"></i></span>
                        <?php endif; ?>
                        <?php if ($p['groups_enabled']): ?>
                        <span class="super-badge super-badge-secondary" title="Groups"><i class="fa-solid fa-users"></i></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php
                    $statusColors = [
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'terminated' => 'secondary'
                    ];
                    ?>
                    <span class="super-badge super-badge-<?= $statusColors[$p['status']] ?? 'secondary' ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td style="color: var(--super-text-muted); font-size: 0.85rem;">
                    <?= date('M j, Y', strtotime($p['created_at'])) ?>
                </td>
                <td>
                    <?php if ($p['status'] === 'active'): ?>
                    <button onclick="suspendPartnership(<?= $p['id'] ?>)" class="super-btn super-btn-sm super-btn-warning" title="Suspend">
                        <i class="fa-solid fa-pause"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ($p['status'] !== 'terminated'): ?>
                    <button onclick="terminatePartnership(<?= $p['id'] ?>)" class="super-btn super-btn-sm super-btn-danger" title="Terminate">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Status Message -->
<div id="statusMessage" style="display: none; position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: 8px; color: white; z-index: 1000;"></div>

<script>
const csrfToken = '<?= Csrf::token() ?>';

// Filter functionality
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('active');
            b.classList.add('super-btn-secondary');
        });
        this.classList.add('active');
        this.classList.remove('super-btn-secondary');

        const filter = this.dataset.filter;
        document.querySelectorAll('.partnership-row').forEach(row => {
            if (filter === 'all' || row.dataset.status === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

function suspendPartnership(id) {
    const reason = prompt('Enter reason for suspension:');
    if (!reason) return;

    fetch('/super-admin/federation/suspend-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showStatus(data.error || 'Failed to suspend partnership', false);
        }
    });
}

function terminatePartnership(id) {
    const reason = prompt('Enter reason for termination (this action cannot be undone):');
    if (!reason) return;

    if (!confirm('Are you sure you want to terminate this partnership?')) return;

    fetch('/super-admin/federation/terminate-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showStatus(data.error || 'Failed to terminate partnership', false);
        }
    });
}

function showStatus(message, success) {
    const el = document.getElementById('statusMessage');
    el.textContent = message;
    el.style.background = success ? '#16a34a' : '#dc2626';
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
