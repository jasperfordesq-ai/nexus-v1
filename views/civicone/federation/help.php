<?php
// Federation Help & FAQ - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federation Help";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
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

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
