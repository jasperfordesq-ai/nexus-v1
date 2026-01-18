<?php
/**
 * GDPR Request Detail View
 * View and process individual GDPR requests
 */
$pageTitle = 'GDPR Request #' . $request['id'];
?>

<div class="gdpr-request-view">
    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/admin/enterprise">Enterprise</a></li>
                <li class="breadcrumb-item"><a href="/admin/enterprise/gdpr">GDPR</a></li>
                <li class="breadcrumb-item"><a href="/admin/enterprise/gdpr/requests">Requests</a></li>
                <li class="breadcrumb-item active">#<?= $request['id'] ?></li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>
                    Request #<?= $request['id'] ?>
                    <span class="badge badge-<?= getStatusBadge($request['status']) ?> ml-2">
                        <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                    </span>
                </h1>
                <p class="text-muted mb-0">
                    <span class="badge badge-<?= getTypeBadge($request['request_type']) ?>">
                        <i class="fas fa-<?= getTypeIcon($request['request_type']) ?> mr-1"></i>
                        <?= formatType($request['request_type']) ?>
                    </span>
                    &middot; Submitted <?= date('F j, Y \a\t g:i A', strtotime($request['created_at'])) ?>
                </p>
            </div>
            <div class="btn-group">
                <?php if ($request['status'] === 'pending'): ?>
                    <button type="button" class="btn btn-success" onclick="processRequest()">
                        <i class="fas fa-play"></i> Start Processing
                    </button>
                <?php elseif ($request['status'] === 'in_progress'): ?>
                    <button type="button" class="btn btn-primary" onclick="completeRequest()">
                        <i class="fas fa-check"></i> Mark Complete
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown">
                    <span class="sr-only">More actions</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#assignModal">
                        <i class="fas fa-user-plus mr-2"></i> Assign
                    </a>
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#noteModal">
                        <i class="fas fa-sticky-note mr-2"></i> Add Note
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php if (!in_array($request['status'], ['completed', 'rejected'])): ?>
                        <a class="dropdown-item text-danger" href="#" onclick="rejectRequest()">
                            <i class="fas fa-times mr-2"></i> Reject Request
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Request Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i> Request Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl>
                                <dt>Request Type</dt>
                                <dd>
                                    <span class="badge badge-<?= getTypeBadge($request['request_type']) ?> badge-lg">
                                        <?= formatType($request['request_type']) ?>
                                    </span>
                                </dd>

                                <dt>Requester Email</dt>
                                <dd><?= htmlspecialchars($request['email']) ?></dd>

                                <?php if ($request['user_id']): ?>
                                    <dt>Linked User Account</dt>
                                    <dd>
                                        <a href="/admin/users/<?= $request['user_id'] ?>">
                                            User #<?= $request['user_id'] ?>
                                            <?php if (!empty($user)): ?>
                                                (<?= htmlspecialchars($user['username']) ?>)
                                            <?php endif; ?>
                                        </a>
                                    </dd>
                                <?php endif; ?>

                                <dt>Verification Status</dt>
                                <dd>
                                    <?php if ($request['verified_at']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-check-circle"></i> Verified on <?= date('M j, Y', strtotime($request['verified_at'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning">
                                            <i class="fas fa-exclamation-circle"></i> Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl>
                                <dt>Submitted</dt>
                                <dd><?= date('F j, Y \a\t g:i A', strtotime($request['created_at'])) ?></dd>

                                <dt>SLA Deadline</dt>
                                <dd>
                                    <?php
                                    $deadline = strtotime($request['created_at']) + (30 * 86400);
                                    $remaining = $deadline - time();
                                    ?>
                                    <?= date('F j, Y', $deadline) ?>
                                    <?php if ($request['status'] !== 'completed' && $request['status'] !== 'rejected'): ?>
                                        <?php if ($remaining < 0): ?>
                                            <span class="badge badge-danger ml-2">OVERDUE</span>
                                        <?php elseif ($remaining < 5 * 86400): ?>
                                            <span class="badge badge-warning ml-2"><?= ceil($remaining / 86400) ?> days left</span>
                                        <?php else: ?>
                                            <span class="badge badge-success ml-2"><?= ceil($remaining / 86400) ?> days left</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </dd>

                                <?php if ($request['completed_at']): ?>
                                    <dt>Completed</dt>
                                    <dd>
                                        <?= date('F j, Y \a\t g:i A', strtotime($request['completed_at'])) ?>
                                        <br>
                                        <small class="text-muted">
                                            Processing time: <?= getProcessingTime($request['created_at'], $request['completed_at']) ?>
                                        </small>
                                    </dd>
                                <?php endif; ?>

                                <dt>Assigned To</dt>
                                <dd>
                                    <?php if ($request['assigned_to']): ?>
                                        <?= htmlspecialchars($assignee['username'] ?? 'Admin #' . $request['assigned_to']) ?>
                                        <a href="#" class="ml-2 text-muted" data-toggle="modal" data-target="#assignModal">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                        <a href="#" class="ml-2" data-toggle="modal" data-target="#assignModal">Assign</a>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($request['details'])): ?>
                        <hr>
                        <h6>Additional Details</h6>
                        <div class="request-details bg-light p-3 rounded">
                            <?= nl2br(htmlspecialchars($request['details'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Data Summary (for export/portability) -->
            <?php if (in_array($request['request_type'], ['data_export', 'data_portability', 'data_access']) && $request['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-database mr-2"></i> User Data Summary</h5>
                        <?php if ($request['status'] !== 'completed'): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="generateExport()">
                                <i class="fas fa-file-export"></i> Generate Export
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Data Category</th>
                                    <th>Records</th>
                                    <th>Size</th>
                                    <th>Include</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dataSummary ?? [] as $category => $info): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-<?= $info['icon'] ?? 'file' ?> mr-2 text-muted"></i>
                                            <?= htmlspecialchars($info['label']) ?>
                                        </td>
                                        <td><?= number_format($info['count']) ?></td>
                                        <td><?= formatSize($info['size'] ?? 0) ?></td>
                                        <td>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input export-toggle" id="inc_<?= $category ?>" checked>
                                                <label class="custom-control-label" for="inc_<?= $category ?>"></label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Deletion Preview (for deletion requests) -->
            <?php if ($request['request_type'] === 'data_deletion' && $request['user_id']): ?>
                <div class="card mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-trash mr-2"></i> Data to be Deleted</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Warning:</strong> This action is irreversible. All data below will be permanently deleted.
                        </div>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Data Category</th>
                                    <th>Records to Delete</th>
                                    <th>Retention Exception</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deletionPreview ?? [] as $category => $info): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($info['label']) ?></td>
                                        <td><?= number_format($info['count']) ?></td>
                                        <td>
                                            <?php if ($info['retained']): ?>
                                                <span class="text-warning" title="<?= htmlspecialchars($info['retention_reason']) ?>">
                                                    <i class="fas fa-lock"></i> <?= $info['retained_count'] ?> retained
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Activity Timeline -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history mr-2"></i> Activity Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($timeline ?? [] as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-<?= $event['color'] ?? 'secondary' ?>">
                                    <i class="fas fa-<?= $event['icon'] ?? 'circle' ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <strong><?= htmlspecialchars($event['action']) ?></strong>
                                        <span class="text-muted ml-2"><?= timeAgo($event['created_at']) ?></span>
                                    </div>
                                    <?php if (!empty($event['details'])): ?>
                                        <p class="mb-0 text-muted"><?= htmlspecialchars($event['details']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($event['user'])): ?>
                                        <small class="text-muted">by <?= htmlspecialchars($event['user']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($timeline)): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-primary">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <strong>Request Created</strong>
                                        <span class="text-muted ml-2"><?= timeAgo($request['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt mr-2"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <?php if ($request['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-success btn-block mb-2" onclick="processRequest()">
                            <i class="fas fa-play mr-2"></i> Start Processing
                        </button>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'in_progress'): ?>
                        <button type="button" class="btn btn-primary btn-block mb-2" onclick="completeRequest()">
                            <i class="fas fa-check mr-2"></i> Mark Complete
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($request['request_type'], ['data_export', 'data_portability']) && $request['user_id']): ?>
                        <button type="button" class="btn btn-outline-primary btn-block mb-2" onclick="generateExport()">
                            <i class="fas fa-file-export mr-2"></i> Generate Export
                        </button>
                    <?php endif; ?>

                    <?php if ($request['download_url']): ?>
                        <a href="<?= htmlspecialchars($request['download_url']) ?>" class="btn btn-outline-success btn-block mb-2" download>
                            <i class="fas fa-download mr-2"></i> Download Export
                        </a>
                    <?php endif; ?>

                    <button type="button" class="btn btn-outline-secondary btn-block" data-toggle="modal" data-target="#noteModal">
                        <i class="fas fa-sticky-note mr-2"></i> Add Note
                    </button>

                    <?php if (!in_array($request['status'], ['completed', 'rejected'])): ?>
                        <hr>
                        <button type="button" class="btn btn-outline-danger btn-block" onclick="rejectRequest()">
                            <i class="fas fa-times mr-2"></i> Reject Request
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-sticky-note mr-2"></i> Notes</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#noteModal">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($notes ?? [])): ?>
                        <p class="text-muted text-center mb-0">No notes yet</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="note-item mb-3">
                                <div class="note-content"><?= nl2br(htmlspecialchars($note['content'])) ?></div>
                                <small class="text-muted">
                                    <?= htmlspecialchars($note['author']) ?> &middot; <?= timeAgo($note['created_at']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Requests -->
            <?php if (!empty($relatedRequests)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-link mr-2"></i> Related Requests</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($relatedRequests as $related): ?>
                                <a href="/admin/enterprise/gdpr/requests/<?= $related['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <span>#<?= $related['id'] ?> - <?= formatType($related['request_type']) ?></span>
                                        <span class="badge badge-<?= getStatusBadge($related['status']) ?>">
                                            <?= ucfirst($related['status']) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= date('M j, Y', strtotime($related['created_at'])) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Request</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="/admin/enterprise/gdpr/requests/<?= $request['id'] ?>/assign" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($admins ?? [] as $admin): ?>
                                <option value="<?= $admin['id'] ?>" <?= $request['assigned_to'] == $admin['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Note Modal -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Note</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="/admin/enterprise/gdpr/requests/<?= $request['id'] ?>/notes" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.gdpr-request-view .timeline {
    position: relative;
    padding-left: 30px;
}

.gdpr-request-view .timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.gdpr-request-view .timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.gdpr-request-view .timeline-icon {
    position: absolute;
    left: -30px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.7rem;
}

.gdpr-request-view .note-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.gdpr-request-view .note-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.badge-lg {
    font-size: 1rem;
    padding: 0.5rem 0.75rem;
}
</style>

<script>
function processRequest() {
    if (confirm('Start processing this request?')) {
        fetch('/admin/enterprise/gdpr/requests/<?= $request['id'] ?>/process', {method: 'POST'})
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert(data.error || 'Failed to process request');
            });
    }
}

function completeRequest() {
    if (confirm('Mark this request as completed?')) {
        fetch('/admin/enterprise/gdpr/requests/<?= $request['id'] ?>/complete', {method: 'POST'})
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert(data.error || 'Failed to complete request');
            });
    }
}

function rejectRequest() {
    const reason = prompt('Enter rejection reason:');
    if (reason) {
        fetch('/admin/enterprise/gdpr/requests/<?= $request['id'] ?>/reject', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({reason: reason})
        }).then(() => location.reload());
    }
}

function generateExport() {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Generating...';

    fetch('/admin/enterprise/gdpr/requests/<?= $request['id'] ?>/generate-export', {method: 'POST'})
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to generate export');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-export mr-2"></i> Generate Export';
            }
        });
}
</script>

<?php
function getStatusBadge($status) {
    return ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'rejected' => 'danger'][$status] ?? 'secondary';
}

function getTypeBadge($type) {
    return ['data_export' => 'primary', 'data_deletion' => 'danger', 'data_rectification' => 'warning', 'data_portability' => 'success', 'data_access' => 'info'][$type] ?? 'secondary';
}

function getTypeIcon($type) {
    return ['data_export' => 'download', 'data_deletion' => 'trash', 'data_rectification' => 'edit', 'data_portability' => 'exchange-alt', 'data_access' => 'eye'][$type] ?? 'file';
}

function formatType($type) {
    return ucwords(str_replace(['data_', '_'], ['', ' '], $type));
}

function getProcessingTime($start, $end) {
    $diff = strtotime($end) - strtotime($start);
    $hours = floor($diff / 3600);
    if ($hours < 24) return $hours . ' hours';
    return round($hours / 24, 1) . ' days';
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}
?>
