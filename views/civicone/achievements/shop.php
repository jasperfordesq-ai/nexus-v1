<?php
/**
 * CivicOne View: XP Shop
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'XP Shop';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/achievements">Achievements</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">XP Shop</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/achievements" class="govuk-back-link govuk-!-margin-bottom-6">Back to Dashboard</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-store govuk-!-margin-right-2" aria-hidden="true"></i>
            XP Shop
        </h1>
        <p class="govuk-body-l">Redeem your XP for exclusive rewards.</p>
    </div>
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="border: 2px solid #1d70b8; background: #f3f2f1; text-align: center;">
            <p class="govuk-body-s govuk-!-margin-bottom-1" style="color: #505a5f;">Your XP Balance</p>
            <p class="govuk-heading-l govuk-!-margin-bottom-0" style="color: #1d70b8;">
                <i class="fa-solid fa-coins" aria-hidden="true"></i>
                <?= number_format($userXP) ?> XP
            </p>
        </div>
    </div>
</div>

<!-- Achievement Navigation -->
<nav class="govuk-!-margin-bottom-6" aria-label="Achievement sections">
    <ul class="govuk-list" style="display: flex; gap: 0.5rem; flex-wrap: wrap; padding: 0; margin: 0;">
        <li><a href="<?= $basePath ?>/achievements" class="govuk-button govuk-button--secondary" data-module="govuk-button">Dashboard</a></li>
        <li><a href="<?= $basePath ?>/achievements/badges" class="govuk-button govuk-button--secondary" data-module="govuk-button">All Badges</a></li>
        <li><a href="<?= $basePath ?>/achievements/challenges" class="govuk-button govuk-button--secondary" data-module="govuk-button">Challenges</a></li>
        <li><a href="<?= $basePath ?>/achievements/collections" class="govuk-button govuk-button--secondary" data-module="govuk-button">Collections</a></li>
        <li><a href="<?= $basePath ?>/achievements/shop" class="govuk-button" data-module="govuk-button">XP Shop</a></li>
    </ul>
</nav>

<div class="shop-wrapper">
    <?php if (empty($items)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <i class="fa-solid fa-store" aria-hidden="true"></i>
            <strong>Shop coming soon</strong>
        </p>
        <p class="govuk-body">Exciting rewards will be available here. Keep earning XP!</p>
    </div>
    <?php else: ?>

    <!-- Category Filters -->
    <div class="govuk-!-margin-bottom-6">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-visually-hidden">Filter by category</legend>
            <div class="govuk-button-group">
                <button type="button" class="govuk-button category-btn active" data-category="all" data-module="govuk-button">All Items</button>
                <button type="button" class="govuk-button govuk-button--secondary category-btn" data-category="boost" data-module="govuk-button">Boosts</button>
                <button type="button" class="govuk-button govuk-button--secondary category-btn" data-category="feature" data-module="govuk-button">Features</button>
                <button type="button" class="govuk-button govuk-button--secondary category-btn" data-category="cosmetic" data-module="govuk-button">Cosmetics</button>
            </div>
        </fieldset>
    </div>

    <div class="govuk-grid-row">
        <?php foreach ($items as $item): ?>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6 shop-item-wrapper"
             data-category="<?= htmlspecialchars($item['category'] ?? 'feature') ?>">
            <div class="shop-item govuk-!-padding-4 <?= $item['owned'] ? 'owned' : '' ?> <?= ($item['xp_cost'] > $userXP && !$item['owned']) ? 'locked' : '' ?>"
                 style="border: 1px solid #b1b4b6; height: 100%; position: relative; <?= $item['owned'] ? 'border-color: #00703c;' : '' ?>">

                <?php if ($item['owned']): ?>
                    <span class="govuk-tag govuk-tag--green" style="position: absolute; top: 0.5rem; right: 0.5rem;">Owned</span>
                <?php elseif ($item['is_limited'] ?? false): ?>
                    <span class="govuk-tag govuk-tag--red" style="position: absolute; top: 0.5rem; right: 0.5rem;">Limited</span>
                <?php elseif ($item['is_new'] ?? false): ?>
                    <span class="govuk-tag govuk-tag--blue" style="position: absolute; top: 0.5rem; right: 0.5rem;">New</span>
                <?php endif; ?>

                <div class="govuk-!-text-align-centre govuk-!-margin-bottom-3" style="font-size: 2.5rem;">
                    <?= $item['icon'] ?? '<i class="fa-solid fa-gift" aria-hidden="true"></i>' ?>
                </div>
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2"><?= htmlspecialchars($item['name']) ?></h3>
                <p class="govuk-body-s" style="color: #505a5f;"><?= htmlspecialchars($item['description']) ?></p>

                <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="govuk-body govuk-!-font-weight-bold" style="color: #1d70b8;">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        <?= number_format($item['xp_cost']) ?>
                    </span>

                    <?php if ($item['owned']): ?>
                        <button type="button" class="govuk-button govuk-button--secondary" disabled data-module="govuk-button">
                            <i class="fa-solid fa-check" aria-hidden="true"></i> Owned
                        </button>
                    <?php elseif ($item['xp_cost'] > $userXP): ?>
                        <button type="button" class="govuk-button govuk-button--secondary" disabled data-module="govuk-button">
                            Need <?= number_format($item['xp_cost'] - $userXP) ?> more
                        </button>
                    <?php else: ?>
                        <button type="button" class="govuk-button" data-module="govuk-button" onclick="confirmPurchase(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>', <?= $item['xp_cost'] ?>, '<?= $item['icon'] ?? '' ?>')">
                            Buy Now
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Purchase Confirmation Modal -->
<div class="purchase-modal" id="purchaseModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div class="govuk-!-padding-6" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; max-width: 400px; width: 90%;" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="govuk-!-text-align-centre govuk-!-margin-bottom-4" style="font-size: 3rem;" id="modalIcon"></div>
        <h2 class="govuk-heading-m govuk-!-text-align-centre" id="modal-title">Confirm Purchase</h2>
        <p class="govuk-body govuk-!-text-align-centre">
            Purchase <strong id="modalItemName"></strong> for <strong id="modalItemCost"></strong> XP?
        </p>
        <div class="govuk-button-group" style="justify-content: center;">
            <button type="button" class="govuk-button govuk-button--secondary" onclick="closeModal()" data-module="govuk-button">Cancel</button>
            <button type="button" class="govuk-button" id="confirmBtn" data-module="govuk-button">Confirm</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="purchase-modal" id="successModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div class="govuk-!-padding-6" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; max-width: 400px; width: 90%;" role="dialog" aria-modal="true" aria-labelledby="success-title">
        <div class="govuk-!-text-align-centre govuk-!-margin-bottom-4" style="font-size: 3rem;" id="successIcon"></div>
        <h2 class="govuk-heading-m govuk-!-text-align-centre" id="success-title">Purchase Complete!</h2>
        <p class="govuk-body govuk-!-text-align-centre" id="successMessage"></p>
        <div class="govuk-!-text-align-centre">
            <button type="button" class="govuk-button" onclick="closeSuccessModal()" data-module="govuk-button">Awesome!</button>
        </div>
    </div>
</div>

<!-- Page-specific JavaScript -->
<script src="/assets/js/civicone-achievements.min.js" defer></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
