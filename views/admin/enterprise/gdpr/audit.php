<?php
/**
 * GDPR Audit Log
 * Complete audit trail for GDPR compliance activities
 */
$pageTitle = 'GDPR Audit Log';
?>

<div class="gdpr-audit">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="/admin/enterprise">Enterprise</a></li>
                    <li class="breadcrumb-item"><a href="/admin/enterprise/gdpr">GDPR</a></li>
                    <li class="breadcrumb-item active">Audit Log</li>
                </ol>
            </nav>
            <h1>GDPR Audit Log</h1>
            <p class="text-muted">Complete audit trail of all GDPR-related activities</p>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" onclick="exportAuditLog()">
                <i class="fas fa-download"></i> Export
            </button>
            <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#scheduleReportModal">
                <i class="fas fa-calendar"></i> Schedule Report
            </button>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-mini">
                <div class="stat-value"><?= number_format($stats['total_entries'] ?? 0) ?></div>
                <div class="stat-label">Total Entries</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-mini">
                <div class="stat-value"><?= $stats['today'] ?? 0 ?></div>
                <div class="stat-label">Today</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-mini">
                <div class="stat-value"><?= $stats['this_week'] ?? 0 ?></div>
                <div class="stat-label">This Week</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-mini">
                <div class="stat-value"><?= $stats['data_exports'] ?? 0 ?></div>
                <div class="stat-label">Data Exports</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-mini">
                <div class="stat-value"><?= $stats['deletions'] ?? 0 ?></div>
                <div class="stat-label">Deletions</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-mini">
                <div class="stat-value"><?= $stats['consent_changes'] ?? 0 ?></div>
                <div class="stat-label">Consent Changes</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row align-items-end">
                <div class="col-md-2">
                    <label class="small text-muted">Action Type</label>
                    <select name="action" class="form-control form-control-sm">
                        <option value="">All Actions</option>
                        <option value="data_exported" <?= ($filters['action'] ?? '') === 'data_exported' ? 'selected' : '' ?>>Data Export</option>
                        <option value="data_deleted" <?= ($filters['action'] ?? '') === 'data_deleted' ? 'selected' : '' ?>>Data Deletion</option>
                        <option value="consent_granted" <?= ($filters['action'] ?? '') === 'consent_granted' ? 'selected' : '' ?>>Consent Granted</option>
                        <option value="consent_withdrawn" <?= ($filters['action'] ?? '') === 'consent_withdrawn' ? 'selected' : '' ?>>Consent Withdrawn</option>
                        <option value="request_created" <?= ($filters['action'] ?? '') === 'request_created' ? 'selected' : '' ?>>Request Created</option>
                        <option value="request_processed" <?= ($filters['action'] ?? '') === 'request_processed' ? 'selected' : '' ?>>Request Processed</option>
                        <option value="breach_reported" <?= ($filters['action'] ?? '') === 'breach_reported' ? 'selected' : '' ?>>Breach Reported</option>
                        <option value="user_data_accessed" <?= ($filters['action'] ?? '') === 'user_data_accessed' ? 'selected' : '' ?>>Data Accessed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">Entity Type</label>
                    <select name="entity_type" class="form-control form-control-sm">
                        <option value="">All Entities</option>
                        <option value="user" <?= ($filters['entity_type'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="request" <?= ($filters['entity_type'] ?? '') === 'request' ? 'selected' : '' ?>>GDPR Request</option>
                        <option value="consent" <?= ($filters['entity_type'] ?? '') === 'consent' ? 'selected' : '' ?>>Consent</option>
                        <option value="breach" <?= ($filters['entity_type'] ?? '') === 'breach' ? 'selected' : '' ?>>Breach</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">User</label>
                    <input type="text" name="user" class="form-control form-control-sm" placeholder="Email or ID" value="<?= htmlspecialchars($filters['user'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= $filters['from_date'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= $filters['to_date'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="/admin/enterprise/gdpr/audit" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 160px;">Timestamp</th>
                            <th style="width: 150px;">Action</th>
                            <th>Details</th>
                            <th style="width: 120px;">Entity</th>
                            <th style="width: 150px;">User</th>
                            <th style="width: 150px;">Performed By</th>
                            <th style="width: 100px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($auditLogs ?? [])): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-history fa-3x text-muted mb-3 d-block"></i>
                                    <h5 class="text-muted">No audit entries found</h5>
                                    <p class="text-muted mb-0">Adjust your filters to see more results</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="text-nowrap" title="<?= $log['created_at'] ?>">
                                            <?= date('M j, Y', strtotime($log['created_at'])) ?>
                                            <br>
                                            <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= getActionBadge($log['action']) ?>">
                                            <i class="fas fa-<?= getActionIcon($log['action']) ?> mr-1"></i>
                                            <?= formatAction($log['action']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="audit-details">
                                            <?= htmlspecialchars($log['details'] ?? '-') ?>
                                            <?php if (!empty($log['metadata'])): ?>
                                                <button type="button" class="btn btn-link btn-sm p-0 ml-2" onclick="showMetadata(<?= htmlspecialchars(json_encode($log['metadata'])) ?>)">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log['entity_type'] && $log['entity_id']): ?>
                                            <a href="<?= getEntityLink($log['entity_type'], $log['entity_id']) ?>" class="badge badge-light">
                                                <?= ucfirst($log['entity_type']) ?> #<?= $log['entity_id'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['user_id']): ?>
                                            <a href="/admin/users/<?= $log['user_id'] ?>">
                                                <?= htmlspecialchars($log['user_email'] ?? 'User #' . $log['user_id']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['performed_by']): ?>
                                            <span title="Admin #<?= $log['performed_by'] ?>">
                                                <?= htmlspecialchars($log['admin_email'] ?? 'Admin') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="small"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($auditLogs) && $totalPages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Showing <?= number_format($offset + 1) ?>-<?= number_format(min($offset + count($auditLogs), $totalCount)) ?>
                    of <?= number_format($totalCount) ?> entries
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage - 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);

                        if ($startPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1&<?= http_build_query(array_filter($filters)) ?>">1</a></li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>

                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $currentPage + 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Retention Notice -->
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle mr-2"></i>
        <strong>Data Retention:</strong> Audit logs are retained for <?= $retentionPeriod ?? '7 years' ?> in compliance with GDPR Article 30.
        Logs older than this period are automatically archived and eventually deleted.
    </div>
</div>

<!-- Metadata Modal -->
<div class="modal fade" id="metadataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Additional Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <pre id="metadataContent" class="bg-light p-3 rounded" style="max-height: 400px; overflow: auto;"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Audit Report</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="/admin/enterprise/gdpr/audit/schedule" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Report Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Weekly GDPR Compliance Report">
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="frequency" class="form-control">
                            <option value="daily">Daily</option>
                            <option value="weekly" selected>Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipients</label>
                        <input type="text" name="recipients" class="form-control" placeholder="email@example.com, another@example.com">
                        <small class="text-muted">Comma-separated email addresses</small>
                    </div>
                    <div class="form-group">
                        <label>Include</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="incAll" name="include[]" value="all" checked>
                            <label class="custom-control-label" for="incAll">All GDPR Activities</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="incExports" name="include[]" value="exports">
                            <label class="custom-control-label" for="incExports">Data Exports Only</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="incDeletions" name="include[]" value="deletions">
                            <label class="custom-control-label" for="incDeletions">Deletions Only</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="incConsent" name="include[]" value="consent">
                            <label class="custom-control-label" for="incConsent">Consent Changes Only</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.gdpr-audit .stat-mini {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.gdpr-audit .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #4e73df;
}

.gdpr-audit .stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #858796;
}

.gdpr-audit .audit-details {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gdpr-audit .table td {
    vertical-align: middle;
}
</style>

<script>
function exportAuditLog() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '/admin/enterprise/gdpr/audit/export?' + params.toString();
}

function showMetadata(metadata) {
    document.getElementById('metadataContent').textContent = JSON.stringify(metadata, null, 2);
    $('#metadataModal').modal('show');
}
</script>

<?php
function getActionBadge($action) {
    $badges = [
        'data_exported' => 'primary',
        'data_deleted' => 'danger',
        'consent_granted' => 'success',
        'consent_withdrawn' => 'warning',
        'request_created' => 'info',
        'request_processed' => 'success',
        'breach_reported' => 'danger',
        'user_data_accessed' => 'secondary',
    ];
    return $badges[$action] ?? 'light';
}

function getActionIcon($action) {
    $icons = [
        'data_exported' => 'download',
        'data_deleted' => 'trash',
        'consent_granted' => 'check',
        'consent_withdrawn' => 'undo',
        'request_created' => 'plus',
        'request_processed' => 'cog',
        'breach_reported' => 'exclamation-triangle',
        'user_data_accessed' => 'eye',
    ];
    return $icons[$action] ?? 'circle';
}

function formatAction($action) {
    return ucwords(str_replace('_', ' ', $action));
}

function getEntityLink($type, $id) {
    $links = [
        'user' => '/admin/users/',
        'request' => '/admin/enterprise/gdpr/requests/',
        'consent' => '/admin/enterprise/gdpr/consents/',
        'breach' => '/admin/enterprise/gdpr/breaches/',
    ];
    return ($links[$type] ?? '#') . $id;
}
?>
