<?php
/**
 * Accessibility Statement - Modern Theme
 */
$pageTitle = 'Accessibility Statement';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>

<style>
#accessibility-wrapper {
    --access-theme: #7c3aed;
    --access-theme-rgb: 124, 58, 237;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #accessibility-wrapper {
        padding-top: 120px;
    }
}

#accessibility-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

[data-theme="light"] #accessibility-wrapper::before {
    background: linear-gradient(135deg,
        rgba(124, 58, 237, 0.08) 0%,
        rgba(139, 92, 246, 0.08) 50%,
        rgba(124, 58, 237, 0.08) 100%);
}

[data-theme="dark"] #accessibility-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(124, 58, 237, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
}

#accessibility-wrapper .access-inner {
    max-width: 900px;
    margin: 0 auto;
}

#accessibility-wrapper .access-header {
    text-align: center;
    margin-bottom: 2rem;
}

#accessibility-wrapper .access-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#accessibility-wrapper .access-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--access-theme) 0%, #5b21b6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(124, 58, 237, 0.4);
}

#accessibility-wrapper .access-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
}

#accessibility-wrapper .access-section {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 1.5rem;
}

[data-theme="light"] #accessibility-wrapper .access-section {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(124, 58, 237, 0.15);
    box-shadow: 0 8px 32px rgba(124, 58, 237, 0.1);
}

[data-theme="dark"] #accessibility-wrapper .access-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(124, 58, 237, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#accessibility-wrapper .access-section h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#accessibility-wrapper .access-section h2 i {
    color: var(--access-theme);
}

#accessibility-wrapper .access-section h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--htb-text-main);
    margin: 1.5rem 0 0.75rem 0;
}

#accessibility-wrapper .access-section p {
    color: var(--htb-text-muted);
    line-height: 1.7;
    margin: 0 0 1rem 0;
}

#accessibility-wrapper .access-section ul {
    margin: 1rem 0;
    padding-left: 0;
    list-style: none;
}

#accessibility-wrapper .access-section ul li {
    position: relative;
    padding-left: 1.75rem;
    margin-bottom: 0.75rem;
    color: var(--htb-text-muted);
    line-height: 1.6;
}

#accessibility-wrapper .access-section ul li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--access-theme);
}

#accessibility-wrapper .access-section a {
    color: var(--access-theme);
    text-decoration: none;
}

#accessibility-wrapper .access-section a:hover {
    text-decoration: underline;
}

#accessibility-wrapper .access-section strong {
    color: var(--htb-text-main);
}

@media (max-width: 768px) {
    #accessibility-wrapper .access-header h1 {
        font-size: 2rem;
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

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
