<?php
/**
 * Consent Decline Page (Modern Theme)
 * Shown when user chooses not to accept updated terms
 */
$pageTitle = 'Unable to Continue';
$hero_title = 'Account Access';
$hero_subtitle = 'Action required';
$hero_gradient = 'htb-hero-gradient-brand';
$hero_type = 'Legal';

require dirname(__DIR__) . '/../layouts/modern/header.php';

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'the platform';
?>

<div class="consent-page">
    <div class="consent-container">
        <!-- Error Banner -->
        <div class="consent-alert consent-alert--error">
            <div class="consent-alert__icon">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <div class="consent-alert__content">
                <h2 class="consent-alert__title">Unable to Continue</h2>
                <p>To use <?= htmlspecialchars($tenantName) ?>, you must accept our updated Terms of Service and Privacy Policy.</p>
            </div>
        </div>

        <!-- Options Card -->
        <div class="consent-card">
            <div class="consent-card__header">
                <h1 class="consent-card__title">Your Options</h1>
            </div>

            <div class="consent-card__body">
                <div class="decline-options">
                    <!-- Option 1: Accept -->
                    <div class="decline-option decline-option--recommended">
                        <div class="decline-option__icon">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div class="decline-option__content">
                            <h3>Accept the Updated Terms</h3>
                            <p>Return to the consent page and review the updated terms. This will allow you to continue using your account.</p>
                            <a href="<?= $basePath ?>/consent-required" class="htb-btn htb-btn-primary">
                                <i class="fa-solid fa-arrow-left"></i> Return to Consent Page
                            </a>
                        </div>
                    </div>

                    <!-- Option 2: Contact Support -->
                    <div class="decline-option">
                        <div class="decline-option__icon decline-option__icon--secondary">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <div class="decline-option__content">
                            <h3>Contact Support</h3>
                            <p>If you have questions about the updated terms or need clarification before accepting, our support team can help.</p>
                            <a href="<?= $basePath ?>/contact" class="htb-btn htb-btn-outline">
                                <i class="fa-solid fa-envelope"></i> Contact Support
                            </a>
                        </div>
                    </div>

                    <!-- Option 3: Delete Account -->
                    <div class="decline-option">
                        <div class="decline-option__icon decline-option__icon--danger">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                        <div class="decline-option__content">
                            <h3>Request Account Deletion</h3>
                            <p>If you no longer wish to use our services, you can request deletion of your account and personal data under GDPR.</p>
                            <a href="<?= $basePath ?>/settings" class="htb-btn htb-btn-outline htb-btn-danger">
                                <i class="fa-solid fa-trash-alt"></i> Go to Account Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="consent-info">
            <h3><i class="fa-solid fa-info-circle"></i> Why do I need to accept?</h3>
            <p>These legal documents explain your rights and responsibilities when using our platform, and how we handle your personal data. Accepting them is required to comply with data protection laws.</p>
            <p>Your account will remain in a restricted state until you accept the updated terms. You won't lose any data.</p>
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

/* Error Alert */
.consent-alert--error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-color: #ef4444;
}

[data-theme="dark"] .consent-alert--error {
    background: rgba(239, 68, 68, 0.15);
}

.consent-alert--error .consent-alert__icon {
    color: #dc2626;
}

/* Alert Banner (shared) */
.consent-alert {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    border-left: 4px solid;
}

.consent-alert__icon {
    font-size: 1.5rem;
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

/* Decline Options */
.decline-options {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.decline-option {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

[data-theme="dark"] .decline-option {
    border-color: #374151;
}

.decline-option--recommended {
    border-color: #a5b4fc;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
}

[data-theme="dark"] .decline-option--recommended {
    background: rgba(99, 102, 241, 0.1);
    border-color: #6366f1;
}

.decline-option__icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #4f46e5;
}

.decline-option__icon--secondary {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: #4b5563;
}

.decline-option__icon--danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
}

[data-theme="dark"] .decline-option__icon {
    background: rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] .decline-option__icon--secondary {
    background: #374151;
    color: #9ca3af;
}

[data-theme="dark"] .decline-option__icon--danger {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.decline-option__content h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #111827;
}

[data-theme="dark"] .decline-option__content h3 {
    color: #f3f4f6;
}

.decline-option__content p {
    font-size: 0.875rem;
    color: #4b5563;
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

[data-theme="dark"] .decline-option__content p {
    color: #9ca3af;
}

/* Buttons */
.htb-btn-outline {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #374151;
}

[data-theme="dark"] .htb-btn-outline {
    border-color: #4b5563;
    color: #d1d5db;
}

.htb-btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

[data-theme="dark"] .htb-btn-outline:hover {
    background: #374151;
}

.htb-btn-danger {
    border-color: #fca5a5;
    color: #b91c1c;
}

[data-theme="dark"] .htb-btn-danger {
    border-color: #ef4444;
    color: #f87171;
}

.htb-btn-danger:hover {
    background: #fef2f2;
}

[data-theme="dark"] .htb-btn-danger:hover {
    background: rgba(239, 68, 68, 0.1);
}

/* Info Section */
.consent-info {
    margin-top: 2rem;
    padding: 1.25rem;
    background: #f9fafb;
    border-radius: 12px;
    border-left: 4px solid #6366f1;
}

[data-theme="dark"] .consent-info {
    background: #1f2937;
}

.consent-info h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0 0 0.75rem 0;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

[data-theme="dark"] .consent-info h3 {
    color: #f3f4f6;
}

.consent-info h3 i {
    color: #6366f1;
}

.consent-info p {
    font-size: 0.875rem;
    color: #4b5563;
    margin: 0 0 0.5rem 0;
    line-height: 1.6;
}

.consent-info p:last-child {
    margin-bottom: 0;
}

[data-theme="dark"] .consent-info p {
    color: #9ca3af;
}

/* Responsive */
@media (max-width: 640px) {
    .decline-option {
        flex-direction: column;
        text-align: center;
    }

    .decline-option__icon {
        margin: 0 auto;
    }

    .decline-option__content .htb-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require dirname(__DIR__) . '/../layouts/modern/footer.php'; ?>
