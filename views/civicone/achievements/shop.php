<?php
$hTitle = 'XP Shop';
$hSubtitle = 'Redeem your XP for exclusive rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>
<!-- CSS moved to /assets/css/civicone-achievements.css (2026-01-19) -->

<div class="shop-wrapper">
    <div class="collections-nav">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill active">XP Shop</a>
    </div>

    <div class="xp-balance-card">
        <div class="xp-balance-info">
            <h3>Your XP Balance</h3>
            <div class="xp-balance-amount"><?= number_format($userXP) ?> XP</div>
        </div>
        <div class="xp-balance-icon">
            <i class="fa-solid fa-coins"></i>
        </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-store"></i></div>
        <h3>Shop Coming Soon</h3>
        <p>Exciting rewards will be available here. Keep earning XP!</p>
    </div>
    <?php else: ?>

    <div class="shop-categories">
        <button class="category-btn active" data-category="all">All Items</button>
        <button class="category-btn" data-category="boost">Boosts</button>
        <button class="category-btn" data-category="feature">Features</button>
        <button class="category-btn" data-category="cosmetic">Cosmetics</button>
    </div>

    <div class="shop-grid">
        <?php foreach ($items as $item): ?>
        <div class="shop-item <?= $item['owned'] ? 'owned' : '' ?> <?= ($item['xp_cost'] > $userXP && !$item['owned']) ? 'locked' : '' ?>"
             data-category="<?= htmlspecialchars($item['category'] ?? 'feature') ?>">

            <?php if ($item['owned']): ?>
                <span class="item-badge owned">Owned</span>
            <?php elseif ($item['is_limited'] ?? false): ?>
                <span class="item-badge limited">Limited</span>
            <?php elseif ($item['is_new'] ?? false): ?>
                <span class="item-badge new">New</span>
            <?php endif; ?>

            <div class="item-icon"><?= $item['icon'] ?? '<i class="fa-solid fa-gift"></i>' ?></div>
            <h3 class="item-name"><?= htmlspecialchars($item['name']) ?></h3>
            <p class="item-description"><?= htmlspecialchars($item['description']) ?></p>

            <div class="item-footer">
                <div class="item-price">
                    <i class="fa-solid fa-star"></i>
                    <?= number_format($item['xp_cost']) ?>
                </div>

                <?php if ($item['owned']): ?>
                    <button class="buy-btn owned" disabled>
                        <i class="fa-solid fa-check"></i> Owned
                    </button>
                <?php elseif ($item['xp_cost'] > $userXP): ?>
                    <button class="buy-btn" disabled>
                        Need <?= number_format($item['xp_cost'] - $userXP) ?> more
                    </button>
                <?php else: ?>
                    <button class="buy-btn" onclick="confirmPurchase(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>', <?= $item['xp_cost'] ?>, '<?= $item['icon'] ?? '' ?>')">
                        Buy Now
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Purchase Confirmation Modal -->
<div class="purchase-modal" id="purchaseModal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <div class="modal-icon" id="modalIcon"></div>
        <h3 class="modal-title">Confirm Purchase</h3>
        <p class="modal-message">
            Purchase <strong id="modalItemName"></strong> for <strong id="modalItemCost"></strong> XP?
        </p>
        <div class="modal-buttons">
            <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn confirm" id="confirmBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="purchase-modal" id="successModal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <div class="modal-icon" id="successIcon"></div>
        <h3 class="modal-title" id="successTitle">Purchase Complete!</h3>
        <p class="modal-message" id="successMessage"></p>
        <div class="modal-buttons">
            <button class="modal-btn confirm" onclick="closeSuccessModal()">Awesome!</button>
        </div>
    </div>
</div>

<!-- Page-specific JavaScript -->
<script src="/assets/js/civicone-achievements.min.js" defer></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
