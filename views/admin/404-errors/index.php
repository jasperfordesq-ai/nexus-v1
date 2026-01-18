<?php
/**
 * Admin 404 Error Tracking Dashboard
 */

$pageTitle = '404 Error Tracking';
require_once __DIR__ . '/../../layouts/admin-header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">404 Error Tracking</h1>
            <p class="text-muted">Monitor and fix broken links across your site</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2">Total 404s</h6>
                    <h2 class="mb-0"><?= number_format($stats['total']) ?></h2>
                    <small>Unique URLs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2">Unresolved</h6>
                    <h2 class="mb-0"><?= number_format($stats['unresolved']) ?></h2>
                    <small>Need attention</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2">Resolved</h6>
                    <h2 class="mb-0"><?= number_format($stats['resolved']) ?></h2>
                    <small>Fixed issues</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2">Total Hits</h6>
                    <h2 class="mb-0"><?= number_format($stats['total_hits']) ?></h2>
                    <small><?= $stats['recent_24h'] ?> in last 24h</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/404-errors" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="resolved" class="form-select">
                        <option value="">All</option>
                        <option value="0" <?= $filters['resolved'] === false ? 'selected' : '' ?>>Unresolved</option>
                        <option value="1" <?= $filters['resolved'] === true ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="order_by" class="form-select">
                        <option value="hit_count" <?= $filters['order_by'] === 'hit_count' ? 'selected' : '' ?>>Hit Count</option>
                        <option value="last_seen_at" <?= $filters['order_by'] === 'last_seen_at' ? 'selected' : '' ?>>Last Seen</option>
                        <option value="first_seen_at" <?= $filters['order_by'] === 'first_seen_at' ? 'selected' : '' ?>>First Seen</option>
                        <option value="url" <?= $filters['order_by'] === 'url' ? 'selected' : '' ?>>URL</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Order</label>
                    <select name="order_dir" class="form-select">
                        <option value="DESC" <?= $filters['order_dir'] === 'DESC' ? 'selected' : '' ?>>Descending</option>
                        <option value="ASC" <?= $filters['order_dir'] === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select name="per_page" class="form-select">
                        <option value="25">25</option>
                        <option value="50" <?= $pagination['per_page'] === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Actions Toolbar -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-sm btn-danger" onclick="cleanOldResolved()">
                    <i class="bi bi-trash"></i> Clean Old Resolved (90+ days)
                </button>
            </div>
            <div>
                <a href="/admin/seo/redirects" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left-right"></i> Manage Redirects
                </a>
            </div>
        </div>
    </div>

    <!-- 404 Errors Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($errors)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No 404 errors found matching your filters.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Hits</th>
                                <th>First Seen</th>
                                <th>Last Seen</th>
                                <th>Referer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($errors as $error): ?>
                                <tr id="error-<?= $error['id'] ?>" class="<?= $error['resolved'] ? 'table-success' : '' ?>">
                                    <td>
                                        <code class="text-break"><?= htmlspecialchars($error['url']) ?></code>
                                        <?php if ($error['notes']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($error['notes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $error['hit_count'] > 10 ? 'danger' : ($error['hit_count'] > 5 ? 'warning' : 'secondary') ?>">
                                            <?= number_format($error['hit_count']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('Y-m-d H:i', strtotime($error['first_seen_at'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('Y-m-d H:i', strtotime($error['last_seen_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($error['referer']): ?>
                                            <small class="text-break">
                                                <a href="<?= htmlspecialchars($error['referer']) ?>" target="_blank" rel="noopener">
                                                    <?= htmlspecialchars(substr($error['referer'], 0, 50)) ?>...
                                                </a>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($error['resolved']): ?>
                                            <span class="badge bg-success">Resolved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unresolved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$error['resolved']): ?>
                                                <button class="btn btn-sm btn-primary" onclick="createRedirectModal(<?= $error['id'] ?>, '<?= htmlspecialchars($error['url'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-arrow-right"></i> Redirect
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="markResolved(<?= $error['id'] ?>)">
                                                    <i class="bi bi-check"></i> Resolve
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-warning" onclick="markUnresolved(<?= $error['id'] ?>)">
                                                    <i class="bi bi-x"></i> Unresolve
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteError(<?= $error['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&order_by=<?= $filters['order_by'] ?>&order_dir=<?= $filters['order_dir'] ?>&resolved=<?= $filters['resolved'] !== null ? ($filters['resolved'] ? '1' : '0') : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Redirect Modal -->
<div class="modal fade" id="redirectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Redirect</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="redirectForm">
                    <input type="hidden" id="errorId" name="error_id">
                    <div class="mb-3">
                        <label class="form-label">Source URL (404)</label>
                        <input type="text" class="form-control" id="sourceUrl" name="source_url" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Destination URL</label>
                        <input type="text" class="form-control" id="destinationUrl" name="destination_url" placeholder="/new-page" required>
                        <small class="form-text text-muted">Enter the URL to redirect to</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRedirect()">Create Redirect</button>
            </div>
        </div>
    </div>
</div>

<script>
function createRedirectModal(errorId, sourceUrl) {
    document.getElementById('errorId').value = errorId;
    document.getElementById('sourceUrl').value = sourceUrl;
    document.getElementById('destinationUrl').value = '';
    new bootstrap.Modal(document.getElementById('redirectModal')).show();
}

function submitRedirect() {
    const formData = new FormData(document.getElementById('redirectForm'));

    fetch('/admin/404-errors/create-redirect', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Redirect created successfully!');
            location.reload();
        } else {
            alert('Failed to create redirect: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

function markResolved(id) {
    if (!confirm('Mark this error as resolved?')) return;

    fetch('/admin/404-errors/mark-resolved', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed: ' + data.message);
        }
    });
}

function markUnresolved(id) {
    if (!confirm('Mark this error as unresolved?')) return;

    fetch('/admin/404-errors/mark-unresolved', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed: ' + data.message);
        }
    });
}

function deleteError(id) {
    if (!confirm('Delete this 404 error log entry?')) return;

    fetch('/admin/404-errors/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('error-' + id).remove();
        } else {
            alert('Failed: ' + data.message);
        }
    });
}

function cleanOldResolved() {
    if (!confirm('This will delete all resolved 404 errors older than 90 days. Continue?')) return;

    fetch('/admin/404-errors/clean-old', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'days=90'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Failed: ' + data.message);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
