<?php
/**
 * Consent Re-acceptance Page (Modern Theme)
 * Professional design for GDPR compliance
 */
$pageTitle = 'Accept Updated Terms';
$hero_title = 'Terms & Conditions Update';
$hero_subtitle = 'Action required to continue';
$hero_gradient = 'htb-hero-gradient-brand';
$hero_type = 'Legal';

require dirname(__DIR__) . '/../layouts/modern/header.php';

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
                        <button type="submit" class="htb-btn htb-btn-primary htb-btn-lg" id="acceptBtn" disabled>
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

<style>
/* Consent Page - Modern Theme */
.consent-page {
    padding: 140px 0 2rem 0; /* 120px header + 20px spacing */
    min-height: 60vh;
}

.consent-container {
    max-width: 720px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Alert Banner */
.consent-alert {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    border-left: 4px solid;
}

.consent-alert--warning {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-color: #f59e0b;
}

[data-theme="dark"] .consent-alert--warning {
    background: rgba(245, 158, 11, 0.15);
}

.consent-alert__icon {
    font-size: 1.5rem;
    color: #d97706;
    flex-shrink: 0;
    margin-top: 2px;
}

.consent-alert__title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: #111827;
}

[data-theme="dark"] .consent-alert__title {
    color: #f3f4f6;
}

.consent-alert__content p {
    margin: 0;
    color: #374151;
    font-size: 0.9375rem;
    line-height: 1.5;
}

[data-theme="dark"] .consent-alert__content p {
    color: #d1d5db;
}

/* Card */
.consent-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .consent-card {
    background: #1f2937;
    border-color: #374151;
}

.consent-card__header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}

[data-theme="dark"] .consent-card__header {
    background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    border-color: #374151;
}

.consent-card__title {
    font-size: 1.375rem;
    font-weight: 700;
    margin: 0;
    color: #111827;
}

[data-theme="dark"] .consent-card__title {
    color: #f3f4f6;
}

.consent-card__body {
    padding: 1.5rem;
}

.consent-lead {
    font-size: 1rem;
    color: #4b5563;
    margin: 0 0 1.5rem 0;
    line-height: 1.6;
}

[data-theme="dark"] .consent-lead {
    color: #9ca3af;
}

/* Consent Items */
.consent-items {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.consent-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

[data-theme="dark"] .consent-item {
    border-color: #374151;
}

.consent-item:hover {
    border-color: #a5b4fc;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.consent-item__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.consent-item__title {
    font-size: 1.0625rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: #111827;
}

[data-theme="dark"] .consent-item__title {
    color: #f3f4f6;
}

.consent-item__description {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0;
    line-height: 1.5;
}

[data-theme="dark"] .consent-item__description {
    color: #9ca3af;
}

/* Tags */
.consent-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    white-space: nowrap;
}

.consent-tag--warning {
    background: #fef3c7;
    color: #92400e;
}

.consent-tag--info {
    background: #e0e7ff;
    color: #3730a3;
}

[data-theme="dark"] .consent-tag--warning {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

[data-theme="dark"] .consent-tag--info {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}

/* Link */
.consent-item__actions {
    margin-bottom: 1rem;
}

.consent-link--external {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #4f46e5;
    font-size: 0.9375rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
}

.consent-link--external:hover {
    color: #4338ca;
    text-decoration: underline;
}

[data-theme="dark"] .consent-link--external {
    color: #818cf8;
}

[data-theme="dark"] .consent-link--external:hover {
    color: #a5b4fc;
}

.consent-link--external i:last-child {
    font-size: 0.75rem;
    opacity: 0.7;
}

/* Checkbox */
.consent-item__checkbox {
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
}

[data-theme="dark"] .consent-item__checkbox {
    border-color: #374151;
}

.consent-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

.consent-checkbox input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.consent-checkbox__box {
    width: 22px;
    height: 22px;
    min-width: 22px;
    border: 2px solid #9ca3af;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    margin-top: 1px;
}

.consent-checkbox__box::after {
    content: '';
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2.5px 2.5px 0;
    transform: rotate(45deg) scale(0);
    transition: transform 0.15s;
}

.consent-checkbox input:checked + .consent-checkbox__box {
    background: #4f46e5;
    border-color: #4f46e5;
}

.consent-checkbox input:checked + .consent-checkbox__box::after {
    transform: rotate(45deg) scale(1);
}

.consent-checkbox input:focus + .consent-checkbox__box {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
}

.consent-checkbox__label {
    font-size: 0.9375rem;
    color: #1f2937;
    line-height: 1.5;
}

[data-theme="dark"] .consent-checkbox__label {
    color: #e5e7eb;
}

.consent-version {
    color: #6b7280;
    font-size: 0.8125rem;
}

/* Submit */
.consent-submit {
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

[data-theme="dark"] .consent-submit {
    border-color: #374151;
}

.consent-submit .htb-btn-lg {
    padding: 0.875rem 2.5rem;
    font-size: 1rem;
    font-weight: 600;
    min-width: 240px;
}

.consent-submit .htb-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.consent-submit__note {
    font-size: 0.8125rem;
    color: #6b7280;
    margin: 1rem 0 0 0;
    line-height: 1.5;
}

/* Help Section */
.consent-help {
    margin-top: 2rem;
    padding: 1.25rem;
    background: #f9fafb;
    border-radius: 12px;
    border-left: 4px solid #4f46e5;
}

[data-theme="dark"] .consent-help {
    background: #1f2937;
}

.consent-help h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #111827;
}

[data-theme="dark"] .consent-help h3 {
    color: #f3f4f6;
}

.consent-help p {
    font-size: 0.875rem;
    color: #4b5563;
    margin: 0 0 0.5rem 0;
    line-height: 1.5;
}

[data-theme="dark"] .consent-help p {
    color: #9ca3af;
}

.consent-help a {
    color: #4f46e5;
}

[data-theme="dark"] .consent-help a {
    color: #818cf8;
}

.consent-help__decline {
    font-size: 0.8125rem;
    color: #6b7280 !important;
    margin-bottom: 0 !important;
}

/* Responsive */
@media (max-width: 640px) {
    .consent-item__header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .consent-submit .htb-btn-lg {
        width: 100%;
        min-width: unset;
    }
}
</style>

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

<?php require dirname(__DIR__) . '/../layouts/modern/footer.php'; ?>
