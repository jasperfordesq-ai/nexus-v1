<?php
// Phoenix View: Accessibility Statement
$hTitle = 'Accessibility Statement';
$hSubtitle = 'Our Commitment to Inclusive Design';
$hGradient = 'civic-hero-gradient-hub';
$hType = 'Legal';

require __DIR__ . '/../..' . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-container" style="margin-top: -80px; position: relative; z-index: 20; display: block; max-width: 900px; margin-left: auto; margin-right: auto;">
    <div class="civic-card">
        <div class="civic-card-body" style="padding: 40px; max-width: 800px; margin: 0 auto; line-height: 1.6;">
            <p><strong>Last Updated:</strong> December 2024</p>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">1. Our Commitment</h2>
            <p>Project NEXUS is committed to ensuring digital accessibility for people with disabilities. We are continually improving the user experience for everyone and applying the relevant accessibility standards.</p>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">2. Conformance Status</h2>
            <p>We aim to conform to the <strong>Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA</strong>. These guidelines explain how to make web content more accessible for people with disabilities and more user-friendly for everyone.</p>
            <p>Our accessibility audit was completed in December 2024, and we continue to make improvements.</p>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">3. Accessibility Features</h2>
            <p>Our platform includes the following accessibility features:</p>

            <h3 style="margin-top: 20px;">Navigation & Structure</h3>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li><strong>Skip to main content</strong> link for keyboard users</li>
                <li><strong>Breadcrumb navigation</strong> on all major pages</li>
                <li>Clear and consistent navigation structure</li>
                <li>Semantic HTML headings (h1, h2, h3) in proper hierarchy</li>
                <li>ARIA landmarks for screen reader navigation</li>
            </ul>

            <h3 style="margin-top: 20px;">Visual Design</h3>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li><strong>Colour contrast</strong> meeting WCAG AA standards (minimum 4.5:1 for text)</li>
                <li><strong>Visible focus indicators</strong> on all interactive elements</li>
                <li>Resizable text up to 200% without loss of functionality</li>
                <li>Support for <strong>dark mode</strong> and high contrast preferences</li>
                <li>Reduced motion support via <code>prefers-reduced-motion</code></li>
            </ul>

            <h3 style="margin-top: 20px;">Forms & Interactions</h3>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li><strong>Keyboard-accessible</strong> forms, buttons, and controls</li>
                <li>Clear form labels with required field indicators</li>
                <li>Descriptive error messages with <code>aria-describedby</code></li>
                <li>Autocomplete attributes for faster form completion</li>
                <li>Accessible radio buttons and checkboxes</li>
            </ul>

            <h3 style="margin-top: 20px;">Content & Links</h3>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>Alternative text for all meaningful images</li>
                <li><strong>Descriptive link text</strong> (avoiding generic "click here")</li>
                <li>Accessible SVG icons with proper ARIA labels</li>
                <li>Data tables with <code>scope</code> attributes and captions</li>
            </ul>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">4. Assistive Technologies</h2>
            <p>This website is designed to be compatible with the following assistive technologies:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>Screen readers (JAWS, NVDA, VoiceOver, TalkBack)</li>
                <li>Screen magnification software (ZoomText, Windows Magnifier)</li>
                <li>Speech recognition software (Dragon NaturallySpeaking)</li>
                <li>Keyboard-only navigation</li>
                <li>Switch access devices</li>
            </ul>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">5. Browser Support</h2>
            <p>For the best accessibility experience, we recommend using the latest versions of:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>Google Chrome</li>
                <li>Mozilla Firefox</li>
                <li>Apple Safari</li>
                <li>Microsoft Edge</li>
            </ul>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">6. Known Limitations</h2>
            <p>While we strive for full accessibility, some content may have limitations:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>Some older user-uploaded images may lack alt text</li>
                <li>Embedded third-party content (maps, videos) may not be fully accessible</li>
                <li>PDF documents may have varying accessibility levels</li>
            </ul>
            <p>We are actively working to identify and resolve accessibility issues. If you encounter any barriers, please contact us.</p>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">7. Feedback & Support</h2>
            <p>We welcome your feedback on the accessibility of Project NEXUS. Please let us know if you encounter accessibility barriers:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>Email: <a href="mailto:accessibility@projectnexus.org">accessibility@projectnexus.org</a></li>
                <li>Use our <a href="<?= $basePath ?>/contact">Contact Form</a></li>
            </ul>
            <p>When contacting us, please include:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li>The page URL where you encountered the issue</li>
                <li>A description of the accessibility barrier</li>
                <li>The assistive technology you use (if applicable)</li>
            </ul>
            <p><strong>We aim to respond to accessibility feedback within 5 business days.</strong></p>

            <h2 style="color: var(--civic-brand, #00796B); margin-top: 30px;">8. Enforcement Procedure</h2>
            <p>If you are not satisfied with our response to your accessibility concern, you may escalate the matter through our formal complaints procedure or contact:</p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <li><strong>Ireland:</strong> Irish Human Rights and Equality Commission (IHREC)</li>
                <li><strong>UK:</strong> Equality Advisory Support Service (EASS)</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../..' . '/layouts/civicone/footer.php'; ?>
