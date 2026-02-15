<?php
/**
 * Admin Page Builder - Gold Standard Edition
 * STANDALONE admin interface using admin-header.php and admin-footer.php
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Get TinyMCE API key from .env
$tinymceApiKey = 'no-api-key';
$envPath = dirname(__DIR__, 4) . '/.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'TINYMCE_API_KEY=') === 0) {
            $tinymceApiKey = trim(substr($line, 16), '"\'');
            break;
        }
    }
}

// Admin header configuration
$adminPageTitle = 'Page Builder';
$adminPageSubtitle = 'Pages';
$adminPageIcon = 'fa-file-lines';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- GrapesJS and Page Builder Dependencies -->
<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-preset-webpage"></script>
<link rel="stylesheet" href="https://unpkg.com/grapesjs-preset-webpage/dist/grapesjs-preset-webpage.min.css">
<!-- TinyMCE for Rich Text Editing -->
<script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<style>
    /* ========================================
       FULLSCREEN BUILDER MODE
       Override admin layout for fullscreen editor
       ======================================== */

    /* Make admin wrapper fullscreen */
    .admin-gold-wrapper {
        padding: 0 !important;
        height: 100vh !important;
        overflow: hidden !important;
    }

    /* Hide the admin header bar for fullscreen */
    .admin-header-bar {
        display: none !important;
    }

    /* Hide navigation */
    .admin-smart-nav {
        display: none !important;
    }

    /* Make content area fullscreen */
    .admin-gold-content {
        padding: 0 !important;
        margin: 0 !important;
        max-width: none !important;
        height: 100vh !important;
        overflow: hidden !important;
    }

    /* ========================================
       PAGE BUILDER STYLES
       ======================================== */

    * { box-sizing: border-box; }

    body, html {
        margin: 0;
        height: 100%;
        overflow: hidden;
        background: #1a1a2e;
        color: #e2e8f0;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .editor-row {
        display: flex;
        height: 100vh;
    }

    .editor-canvas {
        flex-grow: 1;
        position: relative;
    }

    #gjs {
        height: 100%;
        border: none;
    }

    .panel__right {
        width: 300px;
        background: #16213e;
        border-left: 1px solid #0f3460;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }

    .panel-top {
        padding: 12px 15px;
        border-bottom: 1px solid #0f3460;
        display: flex;
        align-items: center;
        gap: 8px;
        background: #1a1a2e;
        flex-wrap: wrap;
    }

    .back-link {
        color: #94a3b8;
        text-decoration: none;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: color 0.2s;
    }

    .back-link:hover { color: #e2e8f0; }

    .toolbar-spacer { flex: 1; }

    /* Toolbar Buttons - Gold Standard Style */
    .toolbar-btn {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(99, 102, 241, 0.2);
        color: rgba(255,255,255,0.7);
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .toolbar-btn:hover {
        background: rgba(255,255,255,0.1);
        border-color: rgba(99, 102, 241, 0.4);
        color: #fff;
        transform: translateY(-1px);
    }

    .toolbar-btn.primary {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border: 1px solid rgba(99, 102, 241, 0.5);
        color: white;
    }

    .toolbar-btn.primary:hover {
        box-shadow: 0 4px 16px rgba(99, 102, 241, 0.5);
        transform: translateY(-2px);
    }

    .toolbar-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
    }

        .unsaved-dot {
            width: 8px;
            height: 8px;
            background: #f59e0b;
            border-radius: 50%;
            display: none;
            animation: pulse 2s infinite;
        }

        .unsaved-dot.visible { display: block; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .autosave-status {
            font-size: 0.7rem;
            color: #64748b;
            width: 100%;
            text-align: right;
            margin-top: 5px;
        }

    /* Settings Box - Gold Standard Glassmorphism */
    .settings-box {
        border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    }

    .settings-box-header {
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
        transition: all 0.3s ease;
    }

    .settings-box-header:hover {
        background: rgba(99, 102, 241, 0.05);
    }

    .settings-box h4 {
        margin: 0;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: rgba(255,255,255,0.7);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .settings-box h4 i {
        font-size: 0.9rem;
        color: #6366f1;
    }

    .settings-box-toggle {
        color: rgba(255,255,255,0.4);
        font-size: 0.7rem;
        transition: transform 0.3s ease;
    }

    .settings-box.collapsed .settings-box-toggle {
        transform: rotate(-90deg);
    }

    .settings-box-content {
        padding: 0 16px 16px;
        overflow: hidden;
        transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.2s ease;
        max-height: 500px;
        opacity: 1;
    }

    .settings-box.collapsed .settings-box-content {
        max-height: 0;
        padding-top: 0;
        padding-bottom: 0;
        opacity: 0;
    }

    .settings-box label {
        display: block;
        font-size: 0.75rem;
        color: rgba(255,255,255,0.6);
        margin-bottom: 6px;
        font-weight: 500;
    }

    /* Gold Standard Form Inputs */
    .settings-box input[type="text"],
    .settings-box input[type="datetime-local"],
    .settings-box textarea {
        width: 100%;
        margin-bottom: 12px;
        padding: 10px 12px;
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        color: #fff;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

        .settings-box input:focus,
        .settings-box textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .settings-box textarea {
            resize: vertical;
            min-height: 60px;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #e2e8f0;
        }

        .checkbox-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #6366f1;
            cursor: pointer;
        }

        .version-badge {
            background: #0f3460;
            color: #94a3b8;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
        }

        #blocks {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }

        /* Expand/Collapse All button */
        .sidebar-actions {
            padding: 8px 16px;
            border-bottom: 1px solid #0f3460;
            display: flex;
            justify-content: flex-end;
        }

        .sidebar-actions button {
            background: transparent;
            border: 1px solid #1e3a5f;
            color: #64748b;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sidebar-actions button:hover {
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
        }

    /* Toast styles removed - using AdminToast from admin-footer.php */

        /* Mobile Warning */
        .mobile-warning {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999;
            padding: 40px 20px;
            text-align: center;
            color: white;
        }

        .mobile-warning-content { max-width: 400px; margin: 0 auto; }
        .mobile-warning-icon { font-size: 4rem; margin-bottom: 20px; }
        .mobile-warning h2 { margin: 0 0 15px; font-size: 1.5rem; }
        .mobile-warning p { color: #9ca3af; line-height: 1.6; margin-bottom: 30px; }

        .mobile-warning .btn-back {
            display: inline-block;
            background: #6366f1;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .mobile-warning .btn-continue {
            display: block;
            margin: 20px auto 0;
            background: transparent;
            border: 1px solid #555;
            color: #9ca3af;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        /* GrapesJS Theme */
        .gjs-one-bg { background-color: #16213e; }
        .gjs-two-color { color: #e2e8f0; }
        .gjs-three-bg { background-color: #0f3460; }
        .gjs-four-color, .gjs-four-color-h:hover { color: #6366f1; }
        .gjs-block { width: auto; height: auto; min-height: auto; }
        .gjs-blocks-c { padding: 5px; }
        .gjs-block-label { font-size: 11px; }

        /* TinyMCE inline editor styles */
        .tox-tinymce-inline {
            z-index: 10000 !important;
        }
        .tox .tox-toolbar-overlord {
            background-color: #1a1a2e !important;
        }
        .tox .tox-toolbar__primary {
            background: #16213e !important;
            border: 1px solid #0f3460 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .tox .tox-tbtn {
            color: #e2e8f0 !important;
        }
        .tox .tox-tbtn:hover {
            background: #0f3460 !important;
        }
        .tox .tox-tbtn--enabled {
            background: #6366f1 !important;
        }
        .tox .tox-tbtn svg {
            fill: #e2e8f0 !important;
        }
        .tox .tox-split-button__chevron svg {
            fill: #e2e8f0 !important;
        }

        /* Editor mode indicator */
        .editor-mode-badge {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        .editor-mode-badge i {
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <!-- Mobile Warning -->
    <div class="mobile-warning" id="mobile-warning">
        <div class="mobile-warning-content">
            <div class="mobile-warning-icon">💻</div>
            <h2>Desktop Recommended</h2>
            <p>The visual page builder works best on a desktop or laptop computer.</p>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/pages" class="btn-back">&larr; Back to Pages</a>
            <button onclick="document.getElementById('mobile-warning').style.display='none'" class="btn-continue">Continue Anyway</button>
        </div>
    </div>

    <!-- Editor Mode Badge -->
    <div class="editor-mode-badge">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
        GrapesJS + TinyMCE
    </div>

    <div class="editor-row">
        <div class="editor-canvas">
            <div id="gjs"><?= $page['content'] ?? '' ?></div>
        </div>
        <div class="panel__right">
            <div class="panel-top">
                <a href="<?= $basePath ?>/admin-legacy/pages" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <div class="unsaved-dot" id="unsaved-dot" title="Unsaved changes"></div>
                <div class="toolbar-spacer"></div>
                <a href="<?= $basePath ?>/admin-legacy/pages/preview/<?= $page['id'] ?>" target="_blank" class="toolbar-btn" title="Preview">
                    <i class="fa-solid fa-eye"></i>
                </a>
                <?php if (($versionCount ?? 0) > 0): ?>
                <a href="<?= $basePath ?>/admin-legacy/pages/versions/<?= $page['id'] ?>" class="toolbar-btn" title="Version History">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span class="version-badge"><?= $versionCount ?></span>
                </a>
                <?php endif; ?>
                <button class="toolbar-btn primary" id="btn-save" onclick="savePage()">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
                <div class="autosave-status" id="autosave-status"></div>
            </div>

            <div class="sidebar-actions">
                <button type="button" onclick="toggleAllSections()" id="toggleAllBtn">
                    <i class="fa-solid fa-compress-alt"></i> Collapse All
                </button>
            </div>

            <div class="settings-box" data-section="page">
                <div class="settings-box-header" onclick="toggleSection(this)">
                    <h4><i class="fa-solid fa-gear"></i> Page Settings</h4>
                    <i class="fa-solid fa-chevron-down settings-box-toggle"></i>
                </div>
                <div class="settings-box-content">
                    <label for="page-title">Title</label>
                    <input type="text" id="page-title" value="<?= htmlspecialchars($page['title']) ?>" placeholder="Page title">

                    <label for="page-slug">URL Slug</label>
                    <input type="text" id="page-slug" value="<?= htmlspecialchars($page['slug']) ?>" placeholder="page-url">

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="is-published" <?= ($page['is_published']) ? 'checked' : '' ?>>
                            <span>Published</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="settings-box collapsed" data-section="schedule">
                <div class="settings-box-header" onclick="toggleSection(this)">
                    <h4><i class="fa-solid fa-calendar"></i> Scheduling</h4>
                    <i class="fa-solid fa-chevron-down settings-box-toggle"></i>
                </div>
                <div class="settings-box-content">
                    <label for="publish-at">Publish Date/Time (optional)</label>
                    <input type="datetime-local" id="publish-at" value="<?= $page['publish_at'] ? date('Y-m-d\TH:i', strtotime($page['publish_at'])) : '' ?>">
                    <small style="color:#64748b; font-size:0.7rem;">Leave empty to publish immediately when checked above</small>
                </div>
            </div>

            <div class="settings-box" data-section="menu">
                <div class="settings-box-header" onclick="toggleSection(this)">
                    <h4><i class="fa-solid fa-bars"></i> Menu Settings</h4>
                    <i class="fa-solid fa-chevron-down settings-box-toggle"></i>
                </div>
                <div class="settings-box-content">
                    <div class="checkbox-group" style="margin-bottom: 12px;">
                        <label class="checkbox-label">
                            <input type="checkbox" id="show-in-menu" <?= !empty($page['show_in_menu']) ? 'checked' : '' ?>>
                            <span>Show in Navigation Menu</span>
                        </label>
                    </div>
                    <label for="menu-location">Menu Location</label>
                    <select id="menu-location" style="width:100%; padding:9px 11px; background:#0f3460; border:1px solid #1e3a5f; border-radius:6px; color:#e2e8f0; font-size:0.85rem;">
                        <option value="about" <?= ($page['menu_location'] ?? 'about') === 'about' ? 'selected' : '' ?>>About Dropdown</option>
                        <option value="main" <?= ($page['menu_location'] ?? '') === 'main' ? 'selected' : '' ?>>Main Navigation</option>
                        <option value="footer" <?= ($page['menu_location'] ?? '') === 'footer' ? 'selected' : '' ?>>Footer Only</option>
                    </select>
                    <small style="color:#64748b; font-size:0.7rem; display:block; margin-top:8px;">Choose where this page appears in site navigation</small>
                </div>
            </div>

            <div class="settings-box collapsed" data-section="seo">
                <div class="settings-box-header" onclick="toggleSection(this)">
                    <h4><i class="fa-solid fa-search"></i> SEO Settings</h4>
                    <i class="fa-solid fa-chevron-down settings-box-toggle"></i>
                </div>
                <div class="settings-box-content">
                    <label for="meta-title">Meta Title</label>
                    <input type="text" id="meta-title" value="<?= htmlspecialchars($seo['meta_title'] ?? '') ?>" placeholder="Custom browser tab title">

                    <label for="meta-description">Meta Description</label>
                    <textarea id="meta-description" placeholder="Description for search engines"><?= htmlspecialchars($seo['meta_description'] ?? '') ?></textarea>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="noindex" <?= !empty($seo['noindex']) ? 'checked' : '' ?>>
                            <span>Hide from search engines</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="settings-box" data-section="blocks">
                <div class="settings-box-header" onclick="toggleSection(this)">
                    <h4><i class="fa-solid fa-cubes"></i> Content Blocks</h4>
                    <i class="fa-solid fa-chevron-down settings-box-toggle"></i>
                </div>
                <div class="settings-box-content" style="padding: 0;">
                    <div id="blocks"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Section collapse toggle
        function toggleSection(header) {
            const box = header.closest('.settings-box');
            box.classList.toggle('collapsed');

            // Save state to localStorage
            const section = box.dataset.section;
            if (section) {
                const collapsed = JSON.parse(localStorage.getItem('pageBuilderCollapsed') || '{}');
                collapsed[section] = box.classList.contains('collapsed');
                localStorage.setItem('pageBuilderCollapsed', JSON.stringify(collapsed));
            }

            updateToggleAllButton();
        }

        // Toggle all sections
        function toggleAllSections() {
            const boxes = document.querySelectorAll('.settings-box[data-section]');
            const allCollapsed = Array.from(boxes).every(box => box.classList.contains('collapsed'));
            const collapsed = {};

            boxes.forEach(box => {
                if (allCollapsed) {
                    box.classList.remove('collapsed');
                } else {
                    box.classList.add('collapsed');
                }
                collapsed[box.dataset.section] = !allCollapsed;
            });

            localStorage.setItem('pageBuilderCollapsed', JSON.stringify(collapsed));
            updateToggleAllButton();
        }

        // Update toggle all button text
        function updateToggleAllButton() {
            const boxes = document.querySelectorAll('.settings-box[data-section]');
            const allCollapsed = Array.from(boxes).every(box => box.classList.contains('collapsed'));
            const btn = document.getElementById('toggleAllBtn');
            if (btn) {
                btn.innerHTML = allCollapsed
                    ? '<i class="fa-solid fa-expand-alt"></i> Expand All'
                    : '<i class="fa-solid fa-compress-alt"></i> Collapse All';
            }
        }

        // Restore collapsed states on load
        document.addEventListener('DOMContentLoaded', function() {
            const collapsed = JSON.parse(localStorage.getItem('pageBuilderCollapsed') || '{}');
            document.querySelectorAll('.settings-box[data-section]').forEach(box => {
                const section = box.dataset.section;
                if (collapsed[section] === true) {
                    box.classList.add('collapsed');
                } else if (collapsed[section] === false) {
                    box.classList.remove('collapsed');
                }
            });
            updateToggleAllButton();
        });

        const basePath = "<?= Nexus\Core\TenantContext::getBasePath() ?>";
        const pageId = <?= (int)$page['id'] ?>;
        let hasUnsavedChanges = false;
        let isSaving = false;
        let autosaveTimer = null;
        let lastAutosave = null;

        // Mobile warning
        if (window.innerWidth < 900) {
            document.getElementById('mobile-warning').style.display = 'block';
        }

        // Initialize GrapesJS with TinyMCE as Rich Text Editor
        const editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            fromElement: true,
            storageManager: false,
            plugins: ['gjs-preset-webpage'],
            pluginsOpts: {
                'gjs-preset-webpage': {
                    modalImportTitle: 'Import HTML',
                    modalImportLabel: '<div style="margin-bottom: 10px; font-size: 13px;">Paste your HTML/CSS code</div>',
                    modalImportContent: (editor) => editor.getHtml() + '<style>' + editor.getCss() + '</style>',
                }
            },
            assetManager: {
                upload: basePath + '/api/upload',
                uploadName: 'files',
                autoAdd: 1,
            },
            blockManager: { appendTo: '#blocks' },
            canvas: {
                styles: ['https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap']
            }
        });

        // Custom TinyMCE Rich Text Editor Plugin for GrapesJS
        // This replaces the default RTE with TinyMCE for better text editing
        editor.setCustomRte({
            enable(el, rte) {
                // el is the element being edited (the text component)
                // Create unique ID for this instance
                el.id = 'tinymce-rte-' + Date.now();

                // Wait for DOM to be ready
                setTimeout(() => {
                    // Remove any existing TinyMCE instance on this element
                    if (tinymce.get(el.id)) {
                        tinymce.get(el.id).remove();
                    }

                    tinymce.init({
                        target: el,
                        inline: true, // Important: inline mode for GrapesJS integration
                        menubar: false,
                        toolbar_mode: 'floating',
                        plugins: 'link lists emoticons code',
                        toolbar: 'bold italic underline strikethrough | forecolor backcolor | link | bullist numlist | emoticons | removeformat code',
                        // Inline-friendly settings
                        relative_urls: false,
                        remove_script_host: false,
                        convert_urls: false,
                        // Custom styling for inline mode
                        content_style: `
                            body {
                                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            }
                        `,
                        // Auto-focus when enabled
                        auto_focus: el.id,
                        // Notify GrapesJS of changes
                        setup: (ed) => {
                            ed.on('change keyup', () => {
                                markUnsaved();
                            });
                            ed.on('init', () => {
                                ed.focus();
                            });
                        }
                    });
                }, 50);

                return el; // Return the element for GrapesJS
            },

            disable(el, rte) {
                // Clean up TinyMCE instance when editing ends
                if (el.id && tinymce.get(el.id)) {
                    tinymce.get(el.id).remove();
                }
            },

            // Get the updated content
            getContent(el, rte) {
                const tinyInstance = tinymce.get(el.id);
                return tinyInstance ? tinyInstance.getContent() : el.innerHTML;
            }
        });

        // Track changes
        editor.on('change:changesCount', markUnsaved);

        ['page-title', 'page-slug', 'meta-title', 'meta-description', 'is-published', 'noindex', 'publish-at', 'show-in-menu', 'menu-location'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', markUnsaved);
                el.addEventListener('change', markUnsaved);
            }
        });

        function markUnsaved() {
            hasUnsavedChanges = true;
            document.getElementById('unsaved-dot').classList.add('visible');
            scheduleAutosave();
        }

        function markSaved(isAutosave = false) {
            if (!isAutosave) {
                hasUnsavedChanges = false;
                document.getElementById('unsaved-dot').classList.remove('visible');
            }
        }

        // Autosave every 60 seconds
        function scheduleAutosave() {
            if (autosaveTimer) clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(() => {
                if (hasUnsavedChanges && !isSaving) {
                    savePage(true);
                }
            }, 60000);
        }

        function updateAutosaveStatus(message) {
            const status = document.getElementById('autosave-status');
            status.textContent = message;
            setTimeout(() => { status.textContent = ''; }, 5000);
        }

        // Warn before leaving
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // Smart Blocks
        const bm = editor.BlockManager;

        // =====================================================
        // THEME-NEUTRAL BLOCKS
        // All blocks use CSS classes instead of inline colors
        // Colors are controlled by dynamic.php CSS
        // =====================================================

        bm.add('members-grid', {
            label: 'Members Grid',
            content: `<div class="smart-block nexus-smart-placeholder" data-smart-type="members-grid" data-limit="6">
                <div class="nexus-placeholder-icon">👥</div>
                <div class="nexus-placeholder-title">[Members Grid - 6 members]</div>
                <div class="nexus-placeholder-desc">Displays community members dynamically</div>
            </div>`,
            category: 'Smart Modules',
            attributes: { class: 'fa fa-users' }
        });

        bm.add('groups-grid', {
            label: 'Hubs Grid',
            content: `<div class="smart-block nexus-smart-placeholder" data-smart-type="groups-grid" data-limit="6">
                <div class="nexus-placeholder-icon">🏠</div>
                <div class="nexus-placeholder-title">[Hubs Grid - 6 hubs]</div>
                <div class="nexus-placeholder-desc">Displays community hubs dynamically</div>
            </div>`,
            category: 'Smart Modules',
            attributes: { class: 'fa fa-layer-group' }
        });

        bm.add('listings-grid', {
            label: 'Listings Grid',
            content: `<div class="smart-block nexus-smart-placeholder" data-smart-type="listings-grid" data-limit="6">
                <div class="nexus-placeholder-icon">📋</div>
                <div class="nexus-placeholder-title">[Listings Grid - 6 listings]</div>
                <div class="nexus-placeholder-desc">Displays recent listings dynamically</div>
            </div>`,
            category: 'Smart Modules',
            attributes: { class: 'fa fa-list' }
        });

        bm.add('events-grid', {
            label: 'Events Grid',
            content: `<div class="smart-block nexus-smart-placeholder" data-smart-type="events-grid" data-limit="6">
                <div class="nexus-placeholder-icon">📅</div>
                <div class="nexus-placeholder-title">[Events Grid - 6 events]</div>
                <div class="nexus-placeholder-desc">Displays upcoming events dynamically</div>
            </div>`,
            category: 'Smart Modules',
            attributes: { class: 'fa fa-calendar' }
        });

        // Rich Text Block - theme-neutral
        bm.add('rich-text-block', {
            label: 'Rich Text',
            content: `<div class="nexus-section nexus-text-block">
                <h2>Click to Edit</h2>
                <p>Double-click this text to open the TinyMCE rich text editor. You can format text, add links, create lists, insert emojis, and more.</p>
                <p>Use the floating toolbar for quick formatting options.</p>
            </div>`,
            category: 'Basic',
            attributes: { class: 'fa fa-align-left' }
        });

        // Article Block - theme-neutral
        bm.add('article-block', {
            label: 'Article',
            content: `<article class="nexus-article">
                <header class="nexus-article-header">
                    <h1>Article Title</h1>
                    <p class="nexus-meta">Published on January 1, 2025 • 5 min read</p>
                </header>
                <div class="nexus-article-content">
                    <p>Start writing your article here. Double-click to edit with TinyMCE for rich formatting options.</p>
                    <p>You can add <strong>bold text</strong>, <em>italics</em>, <a href="#">links</a>, and much more.</p>
                    <h2>Subheading</h2>
                    <p>Continue your content with well-structured sections...</p>
                </div>
            </article>`,
            category: 'Basic',
            attributes: { class: 'fa fa-newspaper' }
        });

        // Hero Section - Premium Holographic Glass
        bm.add('section-hero', {
            label: 'Hero Section',
            category: 'Sections',
            content: `<section class="nexus-section nexus-hero">
                <h1>Welcome to Our Platform</h1>
                <p class="nexus-hero-subtitle">Create stunning experiences with our premium glassmorphism design. Built for communities that want to stand out.</p>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="#" class="nexus-btn nexus-btn-primary">Get Started</a>
                    <a href="#" class="nexus-btn nexus-btn-secondary">Learn More</a>
                </div>
            </section>`
        });

        // Text Section - simple, theme-neutral
        bm.add('section-text', {
            label: 'Text Section',
            category: 'Sections',
            content: `<section class="nexus-section nexus-text-section">
                <h2>Section Title</h2>
                <p>Add your content here. This is a simple text section that adapts to any theme.</p>
            </section>`
        });

        // Card Grid - Premium Glass Cards
        bm.add('section-cards', {
            label: 'Card Grid',
            category: 'Sections',
            content: `<section class="nexus-section nexus-cards-section">
                <h2 class="nexus-section-title">Why Choose Us</h2>
                <p class="nexus-section-subtitle">Discover the features that make our platform stand out from the rest.</p>
                <div class="nexus-card-grid">
                    <div class="nexus-card">
                        <h3>🚀 Lightning Fast</h3>
                        <p>Optimized performance ensures your community loads instantly, keeping members engaged.</p>
                    </div>
                    <div class="nexus-card">
                        <h3>🔒 Secure by Design</h3>
                        <p>Enterprise-grade security protects your community data and member privacy.</p>
                    </div>
                    <div class="nexus-card">
                        <h3>✨ Beautiful Design</h3>
                        <p>Modern glassmorphism aesthetics that elevate your brand and impress members.</p>
                    </div>
                </div>
            </section>`
        });

        // CTA Section - Premium Holographic Panel
        bm.add('section-cta', {
            label: 'CTA Section',
            category: 'Sections',
            content: `<section class="nexus-section nexus-cta">
                <h2>Ready to Transform Your Community?</h2>
                <p>Join thousands of organizations already using our platform to build stronger, more engaged communities.</p>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="#" class="nexus-btn nexus-btn-primary">Start Free Trial</a>
                    <a href="#" class="nexus-btn nexus-btn-secondary">Schedule Demo</a>
                </div>
            </section>`
        });

        // Two Column - Image Left - theme-neutral
        bm.add('section-two-col-img-left', {
            label: 'Image Left',
            category: 'Sections',
            content: `<section class="nexus-section nexus-two-col">
                <div class="nexus-two-col-inner">
                    <div class="nexus-image-placeholder">
                        <span>Drop Image Here</span>
                    </div>
                    <div class="nexus-two-col-content">
                        <h2>Compelling Headline</h2>
                        <p>Add your description here. Explain the benefits and features of what you're showcasing.</p>
                        <a href="#" class="nexus-btn nexus-btn-primary">Learn More</a>
                    </div>
                </div>
            </section>`
        });

        // Two Column - Image Right - theme-neutral
        bm.add('section-two-col-img-right', {
            label: 'Image Right',
            category: 'Sections',
            content: `<section class="nexus-section nexus-two-col nexus-two-col-reverse">
                <div class="nexus-two-col-inner">
                    <div class="nexus-two-col-content">
                        <h2>Another Great Feature</h2>
                        <p>Describe the value proposition here. Keep it concise and benefit-focused.</p>
                        <ul class="nexus-check-list">
                            <li>Benefit point one</li>
                            <li>Benefit point two</li>
                            <li>Benefit point three</li>
                        </ul>
                    </div>
                    <div class="nexus-image-placeholder nexus-image-placeholder-alt">
                        <span>Drop Image Here</span>
                    </div>
                </div>
            </section>`
        });

        // Testimonials Section - theme-neutral
        bm.add('section-testimonials', {
            label: 'Testimonials',
            category: 'Sections',
            content: `<section class="nexus-section nexus-testimonials">
                <h2 class="nexus-section-title">What People Say</h2>
                <p class="nexus-section-subtitle">Hear from our community members</p>
                <div class="nexus-testimonial-grid">
                    <div class="nexus-testimonial-card">
                        <div class="nexus-stars">★★★★★</div>
                        <p class="nexus-quote">"This platform has completely transformed how our community connects. Highly recommended!"</p>
                        <div class="nexus-author">
                            <div class="nexus-avatar"></div>
                            <div class="nexus-author-info">
                                <strong>Sarah Johnson</strong>
                                <span>Community Member</span>
                            </div>
                        </div>
                    </div>
                    <div class="nexus-testimonial-card">
                        <div class="nexus-stars">★★★★★</div>
                        <p class="nexus-quote">"The time banking system is brilliant. I've learned so many new skills while helping others."</p>
                        <div class="nexus-author">
                            <div class="nexus-avatar nexus-avatar-green"></div>
                            <div class="nexus-author-info">
                                <strong>Michael Chen</strong>
                                <span>Active Volunteer</span>
                            </div>
                        </div>
                    </div>
                    <div class="nexus-testimonial-card">
                        <div class="nexus-stars">★★★★★</div>
                        <p class="nexus-quote">"Easy to use, great community, and the support team is incredibly helpful."</p>
                        <div class="nexus-author">
                            <div class="nexus-avatar nexus-avatar-yellow"></div>
                            <div class="nexus-author-info">
                                <strong>Emma Wilson</strong>
                                <span>Group Organizer</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // Stats/Numbers Section - Premium Gradient with Counter Animation
        bm.add('section-stats', {
            label: 'Stats',
            category: 'Sections',
            content: `<section class="nexus-section nexus-stats">
                <div class="nexus-stats-grid">
                    <div class="nexus-stat-item">
                        <div class="nexus-stat-number">10,000+</div>
                        <div class="nexus-stat-label">Active Members</div>
                    </div>
                    <div class="nexus-stat-item">
                        <div class="nexus-stat-number">50,000</div>
                        <div class="nexus-stat-label">Hours Exchanged</div>
                    </div>
                    <div class="nexus-stat-item">
                        <div class="nexus-stat-number">500+</div>
                        <div class="nexus-stat-label">Communities</div>
                    </div>
                    <div class="nexus-stat-item">
                        <div class="nexus-stat-number">99%</div>
                        <div class="nexus-stat-label">Satisfaction</div>
                    </div>
                </div>
            </section>`
        });

        // FAQ Section - theme-neutral
        bm.add('section-faq', {
            label: 'FAQ',
            category: 'Sections',
            content: `<section class="nexus-section nexus-faq">
                <h2 class="nexus-section-title">Frequently Asked Questions</h2>
                <p class="nexus-section-subtitle">Find answers to common questions</p>
                <div class="nexus-faq-list">
                    <div class="nexus-faq-item">
                        <h3>How do I get started?</h3>
                        <p>Simply create an account and complete your profile. You can then browse listings, join groups, and start connecting with your community.</p>
                    </div>
                    <div class="nexus-faq-item">
                        <h3>Is it free to use?</h3>
                        <p>Yes! Basic membership is completely free. We believe in making community connections accessible to everyone.</p>
                    </div>
                    <div class="nexus-faq-item">
                        <h3>How does time banking work?</h3>
                        <p>You earn time credits by helping others and spend them to receive help. One hour of service equals one time credit, regardless of the type of service.</p>
                    </div>
                    <div class="nexus-faq-item">
                        <h3>Can I create my own group?</h3>
                        <p>Absolutely! Any member can create a group around shared interests, neighborhoods, or causes. It's a great way to build community.</p>
                    </div>
                </div>
            </section>`
        });

        // Team Section - theme-neutral
        bm.add('section-team', {
            label: 'Team Grid',
            category: 'Sections',
            content: `<section class="nexus-section nexus-team">
                <h2 class="nexus-section-title">Meet Our Team</h2>
                <p class="nexus-section-subtitle">The people behind the platform</p>
                <div class="nexus-team-grid">
                    <div class="nexus-team-member">
                        <div class="nexus-team-avatar"></div>
                        <h3>John Smith</h3>
                        <p class="nexus-team-role">Founder & CEO</p>
                        <p class="nexus-team-bio">Passionate about building communities</p>
                    </div>
                    <div class="nexus-team-member">
                        <div class="nexus-team-avatar nexus-avatar-green"></div>
                        <h3>Jane Doe</h3>
                        <p class="nexus-team-role">Community Manager</p>
                        <p class="nexus-team-bio">Connecting people every day</p>
                    </div>
                    <div class="nexus-team-member">
                        <div class="nexus-team-avatar nexus-avatar-yellow"></div>
                        <h3>Alex Brown</h3>
                        <p class="nexus-team-role">Lead Developer</p>
                        <p class="nexus-team-bio">Making the magic happen</p>
                    </div>
                    <div class="nexus-team-member">
                        <div class="nexus-team-avatar nexus-avatar-pink"></div>
                        <h3>Lisa Park</h3>
                        <p class="nexus-team-role">Support Lead</p>
                        <p class="nexus-team-bio">Always here to help</p>
                    </div>
                </div>
            </section>`
        });

        // Contact Section - theme-neutral
        bm.add('section-contact', {
            label: 'Contact',
            category: 'Sections',
            content: `<section class="nexus-section nexus-contact">
                <h2 class="nexus-section-title">Get In Touch</h2>
                <p class="nexus-section-subtitle">Have questions? We'd love to hear from you.</p>
                <form class="nexus-contact-form">
                    <div class="nexus-form-row">
                        <input type="text" placeholder="Your Name" class="nexus-input">
                        <input type="email" placeholder="Your Email" class="nexus-input">
                    </div>
                    <textarea placeholder="Your Message" rows="5" class="nexus-textarea"></textarea>
                    <button type="submit" class="nexus-btn nexus-btn-primary nexus-btn-full">Send Message</button>
                </form>
            </section>`
        });

        // Newsletter Signup - Premium Holographic Glass
        bm.add('section-newsletter', {
            label: 'Newsletter',
            category: 'Sections',
            content: `<section class="nexus-section nexus-newsletter">
                <h2>Join Our Newsletter</h2>
                <p>Get the latest updates, tips, and exclusive content delivered straight to your inbox. No spam, ever.</p>
                <form class="nexus-newsletter-form">
                    <input type="email" placeholder="Enter your email address" class="nexus-input">
                    <button type="submit" class="nexus-btn nexus-btn-primary">Subscribe Now</button>
                </form>
            </section>`
        });

        // Video Embed Section - theme-neutral
        bm.add('section-video', {
            label: 'Video',
            category: 'Media',
            content: `<section class="nexus-section nexus-video">
                <h2 class="nexus-section-title">Watch How It Works</h2>
                <p class="nexus-section-subtitle">A quick overview of our platform</p>
                <div class="nexus-video-container">
                    <div class="nexus-video-placeholder">
                        <div class="nexus-play-btn">▶</div>
                        <p>Replace with YouTube/Vimeo embed</p>
                    </div>
                </div>
            </section>`
        });

        // Image Gallery - theme-neutral
        bm.add('section-gallery', {
            label: 'Gallery',
            category: 'Media',
            content: `<section class="nexus-section nexus-gallery">
                <h2 class="nexus-section-title">Gallery</h2>
                <div class="nexus-gallery-grid">
                    <div class="nexus-gallery-item"></div>
                    <div class="nexus-gallery-item nexus-gallery-green"></div>
                    <div class="nexus-gallery-item nexus-gallery-yellow"></div>
                    <div class="nexus-gallery-item nexus-gallery-pink"></div>
                    <div class="nexus-gallery-item nexus-gallery-blue"></div>
                    <div class="nexus-gallery-item nexus-gallery-purple"></div>
                </div>
            </section>`
        });

        // Divider - theme-neutral
        bm.add('divider', {
            label: 'Divider',
            category: 'Basic',
            content: `<hr class="nexus-divider">`,
            attributes: { class: 'fa fa-minus' }
        });

        // Spacer
        bm.add('spacer', {
            label: 'Spacer',
            category: 'Basic',
            content: `<div class="nexus-spacer"></div>`,
            attributes: { class: 'fa fa-arrows-alt-v' }
        });

        // Premium Feature Showcase Block
        bm.add('section-feature-showcase', {
            label: 'Feature Showcase',
            category: 'Sections',
            content: `<section class="nexus-section" style="max-width: 1200px; margin: 0 auto;">
                <h2 class="nexus-section-title">Everything You Need</h2>
                <p class="nexus-section-subtitle">Powerful features designed to help your community thrive and grow together.</p>
                <div class="nexus-card-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                    <div class="nexus-card" style="text-align: center;">
                        <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">💬</div>
                        <h3>Discussion Forums</h3>
                        <p>Engage your community with threaded discussions and real-time conversations.</p>
                    </div>
                    <div class="nexus-card" style="text-align: center;">
                        <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">📅</div>
                        <h3>Event Management</h3>
                        <p>Create, manage, and promote events with built-in RSVP and reminders.</p>
                    </div>
                    <div class="nexus-card" style="text-align: center;">
                        <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">👥</div>
                        <h3>Member Profiles</h3>
                        <p>Rich member profiles with skills, interests, and activity history.</p>
                    </div>
                    <div class="nexus-card" style="text-align: center;">
                        <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: linear-gradient(135deg, rgba(244, 114, 182, 0.1), rgba(236, 72, 153, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">📊</div>
                        <h3>Analytics Dashboard</h3>
                        <p>Track engagement, growth, and community health with detailed insights.</p>
                    </div>
                    <div class="nexus-card" style="text-align: center;">
                        <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(96, 165, 250, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">🔔</div>
                        <h3>Smart Notifications</h3>
                        <p>Keep members engaged with intelligent, personalized notifications.</p>
                    </div>
                    <div class="nexus-card" style="text-align: center;">
                        <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(192, 132, 252, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">🎨</div>
                        <h3>Custom Branding</h3>
                        <p>Make it yours with custom colors, logos, and styling options.</p>
                    </div>
                </div>
            </section>`
        });

        // Premium About/Mission Block
        bm.add('section-about-mission', {
            label: 'About Mission',
            category: 'Sections',
            content: `<section class="nexus-section nexus-text-block" style="max-width: 900px; margin: 0 auto;">
                <h2 style="text-align: center;">Our Mission</h2>
                <p style="text-align: center; font-size: 1.2rem; line-height: 1.9;">We believe that strong communities are the foundation of a better world. Our platform empowers organizations to build meaningful connections, facilitate collaboration, and create lasting impact.</p>
                <p style="text-align: center;">Founded in 2020, we've helped thousands of communities grow and thrive. From local neighborhood groups to global organizations, our tools adapt to your unique needs while maintaining the personal touch that makes communities special.</p>
                <div style="display: flex; justify-content: center; gap: 16px; margin-top: 32px; flex-wrap: wrap;">
                    <a href="#" class="nexus-btn nexus-btn-primary">Learn Our Story</a>
                    <a href="#" class="nexus-btn nexus-btn-secondary">Meet the Team</a>
                </div>
            </section>`
        });

        // Premium Pricing Preview Block
        bm.add('section-pricing-preview', {
            label: 'Pricing Preview',
            category: 'Sections',
            content: `<section class="nexus-section" style="max-width: 1000px; margin: 0 auto;">
                <h2 class="nexus-section-title">Simple, Transparent Pricing</h2>
                <p class="nexus-section-subtitle">Start free and scale as you grow. No hidden fees, no surprises.</p>
                <div class="nexus-card-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 28px;">
                    <div class="nexus-card" style="text-align: center; padding: 40px 32px;">
                        <div style="font-size: 0.9rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">Starter</div>
                        <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 8px;">Free</div>
                        <div style="color: #64748b; margin-bottom: 24px;">Perfect for getting started</div>
                        <ul class="nexus-check-list" style="text-align: left; margin-bottom: 24px;">
                            <li>Up to 100 members</li>
                            <li>Basic features</li>
                            <li>Community support</li>
                        </ul>
                        <a href="#" class="nexus-btn nexus-btn-secondary" style="width: 100%;">Get Started</a>
                    </div>
                    <div class="nexus-card" style="text-align: center; padding: 40px 32px; border: 2px solid rgba(99, 102, 241, 0.3);">
                        <div style="font-size: 0.9rem; color: #6366f1; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">Popular</div>
                        <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 8px;">$29<span style="font-size: 1rem; color: #64748b;">/mo</span></div>
                        <div style="color: #64748b; margin-bottom: 24px;">For growing communities</div>
                        <ul class="nexus-check-list" style="text-align: left; margin-bottom: 24px;">
                            <li>Unlimited members</li>
                            <li>All premium features</li>
                            <li>Priority support</li>
                            <li>Custom branding</li>
                        </ul>
                        <a href="#" class="nexus-btn nexus-btn-primary" style="width: 100%;">Start Free Trial</a>
                    </div>
                    <div class="nexus-card" style="text-align: center; padding: 40px 32px;">
                        <div style="font-size: 0.9rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">Enterprise</div>
                        <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 8px;">Custom</div>
                        <div style="color: #64748b; margin-bottom: 24px;">For large organizations</div>
                        <ul class="nexus-check-list" style="text-align: left; margin-bottom: 24px;">
                            <li>Everything in Pro</li>
                            <li>Dedicated support</li>
                            <li>Custom integrations</li>
                            <li>SLA guarantee</li>
                        </ul>
                        <a href="#" class="nexus-btn nexus-btn-secondary" style="width: 100%;">Contact Sales</a>
                    </div>
                </div>
            </section>`
        });

        // ============================================
        // WORLD-CLASS MOBILE-NATIVE BLOCKS
        // Premium animations, micro-interactions, stunning visuals
        // ============================================

        // 1. PREMIUM Navigation Drawer - Apple/Google Material 3 Level Quality
        bm.add('mobile-nav-drawer', {
            label: '✨ Nav Drawer',
            category: 'Mobile',
            attributes: { class: 'fa fa-bars' },
            content: `<div class="nexus-mobile-only nexus-premium-drawer-wrapper">
                <button class="nexus-premium-trigger-btn" onclick="document.getElementById('premium-drawer').classList.add('open'); document.getElementById('premium-drawer-scrim').classList.add('open');">
                    <span class="nexus-trigger-icon"><i class="fas fa-bars"></i></span>
                    <span class="nexus-trigger-text">Menu</span>
                    <span class="nexus-trigger-ripple"></span>
                </button>

                <div id="premium-drawer-scrim" class="nexus-drawer-scrim" onclick="this.classList.remove('open'); document.getElementById('premium-drawer').classList.remove('open');"></div>

                <nav id="premium-drawer" class="nexus-premium-drawer">
                    <!-- Decorative blur orbs -->
                    <div class="nexus-drawer-orb nexus-drawer-orb-1"></div>
                    <div class="nexus-drawer-orb nexus-drawer-orb-2"></div>

                    <!-- Premium Header with Status -->
                    <div class="nexus-drawer-header-premium">
                        <div class="nexus-drawer-user-card">
                            <div class="nexus-drawer-avatar-ring">
                                <div class="nexus-drawer-avatar">
                                    <span>JD</span>
                                    <div class="nexus-drawer-avatar-glow"></div>
                                </div>
                                <div class="nexus-drawer-status-dot"></div>
                            </div>
                            <div class="nexus-drawer-user-info">
                                <div class="nexus-drawer-user-name">John Doe</div>
                                <div class="nexus-drawer-user-tier">
                                    <span class="nexus-tier-badge"><i class="fas fa-crown"></i> Premium</span>
                                    <span class="nexus-tier-xp">2,450 XP</span>
                                </div>
                            </div>
                            <button class="nexus-drawer-close-premium" onclick="this.closest('.nexus-premium-drawer').classList.remove('open'); document.getElementById('premium-drawer-scrim').classList.remove('open');">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Quick Stats Row -->
                        <div class="nexus-drawer-quick-stats">
                            <div class="nexus-quick-stat">
                                <div class="nexus-stat-value">128</div>
                                <div class="nexus-stat-label">Following</div>
                            </div>
                            <div class="nexus-quick-stat-divider"></div>
                            <div class="nexus-quick-stat">
                                <div class="nexus-stat-value">2.4K</div>
                                <div class="nexus-stat-label">Followers</div>
                            </div>
                            <div class="nexus-quick-stat-divider"></div>
                            <div class="nexus-quick-stat">
                                <div class="nexus-stat-value">89</div>
                                <div class="nexus-stat-label">Posts</div>
                            </div>
                        </div>
                    </div>

                    <!-- Scrollable Navigation -->
                    <div class="nexus-drawer-scroll-area">
                        <div class="nexus-drawer-section">
                            <div class="nexus-drawer-section-header">
                                <span>Main</span>
                                <div class="nexus-section-line"></div>
                            </div>
                            <a href="#" class="nexus-drawer-nav-item active" data-index="0">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-home"></i></div>
                                <span class="nexus-nav-label">Home</span>
                                <div class="nexus-nav-active-indicator"></div>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="1">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-compass"></i></div>
                                <span class="nexus-nav-label">Discover</span>
                                <span class="nexus-nav-tag nexus-tag-new">New</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="2">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-calendar-alt"></i></div>
                                <span class="nexus-nav-label">Events</span>
                                <span class="nexus-nav-counter">5</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="3">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-users"></i></div>
                                <span class="nexus-nav-label">Communities</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="4">
                                <div class="nexus-nav-icon-wrap nexus-nav-icon-messages"><i class="fas fa-comments"></i></div>
                                <span class="nexus-nav-label">Messages</span>
                                <span class="nexus-nav-counter nexus-counter-urgent">12</span>
                            </a>
                        </div>

                        <div class="nexus-drawer-section">
                            <div class="nexus-drawer-section-header">
                                <span>Your Space</span>
                                <div class="nexus-section-line"></div>
                            </div>
                            <a href="#" class="nexus-drawer-nav-item" data-index="5">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-user-circle"></i></div>
                                <span class="nexus-nav-label">Profile</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="6">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-bookmark"></i></div>
                                <span class="nexus-nav-label">Saved</span>
                                <span class="nexus-nav-counter">23</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="7">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-history"></i></div>
                                <span class="nexus-nav-label">Activity</span>
                            </a>
                        </div>

                        <div class="nexus-drawer-section">
                            <div class="nexus-drawer-section-header">
                                <span>Settings</span>
                                <div class="nexus-section-line"></div>
                            </div>
                            <a href="#" class="nexus-drawer-nav-item" data-index="8">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-cog"></i></div>
                                <span class="nexus-nav-label">Preferences</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="9">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-shield-alt"></i></div>
                                <span class="nexus-nav-label">Privacy</span>
                            </a>
                            <a href="#" class="nexus-drawer-nav-item" data-index="10">
                                <div class="nexus-nav-icon-wrap"><i class="fas fa-question-circle"></i></div>
                                <span class="nexus-nav-label">Help Center</span>
                            </a>
                        </div>

                        <!-- Promo Card -->
                        <div class="nexus-drawer-promo-card">
                            <div class="nexus-promo-icon">🚀</div>
                            <div class="nexus-promo-content">
                                <div class="nexus-promo-title">Upgrade to Pro</div>
                                <div class="nexus-promo-desc">Unlock all premium features</div>
                            </div>
                            <i class="fas fa-chevron-right nexus-promo-arrow"></i>
                        </div>
                    </div>

                    <!-- Footer with Sign Out -->
                    <div class="nexus-drawer-footer-premium">
                        <a href="#" class="nexus-drawer-signout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sign Out</span>
                        </a>
                        <div class="nexus-drawer-version">v3.0.0</div>
                    </div>
                </nav>
            </div>`
        });

        // 2. PREMIUM Bottom Sheet - iOS/Android Native Quality
        bm.add('mobile-bottom-sheet', {
            label: '✨ Bottom Sheet',
            category: 'Mobile',
            attributes: { class: 'fa fa-window-maximize fa-rotate-180' },
            content: `<div class="nexus-mobile-only nexus-premium-sheet-wrapper">
                <button class="nexus-premium-trigger-btn" onclick="document.getElementById('premium-sheet').classList.add('open'); document.getElementById('premium-sheet-backdrop').classList.add('open');">
                    <span class="nexus-trigger-icon"><i class="fas fa-share-alt"></i></span>
                    <span class="nexus-trigger-text">Share</span>
                </button>

                <div id="premium-sheet-backdrop" class="nexus-sheet-backdrop" onclick="this.classList.remove('open'); document.getElementById('premium-sheet').classList.remove('open');"></div>

                <div id="premium-sheet" class="nexus-premium-sheet">
                    <!-- Drag Handle with Gesture Hint -->
                    <div class="nexus-sheet-drag-zone">
                        <div class="nexus-sheet-handle">
                            <div class="nexus-sheet-handle-bar"></div>
                        </div>
                    </div>

                    <!-- Sheet Header -->
                    <div class="nexus-sheet-header">
                        <div class="nexus-sheet-title-row">
                            <h3 class="nexus-sheet-title">Share Content</h3>
                            <button class="nexus-sheet-close-btn" onclick="this.closest('.nexus-premium-sheet').classList.remove('open'); document.getElementById('premium-sheet-backdrop').classList.remove('open');">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <p class="nexus-sheet-subtitle">Choose where to share this amazing content</p>
                    </div>

                    <!-- Preview Card -->
                    <div class="nexus-sheet-preview-card">
                        <div class="nexus-preview-image">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="nexus-preview-info">
                            <div class="nexus-preview-title">Amazing Content Title</div>
                            <div class="nexus-preview-url">nexus.community/post/123</div>
                        </div>
                    </div>

                    <!-- Share Grid - Horizontal Scroll -->
                    <div class="nexus-sheet-section">
                        <div class="nexus-sheet-section-label">Share via</div>
                        <div class="nexus-share-scroll-container">
                            <div class="nexus-share-grid-premium">
                                <button class="nexus-share-item-premium" data-app="messages">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #34c759, #30d158);">
                                        <i class="fas fa-comment-dots"></i>
                                    </div>
                                    <span>Messages</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="airdrop">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #007aff, #5856d6);">
                                        <i class="fas fa-broadcast-tower"></i>
                                    </div>
                                    <span>AirDrop</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="whatsapp">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #25d366, #128c7e);">
                                        <i class="fab fa-whatsapp"></i>
                                    </div>
                                    <span>WhatsApp</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="instagram">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);">
                                        <i class="fab fa-instagram"></i>
                                    </div>
                                    <span>Instagram</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="twitter">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #1da1f2, #0d8bd9);">
                                        <i class="fab fa-twitter"></i>
                                    </div>
                                    <span>Twitter</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="facebook">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #1877f2, #0d65d9);">
                                        <i class="fab fa-facebook-f"></i>
                                    </div>
                                    <span>Facebook</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="telegram">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #0088cc, #229ed9);">
                                        <i class="fab fa-telegram-plane"></i>
                                    </div>
                                    <span>Telegram</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="linkedin">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #0077b5, #00669c);">
                                        <i class="fab fa-linkedin-in"></i>
                                    </div>
                                    <span>LinkedIn</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="email">
                                    <div class="nexus-share-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <span>Email</span>
                                </button>
                                <button class="nexus-share-item-premium" data-app="more">
                                    <div class="nexus-share-icon" style="background: var(--glass-bg); border: 2px dashed var(--glass-border);">
                                        <i class="fas fa-ellipsis-h" style="color: var(--glass-text-muted);"></i>
                                    </div>
                                    <span>More</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Copy Link Section -->
                    <div class="nexus-sheet-section">
                        <div class="nexus-sheet-section-label">Or copy link</div>
                        <div class="nexus-copy-link-box">
                            <i class="fas fa-link"></i>
                            <input type="text" value="nexus.community/p/abc123" readonly class="nexus-copy-input">
                            <button class="nexus-copy-btn">
                                <span class="nexus-copy-text">Copy</span>
                                <span class="nexus-copy-done"><i class="fas fa-check"></i></span>
                            </button>
                        </div>
                    </div>

                    <!-- Actions Grid -->
                    <div class="nexus-sheet-section">
                        <div class="nexus-sheet-section-label">More actions</div>
                        <div class="nexus-sheet-actions-grid">
                            <button class="nexus-sheet-action-item">
                                <div class="nexus-action-icon"><i class="fas fa-bookmark"></i></div>
                                <span>Save</span>
                            </button>
                            <button class="nexus-sheet-action-item">
                                <div class="nexus-action-icon"><i class="fas fa-qrcode"></i></div>
                                <span>QR Code</span>
                            </button>
                            <button class="nexus-sheet-action-item">
                                <div class="nexus-action-icon"><i class="fas fa-print"></i></div>
                                <span>Print</span>
                            </button>
                            <button class="nexus-sheet-action-item nexus-action-danger">
                                <div class="nexus-action-icon"><i class="fas fa-flag"></i></div>
                                <span>Report</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cancel Button -->
                    <div class="nexus-sheet-footer">
                        <button class="nexus-sheet-cancel-btn" onclick="this.closest('.nexus-premium-sheet').classList.remove('open'); document.getElementById('premium-sheet-backdrop').classList.remove('open');">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>`
        });

        // 3. PREMIUM Tab Bar - Instagram/TikTok Level Quality
        bm.add('mobile-tab-bar', {
            label: '✨ Tab Bar',
            category: 'Mobile',
            attributes: { class: 'fa fa-th-large' },
            content: `<nav role="navigation" aria-label="Main navigation" class="nexus-premium-tab-bar nexus-mobile-only">
                <!-- Background blur and gradient -->
                <div class="nexus-tab-bar-bg"></div>

                <!-- Active indicator that slides -->
                <div class="nexus-tab-indicator" style="--tab-index: 0;"></div>

                <a href="#" class="nexus-premium-tab active" data-index="0">
                    <div class="nexus-tab-icon-container">
                        <i class="fas fa-home nexus-tab-icon"></i>
                        <i class="fas fa-home nexus-tab-icon-filled"></i>
                    </div>
                    <span class="nexus-tab-label">Home</span>
                </a>

                <a href="#" class="nexus-premium-tab" data-index="1">
                    <div class="nexus-tab-icon-container">
                        <i class="fas fa-compass nexus-tab-icon"></i>
                        <i class="fas fa-compass nexus-tab-icon-filled"></i>
                    </div>
                    <span class="nexus-tab-label">Discover</span>
                </a>

                <a href="#" class="nexus-premium-tab nexus-tab-center" data-index="2">
                    <div class="nexus-tab-center-btn">
                        <div class="nexus-center-btn-inner">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="nexus-center-btn-glow"></div>
                        <div class="nexus-center-btn-pulse"></div>
                    </div>
                </a>

                <a href="#" class="nexus-premium-tab" data-index="3">
                    <div class="nexus-tab-icon-container">
                        <i class="fas fa-bell nexus-tab-icon"></i>
                        <i class="fas fa-bell nexus-tab-icon-filled"></i>
                        <span class="nexus-tab-notification">
                            <span class="nexus-notif-count">9</span>
                            <span class="nexus-notif-ping"></span>
                        </span>
                    </div>
                    <span class="nexus-tab-label">Alerts</span>
                </a>

                <a href="#" class="nexus-premium-tab" data-index="4">
                    <div class="nexus-tab-icon-container">
                        <div class="nexus-tab-avatar">
                            <span>JD</span>
                            <div class="nexus-avatar-ring"></div>
                        </div>
                        <span class="nexus-tab-status-dot"></span>
                    </div>
                    <span class="nexus-tab-label">Profile</span>
                </a>
            </nav>`
        });

        // 4. PREMIUM FAB - Apple/Material Design 3 Level with Explosive Animation
        bm.add('mobile-fab', {
            label: '✨ FAB Button',
            category: 'Mobile',
            attributes: { class: 'fa fa-plus-circle' },
            content: `<div class="nexus-premium-fab-container nexus-mobile-only" id="premium-fab">
                <!-- Backdrop blur when open -->
                <div class="nexus-fab-backdrop"></div>

                <!-- Action Items with Stagger Animation -->
                <div class="nexus-fab-menu">
                    <div class="nexus-fab-item" data-index="0">
                        <span class="nexus-fab-tooltip">
                            <span class="nexus-tooltip-text">Write Post</span>
                            <span class="nexus-tooltip-kbd">P</span>
                        </span>
                        <button class="nexus-fab-action-btn-premium" style="--fab-color: #6366f1; --fab-glow: rgba(99, 102, 241, 0.4);">
                            <i class="fas fa-pen-fancy"></i>
                        </button>
                    </div>
                    <div class="nexus-fab-item" data-index="1">
                        <span class="nexus-fab-tooltip">
                            <span class="nexus-tooltip-text">Photo</span>
                            <span class="nexus-tooltip-kbd">I</span>
                        </span>
                        <button class="nexus-fab-action-btn-premium" style="--fab-color: #10b981; --fab-glow: rgba(16, 185, 129, 0.4);">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    <div class="nexus-fab-item" data-index="2">
                        <span class="nexus-fab-tooltip">
                            <span class="nexus-tooltip-text">Video</span>
                            <span class="nexus-tooltip-kbd">V</span>
                        </span>
                        <button class="nexus-fab-action-btn-premium" style="--fab-color: #ef4444; --fab-glow: rgba(239, 68, 68, 0.4);">
                            <i class="fas fa-video"></i>
                        </button>
                    </div>
                    <div class="nexus-fab-item" data-index="3">
                        <span class="nexus-fab-tooltip">
                            <span class="nexus-tooltip-text">Event</span>
                            <span class="nexus-tooltip-kbd">E</span>
                        </span>
                        <button class="nexus-fab-action-btn-premium" style="--fab-color: #f59e0b; --fab-glow: rgba(245, 158, 11, 0.4);">
                            <i class="fas fa-calendar-star"></i>
                        </button>
                    </div>
                    <div class="nexus-fab-item" data-index="4">
                        <span class="nexus-fab-tooltip">
                            <span class="nexus-tooltip-text">Story</span>
                            <span class="nexus-tooltip-kbd">S</span>
                        </span>
                        <button class="nexus-fab-action-btn-premium" style="--fab-color: #ec4899; --fab-glow: rgba(236, 72, 153, 0.4);">
                            <i class="fas fa-circle-play"></i>
                        </button>
                    </div>
                    <div class="nexus-fab-item" data-index="5">
                        <span class="nexus-fab-tooltip">
                            <span class="nexus-tooltip-text">Poll</span>
                            <span class="nexus-tooltip-kbd">L</span>
                        </span>
                        <button class="nexus-fab-action-btn-premium" style="--fab-color: #8b5cf6; --fab-glow: rgba(139, 92, 246, 0.4);">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>

                <!-- Main FAB Button -->
                <button class="nexus-fab-main" onclick="this.parentElement.classList.toggle('open');">
                    <div class="nexus-fab-icon">
                        <i class="fas fa-plus nexus-fab-plus"></i>
                        <i class="fas fa-times nexus-fab-close"></i>
                    </div>
                    <div class="nexus-fab-ripple"></div>
                    <div class="nexus-fab-glow"></div>
                </button>
            </div>`
        });

        // 5. PREMIUM Stories Rail - Instagram/Snapchat Level Quality
        bm.add('mobile-stories-rail', {
            label: '✨ Stories Rail',
            category: 'Mobile',
            attributes: { class: 'fa fa-circle-user' },
            content: `<div class="nexus-premium-stories nexus-mobile-only">
                <div class="nexus-stories-scroll">
                    <!-- Your Story (Add) -->
                    <div class="nexus-story-premium nexus-story-yours">
                        <div class="nexus-story-ring-container nexus-story-add-ring">
                            <div class="nexus-story-avatar-premium">
                                <span>JD</span>
                            </div>
                            <div class="nexus-story-add-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                        </div>
                        <span class="nexus-story-username">Your Story</span>
                    </div>

                    <!-- Live Story -->
                    <div class="nexus-story-premium nexus-story-live-premium">
                        <div class="nexus-story-ring-container nexus-ring-live">
                            <div class="nexus-live-pulse-ring"></div>
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #818cf8, #c084fc);">
                                <span>AL</span>
                            </div>
                        </div>
                        <div class="nexus-live-badge-premium">
                            <span class="nexus-live-dot"></span>
                            LIVE
                        </div>
                        <span class="nexus-story-username">alex_live</span>
                    </div>

                    <!-- New Story (Unseen with animated ring) -->
                    <div class="nexus-story-premium nexus-story-new">
                        <div class="nexus-story-ring-container nexus-ring-gradient">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                <span>MJ</span>
                            </div>
                            <svg class="nexus-ring-svg" viewBox="0 0 100 100">
                                <circle class="nexus-ring-track" cx="50" cy="50" r="46" />
                                <circle class="nexus-ring-progress" cx="50" cy="50" r="46" stroke-dasharray="289" stroke-dashoffset="0" />
                            </svg>
                        </div>
                        <span class="nexus-story-username">maria_j</span>
                    </div>

                    <!-- Story with multiple segments -->
                    <div class="nexus-story-premium">
                        <div class="nexus-story-ring-container nexus-ring-segments" data-segments="5" data-viewed="2">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #34d399, #10b981);">
                                <span>DV</span>
                            </div>
                            <svg class="nexus-ring-svg" viewBox="0 0 100 100">
                                <circle class="nexus-segment nexus-segment-viewed" cx="50" cy="50" r="46" stroke-dasharray="52 6" stroke-dashoffset="0" />
                                <circle class="nexus-segment nexus-segment-viewed" cx="50" cy="50" r="46" stroke-dasharray="52 6" stroke-dashoffset="-58" />
                                <circle class="nexus-segment nexus-segment-new" cx="50" cy="50" r="46" stroke-dasharray="52 6" stroke-dashoffset="-116" />
                                <circle class="nexus-segment nexus-segment-new" cx="50" cy="50" r="46" stroke-dasharray="52 6" stroke-dashoffset="-174" />
                                <circle class="nexus-segment nexus-segment-new" cx="50" cy="50" r="46" stroke-dasharray="52 6" stroke-dashoffset="-232" />
                            </svg>
                        </div>
                        <span class="nexus-story-username">david_dev</span>
                        <span class="nexus-story-new-badge">3 new</span>
                    </div>

                    <!-- Unseen Story -->
                    <div class="nexus-story-premium nexus-story-new">
                        <div class="nexus-story-ring-container nexus-ring-gradient">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                                <span>SC</span>
                            </div>
                            <svg class="nexus-ring-svg" viewBox="0 0 100 100">
                                <circle class="nexus-ring-progress" cx="50" cy="50" r="46" />
                            </svg>
                        </div>
                        <span class="nexus-story-username">sophie_c</span>
                    </div>

                    <!-- Viewed Story -->
                    <div class="nexus-story-premium nexus-story-viewed">
                        <div class="nexus-story-ring-container nexus-ring-viewed">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #60a5fa, #3b82f6);">
                                <span>JM</span>
                            </div>
                        </div>
                        <span class="nexus-story-username">james_m</span>
                    </div>

                    <!-- More stories -->
                    <div class="nexus-story-premium nexus-story-new">
                        <div class="nexus-story-ring-container nexus-ring-gradient">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                                <span>EM</span>
                            </div>
                            <svg class="nexus-ring-svg" viewBox="0 0 100 100">
                                <circle class="nexus-ring-progress" cx="50" cy="50" r="46" />
                            </svg>
                        </div>
                        <span class="nexus-story-username">emma_w</span>
                    </div>

                    <div class="nexus-story-premium nexus-story-viewed">
                        <div class="nexus-story-ring-container nexus-ring-viewed">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #fb923c, #f97316);">
                                <span>OL</span>
                            </div>
                        </div>
                        <span class="nexus-story-username">oliver_k</span>
                    </div>

                    <div class="nexus-story-premium nexus-story-new">
                        <div class="nexus-story-ring-container nexus-ring-gradient">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #14b8a6, #2dd4bf);">
                                <span>MI</span>
                            </div>
                            <svg class="nexus-ring-svg" viewBox="0 0 100 100">
                                <circle class="nexus-ring-progress" cx="50" cy="50" r="46" />
                            </svg>
                        </div>
                        <span class="nexus-story-username">mia_chen</span>
                    </div>

                    <!-- Close Friends Story -->
                    <div class="nexus-story-premium nexus-story-close-friends">
                        <div class="nexus-story-ring-container nexus-ring-close-friends">
                            <div class="nexus-story-avatar-premium" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                                <span>BF</span>
                            </div>
                            <svg class="nexus-ring-svg" viewBox="0 0 100 100">
                                <circle class="nexus-ring-close" cx="50" cy="50" r="46" />
                            </svg>
                        </div>
                        <span class="nexus-close-friends-star">★</span>
                        <span class="nexus-story-username">bestie</span>
                    </div>
                </div>
            </div>`
        });

        // 6. Pull to Refresh Zone
        bm.add('mobile-pull-refresh', {
            label: 'Pull Refresh',
            category: 'Mobile',
            attributes: { class: 'fa fa-rotate' },
            content: `<div class="nexus-pull-refresh nexus-mobile-only" style="padding: 20px; background: var(--glass-bg); border-radius: var(--radius-lg); text-align: center;">
                <div class="nexus-pull-indicator">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <p style="color: var(--glass-text-muted); margin: 20px 0 0;">Pull down to refresh content</p>
                <p style="color: var(--glass-text-secondary); font-size: 0.9rem; margin: 8px 0 0;">This zone demonstrates the pull-to-refresh pattern</p>
            </div>`
        });

        // 7. PREMIUM Swipe Cards - Tinder/Bumble Level Quality
        bm.add('mobile-swipe-cards', {
            label: '✨ Swipe Cards',
            category: 'Mobile',
            attributes: { class: 'fa fa-layer-group' },
            content: `<div class="nexus-premium-swipe-section nexus-mobile-only">
                <div class="nexus-swipe-header">
                    <h2 class="nexus-swipe-title">Discover People</h2>
                    <div class="nexus-swipe-filters">
                        <button class="nexus-filter-chip active">All</button>
                        <button class="nexus-filter-chip">Nearby</button>
                        <button class="nexus-filter-chip">New</button>
                    </div>
                </div>

                <div class="nexus-swipe-deck">
                    <!-- Card 3 (Back) -->
                    <div class="nexus-premium-card" data-card="3" style="--card-index: 2;">
                        <div class="nexus-card-image-container">
                            <div class="nexus-card-gradient" style="--gradient-1: #8b5cf6; --gradient-2: #a78bfa;"></div>
                            <div class="nexus-card-avatar-large">OL</div>
                        </div>
                    </div>

                    <!-- Card 2 (Middle) -->
                    <div class="nexus-premium-card" data-card="2" style="--card-index: 1;">
                        <div class="nexus-card-image-container">
                            <div class="nexus-card-gradient" style="--gradient-1: #10b981; --gradient-2: #34d399;"></div>
                            <div class="nexus-card-avatar-large">MJ</div>
                            <div class="nexus-card-verified"><i class="fas fa-check"></i></div>
                        </div>
                    </div>

                    <!-- Card 1 (Front/Active) -->
                    <div class="nexus-premium-card nexus-card-active" data-card="1" style="--card-index: 0;">
                        <!-- Choice stamps -->
                        <div class="nexus-card-stamp nexus-stamp-like">LIKE</div>
                        <div class="nexus-card-stamp nexus-stamp-nope">NOPE</div>
                        <div class="nexus-card-stamp nexus-stamp-super">SUPER</div>

                        <div class="nexus-card-image-container">
                            <div class="nexus-card-gradient" style="--gradient-1: #6366f1; --gradient-2: #8b5cf6;"></div>
                            <div class="nexus-card-avatar-large">SC</div>
                            <div class="nexus-card-verified"><i class="fas fa-check"></i></div>
                            <div class="nexus-card-online-indicator"></div>

                            <!-- Carousel dots -->
                            <div class="nexus-card-carousel">
                                <div class="nexus-carousel-dot active"></div>
                                <div class="nexus-carousel-dot"></div>
                                <div class="nexus-carousel-dot"></div>
                            </div>
                        </div>

                        <div class="nexus-card-info">
                            <div class="nexus-card-main-info">
                                <h3 class="nexus-card-name">Sarah Chen <span class="nexus-card-age">28</span></h3>
                                <p class="nexus-card-tagline"><i class="fas fa-briefcase"></i> Product Designer at Figma</p>
                            </div>

                            <div class="nexus-card-details">
                                <div class="nexus-detail-row">
                                    <span class="nexus-detail-icon"><i class="fas fa-map-marker-alt"></i></span>
                                    <span>2 miles away</span>
                                </div>
                                <div class="nexus-detail-row">
                                    <span class="nexus-detail-icon"><i class="fas fa-graduation-cap"></i></span>
                                    <span>Stanford University</span>
                                </div>
                            </div>

                            <div class="nexus-card-interests">
                                <span class="nexus-interest-tag"><i class="fas fa-palette"></i> Design</span>
                                <span class="nexus-interest-tag"><i class="fas fa-music"></i> Music</span>
                                <span class="nexus-interest-tag"><i class="fas fa-hiking"></i> Hiking</span>
                                <span class="nexus-interest-tag"><i class="fas fa-coffee"></i> Coffee</span>
                            </div>

                            <div class="nexus-card-bio">
                                <p>Creative soul who believes good design can change the world. Looking to connect with inspiring people! 🎨✨</p>
                            </div>

                            <div class="nexus-card-match-badge">
                                <div class="nexus-match-score">
                                    <span class="nexus-score-value">87%</span>
                                    <span class="nexus-score-label">Match</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Premium Action Buttons -->
                <div class="nexus-swipe-actions-premium">
                    <button class="nexus-action-btn nexus-btn-rewind" title="Rewind">
                        <i class="fas fa-undo"></i>
                        <span class="nexus-action-hint">Undo</span>
                    </button>
                    <button class="nexus-action-btn nexus-btn-nope" title="Pass">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="nexus-action-btn nexus-btn-super" title="Super Like">
                        <i class="fas fa-star"></i>
                        <span class="nexus-super-glow"></span>
                    </button>
                    <button class="nexus-action-btn nexus-btn-like" title="Like">
                        <i class="fas fa-heart"></i>
                    </button>
                    <button class="nexus-action-btn nexus-btn-boost" title="Boost">
                        <i class="fas fa-bolt"></i>
                        <span class="nexus-action-hint">Boost</span>
                    </button>
                </div>

                <!-- Match notification (hidden by default) -->
                <div class="nexus-match-notification">
                    <div class="nexus-match-content">
                        <h2>It's a Match!</h2>
                        <p>You and Sarah liked each other</p>
                        <div class="nexus-match-avatars">
                            <div class="nexus-match-avatar">JD</div>
                            <div class="nexus-match-heart"><i class="fas fa-heart"></i></div>
                            <div class="nexus-match-avatar">SC</div>
                        </div>
                        <button class="nexus-btn nexus-btn-primary">Send Message</button>
                        <button class="nexus-btn nexus-btn-ghost">Keep Swiping</button>
                    </div>
                </div>
            </div>`
        });

        // 8. PREMIUM Fullscreen Modal - Cinematic App-Store Level Quality
        bm.add('mobile-fullscreen-overlay', {
            label: '✨ Fullscreen Modal',
            category: 'Mobile',
            attributes: { class: 'fa fa-expand' },
            content: `<div class="nexus-mobile-only nexus-premium-modal-wrapper">
                <button class="nexus-premium-trigger-btn" onclick="document.getElementById('premium-modal').classList.add('open');">
                    <span class="nexus-trigger-icon"><i class="fas fa-expand"></i></span>
                    <span class="nexus-trigger-text">Open Modal</span>
                </button>

                <div id="premium-modal" class="nexus-premium-modal">
                    <!-- Hero Section with Parallax -->
                    <div class="nexus-modal-hero">
                        <div class="nexus-hero-gradient"></div>
                        <div class="nexus-hero-pattern"></div>
                        <div class="nexus-hero-emoji">🎭</div>
                        <div class="nexus-hero-particles">
                            <div class="nexus-particle"></div>
                            <div class="nexus-particle"></div>
                            <div class="nexus-particle"></div>
                        </div>

                        <!-- Floating Header -->
                        <div class="nexus-modal-header-float">
                            <button class="nexus-modal-back-btn" onclick="this.closest('.nexus-premium-modal').classList.remove('open');">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="nexus-modal-header-actions">
                                <button class="nexus-modal-icon-btn">
                                    <i class="fas fa-heart"></i>
                                </button>
                                <button class="nexus-modal-icon-btn">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="nexus-modal-body">
                        <!-- Event Badge -->
                        <div class="nexus-modal-badges">
                            <span class="nexus-badge-premium nexus-badge-live">
                                <span class="nexus-live-dot"></span> Featured
                            </span>
                            <span class="nexus-badge-premium nexus-badge-spots">🔥 12 spots left</span>
                        </div>

                        <!-- Title Section -->
                        <div class="nexus-modal-title-section">
                            <h1 class="nexus-modal-title">Community Celebration Night</h1>
                            <p class="nexus-modal-subtitle">The biggest gathering of the year</p>
                        </div>

                        <!-- Host Card -->
                        <div class="nexus-modal-host-card">
                            <div class="nexus-host-avatar">
                                <span>NC</span>
                                <div class="nexus-host-verified"><i class="fas fa-check"></i></div>
                            </div>
                            <div class="nexus-host-info">
                                <span class="nexus-host-label">Hosted by</span>
                                <span class="nexus-host-name">Nexus Community</span>
                            </div>
                            <button class="nexus-follow-btn">Follow</button>
                        </div>

                        <!-- Info Cards -->
                        <div class="nexus-modal-info-grid">
                            <div class="nexus-info-card">
                                <div class="nexus-info-icon" style="--icon-color: #6366f1;">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="nexus-info-content">
                                    <span class="nexus-info-label">Date & Time</span>
                                    <span class="nexus-info-value">Sat, Mar 15 • 7:00 PM</span>
                                </div>
                                <button class="nexus-info-action"><i class="fas fa-calendar-plus"></i></button>
                            </div>
                            <div class="nexus-info-card">
                                <div class="nexus-info-icon" style="--icon-color: #10b981;">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="nexus-info-content">
                                    <span class="nexus-info-label">Location</span>
                                    <span class="nexus-info-value">The Grand Hall, Downtown</span>
                                </div>
                                <button class="nexus-info-action"><i class="fas fa-directions"></i></button>
                            </div>
                            <div class="nexus-info-card">
                                <div class="nexus-info-icon" style="--icon-color: #f59e0b;">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="nexus-info-content">
                                    <span class="nexus-info-label">Price</span>
                                    <span class="nexus-info-value">Free for members</span>
                                </div>
                            </div>
                        </div>

                        <!-- Attendees Preview -->
                        <div class="nexus-modal-attendees">
                            <div class="nexus-attendee-stack">
                                <div class="nexus-attendee" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">JD</div>
                                <div class="nexus-attendee" style="background: linear-gradient(135deg, #ec4899, #f472b6);">MK</div>
                                <div class="nexus-attendee" style="background: linear-gradient(135deg, #10b981, #34d399);">SC</div>
                                <div class="nexus-attendee" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">AL</div>
                                <div class="nexus-attendee nexus-attendee-more">+48</div>
                            </div>
                            <span class="nexus-attendee-label">52 people attending</span>
                        </div>

                        <!-- Description -->
                        <div class="nexus-modal-description">
                            <h3>About This Event</h3>
                            <p>Join us for an unforgettable evening of connection, celebration, and community spirit! This year's celebration features:</p>
                            <ul>
                                <li>🎵 Live music and DJ performances</li>
                                <li>🍕 Complimentary food and refreshments</li>
                                <li>🎮 Interactive games and activities</li>
                                <li>🏆 Awards ceremony for top contributors</li>
                            </ul>
                        </div>

                        <!-- Tags -->
                        <div class="nexus-modal-tags">
                            <span class="nexus-modal-tag">#community</span>
                            <span class="nexus-modal-tag">#celebration</span>
                            <span class="nexus-modal-tag">#networking</span>
                            <span class="nexus-modal-tag">#party</span>
                        </div>
                    </div>

                    <!-- Sticky CTA -->
                    <div class="nexus-modal-cta">
                        <div class="nexus-cta-price">
                            <span class="nexus-price-free">FREE</span>
                            <span class="nexus-price-note">for members</span>
                        </div>
                        <button class="nexus-cta-button" onclick="this.closest('.nexus-premium-modal').classList.remove('open');">
                            <span>Reserve Spot</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>`
        });

        // ============================================
        // WORLD-CLASS DESKTOP-NATIVE BLOCKS
        // Notion/Discord/Linear inspired premium design
        // ============================================

        // 1. PREMIUM Sidebar Rail - Notion/Discord/Linear Level
        bm.add('desktop-sidebar-rail', {
            label: '✨ Sidebar Rail',
            category: 'Desktop',
            attributes: { class: 'fa fa-columns' },
            content: `<aside class="nexus-premium-sidebar nexus-desktop-only">
                <!-- Sidebar Resize Handle -->
                <div class="nexus-sidebar-resize-handle"></div>

                <!-- Brand Header -->
                <div class="nexus-sidebar-header">
                    <div class="nexus-workspace-switcher">
                        <div class="nexus-workspace-icon">
                            <span>N</span>
                            <div class="nexus-workspace-glow"></div>
                        </div>
                        <div class="nexus-workspace-info">
                            <span class="nexus-workspace-name">Nexus HQ</span>
                            <span class="nexus-workspace-plan">Pro Plan</span>
                        </div>
                        <button class="nexus-workspace-dropdown">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <!-- Quick Actions Bar -->
                <div class="nexus-sidebar-quick-actions">
                    <button class="nexus-quick-action-btn nexus-search-trigger">
                        <i class="fas fa-search"></i>
                        <span>Search</span>
                        <kbd>⌘K</kbd>
                    </button>
                    <button class="nexus-quick-action-btn nexus-new-btn">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>

                <!-- Navigation Sections -->
                <div class="nexus-sidebar-scroll">
                    <div class="nexus-nav-section">
                        <a href="#" class="nexus-nav-item active">
                            <div class="nexus-nav-item-icon"><i class="fas fa-home"></i></div>
                            <span class="nexus-nav-item-label">Home</span>
                            <div class="nexus-nav-item-active-bar"></div>
                        </a>
                        <a href="#" class="nexus-nav-item">
                            <div class="nexus-nav-item-icon"><i class="fas fa-inbox"></i></div>
                            <span class="nexus-nav-item-label">Inbox</span>
                            <span class="nexus-nav-item-badge nexus-badge-primary">12</span>
                        </a>
                        <a href="#" class="nexus-nav-item">
                            <div class="nexus-nav-item-icon"><i class="fas fa-calendar-check"></i></div>
                            <span class="nexus-nav-item-label">My Tasks</span>
                            <span class="nexus-nav-item-badge">5</span>
                        </a>
                        <a href="#" class="nexus-nav-item">
                            <div class="nexus-nav-item-icon"><i class="fas fa-compass"></i></div>
                            <span class="nexus-nav-item-label">Explore</span>
                            <span class="nexus-nav-item-tag nexus-tag-new">New</span>
                        </a>
                    </div>

                    <div class="nexus-nav-section">
                        <div class="nexus-nav-section-header">
                            <span>Favorites</span>
                            <button class="nexus-section-action"><i class="fas fa-plus"></i></button>
                        </div>
                        <a href="#" class="nexus-nav-item">
                            <div class="nexus-nav-item-icon nexus-icon-page"><i class="fas fa-file-alt"></i></div>
                            <span class="nexus-nav-item-label">Quick Notes</span>
                        </a>
                        <a href="#" class="nexus-nav-item">
                            <div class="nexus-nav-item-icon nexus-icon-database"><i class="fas fa-table"></i></div>
                            <span class="nexus-nav-item-label">Members DB</span>
                        </a>
                    </div>

                    <div class="nexus-nav-section">
                        <div class="nexus-nav-section-header">
                            <span>Channels</span>
                            <button class="nexus-section-action"><i class="fas fa-plus"></i></button>
                        </div>
                        <a href="#" class="nexus-nav-item nexus-nav-channel">
                            <span class="nexus-channel-hash">#</span>
                            <span class="nexus-nav-item-label">general</span>
                            <span class="nexus-unread-dot"></span>
                        </a>
                        <a href="#" class="nexus-nav-item nexus-nav-channel">
                            <span class="nexus-channel-hash">#</span>
                            <span class="nexus-nav-item-label">announcements</span>
                        </a>
                        <a href="#" class="nexus-nav-item nexus-nav-channel">
                            <span class="nexus-channel-hash">#</span>
                            <span class="nexus-nav-item-label">random</span>
                            <span class="nexus-nav-item-badge nexus-badge-mention">@2</span>
                        </a>
                        <a href="#" class="nexus-nav-item nexus-nav-channel nexus-nav-voice">
                            <span class="nexus-channel-icon"><i class="fas fa-volume-up"></i></span>
                            <span class="nexus-nav-item-label">Voice Lounge</span>
                            <span class="nexus-voice-users">3</span>
                        </a>
                    </div>

                    <div class="nexus-nav-section">
                        <div class="nexus-nav-section-header">
                            <span>Direct Messages</span>
                        </div>
                        <a href="#" class="nexus-nav-item nexus-nav-dm">
                            <div class="nexus-dm-avatar" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                                <span>SC</span>
                                <span class="nexus-dm-status nexus-status-online"></span>
                            </div>
                            <span class="nexus-nav-item-label">Sarah Chen</span>
                        </a>
                        <a href="#" class="nexus-nav-item nexus-nav-dm">
                            <div class="nexus-dm-avatar" style="background: linear-gradient(135deg, #10b981, #34d399);">
                                <span>MK</span>
                                <span class="nexus-dm-status nexus-status-idle"></span>
                            </div>
                            <span class="nexus-nav-item-label">Mike Kim</span>
                            <span class="nexus-nav-item-badge">2</span>
                        </a>
                        <a href="#" class="nexus-nav-item nexus-nav-dm">
                            <div class="nexus-dm-avatar" style="background: linear-gradient(135deg, #6366f1, #818cf8);">
                                <span>AL</span>
                                <span class="nexus-dm-status nexus-status-dnd"></span>
                            </div>
                            <span class="nexus-nav-item-label">Alex Lee</span>
                        </a>
                    </div>
                </div>

                <!-- User Panel -->
                <div class="nexus-sidebar-user-panel">
                    <div class="nexus-user-panel-main">
                        <div class="nexus-user-avatar-container">
                            <div class="nexus-user-avatar">
                                <span>JD</span>
                            </div>
                            <span class="nexus-user-status nexus-status-online"></span>
                        </div>
                        <div class="nexus-user-info">
                            <span class="nexus-user-name">John Doe</span>
                            <span class="nexus-user-status-text">Online</span>
                        </div>
                    </div>
                    <div class="nexus-user-panel-actions">
                        <button class="nexus-user-action" title="Mute">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <button class="nexus-user-action" title="Deafen">
                            <i class="fas fa-headphones"></i>
                        </button>
                        <button class="nexus-user-action" title="Settings">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
            </aside>`
        });

        // 2. Three Column Grid Layout (Desktop) - Enhanced with rich widgets
        bm.add('desktop-three-col', {
            label: 'Three Columns',
            category: 'Desktop',
            attributes: { class: 'fa fa-table-columns' },
            content: `<div class="nexus-three-col nexus-desktop-only">
                <div class="nexus-three-col-left">
                    <div style="padding: 20px;">
                        <div class="nexus-sidebar-label">Navigation</div>
                        <a href="#" class="nexus-sidebar-item active"><i class="fas fa-home"></i> Feed</a>
                        <a href="#" class="nexus-sidebar-item"><i class="fas fa-fire"></i> Trending</a>
                        <a href="#" class="nexus-sidebar-item"><i class="fas fa-bookmark"></i> Saved</a>
                        <a href="#" class="nexus-sidebar-item"><i class="fas fa-history"></i> Recent</a>
                        <div class="nexus-sidebar-divider"></div>
                        <div class="nexus-sidebar-label">Your Groups</div>
                        <a href="#" class="nexus-sidebar-item"><span style="width: 24px; height: 24px; border-radius: 6px; background: #818cf8; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 0.7rem; color: white;">TC</span> Tech Community</a>
                        <a href="#" class="nexus-sidebar-item"><span style="width: 24px; height: 24px; border-radius: 6px; background: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 0.7rem; color: white;">DC</span> Design Club</a>
                        <a href="#" class="nexus-sidebar-item"><span style="width: 24px; height: 24px; border-radius: 6px; background: #f59e0b; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 0.7rem; color: white;">BC</span> Book Club</a>
                    </div>
                </div>
                <div class="nexus-three-col-center">
                    <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px;">
                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #a78bfa);"></div>
                            <div style="flex: 1;">
                                <input type="text" placeholder="What's on your mind?" style="width: 100%; background: var(--holo-gradient); border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 12px 16px; color: var(--glass-text-primary);">
                                <div style="display: flex; gap: 12px; margin-top: 12px;">
                                    <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 6px;"><i class="fas fa-image"></i> Photo</button>
                                    <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 6px;"><i class="fas fa-video"></i> Video</button>
                                    <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 6px;"><i class="fas fa-calendar"></i> Event</button>
                                    <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 6px;"><i class="fas fa-poll"></i> Poll</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nexus-card" style="margin-bottom: 20px;">
                        <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #34d399, #10b981);"></div>
                            <div>
                                <div style="font-weight: 600; color: var(--glass-text-primary);">Sarah Johnson</div>
                                <div style="font-size: 0.85rem; color: var(--glass-text-muted);">2 hours ago · <i class="fas fa-globe"></i></div>
                            </div>
                        </div>
                        <p style="color: var(--glass-text-secondary); margin: 0 0 16px;">Just finished setting up our new community space! Can't wait to host our first event here. 🎉</p>
                        <div style="height: 200px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.15)); border-radius: var(--radius-md); margin-bottom: 16px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-image" style="font-size: 2rem; color: var(--glass-text-muted);"></i></div>
                        <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--glass-border);">
                            <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px;"><i class="far fa-heart"></i> 24 Likes</button>
                            <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px;"><i class="far fa-comment"></i> 8 Comments</button>
                            <button style="background: none; border: none; color: var(--glass-text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px;"><i class="fas fa-share"></i> Share</button>
                        </div>
                    </div>
                </div>
                <div class="nexus-three-col-right">
                    <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px;">
                        <h4 style="color: var(--glass-text-primary); margin: 0 0 16px; font-size: 1rem;">Online Now</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #818cf8; position: relative;"><span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: #22c55e; border-radius: 50%; border: 2px solid var(--glass-bg);"></span></div>
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #f59e0b; position: relative;"><span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: #22c55e; border-radius: 50%; border: 2px solid var(--glass-bg);"></span></div>
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #10b981; position: relative;"><span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: #22c55e; border-radius: 50%; border: 2px solid var(--glass-bg);"></span></div>
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #ec4899; position: relative;"><span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: #22c55e; border-radius: 50%; border: 2px solid var(--glass-bg);"></span></div>
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: var(--glass-text-muted);">+12</div>
                        </div>
                    </div>
                    <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 20px;">
                        <h4 style="color: var(--glass-text-primary); margin: 0 0 16px; font-size: 1rem;">Upcoming Events</h4>
                        <div style="display: flex; gap: 12px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--glass-border);">
                            <div style="width: 48px; text-align: center; padding: 8px; background: var(--holo-gradient); border-radius: var(--radius-sm);"><div style="font-size: 0.75rem; color: var(--glass-text-muted);">MAR</div><div style="font-size: 1.2rem; font-weight: 700; color: var(--glass-primary);">15</div></div>
                            <div><div style="font-weight: 600; color: var(--glass-text-primary); font-size: 0.9rem;">Community Meetup</div><div style="font-size: 0.8rem; color: var(--glass-text-muted);">2:00 PM · Downtown</div></div>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <div style="width: 48px; text-align: center; padding: 8px; background: var(--holo-gradient); border-radius: var(--radius-sm);"><div style="font-size: 0.75rem; color: var(--glass-text-muted);">MAR</div><div style="font-size: 1.2rem; font-weight: 700; color: var(--glass-primary);">22</div></div>
                            <div><div style="font-weight: 600; color: var(--glass-text-primary); font-size: 0.9rem;">Workshop Day</div><div style="font-size: 0.8rem; color: var(--glass-text-muted);">10:00 AM · Online</div></div>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.15)); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: var(--radius-lg); padding: 20px;">
                        <h4 style="color: var(--glass-text-primary); margin: 0 0 8px; font-size: 1rem;">🎉 Upgrade to Pro</h4>
                        <p style="color: var(--glass-text-secondary); font-size: 0.85rem; margin: 0 0 16px;">Unlock premium features and grow your community faster.</p>
                        <button class="nexus-btn nexus-btn-primary" style="width: 100%; font-size: 0.85rem;">Learn More</button>
                    </div>
                </div>
            </div>`
        });

        // 3. Mega Menu (Desktop) - Enhanced with icons, descriptions and featured section
        bm.add('desktop-mega-menu', {
            label: 'Mega Menu',
            category: 'Desktop',
            attributes: { class: 'fa fa-grip' },
            content: `<div class="nexus-desktop-only" style="position: relative; display: inline-block;">
                <button class="nexus-btn nexus-btn-secondary" onmouseenter="document.getElementById('demo-mega').classList.add('open');" onmouseleave="document.getElementById('demo-mega').classList.remove('open');">
                    Products <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 0.8em;"></i>
                </button>
                <div id="demo-mega" class="nexus-mega-menu" style="position: absolute; top: 100%; left: 0; min-width: 900px;" onmouseenter="this.classList.add('open');" onmouseleave="this.classList.remove('open');">
                    <div class="nexus-mega-menu-inner" style="grid-template-columns: 1fr 1fr 280px;">
                        <div class="nexus-mega-menu-column">
                            <h4>Platform</h4>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class="fas fa-users"></i></div>
                                <div><strong>Community Hub</strong><span>Central space for member interactions</span></div>
                            </a>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-calendar"></i></div>
                                <div><strong>Event Manager</strong><span>Create and manage community events</span></div>
                            </a>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="fas fa-address-book"></i></div>
                                <div><strong>Member Directory</strong><span>Find and connect with members</span></div>
                            </a>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(236, 72, 153, 0.1); color: #ec4899;"><i class="fas fa-chart-bar"></i></div>
                                <div><strong>Analytics Dashboard</strong><span>Track engagement and growth metrics</span></div>
                            </a>
                        </div>
                        <div class="nexus-mega-menu-column">
                            <h4>Resources</h4>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;"><i class="fas fa-book"></i></div>
                                <div><strong>Documentation</strong><span>Guides and reference materials</span></div>
                            </a>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(6, 182, 212, 0.1); color: #06b6d4;"><i class="fas fa-code"></i></div>
                                <div><strong>API Reference</strong><span>Build custom integrations</span></div>
                            </a>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(249, 115, 22, 0.1); color: #f97316;"><i class="fas fa-graduation-cap"></i></div>
                                <div><strong>Tutorials</strong><span>Step-by-step learning guides</span></div>
                            </a>
                            <a href="#" class="nexus-mega-menu-item-rich">
                                <div class="nexus-mega-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;"><i class="fas fa-rss"></i></div>
                                <div><strong>Blog</strong><span>News, tips, and best practices</span></div>
                            </a>
                        </div>
                        <div class="nexus-mega-menu-column nexus-mega-menu-featured" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.1)); border-radius: var(--radius-lg); padding: 24px;">
                            <div style="font-size: 2.5rem; margin-bottom: 16px;">🚀</div>
                            <h4 style="margin: 0 0 8px;">What's New</h4>
                            <p style="color: var(--glass-text-secondary); font-size: 0.9rem; margin: 0 0 16px; line-height: 1.6;">Discover our latest AI-powered community insights and engagement tools.</p>
                            <a href="#" class="nexus-btn nexus-btn-primary" style="width: 100%;">Explore Features</a>
                            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--glass-border);">
                                <a href="#" style="color: var(--glass-primary); font-size: 0.85rem; text-decoration: none;"><i class="fas fa-play-circle" style="margin-right: 6px;"></i> Watch Demo</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // 4. Hover Cards (Desktop) - Enhanced with action buttons and badges
        bm.add('desktop-hover-cards', {
            label: 'Hover Cards',
            category: 'Desktop',
            attributes: { class: 'fa fa-id-card' },
            content: `<div class="nexus-desktop-only" style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start;">
                <div class="nexus-hover-card-trigger">
                    <a href="#" style="color: var(--glass-primary); font-weight: 600; text-decoration: none;">@alex_smith</a>
                    <div class="nexus-hover-card">
                        <div class="nexus-hover-card-cover" style="height: 60px; background: linear-gradient(135deg, #818cf8, #a78bfa); border-radius: var(--radius-md) var(--radius-md) 0 0; margin: -16px -16px 0 -16px;"></div>
                        <div class="nexus-hover-card-header" style="margin-top: -24px;">
                            <div class="nexus-hover-card-avatar" style="border: 3px solid var(--glass-bg);">
                                <div class="nexus-hover-card-avatar-inner" style="background: linear-gradient(135deg, #818cf8, #a78bfa);"></div>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <h4 class="nexus-hover-card-name">Alex Smith</h4>
                                    <i class="fas fa-check-circle" style="color: #6366f1; font-size: 0.85rem;"></i>
                                </div>
                                <p class="nexus-hover-card-role">Community Manager</p>
                            </div>
                        </div>
                        <p class="nexus-hover-card-bio">Passionate about building inclusive communities. 5+ years experience in community management and growth.</p>
                        <div class="nexus-hover-card-tags" style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
                            <span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Leadership</span>
                            <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Events</span>
                            <span style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Mentoring</span>
                        </div>
                        <div class="nexus-hover-card-stats">
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">1.2K</div>
                                <div class="nexus-hover-card-stat-label">Posts</div>
                            </div>
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">856</div>
                                <div class="nexus-hover-card-stat-label">Followers</div>
                            </div>
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">42</div>
                                <div class="nexus-hover-card-stat-label">Groups</div>
                            </div>
                        </div>
                        <div class="nexus-hover-card-actions" style="display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--glass-border);">
                            <button class="nexus-btn nexus-btn-primary" style="flex: 1; font-size: 0.85rem; padding: 8px;">Follow</button>
                            <button class="nexus-btn nexus-btn-secondary" style="width: 36px; padding: 8px;"><i class="fas fa-envelope"></i></button>
                        </div>
                    </div>
                </div>
                <div class="nexus-hover-card-trigger">
                    <a href="#" style="color: var(--glass-primary); font-weight: 600; text-decoration: none;">@maria_j</a>
                    <div class="nexus-hover-card">
                        <div class="nexus-hover-card-cover" style="height: 60px; background: linear-gradient(135deg, #f472b6, #ec4899); border-radius: var(--radius-md) var(--radius-md) 0 0; margin: -16px -16px 0 -16px;"></div>
                        <div class="nexus-hover-card-header" style="margin-top: -24px;">
                            <div class="nexus-hover-card-avatar" style="border: 3px solid var(--glass-bg);">
                                <div class="nexus-hover-card-avatar-inner" style="background: linear-gradient(135deg, #f472b6, #ec4899);"></div>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <h4 class="nexus-hover-card-name">Maria Johnson</h4>
                                    <span style="background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.65rem; font-weight: 600;">PRO</span>
                                </div>
                                <p class="nexus-hover-card-role">Event Organizer</p>
                            </div>
                        </div>
                        <p class="nexus-hover-card-bio">Creating memorable experiences through thoughtfully planned community events. Let's connect!</p>
                        <div class="nexus-hover-card-tags" style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
                            <span style="background: rgba(236, 72, 153, 0.1); color: #ec4899; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Events</span>
                            <span style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Planning</span>
                        </div>
                        <div class="nexus-hover-card-stats">
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">324</div>
                                <div class="nexus-hover-card-stat-label">Events</div>
                            </div>
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">2.1K</div>
                                <div class="nexus-hover-card-stat-label">Attendees</div>
                            </div>
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">98%</div>
                                <div class="nexus-hover-card-stat-label">Rating</div>
                            </div>
                        </div>
                        <div class="nexus-hover-card-actions" style="display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--glass-border);">
                            <button class="nexus-btn nexus-btn-primary" style="flex: 1; font-size: 0.85rem; padding: 8px;">Follow</button>
                            <button class="nexus-btn nexus-btn-secondary" style="width: 36px; padding: 8px;"><i class="fas fa-envelope"></i></button>
                        </div>
                    </div>
                </div>
                <div class="nexus-hover-card-trigger">
                    <a href="#" style="color: var(--glass-primary); font-weight: 600; text-decoration: none;">@david_tech</a>
                    <div class="nexus-hover-card">
                        <div class="nexus-hover-card-cover" style="height: 60px; background: linear-gradient(135deg, #34d399, #10b981); border-radius: var(--radius-md) var(--radius-md) 0 0; margin: -16px -16px 0 -16px;"></div>
                        <div class="nexus-hover-card-header" style="margin-top: -24px;">
                            <div class="nexus-hover-card-avatar" style="border: 3px solid var(--glass-bg);">
                                <div class="nexus-hover-card-avatar-inner" style="background: linear-gradient(135deg, #34d399, #10b981);"></div>
                            </div>
                            <div style="flex: 1;">
                                <h4 class="nexus-hover-card-name">David Chen</h4>
                                <p class="nexus-hover-card-role">Developer Advocate</p>
                            </div>
                        </div>
                        <p class="nexus-hover-card-bio">Full-stack developer helping others learn to code. Open source enthusiast.</p>
                        <div class="nexus-hover-card-tags" style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
                            <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Coding</span>
                            <span style="background: rgba(6, 182, 212, 0.1); color: #06b6d4; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">Open Source</span>
                        </div>
                        <div class="nexus-hover-card-stats">
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">89</div>
                                <div class="nexus-hover-card-stat-label">Projects</div>
                            </div>
                            <div class="nexus-hover-card-stat">
                                <div class="nexus-hover-card-stat-value">1.5K</div>
                                <div class="nexus-hover-card-stat-label">Followers</div>
                            </div>
                        </div>
                        <div class="nexus-hover-card-actions" style="display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--glass-border);">
                            <button class="nexus-btn nexus-btn-primary" style="flex: 1; font-size: 0.85rem; padding: 8px;">Follow</button>
                            <button class="nexus-btn nexus-btn-secondary" style="width: 36px; padding: 8px;"><i class="fas fa-envelope"></i></button>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // 5. PREMIUM Toast System - Sonner/React-Hot-Toast Level Quality
        bm.add('desktop-toast', {
            label: '✨ Toast Alerts',
            category: 'Desktop',
            attributes: { class: 'fa fa-bell' },
            content: `<div class="nexus-desktop-only nexus-premium-toast-demo">
                <!-- Toast Stack Container -->
                <div class="nexus-toast-stack">
                    <!-- Success Toast - With checkmark animation -->
                    <div class="nexus-toast-premium nexus-toast-success" style="--toast-index: 0;">
                        <div class="nexus-toast-icon-animated">
                            <svg class="nexus-checkmark" viewBox="0 0 52 52">
                                <circle class="nexus-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                                <path class="nexus-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                            </svg>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">Profile Updated</span>
                            <span class="nexus-toast-desc">Your changes have been saved successfully.</span>
                        </div>
                        <button class="nexus-toast-dismiss"><i class="fas fa-times"></i></button>
                        <div class="nexus-toast-timer"></div>
                    </div>

                    <!-- Error Toast - With shake animation -->
                    <div class="nexus-toast-premium nexus-toast-error" style="--toast-index: 1;">
                        <div class="nexus-toast-icon-animated nexus-icon-error">
                            <i class="fas fa-exclamation"></i>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">Upload Failed</span>
                            <span class="nexus-toast-desc">File size exceeds 10MB limit.</span>
                            <div class="nexus-toast-actions">
                                <button class="nexus-toast-action-btn nexus-action-primary">Retry</button>
                                <button class="nexus-toast-action-btn">Cancel</button>
                            </div>
                        </div>
                        <button class="nexus-toast-dismiss"><i class="fas fa-times"></i></button>
                    </div>

                    <!-- Loading Toast - With spinner -->
                    <div class="nexus-toast-premium nexus-toast-loading" style="--toast-index: 2;">
                        <div class="nexus-toast-spinner">
                            <svg viewBox="0 0 50 50">
                                <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
                            </svg>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">Uploading files...</span>
                            <span class="nexus-toast-desc">3 of 5 completed</span>
                            <div class="nexus-toast-progress-bar">
                                <div class="nexus-progress-fill" style="width: 60%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Promise Toast - Multi-state -->
                    <div class="nexus-toast-premium nexus-toast-promise" style="--toast-index: 3;">
                        <div class="nexus-toast-icon-promise">
                            <div class="nexus-promise-pending"><i class="fas fa-cloud-upload-alt"></i></div>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">Saving to cloud...</span>
                            <span class="nexus-toast-desc">This may take a moment</span>
                        </div>
                        <button class="nexus-toast-dismiss"><i class="fas fa-times"></i></button>
                    </div>

                    <!-- Notification Toast - With avatar -->
                    <div class="nexus-toast-premium nexus-toast-notification" style="--toast-index: 4;">
                        <div class="nexus-toast-avatar-container">
                            <div class="nexus-toast-avatar" style="background: linear-gradient(135deg, #ec4899, #f472b6);">SC</div>
                            <span class="nexus-toast-avatar-badge">💬</span>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">Sarah Chen</span>
                            <span class="nexus-toast-desc">Hey! Are you coming to the meetup? 🎉</span>
                            <span class="nexus-toast-time">Just now</span>
                        </div>
                        <button class="nexus-toast-dismiss"><i class="fas fa-times"></i></button>
                    </div>

                    <!-- Action Toast - With CTA -->
                    <div class="nexus-toast-premium nexus-toast-action-toast" style="--toast-index: 5;">
                        <div class="nexus-toast-icon-action">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">🎁 Limited Time Offer!</span>
                            <span class="nexus-toast-desc">Upgrade to Pro and get 50% off</span>
                        </div>
                        <button class="nexus-toast-cta-btn">Upgrade Now</button>
                        <button class="nexus-toast-dismiss"><i class="fas fa-times"></i></button>
                    </div>

                    <!-- Undo Toast - With timer -->
                    <div class="nexus-toast-premium nexus-toast-undo" style="--toast-index: 6;">
                        <div class="nexus-toast-icon-undo">
                            <i class="fas fa-trash"></i>
                        </div>
                        <div class="nexus-toast-body">
                            <span class="nexus-toast-title">Message deleted</span>
                        </div>
                        <button class="nexus-toast-undo-btn">
                            <i class="fas fa-undo"></i>
                            Undo
                            <span class="nexus-undo-countdown">5</span>
                        </button>
                    </div>
                </div>
            </div>`
        });

        // ============================================
        // GALACTIC-TIER BLOCKS - Beyond World Class
        // Raycast, Linear, Vercel, Stripe inspired
        // ============================================

        // 6. GALACTIC Command Palette - Raycast/Spotlight Level
        bm.add('command-palette', {
            label: '🌌 Command Palette',
            category: 'Desktop',
            attributes: { class: 'fa fa-terminal' },
            content: `<div class="nexus-desktop-only nexus-command-palette-demo">
                <button class="nexus-cmd-trigger" onclick="document.getElementById('cmd-palette').classList.add('open');">
                    <span class="nexus-cmd-trigger-content">
                        <i class="fas fa-search"></i>
                        <span>Search or jump to...</span>
                    </span>
                    <kbd class="nexus-kbd-combo"><span>⌘</span><span>K</span></kbd>
                </button>

                <div id="cmd-palette" class="nexus-cmd-palette" onclick="if(event.target === this) this.classList.remove('open');">
                    <div class="nexus-cmd-container">
                        <!-- Holographic glow effects -->
                        <div class="nexus-cmd-glow nexus-cmd-glow-1"></div>
                        <div class="nexus-cmd-glow nexus-cmd-glow-2"></div>

                        <!-- Search Input -->
                        <div class="nexus-cmd-header">
                            <div class="nexus-cmd-input-wrap">
                                <i class="fas fa-search nexus-cmd-search-icon"></i>
                                <input type="text" class="nexus-cmd-input" placeholder="Type a command or search..." autofocus>
                                <div class="nexus-cmd-input-actions">
                                    <button class="nexus-cmd-scope-btn">
                                        <i class="fas fa-filter"></i>
                                        All
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="nexus-cmd-section">
                            <div class="nexus-cmd-section-title">
                                <span>Quick Actions</span>
                                <span class="nexus-cmd-hint">Tab to navigate</span>
                            </div>
                            <div class="nexus-cmd-list">
                                <div class="nexus-cmd-item nexus-cmd-item-active">
                                    <div class="nexus-cmd-item-icon" style="--cmd-color: #6366f1;">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Create New Post</span>
                                        <span class="nexus-cmd-item-desc">Start writing a new post</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <kbd>⏎</kbd>
                                    </div>
                                </div>
                                <div class="nexus-cmd-item">
                                    <div class="nexus-cmd-item-icon" style="--cmd-color: #10b981;">
                                        <i class="fas fa-calendar-plus"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Create Event</span>
                                        <span class="nexus-cmd-item-desc">Schedule a new event</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <kbd>E</kbd>
                                    </div>
                                </div>
                                <div class="nexus-cmd-item">
                                    <div class="nexus-cmd-item-icon" style="--cmd-color: #f59e0b;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Invite Members</span>
                                        <span class="nexus-cmd-item-desc">Send invite links</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <kbd>I</kbd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent -->
                        <div class="nexus-cmd-section">
                            <div class="nexus-cmd-section-title">
                                <span>Recent</span>
                                <button class="nexus-cmd-clear">Clear</button>
                            </div>
                            <div class="nexus-cmd-list">
                                <div class="nexus-cmd-item">
                                    <div class="nexus-cmd-item-icon nexus-cmd-icon-page">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Community Guidelines</span>
                                        <span class="nexus-cmd-item-path">Pages / About</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <span class="nexus-cmd-time">2m ago</span>
                                    </div>
                                </div>
                                <div class="nexus-cmd-item">
                                    <div class="nexus-cmd-item-icon nexus-cmd-icon-user">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Sarah Chen</span>
                                        <span class="nexus-cmd-item-path">Members / Active</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <span class="nexus-cmd-time">1h ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="nexus-cmd-section">
                            <div class="nexus-cmd-section-title">
                                <span>Go to</span>
                            </div>
                            <div class="nexus-cmd-list">
                                <div class="nexus-cmd-item">
                                    <div class="nexus-cmd-item-icon" style="--cmd-color: #8b5cf6;">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Dashboard</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <kbd>G</kbd><kbd>D</kbd>
                                    </div>
                                </div>
                                <div class="nexus-cmd-item">
                                    <div class="nexus-cmd-item-icon" style="--cmd-color: #ec4899;">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div class="nexus-cmd-item-content">
                                        <span class="nexus-cmd-item-title">Settings</span>
                                    </div>
                                    <div class="nexus-cmd-item-meta">
                                        <kbd>G</kbd><kbd>S</kbd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="nexus-cmd-footer">
                            <div class="nexus-cmd-footer-hints">
                                <span><kbd>↑↓</kbd> Navigate</span>
                                <span><kbd>⏎</kbd> Select</span>
                                <span><kbd>esc</kbd> Close</span>
                            </div>
                            <div class="nexus-cmd-footer-brand">
                                <span class="nexus-cmd-brand-icon">⚡</span>
                                Powered by Nexus
                            </div>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // 7. GALACTIC Notification Center - iOS/macOS Style
        bm.add('notification-center', {
            label: '🌌 Notifications',
            category: 'Desktop',
            attributes: { class: 'fa fa-bell' },
            content: `<div class="nexus-desktop-only nexus-notif-center-demo">
                <button class="nexus-notif-trigger" onclick="document.getElementById('notif-panel').classList.toggle('open');">
                    <i class="fas fa-bell"></i>
                    <span class="nexus-notif-badge-trigger">5</span>
                </button>

                <div id="notif-panel" class="nexus-notif-panel">
                    <!-- Header -->
                    <div class="nexus-notif-header">
                        <h3>Notifications</h3>
                        <div class="nexus-notif-header-actions">
                            <button class="nexus-notif-mark-read">Mark all read</button>
                            <button class="nexus-notif-settings"><i class="fas fa-cog"></i></button>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="nexus-notif-tabs">
                        <button class="nexus-notif-tab active">All</button>
                        <button class="nexus-notif-tab">Mentions <span class="nexus-tab-count">3</span></button>
                        <button class="nexus-notif-tab">Following</button>
                    </div>

                    <!-- Notification Groups -->
                    <div class="nexus-notif-scroll">
                        <div class="nexus-notif-group">
                            <div class="nexus-notif-group-header">
                                <span>Today</span>
                            </div>

                            <!-- Unread notification with glow -->
                            <div class="nexus-notif-item nexus-notif-unread">
                                <div class="nexus-notif-unread-glow"></div>
                                <div class="nexus-notif-avatar" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                                    <span>SC</span>
                                </div>
                                <div class="nexus-notif-content">
                                    <div class="nexus-notif-text">
                                        <strong>Sarah Chen</strong> mentioned you in <strong>Design Review</strong>
                                    </div>
                                    <div class="nexus-notif-preview">
                                        "@john what do you think about this approach?"
                                    </div>
                                    <div class="nexus-notif-meta">
                                        <span class="nexus-notif-time">2 min ago</span>
                                        <span class="nexus-notif-dot">•</span>
                                        <span class="nexus-notif-channel">#design</span>
                                    </div>
                                </div>
                                <div class="nexus-notif-actions">
                                    <button class="nexus-notif-action-btn" title="Reply"><i class="fas fa-reply"></i></button>
                                    <button class="nexus-notif-action-btn" title="Mark as read"><i class="fas fa-check"></i></button>
                                </div>
                            </div>

                            <!-- Like notification -->
                            <div class="nexus-notif-item nexus-notif-unread">
                                <div class="nexus-notif-icon-wrap nexus-notif-icon-like">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="nexus-notif-content">
                                    <div class="nexus-notif-text">
                                        <strong>Alex, Maria</strong> and <strong>12 others</strong> liked your post
                                    </div>
                                    <div class="nexus-notif-meta">
                                        <span class="nexus-notif-time">15 min ago</span>
                                    </div>
                                </div>
                                <div class="nexus-notif-stack">
                                    <div class="nexus-stack-avatar" style="background: linear-gradient(135deg, #6366f1, #818cf8);">A</div>
                                    <div class="nexus-stack-avatar" style="background: linear-gradient(135deg, #10b981, #34d399);">M</div>
                                    <div class="nexus-stack-avatar nexus-stack-more">+12</div>
                                </div>
                            </div>

                            <!-- Event reminder -->
                            <div class="nexus-notif-item nexus-notif-unread">
                                <div class="nexus-notif-icon-wrap nexus-notif-icon-event">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="nexus-notif-content">
                                    <div class="nexus-notif-text">
                                        <strong>Community Meetup</strong> starts in 1 hour
                                    </div>
                                    <div class="nexus-notif-event-card">
                                        <div class="nexus-event-time">
                                            <i class="fas fa-clock"></i> 2:00 PM - 4:00 PM
                                        </div>
                                        <div class="nexus-event-location">
                                            <i class="fas fa-map-marker-alt"></i> Virtual Meeting
                                        </div>
                                    </div>
                                    <div class="nexus-notif-actions-inline">
                                        <button class="nexus-notif-btn-primary">Join Now</button>
                                        <button class="nexus-notif-btn-secondary">Snooze</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="nexus-notif-group">
                            <div class="nexus-notif-group-header">
                                <span>Yesterday</span>
                            </div>

                            <!-- Read notification -->
                            <div class="nexus-notif-item">
                                <div class="nexus-notif-avatar" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                    <span>MK</span>
                                </div>
                                <div class="nexus-notif-content">
                                    <div class="nexus-notif-text">
                                        <strong>Mike Kim</strong> accepted your connection request
                                    </div>
                                    <div class="nexus-notif-meta">
                                        <span class="nexus-notif-time">Yesterday at 3:45 PM</span>
                                    </div>
                                </div>
                            </div>

                            <!-- System notification -->
                            <div class="nexus-notif-item">
                                <div class="nexus-notif-icon-wrap nexus-notif-icon-system">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="nexus-notif-content">
                                    <div class="nexus-notif-text">
                                        Your account security was updated
                                    </div>
                                    <div class="nexus-notif-meta">
                                        <span class="nexus-notif-time">Yesterday at 10:00 AM</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="nexus-notif-footer">
                        <button class="nexus-notif-view-all">View all notifications</button>
                    </div>
                </div>
            </div>`
        });

        // 8. GALACTIC Media Player - Spotify/Apple Music Style
        bm.add('media-player', {
            label: '🌌 Media Player',
            category: 'Desktop',
            attributes: { class: 'fa fa-music' },
            content: `<div class="nexus-desktop-only nexus-media-player-demo">
                <div class="nexus-player-widget">
                    <!-- Album Art with Glow -->
                    <div class="nexus-player-art-container">
                        <div class="nexus-player-art-glow"></div>
                        <div class="nexus-player-art">
                            <div class="nexus-player-art-inner">🎵</div>
                            <div class="nexus-player-vinyl-ring"></div>
                        </div>
                        <div class="nexus-player-art-reflection"></div>
                    </div>

                    <!-- Track Info -->
                    <div class="nexus-player-info">
                        <div class="nexus-player-track-title">Cosmic Harmony</div>
                        <div class="nexus-player-track-artist">The Nebula Collective</div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="nexus-player-progress">
                        <span class="nexus-player-time">1:24</span>
                        <div class="nexus-player-bar">
                            <div class="nexus-player-bar-fill" style="width: 35%;">
                                <div class="nexus-player-bar-glow"></div>
                            </div>
                            <div class="nexus-player-bar-handle"></div>
                        </div>
                        <span class="nexus-player-time">3:45</span>
                    </div>

                    <!-- Controls -->
                    <div class="nexus-player-controls">
                        <button class="nexus-player-btn nexus-player-btn-secondary" title="Shuffle">
                            <i class="fas fa-random"></i>
                        </button>
                        <button class="nexus-player-btn" title="Previous">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button class="nexus-player-btn nexus-player-btn-play" title="Play">
                            <i class="fas fa-play"></i>
                            <div class="nexus-play-ripple"></div>
                        </button>
                        <button class="nexus-player-btn" title="Next">
                            <i class="fas fa-step-forward"></i>
                        </button>
                        <button class="nexus-player-btn nexus-player-btn-secondary nexus-player-btn-active" title="Repeat">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>

                    <!-- Extra Controls -->
                    <div class="nexus-player-extras">
                        <button class="nexus-player-extra-btn" title="Add to Library">
                            <i class="far fa-heart"></i>
                        </button>
                        <div class="nexus-player-volume">
                            <button class="nexus-player-extra-btn" title="Volume">
                                <i class="fas fa-volume-up"></i>
                            </button>
                            <div class="nexus-volume-slider">
                                <div class="nexus-volume-fill" style="width: 70%;"></div>
                            </div>
                        </div>
                        <button class="nexus-player-extra-btn" title="Queue">
                            <i class="fas fa-list"></i>
                        </button>
                        <button class="nexus-player-extra-btn" title="Expand">
                            <i class="fas fa-expand-alt"></i>
                        </button>
                    </div>
                </div>
            </div>`
        });

        // 9. GALACTIC Chat Widget - Intercom/Crisp Style
        bm.add('chat-widget', {
            label: '🌌 Chat Widget',
            category: 'Mobile',
            attributes: { class: 'fa fa-comments' },
            content: `<div class="nexus-chat-widget-demo">
                <!-- Chat Launcher -->
                <button class="nexus-chat-launcher" onclick="document.getElementById('chat-window').classList.toggle('open');">
                    <div class="nexus-chat-launcher-icon">
                        <i class="fas fa-comments nexus-chat-icon-default"></i>
                        <i class="fas fa-times nexus-chat-icon-close"></i>
                    </div>
                    <span class="nexus-chat-unread">2</span>
                    <div class="nexus-chat-launcher-pulse"></div>
                </button>

                <!-- Chat Window -->
                <div id="chat-window" class="nexus-chat-window">
                    <!-- Header -->
                    <div class="nexus-chat-header">
                        <div class="nexus-chat-header-info">
                            <div class="nexus-chat-avatar-stack">
                                <div class="nexus-chat-avatar" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">N</div>
                                <span class="nexus-chat-status-online"></span>
                            </div>
                            <div class="nexus-chat-header-text">
                                <span class="nexus-chat-header-title">Nexus Support</span>
                                <span class="nexus-chat-header-status">
                                    <span class="nexus-typing-indicator">
                                        <span></span><span></span><span></span>
                                    </span>
                                    Usually replies in minutes
                                </span>
                            </div>
                        </div>
                        <div class="nexus-chat-header-actions">
                            <button class="nexus-chat-header-btn"><i class="fas fa-minus"></i></button>
                            <button class="nexus-chat-header-btn" onclick="document.getElementById('chat-window').classList.remove('open');"><i class="fas fa-times"></i></button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="nexus-chat-messages">
                        <!-- Welcome Message -->
                        <div class="nexus-chat-welcome">
                            <div class="nexus-welcome-avatar" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                <span>👋</span>
                            </div>
                            <h4>Hi there!</h4>
                            <p>We're here to help. Send us a message and we'll respond as soon as possible.</p>
                        </div>

                        <!-- Message from support -->
                        <div class="nexus-chat-message nexus-chat-message-received">
                            <div class="nexus-message-avatar" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">S</div>
                            <div class="nexus-message-content">
                                <div class="nexus-message-bubble">
                                    <p>Hello! How can I help you today? 😊</p>
                                </div>
                                <span class="nexus-message-time">2:30 PM</span>
                            </div>
                        </div>

                        <!-- Message from user -->
                        <div class="nexus-chat-message nexus-chat-message-sent">
                            <div class="nexus-message-content">
                                <div class="nexus-message-bubble">
                                    <p>I have a question about the premium features.</p>
                                </div>
                                <span class="nexus-message-time">2:31 PM • Seen</span>
                            </div>
                        </div>

                        <!-- Typing indicator -->
                        <div class="nexus-chat-message nexus-chat-message-received nexus-chat-typing">
                            <div class="nexus-message-avatar" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">S</div>
                            <div class="nexus-message-content">
                                <div class="nexus-message-bubble nexus-typing-bubble">
                                    <span class="nexus-typing-dot"></span>
                                    <span class="nexus-typing-dot"></span>
                                    <span class="nexus-typing-dot"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Replies -->
                    <div class="nexus-chat-quick-replies">
                        <button class="nexus-quick-reply">Pricing</button>
                        <button class="nexus-quick-reply">Features</button>
                        <button class="nexus-quick-reply">Support</button>
                    </div>

                    <!-- Input -->
                    <div class="nexus-chat-input-container">
                        <button class="nexus-chat-attach"><i class="fas fa-paperclip"></i></button>
                        <input type="text" class="nexus-chat-input" placeholder="Type your message...">
                        <button class="nexus-chat-emoji"><i class="far fa-smile"></i></button>
                        <button class="nexus-chat-send">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>

                    <!-- Powered by -->
                    <div class="nexus-chat-powered">
                        <span>Powered by</span>
                        <strong>Nexus</strong>
                    </div>
                </div>
            </div>`
        });

        // 10. GALACTIC Pricing Cards - Stripe Style
        bm.add('pricing-cards', {
            label: '🌌 Pricing Cards',
            category: 'Desktop',
            attributes: { class: 'fa fa-tags' },
            content: `<section class="nexus-section nexus-pricing-section">
                <div class="nexus-pricing-header">
                    <span class="nexus-pricing-badge">Pricing</span>
                    <h2 class="nexus-pricing-title">Choose Your Plan</h2>
                    <p class="nexus-pricing-subtitle">Start free, upgrade when you're ready</p>

                    <!-- Billing Toggle -->
                    <div class="nexus-billing-toggle">
                        <span class="nexus-billing-label">Monthly</span>
                        <label class="nexus-toggle">
                            <input type="checkbox" checked>
                            <span class="nexus-toggle-slider"></span>
                        </label>
                        <span class="nexus-billing-label nexus-billing-active">
                            Yearly
                            <span class="nexus-billing-save">Save 20%</span>
                        </span>
                    </div>
                </div>

                <div class="nexus-pricing-grid">
                    <!-- Free Plan -->
                    <div class="nexus-pricing-card">
                        <div class="nexus-pricing-card-header">
                            <div class="nexus-plan-icon">🌱</div>
                            <h3 class="nexus-plan-name">Starter</h3>
                            <p class="nexus-plan-desc">Perfect for getting started</p>
                        </div>
                        <div class="nexus-pricing-card-price">
                            <span class="nexus-price-currency">$</span>
                            <span class="nexus-price-amount">0</span>
                            <span class="nexus-price-period">/month</span>
                        </div>
                        <ul class="nexus-pricing-features">
                            <li><i class="fas fa-check"></i> Up to 100 members</li>
                            <li><i class="fas fa-check"></i> 5 events per month</li>
                            <li><i class="fas fa-check"></i> Basic analytics</li>
                            <li><i class="fas fa-check"></i> Community support</li>
                            <li class="nexus-feature-disabled"><i class="fas fa-times"></i> Custom branding</li>
                            <li class="nexus-feature-disabled"><i class="fas fa-times"></i> API access</li>
                        </ul>
                        <button class="nexus-pricing-cta nexus-pricing-cta-secondary">
                            Get Started Free
                        </button>
                    </div>

                    <!-- Pro Plan (Popular) -->
                    <div class="nexus-pricing-card nexus-pricing-card-popular">
                        <div class="nexus-popular-badge">Most Popular</div>
                        <div class="nexus-pricing-card-glow"></div>
                        <div class="nexus-pricing-card-header">
                            <div class="nexus-plan-icon">🚀</div>
                            <h3 class="nexus-plan-name">Pro</h3>
                            <p class="nexus-plan-desc">For growing communities</p>
                        </div>
                        <div class="nexus-pricing-card-price">
                            <span class="nexus-price-currency">$</span>
                            <span class="nexus-price-amount">29</span>
                            <span class="nexus-price-period">/month</span>
                        </div>
                        <ul class="nexus-pricing-features">
                            <li><i class="fas fa-check"></i> Unlimited members</li>
                            <li><i class="fas fa-check"></i> Unlimited events</li>
                            <li><i class="fas fa-check"></i> Advanced analytics</li>
                            <li><i class="fas fa-check"></i> Priority support</li>
                            <li><i class="fas fa-check"></i> Custom branding</li>
                            <li><i class="fas fa-check"></i> API access</li>
                        </ul>
                        <button class="nexus-pricing-cta nexus-pricing-cta-primary">
                            Start 14-Day Trial
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <p class="nexus-pricing-note">No credit card required</p>
                    </div>

                    <!-- Enterprise Plan -->
                    <div class="nexus-pricing-card nexus-pricing-card-enterprise">
                        <div class="nexus-pricing-card-header">
                            <div class="nexus-plan-icon">🏢</div>
                            <h3 class="nexus-plan-name">Enterprise</h3>
                            <p class="nexus-plan-desc">For large organizations</p>
                        </div>
                        <div class="nexus-pricing-card-price">
                            <span class="nexus-price-custom">Custom</span>
                        </div>
                        <ul class="nexus-pricing-features">
                            <li><i class="fas fa-check"></i> Everything in Pro</li>
                            <li><i class="fas fa-check"></i> Dedicated support</li>
                            <li><i class="fas fa-check"></i> SLA guarantee</li>
                            <li><i class="fas fa-check"></i> Custom integrations</li>
                            <li><i class="fas fa-check"></i> On-premise option</li>
                            <li><i class="fas fa-check"></i> Security audit</li>
                        </ul>
                        <button class="nexus-pricing-cta nexus-pricing-cta-enterprise">
                            Contact Sales
                        </button>
                    </div>
                </div>

                <!-- Trust Badges -->
                <div class="nexus-pricing-trust">
                    <div class="nexus-trust-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Enterprise-grade security</span>
                    </div>
                    <div class="nexus-trust-item">
                        <i class="fas fa-sync"></i>
                        <span>Cancel anytime</span>
                    </div>
                    <div class="nexus-trust-item">
                        <i class="fas fa-headset"></i>
                        <span>24/7 support</span>
                    </div>
                </div>
            </section>`
        });

        // 11. GALACTIC Timeline/Activity Feed - GitHub Style
        bm.add('activity-timeline', {
            label: '🌌 Timeline Feed',
            category: 'Desktop',
            attributes: { class: 'fa fa-stream' },
            content: `<div class="nexus-timeline-feed nexus-desktop-only">
                <div class="nexus-timeline-header">
                    <h3>Activity</h3>
                    <div class="nexus-timeline-filters">
                        <button class="nexus-timeline-filter active">All</button>
                        <button class="nexus-timeline-filter">Commits</button>
                        <button class="nexus-timeline-filter">Comments</button>
                    </div>
                </div>

                <div class="nexus-timeline">
                    <!-- Today -->
                    <div class="nexus-timeline-date">
                        <span>Today</span>
                    </div>

                    <!-- Commit event -->
                    <div class="nexus-timeline-item">
                        <div class="nexus-timeline-line"></div>
                        <div class="nexus-timeline-icon nexus-timeline-commit">
                            <i class="fas fa-code-commit"></i>
                        </div>
                        <div class="nexus-timeline-content">
                            <div class="nexus-timeline-actor">
                                <div class="nexus-actor-avatar" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">SC</div>
                                <span class="nexus-actor-name">Sarah Chen</span>
                                <span class="nexus-timeline-action">pushed to</span>
                                <span class="nexus-timeline-target">main</span>
                            </div>
                            <div class="nexus-timeline-commits">
                                <div class="nexus-commit-item">
                                    <span class="nexus-commit-sha">a1b2c3d</span>
                                    <span class="nexus-commit-msg">feat: Add dark mode toggle</span>
                                </div>
                                <div class="nexus-commit-item">
                                    <span class="nexus-commit-sha">e4f5g6h</span>
                                    <span class="nexus-commit-msg">fix: Resolve mobile nav issue</span>
                                </div>
                            </div>
                            <span class="nexus-timeline-time">2 hours ago</span>
                        </div>
                    </div>

                    <!-- Comment event -->
                    <div class="nexus-timeline-item">
                        <div class="nexus-timeline-line"></div>
                        <div class="nexus-timeline-icon nexus-timeline-comment">
                            <i class="fas fa-comment"></i>
                        </div>
                        <div class="nexus-timeline-content">
                            <div class="nexus-timeline-actor">
                                <div class="nexus-actor-avatar" style="background: linear-gradient(135deg, #10b981, #34d399);">MK</div>
                                <span class="nexus-actor-name">Mike Kim</span>
                                <span class="nexus-timeline-action">commented on</span>
                                <span class="nexus-timeline-target">#42</span>
                            </div>
                            <div class="nexus-timeline-comment-box">
                                <p>Looks great! Just one suggestion - could we add a transition animation?</p>
                            </div>
                            <span class="nexus-timeline-time">3 hours ago</span>
                        </div>
                    </div>

                    <!-- Merge event -->
                    <div class="nexus-timeline-item">
                        <div class="nexus-timeline-line"></div>
                        <div class="nexus-timeline-icon nexus-timeline-merge">
                            <i class="fas fa-code-merge"></i>
                        </div>
                        <div class="nexus-timeline-content">
                            <div class="nexus-timeline-actor">
                                <div class="nexus-actor-avatar" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">AL</div>
                                <span class="nexus-actor-name">Alex Lee</span>
                                <span class="nexus-timeline-action">merged</span>
                                <span class="nexus-timeline-target">feature/auth</span>
                                <span class="nexus-timeline-action">into</span>
                                <span class="nexus-timeline-target">main</span>
                            </div>
                            <div class="nexus-timeline-pr-card">
                                <span class="nexus-pr-status nexus-pr-merged"><i class="fas fa-check"></i></span>
                                <span class="nexus-pr-title">Add OAuth2 authentication</span>
                                <span class="nexus-pr-number">#38</span>
                            </div>
                            <span class="nexus-timeline-time">5 hours ago</span>
                        </div>
                    </div>

                    <!-- Yesterday -->
                    <div class="nexus-timeline-date">
                        <span>Yesterday</span>
                    </div>

                    <!-- Release event -->
                    <div class="nexus-timeline-item">
                        <div class="nexus-timeline-line"></div>
                        <div class="nexus-timeline-icon nexus-timeline-release">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div class="nexus-timeline-content">
                            <div class="nexus-timeline-actor">
                                <div class="nexus-actor-avatar" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">JD</div>
                                <span class="nexus-actor-name">John Doe</span>
                                <span class="nexus-timeline-action">released</span>
                                <span class="nexus-timeline-target">v2.1.0</span>
                            </div>
                            <div class="nexus-timeline-release-card">
                                <div class="nexus-release-header">
                                    <span class="nexus-release-tag">v2.1.0</span>
                                    <span class="nexus-release-badge">Latest</span>
                                </div>
                                <p class="nexus-release-notes">New features, bug fixes, and performance improvements</p>
                                <div class="nexus-release-assets">
                                    <a href="#" class="nexus-release-asset"><i class="fas fa-file-archive"></i> Source code (zip)</a>
                                </div>
                            </div>
                            <span class="nexus-timeline-time">Yesterday at 4:30 PM</span>
                        </div>
                    </div>
                </div>

                <button class="nexus-timeline-load-more">
                    <i class="fas fa-history"></i> Load more activity
                </button>
            </div>`
        });

        // 12. GALACTIC Testimonial Carousel - Premium Slider
        bm.add('testimonial-carousel', {
            label: '🌌 Testimonials',
            category: 'Desktop',
            attributes: { class: 'fa fa-quote-right' },
            content: `<section class="nexus-section nexus-testimonials-section">
                <div class="nexus-testimonials-header">
                    <span class="nexus-testimonials-badge">Testimonials</span>
                    <h2 class="nexus-testimonials-title">Loved by Communities Worldwide</h2>
                    <p class="nexus-testimonials-subtitle">See what our users have to say</p>
                </div>

                <div class="nexus-testimonials-carousel">
                    <!-- Testimonial 1 -->
                    <div class="nexus-testimonial-card nexus-testimonial-active">
                        <div class="nexus-testimonial-glow"></div>
                        <div class="nexus-testimonial-quote">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="nexus-testimonial-text">
                            "Nexus transformed how we connect with our members. The engagement has increased by 300% since we switched. Absolutely incredible platform!"
                        </p>
                        <div class="nexus-testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="nexus-testimonial-author">
                            <div class="nexus-testimonial-avatar" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                <span>SC</span>
                            </div>
                            <div class="nexus-testimonial-author-info">
                                <span class="nexus-author-name">Sarah Chen</span>
                                <span class="nexus-author-title">Community Manager</span>
                                <span class="nexus-author-company">TechHub Global</span>
                            </div>
                        </div>
                        <div class="nexus-testimonial-company-logo">
                            <span>TechHub</span>
                        </div>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="nexus-testimonial-card">
                        <div class="nexus-testimonial-glow"></div>
                        <div class="nexus-testimonial-quote">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="nexus-testimonial-text">
                            "The best investment we made for our community. The features are top-notch and the support team is amazing. Highly recommended!"
                        </p>
                        <div class="nexus-testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="nexus-testimonial-author">
                            <div class="nexus-testimonial-avatar" style="background: linear-gradient(135deg, #10b981, #34d399);">
                                <span>MK</span>
                            </div>
                            <div class="nexus-testimonial-author-info">
                                <span class="nexus-author-name">Mike Kim</span>
                                <span class="nexus-author-title">Founder</span>
                                <span class="nexus-author-company">DevCommunity</span>
                            </div>
                        </div>
                        <div class="nexus-testimonial-company-logo">
                            <span>DevCom</span>
                        </div>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="nexus-testimonial-card">
                        <div class="nexus-testimonial-glow"></div>
                        <div class="nexus-testimonial-quote">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="nexus-testimonial-text">
                            "We've tried many platforms but none come close to Nexus. The interface is beautiful and our members love the mobile experience."
                        </p>
                        <div class="nexus-testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <div class="nexus-testimonial-author">
                            <div class="nexus-testimonial-avatar" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                                <span>EW</span>
                            </div>
                            <div class="nexus-testimonial-author-info">
                                <span class="nexus-author-name">Emma Wilson</span>
                                <span class="nexus-author-title">Director</span>
                                <span class="nexus-author-company">Creative Collective</span>
                            </div>
                        </div>
                        <div class="nexus-testimonial-company-logo">
                            <span>CC</span>
                        </div>
                    </div>
                </div>

                <!-- Carousel Controls -->
                <div class="nexus-carousel-controls">
                    <button class="nexus-carousel-btn nexus-carousel-prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="nexus-carousel-dots">
                        <span class="nexus-carousel-dot active"></span>
                        <span class="nexus-carousel-dot"></span>
                        <span class="nexus-carousel-dot"></span>
                    </div>
                    <button class="nexus-carousel-btn nexus-carousel-next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <!-- Stats -->
                <div class="nexus-testimonials-stats">
                    <div class="nexus-stat-card">
                        <span class="nexus-stat-number">10,000+</span>
                        <span class="nexus-stat-label">Happy Communities</span>
                    </div>
                    <div class="nexus-stat-card">
                        <span class="nexus-stat-number">4.9/5</span>
                        <span class="nexus-stat-label">Average Rating</span>
                    </div>
                    <div class="nexus-stat-card">
                        <span class="nexus-stat-number">99.9%</span>
                        <span class="nexus-stat-label">Uptime</span>
                    </div>
                </div>
            </section>`
        });

        // 13. GALACTIC Kanban Board - Trello/Linear Style
        bm.add('kanban-board', {
            label: '🌌 Kanban Board',
            category: 'Desktop',
            attributes: { class: 'fa fa-columns' },
            content: `<div class="nexus-kanban-board nexus-desktop-only">
                <div class="nexus-kanban-header">
                    <h3>Project Board</h3>
                    <div class="nexus-kanban-actions">
                        <button class="nexus-kanban-filter">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button class="nexus-kanban-add">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                </div>

                <div class="nexus-kanban-columns">
                    <!-- To Do Column -->
                    <div class="nexus-kanban-column">
                        <div class="nexus-column-header">
                            <div class="nexus-column-title">
                                <span class="nexus-column-dot" style="background: #94a3b8;"></span>
                                <span>To Do</span>
                                <span class="nexus-column-count">3</span>
                            </div>
                            <button class="nexus-column-add"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="nexus-column-cards">
                            <div class="nexus-kanban-card">
                                <div class="nexus-card-labels">
                                    <span class="nexus-card-label" style="background: #ef4444;">Bug</span>
                                </div>
                                <h4 class="nexus-card-title">Fix mobile navigation</h4>
                                <p class="nexus-card-desc">Menu doesn't close on route change</p>
                                <div class="nexus-card-footer">
                                    <div class="nexus-card-assignees">
                                        <div class="nexus-card-assignee" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">SC</div>
                                    </div>
                                    <div class="nexus-card-meta">
                                        <span><i class="fas fa-comment"></i> 2</span>
                                        <span><i class="fas fa-paperclip"></i> 1</span>
                                    </div>
                                </div>
                            </div>
                            <div class="nexus-kanban-card">
                                <div class="nexus-card-labels">
                                    <span class="nexus-card-label" style="background: #6366f1;">Feature</span>
                                    <span class="nexus-card-label" style="background: #f59e0b;">High</span>
                                </div>
                                <h4 class="nexus-card-title">Add dark mode support</h4>
                                <div class="nexus-card-checklist">
                                    <div class="nexus-checklist-progress" style="width: 33%;"></div>
                                    <span>1/3</span>
                                </div>
                                <div class="nexus-card-footer">
                                    <div class="nexus-card-assignees">
                                        <div class="nexus-card-assignee" style="background: linear-gradient(135deg, #10b981, #34d399);">MK</div>
                                        <div class="nexus-card-assignee" style="background: linear-gradient(135deg, #ec4899, #f472b6);">EW</div>
                                    </div>
                                    <span class="nexus-card-due"><i class="fas fa-clock"></i> Mar 15</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="nexus-kanban-column">
                        <div class="nexus-column-header">
                            <div class="nexus-column-title">
                                <span class="nexus-column-dot" style="background: #3b82f6;"></span>
                                <span>In Progress</span>
                                <span class="nexus-column-count">2</span>
                            </div>
                            <button class="nexus-column-add"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="nexus-column-cards">
                            <div class="nexus-kanban-card nexus-card-priority">
                                <div class="nexus-card-priority-bar"></div>
                                <div class="nexus-card-labels">
                                    <span class="nexus-card-label" style="background: #8b5cf6;">Enhancement</span>
                                </div>
                                <h4 class="nexus-card-title">Redesign settings page</h4>
                                <div class="nexus-card-image">
                                    <i class="fas fa-image"></i>
                                </div>
                                <div class="nexus-card-footer">
                                    <div class="nexus-card-assignees">
                                        <div class="nexus-card-assignee" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">AL</div>
                                    </div>
                                    <span class="nexus-card-due nexus-due-soon"><i class="fas fa-clock"></i> Tomorrow</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Review Column -->
                    <div class="nexus-kanban-column">
                        <div class="nexus-column-header">
                            <div class="nexus-column-title">
                                <span class="nexus-column-dot" style="background: #f59e0b;"></span>
                                <span>Review</span>
                                <span class="nexus-column-count">1</span>
                            </div>
                            <button class="nexus-column-add"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="nexus-column-cards">
                            <div class="nexus-kanban-card">
                                <div class="nexus-card-labels">
                                    <span class="nexus-card-label" style="background: #10b981;">Improvement</span>
                                </div>
                                <h4 class="nexus-card-title">Optimize image loading</h4>
                                <div class="nexus-card-pr-link">
                                    <i class="fas fa-code-pull-request"></i>
                                    <span>PR #142</span>
                                    <span class="nexus-pr-checks"><i class="fas fa-check-circle"></i></span>
                                </div>
                                <div class="nexus-card-footer">
                                    <div class="nexus-card-assignees">
                                        <div class="nexus-card-assignee" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">SC</div>
                                    </div>
                                    <div class="nexus-card-meta">
                                        <span><i class="fas fa-comment"></i> 5</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Done Column -->
                    <div class="nexus-kanban-column nexus-column-done">
                        <div class="nexus-column-header">
                            <div class="nexus-column-title">
                                <span class="nexus-column-dot" style="background: #22c55e;"></span>
                                <span>Done</span>
                                <span class="nexus-column-count">4</span>
                            </div>
                        </div>
                        <div class="nexus-column-cards">
                            <div class="nexus-kanban-card nexus-card-completed">
                                <h4 class="nexus-card-title"><i class="fas fa-check"></i> Setup CI/CD pipeline</h4>
                                <div class="nexus-card-footer">
                                    <div class="nexus-card-assignees">
                                        <div class="nexus-card-assignee" style="background: linear-gradient(135deg, #10b981, #34d399);">MK</div>
                                    </div>
                                    <span class="nexus-completed-date">Completed Mar 10</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // ============================================
        // 🌌✨ GALAXY-TIER BLOCKS - COSMIC LEVEL ✨🌌
        // The best page builder blocks in the ENTIRE GALAXY
        // ============================================

        // 🌌 Cosmic Particle Hero - Floating particles with parallax
        bm.add('cosmic-hero', {
            label: '🌌 Cosmic Hero',
            category: 'Galaxy',
            attributes: { class: 'fa fa-star' },
            content: `<section class="nexus-cosmic-hero">
                <div class="cosmic-particles" id="cosmic-particles-hero">
                    <div class="cosmic-particle" style="--delay: 0s; --duration: 20s; --x: 10%;"></div>
                    <div class="cosmic-particle" style="--delay: 2s; --duration: 25s; --x: 20%;"></div>
                    <div class="cosmic-particle" style="--delay: 4s; --duration: 18s; --x: 30%;"></div>
                    <div class="cosmic-particle" style="--delay: 1s; --duration: 22s; --x: 40%;"></div>
                    <div class="cosmic-particle" style="--delay: 3s; --duration: 28s; --x: 50%;"></div>
                    <div class="cosmic-particle" style="--delay: 5s; --duration: 24s; --x: 60%;"></div>
                    <div class="cosmic-particle" style="--delay: 2s; --duration: 19s; --x: 70%;"></div>
                    <div class="cosmic-particle" style="--delay: 4s; --duration: 26s; --x: 80%;"></div>
                    <div class="cosmic-particle" style="--delay: 1s; --duration: 21s; --x: 90%;"></div>
                </div>
                <div class="cosmic-aurora"></div>
                <div class="cosmic-hero-content">
                    <div class="cosmic-badge">
                        <span class="cosmic-badge-dot"></span>
                        <span>Welcome to the Future</span>
                    </div>
                    <h1 class="cosmic-title">
                        <span class="cosmic-title-line">Build Something</span>
                        <span class="cosmic-title-gradient">Extraordinary</span>
                    </h1>
                    <p class="cosmic-subtitle">Create stunning experiences that transcend the ordinary. Powered by the most advanced page builder in the galaxy.</p>
                    <div class="cosmic-cta-group">
                        <a href="#" class="cosmic-btn-primary">
                            <span>Get Started</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a href="#" class="cosmic-btn-secondary">
                            <i class="fas fa-play"></i>
                            <span>Watch Demo</span>
                        </a>
                    </div>
                    <div class="cosmic-stats-row">
                        <div class="cosmic-stat">
                            <span class="cosmic-stat-number" data-target="50000">0</span>
                            <span class="cosmic-stat-label">Active Users</span>
                        </div>
                        <div class="cosmic-stat-divider"></div>
                        <div class="cosmic-stat">
                            <span class="cosmic-stat-number" data-target="99">0</span>
                            <span class="cosmic-stat-suffix">%</span>
                            <span class="cosmic-stat-label">Satisfaction</span>
                        </div>
                        <div class="cosmic-stat-divider"></div>
                        <div class="cosmic-stat">
                            <span class="cosmic-stat-number" data-target="247">0</span>
                            <span class="cosmic-stat-label">Countries</span>
                        </div>
                    </div>
                </div>
                <div class="cosmic-scroll-indicator">
                    <div class="cosmic-mouse">
                        <div class="cosmic-mouse-wheel"></div>
                    </div>
                    <span>Scroll to explore</span>
                </div>
            </section>`
        });

        // 🤖 AI Chat Interface - ChatGPT/Claude style
        bm.add('ai-chat', {
            label: '🤖 AI Chat Interface',
            category: 'Galaxy',
            attributes: { class: 'fa fa-robot' },
            content: `<div class="nexus-ai-chat-demo">
                <div class="ai-chat-container">
                    <div class="ai-chat-header">
                        <div class="ai-chat-header-left">
                            <div class="ai-chat-logo">
                                <div class="ai-logo-orb">
                                    <div class="ai-logo-ring"></div>
                                    <i class="fas fa-bolt"></i>
                                </div>
                            </div>
                            <div class="ai-chat-header-info">
                                <h3>NEXUS AI</h3>
                                <span class="ai-status"><span class="ai-status-dot"></span> Online</span>
                            </div>
                        </div>
                        <div class="ai-chat-header-actions">
                            <button class="ai-header-btn"><i class="fas fa-history"></i></button>
                            <button class="ai-header-btn"><i class="fas fa-cog"></i></button>
                        </div>
                    </div>
                    <div class="ai-chat-messages">
                        <div class="ai-message ai-message-assistant">
                            <div class="ai-message-avatar">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="ai-message-content">
                                <div class="ai-message-bubble ai-typing-effect">
                                    <p>Hello! I'm NEXUS AI, your intelligent assistant. I can help you with:</p>
                                    <ul class="ai-capabilities">
                                        <li><i class="fas fa-code"></i> Code generation & debugging</li>
                                        <li><i class="fas fa-lightbulb"></i> Creative brainstorming</li>
                                        <li><i class="fas fa-chart-line"></i> Data analysis</li>
                                        <li><i class="fas fa-language"></i> Translation & writing</li>
                                    </ul>
                                    <p>How can I assist you today?</p>
                                </div>
                                <span class="ai-message-time">Just now</span>
                            </div>
                        </div>
                        <div class="ai-message ai-message-user">
                            <div class="ai-message-content">
                                <div class="ai-message-bubble">
                                    <p>Can you help me build a landing page?</p>
                                </div>
                                <span class="ai-message-time">1 min ago</span>
                            </div>
                        </div>
                        <div class="ai-message ai-message-assistant">
                            <div class="ai-message-avatar">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="ai-message-content">
                                <div class="ai-message-bubble">
                                    <p>Absolutely! I'd love to help you create a stunning landing page. Here's what we can do:</p>
                                    <div class="ai-code-block">
                                        <div class="ai-code-header">
                                            <span><i class="fas fa-code"></i> HTML Structure</span>
                                            <button class="ai-copy-btn"><i class="fas fa-copy"></i> Copy</button>
                                        </div>
                                        <pre><code>&lt;section class="hero"&gt;
  &lt;h1&gt;Your Amazing Product&lt;/h1&gt;
  &lt;p&gt;Compelling description here&lt;/p&gt;
  &lt;button&gt;Get Started&lt;/button&gt;
&lt;/section&gt;</code></pre>
                                    </div>
                                    <p>Would you like me to expand on this with styling?</p>
                                </div>
                                <div class="ai-message-actions">
                                    <button class="ai-action-btn"><i class="fas fa-thumbs-up"></i></button>
                                    <button class="ai-action-btn"><i class="fas fa-thumbs-down"></i></button>
                                    <button class="ai-action-btn"><i class="fas fa-copy"></i></button>
                                    <button class="ai-action-btn"><i class="fas fa-redo"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ai-chat-input-area">
                        <div class="ai-suggestions">
                            <button class="ai-suggestion">Add animations</button>
                            <button class="ai-suggestion">Make it responsive</button>
                            <button class="ai-suggestion">Add dark mode</button>
                        </div>
                        <div class="ai-input-container">
                            <button class="ai-attach-btn"><i class="fas fa-paperclip"></i></button>
                            <input type="text" placeholder="Ask me anything..." class="ai-input">
                            <div class="ai-input-actions">
                                <button class="ai-voice-btn"><i class="fas fa-microphone"></i></button>
                                <button class="ai-send-btn"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </div>
                        <div class="ai-input-footer">
                            <span><i class="fas fa-shield-alt"></i> Your messages are encrypted</span>
                            <span>NEXUS AI v2.0</span>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // 📊 Animated Data Charts - Glowing animated charts
        bm.add('data-charts', {
            label: '📊 Data Charts',
            category: 'Galaxy',
            attributes: { class: 'fa fa-chart-bar' },
            content: `<div class="nexus-charts-demo">
                <div class="charts-header">
                    <h2 class="charts-title">Analytics Dashboard</h2>
                    <div class="charts-period-selector">
                        <button class="period-btn active">7D</button>
                        <button class="period-btn">30D</button>
                        <button class="period-btn">90D</button>
                        <button class="period-btn">1Y</button>
                    </div>
                </div>
                <div class="charts-grid">
                    <!-- Main Chart -->
                    <div class="chart-card chart-card-large">
                        <div class="chart-card-header">
                            <div>
                                <h3>Revenue Overview</h3>
                                <div class="chart-value-row">
                                    <span class="chart-big-value">$48,352</span>
                                    <span class="chart-change positive"><i class="fas fa-arrow-up"></i> 12.5%</span>
                                </div>
                            </div>
                            <button class="chart-menu-btn"><i class="fas fa-ellipsis-v"></i></button>
                        </div>
                        <div class="chart-area-container">
                            <svg class="chart-area-svg" viewBox="0 0 400 150" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:rgba(139, 92, 246, 0.4)"/>
                                        <stop offset="100%" style="stop-color:rgba(139, 92, 246, 0)"/>
                                    </linearGradient>
                                    <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#8b5cf6"/>
                                        <stop offset="100%" style="stop-color:#06b6d4"/>
                                    </linearGradient>
                                </defs>
                                <path class="chart-area-fill" d="M0,120 Q50,100 100,80 T200,60 T300,40 T400,50 L400,150 L0,150 Z" fill="url(#areaGradient)"/>
                                <path class="chart-area-line" d="M0,120 Q50,100 100,80 T200,60 T300,40 T400,50" stroke="url(#lineGradient)" stroke-width="3" fill="none"/>
                                <circle class="chart-dot" cx="400" cy="50" r="6" fill="#8b5cf6"/>
                            </svg>
                            <div class="chart-tooltip">
                                <span class="tooltip-date">Dec 15</span>
                                <span class="tooltip-value">$8,420</span>
                            </div>
                        </div>
                        <div class="chart-labels">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                    </div>

                    <!-- Donut Chart -->
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <h3>Traffic Sources</h3>
                        </div>
                        <div class="donut-container">
                            <svg class="donut-svg" viewBox="0 0 120 120">
                                <circle class="donut-ring" cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="12"/>
                                <circle class="donut-segment donut-segment-1" cx="60" cy="60" r="50" fill="none" stroke="#8b5cf6" stroke-width="12" stroke-dasharray="110 204" stroke-dashoffset="0"/>
                                <circle class="donut-segment donut-segment-2" cx="60" cy="60" r="50" fill="none" stroke="#06b6d4" stroke-width="12" stroke-dasharray="75 239" stroke-dashoffset="-110"/>
                                <circle class="donut-segment donut-segment-3" cx="60" cy="60" r="50" fill="none" stroke="#22c55e" stroke-width="12" stroke-dasharray="50 264" stroke-dashoffset="-185"/>
                                <circle class="donut-segment donut-segment-4" cx="60" cy="60" r="50" fill="none" stroke="#f59e0b" stroke-width="12" stroke-dasharray="29 285" stroke-dashoffset="-235"/>
                            </svg>
                            <div class="donut-center">
                                <span class="donut-total">12.4K</span>
                                <span class="donut-label">Visitors</span>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <div class="legend-item"><span class="legend-dot" style="background:#8b5cf6"></span>Organic (35%)</div>
                            <div class="legend-item"><span class="legend-dot" style="background:#06b6d4"></span>Direct (24%)</div>
                            <div class="legend-item"><span class="legend-dot" style="background:#22c55e"></span>Referral (16%)</div>
                            <div class="legend-item"><span class="legend-dot" style="background:#f59e0b"></span>Social (9%)</div>
                        </div>
                    </div>

                    <!-- Bar Chart -->
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <h3>Top Products</h3>
                        </div>
                        <div class="bar-chart-container">
                            <div class="bar-row">
                                <span class="bar-label">Pro Plan</span>
                                <div class="bar-track">
                                    <div class="bar-fill bar-fill-1" style="--width: 85%;">
                                        <span class="bar-value">$24,500</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bar-row">
                                <span class="bar-label">Team Plan</span>
                                <div class="bar-track">
                                    <div class="bar-fill bar-fill-2" style="--width: 65%;">
                                        <span class="bar-value">$18,200</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bar-row">
                                <span class="bar-label">Starter</span>
                                <div class="bar-track">
                                    <div class="bar-fill bar-fill-3" style="--width: 45%;">
                                        <span class="bar-value">$12,100</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bar-row">
                                <span class="bar-label">Enterprise</span>
                                <div class="bar-track">
                                    <div class="bar-fill bar-fill-4" style="--width: 30%;">
                                        <span class="bar-value">$8,400</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // 🔄 3D Flip Cards - True 3D perspective transforms
        bm.add('flip-cards', {
            label: '🔄 3D Flip Cards',
            category: 'Galaxy',
            attributes: { class: 'fa fa-clone' },
            content: `<div class="nexus-flip-cards-demo">
                <h2 class="flip-cards-title">Our Services</h2>
                <p class="flip-cards-subtitle">Hover or tap to reveal more</p>
                <div class="flip-cards-grid">
                    <div class="flip-card">
                        <div class="flip-card-inner">
                            <div class="flip-card-front">
                                <div class="flip-card-icon">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <h3>Launch Fast</h3>
                                <p>Deploy in minutes</p>
                                <div class="flip-hint">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Flip for details</span>
                                </div>
                            </div>
                            <div class="flip-card-back">
                                <h3>Launch Fast</h3>
                                <p>Our streamlined deployment process gets your project live in minutes, not days. With automated CI/CD pipelines and one-click deployments.</p>
                                <ul class="flip-features">
                                    <li><i class="fas fa-check"></i> One-click deploy</li>
                                    <li><i class="fas fa-check"></i> Auto-scaling</li>
                                    <li><i class="fas fa-check"></i> Zero downtime</li>
                                </ul>
                                <a href="#" class="flip-btn">Learn More <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="flip-card">
                        <div class="flip-card-inner">
                            <div class="flip-card-front flip-front-cyan">
                                <div class="flip-card-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h3>Stay Secure</h3>
                                <p>Enterprise security</p>
                                <div class="flip-hint">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Flip for details</span>
                                </div>
                            </div>
                            <div class="flip-card-back flip-back-cyan">
                                <h3>Stay Secure</h3>
                                <p>Bank-grade encryption and security protocols protect your data. SOC2 compliant with 24/7 monitoring and threat detection.</p>
                                <ul class="flip-features">
                                    <li><i class="fas fa-check"></i> End-to-end encryption</li>
                                    <li><i class="fas fa-check"></i> SOC2 certified</li>
                                    <li><i class="fas fa-check"></i> 24/7 monitoring</li>
                                </ul>
                                <a href="#" class="flip-btn">Learn More <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="flip-card">
                        <div class="flip-card-inner">
                            <div class="flip-card-front flip-front-green">
                                <div class="flip-card-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h3>Scale Easy</h3>
                                <p>Grow without limits</p>
                                <div class="flip-hint">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Flip for details</span>
                                </div>
                            </div>
                            <div class="flip-card-back flip-back-green">
                                <h3>Scale Easy</h3>
                                <p>From startup to enterprise, our infrastructure scales with you. Handle millions of users with automatic load balancing.</p>
                                <ul class="flip-features">
                                    <li><i class="fas fa-check"></i> Auto-scaling</li>
                                    <li><i class="fas fa-check"></i> Global CDN</li>
                                    <li><i class="fas fa-check"></i> 99.99% uptime</li>
                                </ul>
                                <a href="#" class="flip-btn">Learn More <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // ✨ Morphing Hero Text - Animated text morphing
        bm.add('morphing-text', {
            label: '✨ Morphing Text',
            category: 'Galaxy',
            attributes: { class: 'fa fa-font' },
            content: `<section class="nexus-morphing-hero">
                <div class="morphing-bg-shapes">
                    <div class="morph-shape morph-shape-1"></div>
                    <div class="morph-shape morph-shape-2"></div>
                    <div class="morph-shape morph-shape-3"></div>
                </div>
                <div class="morphing-content">
                    <h1 class="morphing-title">
                        <span class="morphing-static">We build</span>
                        <span class="morphing-words">
                            <span class="morphing-word active">websites</span>
                            <span class="morphing-word">applications</span>
                            <span class="morphing-word">experiences</span>
                            <span class="morphing-word">the future</span>
                        </span>
                    </h1>
                    <p class="morphing-subtitle">Transforming ideas into digital masterpieces with cutting-edge technology and creative excellence.</p>
                    <div class="morphing-cta">
                        <a href="#" class="morph-btn-primary">Start Your Journey</a>
                        <a href="#" class="morph-btn-ghost">View Portfolio</a>
                    </div>
                </div>
                <div class="morphing-brands">
                    <span class="brands-label">Trusted by industry leaders</span>
                    <div class="brands-logos">
                        <div class="brand-logo"><i class="fab fa-google"></i></div>
                        <div class="brand-logo"><i class="fab fa-microsoft"></i></div>
                        <div class="brand-logo"><i class="fab fa-apple"></i></div>
                        <div class="brand-logo"><i class="fab fa-amazon"></i></div>
                        <div class="brand-logo"><i class="fab fa-meta"></i></div>
                    </div>
                </div>
            </section>`
        });

        // 🎯 Floating Radial Menu - Explosive radial navigation
        bm.add('radial-menu', {
            label: '🎯 Radial Menu',
            category: 'Galaxy',
            attributes: { class: 'fa fa-bullseye' },
            content: `<div class="nexus-radial-demo">
                <p class="radial-demo-text">Click the button to expand the radial menu</p>
                <div class="radial-menu-container">
                    <button class="radial-trigger" onclick="this.parentElement.classList.toggle('open')">
                        <i class="fas fa-plus radial-icon-open"></i>
                        <i class="fas fa-times radial-icon-close"></i>
                    </button>
                    <div class="radial-items">
                        <a href="#" class="radial-item" style="--i: 0; --color: #8b5cf6;">
                            <i class="fas fa-home"></i>
                            <span class="radial-tooltip">Home</span>
                        </a>
                        <a href="#" class="radial-item" style="--i: 1; --color: #06b6d4;">
                            <i class="fas fa-user"></i>
                            <span class="radial-tooltip">Profile</span>
                        </a>
                        <a href="#" class="radial-item" style="--i: 2; --color: #22c55e;">
                            <i class="fas fa-cog"></i>
                            <span class="radial-tooltip">Settings</span>
                        </a>
                        <a href="#" class="radial-item" style="--i: 3; --color: #f59e0b;">
                            <i class="fas fa-bell"></i>
                            <span class="radial-tooltip">Notifications</span>
                        </a>
                        <a href="#" class="radial-item" style="--i: 4; --color: #ef4444;">
                            <i class="fas fa-heart"></i>
                            <span class="radial-tooltip">Favorites</span>
                        </a>
                        <a href="#" class="radial-item" style="--i: 5; --color: #ec4899;">
                            <i class="fas fa-envelope"></i>
                            <span class="radial-tooltip">Messages</span>
                        </a>
                    </div>
                    <div class="radial-ring"></div>
                </div>
            </div>`
        });

        // 🏔️ Parallax Section - Multi-layer depth scrolling
        bm.add('parallax-section', {
            label: '🏔️ Parallax Section',
            category: 'Galaxy',
            attributes: { class: 'fa fa-layer-group' },
            content: `<section class="nexus-parallax-section">
                <div class="parallax-layer parallax-layer-back">
                    <div class="parallax-stars"></div>
                </div>
                <div class="parallax-layer parallax-layer-mid">
                    <div class="parallax-mountains">
                        <svg viewBox="0 0 1440 320" preserveAspectRatio="none">
                            <path fill="rgba(139, 92, 246, 0.3)" d="M0,192L60,186.7C120,181,240,171,360,181.3C480,192,600,224,720,213.3C840,203,960,149,1080,138.7C1200,128,1320,160,1380,176L1440,192L1440,320L1380,320C1320,320,1200,320,1080,320C960,320,840,320,720,320C600,320,480,320,360,320C240,320,120,320,60,320L0,320Z"></path>
                        </svg>
                    </div>
                </div>
                <div class="parallax-layer parallax-layer-front">
                    <div class="parallax-mountains-front">
                        <svg viewBox="0 0 1440 320" preserveAspectRatio="none">
                            <path fill="rgba(6, 182, 212, 0.2)" d="M0,256L48,234.7C96,213,192,171,288,176C384,181,480,235,576,234.7C672,235,768,181,864,176C960,171,1056,213,1152,218.7C1248,224,1344,192,1392,176L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                        </svg>
                    </div>
                </div>
                <div class="parallax-content">
                    <h2 class="parallax-title">Immersive Experiences</h2>
                    <p class="parallax-text">Scroll to see the parallax depth effect in action. Each layer moves at a different speed, creating a stunning 3D illusion.</p>
                    <a href="#" class="parallax-btn">Explore More</a>
                </div>
                <div class="parallax-floating-elements">
                    <div class="floating-orb orb-1"></div>
                    <div class="floating-orb orb-2"></div>
                    <div class="floating-orb orb-3"></div>
                </div>
            </section>`
        });

        // 🔢 Animated Counters - Numbers with easing animations
        bm.add('animated-counters', {
            label: '🔢 Animated Counters',
            category: 'Galaxy',
            attributes: { class: 'fa fa-sort-numeric-up' },
            content: `<section class="nexus-counters-section">
                <div class="counters-bg-pattern"></div>
                <div class="counters-header">
                    <h2>Our Impact in Numbers</h2>
                    <p>Real results that speak for themselves</p>
                </div>
                <div class="counters-grid">
                    <div class="counter-card">
                        <div class="counter-icon-wrap">
                            <div class="counter-icon-bg"></div>
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="counter-value">
                            <span class="counter-number" data-target="50000" data-suffix="+">0</span>
                        </div>
                        <div class="counter-label">Happy Customers</div>
                        <div class="counter-bar">
                            <div class="counter-bar-fill" style="--width: 95%;"></div>
                        </div>
                    </div>
                    <div class="counter-card">
                        <div class="counter-icon-wrap">
                            <div class="counter-icon-bg"></div>
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="counter-value">
                            <span class="counter-number" data-target="1200" data-suffix="">0</span>
                        </div>
                        <div class="counter-label">Projects Completed</div>
                        <div class="counter-bar">
                            <div class="counter-bar-fill" style="--width: 85%;"></div>
                        </div>
                    </div>
                    <div class="counter-card">
                        <div class="counter-icon-wrap">
                            <div class="counter-icon-bg"></div>
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="counter-value">
                            <span class="counter-number" data-target="99" data-suffix="%">0</span>
                        </div>
                        <div class="counter-label">Uptime Guarantee</div>
                        <div class="counter-bar">
                            <div class="counter-bar-fill" style="--width: 99%;"></div>
                        </div>
                    </div>
                    <div class="counter-card">
                        <div class="counter-icon-wrap">
                            <div class="counter-icon-bg"></div>
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="counter-value">
                            <span class="counter-number" data-target="24" data-suffix="/7">0</span>
                        </div>
                        <div class="counter-label">Support Available</div>
                        <div class="counter-bar">
                            <div class="counter-bar-fill" style="--width: 100%;"></div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🔀 Before/After Slider - Image comparison
        bm.add('comparison-slider', {
            label: '🔀 Comparison Slider',
            category: 'Galaxy',
            attributes: { class: 'fa fa-columns' },
            content: `<div class="nexus-comparison-demo">
                <h2 class="comparison-title">See the Difference</h2>
                <p class="comparison-subtitle">Drag the slider to compare before and after</p>
                <div class="comparison-container">
                    <div class="comparison-wrapper">
                        <div class="comparison-before">
                            <div class="comparison-image comparison-image-before" style="background: linear-gradient(135deg, #374151, #1f2937);">
                                <div class="comparison-placeholder">
                                    <i class="fas fa-image"></i>
                                    <span>Before Image</span>
                                    <span class="placeholder-hint">Replace with your image</span>
                                </div>
                            </div>
                            <span class="comparison-label comparison-label-before">Before</span>
                        </div>
                        <div class="comparison-after">
                            <div class="comparison-image comparison-image-after" style="background: linear-gradient(135deg, #8b5cf6, #06b6d4);">
                                <div class="comparison-placeholder">
                                    <i class="fas fa-magic"></i>
                                    <span>After Image</span>
                                    <span class="placeholder-hint">Replace with your image</span>
                                </div>
                            </div>
                            <span class="comparison-label comparison-label-after">After</span>
                        </div>
                        <div class="comparison-handle">
                            <div class="comparison-handle-line"></div>
                            <div class="comparison-handle-circle">
                                <i class="fas fa-arrows-alt-h"></i>
                            </div>
                            <div class="comparison-handle-line"></div>
                        </div>
                    </div>
                </div>
            </div>`
        });

        // 🌊 Liquid Blob Section - Morphing blob background
        bm.add('liquid-section', {
            label: '🌊 Liquid Section',
            category: 'Galaxy',
            attributes: { class: 'fa fa-water' },
            content: `<section class="nexus-liquid-section">
                <div class="liquid-blobs">
                    <div class="liquid-blob blob-1"></div>
                    <div class="liquid-blob blob-2"></div>
                    <div class="liquid-blob blob-3"></div>
                </div>
                <div class="liquid-content">
                    <span class="liquid-overline">Creative Solutions</span>
                    <h2 class="liquid-title">Fluid Design.<br/>Solid Results.</h2>
                    <p class="liquid-text">Our designs flow naturally, adapting to every screen and every user. Like water, we find the path of least resistance to create seamless experiences.</p>
                    <div class="liquid-features">
                        <div class="liquid-feature">
                            <div class="liquid-feature-icon"><i class="fas fa-mobile-alt"></i></div>
                            <span>Responsive</span>
                        </div>
                        <div class="liquid-feature">
                            <div class="liquid-feature-icon"><i class="fas fa-bolt"></i></div>
                            <span>Fast</span>
                        </div>
                        <div class="liquid-feature">
                            <div class="liquid-feature-icon"><i class="fas fa-universal-access"></i></div>
                            <span>Accessible</span>
                        </div>
                    </div>
                    <a href="#" class="liquid-btn">
                        <span>Get Started</span>
                        <div class="liquid-btn-bg"></div>
                    </a>
                </div>
            </section>`
        });

        // 🎭 Glassmorphism Cards - Ultimate glass effect
        bm.add('glass-cards', {
            label: '🎭 Glass Cards',
            category: 'Galaxy',
            attributes: { class: 'fa fa-square' },
            content: `<section class="nexus-glass-showcase">
                <div class="glass-bg-orbs">
                    <div class="glass-orb glass-orb-1"></div>
                    <div class="glass-orb glass-orb-2"></div>
                    <div class="glass-orb glass-orb-3"></div>
                </div>
                <h2 class="glass-section-title">Premium Features</h2>
                <div class="glass-cards-grid">
                    <div class="glass-card glass-card-featured">
                        <div class="glass-card-glow"></div>
                        <div class="glass-card-shine"></div>
                        <div class="glass-card-header">
                            <div class="glass-card-icon">
                                <i class="fas fa-crown"></i>
                            </div>
                            <span class="glass-card-badge">Popular</span>
                        </div>
                        <h3 class="glass-card-title">Pro Plan</h3>
                        <p class="glass-card-desc">Everything you need to build amazing products</p>
                        <ul class="glass-card-features">
                            <li><i class="fas fa-check"></i> Unlimited projects</li>
                            <li><i class="fas fa-check"></i> Priority support</li>
                            <li><i class="fas fa-check"></i> Advanced analytics</li>
                            <li><i class="fas fa-check"></i> Custom domains</li>
                        </ul>
                        <div class="glass-card-price">
                            <span class="glass-price-currency">$</span>
                            <span class="glass-price-amount">49</span>
                            <span class="glass-price-period">/month</span>
                        </div>
                        <button class="glass-card-btn">Get Started</button>
                    </div>
                    <div class="glass-card">
                        <div class="glass-card-shine"></div>
                        <div class="glass-card-header">
                            <div class="glass-card-icon glass-icon-cyan">
                                <i class="fas fa-rocket"></i>
                            </div>
                        </div>
                        <h3 class="glass-card-title">Starter</h3>
                        <p class="glass-card-desc">Perfect for getting started</p>
                        <ul class="glass-card-features">
                            <li><i class="fas fa-check"></i> 5 projects</li>
                            <li><i class="fas fa-check"></i> Email support</li>
                            <li><i class="fas fa-check"></i> Basic analytics</li>
                        </ul>
                        <div class="glass-card-price">
                            <span class="glass-price-currency">$</span>
                            <span class="glass-price-amount">19</span>
                            <span class="glass-price-period">/month</span>
                        </div>
                        <button class="glass-card-btn btn btn--ghost">Get Started</button>
                    </div>
                    <div class="glass-card">
                        <div class="glass-card-shine"></div>
                        <div class="glass-card-header">
                            <div class="glass-card-icon glass-icon-green">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <h3 class="glass-card-title">Enterprise</h3>
                        <p class="glass-card-desc">For large organizations</p>
                        <ul class="glass-card-features">
                            <li><i class="fas fa-check"></i> Unlimited everything</li>
                            <li><i class="fas fa-check"></i> Dedicated support</li>
                            <li><i class="fas fa-check"></i> SLA guarantee</li>
                            <li><i class="fas fa-check"></i> Custom contracts</li>
                        </ul>
                        <div class="glass-card-price">
                            <span class="glass-price-custom">Contact Us</span>
                        </div>
                        <button class="glass-card-btn btn btn--ghost">Contact Sales</button>
                    </div>
                </div>
            </section>`
        });

        // 🌠 Constellation Section - Connected dot pattern
        bm.add('constellation', {
            label: '🌠 Constellation',
            category: 'Galaxy',
            attributes: { class: 'fa fa-project-diagram' },
            content: `<section class="nexus-constellation-section">
                <canvas class="constellation-canvas" id="constellation-canvas"></canvas>
                <div class="constellation-content">
                    <h2 class="constellation-title">Connected Ecosystem</h2>
                    <p class="constellation-text">Every point connects. Every connection matters. Build your network with our integrated platform.</p>
                    <div class="constellation-stats">
                        <div class="const-stat">
                            <span class="const-stat-value">500+</span>
                            <span class="const-stat-label">Integrations</span>
                        </div>
                        <div class="const-stat">
                            <span class="const-stat-value">50M</span>
                            <span class="const-stat-label">API Calls/Day</span>
                        </div>
                        <div class="const-stat">
                            <span class="const-stat-value">99.9%</span>
                            <span class="const-stat-label">Uptime</span>
                        </div>
                    </div>
                    <a href="#" class="constellation-btn">Explore Integrations</a>
                </div>
            </section>`
        });

        // ═══════════════════════════════════════════════════════════════════════════════
        // 🌌 UNIVERSE-TIER BLOCKS - Beyond Galactic, Rivaling Type III Civilizations
        // ═══════════════════════════════════════════════════════════════════════════════

        // ⚛️ Quantum Entanglement Cards - Elements that mirror each other's state
        bm.add('quantum-entanglement', {
            label: '⚛️ Quantum Cards',
            category: 'Universe',
            attributes: { class: 'fa fa-atom' },
            content: `<section class="nexus-quantum-section">
                <div class="quantum-field"></div>
                <div class="quantum-header">
                    <span class="quantum-badge">⚛️ Quantum Entangled</span>
                    <h2 class="quantum-title">Entangled Features</h2>
                    <p class="quantum-subtitle">Hover one card - watch its pair respond. Quantum coherence in UI form.</p>
                </div>
                <div class="quantum-cards-container">
                    <div class="quantum-pair" data-pair="alpha">
                        <div class="quantum-card quantum-card-a" data-entangled="alpha">
                            <div class="quantum-glow"></div>
                            <div class="quantum-spin"></div>
                            <div class="quantum-icon">🔮</div>
                            <h3>Particle A</h3>
                            <p>Real-time synchronization across infinite distances</p>
                            <div class="quantum-state">State: <span class="state-value">|↑⟩</span></div>
                        </div>
                        <div class="quantum-connection">
                            <svg class="quantum-line" viewBox="0 0 100 20">
                                <path d="M0,10 Q25,0 50,10 T100,10" class="quantum-wave"/>
                                <circle cx="50" cy="10" r="3" class="quantum-photon"/>
                            </svg>
                        </div>
                        <div class="quantum-card quantum-card-b" data-entangled="alpha">
                            <div class="quantum-glow"></div>
                            <div class="quantum-spin"></div>
                            <div class="quantum-icon">🔮</div>
                            <h3>Particle B</h3>
                            <p>Instantaneous state collapse upon observation</p>
                            <div class="quantum-state">State: <span class="state-value">|↓⟩</span></div>
                        </div>
                    </div>
                    <div class="quantum-pair" data-pair="beta">
                        <div class="quantum-card quantum-card-a" data-entangled="beta">
                            <div class="quantum-glow"></div>
                            <div class="quantum-spin"></div>
                            <div class="quantum-icon">⚡</div>
                            <h3>Energy State</h3>
                            <p>Superposition of all possible configurations</p>
                            <div class="quantum-state">State: <span class="state-value">|+⟩</span></div>
                        </div>
                        <div class="quantum-connection">
                            <svg class="quantum-line" viewBox="0 0 100 20">
                                <path d="M0,10 Q25,0 50,10 T100,10" class="quantum-wave"/>
                                <circle cx="50" cy="10" r="3" class="quantum-photon"/>
                            </svg>
                        </div>
                        <div class="quantum-card quantum-card-b" data-entangled="beta">
                            <div class="quantum-glow"></div>
                            <div class="quantum-spin"></div>
                            <div class="quantum-icon">⚡</div>
                            <h3>Mirror State</h3>
                            <p>Non-local correlation defying classical physics</p>
                            <div class="quantum-state">State: <span class="state-value">|-⟩</span></div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🔷 Tesseract 4D Card - Hyperdimensional rotating cube
        bm.add('tesseract-4d', {
            label: '🔷 Tesseract 4D',
            category: 'Universe',
            attributes: { class: 'fa fa-cube' },
            content: `<section class="nexus-tesseract-section">
                <div class="tesseract-void"></div>
                <div class="tesseract-container">
                    <div class="tesseract-wrapper">
                        <div class="tesseract">
                            <div class="tesseract-cube tesseract-outer">
                                <div class="cube-face cube-front"></div>
                                <div class="cube-face cube-back"></div>
                                <div class="cube-face cube-left"></div>
                                <div class="cube-face cube-right"></div>
                                <div class="cube-face cube-top"></div>
                                <div class="cube-face cube-bottom"></div>
                            </div>
                            <div class="tesseract-cube tesseract-inner">
                                <div class="cube-face cube-front"></div>
                                <div class="cube-face cube-back"></div>
                                <div class="cube-face cube-left"></div>
                                <div class="cube-face cube-right"></div>
                                <div class="cube-face cube-top"></div>
                                <div class="cube-face cube-bottom"></div>
                            </div>
                            <div class="tesseract-edges">
                                <div class="tesseract-edge edge-1"></div>
                                <div class="tesseract-edge edge-2"></div>
                                <div class="tesseract-edge edge-3"></div>
                                <div class="tesseract-edge edge-4"></div>
                                <div class="tesseract-edge edge-5"></div>
                                <div class="tesseract-edge edge-6"></div>
                                <div class="tesseract-edge edge-7"></div>
                                <div class="tesseract-edge edge-8"></div>
                            </div>
                        </div>
                    </div>
                    <div class="tesseract-content">
                        <h2 class="tesseract-title">4th Dimension</h2>
                        <p class="tesseract-text">Experience interfaces that transcend 3D space. Our hyperdimensional design system operates across multiple planes of existence.</p>
                        <div class="tesseract-dimensions">
                            <span class="dim-label">X</span>
                            <span class="dim-label">Y</span>
                            <span class="dim-label">Z</span>
                            <span class="dim-label dim-w">W</span>
                        </div>
                        <button class="tesseract-btn">Enter Hyperspace</button>
                    </div>
                </div>
            </section>`
        });

        // 🧠 Neural Network Visualizer - Animated synaptic connections
        bm.add('neural-network', {
            label: '🧠 Neural Network',
            category: 'Universe',
            attributes: { class: 'fa fa-brain' },
            content: `<section class="nexus-neural-section">
                <div class="neural-background">
                    <div class="neural-layer input-layer">
                        <div class="neuron" style="--i:0"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:1"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:2"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:3"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                    </div>
                    <div class="neural-layer hidden-layer-1">
                        <div class="neuron" style="--i:0"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:1"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:2"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:3"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:4"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:5"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                    </div>
                    <div class="neural-layer hidden-layer-2">
                        <div class="neuron" style="--i:0"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:1"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:2"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron" style="--i:3"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                    </div>
                    <div class="neural-layer output-layer">
                        <div class="neuron neuron-output" style="--i:0"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                        <div class="neuron neuron-output" style="--i:1"><div class="neuron-core"></div><div class="neuron-pulse"></div></div>
                    </div>
                    <svg class="neural-connections" viewBox="0 0 800 400">
                        <defs>
                            <linearGradient id="synapseGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#00f5ff;stop-opacity:0"/>
                                <stop offset="50%" style="stop-color:#00f5ff;stop-opacity:1"/>
                                <stop offset="100%" style="stop-color:#bf00ff;stop-opacity:0"/>
                            </linearGradient>
                        </defs>
                        <g class="synapse-group"></g>
                    </svg>
                </div>
                <div class="neural-content">
                    <div class="neural-badge">🧠 Deep Learning</div>
                    <h2 class="neural-title">Intelligent by Design</h2>
                    <p class="neural-text">Watch as information flows through neural pathways. Each connection strengthens with interaction, learning and adapting to your needs.</p>
                    <div class="neural-metrics">
                        <div class="metric">
                            <span class="metric-value">847M</span>
                            <span class="metric-label">Parameters</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">99.7%</span>
                            <span class="metric-label">Accuracy</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">12ms</span>
                            <span class="metric-label">Inference</span>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🕳️ Gravity Well - Elements pulled toward center
        bm.add('gravity-well', {
            label: '🕳️ Gravity Well',
            category: 'Universe',
            attributes: { class: 'fa fa-circle' },
            content: `<section class="nexus-gravity-section">
                <div class="gravity-field">
                    <div class="gravity-ring" style="--i:1"></div>
                    <div class="gravity-ring" style="--i:2"></div>
                    <div class="gravity-ring" style="--i:3"></div>
                    <div class="gravity-ring" style="--i:4"></div>
                    <div class="gravity-ring" style="--i:5"></div>
                    <div class="gravity-singularity">
                        <div class="singularity-core"></div>
                        <div class="event-horizon"></div>
                    </div>
                    <div class="gravity-particle" style="--orbit:1; --speed:8s; --delay:0s"></div>
                    <div class="gravity-particle" style="--orbit:2; --speed:12s; --delay:1s"></div>
                    <div class="gravity-particle" style="--orbit:3; --speed:16s; --delay:2s"></div>
                    <div class="gravity-particle" style="--orbit:4; --speed:20s; --delay:3s"></div>
                    <div class="gravity-particle" style="--orbit:5; --speed:25s; --delay:4s"></div>
                    <div class="gravity-particle" style="--orbit:2; --speed:10s; --delay:5s"></div>
                    <div class="gravity-particle" style="--orbit:4; --speed:18s; --delay:6s"></div>
                    <div class="gravity-particle" style="--orbit:3; --speed:14s; --delay:7s"></div>
                </div>
                <div class="gravity-content">
                    <h2 class="gravity-title">Gravitational UX</h2>
                    <p class="gravity-text">Everything orbits around your users. Our design philosophy creates natural attraction, pulling visitors toward conversion.</p>
                    <div class="gravity-stats">
                        <div class="g-stat">
                            <span class="g-value">∞</span>
                            <span class="g-label">Density</span>
                        </div>
                        <div class="g-stat">
                            <span class="g-value">c</span>
                            <span class="g-label">Escape Velocity</span>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 📡 Holographic Display - Sci-fi hologram interface
        bm.add('holographic-display', {
            label: '📡 Hologram',
            category: 'Universe',
            attributes: { class: 'fa fa-broadcast-tower' },
            content: `<section class="nexus-hologram-section">
                <div class="holo-scanlines"></div>
                <div class="holo-container">
                    <div class="holo-projector">
                        <div class="projector-base"></div>
                        <div class="projector-beam"></div>
                    </div>
                    <div class="holo-display">
                        <div class="holo-frame">
                            <div class="holo-corner holo-tl"></div>
                            <div class="holo-corner holo-tr"></div>
                            <div class="holo-corner holo-bl"></div>
                            <div class="holo-corner holo-br"></div>
                        </div>
                        <div class="holo-content">
                            <div class="holo-header">
                                <span class="holo-status">● LIVE</span>
                                <span class="holo-id">NEXUS-7749</span>
                            </div>
                            <div class="holo-avatar">
                                <div class="avatar-ring"></div>
                                <div class="avatar-core">👤</div>
                            </div>
                            <h3 class="holo-name">COMMANDER</h3>
                            <p class="holo-role">System Administrator</p>
                            <div class="holo-stats-row">
                                <div class="holo-stat">
                                    <span class="holo-stat-val">2,847</span>
                                    <span class="holo-stat-lbl">MISSIONS</span>
                                </div>
                                <div class="holo-stat">
                                    <span class="holo-stat-val">A+</span>
                                    <span class="holo-stat-lbl">RATING</span>
                                </div>
                            </div>
                            <div class="holo-actions">
                                <button class="holo-btn">CONTACT</button>
                                <button class="holo-btn holo-btn-alt">PROFILE</button>
                            </div>
                        </div>
                        <div class="holo-glitch"></div>
                    </div>
                </div>
            </section>`
        });

        // 🌀 Wormhole Portal - Tunnel effect with depth
        bm.add('wormhole-portal', {
            label: '🌀 Wormhole',
            category: 'Universe',
            attributes: { class: 'fa fa-hurricane' },
            content: `<section class="nexus-wormhole-section">
                <div class="wormhole-container">
                    <div class="wormhole-tunnel">
                        <div class="tunnel-ring" style="--i:1"></div>
                        <div class="tunnel-ring" style="--i:2"></div>
                        <div class="tunnel-ring" style="--i:3"></div>
                        <div class="tunnel-ring" style="--i:4"></div>
                        <div class="tunnel-ring" style="--i:5"></div>
                        <div class="tunnel-ring" style="--i:6"></div>
                        <div class="tunnel-ring" style="--i:7"></div>
                        <div class="tunnel-ring" style="--i:8"></div>
                        <div class="tunnel-ring" style="--i:9"></div>
                        <div class="tunnel-ring" style="--i:10"></div>
                        <div class="tunnel-core">
                            <div class="destination-preview">
                                <span class="dest-icon">🌌</span>
                                <span class="dest-text">ANDROMEDA</span>
                            </div>
                        </div>
                    </div>
                    <div class="wormhole-particles">
                        <div class="w-particle" style="--angle:0deg"></div>
                        <div class="w-particle" style="--angle:30deg"></div>
                        <div class="w-particle" style="--angle:60deg"></div>
                        <div class="w-particle" style="--angle:90deg"></div>
                        <div class="w-particle" style="--angle:120deg"></div>
                        <div class="w-particle" style="--angle:150deg"></div>
                        <div class="w-particle" style="--angle:180deg"></div>
                        <div class="w-particle" style="--angle:210deg"></div>
                        <div class="w-particle" style="--angle:240deg"></div>
                        <div class="w-particle" style="--angle:270deg"></div>
                        <div class="w-particle" style="--angle:300deg"></div>
                        <div class="w-particle" style="--angle:330deg"></div>
                    </div>
                </div>
                <div class="wormhole-content">
                    <h2 class="wormhole-title">Instant Teleportation</h2>
                    <p class="wormhole-text">Navigate between sections faster than light. Our wormhole technology bends spacetime for seamless user journeys.</p>
                    <div class="wormhole-destinations">
                        <button class="dest-btn"><span>🏠</span> Home</button>
                        <button class="dest-btn"><span>📊</span> Dashboard</button>
                        <button class="dest-btn"><span>⚙️</span> Settings</button>
                    </div>
                </div>
            </section>`
        });

        // ⚡ Plasma Energy Field - Electric arc animations
        bm.add('plasma-energy', {
            label: '⚡ Plasma Field',
            category: 'Universe',
            attributes: { class: 'fa fa-bolt' },
            content: `<section class="nexus-plasma-section">
                <div class="plasma-container">
                    <div class="plasma-orb">
                        <div class="plasma-core"></div>
                        <svg class="plasma-arcs" viewBox="0 0 400 400">
                            <defs>
                                <filter id="plasmaGlow">
                                    <feGaussianBlur stdDeviation="3" result="blur"/>
                                    <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                                </filter>
                            </defs>
                            <path class="plasma-arc arc-1" d="M200,200 Q250,100 300,200" filter="url(#plasmaGlow)"/>
                            <path class="plasma-arc arc-2" d="M200,200 Q100,150 100,250" filter="url(#plasmaGlow)"/>
                            <path class="plasma-arc arc-3" d="M200,200 Q300,300 200,350" filter="url(#plasmaGlow)"/>
                            <path class="plasma-arc arc-4" d="M200,200 Q150,50 250,100" filter="url(#plasmaGlow)"/>
                            <path class="plasma-arc arc-5" d="M200,200 Q50,200 100,100" filter="url(#plasmaGlow)"/>
                            <path class="plasma-arc arc-6" d="M200,200 Q350,250 300,350" filter="url(#plasmaGlow)"/>
                        </svg>
                        <div class="plasma-shell"></div>
                    </div>
                    <div class="plasma-content">
                        <div class="plasma-badge">⚡ UNLIMITED POWER</div>
                        <h2 class="plasma-title">Pure Energy</h2>
                        <p class="plasma-text">Harness the fourth state of matter. Our plasma-powered infrastructure delivers unlimited scalability and performance.</p>
                        <div class="plasma-meters">
                            <div class="meter">
                                <div class="meter-label">Power Output</div>
                                <div class="meter-bar"><div class="meter-fill" style="--fill:92%"></div></div>
                                <div class="meter-value">1.21 GW</div>
                            </div>
                            <div class="meter">
                                <div class="meter-label">Containment</div>
                                <div class="meter-bar"><div class="meter-fill" style="--fill:98%"></div></div>
                                <div class="meter-value">98% Stable</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🌈 Dimensional Rift - Portal between realities
        bm.add('dimensional-rift', {
            label: '🌈 Dimension Rift',
            category: 'Universe',
            attributes: { class: 'fa fa-layer-group' },
            content: `<section class="nexus-rift-section">
                <div class="rift-background">
                    <div class="reality-a">
                        <div class="reality-content">
                            <h3>Reality A</h3>
                            <p>Current Timeline</p>
                        </div>
                    </div>
                    <div class="rift-tear">
                        <div class="rift-edge rift-edge-left"></div>
                        <div class="rift-void">
                            <div class="void-star" style="--x:10%; --y:20%"></div>
                            <div class="void-star" style="--x:30%; --y:60%"></div>
                            <div class="void-star" style="--x:50%; --y:30%"></div>
                            <div class="void-star" style="--x:70%; --y:70%"></div>
                            <div class="void-star" style="--x:90%; --y:40%"></div>
                            <div class="void-energy"></div>
                        </div>
                        <div class="rift-edge rift-edge-right"></div>
                    </div>
                    <div class="reality-b">
                        <div class="reality-content">
                            <h3>Reality B</h3>
                            <p>Alternate Timeline</p>
                        </div>
                    </div>
                </div>
                <div class="rift-content">
                    <h2 class="rift-title">Cross-Dimensional Design</h2>
                    <p class="rift-text">Our interfaces exist across multiple realities simultaneously. A/B testing taken to the cosmic extreme.</p>
                    <div class="rift-toggle">
                        <span class="toggle-label">Reality A</span>
                        <div class="toggle-switch">
                            <div class="toggle-slider"></div>
                        </div>
                        <span class="toggle-label">Reality B</span>
                    </div>
                </div>
            </section>`
        });

        // ⏳ Cosmic Timeline - Universal history visualization
        bm.add('cosmic-timeline', {
            label: '⏳ Cosmic Timeline',
            category: 'Universe',
            attributes: { class: 'fa fa-clock' },
            content: `<section class="nexus-cosmic-timeline">
                <div class="timeline-universe">
                    <div class="timeline-header">
                        <h2 class="timeline-title">History of the Universe</h2>
                        <p class="timeline-subtitle">13.8 billion years in one scroll</p>
                    </div>
                    <div class="timeline-track">
                        <div class="timeline-line"></div>
                        <div class="timeline-event" style="--pos:0%">
                            <div class="event-marker">
                                <div class="marker-pulse"></div>
                                <span class="marker-icon">💥</span>
                            </div>
                            <div class="event-content">
                                <span class="event-time">0s</span>
                                <h4 class="event-title">Big Bang</h4>
                                <p class="event-desc">The universe begins from a singularity</p>
                            </div>
                        </div>
                        <div class="timeline-event" style="--pos:20%">
                            <div class="event-marker">
                                <div class="marker-pulse"></div>
                                <span class="marker-icon">⚛️</span>
                            </div>
                            <div class="event-content">
                                <span class="event-time">380,000 years</span>
                                <h4 class="event-title">First Atoms</h4>
                                <p class="event-desc">Hydrogen and helium form</p>
                            </div>
                        </div>
                        <div class="timeline-event" style="--pos:40%">
                            <div class="event-marker">
                                <div class="marker-pulse"></div>
                                <span class="marker-icon">⭐</span>
                            </div>
                            <div class="event-content">
                                <span class="event-time">200M years</span>
                                <h4 class="event-title">First Stars</h4>
                                <p class="event-desc">Cosmic dawn illuminates the void</p>
                            </div>
                        </div>
                        <div class="timeline-event" style="--pos:60%">
                            <div class="event-marker">
                                <div class="marker-pulse"></div>
                                <span class="marker-icon">🌌</span>
                            </div>
                            <div class="event-content">
                                <span class="event-time">1B years</span>
                                <h4 class="event-title">First Galaxies</h4>
                                <p class="event-desc">Structures emerge from the cosmic web</p>
                            </div>
                        </div>
                        <div class="timeline-event" style="--pos:80%">
                            <div class="event-marker">
                                <div class="marker-pulse"></div>
                                <span class="marker-icon">🌍</span>
                            </div>
                            <div class="event-content">
                                <span class="event-time">9.2B years</span>
                                <h4 class="event-title">Earth Forms</h4>
                                <p class="event-desc">Our pale blue dot emerges</p>
                            </div>
                        </div>
                        <div class="timeline-event" style="--pos:100%">
                            <div class="event-marker marker-now">
                                <div class="marker-pulse"></div>
                                <span class="marker-icon">🚀</span>
                            </div>
                            <div class="event-content">
                                <span class="event-time">Now</span>
                                <h4 class="event-title">You Are Here</h4>
                                <p class="event-desc">Building the future with NEXUS</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🌫️ Nebula Form - Cosmic gas cloud contact form
        bm.add('nebula-form', {
            label: '🌫️ Nebula Form',
            category: 'Universe',
            attributes: { class: 'fa fa-cloud' },
            content: `<section class="nexus-nebula-form">
                <div class="nebula-clouds">
                    <div class="nebula-cloud cloud-1"></div>
                    <div class="nebula-cloud cloud-2"></div>
                    <div class="nebula-cloud cloud-3"></div>
                    <div class="nebula-stars">
                        <div class="n-star" style="--x:10%; --y:20%; --s:2px"></div>
                        <div class="n-star" style="--x:25%; --y:40%; --s:1px"></div>
                        <div class="n-star" style="--x:40%; --y:15%; --s:3px"></div>
                        <div class="n-star" style="--x:60%; --y:60%; --s:2px"></div>
                        <div class="n-star" style="--x:75%; --y:30%; --s:1px"></div>
                        <div class="n-star" style="--x:90%; --y:70%; --s:2px"></div>
                    </div>
                </div>
                <div class="nebula-form-container">
                    <div class="nebula-form-header">
                        <span class="form-badge">🌫️ TRANSMISSION</span>
                        <h2 class="form-title">Send a Signal</h2>
                        <p class="form-subtitle">Your message will travel across the cosmos</p>
                    </div>
                    <form class="cosmic-form">
                        <div class="form-field">
                            <label class="field-label">Designation</label>
                            <input type="text" class="cosmic-input" placeholder="Your name" />
                            <div class="field-glow"></div>
                        </div>
                        <div class="form-field">
                            <label class="field-label">Frequency</label>
                            <input type="email" class="cosmic-input" placeholder="your@email.com" />
                            <div class="field-glow"></div>
                        </div>
                        <div class="form-field">
                            <label class="field-label">Transmission</label>
                            <textarea class="cosmic-input cosmic-textarea" placeholder="Your message to the universe..." rows="4"></textarea>
                            <div class="field-glow"></div>
                        </div>
                        <button type="submit" class="cosmic-submit">
                            <span class="btn-text">Broadcast Signal</span>
                            <span class="btn-icon">📡</span>
                            <div class="btn-wave"></div>
                        </button>
                    </form>
                </div>
            </section>`
        });

        // 🎆 Supernova Reveal - Explosive content reveal
        bm.add('supernova-reveal', {
            label: '🎆 Supernova',
            category: 'Universe',
            attributes: { class: 'fa fa-sun' },
            content: `<section class="nexus-supernova-section">
                <div class="supernova-container">
                    <div class="supernova-star">
                        <div class="star-core"></div>
                        <div class="star-corona"></div>
                        <div class="star-flare flare-1"></div>
                        <div class="star-flare flare-2"></div>
                        <div class="star-flare flare-3"></div>
                        <div class="star-flare flare-4"></div>
                    </div>
                    <div class="supernova-explosion">
                        <div class="explosion-ring ring-1"></div>
                        <div class="explosion-ring ring-2"></div>
                        <div class="explosion-ring ring-3"></div>
                        <div class="explosion-debris">
                            <div class="debris" style="--angle:0deg; --dist:100px"></div>
                            <div class="debris" style="--angle:45deg; --dist:120px"></div>
                            <div class="debris" style="--angle:90deg; --dist:90px"></div>
                            <div class="debris" style="--angle:135deg; --dist:110px"></div>
                            <div class="debris" style="--angle:180deg; --dist:95px"></div>
                            <div class="debris" style="--angle:225deg; --dist:115px"></div>
                            <div class="debris" style="--angle:270deg; --dist:105px"></div>
                            <div class="debris" style="--angle:315deg; --dist:125px"></div>
                        </div>
                    </div>
                    <div class="supernova-content">
                        <h2 class="supernova-title">Explosive Launch</h2>
                        <p class="supernova-text">When a star dies, it creates elements for new worlds. When we launch, we create possibilities for new futures.</p>
                        <div class="supernova-countdown">
                            <div class="countdown-item">
                                <span class="count-value">07</span>
                                <span class="count-label">Days</span>
                            </div>
                            <div class="countdown-item">
                                <span class="count-value">23</span>
                                <span class="count-label">Hours</span>
                            </div>
                            <div class="countdown-item">
                                <span class="count-value">45</span>
                                <span class="count-label">Mins</span>
                            </div>
                            <div class="countdown-item">
                                <span class="count-value">12</span>
                                <span class="count-label">Secs</span>
                            </div>
                        </div>
                        <button class="supernova-btn">Notify Me</button>
                    </div>
                </div>
            </section>`
        });

        // 🔭 Observatory Cards - Telescope-style feature reveals
        bm.add('observatory-cards', {
            label: '🔭 Observatory',
            category: 'Universe',
            attributes: { class: 'fa fa-binoculars' },
            content: `<section class="nexus-observatory-section">
                <div class="observatory-dome">
                    <div class="dome-stars"></div>
                    <div class="dome-slit"></div>
                </div>
                <div class="observatory-header">
                    <h2 class="observatory-title">Observe the Features</h2>
                    <p class="observatory-subtitle">Point your telescope at any card to reveal cosmic details</p>
                </div>
                <div class="observatory-grid">
                    <div class="obs-card">
                        <div class="obs-card-lens">
                            <div class="lens-ring"></div>
                            <div class="lens-reflection"></div>
                        </div>
                        <div class="obs-card-content">
                            <div class="obs-icon">🔴</div>
                            <h3 class="obs-title">Red Giant</h3>
                            <p class="obs-desc">Massive scalability for enterprise needs. Expand to millions of users effortlessly.</p>
                            <div class="obs-data">
                                <span>Class: M</span>
                                <span>Temp: 3,500K</span>
                            </div>
                        </div>
                    </div>
                    <div class="obs-card">
                        <div class="obs-card-lens">
                            <div class="lens-ring"></div>
                            <div class="lens-reflection"></div>
                        </div>
                        <div class="obs-card-content">
                            <div class="obs-icon">⚪</div>
                            <h3 class="obs-title">White Dwarf</h3>
                            <p class="obs-desc">Compact and efficient. Maximum performance in minimal footprint.</p>
                            <div class="obs-data">
                                <span>Class: D</span>
                                <span>Temp: 25,000K</span>
                            </div>
                        </div>
                    </div>
                    <div class="obs-card">
                        <div class="obs-card-lens">
                            <div class="lens-ring"></div>
                            <div class="lens-reflection"></div>
                        </div>
                        <div class="obs-card-content">
                            <div class="obs-icon">🟣</div>
                            <h3 class="obs-title">Neutron Star</h3>
                            <p class="obs-desc">Incredibly dense data processing. Compress complexity into simplicity.</p>
                            <div class="obs-data">
                                <span>Class: NS</span>
                                <span>Density: ∞</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🛸 UFO Notification - Hovering alert component
        bm.add('ufo-notification', {
            label: '🛸 UFO Alert',
            category: 'Universe',
            attributes: { class: 'fa fa-bell' },
            content: `<div class="nexus-ufo-notification">
                <div class="ufo-container">
                    <div class="ufo-beam"></div>
                    <div class="ufo-craft">
                        <div class="ufo-dome"></div>
                        <div class="ufo-body">
                            <div class="ufo-light ufo-light-1"></div>
                            <div class="ufo-light ufo-light-2"></div>
                            <div class="ufo-light ufo-light-3"></div>
                        </div>
                        <div class="ufo-ring"></div>
                    </div>
                    <div class="ufo-shadow"></div>
                </div>
                <div class="ufo-message">
                    <div class="message-header">
                        <span class="message-badge">INCOMING TRANSMISSION</span>
                        <button class="message-close">×</button>
                    </div>
                    <div class="message-body">
                        <p class="message-text">Greetings, Earth visitor! You've discovered a new feature. Would you like to explore it?</p>
                    </div>
                    <div class="message-actions">
                        <button class="ufo-btn ufo-btn-primary">Take Me There</button>
                        <button class="ufo-btn ufo-btn-secondary">Maybe Later</button>
                    </div>
                </div>
            </div>`
        });

        // 🌑 Dark Matter Pricing - Invisible value revealed
        bm.add('dark-matter-pricing', {
            label: '🌑 Dark Matter',
            category: 'Universe',
            attributes: { class: 'fa fa-moon' },
            content: `<section class="nexus-dark-matter-section">
                <div class="dark-matter-field">
                    <div class="dm-particle" style="--x:10%; --y:20%"></div>
                    <div class="dm-particle" style="--x:30%; --y:60%"></div>
                    <div class="dm-particle" style="--x:50%; --y:30%"></div>
                    <div class="dm-particle" style="--x:70%; --y:70%"></div>
                    <div class="dm-particle" style="--x:90%; --y:40%"></div>
                    <div class="dm-web"></div>
                </div>
                <div class="dark-matter-header">
                    <span class="dm-badge">🌑 96% of Value is Invisible</span>
                    <h2 class="dm-title">Dark Matter Pricing</h2>
                    <p class="dm-subtitle">The visible price is just the beginning. Hover to reveal the hidden value.</p>
                </div>
                <div class="dark-matter-cards">
                    <div class="dm-card">
                        <div class="dm-card-visible">
                            <h3 class="dm-plan">Starter</h3>
                            <div class="dm-price">$29<span>/mo</span></div>
                            <p class="dm-tagline">What you see</p>
                        </div>
                        <div class="dm-card-hidden">
                            <h4>Hidden Value:</h4>
                            <ul class="dm-hidden-list">
                                <li>24/7 Support ($200 value)</li>
                                <li>Free Upgrades ($150 value)</li>
                                <li>Community Access ($100 value)</li>
                            </ul>
                            <div class="dm-true-value">True Value: <strong>$479/mo</strong></div>
                        </div>
                        <button class="dm-btn">Reveal All</button>
                    </div>
                    <div class="dm-card dm-card-featured">
                        <div class="dm-featured-badge">Most Matter</div>
                        <div class="dm-card-visible">
                            <h3 class="dm-plan">Professional</h3>
                            <div class="dm-price">$99<span>/mo</span></div>
                            <p class="dm-tagline">What you see</p>
                        </div>
                        <div class="dm-card-hidden">
                            <h4>Hidden Value:</h4>
                            <ul class="dm-hidden-list">
                                <li>Priority Support ($500 value)</li>
                                <li>Advanced Analytics ($300 value)</li>
                                <li>API Access ($400 value)</li>
                                <li>Custom Domains ($200 value)</li>
                            </ul>
                            <div class="dm-true-value">True Value: <strong>$1,499/mo</strong></div>
                        </div>
                        <button class="dm-btn">Reveal All</button>
                    </div>
                    <div class="dm-card">
                        <div class="dm-card-visible">
                            <h3 class="dm-plan">Enterprise</h3>
                            <div class="dm-price">Custom</div>
                            <p class="dm-tagline">What you see</p>
                        </div>
                        <div class="dm-card-hidden">
                            <h4>Hidden Value:</h4>
                            <ul class="dm-hidden-list">
                                <li>Dedicated Team (Priceless)</li>
                                <li>SLA Guarantee (Priceless)</li>
                                <li>Custom Development (Priceless)</li>
                            </ul>
                            <div class="dm-true-value">True Value: <strong>∞</strong></div>
                        </div>
                        <button class="dm-btn">Contact Us</button>
                    </div>
                </div>
            </section>`
        });

        // ═══════════════════════════════════════════════════════════════════════════════
        // 🌀 MULTIVERSE-TIER BLOCKS - Transcending Reality Across Infinite Dimensions
        // ═══════════════════════════════════════════════════════════════════════════════

        // 🌀 Parallel Universe Cards - Show alternate versions simultaneously
        bm.add('parallel-universe', {
            label: '🌀 Parallel Cards',
            category: 'Multiverse',
            attributes: { class: 'fa fa-clone' },
            content: `<section class="nexus-parallel-section">
                <div class="parallel-void"></div>
                <div class="parallel-header">
                    <span class="parallel-badge">🌀 Infinite Realities</span>
                    <h2 class="parallel-title">Parallel Universes</h2>
                    <p class="parallel-subtitle">Every choice creates a new reality. See them all at once.</p>
                </div>
                <div class="parallel-container">
                    <div class="universe-card universe-alpha">
                        <div class="universe-label">Universe α</div>
                        <div class="universe-shimmer"></div>
                        <div class="universe-content">
                            <div class="universe-icon">🌍</div>
                            <h3>Earth-Alpha</h3>
                            <p>You chose the blue pill. Reality as you know it.</p>
                            <ul class="universe-traits">
                                <li>Linear time</li>
                                <li>Stable physics</li>
                                <li>Familiar laws</li>
                            </ul>
                        </div>
                    </div>
                    <div class="parallel-divider">
                        <div class="divider-line"></div>
                        <div class="divider-portal">∞</div>
                        <div class="divider-line"></div>
                    </div>
                    <div class="universe-card universe-beta">
                        <div class="universe-label">Universe β</div>
                        <div class="universe-shimmer"></div>
                        <div class="universe-content">
                            <div class="universe-icon">🌎</div>
                            <h3>Earth-Beta</h3>
                            <p>You chose the red pill. See how deep it goes.</p>
                            <ul class="universe-traits">
                                <li>Branching time</li>
                                <li>Quantum flux</li>
                                <li>New possibilities</li>
                            </ul>
                        </div>
                    </div>
                    <div class="parallel-divider">
                        <div class="divider-line"></div>
                        <div class="divider-portal">∞</div>
                        <div class="divider-line"></div>
                    </div>
                    <div class="universe-card universe-gamma">
                        <div class="universe-label">Universe γ</div>
                        <div class="universe-shimmer"></div>
                        <div class="universe-content">
                            <div class="universe-icon">🌏</div>
                            <h3>Earth-Gamma</h3>
                            <p>You chose both pills. Reality is what you make it.</p>
                            <ul class="universe-traits">
                                <li>Infinite time</li>
                                <li>Pure energy</li>
                                <li>Unlimited potential</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🎭 Schrödinger's Cards - Superposition until observed
        bm.add('schrodinger-cards', {
            label: '🎭 Schrödinger',
            category: 'Multiverse',
            attributes: { class: 'fa fa-cat' },
            content: `<section class="nexus-schrodinger-section">
                <div class="schrodinger-particles"></div>
                <div class="schrodinger-header">
                    <span class="schrodinger-badge">🎭 Quantum Superposition</span>
                    <h2 class="schrodinger-title">Schrödinger's Features</h2>
                    <p class="schrodinger-subtitle">Each card exists in all states until you observe it. Click to collapse the wave function.</p>
                </div>
                <div class="schrodinger-grid">
                    <div class="schrodinger-card" data-state="superposition">
                        <div class="card-superposition">
                            <div class="superposition-blur"></div>
                            <div class="superposition-text">|ψ⟩ = α|A⟩ + β|B⟩</div>
                            <div class="superposition-hint">Click to observe</div>
                        </div>
                        <div class="card-collapsed card-state-a">
                            <div class="collapsed-icon">✅</div>
                            <h3>State A: Active</h3>
                            <p>The feature is enabled and fully operational in this timeline.</p>
                        </div>
                        <div class="card-collapsed card-state-b">
                            <div class="collapsed-icon">⏸️</div>
                            <h3>State B: Pending</h3>
                            <p>The feature awaits activation in an alternate branch.</p>
                        </div>
                    </div>
                    <div class="schrodinger-card" data-state="superposition">
                        <div class="card-superposition">
                            <div class="superposition-blur"></div>
                            <div class="superposition-text">|ψ⟩ = α|A⟩ + β|B⟩</div>
                            <div class="superposition-hint">Click to observe</div>
                        </div>
                        <div class="card-collapsed card-state-a">
                            <div class="collapsed-icon">🚀</div>
                            <h3>State A: Launched</h3>
                            <p>Already deployed across all parallel instances.</p>
                        </div>
                        <div class="card-collapsed card-state-b">
                            <div class="collapsed-icon">🔮</div>
                            <h3>State B: Coming Soon</h3>
                            <p>Quantum tunneling in progress from future timeline.</p>
                        </div>
                    </div>
                    <div class="schrodinger-card" data-state="superposition">
                        <div class="card-superposition">
                            <div class="superposition-blur"></div>
                            <div class="superposition-text">|ψ⟩ = α|A⟩ + β|B⟩</div>
                            <div class="superposition-hint">Click to observe</div>
                        </div>
                        <div class="card-collapsed card-state-a">
                            <div class="collapsed-icon">💎</div>
                            <h3>State A: Premium</h3>
                            <p>Exclusive features from the golden timeline.</p>
                        </div>
                        <div class="card-collapsed card-state-b">
                            <div class="collapsed-icon">🎁</div>
                            <h3>State B: Free</h3>
                            <p>Unlocked for all in the abundant universe.</p>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🔀 Timeline Branching - Decision tree visualization
        bm.add('timeline-branching', {
            label: '🔀 Timeline Branch',
            category: 'Multiverse',
            attributes: { class: 'fa fa-code-branch' },
            content: `<section class="nexus-branching-section">
                <div class="branching-bg"></div>
                <div class="branching-header">
                    <h2 class="branching-title">Choose Your Timeline</h2>
                    <p class="branching-subtitle">Every decision branches into infinite possibilities</p>
                </div>
                <div class="branching-tree">
                    <div class="branch-node branch-root">
                        <div class="node-content">
                            <span class="node-icon">🚀</span>
                            <span class="node-text">Start Here</span>
                        </div>
                    </div>
                    <div class="branch-lines level-1">
                        <svg class="branch-svg" viewBox="0 0 400 80">
                            <path class="branch-path" d="M200,0 Q100,40 50,80" />
                            <path class="branch-path" d="M200,0 Q200,40 200,80" />
                            <path class="branch-path" d="M200,0 Q300,40 350,80" />
                        </svg>
                    </div>
                    <div class="branch-level">
                        <div class="branch-node branch-option" data-timeline="explorer">
                            <div class="node-content">
                                <span class="node-icon">🔍</span>
                                <span class="node-text">Explorer</span>
                            </div>
                            <div class="node-description">Discover new features</div>
                        </div>
                        <div class="branch-node branch-option" data-timeline="builder">
                            <div class="node-content">
                                <span class="node-icon">🔨</span>
                                <span class="node-text">Builder</span>
                            </div>
                            <div class="node-description">Create your own path</div>
                        </div>
                        <div class="branch-node branch-option" data-timeline="master">
                            <div class="node-content">
                                <span class="node-icon">👑</span>
                                <span class="node-text">Master</span>
                            </div>
                            <div class="node-description">Control all timelines</div>
                        </div>
                    </div>
                    <div class="branch-lines level-2">
                        <svg class="branch-svg" viewBox="0 0 400 60">
                            <path class="branch-path branch-path-hidden" d="M50,0 Q50,30 25,60" />
                            <path class="branch-path branch-path-hidden" d="M50,0 Q50,30 75,60" />
                            <path class="branch-path branch-path-hidden" d="M200,0 Q200,30 175,60" />
                            <path class="branch-path branch-path-hidden" d="M200,0 Q200,30 225,60" />
                            <path class="branch-path branch-path-hidden" d="M350,0 Q350,30 325,60" />
                            <path class="branch-path branch-path-hidden" d="M350,0 Q350,30 375,60" />
                        </svg>
                    </div>
                    <div class="branch-outcomes">
                        <div class="outcome-group">
                            <span class="outcome">🌟 Discovery</span>
                            <span class="outcome">📚 Knowledge</span>
                        </div>
                        <div class="outcome-group">
                            <span class="outcome">🏗️ Creation</span>
                            <span class="outcome">💡 Innovation</span>
                        </div>
                        <div class="outcome-group">
                            <span class="outcome">⚡ Power</span>
                            <span class="outcome">🌌 Infinity</span>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🪞 Mirror Dimension - Reflected/inverted content
        bm.add('mirror-dimension', {
            label: '🪞 Mirror Dimension',
            category: 'Multiverse',
            attributes: { class: 'fa fa-expand-arrows-alt' },
            content: `<section class="nexus-mirror-section">
                <div class="mirror-container">
                    <div class="mirror-reality mirror-normal">
                        <div class="reality-label">Your Reality</div>
                        <div class="reality-card">
                            <div class="card-icon">☀️</div>
                            <h3>Light Mode</h3>
                            <p>The familiar world you know. Bright, clear, predictable.</p>
                            <div class="card-stats">
                                <span>Entropy: Low</span>
                                <span>Stability: High</span>
                            </div>
                        </div>
                    </div>
                    <div class="mirror-surface">
                        <div class="mirror-frame"></div>
                        <div class="mirror-ripple"></div>
                        <div class="mirror-glow"></div>
                    </div>
                    <div class="mirror-reality mirror-reflected">
                        <div class="reality-label">Mirror Reality</div>
                        <div class="reality-card">
                            <div class="card-icon">🌙</div>
                            <h3>Dark Mode</h3>
                            <p>.elbatciderpnu ,raelc ,thgirB .wonk uoy dlrow railimaf ehT</p>
                            <div class="card-stats">
                                <span>Entropy: High</span>
                                <span>Stability: Flux</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mirror-controls">
                    <button class="mirror-btn">Step Through the Mirror</button>
                </div>
            </section>`
        });

        // ∞ Infinite Loop Carousel - Never-ending content
        bm.add('infinite-loop', {
            label: '∞ Infinite Loop',
            category: 'Multiverse',
            attributes: { class: 'fa fa-infinity' },
            content: `<section class="nexus-infinite-section">
                <div class="infinite-header">
                    <span class="infinite-badge">∞ Eternal Recursion</span>
                    <h2 class="infinite-title">The Infinite Loop</h2>
                    <p class="infinite-subtitle">Content that loops eternally through dimensions</p>
                </div>
                <div class="infinite-track-container">
                    <div class="infinite-track">
                        <div class="infinite-item">
                            <div class="item-number">01</div>
                            <div class="item-content">
                                <h4>Dimension One</h4>
                                <p>The beginning is the end</p>
                            </div>
                        </div>
                        <div class="infinite-item">
                            <div class="item-number">02</div>
                            <div class="item-content">
                                <h4>Dimension Two</h4>
                                <p>Time flows differently here</p>
                            </div>
                        </div>
                        <div class="infinite-item">
                            <div class="item-number">03</div>
                            <div class="item-content">
                                <h4>Dimension Three</h4>
                                <p>Everything is connected</p>
                            </div>
                        </div>
                        <div class="infinite-item">
                            <div class="item-number">04</div>
                            <div class="item-content">
                                <h4>Dimension Four</h4>
                                <p>Past and future merge</p>
                            </div>
                        </div>
                        <div class="infinite-item">
                            <div class="item-number">05</div>
                            <div class="item-content">
                                <h4>Dimension Five</h4>
                                <p>The end is the beginning</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="infinite-indicator">
                    <div class="indicator-loop">
                        <svg viewBox="0 0 100 50">
                            <path class="infinity-path" d="M25,25 C25,10 10,10 10,25 C10,40 25,40 25,25 C25,10 40,10 40,25 C40,40 25,40 25,25" transform="translate(30,0) scale(1.5)" />
                        </svg>
                    </div>
                </div>
            </section>`
        });

        // 🎲 Probability Cloud - Random content each visit
        bm.add('probability-cloud', {
            label: '🎲 Probability Cloud',
            category: 'Multiverse',
            attributes: { class: 'fa fa-dice' },
            content: `<section class="nexus-probability-section">
                <div class="probability-field">
                    <div class="prob-particle" style="--x:10%; --y:20%; --delay:0s"></div>
                    <div class="prob-particle" style="--x:30%; --y:60%; --delay:0.5s"></div>
                    <div class="prob-particle" style="--x:50%; --y:30%; --delay:1s"></div>
                    <div class="prob-particle" style="--x:70%; --y:70%; --delay:1.5s"></div>
                    <div class="prob-particle" style="--x:90%; --y:40%; --delay:2s"></div>
                </div>
                <div class="probability-container">
                    <div class="probability-header">
                        <span class="prob-badge">🎲 Quantum Randomness</span>
                        <h2 class="prob-title">Probability Cloud</h2>
                        <p class="prob-subtitle">Reality shifts with each observation</p>
                    </div>
                    <div class="probability-outcomes">
                        <div class="outcome-card" data-probability="0.35">
                            <div class="outcome-probability">35%</div>
                            <div class="outcome-bar"><div class="bar-fill"></div></div>
                            <h4>Outcome A</h4>
                            <p>The most likely timeline where everything goes to plan</p>
                        </div>
                        <div class="outcome-card" data-probability="0.40">
                            <div class="outcome-probability">40%</div>
                            <div class="outcome-bar"><div class="bar-fill"></div></div>
                            <h4>Outcome B</h4>
                            <p>An unexpected branch leading to greater possibilities</p>
                        </div>
                        <div class="outcome-card" data-probability="0.20">
                            <div class="outcome-probability">20%</div>
                            <div class="outcome-bar"><div class="bar-fill"></div></div>
                            <h4>Outcome C</h4>
                            <p>The rare timeline where miracles happen</p>
                        </div>
                        <div class="outcome-card" data-probability="0.05">
                            <div class="outcome-probability">5%</div>
                            <div class="outcome-bar"><div class="bar-fill"></div></div>
                            <h4>Outcome D</h4>
                            <p>The nearly impossible - yet it exists somewhere</p>
                        </div>
                    </div>
                    <button class="probability-btn">
                        <span class="btn-dice">🎲</span>
                        <span class="btn-text">Collapse Probability</span>
                    </button>
                </div>
            </section>`
        });

        // 🌊 Wave Function Hero - Collapse on scroll/interaction
        bm.add('wave-function', {
            label: '🌊 Wave Function',
            category: 'Multiverse',
            attributes: { class: 'fa fa-water' },
            content: `<section class="nexus-wave-section">
                <div class="wave-background">
                    <div class="wave-line" style="--i:1"></div>
                    <div class="wave-line" style="--i:2"></div>
                    <div class="wave-line" style="--i:3"></div>
                    <div class="wave-line" style="--i:4"></div>
                    <div class="wave-line" style="--i:5"></div>
                </div>
                <div class="wave-content">
                    <div class="wave-state wave-superposition">
                        <div class="state-visual">
                            <div class="psi-symbol">Ψ</div>
                            <div class="wave-oscillation"></div>
                        </div>
                        <h2 class="wave-title">Undefined State</h2>
                        <p class="wave-text">You exist in superposition. All possibilities are open. Scroll to collapse your wave function.</p>
                    </div>
                    <div class="wave-state wave-collapsed">
                        <div class="state-visual">
                            <div class="psi-symbol collapsed">Ψ</div>
                            <div class="particle-point"></div>
                        </div>
                        <h2 class="wave-title">Collapsed!</h2>
                        <p class="wave-text">Your observation has fixed reality. Welcome to this timeline.</p>
                        <button class="wave-btn">Explore This Reality</button>
                    </div>
                </div>
                <div class="wave-progress">
                    <div class="progress-track">
                        <div class="progress-fill"></div>
                    </div>
                    <span class="progress-label">Coherence: 100%</span>
                </div>
            </section>`
        });

        // 🔮 Many-Worlds CTA - Buttons showing alternate outcomes
        bm.add('many-worlds-cta', {
            label: '🔮 Many-Worlds CTA',
            category: 'Multiverse',
            attributes: { class: 'fa fa-hand-pointer' },
            content: `<section class="nexus-manyworlds-section">
                <div class="manyworlds-bg">
                    <div class="world-bubble" style="--x:15%; --y:20%; --size:80px"></div>
                    <div class="world-bubble" style="--x:75%; --y:30%; --size:60px"></div>
                    <div class="world-bubble" style="--x:25%; --y:70%; --size:100px"></div>
                    <div class="world-bubble" style="--x:85%; --y:75%; --size:70px"></div>
                </div>
                <div class="manyworlds-container">
                    <h2 class="manyworlds-title">Every Choice Creates a Universe</h2>
                    <p class="manyworlds-subtitle">Hover to glimpse the future each button creates</p>
                    <div class="manyworlds-buttons">
                        <div class="world-btn-container">
                            <button class="world-btn world-btn-primary">
                                <span class="btn-label">Start Free Trial</span>
                            </button>
                            <div class="world-preview">
                                <div class="preview-header">In this timeline...</div>
                                <div class="preview-content">
                                    <div class="preview-icon">🚀</div>
                                    <p>You discover features that transform your workflow. 30 days later, you can't imagine life without it.</p>
                                    <div class="preview-stats">
                                        <span>+340% productivity</span>
                                        <span>∞ possibilities</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="world-btn-container">
                            <button class="world-btn world-btn-secondary">
                                <span class="btn-label">Watch Demo</span>
                            </button>
                            <div class="world-preview">
                                <div class="preview-header">In this timeline...</div>
                                <div class="preview-content">
                                    <div class="preview-icon">🎬</div>
                                    <p>A 3-minute video changes your perspective. You see what others have built and dream bigger.</p>
                                    <div class="preview-stats">
                                        <span>+1000 ideas</span>
                                        <span>Inspired</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="world-btn-container">
                            <button class="world-btn world-btn-ghost">
                                <span class="btn-label">Maybe Later</span>
                            </button>
                            <div class="world-preview preview-dark">
                                <div class="preview-header">In this timeline...</div>
                                <div class="preview-content">
                                    <div class="preview-icon">⏳</div>
                                    <p>Time passes. Opportunities shift. The multiverse continues without this branch explored.</p>
                                    <div class="preview-stats">
                                        <span>Timeline: Unknown</span>
                                        <span>Path: Uncertain</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // ⚖️ Parallel Pricing - "In another universe" pricing
        bm.add('parallel-pricing', {
            label: '⚖️ Parallel Pricing',
            category: 'Multiverse',
            attributes: { class: 'fa fa-balance-scale' },
            content: `<section class="nexus-parallel-pricing">
                <div class="pricing-dimensions">
                    <div class="dimension-layer" style="--z:-30px; --opacity:0.3"></div>
                    <div class="dimension-layer" style="--z:-20px; --opacity:0.5"></div>
                    <div class="dimension-layer" style="--z:-10px; --opacity:0.7"></div>
                </div>
                <div class="pricing-header">
                    <h2 class="pricing-title">Pricing Across Realities</h2>
                    <p class="pricing-subtitle">Compare what you'd pay in alternate dimensions</p>
                </div>
                <div class="pricing-cards">
                    <div class="pricing-card">
                        <div class="card-dimension">Earth-616</div>
                        <div class="card-header">
                            <h3>Standard</h3>
                            <div class="card-price">
                                <span class="price-current">$49</span>
                                <span class="price-period">/month</span>
                            </div>
                        </div>
                        <div class="card-alternate">
                            <div class="alternate-label">In other dimensions:</div>
                            <div class="alternate-prices">
                                <span class="alt-price">Earth-199999: $79</span>
                                <span class="alt-price">Earth-838: $99</span>
                                <span class="alt-price">Earth-TRN: ∞ credits</span>
                            </div>
                        </div>
                        <ul class="card-features">
                            <li>Stable timeline access</li>
                            <li>5 parallel projects</li>
                            <li>Basic multiverse sync</li>
                        </ul>
                        <button class="card-btn">Lock This Reality</button>
                    </div>
                    <div class="pricing-card pricing-featured">
                        <div class="featured-badge">Best Timeline</div>
                        <div class="card-dimension">Prime Reality</div>
                        <div class="card-header">
                            <h3>Professional</h3>
                            <div class="card-price">
                                <span class="price-current">$99</span>
                                <span class="price-period">/month</span>
                            </div>
                        </div>
                        <div class="card-alternate">
                            <div class="alternate-label">In other dimensions:</div>
                            <div class="alternate-prices">
                                <span class="alt-price">Earth-199999: $149</span>
                                <span class="alt-price">Earth-838: $199</span>
                                <span class="alt-price">Earth-TRN: Unavailable</span>
                            </div>
                        </div>
                        <ul class="card-features">
                            <li>All timeline access</li>
                            <li>Unlimited projects</li>
                            <li>Advanced multiverse sync</li>
                            <li>Timeline branching</li>
                        </ul>
                        <button class="card-btn btn-featured">Claim Your Universe</button>
                    </div>
                    <div class="pricing-card">
                        <div class="card-dimension">Nexus Point</div>
                        <div class="card-header">
                            <h3>Enterprise</h3>
                            <div class="card-price">
                                <span class="price-current">Custom</span>
                            </div>
                        </div>
                        <div class="card-alternate">
                            <div class="alternate-label">In other dimensions:</div>
                            <div class="alternate-prices">
                                <span class="alt-price">All timelines: One price</span>
                                <span class="alt-price">Reality: Yours to shape</span>
                            </div>
                        </div>
                        <ul class="card-features">
                            <li>Create new timelines</li>
                            <li>Multiverse administration</li>
                            <li>Nexus point control</li>
                            <li>Reality anchoring</li>
                        </ul>
                        <button class="card-btn">Contact the Council</button>
                    </div>
                </div>
            </section>`
        });

        // 🎬 Alternate Timeline Stories - What-if narratives
        bm.add('alternate-stories', {
            label: '🎬 Alternate Stories',
            category: 'Multiverse',
            attributes: { class: 'fa fa-film' },
            content: `<section class="nexus-stories-section">
                <div class="stories-header">
                    <h2 class="stories-title">What If...?</h2>
                    <p class="stories-subtitle">Explore alternate timelines of our journey</p>
                </div>
                <div class="stories-container">
                    <div class="story-card">
                        <div class="story-timeline">Timeline #1</div>
                        <div class="story-cover">
                            <div class="cover-gradient"></div>
                            <div class="cover-icon">🌟</div>
                        </div>
                        <div class="story-content">
                            <h3>What if we launched a year earlier?</h3>
                            <p>In this timeline, we pioneered the revolution. Early adopters became legends.</p>
                            <div class="story-outcome">
                                <span class="outcome-label">Outcome:</span>
                                <span class="outcome-value">Market Leader</span>
                            </div>
                        </div>
                    </div>
                    <div class="story-card">
                        <div class="story-timeline">Timeline #2</div>
                        <div class="story-cover">
                            <div class="cover-gradient"></div>
                            <div class="cover-icon">⚡</div>
                        </div>
                        <div class="story-content">
                            <h3>What if we chose a different path?</h3>
                            <p>In this timeline, we zigged when others zagged. The results were... unexpected.</p>
                            <div class="story-outcome">
                                <span class="outcome-label">Outcome:</span>
                                <span class="outcome-value">Cult Classic</span>
                            </div>
                        </div>
                    </div>
                    <div class="story-card story-current">
                        <div class="story-timeline">Current Timeline</div>
                        <div class="story-cover">
                            <div class="cover-gradient"></div>
                            <div class="cover-icon">✨</div>
                        </div>
                        <div class="story-content">
                            <h3>The timeline you're in right now</h3>
                            <p>This is where every decision led. And it's exactly where you need to be.</p>
                            <div class="story-outcome">
                                <span class="outcome-label">Outcome:</span>
                                <span class="outcome-value">You're Here</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🌐 Dimension Hopper - Navigate between content dimensions
        bm.add('dimension-hopper', {
            label: '🌐 Dimension Hopper',
            category: 'Multiverse',
            attributes: { class: 'fa fa-globe' },
            content: `<section class="nexus-hopper-section">
                <div class="hopper-container">
                    <div class="hopper-device">
                        <div class="device-screen">
                            <div class="screen-content dimension-1 active">
                                <div class="dim-header">Dimension 1: Origin</div>
                                <div class="dim-visual">🌍</div>
                                <div class="dim-desc">The starting point of all journeys</div>
                            </div>
                            <div class="screen-content dimension-2">
                                <div class="dim-header">Dimension 2: Innovation</div>
                                <div class="dim-visual">🔬</div>
                                <div class="dim-desc">Where ideas become reality</div>
                            </div>
                            <div class="screen-content dimension-3">
                                <div class="dim-header">Dimension 3: Community</div>
                                <div class="dim-visual">🤝</div>
                                <div class="dim-desc">Stronger together across all realities</div>
                            </div>
                            <div class="screen-content dimension-4">
                                <div class="dim-header">Dimension 4: Future</div>
                                <div class="dim-visual">🚀</div>
                                <div class="dim-desc">The destination is the journey</div>
                            </div>
                        </div>
                        <div class="device-controls">
                            <button class="hop-btn hop-prev">◀ Previous</button>
                            <div class="hop-indicator">
                                <span class="hop-dot active" data-dim="1"></span>
                                <span class="hop-dot" data-dim="2"></span>
                                <span class="hop-dot" data-dim="3"></span>
                                <span class="hop-dot" data-dim="4"></span>
                            </div>
                            <button class="hop-btn hop-next">Next ▶</button>
                        </div>
                    </div>
                    <div class="hopper-coordinates">
                        <span class="coord">X: 47.3°</span>
                        <span class="coord">Y: 122.8°</span>
                        <span class="coord">Z: ∞</span>
                        <span class="coord coord-d">D: 1</span>
                    </div>
                </div>
            </section>`
        });

        // 🔄 Reality Anchor - Fixed point across dimensions
        bm.add('reality-anchor', {
            label: '🔄 Reality Anchor',
            category: 'Multiverse',
            attributes: { class: 'fa fa-anchor' },
            content: `<section class="nexus-anchor-section">
                <div class="anchor-void">
                    <div class="void-streams">
                        <div class="stream" style="--delay:0s"></div>
                        <div class="stream" style="--delay:1s"></div>
                        <div class="stream" style="--delay:2s"></div>
                    </div>
                </div>
                <div class="anchor-container">
                    <div class="anchor-visual">
                        <div class="anchor-rings">
                            <div class="anchor-ring ring-outer"></div>
                            <div class="anchor-ring ring-middle"></div>
                            <div class="anchor-ring ring-inner"></div>
                        </div>
                        <div class="anchor-core">
                            <div class="core-symbol">⚓</div>
                            <div class="core-pulse"></div>
                        </div>
                    </div>
                    <div class="anchor-content">
                        <h2 class="anchor-title">Reality Anchor</h2>
                        <p class="anchor-text">A fixed point in the multiverse. While infinite realities shift and change, this remains constant.</p>
                        <div class="anchor-stats">
                            <div class="anchor-stat">
                                <span class="stat-value">∞</span>
                                <span class="stat-label">Timelines Connected</span>
                            </div>
                            <div class="anchor-stat">
                                <span class="stat-value">1</span>
                                <span class="stat-label">Nexus Point</span>
                            </div>
                            <div class="anchor-stat">
                                <span class="stat-value">0</span>
                                <span class="stat-label">Variants</span>
                            </div>
                        </div>
                        <button class="anchor-btn">Anchor Your Experience</button>
                    </div>
                </div>
            </section>`
        });

        // ============================================================
        // 🕳️ OMNIVERSE-TIER BLOCKS - Beyond All Existence
        // ============================================================

        // 🕳️ Void Genesis - Content emerging from absolute nothingness
        bm.add('void-genesis', {
            label: '🕳️ Void Genesis',
            category: 'Omniverse',
            attributes: { class: 'fa fa-circle' },
            content: `<section class="nexus-void-section">
                <div class="void-absolute"></div>
                <div class="void-emergence">
                    <div class="void-particle" style="--delay: 0s; --x: 20%; --y: 30%;"></div>
                    <div class="void-particle" style="--delay: 0.5s; --x: 80%; --y: 20%;"></div>
                    <div class="void-particle" style="--delay: 1s; --x: 50%; --y: 70%;"></div>
                    <div class="void-particle" style="--delay: 1.5s; --x: 30%; --y: 80%;"></div>
                    <div class="void-particle" style="--delay: 2s; --x: 70%; --y: 50%;"></div>
                </div>
                <div class="void-content">
                    <div class="void-symbol">◯</div>
                    <h2 class="void-title">From Nothing, Everything</h2>
                    <p class="void-text">Before the first thought, before the first spark, there was the Void. And from the Void, all possibilities emerged.</p>
                    <div class="void-emergence-cards">
                        <div class="emergence-card" style="--delay: 0.2s;">
                            <span class="emergence-icon">💫</span>
                            <span class="emergence-label">Energy</span>
                        </div>
                        <div class="emergence-card" style="--delay: 0.4s;">
                            <span class="emergence-icon">⚛️</span>
                            <span class="emergence-label">Matter</span>
                        </div>
                        <div class="emergence-card" style="--delay: 0.6s;">
                            <span class="emergence-icon">🌀</span>
                            <span class="emergence-label">Space</span>
                        </div>
                        <div class="emergence-card" style="--delay: 0.8s;">
                            <span class="emergence-icon">⏳</span>
                            <span class="emergence-label">Time</span>
                        </div>
                    </div>
                    <button class="void-btn">Witness Genesis</button>
                </div>
            </section>`
        });

        // 📜 Cosmic Law Editor - Rewrite the rules of reality
        bm.add('cosmic-law-editor', {
            label: '📜 Cosmic Laws',
            category: 'Omniverse',
            attributes: { class: 'fa fa-scroll' },
            content: `<section class="nexus-cosmic-law-section">
                <div class="law-nebula"></div>
                <div class="law-header">
                    <span class="law-badge">📜 Fundamental Constants</span>
                    <h2 class="law-title">The Laws of Reality</h2>
                    <p class="law-subtitle">These constants define the fabric of existence. In other omniverses, they differ.</p>
                </div>
                <div class="law-editor-container">
                    <div class="law-panel">
                        <div class="law-item">
                            <div class="law-icon">🔆</div>
                            <div class="law-details">
                                <span class="law-name">Speed of Light</span>
                                <span class="law-value">299,792,458 m/s</span>
                            </div>
                            <div class="law-slider">
                                <div class="slider-track">
                                    <div class="slider-fill" style="width: 100%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="law-item">
                            <div class="law-icon">🌍</div>
                            <div class="law-details">
                                <span class="law-name">Gravity Constant</span>
                                <span class="law-value">6.674×10⁻¹¹</span>
                            </div>
                            <div class="law-slider">
                                <div class="slider-track">
                                    <div class="slider-fill" style="width: 45%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="law-item">
                            <div class="law-icon">⚡</div>
                            <div class="law-details">
                                <span class="law-name">Planck Constant</span>
                                <span class="law-value">6.626×10⁻³⁴</span>
                            </div>
                            <div class="law-slider">
                                <div class="slider-track">
                                    <div class="slider-fill" style="width: 30%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="law-item">
                            <div class="law-icon">🧲</div>
                            <div class="law-details">
                                <span class="law-name">Fine Structure</span>
                                <span class="law-value">α ≈ 1/137</span>
                            </div>
                            <div class="law-slider">
                                <div class="slider-track">
                                    <div class="slider-fill" style="width: 60%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="law-preview">
                        <div class="preview-universe">
                            <div class="preview-core"></div>
                            <div class="preview-orbit orbit-1"></div>
                            <div class="preview-orbit orbit-2"></div>
                            <div class="preview-orbit orbit-3"></div>
                        </div>
                        <span class="preview-label">Universe Simulation</span>
                    </div>
                </div>
            </section>`
        });

        // 🧬 Existence DNA - The fundamental code of being
        bm.add('existence-dna', {
            label: '🧬 Existence DNA',
            category: 'Omniverse',
            attributes: { class: 'fa fa-dna' },
            content: `<section class="nexus-dna-section">
                <div class="dna-void"></div>
                <div class="dna-helix-container">
                    <div class="dna-helix">
                        <div class="dna-strand strand-left">
                            <div class="dna-node" style="--i: 0;"><span>Being</span></div>
                            <div class="dna-node" style="--i: 1;"><span>Time</span></div>
                            <div class="dna-node" style="--i: 2;"><span>Space</span></div>
                            <div class="dna-node" style="--i: 3;"><span>Energy</span></div>
                            <div class="dna-node" style="--i: 4;"><span>Matter</span></div>
                            <div class="dna-node" style="--i: 5;"><span>Thought</span></div>
                        </div>
                        <div class="dna-strand strand-right">
                            <div class="dna-node" style="--i: 0;"><span>Void</span></div>
                            <div class="dna-node" style="--i: 1;"><span>Eternity</span></div>
                            <div class="dna-node" style="--i: 2;"><span>Infinity</span></div>
                            <div class="dna-node" style="--i: 3;"><span>Entropy</span></div>
                            <div class="dna-node" style="--i: 4;"><span>Spirit</span></div>
                            <div class="dna-node" style="--i: 5;"><span>Dream</span></div>
                        </div>
                        <div class="dna-connections">
                            <div class="dna-bridge" style="--i: 0;"></div>
                            <div class="dna-bridge" style="--i: 1;"></div>
                            <div class="dna-bridge" style="--i: 2;"></div>
                            <div class="dna-bridge" style="--i: 3;"></div>
                            <div class="dna-bridge" style="--i: 4;"></div>
                            <div class="dna-bridge" style="--i: 5;"></div>
                        </div>
                    </div>
                </div>
                <div class="dna-content">
                    <h2 class="dna-title">The Code of Existence</h2>
                    <p class="dna-text">Every reality is written in the same language. This is the source code of the Omniverse.</p>
                    <button class="dna-btn">Decode Reality</button>
                </div>
            </section>`
        });

        // ⚫ Singularity Core - Where all possibilities collapse
        bm.add('singularity-core', {
            label: '⚫ Singularity',
            category: 'Omniverse',
            attributes: { class: 'fa fa-circle' },
            content: `<section class="nexus-singularity-section">
                <div class="singularity-void"></div>
                <div class="singularity-accretion">
                    <div class="accretion-ring ring-1"></div>
                    <div class="accretion-ring ring-2"></div>
                    <div class="accretion-ring ring-3"></div>
                </div>
                <div class="singularity-core">
                    <div class="event-horizon"></div>
                    <div class="singularity-point"></div>
                </div>
                <div class="singularity-content">
                    <h2 class="singularity-title">The Singularity</h2>
                    <p class="singularity-text">Where infinite density meets zero volume. All information, all possibilities, compressed into a single point.</p>
                    <div class="singularity-stats">
                        <div class="sing-stat">
                            <span class="stat-symbol">∞</span>
                            <span class="stat-name">Density</span>
                        </div>
                        <div class="sing-stat">
                            <span class="stat-symbol">0</span>
                            <span class="stat-name">Volume</span>
                        </div>
                        <div class="sing-stat">
                            <span class="stat-symbol">?</span>
                            <span class="stat-name">Beyond</span>
                        </div>
                    </div>
                    <button class="singularity-btn">Cross the Horizon</button>
                </div>
            </section>`
        });

        // 🌅 Creation Forge - Birth new universes
        bm.add('creation-forge', {
            label: '🌅 Creation Forge',
            category: 'Omniverse',
            attributes: { class: 'fa fa-sun' },
            content: `<section class="nexus-forge-section">
                <div class="forge-cosmos"></div>
                <div class="forge-container">
                    <div class="forge-crucible">
                        <div class="crucible-glow"></div>
                        <div class="crucible-core">
                            <div class="forming-universe">
                                <div class="universe-spark"></div>
                            </div>
                        </div>
                        <div class="crucible-ring"></div>
                    </div>
                    <div class="forge-controls">
                        <div class="forge-ingredient">
                            <span class="ingredient-icon">⚡</span>
                            <span class="ingredient-name">Pure Energy</span>
                            <div class="ingredient-bar"><div class="bar-fill" style="width: 80%;"></div></div>
                        </div>
                        <div class="forge-ingredient">
                            <span class="ingredient-icon">🌀</span>
                            <span class="ingredient-name">Spacetime Fabric</span>
                            <div class="ingredient-bar"><div class="bar-fill" style="width: 65%;"></div></div>
                        </div>
                        <div class="forge-ingredient">
                            <span class="ingredient-icon">💫</span>
                            <span class="ingredient-name">Cosmic Intent</span>
                            <div class="ingredient-bar"><div class="bar-fill" style="width: 90%;"></div></div>
                        </div>
                    </div>
                </div>
                <div class="forge-content">
                    <h2 class="forge-title">The Creation Forge</h2>
                    <p class="forge-text">Where new realities are born. Combine the fundamental essences and witness the birth of a universe.</p>
                    <button class="forge-btn">🌟 Ignite Creation</button>
                </div>
            </section>`
        });

        // 👁️ Omniscient View - See all at once
        bm.add('omniscient-view', {
            label: '👁️ Omniscient View',
            category: 'Omniverse',
            attributes: { class: 'fa fa-eye' },
            content: `<section class="nexus-omniscient-section">
                <div class="omni-void"></div>
                <div class="omni-eye">
                    <div class="eye-outer">
                        <div class="eye-rays">
                            <div class="ray" style="--angle: 0deg;"></div>
                            <div class="ray" style="--angle: 30deg;"></div>
                            <div class="ray" style="--angle: 60deg;"></div>
                            <div class="ray" style="--angle: 90deg;"></div>
                            <div class="ray" style="--angle: 120deg;"></div>
                            <div class="ray" style="--angle: 150deg;"></div>
                            <div class="ray" style="--angle: 180deg;"></div>
                            <div class="ray" style="--angle: 210deg;"></div>
                            <div class="ray" style="--angle: 240deg;"></div>
                            <div class="ray" style="--angle: 270deg;"></div>
                            <div class="ray" style="--angle: 300deg;"></div>
                            <div class="ray" style="--angle: 330deg;"></div>
                        </div>
                        <div class="eye-iris">
                            <div class="iris-pattern"></div>
                            <div class="eye-pupil">
                                <div class="pupil-galaxies"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="omni-visions">
                    <div class="vision-card" style="--delay: 0s;">
                        <span class="vision-time">Past</span>
                        <span class="vision-icon">🏛️</span>
                    </div>
                    <div class="vision-card" style="--delay: 0.2s;">
                        <span class="vision-time">Present</span>
                        <span class="vision-icon">👁️</span>
                    </div>
                    <div class="vision-card" style="--delay: 0.4s;">
                        <span class="vision-time">Future</span>
                        <span class="vision-icon">🔮</span>
                    </div>
                </div>
                <div class="omni-content">
                    <h2 class="omni-title">The Omniscient Eye</h2>
                    <p class="omni-text">To see all timelines, all dimensions, all possibilities simultaneously. This is true awareness.</p>
                </div>
            </section>`
        });

        // 🔱 Primordial Forces - The four fundamentals
        bm.add('primordial-forces', {
            label: '🔱 Primordial Forces',
            category: 'Omniverse',
            attributes: { class: 'fa fa-bolt' },
            content: `<section class="nexus-primordial-section">
                <div class="primordial-void"></div>
                <div class="primordial-header">
                    <span class="primordial-badge">🔱 The Four Pillars</span>
                    <h2 class="primordial-title">Primordial Forces</h2>
                    <p class="primordial-subtitle">The fundamental interactions that shape all reality across every universe.</p>
                </div>
                <div class="forces-container">
                    <div class="force-card force-gravity">
                        <div class="force-visual">
                            <div class="gravity-well"></div>
                            <div class="gravity-object obj-1"></div>
                            <div class="gravity-object obj-2"></div>
                        </div>
                        <h3 class="force-name">Gravity</h3>
                        <p class="force-desc">The curvature of spacetime itself</p>
                        <span class="force-strength">Relative: 1</span>
                    </div>
                    <div class="force-card force-em">
                        <div class="force-visual">
                            <div class="em-field">
                                <div class="em-wave"></div>
                            </div>
                        </div>
                        <h3 class="force-name">Electromagnetic</h3>
                        <p class="force-desc">Light, electricity, magnetism unified</p>
                        <span class="force-strength">Relative: 10³⁶</span>
                    </div>
                    <div class="force-card force-strong">
                        <div class="force-visual">
                            <div class="strong-nucleus">
                                <div class="quark q1"></div>
                                <div class="quark q2"></div>
                                <div class="quark q3"></div>
                                <div class="gluon-field"></div>
                            </div>
                        </div>
                        <h3 class="force-name">Strong Nuclear</h3>
                        <p class="force-desc">Binds quarks, holds atoms together</p>
                        <span class="force-strength">Relative: 10³⁸</span>
                    </div>
                    <div class="force-card force-weak">
                        <div class="force-visual">
                            <div class="weak-decay">
                                <div class="decay-particle"></div>
                                <div class="decay-products"></div>
                            </div>
                        </div>
                        <h3 class="force-name">Weak Nuclear</h3>
                        <p class="force-desc">Governs radioactive decay</p>
                        <span class="force-strength">Relative: 10²⁵</span>
                    </div>
                </div>
            </section>`
        });

        // 📖 Akashic Records - Infinite knowledge library
        bm.add('akashic-records', {
            label: '📖 Akashic Records',
            category: 'Omniverse',
            attributes: { class: 'fa fa-book' },
            content: `<section class="nexus-akashic-section">
                <div class="akashic-void"></div>
                <div class="akashic-library">
                    <div class="library-shelves">
                        <div class="shelf-row">
                            <div class="akashic-book" style="--hue: 260;"></div>
                            <div class="akashic-book" style="--hue: 200;"></div>
                            <div class="akashic-book" style="--hue: 320;"></div>
                            <div class="akashic-book" style="--hue: 40;"></div>
                            <div class="akashic-book" style="--hue: 180;"></div>
                        </div>
                        <div class="shelf-row">
                            <div class="akashic-book" style="--hue: 280;"></div>
                            <div class="akashic-book" style="--hue: 160;"></div>
                            <div class="akashic-book" style="--hue: 60;"></div>
                            <div class="akashic-book" style="--hue: 340;"></div>
                            <div class="akashic-book" style="--hue: 220;"></div>
                        </div>
                    </div>
                    <div class="akashic-glow"></div>
                </div>
                <div class="akashic-content">
                    <div class="akashic-symbol">📖</div>
                    <h2 class="akashic-title">The Akashic Records</h2>
                    <p class="akashic-text">Every thought ever conceived, every event that ever occurred, every possibility that could exist — all recorded here in the infinite library.</p>
                    <div class="akashic-search">
                        <input type="text" class="akashic-input" placeholder="Search all knowledge..." />
                        <button class="akashic-btn">Query the Infinite</button>
                    </div>
                    <div class="akashic-categories">
                        <span class="akashic-tag">Past Lives</span>
                        <span class="akashic-tag">Future Events</span>
                        <span class="akashic-tag">Parallel Selves</span>
                        <span class="akashic-tag">Universal Truths</span>
                    </div>
                </div>
            </section>`
        });

        // ⏳ Eternity Clock - Time before time
        bm.add('eternity-clock', {
            label: '⏳ Eternity Clock',
            category: 'Omniverse',
            attributes: { class: 'fa fa-clock' },
            content: `<section class="nexus-eternity-section">
                <div class="eternity-void"></div>
                <div class="eternity-clock">
                    <div class="clock-outer-ring">
                        <div class="eon-marker" style="--angle: 0deg;"><span>∞</span></div>
                        <div class="eon-marker" style="--angle: 90deg;"><span>α</span></div>
                        <div class="eon-marker" style="--angle: 180deg;"><span>Ω</span></div>
                        <div class="eon-marker" style="--angle: 270deg;"><span>◯</span></div>
                    </div>
                    <div class="clock-inner-ring"></div>
                    <div class="clock-face">
                        <div class="clock-hand hand-eon"></div>
                        <div class="clock-hand hand-era"></div>
                        <div class="clock-hand hand-epoch"></div>
                        <div class="clock-center">
                            <div class="center-gem"></div>
                        </div>
                    </div>
                </div>
                <div class="eternity-content">
                    <h2 class="eternity-title">The Eternity Clock</h2>
                    <p class="eternity-text">Measuring time before time began, and time after time ends. Each tick spans a trillion years.</p>
                    <div class="eternity-readings">
                        <div class="reading">
                            <span class="reading-label">Eons Passed</span>
                            <span class="reading-value">∞</span>
                        </div>
                        <div class="reading">
                            <span class="reading-label">Eons Remaining</span>
                            <span class="reading-value">∞</span>
                        </div>
                        <div class="reading">
                            <span class="reading-label">Current Moment</span>
                            <span class="reading-value">NOW</span>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // 🎭 Consciousness Matrix - The observer creates reality
        bm.add('consciousness-matrix', {
            label: '🎭 Consciousness',
            category: 'Omniverse',
            attributes: { class: 'fa fa-brain' },
            content: `<section class="nexus-consciousness-section">
                <div class="consciousness-void"></div>
                <div class="consciousness-grid">
                    <div class="thought-node" style="--x: 20%; --y: 30%; --delay: 0s;"></div>
                    <div class="thought-node" style="--x: 80%; --y: 25%; --delay: 0.3s;"></div>
                    <div class="thought-node" style="--x: 50%; --y: 60%; --delay: 0.6s;"></div>
                    <div class="thought-node" style="--x: 30%; --y: 70%; --delay: 0.9s;"></div>
                    <div class="thought-node" style="--x: 70%; --y: 75%; --delay: 1.2s;"></div>
                    <div class="thought-connection" style="--x1: 20%; --y1: 30%; --x2: 50%; --y2: 60%;"></div>
                    <div class="thought-connection" style="--x1: 80%; --y1: 25%; --x2: 50%; --y2: 60%;"></div>
                    <div class="thought-connection" style="--x1: 50%; --y1: 60%; --x2: 30%; --y2: 70%;"></div>
                    <div class="thought-connection" style="--x1: 50%; --y1: 60%; --x2: 70%; --y2: 75%;"></div>
                </div>
                <div class="consciousness-center">
                    <div class="awareness-sphere">
                        <div class="sphere-layer layer-1"></div>
                        <div class="sphere-layer layer-2"></div>
                        <div class="sphere-layer layer-3"></div>
                        <div class="sphere-core">I AM</div>
                    </div>
                </div>
                <div class="consciousness-content">
                    <h2 class="consciousness-title">The Consciousness Matrix</h2>
                    <p class="consciousness-text">Reality exists because it is observed. The observer and the observed are one. You are the universe experiencing itself.</p>
                    <div class="awareness-levels">
                        <div class="level level-physical">Physical</div>
                        <div class="level level-mental">Mental</div>
                        <div class="level level-spiritual">Spiritual</div>
                        <div class="level level-cosmic">Cosmic</div>
                    </div>
                </div>
            </section>`
        });

        // 💫 Big Bang Button - The ultimate CTA
        bm.add('big-bang-button', {
            label: '💫 Big Bang CTA',
            category: 'Omniverse',
            attributes: { class: 'fa fa-bomb' },
            content: `<section class="nexus-bigbang-section">
                <div class="bigbang-void"></div>
                <div class="bigbang-container">
                    <div class="bigbang-prelude">
                        <div class="prelude-particles">
                            <div class="prelude-particle" style="--angle: 0deg; --delay: 0s;"></div>
                            <div class="prelude-particle" style="--angle: 45deg; --delay: 0.1s;"></div>
                            <div class="prelude-particle" style="--angle: 90deg; --delay: 0.2s;"></div>
                            <div class="prelude-particle" style="--angle: 135deg; --delay: 0.3s;"></div>
                            <div class="prelude-particle" style="--angle: 180deg; --delay: 0.4s;"></div>
                            <div class="prelude-particle" style="--angle: 225deg; --delay: 0.5s;"></div>
                            <div class="prelude-particle" style="--angle: 270deg; --delay: 0.6s;"></div>
                            <div class="prelude-particle" style="--angle: 315deg; --delay: 0.7s;"></div>
                        </div>
                        <div class="singularity-seed"></div>
                    </div>
                    <button class="bigbang-btn">
                        <span class="btn-text">CREATE UNIVERSE</span>
                        <span class="btn-icon">💥</span>
                    </button>
                    <p class="bigbang-warning">⚠️ Warning: This action will create 10⁸⁰ atoms</p>
                </div>
                <div class="bigbang-content">
                    <h2 class="bigbang-title">The Ultimate Action</h2>
                    <p class="bigbang-text">One click. One moment. An entire universe springs into existence. Stars ignite, galaxies form, life emerges. All from a single decision.</p>
                </div>
            </section>`
        });

        // 🌌 Cosmic Web - The structure connecting everything
        bm.add('cosmic-web', {
            label: '🌌 Cosmic Web',
            category: 'Omniverse',
            attributes: { class: 'fa fa-project-diagram' },
            content: `<section class="nexus-cosmicweb-section">
                <div class="cosmicweb-void"></div>
                <div class="cosmicweb-structure">
                    <svg class="web-svg" viewBox="0 0 400 300" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="webGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#8b5cf6;stop-opacity:0.8" />
                                <stop offset="100%" style="stop-color:#06b6d4;stop-opacity:0.8" />
                            </linearGradient>
                        </defs>
                        <!-- Filaments -->
                        <path class="web-filament" d="M50,150 Q100,100 150,120 T250,100 T350,150" />
                        <path class="web-filament" d="M50,200 Q120,180 200,200 T350,180" />
                        <path class="web-filament" d="M100,50 Q150,100 180,150 T200,250" />
                        <path class="web-filament" d="M300,50 Q280,120 260,180 T280,280" />
                        <path class="web-filament" d="M150,120 Q200,150 260,140" />
                        <!-- Nodes (Galaxy Clusters) -->
                        <circle class="web-node" cx="50" cy="150" r="8" />
                        <circle class="web-node" cx="150" cy="120" r="12" />
                        <circle class="web-node" cx="250" cy="100" r="6" />
                        <circle class="web-node" cx="350" cy="150" r="10" />
                        <circle class="web-node" cx="200" cy="200" r="14" />
                        <circle class="web-node" cx="180" cy="150" r="8" />
                        <circle class="web-node" cx="260" cy="140" r="6" />
                    </svg>
                    <div class="web-glow"></div>
                </div>
                <div class="cosmicweb-content">
                    <h2 class="cosmicweb-title">The Cosmic Web</h2>
                    <p class="cosmicweb-text">The largest structure in the universe. Filaments of dark matter and gas connecting galaxy clusters across billions of light years.</p>
                    <div class="web-stats">
                        <div class="web-stat">
                            <span class="stat-value">10⁸</span>
                            <span class="stat-label">Light Years Span</span>
                        </div>
                        <div class="web-stat">
                            <span class="stat-value">10¹¹</span>
                            <span class="stat-label">Galaxies Connected</span>
                        </div>
                        <div class="web-stat">
                            <span class="stat-value">85%</span>
                            <span class="stat-label">Dark Matter</span>
                        </div>
                    </div>
                </div>
            </section>`
        });

        // Use Admin Toast System from Gold Standard
        function showToast(message, type = 'success') {
            if (window.AdminToast) {
                const title = type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Info';
                window.AdminToast[type](title, message);
            } else {
                // Fallback if AdminToast not loaded yet
                alert(message);
            }
        }

        function savePage(isAutosave = false) {
            if (isSaving) return;

            const html = editor.getHtml();
            const css = editor.getCss();
            const title = document.getElementById('page-title').value.trim();
            const slug = document.getElementById('page-slug').value.trim();
            const published = document.getElementById('is-published').checked ? 1 : 0;
            const publishAt = document.getElementById('publish-at').value;
            const metaTitle = document.getElementById('meta-title').value.trim();
            const metaDesc = document.getElementById('meta-description').value.trim();
            const noindex = document.getElementById('noindex').checked ? 1 : 0;
            const showInMenu = document.getElementById('show-in-menu').checked ? 1 : 0;
            const menuLocation = document.getElementById('menu-location').value;

            if (!isAutosave) {
                if (!title) {
                    showToast('Please enter a page title', 'error');
                    document.getElementById('page-title').focus();
                    return;
                }
                if (!slug) {
                    showToast('Please enter a URL slug', 'error');
                    document.getElementById('page-slug').focus();
                    return;
                }
            }

            const formData = new FormData();
            formData.append('id', pageId);
            formData.append('title', title || 'Untitled');
            formData.append('slug', slug || 'page-' + pageId);
            formData.append('html', `<style>${css}</style>${html}`);
            if (published) formData.append('is_published', 1);
            if (publishAt) formData.append('publish_at', publishAt);
            formData.append('meta_title', metaTitle);
            formData.append('meta_description', metaDesc);
            if (noindex) formData.append('noindex', 1);
            formData.append('show_in_menu', showInMenu);
            formData.append('menu_location', menuLocation);
            if (isAutosave) formData.append('autosave', '1');

            isSaving = true;
            const btn = document.getElementById('btn-save');
            if (!isAutosave) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            }

            fetch(basePath + '/admin-legacy/pages/save', {
                method: 'POST',
                credentials: 'include',
                body: formData
            })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(data => {
                if (data.success) {
                    if (isAutosave) {
                        updateAutosaveStatus('Auto-saved at ' + new Date().toLocaleTimeString());
                    } else {
                        showToast('Page saved successfully!', 'success');
                        markSaved();
                    }
                } else {
                    if (!isAutosave) showToast(data.error || 'Failed to save', 'error');
                }
            })
            .catch(err => {
                if (!isAutosave) showToast('Failed to save: ' + err.message, 'error');
            })
            .finally(() => {
                isSaving = false;
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
            });
        }

        // Ctrl+S shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                savePage();
            }
        });
    </script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
