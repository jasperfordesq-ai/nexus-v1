<?php
/**
 * Group Types Management - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\GroupType;

// Admin check
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ' . TenantContext::getBasePath() . '/');
    exit;
}

$tenantId = TenantContext::getId();
$basePath = TenantContext::getBasePath();

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token for all POST actions
    if (!\Nexus\Core\Csrf::verify()) {
        $message = "Invalid request. Please refresh and try again.";
        $messageType = 'error';
    } else {
        switch ($_POST['action']) {
            case 'toggle_active':
                if (isset($_POST['type_id'])) {
                    GroupType::toggleActive($_POST['type_id']);
                    $message = "Group type status updated";
                    $messageType = 'success';
                }
                break;

            case 'delete':
                if (isset($_POST['type_id'])) {
                    try {
                        GroupType::delete($_POST['type_id']);
                        $message = "Group type deleted successfully";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error deleting group type: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;

            case 'reorder':
                if (isset($_POST['order'])) {
                    $orderedIds = json_decode($_POST['order'], true);
                    GroupType::reorder($orderedIds);
                    $message = "Group types reordered successfully";
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Fetch all group types with stats
$groupTypes = GroupType::all();
$stats = GroupType::getOverviewStats();

// Admin header configuration
$adminPageTitle = 'Group Types';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-layer-group';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="page-hero-content">
        <div class="page-hero-icon">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="page-hero-text">
            <h1>Group Types</h1>
            <p>Organize groups into categories and types</p>
        </div>
    </div>
    <div class="page-hero-actions">
        <a href="<?= $basePath ?>/admin/group-types/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> Create Type
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="admin-alert admin-alert-<?= $messageType ?>">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
    </div>
    <div class="admin-alert-content">
        <?= htmlspecialchars($message) ?>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid-4">
    <div class="stat-card stat-blue">
        <div class="stat-card-icon">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $stats['total_types'] ?></div>
            <div class="stat-card-label">Total Types</div>
        </div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-card-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $stats['active_types'] ?></div>
            <div class="stat-card-label">Active</div>
        </div>
    </div>
    <div class="stat-card stat-purple">
        <div class="stat-card-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $stats['categorized_groups'] ?></div>
            <div class="stat-card-label">Categorized</div>
        </div>
    </div>
    <div class="stat-card stat-orange">
        <div class="stat-card-icon">
            <i class="fa-solid fa-question-circle"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= $stats['uncategorized_groups'] ?></div>
            <div class="stat-card-label">Uncategorized</div>
        </div>
    </div>
</div>

<!-- Group Types List -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">All Group Types</h3>
            <p class="admin-card-subtitle"><?= count($groupTypes) ?> type<?= count($groupTypes) !== 1 ? 's' : '' ?> defined - drag to reorder</p>
        </div>
    </div>

    <?php if (empty($groupTypes)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <h3 class="admin-empty-title">No Group Types Yet</h3>
            <p class="admin-empty-text">Create your first group type to start organizing groups into categories.</p>
            <a href="<?= $basePath ?>/admin/group-types/create" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-plus"></i> Create First Type
            </a>
        </div>
    <?php else: ?>
        <div class="types-list" id="sortable-types">
            <?php foreach ($groupTypes as $type): ?>
            <div class="type-row" data-type-id="<?= $type['id'] ?>">
                <div class="type-drag drag-handle">
                    <i class="fa-solid fa-grip-vertical"></i>
                </div>
                <div class="type-icon-badge" style="background: <?= htmlspecialchars($type['color']) ?>;">
                    <i class="fa-solid <?= htmlspecialchars($type['icon']) ?>"></i>
                </div>
                <div class="type-info">
                    <div class="type-name"><?= htmlspecialchars($type['name']) ?></div>
                    <div class="type-meta">
                        <span class="type-slug"><?= htmlspecialchars($type['slug']) ?></span>
                        <?php if ($type['description']): ?>
                            <span class="type-desc"><?= htmlspecialchars(substr($type['description'], 0, 50)) ?><?= strlen($type['description']) > 50 ? '...' : '' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="type-stats">
                    <span class="type-count">
                        <i class="fa-solid fa-users"></i>
                        <?= $type['group_count'] ?> group<?= $type['group_count'] !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="type-status">
                    <form method="POST" style="display: inline;">
                        <?= \Nexus\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="type_id" value="<?= $type['id'] ?>">
                        <button type="submit" class="status-toggle">
                            <span class="status-badge status-<?= $type['is_active'] ? 'active' : 'inactive' ?>">
                                <span class="status-dot"></span>
                                <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </button>
                    </form>
                </div>
                <div class="type-actions">
                    <a href="<?= $basePath ?>/admin/group-types/edit/<?= $type['id'] ?>" class="action-btn" title="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this type? Groups will not be deleted, only uncategorized.');">
                        <?= \Nexus\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="type_id" value="<?= $type['id'] ?>">
                        <button type="submit" class="action-btn action-btn-danger" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Page Hero */
.page-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2rem;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(99, 102, 241, 0.1));
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%);
    animation: heroFloat 15s ease-in-out infinite;
}

@keyframes heroFloat {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-30px, 30px); }
}

.page-hero-content {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    position: relative;
    z-index: 1;
}

.page-hero-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 25px rgba(139, 92, 246, 0.35);
}

.page-hero-text h1 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.25rem;
}

.page-hero-text p {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.6);
    margin: 0;
}

.page-hero-actions {
    position: relative;
    z-index: 1;
}

/* Stats Grid */
.stats-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    border-color: rgba(99, 102, 241, 0.3);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color, #6366f1), transparent);
}

.stat-blue { --stat-color: #3b82f6; }
.stat-green { --stat-color: #22c55e; }
.stat-purple { --stat-color: #8b5cf6; }
.stat-orange { --stat-color: #f59e0b; }

.stat-card-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.stat-card-value {
    font-size: 1.85rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.stat-card-label {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    margin-top: 4px;
    font-weight: 500;
}

/* Types List */
.types-list {
    display: flex;
    flex-direction: column;
}

.type-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.type-row:hover {
    background: rgba(99, 102, 241, 0.05);
}

.type-row:last-child {
    border-bottom: none;
}

.type-drag {
    color: rgba(255,255,255,0.25);
    cursor: grab;
    padding: 0.5rem;
    margin: -0.5rem;
    transition: color 0.2s;
}

.type-drag:hover {
    color: rgba(255,255,255,0.6);
}

.type-drag:active {
    cursor: grabbing;
}

.type-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.type-info {
    flex: 1;
    min-width: 0;
}

.type-name {
    font-weight: 700;
    color: #fff;
    font-size: 1rem;
    margin-bottom: 4px;
}

.type-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
}

.type-slug {
    font-family: 'Monaco', 'Menlo', monospace;
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    color: #a5b4fc;
}

.type-desc {
    color: rgba(255,255,255,0.4);
}

.type-stats {
    min-width: 100px;
}

.type-count {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.75rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    font-size: 0.8rem;
    color: #a5b4fc;
    font-weight: 600;
}

.type-count i {
    font-size: 0.75rem;
    opacity: 0.7;
}

.type-status {
    min-width: 100px;
}

.status-toggle {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border: 1px solid;
    transition: all 0.2s;
}

.status-active {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.status-inactive {
    background: rgba(148, 163, 184, 0.1);
    border-color: rgba(148, 163, 184, 0.3);
    color: #94a3b8;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.type-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: transparent;
    color: rgba(255,255,255,0.5);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s;
}

.action-btn:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.4);
    color: #a5b4fc;
}

.action-btn-danger:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 14px;
    margin-bottom: 1.5rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.25);
    color: #22c55e;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #ef4444;
}

.admin-alert-icon {
    font-size: 1.25rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }

    .page-hero-actions {
        width: 100%;
    }

    .page-hero-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .stats-grid-4 {
        grid-template-columns: 1fr;
    }

    .type-row {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .type-info {
        order: 1;
        width: 100%;
        margin-left: calc(44px + 1rem);
    }

    .type-meta {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .type-stats,
    .type-status {
        min-width: auto;
    }

    .type-actions {
        margin-left: auto;
    }
}

@media (max-width: 480px) {
    .type-info {
        margin-left: 0;
    }
}
</style>

<!-- Sortable.js for drag-and-drop reordering -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('sortable-types');
    if (list && list.children.length > 0) {
        new Sortable(list, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const order = Array.from(list.children).map(row => row.dataset.typeId);

                // Send reorder request with CSRF token
                const formData = new FormData();
                formData.append('action', 'reorder');
                formData.append('order', JSON.stringify(order));
                formData.append('csrf_token', '<?= \Nexus\Core\Csrf::token() ?>');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    // Show visual feedback
                    const toast = document.createElement('div');
                    toast.className = 'reorder-toast';
                    toast.innerHTML = '<i class="fa-solid fa-check"></i> Order saved';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 2000);
                });
            }
        });
    }
});
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
    background: rgba(99, 102, 241, 0.1);
}

.reorder-toast {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
    animation: toastIn 0.3s ease;
    z-index: 9999;
}

@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
