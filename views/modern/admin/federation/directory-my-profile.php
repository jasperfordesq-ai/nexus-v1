<?php
/**
 * Federation Directory - My Profile
 * Gold Standard admin page for editing own timebank's directory listing
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'My Directory Listing';
$adminPageSubtitle = 'Federation Directory';
$adminPageIcon = 'fa-user-edit';

require __DIR__ . '/../partials/admin-header.php';

$profile = $profile ?? [];
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-edit"></i>
            My Directory Listing
        </h1>
        <p class="admin-page-subtitle">Control how your timebank appears to potential partners</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/federation/directory" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-compass"></i>
            Browse Directory
        </a>
        <a href="<?= $basePath ?>/admin-legacy/federation/partnerships" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-handshake"></i>
            Partnerships
        </a>
    </div>
</div>

<!-- Visibility Status Banner -->
<?php if (empty($profile['federation_discoverable'])): ?>
<div class="admin-alert admin-alert-warning" style="margin-bottom: 1.5rem;">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-eye-slash"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Your Listing is Hidden</div>
        <div class="admin-alert-text">Your timebank is not currently visible in the federation directory. Enable the toggle below to let other timebanks discover you.</div>
    </div>
</div>
<?php else: ?>
<div class="admin-alert admin-alert-success" style="margin-bottom: 1.5rem;">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-eye"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Your Listing is Live</div>
        <div class="admin-alert-text">Other timebanks can find and connect with you through the federation directory.</div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="admin-dashboard-grid">
    <!-- Left Column - Edit Form -->
    <div class="admin-dashboard-main">
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Directory Profile</h3>
                    <p class="admin-card-subtitle">Information shown to other timebanks</p>
                </div>
            </div>
            <div class="admin-card-body">
                <form id="profileForm">
                    <!-- Visibility Toggle -->
                    <div class="toggle-section">
                        <div class="toggle-row">
                            <div class="toggle-content">
                                <div class="toggle-label">
                                    <i class="fa-solid fa-globe"></i>
                                    List in Federation Directory
                                </div>
                                <div class="toggle-help">When enabled, other timebanks can find you and request partnerships</div>
                            </div>
                            <label class="admin-switch">
                                <input type="checkbox" id="discoverable" <?= ($profile['federation_discoverable'] ?? 0) ? 'checked' : '' ?>>
                                <span class="admin-switch-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-divider"></div>

                    <!-- Public Description -->
                    <div class="admin-form-group">
                        <label class="admin-label">
                            <i class="fa-solid fa-align-left"></i>
                            Public Description
                        </label>
                        <textarea id="description" class="admin-input" rows="4"
                            placeholder="Describe your timebank to potential partners..."><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                        <small class="admin-help-text">This will be shown in the federation directory. Max 2000 characters.</small>
                    </div>

                    <!-- Region -->
                    <div class="admin-form-group">
                        <label class="admin-label">
                            <i class="fa-solid fa-location-dot"></i>
                            Region / Location
                        </label>
                        <input type="text" id="region" class="admin-input"
                            value="<?= htmlspecialchars($profile['region'] ?? '') ?>"
                            placeholder="e.g., Dublin, Ireland">
                        <small class="admin-help-text">Helps other timebanks find you by geographic area.</small>
                    </div>

                    <!-- Categories -->
                    <div class="admin-form-group">
                        <label class="admin-label">
                            <i class="fa-solid fa-tags"></i>
                            Categories / Tags
                        </label>
                        <input type="text" id="categories" class="admin-input"
                            value="<?= htmlspecialchars($profile['categories'] ?? '') ?>"
                            placeholder="e.g., community, education, tech, sustainability">
                        <small class="admin-help-text">Comma-separated tags describing your timebank's focus areas.</small>
                    </div>

                    <div class="form-divider"></div>

                    <!-- Contact Section Header -->
                    <div class="form-section-header">
                        <i class="fa-solid fa-address-card"></i>
                        Federation Contact
                    </div>

                    <!-- Contact Name -->
                    <div class="admin-form-group">
                        <label class="admin-label">Contact Name</label>
                        <input type="text" id="contact_name" class="admin-input"
                            value="<?= htmlspecialchars($profile['contact_name'] ?? '') ?>"
                            placeholder="Name for partnership inquiries">
                    </div>

                    <!-- Contact Email -->
                    <div class="admin-form-group">
                        <label class="admin-label">Contact Email</label>
                        <input type="email" id="contact_email" class="admin-input"
                            value="<?= htmlspecialchars($profile['contact_email'] ?? '') ?>"
                            placeholder="email@example.com">
                        <small class="admin-help-text">Where partnership requests and notifications will be sent.</small>
                    </div>

                    <div class="form-divider"></div>

                    <!-- Privacy Options -->
                    <div class="toggle-section">
                        <div class="toggle-row">
                            <div class="toggle-content">
                                <div class="toggle-label">
                                    <i class="fa-solid fa-users"></i>
                                    Show Member Count
                                </div>
                                <div class="toggle-help">Display your active member count in the directory</div>
                            </div>
                            <label class="admin-switch">
                                <input type="checkbox" id="show_member_count" <?= ($profile['federation_member_count_public'] ?? 0) ? 'checked' : '' ?>>
                                <span class="admin-switch-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
                            <i class="fa-solid fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column - Preview & Tips -->
    <div class="admin-dashboard-sidebar">
        <!-- Live Preview Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-emerald">
                    <i class="fa-solid fa-eye"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Live Preview</h3>
                    <p class="admin-card-subtitle">How others see you</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="preview-card">
                    <div class="preview-header">
                        <?php if (!empty($profile['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($profile['logo_url']) ?>" alt="" class="preview-logo">
                        <?php else: ?>
                        <div class="preview-logo-placeholder">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <?php endif; ?>
                        <div class="preview-info">
                            <h4 id="previewName"><?= htmlspecialchars($profile['name'] ?? 'Your Timebank') ?></h4>
                            <span id="previewRegion" class="preview-region">
                                <?php if (!empty($profile['region'])): ?>
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($profile['region']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <p id="previewDescription" class="preview-description">
                        <?= htmlspecialchars($profile['description'] ?? 'No description yet...') ?>
                    </p>

                    <div id="previewMeta" class="preview-meta">
                        <?php if ($profile['federation_member_count_public'] ?? false): ?>
                        <span><i class="fa-solid fa-users"></i> <?= number_format($profile['member_count'] ?? 0) ?> members</span>
                        <?php endif; ?>
                    </div>

                    <div id="previewCategories" class="preview-categories">
                        <?php if (!empty($profile['categories'])): ?>
                        <?php foreach (explode(',', $profile['categories']) as $cat): ?>
                        <span class="preview-tag"><?= htmlspecialchars(trim($cat)) ?></span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="previewVisibility" class="preview-visibility <?= empty($profile['federation_discoverable']) ? 'hidden' : 'visible' ?>">
                    <?php if (empty($profile['federation_discoverable'])): ?>
                    <i class="fa-solid fa-eye-slash"></i>
                    <span>Not visible in directory</span>
                    <?php else: ?>
                    <i class="fa-solid fa-check-circle"></i>
                    <span>Visible in directory</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tips Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-amber">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Tips for Success</h3>
                    <p class="admin-card-subtitle">Make your listing stand out</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="tips-list">
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-purple">
                            <i class="fa-solid fa-pen"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">Write a Compelling Description</div>
                            <div class="tip-text">Share what makes your timebank unique and what kind of partnerships you're looking for.</div>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-cyan">
                            <i class="fa-solid fa-map-marker-alt"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">Add Your Region</div>
                            <div class="tip-text">Help nearby timebanks find you for local collaboration opportunities.</div>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-pink">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">Use Relevant Categories</div>
                            <div class="tip-text">Tags help timebanks with similar interests discover you through filtering.</div>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon tip-icon-green">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="tip-content">
                            <div class="tip-title">Show Community Size</div>
                            <div class="tip-text">Member count can demonstrate your community's scale and activity level.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-green">
                    <i class="fa-solid fa-rocket"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Quick Actions</h3>
                    <p class="admin-card-subtitle">Federation tools</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="admin-quick-actions">
                    <a href="<?= $basePath ?>/admin-legacy/federation/directory" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-purple">
                            <i class="fa-solid fa-compass"></i>
                        </div>
                        <span>Browse Directory</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/federation/partnerships" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-blue">
                            <i class="fa-solid fa-handshake"></i>
                        </div>
                        <span>Partnerships</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/federation" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-orange">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <span>Settings</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/settings#branding" class="admin-quick-action">
                        <div class="admin-quick-action-icon admin-quick-action-icon-pink">
                            <i class="fa-solid fa-image"></i>
                        </div>
                        <span>Update Logo</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Grid Layout */
.admin-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
}

@media (max-width: 1200px) {
    .admin-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

.admin-dashboard-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-dashboard-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
}

.admin-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-card-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.admin-card-header-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-card-header-icon-emerald { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.admin-card-header-icon-amber { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-card-header-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.125rem 0 0 0;
}

.admin-card-body {
    padding: 1.5rem;
}

/* Alert Banner */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
}

.admin-alert-warning {
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-alert-warning .admin-alert-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.admin-alert-warning .admin-alert-title {
    color: #f59e0b;
}

.admin-alert-success {
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-alert-success .admin-alert-icon {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.admin-alert-success .admin-alert-title {
    color: #22c55e;
}

.admin-alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-alert-content {
    flex: 1;
}

.admin-alert-title {
    font-weight: 600;
}

.admin-alert-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.25rem;
}

/* Form Styles */
.toggle-section {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
}

.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.toggle-content {
    flex: 1;
}

.toggle-label {
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.toggle-label i {
    color: #8b5cf6;
}

.toggle-help {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    flex-shrink: 0;
}

.admin-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.admin-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    transition: 0.3s;
    border-radius: 28px;
}

.admin-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.admin-switch input:checked + .admin-switch-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
}

.admin-switch input:checked + .admin-switch-slider:before {
    transform: translateX(24px);
}

.form-divider {
    height: 1px;
    background: rgba(99, 102, 241, 0.15);
    margin: 1.5rem 0;
}

.form-section-header {
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.form-section-header i {
    color: #8b5cf6;
}

.admin-form-group {
    margin-bottom: 1.25rem;
}

.admin-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 0.5rem;
}

.admin-label i {
    color: #8b5cf6;
    font-size: 0.85rem;
}

.admin-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #fff;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.admin-input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

textarea.admin-input {
    resize: vertical;
    min-height: 100px;
}

.admin-help-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-form-actions {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

/* Preview Card */
.preview-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
}

.preview-header {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.preview-logo {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    object-fit: cover;
}

.preview-logo-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 1.25rem;
}

.preview-info {
    flex: 1;
}

.preview-info h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
}

.preview-region {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.preview-region i {
    color: #8b5cf6;
}

.preview-description {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0 0 0.75rem 0;
    line-height: 1.5;
}

.preview-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 0.75rem;
}

.preview-meta span {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.preview-meta i {
    color: #8b5cf6;
}

.preview-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.preview-tag {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.preview-visibility {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.preview-visibility.hidden {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.preview-visibility.visible {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

/* Tips List */
.tips-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tip-item {
    display: flex;
    gap: 0.75rem;
}

.tip-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.tip-icon-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.tip-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }
.tip-icon-pink { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
.tip-icon-green { background: rgba(34, 197, 94, 0.2); color: #22c55e; }

.tip-content {
    flex: 1;
}

.tip-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 0.125rem;
}

.tip-text {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.4;
}

/* Quick Actions */
.admin-quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.admin-quick-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
    transition: all 0.2s;
}

.admin-quick-action:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.admin-quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.admin-quick-action-icon-purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.admin-quick-action-icon-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.admin-quick-action-icon-orange { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.admin-quick-action-icon-pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; }

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-lg {
    padding: 0.875rem 1.75rem;
    font-size: 1rem;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.admin-btn-primary:hover {
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.2);
}

/* Page Header */
.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.admin-page-title i {
    color: #8b5cf6;
}

.admin-page-subtitle {
    color: rgba(255, 255, 255, 0.6);
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
}

.admin-page-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-page-header-actions {
        width: 100%;
        flex-wrap: wrap;
    }

    .toggle-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-switch {
        margin-top: 0.75rem;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';
const csrfToken = '<?= Csrf::token() ?>';
const memberCount = <?= (int)($profile['member_count'] ?? 0) ?>;

// Update preview on input
document.getElementById('description')?.addEventListener('input', updatePreview);
document.getElementById('region')?.addEventListener('input', updatePreview);
document.getElementById('categories')?.addEventListener('input', updatePreview);
document.getElementById('show_member_count')?.addEventListener('change', updatePreview);
document.getElementById('discoverable')?.addEventListener('change', updatePreview);

function updatePreview() {
    const description = document.getElementById('description').value || 'No description yet...';
    const region = document.getElementById('region').value;
    const categories = document.getElementById('categories').value;
    const showMemberCount = document.getElementById('show_member_count').checked;
    const isDiscoverable = document.getElementById('discoverable').checked;

    // Update description with truncation
    const maxLen = 150;
    const truncatedDesc = description.length > maxLen ? description.substring(0, maxLen) + '...' : description;
    document.getElementById('previewDescription').textContent = truncatedDesc;

    // Update region
    const regionEl = document.getElementById('previewRegion');
    if (region) {
        regionEl.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + escapeHtml(region);
    } else {
        regionEl.innerHTML = '';
    }

    // Update member count
    const metaEl = document.getElementById('previewMeta');
    if (showMemberCount) {
        metaEl.innerHTML = '<span><i class="fa-solid fa-users"></i> ' + memberCount.toLocaleString() + ' members</span>';
    } else {
        metaEl.innerHTML = '';
    }

    // Update categories
    const catsEl = document.getElementById('previewCategories');
    if (categories) {
        const cats = categories.split(',').map(c => c.trim()).filter(c => c);
        catsEl.innerHTML = cats.map(c => '<span class="preview-tag">' + escapeHtml(c) + '</span>').join('');
    } else {
        catsEl.innerHTML = '';
    }

    // Update visibility status
    const visEl = document.getElementById('previewVisibility');
    if (isDiscoverable) {
        visEl.className = 'preview-visibility visible';
        visEl.innerHTML = '<i class="fa-solid fa-check-circle"></i><span>Visible in directory</span>';
    } else {
        visEl.className = 'preview-visibility hidden';
        visEl.innerHTML = '<i class="fa-solid fa-eye-slash"></i><span>Not visible in directory</span>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Form submission
document.getElementById('profileForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const data = {
        discoverable: document.getElementById('discoverable').checked,
        description: document.getElementById('description').value,
        region: document.getElementById('region').value,
        categories: document.getElementById('categories').value,
        contact_name: document.getElementById('contact_name').value,
        contact_email: document.getElementById('contact_email').value,
        show_member_count: document.getElementById('show_member_count').checked,
    };

    fetch(basePath + '/admin-legacy/federation/directory/update-profile', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            if (typeof AdminToast !== 'undefined') {
                AdminToast.show('Profile updated successfully', 'success');
            } else {
                alert('Profile updated successfully');
            }
            // Refresh page to update the alert banner
            setTimeout(() => location.reload(), 1500);
        } else {
            if (typeof AdminToast !== 'undefined') {
                AdminToast.show(result.error || 'Failed to update profile', 'error');
            } else {
                alert(result.error || 'Failed to update profile');
            }
        }
        btn.disabled = false;
        btn.innerHTML = originalText;
    })
    .catch(err => {
        if (typeof AdminToast !== 'undefined') {
            AdminToast.show('Network error. Please try again.', 'error');
        } else {
            alert('Network error. Please try again.');
        }
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
