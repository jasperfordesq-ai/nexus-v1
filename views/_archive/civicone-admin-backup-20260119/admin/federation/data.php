<?php
/**
 * Federation Data Management
 * Export/import federation data
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Data';
$adminPageSubtitle = 'Data Management';
$adminPageIcon = 'fa-database';

require __DIR__ . '/../partials/admin-header.php';

$dataStats = $dataStats ?? [];
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-database"></i>
            Federation Data
        </h1>
        <p class="admin-page-subtitle">Manage your federation data</p>
    </div>
</div>

<div class="fed-grid-2">
    <!-- Data Overview -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-header">
            <h3 class="fed-admin-card-title">
                <i class="fa-solid fa-chart-pie"></i>
                Data Overview
            </h3>
        </div>
        <div class="fed-admin-card-body">
            <div class="analytics-metric-grid">
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= number_format($dataStats['messages'] ?? 0) ?></div>
                    <div class="analytics-metric-label">Messages</div>
                </div>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= number_format($dataStats['transactions'] ?? 0) ?></div>
                    <div class="analytics-metric-label">Transactions</div>
                </div>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= number_format($dataStats['partnerships'] ?? 0) ?></div>
                    <div class="analytics-metric-label">Partnerships</div>
                </div>
                <div class="analytics-metric">
                    <div class="analytics-metric-value"><?= number_format($dataStats['audit_logs'] ?? 0) ?></div>
                    <div class="analytics-metric-label">Audit Logs</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Data -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-header">
            <h3 class="fed-admin-card-title">
                <i class="fa-solid fa-download"></i>
                Export Data
            </h3>
        </div>
        <div class="fed-admin-card-body">
            <p class="admin-text-muted" style="margin-bottom: 1rem;">
                Export your federation data for backup or analysis purposes.
            </p>

            <form action="<?= $basePath ?>/admin-legacy/federation/data/export" method="POST">
                <?= Csrf::input() ?>

                <div class="admin-form-group">
                    <label class="admin-label">Data to Export</label>
                    <div class="admin-checkbox-list">
                        <label><input type="checkbox" name="export[]" value="messages" checked> Messages</label>
                        <label><input type="checkbox" name="export[]" value="transactions" checked> Transactions</label>
                        <label><input type="checkbox" name="export[]" value="partnerships" checked> Partnerships</label>
                        <label><input type="checkbox" name="export[]" value="audit_logs"> Audit Logs</label>
                    </div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Format</label>
                    <select name="format" class="admin-input">
                        <option value="json">JSON</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>

                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-download"></i>
                    Export Data
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Data Retention -->
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Data Retention
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <p class="admin-text-muted" style="margin-bottom: 1rem;">
            Configure how long federation data is retained before automatic cleanup.
        </p>

        <form action="<?= $basePath ?>/admin-legacy/federation/data/retention" method="POST">
            <?= Csrf::input() ?>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Audit Logs</label>
                    <select name="retention_audit" class="admin-input">
                        <option value="30" <?= ($dataStats['retention_audit'] ?? 90) == 30 ? 'selected' : '' ?>>30 days</option>
                        <option value="90" <?= ($dataStats['retention_audit'] ?? 90) == 90 ? 'selected' : '' ?>>90 days</option>
                        <option value="180" <?= ($dataStats['retention_audit'] ?? 90) == 180 ? 'selected' : '' ?>>180 days</option>
                        <option value="365" <?= ($dataStats['retention_audit'] ?? 90) == 365 ? 'selected' : '' ?>>1 year</option>
                        <option value="0" <?= ($dataStats['retention_audit'] ?? 90) == 0 ? 'selected' : '' ?>>Forever</option>
                    </select>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Message History</label>
                    <select name="retention_messages" class="admin-input">
                        <option value="90" <?= ($dataStats['retention_messages'] ?? 365) == 90 ? 'selected' : '' ?>>90 days</option>
                        <option value="180" <?= ($dataStats['retention_messages'] ?? 365) == 180 ? 'selected' : '' ?>>180 days</option>
                        <option value="365" <?= ($dataStats['retention_messages'] ?? 365) == 365 ? 'selected' : '' ?>>1 year</option>
                        <option value="730" <?= ($dataStats['retention_messages'] ?? 365) == 730 ? 'selected' : '' ?>>2 years</option>
                        <option value="0" <?= ($dataStats['retention_messages'] ?? 365) == 0 ? 'selected' : '' ?>>Forever</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-save"></i>
                Save Settings
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
