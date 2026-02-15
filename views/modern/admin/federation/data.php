<?php
/**
 * Federation Data Management
 * Import/Export interface for federation data
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Data';
$adminPageSubtitle = 'Import & Export';
$adminPageIcon = 'fa-database';

require __DIR__ . '/../partials/admin-header.php';

$stats = $stats ?? [];
$recentExports = $recentExports ?? [];
$importResults = $_SESSION['import_results'] ?? null;
unset($_SESSION['import_results']);
?>

<style>
/* Data Management Styles */
.data-dashboard {
    display: grid;
    gap: 1.5rem;
}

/* Import Results Alert */
.import-results {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 16px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.import-results h3 {
    color: var(--admin-text, #fff);
    font-size: 1.1rem;
    margin: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.result-stat {
    text-align: center;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
}

.result-stat .value {
    font-size: 1.5rem;
    font-weight: 700;
}

.result-stat .label {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.result-stat.success .value { color: #10b981; }
.result-stat.warning .value { color: #f59e0b; }
.result-stat.error .value { color: #ef4444; }

.import-errors {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 10px;
    padding: 1rem;
    max-height: 200px;
    overflow-y: auto;
}

.import-errors h4 {
    color: #ef4444;
    font-size: 0.9rem;
    margin: 0 0 0.5rem;
}

.import-errors ul {
    margin: 0;
    padding-left: 1.25rem;
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

/* Section Cards */
.section-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 900px) {
    .section-row {
        grid-template-columns: 1fr;
    }
}

.section-card {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 16px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    padding: 1.5rem;
}

.section-card h2 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--admin-text, #fff);
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-card h2 i {
    color: #8b5cf6;
}

.section-card .description {
    font-size: 0.9rem;
    color: var(--admin-text-secondary, #94a3b8);
    margin-bottom: 1.5rem;
}

/* Export Options */
.export-options {
    display: grid;
    gap: 0.75rem;
}

.export-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    transition: all 0.2s;
}

.export-option:hover {
    background: rgba(139, 92, 246, 0.1);
}

.export-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.export-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.export-details strong {
    display: block;
    color: var(--admin-text, #fff);
    margin-bottom: 2px;
}

.export-details span {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.export-btn {
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.export-btn.secondary {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

/* Import Form */
.import-form {
    display: grid;
    gap: 1.25rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin-bottom: 0.5rem;
}

.form-group .hint {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #94a3b8);
    margin-top: 0.35rem;
}

.file-input-wrapper {
    position: relative;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-input-display {
    padding: 2rem;
    background: rgba(0, 0, 0, 0.2);
    border: 2px dashed var(--admin-border, rgba(255, 255, 255, 0.2));
    border-radius: 12px;
    text-align: center;
    transition: all 0.2s;
}

.file-input-display:hover {
    border-color: #8b5cf6;
    background: rgba(139, 92, 246, 0.05);
}

.file-input-display i {
    font-size: 2rem;
    color: #8b5cf6;
    margin-bottom: 0.5rem;
}

.file-input-display p {
    margin: 0;
    color: var(--admin-text-secondary, #94a3b8);
}

.file-input-display .filename {
    color: #10b981;
    font-weight: 600;
    margin-top: 0.5rem;
}

.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 10px;
    color: var(--admin-text, #fff);
    font-size: 1rem;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.checkbox-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

.checkbox-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #8b5cf6;
    margin-top: 2px;
}

.checkbox-item .checkbox-label strong {
    display: block;
    color: var(--admin-text, #fff);
}

.checkbox-item .checkbox-label span {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.import-actions {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.2));
    color: var(--admin-text-secondary, #94a3b8);
}

/* Recent Exports */
.recent-exports {
    margin-top: 1.5rem;
}

.recent-exports h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin: 0 0 1rem;
}

.exports-list {
    display: grid;
    gap: 0.5rem;
}

.export-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.15);
    border-radius: 8px;
}

.export-item-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.export-type-badge {
    padding: 0.25rem 0.5rem;
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.export-meta {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.export-count {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #64748b);
}

/* Full Backup Button */
.full-backup-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.full-backup-btn {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 12px;
    color: #a78bfa;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.full-backup-btn:hover {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(99, 102, 241, 0.2));
    transform: translateY(-2px);
}
</style>

<div class="data-dashboard">
    <?php if ($importResults): ?>
    <div class="import-results">
        <h3><i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Import Complete</h3>
        <div class="results-grid">
            <div class="result-stat">
                <div class="value"><?= $importResults['processed'] ?></div>
                <div class="label">Processed</div>
            </div>
            <div class="result-stat success">
                <div class="value"><?= $importResults['enrolled'] ?></div>
                <div class="label">Enrolled</div>
            </div>
            <div class="result-stat warning">
                <div class="value"><?= $importResults['skipped'] ?></div>
                <div class="label">Skipped</div>
            </div>
            <div class="result-stat error">
                <div class="value"><?= $importResults['not_found'] ?></div>
                <div class="label">Not Found</div>
            </div>
        </div>
        <?php if (!empty($importResults['errors'])): ?>
        <div class="import-errors">
            <h4><i class="fa-solid fa-triangle-exclamation"></i> Errors (<?= count($importResults['errors']) ?>)</h4>
            <ul>
                <?php foreach (array_slice($importResults['errors'], 0, 10) as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
                <?php if (count($importResults['errors']) > 10): ?>
                <li>... and <?= count($importResults['errors']) - 10 ?> more</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="section-row">
        <!-- Export Section -->
        <div class="section-card">
            <h2><i class="fa-solid fa-download"></i> Export Data</h2>
            <p class="description">Download federation data as CSV files for reporting or backup.</p>

            <div class="export-options">
                <div class="export-option">
                    <div class="export-info">
                        <div class="export-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="export-details">
                            <strong>Federated Users</strong>
                            <span><?= number_format($stats['users'] ?? 0) ?> users opted in</span>
                        </div>
                    </div>
                    <a href="/admin-legacy/federation/export/users" class="export-btn">
                        <i class="fa-solid fa-download"></i> Export
                    </a>
                </div>

                <div class="export-option">
                    <div class="export-info">
                        <div class="export-icon"><i class="fa-solid fa-handshake"></i></div>
                        <div class="export-details">
                            <strong>Partnerships</strong>
                            <span><?= number_format($stats['partnerships'] ?? 0) ?> active partnerships</span>
                        </div>
                    </div>
                    <a href="/admin-legacy/federation/export/partnerships" class="export-btn">
                        <i class="fa-solid fa-download"></i> Export
                    </a>
                </div>

                <div class="export-option">
                    <div class="export-info">
                        <div class="export-icon"><i class="fa-solid fa-coins"></i></div>
                        <div class="export-details">
                            <strong>Transactions</strong>
                            <span><?= number_format($stats['transactions'] ?? 0) ?> federated transactions</span>
                        </div>
                    </div>
                    <a href="/admin-legacy/federation/export/transactions" class="export-btn">
                        <i class="fa-solid fa-download"></i> Export
                    </a>
                </div>

                <div class="export-option">
                    <div class="export-info">
                        <div class="export-icon"><i class="fa-solid fa-list-check"></i></div>
                        <div class="export-details">
                            <strong>Audit Log</strong>
                            <span><?= number_format($stats['audit_logs'] ?? 0) ?> log entries</span>
                        </div>
                    </div>
                    <a href="/admin-legacy/federation/export/audit" class="export-btn">
                        <i class="fa-solid fa-download"></i> Export
                    </a>
                </div>
            </div>

            <div class="full-backup-section">
                <a href="/admin-legacy/federation/export/all" class="full-backup-btn">
                    <i class="fa-solid fa-file-zipper"></i>
                    Download Full Backup (ZIP)
                </a>
            </div>

            <?php if (!empty($recentExports)): ?>
            <div class="recent-exports">
                <h3>Recent Exports</h3>
                <div class="exports-list">
                    <?php foreach (array_slice($recentExports, 0, 5) as $export): ?>
                    <div class="export-item">
                        <div class="export-item-info">
                            <span class="export-type-badge"><?= htmlspecialchars($export['export_type']) ?></span>
                            <span class="export-meta"><?= date('M j, g:ia', strtotime($export['created_at'])) ?></span>
                        </div>
                        <span class="export-count"><?= number_format($export['record_count']) ?> records</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Import Section -->
        <div class="section-card">
            <h2><i class="fa-solid fa-upload"></i> Import Users</h2>
            <p class="description">Bulk enroll users in federation from a CSV file.</p>

            <form method="POST" action="/admin-legacy/federation/import/users" enctype="multipart/form-data" class="import-form">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

                <div class="form-group">
                    <label>CSV File</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="csv_file" id="csvFile" accept=".csv" required>
                        <div class="file-input-display" id="fileDisplay">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p>Click or drag CSV file here</p>
                            <p class="filename" id="fileName" style="display: none;"></p>
                        </div>
                    </div>
                    <p class="hint">
                        CSV must contain "email" or "username" column.
                        <a href="/admin-legacy/federation/import/template" style="color: #8b5cf6;">Download template</a>
                    </p>
                </div>

                <div class="form-group">
                    <label for="defaultPrivacy">Default Privacy Level</label>
                    <select name="default_privacy_level" id="defaultPrivacy" class="form-select">
                        <option value="discovery">Discovery - Basic visibility only</option>
                        <option value="social" selected>Social - Profile + messaging (Recommended)</option>
                        <option value="economic">Economic - Full access including transactions</option>
                    </select>
                    <p class="hint">Applied when CSV doesn't specify privacy_level</p>
                </div>

                <div class="form-group">
                    <label>Options</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="skip_existing" checked>
                            <div class="checkbox-label">
                                <strong>Skip already enrolled users</strong>
                                <span>Don't update settings for users already in federation</span>
                            </div>
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="send_notification">
                            <div class="checkbox-label">
                                <strong>Send notification to users</strong>
                                <span>Notify users they've been enrolled in federation</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="import-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-upload"></i> Import Users
                    </button>
                    <a href="/admin-legacy/federation/import/template" class="btn btn-secondary">
                        <i class="fa-solid fa-file-csv"></i> Download Template
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// File input display
const fileInput = document.getElementById('csvFile');
const fileDisplay = document.getElementById('fileDisplay');
const fileName = document.getElementById('fileName');

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        fileName.textContent = this.files[0].name;
        fileName.style.display = 'block';
        fileDisplay.querySelector('p:not(.filename)').textContent = 'File selected:';
    } else {
        fileName.style.display = 'none';
        fileDisplay.querySelector('p:not(.filename)').textContent = 'Click or drag CSV file here';
    }
});

// Drag and drop
fileDisplay.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = '#8b5cf6';
    this.style.background = 'rgba(139, 92, 246, 0.1)';
});

fileDisplay.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '';
    this.style.background = '';
});

fileDisplay.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '';
    this.style.background = '';

    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
