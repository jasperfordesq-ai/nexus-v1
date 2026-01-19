<?php
/**
 * Newsletter Template Create/Edit Form - Gold Standard Admin UI
 * Holographic Glassmorphism Design
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$isEdit = !empty($template);

// Admin header configuration
$adminPageTitle = $isEdit ? 'Edit Template' : 'Create Template';
$adminPageSubtitle = $isEdit ? 'Modify your email template' : 'Design a new reusable email template';
$adminPageIcon = 'fa-solid fa-palette';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="template-form-container">
    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash-error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash-success">
            <i class="fa-solid fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Action Bar -->
    <div class="action-bar">
        <a href="<?= $basePath ?>/admin/newsletters/templates" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Templates
        </a>
        <?php if ($isEdit): ?>
        <a href="<?= $basePath ?>/admin/newsletters/templates/preview/<?= $template['id'] ?>" target="_blank" class="preview-link">
            <i class="fa-solid fa-eye"></i> Preview Template
        </a>
        <?php endif; ?>
    </div>

    <form action="<?= $basePath ?>/admin/newsletters/templates/<?= $isEdit ? 'update/' . $template['id'] : 'store' ?>" method="POST" id="templateForm">
        <?= \Nexus\Core\Csrf::input() ?>

        <div class="form-grid">
            <!-- Main Content Column -->
            <div class="main-column">
                <!-- Template Details Card -->
                <div class="glass-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fa-solid fa-info-circle"></i>
                        </div>
                        <h2>Template Details</h2>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Template Name <span class="required">*</span></label>
                        <input type="text"
                               name="name"
                               value="<?= htmlspecialchars($template['name'] ?? '') ?>"
                               required
                               class="form-input"
                               placeholder="e.g., Monthly Newsletter, Event Announcement">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description"
                                  rows="2"
                                  class="form-textarea"
                                  placeholder="Brief description of when to use this template"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="custom" <?= ($template['category'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>Custom</option>
                                <option value="saved" <?= ($template['category'] ?? '') === 'saved' ? 'selected' : '' ?>>Saved from Newsletter</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1" <?= ($template['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= ($template['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Email Settings Card -->
                <div class="glass-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <h2>Email Settings</h2>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default Subject Line</label>
                        <input type="text"
                               name="subject"
                               value="<?= htmlspecialchars($template['subject'] ?? '') ?>"
                               class="form-input"
                               placeholder="Email subject when using this template">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Preview Text</label>
                        <input type="text"
                               name="preview_text"
                               value="<?= htmlspecialchars($template['preview_text'] ?? '') ?>"
                               class="form-input"
                               placeholder="Text shown in email client preview (after subject)">
                        <p class="form-hint">This appears after the subject line in most email clients.</p>
                    </div>
                </div>

                <!-- Template Content Card -->
                <div class="glass-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fa-solid fa-code"></i>
                        </div>
                        <h2>Template Content (HTML)</h2>
                    </div>

                    <!-- Variables Reference -->
                    <div class="variables-panel">
                        <p class="variables-title">
                            <i class="fa-solid fa-brackets-curly"></i>
                            Available Variables
                        </p>
                        <div class="variables-grid">
                            <button type="button" class="variable-tag" onclick="insertVariable('first_name')">{{first_name}}</button>
                            <button type="button" class="variable-tag" onclick="insertVariable('last_name')">{{last_name}}</button>
                            <button type="button" class="variable-tag" onclick="insertVariable('email')">{{email}}</button>
                            <button type="button" class="variable-tag" onclick="insertVariable('tenant_name')">{{tenant_name}}</button>
                            <button type="button" class="variable-tag" onclick="insertVariable('unsubscribe_link')">{{unsubscribe_link}}</button>
                            <button type="button" class="variable-tag" onclick="insertVariable('view_in_browser')">{{view_in_browser}}</button>
                        </div>
                    </div>

                    <!-- Code Editor -->
                    <div class="code-editor-wrapper">
                        <div class="editor-toolbar">
                            <span class="editor-label">
                                <i class="fa-solid fa-file-code"></i> HTML Editor
                            </span>
                            <div class="editor-actions">
                                <button type="button" class="editor-btn" onclick="formatCode()" title="Format Code">
                                    <i class="fa-solid fa-align-left"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="toggleFullscreen()" title="Toggle Fullscreen">
                                    <i class="fa-solid fa-expand"></i>
                                </button>
                            </div>
                        </div>
                        <textarea name="content"
                                  id="template-content"
                                  rows="25"
                                  class="code-editor"
                                  placeholder="Enter your HTML email template here..."><?= htmlspecialchars($template['content'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="sidebar-column">
                <!-- Actions Card -->
                <div class="glass-card actions-card">
                    <h3 class="sidebar-title">Actions</h3>

                    <div class="actions-stack">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= $isEdit ? 'Update Template' : 'Save Template' ?>
                        </button>

                        <?php if ($isEdit): ?>
                        <a href="<?= $basePath ?>/admin/newsletters/templates/duplicate/<?= $template['id'] ?>" class="btn-secondary">
                            <i class="fa-solid fa-copy"></i>
                            Duplicate Template
                        </a>

                        <button type="button" class="btn-danger" onclick="confirmDelete()">
                            <i class="fa-solid fa-trash"></i>
                            Delete Template
                        </button>
                        <?php endif; ?>

                        <a href="<?= $basePath ?>/admin/newsletters/templates" class="btn-cancel">
                            Cancel
                        </a>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="glass-card tips-card">
                    <h3 class="tips-title">
                        <i class="fa-solid fa-lightbulb"></i>
                        Email Best Practices
                    </h3>
                    <ul class="tips-list">
                        <li>Use inline CSS for best email client compatibility</li>
                        <li>Keep content width under 600px for mobile</li>
                        <li>Test with multiple email clients before sending</li>
                        <li>Always include an unsubscribe link</li>
                        <li>Use web-safe fonts (Arial, Helvetica, Georgia)</li>
                        <li>Include alt text for all images</li>
                    </ul>
                </div>

                <?php if ($isEdit): ?>
                <!-- Usage Stats Card -->
                <div class="glass-card stats-card">
                    <h3 class="sidebar-title">Template Statistics</h3>

                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon usage">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?= $template['use_count'] ?? 0 ?></div>
                                <div class="stat-label">Times Used</div>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon date">
                                <i class="fa-solid fa-calendar-plus"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value-sm"><?= date('M j, Y', strtotime($template['created_at'] ?? 'now')) ?></div>
                                <div class="stat-label">Created</div>
                            </div>
                        </div>

                        <?php if (!empty($template['updated_at'])): ?>
                        <div class="stat-item">
                            <div class="stat-icon updated">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value-sm"><?= date('M j, Y', strtotime($template['updated_at'])) ?></div>
                                <div class="stat-label">Last Updated</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Insert Snippets -->
                <div class="glass-card">
                    <h3 class="sidebar-title">Quick Insert</h3>
                    <div class="snippets-grid">
                        <button type="button" class="snippet-btn" onclick="insertSnippet('button')">
                            <i class="fa-solid fa-square"></i>
                            Button
                        </button>
                        <button type="button" class="snippet-btn" onclick="insertSnippet('divider')">
                            <i class="fa-solid fa-minus"></i>
                            Divider
                        </button>
                        <button type="button" class="snippet-btn" onclick="insertSnippet('image')">
                            <i class="fa-solid fa-image"></i>
                            Image
                        </button>
                        <button type="button" class="snippet-btn" onclick="insertSnippet('columns')">
                            <i class="fa-solid fa-columns"></i>
                            2 Columns
                        </button>
                        <button type="button" class="snippet-btn" onclick="insertSnippet('footer')">
                            <i class="fa-solid fa-shoe-prints"></i>
                            Footer
                        </button>
                        <button type="button" class="snippet-btn" onclick="insertSnippet('social')">
                            <i class="fa-solid fa-share-nodes"></i>
                            Social Links
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" role="dialog" aria-modal="true"-overlay" style="display: none;">
    <div class="modal" role="dialog" aria-modal="true"-content">
        <div class="modal" role="dialog" aria-modal="true"-icon danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Delete Template?</h3>
        <p>Are you sure you want to delete "<strong><?= htmlspecialchars($template['name'] ?? '') ?></strong>"? This action cannot be undone.</p>
        <div class="modal" role="dialog" aria-modal="true"-actions">
            <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <form action="<?= $basePath ?>/admin/newsletters/templates/delete/<?= $template['id'] ?>" method="POST" style="display: inline;">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="btn-danger">
                    <i class="fa-solid fa-trash"></i> Delete Template
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Template Form Container */
.template-form-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px 60px;
}

/* Flash Messages */
.flash-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #fca5a5;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    backdrop-filter: blur(10px);
}

.flash-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.2) 100%);
    border: 1px solid rgba(34, 197, 94, 0.4);
    color: #86efac;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    backdrop-filter: blur(10px);
}

/* Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.back-link {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color 0.2s ease;
}

.back-link:hover {
    color: #a5b4fc;
}

.preview-link {
    color: #a5b4fc;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.preview-link:hover {
    background: rgba(99, 102, 241, 0.25);
    border-color: rgba(99, 102, 241, 0.5);
}

/* Form Grid Layout */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
}

.main-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.sidebar-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Glass Card */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(10px);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.card-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a5b4fc;
    font-size: 1.1rem;
}

.card-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #f1f5f9;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-row .form-group {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-label .required {
    color: #f87171;
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    color: #f1f5f9;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.form-select {
    cursor: pointer;
}

.form-select option {
    background: #1e293b;
    color: #f1f5f9;
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

.form-hint {
    margin: 8px 0 0;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Variables Panel */
.variables-panel {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
}

.variables-title {
    margin: 0 0 12px 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #a5b4fc;
    display: flex;
    align-items: center;
    gap: 8px;
}

.variables-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.variable-tag {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #e2e8f0;
    padding: 6px 12px;
    border-radius: 6px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.variable-tag:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
    color: #a5b4fc;
}

/* Code Editor */
.code-editor-wrapper {
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    background: rgba(0, 0, 0, 0.3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.editor-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 8px;
}

.editor-actions {
    display: flex;
    gap: 8px;
}

.editor-btn {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.6);
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.editor-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.code-editor {
    width: 100%;
    padding: 20px;
    background: rgba(0, 0, 0, 0.4);
    border: none;
    color: #e2e8f0;
    font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
    resize: vertical;
    min-height: 400px;
    box-sizing: border-box;
}

.code-editor:focus {
    outline: none;
}

.code-editor::placeholder {
    color: rgba(255, 255, 255, 0.25);
}

.code-editor.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    min-height: 100vh;
    border-radius: 0;
}

/* Sidebar Styles */
.sidebar-title {
    margin: 0 0 16px 0;
    font-size: 1rem;
    font-weight: 600;
    color: #f1f5f9;
}

.actions-card {
    position: sticky;
    top: 100px;
}

.actions-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Buttons */
.btn-primary {
    width: 100%;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: white;
    padding: 14px 20px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #e2e8f0;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.95rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.25);
}

.btn-danger {
    width: 100%;
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

.btn-cancel {
    width: 100%;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.6);
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.95rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.8);
}

/* Tips Card */
.tips-card {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(22, 163, 74, 0.1) 100%);
    border-color: rgba(34, 197, 94, 0.2);
}

.tips-title {
    margin: 0 0 12px 0;
    font-size: 1rem;
    font-weight: 600;
    color: #86efac;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tips-list {
    margin: 0;
    padding-left: 20px;
    color: rgba(134, 239, 172, 0.8);
    font-size: 0.85rem;
    line-height: 1.8;
}

/* Stats Card */
.stats-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
}

.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.stat-icon.usage {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.stat-icon.date {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.2) 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-icon.updated {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fcd34d;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
}

.stat-value-sm {
    font-size: 0.95rem;
    font-weight: 600;
    color: #f1f5f9;
}

.stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Snippets Grid */
.snippets-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.snippet-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.snippet-btn:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 32px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 1.75rem;
}

.modal-icon.danger {
    background: rgba(239, 68, 68, 0.2);
    border: 2px solid rgba(239, 68, 68, 0.4);
    color: #f87171;
}

.modal-content h3 {
    margin: 0 0 12px 0;
    font-size: 1.25rem;
    color: #f1f5f9;
}

.modal-content p {
    margin: 0 0 24px 0;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.6;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-actions .btn-cancel,
.modal-actions .btn-danger {
    width: auto;
    padding: 12px 24px;
}

/* Responsive */
@media (max-width: 1024px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .sidebar-column {
        order: -1;
    }

    .actions-card {
        position: static;
    }

    .actions-stack {
        flex-direction: row;
        flex-wrap: wrap;
    }

    .actions-stack > * {
        flex: 1;
        min-width: 150px;
    }
}

@media (max-width: 600px) {
    .template-form-container {
        padding: 0 16px 40px;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .action-bar {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }

    .preview-link {
        justify-content: center;
    }

    .snippets-grid {
        grid-template-columns: 1fr;
    }

    .modal-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Insert variable at cursor position
function insertVariable(variable) {
    const textarea = document.getElementById('template-content');
    const varText = '{{' + variable + '}}';
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + varText + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + varText.length;
    textarea.focus();
}

// Insert code snippets
function insertSnippet(type) {
    const textarea = document.getElementById('template-content');
    let snippet = '';

    switch(type) {
        case 'button':
            snippet = `<table role="presentation" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td style="border-radius: 8px; background: #6366f1;">
      <a href="YOUR_LINK" style="display: inline-block; padding: 14px 28px; font-family: Arial, sans-serif; font-size: 16px; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold;">Button Text</a>
    </td>
  </tr>
</table>`;
            break;
        case 'divider':
            snippet = `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td style="padding: 20px 0;">
      <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0;">
    </td>
  </tr>
</table>`;
            break;
        case 'image':
            snippet = `<img src="YOUR_IMAGE_URL" alt="Image description" style="max-width: 100%; height: auto; display: block; border-radius: 8px;" loading="lazy">`;
            break;
        case 'columns':
            snippet = `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td width="48%" valign="top" style="padding-right: 2%;">
      <!-- Left column content -->
      <p style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #374151;">Left column</p>
    </td>
    <td width="48%" valign="top" style="padding-left: 2%;">
      <!-- Right column content -->
      <p style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #374151;">Right column</p>
    </td>
  </tr>
</table>`;
            break;
        case 'footer':
            snippet = `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top: 40px; border-top: 1px solid #e5e7eb;">
  <tr>
    <td style="padding: 30px 0; text-align: center;">
      <p style="margin: 0 0 10px; font-family: Arial, sans-serif; font-size: 14px; color: #6b7280;">
        {{tenant_name}}
      </p>
      <p style="margin: 0; font-family: Arial, sans-serif; font-size: 12px; color: #9ca3af;">
        <a href="{{unsubscribe_link}}" style="color: #6b7280;">Unsubscribe</a> |
        <a href="{{view_in_browser}}" style="color: #6b7280;">View in browser</a>
      </p>
    </td>
  </tr>
</table>`;
            break;
        case 'social':
            snippet = `<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
  <tr>
    <td style="padding: 0 8px;">
      <a href="FACEBOOK_URL"><img src="facebook-icon.png" alt="Facebook" width="32" height="32" loading="lazy"></a>
    </td>
    <td style="padding: 0 8px;">
      <a href="TWITTER_URL"><img src="twitter-icon.png" alt="Twitter" width="32" height="32" loading="lazy"></a>
    </td>
    <td style="padding: 0 8px;">
      <a href="INSTAGRAM_URL"><img src="instagram-icon.png" alt="Instagram" width="32" height="32" loading="lazy"></a>
    </td>
  </tr>
</table>`;
            break;
    }

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + snippet + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + snippet.length;
    textarea.focus();
}

// Toggle fullscreen editor
let isFullscreen = false;
function toggleFullscreen() {
    const textarea = document.getElementById('template-content');
    const wrapper = document.querySelector('.code-editor-wrapper');

    if (isFullscreen) {
        wrapper.style.position = '';
        wrapper.style.top = '';
        wrapper.style.left = '';
        wrapper.style.right = '';
        wrapper.style.bottom = '';
        wrapper.style.zIndex = '';
        wrapper.style.borderRadius = '';
        textarea.style.minHeight = '400px';
        document.body.style.overflow = '';
    } else {
        wrapper.style.position = 'fixed';
        wrapper.style.top = '0';
        wrapper.style.left = '0';
        wrapper.style.right = '0';
        wrapper.style.bottom = '0';
        wrapper.style.zIndex = '9999';
        wrapper.style.borderRadius = '0';
        textarea.style.minHeight = 'calc(100vh - 50px)';
        document.body.style.overflow = 'hidden';
    }

    isFullscreen = !isFullscreen;
}

// Format code (basic indentation)
function formatCode() {
    const textarea = document.getElementById('template-content');
    let code = textarea.value;

    // Basic HTML formatting
    code = code.replace(/></g, '>\n<');
    code = code.replace(/\n\s*\n/g, '\n');

    textarea.value = code;
}

// Delete confirmation modal
function confirmDelete() {
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        if (isFullscreen) toggleFullscreen();
    }
});

// Close modal on backdrop click
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
