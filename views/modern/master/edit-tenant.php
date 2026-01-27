<?php
// Phoenix View: Edit Tenant (Super Admin)
// Path: views/modern/master/edit-tenant.php
//
// CSS extracted to: httpdocs/assets/css/modern-template-extracts.css
// Section: views/modern/master/edit-tenant.php

// Protect the target tenant data from Header/Context overwrites
$targetTenant = $tenant;
$controllerTenantId = $tenant['id'] ?? 'NULL';

$tName = htmlspecialchars($targetTenant['name']);

$hTitle = 'Configure ' . $tName;
$hSubtitle = 'Manage Sub-Account Settings';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Super Admin';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

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
    <div class="mte-edit-tenant--container">

        <!-- Main Configuration Card -->
        <div class="nexus-card">
            <header class="nexus-card-header mte-edit-tenant--card-header">
                <div class="mte-edit-tenant--header-left">
                    <div class="mte-edit-tenant--icon-box mte-edit-tenant--icon-box-primary">⚙️</div>
                    <div>
                        <h3 class="mte-edit-tenant--header-title">General Settings</h3>
                        <div class="mte-edit-tenant--header-subtitle">Core configuration for this timebank</div>
                    </div>
                </div>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin" class="nexus-btn nexus-btn-sm nexus-btn-secondary">Back to Dashboard</a>
            </header>

            <div class="nexus-card-body mte-edit-tenant--card-body">
                <?php if (!empty($_GET['debug'])): ?>
                    <div class="nexus-alert nexus-alert-info">
                        <strong>Debug Config:</strong> <?= htmlspecialchars($targetTenant['configuration']) ?>
                    </div>
                <?php endif; ?>

                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/tenant/update" method="POST">
                    <input type="hidden" name="id" value="<?= $targetTenant['id'] ?>">

                    <!-- Inner Box: Basic Info -->
                    <div class="mte-edit-tenant--inner-box">
                        <h4 class="mte-edit-tenant--section-title">Basic Information</h4>

                        <div class="mte-edit-tenant--form-grid">
                            <div>
                                <label class="mte-edit-tenant--form-label">TimeBank Name</label>
                                <input type="text" name="name" class="nexus-input" value="<?= $tName ?>" required>
                            </div>
                            <div>
                                <label class="mte-edit-tenant--form-label">URL Slug</label>
                                <div class="mte-edit-tenant--input-group">
                                    <span class="mte-edit-tenant--input-prefix">platform.url/</span>
                                    <input type="text" name="slug" class="nexus-input mte-edit-tenant--input-suffix" value="<?= htmlspecialchars($targetTenant['slug']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mte-edit-tenant--form-grid">
                            <div>
                                <label class="mte-edit-tenant--form-label">Custom Domain (Optional)</label>
                                <input type="text" name="domain" class="nexus-input" value="<?= htmlspecialchars($targetTenant['domain'] ?? '') ?>" placeholder="e.g. timebank.cork.ie">
                            </div>

                            <div>
                                <label class="mte-edit-tenant--form-label">Tagline</label>
                                <input type="text" name="tagline" class="nexus-input" value="<?= htmlspecialchars($targetTenant['tagline'] ?? '') ?>" placeholder="e.g. Connecting the community">
                            </div>
                        </div>

                        <label class="mte-edit-tenant--form-label">Description</label>
                        <textarea name="description" class="nexus-input" rows="3"><?= htmlspecialchars($targetTenant['description'] ?? '') ?></textarea>
                    </div>

                    <?php $config = json_decode($targetTenant['configuration'] ?? '[]', true); ?>
                    <div>
                        <label class="mte-edit-tenant--form-label">Tenant Footer Text</label>
                        <textarea name="footer_text" class="nexus-input" rows="2" placeholder="e.g. Registered Charity Number: 12345"><?= htmlspecialchars($config['footer_text'] ?? '') ?></textarea>
                        <p class="mte-edit-tenant--form-hint">This text will appear above the footer on all public pages.</p>
                    </div>

                    <!-- Legal Docs -->
                    <div class="mte-edit-tenant--section-divider">
                        <h4 class="mte-edit-tenant--section-title">Legal Documents</h4>

                        <div class="mte-edit-tenant--form-grid">
                            <div>
                                <label class="mte-edit-tenant--form-label">Privacy Policy</label>
                                <textarea name="privacy_text" class="nexus-input" rows="8" placeholder="Enter custom Privacy Policy text..."><?= htmlspecialchars($config['privacy_text'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="mte-edit-tenant--form-label">Terms of Service</label>
                                <textarea name="terms_text" class="nexus-input" rows="8" placeholder="Enter custom Terms of Service text..."><?= htmlspecialchars($config['terms_text'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <p class="mte-edit-tenant--form-hint">If left blank, the platform default legal pages will be displayed.</p>
                    </div>
            </div>

            <!-- Module Grid -->
            <div class="mte-edit-tenant--module-section">
                <div class="mte-edit-tenant--module-header">
                    <h4 class="mte-edit-tenant--module-title">📦 Module Installation</h4>
                    <span class="mte-edit-tenant--module-badge">Drag & Drop Disabled</span>
                </div>

                <div class="mte-edit-tenant--module-grid">
                    <?php foreach ($modules as $key => $mod):
                        $isActive = $feats[$key] ?? ($key === 'blog');
                    ?>
                        <label class="mte-edit-tenant--module-card" data-active="<?= $isActive ? 'true' : 'false' ?>">
                            <input type="checkbox" name="feat_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> class="mte-edit-tenant--module-checkbox">
                            <div class="mte-edit-tenant--module-info">
                                <div class="mte-edit-tenant--module-name"><?= $mod['label'] ?></div>
                                <div class="mte-edit-tenant--module-status">
                                    <?= $isActive ? 'Active Module' : 'Not Installed' ?>
                                </div>
                            </div>
                            <div class="mte-edit-tenant--module-icon-box">
                                <span class="dashicons dashicons-<?= $mod['icon'] ?> mte-edit-tenant--module-icon"></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mte-edit-tenant--submit-row">
                <button type="submit" class="nexus-btn nexus-btn-primary mte-edit-tenant--submit-btn">
                    Save Tenant Configuration
                </button>
            </div>
            </form>
        </div>
    </div>

    <!-- Tenant Admins Section -->
    <div class="nexus-card">
        <header class="nexus-card-header mte-edit-tenant--card-header">
            <div class="mte-edit-tenant--header-left">
                <div class="mte-edit-tenant--icon-box mte-edit-tenant--icon-box-success">🛡️</div>
                <div>
                    <h3 class="mte-edit-tenant--header-title">Tenant Administrators</h3>
                    <div class="mte-edit-tenant--header-subtitle">Manage access for this sub-account</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body mte-edit-tenant--card-body">
            <!-- Inner Box for Admins -->
            <div class="mte-edit-tenant--inner-box">
                <div class="mte-edit-tenant--admin-grid">
                    <?php if (empty($admins)): ?>
                        <div class="mte-edit-tenant--admin-empty">
                            No admins assigned! This TimeBank cannot be managed by anyone properly.
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $a): ?>
                            <div class="mte-edit-tenant--admin-card">
                                <div class="mte-edit-tenant--admin-info">
                                    <div class="mte-edit-tenant--admin-avatar">
                                        <?= strtoupper(substr($a['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="mte-edit-tenant--admin-name"><?= htmlspecialchars($a['name']) ?></div>
                                        <div class="mte-edit-tenant--admin-email"><?= htmlspecialchars($a['email']) ?></div>
                                    </div>
                                </div>
                                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/tenant/delete-admin" method="POST" onsubmit="return confirm('Revoke access for this admin?');">
                                    <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="mte-edit-tenant--admin-delete-btn">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mte-edit-tenant--add-admin-section">
                <h5 class="mte-edit-tenant--add-admin-title">Grant Access to New Admin</h5>
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/admin/add" method="POST" class="mte-edit-tenant--add-admin-form">
                    <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                    <div class="mte-edit-tenant--add-admin-grid">
                        <div>
                            <label class="mte-edit-tenant--add-admin-label">Full Name</label>
                            <input type="text" name="name" class="nexus-input" placeholder="e.g. John Doe" required>
                        </div>
                        <div>
                            <label class="mte-edit-tenant--add-admin-label">Email Address</label>
                            <input type="email" name="email" class="nexus-input" placeholder="john@example.com" required>
                        </div>
                        <div>
                            <label class="mte-edit-tenant--add-admin-label">Password</label>
                            <input type="password" name="password" class="nexus-input" placeholder="Create generic password" required>
                        </div>
                        <button type="submit" class="nexus-btn nexus-btn-secondary mte-edit-tenant--add-admin-submit">Grant Access</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="mte-edit-tenant--danger-zone">
        <div class="mte-edit-tenant--danger-box">
            <div class="mte-edit-tenant--danger-title">⚠️ Danger Zone</div>
            <div class="mte-edit-tenant--danger-text">Tenant deletion is currently disabled. Contact system administrator.</div>
        </div>
    </div>

</div>
</div>


<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
