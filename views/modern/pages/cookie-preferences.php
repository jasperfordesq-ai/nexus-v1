<?php
/**
 * Cookie Preferences Page - Modern Theme
 * Standalone page for managing cookie consent preferences
 */

use Nexus\Core\TenantContext;

$pageTitle = $pageTitle ?? 'Cookie Preferences';
$basePath = TenantContext::getBasePath();

require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="cookie-preferences-page">
    <div class="preferences-container">
        <!-- Header Section -->
        <div class="preferences-header">
            <a href="<?= htmlspecialchars($basePath) ?>/legal" class="back-link">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Legal Hub
            </a>

            <h1 class="preferences-title">Cookie Preferences</h1>
            <p class="preferences-subtitle">
                Manage how we use cookies and similar technologies on <?= htmlspecialchars($tenantName) ?>.
                Your choices will be saved and applied across the site.
            </p>
        </div>

        <!-- Current Consent Status -->
        <div class="consent-status-card" id="consent-status">
            <div class="status-header">
                <h2 class="status-title">Current Settings</h2>
                <span class="status-badge" id="status-badge">Loading...</span>
            </div>
            <div class="status-details" id="status-details">
                <p class="status-loading">Loading your preferences...</p>
            </div>
        </div>

        <!-- Cookie Categories -->
        <div class="cookie-categories">

            <!-- Essential Cookies (Always On) -->
            <div class="category-card essential-category">
                <div class="category-header">
                    <div class="category-info">
                        <h3 class="category-title">
                            <svg class="category-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 16V12M12 8H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            Essential Cookies
                        </h3>
                        <span class="category-badge required-badge">Always Active</span>
                    </div>
                    <p class="category-description">
                        These cookies are necessary for the website to function and cannot be switched off.
                        They are usually only set in response to actions you make, such as logging in or filling in forms.
                    </p>
                </div>

                <div class="category-details">
                    <h4 class="details-title">What these cookies do:</h4>
                    <ul class="details-list">
                        <li>Keep you logged in securely</li>
                        <li>Remember your security preferences</li>
                        <li>Protect against fraudulent activity</li>
                        <li>Enable core site functionality</li>
                    </ul>

                    <details class="cookie-list-details">
                        <summary class="cookie-list-summary">View cookies (<?= count($cookies['essential'] ?? []) ?>)</summary>
                        <div class="cookie-list">
                            <?php if (!empty($cookies['essential'])): ?>
                                <?php foreach ($cookies['essential'] as $cookie): ?>
                                <div class="cookie-item">
                                    <code class="cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                    <p class="cookie-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                                    <span class="cookie-duration">Expires: <?= htmlspecialchars($cookie['duration']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-cookies">No essential cookies listed.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </div>

            <!-- Functional Cookies -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-info">
                        <h3 class="category-title">
                            <svg class="category-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10.325 4.317C10.751 2.561 13.249 2.561 13.675 4.317C13.7389 4.5808 13.8642 4.82578 14.0407 5.032C14.2172 5.23822 14.4399 5.39985 14.6907 5.50375C14.9414 5.60764 15.2132 5.65085 15.4838 5.62987C15.7544 5.60889 16.0162 5.5243 16.248 5.383C17.791 4.443 19.558 6.209 18.618 7.753C18.4769 7.98466 18.3924 8.24634 18.3715 8.51677C18.3506 8.78721 18.3938 9.05877 18.4975 9.30938C18.6013 9.55999 18.7627 9.78258 18.9687 9.95905C19.1747 10.1355 19.4194 10.2609 19.683 10.325C21.439 10.751 21.439 13.249 19.683 13.675C19.4192 13.7389 19.1742 13.8642 18.968 14.0407C18.7618 14.2172 18.6001 14.4399 18.4963 14.6907C18.3924 14.9414 18.3491 15.2132 18.3701 15.4838C18.3911 15.7544 18.4757 16.0162 18.617 16.248C19.557 17.791 17.791 19.558 16.247 18.618C16.0153 18.4769 15.7537 18.3924 15.4832 18.3715C15.2128 18.3506 14.9412 18.3938 14.6906 18.4975C14.44 18.6013 14.2174 18.7627 14.0409 18.9687C13.8645 19.1747 13.7391 19.4194 13.675 19.683C13.249 21.439 10.751 21.439 10.325 19.683C10.2611 19.4192 10.1358 19.1742 9.95929 18.968C9.7828 18.7618 9.56011 18.6001 9.30935 18.4963C9.05859 18.3924 8.78683 18.3491 8.51621 18.3701C8.24559 18.3911 7.98375 18.4757 7.752 18.617C6.209 19.557 4.442 17.791 5.382 16.247C5.5231 16.0153 5.60755 15.7537 5.62848 15.4832C5.64942 15.2128 5.60624 14.9412 5.50247 14.6906C5.3987 14.44 5.23726 14.2174 5.03127 14.0409C4.82529 13.8645 4.58056 13.7391 4.317 13.675C2.561 13.249 2.561 10.751 4.317 10.325C4.5808 10.2611 4.82578 10.1358 5.032 9.95929C5.23822 9.7828 5.39985 9.56011 5.50375 9.30935C5.60764 9.05859 5.65085 8.78683 5.62987 8.51621C5.60889 8.24559 5.5243 7.98375 5.383 7.752C4.443 6.209 6.209 4.442 7.753 5.382C8.753 5.99 10.049 5.452 10.325 4.317Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Functional Cookies
                        </h3>
                        <label class="category-toggle">
                            <input type="checkbox" id="functional-toggle" class="toggle-input">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p class="category-description">
                        These cookies enable enhanced functionality and personalization, such as remembering your
                        preferences and settings. If you do not allow these cookies, some or all of these features may not work properly.
                    </p>
                </div>

                <div class="category-details">
                    <h4 class="details-title">What these cookies do:</h4>
                    <ul class="details-list">
                        <li>Remember your display preferences (theme, layout)</li>
                        <li>Save your location or region</li>
                        <li>Personalize content based on your interests</li>
                        <li>Enable social media features</li>
                    </ul>

                    <details class="cookie-list-details">
                        <summary class="cookie-list-summary">View cookies (<?= count($cookies['functional'] ?? []) ?>)</summary>
                        <div class="cookie-list">
                            <?php if (!empty($cookies['functional'])): ?>
                                <?php foreach ($cookies['functional'] as $cookie): ?>
                                <div class="cookie-item">
                                    <code class="cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                    <p class="cookie-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                                    <span class="cookie-duration">Expires: <?= htmlspecialchars($cookie['duration']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-cookies">No functional cookies currently in use.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </div>

            <!-- Analytics Cookies -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-info">
                        <h3 class="category-title">
                            <svg class="category-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 11L12 14L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Analytics Cookies
                        </h3>
                        <label class="category-toggle">
                            <input type="checkbox" id="analytics-toggle" class="toggle-input">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p class="category-description">
                        These cookies help us understand how visitors interact with our website by collecting
                        and reporting information anonymously. This helps us improve the site experience.
                    </p>
                </div>

                <div class="category-details">
                    <h4 class="details-title">What these cookies do:</h4>
                    <ul class="details-list">
                        <li>Count visits and measure traffic sources</li>
                        <li>Understand which pages are most popular</li>
                        <li>See how visitors move around the site</li>
                        <li>Help us improve site performance</li>
                    </ul>

                    <details class="cookie-list-details">
                        <summary class="cookie-list-summary">View cookies (<?= count($cookies['analytics'] ?? []) ?>)</summary>
                        <div class="cookie-list">
                            <?php if (!empty($cookies['analytics'])): ?>
                                <?php foreach ($cookies['analytics'] as $cookie): ?>
                                <div class="cookie-item">
                                    <code class="cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                    <p class="cookie-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                                    <span class="cookie-duration">Expires: <?= htmlspecialchars($cookie['duration']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-cookies">No analytics cookies currently in use.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </div>

            <!-- Marketing Cookies -->
            <div class="category-card">
                <div class="category-header">
                    <div class="category-info">
                        <h3 class="category-title">
                            <svg class="category-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Marketing Cookies
                        </h3>
                        <label class="category-toggle">
                            <input type="checkbox" id="marketing-toggle" class="toggle-input">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p class="category-description">
                        These cookies may be set by our advertising partners to build a profile of your interests
                        and show you relevant content on other sites. They do not store personal information directly.
                    </p>
                </div>

                <div class="category-details">
                    <h4 class="details-title">What these cookies do:</h4>
                    <ul class="details-list">
                        <li>Track visits across websites</li>
                        <li>Build a profile of your interests</li>
                        <li>Show relevant advertisements</li>
                        <li>Measure campaign effectiveness</li>
                    </ul>

                    <details class="cookie-list-details">
                        <summary class="cookie-list-summary">View cookies (<?= count($cookies['marketing'] ?? []) ?>)</summary>
                        <div class="cookie-list">
                            <?php if (!empty($cookies['marketing'])): ?>
                                <?php foreach ($cookies['marketing'] as $cookie): ?>
                                <div class="cookie-item">
                                    <code class="cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                    <p class="cookie-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                                    <span class="cookie-duration">Expires: <?= htmlspecialchars($cookie['duration']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-cookies">No marketing cookies currently in use.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </div>

        </div>

        <!-- Action Buttons -->
        <div class="preferences-actions">
            <button type="button" class="btn-save-preferences" id="save-preferences-btn">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16.6667 5L7.50004 14.1667L3.33337 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Save My Preferences
            </button>

            <button type="button" class="btn-accept-all" id="accept-all-btn">
                Accept All Cookies
            </button>

            <button type="button" class="btn-reject-all" id="reject-all-btn">
                Reject Optional Cookies
            </button>
        </div>

        <!-- Additional Information -->
        <div class="preferences-footer">
            <p class="footer-text">
                For more information about how we use cookies, please read our
                <a href="<?= htmlspecialchars($basePath) ?>/legal/cookies" class="footer-link">Cookie Policy</a>
                and <a href="<?= htmlspecialchars($basePath) ?>/privacy" class="footer-link">Privacy Policy</a>.
            </p>

            <p class="footer-note">
                Your preferences are stored locally and will be remembered for 12 months.
                You can change your settings at any time by returning to this page.
            </p>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Wait for cookie consent library to load
    if (typeof window.NexusCookieConsent === 'undefined') {
        console.error('Cookie consent library not loaded');
        return;
    }

    const functionalToggle = document.getElementById('functional-toggle');
    const analyticsToggle = document.getElementById('analytics-toggle');
    const marketingToggle = document.getElementById('marketing-toggle');
    const saveBtn = document.getElementById('save-preferences-btn');
    const acceptAllBtn = document.getElementById('accept-all-btn');
    const rejectAllBtn = document.getElementById('reject-all-btn');
    const statusBadge = document.getElementById('status-badge');
    const statusDetails = document.getElementById('status-details');

    // Load current preferences
    function loadCurrentPreferences() {
        const consent = window.NexusCookieConsent.getConsent();

        if (consent) {
            functionalToggle.checked = consent.functional || false;
            analyticsToggle.checked = consent.analytics || false;
            marketingToggle.checked = consent.marketing || false;

            updateStatusDisplay(consent);
        } else {
            statusBadge.textContent = 'Not Set';
            statusBadge.className = 'status-badge status-warning';
            statusDetails.innerHTML = '<p class="status-message">You have not set your cookie preferences yet.</p>';
        }
    }

    // Update status display
    function updateStatusDisplay(consent) {
        const enabledCategories = [];
        if (consent.functional) enabledCategories.push('Functional');
        if (consent.analytics) enabledCategories.push('Analytics');
        if (consent.marketing) enabledCategories.push('Marketing');

        if (enabledCategories.length === 0) {
            statusBadge.textContent = 'Essential Only';
            statusBadge.className = 'status-badge status-minimal';
            statusDetails.innerHTML = '<p class="status-message">Only essential cookies are enabled. Optional features may be limited.</p>';
        } else if (enabledCategories.length === 3) {
            statusBadge.textContent = 'All Enabled';
            statusBadge.className = 'status-badge status-success';
            statusDetails.innerHTML = '<p class="status-message">All cookie categories are enabled for the full experience.</p>';
        } else {
            statusBadge.textContent = 'Custom';
            statusBadge.className = 'status-badge status-custom';
            statusDetails.innerHTML = `<p class="status-message">You have enabled: ${enabledCategories.join(', ')}</p>`;
        }

        if (consent.granted_at) {
            const grantedDate = new Date(consent.granted_at);
            const dateStr = grantedDate.toLocaleDateString('en-GB', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            statusDetails.innerHTML += `<p class="status-date">Last updated: ${dateStr}</p>`;
        }
    }

    // Save preferences
    function savePreferences() {
        const preferences = {
            functional: functionalToggle.checked,
            analytics: analyticsToggle.checked,
            marketing: marketingToggle.checked
        };

        window.NexusCookieConsent.savePreferences(preferences)
            .then(() => {
                showNotification('Your cookie preferences have been saved successfully.', 'success');
                updateStatusDisplay({
                    ...preferences,
                    granted_at: new Date().toISOString()
                });
            })
            .catch(err => {
                console.error('Failed to save preferences:', err);
                showNotification('Failed to save preferences. Please try again.', 'error');
            });
    }

    // Accept all
    function acceptAll() {
        functionalToggle.checked = true;
        analyticsToggle.checked = true;
        marketingToggle.checked = true;

        window.NexusCookieConsent.acceptAll()
            .then(() => {
                showNotification('All cookies accepted.', 'success');
                updateStatusDisplay({
                    functional: true,
                    analytics: true,
                    marketing: true,
                    granted_at: new Date().toISOString()
                });
            })
            .catch(err => {
                console.error('Failed to accept all:', err);
                showNotification('Failed to update preferences. Please try again.', 'error');
            });
    }

    // Reject all
    function rejectAll() {
        functionalToggle.checked = false;
        analyticsToggle.checked = false;
        marketingToggle.checked = false;

        window.NexusCookieConsent.rejectAll()
            .then(() => {
                showNotification('Optional cookies rejected. Only essential cookies will be used.', 'success');
                updateStatusDisplay({
                    functional: false,
                    analytics: false,
                    marketing: false,
                    granted_at: new Date().toISOString()
                });
            })
            .catch(err => {
                console.error('Failed to reject all:', err);
                showNotification('Failed to update preferences. Please try again.', 'error');
            });
    }

    // Show notification
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `cookie-notification cookie-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <svg class="notification-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16.6667 5L7.50004 14.1667L3.33337 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('visible');
        }, 10);

        setTimeout(() => {
            notification.classList.remove('visible');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // Event listeners
    saveBtn.addEventListener('click', savePreferences);
    acceptAllBtn.addEventListener('click', acceptAll);
    rejectAllBtn.addEventListener('click', rejectAll);

    // Load preferences on page load
    loadCurrentPreferences();
})();
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
