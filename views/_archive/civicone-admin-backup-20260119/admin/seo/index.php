<?php
/**
 * Admin SEO Settings - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'SEO';
$adminPageSubtitle = 'Optimization';
$adminPageIcon = 'fa-search';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-search"></i>
            SEO Settings
        </h1>
        <p class="admin-page-subtitle">Search engine optimization & meta configuration</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <span>SEO settings saved successfully!</span>
</div>
<?php endif; ?>

<!-- Two Column Layout -->
<div class="admin-seo-layout">
    <!-- Main Settings Column -->
    <div class="admin-seo-main">
        <!-- Global Settings Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-emerald">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Global SEO Settings</h3>
                    <p class="admin-card-subtitle">Default meta tags, Open Graph, and indexing</p>
                </div>
            </div>
            <div class="admin-card-body">
                <form action="<?= $basePath ?>/admin-legacy/seo/store" method="POST">
                    <?= Csrf::input() ?>

                    <!-- Meta Title -->
                    <div class="admin-form-group">
                        <label class="admin-label">Default Meta Title</label>
                        <p class="admin-help-text">The default title shown in search results and browser tabs.</p>
                        <input type="text" name="meta_title" value="<?= htmlspecialchars($seo['meta_title'] ?? '') ?>" class="admin-input" placeholder="Your Site Name | Tagline" maxlength="70">
                        <span class="admin-char-hint">Recommended: 50-60 characters</span>
                    </div>

                    <!-- Meta Description -->
                    <div class="admin-form-group">
                        <label class="admin-label">Default Meta Description</label>
                        <p class="admin-help-text">The description shown in search engine results. Critical for click-through rates.</p>
                        <textarea name="meta_description" rows="3" class="admin-textarea" placeholder="A compelling description of your platform..." maxlength="160"><?= htmlspecialchars($seo['meta_description'] ?? '') ?></textarea>
                        <span class="admin-char-hint">Recommended: 150-160 characters</span>
                    </div>

                    <!-- OG Image -->
                    <div class="admin-form-group">
                        <label class="admin-label">Default Open Graph Image</label>
                        <p class="admin-help-text">Image shown when your site is shared on social media.</p>
                        <input type="text" name="og_image_url" value="<?= htmlspecialchars($seo['og_image_url'] ?? '') ?>" class="admin-input" placeholder="/assets/images/og-default.jpg">
                        <span class="admin-char-hint">Recommended: 1200x630 pixels</span>
                    </div>

                    <!-- NoIndex Toggle -->
                    <div class="admin-warning-box">
                        <div class="admin-warning-content">
                            <div class="admin-warning-icon">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                            </div>
                            <div class="admin-warning-text">
                                <h4>Block Search Engine Indexing</h4>
                                <p>Enable this to add a <code>noindex</code> meta tag globally. Use for staging/development sites.</p>
                            </div>
                            <label class="admin-toggle">
                                <input type="checkbox" name="noindex" <?= !empty($seo['noindex']) ? 'checked' : '' ?>>
                                <span class="admin-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            Save SEO Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar Column -->
    <div class="admin-seo-sidebar">
        <!-- SEO Tools Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-purple">
                    <i class="fa-solid fa-toolbox"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">SEO Tools</h3>
                    <p class="admin-card-subtitle">Quick access</p>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="admin-tool-list">
                    <a href="<?= $basePath ?>/admin-legacy/seo/audit" class="admin-tool-item">
                        <div class="admin-tool-icon admin-tool-icon-emerald">
                            <i class="fa-solid fa-stethoscope"></i>
                        </div>
                        <div class="admin-tool-info">
                            <h4>SEO Audit</h4>
                            <p>Health check & issues</p>
                        </div>
                        <i class="fa-solid fa-chevron-right admin-tool-arrow"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/seo/bulk/listing" class="admin-tool-item">
                        <div class="admin-tool-icon admin-tool-icon-indigo">
                            <i class="fa-solid fa-table-list"></i>
                        </div>
                        <div class="admin-tool-info">
                            <h4>Bulk Editor</h4>
                            <p>Edit SEO in bulk</p>
                        </div>
                        <i class="fa-solid fa-chevron-right admin-tool-arrow"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/seo/redirects" class="admin-tool-item">
                        <div class="admin-tool-icon admin-tool-icon-amber">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                        </div>
                        <div class="admin-tool-info">
                            <h4>301 Redirects</h4>
                            <p>Manage URL redirects</p>
                        </div>
                        <i class="fa-solid fa-chevron-right admin-tool-arrow"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/seo/organization" class="admin-tool-item">
                        <div class="admin-tool-icon admin-tool-icon-pink">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="admin-tool-info">
                            <h4>Organization</h4>
                            <p>Schema & social links</p>
                        </div>
                        <i class="fa-solid fa-chevron-right admin-tool-arrow"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Status Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-check-double"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">SEO Features</h3>
                    <p class="admin-card-subtitle">System status</p>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="admin-status-list">
                    <div class="admin-status-item">
                        <div class="admin-status-info">
                            <span class="admin-status-name">Dynamic Sitemap</span>
                            <a href="<?= $basePath ?>/sitemap.xml" target="_blank" class="admin-status-link">
                                <i class="fa-solid fa-external-link"></i>
                            </a>
                        </div>
                        <span class="admin-status-badge admin-status-active">
                            <span class="admin-status-dot"></span> Active
                        </span>
                    </div>
                    <div class="admin-status-item">
                        <div class="admin-status-info">
                            <span class="admin-status-name">Robots.txt</span>
                            <a href="<?= $basePath ?>/robots.txt" target="_blank" class="admin-status-link">
                                <i class="fa-solid fa-external-link"></i>
                            </a>
                        </div>
                        <span class="admin-status-badge admin-status-active">
                            <span class="admin-status-dot"></span> Active
                        </span>
                    </div>
                    <div class="admin-status-item">
                        <span class="admin-status-name">Open Graph Tags</span>
                        <span class="admin-status-badge admin-status-active">
                            <span class="admin-status-dot"></span> Active
                        </span>
                    </div>
                    <div class="admin-status-item">
                        <span class="admin-status-name">Canonical URLs</span>
                        <span class="admin-status-badge admin-status-active">
                            <span class="admin-status-dot"></span> Active
                        </span>
                    </div>
                    <div class="admin-status-item">
                        <span class="admin-status-name">JSON-LD Schemas</span>
                        <span class="admin-status-badge admin-status-active">
                            <span class="admin-status-dot"></span> Active
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Two Column Layout */
.admin-seo-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
}

.admin-seo-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-seo-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.admin-alert-success {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

/* Form Elements */
.admin-form-group {
    margin-bottom: 1.5rem;
}

.admin-label {
    display: block;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

.admin-help-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 0.75rem 0;
}

.admin-input,
.admin-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.admin-input:focus,
.admin-textarea:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-input::placeholder,
.admin-textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.admin-textarea {
    resize: vertical;
    min-height: 100px;
}

.admin-char-hint {
    display: block;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.5rem;
}

/* Warning Box */
.admin-warning-box {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.admin-warning-content {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.admin-warning-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(239, 68, 68, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f87171;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-warning-text {
    flex: 1;
}

.admin-warning-text h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #fca5a5;
}

.admin-warning-text p {
    margin: 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-warning-text code {
    background: rgba(0, 0, 0, 0.3);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8rem;
    color: #fca5a5;
}

/* Toggle Switch */
.admin-toggle {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
}

.admin-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.admin-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 26px;
    transition: 0.3s;
}

.admin-toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
}

.admin-toggle input:checked + .admin-toggle-slider {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.admin-toggle input:checked + .admin-toggle-slider:before {
    transform: translateX(22px);
}

/* Form Actions */
.admin-form-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 0.5rem;
}

/* Tool List */
.admin-tool-list {
    display: flex;
    flex-direction: column;
}

.admin-tool-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    text-decoration: none;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.admin-tool-item:last-child {
    border-bottom: none;
}

.admin-tool-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-tool-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-tool-icon-emerald {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

.admin-tool-icon-indigo {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.admin-tool-icon-amber {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

.admin-tool-icon-pink {
    background: rgba(236, 72, 153, 0.15);
    color: #f472b6;
}

.admin-tool-info {
    flex: 1;
}

.admin-tool-info h4 {
    margin: 0 0 2px 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
}

.admin-tool-info p {
    margin: 0;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-tool-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.75rem;
}

.admin-tool-item:hover .admin-tool-arrow {
    color: rgba(255, 255, 255, 0.6);
}

/* Status List */
.admin-status-list {
    display: flex;
    flex-direction: column;
}

.admin-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.admin-status-item:last-child {
    border-bottom: none;
}

.admin-status-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-status-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: #fff;
}

.admin-status-link {
    color: #818cf8;
    font-size: 0.75rem;
    transition: color 0.2s;
}

.admin-status-link:hover {
    color: #a5b4fc;
}

.admin-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}

.admin-status-badge .admin-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.admin-status-active {
    color: #34d399;
}

.admin-status-active .admin-status-dot {
    background: #34d399;
    box-shadow: 0 0 6px rgba(52, 211, 153, 0.5);
}

/* Card Header Icon Colors */
.admin-card-header-icon-emerald {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

.admin-card-header-icon-purple {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.admin-card-header-icon-cyan {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 1024px) {
    .admin-seo-layout {
        grid-template-columns: 1fr;
    }

    .admin-seo-sidebar {
        order: -1;
    }
}

@media (max-width: 768px) {
    .admin-warning-content {
        flex-direction: column;
    }

    .admin-toggle {
        align-self: flex-start;
    }

    .admin-form-actions {
        justify-content: stretch;
    }

    .admin-btn-primary {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
