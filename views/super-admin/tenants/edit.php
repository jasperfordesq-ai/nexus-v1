<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Edit Tenant Form
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Edit Tenant';

// Parse configuration JSON
$config = json_decode($tenant['configuration'] ?? '{}', true) ?: [];

require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/tenants"><?= __('super_admin.tenants.show.breadcrumb_tenants') ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/tenants/<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <span><?= __('super_admin.tenants.edit.breadcrumb_edit') ?></span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-pen"></i>
            <?= __('super_admin.tenants.edit.title') ?>
        </h1>
        <p class="super-page-subtitle">
            <?= __('super_admin.tenants.edit.subtitle', ['name' => htmlspecialchars($tenant['name']), 'depth' => $tenant['depth']]) ?>
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <?= __('super_admin.tenants.edit.back_btn') ?>
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="super-alert super-alert-danger" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="super-alert super-alert-success" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-check-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Main Form -->
    <div>
        <div class="super-card">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-building"></i>
                    <?= __('super_admin.tenants.edit.details_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <!-- Name -->
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <?= __('super_admin.tenants.edit.name_label') ?> <span style="color: var(--super-danger);">*</span>
                            </label>
                            <input type="text" name="name" class="super-form-input" required
                                   value="<?= htmlspecialchars($tenant['name']) ?>">
                        </div>

                        <!-- Slug -->
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <?= __('super_admin.tenants.edit.slug_label') ?> <span style="color: var(--super-danger);">*</span>
                            </label>
                            <input type="text" name="slug" class="super-form-input" required
                                   pattern="[a-z0-9-]+"
                                   value="<?= htmlspecialchars($tenant['slug']) ?>">
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.slug_help') ?></p>
                        </div>
                    </div>

                    <!-- Tagline -->
                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.tenants.edit.tagline_label') ?></label>
                        <input type="text" name="tagline" class="super-form-input"
                               value="<?= htmlspecialchars($tenant['tagline'] ?? '') ?>">
                    </div>

                    <!-- Domain -->
                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.tenants.edit.domain_label') ?></label>
                        <input type="text" name="domain" class="super-form-input"
                               value="<?= htmlspecialchars($tenant['domain'] ?? '') ?>">
                        <p class="super-form-help"><?= __('super_admin.tenants.edit.domain_help') ?></p>
                    </div>

                    <!-- Description -->
                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.tenants.edit.description_label') ?></label>
                        <textarea name="description" class="super-form-textarea" rows="3"><?= htmlspecialchars($tenant['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Active Status -->
                    <div class="super-form-group">
                        <label class="super-form-checkbox">
                            <input type="checkbox" name="is_active" value="1"
                                <?= $tenant['is_active'] ? 'checked' : '' ?>>
                            <span><?= __('super_admin.tenants.edit.is_active_label') ?></span>
                        </label>
                        <p class="super-form-help"><?= __('super_admin.tenants.edit.is_active_help') ?></p>
                    </div>

                    <!-- Submit -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.tenants.edit.save_btn') ?>
                        </button>
                        <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-secondary">
                            <i class="fa-solid fa-times"></i>
                            <?= __('super_admin.tenants.edit.cancel_btn') ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- SEO Settings Card -->
        <div class="super-card" style="margin-top: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    <?= __('super_admin.tenants.edit.seo_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <!-- SERP Preview -->
                    <div style="background: var(--super-bg); border: 1px solid var(--super-border); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="font-size: 0.7rem; color: var(--super-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;"><?= __('super_admin.tenants.edit.seo_preview_label') ?></div>
                        <div id="serpTitle" style="font-size: 1.1rem; color: #1a0dab; margin-bottom: 0.25rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($tenant['meta_title'] ?: $tenant['name']) ?>
                        </div>
                        <div style="font-size: 0.8rem; color: #006621; margin-bottom: 0.25rem;">
                            <?= htmlspecialchars($tenant['domain'] ?: 'yourdomain.com/' . $tenant['slug']) ?>
                        </div>
                        <div id="serpDesc" style="font-size: 0.8rem; color: #545454; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($tenant['meta_description'] ?: 'Add a meta description to see how this tenant appears in search results...') ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <!-- Meta Title -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.meta_title_label') ?></label>
                            <input type="text" name="meta_title" class="super-form-input" maxlength="70"
                                   value="<?= htmlspecialchars($tenant['meta_title'] ?? '') ?>"
                                   placeholder="<?= __('super_admin.tenants.edit.meta_title_placeholder') ?>"
                                   oninput="document.getElementById('serpTitle').textContent = this.value || '<?= addslashes($tenant['name']) ?>'">
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.meta_title_help') ?></p>
                        </div>

                        <!-- H1 Headline -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.h1_headline_label') ?></label>
                            <input type="text" name="h1_headline" class="super-form-input" maxlength="100"
                                   value="<?= htmlspecialchars($tenant['h1_headline'] ?? '') ?>"
                                   placeholder="<?= __('super_admin.tenants.edit.h1_headline_placeholder') ?>">
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.h1_headline_help') ?></p>
                        </div>
                    </div>

                    <!-- Meta Description -->
                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.tenants.edit.meta_description_label') ?></label>
                        <textarea name="meta_description" class="super-form-textarea" rows="2" maxlength="180"
                                  placeholder="<?= __('super_admin.tenants.edit.meta_description_placeholder') ?>"
                                  oninput="document.getElementById('serpDesc').textContent = this.value || 'Add a meta description...'"><?= htmlspecialchars($tenant['meta_description'] ?? '') ?></textarea>
                        <p class="super-form-help"><?= __('super_admin.tenants.edit.meta_description_help') ?></p>
                    </div>

                    <!-- Hero Intro -->
                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.tenants.edit.hero_intro_label') ?></label>
                        <textarea name="hero_intro" class="super-form-textarea" rows="2"
                                  placeholder="<?= __('super_admin.tenants.edit.hero_intro_placeholder') ?>"><?= htmlspecialchars($tenant['hero_intro'] ?? '') ?></textarea>
                        <p class="super-form-help"><?= __('super_admin.tenants.edit.hero_intro_help') ?></p>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <!-- OG Image -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.og_image_label') ?></label>
                            <input type="url" name="og_image_url" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['og_image_url'] ?? '') ?>"
                                   placeholder="https://example.com/image.jpg">
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.og_image_help') ?></p>
                        </div>

                        <!-- Robots Directive -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.robots_label') ?></label>
                            <select name="robots_directive" class="super-form-select">
                                <option value="index, follow" <?= ($tenant['robots_directive'] ?? '') === 'index, follow' ? 'selected' : '' ?>>index, follow (Default)</option>
                                <option value="noindex, follow" <?= ($tenant['robots_directive'] ?? '') === 'noindex, follow' ? 'selected' : '' ?>>noindex, follow</option>
                                <option value="index, nofollow" <?= ($tenant['robots_directive'] ?? '') === 'index, nofollow' ? 'selected' : '' ?>>index, nofollow</option>
                                <option value="noindex, nofollow" <?= ($tenant['robots_directive'] ?? '') === 'noindex, nofollow' ? 'selected' : '' ?>>noindex, nofollow</option>
                            </select>
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.robots_help') ?></p>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.tenants.edit.save_seo_btn') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contact & Location Card -->
        <div class="super-card" style="margin-top: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-location-dot"></i>
                    <?= __('super_admin.tenants.edit.contact_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <!-- Contact Email -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.contact_email_label') ?></label>
                            <input type="email" name="contact_email" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['contact_email'] ?? '') ?>"
                                   placeholder="contact@example.com">
                        </div>

                        <!-- Contact Phone -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.contact_phone_label') ?></label>
                            <input type="text" name="contact_phone" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['contact_phone'] ?? '') ?>"
                                   placeholder="+1 234 567 8900">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.tenants.edit.address_label') ?></label>
                        <textarea name="address" class="super-form-textarea" rows="2"
                                  placeholder="<?= __('super_admin.tenants.edit.address_placeholder') ?>"><?= htmlspecialchars($tenant['address'] ?? '') ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <!-- Location Name -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.location_name_label') ?></label>
                            <input type="text" name="location_name" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['location_name'] ?? '') ?>"
                                   placeholder="<?= __('super_admin.tenants.edit.location_name_placeholder') ?>">
                        </div>

                        <!-- Country Code -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.country_code_label') ?></label>
                            <input type="text" name="country_code" class="super-form-input" maxlength="2"
                                   value="<?= htmlspecialchars($tenant['country_code'] ?? '') ?>"
                                   placeholder="IE, US, GB..." style="text-transform: uppercase;">
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.country_code_help') ?></p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <!-- Latitude -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.latitude_label') ?></label>
                            <input type="number" name="latitude" class="super-form-input" step="0.00000001"
                                   value="<?= htmlspecialchars($tenant['latitude'] ?? '') ?>"
                                   placeholder="53.3498">
                        </div>

                        <!-- Longitude -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.longitude_label') ?></label>
                            <input type="number" name="longitude" class="super-form-input" step="0.00000001"
                                   value="<?= htmlspecialchars($tenant['longitude'] ?? '') ?>"
                                   placeholder="-6.2603">
                        </div>

                        <!-- Service Area -->
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.service_area_label') ?></label>
                            <select name="service_area" class="super-form-select">
                                <option value="local" <?= ($tenant['service_area'] ?? '') === 'local' ? 'selected' : '' ?>>Local</option>
                                <option value="regional" <?= ($tenant['service_area'] ?? '') === 'regional' ? 'selected' : '' ?>>Regional</option>
                                <option value="national" <?= ($tenant['service_area'] ?? '') === 'national' || empty($tenant['service_area']) ? 'selected' : '' ?>>National</option>
                                <option value="international" <?= ($tenant['service_area'] ?? '') === 'international' ? 'selected' : '' ?>>International</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.tenants.edit.save_contact_btn') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Social Media Card -->
        <div class="super-card" style="margin-top: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-share-nodes"></i>
                    <?= __('super_admin.tenants.edit.social_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <!-- Facebook -->
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <i class="fa-brands fa-facebook" style="color: #1877f2; margin-right: 0.5rem;"></i>
                                Facebook
                            </label>
                            <input type="url" name="social_facebook" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['social_facebook'] ?? '') ?>"
                                   placeholder="https://facebook.com/yourpage">
                        </div>

                        <!-- Twitter/X -->
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <i class="fa-brands fa-x-twitter" style="margin-right: 0.5rem;"></i>
                                Twitter / X
                            </label>
                            <input type="url" name="social_twitter" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['social_twitter'] ?? '') ?>"
                                   placeholder="https://x.com/yourhandle">
                        </div>

                        <!-- Instagram -->
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <i class="fa-brands fa-instagram" style="color: #e4405f; margin-right: 0.5rem;"></i>
                                Instagram
                            </label>
                            <input type="url" name="social_instagram" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['social_instagram'] ?? '') ?>"
                                   placeholder="https://instagram.com/yourhandle">
                        </div>

                        <!-- LinkedIn -->
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <i class="fa-brands fa-linkedin" style="color: #0a66c2; margin-right: 0.5rem;"></i>
                                LinkedIn
                            </label>
                            <input type="url" name="social_linkedin" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['social_linkedin'] ?? '') ?>"
                                   placeholder="https://linkedin.com/company/yourcompany">
                        </div>

                        <!-- YouTube -->
                        <div class="super-form-group" style="grid-column: span 2;">
                            <label class="super-form-label">
                                <i class="fa-brands fa-youtube" style="color: #ff0000; margin-right: 0.5rem;"></i>
                                YouTube
                            </label>
                            <input type="url" name="social_youtube" class="super-form-input"
                                   value="<?= htmlspecialchars($tenant['social_youtube'] ?? '') ?>"
                                   placeholder="https://youtube.com/@yourchannel">
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.tenants.edit.save_social_btn') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Platform Modules Card -->
        <div class="super-card" style="margin-top: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-puzzle-piece"></i>
                    <?= __('super_admin.tenants.edit.modules_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <?php
                // Parse features JSON
                $features = json_decode($tenant['features'] ?? '{}', true) ?: [];

                // Module definitions
                $modules = [
                    'listings' => ['icon' => 'fa-list-check', 'label' => 'Offers & Requests', 'desc' => 'Listing marketplace for offers and requests'],
                    'groups' => ['icon' => 'fa-people-group', 'label' => 'Local Hubs', 'desc' => 'Community groups and local hubs'],
                    'wallet' => ['icon' => 'fa-wallet', 'label' => 'Wallet & Transactions', 'desc' => 'Time credit wallet and transactions'],
                    'volunteering' => ['icon' => 'fa-hand-holding-heart', 'label' => 'Volunteering', 'desc' => 'Volunteer opportunity management'],
                    'events' => ['icon' => 'fa-calendar-days', 'label' => 'Events', 'desc' => 'Event creation and management'],
                    'resources' => ['icon' => 'fa-book-open', 'label' => 'Resource Library', 'desc' => 'Shared resource documentation'],
                    'polls' => ['icon' => 'fa-square-poll-vertical', 'label' => 'Live Polls', 'desc' => 'Community voting and polls'],
                    'goals' => ['icon' => 'fa-bullseye', 'label' => 'Goal Buddy', 'desc' => 'Goal setting and tracking'],
                    'blog' => ['icon' => 'fa-newspaper', 'label' => 'News / Blog', 'desc' => 'Content publishing and news'],
                    'help_center' => ['icon' => 'fa-circle-question', 'label' => 'Help Center', 'desc' => 'Support documentation and FAQs'],
                ];
                ?>
                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/update">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="update_modules" value="1">

                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        <?= __('super_admin.tenants.edit.modules_desc') ?>
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                        <?php foreach ($modules as $key => $mod):
                            $isActive = $features[$key] ?? ($key === 'blog'); // Blog default true
                        ?>
                        <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: <?= $isActive ? 'rgba(34, 197, 94, 0.1)' : 'var(--super-bg)' ?>; border: 1px solid <?= $isActive ? 'rgba(34, 197, 94, 0.3)' : 'var(--super-border)' ?>; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                            <input type="checkbox" name="feat_<?= $key ?>" value="1" <?= $isActive ? 'checked' : '' ?>
                                   style="width: 18px; height: 18px; accent-color: var(--super-success);">
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fa-solid <?= $mod['icon'] ?>" style="color: <?= $isActive ? 'var(--super-success)' : 'var(--super-text-muted)' ?>; font-size: 0.9rem; width: 1rem;"></i>
                                    <span style="font-weight: 600; font-size: 0.875rem;"><?= $mod['label'] ?></span>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--super-text-muted); margin-top: 0.125rem; margin-left: 1.5rem;"><?= $mod['desc'] ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.tenants.edit.save_modules_btn') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Legal Documents Card -->
        <div class="super-card" style="margin-top: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-file-contract"></i>
                    <?= __('super_admin.tenants.edit.legal_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <div style="background: rgba(6, 182, 212, 0.1); border: 1px solid rgba(6, 182, 212, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin: 0;">
                            <i class="fa-solid fa-info-circle" style="color: #22d3ee; margin-right: 0.5rem;"></i>
                            <?= __('super_admin.tenants.edit.legal_note') ?>
                        </p>
                    </div>

                    <!-- Privacy Policy -->
                    <div class="super-form-group">
                        <label class="super-form-label">
                            <i class="fa-solid fa-shield-halved" style="color: #6366f1; margin-right: 0.5rem;"></i>
                            <?= __('super_admin.tenants.edit.privacy_policy_label') ?>
                        </label>
                        <textarea name="privacy_text" class="super-form-textarea" rows="4"
                                  placeholder="<?= __('super_admin.tenants.edit.privacy_policy_placeholder') ?>"><?= htmlspecialchars($config['privacy_text'] ?? '') ?></textarea>
                        <p class="super-form-help"><?= __('super_admin.tenants.edit.privacy_policy_help') ?></p>
                    </div>

                    <!-- Terms of Service -->
                    <div class="super-form-group">
                        <label class="super-form-label">
                            <i class="fa-solid fa-gavel" style="color: #3b82f6; margin-right: 0.5rem;"></i>
                            <?= __('super_admin.tenants.edit.terms_label') ?>
                        </label>
                        <textarea name="terms_text" class="super-form-textarea" rows="4"
                                  placeholder="<?= __('super_admin.tenants.edit.terms_placeholder') ?>"><?= htmlspecialchars($config['terms_text'] ?? '') ?></textarea>
                        <p class="super-form-help"><?= __('super_admin.tenants.edit.terms_help') ?></p>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.tenants.edit.save_legal_btn') ?>
                        </button>
                        <?php if (!empty($config['privacy_text']) || !empty($config['terms_text'])): ?>
                            <button type="submit" name="clear_legal" value="1" class="super-btn super-btn-secondary"
                                    onclick="document.querySelector('textarea[name=privacy_text]').value=''; document.querySelector('textarea[name=terms_text]').value='';">
                                <i class="fa-solid fa-eraser"></i>
                                <?= __('super_admin.tenants.edit.clear_legal_btn') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Hierarchy Info -->
        <div class="super-card" style="margin-bottom: 1rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-sitemap"></i>
                    <?= __('super_admin.tenants.edit.hierarchy_position_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <div style="margin-bottom: 1rem;">
                    <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.tenants.edit.field_path') ?></strong>
                    <code style="background: var(--super-bg); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                        <?= htmlspecialchars($tenant['path']) ?>
                    </code>
                </div>
                <div style="margin-bottom: 1rem;">
                    <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.tenants.edit.field_depth_level') ?></strong>
                    <span><?= $tenant['depth'] ?></span>
                </div>
                <?php if ($tenant['parent_id']): ?>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.tenants.edit.field_parent_tenant') ?></strong>
                        <a href="/super-admin/tenants/<?= $tenant['parent_id'] ?>" class="super-table-link">
                            <?= htmlspecialchars($tenant['parent_name'] ?? 'ID: ' . $tenant['parent_id']) ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.tenants.edit.field_parent_tenant') ?></strong>
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.tenants.edit.root_tenant_no_parent') ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hub Settings -->
        <div class="super-card" style="margin-bottom: 1rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-network-wired"></i>
                    <?= __('super_admin.tenants.edit.hub_settings_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                    <?= __('super_admin.tenants.edit.hub_settings_desc') ?>
                </p>

                <div style="margin-bottom: 1rem;">
                    <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.tenants.edit.hub_current_status_label') ?></strong>
                    <?php if ($tenant['allows_subtenants']): ?>
                        <span class="super-badge super-badge-purple"><?= __('super_admin.tenants.edit.hub_enabled_badge') ?></span>
                    <?php else: ?>
                        <span class="super-badge super-badge-secondary"><?= __('super_admin.tenants.edit.standard_tenant_badge') ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($tenant['allows_subtenants']): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.tenants.edit.max_depth_label') ?></strong>
                        <span><?= $tenant['max_depth'] > 0 ? __('super_admin.tenants.edit.max_depth_levels', ['count' => $tenant['max_depth']]) : __('super_admin.tenants.edit.max_depth_unlimited') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/toggle-hub">
                    <?= Csrf::field() ?>
                    <?php if ($tenant['allows_subtenants']): ?>
                        <input type="hidden" name="enable" value="0">
                        <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                onclick="return confirm('<?= __('super_admin.tenants.edit.disable_hub_confirm') ?>');">
                            <i class="fa-solid fa-toggle-off"></i>
                            <?= __('super_admin.tenants.edit.disable_hub_btn') ?>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="enable" value="1">
                        <button type="submit" class="super-btn super-btn-success" style="width: 100%; justify-content: center;">
                            <i class="fa-solid fa-toggle-on"></i>
                            <?= __('super_admin.tenants.edit.enable_hub_btn') ?>
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Move/Re-parent Tenant -->
        <?php if (!empty($availableParents) && (int)$tenant['id'] !== 1): ?>
            <div class="super-card" style="margin-bottom: 1rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-sitemap"></i>
                        <?= $tenant['parent_id'] ? __('super_admin.tenants.edit.move_tenant_card_title') : __('super_admin.tenants.edit.assign_to_parent_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <?php if ($tenant['parent_id']): ?>
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                            <?= __('super_admin.tenants.edit.move_tenant_desc') ?>
                        </p>
                    <?php else: ?>
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                            <?= __('super_admin.tenants.edit.root_tenant_desc') ?>
                        </p>
                    <?php endif; ?>
                    <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/move">
                        <?= Csrf::field() ?>
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.tenants.edit.new_parent_label') ?></label>
                            <select name="new_parent_id" class="super-form-select" required>
                                <option value=""><?= __('super_admin.tenants.edit.new_parent_placeholder') ?></option>
                                <?php foreach ($availableParents as $parent): ?>
                                    <?php if ($parent['id'] != $tenant['id']): ?>
                                        <?php $isCurrent = ($parent['id'] == $tenant['parent_id']); ?>
                                        <option value="<?= $parent['id'] ?>" <?= $isCurrent ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($parent['display_name']) ?>
                                            <?= $isCurrent ? ' ' . __('super_admin.tenants.edit.current_parent_suffix') : '' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="super-form-help"><?= __('super_admin.tenants.edit.new_parent_help') ?></p>
                        </div>
                        <button type="submit" class="super-btn super-btn-warning" style="width: 100%; justify-content: center;"
                                onclick="return confirm('<?= $tenant['parent_id'] ? __('super_admin.tenants.edit.move_tenant_confirm_move') : __('super_admin.tenants.edit.move_tenant_confirm_assign') ?>');">
                            <i class="fa-solid fa-exchange-alt"></i>
                            <?= $tenant['parent_id'] ? __('super_admin.tenants.edit.move_tenant_btn') : __('super_admin.tenants.edit.assign_subtenant_btn') ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tenant Status Toggle -->
        <?php if ((int)$tenant['id'] !== 1): ?>
            <?php if ((int)$tenant['is_active'] === 1): ?>
                <!-- Danger Zone - Deactivate -->
                <div class="super-card" style="border-color: var(--super-danger);">
                    <div class="super-card-header" style="background: rgba(239, 68, 68, 0.1);">
                        <h3 class="super-card-title" style="color: var(--super-danger);">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <?= __('super_admin.tenants.edit.danger_zone_card_title') ?>
                        </h3>
                    </div>
                    <div class="super-card-body">
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                            <?= __('super_admin.tenants.edit.deactivate_desc') ?>
                        </p>
                        <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/delete">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('<?= __('super_admin.tenants.edit.deactivate_confirm') ?>');">
                                <i class="fa-solid fa-power-off"></i>
                                <?= __('super_admin.tenants.edit.deactivate_btn') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reactivate -->
                <div class="super-card" style="border-color: var(--super-success);">
                    <div class="super-card-header" style="background: rgba(34, 197, 94, 0.1);">
                        <h3 class="super-card-title" style="color: var(--super-success);">
                            <i class="fa-solid fa-heart-pulse"></i>
                            <?= __('super_admin.tenants.edit.reactivate_card_title') ?>
                        </h3>
                    </div>
                    <div class="super-card-body">
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                            <?= __('super_admin.tenants.edit.reactivate_desc') ?>
                        </p>
                        <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/reactivate">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-success" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-power-off"></i>
                                <?= __('super_admin.tenants.edit.reactivate_btn') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
