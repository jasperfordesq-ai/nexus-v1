<?php
/**
 * Admin Plan Manager - Index
 * Super Admin interface for managing subscription plans
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Plans';
$adminPageSubtitle = 'Subscriptions';
$adminPageIcon = 'fa-crown';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="page-hero-content">
        <div class="page-hero-icon">
            <i class="fa-solid fa-crown"></i>
        </div>
        <div class="page-hero-text">
            <h1>Subscription Plans</h1>
            <p>Manage pricing tiers, features, and limits</p>
        </div>
    </div>
    <div class="page-hero-actions">
        <a href="<?= $basePath ?>/admin/plans/subscriptions" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-users"></i> Tenant Subscriptions
        </a>
        <a href="<?= $basePath ?>/admin/plans/comparison" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-table"></i> Compare Plans
        </a>
        <a href="<?= $basePath ?>/admin/plans/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Plan
        </a>
    </div>
</div>

<!-- Plans Grid -->
<div class="plans-grid">
    <?php foreach ($plans as $plan):
        $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features'];
        $layouts = is_string($plan['allowed_layouts']) ? json_decode($plan['allowed_layouts'], true) : $plan['allowed_layouts'];
        $tierColors = ['blue', 'green', 'purple', 'orange'];
        $colorIndex = $plan['tier_level'] % count($tierColors);
        $color = $tierColors[$colorIndex];
    ?>
    <div class="plan-card <?= $plan['is_active'] ? '' : 'plan-inactive' ?>" data-plan-id="<?= $plan['id'] ?>">
        <div class="plan-header plan-header-<?= $color ?>">
            <div class="plan-tier">Tier <?= $plan['tier_level'] ?></div>
            <h3 class="plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
            <?php if (!$plan['is_active']): ?>
                <span class="plan-status-badge">Inactive</span>
            <?php endif; ?>
        </div>

        <div class="plan-pricing">
            <div class="plan-price">
                <span class="price-amount">$<?= number_format($plan['price_monthly'], 0) ?></span>
                <span class="price-period">/month</span>
            </div>
            <?php if ($plan['price_yearly'] > 0): ?>
            <div class="plan-price-yearly">
                or $<?= number_format($plan['price_yearly'], 0) ?>/year
            </div>
            <?php endif; ?>
        </div>

        <div class="plan-description">
            <?= htmlspecialchars($plan['description']) ?>
        </div>

        <div class="plan-limits">
            <div class="plan-limit">
                <i class="fa-solid fa-bars"></i>
                <strong><?= $plan['max_menus'] == 999 ? 'Unlimited' : $plan['max_menus'] ?></strong>
                Menus
            </div>
            <div class="plan-limit">
                <i class="fa-solid fa-link"></i>
                <strong><?= $plan['max_menu_items'] == 999 ? 'Unlimited' : $plan['max_menu_items'] ?></strong>
                Items per menu
            </div>
            <div class="plan-limit">
                <i class="fa-solid fa-palette"></i>
                <strong><?= count($layouts ?? []) ?></strong>
                Layouts
            </div>
        </div>

        <div class="plan-features">
            <strong>Features:</strong>
            <ul>
                <?php foreach ($features ?? [] as $feature => $enabled): ?>
                    <?php if ($enabled): ?>
                    <li><i class="fa-solid fa-check"></i> <?= ucwords(str_replace('_', ' ', $feature)) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="plan-actions">
            <a href="<?= $basePath ?>/admin/plans/edit/<?= $plan['id'] ?>" class="admin-btn admin-btn-sm admin-btn-secondary">
                <i class="fa-solid fa-edit"></i> Edit
            </a>
            <button onclick="deletePlan(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name']) ?>')" class="admin-btn admin-btn-sm admin-btn-danger">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($plans)): ?>
<div class="admin-empty-state">
    <div class="admin-empty-icon">
        <i class="fa-solid fa-crown"></i>
    </div>
    <h3 class="admin-empty-title">No Plans Yet</h3>
    <p class="admin-empty-text">Create your first subscription plan to get started.</p>
    <a href="<?= $basePath ?>/admin/plans/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
        <i class="fa-solid fa-plus"></i> Create First Plan
    </a>
</div>
<?php endif; ?>

<style>
.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.plan-card {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.plan-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.plan-inactive {
    opacity: 0.6;
    filter: grayscale(0.5);
}

.plan-header {
    padding: 2rem;
    text-align: center;
    position: relative;
}

.plan-header-blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.plan-header-green { background: linear-gradient(135deg, #22c55e, #16a34a); }
.plan-header-purple { background: linear-gradient(135deg, #a855f7, #9333ea); }
.plan-header-orange { background: linear-gradient(135deg, #f59e0b, #ea580c); }

.plan-tier {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.plan-name {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0.5rem 0 0 0;
    color: #fff;
}

.plan-status-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(239, 68, 68, 0.9);
    color: #fff;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.plan-pricing {
    padding: 1.5rem 2rem;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.plan-price {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 0.5rem;
}

.price-amount {
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
}

.price-period {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.6);
}

.plan-price-yearly {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}

.plan-description {
    padding: 1.5rem 2rem;
    color: rgba(255, 255, 255, 0.8);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.plan-limits {
    padding: 1.5rem 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.plan-limit {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
}

.plan-limit i {
    color: rgba(255, 255, 255, 0.5);
}

.plan-limit strong {
    color: #fff;
    min-width: 3rem;
}

.plan-features {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.plan-features strong {
    display: block;
    margin-bottom: 0.75rem;
    color: rgba(255, 255, 255, 0.8);
}

.plan-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.plan-features li {
    padding: 0.25rem 0;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
}

.plan-features li i {
    color: #22c55e;
    margin-right: 0.5rem;
}

.plan-actions {
    padding: 1.5rem 2rem;
    display: flex;
    gap: 0.5rem;
}

.plan-actions .admin-btn {
    flex: 1;
}
</style>

<script>
function deletePlan(planId, planName) {
    if (!confirm(`Are you sure you want to delete the "${planName}" plan?\n\nThis action cannot be undone if tenants are using this plan.`)) {
        return;
    }

    fetch('<?= $basePath ?>/admin/plans/delete/' + planId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'csrf_token=<?= Csrf::generate() ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete plan'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
