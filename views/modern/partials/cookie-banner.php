<?php
/**
 * Cookie Consent Banner - Modern Theme
 * Displays on first visit, allows granular consent management
 * Theme: Modern glassmorphism design with animations
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
$tenantName = $tenant['name'] ?? 'This Community';
?>

<!-- Cookie Consent Banner - Modern Theme -->
<div id="nexus-cookie-banner" class="cookie-banner" role="dialog" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-description" aria-hidden="true">
    <div class="cookie-banner-container">
        <!-- Banner Content -->
        <div class="cookie-banner-content">
            <!-- Icon -->
            <div class="cookie-banner-icon">
                <i class="fa-solid fa-cookie-bite"></i>
            </div>

            <!-- Text -->
            <div class="cookie-banner-text">
                <h2 id="cookie-banner-title" class="cookie-banner-title">
                    <i class="fa-solid fa-shield-halved"></i>
                    We Value Your Privacy
                </h2>
                <p id="cookie-banner-description" class="cookie-banner-description">
                    <?php if (!empty($tenantSettings['banner_message'])): ?>
                        <?= htmlspecialchars($tenantSettings['banner_message']) ?>
                    <?php else: ?>
                        We use essential cookies to make our site work. With your consent, we may also use
                        non-essential cookies to improve user experience and analyze website traffic.
                        By clicking "Accept All", you agree to our use of cookies.
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($basePath) ?>/legal/cookies" class="cookie-policy-link">
                        Learn more in our Cookie Policy
                    </a>
                </p>
            </div>
        </div>

        <!-- Banner Actions -->
        <div class="cookie-banner-actions">
            <button
                type="button"
                class="btn btn-primary cookie-btn-accept-all"
                onclick="handleAcceptAll()"
                aria-label="Accept all cookies"
            >
                <i class="fa-solid fa-check"></i>
                Accept All
            </button>

            <?php if ($tenantSettings['show_reject_all']): ?>
            <button
                type="button"
                class="btn btn-secondary cookie-btn-reject-all"
                onclick="handleRejectAll()"
                aria-label="Reject non-essential cookies"
            >
                <i class="fa-solid fa-times"></i>
                Essential Only
            </button>
            <?php endif; ?>

            <button
                type="button"
                class="btn btn-outline cookie-btn-manage"
                onclick="openCookiePreferences()"
                aria-label="Manage cookie preferences"
            >
                <i class="fa-solid fa-sliders"></i>
                Manage Preferences
            </button>
        </div>
    </div>
</div>

<!-- Cookie Preferences Modal -->
<div id="cookie-preferences-modal" class="cookie-modal" role="dialog" aria-labelledby="preferences-title" aria-modal="true" style="display: none;">
    <div class="cookie-modal-backdrop" onclick="closeCookiePreferences()"></div>

    <div class="cookie-modal-content">
        <!-- Modal Header -->
        <div class="cookie-modal-header">
            <h2 id="preferences-title" class="cookie-modal-title">
                <i class="fa-solid fa-cookie-bite"></i>
                Cookie Preferences
            </h2>
            <button
                type="button"
                class="cookie-modal-close"
                onclick="closeCookiePreferences()"
                aria-label="Close preferences"
            >
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="cookie-modal-body">
            <p class="cookie-preferences-intro">
                We use cookies to enhance your browsing experience and analyze our traffic.
                Please choose which types of cookies you're comfortable with.
            </p>

            <!-- Essential Cookies (Always On) -->
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-info">
                        <div class="cookie-category-title">
                            <i class="fa-solid fa-shield-halved"></i>
                            <h3>Essential Cookies</h3>
                            <span class="cookie-category-badge cookie-required">Required</span>
                        </div>
                        <p class="cookie-category-description">
                            These cookies are necessary for the website to function and cannot be switched off.
                            They are usually only set in response to actions made by you such as setting your
                            privacy preferences, logging in, or filling in forms.
                        </p>
                    </div>
                    <div class="cookie-toggle">
                        <input
                            type="checkbox"
                            id="cookie-essential"
                            checked
                            disabled
                            class="cookie-toggle-input"
                        >
                        <label for="cookie-essential" class="cookie-toggle-label">
                            <span class="cookie-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookies['essential'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php foreach ($cookies['essential'] ?? [] as $cookie): ?>
                        <li class="cookie-item">
                            <div class="cookie-item-header">
                                <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                                <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                            </div>
                            <p class="cookie-item-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                            <span class="cookie-item-provider"><?= htmlspecialchars($cookie['third_party']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>

            <!-- Functional Cookies -->
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-info">
                        <div class="cookie-category-title">
                            <i class="fa-solid fa-gear"></i>
                            <h3>Functional Cookies</h3>
                        </div>
                        <p class="cookie-category-description">
                            These cookies enable enhanced functionality and personalization, such as remembering
                            your theme preference or language settings. They may be set by us or by third-party
                            providers whose services we have added to our pages.
                        </p>
                    </div>
                    <div class="cookie-toggle">
                        <input
                            type="checkbox"
                            id="cookie-functional"
                            class="cookie-toggle-input"
                        >
                        <label for="cookie-functional" class="cookie-toggle-label">
                            <span class="cookie-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookies['functional'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php foreach ($cookies['functional'] ?? [] as $cookie): ?>
                        <li class="cookie-item">
                            <div class="cookie-item-header">
                                <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                                <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                            </div>
                            <p class="cookie-item-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                            <span class="cookie-item-provider"><?= htmlspecialchars($cookie['third_party']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>

            <!-- Analytics Cookies -->
            <?php if ($tenantSettings['analytics_enabled']): ?>
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-info">
                        <div class="cookie-category-title">
                            <i class="fa-solid fa-chart-line"></i>
                            <h3>Analytics Cookies</h3>
                        </div>
                        <p class="cookie-category-description">
                            These cookies help us understand how visitors interact with our website by collecting
                            and reporting information anonymously. This helps us improve our site and services.
                        </p>
                    </div>
                    <div class="cookie-toggle">
                        <input
                            type="checkbox"
                            id="cookie-analytics"
                            class="cookie-toggle-input"
                        >
                        <label for="cookie-analytics" class="cookie-toggle-label">
                            <span class="cookie-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookies['analytics'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php if (empty($cookies['analytics'])): ?>
                        <li class="cookie-list-empty">No analytics cookies are currently in use.</li>
                        <?php else: ?>
                            <?php foreach ($cookies['analytics'] as $cookie): ?>
                            <li class="cookie-item">
                                <div class="cookie-item-header">
                                    <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                                    <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                                </div>
                                <p class="cookie-item-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                                <span class="cookie-item-provider"><?= htmlspecialchars($cookie['third_party']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </details>
            </div>
            <?php endif; ?>

            <!-- Marketing Cookies -->
            <?php if ($tenantSettings['marketing_enabled']): ?>
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-info">
                        <div class="cookie-category-title">
                            <i class="fa-solid fa-bullhorn"></i>
                            <h3>Marketing Cookies</h3>
                        </div>
                        <p class="cookie-category-description">
                            These cookies may be set through our site by our advertising partners. They may be
                            used to build a profile of your interests and show you relevant content on other sites.
                        </p>
                    </div>
                    <div class="cookie-toggle">
                        <input
                            type="checkbox"
                            id="cookie-marketing"
                            class="cookie-toggle-input"
                        >
                        <label for="cookie-marketing" class="cookie-toggle-label">
                            <span class="cookie-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookies['marketing'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php if (empty($cookies['marketing'])): ?>
                        <li class="cookie-list-empty">No marketing cookies are currently in use.</li>
                        <?php else: ?>
                            <?php foreach ($cookies['marketing'] as $cookie): ?>
                            <li class="cookie-item">
                                <div class="cookie-item-header">
                                    <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                                    <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                                </div>
                                <p class="cookie-item-purpose"><?= htmlspecialchars($cookie['purpose']) ?></p>
                                <span class="cookie-item-provider"><?= htmlspecialchars($cookie['third_party']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </details>
            </div>
            <?php endif; ?>
        </div>

        <!-- Modal Footer -->
        <div class="cookie-modal-footer">
            <button
                type="button"
                class="btn btn-secondary"
                onclick="closeCookiePreferences()"
            >
                <i class="fa-solid fa-times"></i>
                Cancel
            </button>
            <button
                type="button"
                class="btn btn-primary"
                onclick="saveCustomPreferences()"
            >
                <i class="fa-solid fa-save"></i>
                Save Preferences
            </button>
        </div>
    </div>
</div>

<!-- Cookie Banner JavaScript -->
<script>
// Handle Accept All
async function handleAcceptAll() {
    const success = await window.NexusCookieConsent.acceptAll();
    if (success) {
        showCookieToast('Your cookie preferences have been saved. All cookies enabled.');
    } else {
        showCookieToast('Failed to save preferences. Please try again.', 'error');
    }
}

// Handle Reject All
async function handleRejectAll() {
    const success = await window.NexusCookieConsent.rejectAll();
    if (success) {
        showCookieToast('Only essential cookies will be used.');
    } else {
        showCookieToast('Failed to save preferences. Please try again.', 'error');
    }
}

// Open Preferences Modal
function openCookiePreferences() {
    const modal = document.getElementById('cookie-preferences-modal');
    const banner = document.getElementById('nexus-cookie-banner');

    // Hide banner if visible
    if (banner) {
        banner.classList.remove('visible');
    }

    // Show modal
    modal.style.display = 'block';
    document.body.classList.add('modal-open');

    // Load current preferences
    const consent = window.NexusCookieConsent.getConsent();
    if (consent) {
        document.getElementById('cookie-functional').checked = consent.functional || false;
        const analyticsCheckbox = document.getElementById('cookie-analytics');
        if (analyticsCheckbox) {
            analyticsCheckbox.checked = consent.analytics || false;
        }
        const marketingCheckbox = document.getElementById('cookie-marketing');
        if (marketingCheckbox) {
            marketingCheckbox.checked = consent.marketing || false;
        }
    }

    // Focus management for accessibility
    setTimeout(() => {
        modal.querySelector('.cookie-modal-close').focus();
    }, 100);
}

// Close Preferences Modal
function closeCookiePreferences() {
    const modal = document.getElementById('cookie-preferences-modal');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');

    // If no consent exists, show banner again
    if (!window.NexusCookieConsent.hasConsent()) {
        window.NexusCookieConsent.showBanner();
    }
}

// Save Custom Preferences
async function saveCustomPreferences() {
    const choices = {
        functional: document.getElementById('cookie-functional').checked
    };

    // Only include analytics/marketing if they exist (tenant may have them disabled)
    const analyticsCheckbox = document.getElementById('cookie-analytics');
    if (analyticsCheckbox) {
        choices.analytics = analyticsCheckbox.checked;
    }

    const marketingCheckbox = document.getElementById('cookie-marketing');
    if (marketingCheckbox) {
        choices.marketing = marketingCheckbox.checked;
    }

    const success = await window.NexusCookieConsent.savePreferences(choices);
    if (success) {
        closeCookiePreferences();
        showCookieToast('Your cookie preferences have been saved.');
    } else {
        showCookieToast('Failed to save preferences. Please try again.', 'error');
    }
}

// Show toast notification
function showCookieToast(message, type = 'success') {
    // Check if toast system exists
    if (typeof showToast === 'function') {
        showToast(message, type);
        return;
    }

    // Fallback: Create simple toast
    const toast = document.createElement('div');
    toast.className = `cookie-toast cookie-toast-${type}`;
    toast.innerHTML = `
        <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => toast.classList.add('visible'), 100);

    // Remove after 4 seconds
    setTimeout(() => {
        toast.classList.remove('visible');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Keyboard accessibility for modal
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('cookie-preferences-modal');
    if (modal && modal.style.display === 'block' && e.key === 'Escape') {
        closeCookiePreferences();
    }
});

// Trap focus in modal when open
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('cookie-preferences-modal');
    if (modal && modal.style.display === 'block' && e.key === 'Tab') {
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                lastElement.focus();
                e.preventDefault();
            }
        } else {
            if (document.activeElement === lastElement) {
                firstElement.focus();
                e.preventDefault();
            }
        }
    }
});
</script>
