<?php
$pageTitle = $pageTitle ?? 'Restore Blog Posts';
require __DIR__ . '/../partials/admin-header.php';
?>

<style>
.restore-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px;
}

.restore-header {
    background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
    border-radius: 16px;
    padding: 40px;
    color: white;
    margin-bottom: 32px;
    text-align: center;
}

.restore-header h1 {
    color: white;
    margin: 0 0 16px 0;
    font-size: 32px;
}

.restore-header p {
    margin: 0;
    font-size: 16px;
    opacity: 0.95;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #7c3aed;
    margin: 0;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    margin: 8px 0 0 0;
}

.action-section {
    background: white;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 32px;
}

.action-section h2 {
    margin: 0 0 24px 0;
    font-size: 24px;
    color: #1f2937;
}

.btn {
    display: inline-block;
    padding: 14px 28px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 15px;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.file-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.file-item {
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.file-info h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #1f2937;
}

.file-meta {
    font-size: 14px;
    color: #6b7280;
}

.file-actions {
    display: flex;
    gap: 12px;
}

.diagnostic-section {
    background: #fef3c7;
    border: 2px solid #fbbf24;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 32px;
}

.diagnostic-section h3 {
    margin: 0 0 16px 0;
    color: #92400e;
    font-size: 18px;
}

.upload-area {
    border: 3px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #f9fafb;
    margin: 24px 0;
}

.upload-area.drag-over {
    border-color: #7c3aed;
    background: #f5f3ff;
}

.upload-icon {
    font-size: 48px;
    color: #9ca3af;
    margin-bottom: 16px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.modal-header h2 {
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin: 16px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #7c3aed, #5b21b6);
    transition: width 0.3s;
}

.loading {
    text-align: center;
    padding: 40px;
}

.spinner {
    border: 4px solid #f3f4f6;
    border-top: 4px solid #7c3aed;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="restore-container">
    <div class="restore-header">
        <h1>🔄 Blog Post Restore Tool</h1>
        <p>Import blog posts from SQL export files - No command line required!</p>
    </div>

    <!-- Current Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['tenant_posts'] ?></div>
            <div class="stat-label">Posts in Current Tenant (<?= $tenantId ?>)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['published'] ?></div>
            <div class="stat-label">Published Posts</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['drafts'] ?></div>
            <div class="stat-label">Draft Posts</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_posts'] ?></div>
            <div class="stat-label">Total Posts (All Tenants)</div>
        </div>
    </div>

    <!-- Diagnostic Section -->
    <div class="diagnostic-section">
        <h3>⚠️ Missing Your Blog Posts?</h3>
        <p style="margin-bottom: 16px;">Run a diagnostic to check your database and identify any issues.</p>
        <button class="btn btn-secondary" onclick="runDiagnostic()">
            🔍 Run Diagnostic
        </button>
    </div>

    <!-- Available Export Files -->
    <?php if (!empty($exportFiles)): ?>
    <div class="action-section">
        <h2>📦 Available Export Files</h2>
        <p style="color: #6b7280; margin-bottom: 24px;">
            These SQL export files are available in your /exports/ directory. Click "Import" to restore blogs from any file.
        </p>

        <ul class="file-list">
            <?php foreach ($exportFiles as $file): ?>
            <li class="file-item">
                <div class="file-info">
                    <h3>📄 <?= htmlspecialchars($file['name']) ?></h3>
                    <div class="file-meta">
                        Size: <?= $file['size'] ?> |
                        Created: <?= $file['age'] ?> (<?= $file['date'] ?>)
                    </div>
                </div>
                <div class="file-actions">
                    <button class="btn btn-primary" onclick="importFile('<?= htmlspecialchars($file['name']) ?>')">
                        ⬆️ Import
                    </button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Upload New Export File -->
    <div class="action-section">
        <h2>📤 Upload SQL Export File</h2>
        <p style="color: #6b7280; margin-bottom: 24px;">
            Don't have an export file here? Upload one from your local machine.
        </p>

        <div class="upload-area" id="uploadArea">
            <div class="upload-icon">📁</div>
            <h3 style="margin: 0 0 8px 0;">Drag & Drop SQL File Here</h3>
            <p style="color: #6b7280; margin: 0 0 16px 0;">or click to browse</p>
            <input type="file" id="fileInput" accept=".sql" style="display: none;">
            <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                Choose File
            </button>
        </div>

        <div id="uploadStatus"></div>
    </div>

    <!-- Export from Current Database -->
    <div class="action-section">
        <h2>💾 Export Blogs from This Server</h2>
        <p style="color: #6b7280; margin-bottom: 24px;">
            Create a new export file from the current database to use on another server.
        </p>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/admin-legacy/blog-restore/export?tenant=<?= $tenantId ?>&status=all" class="btn btn-primary">
                📥 Export All Posts
            </a>
            <a href="/admin-legacy/blog-restore/export?tenant=<?= $tenantId ?>&status=published" class="btn btn-secondary">
                📥 Export Published Only
            </a>
        </div>
    </div>
</div>

<!-- Diagnostic Modal -->
<div id="diagnosticModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🔍 Database Diagnostic</h2>
            <button class="close-btn" onclick="closeModal('diagnosticModal')">&times;</button>
        </div>
        <div id="diagnosticContent">
            <div class="loading">
                <div class="spinner"></div>
                <p>Running diagnostic...</p>
            </div>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div id="importModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⬆️ Import Blog Posts</h2>
            <button class="close-btn" onclick="closeModal('importModal')">&times;</button>
        </div>
        <div id="importContent"></div>
    </div>
</div>

<script>
let currentImportFile = null;

function runDiagnostic() {
    openModal('diagnosticModal');

    fetch('/admin-legacy/blog-restore/diagnostic')
        .then(r => r.json())
        .then(data => {
            let html = '';

            if (!data.success) {
                html = `<div class="alert alert-error">Error: ${data.error}</div>`;
            } else {
                // Table status
                html += `<div class="alert ${data.table_exists ? 'alert-success' : 'alert-error'}">`;
                html += data.table_exists ? '✓ Posts table exists' : '✗ Posts table does NOT exist!';
                html += '</div>';

                if (data.table_exists) {
                    // Post counts
                    html += `<h3 style="margin: 24px 0 12px 0;">Post Statistics</h3>`;
                    html += `<p><strong>Total posts in database:</strong> ${data.total_posts}</p>`;
                    html += `<p><strong>Posts in your tenant (<?= $tenantId ?>):</strong> ${data.tenant_posts}</p>`;

                    // By tenant
                    if (data.posts_by_tenant.length > 0) {
                        html += `<h3 style="margin: 24px 0 12px 0;">Posts by Tenant</h3>`;
                        html += '<ul>';
                        data.posts_by_tenant.forEach(t => {
                            html += `<li>Tenant ${t.tenant_id}: ${t.count} posts</li>`;
                        });
                        html += '</ul>';
                    }

                    // Recent posts
                    if (data.recent_posts.length > 0) {
                        html += `<h3 style="margin: 24px 0 12px 0;">Recent Posts</h3>`;
                        html += '<ul>';
                        data.recent_posts.forEach(p => {
                            html += `<li>[${p.id}] ${p.title} (${p.status}) - ${p.created_at}</li>`;
                        });
                        html += '</ul>';
                    } else if (data.tenant_posts === 0) {
                        html += `<div class="alert alert-warning" style="margin-top: 24px;">`;
                        html += `<strong>⚠️ No posts found in your tenant!</strong><br>`;
                        html += `Your blogs may be in a different tenant, or they were never imported to this server.`;
                        html += `</div>`;
                    }
                }
            }

            document.getElementById('diagnosticContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('diagnosticContent').innerHTML =
                `<div class="alert alert-error">Error running diagnostic: ${err.message}</div>`;
        });
}

function importFile(filename) {
    currentImportFile = filename;
    openModal('importModal');

    const html = `
        <div class="alert alert-warning">
            <strong>⚠️ Import Confirmation</strong><br>
            You are about to import blog posts from:<br>
            <strong>${filename}</strong>
        </div>

        <p><strong>This will:</strong></p>
        <ul>
            <li>Create an automatic backup of your current posts</li>
            <li>Import blog posts from the SQL file</li>
            <li>Skip any duplicate posts</li>
        </ul>

        <p><strong>Current posts in your tenant:</strong> <?= $stats['tenant_posts'] ?></p>

        <div style="margin-top: 24px; display: flex; gap: 12px;">
            <button class="btn btn-primary" onclick="executeImport()">
                ✓ Yes, Import Posts
            </button>
            <button class="btn btn-secondary" onclick="closeModal('importModal')">
                Cancel
            </button>
        </div>
    `;

    document.getElementById('importContent').innerHTML = html;
}

function executeImport() {
    const content = document.getElementById('importContent');
    content.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Importing blog posts...</p>
            <p style="color: #6b7280; font-size: 14px;">This may take a moment. Please wait.</p>
        </div>
    `;

    const formData = new FormData();
    formData.append('filename', currentImportFile);
    formData.append('csrf_token', '<?= \Nexus\Core\Csrf::generate() ?>');

    fetch('/admin-legacy/blog-restore/import', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        let html = '';

        if (data.success) {
            html += `<div class="alert alert-success">
                <strong>✓ Import Successful!</strong>
            </div>`;

            html += `<h3 style="margin: 24px 0 12px 0;">Import Results</h3>`;
            html += `<p><strong>Posts before:</strong> ${data.before_count}</p>`;
            html += `<p><strong>Posts after:</strong> ${data.after_count}</p>`;
            html += `<p><strong>Posts added:</strong> ${data.added_count}</p>`;

            if (data.skipped > 0) {
                html += `<p><strong>Skipped (duplicates):</strong> ${data.skipped}</p>`;
            }

            if (data.backup_file) {
                html += `<p style="color: #6b7280; font-size: 14px; margin-top: 16px;">`;
                html += `Backup created: ${data.backup_file}`;
                html += `</p>`;
            }

            html += `<div style="margin-top: 24px;">
                <button class="btn btn-primary" onclick="location.reload()">
                    🔄 Refresh Page
                </button>
                <a href="/admin-legacy/news" class="btn btn-secondary">
                    📝 View Blog Posts
                </a>
            </div>`;
        } else {
            html += `<div class="alert alert-error">
                <strong>✗ Import Failed</strong><br>
                ${data.error}
            </div>`;

            html += `<button class="btn btn-secondary" onclick="closeModal('importModal')" style="margin-top: 16px;">
                Close
            </button>`;
        }

        content.innerHTML = html;
    })
    .catch(err => {
        content.innerHTML = `
            <div class="alert alert-error">
                <strong>✗ Import Error</strong><br>
                ${err.message}
            </div>
            <button class="btn btn-secondary" onclick="closeModal('importModal')" style="margin-top: 16px;">
                Close
            </button>
        `;
    });
}

// File upload handling
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('drag-over');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('drag-over');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileUpload(files[0]);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFileUpload(e.target.files[0]);
    }
});

function handleFileUpload(file) {
    const statusDiv = document.getElementById('uploadStatus');

    if (!file.name.endsWith('.sql')) {
        statusDiv.innerHTML = `<div class="alert alert-error">Please upload a .sql file</div>`;
        return;
    }

    statusDiv.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Uploading ${file.name}...</p>
        </div>
    `;

    const formData = new FormData();
    formData.append('sql_file', file);
    formData.append('csrf_token', '<?= \Nexus\Core\Csrf::generate() ?>');

    fetch('/admin-legacy/blog-restore/upload', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✓ File uploaded successfully!</strong><br>
                    ${data.filename} (${data.size})
                </div>
                <button class="btn btn-primary" onclick="importFile('${data.filename}')" style="margin-top: 16px;">
                    ⬆️ Import This File Now
                </button>
            `;
        } else {
            statusDiv.innerHTML = `<div class="alert alert-error">Upload failed: ${data.error}</div>`;
        }
    })
    .catch(err => {
        statusDiv.innerHTML = `<div class="alert alert-error">Upload error: ${err.message}</div>`;
    });
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modals when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
