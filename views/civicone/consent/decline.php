<?php
/**
 * CivicOne Consent Decline Page
 * Shown when user chooses not to accept updated terms
 */
$pageTitle = 'Unable to Continue';
$hero_title = 'Account Access';
$hero_subtitle = 'Action required';

require dirname(__DIR__) . '/../layouts/civicone/header.php';
?>
<link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/civicone-consent-decline.css">
<?php

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'the platform';
?>

<div class="civic-container civic-container--narrow">
    <div class="consent-page">
        <!-- Warning Banner -->
        <div class="civic-notification civic-notification--error">
            <div class="civic-notification__icon">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <div class="civic-notification__content">
                <h2 class="civic-notification__title">Unable to Continue</h2>
                <p>To use <?= htmlspecialchars($tenantName) ?>, you must accept our updated Terms of Service and Privacy Policy.</p>
            </div>
        </div>

        <!-- Options Card -->
        <div class="civic-card">
            <div class="civic-card__header">
                <h1 class="civic-card__title">Your Options</h1>
            </div>

            <div class="civic-card__body">
                <div class="decline-options">
                    <!-- Option 1: Accept -->
                    <div class="decline-option decline-option--recommended">
                        <div class="decline-option__icon">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div class="decline-option__content">
                            <h3>Accept the Updated Terms</h3>
                            <p>Return to the consent page and review the updated terms. This will allow you to continue using your account.</p>
                            <a href="<?= $basePath ?>/consent-required" class="civic-btn civic-btn--primary">
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
                            <a href="<?= $basePath ?>/contact" class="civic-btn civic-btn--outline">
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
                            <a href="<?= $basePath ?>/settings" class="civic-btn civic-btn--outline civic-btn--danger">
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


<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
