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
    <div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 40px;">

        <!-- Main Configuration Card -->
        <div class="nexus-card">
            <header class="nexus-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="background:var(--primary); color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">⚙️</div>
                    <div>
                        <h3 style="margin:0; font-size:1.1rem;">General Settings</h3>
                        <div style="font-size:0.85rem; color:var(--nexus-text-muted);">Core configuration for this timebank</div>
                    </div>
                </div>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin" class="nexus-btn nexus-btn-sm nexus-btn-secondary">Back to Dashboard</a>
            </header>

            <div class="nexus-card-body" style="padding: 30px;">
                <?php if (!empty($_GET['debug'])): ?>
                    <div class="nexus-alert nexus-alert-info">
                        <strong>Debug Config:</strong> <?= htmlspecialchars($targetTenant['configuration']) ?>
                    </div>
                <?php endif; ?>

                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/tenant/update" method="POST">
                    <input type="hidden" name="id" value="<?= $targetTenant['id'] ?>">

                    <!-- Inner Box: Basic Info -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                        <h4 style="margin:0 0 20px 0; color:#475569; font-size:0.95rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Basic Information</h4>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">TimeBank Name</label>
                                <input type="text" name="name" class="nexus-input" value="<?= $tName ?>" required>
                            </div>
                            <div>
                                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">URL Slug</label>
                                <div style="display:flex; align-items:center;">
                                    <span style="background:#fff; padding:12px 15px; border:1px solid #e2e8f0; border-right:0; border-radius:10px 0 0 10px; color:#6b7280; font-family: monospace;">platform.url/</span>
                                    <input type="text" name="slug" class="nexus-input" value="<?= htmlspecialchars($targetTenant['slug']) ?>" required style="border-radius: 0 10px 10px 0;">
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Custom Domain (Optional)</label>
                                <input type="text" name="domain" class="nexus-input" value="<?= htmlspecialchars($targetTenant['domain'] ?? '') ?>" placeholder="e.g. timebank.cork.ie">
                            </div>

                            <div>
                                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Tagline</label>
                                <input type="text" name="tagline" class="nexus-input" value="<?= htmlspecialchars($targetTenant['tagline'] ?? '') ?>" placeholder="e.g. Connecting the community">
                            </div>
                        </div>

                        <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Description</label>
                        <textarea name="description" class="nexus-input" rows="3"><?= htmlspecialchars($targetTenant['description'] ?? '') ?></textarea>
                    </div>

                    <?php $config = json_decode($targetTenant['configuration'] ?? '[]', true); ?>
                    <div style="margin-top: 20px;">
                        <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Tenant Footer Text</label>
                        <textarea name="footer_text" class="nexus-input" rows="2" placeholder="e.g. Registered Charity Number: 12345"><?= htmlspecialchars($config['footer_text'] ?? '') ?></textarea>
                        <p style="font-size: 0.8rem; color: #6b7280; margin-top: 5px;">This text will appear above the footer on all public pages.</p>
                    </div>

                    <!-- Legal Docs -->
                    <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <h4 style="margin:0 0 20px 0; color:#475569; font-size:0.95rem; text-transform:uppercase; letter-spacing:0.5px;">Legal Documents</h4>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                            <div>
                                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Privacy Policy</label>
                                <textarea name="privacy_text" class="nexus-input" rows="8" placeholder="Enter custom Privacy Policy text..."><?= htmlspecialchars($config['privacy_text'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Terms of Service</label>
                                <textarea name="terms_text" class="nexus-input" rows="8" placeholder="Enter custom Terms of Service text..."><?= htmlspecialchars($config['terms_text'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <p style="font-size: 0.8rem; color: #6b7280; margin-top: 10px;">If left blank, the platform default legal pages will be displayed.</p>
                    </div>
            </div>

            <!-- Module Grid -->
            <div style="margin-bottom: 30px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h4 style="margin:0; font-size:1rem;">📦 Module Installation</h4>
                    <span style="font-size:0.8rem; background:#eff6ff; color:#1d4ed8; padding:2px 8px; border-radius:4px;">Drag & Drop Disabled</span>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:15px;">
                    <?php foreach ($modules as $key => $mod):
                        $isActive = $feats[$key] ?? ($key === 'blog'); // Blog default true
                    ?>
                        <label style="
                                    display: flex; 
                                    align-items: center; 
                                    padding: 15px; 
                                    border: 1px solid <?= $isActive ? '#4f46e5' : '#e5e7eb' ?>; 
                                    border-radius: 12px; 
                                    background: <?= $isActive ? 'rgba(79, 70, 229, 0.05)' : '#fff' ?>; 
                                    cursor: pointer;
                                    transition: all 0.2s;
                                    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                                ">
                            <input type="checkbox" name="feat_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> style="margin-right: 15px; width: 20px; height: 20px; accent-color: #4f46e5;">
                            <div style="flex:1;">
                                <div style="font-weight:600; font-size:0.95rem; color:#1f2937;"><?= $mod['label'] ?></div>
                                <div style="font-size:0.8rem; color:<?= $isActive ? '#4f46e5' : '#9ca3af' ?>;">
                                    <?= $isActive ? 'Active Module' : 'Not Installed' ?>
                                </div>
                            </div>
                            <div style="background:<?= $isActive ? '#e0e7ff' : '#f3f4f6' ?>; width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                                <span class="dashicons dashicons-<?= $mod['icon'] ?>" style="font-size: 20px; color: <?= $isActive ? '#4f46e5' : '#9ca3af' ?>;"></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="text-align:right;">
                <button type="submit" class="nexus-btn nexus-btn-primary" style="padding: 12px 30px; font-size:1rem; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);">
                    Save Tenant Configuration
                </button>
            </div>
            </form>
        </div>
    </div>

    <!-- Tenant Admins Section (Refactored to match) -->
    <div class="nexus-card">
        <header class="nexus-card-header" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:#10b981; color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🛡️</div>
                <div>
                    <h3 style="margin:0; font-size:1.1rem;">Tenant Administrators</h3>
                    <div style="font-size:0.85rem; color:var(--nexus-text-muted);">Manage access for this sub-account</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body" style="padding: 30px;">
            <!-- Inner Box for Admins -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php if (empty($admins)): ?>
                        <div style="grid-column: 1 / -1; padding: 15px; background: #fff1f2; color: #be123c; border-radius: 8px; font-size: 0.9rem; text-align: center; border:1px solid #fecdd3;">
                            No admins assigned! This TimeBank cannot be managed by anyone properly.
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $a): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                                <div style="display:flex; align-items:center; gap:12px; overflow: hidden;">
                                    <div style="width:36px; height:36px; background:#f3f4f6; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#6b7280; font-weight:bold;">
                                        <?= strtoupper(substr($a['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.95rem; color:#1f2937;"><?= htmlspecialchars($a['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($a['email']) ?></div>
                                    </div>
                                </div>
                                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/tenant/delete-admin" method="POST" onsubmit="return confirm('Revoke access for this admin?');" style="margin:0;">
                                    <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:#ef4444; font-size:1.4rem; cursor:pointer; line-height: 1; padding:0 5px;">&times;</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div style="border-top: 1px solid #e5e7eb; padding-top: 25px;">
                <h5 style="margin:0 0 15px 0; color:#374151;">Grant Access to New Admin</h5>
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/admin/add" method="POST" style="background: #fff; padding: 20px; border:1px solid #e2e8f0; border-radius: 12px;">
                    <input type="hidden" name="tenant_id" value="<?= $targetTenant['id'] ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                        <div>
                            <label style="display:block; font-size:0.8rem; color:#6b7280; margin-bottom:5px;">Full Name</label>
                            <input type="text" name="name" class="nexus-input" placeholder="e.g. John Doe" required>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; color:#6b7280; margin-bottom:5px;">Email Address</label>
                            <input type="email" name="email" class="nexus-input" placeholder="john@example.com" required>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; color:#6b7280; margin-bottom:5px;">Password</label>
                            <input type="password" name="password" class="nexus-input" placeholder="Create generic password" required>
                        </div>
                        <button type="submit" class="nexus-btn nexus-btn-secondary" style="height: 42px;">Grant Access</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div style="opacity: 0.7; transition: opacity 0.2s; text-align: center;">
        <div style="display:inline-block; padding: 15px 30px; border: 1px dashed #ef4444; border-radius: 12px; background: #fef2f2;">
            <div style="color: #991b1b; font-weight: 700; font-size: 0.9rem; margin-bottom: 5px;">⚠️ Danger Zone</div>
            <div style="font-size: 0.8rem; color: #b91c1c;">Tenant deletion is currently disabled. Contact system administrator.</div>
        </div>
    </div>

</div>
</div>

<style>
    .super-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    /* Desktop spacing */
    @media (min-width: 601px) {
        .super-admin-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 600px) {
        .super-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .super-admin-wrapper [style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
        }

        .super-admin-wrapper .nexus-card {
            border-radius: 12px;
        }
    }

    /* ========================================
       DARK MODE FOR EDIT TENANT
       ======================================== */

    [data-theme="dark"] .nexus-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .nexus-card-header {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] .nexus-card-header h3 {
        color: #f1f5f9;
    }

    [data-theme="dark"] .nexus-card-header div[style*="color:var(--nexus-text-muted)"] {
        color: #94a3b8 !important;
    }

    /* Inner boxes */
    [data-theme="dark"] div[style*="background: #f8fafc"] {
        background: rgba(15, 23, 42, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] div[style*="background: #f8fafc"] h4[style*="color:#475569"] {
        color: #e2e8f0 !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Form labels */
    [data-theme="dark"] label[style*="color:#1e293b"] {
        color: #f1f5f9 !important;
    }

    [data-theme="dark"] label[style*="font-weight: 600"] {
        color: #e2e8f0;
    }

    /* Form inputs */
    [data-theme="dark"] .nexus-input {
        background: rgba(15, 23, 42, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #f1f5f9 !important;
    }

    /* URL slug prefix */
    [data-theme="dark"] span[style*="background:#fff"][style*="border:1px solid #e2e8f0"] {
        background: rgba(30, 41, 59, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #94a3b8 !important;
    }

    /* Help text */
    [data-theme="dark"] p[style*="color: #6b7280"],
    [data-theme="dark"] p[style*="color:#6b7280"] {
        color: #94a3b8 !important;
    }

    /* Legal docs section header */
    [data-theme="dark"] div[style*="border-top: 1px solid #e2e8f0"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] h4[style*="color:#475569"] {
        color: #e2e8f0 !important;
    }

    /* Module installation section */
    [data-theme="dark"] div[style*="border-bottom:1px solid #eee"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] h4[style*="margin:0; font-size:1rem"] {
        color: #f1f5f9;
    }

    [data-theme="dark"] span[style*="background:#eff6ff"][style*="color:#1d4ed8"] {
        background: rgba(99, 102, 241, 0.2) !important;
        color: #a5b4fc !important;
    }

    /* Module cards - inactive */
    [data-theme="dark"] label[style*="border: 1px solid"][style*="#e5e7eb"][style*="background:"][style*="#fff"] {
        background: rgba(30, 41, 59, 0.4) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Module cards - active */
    [data-theme="dark"] label[style*="border: 1px solid #4f46e5"] {
        border-color: #6366f1 !important;
        background: rgba(99, 102, 241, 0.15) !important;
    }

    [data-theme="dark"] label div[style*="color:#1f2937"] {
        color: #f1f5f9 !important;
    }

    [data-theme="dark"] label div[style*="color:#9ca3af"] {
        color: #64748b !important;
    }

    [data-theme="dark"] label div[style*="color:#4f46e5"] {
        color: #a5b4fc !important;
    }

    [data-theme="dark"] label div[style*="background:"][style*="#f3f4f6"] {
        background: rgba(51, 65, 85, 0.6) !important;
    }

    [data-theme="dark"] label div[style*="background:"][style*="#e0e7ff"] {
        background: rgba(99, 102, 241, 0.3) !important;
    }

    /* Tenant Admins section */
    [data-theme="dark"] div[style*="background: #fff"][style*="border: 1px solid #e2e8f0"] {
        background: rgba(30, 41, 59, 0.4) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] div[style*="background:#f3f4f6"][style*="border-radius:50%"] {
        background: rgba(51, 65, 85, 0.6) !important;
        color: #94a3b8 !important;
    }

    [data-theme="dark"] div[style*="color:#1f2937"] {
        color: #f1f5f9 !important;
    }

    /* No admins warning */
    [data-theme="dark"] div[style*="background: #fff1f2"][style*="color: #be123c"] {
        background: rgba(239, 68, 68, 0.15) !important;
        border-color: rgba(239, 68, 68, 0.3) !important;
        color: #fca5a5 !important;
    }

    /* Grant access form */
    [data-theme="dark"] h5[style*="color:#374151"] {
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] form[style*="background: #fff"][style*="border:1px solid #e2e8f0"] {
        background: rgba(30, 41, 59, 0.4) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] label[style*="color:#6b7280"] {
        color: #94a3b8 !important;
    }

    /* Danger zone */
    [data-theme="dark"] div[style*="border: 1px dashed #ef4444"][style*="background: #fef2f2"] {
        background: rgba(239, 68, 68, 0.1) !important;
        border-color: rgba(239, 68, 68, 0.4) !important;
    }

    [data-theme="dark"] div[style*="color: #991b1b"] {
        color: #fca5a5 !important;
    }

    [data-theme="dark"] div[style*="color: #b91c1c"] {
        color: #f87171 !important;
    }

    /* Buttons */
    [data-theme="dark"] .nexus-btn-secondary {
        background: rgba(51, 65, 85, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #e2e8f0 !important;
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>