<?php
/**
 * Page Builder V2 - Modern Builder Interface
 * Clean, functional page builder with Gold Standard design
 */

use Nexus\Core\TenantContext;
use Nexus\PageBuilder\PageRenderer;
use Nexus\PageBuilder\BlockRegistry;

$basePath = TenantContext::getBasePath();
$pageId = (int)($page['id'] ?? 0);

// Get existing blocks
$blocks = PageRenderer::getBlocks($pageId);

// Get all available block types
$allBlocks = BlockRegistry::getAllBlocks();

// Group blocks by category
$categories = [];
foreach ($allBlocks as $blockType => $config) {
    $category = $config['category'];
    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }
    $categories[$category][$blockType] = $config;
}

// Admin header configuration
$adminPageTitle = 'Page Builder V2';
$adminPageSubtitle = 'Pages';
$adminPageIcon = 'fa-file-lines';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
    /* Builder Layout */
    .builder-container {
        display: grid;
        grid-template-columns: 280px 1fr 320px;
        gap: 1rem;
        height: calc(100vh - 120px);
        padding: 1rem;
        max-width: 100%;
    }

    /* Left Panel - Block Palette */
    .block-palette {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 12px;
        padding: 1rem;
        overflow-y: auto;
        backdrop-filter: blur(10px);
    }

    .block-palette h3 {
        margin: 0 0 1rem 0;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: rgba(255,255,255,0.7);
    }

    .block-category {
        margin-bottom: 1.5rem;
    }

    .block-category-title {
        font-size: 0.75rem;
        color: #6366f1;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .block-item {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .block-item:hover {
        background: rgba(99, 102, 241, 0.15);
        border-color: rgba(99, 102, 241, 0.4);
        transform: translateX(4px);
    }

    .block-item i {
        color: #6366f1;
        font-size: 1.1rem;
    }

    .block-item-content {
        flex: 1;
    }

    .block-item-label {
        font-size: 0.85rem;
        color: #fff;
        font-weight: 500;
    }

    .block-item-desc {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.5);
        margin-top: 2px;
    }

    /* Center Panel - Canvas */
    .builder-canvas {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 12px;
        overflow-y: auto;
        backdrop-filter: blur(10px);
        display: flex;
        flex-direction: column;
    }

    .canvas-toolbar {
        padding: 1rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.2);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .canvas-content {
        flex: 1;
        padding: 1rem;
        min-height: 400px;
    }

    .canvas-block {
        background: rgba(255,255,255,0.03);
        border: 2px dashed rgba(99, 102, 241, 0.3);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        position: relative;
        transition: all 0.2s;
    }

    .canvas-block:hover {
        border-color: rgba(99, 102, 241, 0.6);
        background: rgba(99, 102, 241, 0.05);
    }

    .canvas-block.selected {
        border-color: #6366f1;
        border-style: solid;
        background: rgba(99, 102, 241, 0.1);
    }

    .block-controls {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        display: flex;
        gap: 0.25rem;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .canvas-block:hover .block-controls {
        opacity: 1;
    }

    .block-control-btn {
        background: rgba(15, 23, 42, 0.9);
        border: 1px solid rgba(99, 102, 241, 0.3);
        color: #fff;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
    }

    .block-control-btn:hover {
        background: #6366f1;
    }

    .block-type-badge {
        display: inline-block;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    /* Right Panel - Settings */
    .block-settings {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 12px;
        padding: 1rem;
        overflow-y: auto;
        backdrop-filter: blur(10px);
    }

    /* Settings Tabs */
    .settings-tabs {
        display: flex;
        gap: 0.5rem;
        padding: 0.5rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.2);
        margin-bottom: 1rem;
    }

    .settings-tab {
        flex: 1;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        padding: 0.5rem;
        color: rgba(255,255,255,0.7);
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .settings-tab:hover {
        background: rgba(99, 102, 241, 0.1);
        border-color: rgba(99, 102, 241, 0.4);
    }

    .settings-tab.active {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-color: #6366f1;
        color: white;
    }

    .settings-panel-content {
        display: none;
    }

    .settings-panel-content.active {
        display: block;
    }

    .form-checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-checkbox {
        width: auto;
    }

    .settings-header {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    }

    .settings-header h3 {
        margin: 0;
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
    }

    .settings-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.7);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-input,
    .form-textarea,
    .form-select {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        padding: 0.75rem;
        color: #fff;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .form-input:focus,
    .form-textarea:focus,
    .form-select:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    .form-textarea {
        min-height: 100px;
        resize: vertical;
    }

    .form-checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-checkbox {
        width: 18px;
        height: 18px;
        accent-color: #6366f1;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: rgba(255,255,255,0.5);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    @media (max-width: 1400px) {
        .builder-container {
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr auto;
            height: auto;
        }

        .block-palette,
        .block-settings {
            max-height: 300px;
        }
    }
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-file-lines"></i>
            Page Builder V2
        </h1>
        <p class="admin-page-subtitle">Building: <?= htmlspecialchars($page['title']) ?></p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/pages" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Pages
        </a>
        <button id="save-btn" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Save Page
        </button>
    </div>
</div>

<!-- Builder Container -->
<div class="builder-container">
    <!-- Left: Block Palette -->
    <div class="block-palette">
        <h3><i class="fa-solid fa-cubes"></i> Add Blocks</h3>

        <?php foreach ($categories as $categoryName => $categoryBlocks): ?>
            <div class="block-category">
                <div class="block-category-title"><?= ucfirst($categoryName) ?></div>
                <?php foreach ($categoryBlocks as $blockType => $config): ?>
                    <div class="block-item" data-type="<?= $blockType ?>" onclick="addBlock('<?= $blockType ?>')">
                        <i class="fa-solid <?= $config['icon'] ?>"></i>
                        <div class="block-item-content">
                            <div class="block-item-label"><?= $config['label'] ?></div>
                            <?php if (isset($config['description'])): ?>
                                <div class="block-item-desc"><?= $config['description'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Center: Canvas -->
    <div class="builder-canvas">
        <div class="canvas-toolbar">
            <button class="admin-btn admin-btn-secondary admin-btn-sm" onclick="clearAllBlocks()" title="Clear All">
                <i class="fa-solid fa-trash"></i>
            </button>
            <div style="flex: 1;"></div>
            <span style="font-size: 0.75rem; color: rgba(255,255,255,0.5);">
                <span id="block-count">0</span> blocks
            </span>
        </div>
        <div class="canvas-content" id="canvas">
            <div class="empty-state">
                <i class="fa-solid fa-cube"></i>
                <div>Click a block on the left to add it to your page</div>
            </div>
        </div>
    </div>

    <!-- Right: Settings Panel with Tabs -->
    <div class="block-settings">
        <div class="settings-tabs">
            <button class="settings-tab active" data-tab="block" onclick="switchSettingsTab('block')">
                <i class="fa-solid fa-cube"></i> Block Settings
            </button>
            <button class="settings-tab" data-tab="page" onclick="switchSettingsTab('page')">
                <i class="fa-solid fa-gear"></i> Page Settings
            </button>
        </div>

        <div id="settings-panel-block" class="settings-panel-content active">
            <div class="empty-state">
                <i class="fa-solid fa-sliders"></i>
                <div>Select a block to edit its settings</div>
            </div>
        </div>

        <div id="settings-panel-page" class="settings-panel-content" style="display: none;">
            <div class="settings-header">
                <h3><i class="fa-solid fa-gear"></i> Page Settings</h3>
            </div>
            <div class="settings-form" id="page-settings-form">
                <div class="form-group">
                    <label class="form-label">Page Title</label>
                    <input type="text" class="form-input" id="page-title" value="<?= htmlspecialchars($page['title']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">URL Slug</label>
                    <input type="text" class="form-input" id="page-slug" value="<?= htmlspecialchars($page['slug']) ?>" placeholder="my-page-url">
                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5); margin-top: 0.25rem;">
                        Preview: /page/<span id="slug-preview"><?= htmlspecialchars($page['slug']) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="form-checkbox-group">
                        <input type="checkbox" class="form-checkbox" id="page-published" <?= ($page['is_published'] ?? 0) ? 'checked' : '' ?>>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Published (visible to public)</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Add to Navigation</label>
                    <div class="form-checkbox-group">
                        <input type="checkbox" class="form-checkbox" id="page-show-in-menu" <?= ($page['show_in_menu'] ?? 0) ? 'checked' : '' ?>>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Show in menu</span>
                    </div>
                </div>

                <div class="form-group" id="menu-location-group" style="<?= ($page['show_in_menu'] ?? 0) ? '' : 'display:none;' ?>">
                    <label class="form-label">Menu Location</label>
                    <select class="form-select" id="page-menu-location">
                        <option value="about" <?= ($page['menu_location'] ?? 'about') === 'about' ? 'selected' : '' ?>>About Menu (Header Dropdown)</option>
                        <option value="main" <?= ($page['menu_location'] ?? 'about') === 'main' ? 'selected' : '' ?>>Main Navigation</option>
                        <option value="footer" <?= ($page['menu_location'] ?? 'about') === 'footer' ? 'selected' : '' ?>>Footer</option>
                    </select>
                </div>

                <div class="form-group">
                    <button class="admin-btn admin-btn-primary" onclick="savePageSettings()" style="width: 100%;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Settings
                    </button>
                </div>

                <div class="form-group">
                    <a href="<?= $basePath ?>/page/<?= htmlspecialchars($page['slug']) ?>" target="_blank" class="admin-btn admin-btn-secondary" style="width: 100%; text-align: center; display: block; text-decoration: none;">
                        <i class="fa-solid fa-eye"></i> Preview Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const basePath = "<?= $basePath ?>";
const pageId = <?= $pageId ?>;

// Block type definitions with defaults
const blockDefinitions = <?= json_encode($allBlocks) ?>;

// Current blocks state
let blocks = <?= json_encode($blocks) ?>;
let selectedBlockIndex = null;

// Security: HTML escape function to prevent XSS
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// Initialize
renderCanvas();

// Add a new block
function addBlock(type) {
    const defaults = blockDefinitions[type].defaults || {};
    blocks.push({
        type: type,
        data: {...defaults}
    });
    renderCanvas();
    selectBlock(blocks.length - 1);
}

// Render canvas
function renderCanvas() {
    const canvas = document.getElementById('canvas');
    const blockCount = document.getElementById('block-count');

    if (blocks.length === 0) {
        canvas.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-cube"></i>
                <div>Click a block on the left to add it to your page</div>
            </div>
        `;
        blockCount.textContent = '0';
        return;
    }

    blockCount.textContent = blocks.length;

    canvas.innerHTML = blocks.map((block, index) => {
        const config = blockDefinitions[block.type];
        const isSelected = index === selectedBlockIndex;

        return `
            <div class="canvas-block ${isSelected ? 'selected' : ''}" onclick="selectBlock(${index})">
                <div class="block-controls">
                    <button class="block-control-btn" onclick="event.stopPropagation(); moveBlockUp(${index})" title="Move Up">
                        <i class="fa-solid fa-arrow-up"></i>
                    </button>
                    <button class="block-control-btn" onclick="event.stopPropagation(); moveBlockDown(${index})" title="Move Down">
                        <i class="fa-solid fa-arrow-down"></i>
                    </button>
                    <button class="block-control-btn" onclick="event.stopPropagation(); deleteBlock(${index})" title="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <div class="block-type-badge">
                    <i class="fa-solid ${config.icon}"></i> ${config.label}
                </div>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.7);">
                    ${getBlockPreview(block)}
                </div>
            </div>
        `;
    }).join('');
}

// Get block preview text
function getBlockPreview(block) {
    const data = block.data;
    switch(block.type) {
        case 'hero':
            return `Title: ${data.title || 'Untitled'}`;
        case 'richtext':
            const text = data.content ? data.content.replace(/<[^>]*>/g, '').substring(0, 100) : 'Empty';
            return text;
        case 'members-grid':
            return `Showing ${data.limit || 6} members in ${data.columns || 3} columns`;
        default:
            return 'Block data: ' + Object.keys(data).join(', ');
    }
}

// Select a block
function selectBlock(index) {
    selectedBlockIndex = index;
    renderCanvas();
    renderSettings();
}

// Render settings panel
function renderSettings() {
    const panel = document.getElementById('settings-panel-block');

    if (selectedBlockIndex === null) {
        panel.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-sliders"></i>
                <div>Select a block to edit its settings</div>
            </div>
        `;
        return;
    }

    const block = blocks[selectedBlockIndex];
    const config = blockDefinitions[block.type];

    let html = `
        <div class="settings-header">
            <h3><i class="fa-solid ${config.icon}"></i> ${config.label}</h3>
        </div>
        <div class="settings-form" id="settings-form">
    `;

    // Render form fields
    for (const [fieldName, fieldConfig] of Object.entries(config.fields)) {
        const value = block.data[fieldName] ?? fieldConfig.default ?? '';
        // Security: Escape user-controlled values to prevent XSS
        const safeFieldName = escapeHtml(fieldName);
        const safeValue = escapeHtml(value);
        const safeLabel = escapeHtml(fieldConfig.label);

        html += `<div class="form-group">`;
        html += `<label class="form-label">${safeLabel}</label>`;

        switch (fieldConfig.type) {
            case 'text':
                html += `<input type="text" class="form-input" data-field="${safeFieldName}" value="${safeValue}" onchange="updateBlockData('${safeFieldName}', this.value)">`;
                break;

            case 'textarea':
            case 'wysiwyg':
                html += `<textarea class="form-textarea" data-field="${safeFieldName}" onchange="updateBlockData('${safeFieldName}', this.value)" rows="${fieldConfig.rows || 4}">${safeValue}</textarea>`;
                break;

            case 'number':
                html += `<input type="number" class="form-input" data-field="${safeFieldName}" value="${safeValue}" min="${fieldConfig.min || 1}" max="${fieldConfig.max || 100}" onchange="updateBlockData('${safeFieldName}', parseInt(this.value))">`;
                break;

            case 'select':
                html += `<select class="form-select" data-field="${safeFieldName}" onchange="updateBlockData('${safeFieldName}', this.value)">`;
                for (const [optValue, optLabel] of Object.entries(fieldConfig.options)) {
                    const selected = value == optValue ? 'selected' : '';
                    html += `<option value="${escapeHtml(optValue)}" ${selected}>${escapeHtml(optLabel)}</option>`;
                }
                html += `</select>`;
                break;

            case 'checkbox':
                const checked = value ? 'checked' : '';
                html += `<div class="form-checkbox-group">
                    <input type="checkbox" class="form-checkbox" data-field="${safeFieldName}" ${checked} onchange="updateBlockData('${safeFieldName}', this.checked)">
                    <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">${safeLabel}</span>
                </div>`;
                break;

            case 'range':
                html += `<input type="range" class="form-input" data-field="${safeFieldName}" value="${safeValue}" min="${fieldConfig.min}" max="${fieldConfig.max}" step="${fieldConfig.step}" onchange="updateBlockData('${safeFieldName}', this.value)">`;
                html += `<div style="text-align: center; font-size: 0.75rem; color: rgba(255,255,255,0.5);">${safeValue}</div>`;
                break;
        }

        html += `</div>`;
    }

    html += `</div>`;
    panel.innerHTML = html;
}

// Update block data
function updateBlockData(field, value) {
    if (selectedBlockIndex !== null) {
        blocks[selectedBlockIndex].data[field] = value;
        renderCanvas();
        renderSettings(); // Re-render to update range values etc
    }
}

// Move block up
function moveBlockUp(index) {
    if (index > 0) {
        [blocks[index], blocks[index - 1]] = [blocks[index - 1], blocks[index]];
        if (selectedBlockIndex === index) {
            selectedBlockIndex = index - 1;
        }
        renderCanvas();
    }
}

// Move block down
function moveBlockDown(index) {
    if (index < blocks.length - 1) {
        [blocks[index], blocks[index + 1]] = [blocks[index + 1], blocks[index]];
        if (selectedBlockIndex === index) {
            selectedBlockIndex = index + 1;
        }
        renderCanvas();
    }
}

// Delete block
function deleteBlock(index) {
    if (confirm('Delete this block?')) {
        blocks.splice(index, 1);
        if (selectedBlockIndex === index) {
            selectedBlockIndex = null;
        } else if (selectedBlockIndex > index) {
            selectedBlockIndex--;
        }
        renderCanvas();
        renderSettings();
    }
}

// Clear all blocks
function clearAllBlocks() {
    if (confirm('Clear all blocks? This cannot be undone.')) {
        blocks = [];
        selectedBlockIndex = null;
        renderCanvas();
        renderSettings();
    }
}

// Save blocks
document.getElementById('save-btn').addEventListener('click', async () => {
    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const response = await fetch(`${basePath}/admin/api/pages/${pageId}/blocks`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ blocks })
        });

        const result = await response.json();

        if (result.success) {
            window.AdminToast.success('Saved', 'Page blocks saved successfully!');
        } else {
            window.AdminToast.error('Error', result.error || 'Failed to save blocks');
        }
    } catch (error) {
        window.AdminToast.error('Error', 'Network error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Page';
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('save-btn').click();
    }
});

// Settings tab switching
function switchSettingsTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');

    // Update panels
    document.querySelectorAll('.settings-panel-content').forEach(panel => {
        panel.classList.remove('active');
        panel.style.display = 'none';
    });

    const activePanel = document.getElementById(`settings-panel-${tab}`);
    activePanel.classList.add('active');
    activePanel.style.display = 'block';
}

// Update slug preview
document.getElementById('page-slug')?.addEventListener('input', (e) => {
    document.getElementById('slug-preview').textContent = e.target.value || 'slug';
});

// Toggle menu location visibility
document.getElementById('page-show-in-menu')?.addEventListener('change', (e) => {
    const locationGroup = document.getElementById('menu-location-group');
    locationGroup.style.display = e.target.checked ? 'block' : 'none';
});

// Save page settings
async function savePageSettings() {
    const btn = event.target;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const data = {
            title: document.getElementById('page-title').value,
            slug: document.getElementById('page-slug').value,
            is_published: document.getElementById('page-published').checked ? 1 : 0,
            show_in_menu: document.getElementById('page-show-in-menu').checked ? 1 : 0,
            menu_location: document.getElementById('page-menu-location').value
        };

        const response = await fetch(`${basePath}/admin/api/pages/${pageId}/settings`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            window.AdminToast.success('Saved', 'Page settings updated successfully!');

            // Update page title in header if AdminPageTitle exists
            const pageTitle = document.querySelector('.admin-page-subtitle');
            if (pageTitle) {
                pageTitle.textContent = 'Building: ' + data.title;
            }

            // Update preview link
            const previewLink = document.querySelector('a[href*="/page/"]');
            if (previewLink) {
                previewLink.href = `${basePath}/page/${data.slug}`;
            }
        } else {
            window.AdminToast.error('Error', result.error || 'Failed to save settings');
        }
    } catch (error) {
        window.AdminToast.error('Error', 'Network error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
