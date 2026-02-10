<?php
/**
 * Risk Tag Form - CivicOne Theme (GOV.UK)
 * Add or edit risk tag for a listing
 * Path: views/civicone/admin/broker-controls/risk-tags/form.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$listing = $listing ?? [];
$existingTag = $existing_tag ?? null;
$isEdit = !empty($existingTag);

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$riskCategories = [
    'safeguarding' => 'Safeguarding Concern',
    'financial' => 'Financial Risk',
    'health_safety' => 'Health & Safety',
    'legal' => 'Legal/Regulatory',
    'reputation' => 'Reputational Risk',
    'fraud' => 'Potential Fraud',
    'other' => 'Other',
];

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin/broker-controls/risk-tags" class="govuk-back-link">Back to Risk Tags</a>

        <h1 class="govuk-heading-xl"><?= $isEdit ? 'Edit' : 'Add' ?> Risk Tag</h1>

        <?php if ($flashError): ?>
        <div class="govuk-error-summary" role="alert">
            <h2 class="govuk-error-summary__title">There is a problem</h2>
            <div class="govuk-error-summary__body">
                <p><?= htmlspecialchars($flashError) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Listing Details -->
        <div class="govuk-summary-card govuk-!-margin-bottom-6">
            <div class="govuk-summary-card__title-wrapper">
                <h2 class="govuk-summary-card__title">Listing Details</h2>
            </div>
            <div class="govuk-summary-card__content">
                <dl class="govuk-summary-list">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Title</dt>
                        <dd class="govuk-summary-list__value">
                            <?= htmlspecialchars($listing['title'] ?? 'Unknown') ?>
                            <strong class="govuk-tag govuk-tag--<?= ($listing['type'] ?? '') === 'offer' ? 'green' : 'blue' ?>">
                                <?= ucfirst($listing['type'] ?? '') ?>
                            </strong>
                        </dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Owner</dt>
                        <dd class="govuk-summary-list__value"><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">Hours</dt>
                        <dd class="govuk-summary-list__value"><?= number_format($listing['hours'] ?? 0, 1) ?> hours</dd>
                    </div>
                </dl>
                <p class="govuk-body">
                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?? '' ?>" class="govuk-link" target="_blank">
                        View listing (opens in new tab)
                    </a>
                </p>
            </div>
        </div>

        <!-- Risk Tag Form -->
        <form method="POST" action="<?= $basePath ?>/admin/broker-controls/risk-tags/<?= $listing['id'] ?? '' ?>">
            <?= Csrf::input() ?>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">Risk Level</h2>
                    </legend>
                    <div class="govuk-radios" data-module="govuk-radios">
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="risk-low" name="risk_level" type="radio" value="low"
                                   <?= ($existingTag['risk_level'] ?? '') === 'low' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="risk-low">
                                <strong class="govuk-tag govuk-tag--grey">Low</strong>
                                Minor concern, monitor only
                            </label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="risk-medium" name="risk_level" type="radio" value="medium"
                                   <?= ($existingTag['risk_level'] ?? '') === 'medium' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="risk-medium">
                                <strong class="govuk-tag govuk-tag--yellow">Medium</strong>
                                Moderate concern, review messages
                            </label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="risk-high" name="risk_level" type="radio" value="high"
                                   <?= ($existingTag['risk_level'] ?? '') === 'high' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="risk-high">
                                <strong class="govuk-tag govuk-tag--orange">High</strong>
                                Significant concern, broker approval needed
                            </label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="risk-critical" name="risk_level" type="radio" value="critical"
                                   <?= ($existingTag['risk_level'] ?? '') === 'critical' ? 'checked' : '' ?>>
                            <label class="govuk-label govuk-radios__label" for="risk-critical">
                                <strong class="govuk-tag govuk-tag--red">Critical</strong>
                                Severe concern, immediate action required
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="risk_category">Risk Category</label>
                <select class="govuk-select" id="risk_category" name="risk_category">
                    <option value="">Select a category</option>
                    <?php foreach ($riskCategories as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($existingTag['risk_category'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="risk_notes">Notes</label>
                <div class="govuk-hint">Describe the risk concern in detail. These notes are only visible to brokers.</div>
                <textarea class="govuk-textarea" id="risk_notes" name="risk_notes" rows="5"><?= htmlspecialchars($existingTag['risk_notes'] ?? '') ?></textarea>
            </div>

            <div class="govuk-form-group">
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="requires_approval" name="requires_approval" type="checkbox" value="1"
                               <?= ($existingTag['requires_approval'] ?? false) ? 'checked' : '' ?>>
                        <label class="govuk-label govuk-checkboxes__label" for="requires_approval">
                            Require broker approval for exchanges involving this listing
                        </label>
                    </div>
                </div>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <?= $isEdit ? 'Update risk tag' : 'Save risk tag' ?>
                </button>
                <a href="<?= $basePath ?>/admin/broker-controls/risk-tags" class="govuk-link">Cancel</a>
            </div>

        </form>

    </main>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>
