<?php
$hTitle = 'XP Shop';
$hSubtitle = 'Redeem your XP for exclusive rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Load achievements CSS
$additionalCSS = '<link rel="stylesheet" href="/assets/css/achievements.min.css?v=' . time() . '">';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<div class="shop-wrapper">
    <div class="collections-nav">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill active">XP Shop</a>
        <a href="<?= $basePath ?>/achievements/seasons" class="nav-pill">Seasons</a>
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
    <div class="modal-content">
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
    <div class="modal-content">
        <div class="modal-icon" id="successIcon"></div>
        <h3 class="modal-title" id="successTitle">Purchase Complete!</h3>
        <p class="modal-message" id="successMessage"></p>
        <div class="modal-buttons">
            <button class="modal-btn confirm" onclick="closeSuccessModal()">Awesome!</button>
        </div>
    </div>
</div>

<script>
// Category filtering
document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const category = this.dataset.category;
        document.querySelectorAll('.shop-item').forEach(item => {
            if (category === 'all' || item.dataset.category === category) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

let currentItemId = null;

function confirmPurchase(itemId, itemName, itemCost, itemIcon) {
    currentItemId = itemId;
    document.getElementById('modalIcon').innerHTML = itemIcon || '<i class="fa-solid fa-gift"></i>';
    document.getElementById('modalItemName').textContent = itemName;
    document.getElementById('modalItemCost').textContent = itemCost.toLocaleString();
    document.getElementById('purchaseModal').classList.add('active');
}

function closeModal() {
    document.getElementById('purchaseModal').classList.remove('active');
    currentItemId = null;
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
    location.reload(); // Refresh to update XP and ownership status
}

document.getElementById('confirmBtn').addEventListener('click', async function() {
    if (!currentItemId) return;

    this.disabled = true;
    this.textContent = 'Processing...';

    try {
        const formData = new FormData();
        formData.append('item_id', currentItemId);

        const response = await fetch('<?= $basePath ?>/api/shop/purchase', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        closeModal();

        if (result.success) {
            document.getElementById('successIcon').innerHTML = '<i class="fa-solid fa-check-circle" style="color: #10b981;"></i>';
            document.getElementById('successTitle').textContent = 'Purchase Complete!';
            document.getElementById('successMessage').textContent = result.message || 'Your item has been added to your account.';
            document.getElementById('successModal').classList.add('active');

            // Trigger confetti if available
            if (typeof showConfetti === 'function') {
                showConfetti();
            }
        } else {
            document.getElementById('successIcon').innerHTML = '<i class="fa-solid fa-times-circle" style="color: #ef4444;"></i>';
            document.getElementById('successTitle').textContent = 'Purchase Failed';
            document.getElementById('successMessage').textContent = result.error || 'Something went wrong. Please try again.';
            document.getElementById('successModal').classList.add('active');
        }
    } catch (error) {
        closeModal();
        document.getElementById('successIcon').innerHTML = '<i class="fa-solid fa-times-circle" style="color: #ef4444;"></i>';
        document.getElementById('successTitle').textContent = 'Error';
        document.getElementById('successMessage').textContent = 'Failed to process purchase. Please try again.';
        document.getElementById('successModal').classList.add('active');
    }

    this.disabled = false;
    this.textContent = 'Confirm';
});

// Close modals on outside click
document.querySelectorAll('.purchase-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
