<?php
$hTitle = 'XP Shop';
$hSubtitle = 'Redeem your XP for exclusive rewards';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Gamification';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<style>
.shop-wrapper {
    margin-top: 120px;
    padding: 0 20px 60px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.collections-nav {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.nav-pill {
    padding: 10px 20px;
    border-radius: 25px;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-pill:hover, .nav-pill.active {
    background: white;
    color: #1e1e2e;
}

.xp-balance-card {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 20px;
    padding: 24px 32px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(79, 70, 229, 0.3);
}

.xp-balance-info h3 {
    font-size: 14px;
    font-weight: 500;
    opacity: 0.9;
    margin-bottom: 4px;
}

.xp-balance-amount {
    font-size: 36px;
    font-weight: 800;
}

.xp-balance-icon {
    font-size: 48px;
    opacity: 0.8;
}

.shop-categories {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.category-btn {
    padding: 8px 16px;
    border-radius: 20px;
    background: rgba(255,255,255,0.95);
    color: #6b7280;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.category-btn:hover, .category-btn.active {
    background: #4f46e5;
    color: white;
}

.shop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.shop-item {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}

.shop-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.shop-item.owned {
    border: 2px solid #10b981;
}

.shop-item.locked {
    opacity: 0.7;
}

.item-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.item-badge.owned {
    background: #d1fae5;
    color: #059669;
}

.item-badge.limited {
    background: #fef3c7;
    color: #d97706;
}

.item-badge.new {
    background: #dbeafe;
    color: #2563eb;
}

.item-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin-bottom: 16px;
}

.shop-item.owned .item-icon {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
}

.item-name {
    font-size: 18px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 8px;
}

.item-description {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
    margin-bottom: 16px;
}

.item-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.item-price {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 18px;
    font-weight: 700;
    color: #4f46e5;
}

.item-price i {
    color: #fbbf24;
}

.buy-btn {
    padding: 10px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.buy-btn:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

.buy-btn:disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

.buy-btn.owned {
    background: #10b981;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: white;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.purchase-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.purchase-modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 32px;
    max-width: 400px;
    width: 90%;
    text-align: center;
}

.modal-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 8px;
}

.modal-message {
    color: #6b7280;
    margin-bottom: 24px;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.modal-btn.cancel {
    background: #f3f4f6;
    color: #6b7280;
}

.modal-btn.confirm {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
}

.modal-btn:hover {
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .xp-balance-card {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }

    .shop-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
    <div class="modal" role="dialog" aria-modal="true"-content">
        <div class="modal" role="dialog" aria-modal="true"-icon" id="modalIcon"></div>
        <h3 class="modal" role="dialog" aria-modal="true"-title">Confirm Purchase</h3>
        <p class="modal" role="dialog" aria-modal="true"-message">
            Purchase <strong id="modalItemName"></strong> for <strong id="modalItemCost"></strong> XP?
        </p>
        <div class="modal" role="dialog" aria-modal="true"-buttons">
            <button class="modal" role="dialog" aria-modal="true"-btn cancel" onclick="closeModal()">Cancel</button>
            <button class="modal" role="dialog" aria-modal="true"-btn confirm" id="confirmBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="purchase-modal" id="successModal">
    <div class="modal" role="dialog" aria-modal="true"-content">
        <div class="modal" role="dialog" aria-modal="true"-icon" id="successIcon"></div>
        <h3 class="modal" role="dialog" aria-modal="true"-title" id="successTitle">Purchase Complete!</h3>
        <p class="modal" role="dialog" aria-modal="true"-message" id="successMessage"></p>
        <div class="modal" role="dialog" aria-modal="true"-buttons">
            <button class="modal" role="dialog" aria-modal="true"-btn confirm" onclick="closeSuccessModal()">Awesome!</button>
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

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
