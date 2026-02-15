<?php
/**
 * WebP Image Converter - Gold Standard v2.1
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Admin\WebPConverter;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'WebP Converter';
$adminPageSubtitle = 'System';
$adminPageIcon = 'fa-image';

// Include standalone admin header
require __DIR__ . '/../modern/admin-legacy/partials/admin-header.php';

// Initialize converter
$converter = new WebPConverter();
$cwebpAvailable = $converter->isCwebpAvailable();

// Handle form submission
$results = null;
$error = null;
$stats = $converter->getStats();
$oversizedStats = $converter->getOversizedStats(1920);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else if ($_POST['action'] === 'convert_all') {
        // Increase limits for batch conversion
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '512M');

        try {
            $results = $converter->convertAll();
            // Refresh stats
            $stats = $converter->getStats();
        } catch (\Throwable $e) {
            $error = 'Conversion failed: ' . $e->getMessage();
        }
    }
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-image"></i>
            WebP Image Converter
        </h1>
        <p class="admin-page-subtitle">Convert images to WebP format for 25-35% smaller file sizes and faster page loads</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/settings" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
    </div>
</div>

<?php if (!$cwebpAvailable): ?>
<!-- cwebp Not Installed Warning -->
<div class="admin-glass-card config-warning-card" style="max-width: 1200px;">
    <div class="config-warning-content">
        <div class="config-warning-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="config-warning-text">
            <strong>cwebp not installed</strong>
            <p>The cwebp command-line tool is required for WebP conversion. Install it using:</p>
            <div class="config-code-block">
                <code>Ubuntu/Debian:</code> sudo apt-get install webp<br>
                <code>CentOS/RHEL:</code> sudo yum install libwebp-tools<br>
                <code>macOS:</code> brew install webp<br>
                <code>Windows:</code> choco install webp
            </div>
            <p class="config-hint">After installation, refresh this page to verify.</p>
        </div>
    </div>
</div>
<?php else: ?>
<!-- cwebp Available Status -->
<div class="admin-glass-card status-card-success" style="max-width: 1200px;">
    <div class="status-card-content">
        <div class="status-card-icon">
            <i class="fa-solid fa-check"></i>
        </div>
        <div class="status-card-text">
            <strong>cwebp is installed and ready</strong>
            <p>WebP conversion is available on this server</p>
        </div>
        <div class="status-indicator status-active"></div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="native-stats-grid" style="max-width: 1200px;">
    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-images">
            <i class="fa-solid fa-images"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['total_images']) ?></div>
            <div class="stat-card-label">Total Images</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-photo-film"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-webp">
            <i class="fa-solid fa-file-image"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['total_webp']) ?></div>
            <div class="stat-card-label">WebP Versions</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-check-circle"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-coverage">
            <i class="fa-solid fa-percentage"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $stats['coverage_percent'] ?>%</div>
            <div class="stat-card-label">Coverage</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native <?= $stats['missing_webp'] > 0 ? 'stat-card-attention' : 'stat-card-highlight' ?>">
        <div class="stat-card-icon <?= $stats['missing_webp'] > 0 ? 'stat-icon-missing' : 'stat-icon-complete' ?>">
            <i class="fa-solid fa-<?= $stats['missing_webp'] > 0 ? 'clock' : 'check-double' ?>"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['missing_webp']) ?></div>
            <div class="stat-card-label"><?= $stats['missing_webp'] > 0 ? 'Missing WebP' : 'All Converted' ?></div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-<?= $stats['missing_webp'] > 0 ? 'hourglass-half' : 'trophy' ?>"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native <?= $oversizedStats['count'] > 0 ? 'stat-card-attention' : 'stat-card-highlight' ?>">
        <div class="stat-card-icon <?= $oversizedStats['count'] > 0 ? 'stat-icon-oversized' : 'stat-icon-complete' ?>">
            <i class="fa-solid fa-<?= $oversizedStats['count'] > 0 ? 'expand' : 'compress' ?>"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($oversizedStats['count']) ?></div>
            <div class="stat-card-label"><?= $oversizedStats['count'] > 0 ? 'Oversized (>1920px)' : 'All Optimized' ?></div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-<?= $oversizedStats['count'] > 0 ? 'up-right-and-down-left-from-center' : 'check' ?>"></i>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="webp-content-grid" style="max-width: 1200px;">
    <!-- Conversion Form Card -->
    <?php if ($cwebpAvailable): ?>
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #6366f1);">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Convert Images</h3>
                <p class="admin-card-subtitle">Convert all images to WebP format</p>
            </div>
        </div>
        <div class="admin-card-body">
            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fa-solid fa-times-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div id="conversionForm">
                <input type="hidden" id="csrf_token" value="<?= Csrf::generate() ?>">

                <div class="form-group">
                    <label class="form-label">Directories to Scan</label>
                    <div class="webp-dir-list">
                        <div class="webp-dir-item">
                            <i class="fa-solid fa-folder"></i>
                            <code>httpdocs/assets/img</code>
                            <span>Platform images</span>
                        </div>
                        <div class="webp-dir-item">
                            <i class="fa-solid fa-folder"></i>
                            <code>httpdocs/uploads</code>
                            <span>User uploads</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Quality Settings</label>
                    <div class="quality-display">
                        <div class="quality-value">
                            <span class="quality-number">85</span>
                            <span class="quality-label">Quality</span>
                        </div>
                        <div class="quality-info">
                            <i class="fa-solid fa-info-circle"></i>
                            <span>Optimal balance between file size and visual quality</span>
                        </div>
                    </div>
                </div>

                <!-- Batch Progress (hidden initially) -->
                <div id="batchProgress" style="display: none;">
                    <div class="batch-progress-header">
                        <span id="batchStatusText">Preparing...</span>
                        <span id="batchCounter">0 / 0</span>
                    </div>
                    <div class="webp-progress-bar">
                        <div class="webp-progress-fill" id="batchProgressBar" style="width: 0%"></div>
                    </div>
                    <div id="batchCurrentFile" class="batch-current-file"></div>
                </div>

                <button type="button"
                        id="convertBtn"
                        class="admin-btn admin-btn-primary admin-btn-lg admin-btn-block">
                    <i class="fa-solid fa-play"></i>
                    Convert All Images
                </button>

                <?php if ($stats['missing_webp'] > 0): ?>
                <p class="convert-hint" id="convertHint">
                    <i class="fa-solid fa-info-circle"></i>
                    This will convert <?= number_format($stats['missing_webp']) ?> images to WebP format
                </p>
                <?php else: ?>
                <p class="convert-hint convert-hint-success">
                    <i class="fa-solid fa-check-circle"></i>
                    All images already have WebP versions!
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resize Oversized Images Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fa-solid fa-compress-arrows-alt"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Resize Oversized Images</h3>
                <p class="admin-card-subtitle">Downscale images larger than 1920px</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div id="resizeForm">
                <div class="form-group">
                    <label class="form-label">Maximum Dimension</label>
                    <div class="quality-display">
                        <div class="quality-value" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <span class="quality-number">1920</span>
                            <span class="quality-label">pixels</span>
                        </div>
                        <div class="quality-info">
                            <i class="fa-solid fa-info-circle"></i>
                            <span>Images wider or taller than this will be resized</span>
                        </div>
                    </div>
                </div>

                <!-- Resize Progress (hidden initially) -->
                <div id="resizeProgress" style="display: none;">
                    <div class="batch-progress-header">
                        <span id="resizeStatusText">Preparing...</span>
                        <span id="resizeCounter">0 / 0</span>
                    </div>
                    <div class="webp-progress-bar">
                        <div class="webp-progress-fill" id="resizeProgressBar" style="width: 0%"></div>
                    </div>
                    <div id="resizeCurrentFile" class="batch-current-file"></div>
                </div>

                <button type="button"
                        id="resizeBtn"
                        class="admin-btn admin-btn-danger admin-btn-lg admin-btn-block"
                        <?= $oversizedStats['count'] === 0 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-compress"></i>
                    Resize Oversized Images
                </button>

                <?php if ($oversizedStats['count'] > 0): ?>
                <p class="convert-hint" id="resizeHint">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    Found <?= number_format($oversizedStats['count']) ?> oversized images
                    (<?= number_format($oversizedStats['total_size'] / 1048576, 1) ?> MB)
                    <?php if ($oversizedStats['max_width'] > 0): ?>
                    <br><small>Largest: <?= $oversizedStats['max_width'] ?>x<?= $oversizedStats['max_height'] ?>px</small>
                    <?php endif; ?>
                </p>
                <?php else: ?>
                <p class="convert-hint convert-hint-success">
                    <i class="fa-solid fa-check-circle"></i>
                    All images are within size limits!
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Quick Actions</h3>
                <p class="admin-card-subtitle">Common tasks and system status</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="quick-actions-list">
                <a href="<?= $basePath ?>/admin-legacy/image-settings" class="quick-action-item">
                    <div class="quick-action-icon quick-action-settings">
                        <i class="fa-solid fa-gear"></i>
                    </div>
                    <div class="quick-action-text">
                        <strong>Image Settings</strong>
                        <span>Configure image optimization</span>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin-legacy" class="quick-action-item">
                    <div class="quick-action-icon quick-action-dashboard">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                    <div class="quick-action-text">
                        <strong>Performance Dashboard</strong>
                        <span>View site performance metrics</span>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <div class="quick-action-item quick-action-status">
                    <div class="quick-action-icon quick-action-cwebp">
                        <i class="fa-solid fa-terminal"></i>
                    </div>
                    <div class="quick-action-text">
                        <strong>cwebp Status</strong>
                        <span class="<?= $cwebpAvailable ? 'status-connected' : 'status-disconnected' ?>">
                            <?= $cwebpAvailable ? 'Installed & Ready' : 'Not Installed' ?>
                        </span>
                    </div>
                    <div class="status-indicator <?= $cwebpAvailable ? 'status-active' : 'status-inactive' ?>"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Section -->
<div class="admin-glass-card" style="max-width: 1200px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Conversion Progress</h3>
            <p class="admin-card-subtitle">WebP conversion coverage status</p>
        </div>
        <span class="coverage-badge <?= $stats['coverage_percent'] == 100 ? 'coverage-complete' : '' ?>">
            <?= $stats['coverage_percent'] ?>% Complete
        </span>
    </div>
    <div class="admin-card-body">
        <div class="webp-progress-container">
            <div class="webp-progress-bar">
                <div class="webp-progress-fill" style="width: <?= $stats['coverage_percent'] ?>%"></div>
            </div>
            <div class="webp-progress-stats">
                <span><?= number_format($stats['total_webp']) ?> / <?= number_format($stats['total_images']) ?> images converted</span>
            </div>
        </div>

        <?php if ($stats['potential_savings'] > 0): ?>
        <div class="potential-savings-card">
            <div class="potential-savings-icon">
                <i class="fa-solid fa-piggy-bank"></i>
            </div>
            <div class="potential-savings-content">
                <div class="potential-savings-value">
                    ~<?= number_format($stats['potential_savings'] / 1048576, 1) ?> MB
                </div>
                <div class="potential-savings-label">
                    Potential file size reduction
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($results): ?>
<!-- Conversion Results -->
<div class="admin-glass-card" style="max-width: 1200px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
            <i class="fa-solid fa-clipboard-check"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Conversion Results</h3>
            <p class="admin-card-subtitle">Summary of the conversion process</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="webp-results-summary">
            <div class="webp-result-stat success">
                <i class="fa-solid fa-check-circle"></i>
                <strong><?= $results['converted'] ?></strong>
                <span>Converted</span>
            </div>
            <div class="webp-result-stat warning">
                <i class="fa-solid fa-forward"></i>
                <strong><?= $results['skipped'] ?></strong>
                <span>Skipped</span>
            </div>
            <div class="webp-result-stat error">
                <i class="fa-solid fa-times-circle"></i>
                <strong><?= $results['failed'] ?></strong>
                <span>Failed</span>
            </div>
        </div>

        <?php if ($results['converted'] > 0): ?>
        <div class="webp-savings-card">
            <div class="webp-savings-icon">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div class="webp-savings-content">
                <div class="webp-savings-value">
                    <?= number_format($results['total_savings'] / 1048576, 2) ?> MB
                </div>
                <div class="webp-savings-label">
                    Space Saved
                    <?php
                    $savingsPercent = $results['total_original_size'] > 0
                        ? round(($results['total_savings'] / $results['total_original_size']) * 100, 1)
                        : 0;
                    ?>
                    <span class="webp-savings-percent">(<?= $savingsPercent ?>% reduction)</span>
                </div>
            </div>
        </div>

        <details class="webp-file-details">
            <summary>
                <i class="fa-solid fa-list"></i>
                View Converted Files (<?= $results['converted'] ?>)
            </summary>
            <div class="webp-file-list">
                <?php foreach ($results['files'] as $file): ?>
                    <?php if ($file['success'] && !isset($file['skipped'])): ?>
                    <div class="webp-file-item">
                        <div class="webp-file-path">
                            <i class="fa-solid fa-image"></i>
                            <?= htmlspecialchars(basename($file['file'])) ?>
                        </div>
                        <div class="webp-file-savings">
                            <?= number_format($file['original_size'] / 1024, 1) ?> KB
                            <i class="fa-solid fa-arrow-right"></i>
                            <?= number_format($file['webp_size'] / 1024, 1) ?> KB
                            <span class="webp-savings-badge"><?= $file['savings'] ?>%</span>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Performance Impact -->
<div class="admin-glass-card" style="max-width: 1200px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ec4899, #be185d);">
            <i class="fa-solid fa-rocket"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Performance Impact</h3>
            <p class="admin-card-subtitle">Expected improvements from WebP conversion</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="webp-impact-grid">
            <div class="webp-impact-item">
                <div class="webp-impact-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    <i class="fa-solid fa-compress"></i>
                </div>
                <div class="webp-impact-title">File Size</div>
                <div class="webp-impact-value">25-35%</div>
                <div class="webp-impact-detail">Smaller files</div>
            </div>

            <div class="webp-impact-item">
                <div class="webp-impact-icon" style="background: linear-gradient(135deg, #22c55e, #10b981);">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="webp-impact-title">Load Time</div>
                <div class="webp-impact-value">40-60%</div>
                <div class="webp-impact-detail">Faster loading</div>
            </div>

            <div class="webp-impact-item">
                <div class="webp-impact-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fa-solid fa-gauge-high"></i>
                </div>
                <div class="webp-impact-title">Lighthouse</div>
                <div class="webp-impact-value">+10-15</div>
                <div class="webp-impact-detail">Score boost</div>
            </div>

            <div class="webp-impact-item">
                <div class="webp-impact-icon" style="background: linear-gradient(135deg, #ec4899, #be185d);">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <div class="webp-impact-title">Support</div>
                <div class="webp-impact-value">95%+</div>
                <div class="webp-impact-detail">Browser coverage</div>
            </div>
        </div>
    </div>
</div>

<!-- How to Use -->
<div class="admin-glass-card" style="max-width: 1200px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
            <i class="fa-solid fa-code"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">How to Use WebP Images</h3>
            <p class="admin-card-subtitle">Integration guide for your templates</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="webp-usage-section">
            <h4><i class="fa-solid fa-star"></i> Recommended: Helper Function</h4>
            <p>Use the <code>webp_image()</code> helper in your templates for automatic WebP serving with fallback:</p>

            <div class="webp-code-block">
                <div class="webp-code-label">Before:</div>
                <pre><code>&lt;img src="&lt;?= $post-&gt;image_url ?&gt;" alt="&lt;?= $post-&gt;title ?&gt;"&gt;</code></pre>
            </div>

            <div class="webp-code-block webp-code-block-success">
                <div class="webp-code-label">After:</div>
                <pre><code>&lt;?= webp_image($post-&gt;image_url, $post-&gt;title, 'post-image') ?&gt;</code></pre>
            </div>

            <div class="webp-benefits">
                <div class="webp-benefit"><i class="fa-solid fa-check"></i> Automatic WebP serving with fallback</div>
                <div class="webp-benefit"><i class="fa-solid fa-check"></i> 95% of users get smaller WebP files</div>
                <div class="webp-benefit"><i class="fa-solid fa-check"></i> 5% get original (older browsers)</div>
                <div class="webp-benefit"><i class="fa-solid fa-check"></i> 100% compatibility guaranteed</div>
            </div>
        </div>
    </div>
</div>

<style>
/* Page Header Extension */
.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-page-header-content {
    flex: 1;
}

.admin-page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.25rem;
}

.admin-page-title i {
    color: #a5b4fc;
}

.admin-page-subtitle {
    margin: 0;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-page-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Status Card Success */
.status-card-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%) !important;
    border-color: rgba(34, 197, 94, 0.3) !important;
}

.status-card-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-card-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #22c55e, #10b981);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.status-card-icon i {
    color: white;
    font-size: 1.25rem;
}

.status-card-text {
    flex: 1;
}

.status-card-text strong {
    display: block;
    color: #22c55e;
    font-size: 1.1rem;
}

.status-card-text p {
    margin: 0.25rem 0 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

/* Config Warning Card */
.config-warning-card {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}

.config-warning-content {
    display: flex;
    gap: 1.25rem;
    padding: 0.5rem;
}

.config-warning-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.config-warning-icon i {
    font-size: 1.5rem;
    color: white;
}

.config-warning-text {
    flex: 1;
    color: #fca5a5;
}

.config-warning-text strong {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: #ef4444;
}

.config-warning-text p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

.config-warning-text code {
    background: rgba(0, 0, 0, 0.3);
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-family: 'Monaco', 'Menlo', monospace;
}

.config-code-block {
    background: rgba(0, 0, 0, 0.2);
    padding: 12px 16px;
    border-radius: 8px;
    margin: 0.75rem 0;
    line-height: 1.8;
}

.config-hint {
    opacity: 0.7;
    font-size: 0.85rem !important;
}

/* Stats Grid */
.native-stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card-native {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card-native:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.stat-card-highlight {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(16, 185, 129, 0.1) 100%) !important;
    border-color: rgba(34, 197, 94, 0.3) !important;
}

.stat-card-attention {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}

.stat-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-icon-images {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(79, 70, 229, 0.2));
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.stat-icon-webp {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.2));
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-icon-coverage {
    background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
    border: 1px solid rgba(251, 146, 60, 0.3);
    color: #fdba74;
}

.stat-icon-missing {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.stat-icon-complete {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-card-content {
    flex: 1;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-card-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.stat-card-decoration {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.15);
}

/* Content Grid */
.webp-content-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Card Header */
.admin-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.admin-card-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.admin-card-header-icon i {
    font-size: 1.25rem;
    color: white;
}

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #fff;
}

.admin-card-subtitle {
    margin: 0.25rem 0 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Coverage Badge */
.coverage-badge {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.08);
    padding: 0.4rem 1rem;
    border-radius: 20px;
    margin-left: auto;
}

.coverage-badge.coverage-complete {
    background: rgba(34, 197, 94, 0.15);
    color: #86efac;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.alert-error {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 0.75rem;
    color: #fca5a5;
    margin-bottom: 1.25rem;
}

/* Directory List */
.webp-dir-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.webp-dir-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
}

.webp-dir-item i {
    color: #6366f1;
}

.webp-dir-item code {
    font-family: 'Monaco', 'Menlo', monospace;
    color: #a5b4fc;
    font-size: 0.9rem;
}

.webp-dir-item span {
    margin-left: auto;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Quality Display */
.quality-display {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
}

.quality-value {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 0.75rem;
}

.quality-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: white;
    line-height: 1;
}

.quality-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.quality-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.quality-info i {
    color: #6366f1;
}

/* Button Extensions */
.admin-btn-lg {
    padding: 0.9rem 1.5rem;
    font-size: 1rem;
}

.admin-btn-block {
    width: 100%;
    justify-content: center;
}

/* Convert Hint */
.convert-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.convert-hint-success {
    color: #86efac;
}

/* Batch Progress */
.batch-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
}

.batch-current-file {
    margin-top: 0.75rem;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: #a5b4fc;
    text-align: center;
    padding: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    word-break: break-all;
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a) !important;
}

.admin-btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
}

.admin-btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
}

.stat-icon-oversized {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

/* Quick Actions */
.quick-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-action-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-action-item:not(.quick-action-status):hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.2);
}

.quick-action-status {
    cursor: default;
}

.quick-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.quick-action-settings {
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.2), rgba(75, 85, 99, 0.2));
    border: 1px solid rgba(107, 114, 128, 0.3);
    color: #9ca3af;
}

.quick-action-dashboard {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.quick-action-cwebp {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.2));
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.quick-action-text {
    flex: 1;
}

.quick-action-text strong {
    display: block;
    color: #fff;
    font-size: 0.95rem;
    margin-bottom: 0.15rem;
}

.quick-action-text span {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.quick-action-text .status-connected {
    color: #86efac;
}

.quick-action-text .status-disconnected {
    color: #fca5a5;
}

.quick-action-arrow {
    color: rgba(255, 255, 255, 0.3);
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status-indicator.status-active {
    background: #22c55e;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
}

.status-indicator.status-inactive {
    background: #ef4444;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

/* Progress Section */
.webp-progress-container {
    margin-bottom: 1.5rem;
}

.webp-progress-bar {
    height: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 0.75rem;
}

.webp-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
    transition: width 0.5s ease;
    border-radius: 6px;
}

.webp-progress-stats {
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.potential-savings-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 12px;
}

.potential-savings-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #22c55e, #10b981);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.potential-savings-icon i {
    font-size: 1.25rem;
    color: white;
}

.potential-savings-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #22c55e;
}

.potential-savings-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

/* Results Summary */
.webp-results-summary {
    display: flex;
    gap: 2rem;
    justify-content: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 16px;
}

.webp-result-stat {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.webp-result-stat i {
    font-size: 1.5rem;
}

.webp-result-stat strong {
    font-size: 2rem;
}

.webp-result-stat.success { color: #22c55e; }
.webp-result-stat.warning { color: #f59e0b; }
.webp-result-stat.error { color: #ef4444; }

.webp-savings-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.05));
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 16px;
    margin-bottom: 1.5rem;
}

.webp-savings-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #22c55e, #10b981);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.webp-savings-icon i {
    font-size: 1.75rem;
    color: white;
}

.webp-savings-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #22c55e;
}

.webp-savings-label {
    color: rgba(255, 255, 255, 0.8);
}

.webp-savings-percent {
    color: rgba(255, 255, 255, 0.6);
}

/* File Details */
.webp-file-details {
    margin-top: 1.5rem;
}

.webp-file-details summary {
    cursor: pointer;
    padding: 1rem 1.5rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    transition: all 0.2s;
}

.webp-file-details summary:hover {
    background: rgba(99, 102, 241, 0.15);
}

.webp-file-list {
    max-height: 400px;
    overflow-y: auto;
    margin-top: 1rem;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.2);
}

.webp-file-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.webp-file-path {
    font-family: 'Monaco', 'Menlo', monospace;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.9);
}

.webp-file-path i {
    color: #6366f1;
}

.webp-file-savings {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.webp-savings-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Impact Grid */
.webp-impact-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
}

.webp-impact-item {
    text-align: center;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    transition: all 0.2s;
}

.webp-impact-item:hover {
    transform: translateY(-4px);
    background: rgba(255, 255, 255, 0.06);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
}

.webp-impact-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.webp-impact-icon i {
    font-size: 1.5rem;
    color: white;
}

.webp-impact-title {
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
}

.webp-impact-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #6366f1;
    margin-bottom: 0.25rem;
}

.webp-impact-detail {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

/* Usage Section */
.webp-usage-section h4 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    color: #fff;
}

.webp-usage-section h4 i {
    color: #f59e0b;
}

.webp-usage-section p {
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 1rem;
}

.webp-usage-section code {
    background: rgba(99, 102, 241, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Monaco', 'Menlo', monospace;
    color: #a5b4fc;
}

.webp-code-block {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin: 1rem 0;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.webp-code-block-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.2);
}

.webp-code-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.webp-code-block pre {
    margin: 0;
    overflow-x: auto;
}

.webp-code-block code {
    font-family: 'Monaco', 'Menlo', monospace;
    color: #22c55e;
    background: none;
    padding: 0;
}

.webp-benefits {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.webp-benefit {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.9);
}

.webp-benefit i {
    color: #22c55e;
}

/* Responsive */
@media (max-width: 1400px) {
    .native-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1200px) {
    .native-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .webp-impact-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1200px) {
    .webp-content-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 900px) {
    .webp-content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .native-stats-grid {
        grid-template-columns: 1fr;
    }

    .webp-impact-grid {
        grid-template-columns: 1fr;
    }

    .webp-benefits {
        grid-template-columns: 1fr;
    }

    .config-warning-content {
        flex-direction: column;
    }

    .webp-results-summary {
        flex-direction: column;
        gap: 1rem;
    }

    .webp-savings-card {
        flex-direction: column;
        text-align: center;
    }

    .webp-file-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<script>
(function() {
    const btn = document.getElementById('convertBtn');
    const csrfToken = document.getElementById('csrf_token')?.value;
    const batchProgress = document.getElementById('batchProgress');
    const batchStatusText = document.getElementById('batchStatusText');
    const batchCounter = document.getElementById('batchCounter');
    const batchProgressBar = document.getElementById('batchProgressBar');
    const batchCurrentFile = document.getElementById('batchCurrentFile');
    const convertHint = document.getElementById('convertHint');

    let isConverting = false;
    let results = { converted: 0, skipped: 0, failed: 0, totalSavings: 0 };

    btn?.addEventListener('click', async function() {
        if (isConverting) return;

        if (!confirm('Convert all images to WebP? This processes images one at a time to avoid timeouts.')) {
            return;
        }

        isConverting = true;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Starting...';
        batchProgress.style.display = 'block';
        if (convertHint) convertHint.style.display = 'none';

        try {
            // Step 1: Get list of pending images
            batchStatusText.textContent = 'Scanning for images...';
            const pendingResponse = await fetch('<?= $basePath ?>/admin-legacy/webp-converter/convert', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&action=get_pending`
            });

            const pendingData = await pendingResponse.json();

            if (!pendingData.success) {
                throw new Error(pendingData.error || 'Failed to get pending images');
            }

            const images = pendingData.images;
            const total = images.length;

            if (total === 0) {
                batchStatusText.textContent = 'No images need conversion!';
                btn.innerHTML = '<i class="fa-solid fa-check"></i> All Done';
                return;
            }

            batchCounter.textContent = `0 / ${total}`;
            batchStatusText.textContent = 'Converting images...';

            // Step 2: Convert images one by one
            for (let i = 0; i < images.length; i++) {
                const imagePath = images[i];
                const filename = imagePath.split(/[/\\]/).pop();

                batchCurrentFile.textContent = filename;
                batchCounter.textContent = `${i + 1} / ${total}`;
                batchProgressBar.style.width = `${((i + 1) / total) * 100}%`;

                try {
                    const convertResponse = await fetch('<?= $basePath ?>/admin-legacy/webp-converter/convert', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `csrf_token=${encodeURIComponent(csrfToken)}&action=convert_single&image_path=${encodeURIComponent(imagePath)}`
                    });

                    const result = await convertResponse.json();

                    if (result.success) {
                        results.converted++;
                        if (result.savings_bytes) {
                            results.totalSavings += result.savings_bytes;
                        }
                    } else if (result.skipped) {
                        results.skipped++;
                    } else {
                        results.failed++;
                        console.warn('Failed to convert:', imagePath, result.message);
                    }
                } catch (err) {
                    results.failed++;
                    console.error('Error converting:', imagePath, err);
                }
            }

            // Step 3: Show completion
            const savedMB = (results.totalSavings / 1048576).toFixed(2);
            batchStatusText.textContent = `Done! Converted: ${results.converted}, Skipped: ${results.skipped}, Failed: ${results.failed}`;
            batchCurrentFile.textContent = `Saved ${savedMB} MB`;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Conversion Complete';
            btn.classList.remove('admin-btn-primary');
            btn.classList.add('admin-btn-success');

            // Reload page after 3 seconds to show updated stats
            setTimeout(() => location.reload(), 3000);

        } catch (error) {
            batchStatusText.textContent = 'Error: ' + error.message;
            btn.innerHTML = '<i class="fa-solid fa-times"></i> Failed';
            btn.disabled = false;
            isConverting = false;
        }
    });

    // Resize Oversized Images functionality
    const resizeBtn = document.getElementById('resizeBtn');
    const resizeProgress = document.getElementById('resizeProgress');
    const resizeStatusText = document.getElementById('resizeStatusText');
    const resizeCounter = document.getElementById('resizeCounter');
    const resizeProgressBar = document.getElementById('resizeProgressBar');
    const resizeCurrentFile = document.getElementById('resizeCurrentFile');
    const resizeHint = document.getElementById('resizeHint');

    let isResizing = false;
    let resizeResults = { resized: 0, skipped: 0, failed: 0, totalSavings: 0 };

    resizeBtn?.addEventListener('click', async function() {
        if (isResizing) return;

        if (!confirm('Resize all oversized images to max 1920px? This will permanently modify the original files. Make sure you have a backup!')) {
            return;
        }

        isResizing = true;
        resizeBtn.disabled = true;
        resizeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Starting...';
        resizeProgress.style.display = 'block';
        if (resizeHint) resizeHint.style.display = 'none';

        try {
            // Step 1: Get list of oversized images
            resizeStatusText.textContent = 'Scanning for oversized images...';
            const oversizedResponse = await fetch('<?= $basePath ?>/admin-legacy/webp-converter/convert', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&action=get_oversized&max_dimension=1920`
            });

            const oversizedData = await oversizedResponse.json();

            if (!oversizedData.success) {
                throw new Error(oversizedData.error || 'Failed to get oversized images');
            }

            const images = oversizedData.images;
            const total = images.length;

            if (total === 0) {
                resizeStatusText.textContent = 'No oversized images found!';
                resizeBtn.innerHTML = '<i class="fa-solid fa-check"></i> All Done';
                return;
            }

            resizeCounter.textContent = `0 / ${total}`;
            resizeStatusText.textContent = 'Resizing images...';

            // Step 2: Resize images one by one
            for (let i = 0; i < images.length; i++) {
                const img = images[i];
                const filename = img.path.split(/[/\\]/).pop();

                resizeCurrentFile.textContent = `${filename} (${img.width}x${img.height})`;
                resizeCounter.textContent = `${i + 1} / ${total}`;
                resizeProgressBar.style.width = `${((i + 1) / total) * 100}%`;

                try {
                    const resizeResponse = await fetch('<?= $basePath ?>/admin-legacy/webp-converter/convert', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `csrf_token=${encodeURIComponent(csrfToken)}&action=resize_single&image_path=${encodeURIComponent(img.path)}&max_dimension=1920`
                    });

                    const result = await resizeResponse.json();

                    if (result.success) {
                        resizeResults.resized++;
                        if (result.savings) {
                            resizeResults.totalSavings += result.savings;
                        }
                    } else if (result.skipped) {
                        resizeResults.skipped++;
                    } else {
                        resizeResults.failed++;
                        console.warn('Failed to resize:', img.path, result.message);
                    }
                } catch (err) {
                    resizeResults.failed++;
                    console.error('Error resizing:', img.path, err);
                }
            }

            // Step 3: Show completion
            const savedMB = (resizeResults.totalSavings / 1048576).toFixed(2);
            resizeStatusText.textContent = `Done! Resized: ${resizeResults.resized}, Skipped: ${resizeResults.skipped}, Failed: ${resizeResults.failed}`;
            resizeCurrentFile.textContent = `Saved ${savedMB} MB`;
            resizeBtn.innerHTML = '<i class="fa-solid fa-check"></i> Resize Complete';
            resizeBtn.classList.remove('admin-btn-danger');
            resizeBtn.classList.add('admin-btn-success');

            // Reload page after 3 seconds to show updated stats
            setTimeout(() => location.reload(), 3000);

        } catch (error) {
            resizeStatusText.textContent = 'Error: ' + error.message;
            resizeBtn.innerHTML = '<i class="fa-solid fa-times"></i> Failed';
            resizeBtn.disabled = false;
            isResizing = false;
        }
    });
})();
</script>

<?php require __DIR__ . '/../modern/admin-legacy/partials/admin-footer.php'; ?>
