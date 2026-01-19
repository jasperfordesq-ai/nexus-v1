<?php
/**
 * Modern GDPR Request Detail View - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'GDPR Request #' . ($request['id'] ?? '0');
$adminPageSubtitle = 'Enterprise GDPR';
$adminPageIcon = 'fa-file-shield';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'requests';

// Extract data with defaults
$request = $request ?? [];
$user = $user ?? null;
$assignee = $assignee ?? null;
$admins = $admins ?? [];
$timeline = $timeline ?? [];
$notes = $notes ?? [];
$dataSummary = $dataSummary ?? [];
$deletionPreview = $deletionPreview ?? [];
$relatedRequests = $relatedRequests ?? [];

// Helper functions
function gdprGetStatusBadge($status) {
    return ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'rejected' => 'danger'][$status] ?? 'default';
}

function gdprGetTypeBadge($type) {
    return ['data_export' => 'primary', 'data_deletion' => 'danger', 'data_rectification' => 'warning', 'data_portability' => 'success', 'data_access' => 'info'][$type] ?? 'default';
}

function gdprGetTypeIcon($type) {
    return ['data_export' => 'download', 'data_deletion' => 'trash', 'data_rectification' => 'edit', 'data_portability' => 'exchange-alt', 'data_access' => 'eye'][$type] ?? 'file';
}

function gdprFormatType($type) {
    return ucwords(str_replace(['data_', '_'], ['', ' '], $type));
}

function gdprGetProcessingTime($start, $end) {
    $diff = strtotime($end) - strtotime($start);
    $hours = floor($diff / 3600);
    if ($hours < 24) return $hours . ' hours';
    return round($hours / 24, 1) . ' days';
}

function gdprFormatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function gdprTimeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

// SLA Calculation
$deadline = isset($request['created_at']) ? strtotime($request['created_at']) + (30 * 86400) : 0;
$remaining = $deadline - time();
$slaClass = 'success';
$slaText = ceil($remaining / 86400) . ' days left';
if ($remaining < 0) {
    $slaClass = 'danger';
    $slaText = 'OVERDUE';
} elseif ($remaining < 5 * 86400) {
    $slaClass = 'warning';
    $slaText = ceil($remaining / 86400) . ' days left';
}
?>

<!-- Page Header -->
<div class="gdpr-page-header">
    <div class="gdpr-page-header-content">
        <h1 class="gdpr-page-title">
            <a href="<?= $basePath ?>/admin/enterprise/gdpr/requests" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Request #<?= htmlspecialchars($request['id'] ?? '0') ?>
            <span class="status-badge <?= gdprGetStatusBadge($request['status'] ?? 'pending') ?>">
                <?= ucfirst(str_replace('_', ' ', $request['status'] ?? 'pending')) ?>
            </span>
        </h1>
        <p class="gdpr-page-subtitle">
            <span class="type-badge <?= gdprGetTypeBadge($request['request_type'] ?? '') ?>">
                <i class="fa-solid fa-<?= gdprGetTypeIcon($request['request_type'] ?? '') ?>"></i>
                <?= gdprFormatType($request['request_type'] ?? 'unknown') ?>
            </span>
            <span class="separator">&middot;</span>
            Submitted <?= isset($request['created_at']) ? date('F j, Y \a\t g:i A', strtotime($request['created_at'])) : 'N/A' ?>
        </p>
    </div>
    <div class="gdpr-page-actions">
        <?php if (($request['status'] ?? '') === 'pending'): ?>
            <button type="button" class="gdpr-btn gdpr-btn-success" onclick="processRequest()">
                <i class="fa-solid fa-play"></i> Start Processing
            </button>
        <?php elseif (($request['status'] ?? '') === 'in_progress'): ?>
            <button type="button" class="gdpr-btn gdpr-btn-primary" onclick="completeRequest()">
                <i class="fa-solid fa-check"></i> Mark Complete
            </button>
        <?php endif; ?>
        <div class="gdpr-dropdown">
            <button type="button" class="gdpr-btn gdpr-btn-secondary gdpr-dropdown-trigger">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div class="gdpr-dropdown-menu">
                <button type="button" onclick="openAssignModal()">
                    <i class="fa-solid fa-user-plus"></i> Assign
                </button>
                <button type="button" onclick="openNoteModal()">
                    <i class="fa-solid fa-sticky-note"></i> Add Note
                </button>
                <?php if (!in_array($request['status'] ?? '', ['completed', 'rejected'])): ?>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="danger" onclick="rejectRequest()">
                        <i class="fa-solid fa-times"></i> Reject Request
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Main Content Grid -->
<div class="gdpr-content-grid">
    <!-- Main Column -->
    <div class="gdpr-main-column">
        <!-- Request Details Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    <i class="fa-solid fa-info-circle"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Request Details</h3>
                    <p class="admin-card-subtitle">Complete information about this request</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="details-grid">
                    <div class="details-column">
                        <div class="detail-item">
                            <label>Request Type</label>
                            <span class="type-badge large <?= gdprGetTypeBadge($request['request_type'] ?? '') ?>">
                                <i class="fa-solid fa-<?= gdprGetTypeIcon($request['request_type'] ?? '') ?>"></i>
                                <?= gdprFormatType($request['request_type'] ?? 'unknown') ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <label>Requester Email</label>
                            <span class="detail-value"><?= htmlspecialchars($request['email'] ?? 'N/A') ?></span>
                        </div>

                        <?php if (!empty($request['user_id'])): ?>
                        <div class="detail-item">
                            <label>Linked User Account</label>
                            <a href="<?= $basePath ?>/admin/users/<?= $request['user_id'] ?>" class="detail-link">
                                User #<?= $request['user_id'] ?>
                                <?php if (!empty($user)): ?>
                                    (<?= htmlspecialchars($user['username'] ?? '') ?>)
                                <?php endif; ?>
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="detail-item">
                            <label>Verification Status</label>
                            <?php if (!empty($request['verified_at'])): ?>
                                <span class="verification-status verified">
                                    <i class="fa-solid fa-check-circle"></i>
                                    Verified on <?= date('M j, Y', strtotime($request['verified_at'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="verification-status pending">
                                    <i class="fa-solid fa-exclamation-circle"></i>
                                    Pending Verification
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="details-column">
                        <div class="detail-item">
                            <label>Submitted</label>
                            <span class="detail-value"><?= isset($request['created_at']) ? date('F j, Y \a\t g:i A', strtotime($request['created_at'])) : 'N/A' ?></span>
                        </div>

                        <div class="detail-item">
                            <label>SLA Deadline</label>
                            <span class="detail-value">
                                <?= date('F j, Y', $deadline) ?>
                                <?php if (!in_array($request['status'] ?? '', ['completed', 'rejected'])): ?>
                                    <span class="sla-badge <?= $slaClass ?>"><?= $slaText ?></span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if (!empty($request['completed_at'])): ?>
                        <div class="detail-item">
                            <label>Completed</label>
                            <span class="detail-value">
                                <?= date('F j, Y \a\t g:i A', strtotime($request['completed_at'])) ?>
                                <span class="processing-time">
                                    Processing time: <?= gdprGetProcessingTime($request['created_at'], $request['completed_at']) ?>
                                </span>
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="detail-item">
                            <label>Assigned To</label>
                            <?php if (!empty($request['assigned_to'])): ?>
                                <span class="detail-value">
                                    <?= htmlspecialchars($assignee['username'] ?? 'Admin #' . $request['assigned_to']) ?>
                                    <button type="button" class="edit-btn" onclick="openAssignModal()">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </span>
                            <?php else: ?>
                                <span class="detail-value unassigned">
                                    Unassigned
                                    <button type="button" class="assign-btn" onclick="openAssignModal()">Assign</button>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($request['details'])): ?>
                <div class="additional-details">
                    <label>Additional Details</label>
                    <div class="details-content">
                        <?= nl2br(htmlspecialchars($request['details'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Data Summary Card (for export/portability) -->
        <?php if (in_array($request['request_type'] ?? '', ['data_export', 'data_portability', 'data_access']) && !empty($request['user_id'])): ?>
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">User Data Summary</h3>
                    <p class="admin-card-subtitle">Data categories available for export</p>
                </div>
                <?php if (($request['status'] ?? '') !== 'completed'): ?>
                <button type="button" class="gdpr-btn gdpr-btn-sm gdpr-btn-primary" onclick="generateExport()">
                    <i class="fa-solid fa-file-export"></i> Generate Export
                </button>
                <?php endif; ?>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Data Category</th>
                                <th>Records</th>
                                <th>Size</th>
                                <th>Include</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dataSummary as $category => $info): ?>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-<?= $info['icon'] ?? 'file' ?> category-icon"></i>
                                    <?= htmlspecialchars($info['label'] ?? $category) ?>
                                </td>
                                <td><?= number_format($info['count'] ?? 0) ?></td>
                                <td><?= gdprFormatSize($info['size'] ?? 0) ?></td>
                                <td>
                                    <label class="toggle-switch small">
                                        <input type="checkbox" class="export-toggle" data-category="<?= $category ?>" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($dataSummary)): ?>
                            <tr>
                                <td colspan="4" class="empty-row">No data categories available</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Deletion Preview Card (for deletion requests) -->
        <?php if (($request['request_type'] ?? '') === 'data_deletion' && !empty($request['user_id'])): ?>
        <div class="admin-glass-card danger-card">
            <div class="admin-card-header danger-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Data to be Deleted</h3>
                    <p class="admin-card-subtitle">Review before processing deletion</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="warning-banner">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Warning:</strong> This action is irreversible. All data below will be permanently deleted.
                </div>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Data Category</th>
                                <th>Records to Delete</th>
                                <th>Retention Exception</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deletionPreview as $category => $info): ?>
                            <tr>
                                <td><?= htmlspecialchars($info['label'] ?? $category) ?></td>
                                <td><?= number_format($info['count'] ?? 0) ?></td>
                                <td>
                                    <?php if (!empty($info['retained'])): ?>
                                        <span class="retention-warning" title="<?= htmlspecialchars($info['retention_reason'] ?? '') ?>">
                                            <i class="fa-solid fa-lock"></i> <?= $info['retained_count'] ?? 0 ?> retained
                                        </span>
                                    <?php else: ?>
                                        <span class="no-retention">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Timeline Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Activity Timeline</h3>
                    <p class="admin-card-subtitle">History of all actions on this request</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="timeline">
                    <?php if (!empty($timeline)): ?>
                        <?php foreach ($timeline as $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon <?= $event['color'] ?? 'default' ?>">
                                <i class="fa-solid fa-<?= $event['icon'] ?? 'circle' ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <strong><?= htmlspecialchars($event['action'] ?? '') ?></strong>
                                    <span class="timeline-time"><?= gdprTimeAgo($event['created_at'] ?? date('Y-m-d')) ?></span>
                                </div>
                                <?php if (!empty($event['details'])): ?>
                                    <p class="timeline-details"><?= htmlspecialchars($event['details']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($event['user'])): ?>
                                    <span class="timeline-user">by <?= htmlspecialchars($event['user']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="timeline-item">
                            <div class="timeline-icon primary">
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <strong>Request Created</strong>
                                    <span class="timeline-time"><?= gdprTimeAgo($request['created_at'] ?? date('Y-m-d')) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Column -->
    <div class="gdpr-sidebar-column">
        <!-- Quick Actions Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Quick Actions</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="quick-actions-list">
                    <?php if (($request['status'] ?? '') === 'pending'): ?>
                        <button type="button" class="quick-action-btn success" onclick="processRequest()">
                            <i class="fa-solid fa-play"></i> Start Processing
                        </button>
                    <?php endif; ?>

                    <?php if (($request['status'] ?? '') === 'in_progress'): ?>
                        <button type="button" class="quick-action-btn primary" onclick="completeRequest()">
                            <i class="fa-solid fa-check"></i> Mark Complete
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($request['request_type'] ?? '', ['data_export', 'data_portability']) && !empty($request['user_id'])): ?>
                        <button type="button" class="quick-action-btn outline" onclick="generateExport()">
                            <i class="fa-solid fa-file-export"></i> Generate Export
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($request['download_url'])): ?>
                        <a href="<?= htmlspecialchars($request['download_url']) ?>" class="quick-action-btn outline-success" download>
                            <i class="fa-solid fa-download"></i> Download Export
                        </a>
                    <?php endif; ?>

                    <button type="button" class="quick-action-btn outline" onclick="openNoteModal()">
                        <i class="fa-solid fa-sticky-note"></i> Add Note
                    </button>

                    <?php if (!in_array($request['status'] ?? '', ['completed', 'rejected'])): ?>
                        <div class="action-divider"></div>
                        <button type="button" class="quick-action-btn outline-danger" onclick="rejectRequest()">
                            <i class="fa-solid fa-times"></i> Reject Request
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notes Card -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                    <i class="fa-solid fa-sticky-note"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Notes</h3>
                </div>
                <button type="button" class="gdpr-btn gdpr-btn-sm gdpr-btn-secondary" onclick="openNoteModal()">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($notes)): ?>
                    <div class="notes-list">
                        <?php foreach ($notes as $note): ?>
                        <div class="note-item">
                            <div class="note-content"><?= nl2br(htmlspecialchars($note['content'] ?? '')) ?></div>
                            <div class="note-meta">
                                <?= htmlspecialchars($note['author'] ?? 'Unknown') ?> &middot; <?= gdprTimeAgo($note['created_at'] ?? date('Y-m-d')) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-notes">
                        <i class="fa-solid fa-sticky-note"></i>
                        <p>No notes yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Requests Card -->
        <?php if (!empty($relatedRequests)): ?>
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                    <i class="fa-solid fa-link"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Related Requests</h3>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="related-list">
                    <?php foreach ($relatedRequests as $related): ?>
                    <a href="<?= $basePath ?>/admin/enterprise/gdpr/requests/<?= $related['id'] ?>" class="related-item">
                        <div class="related-info">
                            <span class="related-id">#<?= $related['id'] ?></span>
                            <span class="related-type"><?= gdprFormatType($related['request_type'] ?? '') ?></span>
                        </div>
                        <span class="status-badge small <?= gdprGetStatusBadge($related['status'] ?? '') ?>">
                            <?= ucfirst($related['status'] ?? '') ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="assignModal">
    <div class="modal" role="dialog" aria-modal="true"-container modal-sm">
        <div class="modal" role="dialog" aria-modal="true"-header">
            <h3 class="modal" role="dialog" aria-modal="true"-title">
                <i class="fa-solid fa-user-plus"></i> Assign Request
            </h3>
            <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeAssignModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="<?= $basePath ?>/admin/enterprise/gdpr/requests/<?= $request['id'] ?? 0 ?>/assign" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <div class="modal" role="dialog" aria-modal="true"-body">
                <div class="form-group">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= ($request['assigned_to'] ?? '') == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['username'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal" role="dialog" aria-modal="true"-footer">
                <button type="button" class="gdpr-btn gdpr-btn-secondary" onclick="closeAssignModal()">Cancel</button>
                <button type="submit" class="gdpr-btn gdpr-btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- Note Modal -->
<div class="modal" role="dialog" aria-modal="true"-overlay" id="noteModal">
    <div class="modal" role="dialog" aria-modal="true"-container modal-sm">
        <div class="modal" role="dialog" aria-modal="true"-header">
            <h3 class="modal" role="dialog" aria-modal="true"-title">
                <i class="fa-solid fa-sticky-note"></i> Add Note
            </h3>
            <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeNoteModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="<?= $basePath ?>/admin/enterprise/gdpr/requests/<?= $request['id'] ?? 0 ?>/notes" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <div class="modal" role="dialog" aria-modal="true"-body">
                <div class="form-group">
                    <label class="form-label">Note</label>
                    <textarea name="content" class="form-control" rows="4" required placeholder="Enter your note..."></textarea>
                </div>
            </div>
            <div class="modal" role="dialog" aria-modal="true"-footer">
                <button type="button" class="gdpr-btn gdpr-btn-secondary" onclick="closeNoteModal()">Cancel</button>
                <button type="submit" class="gdpr-btn gdpr-btn-primary">Add Note</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Page Header */
.gdpr-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.gdpr-page-header-content {
    flex: 1;
}

.gdpr-page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.gdpr-page-subtitle {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.gdpr-page-subtitle .separator {
    color: rgba(255, 255, 255, 0.3);
}

.back-link {
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    transition: color 0.2s;
}

.back-link:hover {
    color: #fff;
}

.gdpr-page-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Buttons */
.gdpr-btn {
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

.gdpr-btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

.gdpr-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.gdpr-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.gdpr-btn-success {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
}

.gdpr-btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
}

.gdpr-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.gdpr-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}

/* Dropdown */
.gdpr-dropdown {
    position: relative;
}

.gdpr-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 180px;
    background: #1e293b;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    padding: 0.5rem;
    z-index: 100;
    display: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    margin-top: 0.5rem;
}

.gdpr-dropdown:hover .gdpr-dropdown-menu,
.gdpr-dropdown:focus-within .gdpr-dropdown-menu {
    display: block;
}

.gdpr-dropdown-menu button {
    width: 100%;
    padding: 0.6rem 0.75rem;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    text-align: left;
    cursor: pointer;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    transition: all 0.15s;
    font-family: inherit;
}

.gdpr-dropdown-menu button:hover {
    background: rgba(99, 102, 241, 0.15);
    color: #fff;
}

.gdpr-dropdown-menu button.danger:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.dropdown-divider {
    height: 1px;
    background: rgba(99, 102, 241, 0.2);
    margin: 0.5rem 0;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.status-badge.small {
    padding: 0.25rem 0.5rem;
    font-size: 0.65rem;
}

.status-badge.pending, .status-badge.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
}

.status-badge.in_progress, .status-badge.info {
    background: rgba(6, 182, 212, 0.15);
    color: #67e8f9;
}

.status-badge.completed, .status-badge.success {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
}

.status-badge.rejected, .status-badge.danger {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.status-badge.default {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

/* Type Badges */
.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.7rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.type-badge.large {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.type-badge.primary { background: rgba(99, 102, 241, 0.15); color: #a5b4fc; }
.type-badge.success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
.type-badge.warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
.type-badge.danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
.type-badge.info { background: rgba(6, 182, 212, 0.15); color: #67e8f9; }

/* Content Grid */
.gdpr-content-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
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

.admin-card-body {
    padding: 1.25rem;
}

/* Details Grid */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.detail-item {
    margin-bottom: 1.25rem;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-item label {
    display: block;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.detail-value {
    color: #fff;
    font-size: 0.9rem;
}

.detail-link {
    color: #a5b4fc;
    text-decoration: none;
}

.detail-link:hover {
    color: #c7d2fe;
    text-decoration: underline;
}

.verification-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.9rem;
}

.verification-status.verified {
    color: #6ee7b7;
}

.verification-status.pending {
    color: #fcd34d;
}

.sla-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    margin-left: 0.5rem;
    text-transform: uppercase;
}

.sla-badge.success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
.sla-badge.warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
.sla-badge.danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }

.processing-time {
    display: block;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.25rem;
}

.edit-btn {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    padding: 0.25rem;
    margin-left: 0.5rem;
    transition: color 0.2s;
}

.edit-btn:hover {
    color: #a5b4fc;
}

.assign-btn {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    margin-left: 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    transition: all 0.2s;
}

.assign-btn:hover {
    background: rgba(99, 102, 241, 0.25);
}

.detail-value.unassigned {
    color: rgba(255, 255, 255, 0.5);
}

/* Additional Details */
.additional-details {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

.additional-details label {
    display: block;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.details-content {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 8px;
    padding: 1rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Table Styles */
.admin-table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
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
    background: rgba(99, 102, 241, 0.05);
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

.category-icon {
    color: rgba(255, 255, 255, 0.4);
    margin-right: 0.5rem;
}

.empty-row {
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
    padding: 2rem !important;
}

/* Toggle Switch */
.toggle-switch {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.toggle-switch input {
    display: none;
}

.toggle-slider {
    width: 36px;
    height: 20px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    position: relative;
    transition: all 0.2s;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.2s;
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.toggle-switch input:checked + .toggle-slider::after {
    left: 18px;
}

.toggle-switch.small .toggle-slider {
    width: 32px;
    height: 18px;
}

.toggle-switch.small .toggle-slider::after {
    width: 14px;
    height: 14px;
}

.toggle-switch.small input:checked + .toggle-slider::after {
    left: 16px;
}

/* Danger Card */
.danger-card {
    border-color: rgba(239, 68, 68, 0.3);
}

.danger-header {
    background: rgba(239, 68, 68, 0.05);
}

.warning-banner {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    color: #fcd34d;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.warning-banner i {
    font-size: 1.25rem;
}

.retention-warning {
    color: #fcd34d;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.no-retention {
    color: rgba(255, 255, 255, 0.4);
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(99, 102, 241, 0.2);
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -40px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.65rem;
    background: #64748b;
}

.timeline-icon.primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.timeline-icon.success { background: linear-gradient(135deg, #10b981, #34d399); }
.timeline-icon.warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.timeline-icon.danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
.timeline-icon.info { background: linear-gradient(135deg, #06b6d4, #22d3ee); }

.timeline-content {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 8px;
    padding: 0.875rem 1rem;
}

.timeline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    color: #fff;
    font-size: 0.9rem;
}

.timeline-time {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

.timeline-details {
    margin: 0.5rem 0 0 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.timeline-user {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

/* Quick Actions */
.quick-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    font-family: inherit;
}

.quick-action-btn.primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.quick-action-btn.success {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

.quick-action-btn.outline {
    background: transparent;
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: rgba(255, 255, 255, 0.8);
}

.quick-action-btn.outline:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.5);
}

.quick-action-btn.outline-success {
    background: transparent;
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}

.quick-action-btn.outline-success:hover {
    background: rgba(16, 185, 129, 0.1);
}

.quick-action-btn.outline-danger {
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.quick-action-btn.outline-danger:hover {
    background: rgba(239, 68, 68, 0.1);
}

.action-divider {
    height: 1px;
    background: rgba(99, 102, 241, 0.15);
    margin: 0.5rem 0;
}

/* Notes */
.notes-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.note-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.note-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.note-content {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 0.5rem;
}

.note-meta {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

.empty-notes {
    text-align: center;
    padding: 1.5rem;
    color: rgba(255, 255, 255, 0.4);
}

.empty-notes i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: block;
    opacity: 0.3;
}

.empty-notes p {
    margin: 0;
}

/* Related Requests */
.related-list {
    display: flex;
    flex-direction: column;
}

.related-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    text-decoration: none;
    transition: background 0.2s;
}

.related-item:last-child {
    border-bottom: none;
}

.related-item:hover {
    background: rgba(99, 102, 241, 0.08);
}

.related-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.related-id {
    color: #fff;
    font-weight: 600;
    font-size: 0.9rem;
}

.related-type {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
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
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.modal-container.modal-sm {
    max-width: 400px;
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
.form-group {
    margin-bottom: 1.25rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
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
    font-family: inherit;
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
    min-height: 100px;
}

select.form-control option {
    background: #1e293b;
}

/* Responsive */
@media (max-width: 1024px) {
    .gdpr-content-grid {
        grid-template-columns: 1fr;
    }

    .details-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .gdpr-page-header {
        flex-direction: column;
        gap: 1rem;
    }

    .gdpr-page-title {
        font-size: 1.35rem;
    }

    .gdpr-page-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';
const requestId = <?= $request['id'] ?? 0 ?>;
const csrfToken = '<?= Csrf::generate() ?>';

// Process request
function processRequest() {
    if (confirm('Start processing this request?')) {
        fetch(basePath + '/admin/enterprise/gdpr/requests/' + requestId + '/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Failed to process request');
        })
        .catch(err => alert('Error: ' + err.message));
    }
}

// Complete request
function completeRequest() {
    if (confirm('Mark this request as completed?')) {
        fetch(basePath + '/admin/enterprise/gdpr/requests/' + requestId + '/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Failed to complete request');
        })
        .catch(err => alert('Error: ' + err.message));
    }
}

// Reject request
function rejectRequest() {
    const reason = prompt('Enter rejection reason:');
    if (reason) {
        fetch(basePath + '/admin/enterprise/gdpr/requests/' + requestId + '/reject', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({reason: reason})
        })
        .then(() => location.reload())
        .catch(err => alert('Error: ' + err.message));
    }
}

// Generate export
function generateExport() {
    const btn = event.target;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';

    // Collect selected categories
    const categories = [];
    document.querySelectorAll('.export-toggle:checked').forEach(toggle => {
        categories.push(toggle.dataset.category);
    });

    fetch(basePath + '/admin/enterprise/gdpr/requests/' + requestId + '/generate-export', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({categories: categories})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to generate export');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

// Modal functions
function openAssignModal() {
    document.getElementById('assignModal').classList.add('show');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.remove('show');
}

function openNoteModal() {
    document.getElementById('noteModal').classList.add('show');
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('show');
}

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAssignModal();
        closeNoteModal();
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
