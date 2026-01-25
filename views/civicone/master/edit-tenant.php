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

// Module Definitions for UI (using CSS class suffixes)
$modules = [
    'listings' => ['icon' => 'fa-list', 'label' => 'Offers & Requests', 'colorClass' => 'blue'],
    'groups' => ['icon' => 'fa-users', 'label' => 'Local Hubs', 'colorClass' => 'purple'],
    'wallet' => ['icon' => 'fa-wallet', 'label' => 'Wallet & Transactions', 'colorClass' => 'green'],
    'volunteering' => ['icon' => 'fa-heart', 'label' => 'Volunteering', 'colorClass' => 'orange'],
    'events' => ['icon' => 'fa-calendar', 'label' => 'Events', 'colorClass' => 'pink'],
    'resources' => ['icon' => 'fa-book', 'label' => 'Resource Library', 'colorClass' => 'light-blue'],
    'polls' => ['icon' => 'fa-chart-bar', 'label' => 'Live Polls', 'colorClass' => 'teal'],
    'goals' => ['icon' => 'fa-trophy', 'label' => 'Goal Buddy', 'colorClass' => 'brown'],
    'blog' => ['icon' => 'fa-pen', 'label' => 'News / Blog', 'colorClass' => 'dark-purple'],
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
                    <i class="fa-solid fa-cog govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
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
            <div class="govuk-!-margin-bottom-8 civicone-section-card">
                <div class="govuk-!-padding-4 civicone-panel-bg civicone-section-card-header">
                    <div class="civicone-section-header-flex">
                        <div class="civicone-section-icon civicone-section-icon--blue">
                            <i class="fa-solid fa-cog" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2 class="govuk-heading-m govuk-!-margin-bottom-0">General Settings</h2>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Core configuration for this timebank</p>
                        </div>
                    </div>
                </div>

                <div class="govuk-!-padding-4">
                    <!-- Basic Information -->
                    <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg civicone-border-left-blue">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-info-circle govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
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
                                    <div class="civicone-slug-group">
                                        <span class="govuk-!-padding-2 civicone-slug-prefix">platform.url/</span>
                                        <input type="text" name="slug" id="slug" class="govuk-input civicone-slug-input" value="<?= htmlspecialchars($targetTenant['slug']) ?>" required>
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
                    <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg civicone-border-left-grey">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-file-contract govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
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
                    <div class="govuk-!-margin-bottom-4 govuk-!-padding-4 civicone-panel-bg civicone-border-left-green">
                        <div class="civicone-section-header-flex--between govuk-!-margin-bottom-4">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                                <i class="fa-solid fa-puzzle-piece govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                                Module Installation
                            </h3>
                            <strong class="govuk-tag govuk-tag--grey">Click to toggle</strong>
                        </div>

                        <div class="govuk-grid-row">
                            <?php foreach ($modules as $key => $mod):
                                $isActive = $feats[$key] ?? ($key === 'blog'); // Blog default true
                                $borderClass = $isActive ? "civicone-border-left-{$mod['colorClass']}-sm" : 'civicone-border-left-grey-sm';
                                $iconClass = $isActive ? "civicone-section-icon--{$mod['colorClass']}" : 'civicone-section-icon--grey';
                            ?>
                                <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                                    <label class="civicone-module-card">
                                        <div class="civicone-module-card-inner <?= $isActive ? 'civicone-module-card--active' : 'civicone-module-card--inactive' ?> <?= $borderClass ?>">
                                            <input type="checkbox" name="feat_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> class="civicone-checkbox-hidden">
                                            <div class="civicone-section-header-flex">
                                                <div class="civicone-section-icon civicone-section-icon--sm <?= $iconClass ?>">
                                                    <i class="fa-solid <?= $mod['icon'] ?>" aria-hidden="true"></i>
                                                </div>
                                                <div>
                                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= $mod['label'] ?></p>
                                                    <p class="govuk-body-s govuk-!-margin-bottom-0 <?= $isActive ? 'civicone-text-success' : 'civicone-secondary-text' ?>">
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
        <div class="govuk-!-margin-bottom-8 civicone-section-card">
            <div class="govuk-!-padding-4 civicone-panel-bg civicone-section-card-header">
                <div class="civicone-section-header-flex">
                    <div class="civicone-section-icon civicone-section-icon--green">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Tenant Administrators</h2>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Manage access for this sub-account</p>
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
                                    <div class="govuk-!-padding-3 civicone-panel-bg civicone-border-left-green">
                                        <div class="civicone-admin-card">
                                            <div class="civicone-admin-info">
                                                <div class="civicone-admin-avatar">
                                                    <?= strtoupper(substr($a['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= htmlspecialchars($a['name']) ?></p>
                                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text"><?= htmlspecialchars($a['email']) ?></p>
                                                </div>
                                            </div>
                                            <form action="<?= $basePath ?>/super-admin/tenant/delete-admin" method="POST" onsubmit="return confirm('Revoke access for this admin?');">
                                                <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                                                <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 civicone-btn-xs" data-module="govuk-button" aria-label="Remove admin">
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
                <div class="govuk-!-padding-4 civicone-panel-bg civicone-border-left-blue">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                        <i class="fa-solid fa-user-plus govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
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
                                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-full" data-module="govuk-button">
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
        <div class="govuk-!-padding-4 civicone-danger-zone">
            <div class="civicone-section-header-flex">
                <div class="civicone-section-icon civicone-section-icon--red">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-0 civicone-heading-red">Danger Zone</h2>
                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Tenant deletion is currently disabled. Contact system administrator.</p>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
