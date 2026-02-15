<?php
/**
 * Admin Newsletter Templates - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$grouped = $grouped ?? ['starter' => [], 'saved' => [], 'custom' => []];

// Admin header configuration
$adminPageTitle = 'Templates';
$adminPageSubtitle = 'Newsletters';
$adminPageIcon = 'fa-palette';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon"><i class="fa-solid fa-check-circle"></i></div>
    <div class="admin-alert-content"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <div class="admin-alert-icon"><i class="fa-solid fa-times-circle"></i></div>
    <div class="admin-alert-content"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-palette"></i>
            Template Library
        </h1>
        <p class="admin-page-subtitle">Pre-built and custom email templates for your newsletters</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Create Template
        </a>
    </div>
</div>

<?php if (!empty($grouped['starter'])): ?>
<!-- Starter Templates -->
<div class="template-section">
    <div class="template-section-header">
        <div class="template-section-icon template-section-icon-amber">
            <i class="fa-solid fa-star"></i>
        </div>
        <h2 class="template-section-title">Starter Templates</h2>
    </div>

    <div class="template-grid">
        <?php foreach ($grouped['starter'] as $template): ?>
        <div class="template-card">
            <!-- Preview Area -->
            <div class="template-preview template-preview-amber">
                <iframe src="<?= $basePath ?>/admin-legacy/newsletters/templates/preview/<?= $template['id'] ?>" class="template-iframe"></iframe>
                <div class="template-preview-overlay"></div>
            </div>

            <!-- Info -->
            <div class="template-info">
                <div class="template-header">
                    <h3 class="template-name"><?= htmlspecialchars($template['name']) ?></h3>
                    <span class="template-badge template-badge-amber">STARTER</span>
                </div>
                <p class="template-description">
                    <?= htmlspecialchars($template['description'] ?? 'Ready-to-use template') ?>
                </p>
                <div class="template-actions">
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/preview/<?= $template['id'] ?>" target="_blank" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-eye"></i> Preview
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/duplicate/<?= $template['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                        <i class="fa-solid fa-copy"></i> Use
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($grouped['saved'])): ?>
<!-- Saved Templates -->
<div class="template-section">
    <div class="template-section-header">
        <div class="template-section-icon template-section-icon-blue">
            <i class="fa-solid fa-bookmark"></i>
        </div>
        <h2 class="template-section-title">Saved Templates</h2>
    </div>

    <div class="template-grid">
        <?php foreach ($grouped['saved'] as $template): ?>
        <div class="template-card">
            <div class="template-preview template-preview-blue">
                <iframe src="<?= $basePath ?>/admin-legacy/newsletters/templates/preview/<?= $template['id'] ?>" class="template-iframe"></iframe>
                <div class="template-preview-overlay"></div>
            </div>
            <div class="template-info">
                <div class="template-header">
                    <h3 class="template-name"><?= htmlspecialchars($template['name']) ?></h3>
                    <span class="template-use-count">Used <?= $template['use_count'] ?>x</span>
                </div>
                <?php if (!empty($template['subject'])): ?>
                <p class="template-subject"><?= htmlspecialchars($template['subject']) ?></p>
                <?php endif; ?>
                <div class="template-actions">
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/edit/<?= $template['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/duplicate/<?= $template['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                        <i class="fa-solid fa-copy"></i> Use
                    </a>
                    <form action="<?= $basePath ?>/admin-legacy/newsletters/templates/delete" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this template?')">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="id" value="<?= $template['id'] ?>">
                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($grouped['custom'])): ?>
<!-- Custom Templates -->
<div class="template-section">
    <div class="template-section-header">
        <div class="template-section-icon template-section-icon-green">
            <i class="fa-solid fa-palette"></i>
        </div>
        <h2 class="template-section-title">Custom Templates</h2>
    </div>

    <div class="template-grid">
        <?php foreach ($grouped['custom'] as $template): ?>
        <div class="template-card">
            <div class="template-preview template-preview-green">
                <iframe src="<?= $basePath ?>/admin-legacy/newsletters/templates/preview/<?= $template['id'] ?>" class="template-iframe"></iframe>
                <div class="template-preview-overlay"></div>
            </div>
            <div class="template-info">
                <div class="template-header">
                    <h3 class="template-name"><?= htmlspecialchars($template['name']) ?></h3>
                    <span class="template-use-count">Used <?= $template['use_count'] ?>x</span>
                </div>
                <?php if (!empty($template['description'])): ?>
                <p class="template-description">
                    <?= htmlspecialchars(substr($template['description'], 0, 80)) ?><?= strlen($template['description']) > 80 ? '...' : '' ?>
                </p>
                <?php endif; ?>
                <div class="template-actions">
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/edit/<?= $template['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/duplicate/<?= $template['id'] ?>" class="admin-btn admin-btn-success admin-btn-sm">
                        <i class="fa-solid fa-copy"></i> Use
                    </a>
                    <form action="<?= $basePath ?>/admin-legacy/newsletters/templates/delete" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this template?')">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="id" value="<?= $template['id'] ?>">
                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($grouped['starter']) && empty($grouped['saved']) && empty($grouped['custom'])): ?>
<!-- Empty State -->
<div class="admin-glass-card">
    <div class="admin-empty-state">
        <div class="admin-empty-icon">
            <i class="fa-solid fa-palette"></i>
        </div>
        <h3 class="admin-empty-title">No templates yet</h3>
        <p class="admin-empty-text">Create your first template or run the migration to load starter templates.</p>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/templates/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
            <i class="fa-solid fa-plus"></i>
            Create Template
        </a>
    </div>
</div>
<?php endif; ?>

<style>
/* Template Section */
.template-section {
    margin-bottom: 2.5rem;
}

.template-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.template-section-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.template-section-icon-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.template-section-icon-blue {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.template-section-icon-green {
    background: linear-gradient(135deg, #22c55e, #16a34a);
}

.template-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

/* Template Grid */
.template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
}

/* Template Card */
.template-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.template-card:hover {
    transform: translateY(-4px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

/* Template Preview */
.template-preview {
    height: 160px;
    position: relative;
    overflow: hidden;
}

.template-preview-amber {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.1));
}

.template-preview-blue {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.1));
}

.template-preview-green {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.1));
}

.template-iframe {
    width: 200%;
    height: 200%;
    transform: scale(0.5);
    transform-origin: top left;
    pointer-events: none;
    border: none;
    position: absolute;
    top: 0;
    left: 0;
}

.template-preview-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, transparent 60%, rgba(15, 23, 42, 0.3));
}

/* Template Info */
.template-info {
    padding: 1.25rem;
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    gap: 0.5rem;
}

.template-name {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
}

.template-badge {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    flex-shrink: 0;
}

.template-badge-amber {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.4);
}

.template-use-count {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
    flex-shrink: 0;
}

.template-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin: 0 0 1rem 0;
    line-height: 1.4;
}

.template-subject {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin: 0 0 0.75rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Template Actions */
.template-actions {
    display: flex;
    gap: 0.5rem;
}

.template-actions .admin-btn {
    flex: 1;
    justify-content: center;
}

.template-actions form {
    flex: 0;
}

.template-actions form .admin-btn {
    flex: none;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-alert-icon {
    font-size: 1.25rem;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
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

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
}

.admin-btn-sm {
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .template-grid {
        grid-template-columns: 1fr;
    }

    .admin-page-header-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .template-actions {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .template-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
