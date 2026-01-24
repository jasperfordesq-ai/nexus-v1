<?php
/**
 * Cookie Consent Banner - CivicOne Theme
 * GOV.UK Design System Compliant
 * WCAG 2.1 AA Accessible
 */

use Nexus\Core\TenantContext;
use Nexus\Services\CookieInventoryService;
use Nexus\Services\CookieConsentService;

$basePath = TenantContext::getBasePath();
$tenantId = TenantContext::getId();

// Get cookie categories for display
$cookies = CookieInventoryService::getAllCookies($tenantId);
$counts = CookieInventoryService::getCookieCounts($tenantId);
$tenantSettings = CookieConsentService::getTenantSettings($tenantId);

// Get tenant name
$tenant = TenantContext::get();
$tenantName = $tenant['name'] ?? 'This Service';
?>

<!-- Cookie Consent Banner - GOV.UK Pattern -->
<div id="nexus-cookie-banner" class="govuk-cookie-banner" role="region" aria-label="Cookie banner" aria-hidden="true">
    <div class="govuk-cookie-banner__message govuk-width-container">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h2 id="cookie-banner-title" class="govuk-cookie-banner__heading govuk-heading-m">
                    Cookies on <?= htmlspecialchars($tenantName) ?>
                </h2>
                <div class="govuk-cookie-banner__content">
                    <p class="govuk-body" id="cookie-banner-description">
                        <?php if (!empty($tenantSettings['banner_message'])): ?>
                            <?= htmlspecialchars($tenantSettings['banner_message']) ?>
                        <?php else: ?>
                            We use some essential cookies to make this service work.
                        <?php endif; ?>
                    </p>
                    <?php if ($tenantSettings['analytics_enabled'] || $tenantSettings['marketing_enabled']): ?>
                    <p class="govuk-body">
                        We'd<?php if ($tenantSettings['analytics_enabled']): ?> also like to use analytics cookies so we can understand how you use the service and make improvements<?php endif; ?><?php if ($tenantSettings['marketing_enabled']): ?><?php if ($tenantSettings['analytics_enabled']): ?>, and<?php else: ?> also like to use<?php endif; ?> marketing cookies to show you relevant content<?php endif; ?>.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="govuk-button-group">
            <button
                type="button"
                class="govuk-button"
                data-module="govuk-button"
                onclick="handleAcceptAll()"
            >
                Accept all cookies
            </button>
            <?php if ($tenantSettings['show_reject_all']): ?>
            <button
                type="button"
                class="govuk-button govuk-button--secondary"
                data-module="govuk-button"
                onclick="handleRejectAll()"
            >
                Reject optional cookies
            </button>
            <?php endif; ?>
            <a
                class="govuk-link"
                href="#"
                onclick="openCookiePreferences(); return false;"
            >
                View cookie settings
            </a>
        </div>
    </div>
</div>

<!-- Cookie Preferences Panel - GOV.UK Pattern -->
<div id="cookie-preferences-modal" class="govuk-cookie-preferences-panel" style="display: none;" role="dialog" aria-labelledby="preferences-title" aria-modal="true">
    <div class="govuk-width-container">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Close Button -->
                <button
                    type="button"
                    class="govuk-cookie-preferences-close"
                    onclick="closeCookiePreferences()"
                    aria-label="Close cookie preferences"
                >
                    <span aria-hidden="true">×</span>
                    <span class="govuk-visually-hidden">Close</span>
                </button>

                <h2 id="preferences-title" class="govuk-heading-l">
                    Cookie preferences
                </h2>

                <p class="govuk-body">
                    We use cookies to make this service work and collect analytics information.
                    To accept or reject cookies, turn on JavaScript in your browser settings or reload this page.
                </p>

                <!-- Essential Cookies -->
                <div class="govuk-cookie-category">
                    <h3 class="govuk-heading-m">
                        Essential cookies
                    </h3>
                    <p class="govuk-body">
                        Essential cookies keep your information secure while you use this service.
                        We do not need to ask permission to use them.
                    </p>

                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary">
                            <span class="govuk-details__summary-text">
                                View essential cookies (<?= count($cookies['essential'] ?? []) ?>)
                            </span>
                        </summary>
                        <div class="govuk-details__text">
                            <table class="govuk-table govuk-cookie-table">
                                <thead class="govuk-table__head">
                                    <tr class="govuk-table__row">
                                        <th scope="col" class="govuk-table__header">Cookie name</th>
                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                        <th scope="col" class="govuk-table__header">Expires</th>
                                    </tr>
                                </thead>
                                <tbody class="govuk-table__body">
                                    <?php foreach ($cookies['essential'] ?? [] as $cookie): ?>
                                    <tr class="govuk-table__row">
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['cookie_name']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>

                <!-- Functional Cookies -->
                <div class="govuk-cookie-category">
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset" aria-describedby="functional-hint">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h3 class="govuk-fieldset__heading">
                                    Functional cookies
                                </h3>
                            </legend>
                            <div id="functional-hint" class="govuk-hint">
                                Functional cookies help us remember your settings, like your theme preference.
                            </div>
                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="cookie-functional-on" name="cookie-functional" type="radio" value="true">
                                    <label class="govuk-label govuk-radios__label" for="cookie-functional-on">
                                        Use functional cookies
                                    </label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="cookie-functional-off" name="cookie-functional" type="radio" value="false" checked>
                                    <label class="govuk-label govuk-radios__label" for="cookie-functional-off">
                                        Do not use functional cookies
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary">
                            <span class="govuk-details__summary-text">
                                View functional cookies (<?= count($cookies['functional'] ?? []) ?>)
                            </span>
                        </summary>
                        <div class="govuk-details__text">
                            <table class="govuk-table govuk-cookie-table">
                                <thead class="govuk-table__head">
                                    <tr class="govuk-table__row">
                                        <th scope="col" class="govuk-table__header">Cookie name</th>
                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                        <th scope="col" class="govuk-table__header">Expires</th>
                                    </tr>
                                </thead>
                                <tbody class="govuk-table__body">
                                    <?php foreach ($cookies['functional'] ?? [] as $cookie): ?>
                                    <tr class="govuk-table__row">
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['cookie_name']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>

                <!-- Analytics Cookies -->
                <?php if ($tenantSettings['analytics_enabled']): ?>
                <div class="govuk-cookie-category">
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset" aria-describedby="analytics-hint">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h3 class="govuk-fieldset__heading">
                                    Analytics cookies
                                </h3>
                            </legend>
                            <div id="analytics-hint" class="govuk-hint">
                                We use analytics cookies to measure how you use this service so we can improve it.
                            </div>
                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="cookie-analytics-on" name="cookie-analytics" type="radio" value="true">
                                    <label class="govuk-label govuk-radios__label" for="cookie-analytics-on">
                                        Use analytics cookies
                                    </label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="cookie-analytics-off" name="cookie-analytics" type="radio" value="false" checked>
                                    <label class="govuk-label govuk-radios__label" for="cookie-analytics-off">
                                        Do not use analytics cookies
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary">
                            <span class="govuk-details__summary-text">
                                View analytics cookies (<?= count($cookies['analytics'] ?? []) ?>)
                            </span>
                        </summary>
                        <div class="govuk-details__text">
                            <?php if (empty($cookies['analytics'])): ?>
                            <p class="govuk-body">No analytics cookies are currently in use.</p>
                            <?php else: ?>
                            <table class="govuk-table govuk-cookie-table">
                                <thead class="govuk-table__head">
                                    <tr class="govuk-table__row">
                                        <th scope="col" class="govuk-table__header">Cookie name</th>
                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                        <th scope="col" class="govuk-table__header">Expires</th>
                                    </tr>
                                </thead>
                                <tbody class="govuk-table__body">
                                    <?php foreach ($cookies['analytics'] as $cookie): ?>
                                    <tr class="govuk-table__row">
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['cookie_name']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <?php endif; ?>

                <!-- Marketing Cookies -->
                <?php if ($tenantSettings['marketing_enabled']): ?>
                <div class="govuk-cookie-category">
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset" aria-describedby="marketing-hint">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h3 class="govuk-fieldset__heading">
                                    Marketing cookies
                                </h3>
                            </legend>
                            <div id="marketing-hint" class="govuk-hint">
                                We use marketing cookies to show you content that may be relevant to you.
                            </div>
                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="cookie-marketing-on" name="cookie-marketing" type="radio" value="true">
                                    <label class="govuk-label govuk-radios__label" for="cookie-marketing-on">
                                        Use marketing cookies
                                    </label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="cookie-marketing-off" name="cookie-marketing" type="radio" value="false" checked>
                                    <label class="govuk-label govuk-radios__label" for="cookie-marketing-off">
                                        Do not use marketing cookies
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary">
                            <span class="govuk-details__summary-text">
                                View marketing cookies (<?= count($cookies['marketing'] ?? []) ?>)
                            </span>
                        </summary>
                        <div class="govuk-details__text">
                            <?php if (empty($cookies['marketing'])): ?>
                            <p class="govuk-body">No marketing cookies are currently in use.</p>
                            <?php else: ?>
                            <table class="govuk-table govuk-cookie-table">
                                <thead class="govuk-table__head">
                                    <tr class="govuk-table__row">
                                        <th scope="col" class="govuk-table__header">Cookie name</th>
                                        <th scope="col" class="govuk-table__header">Purpose</th>
                                        <th scope="col" class="govuk-table__header">Expires</th>
                                    </tr>
                                </thead>
                                <tbody class="govuk-table__body">
                                    <?php foreach ($cookies['marketing'] as $cookie): ?>
                                    <tr class="govuk-table__row">
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['cookie_name']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                        <td class="govuk-table__cell"><?= htmlspecialchars($cookie['duration']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <?php endif; ?>

                <!-- Save Button -->
                <button
                    type="button"
                    class="govuk-button"
                    data-module="govuk-button"
                    onclick="saveCustomPreferences()"
                >
                    Save cookie preferences
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cookie Banner JavaScript (GOV.UK Pattern) -->
<script>
// Handle Accept All
async function handleAcceptAll() {
    const success = await window.NexusCookieConsent.acceptAll();
    if (success) {
        showGovukNotification('You have accepted all cookies.');
    } else {
        showGovukNotification('Failed to save preferences. Please try again.', 'error');
    }
}

// Handle Reject All
async function handleRejectAll() {
    const success = await window.NexusCookieConsent.rejectAll();
    if (success) {
        showGovukNotification('You have rejected optional cookies. Only essential cookies will be used.');
    } else {
        showGovukNotification('Failed to save preferences. Please try again.', 'error');
    }
}

// Open Preferences Panel
function openCookiePreferences() {
    const panel = document.getElementById('cookie-preferences-modal');
    const banner = document.getElementById('nexus-cookie-banner');

    // Hide banner
    if (banner) {
        banner.setAttribute('aria-hidden', 'true');
        banner.style.display = 'none';
    }

    // Show panel
    panel.style.display = 'block';
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('govuk-cookie-preferences-open');

    // Load current preferences
    const consent = window.NexusCookieConsent.getConsent();
    if (consent) {
        // Functional
        document.getElementById(consent.functional ? 'cookie-functional-on' : 'cookie-functional-off').checked = true;

        // Analytics (if exists)
        const analyticsOn = document.getElementById('cookie-analytics-on');
        const analyticsOff = document.getElementById('cookie-analytics-off');
        if (analyticsOn && analyticsOff) {
            document.getElementById(consent.analytics ? 'cookie-analytics-on' : 'cookie-analytics-off').checked = true;
        }

        // Marketing (if exists)
        const marketingOn = document.getElementById('cookie-marketing-on');
        const marketingOff = document.getElementById('cookie-marketing-off');
        if (marketingOn && marketingOff) {
            document.getElementById(consent.marketing ? 'cookie-marketing-on' : 'cookie-marketing-off').checked = true;
        }
    }

    // Focus management
    setTimeout(() => {
        panel.querySelector('.govuk-cookie-preferences-close').focus();
    }, 100);
}

// Close Preferences Panel
function closeCookiePreferences() {
    const panel = document.getElementById('cookie-preferences-modal');
    panel.style.display = 'none';
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('govuk-cookie-preferences-open');

    // If no consent exists, show banner again
    if (!window.NexusCookieConsent.hasConsent()) {
        const banner = document.getElementById('nexus-cookie-banner');
        if (banner) {
            banner.setAttribute('aria-hidden', 'false');
            banner.style.display = 'block';
        }
    }
}

// Save Custom Preferences (GOV.UK Radio Buttons)
async function saveCustomPreferences() {
    const choices = {
        functional: document.getElementById('cookie-functional-on').checked
    };

    // Analytics (if exists)
    const analyticsOn = document.getElementById('cookie-analytics-on');
    if (analyticsOn) {
        choices.analytics = analyticsOn.checked;
    }

    // Marketing (if exists)
    const marketingOn = document.getElementById('cookie-marketing-on');
    if (marketingOn) {
        choices.marketing = marketingOn.checked;
    }

    const success = await window.NexusCookieConsent.savePreferences(choices);
    if (success) {
        closeCookiePreferences();
        showGovukNotification('Your cookie preferences have been saved.');
    } else {
        showGovukNotification('Failed to save preferences. Please try again.', 'error');
    }
}

// Show GOV.UK style notification
function showGovukNotification(message, type = 'success') {
    // Remove existing notification
    const existing = document.querySelector('.govuk-cookie-notification');
    if (existing) {
        existing.remove();
    }

    // Create notification
    const notification = document.createElement('div');
    notification.className = 'govuk-cookie-notification govuk-cookie-notification--' + type;
    notification.setAttribute('role', type === 'error' ? 'alert' : 'status');
    notification.innerHTML = `
        <div class="govuk-width-container">
            <p class="govuk-body">${message}</p>
        </div>
    `;

    // Insert at top of page
    document.body.insertBefore(notification, document.body.firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Keyboard accessibility
document.addEventListener('keydown', function(e) {
    const panel = document.getElementById('cookie-preferences-modal');
    if (panel && panel.style.display === 'block' && e.key === 'Escape') {
        closeCookiePreferences();
    }
});
</script>
