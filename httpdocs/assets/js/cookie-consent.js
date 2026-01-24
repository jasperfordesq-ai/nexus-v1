/**
 * NEXUS Cookie Consent Manager
 * ============================
 * Handles cookie consent detection, storage, and enforcement
 * for EU ePrivacy Directive and GDPR compliance.
 *
 * Features:
 * - Checks for existing consent on page load
 * - Shows banner if no valid consent found
 * - Saves choices to server and localStorage
 * - Enforces consent before loading tracking scripts
 * - Multi-tenant and theme aware
 * - WCAG 2.1 AA accessible
 *
 * @version 1.0.0
 * @author Project NEXUS
 */

window.NexusCookieConsent = (function() {
    'use strict';

    // Configuration
    const CONFIG = {
        API_BASE: window.NEXUS_BASE || '',
        STORAGE_KEY: 'nexus_cookie_consent',
        CONSENT_DURATION: 365, // days
        CONSENT_VERSION: '1.0',
        DEBUG: true // Enable debug logging temporarily
    };

    // State
    let consentData = null;
    let bannerShown = false;
    let initialized = false;
    let analyticsLoaded = false;
    let marketingLoaded = false;

    /**
     * Log debug messages
     */
    function debug(...args) {
        if (CONFIG.DEBUG) {
            console.log('[Cookie Consent]', ...args);
        }
    }

    /**
     * Initialize the consent manager
     */
    function init() {
        if (initialized) {
            debug('Already initialized');
            return;
        }

        debug('Initializing...');
        initialized = true;

        // Load existing consent
        loadConsent();

        // Check if consent is valid
        if (!hasValidConsent()) {
            debug('No valid consent found, showing banner');
            showBanner();
        } else {
            debug('Valid consent found:', consentData);
            applyConsent();
        }
    }

    /**
     * Load consent from localStorage or server
     */
    function loadConsent() {
        // Try localStorage first (instant)
        const stored = localStorage.getItem(CONFIG.STORAGE_KEY);
        if (stored) {
            try {
                consentData = JSON.parse(stored);
                debug('Loaded from localStorage:', consentData);

                // Validate expiry
                if (isExpired(consentData)) {
                    debug('Stored consent is expired');
                    consentData = null;
                    localStorage.removeItem(CONFIG.STORAGE_KEY);
                }
            } catch (e) {
                console.error('[Cookie Consent] Failed to parse stored consent:', e);
                localStorage.removeItem(CONFIG.STORAGE_KEY);
            }
        }

        // If logged in, sync with server (async)
        if (window.NEXUS_USER_ID) {
            syncWithServer();
        }
    }

    /**
     * Check if user has valid consent
     */
    function hasValidConsent() {
        return consentData !== null && !isExpired(consentData);
    }

    /**
     * Check if consent is expired
     */
    function isExpired(consent) {
        if (!consent || !consent.expires_at) {
            return true;
        }

        const expiryTime = new Date(consent.expires_at);
        const now = new Date();

        return expiryTime < now;
    }

    /**
     * Sync consent with server (async)
     */
    async function syncWithServer() {
        try {
            debug('Syncing with server...');
            const response = await fetch(`${CONFIG.API_BASE}/api/cookie-consent`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.consent) {
                    debug('Synced from server:', data.consent);
                    consentData = data.consent;
                    localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(consentData));

                    // Apply consent if not already applied
                    if (!analyticsLoaded || !marketingLoaded) {
                        applyConsent();
                    }
                }
            } else if (response.status === 404) {
                // No consent on server, use localStorage if available
                debug('No consent found on server');
            }
        } catch (error) {
            console.error('[Cookie Consent] Server sync failed:', error);
        }
    }

    /**
     * Save consent choice to server and localStorage
     */
    async function saveConsent(choices) {
        const payload = {
            essential: true, // Always true
            functional: choices.functional || false,
            analytics: choices.analytics || false,
            marketing: choices.marketing || false,
            consent_version: CONFIG.CONSENT_VERSION,
            csrf_token: getCsrfToken()
        };

        debug('Saving consent:', payload);

        try {
            const response = await fetch(`${CONFIG.API_BASE}/api/cookie-consent`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.consent) {
                    debug('Consent saved successfully:', data.consent);
                    consentData = data.consent;
                    localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(consentData));

                    applyConsent();
                    hideBanner();

                    return true;
                }
            } else {
                console.error('[Cookie Consent] Save failed with status:', response.status);
            }
        } catch (error) {
            console.error('[Cookie Consent] Save failed:', error);
        }

        return false;
    }

    /**
     * Apply consent choices (load scripts based on permissions)
     */
    function applyConsent() {
        if (!consentData) {
            debug('No consent data to apply');
            return;
        }

        debug('Applying consent:', consentData);

        // Dispatch event for other scripts to listen to
        window.dispatchEvent(new CustomEvent('nexus:consent:ready', {
            detail: consentData
        }));

        // Load analytics if consented
        if (consentData.analytics && !analyticsLoaded) {
            loadAnalytics();
            analyticsLoaded = true;
        }

        // Load marketing scripts if consented
        if (consentData.marketing && !marketingLoaded) {
            loadMarketing();
            marketingLoaded = true;
        }

        // Set functional cookies if allowed
        if (consentData.functional) {
            enableFunctionalCookies();
        }
    }

    /**
     * Show cookie banner
     */
    function showBanner() {
        if (bannerShown) {
            debug('Banner already shown');
            return;
        }

        const banner = document.getElementById('nexus-cookie-banner');
        if (banner) {
            banner.classList.add('visible');
            banner.setAttribute('aria-hidden', 'false');
            bannerShown = true;
            debug('Banner shown');

            // Focus management for accessibility
            setTimeout(() => {
                const firstButton = banner.querySelector('button');
                if (firstButton) {
                    firstButton.focus();
                }
            }, 100);
        } else {
            debug('Banner element not found in DOM');
        }
    }

    /**
     * Hide cookie banner
     */
    function hideBanner() {
        const banner = document.getElementById('nexus-cookie-banner');
        if (banner) {
            banner.classList.remove('visible');
            banner.setAttribute('aria-hidden', 'true');
            debug('Banner hidden');
        }
    }

    /**
     * Load analytics scripts (when user consents)
     */
    function loadAnalytics() {
        debug('Loading analytics scripts...');

        // Dispatch event so tenant-specific analytics can be loaded
        window.dispatchEvent(new CustomEvent('nexus:consent:analytics', {
            detail: { allowed: true }
        }));

        // Example: Load Google Analytics if configured
        // This should be implemented based on tenant settings
        // if (window.NEXUS_ANALYTICS_ID) {
        //     const script = document.createElement('script');
        //     script.src = `https://www.googletagmanager.com/gtag/js?id=${window.NEXUS_ANALYTICS_ID}`;
        //     script.async = true;
        //     document.head.appendChild(script);
        // }
    }

    /**
     * Load marketing scripts (when user consents)
     */
    function loadMarketing() {
        debug('Loading marketing scripts...');

        // Dispatch event so tenant-specific marketing can be loaded
        window.dispatchEvent(new CustomEvent('nexus:consent:marketing', {
            detail: { allowed: true }
        }));

        // Example: Load marketing pixels if configured
        // Implementation depends on tenant configuration
    }

    /**
     * Enable functional cookies
     */
    function enableFunctionalCookies() {
        debug('Functional cookies enabled');

        // Dispatch event
        window.dispatchEvent(new CustomEvent('nexus:consent:functional', {
            detail: { allowed: true }
        }));
    }

    /**
     * Get CSRF token from meta tag
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /**
     * Accept all cookies
     */
    async function acceptAll() {
        debug('Accept all clicked');
        return await saveConsent({
            functional: true,
            analytics: true,
            marketing: true
        });
    }

    /**
     * Reject all non-essential cookies
     */
    async function rejectAll() {
        debug('Reject all clicked');
        return await saveConsent({
            functional: false,
            analytics: false,
            marketing: false
        });
    }

    /**
     * Save custom preferences
     */
    async function savePreferences(choices) {
        debug('Saving custom preferences:', choices);
        return await saveConsent(choices);
    }

    /**
     * Check if user has consented to a category
     */
    function hasConsent(category) {
        // Essential is always allowed
        if (category === 'essential') {
            return true;
        }

        if (!consentData) {
            return false;
        }

        return consentData[category] === true || consentData[category] === 1;
    }

    /**
     * Get current consent data
     */
    function getConsent() {
        return consentData;
    }

    // Public API
    const publicAPI = {
        // Initialization
        init: init,

        // Status checks
        hasConsent: hasValidConsent,
        hasConsentFor: hasConsent,
        canUseEssential: () => true,
        canUseFunctional: () => hasConsent('functional'),
        canUseAnalytics: () => hasConsent('analytics'),
        canUseMarketing: () => hasConsent('marketing'),

        // Actions
        acceptAll: acceptAll,
        rejectAll: rejectAll,
        savePreferences: savePreferences,

        // Data access
        getConsent: getConsent,

        // UI control
        showBanner: showBanner,
        hideBanner: hideBanner,

        // Debug
        debug: (enable) => { CONFIG.DEBUG = enable; }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

    return publicAPI;
})();

/**
 * Helper function to check consent before setting cookies
 * Use this wrapper when setting functional cookies
 */
function setCookieWithConsent(name, value, days = 365, path = '/') {
    if (window.NexusCookieConsent.canUseFunctional()) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=${path};SameSite=Lax`;
        return true;
    } else {
        // Use localStorage as fallback if no consent
        try {
            localStorage.setItem(name, value);
            return false; // Cookie not set, but value stored
        } catch (e) {
            console.warn('[Cookie Consent] Cannot set cookie or localStorage:', name);
            return false;
        }
    }
}

/**
 * Helper function to get cookie value (checks both cookies and localStorage)
 */
function getCookieOrStorage(name) {
    // Try cookie first
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [key, value] = cookie.trim().split('=');
        if (key === name) {
            return value;
        }
    }

    // Fallback to localStorage
    try {
        return localStorage.getItem(name);
    } catch (e) {
        return null;
    }
}
