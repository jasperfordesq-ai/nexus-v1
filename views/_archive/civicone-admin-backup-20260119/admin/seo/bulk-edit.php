<?php
/**
 * Admin Bulk SEO Editor - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$csrfToken = Csrf::generate();

// Admin header configuration
$adminPageTitle = 'Bulk SEO Editor';
$adminPageSubtitle = 'SEO';
$adminPageIcon = 'fa-pen-to-square';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/seo" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Bulk SEO Editor
        </h1>
        <p class="admin-page-subtitle"><?= ucfirst($type) ?>s - Edit meta titles and descriptions in bulk</p>
    </div>
    <div class="admin-page-header-actions">
        <div class="type-tabs">
            <a href="<?= $basePath ?>/admin-legacy/seo/bulk/listing" class="type-tab <?= $type === 'listing' ? 'active' : '' ?>">Listings</a>
            <a href="<?= $basePath ?>/admin-legacy/seo/bulk/event" class="type-tab <?= $type === 'event' ? 'active' : '' ?>">Events</a>
            <a href="<?= $basePath ?>/admin-legacy/seo/bulk/group" class="type-tab <?= $type === 'group' ? 'active' : '' ?>">Groups</a>
            <a href="<?= $basePath ?>/admin-legacy/seo/bulk/post" class="type-tab <?= $type === 'post' ? 'active' : '' ?>">Posts</a>
        </div>
    </div>
</div>

<!-- Items Table -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title"><?= ucfirst($type) ?>s (<?= count($items) ?>)</h3>
            <p class="admin-card-subtitle">Click on a field to edit, then press Enter or click Save</p>
        </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fa-solid fa-folder-open"></i>
        </div>
        <h3>No <?= $type ?>s Found</h3>
        <p>There are no <?= $type ?>s to edit.</p>
    </div>
    <?php else: ?>
    <div class="admin-table-wrapper">
        <table class="admin-table bulk-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Title</th>
                    <th style="width: 30%;">Meta Title</th>
                    <th style="width: 35%;">Meta Description</th>
                    <th style="width: 10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr id="row-<?= $item['id'] ?>">
                    <td>
                        <div class="item-title" title="<?= htmlspecialchars($item['title']) ?>">
                            <?= htmlspecialchars($item['title']) ?>
                        </div>
                    </td>
                    <td>
                        <input type="text"
                               class="bulk-input"
                               data-id="<?= $item['id'] ?>"
                               data-field="meta_title"
                               value="<?= htmlspecialchars($item['meta_title'] ?? '') ?>"
                               placeholder="<?= htmlspecialchars(substr($item['title'], 0, 60)) ?>"
                               maxlength="70">
                        <div class="char-count"><span class="current"><?= strlen($item['meta_title'] ?? '') ?></span>/60</div>
                    </td>
                    <td>
                        <input type="text"
                               class="bulk-input"
                               data-id="<?= $item['id'] ?>"
                               data-field="meta_description"
                               value="<?= htmlspecialchars($item['meta_description'] ?? '') ?>"
                               placeholder="Auto-generated from content..."
                               maxlength="160">
                        <div class="char-count"><span class="current"><?= strlen($item['meta_description'] ?? '') ?></span>/160</div>
                    </td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="save-btn" data-id="<?= $item['id'] ?>" title="Save">
                                <i class="fa-solid fa-check"></i>
                            </button>
                            <span class="save-status" id="status-<?= $item['id'] ?>"></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Type Tabs */
.type-tabs {
    display: flex;
    gap: 0.5rem;
}

.type-tab {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
}

.type-tab:hover {
    background: rgba(255, 255, 255, 0.15);
}

.type-tab.active {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border-color: transparent;
}

/* Item Title */
.item-title {
    font-weight: 500;
    color: #f1f5f9;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Bulk Input */
.bulk-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    font-size: 0.85rem;
    transition: all 0.2s;
    background: rgba(0, 0, 0, 0.2);
    color: #f1f5f9;
}

.bulk-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.bulk-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.bulk-input.modified {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
}

.bulk-input.saved {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

/* Character Count */
.char-count {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.4);
    text-align: right;
    margin-top: 4px;
}

.char-count .current.warning { color: #f59e0b; }
.char-count .current.error { color: #ef4444; }

/* Row Actions */
.row-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.save-btn {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.save-btn:hover {
    transform: scale(1.05);
}

.save-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.save-status {
    font-size: 0.8rem;
}

.save-status.success { color: #10b981; }
.save-status.error { color: #ef4444; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: rgba(99, 102, 241, 0.5);
}

.empty-state h3 {
    color: #fff;
    margin: 0 0 0.5rem;
}

.empty-state p {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Mobile */
@media (max-width: 900px) {
    .type-tabs {
        flex-wrap: wrap;
    }

    .type-tab {
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
    }

    .bulk-table thead {
        display: none;
    }

    .bulk-table tbody tr {
        display: block;
        padding: 1rem;
        margin-bottom: 0.75rem;
        background: rgba(30, 41, 59, 0.5);
        border-radius: 12px;
    }

    .bulk-table td {
        display: block;
        padding: 0.5rem 0;
    }

    .item-title {
        max-width: 100%;
        margin-bottom: 0.5rem;
        font-size: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.bulk-input');
    const type = '<?= $type ?>';
    const csrfToken = '<?= $csrfToken ?>';
    const basePath = '<?= $basePath ?>';

    inputs.forEach(input => {
        const originalValue = input.value;

        input.addEventListener('input', function() {
            const count = this.value.length;
            const countSpan = this.parentElement.querySelector('.current');
            const max = this.dataset.field === 'meta_title' ? 60 : 160;

            countSpan.textContent = count;
            countSpan.classList.remove('warning', 'error');
            if (count > max) countSpan.classList.add('error');
            else if (count > max * 0.9) countSpan.classList.add('warning');

            this.classList.toggle('modified', this.value !== originalValue);
            this.classList.remove('saved');
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveItem(this.dataset.id);
            }
        });
    });

    document.querySelectorAll('.save-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            saveItem(this.dataset.id);
        });
    });

    function saveItem(id) {
        const row = document.getElementById('row-' + id);
        const titleInput = row.querySelector('[data-field="meta_title"]');
        const descInput = row.querySelector('[data-field="meta_description"]');
        const status = document.getElementById('status-' + id);
        const btn = row.querySelector('.save-btn');

        btn.disabled = true;
        status.textContent = '...';
        status.className = 'save-status';

        const formData = new FormData();
        formData.append('type', type);
        formData.append('id', id);
        formData.append('meta_title', titleInput.value);
        formData.append('meta_description', descInput.value);
        formData.append('csrf_token', csrfToken);

        fetch(basePath + '/admin-legacy/seo/bulk/save', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                status.textContent = '\u2713';
                status.className = 'save-status success';
                titleInput.classList.remove('modified');
                titleInput.classList.add('saved');
                descInput.classList.remove('modified');
                descInput.classList.add('saved');
                setTimeout(() => { status.textContent = ''; }, 2000);
            } else {
                status.textContent = '\u2717';
                status.className = 'save-status error';
            }
        })
        .catch(() => {
            btn.disabled = false;
            status.textContent = '\u2717';
            status.className = 'save-status error';
        });
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
