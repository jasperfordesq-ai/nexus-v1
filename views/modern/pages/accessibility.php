<?php
/**
 * Accessibility Statement - Modern Theme
 */
$pageTitle = 'Accessibility Statement';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>


<div id="accessibility-wrapper">
    <div class="access-inner">

        <div class="access-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-universal-access"></i></span>
                Accessibility Statement
            </h1>
            <p>Our Commitment to Inclusive Design</p>
        </div>

        <div class="access-section">
            <h2><i class="fa-solid fa-heart"></i> Our Commitment</h2>
            <p>We are committed to ensuring digital accessibility for people with disabilities. We are continually improving the user experience for everyone and applying the relevant accessibility standards.</p>
            <p><strong>Last Updated:</strong> <?= date('F Y') ?></p>
        </div>

        <div class="access-section">
            <h2><i class="fa-solid fa-check-double"></i> Conformance Status</h2>
            <p>We aim to conform to the <strong>Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA</strong>. These guidelines explain how to make web content more accessible for people with disabilities and more user-friendly for everyone.</p>
        </div>

        <div class="access-section">
            <h2><i class="fa-solid fa-list-check"></i> Accessibility Features</h2>

            <h3>Navigation & Structure</h3>
            <ul>
                <li><strong>Skip to main content</strong> link for keyboard users</li>
                <li><strong>Breadcrumb navigation</strong> on all major pages</li>
                <li>Clear and consistent navigation structure</li>
                <li>Semantic HTML headings in proper hierarchy</li>
                <li>ARIA landmarks for screen reader navigation</li>
            </ul>

            <h3>Visual Design</h3>
            <ul>
                <li><strong>Colour contrast</strong> meeting WCAG AA standards (minimum 4.5:1 for text)</li>
                <li><strong>Visible focus indicators</strong> on all interactive elements</li>
                <li>Resizable text up to 200% without loss of functionality</li>
                <li>Support for <strong>dark mode</strong> and high contrast preferences</li>
                <li>Reduced motion support via prefers-reduced-motion</li>
            </ul>

            <h3>Forms & Interactions</h3>
            <ul>
                <li><strong>Keyboard-accessible</strong> forms, buttons, and controls</li>
                <li>Clear form labels with required field indicators</li>
                <li>Descriptive error messages</li>
                <li>Accessible radio buttons and checkboxes</li>
            </ul>
        </div>

        <div class="access-section">
            <h2><i class="fa-solid fa-headphones"></i> Assistive Technologies</h2>
            <p>This website is designed to be compatible with:</p>
            <ul>
                <li>Screen readers (JAWS, NVDA, VoiceOver, TalkBack)</li>
                <li>Screen magnification software</li>
                <li>Speech recognition software</li>
                <li>Keyboard-only navigation</li>
                <li>Switch access devices</li>
            </ul>
        </div>

        <div class="access-section">
            <h2><i class="fa-solid fa-message"></i> Feedback & Support</h2>
            <p>We welcome your feedback on the accessibility of this platform. Please let us know if you encounter accessibility barriers:</p>
            <ul>
                <li>Use our <a href="<?= $basePath ?>/contact">Contact Form</a></li>
            </ul>
            <p>When contacting us, please include:</p>
            <ul>
                <li>The page URL where you encountered the issue</li>
                <li>A description of the accessibility barrier</li>
                <li>The assistive technology you use (if applicable)</li>
            </ul>
            <p><strong>We aim to respond to accessibility feedback within 5 business days.</strong></p>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
