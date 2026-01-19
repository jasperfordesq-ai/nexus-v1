<?php
/**
 * Admin Plan Comparison
 * Visual comparison matrix
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Plan Comparison';
$adminPageSubtitle = 'Plans';
$adminPageIcon = 'fa-table';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="page-hero">
    <div class="page-hero-content">
        <h1>
            <a href="<?= $basePath ?>/admin/plans" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Plan Comparison
        </h1>
        <p>Side-by-side feature comparison</p>
    </div>
</div>

<div class="comparison-table-container">
    <table class="comparison-table">
        <thead>
            <tr>
                <th class="feature-column">Feature</th>
                <?php foreach ($plans as $plan): ?>
                <th class="plan-column plan-tier-<?= $plan['tier_level'] ?>">
                    <div class="plan-header">
                        <h3><?= htmlspecialchars($plan['name']) ?></h3>
                        <div class="plan-price">
                            $<?= number_format($plan['price_monthly'], 0) ?>
                            <span>/mo</span>
                        </div>
                        <div class="plan-tier-badge">Tier <?= $plan['tier_level'] ?></div>
                    </div>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <!-- Pricing -->
            <tr class="section-header">
                <td colspan="<?= count($plans) + 1 ?>"><strong>💰 Pricing</strong></td>
            </tr>
            <tr>
                <td>Monthly Price</td>
                <?php foreach ($plans as $plan): ?>
                <td class="value-cell">$<?= number_format($plan['price_monthly'], 2) ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Yearly Price</td>
                <?php foreach ($plans as $plan): ?>
                <td class="value-cell">
                    <?= $plan['price_yearly'] > 0 ? '$' . number_format($plan['price_yearly'], 2) : '-' ?>
                </td>
                <?php endforeach; ?>
            </tr>

            <!-- Limits -->
            <tr class="section-header">
                <td colspan="<?= count($plans) + 1 ?>"><strong>📊 Limits</strong></td>
            </tr>
            <tr>
                <td>Max Menus</td>
                <?php foreach ($plans as $plan): ?>
                <td class="value-cell">
                    <?= $plan['max_menus'] == 999 ? '∞ Unlimited' : $plan['max_menus'] ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Max Menu Items</td>
                <?php foreach ($plans as $plan): ?>
                <td class="value-cell">
                    <?= $plan['max_menu_items'] == 999 ? '∞ Unlimited' : $plan['max_menu_items'] ?>
                </td>
                <?php endforeach; ?>
            </tr>

            <!-- Layouts -->
            <tr class="section-header">
                <td colspan="<?= count($plans) + 1 ?>"><strong>🎨 Layouts</strong></td>
            </tr>
            <tr>
                <td>Allowed Layouts</td>
                <?php foreach ($plans as $plan): ?>
                <td class="value-cell">
                    <?php
                    $layouts = $plan['allowed_layouts'] ?? [];
                    if (count($layouts) >= 3):
                        echo 'All Layouts';
                    else:
                        echo implode(', ', array_map('ucfirst', $layouts));
                    endif;
                    ?>
                </td>
                <?php endforeach; ?>
            </tr>

            <!-- Features -->
            <tr class="section-header">
                <td colspan="<?= count($plans) + 1 ?>"><strong>✨ Features</strong></td>
            </tr>
            <?php
            // Collect all unique features
            $allFeatures = [];
            foreach ($plans as $plan) {
                foreach ($plan['features'] as $feature => $enabled) {
                    $allFeatures[$feature] = ucwords(str_replace('_', ' ', $feature));
                }
            }

            foreach ($allFeatures as $featureKey => $featureName):
            ?>
            <tr>
                <td><?= $featureName ?></td>
                <?php foreach ($plans as $plan): ?>
                <td class="check-cell">
                    <?php if (isset($plan['features'][$featureKey]) && $plan['features'][$featureKey]): ?>
                        <i class="fa-solid fa-check" style="color: #22c55e;"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-xmark" style="color: rgba(255,255,255,0.3);"></i>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.comparison-table-container {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(139, 92, 246, 0.05));
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    overflow-x: auto;
    padding: 2rem;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.comparison-table th,
.comparison-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.feature-column {
    width: 200px;
    font-weight: 600;
    background: rgba(0, 0, 0, 0.3);
    position: sticky;
    left: 0;
    z-index: 10;
}

.plan-column {
    min-width: 180px;
    text-align: center;
    vertical-align: top;
}

.plan-tier-0 { background: linear-gradient(to bottom, rgba(100, 116, 139, 0.2), transparent); }
.plan-tier-1 { background: linear-gradient(to bottom, rgba(34, 197, 94, 0.2), transparent); }
.plan-tier-2 { background: linear-gradient(to bottom, rgba(168, 85, 247, 0.2), transparent); }
.plan-tier-3 { background: linear-gradient(to bottom, rgba(245, 158, 11, 0.2), transparent); }

.plan-header {
    padding: 1rem 0;
}

.plan-header h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #fff;
}

.plan-price {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.5rem;
}

.plan-price span {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.6);
}

.plan-tier-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.section-header td {
    background: rgba(255, 255, 255, 0.05);
    padding: 1.5rem 1rem;
    font-size: 1.1rem;
}

.value-cell {
    text-align: center;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

.check-cell {
    text-align: center;
    font-size: 1.25rem;
}

.admin-back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
}

.admin-back-link:hover {
    opacity: 0.8;
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
