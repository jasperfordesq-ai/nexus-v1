<?php
/**
 * Admin GDPR Consent Management - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Consent Management';
$adminPageSubtitle = 'Enterprise GDPR';
$adminPageIcon = 'fa-clipboard-check';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'consents';

// Extract data with defaults
$stats = $stats ?? [];
$consentTypes = $consentTypes ?? [];
$consents = $consents ?? [];
$selectedType = $selectedType ?? null;
$selectedTypeName = $selectedTypeName ?? null;
$filters = $filters ?? [];
$totalPages = $totalPages ?? 1;
$pageCurrent = $pageCurrent ?? 1;
$totalCount = $totalCount ?? 0;
$offset = $offset ?? 0;
$trendLabels = $trendLabels ?? ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
$trendData = $trendData ?? [50, 75, 60, 90];

// Safely extract chart data from consent types
$chartLabels = [];
$chartGranted = [];
$chartDenied = [];
foreach ($consentTypes as $ct) {
    $chartLabels[] = $ct['name'] ?? 'Unknown';
    $chartGranted[] = $ct['granted_count'] ?? 0;
    $chartDenied[] = $ct['denied_count'] ?? 0;
}

// Helper function
function getSourceBadgeClass($source) {
    return ['web' => 'primary', 'mobile' => 'info', 'api' => 'secondary', 'import' => 'warning'][$source] ?? 'default';
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Consent Management
        </h1>
        <p class="admin-page-subtitle">Manage consent types and track user consent records</p>
    </div>
    <div class="admin-page-actions">
        <button type="button" class="admin-btn admin-btn-primary" onclick="openNewConsentTypeModal()">
            <i class="fa-solid fa-plus"></i> New Consent Type
        </button>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Stats Grid -->
<div class="consent-stats-grid">
    <div class="consent-stat-card primary">
        <div class="consent-stat-icon">
            <i class="fa-solid fa-file-signature"></i>
        </div>
        <div class="consent-stat-content">
            <div class="consent-stat-value"><?= number_format($stats['total_consents'] ?? 0) ?></div>
            <div class="consent-stat-label">Total Consents</div>
        </div>
    </div>

    <div class="consent-stat-card success">
        <div class="consent-stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="consent-stat-content">
            <div class="consent-stat-value"><?= number_format($stats['consent_rate'] ?? 0, 1) ?>%</div>
            <div class="consent-stat-label">Overall Consent Rate</div>
        </div>
    </div>

    <div class="consent-stat-card info">
        <div class="consent-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="consent-stat-content">
            <div class="consent-stat-value"><?= number_format($stats['users_with_consent'] ?? 0) ?></div>
            <div class="consent-stat-label">Users with Consent</div>
        </div>
    </div>

    <div class="consent-stat-card warning">
        <div class="consent-stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="consent-stat-content">
            <div class="consent-stat-value"><?= number_format($stats['pending_reconsent'] ?? 0) ?></div>
            <div class="consent-stat-label">Pending Re-consent</div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="consent-content-grid">
    <!-- Consent Types Panel -->
    <div class="admin-glass-card consent-types-panel">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fa-solid fa-list"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Consent Types</h3>
                <p class="admin-card-subtitle">Configured consent categories</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <div class="consent-types-list">
                <?php if (!empty($consentTypes)): ?>
                    <?php foreach ($consentTypes as $type): ?>
                        <?php
                        $grantedCount = $type['granted_count'] ?? 0;
                        $deniedCount = $type['denied_count'] ?? 0;
                        $typeId = $type['id'] ?? 0;
                        $rate = ($grantedCount + $deniedCount) > 0
                            ? ($grantedCount / ($grantedCount + $deniedCount)) * 100
                            : 0;
                        $isSelected = $selectedType == $typeId;
                        $isRequired = $type['required'] ?? $type['is_required'] ?? false;
                        ?>
                        <a href="?type=<?= $typeId ?>" class="consent-type-item <?= $isSelected ? 'active' : '' ?>">
                            <div class="consent-type-header">
                                <div class="consent-type-name">
                                    <?= htmlspecialchars($type['name'] ?? 'Unnamed') ?>
                                    <?php if ($isRequired): ?>
                                        <span class="required-badge">Required</span>
                                    <?php endif; ?>
                                </div>
                                <div class="consent-type-actions-dropdown">
                                    <button type="button" class="dropdown-trigger" onclick="event.preventDefault(); event.stopPropagation(); toggleTypeMenu(<?= $typeId ?>)">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu-custom" id="typeMenu<?= $typeId ?>">
                                        <button type="button" onclick="editConsentType(<?= $typeId ?>)">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <button type="button" onclick="viewConsentHistory(<?= $typeId ?>)">
                                            <i class="fa-solid fa-clock-rotate-left"></i> History
                                        </button>
                                        <button type="button" class="danger" onclick="deleteConsentType(<?= $typeId ?>)">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p class="consent-type-desc"><?= htmlspecialchars(substr($type['description'] ?? '', 0, 80)) ?>...</p>
                            <div class="consent-type-stats">
                                <span class="stat granted">
                                    <i class="fa-solid fa-check"></i> <?= number_format($grantedCount) ?>
                                </span>
                                <span class="stat denied">
                                    <i class="fa-solid fa-xmark"></i> <?= number_format($deniedCount) ?>
                                </span>
                            </div>
                            <div class="consent-type-progress">
                                <div class="progress-fill" style="width: <?= $rate ?>%"></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-types">
                        <i class="fa-solid fa-file-signature"></i>
                        <p>No consent types configured</p>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="openNewConsentTypeModal()">
                            <i class="fa-solid fa-plus"></i> Add First Type
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Consent Records Panel -->
    <div class="admin-glass-card consent-records-panel">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">
                    <?php if ($selectedTypeName): ?>
                        Consents: <?= htmlspecialchars($selectedTypeName) ?>
                    <?php else: ?>
                        All Consent Records
                    <?php endif; ?>
                </h3>
                <p class="admin-card-subtitle">User consent history and status</p>
            </div>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" onclick="exportConsents()">
                <i class="fa-solid fa-download"></i> Export
            </button>
        </div>

        <!-- Filters -->
        <div class="consent-filters">
            <form method="GET" class="filters-form">
                <?php if ($selectedType): ?>
                    <input type="hidden" name="type" value="<?= $selectedType ?>">
                <?php endif; ?>
                <div class="filter-group">
                    <input type="text" name="search" class="filter-input" placeholder="Search user..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <div class="filter-group">
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="granted" <?= ($filters['status'] ?? '') === 'granted' ? 'selected' : '' ?>>Granted</option>
                        <option value="denied" <?= ($filters['status'] ?? '') === 'denied' ? 'selected' : '' ?>>Denied</option>
                        <option value="withdrawn" <?= ($filters['status'] ?? '') === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="period" class="filter-select">
                        <option value="">All Time</option>
                        <option value="today" <?= ($filters['period'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= ($filters['period'] ?? '') === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= ($filters['period'] ?? '') === 'month' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </div>
                <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
            </form>
        </div>

        <div class="admin-card-body" style="padding: 0;">
            <?php if (!empty($consents)): ?>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Consent Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Source</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consents as $consent): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-name"><?= htmlspecialchars($consent['username'] ?? 'User #' . $consent['user_id']) ?></div>
                                    <div class="user-email"><?= htmlspecialchars($consent['email'] ?? '') ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="type-badge"><?= htmlspecialchars($consent['consent_type_name']) ?></span>
                            </td>
                            <td>
                                <?php if ($consent['granted']): ?>
                                    <span class="status-badge granted">
                                        <i class="fa-solid fa-check"></i> Granted
                                    </span>
                                <?php elseif (!empty($consent['withdrawn_at'])): ?>
                                    <span class="status-badge withdrawn">
                                        <i class="fa-solid fa-rotate-left"></i> Withdrawn
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge denied">
                                        <i class="fa-solid fa-xmark"></i> Denied
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="date-cell" title="<?= date('Y-m-d H:i:s', strtotime($consent['created_at'])) ?>">
                                    <?= date('M j, Y', strtotime($consent['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="source-badge <?= getSourceBadgeClass($consent['source'] ?? 'web') ?>">
                                    <?= ucfirst($consent['source'] ?? 'web') ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="action-btn" onclick="viewConsentDetail(<?= $consent['id'] ?>)" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="table-footer">
                <div class="table-info">
                    Showing <?= $offset + 1 ?>-<?= min($offset + count($consents), $totalCount) ?> of <?= number_format($totalCount) ?>
                </div>
                <div class="pagination">
                    <?php for ($i = max(1, $pageCurrent - 2); $i <= min($totalPages, $pageCurrent + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>" class="page-link <?= $i === $pageCurrent ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <h3>No Consent Records Found</h3>
                <p>No records match your current filters</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Analytics Section -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Consent Analytics</h3>
            <p class="admin-card-subtitle">Visual breakdown of consent data</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="analytics-grid">
            <div class="chart-container">
                <h4 class="chart-title">Consent Rate by Type</h4>
                <div class="chart-wrapper">
                    <canvas id="consentByTypeChart"></canvas>
                </div>
            </div>
            <div class="chart-container">
                <h4 class="chart-title">Consent Trends (30 Days)</h4>
                <div class="chart-wrapper">
                    <canvas id="consentTrendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Consent Type Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="newConsentTypeModal">
    <div class="modal" role="dialog" aria-modal="true"-container">
        <div class="modal" role="dialog" aria-modal="true"-header">
            <h3 class="modal" role="dialog" aria-modal="true"-title">
                <i class="fa-solid fa-plus"></i> Create Consent Type
            </h3>
            <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeNewConsentTypeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="<?= $basePath ?>/admin-legacy/enterprise/gdpr/consents/types" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <div class="modal" role="dialog" aria-modal="true"-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Marketing Emails">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug <span class="required">*</span></label>
                        <input type="text" name="slug" class="form-control" required placeholder="e.g., marketing_emails">
                        <small class="form-hint">Unique identifier (lowercase, underscores)</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description <span class="required">*</span></label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Explain what this consent is for..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Legal Basis</label>
                    <select name="legal_basis" class="form-control">
                        <option value="consent">Consent (Art. 6(1)(a))</option>
                        <option value="contract">Contract (Art. 6(1)(b))</option>
                        <option value="legal_obligation">Legal Obligation (Art. 6(1)(c))</option>
                        <option value="vital_interests">Vital Interests (Art. 6(1)(d))</option>
                        <option value="public_task">Public Task (Art. 6(1)(e))</option>
                        <option value="legitimate_interests">Legitimate Interests (Art. 6(1)(f))</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="required">
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Required for service</span>
                        </label>
                        <small class="form-hint">Users cannot use the service without this consent</small>
                    </div>
                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="active" checked>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Active</span>
                        </label>
                        <small class="form-hint">Show this consent type to users</small>
                    </div>
                </div>
            </div>
            <div class="modal" role="dialog" aria-modal="true"-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="closeNewConsentTypeModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Create Consent Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Consent Detail Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="consentDetailModal">
    <div class="modal" role="dialog" aria-modal="true"-container modal-sm">
        <div class="modal" role="dialog" aria-modal="true"-header">
            <h3 class="modal" role="dialog" aria-modal="true"-title">
                <i class="fa-solid fa-file-lines"></i> Consent Details
            </h3>
            <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeConsentDetailModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal" role="dialog" aria-modal="true"-body" id="consentDetailContent">
            <div class="loading-state">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <span>Loading...</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Page Header */
.admin-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.admin-page-header-content {
    flex: 1;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-page-subtitle {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

.back-link {
    color: inherit;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 1;
}

/* Admin Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
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
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}

/* Stats Grid */
.consent-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.consent-stat-card {
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.consent-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--stat-color), transparent);
}

.consent-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0 25px var(--stat-glow);
}

.consent-stat-card.primary { --stat-color: #6366f1; --stat-glow: rgba(99, 102, 241, 0.3); }
.consent-stat-card.success { --stat-color: #10b981; --stat-glow: rgba(16, 185, 129, 0.3); }
.consent-stat-card.info { --stat-color: #06b6d4; --stat-glow: rgba(6, 182, 212, 0.3); }
.consent-stat-card.warning { --stat-color: #f59e0b; --stat-glow: rgba(245, 158, 11, 0.3); }

.consent-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--stat-color), var(--stat-color));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.consent-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.consent-stat-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Content Grid */
.consent-content-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Card Styles */
.admin-card-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-card-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.15rem 0 0 0;
}

/* Consent Types List */
.consent-types-list {
    max-height: 600px;
    overflow-y: auto;
}

.consent-type-item {
    display: block;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
}

.consent-type-item:hover {
    background: rgba(99, 102, 241, 0.08);
}

.consent-type-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.15));
    border-left: 3px solid #6366f1;
}

.consent-type-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.consent-type-name {
    font-weight: 700;
    color: #fff;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.required-badge {
    font-size: 0.6rem;
    padding: 0.2rem 0.5rem;
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
}

.consent-type-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0 0 0.75rem 0;
    line-height: 1.4;
}

.consent-type-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.consent-type-stats .stat {
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.consent-type-stats .stat.granted {
    color: #10b981;
}

.consent-type-stats .stat.denied {
    color: #ef4444;
}

.consent-type-progress {
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.consent-type-progress .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399);
    border-radius: 2px;
    transition: width 0.3s;
}

/* Dropdown Actions */
.consent-type-actions-dropdown {
    position: relative;
}

.dropdown-trigger {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.dropdown-trigger:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.dropdown-menu-custom {
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 140px;
    background: #1e293b;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    padding: 0.5rem;
    z-index: 100;
    display: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
}

.dropdown-menu-custom.show {
    display: block;
}

.dropdown-menu-custom button {
    width: 100%;
    padding: 0.5rem 0.75rem;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
    text-align: left;
    cursor: pointer;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.15s;
}

.dropdown-menu-custom button:hover {
    background: rgba(99, 102, 241, 0.15);
    color: #fff;
}

.dropdown-menu-custom button.danger:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.empty-types {
    text-align: center;
    padding: 3rem 1.5rem;
    color: rgba(255, 255, 255, 0.5);
}

.empty-types i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.empty-types p {
    margin: 0 0 1rem 0;
}

/* Filters */
.consent-filters {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    background: rgba(0, 0, 0, 0.2);
}

.filters-form {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    flex: 1;
    min-width: 120px;
}

.filter-input,
.filter-select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    color: #fff;
    font-size: 0.8rem;
}

.filter-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.filter-select option {
    background: #1e293b;
}

/* Table */
.admin-table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table thead {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table th {
    padding: 0.875rem 1rem;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.9);
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.06);
}

.user-cell .user-name {
    font-weight: 600;
    color: #fff;
}

.user-cell .user-email {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.type-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.6rem;
    border-radius: 2rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.status-badge.granted {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
}

.status-badge.denied {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.status-badge.withdrawn {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
}

.source-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
}

.source-badge.primary { background: rgba(99, 102, 241, 0.15); color: #a5b4fc; }
.source-badge.info { background: rgba(6, 182, 212, 0.15); color: #67e8f9; }
.source-badge.secondary { background: rgba(100, 116, 139, 0.15); color: #94a3b8; }
.source-badge.warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
.source-badge.default { background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.6); }

.date-cell {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.action-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    color: #fff;
}

/* Table Footer */
.table-footer {
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

.table-info {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}

.pagination {
    display: flex;
    gap: 0.25rem;
}

.page-link {
    padding: 0.4rem 0.7rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.page-link:hover {
    background: rgba(99, 102, 241, 0.2);
    color: #fff;
}

.page-link.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-color: transparent;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.3);
}

.empty-state h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Analytics */
.analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.chart-container {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    padding: 1.25rem;
}

.chart-wrapper {
    position: relative;
    height: 220px;
    width: 100%;
}

.chart-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    margin: 0 0 1rem 0;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-overlay.show {
    display: flex;
}

.modal-container {
    background: #0f172a;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.modal-container.modal-sm {
    max-width: 450px;
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-label .required {
    color: #ef4444;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    color: #fff;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

select.form-control option {
    background: #1e293b;
}

.form-hint {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

/* Toggle Switch */
.toggle-switch {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.toggle-switch input {
    display: none;
}

.toggle-slider {
    width: 44px;
    height: 24px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    position: relative;
    transition: all 0.2s;
    flex-shrink: 0;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.2s;
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.toggle-switch input:checked + .toggle-slider::after {
    left: 23px;
}

.toggle-label {
    font-weight: 600;
    color: #fff;
    font-size: 0.875rem;
}

.loading-state {
    text-align: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.5);
}

.loading-state i {
    font-size: 2rem;
    margin-bottom: 1rem;
    display: block;
}

/* Responsive */
@media (max-width: 1024px) {
    .consent-content-grid {
        grid-template-columns: 1fr;
    }

    .consent-types-list {
        max-height: 400px;
    }

    .analytics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        gap: 1rem;
    }

    .admin-page-actions {
        width: 100%;
    }

    .admin-page-actions .admin-btn {
        flex: 1;
        justify-content: center;
    }

    .consent-stats-grid {
        grid-template-columns: 1fr 1fr;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .filters-form {
        flex-direction: column;
    }

    .filter-group {
        width: 100%;
    }

    .admin-table {
        min-width: 600px;
    }
}

@media (max-width: 480px) {
    .consent-stats-grid {
        grid-template-columns: 1fr;
    }

    .admin-page-title {
        font-size: 1.35rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
<script>
const basePath = '<?= $basePath ?>';

// Chart.js default colors for dark mode
Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';

document.addEventListener('DOMContentLoaded', function() {
    // Consent by Type Chart
    new Chart(document.getElementById('consentByTypeChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Granted',
                data: <?= json_encode($chartGranted) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderRadius: 4
            }, {
                label: 'Denied',
                data: <?= json_encode($chartDenied) ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' } }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Consent Trends Chart
    new Chart(document.getElementById('consentTrendsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'New Consents',
                data: <?= json_encode($trendData) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });
});

// Modal functions
function openNewConsentTypeModal() {
    document.getElementById('newConsentTypeModal').classList.add('show');
}

function closeNewConsentTypeModal() {
    document.getElementById('newConsentTypeModal').classList.remove('show');
}

function closeConsentDetailModal() {
    document.getElementById('consentDetailModal').classList.remove('show');
}

function viewConsentDetail(id) {
    document.getElementById('consentDetailModal').classList.add('show');
    document.getElementById('consentDetailContent').innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading...</span></div>';

    fetch(basePath + '/admin-legacy/enterprise/gdpr/consents/' + id)
        .then(r => r.text())
        .then(html => document.getElementById('consentDetailContent').innerHTML = html)
        .catch(() => document.getElementById('consentDetailContent').innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to load details</p>');
}

// Dropdown menus
function toggleTypeMenu(id) {
    document.querySelectorAll('.dropdown-menu-custom').forEach(m => {
        if (m.id !== 'typeMenu' + id) m.classList.remove('show');
    });
    document.getElementById('typeMenu' + id).classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.consent-type-actions-dropdown')) {
        document.querySelectorAll('.dropdown-menu-custom').forEach(m => m.classList.remove('show'));
    }
});

function editConsentType(id) {
    window.location.href = basePath + '/admin-legacy/enterprise/gdpr/consents/types/' + id + '/edit';
}

function viewConsentHistory(id) {
    window.location.href = basePath + '/admin-legacy/enterprise/gdpr/consents/types/' + id + '/history';
}

function deleteConsentType(id) {
    if (confirm('Delete this consent type? This cannot be undone.')) {
        fetch(basePath + '/admin-legacy/enterprise/gdpr/consents/types/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': '<?= Csrf::generate() ?>' }
        })
        .then(() => location.reload());
    }
}

function exportConsents() {
    window.location.href = basePath + '/admin-legacy/enterprise/gdpr/consents/export?' + new URLSearchParams(window.location.search);
}

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNewConsentTypeModal();
        closeConsentDetailModal();
    }
});

// Close modals on backdrop click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
