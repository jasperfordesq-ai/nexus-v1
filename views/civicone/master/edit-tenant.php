<?php
/**
 * Edit Tenant - Platform Admin
 * GOV.UK Design System (WCAG 2.1 AA)
 */

// Protect the target tenant data from Header/Context overwrites
$targetTenant = $tenant;
$tName = htmlspecialchars($targetTenant['name']);

$pageTitle = 'Configure ' . $tName;
$basePath = \Nexus\Core\TenantContext::getBasePath();

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$feats = json_decode($targetTenant['features'] ?? '[]', true);
$config = json_decode($targetTenant['configuration'] ?? '[]', true);

// Module Definitions for UI
$modules = [
    'listings' => ['icon' => 'fa-list', 'label' => 'Offers & Requests', 'color' => '#1d70b8'],
    'groups' => ['icon' => 'fa-users', 'label' => 'Local Hubs', 'color' => '#912b88'],
    'wallet' => ['icon' => 'fa-wallet', 'label' => 'Wallet & Transactions', 'color' => '#00703c'],
    'volunteering' => ['icon' => 'fa-heart', 'label' => 'Volunteering', 'color' => '#f47738'],
    'events' => ['icon' => 'fa-calendar', 'label' => 'Events', 'color' => '#d53880'],
    'resources' => ['icon' => 'fa-book', 'label' => 'Resource Library', 'color' => '#5694ca'],
    'polls' => ['icon' => 'fa-chart-bar', 'label' => 'Live Polls', 'color' => '#28a197'],
    'goals' => ['icon' => 'fa-trophy', 'label' => 'Goal Buddy', 'color' => '#b58840'],
    'blog' => ['icon' => 'fa-pen', 'label' => 'News / Blog', 'color' => '#4c2c92'],
];
?>

<div class="govuk-width-container">
    <nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/super-admin">Platform Master</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">Configure <?= $tName ?></li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper">
        <!-- Header -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-cog govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Configure <?= $tName ?>
                </h1>
                <p class="govuk-body-l">Manage sub-account settings</p>
            </div>
            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                <a href="<?= $basePath ?>/super-admin" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-arrow-left govuk-!-margin-right-2" aria-hidden="true"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (!empty($_GET['debug'])): ?>
            <div class="govuk-notification-banner" role="region" aria-labelledby="debug-banner-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="debug-banner-title">Debug Config</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading"><?= htmlspecialchars($targetTenant['configuration']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= $basePath ?>/super-admin/tenant/update" method="POST">
            <input type="hidden" name="id" value="<?= $targetTenant['id'] ?>">

            <!-- General Settings -->
            <div class="govuk-!-margin-bottom-8" style="border: 1px solid #b1b4b6;">
                <div class="govuk-!-padding-4 civicone-panel-bg" style="border-bottom: 1px solid #b1b4b6;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; border-radius: 8px; background: #1d70b8; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fa-solid fa-cog" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-0">General Settings</h2>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Core configuration for this timebank</p>
                        </div>
                    </div>
                </div>

                <div class="govuk-!-padding-4">
                    <!-- Basic Information -->
                    <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-info-circle govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                            Basic Information
                        </h3>

                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="name">TimeBank Name</label>
                                    <input type="text" name="name" id="name" class="govuk-input" value="<?= $tName ?>" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="slug">URL Slug</label>
                                    <div style="display: flex;">
                                        <span class="govuk-!-padding-2" style="background: white; border: 2px solid #0b0c0c; border-right: none; color: #505a5f; font-family: monospace;">platform.url/</span>
                                        <input type="text" name="slug" id="slug" class="govuk-input" style="border-radius: 0;" value="<?= htmlspecialchars($targetTenant['slug']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="domain">Custom Domain (Optional)</label>
                                    <input type="text" name="domain" id="domain" class="govuk-input" value="<?= htmlspecialchars($targetTenant['domain'] ?? '') ?>" placeholder="e.g. timebank.cork.ie">
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="tagline">Tagline</label>
                                    <input type="text" name="tagline" id="tagline" class="govuk-input" value="<?= htmlspecialchars($targetTenant['tagline'] ?? '') ?>" placeholder="e.g. Connecting the community">
                                </div>
                            </div>
                        </div>

                        <div class="govuk-form-group">
                            <label class="govuk-label" for="description">Description</label>
                            <textarea name="description" id="description" class="govuk-textarea" rows="3"><?= htmlspecialchars($targetTenant['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Footer & Legal -->
                    <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #505a5f;">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-file-contract govuk-!-margin-right-2" style="color: #505a5f;" aria-hidden="true"></i>
                            Footer & Legal Documents
                        </h3>

                        <div class="govuk-form-group">
                            <label class="govuk-label" for="footer_text">Tenant Footer Text</label>
                            <textarea name="footer_text" id="footer_text" class="govuk-textarea" rows="2" placeholder="e.g. Registered Charity Number: 12345"><?= htmlspecialchars($config['footer_text'] ?? '') ?></textarea>
                            <div class="govuk-hint">This text will appear above the footer on all public pages.</div>
                        </div>

                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="privacy_text">Privacy Policy</label>
                                    <textarea name="privacy_text" id="privacy_text" class="govuk-textarea" rows="6" placeholder="Enter custom Privacy Policy text..."><?= htmlspecialchars($config['privacy_text'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="terms_text">Terms of Service</label>
                                    <textarea name="terms_text" id="terms_text" class="govuk-textarea" rows="6" placeholder="Enter custom Terms of Service text..."><?= htmlspecialchars($config['terms_text'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="govuk-hint">If left blank, the platform default legal pages will be displayed.</div>
                    </div>

                    <!-- Module Installation -->
                    <div class="govuk-!-margin-bottom-4 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #00703c;">
                        <div style="display: flex; justify-content: space-between; align-items: center;" class="govuk-!-margin-bottom-4">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                                <i class="fa-solid fa-puzzle-piece govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
                                Module Installation
                            </h3>
                            <strong class="govuk-tag govuk-tag--grey">Click to toggle</strong>
                        </div>

                        <div class="govuk-grid-row">
                            <?php foreach ($modules as $key => $mod):
                                $isActive = $feats[$key] ?? ($key === 'blog'); // Blog default true
                            ?>
                                <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                                    <label style="display: block; cursor: pointer;">
                                        <div class="govuk-!-padding-3" style="background: <?= $isActive ? 'white' : '#f3f2f1' ?>; border: 2px solid <?= $isActive ? $mod['color'] : '#b1b4b6' ?>; border-left: 5px solid <?= $isActive ? $mod['color'] : '#b1b4b6' ?>;">
                                            <input type="checkbox" name="feat_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> style="position: absolute; opacity: 0;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 36px; height: 36px; border-radius: 8px; background: <?= $isActive ? $mod['color'] : '#b1b4b6' ?>; display: flex; align-items: center; justify-content: center; color: white;">
                                                    <i class="fa-solid <?= $mod['icon'] ?>" aria-hidden="true"></i>
                                                </div>
                                                <div>
                                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= $mod['label'] ?></p>
                                                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: <?= $isActive ? '#00703c' : '#505a5f' ?>;">
                                                        <?= $isActive ? 'Active Module' : 'Not Installed' ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="govuk-!-text-align-right">
                        <button type="submit" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-save govuk-!-margin-right-2" aria-hidden="true"></i>
                            Save Tenant Configuration
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Tenant Administrators -->
        <div class="govuk-!-margin-bottom-8" style="border: 1px solid #b1b4b6;">
            <div class="govuk-!-padding-4 civicone-panel-bg" style="border-bottom: 1px solid #b1b4b6;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #00703c; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Tenant Administrators</h2>
                        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Manage access for this sub-account</p>
                    </div>
                </div>
            </div>

            <div class="govuk-!-padding-4">
                <!-- Current Admins -->
                <div class="govuk-!-margin-bottom-6">
                    <?php if (empty($admins)): ?>
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">Warning</span>
                                No admins assigned! This TimeBank cannot be managed by anyone properly.
                            </strong>
                        </div>
                    <?php else: ?>
                        <div class="govuk-grid-row">
                            <?php foreach ($admins as $a): ?>
                                <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                                    <div class="govuk-!-padding-3 civicone-panel-bg" style="border-left: 5px solid #00703c;">
                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #00703c; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                    <?= strtoupper(substr($a['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= htmlspecialchars($a['name']) ?></p>
                                                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= htmlspecialchars($a['email']) ?></p>
                                                </div>
                                            </div>
                                            <form action="<?= $basePath ?>/super-admin/tenant/delete-admin" method="POST" onsubmit="return confirm('Revoke access for this admin?');">
                                                <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                                                <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button" style="padding: 6px 10px;" aria-label="Remove admin">
                                                    <i class="fa-solid fa-times" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add New Admin -->
                <div class="govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                        <i class="fa-solid fa-user-plus govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                        Grant Access to New Admin
                    </h3>

                    <form action="<?= $basePath ?>/super-admin/admin/add" method="POST">
                        <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">

                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="admin_name_new">Full Name</label>
                                    <input type="text" name="name" id="admin_name_new" class="govuk-input" placeholder="e.g. John Doe" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="admin_email_new">Email Address</label>
                                    <input type="email" name="email" id="admin_email_new" class="govuk-input" placeholder="john@example.com" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="admin_password_new">Password</label>
                                    <input type="password" name="password" id="admin_password_new" class="govuk-input" placeholder="Create password" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group">
                                    <label class="govuk-label">&nbsp;</label>
                                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" style="width: 100%;">
                                        <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                        Grant Access
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="govuk-!-padding-4" style="background: #d4351c15; border: 2px solid #d4351c;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 8px; background: #d4351c; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-0" style="color: #d4351c;">Danger Zone</h2>
                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Tenant deletion is currently disabled. Contact system administrator.</p>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
