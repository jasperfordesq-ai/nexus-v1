<?php
/**
 * CivicOne Consent Re-acceptance Page
 * Professional GOV.UK-inspired design for GDPR compliance
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

<div class="civic-container civic-container--narrow">
    <div class="consent-page">
        <!-- Notification Banner -->
        <div class="civic-notification civic-notification--warning">
            <div class="civic-notification__icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="civic-notification__content">
                <h2 class="civic-notification__title">Important: Updated Terms</h2>
                <p>We've updated our terms and conditions. You must review and accept these changes to continue using <?= htmlspecialchars($tenantName) ?>.</p>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="civic-card">
            <div class="civic-card__header">
                <h1 class="civic-card__title">Review and Accept</h1>
            </div>

            <div class="civic-card__body">
                <p class="civic-lead">Please read each document carefully before accepting. These terms govern your use of our services and explain how we handle your personal data.</p>

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
                                <span class="civic-tag civic-tag--warning">Updated</span>
                                <?php else: ?>
                                <span class="civic-tag civic-tag--info">Required</span>
                                <?php endif; ?>
                            </div>

                            <div class="consent-item__actions">
                                <a href="<?= $docUrl ?>" target="_blank" class="civic-link civic-link--external">
                                    <i class="fa-solid fa-file-lines"></i>
                                    Read full <?= htmlspecialchars($consent['name']) ?>
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </div>

                            <div class="consent-item__checkbox">
                                <label class="civic-checkbox">
                                    <input type="checkbox" name="consents[]" class="consent-check"
                                           value="<?= htmlspecialchars($consent['slug']) ?>" required>
                                    <span class="civic-checkbox__box"></span>
                                    <span class="civic-checkbox__label">
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

<style>
/* Consent Page - CivicOne Theme */
.consent-page {
    padding: var(--space-600, 2rem) 0;
}

/* Notification Banner */
.civic-notification {
    display: flex;
    gap: var(--space-400, 1rem);
    padding: var(--space-400, 1rem) var(--space-500, 1.25rem);
    border-radius: var(--radius-md, 8px);
    margin-bottom: var(--space-600, 2rem);
    border-left: 4px solid;
}

.civic-notification--warning {
    background: var(--color-warning-50, #fffbeb);
    border-color: var(--color-warning-500, #f59e0b);
}

[data-theme="dark"] .civic-notification--warning {
    background: rgba(245, 158, 11, 0.1);
}

.civic-notification__icon {
    font-size: 1.5rem;
    color: var(--color-warning-600, #d97706);
    flex-shrink: 0;
    margin-top: 2px;
}

.civic-notification__title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: var(--color-gray-900, #111827);
}

[data-theme="dark"] .civic-notification__title {
    color: var(--color-gray-100, #f3f4f6);
}

.civic-notification__content p {
    margin: 0;
    color: var(--color-gray-700, #374151);
    font-size: 0.9375rem;
}

[data-theme="dark"] .civic-notification__content p {
    color: var(--color-gray-300, #d1d5db);
}

/* Card Styles */
.civic-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-gray-200, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .civic-card {
    background: var(--color-gray-800, #1f2937);
    border-color: var(--color-gray-700, #374151);
}

.civic-card__header {
    padding: var(--space-500, 1.25rem) var(--space-600, 1.5rem);
    border-bottom: 1px solid var(--color-gray-200, #e5e7eb);
    background: var(--color-gray-50, #f9fafb);
}

[data-theme="dark"] .civic-card__header {
    background: var(--color-gray-900, #111827);
    border-color: var(--color-gray-700, #374151);
}

.civic-card__title {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    color: var(--color-gray-900, #111827);
}

[data-theme="dark"] .civic-card__title {
    color: var(--color-gray-100, #f3f4f6);
}

.civic-card__body {
    padding: var(--space-600, 1.5rem);
}

.civic-lead {
    font-size: 1rem;
    color: var(--color-gray-600, #4b5563);
    margin: 0 0 var(--space-600, 1.5rem) 0;
    line-height: 1.6;
}

[data-theme="dark"] .civic-lead {
    color: var(--color-gray-400, #9ca3af);
}

/* Consent Items */
.consent-items {
    display: flex;
    flex-direction: column;
    gap: var(--space-400, 1rem);
    margin-bottom: var(--space-600, 1.5rem);
}

.consent-item {
    border: 1px solid var(--color-gray-200, #e5e7eb);
    border-radius: var(--radius-md, 8px);
    padding: var(--space-500, 1.25rem);
    transition: border-color 0.2s, box-shadow 0.2s;
}

[data-theme="dark"] .consent-item {
    border-color: var(--color-gray-700, #374151);
}

.consent-item:hover {
    border-color: var(--color-primary-300, #a5b4fc);
}

.consent-item__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--space-400, 1rem);
    margin-bottom: var(--space-400, 1rem);
}

.consent-item__title {
    font-size: 1.0625rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: var(--color-gray-900, #111827);
}

[data-theme="dark"] .consent-item__title {
    color: var(--color-gray-100, #f3f4f6);
}

.consent-item__description {
    font-size: 0.875rem;
    color: var(--color-gray-600, #4b5563);
    margin: 0;
    line-height: 1.5;
}

[data-theme="dark"] .consent-item__description {
    color: var(--color-gray-400, #9ca3af);
}

/* Tags */
.civic-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    white-space: nowrap;
}

.civic-tag--warning {
    background: var(--color-warning-100, #fef3c7);
    color: var(--color-warning-800, #92400e);
}

.civic-tag--info {
    background: var(--color-primary-100, #e0e7ff);
    color: var(--color-primary-800, #3730a3);
}

[data-theme="dark"] .civic-tag--warning {
    background: rgba(245, 158, 11, 0.2);
    color: var(--color-warning-400, #fbbf24);
}

[data-theme="dark"] .civic-tag--info {
    background: rgba(99, 102, 241, 0.2);
    color: var(--color-primary-400, #818cf8);
}

/* Link Styles */
.consent-item__actions {
    margin-bottom: var(--space-400, 1rem);
}

.civic-link--external {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-primary-600, #4f46e5);
    font-size: 0.9375rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
}

.civic-link--external:hover {
    color: var(--color-primary-700, #4338ca);
    text-decoration: underline;
}

[data-theme="dark"] .civic-link--external {
    color: var(--color-primary-400, #818cf8);
}

[data-theme="dark"] .civic-link--external:hover {
    color: var(--color-primary-300, #a5b4fc);
}

.civic-link--external i:last-child {
    font-size: 0.75rem;
    opacity: 0.7;
}

/* Checkbox Styles */
.consent-item__checkbox {
    padding-top: var(--space-400, 1rem);
    border-top: 1px solid var(--color-gray-100, #f3f4f6);
}

[data-theme="dark"] .consent-item__checkbox {
    border-color: var(--color-gray-700, #374151);
}

.civic-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

.civic-checkbox input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.civic-checkbox__box {
    width: 22px;
    height: 22px;
    min-width: 22px;
    border: 2px solid var(--color-gray-400, #9ca3af);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    margin-top: 1px;
}

.civic-checkbox__box::after {
    content: '';
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg) scale(0);
    transition: transform 0.15s;
}

.civic-checkbox input:checked + .civic-checkbox__box {
    background: var(--color-primary-600, #4f46e5);
    border-color: var(--color-primary-600, #4f46e5);
}

.civic-checkbox input:checked + .civic-checkbox__box::after {
    transform: rotate(45deg) scale(1);
}

.civic-checkbox input:focus + .civic-checkbox__box {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
}

.civic-checkbox__label {
    font-size: 0.9375rem;
    color: var(--color-gray-800, #1f2937);
    line-height: 1.5;
}

[data-theme="dark"] .civic-checkbox__label {
    color: var(--color-gray-200, #e5e7eb);
}

.consent-version {
    color: var(--color-gray-500, #6b7280);
    font-size: 0.8125rem;
}

/* Submit Section */
.consent-submit {
    padding-top: var(--space-500, 1.25rem);
    border-top: 1px solid var(--color-gray-200, #e5e7eb);
    text-align: center;
}

[data-theme="dark"] .consent-submit {
    border-color: var(--color-gray-700, #374151);
}

.civic-btn--large {
    padding: 0.875rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    min-width: 220px;
}

.civic-btn--primary {
    background: var(--color-primary-600, #4f46e5);
    color: white;
    border: none;
    border-radius: var(--radius-md, 8px);
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
}

.civic-btn--primary:hover:not(:disabled) {
    background: var(--color-primary-700, #4338ca);
}

.civic-btn--primary:active:not(:disabled) {
    transform: scale(0.98);
}

.civic-btn--primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.consent-submit__note {
    font-size: 0.8125rem;
    color: var(--color-gray-500, #6b7280);
    margin: var(--space-400, 1rem) 0 0 0;
    line-height: 1.5;
}

/* Help Section */
.consent-help {
    margin-top: var(--space-600, 1.5rem);
    padding: var(--space-500, 1.25rem);
    background: var(--color-gray-50, #f9fafb);
    border-radius: var(--radius-md, 8px);
}

[data-theme="dark"] .consent-help {
    background: var(--color-gray-800, #1f2937);
}

.consent-help h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: var(--color-gray-900, #111827);
}

[data-theme="dark"] .consent-help h3 {
    color: var(--color-gray-100, #f3f4f6);
}

.consent-help p {
    font-size: 0.875rem;
    color: var(--color-gray-600, #4b5563);
    margin: 0 0 0.5rem 0;
    line-height: 1.5;
}

[data-theme="dark"] .consent-help p {
    color: var(--color-gray-400, #9ca3af);
}

.consent-help a {
    color: var(--color-primary-600, #4f46e5);
}

[data-theme="dark"] .consent-help a {
    color: var(--color-primary-400, #818cf8);
}

.consent-help__decline {
    font-size: 0.8125rem;
    color: var(--color-gray-500, #6b7280);
}

/* Responsive */
@media (max-width: 640px) {
    .consent-item__header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .civic-btn--large {
        width: 100%;
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

<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
