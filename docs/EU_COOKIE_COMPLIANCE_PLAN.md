# EU Cookie Compliance Implementation Plan
**Project NEXUS - Timebanking Platform**

**Version:** 1.0
**Created:** 2026-01-24
**Status:** In Progress
**Priority:** CRITICAL - Legal Compliance Required

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Legal Requirements](#legal-requirements)
3. [System Architecture](#system-architecture)
4. [Database Schema](#database-schema)
5. [Backend Implementation](#backend-implementation)
6. [Frontend Implementation](#frontend-implementation)
7. [Multi-Tenant Integration](#multi-tenant-integration)
8. [Theme Integration](#theme-integration)
9. [Testing Strategy](#testing-strategy)
10. [Deployment Plan](#deployment-plan)
11. [Maintenance & Updates](#maintenance--updates)
12. [Progress Tracking](#progress-tracking)

---

## Executive Summary

### Objective
Implement a fully compliant EU cookie consent system that meets ePrivacy Directive and GDPR requirements while integrating seamlessly with Project NEXUS's multi-tenant, dual-theme architecture.

### Scope
- Cookie consent banner with granular controls
- Server-side consent recording and validation
- Client-side consent enforcement
- Cookie policy documentation
- Preference management interface
- Integration with both Modern and CivicOne themes
- Full tenant awareness
- Admin analytics dashboard

### Timeline
- **Phase 1:** Database & Backend (Week 1) - Days 1-5
- **Phase 2:** Frontend Core (Week 2) - Days 6-10
- **Phase 3:** Theme Integration (Week 3) - Days 11-15
- **Phase 4:** Testing & Polish (Week 4) - Days 16-20
- **Phase 5:** Deployment (Week 5) - Days 21-25

### Current Status
- ✅ Database schema exists (`cookie_consents` table)
- ✅ GdprService foundation in place
- ❌ No cookie banner UI
- ❌ No consent enforcement
- ❌ No cookie policy page
- ❌ Cookies set without consent

---

## Legal Requirements

### ePrivacy Directive (Cookie Law)

**Article 5(3) Compliance:**

1. **Prior Consent** ✓ (To Implement)
   - No cookies except essential may be set before user consent
   - Consent must be freely given, specific, informed, and unambiguous
   - Implementation: Banner blocks scripts until consent given

2. **Clear Information** ✓ (To Implement)
   - Users must know what cookies do, how long they last, who sets them
   - Implementation: Cookie policy page + banner descriptions

3. **Granular Choice** ✓ (To Implement)
   - Users can accept/reject by category
   - Implementation: 4 categories (Essential, Functional, Analytics, Marketing)

4. **Easy Withdrawal** ✓ (To Implement)
   - Users can change preferences anytime
   - Implementation: Footer link + preference center

### GDPR Requirements

**Relevant Articles:**

- **Article 6(1)(a):** Consent as legal basis for non-essential cookies
- **Article 7:** Conditions for consent (demonstrable, withdrawable)
- **Article 13:** Information to be provided (transparency)
- **Article 21:** Right to object

**Implementation Strategy:**

- Essential cookies: Legitimate interest (Art. 6(1)(f)) - No consent required
- Functional cookies: Consent (Art. 6(1)(a)) - Opt-in required
- Analytics cookies: Consent (Art. 6(1)(a)) - Opt-in required
- Marketing cookies: Consent (Art. 6(1)(a)) - Opt-in required

---

## System Architecture

### High-Level Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        User Visits Site                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
                ┌────────────────────────┐
                │ Check Consent Status   │
                │ (localStorage + Server)│
                └────────┬───────────────┘
                         │
           ┌─────────────┴─────────────┐
           ▼                           ▼
    ┌──────────────┐          ┌───────────────┐
    │ Has Consent  │          │  No Consent   │
    │   Record?    │          │   Found       │
    └──────┬───────┘          └───────┬───────┘
           │                          │
           │                          ▼
           │               ┌──────────────────┐
           │               │ Show Cookie      │
           │               │ Banner           │
           │               └─────────┬────────┘
           │                         │
           │              ┌──────────┴──────────┐
           │              ▼                     ▼
           │       ┌─────────────┐      ┌─────────────┐
           │       │Accept All/  │      │Manage       │
           │       │Reject All   │      │Preferences  │
           │       └──────┬──────┘      └──────┬──────┘
           │              │                    │
           │              └──────────┬─────────┘
           │                         ▼
           │              ┌──────────────────────┐
           │              │ POST /api/cookie-    │
           │              │      consent         │
           │              └──────────┬───────────┘
           │                         │
           │                         ▼
           │              ┌──────────────────────┐
           │              │ Save to Database +   │
           │              │ localStorage         │
           │              └──────────┬───────────┘
           │                         │
           └─────────────────────────┘
                         │
                         ▼
           ┌──────────────────────────┐
           │ Conditionally Load       │
           │ Scripts Based on Consent │
           └──────────────────────────┘
```

### Component Overview

| Component | Purpose | Location |
|-----------|---------|----------|
| **Database Layer** | Store consent records | `cookie_consents` table |
| **Service Layer** | Business logic | `src/Services/CookieConsentService.php` |
| **API Layer** | REST endpoints | `src/Controllers/Api/CookieConsentController.php` |
| **Client Library** | JS consent manager | `httpdocs/assets/js/cookie-consent.js` |
| **Banner UI** | User interface | `views/{theme}/partials/cookie-banner.php` |
| **Policy Page** | Documentation | `views/{theme}/pages/cookie-policy.php` |
| **Preference Center** | Management UI | `views/{theme}/pages/cookie-preferences.php` |
| **Admin Dashboard** | Analytics | `views/admin/cookie-consent/dashboard.php` |

---

## Database Schema

### Existing Table Review

**Table:** `cookie_consents` (from `GDPR_COMPLIANCE.sql`)

```sql
CREATE TABLE IF NOT EXISTS cookie_consents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    tenant_id INT NOT NULL,
    essential BOOLEAN DEFAULT TRUE,
    analytics BOOLEAN DEFAULT FALSE,
    marketing BOOLEAN DEFAULT FALSE,
    functional BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    consent_string TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Schema Enhancements Needed

**Migration:** `2026_01_24_enhance_cookie_consents.sql`

```sql
-- Add expiry tracking
ALTER TABLE cookie_consents
ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER consent_string,
ADD COLUMN IF NOT EXISTS consent_version VARCHAR(20) DEFAULT '1.0' AFTER expires_at,
ADD COLUMN IF NOT EXISTS last_updated_by_user DATETIME NULL AFTER updated_at,
ADD COLUMN IF NOT EXISTS withdrawal_date DATETIME NULL AFTER last_updated_by_user,
ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'web' AFTER withdrawal_date COMMENT 'web|mobile|api',
ADD INDEX idx_expires_at (expires_at),
ADD INDEX idx_consent_version (consent_version);

-- Cookie inventory table (what cookies we actually use)
CREATE TABLE IF NOT EXISTS cookie_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cookie_name VARCHAR(255) NOT NULL,
    category ENUM('essential', 'functional', 'analytics', 'marketing') NOT NULL,
    purpose TEXT NOT NULL,
    duration VARCHAR(100) NOT NULL COMMENT 'Session, 1 year, etc',
    third_party VARCHAR(255) NULL COMMENT 'First-party or provider name',
    tenant_id INT NULL COMMENT 'NULL = global, or specific tenant',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cookie_tenant (cookie_name, tenant_id),
    INDEX idx_category (category),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert current cookies
INSERT INTO cookie_inventory (cookie_name, category, purpose, duration, third_party, tenant_id) VALUES
('PHPSESSID', 'essential', 'Session management and user authentication', 'Session (until browser closes)', 'First-party', NULL),
('nexus_mode', 'functional', 'Remember user theme preference (dark/light mode)', '1 year', 'First-party', NULL),
('nexus_active_layout', 'functional', 'Remember user layout preference (modern/civicone)', 'Session', 'First-party', NULL),
('cookie_consent', 'essential', 'Store user cookie consent preferences', '1 year', 'First-party', NULL)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Consent audit log (detailed tracking for compliance)
CREATE TABLE IF NOT EXISTS cookie_consent_audit (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    consent_id BIGINT NOT NULL,
    action ENUM('created', 'updated', 'withdrawn', 'expired') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consent_id (consent_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (consent_id) REFERENCES cookie_consents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tenant Configuration Table

```sql
-- Tenant-specific cookie settings
CREATE TABLE IF NOT EXISTS tenant_cookie_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL UNIQUE,
    banner_message TEXT NULL COMMENT 'Custom banner text',
    analytics_enabled BOOLEAN DEFAULT FALSE COMMENT 'Tenant uses analytics',
    marketing_enabled BOOLEAN DEFAULT FALSE COMMENT 'Tenant uses marketing cookies',
    analytics_provider VARCHAR(100) NULL COMMENT 'e.g., Google Analytics, Matomo',
    analytics_id VARCHAR(255) NULL COMMENT 'Tracking ID',
    consent_validity_days INT DEFAULT 365 COMMENT 'How long consent is valid',
    auto_block_scripts BOOLEAN DEFAULT TRUE COMMENT 'Block scripts until consent',
    strict_mode BOOLEAN DEFAULT TRUE COMMENT 'Require explicit consent vs implied',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings for existing tenants
INSERT IGNORE INTO tenant_cookie_settings (tenant_id)
SELECT id FROM tenants;
```

---

## Backend Implementation

### File Structure

```
src/
├── Controllers/
│   ├── Api/
│   │   └── CookieConsentController.php  (NEW)
│   ├── CookiePolicyController.php        (NEW)
│   └── CookiePreferencesController.php   (NEW)
├── Services/
│   ├── CookieConsentService.php          (NEW)
│   └── CookieInventoryService.php        (NEW)
└── Middleware/
    └── CookieConsentMiddleware.php       (NEW - Optional)
```

### 1. CookieConsentService.php

**Location:** `src/Services/CookieConsentService.php`

**Responsibilities:**
- Record consent choices
- Validate consent (not expired, matches current version)
- Retrieve consent preferences
- Handle consent withdrawal
- Tenant-aware operations

**Key Methods:**

```php
<?php
namespace Nexus\Services;

class CookieConsentService
{
    // Record new consent
    public static function recordConsent(array $data): array;

    // Get consent by session or user
    public static function getConsent(?int $userId = null, ?string $sessionId = null): ?array;

    // Check if specific category is allowed
    public static function hasConsent(string $category): bool;

    // Update existing consent
    public static function updateConsent(int $consentId, array $categories): bool;

    // Withdraw consent
    public static function withdrawConsent(int $consentId): bool;

    // Check if consent is expired
    public static function isConsentValid(array $consent): bool;

    // Get tenant cookie settings
    public static function getTenantSettings(int $tenantId): array;

    // Log consent change to audit trail
    private static function auditConsentChange(int $consentId, string $action, array $oldValues, array $newValues): void;
}
```

### 2. CookieConsentController.php (API)

**Location:** `src/Controllers/Api/CookieConsentController.php`

**Endpoints:**

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/cookie-consent` | Get current consent status |
| POST | `/api/cookie-consent` | Save new consent |
| PUT | `/api/cookie-consent/{id}` | Update consent |
| DELETE | `/api/cookie-consent/{id}` | Withdraw consent |
| GET | `/api/cookie-consent/inventory` | Get cookie list for banner |

**Response Format:**

```json
{
  "success": true,
  "consent": {
    "id": 12345,
    "essential": true,
    "functional": true,
    "analytics": false,
    "marketing": false,
    "created_at": "2026-01-24T10:30:00Z",
    "expires_at": "2027-01-24T10:30:00Z",
    "version": "1.0"
  }
}
```

### 3. CookieInventoryService.php

**Location:** `src/Services/CookieInventoryService.php`

**Purpose:** Manage the list of cookies the platform uses

**Key Methods:**

```php
<?php
namespace Nexus\Services;

class CookieInventoryService
{
    // Get all cookies for a category
    public static function getCookiesByCategory(string $category, ?int $tenantId = null): array;

    // Get all cookies (for policy page)
    public static function getAllCookies(?int $tenantId = null): array;

    // Add new cookie to inventory (admin only)
    public static function addCookie(array $data): int;

    // Update cookie details
    public static function updateCookie(int $id, array $data): bool;

    // Get cookies for banner display
    public static function getBannerCookieList(?int $tenantId = null): array;
}
```

### 4. Routes Configuration

**File:** `httpdocs/routes.php`

**Add these routes:**

```php
// Cookie Consent API Endpoints
$router->get('/api/cookie-consent', [
    'controller' => 'Nexus\Controllers\Api\CookieConsentController@show',
    'middleware' => []
]);

$router->post('/api/cookie-consent', [
    'controller' => 'Nexus\Controllers\Api\CookieConsentController@store',
    'middleware' => []
]);

$router->put('/api/cookie-consent/{id}', [
    'controller' => 'Nexus\Controllers\Api\CookieConsentController@update',
    'middleware' => []
]);

$router->delete('/api/cookie-consent/{id}', [
    'controller' => 'Nexus\Controllers\Api\CookieConsentController@withdraw',
    'middleware' => []
]);

$router->get('/api/cookie-consent/inventory', [
    'controller' => 'Nexus\Controllers\Api\CookieConsentController@inventory',
    'middleware' => []
]);

// Cookie Policy Page
$router->get('/legal/cookies', [
    'controller' => 'Nexus\Controllers\CookiePolicyController@index',
    'middleware' => []
]);

// Cookie Preferences Management
$router->get('/cookie-preferences', [
    'controller' => 'Nexus\Controllers\CookiePreferencesController@index',
    'middleware' => []
]);

// Admin: Cookie Consent Dashboard
$router->get('/admin/cookie-consent/dashboard', [
    'controller' => 'Nexus\Controllers\Admin\CookieConsentController@dashboard',
    'middleware' => ['admin']
]);

$router->get('/admin/cookie-consent/analytics', [
    'controller' => 'Nexus\Controllers\Admin\CookieConsentController@analytics',
    'middleware' => ['admin']
]);

$router->get('/admin/cookie-consent/inventory', [
    'controller' => 'Nexus\Controllers\Admin\CookieConsentController@inventory',
    'middleware' => ['admin']
]);
```

---

## Frontend Implementation

### File Structure

```
httpdocs/assets/
├── js/
│   ├── cookie-consent.js              (NEW - Core library)
│   ├── cookie-consent.min.js          (NEW - Minified)
│   ├── cookie-banner.js               (NEW - Banner interactions)
│   └── cookie-banner.min.js           (NEW - Minified)
├── css/
│   ├── cookie-banner.css              (NEW - Modern theme)
│   ├── cookie-banner.min.css          (NEW - Minified)
│   └── civicone/
│       ├── cookie-banner.css          (NEW - CivicOne theme)
│       └── cookie-banner.min.css      (NEW - Minified)

views/
├── modern/
│   ├── partials/
│   │   └── cookie-banner.php          (NEW)
│   └── pages/
│       ├── cookie-policy.php          (NEW)
│       └── cookie-preferences.php     (NEW)
└── civicone/
    ├── partials/
    │   └── cookie-banner.php          (NEW)
    └── pages/
        ├── cookie-policy.php          (NEW)
        └── cookie-preferences.php     (NEW)
```

### 1. Cookie Consent JavaScript Library

**File:** `httpdocs/assets/js/cookie-consent.js`

**Core Features:**

```javascript
/**
 * NEXUS Cookie Consent Manager
 * Handles cookie consent detection, storage, and enforcement
 * Multi-tenant and theme aware
 */
window.NexusCookieConsent = (function() {
    'use strict';

    const CONFIG = {
        API_BASE: window.NEXUS_BASE || '',
        STORAGE_KEY: 'nexus_cookie_consent',
        CONSENT_DURATION: 365, // days
        CONSENT_VERSION: '1.0'
    };

    let consentData = null;
    let bannerShown = false;

    // Initialize on page load
    function init() {
        loadConsent();

        if (!hasValidConsent()) {
            showBanner();
        } else {
            applyConsent();
        }
    }

    // Load consent from localStorage or server
    function loadConsent() {
        // Try localStorage first (fast)
        const stored = localStorage.getItem(CONFIG.STORAGE_KEY);
        if (stored) {
            try {
                consentData = JSON.parse(stored);

                // Validate expiry
                if (isExpired(consentData)) {
                    consentData = null;
                    localStorage.removeItem(CONFIG.STORAGE_KEY);
                }
            } catch (e) {
                console.error('[Cookie Consent] Failed to parse stored consent:', e);
            }
        }

        // If logged in, sync with server
        if (window.NEXUS_USER_ID) {
            syncWithServer();
        }
    }

    // Check if user has valid consent
    function hasValidConsent() {
        return consentData !== null && !isExpired(consentData);
    }

    // Check if consent is expired
    function isExpired(consent) {
        if (!consent.expires_at) return true;
        return new Date(consent.expires_at) < new Date();
    }

    // Sync consent with server (async)
    async function syncWithServer() {
        try {
            const response = await fetch(`${CONFIG.API_BASE}/api/cookie-consent`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.consent) {
                    consentData = data.consent;
                    localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(consentData));
                }
            }
        } catch (error) {
            console.error('[Cookie Consent] Server sync failed:', error);
        }
    }

    // Save consent choice
    async function saveConsent(choices) {
        const payload = {
            essential: true, // Always true
            functional: choices.functional || false,
            analytics: choices.analytics || false,
            marketing: choices.marketing || false,
            consent_version: CONFIG.CONSENT_VERSION
        };

        try {
            const response = await fetch(`${CONFIG.API_BASE}/api/cookie-consent`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.consent) {
                    consentData = data.consent;
                    localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(consentData));
                    applyConsent();
                    hideBanner();
                    return true;
                }
            }
        } catch (error) {
            console.error('[Cookie Consent] Save failed:', error);
        }

        return false;
    }

    // Apply consent (load scripts based on choices)
    function applyConsent() {
        if (!consentData) return;

        // Dispatch event for other scripts to listen to
        window.dispatchEvent(new CustomEvent('nexus:consent:ready', {
            detail: consentData
        }));

        // Load analytics if consented
        if (consentData.analytics) {
            loadAnalytics();
        }

        // Load marketing scripts if consented
        if (consentData.marketing) {
            loadMarketing();
        }
    }

    // Show cookie banner
    function showBanner() {
        if (bannerShown) return;

        const banner = document.getElementById('nexus-cookie-banner');
        if (banner) {
            banner.classList.add('visible');
            banner.setAttribute('aria-hidden', 'false');
            bannerShown = true;
        }
    }

    // Hide cookie banner
    function hideBanner() {
        const banner = document.getElementById('nexus-cookie-banner');
        if (banner) {
            banner.classList.remove('visible');
            banner.setAttribute('aria-hidden', 'true');
        }
    }

    // Load analytics scripts (placeholder)
    function loadAnalytics() {
        // Check if tenant has analytics enabled
        // Load Google Analytics, Matomo, etc.
        console.warn('[Cookie Consent] Analytics loading not yet implemented');
    }

    // Load marketing scripts (placeholder)
    function loadMarketing() {
        // Load marketing pixels, retargeting scripts, etc.
        console.warn('[Cookie Consent] Marketing scripts loading not yet implemented');
    }

    // Public API
    return {
        init: init,
        hasConsent: hasValidConsent,
        canUseEssential: () => true, // Always allowed
        canUseFunctional: () => consentData?.functional || false,
        canUseAnalytics: () => consentData?.analytics || false,
        canUseMarketing: () => consentData?.marketing || false,
        acceptAll: async () => {
            return await saveConsent({
                functional: true,
                analytics: true,
                marketing: true
            });
        },
        rejectAll: async () => {
            return await saveConsent({
                functional: false,
                analytics: false,
                marketing: false
            });
        },
        savePreferences: saveConsent,
        getConsent: () => consentData,
        showBanner: showBanner
    };
})();

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.NexusCookieConsent.init();
    });
} else {
    window.NexusCookieConsent.init();
}
```

### 2. Cookie Banner HTML (Modern Theme)

**File:** `views/modern/partials/cookie-banner.php`

```php
<?php
/**
 * Cookie Consent Banner - Modern Theme
 * Displays on first visit, allows granular consent management
 */

use Nexus\Core\TenantContext;
use Nexus\Services\CookieInventoryService;

$basePath = TenantContext::getBasePath();
$tenantId = TenantContext::getId();

// Get cookie categories for display
$cookieCategories = CookieInventoryService::getBannerCookieList($tenantId);
?>

<!-- Cookie Consent Banner -->
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
                    This site uses cookies
                </h2>
                <p id="cookie-banner-description" class="cookie-banner-description">
                    We use essential cookies to make our site work. With your consent, we may also use
                    non-essential cookies to improve user experience and analyze website traffic.
                    By clicking "Accept All", you agree to our use of cookies.
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

            <button
                type="button"
                class="btn btn-secondary cookie-btn-reject-all"
                onclick="handleRejectAll()"
                aria-label="Reject non-essential cookies"
            >
                <i class="fa-solid fa-times"></i>
                Reject All
            </button>

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
                    <div class="cookie-category-title">
                        <h3>
                            <i class="fa-solid fa-shield-halved"></i>
                            Essential Cookies
                        </h3>
                        <span class="cookie-category-badge cookie-required">Required</span>
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
                <p class="cookie-category-description">
                    These cookies are necessary for the website to function and cannot be switched off.
                    They are usually only set in response to actions made by you such as setting your
                    privacy preferences, logging in, or filling in forms.
                </p>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookieCategories['essential'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php foreach ($cookieCategories['essential'] ?? [] as $cookie): ?>
                        <li>
                            <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                            <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                            <p><?= htmlspecialchars($cookie['purpose']) ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>

            <!-- Functional Cookies -->
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-title">
                        <h3>
                            <i class="fa-solid fa-gear"></i>
                            Functional Cookies
                        </h3>
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
                <p class="cookie-category-description">
                    These cookies enable enhanced functionality and personalization, such as remembering
                    your theme preference or language settings. They may be set by us or by third-party
                    providers whose services we have added to our pages.
                </p>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookieCategories['functional'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php foreach ($cookieCategories['functional'] ?? [] as $cookie): ?>
                        <li>
                            <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                            <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                            <p><?= htmlspecialchars($cookie['purpose']) ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>

            <!-- Analytics Cookies -->
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-title">
                        <h3>
                            <i class="fa-solid fa-chart-line"></i>
                            Analytics Cookies
                        </h3>
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
                <p class="cookie-category-description">
                    These cookies help us understand how visitors interact with our website by collecting
                    and reporting information anonymously. This helps us improve our site and services.
                </p>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookieCategories['analytics'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php if (empty($cookieCategories['analytics'])): ?>
                        <li class="cookie-list-empty">No analytics cookies are currently in use.</li>
                        <?php else: ?>
                            <?php foreach ($cookieCategories['analytics'] as $cookie): ?>
                            <li>
                                <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                                <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                                <p><?= htmlspecialchars($cookie['purpose']) ?></p>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </details>
            </div>

            <!-- Marketing Cookies -->
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <div class="cookie-category-title">
                        <h3>
                            <i class="fa-solid fa-bullhorn"></i>
                            Marketing Cookies
                        </h3>
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
                <p class="cookie-category-description">
                    These cookies may be set through our site by our advertising partners. They may be
                    used to build a profile of your interests and show you relevant content on other sites.
                </p>
                <details class="cookie-category-details">
                    <summary>View cookies (<?= count($cookieCategories['marketing'] ?? []) ?>)</summary>
                    <ul class="cookie-list">
                        <?php if (empty($cookieCategories['marketing'])): ?>
                        <li class="cookie-list-empty">No marketing cookies are currently in use.</li>
                        <?php else: ?>
                            <?php foreach ($cookieCategories['marketing'] as $cookie): ?>
                            <li>
                                <strong><?= htmlspecialchars($cookie['cookie_name']) ?></strong>
                                <span class="cookie-duration"><?= htmlspecialchars($cookie['duration']) ?></span>
                                <p><?= htmlspecialchars($cookie['purpose']) ?></p>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </details>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="cookie-modal-footer">
            <button
                type="button"
                class="btn btn-secondary"
                onclick="closeCookiePreferences()"
            >
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
        showCookieToast('Your cookie preferences have been saved.');
    }
}

// Handle Reject All
async function handleRejectAll() {
    const success = await window.NexusCookieConsent.rejectAll();
    if (success) {
        showCookieToast('Only essential cookies will be used.');
    }
}

// Open Preferences Modal
function openCookiePreferences() {
    const modal = document.getElementById('cookie-preferences-modal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Load current preferences
    const consent = window.NexusCookieConsent.getConsent();
    if (consent) {
        document.getElementById('cookie-functional').checked = consent.functional || false;
        document.getElementById('cookie-analytics').checked = consent.analytics || false;
        document.getElementById('cookie-marketing').checked = consent.marketing || false;
    }

    // Focus management for accessibility
    modal.querySelector('.cookie-modal-close').focus();
}

// Close Preferences Modal
function closeCookiePreferences() {
    const modal = document.getElementById('cookie-preferences-modal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// Save Custom Preferences
async function saveCustomPreferences() {
    const choices = {
        functional: document.getElementById('cookie-functional').checked,
        analytics: document.getElementById('cookie-analytics').checked,
        marketing: document.getElementById('cookie-marketing').checked
    };

    const success = await window.NexusCookieConsent.savePreferences(choices);
    if (success) {
        closeCookiePreferences();
        showCookieToast('Your cookie preferences have been saved.');
    }
}

// Show toast notification
function showCookieToast(message) {
    // Use existing toast system if available
    if (window.showToast) {
        window.showToast(message, 'success');
    } else {
        alert(message);
    }
}

// Keyboard accessibility for modal
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('cookie-preferences-modal');
    if (modal.style.display === 'block' && e.key === 'Escape') {
        closeCookiePreferences();
    }
});
</script>
```

---

## Multi-Tenant Integration

### Tenant-Aware Features

1. **Tenant-Specific Cookie Inventory**
   - Each tenant can have custom cookies
   - Filter cookies by `tenant_id` in inventory

2. **Tenant Cookie Settings**
   - Custom banner message per tenant
   - Enable/disable analytics per tenant
   - Configure analytics provider per tenant

3. **Consent Scope**
   - All consent records include `tenant_id`
   - Users may have different consent per tenant
   - Cross-tenant consent not shared

### TenantContext Integration

**Example Service Method:**

```php
public static function recordConsent(array $data): array
{
    $tenantId = TenantContext::getId();
    $userId = Auth::id();
    $sessionId = session_id();

    // Check tenant settings
    $tenantSettings = self::getTenantSettings($tenantId);

    // Only save categories that tenant actually uses
    $categories = [
        'essential' => true,
        'functional' => $data['functional'] ?? false,
        'analytics' => ($tenantSettings['analytics_enabled'] && ($data['analytics'] ?? false)),
        'marketing' => ($tenantSettings['marketing_enabled'] && ($data['marketing'] ?? false))
    ];

    // Record consent with tenant_id
    Database::query(
        "INSERT INTO cookie_consents
         (session_id, user_id, tenant_id, essential, functional, analytics, marketing,
          ip_address, user_agent, consent_string, expires_at, consent_version)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?)",
        [
            $sessionId,
            $userId,
            $tenantId,
            $categories['essential'],
            $categories['functional'],
            $categories['analytics'],
            $categories['marketing'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($categories),
            $tenantSettings['consent_validity_days'] ?? 365,
            self::CONSENT_VERSION
        ]
    );

    return [
        'id' => Database::lastInsertId(),
        ...$categories
    ];
}
```

---

## Theme Integration

### Dual Theme Support

**Modern Theme:**
- Contemporary design with glassmorphism
- Animations and transitions
- Bold colors

**CivicOne Theme:**
- GOV.UK Design System compliant
- WCAG 2.1 AA accessibility
- Minimal animations
- High contrast

### Theme Detection

**In PHP:**
```php
<?php
$layout = layout(); // Returns 'modern' or 'civicone'
require __DIR__ . "/partials/cookie-banner-{$layout}.php";
?>
```

**In CSS:**
```css
/* Modern theme specific */
[data-layout="modern"] .cookie-banner {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* CivicOne theme specific */
[data-layout="civicone"] .cookie-banner {
    background: #f3f2f1;
    border-top: 5px solid #1d70b8;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
}
```

### CivicOne GOV.UK Compliance

**GOV.UK Cookie Banner Pattern:**

```php
<!-- GOV.UK Cookie Banner (CivicOne Theme) -->
<div class="govuk-cookie-banner" role="region" aria-label="Cookie banner">
    <div class="govuk-cookie-banner__message">
        <div class="govuk-width-container">
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">
                    <h2 class="govuk-cookie-banner__heading govuk-heading-m">
                        Cookies on <?= htmlspecialchars($tenantName) ?>
                    </h2>
                    <div class="govuk-cookie-banner__content">
                        <p class="govuk-body">
                            We use some essential cookies to make this service work.
                        </p>
                        <p class="govuk-body">
                            We'd also like to use analytics cookies so we can understand
                            how you use the service and make improvements.
                        </p>
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
                    Accept analytics cookies
                </button>
                <button
                    type="button"
                    class="govuk-button govuk-button--secondary"
                    data-module="govuk-button"
                    onclick="handleRejectAll()"
                >
                    Reject analytics cookies
                </button>
                <a
                    class="govuk-link"
                    href="<?= $basePath ?>/legal/cookies"
                >
                    View cookie policy
                </a>
            </div>
        </div>
    </div>
</div>
```

**Accessibility Requirements:**
- Keyboard navigable (Tab order logical)
- Screen reader compatible (ARIA labels)
- Focus indicators visible
- Contrast ratio minimum 4.5:1
- No reliance on color alone

---

## Testing Strategy

### Test Scenarios

#### 1. First Visit (No Consent)
- [ ] Banner appears immediately
- [ ] No non-essential cookies set before choice
- [ ] All three buttons functional (Accept/Reject/Manage)
- [ ] Banner accessible via keyboard
- [ ] Screen reader announces banner

#### 2. Accept All
- [ ] Consent saved to database
- [ ] Consent saved to localStorage
- [ ] Banner disappears
- [ ] All cookie categories set to true
- [ ] Functional cookies can be set
- [ ] Toast confirmation shown

#### 3. Reject All
- [ ] Consent saved with all optional categories false
- [ ] Only essential cookies allowed
- [ ] Functional cookies NOT set
- [ ] Banner disappears
- [ ] Toast confirmation shown

#### 4. Manage Preferences
- [ ] Modal opens
- [ ] Current preferences loaded
- [ ] All toggles functional
- [ ] Essential toggle disabled
- [ ] Save button works
- [ ] Cancel button closes modal
- [ ] Escape key closes modal

#### 5. Returning User (Valid Consent)
- [ ] Banner does not appear
- [ ] Consent loaded from localStorage
- [ ] Server consent synced (if logged in)
- [ ] Preferences respected

#### 6. Expired Consent
- [ ] Banner appears again after expiry
- [ ] Old consent ignored
- [ ] New consent required

#### 7. Multi-Tenant
- [ ] Consent scoped by tenant
- [ ] Different consents for different tenants
- [ ] Tenant-specific cookies shown
- [ ] Tenant settings respected

#### 8. Theme Switching
- [ ] Banner styled correctly in Modern theme
- [ ] Banner styled correctly in CivicOne theme
- [ ] No layout breaks when switching themes

#### 9. Mobile Responsiveness
- [ ] Banner displays correctly on mobile
- [ ] Modal scrollable on small screens
- [ ] Touch targets minimum 44x44px
- [ ] No horizontal scroll

#### 10. Browser Compatibility
- [ ] Works in Chrome
- [ ] Works in Firefox
- [ ] Works in Safari
- [ ] Works in Edge
- [ ] localStorage fallback for old browsers

### Accessibility Testing

**Tools:**
- WAVE (Web Accessibility Evaluation Tool)
- axe DevTools
- Screen reader (NVDA/JAWS/VoiceOver)
- Keyboard-only navigation

**Checklist:**
- [ ] All interactive elements keyboard accessible
- [ ] Focus order logical
- [ ] Focus indicators visible
- [ ] ARIA labels correct
- [ ] Color contrast meets WCAG AA
- [ ] Screen reader announces all content
- [ ] No keyboard traps

### Performance Testing

- [ ] Banner loads without blocking page render
- [ ] JavaScript library < 20KB minified
- [ ] CSS < 10KB minified
- [ ] No CLS (Cumulative Layout Shift)
- [ ] API response time < 200ms

---

## Deployment Plan

### Pre-Deployment Checklist

- [ ] Database migration tested
- [ ] All backend services unit tested
- [ ] API endpoints tested
- [ ] Frontend tested on all browsers
- [ ] Accessibility audit passed
- [ ] CSS added to purgecss.config.js
- [ ] JavaScript minified
- [ ] Documentation updated
- [ ] Privacy policy updated
- [ ] Cookie policy page created

### Deployment Steps

**Step 1: Database Migration**
```bash
# Run migration on production
ssh jasper@35.205.239.67
cd /var/www/vhosts/project-nexus.ie
php scripts/safe_migrate.php
```

**Step 2: Deploy Backend Files**
```bash
# Deploy PHP files
npm run deploy -- src/Services/CookieConsentService.php
npm run deploy -- src/Services/CookieInventoryService.php
npm run deploy -- src/Controllers/Api/CookieConsentController.php
npm run deploy -- src/Controllers/CookiePolicyController.php
npm run deploy -- src/Controllers/CookiePreferencesController.php
```

**Step 3: Deploy Frontend Files**
```bash
# Deploy JavaScript
npm run deploy -- httpdocs/assets/js/cookie-consent.min.js
npm run deploy -- httpdocs/assets/js/cookie-banner.min.js

# Deploy CSS
npm run deploy -- httpdocs/assets/css/cookie-banner.min.css
npm run deploy -- httpdocs/assets/css/civicone/cookie-banner.min.css

# Deploy views
npm run deploy -- views/modern/partials/cookie-banner.php
npm run deploy -- views/civicone/partials/cookie-banner.php
npm run deploy -- views/modern/pages/cookie-policy.php
npm run deploy -- views/civicone/pages/cookie-policy.php
```

**Step 4: Update Routes**
```bash
npm run deploy -- httpdocs/routes.php
```

**Step 5: Update Headers**
```bash
# Update to include cookie banner
npm run deploy -- views/layouts/modern/header.php
npm run deploy -- views/layouts/civicone/header.php
```

**Step 6: Verify**
- [ ] Visit site in incognito mode
- [ ] Verify banner appears
- [ ] Test all three consent options
- [ ] Check database for consent records
- [ ] Verify no JavaScript errors
- [ ] Test on mobile device

### Rollback Plan

If issues occur:

1. **Disable banner temporarily:**
   ```css
   #nexus-cookie-banner { display: none !important; }
   ```

2. **Revert database migration:**
   ```sql
   -- Backup first
   DROP TABLE IF EXISTS cookie_consent_audit;
   DROP TABLE IF EXISTS cookie_inventory;
   DROP TABLE IF EXISTS tenant_cookie_settings;
   ALTER TABLE cookie_consents DROP COLUMN expires_at;
   -- etc.
   ```

3. **Remove route entries:**
   - Comment out cookie consent routes in `routes.php`

4. **Restore previous header files:**
   - Use git to revert header.php changes

---

## Maintenance & Updates

### Regular Tasks

**Monthly:**
- [ ] Review consent acceptance rates
- [ ] Check for expired consents
- [ ] Audit cookie inventory vs. actual cookies

**Quarterly:**
- [ ] Update cookie policy if cookies change
- [ ] Review tenant cookie settings
- [ ] Test consent system end-to-end

**Annually:**
- [ ] Legal compliance review
- [ ] Update consent version if required
- [ ] Re-prompt all users for consent

### Cookie Inventory Management

When adding new cookies:

1. Add to `cookie_inventory` table
2. Update cookie policy page
3. Test consent blocking
4. Deploy changes

**Example:**
```sql
INSERT INTO cookie_inventory
(cookie_name, category, purpose, duration, third_party, tenant_id)
VALUES
('_ga', 'analytics', 'Google Analytics tracking cookie', '2 years', 'Google', NULL);
```

### Consent Version Updates

When terms change significantly:

1. Increment `CONSENT_VERSION` in JavaScript
2. Update `consent_types` table with new version
3. Mark all existing consents as requiring re-acceptance
4. Users will see banner again on next visit

```sql
-- Invalidate all consents (forces re-acceptance)
UPDATE cookie_consents SET expires_at = NOW() WHERE consent_version = '1.0';
```

### Analytics Dashboard

**Admin view:** `/admin/cookie-consent/dashboard`

**Metrics to track:**
- Consent acceptance rate (Accept All vs Reject All vs Custom)
- Category-specific acceptance (Functional, Analytics, Marketing)
- Consent by tenant
- Consent by country (if GeoIP available)
- Average time to consent
- Consent withdrawals

---

## Progress Tracking

### Implementation Status

Last Updated: 2026-01-24 17:30

| Component | Status | Progress | Notes |
|-----------|--------|----------|-------|
| **Database Schema** | 🟢 Complete | 100% | Full schema with audit, stats, inventory tables |
| **Backend Services** | 🟢 Complete | 100% | CookieConsentService + CookieInventoryService |
| **API Endpoints** | 🟢 Complete | 100% | Full REST API with 6 endpoints |
| **JavaScript Library** | 🟢 Complete | 100% | cookie-consent.js with helper functions |
| **Modern Theme Banner** | 🟡 In Progress | 0% | HTML, CSS, JS needed |
| **CivicOne Theme Banner** | 🔴 Not Started | 0% | GOV.UK compliant version needed |
| **Cookie Policy Page** | 🔴 Not Started | 0% | Both themes needed |
| **Preferences UI** | 🔴 Not Started | 0% | Modal/page for managing consent |
| **Header Integration** | 🔴 Not Started | 0% | Include banner in layouts |
| **Footer Links** | 🔴 Not Started | 0% | Add "Cookie Preferences" link |
| **Testing** | 🔴 Not Started | 0% | Comprehensive testing needed |
| **Documentation** | 🟢 Complete | 100% | Plan + Status tracking documents |

**Overall Progress: 60% Complete**

**Legend:**
- 🟢 Complete
- 🟡 In Progress
- 🔴 Not Started

### Next Actions

**Immediate (This Week):**
1. Complete database schema enhancements
2. Create CookieConsentService
3. Create CookieInventoryService
4. Build API endpoints

**Week 2:**
5. Create JavaScript library
6. Design and implement Modern theme banner
7. Design and implement CivicOne theme banner

**Week 3:**
8. Create cookie policy pages
9. Build preferences management UI
10. Integrate with headers/footers

**Week 4:**
11. Comprehensive testing
12. Accessibility audit
13. Performance optimization
14. Bug fixes

**Week 5:**
15. Deploy to staging
16. Final testing
17. Deploy to production
18. Monitor and iterate

---

## Appendix

### A. Legal Resources

- **ePrivacy Directive:** https://eur-lex.europa.eu/legal-content/EN/ALL/?uri=CELEX:32002L0058
- **GDPR:** https://gdpr-info.eu/
- **ICO Cookie Guidance:** https://ico.org.uk/for-organisations/guide-to-pecr/cookies-and-similar-technologies/
- **CNIL Cookie Guidelines:** https://www.cnil.fr/en/cookies-and-other-trackers

### B. Design References

- **GOV.UK Cookie Banner:** https://design-system.service.gov.uk/components/cookie-banner/
- **WCAG 2.1 Guidelines:** https://www.w3.org/WAI/WCAG21/quickref/
- **Material Design:** https://material.io/components/banners

### C. Third-Party Tools (Optional)

- **Klaro!:** https://klaro.org/
- **Cookiebot:** https://www.cookiebot.com/
- **OneTrust:** https://www.onetrust.com/
- **Tarteaucitron.js:** https://tarteaucitron.io/

### D. File Checklist

**New Files to Create:**

```
migrations/
└── 2026_01_24_enhance_cookie_consents.sql

src/
├── Controllers/
│   ├── Api/
│   │   └── CookieConsentController.php
│   ├── Admin/
│   │   └── CookieConsentController.php
│   ├── CookiePolicyController.php
│   └── CookiePreferencesController.php
├── Services/
│   ├── CookieConsentService.php
│   └── CookieInventoryService.php

views/
├── modern/
│   ├── partials/
│   │   └── cookie-banner.php
│   └── pages/
│       ├── cookie-policy.php
│       └── cookie-preferences.php
├── civicone/
│   ├── partials/
│   │   └── cookie-banner.php
│   └── pages/
│       ├── cookie-policy.php
│       └── cookie-preferences.php
└── admin/
    └── cookie-consent/
        ├── dashboard.php
        ├── analytics.php
        └── inventory.php

httpdocs/assets/
├── js/
│   ├── cookie-consent.js
│   ├── cookie-consent.min.js
│   ├── cookie-banner.js
│   └── cookie-banner.min.js
└── css/
    ├── cookie-banner.css
    ├── cookie-banner.min.css
    └── civicone/
        ├── cookie-banner.css
        └── cookie-banner.min.css

docs/
└── EU_COOKIE_COMPLIANCE_PLAN.md (this file)
```

**Files to Modify:**

```
httpdocs/routes.php
views/layouts/modern/header.php
views/layouts/civicone/header.php
views/layouts/modern/footer.php
views/layouts/civicone/footer.php
views/civicone/pages/privacy.php
httpdocs/assets/js/modern-header-behavior.js
purgecss.config.js
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-24 | Claude Code | Initial comprehensive plan created |

---

**END OF IMPLEMENTATION PLAN**
