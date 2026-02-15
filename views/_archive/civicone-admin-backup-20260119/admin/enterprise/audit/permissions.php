<?php
/**
 * Permission Audit Log Viewer
 * Gold Standard v2.0 Admin Interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\Enterprise\PermissionService;

$adminPageTitle = 'Permission Audit Log';
$adminPageSubtitle = 'Compliance & Security';
$adminPageIcon = 'fa-clipboard-list';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$permService = new PermissionService();
$db = Database::getInstance();
$currentUserId = $_SESSION['user_id'] ?? 0;

// Check permission
if (!$permService->can($currentUserId, 'system.audit_logs')) {
    echo '<div class="alert alert-danger">You do not have permission to view audit logs.</div>';
    require dirname(__DIR__, 2) . '/partials/admin-footer.php';
    exit;
}

// Get filters from query parameters
$filterUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$filterPermission = $_GET['permission'] ?? null;
$filterEventType = $_GET['event_type'] ?? null;
$filterFromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$filterToDate = $_GET['to_date'] ?? date('Y-m-d');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$sql = "
    SELECT
        pal.*,
        u.username,
        u.email,
        u.avatar_url,
        ab.username as actor_username,
        r.display_name as role_name,
        p.display_name as permission_display_name
    FROM permission_audit_log pal
    LEFT JOIN users u ON pal.user_id = u.id
    LEFT JOIN users ab ON pal.actor_id = ab.id
    LEFT JOIN roles r ON pal.role_id = r.id
    LEFT JOIN permissions p ON pal.permission_id = p.id
    WHERE 1=1
";

$params = [];

if ($filterUserId) {
    $sql .= " AND pal.user_id = ?";
    $params[] = $filterUserId;
}

if ($filterPermission) {
    $sql .= " AND pal.permission_name LIKE ?";
    $params[] = '%' . $filterPermission . '%';
}

if ($filterEventType) {
    $sql .= " AND pal.event_type = ?";
    $params[] = $filterEventType;
}

if ($filterFromDate) {
    $sql .= " AND DATE(pal.created_at) >= ?";
    $params[] = $filterFromDate;
}

if ($filterToDate) {
    $sql .= " AND DATE(pal.created_at) <= ?";
    $params[] = $filterToDate;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subquery";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Add pagination (use direct values for LIMIT/OFFSET as they can't be parameterized properly)
$sql .= " ORDER BY pal.created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_today' => $db->query("SELECT COUNT(*) as count FROM permission_audit_log WHERE DATE(created_at) = CURDATE()")->fetch()['count'],
    'grants_today' => $db->query("SELECT COUNT(*) as count FROM permission_audit_log WHERE DATE(created_at) = CURDATE() AND event_type = 'role_assigned'")->fetch()['count'],
    'checks_today' => $db->query("SELECT COUNT(*) as count FROM permission_audit_log WHERE DATE(created_at) = CURDATE() AND event_type = 'permission_check'")->fetch()['count'],
    'denials_today' => $db->query("SELECT COUNT(*) as count FROM permission_audit_log WHERE DATE(created_at) = CURDATE() AND event_type = 'permission_check' AND result = 'denied'")->fetch()['count']
];

// Get unique event types for filter
$eventTypes = $db->query("SELECT DISTINCT event_type FROM permission_audit_log ORDER BY event_type")->fetchAll();

// Get recent users for filter
$recentUsers = $db->query("
    SELECT DISTINCT u.id, u.username
    FROM users u
    JOIN permission_audit_log pal ON u.id = pal.user_id
    WHERE DATE(pal.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY u.username
    LIMIT 50
")->fetchAll();
?>

<style>
/* Audit Log - Gold Standard v2.0 */
.audit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.audit-filters {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
}

.filter-input, .filter-select {
    padding: 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    font-size: 0.875rem;
    background: rgba(30, 41, 59, 0.7);
    color: #f1f5f9;
    transition: all 0.3s;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.audit-table-container {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    overflow: hidden;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
}

.audit-table thead {
    background: rgba(30, 41, 59, 0.5);
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
}

.audit-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.audit-table td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    font-size: 0.875rem;
    color: #f1f5f9;
}

.audit-table tbody tr {
    transition: background 0.2s;
}

.audit-table tbody tr:hover {
    background: rgba(30, 41, 59, 0.4);
}

.audit-table tbody tr:last-child td {
    border-bottom: none;
}

.event-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.event-role-assigned {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.event-role-revoked {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.event-permission-granted {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.event-permission-revoked {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.event-permission-check,
.event-permission-checked,
.event-access-denied {
    background: linear-gradient(135deg, rgba(100, 116, 139, 0.15), rgba(71, 85, 105, 0.15));
    border: 1px solid rgba(100, 116, 139, 0.3);
    color: #94a3b8;
}

.result-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.result-allowed,
.result-granted {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.result-denied {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.user-avatar-placeholder {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--admin-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    color: var(--admin-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-email {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permission-name {
    font-family: 'Courier New', monospace;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--admin-text);
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1.25rem 1.5rem;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
}

.pagination-info {
    font-size: 0.875rem;
    color: #94a3b8;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    gap: 0.5rem;
}

.pagination-btn {
    padding: 0.625rem 1rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    background: rgba(30, 41, 59, 0.7);
    color: #f1f5f9;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.pagination-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pagination-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--admin-text-muted);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.ip-address {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-clipboard-list"></i>
            Permission Audit Log
        </h1>
        <p class="admin-page-subtitle">Complete activity trail for compliance and security</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/roles" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-user-tag"></i> Manage Roles
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="exportAuditLog()">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="audit-stats">
    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-purple">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_today']) ?></div>
            <div class="admin-stat-label">Total Events Today</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-green">
            <i class="fas fa-user-tag"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['grants_today']) ?></div>
            <div class="admin-stat-label">Role Assignments</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-blue">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['checks_today']) ?></div>
            <div class="admin-stat-label">Permission Checks</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-red">
            <i class="fas fa-ban"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['denials_today']) ?></div>
            <div class="admin-stat-label">Access Denials</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="audit-filters">
    <form method="GET" action="">
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">User</label>
                <select name="user_id" class="filter-select">
                    <option value="">All Users</option>
                    <?php foreach ($recentUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filterUserId === $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Permission</label>
                <input
                    type="text"
                    name="permission"
                    class="filter-input"
                    placeholder="e.g. users.delete"
                    value="<?= htmlspecialchars($filterPermission ?? '') ?>"
                >
            </div>

            <div class="filter-group">
                <label class="filter-label">Event Type</label>
                <select name="event_type" class="filter-select">
                    <option value="">All Events</option>
                    <?php foreach ($eventTypes as $et): ?>
                        <option value="<?= htmlspecialchars($et['event_type']) ?>" <?= $filterEventType === $et['event_type'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($et['event_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">From Date</label>
                <input
                    type="date"
                    name="from_date"
                    class="filter-input"
                    value="<?= htmlspecialchars($filterFromDate) ?>"
                >
            </div>

            <div class="filter-group">
                <label class="filter-label">To Date</label>
                <input
                    type="date"
                    name="to_date"
                    class="filter-input"
                    value="<?= htmlspecialchars($filterToDate) ?>"
                >
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="<?= TenantContext::getBasePath() ?>/admin-legacy/enterprise/audit/permissions" class="admin-btn admin-btn-secondary">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Audit Log Table -->
<div class="audit-table-container">
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-clipboard-list"></i></div>
            <p>No audit log entries found for the selected filters</p>
        </div>
    <?php else: ?>
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Event</th>
                    <th>Permission/Role</th>
                    <th>Result</th>
                    <th>Assigned By</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <div><?= date('M j, Y', strtotime($log['created_at'])) ?></div>
                            <div style="font-size: 0.75rem; color: var(--admin-text-muted);">
                                <?= date('g:i A', strtotime($log['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div class="user-cell">
                                <?php if (!empty($log['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($log['avatar_url']) ?>" loading="lazy" alt="Avatar" class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <?= strtoupper(substr($log['username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></div>
                                    <div class="user-email"><?= htmlspecialchars($log['email'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="event-badge event-<?= str_replace('_', '-', $log['event_type']) ?>">
                                <?= htmlspecialchars($log['event_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['permission_name']): ?>
                                <div class="permission-name"><?= htmlspecialchars($log['permission_name']) ?></div>
                            <?php elseif ($log['role_name']): ?>
                                <div style="font-weight: 600;"><?= htmlspecialchars($log['role_name']) ?></div>
                            <?php else: ?>
                                <span style="color: var(--admin-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['result']): ?>
                                <span class="result-badge result-<?= $log['result'] ?>">
                                    <?= htmlspecialchars($log['result']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--admin-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['actor_username']): ?>
                                <div style="font-weight: 600; font-size: 0.875rem;">
                                    <?= htmlspecialchars($log['actor_username']) ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--admin-text-muted);">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['ip_address']): ?>
                                <span class="ip-address"><?= htmlspecialchars($log['ip_address']) ?></span>
                            <?php else: ?>
                                <span style="color: var(--admin-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <div class="pagination-info">
        Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalRecords)) ?> of <?= number_format($totalRecords) ?> entries
    </div>
    <div class="pagination-controls">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="pagination-btn">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn">
                <i class="fas fa-angle-left"></i> Prev
            </a>
        <?php endif; ?>

        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        for ($i = $startPage; $i <= $endPage; $i++):
        ?>
            <a
                href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                class="pagination-btn <?= $i === $page ? 'active' : '' ?>"
            >
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn">
                Next <i class="fas fa-angle-right"></i>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="pagination-btn">
                <i class="fas fa-angle-double-right"></i>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function exportAuditLog() {
    // Build query string with current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');

    // TODO: Implement CSV export endpoint
    alert('CSV export coming soon! This will download audit logs with current filters.\n\nAPI endpoint: GET /admin-legacy/api/audit/permissions?' + params.toString());
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
