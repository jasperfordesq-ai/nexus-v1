<?php
/**
 * Admin 301 Redirect Manager - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = '301 Redirects';
$adminPageSubtitle = 'SEO';
$adminPageIcon = 'fa-route';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/seo" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            301 Redirects
        </h1>
        <p class="admin-page-subtitle">Manage URL redirects for SEO</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <span>Redirect saved successfully!</span>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="admin-alert admin-alert-warning">
    <i class="fa-solid fa-trash"></i>
    <span>Redirect deleted.</span>
</div>
<?php endif; ?>

<!-- Add New Redirect -->
<div class="admin-glass-card" style="max-width: 1000px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fa-solid fa-plus"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Add New Redirect</h3>
            <p class="admin-card-subtitle">Create a new 301 redirect rule</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/seo/redirects/store" method="POST" class="redirect-form">
            <?= Csrf::input() ?>
            <div class="form-group">
                <label for="source_url">Source URL</label>
                <input type="text" id="source_url" name="source_url" placeholder="/old-page" required>
                <small>The old URL path (without domain)</small>
            </div>
            <div class="form-group">
                <label for="destination_url">Destination URL</label>
                <input type="text" id="destination_url" name="destination_url" placeholder="/new-page" required>
                <small>The new URL path or full URL</small>
            </div>
            <div class="form-group form-submit">
                <button type="submit" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-plus"></i> Add Redirect
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Existing Redirects -->
<div class="admin-glass-card" style="max-width: 1000px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Active Redirects</h3>
            <p class="admin-card-subtitle"><?= count($redirects) ?> redirect<?= count($redirects) !== 1 ? 's' : '' ?> configured</p>
        </div>
    </div>

    <?php if (empty($redirects)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fa-solid fa-route"></i>
        </div>
        <h3>No Redirects Configured</h3>
        <p>Add your first redirect using the form above.</p>
    </div>
    <?php else: ?>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th style="width: 50px;"></th>
                    <th>Destination</th>
                    <th>Hits</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($redirects as $redirect): ?>
                <tr>
                    <td>
                        <code class="url-code source"><?= htmlspecialchars($redirect['source_url']) ?></code>
                    </td>
                    <td style="text-align: center; color: rgba(255,255,255,0.4);">
                        <i class="fa-solid fa-arrow-right"></i>
                    </td>
                    <td>
                        <code class="url-code destination"><?= htmlspecialchars($redirect['destination_url']) ?></code>
                    </td>
                    <td>
                        <span class="hits-badge"><?= number_format($redirect['hits'] ?? 0) ?></span>
                    </td>
                    <td class="date-cell">
                        <?= date('M j, Y', strtotime($redirect['created_at'])) ?>
                    </td>
                    <td>
                        <form action="<?= $basePath ?>/admin/seo/redirects/delete" method="POST" class="inline-form" onsubmit="return confirm('Delete this redirect?');">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= $redirect['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Info Card -->
<div class="admin-glass-card info-card" style="max-width: 1000px;">
    <div class="admin-card-body">
        <div class="info-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>
        <div class="info-content">
            <h4>About 301 Redirects</h4>
            <p>301 redirects permanently redirect visitors and search engines from old URLs to new ones. This preserves SEO value and prevents 404 errors. Use them when you change URL structures, rename pages, or merge content. The "Hits" counter shows how many times each redirect has been used.</p>
        </div>
    </div>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    max-width: 1000px;
}

.admin-alert-success {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
}

.admin-alert-warning {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}

/* Redirect Form */
.redirect-form {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
}

.redirect-form .form-group {
    flex: 1;
    min-width: 200px;
    margin-bottom: 0;
}

.redirect-form .form-submit {
    flex: none;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.form-group input {
    width: 100%;
    padding: 0.65rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.form-group input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-group small {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #34d399, #10b981);
}

/* URL Codes */
.url-code {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-family: 'Monaco', 'Menlo', monospace;
}

.url-code.source {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.url-code.destination {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

/* Hits Badge */
.hits-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #e2e8f0;
}

/* Date Cell */
.date-cell {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Inline Form */
.inline-form {
    display: inline;
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: rgba(99, 102, 241, 0.5);
}

.empty-state h3 {
    color: #fff;
    margin: 0 0 0.5rem;
}

.empty-state p {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.info-card .admin-card-body {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}

.info-content h4 {
    margin: 0 0 0.5rem;
    color: #fff;
    font-size: 1rem;
}

.info-content p {
    margin: 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Mobile */
@media (max-width: 768px) {
    .redirect-form {
        flex-direction: column;
    }

    .redirect-form .form-group,
    .redirect-form .form-submit {
        width: 100%;
    }

    .redirect-form .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .info-card .admin-card-body {
        flex-direction: column;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
