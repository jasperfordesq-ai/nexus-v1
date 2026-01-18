<?php
// Federation Help & FAQ - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federation Help";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
$federationEnabled = $federationEnabled ?? false;
$userOptedIn = $userOptedIn ?? false;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-help-wrapper">

        <style>
            /* Offline Banner */
            .offline-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                padding: 12px 20px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.9rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transform: translateY(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #fed-help-wrapper {
                padding: 120px 20px 60px;
                max-width: 900px;
                margin: 0 auto;
            }

            @media (max-width: 768px) {
                #fed-help-wrapper {
                    padding: 100px 16px 100px;
                }
            }

            /* Back Link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted, #6b7280);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 24px;
                transition: color 0.2s ease;
                animation: fadeInUp 0.4s ease-out;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Page Header */
            .help-header {
                text-align: center;
                margin-bottom: 40px;
                animation: fadeInUp 0.4s ease-out 0.05s both;
            }

            .help-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #8b5cf6, #6366f1);
                border-radius: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 2rem;
                color: white;
                box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
            }

            .help-title {
                font-size: 2rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 12px;
            }

            [data-theme="dark"] .help-title {
                color: #f1f5f9;
            }

            .help-subtitle {
                font-size: 1.1rem;
                color: var(--htb-text-muted, #6b7280);
                max-width: 600px;
                margin: 0 auto;
            }

            /* Quick Links */
            .quick-links {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                justify-content: center;
                margin-bottom: 40px;
                animation: fadeInUp 0.4s ease-out 0.1s both;
            }

            .quick-link {
                padding: 10px 20px;
                background: rgba(139, 92, 246, 0.1);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 100px;
                color: #8b5cf6;
                text-decoration: none;
                font-size: 0.9rem;
                font-weight: 600;
                transition: all 0.2s ease;
            }

            .quick-link:hover {
                background: rgba(139, 92, 246, 0.2);
                transform: translateY(-2px);
            }

            [data-theme="dark"] .quick-link {
                background: rgba(139, 92, 246, 0.15);
                border-color: rgba(139, 92, 246, 0.3);
            }

            /* FAQ Section */
            .faq-section {
                margin-bottom: 40px;
                animation: fadeInUp 0.4s ease-out 0.15s both;
            }

            .faq-section-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            [data-theme="dark"] .faq-section-title {
                color: #f1f5f9;
            }

            .faq-section-title i {
                color: #8b5cf6;
            }

            /* FAQ Item */
            .faq-item {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.8),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 16px;
                margin-bottom: 12px;
                overflow: hidden;
                box-shadow: 0 4px 16px rgba(139, 92, 246, 0.08);
            }

            [data-theme="dark"] .faq-item {
                background: linear-gradient(135deg,
                        rgba(30, 41, 59, 0.8),
                        rgba(30, 41, 59, 0.6));
                border-color: rgba(255, 255, 255, 0.1);
            }

            .faq-question {
                width: 100%;
                padding: 20px 24px;
                background: none;
                border: none;
                text-align: left;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                font-size: 1rem;
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
                transition: background 0.2s ease;
            }

            [data-theme="dark"] .faq-question {
                color: #f1f5f9;
            }

            .faq-question:hover {
                background: rgba(139, 92, 246, 0.05);
            }

            .faq-question i {
                color: #8b5cf6;
                font-size: 0.9rem;
                transition: transform 0.3s ease;
                flex-shrink: 0;
            }

            .faq-item.open .faq-question i {
                transform: rotate(180deg);
            }

            .faq-answer {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease, padding 0.3s ease;
            }

            .faq-item.open .faq-answer {
                max-height: 500px;
            }

            .faq-answer-content {
                padding: 0 24px 20px;
                color: var(--htb-text-muted, #6b7280);
                font-size: 0.95rem;
                line-height: 1.7;
            }

            .faq-answer-content a {
                color: #8b5cf6;
                text-decoration: none;
                font-weight: 500;
            }

            .faq-answer-content a:hover {
                text-decoration: underline;
            }

            .faq-answer-content ul {
                margin: 12px 0;
                padding-left: 20px;
            }

            .faq-answer-content li {
                margin-bottom: 8px;
            }

            /* Help Cards */
            .help-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-top: 40px;
                animation: fadeInUp 0.4s ease-out 0.2s both;
            }

            .help-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.8),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                padding: 24px;
                text-decoration: none;
                transition: all 0.3s ease;
                box-shadow: 0 4px 16px rgba(139, 92, 246, 0.08);
            }

            [data-theme="dark"] .help-card {
                background: linear-gradient(135deg,
                        rgba(30, 41, 59, 0.8),
                        rgba(30, 41, 59, 0.6));
                border-color: rgba(255, 255, 255, 0.1);
            }

            .help-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 8px 24px rgba(139, 92, 246, 0.15);
            }

            .help-card-icon {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.1));
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 16px;
            }

            .help-card-icon i {
                font-size: 1.25rem;
                color: #8b5cf6;
            }

            .help-card-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 8px;
            }

            [data-theme="dark"] .help-card-title {
                color: #f1f5f9;
            }

            .help-card-desc {
                font-size: 0.9rem;
                color: var(--htb-text-muted, #6b7280);
                margin: 0;
                line-height: 1.5;
            }

            /* Contact Section */
            .contact-section {
                margin-top: 48px;
                padding: 32px;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.1),
                        rgba(168, 85, 247, 0.08));
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 20px;
                text-align: center;
                animation: fadeInUp 0.4s ease-out 0.25s both;
            }

            .contact-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 12px;
            }

            [data-theme="dark"] .contact-title {
                color: #f1f5f9;
            }

            .contact-desc {
                color: var(--htb-text-muted, #6b7280);
                margin: 0 0 20px;
                font-size: 0.95rem;
            }

            .contact-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
            }

            .contact-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            /* Focus styles */
            .faq-question:focus-visible,
            .quick-link:focus-visible,
            .help-card:focus-visible,
            .contact-btn:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Touch targets */
            .faq-question,
            .quick-link,
            .contact-btn {
                min-height: 44px;
            }
        </style>

        <!-- Hero Section -->
        <div class="help-header">
            <div class="help-icon">
                <i class="fa-solid fa-circle-question"></i>
            </div>
            <h1 class="help-title">Federation Help & FAQ</h1>
            <p class="help-subtitle">
                Learn about partner timebanks and how to connect with members from other communities.
            </p>
        </div>

        <?php $currentPage = 'help'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Quick Links -->
        <div class="quick-links">
            <a href="#getting-started" class="quick-link">Getting Started</a>
            <a href="#privacy" class="quick-link">Privacy & Safety</a>
            <a href="#features" class="quick-link">Features</a>
            <a href="#troubleshooting" class="quick-link">Troubleshooting</a>
        </div>

        <!-- Getting Started -->
        <div class="faq-section" id="getting-started">
            <h2 class="faq-section-title">
                <i class="fa-solid fa-rocket"></i>
                Getting Started
            </h2>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    What is federation?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Federation allows different timebanks to connect and share resources while maintaining their independence. Members from partner timebanks can browse each other's profiles, listings, events, and groups - and even exchange time credits across communities.
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    How do I enable federation for my account?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <ol>
                            <li>Go to <a href="<?= $basePath ?>/settings?section=federation">Settings &rarr; Federation</a></li>
                            <li>Toggle "Enable Federation" to ON</li>
                            <li>Choose your privacy level (Discovery, Social, or Economic)</li>
                            <li>Save your settings</li>
                        </ol>
                        Once enabled, you'll appear in partner timebank searches and can interact with their members.
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    What are partner timebanks?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Partner timebanks are other timebanking communities that have established a formal partnership with your timebank. Administrators from both timebanks agree to share certain features (like member profiles, listings, or events) with each other's communities.
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy & Safety -->
        <div class="faq-section" id="privacy">
            <h2 class="faq-section-title">
                <i class="fa-solid fa-shield-halved"></i>
                Privacy & Safety
            </h2>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    What information is shared with partner timebanks?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        You control what's shared through your privacy settings:
                        <ul>
                            <li><strong>Discovery Level:</strong> Name, avatar, and bio only</li>
                            <li><strong>Social Level:</strong> Plus skills, location (if enabled), and the ability to receive messages</li>
                            <li><strong>Economic Level:</strong> Plus the ability to receive/send time credit transactions</li>
                        </ul>
                        You can change these settings at any time in your <a href="<?= $basePath ?>/settings?section=federation">Federation Settings</a>.
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    Can I hide my profile from partner timebanks?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Yes! You have complete control. You can:
                        <ul>
                            <li>Disable federation entirely to be invisible to all partner timebanks</li>
                            <li>Hide specific information (location, skills, etc.)</li>
                            <li>Disable messaging or transactions from federated members</li>
                        </ul>
                        Your local timebank profile is not affected by these settings.
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    How do I report inappropriate behavior?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        If you encounter inappropriate behavior from a member of a partner timebank, you can:
                        <ul>
                            <li>Block the user from their profile page</li>
                            <li>Report the message or interaction using the report button</li>
                            <li>Contact your local timebank administrators</li>
                        </ul>
                        Reports are shared with both timebank's administrators for review.
                    </div>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="faq-section" id="features">
            <h2 class="faq-section-title">
                <i class="fa-solid fa-stars"></i>
                Features
            </h2>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    Can I send time credits to members of other timebanks?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Yes, if both timebanks have enabled federated transactions. Your time credits work the same way - 1 hour = 1 hour, regardless of which timebank the member belongs to. All federated transactions are logged for transparency.
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    How do I join a group from a partner timebank?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <ol>
                            <li>Browse <a href="<?= $basePath ?>/federation/groups">Federated Groups</a></li>
                            <li>Find a group you're interested in</li>
                            <li>Click "Join Group" or "Request to Join"</li>
                            <li>Some groups require admin approval</li>
                        </ol>
                        You'll receive a notification when you're accepted.
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    Can I attend events from partner timebanks?
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Yes! Browse <a href="<?= $basePath ?>/federation/events">Federated Events</a> to see upcoming events from partner timebanks. You can RSVP to events marked as "Open to Federation." Some events may be in-person at the partner timebank's location, while others may be virtual.
                    </div>
                </div>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="faq-section" id="troubleshooting">
            <h2 class="faq-section-title">
                <i class="fa-solid fa-wrench"></i>
                Troubleshooting
            </h2>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    I can't see any partner timebanks
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        This could be because:
                        <ul>
                            <li>Your timebank doesn't have any active partnerships yet</li>
                            <li>Federation may not be enabled for your timebank (contact your admin)</li>
                            <li>You may need to enable federation in your personal settings</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    A member from a partner timebank can't find me
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Check your <a href="<?= $basePath ?>/settings?section=federation">Federation Settings</a> and make sure:
                        <ul>
                            <li>"Enable Federation" is turned ON</li>
                            <li>"Appear in Federated Search" is enabled</li>
                            <li>Your profile visibility is set appropriately</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" aria-expanded="false">
                    My transaction to a partner member failed
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        Transaction failures can occur if:
                        <ul>
                            <li>You don't have enough time credits</li>
                            <li>The recipient has disabled federated transactions</li>
                            <li>The partnership between timebanks has been suspended</li>
                            <li>There's a temporary network issue</li>
                        </ul>
                        If the problem persists, contact your timebank administrator.
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access Cards -->
        <div class="help-cards">
            <a href="<?= $basePath ?>/settings?section=federation" class="help-card">
                <div class="help-card-icon">
                    <i class="fa-solid fa-cog"></i>
                </div>
                <h3 class="help-card-title">Federation Settings</h3>
                <p class="help-card-desc">Manage your privacy preferences and federation options.</p>
            </a>

            <a href="<?= $basePath ?>/federation" class="help-card">
                <div class="help-card-icon">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <h3 class="help-card-title">Partner Timebanks</h3>
                <p class="help-card-desc">Browse all partner timebanks and their available features.</p>
            </a>

            <a href="<?= $basePath ?>/federation/activity" class="help-card">
                <div class="help-card-icon">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <h3 class="help-card-title">Activity Feed</h3>
                <p class="help-card-desc">View your recent federated messages, transactions, and updates.</p>
            </a>
        </div>

        <!-- Contact Section -->
        <div class="contact-section">
            <h3 class="contact-title">Still have questions?</h3>
            <p class="contact-desc">Our team is here to help you get the most out of federation.</p>
            <a href="<?= $basePath ?>/help" class="contact-btn">
                <i class="fa-solid fa-headset"></i>
                Contact Support
            </a>
        </div>

    </div>
</div>

<script>
// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(function(button) {
    button.addEventListener('click', function() {
        const item = this.closest('.faq-item');
        const isOpen = item.classList.contains('open');

        // Close all other items
        document.querySelectorAll('.faq-item.open').forEach(function(openItem) {
            if (openItem !== item) {
                openItem.classList.remove('open');
                openItem.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
            }
        });

        // Toggle current item
        item.classList.toggle('open');
        this.setAttribute('aria-expanded', !isOpen);
    });
});

// Smooth scroll for quick links
document.querySelectorAll('.quick-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
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

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
