<?php
/**
 * Broker Controls Dashboard
 * Overview of all broker control features and pending actions
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Broker Controls';
$adminPageSubtitle = 'Manage exchange workflows, risk tagging, and messaging';
$adminPageIcon = 'fa-shield-halved';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$config = $config ?? [];
$featureSummary = $feature_summary ?? [];
$pendingExchanges = $pending_exchanges ?? 0;
$unreviewedMessages = $unreviewed_messages ?? 0;
$highRiskListings = $high_risk_listings ?? 0;
$usersUnderMonitoring = $users_under_monitoring ?? 0;
$recentActivity = $recent_activity ?? [];

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-shield-halved"></i>
            Broker Controls Dashboard
        </h1>
        <p class="admin-page-subtitle">Manage exchange workflows, risk tagging, and message oversight</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/broker-controls/configuration" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-sliders"></i> Configuration
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($flashSuccess): ?>
<div class="broker-flash broker-flash--success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="broker-flash broker-flash--error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="broker-stats-grid">
    <!-- Pending Exchanges -->
    <a href="<?= $basePath ?>/admin/broker-controls/exchanges" class="admin-glass-card broker-stat-card">
        <div class="broker-stat-icon broker-stat-icon--warning">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="broker-stat-content">
            <div class="broker-stat-value"><?= $pendingExchanges ?></div>
            <div class="broker-stat-label">Pending Exchanges</div>
        </div>
        <?php if ($pendingExchanges > 0): ?>
        <span class="admin-badge admin-badge-warning"><?= $pendingExchanges ?></span>
        <?php endif; ?>
    </a>

    <!-- Unreviewed Messages -->
    <a href="<?= $basePath ?>/admin/broker-controls/messages" class="admin-glass-card broker-stat-card">
        <div class="broker-stat-icon broker-stat-icon--info">
            <i class="fa-solid fa-envelope-open-text"></i>
        </div>
        <div class="broker-stat-content">
            <div class="broker-stat-value"><?= $unreviewedMessages ?></div>
            <div class="broker-stat-label">Unreviewed Messages</div>
        </div>
        <?php if ($unreviewedMessages > 0): ?>
        <span class="admin-badge admin-badge-info"><?= $unreviewedMessages ?></span>
        <?php endif; ?>
    </a>

    <!-- High Risk Listings -->
    <a href="<?= $basePath ?>/admin/broker-controls/risk-tags?level=high" class="admin-glass-card broker-stat-card">
        <div class="broker-stat-icon broker-stat-icon--danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="broker-stat-content">
            <div class="broker-stat-value"><?= $highRiskListings ?></div>
            <div class="broker-stat-label">High Risk Listings</div>
        </div>
    </a>

    <!-- Users Under Monitoring -->
    <a href="<?= $basePath ?>/admin/broker-controls/monitoring" class="admin-glass-card broker-stat-card">
        <div class="broker-stat-icon broker-stat-icon--purple">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="broker-stat-content">
            <div class="broker-stat-value"><?= $usersUnderMonitoring ?></div>
            <div class="broker-stat-label">Users Monitored</div>
        </div>
    </a>
</div>

<!-- Feature Status Cards -->
<div class="broker-feature-grid">
    <!-- Messaging Controls -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--success">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Messaging Controls</h3>
                <p class="admin-card-subtitle">Direct messaging and monitoring settings</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-feature-status-list">
                <div class="broker-feature-status-item">
                    <span>Direct Messaging</span>
                    <?php if ($featureSummary['direct_messaging'] ?? true): ?>
                    <span class="admin-badge admin-badge-success">Enabled</span>
                    <?php else: ?>
                    <span class="admin-badge admin-badge-danger">Disabled</span>
                    <?php endif; ?>
                </div>
                <div class="broker-feature-status-item">
                    <span>First Contact Monitoring</span>
                    <?php if ($featureSummary['first_contact_monitoring'] ?? false): ?>
                    <span class="admin-badge admin-badge-success">Active</span>
                    <?php else: ?>
                    <span class="admin-badge admin-badge-secondary">Off</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Exchange Workflow -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--warning">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Exchange Workflow</h3>
                <p class="admin-card-subtitle">Structured exchange request system</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-feature-status-list">
                <div class="broker-feature-status-item">
                    <span>Exchange Workflow</span>
                    <?php if ($featureSummary['exchange_workflow'] ?? false): ?>
                    <span class="admin-badge admin-badge-success">Enabled</span>
                    <?php else: ?>
                    <span class="admin-badge admin-badge-secondary">Disabled</span>
                    <?php endif; ?>
                </div>
                <div class="broker-feature-status-item">
                    <span>Broker Approval Required</span>
                    <?php if ($featureSummary['broker_approval_required'] ?? false): ?>
                    <span class="admin-badge admin-badge-warning">Required</span>
                    <?php else: ?>
                    <span class="admin-badge admin-badge-secondary">Optional</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Risk Tagging -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--danger">
                <i class="fa-solid fa-tags"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Risk Tagging</h3>
                <p class="admin-card-subtitle">Listing risk assessment and flags</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-feature-status-list">
                <div class="broker-feature-status-item">
                    <span>Risk Tagging</span>
                    <?php if ($featureSummary['risk_tagging'] ?? true): ?>
                    <span class="admin-badge admin-badge-success">Enabled</span>
                    <?php else: ?>
                    <span class="admin-badge admin-badge-secondary">Disabled</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= $basePath ?>/admin/broker-controls/risk-tags" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-top: 1rem;">
                <i class="fa-solid fa-eye"></i> View All Tags
            </a>
        </div>
    </div>

    <!-- Broker Visibility -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--info">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Broker Visibility</h3>
                <p class="admin-card-subtitle">Message review and compliance</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-feature-status-list">
                <div class="broker-feature-status-item">
                    <span>Message Visibility</span>
                    <?php if ($featureSummary['broker_visibility'] ?? false): ?>
                    <span class="admin-badge admin-badge-success">Active</span>
                    <?php else: ?>
                    <span class="admin-badge admin-badge-secondary">Disabled</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= $basePath ?>/admin/broker-controls/messages" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-top: 1rem;">
                <i class="fa-solid fa-inbox"></i> Review Messages
            </a>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<?php if (!empty($recentActivity)): ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon broker-stat-icon--primary">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Recent Exchange Activity</h3>
            <p class="admin-card-subtitle">Latest exchange requests and updates</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Requester</th>
                        <th>Provider</th>
                        <th>Listing</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $activity): ?>
                    <tr>
                        <td>#<?= $activity['id'] ?></td>
                        <td><?= htmlspecialchars($activity['requester_name']) ?></td>
                        <td><?= htmlspecialchars($activity['provider_name']) ?></td>
                        <td><?= htmlspecialchars($activity['listing_title']) ?></td>
                        <td>
                            <?php
                            $statusClass = match($activity['status']) {
                                'completed' => 'success',
                                'cancelled', 'expired' => 'danger',
                                'disputed' => 'danger',
                                'pending_broker' => 'warning',
                                default => 'info'
                            };
                            ?>
                            <span class="admin-badge admin-badge-<?= $statusClass ?>"><?= ucwords(str_replace('_', ' ', $activity['status'])) ?></span>
                        </td>
                        <td><?= date('M j, Y', strtotime($activity['created_at'])) ?></td>
                        <td>
                            <a href="<?= $basePath ?>/admin/broker-controls/exchanges/<?= $activity['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
