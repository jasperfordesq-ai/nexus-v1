<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Seed Generator';
$adminPageSubtitle = 'Database Tools';
$adminPageIcon = 'fa-database';

require dirname(__DIR__) . '/partials/admin-header.php';

$stats = $stats ?? [];
$tables = $tables ?? [];
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-database"></i> Database Seed Generator
        </h1>
        <p class="admin-page-subtitle">Generate seed data for new tenant deployments</p>
    </div>
</div>

<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-body">
        <h3 style="margin-bottom: 1rem; color: var(--admin-text);">Database Statistics</h3>
        <?php if (!empty($stats)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
            <?php foreach ($stats as $key => $value): ?>
            <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; padding: 1rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--admin-text-muted); margin-bottom: 0.25rem;"><?= htmlspecialchars($key) ?></div>
                <div style="font-size: 1.25rem; font-weight: 700; color: var(--admin-text);"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: var(--admin-text-muted);">No statistics available.</p>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="admin-card">
        <div class="admin-card-body" style="text-align: center; padding: 2rem;">
            <i class="fa-solid fa-rocket" style="font-size: 2rem; color: #10b981; margin-bottom: 1rem;"></i>
            <h3 style="margin-bottom: 0.5rem; color: var(--admin-text);">Production Seed</h3>
            <p style="color: var(--admin-text-muted); margin-bottom: 1rem;">Generate minimal seed data for a new production tenant</p>
            <form method="POST" action="<?= $basePath ?>/admin-legacy/seed-generator/generate-production">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                <button type="submit" class="admin-btn admin-btn-primary">Generate Production Seed</button>
            </form>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-body" style="text-align: center; padding: 2rem;">
            <i class="fa-solid fa-flask" style="font-size: 2rem; color: #f59e0b; margin-bottom: 1rem;"></i>
            <h3 style="margin-bottom: 0.5rem; color: var(--admin-text);">Demo Seed</h3>
            <p style="color: var(--admin-text-muted); margin-bottom: 1rem;">Generate demo data with sample users, listings, and transactions</p>
            <form method="POST" action="<?= $basePath ?>/admin-legacy/seed-generator/generate-demo">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                <button type="submit" class="admin-btn admin-btn-secondary">Generate Demo Seed</button>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($tables)): ?>
<div class="admin-card">
    <div class="admin-card-body">
        <h3 style="margin-bottom: 1rem; color: var(--admin-text);">Table Information</h3>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Rows</th>
                        <th>Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                    <tr>
                        <td><?= htmlspecialchars($table['name'] ?? $table['TABLE_NAME'] ?? '') ?></td>
                        <td><?= number_format($table['rows'] ?? $table['TABLE_ROWS'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($table['size'] ?? $table['DATA_LENGTH'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
