<?php
// Phoenix View: Edit Tenant (Super Admin)
// Path: views/modern/master/edit-tenant.php

// Protect the target tenant data from Header/Context overwrites
// Protect the target tenant data from Header/Context overwrites
$targetTenant = $tenant;
$controllerTenantId = $tenant['id'] ?? 'NULL';

$tName = htmlspecialchars($targetTenant['name']);

$tName = htmlspecialchars($targetTenant['name']);
$hTitle = 'Configure ' . $tName;
$hSubtitle = 'Manage Sub-Account Settings';
$hGradient = 'mt-hero-gradient-brand'; // Use brand gradient
$hType = 'Super Admin';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$feats = json_decode($targetTenant['features'] ?? '[]', true);

// Module Definitions for UI
$modules = [
    'listings' => ['icon' => 'list-view', 'label' => 'Offers & Requests', 'color' => 'blue'],
    'groups' => ['icon' => 'groups', 'label' => 'Local Hubs', 'color' => 'purple'],
    'wallet' => ['icon' => 'money', 'label' => 'Wallet & Transactions', 'color' => 'green'],
    'volunteering' => ['icon' => 'heart', 'label' => 'Volunteering', 'color' => 'orange'],
    'events' => ['icon' => 'calendar-alt', 'label' => 'Events', 'color' => 'pink'],
    'resources' => ['icon' => 'book', 'label' => 'Resource Library', 'color' => 'cyan'],
    'polls' => ['icon' => 'chart-bar', 'label' => 'Live Polls', 'color' => 'teal'],
    'goals' => ['icon' => 'awards', 'label' => 'Goal Buddy', 'color' => 'rose'],
    'blog' => ['icon' => 'welcome-write-blog', 'label' => 'News / Blog', 'color' => 'indigo'],
];
?>

<div class="super-admin-wrapper">

    <!-- Centered Container for Intelligent Layout -->
    <div class="edit-tenant-container">

        <!-- Main Configuration Card -->
        <div class="nexus-card">
            <header class="nexus-card-header card-header-flex">
                <div class="card-header-left">
                    <div class="card-header-icon card-header-icon--primary">&#9881;</div>
                    <div>
                        <h3 class="card-header-title">General Settings</h3>
                        <div class="card-header-subtitle">Core configuration for this timebank</div>
                    </div>
                </div>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin" class="nexus-btn nexus-btn-sm nexus-btn-secondary">Back to Dashboard</a>
            </header>

            <div class="nexus-card-body card-body-padded">
                <?php if (!empty($_GET['debug'])): ?>
                    <div class="nexus-alert nexus-alert-info">
                        <strong>Debug Config:</strong> <?= htmlspecialchars($targetTenant['configuration']) ?>
                    </div>
                <?php endif; ?>

                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/tenant/update" method="POST">
                    <input type="hidden" name="id" value="<?= $targetTenant['id'] ?>">

                    <!-- Inner Box: Basic Info -->
                    <div class="form-section-box">
                        <h4 class="form-section-title">Basic Information</h4>

                        <div class="form-grid-2col">
                            <div>
                                <label class="form-label">TimeBank Name</label>
                                <input type="text" name="name" class="nexus-input" value="<?= $tName ?>" required>
                            </div>
                            <div>
                                <label class="form-label">URL Slug</label>
                                <div class="input-group-url">
                                    <span class="input-group-url__prefix">platform.url/</span>
                                    <input type="text" name="slug" class="nexus-input" value="<?= htmlspecialchars($targetTenant['slug']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid-2col">
                            <div>
                                <label class="form-label">Custom Domain (Optional)</label>
                                <input type="text" name="domain" class="nexus-input" value="<?= htmlspecialchars($targetTenant['domain'] ?? '') ?>" placeholder="e.g. timebank.cork.ie">
                            </div>

                            <div>
                                <label class="form-label">Tagline</label>
                                <input type="text" name="tagline" class="nexus-input" value="<?= htmlspecialchars($targetTenant['tagline'] ?? '') ?>" placeholder="e.g. Connecting the community">
                            </div>
                        </div>

                        <label class="form-label">Description</label>
                        <textarea name="description" class="nexus-input" rows="3"><?= htmlspecialchars($targetTenant['description'] ?? '') ?></textarea>
                    </div>

                    <?php $config = json_decode($targetTenant['configuration'] ?? '[]', true); ?>
                    <div class="form-divider">
                        <label class="form-label">Tenant Footer Text</label>
                        <textarea name="footer_text" class="nexus-input" rows="2" placeholder="e.g. Registered Charity Number: 12345"><?= htmlspecialchars($config['footer_text'] ?? '') ?></textarea>
                        <p class="form-help-text">This text will appear above the footer on all public pages.</p>
                    </div>

                    <!-- Legal Docs -->
                    <div class="form-divider--section">
                        <h4 class="form-section-title form-section-title--no-border">Legal Documents</h4>

                        <div class="form-grid-2col-gap30">
                            <div>
                                <label class="form-label">Privacy Policy</label>
                                <textarea name="privacy_text" class="nexus-input" rows="8" placeholder="Enter custom Privacy Policy text..."><?= htmlspecialchars($config['privacy_text'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="form-label">Terms of Service</label>
                                <textarea name="terms_text" class="nexus-input" rows="8" placeholder="Enter custom Terms of Service text..."><?= htmlspecialchars($config['terms_text'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <p class="form-help-text form-help-text--mt10">If left blank, the platform default legal pages will be displayed.</p>
                    </div>
            </div>

            <!-- Module Grid -->
            <div class="module-section">
                <div class="module-section-header">
                    <h4 class="module-section-title">&#128230; Module Installation</h4>
                    <span class="module-section-badge">Drag & Drop Disabled</span>
                </div>

                <div class="module-grid">
                    <?php foreach ($modules as $key => $mod):
                        $isActive = $feats[$key] ?? ($key === 'blog'); // Blog default true
                    ?>
                        <label class="module-card <?= $isActive ? 'module-card--active' : '' ?>">
                            <input type="checkbox" name="feat_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> class="module-card__checkbox">
                            <div class="module-card__content">
                                <div class="module-card__label"><?= $mod['label'] ?></div>
                                <div class="module-card__status <?= $isActive ? 'module-card__status--active' : '' ?>">
                                    <?= $isActive ? 'Active Module' : 'Not Installed' ?>
                                </div>
                            </div>
                            <div class="module-card__icon <?= $isActive ? 'module-card__icon--active' : '' ?>">
                                <span class="dashicons dashicons-<?= $mod['icon'] ?>"></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions-right">
                <button type="submit" class="nexus-btn nexus-btn-primary btn-submit-primary">
                    Save Tenant Configuration
                </button>
            </div>
            </form>
        </div>
    </div>

    <!-- Tenant Admins Section (Refactored to match) -->
    <div class="nexus-card">
        <header class="nexus-card-header card-header-flex">
            <div class="card-header-left">
                <div class="card-header-icon card-header-icon--success">&#128737;</div>
                <div>
                    <h3 class="card-header-title">Tenant Administrators</h3>
                    <div class="card-header-subtitle">Manage access for this sub-account</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body card-body-padded">
            <!-- Inner Box for Admins -->
            <div class="form-section-box form-section-box--admin">
                <div class="form-grid-auto">
                    <?php if (empty($admins)): ?>
                        <div class="admin-alert-warning">
                            No admins assigned! This TimeBank cannot be managed by anyone properly.
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $a): ?>
                            <div class="admin-card">
                                <div class="admin-card__info">
                                    <div class="admin-card__avatar">
                                        <?= strtoupper(substr($a['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="admin-card__name"><?= htmlspecialchars($a['name']) ?></div>
                                        <div class="admin-card__email"><?= htmlspecialchars($a['email']) ?></div>
                                    </div>
                                </div>
                                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/tenant/delete-admin" method="POST" onsubmit="return confirm('Revoke access for this admin?');" class="admin-card__delete-form">
                                    <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="admin-card__delete-btn">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-divider--admin">
                <h5 class="grant-access-title">Grant Access to New Admin</h5>
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/admin/add" method="POST" class="grant-access-form">
                    <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                    <div class="form-grid-4col">
                        <div>
                            <label class="form-label--small">Full Name</label>
                            <input type="text" name="name" class="nexus-input" placeholder="e.g. John Doe" required>
                        </div>
                        <div>
                            <label class="form-label--small">Email Address</label>
                            <input type="email" name="email" class="nexus-input" placeholder="john@example.com" required>
                        </div>
                        <div>
                            <label class="form-label--small">Password</label>
                            <input type="password" name="password" class="nexus-input" placeholder="Create generic password" required>
                        </div>
                        <button type="submit" class="nexus-btn nexus-btn-secondary btn-grant-access">Grant Access</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="danger-zone-wrapper">
        <div class="danger-zone-box">
            <div class="danger-zone-title">&#9888; Danger Zone</div>
            <div class="danger-zone-text">Tenant deletion is currently disabled. Contact system administrator.</div>
        </div>
    </div>

</div>
</div>

<!-- Master Edit Tenant CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-master-edit-tenant.min.css">
