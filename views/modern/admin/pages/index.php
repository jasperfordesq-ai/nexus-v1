<?php
/**
 * Admin Page Manager - Gold Standard
 * STANDALONE admin interface with drag-to-reorder
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Pages';
$adminPageSubtitle = 'CMS';
$adminPageIcon = 'fa-file-lines';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-file-lines"></i>
            Page Manager
        </h1>
        <p class="admin-page-subtitle">Create and manage custom pages with the visual builder</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/pages/create?confirm=1" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Page
        </a>
    </div>
</div>

<!-- Pages Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Custom Pages</h3>
            <p class="admin-card-subtitle">Drag to reorder, click to manage</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($pages)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-file-circle-plus"></i>
            </div>
            <h3 class="admin-empty-title">No Custom Pages Yet</h3>
            <p class="admin-empty-text">Create your first page using the visual page builder.</p>
            <a href="<?= $basePath ?>/admin-legacy/pages/create?confirm=1" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i> Create First Page
            </a>
        </div>
        <?php else: ?>
        <div id="pagesList" class="pages-sortable-list">
            <?php foreach ($pages as $p): ?>
            <div class="page-row" data-page-id="<?= $p['id'] ?>">
                <div class="page-drag-handle">
                    <i class="fa-solid fa-grip-vertical"></i>
                </div>
                <div class="page-info">
                    <div class="page-title-row">
                        <span class="page-title"><?= htmlspecialchars($p['title']) ?></span>
                        <?php if (!empty($p['is_published'])): ?>
                            <span class="page-status page-status-published">
                                <i class="fa-solid fa-check-circle"></i> Published
                            </span>
                        <?php elseif (!empty($p['publish_at']) && strtotime($p['publish_at']) > time()): ?>
                            <span class="page-status page-status-scheduled">
                                <i class="fa-solid fa-clock"></i> Scheduled
                            </span>
                        <?php else: ?>
                            <span class="page-status page-status-draft">
                                <i class="fa-solid fa-pen"></i> Draft
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['show_in_menu'])): ?>
                            <?php
                            $menuLabels = [
                                'about' => ['About Menu', 'fa-circle-info', '#8b5cf6'],
                                'main' => ['Main Nav', 'fa-bars', '#6366f1'],
                                'footer' => ['Footer', 'fa-shoe-prints', '#64748b']
                            ];
                            $loc = $p['menu_location'] ?? 'about';
                            $menuInfo = $menuLabels[$loc] ?? ['Menu', 'fa-bars', '#64748b'];
                            ?>
                            <span class="page-status page-status-menu" style="background: <?= $menuInfo[2] ?>22; color: <?= $menuInfo[2] ?>;">
                                <i class="fa-solid <?= $menuInfo[1] ?>"></i> <?= $menuInfo[0] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="page-slug">/page/<?= htmlspecialchars($p['slug']) ?></div>
                </div>
                <div class="page-actions">
                    <a href="<?= $basePath ?>/admin-legacy/pages/builder/<?= $p['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm" title="Edit Page">
                        <i class="fa-solid fa-pen-ruler"></i> <span class="btn-text">Design</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/pages/preview/<?= $p['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Preview Page" target="_blank">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/pages/duplicate/<?= $p['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Duplicate Page" onclick="return confirm('Create a copy of this page?')">
                        <i class="fa-solid fa-copy"></i>
                    </a>
                    <?php if (!empty($p['is_published'])): ?>
                        <a href="<?= $basePath ?>/page/<?= $p['slug'] ?>" class="admin-btn admin-btn-success admin-btn-sm" title="View Live Page" target="_blank">
                            <i class="fa-solid fa-external-link"></i>
                        </a>
                    <?php endif; ?>
                    <form action="<?= $basePath ?>/admin-legacy/pages/delete" method="POST" style="display:inline;" onsubmit="return confirm('Delete this page? This cannot be undone.');">
                        <?= Csrf::input() ?>
                        <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Delete Page">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="reorderStatus" class="reorder-status"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Help Card -->
<div class="admin-glass-card help-card" style="margin-top: 1.5rem;">
    <div class="admin-card-body" style="padding: 1.25rem;">
        <div class="help-items">
            <div class="help-item">
                <i class="fa-solid fa-grip-vertical"></i>
                <span>Drag rows to reorder pages</span>
            </div>
            <div class="help-item">
                <i class="fa-solid fa-bars"></i>
                <span>Add pages to navigation menus</span>
            </div>
            <div class="help-item">
                <i class="fa-solid fa-copy"></i>
                <span>Duplicate to create templates</span>
            </div>
            <div class="help-item">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Version history in editor</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Page Manager Specific Styles */

/* Sortable List */
.pages-sortable-list {
    display: flex;
    flex-direction: column;
}

.page-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: rgba(30, 41, 59, 0.3);
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.page-row:last-child {
    border-bottom: none;
}

.page-row:hover {
    background: rgba(99, 102, 241, 0.08);
}

.page-row.dragging {
    opacity: 0.5;
    background: rgba(99, 102, 241, 0.2);
}

.page-row.drag-over {
    border-top: 2px solid #6366f1;
}

/* Drag Handle */
.page-drag-handle {
    cursor: grab;
    color: rgba(255, 255, 255, 0.4);
    padding: 0.5rem;
    transition: color 0.2s;
}

.page-drag-handle:hover {
    color: #818cf8;
}

.page-drag-handle:active {
    cursor: grabbing;
}

/* Page Info */
.page-info {
    flex: 1;
    min-width: 0;
}

.page-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.page-title {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.page-slug {
    font-family: 'Fira Code', monospace;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 4px;
}

/* Status Badges */
.page-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.page-status-published {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.page-status-draft {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.page-status-scheduled {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.page-status-menu {
    border: 1px solid currentColor;
    opacity: 0.8;
}

/* Actions */
.page-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-success {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-btn-success:hover {
    background: rgba(34, 197, 94, 0.25);
    border-color: rgba(34, 197, 94, 0.5);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Reorder Status */
.reorder-status {
    padding: 0.75rem 1.5rem;
    text-align: center;
    font-size: 0.85rem;
    display: none;
}

.reorder-status.saving {
    display: block;
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border-top: 1px solid rgba(245, 158, 11, 0.3);
}

.reorder-status.saved {
    display: block;
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border-top: 1px solid rgba(34, 197, 94, 0.3);
}

.reorder-status.error {
    display: block;
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border-top: 1px solid rgba(239, 68, 68, 0.3);
}

/* Help Card */
.help-card {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.help-items {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.help-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.help-item i {
    color: #22d3ee;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .page-row {
        flex-wrap: wrap;
        padding: 1rem;
    }

    .page-info {
        flex: 1 1 calc(100% - 50px);
        order: 1;
    }

    .page-drag-handle {
        order: 0;
    }

    .page-actions {
        order: 2;
        width: 100%;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(99, 102, 241, 0.1);
    }

    .page-actions .btn-text {
        display: none;
    }

    .help-items {
        flex-direction: column;
        gap: 0.75rem;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';
const csrfToken = '<?= Csrf::generate() ?>';

document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('pagesList');
    if (!list) return;

    let draggedItem = null;

    list.querySelectorAll('.page-row').forEach(row => {
        const handle = row.querySelector('.page-drag-handle');

        handle.addEventListener('mousedown', () => {
            row.setAttribute('draggable', 'true');
        });

        handle.addEventListener('mouseup', () => {
            row.setAttribute('draggable', 'false');
        });

        row.addEventListener('dragstart', handleDragStart);
        row.addEventListener('dragend', handleDragEnd);
        row.addEventListener('dragover', handleDragOver);
        row.addEventListener('drop', handleDrop);
        row.addEventListener('dragleave', handleDragLeave);
    });

    function handleDragStart(e) {
        draggedItem = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.pageId);
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        this.setAttribute('draggable', 'false');
        list.querySelectorAll('.page-row').forEach(row => {
            row.classList.remove('drag-over');
        });
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (this !== draggedItem) {
            this.classList.add('drag-over');
        }
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('drag-over');

        if (this !== draggedItem) {
            const allRows = [...list.querySelectorAll('.page-row')];
            const draggedIndex = allRows.indexOf(draggedItem);
            const targetIndex = allRows.indexOf(this);

            if (draggedIndex < targetIndex) {
                this.parentNode.insertBefore(draggedItem, this.nextSibling);
            } else {
                this.parentNode.insertBefore(draggedItem, this);
            }
            saveOrder();
        }
    }

    function saveOrder() {
        const status = document.getElementById('reorderStatus');
        const rows = list.querySelectorAll('.page-row');
        const order = [];

        rows.forEach((row, index) => {
            order.push(row.dataset.pageId);
        });

        status.textContent = 'Saving order...';
        status.className = 'reorder-status saving';

        const formData = new FormData();
        order.forEach((id, index) => {
            formData.append('order[]', id);
        });
        formData.append('csrf_token', csrfToken);

        fetch(`${basePath}/admin-legacy/pages/reorder`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                status.textContent = 'Order saved!';
                status.className = 'reorder-status saved';
                setTimeout(() => { status.className = 'reorder-status'; }, 2000);
            } else {
                throw new Error(data.error || 'Failed to save');
            }
        })
        .catch(err => {
            status.textContent = 'Failed to save order';
            status.className = 'reorder-status error';
            setTimeout(() => { status.className = 'reorder-status'; }, 3000);
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
