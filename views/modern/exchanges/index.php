<?php
/**
 * Modern View: Exchanges List
 * User-facing exchange workflow dashboard
 */
$hTitle = 'My Exchanges';
$hSubtitle = 'Track and manage your service exchanges';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Exchanges';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
$status = $_GET['status'] ?? 'active';

// Status labels for display
$statusLabels = [
    'pending_provider' => 'Pending',
    'pending_broker' => 'Under Review',
    'accepted' => 'Accepted',
    'in_progress' => 'In Progress',
    'pending_confirmation' => 'Confirming',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'disputed' => 'Disputed',
    'expired' => 'Expired',
];
?>

<div class="exchanges-container">
    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="glass-alert glass-alert--success">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="glass-alert glass-alert--danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Tabs -->
    <nav class="exchanges-tabs" aria-label="Exchange filters">
        <a href="<?= $basePath ?>/exchanges?status=active"
           class="exchange-tab <?= $status === 'active' ? 'active' : '' ?>"
           aria-current="<?= $status === 'active' ? 'page' : 'false' ?>">
            Active
        </a>
        <a href="<?= $basePath ?>/exchanges?status=pending"
           class="exchange-tab <?= $status === 'pending' ? 'active' : '' ?>"
           aria-current="<?= $status === 'pending' ? 'page' : 'false' ?>">
            Pending
        </a>
        <a href="<?= $basePath ?>/exchanges?status=completed"
           class="exchange-tab <?= $status === 'completed' ? 'active' : '' ?>"
           aria-current="<?= $status === 'completed' ? 'page' : 'false' ?>">
            Completed
        </a>
        <a href="<?= $basePath ?>/exchanges?status=all"
           class="exchange-tab <?= $status === 'all' ? 'active' : '' ?>"
           aria-current="<?= $status === 'all' ? 'page' : 'false' ?>">
            All
        </a>
    </nav>

    <!-- Exchange List -->
    <?php if (empty($exchanges)): ?>
        <div class="exchange-empty">
            <div class="exchange-empty-icon">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <h2 class="exchange-empty-title">No exchanges yet</h2>
            <p class="exchange-empty-text">
                <?php if ($status === 'active'): ?>
                    You don't have any active exchanges. Browse listings to request an exchange!
                <?php elseif ($status === 'pending'): ?>
                    You don't have any pending exchanges.
                <?php elseif ($status === 'completed'): ?>
                    You haven't completed any exchanges yet.
                <?php else: ?>
                    You don't have any exchanges yet.
                <?php endif; ?>
            </p>
            <a href="<?= $basePath ?>/listings" class="glass-button glass-button--primary">
                <i class="fa-solid fa-search"></i> Browse Listings
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($exchanges as $exchange):
            $isRequester = $exchange['requester_id'] === $currentUserId;
            $otherUser = $isRequester
                ? ['name' => $exchange['provider_name'], 'avatar' => $exchange['provider_avatar']]
                : ['name' => $exchange['requester_name'], 'avatar' => $exchange['requester_avatar']];
            $statusClass = strtolower($exchange['status']);
        ?>
        <article class="exchange-card">
            <div class="exchange-card-header">
                <h2 class="exchange-listing-title">
                    <a href="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>">
                        <?= htmlspecialchars($exchange['listing_title']) ?>
                    </a>
                </h2>
                <span class="exchange-status-badge exchange-status-badge--<?= $statusClass ?>">
                    <?= $statusLabels[$exchange['status']] ?? ucfirst(str_replace('_', ' ', $exchange['status'])) ?>
                </span>
            </div>

            <div class="exchange-card-body">
                <div class="exchange-participants">
                    <div class="exchange-participant">
                        <?php if (!empty($exchange['requester_avatar'])): ?>
                            <img src="<?= htmlspecialchars($exchange['requester_avatar']) ?>"
                                 alt=""
                                 class="exchange-participant-avatar">
                        <?php else: ?>
                            <span class="exchange-participant-avatar-placeholder">
                                <?= strtoupper(substr($exchange['requester_name'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($exchange['requester_name']) ?></span>
                        <?php if ($isRequester): ?>
                            <span class="glass-badge glass-badge--sm">(You)</span>
                        <?php endif; ?>
                    </div>
                    <i class="fa-solid fa-arrow-right exchange-arrow"></i>
                    <div class="exchange-participant">
                        <?php if (!empty($exchange['provider_avatar'])): ?>
                            <img src="<?= htmlspecialchars($exchange['provider_avatar']) ?>"
                                 alt=""
                                 class="exchange-participant-avatar">
                        <?php else: ?>
                            <span class="exchange-participant-avatar-placeholder">
                                <?= strtoupper(substr($exchange['provider_name'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($exchange['provider_name']) ?></span>
                        <?php if (!$isRequester): ?>
                            <span class="glass-badge glass-badge--sm">(You)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="exchange-meta">
                    <div class="exchange-meta-item">
                        <i class="fa-solid fa-clock"></i>
                        <span><?= number_format($exchange['proposed_hours'], 1) ?> hours</span>
                    </div>
                    <div class="exchange-meta-item">
                        <i class="fa-solid fa-calendar"></i>
                        <span><?= date('M j, Y', strtotime($exchange['created_at'])) ?></span>
                    </div>
                    <div class="exchange-meta-item">
                        <i class="fa-solid fa-<?= $exchange['listing_type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding' ?>"></i>
                        <span><?= ucfirst($exchange['listing_type']) ?></span>
                    </div>
                </div>
            </div>

            <div class="exchange-card-footer">
                <a href="<?= $basePath ?>/exchanges/<?= $exchange['id'] ?>" class="glass-button glass-button--outline glass-button--sm">
                    View Details <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </article>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="glass-pagination" aria-label="Pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= $basePath ?>/exchanges?status=<?= $status ?>&page=<?= $i ?>"
                       class="glass-pagination-item <?= $i === (int)($_GET['page'] ?? 1) ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
