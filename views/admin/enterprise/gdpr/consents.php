<?php
/**
 * GDPR Consent Management
 * View and manage user consents across the platform
 */
$pageTitle = 'Consent Management';
?>

<div class="consent-management">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="/admin-legacy/enterprise">Enterprise</a></li>
                    <li class="breadcrumb-item"><a href="/admin-legacy/enterprise/gdpr">GDPR</a></li>
                    <li class="breadcrumb-item active">Consents</li>
                </ol>
            </nav>
            <h1>Consent Management</h1>
            <p class="text-muted">Manage consent types and track user consent records</p>
        </div>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newConsentTypeModal">
            <i class="fas fa-plus"></i> New Consent Type
        </button>
    </div>

    <!-- Consent Overview Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="mb-0"><?= $stats['total_consents'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Total Consents</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="mb-0"><?= number_format($stats['consent_rate'] ?? 0, 1) ?>%</h3>
                            <p class="text-muted mb-0">Overall Consent Rate</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="mb-0"><?= number_format($stats['users_with_consent'] ?? 0) ?></h3>
                            <p class="text-muted mb-0">Users with Consent</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="mb-0"><?= $stats['pending_reconsent'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Pending Re-consent</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Consent Types -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list mr-2"></i> Consent Types</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($consentTypes ?? [] as $type): ?>
                            <div class="list-group-item consent-type-item <?= $selectedType == $type['id'] ? 'active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="consent-type-info">
                                        <h6 class="mb-1">
                                            <a href="?type=<?= $type['id'] ?>" class="<?= $selectedType == $type['id'] ? 'text-white' : '' ?>">
                                                <?= htmlspecialchars($type['name']) ?>
                                            </a>
                                            <?php if ($type['required']): ?>
                                                <span class="badge badge-danger ml-1">Required</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-2 small <?= $selectedType == $type['id'] ? 'text-white-50' : 'text-muted' ?>">
                                            <?= htmlspecialchars(substr($type['description'], 0, 100)) ?>...
                                        </p>
                                        <div class="consent-stats">
                                            <span class="mr-3">
                                                <i class="fas fa-check text-success"></i>
                                                <?= number_format($type['granted_count']) ?> granted
                                            </span>
                                            <span>
                                                <i class="fas fa-times text-danger"></i>
                                                <?= number_format($type['denied_count']) ?> denied
                                            </span>
                                        </div>
                                    </div>
                                    <div class="consent-type-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link <?= $selectedType == $type['id'] ? 'text-white' : '' ?>" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="#" onclick="editConsentType(<?= $type['id'] ?>)">
                                                    <i class="fas fa-edit mr-2"></i> Edit
                                                </a>
                                                <a class="dropdown-item" href="#" onclick="viewConsentHistory(<?= $type['id'] ?>)">
                                                    <i class="fas fa-history mr-2"></i> View History
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteConsentType(<?= $type['id'] ?>)">
                                                    <i class="fas fa-trash mr-2"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 4px;">
                                    <?php $rate = $type['granted_count'] + $type['denied_count'] > 0
                                        ? ($type['granted_count'] / ($type['granted_count'] + $type['denied_count'])) * 100
                                        : 0; ?>
                                    <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($consentTypes)): ?>
                            <div class="list-group-item text-center py-4">
                                <i class="fas fa-file-signature fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No consent types configured</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consent Records -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list mr-2"></i>
                            <?php if ($selectedTypeName): ?>
                                Consents: <?= htmlspecialchars($selectedTypeName) ?>
                            <?php else: ?>
                                All Consent Records
                            <?php endif; ?>
                        </h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportConsents()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card-body border-bottom py-2">
                    <form method="GET" class="row align-items-center">
                        <?php if ($selectedType): ?>
                            <input type="hidden" name="type" value="<?= $selectedType ?>">
                        <?php endif; ?>
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search user..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control form-control-sm">
                                <option value="">All Status</option>
                                <option value="granted" <?= ($filters['status'] ?? '') === 'granted' ? 'selected' : '' ?>>Granted</option>
                                <option value="denied" <?= ($filters['status'] ?? '') === 'denied' ? 'selected' : '' ?>>Denied</option>
                                <option value="withdrawn" <?= ($filters['status'] ?? '') === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="period" class="form-control form-control-sm">
                                <option value="">All Time</option>
                                <option value="today" <?= ($filters['period'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="week" <?= ($filters['period'] ?? '') === 'week' ? 'selected' : '' ?>>This Week</option>
                                <option value="month" <?= ($filters['period'] ?? '') === 'month' ? 'selected' : '' ?>>This Month</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary btn-block">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
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
                                <?php if (empty($consents ?? [])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="fas fa-inbox fa-2x text-muted mb-2 d-block"></i>
                                            <p class="text-muted mb-0">No consent records found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($consents as $consent): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?= htmlspecialchars($consent['username'] ?? 'User #' . $consent['user_id']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($consent['email'] ?? '') ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-light"><?= htmlspecialchars($consent['consent_type_name']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($consent['granted']): ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-check mr-1"></i> Granted
                                                    </span>
                                                <?php elseif ($consent['withdrawn_at']): ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-undo mr-1"></i> Withdrawn
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-times mr-1"></i> Denied
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span title="<?= date('Y-m-d H:i:s', strtotime($consent['created_at'])) ?>">
                                                    <?= date('M j, Y', strtotime($consent['created_at'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= getSourceBadge($consent['source']) ?>">
                                                    <?= ucfirst($consent['source'] ?? 'web') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="#" onclick="viewConsentDetail(<?= $consent['id'] ?>)" class="btn btn-sm btn-link">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($consents) && $totalPages > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Showing <?= $offset + 1 ?>-<?= min($offset + count($consents), $totalCount) ?> of <?= number_format($totalCount) ?>
                        </small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Consent Analytics -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i> Consent Analytics</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Consent Rate by Type</h6>
                    <canvas id="consentByTypeChart" height="200"></canvas>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Consent Trends (30 Days)</h6>
                    <canvas id="consentTrendsChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Consent Type Modal -->
<div class="modal fade" id="newConsentTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Consent Type</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="/admin-legacy/enterprise/gdpr/consents/types" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="e.g., Marketing Emails">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Slug <span class="text-danger">*</span></label>
                                <input type="text" name="slug" class="form-control" required placeholder="e.g., marketing_emails">
                                <small class="text-muted">Unique identifier (lowercase, underscores)</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="3" required placeholder="Explain what this consent is for..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Legal Basis</label>
                        <select name="legal_basis" class="form-control">
                            <option value="consent">Consent (Art. 6(1)(a))</option>
                            <option value="contract">Contract (Art. 6(1)(b))</option>
                            <option value="legal_obligation">Legal Obligation (Art. 6(1)(c))</option>
                            <option value="vital_interests">Vital Interests (Art. 6(1)(d))</option>
                            <option value="public_task">Public Task (Art. 6(1)(e))</option>
                            <option value="legitimate_interests">Legitimate Interests (Art. 6(1)(f))</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="required" name="required">
                                    <label class="custom-control-label" for="required">Required for service</label>
                                </div>
                                <small class="text-muted">Users cannot use the service without this consent</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="active" name="active" checked>
                                    <label class="custom-control-label" for="active">Active</label>
                                </div>
                                <small class="text-muted">Show this consent type to users</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Consent Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Consent Detail Modal -->
<div class="modal fade" id="consentDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consent Record Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="consentDetailContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.consent-management .stat-card {
    border: none;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

.consent-management .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.consent-management .consent-type-item {
    transition: background 0.2s;
}

.consent-management .consent-type-item:hover {
    background: #f8f9fa;
}

.consent-management .consent-type-item.active {
    background: #4e73df;
    border-color: #4e73df;
}

.consent-management .consent-type-item.active h6,
.consent-management .consent-type-item.active .consent-stats {
    color: white;
}

.consent-management .user-info {
    line-height: 1.3;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Consent by Type Chart
    new Chart(document.getElementById('consentByTypeChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($consentTypes ?? [], 'name')) ?>,
            datasets: [{
                label: 'Granted',
                data: <?= json_encode(array_column($consentTypes ?? [], 'granted_count')) ?>,
                backgroundColor: '#28a745'
            }, {
                label: 'Denied',
                data: <?= json_encode(array_column($consentTypes ?? [], 'denied_count')) ?>,
                backgroundColor: '#dc3545'
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });

    // Consent Trends Chart
    new Chart(document.getElementById('consentTrendsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels ?? ['Week 1', 'Week 2', 'Week 3', 'Week 4']) ?>,
            datasets: [{
                label: 'New Consents',
                data: <?= json_encode($trendData ?? [50, 75, 60, 90]) ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});

function viewConsentDetail(id) {
    $('#consentDetailModal').modal('show');
    fetch('/admin-legacy/enterprise/gdpr/consents/' + id)
        .then(r => r.text())
        .then(html => document.getElementById('consentDetailContent').innerHTML = html);
}

function editConsentType(id) {
    window.location.href = '/admin-legacy/enterprise/gdpr/consents/types/' + id + '/edit';
}

function deleteConsentType(id) {
    if (confirm('Delete this consent type? This cannot be undone.')) {
        fetch('/admin-legacy/enterprise/gdpr/consents/types/' + id, {method: 'DELETE'})
            .then(() => location.reload());
    }
}

function exportConsents() {
    window.location.href = '/admin-legacy/enterprise/gdpr/consents/export?' + new URLSearchParams(window.location.search);
}
</script>

<?php
function getSourceBadge($source) {
    return ['web' => 'primary', 'mobile' => 'info', 'api' => 'secondary', 'import' => 'warning'][$source] ?? 'light';
}
?>
