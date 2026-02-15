<?php
/**
 * Broker Controls Configuration
 * Unified settings for all broker control features
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Broker Controls Configuration';
$adminPageSubtitle = 'Configure messaging, exchanges, risk tagging, and visibility';
$adminPageIcon = 'fa-sliders';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$config = $config ?? [];
$messaging = $config['messaging'] ?? [];
$riskTagging = $config['risk_tagging'] ?? [];
$exchangeWorkflow = $config['exchange_workflow'] ?? [];
$brokerVisibility = $config['broker_visibility'] ?? [];

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="broker-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Broker Controls Configuration
        </h1>
        <p class="admin-page-subtitle">Configure all broker control features for your community</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
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

<form action="<?= $basePath ?>/admin-legacy/broker-controls/configuration" method="POST" id="configForm">
    <?= Csrf::input() ?>

    <!-- Messaging Controls -->
    <div class="admin-glass-card broker-config-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--success">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Messaging Controls</h3>
                <p class="admin-card-subtitle">Control direct messaging between members</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Enable Direct Messaging</strong>
                    <p>When enabled, members can send messages directly to each other. When disabled, all communication must go through the exchange request system.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="direct_messaging_enabled" <?= ($messaging['direct_messaging_enabled'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Require Exchange Request for Listings</strong>
                    <p>When enabled, members must submit an exchange request rather than messaging directly about listings.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="require_exchange_for_listings" <?= ($messaging['require_exchange_for_listings'] ?? false) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>First Contact Monitoring</strong>
                    <p>Copy the first message between any two members to the broker review queue.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="first_contact_monitoring" <?= ($messaging['first_contact_monitoring'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-input-row">
                <label>
                    <strong>New Member Monitoring Period (days)</strong>
                    <p>Copy all messages from new members for this many days after joining. Set to 0 to disable.</p>
                </label>
                <input type="number" name="new_member_monitoring_days" min="0" max="365"
                       value="<?= (int)($messaging['new_member_monitoring_days'] ?? 30) ?>"
                       class="broker-config-input">
            </div>
        </div>
    </div>

    <!-- Risk Tagging -->
    <div class="admin-glass-card broker-config-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--danger">
                <i class="fa-solid fa-tags"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Risk Tagging</h3>
                <p class="admin-card-subtitle">Configure listing risk assessment</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Enable Risk Tagging</strong>
                    <p>Allow brokers to tag listings with risk levels (low, medium, high, critical).</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="risk_tagging_enabled" <?= ($riskTagging['enabled'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>High-Risk Requires Approval</strong>
                    <p>Require broker approval for any exchange involving a high or critical risk listing.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="high_risk_requires_approval" <?= ($riskTagging['high_risk_requires_approval'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Notify on High-Risk Match</strong>
                    <p>Send a notification to brokers when a match involves a high-risk listing.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="notify_on_high_risk_match" <?= ($riskTagging['notify_on_high_risk_match'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- Exchange Workflow -->
    <div class="admin-glass-card broker-config-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--warning">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Exchange Workflow</h3>
                <p class="admin-card-subtitle">Configure the structured exchange request system</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Enable Exchange Workflow</strong>
                    <p>Enable the structured exchange request system with status tracking and dual-party confirmation.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="exchange_workflow_enabled" <?= ($exchangeWorkflow['enabled'] ?? false) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Require Broker Approval</strong>
                    <p>All exchange requests must be approved by a broker before the parties can proceed.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="require_broker_approval" <?= ($exchangeWorkflow['require_broker_approval'] ?? false) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Auto-Approve Low Risk</strong>
                    <p>Automatically approve exchange requests for listings without risk tags or with low risk.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="auto_approve_low_risk" <?= ($exchangeWorkflow['auto_approve_low_risk'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-input-row">
                <label>
                    <strong>Max Hours Without Approval</strong>
                    <p>Exchanges below this hour threshold can proceed without broker approval.</p>
                </label>
                <input type="number" name="max_hours_without_approval" min="0" max="24" step="0.5"
                       value="<?= (float)($exchangeWorkflow['max_hours_without_approval'] ?? 4) ?>"
                       class="broker-config-input">
            </div>

            <div class="broker-config-input-row">
                <label>
                    <strong>Confirmation Deadline (hours)</strong>
                    <p>Both parties must confirm the exchange within this time after completion.</p>
                </label>
                <input type="number" name="confirmation_deadline_hours" min="1" max="720"
                       value="<?= (int)($exchangeWorkflow['confirmation_deadline_hours'] ?? 72) ?>"
                       class="broker-config-input">
            </div>

            <div class="broker-config-input-row">
                <label>
                    <strong>Request Expiry (hours)</strong>
                    <p>Exchange requests expire if not actioned within this time.</p>
                </label>
                <input type="number" name="expiry_hours" min="1" max="720"
                       value="<?= (int)($exchangeWorkflow['expiry_hours'] ?? 168) ?>"
                       class="broker-config-input">
            </div>
        </div>
    </div>

    <!-- Broker Visibility -->
    <div class="admin-glass-card broker-config-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon broker-stat-icon--info">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Broker Visibility</h3>
                <p class="admin-card-subtitle">Message oversight for compliance and safeguarding</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="broker-config-info-box">
                <i class="fa-solid fa-info-circle"></i>
                <span>These settings control which messages are copied to the broker review queue. This is essential for insurance compliance in the UK and for safeguarding vulnerable members.</span>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Enable Broker Visibility</strong>
                    <p>Master switch for all message copying features.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="broker_visibility_enabled" <?= ($brokerVisibility['enabled'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Copy First Contact Messages</strong>
                    <p>Copy the first message between any two members for broker review.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="copy_first_contact" <?= ($brokerVisibility['copy_first_contact'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Copy New Member Messages</strong>
                    <p>Copy all messages from members during their monitoring period.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="copy_new_member_messages" <?= ($brokerVisibility['copy_new_member_messages'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-toggle-row">
                <div class="broker-config-toggle-info">
                    <strong>Copy High-Risk Listing Messages</strong>
                    <p>Copy messages about listings tagged as high or critical risk.</p>
                </div>
                <label class="broker-config-toggle">
                    <input type="checkbox" name="copy_high_risk_listing_messages" <?= ($brokerVisibility['copy_high_risk_listing_messages'] ?? true) ? 'checked' : '' ?>>
                    <span class="broker-config-toggle-slider"></span>
                </label>
            </div>

            <div class="broker-config-input-row">
                <label>
                    <strong>Random Sample Percentage</strong>
                    <p>Percentage of messages to randomly sample for compliance review (0 = disabled).</p>
                </label>
                <input type="number" name="random_sample_percentage" min="0" max="100"
                       value="<?= (int)($brokerVisibility['random_sample_percentage'] ?? 0) ?>"
                       class="broker-config-input">
            </div>

            <div class="broker-config-input-row">
                <label>
                    <strong>Retention Period (days)</strong>
                    <p>How long to keep message copies before automatic deletion.</p>
                </label>
                <input type="number" name="retention_days" min="1" max="3650"
                       value="<?= (int)($brokerVisibility['retention_days'] ?? 365) ?>"
                       class="broker-config-input">
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div class="broker-config-actions">
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
            <i class="fa-solid fa-save"></i> Save Configuration
        </button>
        <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="admin-btn admin-btn-secondary admin-btn-lg">
            Cancel
        </a>
    </div>
</form>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
