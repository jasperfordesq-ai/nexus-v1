<?php
/**
 * Platform Requirements Checker - Gold Standard v2.0
 * Comprehensive dependency and environment validation
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Platform Requirements';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-clipboard-check';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'monitoring';
$currentPage = 'requirements';

// Extract requirements data
$req = $requirements ?? [];
$overallStatus = $req['overall_status'] ?? 'unknown';
$php = $req['php'] ?? [];
$extensions = $req['extensions'] ?? [];
$phpFunctions = $req['php_functions'] ?? [];
$externalBinaries = $req['external_binaries'] ?? [];
$directories = $req['writable_directories'] ?? [];
$composer = $req['composer'] ?? [];
$services = $req['services'] ?? [];
$iniSettings = $req['ini_settings'] ?? [];

// Status badge helper
function getStatusBadge(string $status): string {
    $classes = [
        'pass' => 'badge-success',
        'warning' => 'badge-warning',
        'fail' => 'badge-danger',
        'info' => 'badge-info',
        'unknown' => 'badge-secondary',
    ];
    $icons = [
        'pass' => 'fa-check-circle',
        'warning' => 'fa-exclamation-triangle',
        'fail' => 'fa-times-circle',
        'info' => 'fa-info-circle',
        'unknown' => 'fa-question-circle',
    ];
    $labels = [
        'pass' => 'Pass',
        'warning' => 'Warning',
        'fail' => 'Fail',
        'info' => 'Info',
        'unknown' => 'Unknown',
    ];
    $class = $classes[$status] ?? $classes['unknown'];
    $icon = $icons[$status] ?? $icons['unknown'];
    $label = $labels[$status] ?? 'Unknown';
    return "<span class=\"req-badge {$class}\"><i class=\"fa-solid {$icon}\"></i> {$label}</span>";
}
?>

<style>
/* Requirements Page Styles */
.req-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.req-page-header-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.req-page-title {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0;
}

.req-page-title i {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
}

.req-page-subtitle {
    color: #94a3b8;
    font-size: 1rem;
    margin: 0;
    padding-left: 72px;
}

.req-page-actions {
    display: flex;
    gap: 12px;
}

/* Overall Status Banner */
.req-status-banner {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
}

.req-status-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.req-status-banner.pass::before {
    background: linear-gradient(90deg, #10b981, #34d399);
}

.req-status-banner.warning::before {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.req-status-banner.fail::before {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.req-status-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
}

.req-status-banner.pass .req-status-icon {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.req-status-banner.warning .req-status-icon {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.req-status-banner.fail .req-status-icon {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.req-status-content {
    flex: 1;
}

.req-status-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 8px 0;
}

.req-status-subtitle {
    color: #94a3b8;
    margin: 0;
    font-size: 1rem;
}

/* Section Cards */
.req-section {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    margin-bottom: 24px;
    overflow: hidden;
}

.req-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.req-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
}

.req-section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.req-section-icon.purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.req-section-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.req-section-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.req-section-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.req-section-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
.req-section-icon.pink { background: linear-gradient(135deg, #ec4899, #db2777); }

.req-section-body {
    padding: 0;
}

/* Badges */
.req-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.badge-info {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.badge-secondary {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

/* PHP Version Card */
.php-version-card {
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 24px;
}

.php-version-display {
    text-align: center;
    padding: 20px 32px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 16px;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.php-version-label {
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}

.php-version-number {
    font-size: 2rem;
    font-weight: 800;
    font-family: 'JetBrains Mono', monospace;
}

.php-version-number.pass { color: #10b981; }
.php-version-number.warning { color: #f59e0b; }
.php-version-number.fail { color: #ef4444; }

.php-version-info {
    flex: 1;
}

.php-version-message {
    font-size: 1rem;
    color: #f1f5f9;
    margin-bottom: 12px;
}

.php-version-details {
    display: flex;
    gap: 24px;
    font-size: 0.875rem;
    color: #94a3b8;
}

.php-version-details span {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Requirements Table */
.req-table {
    width: 100%;
}

.req-table-row {
    display: flex;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: background 0.15s;
}

.req-table-row:last-child {
    border-bottom: none;
}

.req-table-row:hover {
    background: rgba(99, 102, 241, 0.05);
}

.req-table-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    margin-right: 16px;
    flex-shrink: 0;
}

.req-table-icon.pass {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.req-table-icon.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.req-table-icon.fail {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.req-table-icon.info {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.req-table-content {
    flex: 1;
    min-width: 0;
}

.req-table-name {
    font-weight: 600;
    color: #f1f5f9;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.req-table-name code {
    font-family: 'JetBrains Mono', monospace;
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.85rem;
}

.req-table-desc {
    font-size: 0.8rem;
    color: #64748b;
}

.req-table-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
    color: #94a3b8;
    margin-left: 16px;
    text-align: right;
}

.req-table-badge {
    margin-left: 16px;
}

/* Extensions Grid */
.ext-summary {
    display: flex;
    gap: 24px;
    padding: 16px 24px;
    background: rgba(30, 41, 59, 0.5);
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.ext-summary-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
}

.ext-summary-item .count {
    font-weight: 700;
    font-size: 1.25rem;
}

.ext-summary-item.loaded .count { color: #10b981; }
.ext-summary-item.missing .count { color: #ef4444; }
.ext-summary-item.optional .count { color: #f59e0b; }
.ext-summary-item.total .count { color: #818cf8; }

/* Services Section */
.service-row {
    display: flex;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.service-row:last-child {
    border-bottom: none;
}

.service-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
    margin-right: 16px;
    flex-shrink: 0;
}

.service-icon.database { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.service-icon.redis { background: linear-gradient(135deg, #ef4444, #dc2626); }
.service-icon.mail { background: linear-gradient(135deg, #10b981, #059669); }
.service-icon.vault { background: linear-gradient(135deg, #f59e0b, #d97706); }

.service-content {
    flex: 1;
}

.service-name {
    font-weight: 600;
    color: #f1f5f9;
    font-size: 0.95rem;
}

.service-message {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 2px;
}

.service-latency {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    color: #10b981;
    margin-left: 16px;
}

/* INI Settings Table */
.ini-table {
    width: 100%;
}

.ini-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 16px;
    padding: 12px 24px;
    background: rgba(30, 41, 59, 0.5);
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ini-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 16px;
    padding: 14px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    align-items: center;
}

.ini-row:last-child {
    border-bottom: none;
}

.ini-name {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
    color: #f1f5f9;
}

.ini-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.875rem;
}

.ini-current { color: #f1f5f9; }
.ini-min { color: #94a3b8; }
.ini-rec { color: #818cf8; }

/* Admin Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.admin-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.admin-btn-secondary {
    background: rgba(30, 41, 59, 0.8);
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.admin-btn-secondary:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .req-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .req-page-title {
        font-size: 1.5rem;
    }

    .req-page-title i {
        width: 44px;
        height: 44px;
        font-size: 1.2rem;
    }

    .req-page-subtitle {
        padding-left: 60px;
    }

    .req-status-banner {
        flex-direction: column;
        text-align: center;
    }

    .php-version-card {
        flex-direction: column;
    }

    .ext-summary {
        flex-wrap: wrap;
    }

    .ini-header,
    .ini-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .ini-header {
        display: none;
    }
}
</style>

<!-- Page Header -->
<div class="req-page-header">
    <div class="req-page-header-content">
        <h1 class="req-page-title">
            <i class="fa-solid fa-clipboard-check"></i>
            Platform Requirements
        </h1>
        <p class="req-page-subtitle">Validate server configuration and dependencies</p>
    </div>
    <div class="req-page-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Monitoring
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/requirements" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-rotate"></i> Re-check
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Overall Status Banner -->
<div class="req-status-banner <?= $overallStatus ?>">
    <div class="req-status-icon">
        <?php if ($overallStatus === 'pass'): ?>
            <i class="fa-solid fa-shield-check"></i>
        <?php elseif ($overallStatus === 'warning'): ?>
            <i class="fa-solid fa-triangle-exclamation"></i>
        <?php else: ?>
            <i class="fa-solid fa-circle-xmark"></i>
        <?php endif; ?>
    </div>
    <div class="req-status-content">
        <?php if ($overallStatus === 'pass'): ?>
            <h2 class="req-status-title">All Requirements Met</h2>
            <p class="req-status-subtitle">Your server meets all platform requirements</p>
        <?php elseif ($overallStatus === 'warning'): ?>
            <h2 class="req-status-title">Some Recommendations</h2>
            <p class="req-status-subtitle">Platform will work but some optimizations are recommended</p>
        <?php else: ?>
            <h2 class="req-status-title">Requirements Not Met</h2>
            <p class="req-status-subtitle">Please address the issues below before continuing</p>
        <?php endif; ?>
    </div>
    <?= getStatusBadge($overallStatus) ?>
</div>

<!-- PHP Version -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon purple">
                <i class="fa-brands fa-php"></i>
            </div>
            PHP Version
        </h3>
        <?= getStatusBadge($php['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <div class="php-version-card">
            <div class="php-version-display">
                <div class="php-version-label">Current Version</div>
                <div class="php-version-number <?= $php['status'] ?? '' ?>"><?= htmlspecialchars($php['current'] ?? 'Unknown') ?></div>
            </div>
            <div class="php-version-info">
                <div class="php-version-message"><?= htmlspecialchars($php['message'] ?? '') ?></div>
                <div class="php-version-details">
                    <span><i class="fa-solid fa-check"></i> Required: <?= htmlspecialchars($php['required'] ?? 'N/A') ?>+</span>
                    <span><i class="fa-solid fa-star"></i> Recommended: <?= htmlspecialchars($php['recommended'] ?? 'N/A') ?>+</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PHP Extensions -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon green">
                <i class="fa-solid fa-puzzle-piece"></i>
            </div>
            PHP Extensions
        </h3>
        <?= getStatusBadge($extensions['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <div class="ext-summary">
            <div class="ext-summary-item total">
                <span class="count"><?= $extensions['total_loaded'] ?? 0 ?></span>
                <span class="label">Total Loaded</span>
            </div>
            <div class="ext-summary-item loaded">
                <span class="count"><?= count(array_filter($extensions['extensions'] ?? [], fn($e) => $e['loaded'] && $e['required'])) ?></span>
                <span class="label">Required (OK)</span>
            </div>
            <div class="ext-summary-item missing">
                <span class="count"><?= $extensions['missing_required'] ?? 0 ?></span>
                <span class="label">Required (Missing)</span>
            </div>
            <div class="ext-summary-item optional">
                <span class="count"><?= $extensions['missing_optional'] ?? 0 ?></span>
                <span class="label">Optional (Missing)</span>
            </div>
        </div>
        <div class="req-table">
            <?php foreach (($extensions['extensions'] ?? []) as $ext): ?>
                <div class="req-table-row">
                    <div class="req-table-icon <?= $ext['status'] ?>">
                        <i class="fa-solid fa-<?= $ext['loaded'] ? 'check' : ($ext['required'] ? 'times' : 'minus') ?>"></i>
                    </div>
                    <div class="req-table-content">
                        <div class="req-table-name">
                            <code><?= htmlspecialchars($ext['name']) ?></code>
                            <?php if ($ext['required']): ?>
                                <span style="color: #ef4444; font-size: 0.7rem; margin-left: 6px;">REQUIRED</span>
                            <?php endif; ?>
                        </div>
                        <div class="req-table-desc"><?= htmlspecialchars($ext['description']) ?></div>
                        <?php if (!empty($ext['used_in'])): ?>
                            <div class="req-table-used" style="font-size: 0.7rem; color: #64748b; margin-top: 4px;">
                                <i class="fa-solid fa-code" style="margin-right: 4px;"></i>
                                <?= htmlspecialchars(implode(', ', array_slice($ext['used_in'], 0, 3))) ?>
                                <?php if (count($ext['used_in']) > 3): ?>
                                    <span style="color: #818cf8;">+<?= count($ext['used_in']) - 3 ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="req-table-badge">
                        <?= getStatusBadge($ext['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- PHP Functions -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon cyan">
                <i class="fa-solid fa-code"></i>
            </div>
            PHP Functions
            <span style="font-weight: 400; font-size: 0.85rem; color: #94a3b8; margin-left: 8px;">
                (<?= $phpFunctions['total_checked'] ?? 0 ?> checked)
            </span>
        </h3>
        <?= getStatusBadge($phpFunctions['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <?php if (($phpFunctions['missing_critical'] ?? 0) > 0): ?>
            <div style="padding: 16px 24px; background: rgba(239, 68, 68, 0.1); border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
                <div style="color: #f87171; font-size: 0.875rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= $phpFunctions['missing_critical'] ?> critical function(s) missing
                </div>
            </div>
        <?php endif; ?>
        <div class="req-table">
            <?php foreach (($phpFunctions['functions'] ?? []) as $func): ?>
                <div class="req-table-row">
                    <div class="req-table-icon <?= $func['status'] ?>">
                        <i class="fa-solid fa-<?= $func['exists'] ? 'check' : ($func['critical'] ? 'times' : 'minus') ?>"></i>
                    </div>
                    <div class="req-table-content">
                        <div class="req-table-name">
                            <code><?= htmlspecialchars($func['name']) ?>()</code>
                            <?php if ($func['critical']): ?>
                                <span style="color: #ef4444; font-size: 0.7rem; margin-left: 6px;">CRITICAL</span>
                            <?php endif; ?>
                        </div>
                        <div class="req-table-desc">
                            <?= htmlspecialchars($func['description']) ?>
                            <span style="color: #818cf8; margin-left: 8px;">[<?= htmlspecialchars($func['extension']) ?>]</span>
                        </div>
                    </div>
                    <div class="req-table-badge">
                        <?= getStatusBadge($func['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- External Binaries -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon pink">
                <i class="fa-solid fa-terminal"></i>
            </div>
            External Binaries
        </h3>
        <?= getStatusBadge($externalBinaries['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <div class="req-table">
            <?php foreach (($externalBinaries['binaries'] ?? []) as $bin): ?>
                <div class="req-table-row">
                    <div class="req-table-icon <?= $bin['status'] ?>">
                        <i class="fa-solid fa-<?= $bin['available'] ? 'check' : ($bin['required'] ? 'times' : 'minus') ?>"></i>
                    </div>
                    <div class="req-table-content">
                        <div class="req-table-name">
                            <code><?= htmlspecialchars($bin['name']) ?></code>
                            <?php if ($bin['required']): ?>
                                <span style="color: #ef4444; font-size: 0.7rem; margin-left: 6px;">REQUIRED</span>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 0.7rem; margin-left: 6px;">OPTIONAL</span>
                            <?php endif; ?>
                        </div>
                        <div class="req-table-desc"><?= htmlspecialchars($bin['description']) ?></div>
                        <?php if ($bin['version']): ?>
                            <div style="font-size: 0.7rem; color: #10b981; margin-top: 4px; font-family: 'JetBrains Mono', monospace;">
                                <?= htmlspecialchars(substr($bin['version'], 0, 60)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="req-table-value">
                        <?= $bin['available'] ? '<span style="color: #10b981;">Available</span>' : '<span style="color: #94a3b8;">Not found</span>' ?>
                    </div>
                    <div class="req-table-badge">
                        <?= getStatusBadge($bin['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Writable Directories -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon amber">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            Writable Directories
        </h3>
        <?= getStatusBadge($directories['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <div class="req-table">
            <?php foreach (($directories['directories'] ?? []) as $dir): ?>
                <div class="req-table-row">
                    <div class="req-table-icon <?= $dir['status'] ?>">
                        <i class="fa-solid fa-<?= $dir['writable'] ? 'check' : ($dir['exists'] ? 'lock' : 'folder-minus') ?>"></i>
                    </div>
                    <div class="req-table-content">
                        <div class="req-table-name"><?= htmlspecialchars($dir['name']) ?></div>
                        <div class="req-table-desc" style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem;">
                            <?= htmlspecialchars($dir['path']) ?>
                        </div>
                    </div>
                    <div class="req-table-value">
                        <?php if (!$dir['exists']): ?>
                            <span style="color: #f59e0b;">Does not exist</span>
                        <?php elseif (!$dir['writable']): ?>
                            <span style="color: #ef4444;">Not writable</span>
                        <?php else: ?>
                            <span style="color: #10b981;">Writable</span>
                        <?php endif; ?>
                    </div>
                    <div class="req-table-badge">
                        <?= getStatusBadge($dir['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- External Services -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon cyan">
                <i class="fa-solid fa-network-wired"></i>
            </div>
            External Services
        </h3>
        <?= getStatusBadge($services['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <?php foreach (($services['services'] ?? []) as $service): ?>
            <div class="service-row">
                <div class="service-icon <?= strtolower(explode(' ', $service['name'])[0]) ?>">
                    <?php
                    $iconMap = [
                        'Database' => 'fa-database',
                        'Redis' => 'fa-bolt',
                        'SMTP' => 'fa-envelope',
                        'HashiCorp' => 'fa-key',
                    ];
                    $icon = 'fa-server';
                    foreach ($iconMap as $key => $val) {
                        if (strpos($service['name'], $key) !== false) {
                            $icon = $val;
                            break;
                        }
                    }
                    ?>
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div class="service-content">
                    <div class="service-name"><?= htmlspecialchars($service['name']) ?></div>
                    <div class="service-message"><?= htmlspecialchars($service['message'] ?? '') ?></div>
                </div>
                <?php if (isset($service['latency_ms'])): ?>
                    <div class="service-latency"><?= $service['latency_ms'] ?>ms</div>
                <?php endif; ?>
                <div class="req-table-badge">
                    <?= getStatusBadge($service['status']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Composer Packages -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon pink">
                <i class="fa-solid fa-box"></i>
            </div>
            Composer Packages
            <span style="font-weight: 400; font-size: 0.85rem; color: #94a3b8; margin-left: 8px;">
                (<?= $composer['total_packages'] ?? 0 ?> packages)
            </span>
        </h3>
        <?= getStatusBadge($composer['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <?php if (!empty($composer['issues'])): ?>
            <div style="padding: 16px 24px; background: rgba(239, 68, 68, 0.1); border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
                <?php foreach ($composer['issues'] as $issue): ?>
                    <div style="color: #f87171; font-size: 0.875rem; margin-bottom: 4px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($issue) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="req-table">
            <?php foreach (($composer['packages'] ?? []) as $pkg): ?>
                <div class="req-table-row">
                    <div class="req-table-icon <?= $pkg['status'] ?>">
                        <i class="fa-solid fa-<?= $pkg['status'] === 'pass' ? 'check' : 'times' ?>"></i>
                    </div>
                    <div class="req-table-content">
                        <div class="req-table-name"><code><?= htmlspecialchars($pkg['name']) ?></code></div>
                        <div class="req-table-desc">Required: <?= htmlspecialchars($pkg['required_version']) ?></div>
                    </div>
                    <div class="req-table-value">
                        <?= $pkg['installed_version'] ? htmlspecialchars($pkg['installed_version']) : '<span style="color: #ef4444;">Not installed</span>' ?>
                    </div>
                    <div class="req-table-badge">
                        <?= getStatusBadge($pkg['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- PHP INI Settings -->
<div class="req-section">
    <div class="req-section-header">
        <h3 class="req-section-title">
            <div class="req-section-icon red">
                <i class="fa-solid fa-sliders"></i>
            </div>
            PHP Configuration (php.ini)
        </h3>
        <?= getStatusBadge($iniSettings['status'] ?? 'unknown') ?>
    </div>
    <div class="req-section-body">
        <div class="ini-table">
            <div class="ini-header">
                <div>Setting</div>
                <div>Current</div>
                <div>Minimum</div>
                <div>Recommended</div>
                <div>Status</div>
            </div>
            <?php foreach (($iniSettings['settings'] ?? []) as $setting): ?>
                <div class="ini-row">
                    <div class="ini-name"><?= htmlspecialchars($setting['name']) ?></div>
                    <div class="ini-value ini-current"><?= htmlspecialchars($setting['current']) ?></div>
                    <div class="ini-value ini-min"><?= htmlspecialchars($setting['minimum']) ?></div>
                    <div class="ini-value ini-rec"><?= htmlspecialchars($setting['recommended']) ?></div>
                    <div><?= getStatusBadge($setting['status']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
