<?php
/**
 * Super Admin Tenant Edit - Gold Standard
 * Configure individual community settings
 * Path: views/modern/admin/super-admin/tenant-edit.php
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// $tenant is passed from the controller
if (!isset($tenant) || !$tenant) {
    echo '<p>Tenant not found.</p>';
    return;
}

$tName = htmlspecialchars($tenant['name']);
$feats = json_decode($tenant['features'] ?? '[]', true);
$config = json_decode($tenant['configuration'] ?? '[]', true);

// Fetch admins for this tenant
$admins = Database::query("SELECT * FROM users WHERE tenant_id = ? AND role = 'admin'", [$tenant['id']])->fetchAll();

// Module definitions
$modules = [
    'listings' => ['icon' => 'fa-list-check', 'label' => 'Offers & Requests', 'desc' => 'Listing marketplace'],
    'groups' => ['icon' => 'fa-people-group', 'label' => 'Local Hubs', 'desc' => 'Community groups'],
    'wallet' => ['icon' => 'fa-wallet', 'label' => 'Wallet & Transactions', 'desc' => 'Time credits'],
    'volunteering' => ['icon' => 'fa-hand-holding-heart', 'label' => 'Volunteering', 'desc' => 'Volunteer management'],
    'events' => ['icon' => 'fa-calendar-days', 'label' => 'Events', 'desc' => 'Event system'],
    'resources' => ['icon' => 'fa-book-open', 'label' => 'Resource Library', 'desc' => 'Shared resources'],
    'polls' => ['icon' => 'fa-square-poll-vertical', 'label' => 'Live Polls', 'desc' => 'Community voting'],
    'goals' => ['icon' => 'fa-bullseye', 'label' => 'Goal Buddy', 'desc' => 'Goal tracking'],
    'blog' => ['icon' => 'fa-newspaper', 'label' => 'News / Blog', 'desc' => 'Content publishing'],
    'help_center' => ['icon' => 'fa-circle-question', 'label' => 'Help Center', 'desc' => 'Support docs'],
];

// Flash messages
$successMsg = $_GET['msg'] ?? null;
$errorMsg = $_GET['error'] ?? null;

// Header config
$superAdminPageTitle = 'Configure ' . $tName;
$superAdminPageSubtitle = 'Tenant Configuration';
$superAdminPageIcon = 'fa-cog';

require dirname(__DIR__) . '/partials/super-admin-header.php';
?>

<?php if ($successMsg): ?>
<div class="super-admin-flash-message" data-type="success" style="display:none;">
    <?php
    switch($successMsg) {
        case 'updated': echo 'Configuration saved successfully!'; break;
        case 'admin_added': echo 'Administrator access granted.'; break;
        case 'admin_removed': echo 'Administrator access revoked.'; break;
        default: echo htmlspecialchars($successMsg);
    }
    ?>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="super-admin-flash-message" data-type="error" style="display:none;">
    <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<!-- Back Link -->
<div style="margin-bottom: 1rem;">
    <a href="/super-admin" class="super-admin-btn super-admin-btn-secondary super-admin-btn-sm">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Dashboard
    </a>
</div>

<!-- Page Header -->
<div class="super-admin-page-header">
    <div class="super-admin-page-header-content">
        <div class="super-admin-page-header-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div>
            <h1 class="super-admin-page-title"><?= $tName ?></h1>
            <p class="super-admin-page-subtitle">Configure community settings, modules, and administrators</p>
        </div>
    </div>
    <a href="<?= $basePath ?>/<?= htmlspecialchars($tenant['slug']) ?>" target="_blank" class="super-admin-btn super-admin-btn-secondary">
        <i class="fa-solid fa-external-link"></i>
        Visit Site
    </a>
</div>

<!-- Two Column Layout -->
<div class="super-admin-two-col">
    <!-- Main Column -->
    <div>
        <!-- Global Configuration -->
        <div class="super-admin-glass-card">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-purple">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Global Configuration</h3>
                    <p class="super-admin-card-subtitle">Core settings for this community</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <form action="/super-admin/tenant/update" method="POST">
                    <input type="hidden" name="id" value="<?= $tenant['id'] ?>">

                    <!-- Identity Section -->
                    <div style="background: rgba(147, 51, 234, 0.1); border: 1px solid rgba(147, 51, 234, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #c084fc; font-weight: 700;">
                            <i class="fa-solid fa-fingerprint" style="margin-right: 6px;"></i> Identity
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Community Name</label>
                                <input type="text" name="name" class="super-admin-input" value="<?= $tName ?>" required>
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">URL Slug</label>
                                <input type="text" name="slug" class="super-admin-input" value="<?= htmlspecialchars($tenant['slug']) ?>" required>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Custom Domain <span style="opacity: 0.5;">(optional)</span></label>
                                <input type="text" name="domain" class="super-admin-input" value="<?= htmlspecialchars($tenant['domain'] ?? '') ?>" placeholder="community.example.com">
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Tagline</label>
                                <input type="text" name="tagline" class="super-admin-input" value="<?= htmlspecialchars($tenant['tagline'] ?? '') ?>" placeholder="Short description">
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="super-admin-form-group">
                        <label class="super-admin-label">Description</label>
                        <textarea name="description" class="super-admin-input" rows="3" style="resize: vertical;" placeholder="Full community description"><?= htmlspecialchars($tenant['description'] ?? '') ?></textarea>
                    </div>

                    <!-- SEO & Search Engine Section -->
                    <div style="background: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #fb923c; font-weight: 700;">
                            <i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i> SEO & Search Engines
                        </h4>
                        <p style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin: 0 0 16px;">
                            Control how this tenant appears in Google search results. These override the defaults.
                        </p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Meta Title <span style="opacity: 0.5;">(max 60 chars)</span></label>
                                <input type="text" name="meta_title" class="super-admin-input" maxlength="70" value="<?= htmlspecialchars($tenant['meta_title'] ?? '') ?>" placeholder="Browser tab title for search results">
                                <div style="font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 4px;">
                                    <span id="meta_title_count"><?= strlen($tenant['meta_title'] ?? '') ?></span>/60 characters
                                </div>
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">H1 Headline</label>
                                <input type="text" name="h1_headline" class="super-admin-input" maxlength="100" value="<?= htmlspecialchars($tenant['h1_headline'] ?? '') ?>" placeholder="Main heading on homepage">
                            </div>
                        </div>
                        <div class="super-admin-form-group" style="margin-bottom: 16px;">
                            <label class="super-admin-label">Meta Description <span style="opacity: 0.5;">(max 160 chars)</span></label>
                            <textarea name="meta_description" class="super-admin-input" rows="2" maxlength="180" style="resize: vertical;" placeholder="The snippet shown in Google search results"><?= htmlspecialchars($tenant['meta_description'] ?? '') ?></textarea>
                            <div style="font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 4px;">
                                <span id="meta_desc_count"><?= strlen($tenant['meta_description'] ?? '') ?></span>/160 characters
                            </div>
                        </div>
                        <div class="super-admin-form-group" style="margin: 0;">
                            <label class="super-admin-label">Hero Intro Text</label>
                            <textarea name="hero_intro" class="super-admin-input" rows="2" style="resize: vertical;" placeholder="2-3 sentence intro below the H1 headline"><?= htmlspecialchars($tenant['hero_intro'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Location & Geo-Targeting Section -->
                    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #60a5fa; font-weight: 700;">
                            <i class="fa-solid fa-location-dot" style="margin-right: 6px;"></i> Location & Geo-Targeting
                        </h4>
                        <p style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin: 0 0 16px;">
                            Set the headquarters location for local SEO. This powers Schema.org markup for "near me" searches.
                        </p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Country Code <span style="opacity: 0.5;">(ISO 2-letter)</span></label>
                                <select name="country_code" class="super-admin-input">
                                    <option value="">-- Select Country --</option>
                                    <option value="IE" <?= ($tenant['country_code'] ?? '') === 'IE' ? 'selected' : '' ?>>Ireland (IE)</option>
                                    <option value="GB" <?= ($tenant['country_code'] ?? '') === 'GB' ? 'selected' : '' ?>>United Kingdom (GB)</option>
                                    <option value="US" <?= ($tenant['country_code'] ?? '') === 'US' ? 'selected' : '' ?>>United States (US)</option>
                                    <option value="CA" <?= ($tenant['country_code'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada (CA)</option>
                                    <option value="AU" <?= ($tenant['country_code'] ?? '') === 'AU' ? 'selected' : '' ?>>Australia (AU)</option>
                                    <option value="NZ" <?= ($tenant['country_code'] ?? '') === 'NZ' ? 'selected' : '' ?>>New Zealand (NZ)</option>
                                    <option value="DE" <?= ($tenant['country_code'] ?? '') === 'DE' ? 'selected' : '' ?>>Germany (DE)</option>
                                    <option value="FR" <?= ($tenant['country_code'] ?? '') === 'FR' ? 'selected' : '' ?>>France (FR)</option>
                                    <option value="ES" <?= ($tenant['country_code'] ?? '') === 'ES' ? 'selected' : '' ?>>Spain (ES)</option>
                                    <option value="IT" <?= ($tenant['country_code'] ?? '') === 'IT' ? 'selected' : '' ?>>Italy (IT)</option>
                                    <option value="NL" <?= ($tenant['country_code'] ?? '') === 'NL' ? 'selected' : '' ?>>Netherlands (NL)</option>
                                    <option value="BE" <?= ($tenant['country_code'] ?? '') === 'BE' ? 'selected' : '' ?>>Belgium (BE)</option>
                                    <option value="PT" <?= ($tenant['country_code'] ?? '') === 'PT' ? 'selected' : '' ?>>Portugal (PT)</option>
                                    <option value="SE" <?= ($tenant['country_code'] ?? '') === 'SE' ? 'selected' : '' ?>>Sweden (SE)</option>
                                    <option value="NO" <?= ($tenant['country_code'] ?? '') === 'NO' ? 'selected' : '' ?>>Norway (NO)</option>
                                    <option value="DK" <?= ($tenant['country_code'] ?? '') === 'DK' ? 'selected' : '' ?>>Denmark (DK)</option>
                                    <option value="FI" <?= ($tenant['country_code'] ?? '') === 'FI' ? 'selected' : '' ?>>Finland (FI)</option>
                                    <option value="PL" <?= ($tenant['country_code'] ?? '') === 'PL' ? 'selected' : '' ?>>Poland (PL)</option>
                                    <option value="AT" <?= ($tenant['country_code'] ?? '') === 'AT' ? 'selected' : '' ?>>Austria (AT)</option>
                                    <option value="CH" <?= ($tenant['country_code'] ?? '') === 'CH' ? 'selected' : '' ?>>Switzerland (CH)</option>
                                </select>
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Service Area</label>
                                <select name="service_area" class="super-admin-input">
                                    <option value="local" <?= ($tenant['service_area'] ?? '') === 'local' ? 'selected' : '' ?>>Local (City/Town)</option>
                                    <option value="regional" <?= ($tenant['service_area'] ?? '') === 'regional' ? 'selected' : '' ?>>Regional (County/State)</option>
                                    <option value="national" <?= ($tenant['service_area'] ?? 'national') === 'national' ? 'selected' : '' ?>>National (Country-wide)</option>
                                    <option value="international" <?= ($tenant['service_area'] ?? '') === 'international' ? 'selected' : '' ?>>International (Global)</option>
                                </select>
                            </div>
                        </div>
                        <div class="super-admin-form-group" style="margin-bottom: 16px;">
                            <label class="super-admin-label">Location Search <span style="opacity: 0.5;">(Google Maps)</span></label>
                            <div style="position: relative;">
                                <input type="text" id="location_search" class="super-admin-input" placeholder="Search for headquarters address..." autocomplete="off">
                                <div id="location_suggestions" style="position: absolute; top: 100%; left: 0; right: 0; background: #1a1025; border: 1px solid rgba(147, 51, 234, 0.3); border-radius: 8px; max-height: 200px; overflow-y: auto; z-index: 100; display: none;"></div>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px;">
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Location Name</label>
                                <input type="text" name="location_name" id="location_name" class="super-admin-input" value="<?= htmlspecialchars($tenant['location_name'] ?? '') ?>" placeholder="e.g., Dublin, Ireland">
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="super-admin-input" value="<?= htmlspecialchars($tenant['latitude'] ?? '') ?>" placeholder="53.3498">
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="super-admin-input" value="<?= htmlspecialchars($tenant['longitude'] ?? '') ?>" placeholder="-6.2603">
                            </div>
                        </div>
                    </div>

                    <!-- Footer Text -->
                    <div class="super-admin-form-group">
                        <label class="super-admin-label">Custom Footer Text</label>
                        <input type="text" name="footer_text" class="super-admin-input" value="<?= htmlspecialchars($config['footer_text'] ?? '') ?>" placeholder="Footer copyright or message">
                    </div>

                    <!-- Policy Documents -->
                    <div style="background: rgba(6, 182, 212, 0.1); border: 1px solid rgba(6, 182, 212, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #22d3ee; font-weight: 700;">
                            <i class="fa-solid fa-file-contract" style="margin-right: 6px;"></i> Policy Documents
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Privacy Policy</label>
                                <textarea name="privacy_text" class="super-admin-input" rows="5" style="resize: vertical;"><?= htmlspecialchars($config['privacy_text'] ?? '') ?></textarea>
                            </div>
                            <div class="super-admin-form-group" style="margin: 0;">
                                <label class="super-admin-label">Terms of Service</label>
                                <textarea name="terms_text" class="super-admin-input" rows="5" style="resize: vertical;"><?= htmlspecialchars($config['terms_text'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Module Installation -->
                    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #34d399; font-weight: 700;">
                            <i class="fa-solid fa-puzzle-piece" style="margin-right: 6px;"></i> Module Installation
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                            <?php foreach ($modules as $key => $mod):
                                $isActive = $feats[$key] ?? ($key === 'blog'); // Blog default true
                            ?>
                            <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: <?= $isActive ? 'rgba(16, 185, 129, 0.15)' : 'rgba(15, 10, 26, 0.4)' ?>; border: 1px solid <?= $isActive ? 'rgba(16, 185, 129, 0.3)' : 'rgba(147, 51, 234, 0.15)' ?>; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="feat_<?= $key ?>" <?= $isActive ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #10b981;">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid <?= $mod['icon'] ?>" style="color: <?= $isActive ? '#34d399' : 'rgba(255,255,255,0.4)' ?>; font-size: 0.9rem;"></i>
                                        <span style="font-weight: 600; font-size: 0.85rem; color: <?= $isActive ? '#fff' : 'rgba(255,255,255,0.7)' ?>;"><?= $mod['label'] ?></span>
                                    </div>
                                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 2px;"><?= $mod['desc'] ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="super-admin-btn super-admin-btn-primary" style="padding: 12px 24px;">
                            <i class="fa-solid fa-check"></i>
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Tenant Info -->
        <div class="super-admin-glass-card">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-cyan">
                    <i class="fa-solid fa-info-circle"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Tenant Info</h3>
                    <p class="super-admin-card-subtitle">Quick reference</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(147, 51, 234, 0.1); border-radius: 8px;">
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Tenant ID</span>
                        <span style="font-family: monospace; font-size: 0.85rem; color: #c084fc;">#<?= $tenant['id'] ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(147, 51, 234, 0.1); border-radius: 8px;">
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">URL Path</span>
                        <span style="font-family: monospace; font-size: 0.85rem; color: #c084fc;">/<?= htmlspecialchars($tenant['slug']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(147, 51, 234, 0.1); border-radius: 8px;">
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Admins</span>
                        <span style="font-size: 0.85rem; color: #fff; font-weight: 600;"><?= count($admins) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(147, 51, 234, 0.1); border-radius: 8px;">
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Created</span>
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.7);">
                            <?= isset($tenant['created_at']) ? date('M j, Y', strtotime($tenant['created_at'])) : 'N/A' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Administrators -->
        <div class="super-admin-glass-card">
            <div class="super-admin-card-header">
                <div class="super-admin-card-header-icon super-admin-card-header-icon-emerald">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Administrators</h3>
                    <p class="super-admin-card-subtitle"><?= count($admins) ?> users with admin access</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <!-- Current Admins -->
                <?php if (!empty($admins)): ?>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                    <?php foreach ($admins as $a): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem;">
                                <?= strtoupper(substr($a['first_name'], 0, 1) . substr($a['last_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.85rem; color: #fff;"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5);"><?= htmlspecialchars($a['email']) ?></div>
                            </div>
                        </div>
                        <form action="/super-admin/admin/delete" method="POST" onsubmit="return confirm('Revoke admin access for this user?');" style="margin: 0;">
                            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="super-admin-btn super-admin-btn-danger super-admin-btn-sm" style="padding: 4px 8px;">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="padding: 20px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.85rem; margin-bottom: 16px;">
                    No administrators assigned yet.
                </div>
                <?php endif; ?>

                <!-- Add Admin Form -->
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 12px;">
                    <h5 style="margin: 0 0 12px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #34d399; font-weight: 700;">
                        <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i> Grant Access
                    </h5>
                    <form action="/super-admin/admin/add" method="POST">
                        <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                        <div class="super-admin-form-group" style="margin-bottom: 8px;">
                            <input type="text" name="name" class="super-admin-input" placeholder="Full Name" required style="font-size: 0.85rem; padding: 8px 10px;">
                        </div>
                        <div class="super-admin-form-group" style="margin-bottom: 8px;">
                            <input type="email" name="email" class="super-admin-input" placeholder="Email" required style="font-size: 0.85rem; padding: 8px 10px;">
                        </div>
                        <div class="super-admin-form-group" style="margin-bottom: 12px;">
                            <input type="password" name="password" class="super-admin-input" placeholder="Password" required style="font-size: 0.85rem; padding: 8px 10px;">
                        </div>
                        <button type="submit" class="super-admin-btn super-admin-btn-primary super-admin-btn-sm" style="width: 100%;">
                            <i class="fa-solid fa-plus"></i> Add Administrator
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="super-admin-glass-card" style="border-color: rgba(239, 68, 68, 0.3);">
            <div class="super-admin-card-header" style="border-color: rgba(239, 68, 68, 0.2);">
                <div class="super-admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="super-admin-card-header-content">
                    <h3 class="super-admin-card-title">Danger Zone</h3>
                    <p class="super-admin-card-subtitle">Irreversible actions</p>
                </div>
            </div>
            <div class="super-admin-card-body">
                <p style="font-size: 0.8rem; color: rgba(255,255,255,0.6); margin: 0 0 12px;">
                    These actions are permanent and cannot be undone. Proceed with caution.
                </p>
                <button type="button" class="super-admin-btn super-admin-btn-danger" style="width: 100%; opacity: 0.7;" disabled>
                    <i class="fa-solid fa-trash"></i>
                    Delete Community (Coming Soon)
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional Tenant Edit styles */
@media (max-width: 768px) {
    .super-admin-card-body form [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}

/* Location suggestions styling */
#location_suggestions .suggestion-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(147, 51, 234, 0.1);
    font-size: 0.85rem;
    color: rgba(255,255,255,0.8);
}
#location_suggestions .suggestion-item:hover {
    background: rgba(147, 51, 234, 0.2);
}
#location_suggestions .suggestion-item:last-child {
    border-bottom: none;
}
</style>

<script>
// Character counters for SEO fields
document.addEventListener('DOMContentLoaded', function() {
    // Meta Title Counter
    const metaTitleInput = document.querySelector('input[name="meta_title"]');
    const metaTitleCount = document.getElementById('meta_title_count');
    if (metaTitleInput && metaTitleCount) {
        metaTitleInput.addEventListener('input', function() {
            const len = this.value.length;
            metaTitleCount.textContent = len;
            metaTitleCount.style.color = len > 60 ? '#ef4444' : 'rgba(255,255,255,0.4)';
        });
    }

    // Meta Description Counter
    const metaDescInput = document.querySelector('textarea[name="meta_description"]');
    const metaDescCount = document.getElementById('meta_desc_count');
    if (metaDescInput && metaDescCount) {
        metaDescInput.addEventListener('input', function() {
            const len = this.value.length;
            metaDescCount.textContent = len;
            metaDescCount.style.color = len > 160 ? '#ef4444' : 'rgba(255,255,255,0.4)';
        });
    }

    // Google Maps Geocoding for Location Search
    const GOOGLE_MAPS_KEY = '<?= $_ENV['GOOGLE_MAPS_API_KEY'] ?? '' ?>';
    const locationSearch = document.getElementById('location_search');
    const suggestions = document.getElementById('location_suggestions');
    const locationName = document.getElementById('location_name');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');

    if (locationSearch && GOOGLE_MAPS_KEY) {
        let debounceTimer;

        locationSearch.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length < 3) {
                suggestions.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(async () => {
                try {
                    const response = await fetch(
                        `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(query)}&key=${GOOGLE_MAPS_KEY}`
                    );
                    const data = await response.json();

                    if (data.results && data.results.length > 0) {
                        suggestions.innerHTML = data.results.slice(0, 5).map(r => `
                            <div class="suggestion-item" data-place="${r.formatted_address}" data-lat="${r.geometry.location.lat}" data-lng="${r.geometry.location.lng}">
                                ${r.formatted_address}
                            </div>
                        `).join('');
                        suggestions.style.display = 'block';

                        // Add click handlers
                        suggestions.querySelectorAll('.suggestion-item').forEach(item => {
                            item.addEventListener('click', function() {
                                locationName.value = this.dataset.place;
                                latitudeInput.value = this.dataset.lat;
                                longitudeInput.value = this.dataset.lng;
                                locationSearch.value = '';
                                suggestions.style.display = 'none';
                            });
                        });
                    } else {
                        suggestions.style.display = 'none';
                    }
                } catch (err) {
                    console.error('Geocoding error:', err);
                    suggestions.style.display = 'none';
                }
            }, 300);
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!locationSearch.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/super-admin-footer.php'; ?>
