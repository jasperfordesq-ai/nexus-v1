<?php
/**
 * Image Optimization Settings
 * Configure WebP conversion, quality settings, and auto-optimization
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Admin\WebPConverter;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Image Settings';
$adminPageSubtitle = 'Optimization';
$adminPageIcon = 'fa-image';

// Include standalone admin header
require __DIR__ . '/../modern/admin/partials/admin-header.php';

// Get current tenant configuration
$tenant = TenantContext::get();
$configJson = json_decode($tenant['configuration'] ?? '{}', true);
$imageConfig = $configJson['image_optimization'] ?? [];

// Default values
$webpQuality = $imageConfig['webp_quality'] ?? 85;
$autoConvert = $imageConfig['auto_convert'] ?? true;
$lazyLoading = $imageConfig['lazy_loading'] ?? true;
$servingEnabled = $imageConfig['serving_enabled'] ?? true;

// Get WebP converter stats
$converter = new WebPConverter();
$stats = $converter->getStats();
$cwebpAvailable = $converter->isCwebpAvailable();

// Check for success message
$saved = isset($_GET['saved']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-image"></i>
            Image Optimization Settings
        </h1>
        <p class="admin-page-subtitle">Configure WebP conversion, quality settings, and automatic optimization</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/webp-converter" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-wand-magic-sparkles"></i> WebP Converter
        </a>
    </div>
</div>

<?php if ($saved): ?>
<div class="admin-glass-card status-card-success" style="max-width: 1200px;">
    <div class="status-card-content">
        <div class="status-card-icon">
            <i class="fa-solid fa-check"></i>
        </div>
        <div class="status-card-text">
            <strong>Settings saved successfully!</strong>
            <p>Your image optimization settings have been updated.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="native-stats-grid" style="max-width: 1200px;">
    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-images">
            <i class="fa-solid fa-images"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['total_images']) ?></div>
            <div class="stat-card-label">Total Images</div>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-webp">
            <i class="fa-solid fa-file-image"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $stats['coverage_percent'] ?>%</div>
            <div class="stat-card-label">WebP Coverage</div>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-coverage">
            <i class="fa-solid fa-gauge-high"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $webpQuality ?></div>
            <div class="stat-card-label">Quality Setting</div>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native <?= $cwebpAvailable ? 'stat-card-highlight' : 'stat-card-attention' ?>">
        <div class="stat-card-icon <?= $cwebpAvailable ? 'stat-icon-complete' : 'stat-icon-missing' ?>">
            <i class="fa-solid fa-<?= $cwebpAvailable ? 'check-circle' : 'times-circle' ?>"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $cwebpAvailable ? 'Ready' : 'Missing' ?></div>
            <div class="stat-card-label">cwebp Status</div>
        </div>
    </div>
</div>

<!-- Settings Form -->
<form action="<?= $basePath ?>/admin/image-settings/save" method="POST">
    <?= Csrf::input() ?>

    <div class="settings-grid" style="max-width: 1200px;">
        <!-- WebP Serving Settings -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">WebP Serving</h3>
                    <p class="admin-card-subtitle">Control how optimized images are served</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="settings-option">
                    <div class="settings-option-content">
                        <div class="settings-option-title">Enable WebP Serving</div>
                        <div class="settings-option-desc">
                            Automatically serve WebP images to supported browsers using the <code>webp_image()</code> helper.
                            Falls back to original format for older browsers.
                        </div>
                    </div>
                    <label class="nexus-switch">
                        <input type="checkbox" name="serving_enabled" <?= $servingEnabled ? 'checked' : '' ?>>
                        <span class="nexus-slider"></span>
                    </label>
                </div>

                <div class="settings-option">
                    <div class="settings-option-content">
                        <div class="settings-option-title">Lazy Loading</div>
                        <div class="settings-option-desc">
                            Add <code>loading="lazy"</code> to images for better page load performance.
                            Images load only when they enter the viewport.
                        </div>
                    </div>
                    <label class="nexus-switch">
                        <input type="checkbox" name="lazy_loading" <?= $lazyLoading ? 'checked' : '' ?>>
                        <span class="nexus-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Auto Conversion Settings -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #22c55e, #10b981);">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Auto Conversion</h3>
                    <p class="admin-card-subtitle">Automatic WebP generation on upload</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="settings-option">
                    <div class="settings-option-content">
                        <div class="settings-option-title">Convert on Upload</div>
                        <div class="settings-option-desc">
                            Automatically generate WebP versions when users upload JPG or PNG images.
                            <?php if (!$cwebpAvailable): ?>
                            <div class="settings-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Requires cwebp to be installed on the server.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <label class="nexus-switch">
                        <input type="checkbox" name="auto_convert" <?= $autoConvert ? 'checked' : '' ?> <?= !$cwebpAvailable ? 'disabled' : '' ?>>
                        <span class="nexus-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Quality Settings -->
    <div class="admin-glass-card" style="max-width: 1200px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Quality Settings</h3>
                <p class="admin-card-subtitle">Balance between file size and visual quality</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="quality-slider-container">
                <div class="quality-slider-header">
                    <label class="quality-slider-label">WebP Quality</label>
                    <div class="quality-slider-value">
                        <span id="qualityValue"><?= $webpQuality ?></span>%
                    </div>
                </div>

                <input type="range"
                       name="webp_quality"
                       id="qualitySlider"
                       min="50"
                       max="100"
                       value="<?= $webpQuality ?>"
                       class="quality-slider">

                <div class="quality-slider-labels">
                    <span>Smaller files</span>
                    <span>Higher quality</span>
                </div>

                <div class="quality-presets">
                    <button type="button" class="quality-preset" data-quality="60">
                        <span class="preset-value">60</span>
                        <span class="preset-label">Low</span>
                    </button>
                    <button type="button" class="quality-preset" data-quality="75">
                        <span class="preset-value">75</span>
                        <span class="preset-label">Medium</span>
                    </button>
                    <button type="button" class="quality-preset active" data-quality="85">
                        <span class="preset-value">85</span>
                        <span class="preset-label">Recommended</span>
                    </button>
                    <button type="button" class="quality-preset" data-quality="95">
                        <span class="preset-value">95</span>
                        <span class="preset-label">High</span>
                    </button>
                </div>

                <div class="quality-info-box">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>
                        <strong>Quality 85</strong> is recommended for most use cases.
                        It provides excellent visual quality with 25-35% file size reduction.
                        Lower values save more space but may show compression artifacts.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Guide -->
    <div class="admin-glass-card" style="max-width: 1200px;">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                <i class="fa-solid fa-code"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">How to Use</h3>
                <p class="admin-card-subtitle">Integration guide for templates</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="usage-examples">
                <div class="usage-example">
                    <h4><i class="fa-solid fa-star"></i> Basic Usage</h4>
                    <p>Replace standard <code>&lt;img&gt;</code> tags with the <code>webp_image()</code> helper:</p>
                    <div class="code-comparison">
                        <div class="code-block code-block-before">
                            <div class="code-label">Before:</div>
                            <pre><code>&lt;img src="&lt;?= $post['image_url'] ?&gt;" alt="&lt;?= $post['title'] ?&gt;"&gt;</code></pre>
                        </div>
                        <div class="code-block code-block-after">
                            <div class="code-label">After:</div>
                            <pre><code>&lt;?= webp_image($post['image_url'], $post['title']) ?&gt;</code></pre>
                        </div>
                    </div>
                </div>

                <div class="usage-example">
                    <h4><i class="fa-solid fa-user-circle"></i> Avatar Images</h4>
                    <p>Use the <code>webp_avatar()</code> helper for user profile pictures:</p>
                    <div class="code-block code-block-after">
                        <pre><code>&lt;?= webp_avatar($user['avatar'], $user['name'], 48) ?&gt;</code></pre>
                    </div>
                </div>

                <div class="usage-example">
                    <h4><i class="fa-solid fa-palette"></i> With CSS Classes</h4>
                    <p>Add CSS classes and additional attributes:</p>
                    <div class="code-block code-block-after">
                        <pre><code>&lt;?= webp_image($image, 'Hero', 'hero-image rounded-lg', ['width' => 1200]) ?&gt;</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="form-actions" style="max-width: 1200px;">
        <a href="<?= $basePath ?>/admin/webp-converter" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Converter
        </a>
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
            <i class="fa-solid fa-save"></i> Save Settings
        </button>
    </div>
</form>

<style>
/* Page Header */
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

/* Stats Grid */
.native-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card-native {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
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

.stat-icon-complete {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-icon-missing {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
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

/* Settings Grid */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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

/* Settings Options */
.settings-option {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.settings-option:last-child {
    margin-bottom: 0;
}

.settings-option-content {
    flex: 1;
}

.settings-option-title {
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
}

.settings-option-desc {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.5;
}

.settings-option-desc code {
    background: rgba(99, 102, 241, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Monaco', 'Menlo', monospace;
    color: #a5b4fc;
    font-size: 0.85rem;
}

.settings-warning {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 8px;
    color: #fca5a5;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Switch Toggle */
.nexus-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    flex-shrink: 0;
}

.nexus-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.nexus-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.1);
    transition: 0.3s;
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.nexus-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.nexus-switch input:checked + .nexus-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
}

.nexus-switch input:checked + .nexus-slider:before {
    transform: translateX(24px);
}

.nexus-switch input:disabled + .nexus-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Quality Slider */
.quality-slider-container {
    padding: 1rem;
}

.quality-slider-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.quality-slider-label {
    font-weight: 600;
    color: #fff;
}

.quality-slider-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #6366f1;
}

.quality-slider {
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    outline: none;
    -webkit-appearance: none;
    margin-bottom: 0.75rem;
}

.quality-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    cursor: pointer;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.quality-slider::-moz-range-thumb {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    cursor: pointer;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.quality-slider-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1.5rem;
}

.quality-presets {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.quality-preset {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    color: rgba(255, 255, 255, 0.7);
}

.quality-preset:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

.quality-preset.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border-color: rgba(99, 102, 241, 0.5);
    color: #fff;
}

.preset-value {
    font-size: 1.25rem;
    font-weight: 700;
}

.preset-label {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.quality-info-box {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    line-height: 1.5;
}

.quality-info-box i {
    color: #6366f1;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.quality-info-box strong {
    color: #a5b4fc;
}

/* Usage Examples */
.usage-examples {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.usage-example h4 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #fff;
    margin: 0 0 0.75rem;
}

.usage-example h4 i {
    color: #f59e0b;
}

.usage-example p {
    color: rgba(255, 255, 255, 0.7);
    margin: 0 0 1rem;
    font-size: 0.95rem;
}

.usage-example code {
    background: rgba(99, 102, 241, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Monaco', 'Menlo', monospace;
    color: #a5b4fc;
    font-size: 0.85rem;
}

.code-comparison {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.code-block {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.code-block-before {
    border-color: rgba(239, 68, 68, 0.2);
}

.code-block-after {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.2);
}

.code-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.code-block pre {
    margin: 0;
    overflow-x: auto;
}

.code-block code {
    font-family: 'Monaco', 'Menlo', monospace;
    color: #22c55e;
    background: none;
    padding: 0;
    font-size: 0.85rem;
}

.code-block-before code {
    color: #fca5a5;
}

/* Success Card */
.status-card-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%) !important;
    border-color: rgba(34, 197, 94, 0.3) !important;
    margin-bottom: 1.5rem;
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

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.admin-btn-lg {
    padding: 0.9rem 1.5rem;
    font-size: 1rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .native-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1024px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }

    .code-comparison {
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

    .quality-presets {
        flex-wrap: wrap;
    }

    .quality-preset {
        flex: 1;
        min-width: 80px;
    }

    .form-actions {
        flex-direction: column;
        gap: 1rem;
    }

    .form-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
(function() {
    const slider = document.getElementById('qualitySlider');
    const valueDisplay = document.getElementById('qualityValue');
    const presets = document.querySelectorAll('.quality-preset');

    // Update display when slider changes
    slider.addEventListener('input', function() {
        valueDisplay.textContent = this.value;
        updateActivePreset(parseInt(this.value));
    });

    // Preset button clicks
    presets.forEach(function(preset) {
        preset.addEventListener('click', function() {
            const quality = parseInt(this.dataset.quality);
            slider.value = quality;
            valueDisplay.textContent = quality;
            updateActivePreset(quality);
        });
    });

    function updateActivePreset(value) {
        presets.forEach(function(preset) {
            const presetValue = parseInt(preset.dataset.quality);
            if (presetValue === value) {
                preset.classList.add('active');
            } else {
                preset.classList.remove('active');
            }
        });
    }
})();
</script>

<?php require __DIR__ . '/../modern/admin/partials/admin-footer.php'; ?>
