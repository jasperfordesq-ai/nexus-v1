<?php
/**
 * Test Run Details View - FDS Gold Standard
 *
 * Shows detailed information about a specific test run
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Test Run Details';
$adminPageSubtitle = 'Detailed test execution results';
$adminPageIcon = 'fa-file-alt';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require __DIR__ . '/../../modern/admin/partials/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-file-alt"></i>
            Test Run Details
        </h1>
        <p class="admin-page-subtitle">
            <?= date('F j, Y g:i A', strtotime($run['created_at'])) ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/tests" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Test Runner
        </a>
        <button class="admin-btn admin-btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            Print Report
        </button>
    </div>
</div>

<!-- Test Run Summary Stats -->
<div class="admin-stats-grid">
    <!-- Suite -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= htmlspecialchars(ucfirst($run['suite'])) ?></div>
            <div class="admin-stat-label">Test Suite</div>
        </div>
    </div>

    <!-- Tests -->
    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $run['tests'] ?></div>
            <div class="admin-stat-label">Tests Run</div>
        </div>
    </div>

    <!-- Assertions -->
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $run['assertions'] ?></div>
            <div class="admin-stat-label">Assertions</div>
        </div>
    </div>

    <!-- Duration -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-stopwatch"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $run['duration'] ?>s</div>
            <div class="admin-stat-label">Duration</div>
        </div>
    </div>
</div>

<div class="admin-grid-2">
    <!-- Test Summary Card -->
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="admin-card-icon admin-card-icon-<?= $run['success'] ? 'green' : 'red' ?>">
                <i class="fa-solid fa-<?= $run['success'] ? 'check-circle' : 'times-circle' ?>"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Test Summary</h3>
                <p class="admin-card-subtitle">Overall test execution results</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="admin-info-grid">
                <div class="admin-info-item">
                    <span class="admin-info-label">Status</span>
                    <?php if ($run['success']): ?>
                        <span class="admin-badge admin-badge-success admin-badge-lg">
                            <i class="fa-solid fa-check"></i> Passed
                        </span>
                    <?php else: ?>
                        <span class="admin-badge admin-badge-danger admin-badge-lg">
                            <i class="fa-solid fa-times"></i> Failed
                        </span>
                    <?php endif; ?>
                </div>

                <div class="admin-info-item">
                    <span class="admin-info-label">Errors</span>
                    <span class="admin-info-value <?= $run['errors'] > 0 ? 'admin-text-danger' : 'admin-text-success' ?>">
                        <?= $run['errors'] ?>
                    </span>
                </div>

                <div class="admin-info-item">
                    <span class="admin-info-label">Failures</span>
                    <span class="admin-info-value <?= $run['failures'] > 0 ? 'admin-text-warning' : 'admin-text-success' ?>">
                        <?= $run['failures'] ?>
                    </span>
                </div>

                <div class="admin-info-item">
                    <span class="admin-info-label">Skipped</span>
                    <span class="admin-info-value <?= ($run['skipped'] ?? 0) > 0 ? 'admin-text-muted' : 'admin-text-success' ?>">
                        <?= $run['skipped'] ?? 0 ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Breakdown Chart -->
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="admin-card-icon admin-card-icon-indigo">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Test Breakdown</h3>
                <p class="admin-card-subtitle">Visual distribution of results</p>
            </div>
        </div>
        <div class="admin-card-body">
            <canvas id="testChart" style="max-height: 250px;"></canvas>
        </div>
    </div>
</div>

<!-- Execution Details -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-icon admin-card-icon-gray">
            <i class="fa-solid fa-info-circle"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Execution Details</h3>
            <p class="admin-card-subtitle">Test run metadata and information</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="admin-info-grid admin-info-grid-3">
            <div class="admin-info-item">
                <span class="admin-info-label">Run ID</span>
                <span class="admin-info-value">#<?= $run['id'] ?></span>
            </div>

            <div class="admin-info-item">
                <span class="admin-info-label">Suite</span>
                <span class="admin-badge admin-badge-secondary">
                    <?= htmlspecialchars(ucfirst($run['suite'])) ?>
                </span>
            </div>

            <div class="admin-info-item">
                <span class="admin-info-label">Executed By</span>
                <?php if ($run['username']): ?>
                    <div class="admin-user-cell">
                        <div class="admin-user-avatar">
                            <?= strtoupper(substr($run['first_name'], 0, 1) . substr($run['last_name'], 0, 1)) ?>
                        </div>
                        <span><?= htmlspecialchars($run['first_name'] . ' ' . $run['last_name']) ?></span>
                    </div>
                <?php else: ?>
                    <span class="admin-text-muted">System</span>
                <?php endif; ?>
            </div>

            <div class="admin-info-item">
                <span class="admin-info-label">Execution Time</span>
                <span class="admin-info-value">
                    <i class="fa-solid fa-clock"></i>
                    <?= date('M j, Y g:i A', strtotime($run['created_at'])) ?>
                </span>
            </div>

            <div class="admin-info-item">
                <span class="admin-info-label">Duration</span>
                <span class="admin-info-value">
                    <i class="fa-solid fa-stopwatch"></i>
                    <?= $run['duration'] ?>s
                </span>
            </div>

            <div class="admin-info-item">
                <span class="admin-info-label">Tenant</span>
                <span class="admin-info-value">
                    <i class="fa-solid fa-building"></i>
                    #<?= $run['tenant_id'] ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Test Output -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-icon admin-card-icon-gray">
            <i class="fa-solid fa-terminal"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Test Output</h3>
            <p class="admin-card-subtitle">Complete test execution log</p>
        </div>
        <button class="admin-btn admin-btn-secondary admin-btn-sm" onclick="copyToClipboard()">
            <i class="fa-solid fa-copy"></i>
            Copy
        </button>
    </div>
    <div class="admin-card-body">
        <pre id="test-output" class="admin-code-block"><?= htmlspecialchars($run['output']) ?></pre>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
/* Grid System */
.admin-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.admin-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.admin-info-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(2, 1fr);
}

.admin-info-grid-3 {
    grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 1200px) {
    .admin-grid-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-grid-2,
    .admin-grid-3,
    .admin-info-grid,
    .admin-info-grid-3 {
        grid-template-columns: 1fr;
    }
}

.admin-info-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.admin-info-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
}

.admin-info-value {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text);
}

.admin-code-block {
    background: #0a0e1a;
    color: #e2e8f0;
    padding: 1.5rem;
    border-radius: 0.5rem;
    font-family: 'Courier New', Consolas, monospace;
    font-size: 0.875rem;
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid rgba(99, 102, 241, 0.2);
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.admin-user-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-user-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}

.admin-badge-lg {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}
</style>

<script>
$(document).ready(function() {
    // Create test breakdown chart
    const ctx = document.getElementById('testChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed', 'Skipped'],
            datasets: [{
                data: [
                    <?= $run['tests'] - $run['errors'] - $run['failures'] - ($run['skipped'] ?? 0) ?>,
                    <?= $run['errors'] + $run['failures'] ?>,
                    <?= $run['skipped'] ?? 0 ?>
                ],
                backgroundColor: [
                    'rgba(28, 200, 138, 0.8)',
                    'rgba(231, 74, 59, 0.8)',
                    'rgba(246, 194, 62, 0.8)'
                ],
                borderColor: [
                    'rgba(28, 200, 138, 1)',
                    'rgba(231, 74, 59, 1)',
                    'rgba(246, 194, 62, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#e2e8f0',
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
});

// Copy to clipboard function
function copyToClipboard() {
    const output = document.getElementById('test-output');
    const textArea = document.createElement('textarea');
    textArea.value = output.textContent;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);

    // Show feedback
    alert('Test output copied to clipboard!');
}
</script>

<?php
// Include admin footer
require __DIR__ . '/../../modern/admin/partials/admin-footer.php';
?>
