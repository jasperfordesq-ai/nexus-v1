<?php
/**
 * CivicOne Consent Re-acceptance Page
 * GOV.UK-inspired design for GDPR compliance
 */
$pageTitle = 'Accept Updated Terms';
$hero_title = 'Terms & Conditions Update';
$hero_subtitle = 'Action required to continue';

require dirname(__DIR__) . '/../layouts/civicone/header.php';
?>
<link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/civicone-consent-required.css">
<?php

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$consents = $consents ?? [];
$csrfToken = \Nexus\Core\Csrf::generate();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'the platform';
?>

<div class="consent-page">
    <div class="consent-container">
        <!-- Warning Banner -->
        <div class="consent-alert consent-alert--warning">
            <div class="consent-alert__icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="consent-alert__content">
                <h2 class="consent-alert__title">Important: Updated Terms</h2>
                <p>We've updated our terms and conditions. You must review and accept these changes to continue using <?= htmlspecialchars($tenantName) ?>.</p>
            </div>
        </div>

        <!-- Main Card -->
        <div class="consent-card">
            <div class="consent-card__header">
                <h1 class="consent-card__title">Review and Accept</h1>
            </div>

            <div class="consent-card__body">
                <p class="consent-lead">Please read each document carefully before accepting. These terms govern your use of our services and explain how we handle your personal data.</p>

                <form id="consentForm" class="consent-form">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $csrfToken ?>">

                    <div class="consent-items">
                        <?php foreach ($consents as $consent): ?>
                        <?php
                            // Determine the correct URL for each consent type
                            $docUrl = '#';
                            if ($consent['slug'] === 'terms_of_service') {
                                $docUrl = $basePath . '/terms';
                            } elseif ($consent['slug'] === 'privacy_policy') {
                                $docUrl = $basePath . '/privacy';
                            }
                        ?>
                        <div class="consent-item">
                            <div class="consent-item__header">
                                <div class="consent-item__info">
                                    <h3 class="consent-item__title"><?= htmlspecialchars($consent['name']) ?></h3>
                                    <p class="consent-item__description"><?= htmlspecialchars($consent['description'] ?? '') ?></p>
                                </div>
                                <?php if (($consent['reason'] ?? '') === 'version_outdated'): ?>
                                <span class="consent-tag consent-tag--warning">Updated</span>
                                <?php else: ?>
                                <span class="consent-tag consent-tag--info">Required</span>
                                <?php endif; ?>
                            </div>

                            <div class="consent-item__actions">
                                <a href="<?= $docUrl ?>" target="_blank" class="consent-link consent-link--external">
                                    <i class="fa-solid fa-file-lines"></i>
                                    Read full <?= htmlspecialchars($consent['name']) ?>
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </div>

                            <div class="consent-item__checkbox">
                                <label class="consent-checkbox">
                                    <input type="checkbox" name="consents[]" class="consent-check"
                                           value="<?= htmlspecialchars($consent['slug']) ?>" required>
                                    <span class="consent-checkbox__box"></span>
                                    <span class="consent-checkbox__label">
                                        I have read and agree to the <?= htmlspecialchars($consent['name']) ?>
                                        <span class="consent-version">(Version <?= htmlspecialchars($consent['current_version']) ?>)</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="consent-submit">
                        <button type="submit" class="civic-btn civic-btn--primary civic-btn--large" id="acceptBtn" disabled>
                            Accept and Continue
                        </button>
                        <p class="consent-submit__note">
                            By clicking "Accept and Continue", you confirm that you have read and understood the documents above.
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Help Section -->
        <div class="consent-help">
            <h3>Need help?</h3>
            <p>If you have questions about these terms or need assistance, please <a href="<?= $basePath ?>/contact">contact our support team</a>.</p>
            <p class="consent-help__decline">
                If you do not agree to these terms, you will not be able to continue using your account.
                <a href="<?= $basePath ?>/consent/decline">Learn more about your options</a>.
            </p>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('consentForm');
    const checkboxes = form.querySelectorAll('.consent-check');
    const submitBtn = document.getElementById('acceptBtn');

    function updateButtonState() {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        submitBtn.disabled = !allChecked;
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateButtonState));

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const consents = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        try {
            const response = await fetch('<?= $basePath ?>/consent/accept', {
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

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Failed to save consent. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Accept and Continue';
            }
        } catch (err) {
            console.error('Consent submission error:', err);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Accept and Continue';
        }
    });
});
</script>

<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
