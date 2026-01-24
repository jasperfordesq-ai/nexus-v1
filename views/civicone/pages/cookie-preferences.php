<?php
/**
 * Cookie Preferences Page - CivicOne Theme (GOV.UK Design System)
 * WCAG 2.1 AA Compliant
 * Standalone page for managing cookie consent preferences
 */

use Nexus\Core\TenantContext;

$pageTitle = $pageTitle ?? 'Cookie Preferences';
$basePath = TenantContext::getBasePath();

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <a href="<?= htmlspecialchars($basePath) ?>/legal" class="govuk-back-link">Back to Legal Hub</a>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <!-- Page Header -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Cookie Preferences</h1>
                <p class="govuk-body-l">
                    Manage how we use cookies and similar technologies on <?= htmlspecialchars($tenantName) ?>.
                </p>
            </div>
        </div>

        <!-- Current Settings Status -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-full">
                <div class="govuk-notification-banner" role="region" aria-labelledby="current-settings-title" id="consent-status-banner">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="current-settings-title">
                            Current settings
                        </h2>
                    </div>
                    <div class="govuk-notification-banner__content" id="consent-status-content">
                        <p class="govuk-notification-banner__heading">
                            Loading your cookie preferences...
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cookie Categories -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <!-- Form Start -->
                <form id="cookie-preferences-form" novalidate>

                    <!-- Essential Cookies -->
                    <div class="govuk-form-group cookie-category-section">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    Essential cookies
                                </h2>
                            </legend>

                            <div class="govuk-inset-text">
                                These cookies are necessary for the service to function and cannot be switched off.
                                They are usually only set in response to actions you make, such as logging in or filling in forms.
                            </div>

                            <div class="govuk-radios govuk-radios--inline govuk-radios--small">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="essential-on" name="essential" type="radio" value="true" checked disabled>
                                    <label class="govuk-label govuk-radios__label" for="essential-on">
                                        On
                                    </label>
                                </div>
                            </div>

                            <details class="govuk-details" data-module="govuk-details">
                                <summary class="govuk-details__summary">
                                    <span class="govuk-details__summary-text">
                                        What do essential cookies do?
                                    </span>
                                </summary>
                                <div class="govuk-details__text">
                                    <p class="govuk-body">Essential cookies:</p>
                                    <ul class="govuk-list govuk-list--bullet">
                                        <li>keep you logged in securely</li>
                                        <li>remember your security preferences</li>
                                        <li>protect against fraudulent activity</li>
                                        <li>enable core site functionality</li>
                                    </ul>

                                    <?php if (!empty($cookies['essential'])): ?>
                                    <details class="govuk-details" data-module="govuk-details">
                                        <summary class="govuk-details__summary">
                                            <span class="govuk-details__summary-text">
                                                View essential cookies (<?= count($cookies['essential']) ?>)
                                            </span>
                                        </summary>
                                        <div class="govuk-details__text">
                                            <table class="govuk-table govuk-!-font-size-16">
                                                <thead class="govuk-table__head">
                                                    <tr class="govuk-table__row">
                                                        <th scope="col" class="govuk-table__header">Name</th>
                                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                                        <th scope="col" class="govuk-table__header">Expires</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="govuk-table__body">
                                                    <?php foreach ($cookies['essential'] as $cookie): ?>
                                                    <tr class="govuk-table__row">
                                                        <th scope="row" class="govuk-table__header"><code><?= htmlspecialchars($cookie['cookie_name']) ?></code></th>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </fieldset>
                    </div>

                    <!-- Functional Cookies -->
                    <div class="govuk-form-group cookie-category-section">
                        <fieldset class="govuk-fieldset" aria-describedby="functional-hint">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    Functional cookies
                                </h2>
                            </legend>

                            <div id="functional-hint" class="govuk-hint">
                                These cookies enable enhanced functionality and personalization. If you do not allow these cookies,
                                some or all of these features may not work properly.
                            </div>

                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="functional-on" name="functional" type="radio" value="true">
                                    <label class="govuk-label govuk-radios__label" for="functional-on">
                                        On
                                    </label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="functional-off" name="functional" type="radio" value="false" checked>
                                    <label class="govuk-label govuk-radios__label" for="functional-off">
                                        Off
                                    </label>
                                </div>
                            </div>

                            <details class="govuk-details" data-module="govuk-details">
                                <summary class="govuk-details__summary">
                                    <span class="govuk-details__summary-text">
                                        What do functional cookies do?
                                    </span>
                                </summary>
                                <div class="govuk-details__text">
                                    <p class="govuk-body">Functional cookies:</p>
                                    <ul class="govuk-list govuk-list--bullet">
                                        <li>remember your display preferences (theme, layout)</li>
                                        <li>save your location or region</li>
                                        <li>personalize content based on your interests</li>
                                        <li>enable social media features</li>
                                    </ul>

                                    <?php if (!empty($cookies['functional'])): ?>
                                    <details class="govuk-details" data-module="govuk-details">
                                        <summary class="govuk-details__summary">
                                            <span class="govuk-details__summary-text">
                                                View functional cookies (<?= count($cookies['functional']) ?>)
                                            </span>
                                        </summary>
                                        <div class="govuk-details__text">
                                            <table class="govuk-table govuk-!-font-size-16">
                                                <thead class="govuk-table__head">
                                                    <tr class="govuk-table__row">
                                                        <th scope="col" class="govuk-table__header">Name</th>
                                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                                        <th scope="col" class="govuk-table__header">Expires</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="govuk-table__body">
                                                    <?php foreach ($cookies['functional'] as $cookie): ?>
                                                    <tr class="govuk-table__row">
                                                        <th scope="row" class="govuk-table__header"><code><?= htmlspecialchars($cookie['cookie_name']) ?></code></th>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </fieldset>
                    </div>

                    <!-- Analytics Cookies -->
                    <div class="govuk-form-group cookie-category-section">
                        <fieldset class="govuk-fieldset" aria-describedby="analytics-hint">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    Analytics cookies
                                </h2>
                            </legend>

                            <div id="analytics-hint" class="govuk-hint">
                                These cookies help us understand how visitors interact with our website by collecting
                                and reporting information anonymously.
                            </div>

                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="analytics-on" name="analytics" type="radio" value="true">
                                    <label class="govuk-label govuk-radios__label" for="analytics-on">
                                        On
                                    </label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="analytics-off" name="analytics" type="radio" value="false" checked>
                                    <label class="govuk-label govuk-radios__label" for="analytics-off">
                                        Off
                                    </label>
                                </div>
                            </div>

                            <details class="govuk-details" data-module="govuk-details">
                                <summary class="govuk-details__summary">
                                    <span class="govuk-details__summary-text">
                                        What do analytics cookies do?
                                    </span>
                                </summary>
                                <div class="govuk-details__text">
                                    <p class="govuk-body">Analytics cookies:</p>
                                    <ul class="govuk-list govuk-list--bullet">
                                        <li>count visits and measure traffic sources</li>
                                        <li>understand which pages are most popular</li>
                                        <li>see how visitors move around the site</li>
                                        <li>help us improve site performance</li>
                                    </ul>

                                    <?php if (!empty($cookies['analytics'])): ?>
                                    <details class="govuk-details" data-module="govuk-details">
                                        <summary class="govuk-details__summary">
                                            <span class="govuk-details__summary-text">
                                                View analytics cookies (<?= count($cookies['analytics']) ?>)
                                            </span>
                                        </summary>
                                        <div class="govuk-details__text">
                                            <table class="govuk-table govuk-!-font-size-16">
                                                <thead class="govuk-table__head">
                                                    <tr class="govuk-table__row">
                                                        <th scope="col" class="govuk-table__header">Name</th>
                                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                                        <th scope="col" class="govuk-table__header">Expires</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="govuk-table__body">
                                                    <?php foreach ($cookies['analytics'] as $cookie): ?>
                                                    <tr class="govuk-table__row">
                                                        <th scope="row" class="govuk-table__header"><code><?= htmlspecialchars($cookie['cookie_name']) ?></code></th>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </fieldset>
                    </div>

                    <!-- Marketing Cookies -->
                    <div class="govuk-form-group cookie-category-section">
                        <fieldset class="govuk-fieldset" aria-describedby="marketing-hint">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    Marketing cookies
                                </h2>
                            </legend>

                            <div id="marketing-hint" class="govuk-hint">
                                These cookies may be set by our advertising partners to build a profile of your interests
                                and show you relevant content on other sites.
                            </div>

                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="marketing-on" name="marketing" type="radio" value="true">
                                    <label class="govuk-label govuk-radios__label" for="marketing-on">
                                        On
                                    </label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="marketing-off" name="marketing" type="radio" value="false" checked>
                                    <label class="govuk-label govuk-radios__label" for="marketing-off">
                                        Off
                                    </label>
                                </div>
                            </div>

                            <details class="govuk-details" data-module="govuk-details">
                                <summary class="govuk-details__summary">
                                    <span class="govuk-details__summary-text">
                                        What do marketing cookies do?
                                    </span>
                                </summary>
                                <div class="govuk-details__text">
                                    <p class="govuk-body">Marketing cookies:</p>
                                    <ul class="govuk-list govuk-list--bullet">
                                        <li>track visits across websites</li>
                                        <li>build a profile of your interests</li>
                                        <li>show relevant advertisements</li>
                                        <li>measure campaign effectiveness</li>
                                    </ul>

                                    <?php if (!empty($cookies['marketing'])): ?>
                                    <details class="govuk-details" data-module="govuk-details">
                                        <summary class="govuk-details__summary">
                                            <span class="govuk-details__summary-text">
                                                View marketing cookies (<?= count($cookies['marketing']) ?>)
                                            </span>
                                        </summary>
                                        <div class="govuk-details__text">
                                            <table class="govuk-table govuk-!-font-size-16">
                                                <thead class="govuk-table__head">
                                                    <tr class="govuk-table__row">
                                                        <th scope="col" class="govuk-table__header">Name</th>
                                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                                        <th scope="col" class="govuk-table__header">Expires</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="govuk-table__body">
                                                    <?php foreach ($cookies['marketing'] as $cookie): ?>
                                                    <tr class="govuk-table__row">
                                                        <th scope="row" class="govuk-table__header"><code><?= htmlspecialchars($cookie['cookie_name']) ?></code></th>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </fieldset>
                    </div>

                    <!-- Action Buttons -->
                    <div class="govuk-button-group">
                        <button type="button" class="govuk-button" data-module="govuk-button" id="save-preferences-btn">
                            Save my preferences
                        </button>

                        <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" id="accept-all-btn">
                            Accept all cookies
                        </button>

                        <button type="button" class="govuk-button govuk-button--warning" data-module="govuk-button" id="reject-all-btn">
                            Reject optional cookies
                        </button>
                    </div>

                </form>

                <!-- Additional Information -->
                <div class="govuk-inset-text govuk-!-margin-top-6">
                    <p class="govuk-body">
                        For more information about how we use cookies, please read our
                        <a href="<?= htmlspecialchars($basePath) ?>/legal/cookies" class="govuk-link">Cookie Policy</a>
                        and <a href="<?= htmlspecialchars($basePath) ?>/privacy" class="govuk-link">Privacy Policy</a>.
                    </p>
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        Your preferences are stored locally and will be remembered for 12 months.
                        You can change your settings at any time by returning to this page.
                    </p>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-related-navigation" role="complementary">
                    <h2 class="govuk-heading-s" id="related-nav-heading">
                        Related content
                    </h2>
                    <nav role="navigation" aria-labelledby="related-nav-heading">
                        <ul class="govuk-list govuk-!-font-size-16">
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/legal/cookies">
                                    Cookie Policy
                                </a>
                            </li>
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/privacy">
                                    Privacy Policy
                                </a>
                            </li>
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/terms">
                                    Terms of Service
                                </a>
                            </li>
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/legal">
                                    Legal information
                                </a>
                            </li>
                        </ul>
                    </nav>
                </aside>
            </div>
        </div>

    </main>
</div>

<script>
(function() {
    'use strict';

    // Wait for cookie consent library to load
    if (typeof window.NexusCookieConsent === 'undefined') {
        console.error('Cookie consent library not loaded');
        return;
    }

    // Form elements
    const functionalOnRadio = document.getElementById('functional-on');
    const functionalOffRadio = document.getElementById('functional-off');
    const analyticsOnRadio = document.getElementById('analytics-on');
    const analyticsOffRadio = document.getElementById('analytics-off');
    const marketingOnRadio = document.getElementById('marketing-on');
    const marketingOffRadio = document.getElementById('marketing-off');

    // Buttons
    const saveBtn = document.getElementById('save-preferences-btn');
    const acceptAllBtn = document.getElementById('accept-all-btn');
    const rejectAllBtn = document.getElementById('reject-all-btn');

    // Status banner
    const statusBanner = document.getElementById('consent-status-banner');
    const statusContent = document.getElementById('consent-status-content');

    // Load current preferences
    function loadCurrentPreferences() {
        const consent = window.NexusCookieConsent.getConsent();

        if (consent) {
            // Set radio buttons
            if (consent.functional) {
                functionalOnRadio.checked = true;
            } else {
                functionalOffRadio.checked = true;
            }

            if (consent.analytics) {
                analyticsOnRadio.checked = true;
            } else {
                analyticsOffRadio.checked = true;
            }

            if (consent.marketing) {
                marketingOnRadio.checked = true;
            } else {
                marketingOffRadio.checked = true;
            }

            updateStatusBanner(consent);
        } else {
            showWarningBanner('You have not set your cookie preferences yet.');
        }
    }

    // Update status banner
    function updateStatusBanner(consent) {
        const enabledCategories = [];
        if (consent.functional) enabledCategories.push('functional');
        if (consent.analytics) enabledCategories.push('analytics');
        if (consent.marketing) enabledCategories.push('marketing');

        let statusMessage = '';
        let bannerClass = 'govuk-notification-banner';

        if (enabledCategories.length === 0) {
            statusMessage = 'Only essential cookies are enabled.';
            bannerClass += ' govuk-notification-banner--warning';
        } else if (enabledCategories.length === 3) {
            statusMessage = 'All cookie categories are enabled.';
            bannerClass += ' govuk-notification-banner--success';
        } else {
            statusMessage = 'You have enabled: ' + enabledCategories.join(', ') + ' cookies.';
        }

        if (consent.granted_at) {
            const grantedDate = new Date(consent.granted_at);
            const dateStr = grantedDate.toLocaleDateString('en-GB', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            statusMessage += '<br><span class="govuk-body-s">Last updated: ' + dateStr + '</span>';
        }

        statusBanner.className = bannerClass;
        statusContent.innerHTML = '<p class="govuk-notification-banner__heading">' + statusMessage + '</p>';
    }

    // Show warning banner
    function showWarningBanner(message) {
        statusBanner.className = 'govuk-notification-banner govuk-notification-banner--warning';
        statusContent.innerHTML = '<p class="govuk-notification-banner__heading">' + message + '</p>';
    }

    // Show success banner
    function showSuccessBanner(message) {
        statusBanner.className = 'govuk-notification-banner govuk-notification-banner--success';
        statusContent.innerHTML = '<p class="govuk-notification-banner__heading">' + message + '</p>';
        statusBanner.setAttribute('role', 'alert');

        // Scroll to top to show banner
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Reset role after announcement
        setTimeout(() => {
            statusBanner.setAttribute('role', 'region');
        }, 3000);
    }

    // Get current form values
    function getFormValues() {
        return {
            functional: functionalOnRadio.checked,
            analytics: analyticsOnRadio.checked,
            marketing: marketingOnRadio.checked
        };
    }

    // Save preferences
    function savePreferences() {
        const preferences = getFormValues();

        window.NexusCookieConsent.savePreferences(preferences)
            .then(() => {
                showSuccessBanner('Your cookie preferences have been saved successfully.');
                updateStatusBanner({
                    ...preferences,
                    granted_at: new Date().toISOString()
                });
            })
            .catch(err => {
                console.error('Failed to save preferences:', err);
                showWarningBanner('Failed to save preferences. Please try again.');
            });
    }

    // Accept all cookies
    function acceptAll() {
        functionalOnRadio.checked = true;
        analyticsOnRadio.checked = true;
        marketingOnRadio.checked = true;

        window.NexusCookieConsent.acceptAll()
            .then(() => {
                showSuccessBanner('All cookies accepted.');
                updateStatusBanner({
                    functional: true,
                    analytics: true,
                    marketing: true,
                    granted_at: new Date().toISOString()
                });
            })
            .catch(err => {
                console.error('Failed to accept all:', err);
                showWarningBanner('Failed to update preferences. Please try again.');
            });
    }

    // Reject all optional cookies
    function rejectAll() {
        functionalOffRadio.checked = true;
        analyticsOffRadio.checked = true;
        marketingOffRadio.checked = true;

        window.NexusCookieConsent.rejectAll()
            .then(() => {
                showSuccessBanner('Optional cookies rejected. Only essential cookies will be used.');
                updateStatusBanner({
                    functional: false,
                    analytics: false,
                    marketing: false,
                    granted_at: new Date().toISOString()
                });
            })
            .catch(err => {
                console.error('Failed to reject all:', err);
                showWarningBanner('Failed to update preferences. Please try again.');
            });
    }

    // Event listeners
    saveBtn.addEventListener('click', savePreferences);
    acceptAllBtn.addEventListener('click', acceptAll);
    rejectAllBtn.addEventListener('click', rejectAll);

    // Load preferences on page load
    loadCurrentPreferences();
})();
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
