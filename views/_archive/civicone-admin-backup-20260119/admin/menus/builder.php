<?php
/**
 * Admin Menu Builder
 * Visual menu item management with drag-and-drop
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Menu Builder';
$adminPageSubtitle = 'Navigation';
$adminPageIcon = 'fa-bars';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/menus" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= htmlspecialchars($menu['name']) ?>
        </h1>
        <p class="admin-page-subtitle">Manage menu items and settings</p>
    </div>
    <div class="admin-page-header-actions">
        <button onclick="openSettingsModal()" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gear"></i> Settings
        </button>
        <button onclick="addMenuItem()" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> Add Item
        </button>
    </div>
</div>

<!-- Plan Limits Alert -->
<?php
$itemCount = count($menu['items'] ?? []);
$maxItems = $plan_limits['max_menu_items'] ?? 10;
if ($itemCount >= $maxItems * 0.8):
?>
<div class="admin-alert admin-alert-warning">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-exclamation-triangle"></i>
    </div>
    <div class="admin-alert-content">
        <strong>Approaching Limit</strong>
        <p>You have <?= $itemCount ?> of <?= $maxItems ?> menu items allowed on your plan.</p>
    </div>
</div>
<?php endif; ?>

<div class="menu-builder-layout">
    <!-- Menu Items Panel -->
    <div class="menu-items-panel admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-cyan">
                <i class="fa-solid fa-list"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Menu Structure</h3>
                <p class="admin-card-subtitle">Drag to reorder, click to edit</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (empty($menu['items'])): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <i class="fa-solid fa-link"></i>
                </div>
                <h3 class="admin-empty-title">No Menu Items</h3>
                <p class="admin-empty-text">Add your first menu item to get started.</p>
                <button onclick="addMenuItem()" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                    <i class="fa-solid fa-plus"></i> Add First Item
                </button>
            </div>
            <?php else: ?>
            <div id="menuItemsList" class="menu-items-list">
                <?php foreach ($menu['items'] as $item): ?>
                    <?php renderMenuItem($item); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Menu Preview Panel -->
    <div class="menu-preview-panel admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Live Preview</h3>
                <p class="admin-card-subtitle">How your menu will appear</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div id="menuPreview" class="menu-preview">
                <nav role="navigation" aria-label="Main navigation" class="preview-menu">
                    <ul>
                        <?php foreach ($menu['items'] ?? [] as $item): ?>
                            <?php renderPreviewItem($item); ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to render menu items recursively
function renderMenuItem($item, $depth = 0) {
    $indent = $depth * 2;
    ?>
    <div class="menu-item-row" data-item-id="<?= $item['id'] ?>" data-depth="<?= $depth ?>" style="padding-left: <?= $indent ?>rem;">
        <div class="menu-item-drag">
            <i class="fa-solid fa-grip-vertical"></i>
        </div>
        <div class="menu-item-icon">
            <?php if ($item['icon']): ?>
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
            <?php else: ?>
                <i class="fa-solid fa-link"></i>
            <?php endif; ?>
        </div>
        <div class="menu-item-info">
            <span class="menu-item-label"><?= htmlspecialchars($item['label']) ?></span>
            <span class="menu-item-type"><?= htmlspecialchars($item['type']) ?></span>
            <?php if ($item['url'] ?? null): ?>
                <span class="menu-item-url"><?= htmlspecialchars($item['url']) ?></span>
            <?php endif; ?>
        </div>
        <div class="menu-item-actions">
            <button onclick="editMenuItem(<?= $item['id'] ?>)" class="admin-btn-icon">
                <i class="fa-solid fa-edit"></i>
            </button>
            <button onclick="deleteMenuItem(<?= $item['id'] ?>)" class="admin-btn-icon admin-btn-danger">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </div>
    <?php
    if (!empty($item['children'])) {
        foreach ($item['children'] as $child) {
            renderMenuItem($child, $depth + 1);
        }
    }
}

function renderPreviewItem($item) {
    if ($item['type'] === 'divider') {
        echo '<li><hr class="menu-divider"></li>';
        return;
    }
    ?>
    <li>
        <?php if ($item['type'] === 'dropdown' && !empty($item['children'])): ?>
            <details>
                <summary>
                    <?php if ($item['icon']): ?>
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($item['label']) ?>
                </summary>
                <ul>
                    <?php foreach ($item['children'] as $child): ?>
                        <?php renderPreviewItem($child); ?>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php else: ?>
            <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>">
                <?php if ($item['icon']): ?>
                    <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <?php endif; ?>
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endif; ?>
    </li>
    <?php
}
?>

<!-- Add/Edit Item Modal -->
<div id="itemModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-overlay" onclick="closeItemModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2 id="itemModalTitle">Add Menu Item</h2>
            <button onclick="closeItemModal()" class="admin-modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <form id="itemForm">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                <input type="hidden" id="item_id" name="item_id" value="">

                <div class="form-group">
                    <label>Item Type</label>
                    <select name="type" id="item_type" onchange="updateItemFormFields()">
                        <?php foreach ($item_types as $value => $label): ?>
                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Label *</label>
                    <input type="text" name="label" id="item_label" required>
                </div>

                <div class="form-group" id="url_field">
                    <label>URL</label>
                    <input type="text" name="url" id="item_url" placeholder="/path or https://external.com">
                </div>

                <div class="form-group" id="page_field" style="display: none;">
                    <label>Select Page</label>
                    <select name="page_id" id="item_page_id">
                        <option value="">-- Select Page --</option>
                        <?php foreach ($pages as $page): ?>
                        <option value="<?= $page['id'] ?>"><?= htmlspecialchars($page['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Icon (FontAwesome class)</label>
                        <input type="text" name="icon" id="item_icon" placeholder="fa-solid fa-home">
                    </div>

                    <div class="form-group">
                        <label>CSS Class</label>
                        <input type="text" name="css_class" id="item_css_class" placeholder="custom-class">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Parent Item</label>
                        <select name="parent_id" id="item_parent_id">
                            <option value="">-- Top Level --</option>
                            <?php foreach ($menu['items'] ?? [] as $item): ?>
                                <?php if ($item['type'] === 'dropdown'): ?>
                                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['label']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Target</label>
                        <select name="target" id="item_target">
                            <option value="_self">Same window</option>
                            <option value="_blank">New window</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="item_sort_order" value="0">
                </div>

                <details>
                    <summary style="cursor: pointer; padding: 0.5rem 0; font-weight: 600;">
                        <i class="fa-solid fa-eye"></i> Visibility Rules (Optional)
                    </summary>
                    <div style="padding: 1rem 0;">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="requires_auth" id="item_requires_auth" value="1">
                                Requires authentication
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Minimum Role</label>
                            <select name="min_role" id="item_min_role">
                                <option value="">-- No restriction --</option>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Requires Feature</label>
                            <input type="text" name="requires_feature" id="item_requires_feature" placeholder="groups, wallet, etc.">
                        </div>
                    </div>
                </details>
            </form>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closeItemModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="saveMenuItem()" class="admin-btn admin-btn-primary">Save Item</button>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-overlay" onclick="closeSettingsModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2>Menu Settings</h2>
            <button onclick="closeSettingsModal()" class="admin-modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <form id="settingsForm">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

                <div class="form-group">
                    <label>Menu Name *</label>
                    <input type="text" name="name" id="menu_name" value="<?= htmlspecialchars($menu['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Slug *</label>
                    <input type="text" name="slug" id="menu_slug" value="<?= htmlspecialchars($menu['slug']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="menu_description" rows="3"><?= htmlspecialchars($menu['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location" id="menu_location">
                            <?php foreach ($available_locations as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $menu['location'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Layout (Optional)</label>
                        <select name="layout" id="menu_layout">
                            <option value="">-- All Layouts --</option>
                            <?php foreach ($available_layouts as $layout): ?>
                            <option value="<?= $layout ?>" <?= $menu['layout'] === $layout ? 'selected' : '' ?>><?= htmlspecialchars($layout) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="menu_is_active" value="1" <?= $menu['is_active'] ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
            </form>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closeSettingsModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="saveMenuSettings()" class="admin-btn admin-btn-primary">Save Settings</button>
        </div>
    </div>
</div>

<style>
.menu-builder-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
}

@media (max-width: 1200px) {
    .menu-builder-layout {
        grid-template-columns: 1fr;
    }
}

.menu-items-list {
    display: flex;
    flex-direction: column;
}

.menu-item-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: background-color 0.2s;
    cursor: move;
}

.menu-item-row:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.menu-item-drag {
    color: rgba(255, 255, 255, 0.3);
    cursor: grab;
}

.menu-item-drag:active {
    cursor: grabbing;
}

.menu-item-icon {
    color: rgba(255, 255, 255, 0.6);
}

.menu-item-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.menu-item-label {
    font-weight: 600;
}

.menu-item-type {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
}

.menu-item-url {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.4);
    font-family: monospace;
}

.menu-item-actions {
    display: flex;
    gap: 0.5rem;
}

.admin-btn-icon {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.admin-btn-icon:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.admin-btn-icon.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.menu-preview {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 0.5rem;
    padding: 1.5rem;
}

.preview-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.preview-menu li {
    margin-bottom: 0.5rem;
}

.preview-menu a {
    display: block;
    padding: 0.5rem;
    border-radius: 0.25rem;
    color: #fff;
    text-decoration: none;
    transition: background-color 0.2s;
}

.preview-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
}

.preview-menu summary {
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.25rem;
}

.preview-menu summary:hover {
    background: rgba(255, 255, 255, 0.1);
}

.preview-menu ul ul {
    padding-left: 1.5rem;
    margin-top: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.admin-back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
}

.admin-back-link:hover {
    opacity: 0.8;
}
</style>

<script>
const menuId = <?= $menu['id'] ?>;
const basePath = '<?= $basePath ?>';
const csrfToken = '<?= Csrf::generate() ?>';

function addMenuItem() {
    document.getElementById('itemModalTitle').textContent = 'Add Menu Item';
    document.getElementById('itemForm').reset();
    document.getElementById('item_id').value = '';
    document.getElementById('itemModal').style.display = 'flex';
}

function editMenuItem(itemId) {
    // Fetch item data and populate the form
    fetch(`${basePath}/admin/menus/item/${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.item) {
                const item = data.item;

                // Populate form fields
                document.getElementById('item_id').value = item.id || '';
                document.getElementById('item_type').value = item.type || 'link';
                document.getElementById('item_label').value = item.label || '';
                document.getElementById('item_url').value = item.url || '';
                document.getElementById('item_page_id').value = item.page_id || '';
                document.getElementById('item_icon').value = item.icon || '';
                document.getElementById('item_css_class').value = item.css_class || '';
                document.getElementById('item_parent_id').value = item.parent_id || '';
                document.getElementById('item_target').value = item.target || '_self';
                document.getElementById('item_sort_order').value = item.sort_order || 0;

                // Handle visibility rules
                if (item.visibility_rules) {
                    const rules = typeof item.visibility_rules === 'string'
                        ? JSON.parse(item.visibility_rules)
                        : item.visibility_rules;

                    document.getElementById('item_requires_auth').checked = rules.requires_auth || false;
                    document.getElementById('item_min_role').value = rules.min_role || '';
                    document.getElementById('item_requires_feature').value = rules.requires_feature || '';
                }

                // Update form fields visibility based on type
                updateItemFormFields();

                // Open modal
                document.getElementById('itemModal').style.display = 'flex';
            } else {
                alert('Error: Could not load menu item data');
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

function closeItemModal() {
    document.getElementById('itemModal').style.display = 'none';
}

function saveMenuItem() {
    const form = document.getElementById('itemForm');
    const formData = new FormData(form);
    const itemId = document.getElementById('item_id').value;
    const url = itemId
        ? `${basePath}/admin/menus/item/update/${itemId}`
        : `${basePath}/admin/menus/item/add`;

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to save menu item'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function deleteMenuItem(itemId) {
    if (!confirm('Delete this menu item?')) {
        return;
    }

    fetch(`${basePath}/admin/menus/item/delete/${itemId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete'));
        }
    });
}

function openSettingsModal() {
    document.getElementById('settingsModal').style.display = 'flex';
}

function closeSettingsModal() {
    document.getElementById('settingsModal').style.display = 'none';
}

function saveMenuSettings() {
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);

    fetch(`${basePath}/admin/menus/update/${menuId}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to save settings'));
        }
    });
}

function updateItemFormFields() {
    const type = document.getElementById('item_type').value;
    const urlField = document.getElementById('url_field');
    const pageField = document.getElementById('page_field');

    if (type === 'page') {
        urlField.style.display = 'none';
        pageField.style.display = 'block';
    } else if (type === 'dropdown' || type === 'divider') {
        urlField.style.display = 'none';
        pageField.style.display = 'none';
    } else {
        urlField.style.display = 'block';
        pageField.style.display = 'none';
    }
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
