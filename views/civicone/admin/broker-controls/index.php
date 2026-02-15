<?php
/**
 * Broker Controls Dashboard - CivicOne Theme (GOV.UK)
 * Main dashboard for broker control features
 * Path: views/civicone/admin-legacy/broker-controls/index.php
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$stats = $stats ?? [];
$features = $features ?? [];

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Broker Controls</h1>
                <p class="govuk-body-l">Manage broker oversight features for your community.</p>
            </div>
            <div class="govuk-grid-column-one-third" style="text-align: right;">
                <a href="<?= $basePath ?>/admin-legacy/broker-controls/configuration" class="govuk-button">
                    Configuration
                </a>
            </div>
        </div>

        <!-- Feature Status -->
        <h2 class="govuk-heading-l">Feature Status</h2>
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: <?= ($features['direct_messaging'] ?? true) ? '#00703c' : '#d4351c' ?>; padding: 15px;">
                    <p class="govuk-body" style="color: white; margin: 0 0 5px 0; font-size: 14px;">Direct Messaging</p>
                    <div style="font-size: 24px; font-weight: bold; color: white;">
                        <?= ($features['direct_messaging'] ?? true) ? 'Enabled' : 'Disabled' ?>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: <?= ($features['exchange_workflow'] ?? false) ? '#00703c' : '#505a5f' ?>; padding: 15px;">
                    <p class="govuk-body" style="color: white; margin: 0 0 5px 0; font-size: 14px;">Exchange Workflow</p>
                    <div style="font-size: 24px; font-weight: bold; color: white;">
                        <?= ($features['exchange_workflow'] ?? false) ? 'Enabled' : 'Disabled' ?>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: <?= ($features['risk_tagging'] ?? true) ? '#00703c' : '#505a5f' ?>; padding: 15px;">
                    <p class="govuk-body" style="color: white; margin: 0 0 5px 0; font-size: 14px;">Risk Tagging</p>
                    <div style="font-size: 24px; font-weight: bold; color: white;">
                        <?= ($features['risk_tagging'] ?? true) ? 'Enabled' : 'Disabled' ?>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: <?= ($features['broker_visibility'] ?? true) ? '#00703c' : '#505a5f' ?>; padding: 15px;">
                    <p class="govuk-body" style="color: white; margin: 0 0 5px 0; font-size: 14px;">Message Visibility</p>
                    <div style="font-size: 24px; font-weight: bold; color: white;">
                        <?= ($features['broker_visibility'] ?? true) ? 'Enabled' : 'Disabled' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Statistics -->
        <h2 class="govuk-heading-l">Quick Statistics</h2>
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #1d70b8; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['pending_exchanges'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Pending Exchanges</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #f47738; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['unreviewed_messages'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Unreviewed Messages</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #d4351c; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['high_risk_listings'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">High Risk Listings</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #00703c; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['completed_exchanges'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Completed (30d)</p>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <h2 class="govuk-heading-l">Manage</h2>
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Exchange Requests</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-body">Review and approve exchange requests between members.</p>
                        <a href="<?= $basePath ?>/admin-legacy/broker-controls/exchanges" class="govuk-button">
                            View exchanges
                        </a>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Risk Tags</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-body">Manage risk assessments for listings.</p>
                        <a href="<?= $basePath ?>/admin-legacy/broker-controls/risk-tags" class="govuk-button">
                            View risk tags
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="govuk-grid-row govuk-!-margin-top-4">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Message Review</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-body">Review flagged and monitored messages.</p>
                        <a href="<?= $basePath ?>/admin-legacy/broker-controls/messages" class="govuk-button">
                            Review messages
                        </a>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">User Monitoring</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-body">Manage user messaging restrictions.</p>
                        <a href="<?= $basePath ?>/admin-legacy/broker-controls/monitoring" class="govuk-button">
                            View monitoring
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
