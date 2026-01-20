<?php
/**
 * Consent Decline Warning Page
 * Shown when user chooses not to accept updated terms
 */
$pageTitle = $pageTitle ?? 'Unable to Continue';
$hero_title = 'Account Access';
$hero_subtitle = 'Action Required';
$hero_gradient = 'htb-hero-gradient-danger';
$hero_type = 'Legal';

require dirname(__DIR__) . '/../layouts/modern/header.php';

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
?>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/consent-required.css?v=<?= $cssVersionTimestamp ?? time() ?>">

<div class="consent-required-page">
    <div class="consent-container consent-decline">
        <div class="consent-header">
            <div class="consent-icon decline-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h1>Unable to Continue Without Consent</h1>
            <p class="consent-intro">
                To use this platform, you must accept our updated Terms of Service and Privacy Policy.
                These documents outline how we protect your data and the rules for using our services.
            </p>
        </div>

        <div class="decline-info">
            <h3>Your Options</h3>
            <div class="decline-options">
                <div class="decline-option">
                    <div class="option-icon">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="option-content">
                        <h4>Accept the Terms</h4>
                        <p>Return to the consent page and accept the updated terms to continue using your account.</p>
                        <a href="<?= $basePath ?>/consent-required" class="htb-btn htb-btn-primary">
                            <i class="fa-solid fa-arrow-left"></i> Return to Consent Page
                        </a>
                    </div>
                </div>

                <div class="decline-option">
                    <div class="option-icon warning">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                    <div class="option-content">
                        <h4>Request Account Deletion</h4>
                        <p>If you no longer wish to use our services, you can request deletion of your account and personal data.</p>
                        <a href="<?= $basePath ?>/settings" class="htb-btn htb-btn-outline">
                            <i class="fa-solid fa-cog"></i> Go to Settings
                        </a>
                    </div>
                </div>

                <div class="decline-option">
                    <div class="option-icon neutral">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="option-content">
                        <h4>Contact Support</h4>
                        <p>If you have questions about our terms or need clarification, our support team is here to help.</p>
                        <a href="mailto:support@hourtimebank.com" class="htb-btn htb-btn-outline">
                            <i class="fa-solid fa-envelope"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="consent-footer">
            <p>
                <i class="fa-solid fa-info-circle"></i>
                Your account will remain in a limited state until you accept the updated terms.
            </p>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/../layouts/modern/footer.php'; ?>
