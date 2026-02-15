<?php
/**
 * Broker Controls Configuration - CivicOne Theme (GOV.UK)
 * Configure broker control features per tenant
 * Path: views/civicone/admin-legacy/broker-controls/configuration.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$config = $config ?? [];
$messaging = $config['messaging'] ?? [];
$risk_tagging = $config['risk_tagging'] ?? [];
$exchange_workflow = $config['exchange_workflow'] ?? [];
$broker_visibility = $config['broker_visibility'] ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="govuk-back-link">Back to Broker Controls</a>

        <h1 class="govuk-heading-xl">Broker Controls Configuration</h1>
        <p class="govuk-body-l">Configure broker oversight features for your community.</p>

        <?php if ($flashSuccess): ?>
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading"><?= htmlspecialchars($flashSuccess) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
        <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
            <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
            <div class="govuk-error-summary__body">
                <p><?= htmlspecialchars($flashError) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $basePath ?>/admin-legacy/broker-controls/configuration">
            <?= Csrf::input() ?>

            <!-- Messaging Settings -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">Messaging</h2>
                    </legend>

                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="direct_messaging_enabled" name="messaging[direct_messaging_enabled]" type="checkbox" value="1"
                                   <?= ($messaging['direct_messaging_enabled'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="direct_messaging_enabled">
                                Enable direct messaging
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Allow members to send direct messages to each other. If disabled, members must use the exchange request system.
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <!-- Exchange Workflow Settings -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">Exchange Workflow</h2>
                    </legend>

                    <div class="govuk-checkboxes govuk-!-margin-bottom-4" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="exchange_enabled" name="exchange_workflow[enabled]" type="checkbox" value="1"
                                   <?= ($exchange_workflow['enabled'] ?? false) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="exchange_enabled">
                                Enable exchange workflow
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Require members to use a structured exchange request process for services.
                            </div>
                        </div>

                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="require_broker_approval" name="exchange_workflow[require_broker_approval]" type="checkbox" value="1"
                                   <?= ($exchange_workflow['require_broker_approval'] ?? false) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="require_broker_approval">
                                Require broker approval for exchanges
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                All exchanges must be approved by a broker before they can proceed.
                            </div>
                        </div>

                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="auto_approve_low_risk" name="exchange_workflow[auto_approve_low_risk]" type="checkbox" value="1"
                                   <?= ($exchange_workflow['auto_approve_low_risk'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="auto_approve_low_risk">
                                Auto-approve low risk exchanges
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Automatically approve exchanges that don't involve high-risk listings or exceed hour thresholds.
                            </div>
                        </div>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="max_hours_without_approval">
                            Maximum hours without approval
                        </label>
                        <div class="govuk-hint">
                            Exchanges exceeding this hour amount require broker approval (0-24 hours).
                        </div>
                        <input class="govuk-input govuk-input--width-5" id="max_hours_without_approval"
                               name="exchange_workflow[max_hours_without_approval]" type="number" step="0.5" min="0" max="24"
                               value="<?= htmlspecialchars($exchange_workflow['max_hours_without_approval'] ?? 4) ?>">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="confirmation_deadline_hours">
                            Confirmation deadline (hours)
                        </label>
                        <div class="govuk-hint">
                            How long members have to confirm completed exchanges (1-720 hours).
                        </div>
                        <input class="govuk-input govuk-input--width-5" id="confirmation_deadline_hours"
                               name="exchange_workflow[confirmation_deadline_hours]" type="number" min="1" max="720"
                               value="<?= htmlspecialchars($exchange_workflow['confirmation_deadline_hours'] ?? 72) ?>">
                    </div>
                </fieldset>
            </div>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <!-- Risk Tagging Settings -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">Risk Tagging</h2>
                    </legend>

                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="risk_tagging_enabled" name="risk_tagging[enabled]" type="checkbox" value="1"
                                   <?= ($risk_tagging['enabled'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="risk_tagging_enabled">
                                Enable risk tagging
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Allow brokers to tag listings with risk assessments.
                            </div>
                        </div>

                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="high_risk_requires_approval" name="risk_tagging[high_risk_requires_approval]" type="checkbox" value="1"
                                   <?= ($risk_tagging['high_risk_requires_approval'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="high_risk_requires_approval">
                                High-risk listings require approval
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Exchanges involving high or critical risk listings require broker approval.
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <!-- Broker Visibility Settings -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">Broker Message Visibility</h2>
                    </legend>

                    <div class="govuk-checkboxes govuk-!-margin-bottom-4" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="broker_visibility_enabled" name="broker_visibility[enabled]" type="checkbox" value="1"
                                   <?= ($broker_visibility['enabled'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="broker_visibility_enabled">
                                Enable broker message visibility
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Copy certain messages to a broker review queue for compliance monitoring.
                            </div>
                        </div>

                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="copy_first_contact" name="broker_visibility[copy_first_contact]" type="checkbox" value="1"
                                   <?= ($broker_visibility['copy_first_contact'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="copy_first_contact">
                                Copy first contact messages
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                When two members message for the first time, copy the message for review.
                            </div>
                        </div>

                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="copy_new_member_messages" name="broker_visibility[copy_new_member_messages]" type="checkbox" value="1"
                                   <?= ($broker_visibility['copy_new_member_messages'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="copy_new_member_messages">
                                Copy new member messages
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Copy messages from members who joined recently.
                            </div>
                        </div>

                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="copy_high_risk_listing_messages" name="broker_visibility[copy_high_risk_listing_messages]" type="checkbox" value="1"
                                   <?= ($broker_visibility['copy_high_risk_listing_messages'] ?? true) ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-checkboxes__label" for="copy_high_risk_listing_messages">
                                Copy high-risk listing messages
                            </label>
                            <div class="govuk-hint govuk-checkboxes__hint">
                                Copy messages about listings tagged as high or critical risk.
                            </div>
                        </div>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="retention_days">
                            Message copy retention (days)
                        </label>
                        <div class="govuk-hint">
                            How long to keep message copies before automatic deletion (1-3650 days).
                        </div>
                        <input class="govuk-input govuk-input--width-5" id="retention_days"
                               name="broker_visibility[retention_days]" type="number" min="1" max="3650"
                               value="<?= htmlspecialchars($broker_visibility['retention_days'] ?? 365) ?>">
                    </div>
                </fieldset>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    Save configuration
                </button>
                <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="govuk-link">Cancel</a>
            </div>

        </form>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
