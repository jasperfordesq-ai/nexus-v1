<?php
/**
 * Modern Dashboard - My Listings Page
 * Dedicated route version (replaces tab-based approach)
 */

$hero_title = "My Listings";
$hero_subtitle = "Manage your offers and requests";
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

// Separate counts
$offerCount = 0;
$reqCount = 0;
foreach ($my_listings as $ml) {
    if ($ml['type'] === 'offer') $offerCount++;
    else $reqCount++;
}
?>

<div class="dashboard-glass-bg"></div>

<div class="dashboard-container">

    <!-- Glass Navigation -->
    <div class="dash-tabs-glass">
        <a href="<?= $basePath ?>/dashboard" class="dash-tab-glass">
            <i class="fa-solid fa-house"></i> Overview
        </a>
        <a href="<?= $basePath ?>/dashboard/notifications" class="dash-tab-glass">
            <i class="fa-solid fa-bell"></i> Notifications
            <?php
            $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
            if ($uCount > 0): ?>
                <span class="dash-notif-badge"><?= $uCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/dashboard/hubs" class="dash-tab-glass">
            <i class="fa-solid fa-users"></i> My Hubs
        </a>
        <a href="<?= $basePath ?>/dashboard/listings" class="dash-tab-glass active">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-tab-glass">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="<?= $basePath ?>/dashboard/events" class="dash-tab-glass">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <div class="htb-card">
        <div class="htb-card-header dash-listings-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem;">My Listings</h3>
            <div class="dash-listings-stats" style="display: flex; gap: 10px;">
                <span style="font-size:0.85rem; color:#10b981; background:#ecfdf5; padding:6px 12px; border-radius:99px; font-weight:600;"><?= $offerCount ?> Offers</span>
                <span style="font-size:0.85rem; color:#f59e0b; background:#fffbeb; padding:6px 12px; border-radius:99px; font-weight:600;"><?= $reqCount ?> Requests</span>
            </div>
        </div>

        <div class="dash-listings-content" style="padding: 20px;">
            <a href="<?= $basePath ?>/compose?type=listing" class="htb-btn htb-btn-primary" style="margin-bottom: 20px; justify-content:center; max-width:200px;"><i class="fa-solid fa-plus"></i> Post New Listing</a>

            <?php if (empty($my_listings)): ?>
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <div style="font-size: 3rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-seedling"></i></div>
                    <p>You haven't posted any offers or requests yet.</p>
                </div>
            <?php else: ?>
                <div class="dash-listings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($my_listings as $l): ?>
                        <div class="htb-card" id="listing-<?= $l['id'] ?>" style="border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column;">
                            <?php if (!empty($l['image_url'])): ?>
                                <div style="height: 140px; background: url('<?= htmlspecialchars($l['image_url']) ?>') center/cover no-repeat;"></div>
                            <?php else: ?>
                                <div style="height: 140px; background: linear-gradient(135deg, #e0e7ff, #ede9fe); display: flex; align-items: center; justify-content: center; color: #a5b4fc; font-size: 3rem;">
                                    <i class="fa-solid fa-<?= $l['type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding' ?>"></i>
                                </div>
                            <?php endif; ?>

                            <div class="htb-card-body" style="flex-grow: 1; display: flex; flex-direction: column;">
                                <div style="margin-bottom: 10px;">
                                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: <?= $l['type'] === 'offer' ? '#10b981' : '#f59e0b' ?>;">
                                        <?= strtoupper($l['type']) ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: #94a3b8; float: right;">
                                        <?= date('M j, Y', strtotime($l['created_at'])) ?>
                                    </span>
                                </div>
                                <h4 style="margin: 0 0 5px 0; font-size: 1.1rem; line-height: 1.4;">
                                    <a href="<?= $basePath ?>/listings/<?= $l['id'] ?>" style="color: #1e293b; text-decoration: none;"><?= htmlspecialchars($l['title']) ?></a>
                                </h4>

                                <div class="dash-listing-card-actions" style="margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px;">
                                    <a href="<?= $basePath ?>/listings/<?= $l['id'] ?>" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;"><i class="fa-solid fa-eye"></i> View</a>
                                    <button onclick="deleteListing(<?= $l['id'] ?>)" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #fef2f2; border: 1px solid #fecaca; color: #ef4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteListing(id) {
    if (!confirm("Are you sure you want to delete this listing? It cannot be undone.")) return;

    const el = document.getElementById('listing-' + id);
    if (el) el.style.opacity = '0.5';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json'
    };
    if (csrf) headers['X-CSRF-Token'] = csrf;

    const body = new URLSearchParams();
    body.append('id', id);
    if (csrf) body.append('csrf_token', csrf);

    fetch('<?= $basePath ?>/api/listings/delete', {
        method: 'POST',
        headers: headers,
        body: body,
        credentials: 'same-origin'
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => {
                throw new Error(data.error || 'Request failed');
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            if (el) el.remove();
        } else {
            alert('Failed: ' + (data.error || 'Unknown error'));
            if (el) el.style.opacity = '1';
        }
    })
    .catch(e => {
        alert('Error: ' + e.message);
        if (el) el.style.opacity = '1';
    });
}
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
