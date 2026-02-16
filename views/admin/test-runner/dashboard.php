<?php
/**
 * Test Runner Dashboard - FDS Gold Standard
 *
 * Admin UI for running and monitoring API tests
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'API Test Runner';
$adminPageSubtitle = 'Automated testing and API health monitoring';
$adminPageIcon = 'fa-flask';

// Include the standalone admin header (includes <!DOCTYPE html>, <head>, etc.)
require __DIR__ . '/../../layouts/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-flask"></i>
            API Test Runner
        </h1>
        <p class="admin-page-subtitle">Automated testing and API health monitoring</p>
    </div>
    <div class="admin-page-header-actions">
        <button class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <button class="admin-btn admin-btn-primary" onclick="runAllTests()">
            <i class="fa-solid fa-play"></i>
            Run All Tests
        </button>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="admin-stats-grid">
    <!-- Total Test Runs -->
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-vial"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_runs']) ?></div>
            <div class="admin-stat-label">Total Test Runs</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>All Time</span>
        </div>
    </div>

    <!-- Success Rate -->
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $stats['success_rate'] ?>%</div>
            <div class="admin-stat-label">Success Rate</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-arrow-up"></i>
            <span>Healthy</span>
        </div>
    </div>

    <!-- Total Tests -->
    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_tests']) ?></div>
            <div class="admin-stat-label">Total Tests</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-check"></i>
            <span>Assertions</span>
        </div>
    </div>

    <!-- Average Duration -->
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-stopwatch"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $stats['avg_duration'] ?>s</div>
            <div class="admin-stat-label">Avg Duration</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-gauge"></i>
            <span>Speed</span>
        </div>
    </div>
</div>

<!-- Test Suites Section -->
<div class="admin-section-header">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-play-circle"></i>
        Test Suites
    </h2>
    <p class="admin-section-subtitle">Click any suite to run tests and validate API endpoints</p>
</div>

<div class="admin-grid-3">
    <?php foreach ($suites as $key => $suite): ?>
    <div class="admin-card admin-card-hover test-suite-card" data-suite="<?= htmlspecialchars($key) ?>">
        <div class="admin-card-header">
            <div class="admin-card-icon admin-card-icon-<?= ['blue', 'green', 'purple', 'orange', 'cyan', 'pink', 'indigo', 'teal', 'red'][array_search($key, array_keys($suites)) % 9] ?>">
                <span style="font-size: 1.5rem;"><?= $suite['icon'] ?></span>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title"><?= htmlspecialchars($suite['name']) ?></h3>
                <p class="admin-card-subtitle"><?= htmlspecialchars($suite['description']) ?></p>
            </div>
        </div>
        <div class="admin-card-body">
            <button class="admin-btn admin-btn-primary admin-btn-block run-test-btn" data-suite="<?= htmlspecialchars($key) ?>">
                <i class="fa-solid fa-play"></i>
                Run Tests
            </button>
            <div class="test-status" style="display:none; margin-top: 0.75rem; text-align: center;">
                <div class="admin-loading-spinner"></div>
                <span style="margin-left: 0.5rem; color: var(--admin-text-muted);">Running tests...</span>
            </div>
            <div class="test-result" style="display:none; margin-top: 0.75rem;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Test Output Section -->
<div id="test-output-section" style="display:none; margin-top: 2rem;">
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="admin-card-icon admin-card-icon-gray">
                <i class="fa-solid fa-terminal"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Test Output</h3>
                <p class="admin-card-subtitle">Detailed test execution results</p>
            </div>
            <button class="admin-btn admin-btn-secondary admin-btn-sm" onclick="closeTestOutput()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-card-body">
            <pre id="test-output" class="admin-code-block"></pre>
        </div>
    </div>
</div>

<!-- Recent Test Runs Section -->
<div class="admin-section-header" style="margin-top: 2rem;">
    <h2 class="admin-section-title">
        <i class="fa-solid fa-history"></i>
        Recent Test Runs
    </h2>
    <p class="admin-section-subtitle">View history of test executions and results</p>
</div>

<?php if (empty($recentRuns)): ?>
<div class="admin-card">
    <div class="admin-card-body" style="text-align: center; padding: 3rem 1rem;">
        <div class="admin-empty-state">
            <div class="admin-empty-state-icon">
                <i class="fa-solid fa-info-circle"></i>
            </div>
            <h3 class="admin-empty-state-title">No test runs yet</h3>
            <p class="admin-empty-state-text">Run your first test to get started!</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="admin-card">
    <div class="admin-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Suite</th>
                    <th>Tests</th>
                    <th>Assertions</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Run By</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRuns as $run): ?>
                <tr>
                    <td>
                        <span class="admin-badge admin-badge-secondary">
                            <?= htmlspecialchars(ucfirst($run['suite'])) ?>
                        </span>
                    </td>
                    <td><?= $run['tests'] ?></td>
                    <td><?= $run['assertions'] ?></td>
                    <td>
                        <?php if ($run['success']): ?>
                            <span class="admin-badge admin-badge-success">
                                <i class="fa-solid fa-check"></i> Passed
                            </span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-danger">
                                <i class="fa-solid fa-times"></i> Failed
                            </span>
                        <?php endif; ?>
                        <?php if ($run['errors'] > 0): ?>
                            <small class="admin-text-danger" style="display: block; margin-top: 0.25rem;">
                                <?= $run['errors'] ?> errors
                            </small>
                        <?php endif; ?>
                        <?php if ($run['failures'] > 0): ?>
                            <small class="admin-text-warning" style="display: block; margin-top: 0.25rem;">
                                <?= $run['failures'] ?> failures
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><?= $run['duration'] ?>s</td>
                    <td>
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
                    </td>
                    <td>
                        <span class="admin-text-muted admin-text-sm">
                            <?= date('M j, Y g:i A', strtotime($run['created_at'])) ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= $basePath ?>/admin-legacy/tests/view?id=<?= $run['id'] ?>"
                           class="admin-btn admin-btn-secondary admin-btn-sm">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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

@media (max-width: 1200px) {
    .admin-grid-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-grid-2,
    .admin-grid-3 {
        grid-template-columns: 1fr;
    }
}

/* Test Suite Specific Styles */
.test-suite-card {
    transition: all 0.3s ease;
}

.test-suite-card:hover {
    transform: translateY(-2px);
}

.admin-code-block {
    background: #0a0e1a;
    color: #e2e8f0;
    padding: 1.5rem;
    border-radius: 0.5rem;
    font-family: 'Courier New', Consolas, monospace;
    font-size: 0.875rem;
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid rgba(99, 102, 241, 0.2);
    margin: 0;
}

.admin-loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
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
</style>

<script>
jQuery(document).ready(function($) {
    console.log('Test Runner initialized');
    console.log('Found ' + $('.run-test-btn').length + ' test buttons');

    // Run test button click handler
    $('.run-test-btn').click(function(e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('Button clicked for suite:', $(this).data('suite'));
        const suite = $(this).data('suite');
        const card = $(this).closest('.test-suite-card');
        const btn = $(this);
        const statusDiv = card.find('.test-status');
        const resultDiv = card.find('.test-result');

        // Show loading state
        btn.prop('disabled', true);
        statusDiv.show();
        resultDiv.hide();

        // Run tests via AJAX
        console.log('Starting AJAX request to:', '<?= $basePath ?>/admin-legacy/tests/run');
        $.ajax({
            url: '<?= $basePath ?>/admin-legacy/tests/run',
            method: 'POST',
            data: {
                suite: suite,
                save_results: 'true'
            },
            success: function(response) {
                console.log('AJAX success:', response);
                // Hide loading
                statusDiv.hide();
                btn.prop('disabled', false);

                // Show result
                if (response.success) {
                    resultDiv.html(
                        '<div class="admin-alert admin-alert-success" style="margin: 0;">' +
                        '<div class="admin-alert-icon"><i class="fa-solid fa-check-circle"></i></div>' +
                        '<div class="admin-alert-content">' +
                        '<div class="admin-alert-title">Tests Passed!</div>' +
                        '<div class="admin-alert-text">' +
                        response.tests + ' tests, ' +
                        response.assertions + ' assertions ' +
                        '(' + response.duration + 's)' +
                        '</div>' +
                        '</div>' +
                        '</div>'
                    );
                } else {
                    resultDiv.html(
                        '<div class="admin-alert admin-alert-danger" style="margin: 0;">' +
                        '<div class="admin-alert-icon"><i class="fa-solid fa-exclamation-circle"></i></div>' +
                        '<div class="admin-alert-content">' +
                        '<div class="admin-alert-title">Tests Failed</div>' +
                        '<div class="admin-alert-text">' +
                        (response.errors || 0) + ' errors, ' +
                        (response.failures || 0) + ' failures' +
                        '</div>' +
                        '</div>' +
                        '</div>'
                    );
                }
                resultDiv.show();

                // Show output
                if (response.output) {
                    $('#test-output').text(response.output);
                    $('#test-output-section').slideDown();
                }

                // Reload page after 2 seconds to show new result in history
                setTimeout(function() {
                    location.reload();
                }, 2000);
            },
            error: function(xhr) {
                console.log('AJAX error:', xhr);
                statusDiv.hide();
                btn.prop('disabled', false);

                let errorMsg = 'Failed to run tests';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }

                resultDiv.html(
                    '<div class="admin-alert admin-alert-danger" style="margin: 0;">' +
                    '<div class="admin-alert-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>' +
                    '<div class="admin-alert-content">' +
                    '<div class="admin-alert-title">Error</div>' +
                    '<div class="admin-alert-text">' + errorMsg + '</div>' +
                    '</div>' +
                    '</div>'
                );
                resultDiv.show();
            }
        });
    });
}); // End jQuery ready

// Run all tests function
function runAllTests() {
    $('.run-test-btn[data-suite="all"]').click();
}

// Close test output
function closeTestOutput() {
    $('#test-output-section').slideUp();
}
</script>

<?php
// Include admin footer
require __DIR__ . '/../../layouts/admin-footer.php';
?>
