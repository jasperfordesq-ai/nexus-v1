<?php
/**
 * CivicOne Consent Re-acceptance Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Accept Updated Terms';
$hero_title = 'Terms & Conditions Update';
$hero_subtitle = 'Action required to continue';

require dirname(__DIR__) . '/../layouts/civicone/header.php';

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$consents = $consents ?? [];
$csrfToken = \Nexus\Core\Csrf::generate();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'the platform';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Accept Updated Terms</li>
    </ol>
</nav>

<!-- Warning Banner -->
<div class="govuk-notification-banner govuk-notification-banner--warning" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Important</h2>
    </div>
    <div class="govuk-notification-banner__content">
        <p class="govuk-notification-banner__heading">
            <i class="fa-solid fa-triangle-exclamation govuk-!-margin-right-2" aria-hidden="true"></i>
            Updated Terms
        </p>
        <p class="govuk-body">We've updated our terms and conditions. You must review and accept these changes to continue using <?= htmlspecialchars($tenantName) ?>.</p>
    </div>
</div>

<h1 class="govuk-heading-xl">Review and Accept</h1>
<p class="govuk-body-l govuk-!-margin-bottom-6">Please read each document carefully before accepting. These terms govern your use of our services and explain how we handle your personal data.</p>

<form id="consentForm">
    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $csrfToken ?>">

    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
            <h2 class="govuk-fieldset__heading">Terms to Accept</h2>
        </legend>

        <?php foreach ($consents as $consent): ?>
            <?php
            $docUrl = '#';
            if ($consent['slug'] === 'terms_of_service') {
                $docUrl = $basePath . '/terms';
            } elseif ($consent['slug'] === 'privacy_policy') {
                $docUrl = $basePath . '/privacy';
            }
            ?>
            <div class="govuk-!-padding-4 govuk-!-margin-bottom-4 civicone-action-card">
                <div class="govuk-grid-row govuk-!-margin-bottom-3">
                    <div class="govuk-grid-column-two-thirds">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-1"><?= htmlspecialchars($consent['name']) ?></h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text"><?= htmlspecialchars($consent['description'] ?? '') ?></p>
                    </div>
                    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                        <?php if (($consent['reason'] ?? '') === 'version_outdated'): ?>
                            <span class="govuk-tag govuk-tag--yellow">Updated</span>
                        <?php else: ?>
                            <span class="govuk-tag govuk-tag--light-blue">Required</span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="govuk-body govuk-!-margin-bottom-3">
                    <a href="<?= $docUrl ?>" target="_blank" class="govuk-link" rel="noopener">
                        <i class="fa-solid fa-file-lines govuk-!-margin-right-1" aria-hidden="true"></i>
                        Read full <?= htmlspecialchars($consent['name']) ?>
                        <i class="fa-solid fa-arrow-up-right-from-square govuk-!-margin-left-1" aria-hidden="true"></i>
                    </a>
                </p>

                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input consent-check" id="consent-<?= htmlspecialchars($consent['slug']) ?>" name="consents[]" type="checkbox" value="<?= htmlspecialchars($consent['slug']) ?>" required>
                        <label class="govuk-label govuk-checkboxes__label" for="consent-<?= htmlspecialchars($consent['slug']) ?>">
                            I have read and agree to the <?= htmlspecialchars($consent['name']) ?>
                            <span class="govuk-hint govuk-!-margin-bottom-0">(Version <?= htmlspecialchars($consent['current_version']) ?>)</span>
                        </label>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <div class="govuk-!-margin-bottom-6">
        <button type="submit" class="govuk-button" data-module="govuk-button" id="acceptBtn" disabled>
            <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> Accept and Continue
        </button>
        <p class="govuk-body-s civicone-secondary-text">By clicking "Accept and Continue", you confirm that you have read and understood the documents above.</p>
    </div>
</form>

<div class="govuk-inset-text">
    <h3 class="govuk-heading-s">Need help?</h3>
    <p class="govuk-body govuk-!-margin-bottom-2">If you have questions about these terms or need assistance, please <a href="<?= $basePath ?>/contact" class="govuk-link">contact our support team</a>.</p>
    <p class="govuk-body govuk-!-margin-bottom-0">If you do not agree to these terms, you will not be able to continue using your account. <a href="<?= $basePath ?>/consent/decline" class="govuk-link">Learn more about your options</a>.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('consentForm');
    var checkboxes = form.querySelectorAll('.consent-check');
    var submitBtn = document.getElementById('acceptBtn');

    function updateButtonState() {
        var allChecked = Array.from(checkboxes).every(function(cb) { return cb.checked; });
        submitBtn.disabled = !allChecked;
    }

    checkboxes.forEach(function(cb) { cb.addEventListener('change', updateButtonState); });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        var consents = Array.from(checkboxes)
            .filter(function(cb) { return cb.checked; })
            .map(function(cb) { return cb.value; });

        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        try {
            var response = await fetch('<?= $basePath ?>/consent/accept', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                },
                body: JSON.stringify({
                    consents: consents,
                    csrf_token: document.getElementById('csrf_token').value
                })
            });

            var data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Failed to save consent. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> Accept and Continue';
            }
        } catch (err) {
            console.warn('Consent submission error:', err);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> Accept and Continue';
        }
    });
});
</script>

<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
