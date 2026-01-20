<?php
/**
 * Modern Dashboard - My Events Page
 * Dedicated route version (replaces tab-based approach)
 */

$hero_title = "My Events";
$hero_subtitle = "Events you're hosting and attending";
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
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
        <a href="<?= $basePath ?>/dashboard/listings" class="dash-tab-glass">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-tab-glass">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="<?= $basePath ?>/dashboard/events" class="dash-tab-glass active">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <div class="dash-events-flex" style="display: flex; gap: 30px; flex-wrap: wrap;">

        <!-- Left: Hosting -->
        <div style="flex: 1; min-width: 300px;">
            <div class="dash-events-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 1.1rem; color: #334155;"><i class="fa-solid fa-calendar-star" style="margin-right: 8px; color: #4f46e5;"></i>Hosting</h3>
                <a href="<?= $basePath ?>/events/create" class="htb-btn htb-btn-sm htb-btn-primary"><i class="fa-solid fa-plus"></i> Create Event</a>
            </div>

            <?php if (empty($hosting)): ?>
                <div class="htb-card" style="padding: 40px 20px; text-align: center; color: #94a3b8;">
                    <div style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-calendar-xmark"></i></div>
                    <p style="margin: 0;">You are not hosting any upcoming events.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($hosting as $e): ?>
                        <div class="htb-card">
                            <div class="htb-card-body" style="padding: 16px;">
                                <div class="dash-event-card-inner" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 0.8rem; font-weight: 700; color: #4f46e5; text-transform: uppercase;">
                                            <?= date('M j @ g:i A', strtotime($e['start_time'])) ?>
                                        </div>
                                        <h4 style="margin: 5px 0; font-size: 1rem; line-height: 1.3;">
                                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" style="color: #1e293b; text-decoration: none;">
                                                <?= htmlspecialchars($e['title']) ?>
                                            </a>
                                        </h4>
                                        <div style="font-size: 0.85rem; color: #64748b;">
                                            <i class="fa-solid fa-location-dot" style="margin-right: 5px;"></i> <?= htmlspecialchars($e['location'] ?? 'TBA') ?>
                                        </div>
                                    </div>
                                    <div class="dash-event-stats" style="text-align: right; font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                                        <div><strong style="color: #10b981;"><?= $e['attending_count'] ?? 0 ?></strong> Going</div>
                                        <div><strong><?= $e['invited_count'] ?? 0 ?></strong> Invited</div>
                                    </div>
                                </div>
                                <div class="dash-event-actions" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px;">
                                    <a href="<?= $basePath ?>/events/<?= $e['id'] ?>/edit" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1;"><i class="fa-solid fa-pen"></i> Edit</a>
                                    <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1;"><i class="fa-solid fa-users"></i> Manage</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Attending -->
        <div style="flex: 1; min-width: 300px;">
            <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; color: #334155;"><i class="fa-solid fa-calendar-check" style="margin-right: 8px; color: #10b981;"></i>Attending</h3>

            <?php if (empty($attending)): ?>
                <div class="htb-card" style="padding: 40px 20px; text-align: center; color: #94a3b8;">
                    <div style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-calendar-plus"></i></div>
                    <p style="margin: 0;">You are not attending any upcoming events.</p>
                    <a href="<?= $basePath ?>/events" style="color: #4f46e5; font-size: 0.9rem; display: inline-block; margin-top: 10px;">Browse Events</a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($attending as $e): ?>
                        <div class="htb-card">
                            <div class="htb-card-body" style="padding: 16px; display: flex; gap: 14px;">
                                <!-- Date Box -->
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 56px; min-width: 56px; background: linear-gradient(135deg, #eff6ff, #e0e7ff); border-radius: 10px; padding: 8px 0;">
                                    <div style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700; color: #4f46e5;"><?= date('M', strtotime($e['start_time'])) ?></div>
                                    <div style="font-size: 1.4rem; font-weight: 800; color: #1e293b; line-height: 1;"><?= date('j', strtotime($e['start_time'])) ?></div>
                                </div>

                                <!-- Details -->
                                <div style="flex: 1; min-width: 0;">
                                    <h4 style="margin: 0 0 5px 0; font-size: 0.95rem; line-height: 1.3;">
                                        <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" style="color: #1e293b; text-decoration: none;">
                                            <?= htmlspecialchars($e['title']) ?>
                                        </a>
                                    </h4>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 6px;">
                                        <?= date('g:i A', strtotime($e['start_time'])) ?> • <?= htmlspecialchars($e['organizer_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #10b981; font-weight: 600; background: #ecfdf5; display: inline-block; padding: 3px 8px; border-radius: 99px;">
                                        <i class="fa-solid fa-check-circle"></i> Going
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
