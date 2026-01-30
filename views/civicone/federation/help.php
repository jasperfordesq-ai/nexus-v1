<?php
/**
 * Federation Help & FAQ
 * CivicOne Theme - GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federation Help";
$hideHero = true;
$bodyClass = 'civicone--federation';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
$federationEnabled = $federationEnabled ?? false;
$userOptedIn = $userOptedIn ?? false;
?>

<!-- Offline Banner -->
<div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-margin-bottom-4 hidden" id="offlineBanner" role="alert" aria-live="polite">
    <div class="govuk-notification-banner__content">
        <p class="govuk-body">
            <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
            No internet connection
        </p>
    </div>
</div>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Federation', 'href' => $basePath . '/federation'],
        ['text' => 'Help & FAQ']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
            <i class="fa-solid fa-circle-question govuk-!-margin-right-2" aria-hidden="true"></i>
            Federation Help & FAQ
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            Learn about partner timebanks and how to connect with members from other communities.
        </p>
    </div>
</div>

<?php $currentPage = 'help'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

<!-- Quick Links -->
<nav class="govuk-!-margin-bottom-6" aria-label="Jump to section">
    <ul class="govuk-list civicone-quick-links-row">
        <li><a href="#getting-started" class="govuk-link govuk-!-padding-2 civicone-panel-bg civicone-quick-link-item">Getting Started</a></li>
        <li><a href="#privacy" class="govuk-link govuk-!-padding-2 civicone-panel-bg civicone-quick-link-item">Privacy & Safety</a></li>
        <li><a href="#features" class="govuk-link govuk-!-padding-2 civicone-panel-bg civicone-quick-link-item">Features</a></li>
        <li><a href="#troubleshooting" class="govuk-link govuk-!-padding-2 civicone-panel-bg civicone-quick-link-item">Troubleshooting</a></li>
    </ul>
</nav>

    <!-- Getting Started -->
    <section class="govuk-!-margin-bottom-8" id="getting-started" aria-labelledby="getting-started-heading">
        <h2 id="getting-started-heading" class="govuk-heading-l">
            <i class="fa-solid fa-rocket govuk-!-margin-right-2" aria-hidden="true"></i>
            Getting Started
        </h2>

        <div class="govuk-accordion" data-module="govuk-accordion" id="accordion-getting-started">
            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-1">
                            What is federation?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-1" class="govuk-accordion__section-content">
                    <p class="govuk-body">
                        Federation allows different timebanks to connect and share resources while maintaining their independence. Members from partner timebanks can browse each other's profiles, listings, events, and groups - and even exchange time credits across communities.
                    </p>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-2">
                            How do I enable federation for my account?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-2" class="govuk-accordion__section-content">
                    <ol class="govuk-list govuk-list--number">
                        <li>Go to <a href="<?= $basePath ?>/settings?section=federation" class="govuk-link">Settings &rarr; Federation</a></li>
                        <li>Toggle "Enable Federation" to ON</li>
                        <li>Choose your privacy level (Discovery, Social, or Economic)</li>
                        <li>Save your settings</li>
                    </ol>
                    <p class="govuk-body">Once enabled, you'll appear in partner timebank searches and can interact with their members.</p>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-3">
                            What are partner timebanks?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-3" class="govuk-accordion__section-content">
                    <p class="govuk-body">
                        Partner timebanks are other timebanking communities that have established a formal partnership with your timebank. Administrators from both timebanks agree to share certain features (like member profiles, listings, or events) with each other's communities.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Privacy & Safety -->
    <section class="govuk-!-margin-bottom-8" id="privacy" aria-labelledby="privacy-heading">
        <h2 id="privacy-heading" class="govuk-heading-l">
            <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" aria-hidden="true"></i>
            Privacy & Safety
        </h2>

        <div class="govuk-accordion" data-module="govuk-accordion" id="accordion-privacy">
            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-4">
                            What information is shared with partner timebanks?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-4" class="govuk-accordion__section-content">
                    <p class="govuk-body">You control what's shared through your privacy settings:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li><strong>Discovery Level:</strong> Name, avatar, and bio only</li>
                        <li><strong>Social Level:</strong> Plus skills, location (if enabled), and the ability to receive messages</li>
                        <li><strong>Economic Level:</strong> Plus the ability to receive/send time credit transactions</li>
                    </ul>
                    <p class="govuk-body">You can change these settings at any time in your <a href="<?= $basePath ?>/settings?section=federation" class="govuk-link">Federation Settings</a>.</p>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-5">
                            Can I hide my profile from partner timebanks?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-5" class="govuk-accordion__section-content">
                    <p class="govuk-body">Yes! You have complete control. You can:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>Disable federation entirely to be invisible to all partner timebanks</li>
                        <li>Hide specific information (location, skills, etc.)</li>
                        <li>Disable messaging or transactions from federated members</li>
                    </ul>
                    <p class="govuk-body">Your local timebank profile is not affected by these settings.</p>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-6">
                            How do I report inappropriate behavior?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-6" class="govuk-accordion__section-content">
                    <p class="govuk-body">If you encounter inappropriate behavior from a member of a partner timebank, you can:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>Block the user from their profile page</li>
                        <li>Report the message or interaction using the report button</li>
                        <li>Contact your local timebank administrators</li>
                    </ul>
                    <p class="govuk-body">Reports are shared with both timebank's administrators for review.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="govuk-!-margin-bottom-8" id="features" aria-labelledby="features-heading">
        <h2 id="features-heading" class="govuk-heading-l">
            <i class="fa-solid fa-star govuk-!-margin-right-2" aria-hidden="true"></i>
            Features
        </h2>

        <div class="govuk-accordion" data-module="govuk-accordion" id="accordion-features">
            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-7">
                            Can I send time credits to members of other timebanks?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-7" class="govuk-accordion__section-content">
                    <p class="govuk-body">
                        Yes, if both timebanks have enabled federated transactions. Your time credits work the same way - 1 hour = 1 hour, regardless of which timebank the member belongs to. All federated transactions are logged for transparency.
                    </p>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-8">
                            How do I join a group from a partner timebank?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-8" class="govuk-accordion__section-content">
                    <ol class="govuk-list govuk-list--number">
                        <li>Browse <a href="<?= $basePath ?>/federation/groups" class="govuk-link">Federated Groups</a></li>
                        <li>Find a group you're interested in</li>
                        <li>Click "Join Group" or "Request to Join"</li>
                        <li>Some groups require admin approval</li>
                    </ol>
                    <p class="govuk-body">You'll receive a notification when you're accepted.</p>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-9">
                            Can I attend events from partner timebanks?
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-9" class="govuk-accordion__section-content">
                    <p class="govuk-body">
                        Yes! Browse <a href="<?= $basePath ?>/federation/events" class="govuk-link">Federated Events</a> to see upcoming events from partner timebanks. You can RSVP to events marked as "Open to Federation." Some events may be in-person at the partner timebank's location, while others may be virtual.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Troubleshooting -->
    <section class="govuk-!-margin-bottom-8" id="troubleshooting" aria-labelledby="troubleshooting-heading">
        <h2 id="troubleshooting-heading" class="govuk-heading-l">
            <i class="fa-solid fa-wrench govuk-!-margin-right-2" aria-hidden="true"></i>
            Troubleshooting
        </h2>

        <div class="govuk-accordion" data-module="govuk-accordion" id="accordion-troubleshooting">
            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-10">
                            I can't see any partner timebanks
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-10" class="govuk-accordion__section-content">
                    <p class="govuk-body">This could be because:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>Your timebank doesn't have any active partnerships yet</li>
                        <li>Federation may not be enabled for your timebank (contact your admin)</li>
                        <li>You may need to enable federation in your personal settings</li>
                    </ul>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-11">
                            A member from a partner timebank can't find me
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-11" class="govuk-accordion__section-content">
                    <p class="govuk-body">Check your <a href="<?= $basePath ?>/settings?section=federation" class="govuk-link">Federation Settings</a> and make sure:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>"Enable Federation" is turned ON</li>
                        <li>"Appear in Federated Search" is enabled</li>
                        <li>Your profile visibility is set appropriately</li>
                    </ul>
                </div>
            </div>

            <div class="govuk-accordion__section">
                <div class="govuk-accordion__section-header">
                    <h3 class="govuk-accordion__section-heading">
                        <span class="govuk-accordion__section-button" id="accordion-heading-12">
                            My transaction to a partner member failed
                        </span>
                    </h3>
                </div>
                <div id="accordion-content-12" class="govuk-accordion__section-content">
                    <p class="govuk-body">Transaction failures can occur if:</p>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>You don't have enough time credits</li>
                        <li>The recipient has disabled federated transactions</li>
                        <li>The partnership between timebanks has been suspended</li>
                        <li>There's a temporary network issue</li>
                    </ul>
                    <p class="govuk-body">If the problem persists, contact your timebank administrator.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Access Cards -->
    <section class="govuk-!-margin-bottom-8" aria-labelledby="quick-access-heading">
        <h2 id="quick-access-heading" class="govuk-heading-l">
            <i class="fa-solid fa-link govuk-!-margin-right-2" aria-hidden="true"></i>
            Quick Links
        </h2>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/settings?section=federation" class="govuk-link civicone-link-card">
                    <div class="govuk-!-padding-4 civicone-report-section civicone-full-height">
                        <p class="govuk-body govuk-!-margin-bottom-2">
                            <i class="fa-solid fa-cog fa-2x civicone-icon-blue" aria-hidden="true"></i>
                        </p>
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">Federation Settings</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Manage your privacy preferences and federation options.</p>
                    </div>
                </a>
            </div>

            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/federation" class="govuk-link civicone-link-card">
                    <div class="govuk-!-padding-4 civicone-report-section civicone-full-height">
                        <p class="govuk-body govuk-!-margin-bottom-2">
                            <i class="fa-solid fa-globe fa-2x civicone-icon-blue" aria-hidden="true"></i>
                        </p>
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">Partner Timebanks</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Browse all partner timebanks and their available features.</p>
                    </div>
                </a>
            </div>

            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/federation/activity" class="govuk-link civicone-link-card">
                    <div class="govuk-!-padding-4 civicone-report-section civicone-full-height">
                        <p class="govuk-body govuk-!-margin-bottom-2">
                            <i class="fa-solid fa-bell fa-2x civicone-icon-blue" aria-hidden="true"></i>
                        </p>
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">Activity Feed</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">View your recent federated messages, transactions, and updates.</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <div class="govuk-inset-text govuk-!-margin-bottom-6 civicone-inset-blue">
        <h3 class="govuk-heading-s govuk-!-margin-bottom-2" id="contact-heading">Still have questions?</h3>
        <p class="govuk-body govuk-!-margin-bottom-4">Our team is here to help you get the most out of federation.</p>
        <a href="<?= $basePath ?>/help" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-headset govuk-!-margin-right-1" aria-hidden="true"></i>
            Contact Support
        </a>
    </div>
</div>

<!-- Smooth scroll handled by civicone-common.js initSmoothScroll() -->
<!-- Offline indicator handled by civicone-common.js initOfflineBanner() -->

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
