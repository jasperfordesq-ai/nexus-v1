<?php
/**
 * Terms of Service - Modern Glassmorphism Design
 * Theme Color: Blue (#3b82f6)
 */
$pageTitle = 'Terms of Service';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
}
?>


<div id="terms-glass-wrapper" class="nexus-terms-glass-wrapper">
    <div class="terms-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="terms-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-file-contract"></i></span>
                Terms of Service
            </h1>
            <p>The rules and guidelines for using our platform</p>
            <span class="last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= date('F j, Y') ?>
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="terms-quick-nav">
            <a href="#time-credits" class="terms-nav-btn">
                <i class="fa-solid fa-clock"></i> Time Credits
            </a>
            <a href="#community" class="terms-nav-btn">
                <i class="fa-solid fa-users"></i> Community Rules
            </a>
            <a href="#prohibited" class="terms-nav-btn">
                <i class="fa-solid fa-ban"></i> Prohibited
            </a>
            <a href="#liability" class="terms-nav-btn">
                <i class="fa-solid fa-shield"></i> Liability
            </a>
        </div>

        <!-- Introduction -->
        <div class="terms-section highlight">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-handshake"></i></div>
                <h2>Welcome to <?= htmlspecialchars($tenantName) ?></h2>
            </div>
            <p>By accessing or using our platform, you agree to be bound by these Terms of Service. Please read them carefully before participating in our community.</p>
            <p>These terms establish a framework for <strong>fair, respectful, and meaningful exchanges</strong> between community members. Our goal is to create a trusted environment where everyone's time is valued equally.</p>
        </div>

        <!-- Time Credit System -->
        <div class="terms-section" id="time-credits">
            <div class="section-header">
                <div class="section-number">1</div>
                <h2>Time Credit System</h2>
            </div>
            <p>Our platform operates on a simple but powerful principle: <strong>everyone's time is equal</strong>.</p>

            <div class="time-credit-visual">
                <div class="credit-box">
                    <div class="credit-icon"><i class="fa-solid fa-clock"></i></div>
                    <span class="credit-label">1 Hour of Service</span>
                </div>
                <span class="equals-sign">=</span>
                <div class="credit-box">
                    <div class="credit-icon"><i class="fa-solid fa-gem"></i></div>
                    <span class="credit-label">1 Time Credit</span>
                </div>
            </div>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-info-circle"></i></span>
                <div class="notice-content">
                    <h4>Important</h4>
                    <p>Time Credits have no monetary value and cannot be exchanged for cash. They exist solely to facilitate community exchanges.</p>
                </div>
            </div>

            <ul>
                <li>One hour of service provided equals one Time Credit earned</li>
                <li>Credits can be used to receive services from other members</li>
                <li>The type of service does not affect the credit value</li>
                <li>Credits are tracked automatically through the platform</li>
            </ul>
        </div>

        <!-- Account Responsibilities -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">2</div>
                <h2>Account Responsibilities</h2>
            </div>
            <p>When you create an account, you agree to:</p>
            <ul>
                <li><strong>Provide accurate information:</strong> Your profile must reflect your true identity and skills</li>
                <li><strong>Maintain security:</strong> Keep your login credentials confidential and secure</li>
                <li><strong>Use one account:</strong> Each person may only maintain one active account</li>
                <li><strong>Stay current:</strong> Update your profile when your skills or availability change</li>
                <li><strong>Be reachable:</strong> Respond to messages and requests in a timely manner</li>
            </ul>
        </div>

        <!-- Community Guidelines -->
        <div class="terms-section highlight" id="community">
            <div class="section-header">
                <div class="section-number">3</div>
                <h2>Community Guidelines</h2>
            </div>
            <p>Our community is built on <strong>trust, respect, and mutual support</strong>. All members must:</p>
            <ol>
                <li><strong>Treat everyone with respect</strong> — Be kind and courteous in all interactions</li>
                <li><strong>Honor your commitments</strong> — If you agree to an exchange, follow through</li>
                <li><strong>Communicate clearly</strong> — Keep other members informed about your availability</li>
                <li><strong>Be inclusive</strong> — Welcome members of all backgrounds and abilities</li>
                <li><strong>Give honest feedback</strong> — Help the community by providing fair reviews</li>
            </ol>
        </div>

        <!-- Prohibited Activities -->
        <div class="terms-section" id="prohibited">
            <div class="section-header">
                <div class="section-number">4</div>
                <h2>Prohibited Activities</h2>
            </div>
            <p>The following activities are strictly prohibited and may result in account termination:</p>

            <div class="prohibited-grid">
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Harassment or discrimination</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Fraudulent exchanges</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Illegal services or activities</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Spam or solicitation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Impersonation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Sharing others' private info</span>
                </div>
            </div>
        </div>

        <!-- Safety -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">5</div>
                <h2>Safety & Meetings</h2>
            </div>
            <p>Your safety is important. We recommend following these guidelines:</p>
            <ul>
                <li><strong>First meetings:</strong> Meet in public places for initial exchanges</li>
                <li><strong>Verify identity:</strong> Confirm the member's profile before meeting</li>
                <li><strong>Trust your instincts:</strong> If something feels wrong, don't proceed</li>
                <li><strong>Report concerns:</strong> Let us know about any suspicious behavior</li>
                <li><strong>Keep records:</strong> Document exchanges through the platform</li>
            </ul>
        </div>

        <!-- Liability -->
        <div class="terms-section" id="liability">
            <div class="section-header">
                <div class="section-number">6</div>
                <h2>Limitation of Liability</h2>
            </div>
            <p><?= htmlspecialchars($tenantName) ?> provides a platform for community members to connect and exchange services. However:</p>
            <ul>
                <li>We do not guarantee the quality or safety of any services exchanged</li>
                <li>We are not responsible for disputes between members</li>
                <li>Members exchange services at their own risk</li>
                <li>We recommend obtaining appropriate insurance for professional services</li>
            </ul>
            <p>By using the platform, you agree to hold <?= htmlspecialchars($tenantName) ?> harmless from any claims arising from your participation.</p>
        </div>

        <!-- Termination -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">7</div>
                <h2>Account Termination</h2>
            </div>
            <p>We reserve the right to suspend or terminate accounts that violate these terms. Reasons for termination include:</p>
            <ul>
                <li>Repeated violation of community guidelines</li>
                <li>Fraudulent or deceptive behavior</li>
                <li>Harassment of other members</li>
                <li>Extended inactivity (over 12 months)</li>
                <li>Providing false information</li>
            </ul>
            <p>You may also close your account at any time through your account settings.</p>
        </div>

        <!-- Changes to Terms -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">8</div>
                <h2>Changes to These Terms</h2>
            </div>
            <p>We may update these terms from time to time to reflect changes in our practices or for legal reasons. When we make significant changes:</p>
            <ul>
                <li>We will notify you via email or platform notification</li>
                <li>The updated date will be shown at the top of this page</li>
                <li>Continued use of the platform constitutes acceptance of the new terms</li>
            </ul>
        </div>

        <!-- Contact CTA -->
        <div class="terms-cta">
            <h2><i class="fa-solid fa-question-circle"></i> Have Questions?</h2>
            <p>If you have any questions about these Terms of Service or need clarification on any points, our team is here to help.</p>
            <a href="<?= $basePath ?>/contact" class="terms-cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Us
            </a>
        </div>

    </div>
</div>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('#terms-glass-wrapper .terms-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Button press states
document.querySelectorAll('#terms-glass-wrapper .terms-nav-btn, #terms-glass-wrapper .terms-cta-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
