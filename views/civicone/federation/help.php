<?php
/**
 * Federation Help & FAQ
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federation Help";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
$federationEnabled = $federationEnabled ?? false;
$userOptedIn = $userOptedIn ?? false;
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation" class="civic-fed-back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federation Hub
    </a>

    <!-- Page Header -->
    <header class="civic-fed-header">
        <h1>Federation Help & FAQ</h1>
    </header>

    <p class="civic-fed-intro">
        Learn about partner timebanks and how to connect with members from other communities.
    </p>

    <?php $currentPage = 'help'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

    <!-- Quick Links -->
    <nav class="civic-fed-quick-links" aria-label="Jump to section">
        <a href="#getting-started" class="civic-fed-quick-link">Getting Started</a>
        <a href="#privacy" class="civic-fed-quick-link">Privacy & Safety</a>
        <a href="#features" class="civic-fed-quick-link">Features</a>
        <a href="#troubleshooting" class="civic-fed-quick-link">Troubleshooting</a>
    </nav>

    <!-- Getting Started -->
    <section class="civic-fed-faq-section" id="getting-started" aria-labelledby="getting-started-heading">
        <h2 id="getting-started-heading" class="civic-fed-faq-heading">
            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
            Getting Started
        </h2>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-1">
                What is federation?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-1">
                <div class="civic-fed-faq-content">
                    Federation allows different timebanks to connect and share resources while maintaining their independence. Members from partner timebanks can browse each other's profiles, listings, events, and groups - and even exchange time credits across communities.
                </div>
            </div>
        </div>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-2">
                How do I enable federation for my account?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-2">
                <div class="civic-fed-faq-content">
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

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-3">
                What are partner timebanks?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-3">
                <div class="civic-fed-faq-content">
                    Partner timebanks are other timebanking communities that have established a formal partnership with your timebank. Administrators from both timebanks agree to share certain features (like member profiles, listings, or events) with each other's communities.
                </div>
            </div>
        </div>
    </section>

    <!-- Privacy & Safety -->
    <section class="civic-fed-faq-section" id="privacy" aria-labelledby="privacy-heading">
        <h2 id="privacy-heading" class="civic-fed-faq-heading">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            Privacy & Safety
        </h2>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-4">
                What information is shared with partner timebanks?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-4">
                <div class="civic-fed-faq-content">
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

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-5">
                Can I hide my profile from partner timebanks?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-5">
                <div class="civic-fed-faq-content">
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

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-6">
                How do I report inappropriate behavior?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-6">
                <div class="civic-fed-faq-content">
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
    </section>

    <!-- Features -->
    <section class="civic-fed-faq-section" id="features" aria-labelledby="features-heading">
        <h2 id="features-heading" class="civic-fed-faq-heading">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            Features
        </h2>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-7">
                Can I send time credits to members of other timebanks?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-7">
                <div class="civic-fed-faq-content">
                    Yes, if both timebanks have enabled federated transactions. Your time credits work the same way - 1 hour = 1 hour, regardless of which timebank the member belongs to. All federated transactions are logged for transparency.
                </div>
            </div>
        </div>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-8">
                How do I join a group from a partner timebank?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-8">
                <div class="civic-fed-faq-content">
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

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-9">
                Can I attend events from partner timebanks?
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-9">
                <div class="civic-fed-faq-content">
                    Yes! Browse <a href="<?= $basePath ?>/federation/events">Federated Events</a> to see upcoming events from partner timebanks. You can RSVP to events marked as "Open to Federation." Some events may be in-person at the partner timebank's location, while others may be virtual.
                </div>
            </div>
        </div>
    </section>

    <!-- Troubleshooting -->
    <section class="civic-fed-faq-section" id="troubleshooting" aria-labelledby="troubleshooting-heading">
        <h2 id="troubleshooting-heading" class="civic-fed-faq-heading">
            <i class="fa-solid fa-wrench" aria-hidden="true"></i>
            Troubleshooting
        </h2>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-10">
                I can't see any partner timebanks
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-10">
                <div class="civic-fed-faq-content">
                    This could be because:
                    <ul>
                        <li>Your timebank doesn't have any active partnerships yet</li>
                        <li>Federation may not be enabled for your timebank (contact your admin)</li>
                        <li>You may need to enable federation in your personal settings</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-11">
                A member from a partner timebank can't find me
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-11">
                <div class="civic-fed-faq-content">
                    Check your <a href="<?= $basePath ?>/settings?section=federation">Federation Settings</a> and make sure:
                    <ul>
                        <li>"Enable Federation" is turned ON</li>
                        <li>"Appear in Federated Search" is enabled</li>
                        <li>Your profile visibility is set appropriately</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="civic-fed-faq-item">
            <button class="civic-fed-faq-question" aria-expanded="false" aria-controls="faq-12">
                My transaction to a partner member failed
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="civic-fed-faq-answer" id="faq-12">
                <div class="civic-fed-faq-content">
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
    </section>

    <!-- Quick Access Cards -->
    <section class="civic-fed-section" aria-labelledby="quick-access-heading">
        <h2 id="quick-access-heading" class="civic-fed-section-title">
            <i class="fa-solid fa-link" aria-hidden="true"></i>
            Quick Links
        </h2>

        <div class="civic-fed-hub-grid civic-fed-hub-grid--small">
            <a href="<?= $basePath ?>/settings?section=federation" class="civic-fed-hub-card">
                <div class="civic-fed-hub-card-icon">
                    <i class="fa-solid fa-cog" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Federation Settings</h3>
                    <p>Manage your privacy preferences and federation options.</p>
                </div>
            </a>

            <a href="<?= $basePath ?>/federation" class="civic-fed-hub-card">
                <div class="civic-fed-hub-card-icon">
                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Partner Timebanks</h3>
                    <p>Browse all partner timebanks and their available features.</p>
                </div>
            </a>

            <a href="<?= $basePath ?>/federation/activity" class="civic-fed-hub-card">
                <div class="civic-fed-hub-card-icon">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                </div>
                <div class="civic-fed-hub-card-content">
                    <h3>Activity Feed</h3>
                    <p>View your recent federated messages, transactions, and updates.</p>
                </div>
            </a>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="civic-fed-card civic-fed-card--accent" aria-labelledby="contact-heading">
        <div class="civic-fed-card-body">
            <div class="civic-fed-contact">
                <h3 id="contact-heading">Still have questions?</h3>
                <p>Our team is here to help you get the most out of federation.</p>
                <a href="<?= $basePath ?>/help" class="civic-fed-btn civic-fed-btn--primary">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    Contact Support
                </a>
            </div>
        </div>
    </section>
</div>

<script>
// FAQ Accordion
document.querySelectorAll('.civic-fed-faq-question').forEach(function(button) {
    button.addEventListener('click', function() {
        const item = this.closest('.civic-fed-faq-item');
        const isOpen = item.classList.contains('civic-fed-faq-item--open');
        const answerId = this.getAttribute('aria-controls');

        // Close all other items
        document.querySelectorAll('.civic-fed-faq-item--open').forEach(function(openItem) {
            if (openItem !== item) {
                openItem.classList.remove('civic-fed-faq-item--open');
                openItem.querySelector('.civic-fed-faq-question').setAttribute('aria-expanded', 'false');
            }
        });

        // Toggle current item
        item.classList.toggle('civic-fed-faq-item--open');
        this.setAttribute('aria-expanded', !isOpen);
    });
});

// Smooth scroll for quick links
document.querySelectorAll('.civic-fed-quick-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                target.focus();
            }
        }
    });
});

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
