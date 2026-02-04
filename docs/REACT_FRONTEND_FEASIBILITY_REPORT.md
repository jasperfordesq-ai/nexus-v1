# React Frontend Feasibility Report

## Project NEXUS - New Frontend Migration Analysis

**Date:** 2026-02-03
**Status:** Read-Only Analysis / Planning Phase
**Author:** Architecture Review

---

## Executive Summary (1-Page Bullets)

### Can We Do This?
- **YES** - A new React frontend is feasible with the existing PHP backend
- The API surface is **mature** (44 controllers, 150+ endpoints, OpenAPI spec exists)
- Tenant resolution supports **stateless API requests** via `X-Tenant-ID` header or JWT claims
- Auth supports **Bearer tokens** with long-lived mobile tokens (1 year access, 5 year refresh)
- CORS is **whitelist-based** and configurable via `ALLOWED_ORIGINS` env var

### Biggest Risks
1. **No Tenant Bootstrap Endpoint** - Must be created (API GAP)
2. **CORS Configuration** - Must add new React app origins
3. **Cookie-Based Auth** - Works only with same-origin/reverse-proxy; Bearer tokens recommended
4. **Missing Feature Endpoints** - Some admin/settings routes are session-only

### Minimum Viable API Surface
- `GET /api/v2/tenant/bootstrap` (NEW - must create)
- `POST /api/auth/login` (exists)
- `POST /api/auth/refresh-token` (exists)
- `GET /api/v2/listings` (exists)
- `GET /api/v2/users/me` (exists)
- `GET /api/v2/feed` (exists)
- `GET /api/v2/notifications` (exists)

### Recommended Approach
- **Option B: Same-Domain Reverse Proxy** for initial rollout (simplest)
- **Option A: Direct CORS** for mobile apps and external SPAs

---

## Section 1: Repo Findings

### 1.1 Tenant Resolution Mechanism

**Location:** [TenantContext.php](../src/Core/TenantContext.php)

The tenant resolution follows a **5-level priority chain**:

| Priority | Method | Trigger | File Location |
|----------|--------|---------|---------------|
| 1 | Domain | `HTTP_HOST` matches `tenants.domain` | `TenantContext.php:21-46` |
| 2 | X-Tenant-ID Header | `X-Tenant-ID: N` header present | `TenantContext.php:48-87` |
| 2.5 | Bearer Token | JWT payload has `tenant_id` + `/api/` path | `TenantContext.php:89-114` |
| 3 | URL Path | First segment is tenant slug (e.g., `/hour-timebank/`) | `TenantContext.php:116-224` |
| 4 | Session | `$_SESSION['tenant_id']` for logged-in users | `TenantContext.php:226-246` |
| 5 | Fallback | Default to Master tenant (ID=1) | `TenantContext.php:248-270` |

**Key Code:**
```php
// Domain resolution (highest priority for non-master)
$host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? '');
$stmt = $db->prepare("SELECT * FROM tenants WHERE domain = ?");
$stmt->execute([$host]);

// X-Tenant-ID header resolution
$headerTenantId = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
if ($headerTenantId !== null && is_numeric($headerTenantId)) {
    // Validates tenant exists and is active
}

// Bearer token tenant extraction
$tokenTenantId = self::extractTenantIdFromBearerToken();
if ($tokenTenantId !== null && strpos($requestUri, '/api/') !== false) {
    // Uses tenant from JWT payload
}
```

**Critical Finding:** The backend **already supports stateless API tenant resolution** via:
1. `X-Tenant-ID` header (preferred for React app)
2. `tenant_id` claim in JWT (automatically included in tokens)

### 1.2 Existing API Endpoints

**Location:** [routes.php](../httpdocs/routes.php) (~1,800 lines)
**Controllers:** [src/Controllers/Api/](../src/Controllers/Api/) (44 controllers)
**OpenAPI Spec:** [docs/openapi.yaml](../docs/openapi.yaml) (v2.0.0)

#### Core API Endpoints (Usable by React Frontend)

**Authentication:**
```
POST   /api/auth/login                    - Login (returns JWT tokens)
POST   /api/auth/refresh-token            - Refresh access token
POST   /api/auth/logout                   - Logout / revoke tokens
POST   /api/auth/register                 - User registration
GET    /api/auth/csrf-token               - Get CSRF token (for session auth)
POST   /api/auth/forgot-password          - Password reset request
POST   /api/auth/reset-password           - Complete password reset
```

**Users:**
```
GET    /api/v2/users/me                   - Current user profile
PUT    /api/v2/users/me                   - Update profile
GET    /api/v2/users/{id}                 - Public user profile
```

**Listings (Marketplace):**
```
GET    /api/v2/listings                   - List (paginated, filterable)
POST   /api/v2/listings                   - Create
GET    /api/v2/listings/{id}              - Detail
PUT    /api/v2/listings/{id}              - Update
DELETE /api/v2/listings/{id}              - Delete
GET    /api/v2/listings/nearby            - Geospatial search
```

**Messages:**
```
GET    /api/v2/messages                   - Inbox (conversations)
POST   /api/v2/messages                   - Send message
GET    /api/v2/messages/{id}              - Conversation detail
GET    /api/v2/messages/unread-count      - Unread count
```

**Events:**
```
GET    /api/v2/events                     - List events
POST   /api/v2/events/{id}/rsvp           - RSVP to event
```

**Groups:**
```
GET    /api/v2/groups                     - List groups
POST   /api/v2/groups/{id}/join           - Join group
```

**Wallet:**
```
GET    /api/v2/wallet/balance             - Get balance
GET    /api/v2/wallet/transactions        - Transaction history
POST   /api/v2/wallet/transfer            - Transfer credits
```

**Social Feed:**
```
GET    /api/v2/feed                       - Social feed (paginated)
POST   /api/v2/feed/posts                 - Create post
POST   /api/v2/feed/like                  - Like/unlike
```

**Notifications:**
```
GET    /api/v2/notifications              - List notifications
GET    /api/v2/notifications/counts       - Unread counts
POST   /api/v2/notifications/read-all     - Mark all read
```

**Search:**
```
GET    /api/v2/search                     - Global search
GET    /api/v2/search/suggestions         - Autocomplete
```

**Gamification:**
```
GET    /api/v2/gamification/profile       - User's gamification data
GET    /api/v2/gamification/badges        - Available badges
GET    /api/v2/gamification/leaderboard   - Leaderboard
POST   /api/v2/gamification/daily-reward  - Claim daily reward
```

### 1.3 Authentication Mechanisms

**Location:**
- [Auth.php](../src/Core/Auth.php) - Session-based auth
- [ApiAuth.php](../src/Core/ApiAuth.php) - Bearer token auth (trait)
- [TokenService.php](../src/Services/TokenService.php) - JWT generation/validation
- [AuthController.php](../src/Controllers/Api/AuthController.php) - Login endpoints

#### Dual Auth Support

| Method | Use Case | Session Created? | CSRF Required? |
|--------|----------|------------------|----------------|
| Session Cookie | Web browsers | Yes | Yes |
| Bearer Token | Mobile/SPAs | No | No |

**JWT Token Format:**
```json
{
  "user_id": 123,
  "tenant_id": 2,
  "type": "access",
  "platform": "mobile",
  "role": "member",
  "iat": 1675000000,
  "exp": 1675000000
}
```

**Token Expiry:**
| Type | Web | Mobile |
|------|-----|--------|
| Access | 2 hours | 1 year |
| Refresh | 2 years | 5 years |

**Login Response (from AuthController.php:208-228):**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "avatar_url": "/uploads/avatars/...",
    "tenant_id": 2
  },
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 31536000,
  "config": {
    "modules": {
      "events": true,
      "polls": true,
      "goals": true
    }
  }
}
```

**2FA Support:**
- TOTP (Authenticator app)
- WebAuthn/Passkeys
- Backup codes
- Trusted devices (30-day cookie)

### 1.4 CORS Configuration

**Location:** [CorsHelper.php](../src/Helpers/CorsHelper.php)

**Default Allowed Origins:**
```php
private static array $defaultOrigins = [
    'https://project-nexus.ie',
    'https://www.project-nexus.ie',
    'https://hour-timebank.ie',
    'https://www.hour-timebank.ie',
    'http://staging.timebank.local',
];
```

**Override via Environment:**
```env
ALLOWED_ORIGINS=https://app.project-nexus.ie,http://localhost:3000
```

**Headers Set:**
```
Access-Control-Allow-Origin: <validated-origin>  (NOT wildcard)
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Tenant-ID, Accept, Origin
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
Vary: Origin
```

**Important:** CORS validates origin against allowlist. React app URL must be added.

### 1.5 Cookie Settings

**Location:** [index.php](../httpdocs/index.php):280-330

```php
session_set_cookie_params([
    'lifetime' => $sessionLifetime,  // 14 days (mobile) or 2 hours (desktop)
    'path' => '/',
    'domain' => '',                  // Not explicitly set
    'secure' => $isSecure,           // true if HTTPS
    'httponly' => true,              // No JS access
    'samesite' => $sameSite          // 'Lax' (web) or 'None' (mobile WebView)
]);
```

**Implication for React:**
- Cross-origin requests need `SameSite=None; Secure` for cookies
- **Recommendation:** Use Bearer tokens for React frontend (avoids cookie issues)

### 1.6 Theme/Branding Data

**Location:**
- [LayoutHelper.php](../src/Services/LayoutHelper.php) - Theme switching
- [TenantContext.php](../src/Core/TenantContext.php) - Tenant settings

**Theme Resolution Priority:**
1. User's saved preference (`users.preferred_layout`)
2. Session (`$_SESSION['nexus_active_layout']`)
3. Tenant default (`tenants.default_layout`)
4. Hardcoded: `'modern'`

**Tenant Data Available:**
```sql
-- From tenants table
name                  -- Display name
slug                  -- URL slug
domain                -- Custom domain
tagline               -- Short description
default_layout        -- 'modern' or 'civicone'
configuration         -- JSON: modules, footer_text, etc.
features              -- JSON: feature flags
meta_title            -- SEO title
meta_description      -- SEO description
og_image_url          -- Social share image
```

**Configuration JSON Example:**
```json
{
  "modules": {
    "events": true,
    "listings": true,
    "groups": true,
    "wallet": true,
    "volunteering": true
  },
  "footer_text": "Charity Registration: 12345",
  "privacy_text": "...",
  "terms_text": "..."
}
```

---

## Section 2: Feasibility Assessment

### 2.1 Can We Serve a React Frontend Per Tenant Domain?

**YES** - The architecture supports this via:

1. **Domain Resolution:** Each tenant can have a custom domain (`tenants.domain`)
2. **X-Tenant-ID Header:** API requests can specify tenant explicitly
3. **JWT Tenant Claim:** Tokens include `tenant_id` for stateless requests

**Deployment Options:**

| Option | How It Works | Complexity |
|--------|--------------|------------|
| **A) Multi-Domain React** | Deploy React to each tenant domain, use `X-Tenant-ID` | Medium |
| **B) Single React + Proxy** | One React app behind reverse proxy, domain → tenant mapping | Low |
| **C) Subdomain Routing** | `tenant.project-nexus.ie` with wildcard SSL | Medium |

### 2.2 Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| **No tenant bootstrap endpoint** | HIGH | Create `GET /api/v2/tenant/bootstrap` |
| **CORS not configured for new domain** | MEDIUM | Add to `ALLOWED_ORIGINS` env var |
| **Session cookies won't work cross-origin** | LOW | Use Bearer tokens (already supported) |
| **Some features are session-only** | MEDIUM | Identify and migrate to API |
| **Rate limiting by IP behind proxy** | LOW | Configure `X-Forwarded-For` trust |
| **2FA flow complexity** | LOW | Already has API support |

### 2.3 Minimum Viable API Surface

**Required for MVP:**

| Category | Endpoints | Status |
|----------|-----------|--------|
| **Tenant Bootstrap** | `GET /api/v2/tenant/bootstrap` | **MISSING - MUST CREATE** |
| **Auth** | login, logout, refresh | Exists |
| **Profile** | GET/PUT users/me | Exists |
| **Listings** | CRUD + search | Exists |
| **Feed** | GET feed, POST posts | Exists |
| **Notifications** | GET + counts | Exists |
| **Theme** | GET/POST layout | Exists (`/api/layout-switch`) |

---

## Section 3: Integration Guide (Draft)

### 3.1 Tenant Resolution Contract

**How the React Frontend Determines Tenant:**

```
┌─────────────────────────────────────────────────────────────┐
│  1. On App Load, Frontend Extracts Tenant from:            │
│     - window.location.hostname (e.g., hour-timebank.ie)    │
│                                                             │
│  2. Frontend Calls: GET /api/v2/tenant/bootstrap           │
│     - Header: X-Tenant-ID: (from domain lookup OR config)  │
│     - Returns: tenant config, branding, features           │
│                                                             │
│  3. On Login, Backend Returns JWT with tenant_id claim     │
│                                                             │
│  4. All Subsequent API Calls Include:                      │
│     - Authorization: Bearer <token>                         │
│     - X-Tenant-ID: <tenant_id> (optional, token has it)    │
└─────────────────────────────────────────────────────────────┘
```

**What Frontend Must Send on Every API Call:**

```javascript
// Required Headers
{
  "Authorization": "Bearer <access_token>",  // Required for authenticated endpoints
  "Content-Type": "application/json",
  "X-Tenant-ID": "<tenant_id>"              // Optional if token has tenant_id
}

// For mutations (POST/PUT/DELETE), if using session auth:
{
  "X-CSRF-Token": "<csrf_token>"            // Only for session auth, not Bearer
}
```

### 3.2 Recommended Approach

**Option A: Frontend Calls Backend Directly (CORS)**

```
React App (https://app.project-nexus.ie)
    │
    ├── /api/* requests → Backend (https://project-nexus.ie)
    │                     (with CORS headers)
    │
    └── Auth: Bearer tokens only (no session cookies)

Pros: Simplest deployment, standard SPA pattern
Cons: Must configure CORS, can't use session cookies easily
```

**Option B: Same-Domain Reverse Proxy (Recommended for Initial Rollout)**

```
Nginx/Cloudflare
    │
    ├── /api/*  → PHP Backend (existing)
    └── /*      → React App (static files or Node server)

Same domain, so:
- Cookies work normally
- No CORS issues
- Session OR Bearer auth works

Pros: Zero CORS config, cookies work, gradual migration possible
Cons: More infrastructure to manage
```

**Nginx Config Example (Option B):**
```nginx
server {
    listen 443 ssl;
    server_name hour-timebank.ie;

    # React app (default)
    location / {
        root /var/www/react-app/dist;
        try_files $uri $uri/ /index.html;
    }

    # API proxy to PHP backend
    location /api/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 3.3 Example Request/Response Shapes

#### Tenant Bootstrap (PROPOSED NEW ENDPOINT)

```
GET /api/v2/tenant/bootstrap
Headers:
  X-Tenant-ID: 2   (or resolve from domain)

Response 200:
{
  "data": {
    "id": 2,
    "name": "Hour Timebank",
    "slug": "hour-timebank",
    "domain": "hour-timebank.ie",
    "tagline": "Exchange skills, build community",
    "default_layout": "modern",
    "branding": {
      "logo_url": "/uploads/tenants/2/logo.png",
      "primary_color": "#6366f1",
      "og_image_url": "/uploads/tenants/2/og.jpg"
    },
    "features": {
      "listings": true,
      "events": true,
      "groups": true,
      "wallet": true,
      "volunteering": true,
      "gamification": true,
      "federation": false
    },
    "seo": {
      "meta_title": "Hour Timebank | Community Time Exchange",
      "meta_description": "Join our community..."
    }
  }
}
```

#### Authentication

**Login Request:**
```
POST /api/auth/login
Content-Type: application/json
X-Tenant-ID: 2

{
  "email": "user@example.com",
  "password": "secret123"
}
```

**Login Response (Success):**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "user@example.com",
    "avatar_url": "/uploads/avatars/123.jpg",
    "tenant_id": 2
  },
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 31536000,
  "config": {
    "modules": {"events": true, "polls": true}
  }
}
```

**Login Response (2FA Required):**
```json
{
  "success": false,
  "requires_2fa": true,
  "two_factor_token": "abc123...",
  "methods": ["totp", "backup_code"],
  "code": "AUTH_2FA_REQUIRED",
  "user": {
    "id": 123,
    "first_name": "John",
    "email_masked": "j***@example.com"
  }
}
```

#### Listings

**List Listings:**
```
GET /api/v2/listings?type=offer&per_page=20&cursor=abc123
Authorization: Bearer <token>
X-Tenant-ID: 2

Response 200:
{
  "data": [
    {
      "id": 456,
      "title": "Guitar Lessons",
      "description": "Learn acoustic guitar...",
      "type": "offer",
      "category_id": 5,
      "user": {
        "id": 123,
        "first_name": "John",
        "avatar_url": "..."
      },
      "created_at": "2026-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "per_page": 20,
    "has_more": true,
    "cursor": "def456"
  }
}
```

---

## Section 4: API Test Plan (Runnable)

### 4.1 Prerequisites

```bash
# Set variables
export API_BASE="http://staging.timebank.local"
export TENANT_ID=2  # hour-timebank
export TEST_EMAIL="test@example.com"
export TEST_PASSWORD="testpassword123"
```

### 4.2 Tenant Bootstrap Tests (NEW ENDPOINT)

**Test B1: Tenant Bootstrap via X-Tenant-ID Header**
```bash
curl -s -X GET "${API_BASE}/api/v2/tenant/bootstrap" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq '{ id: .data.id, name: .data.name, features: .data.features }'

# Expected: Returns tenant 2 config with id, name, and features
# {
#   "id": 2,
#   "name": "Hour Timebank",
#   "features": { "listings": true, "events": true, ... }
# }
```

**Test B2: Tenant Bootstrap via Domain (Master Tenant)**
```bash
curl -s -X GET "${API_BASE}/api/v2/tenant/bootstrap" \
  -H "Accept: application/json" | jq '{ id: .data.id, name: .data.name }'

# Expected: Returns master tenant (ID=1) when no X-Tenant-ID header
# { "id": 1, "name": "Project NEXUS" }
```

**Test B3: Tenant Bootstrap with Invalid Tenant ID**
```bash
curl -s -X GET "${API_BASE}/api/v2/tenant/bootstrap" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: 99999" | jq '.'

# Expected: 400 error with invalid tenant message
# { "errors": [{ "code": "INVALID_TENANT", "message": "..." }] }
```

**Test B4: Full Bootstrap Response Structure**
```bash
curl -s -X GET "${API_BASE}/api/v2/tenant/bootstrap" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq 'keys'

# Expected: Shows all top-level response keys
# ["data", "meta"]
# And .data should contain: id, name, slug, default_layout, features, etc.
```

### 4.3 Tenant Resolution Tests (Listings)

**Test 1: Domain-Based Resolution (Master Domain)**
```bash
curl -s -X GET "${API_BASE}/api/v2/listings" \
  -H "Accept: application/json" | jq '.data[0].id // "empty"'

# Expected: Returns listings from master tenant (ID=1)
```

**Test 2: X-Tenant-ID Header Resolution**
```bash
curl -s -X GET "${API_BASE}/api/v2/listings" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: 2" | jq '.data[0].id // "empty"'

# Expected: Returns listings from tenant 2 (hour-timebank)
```

**Test 3: Invalid Tenant ID**
```bash
curl -s -X GET "${API_BASE}/api/v2/listings" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: 99999" | jq '.'

# Expected: 400 error with invalid tenant message
```

### 4.3 Authentication Flow Tests

**Test 4: Login**
```bash
curl -s -X POST "${API_BASE}/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: ${TENANT_ID}" \
  -d '{
    "email": "'"${TEST_EMAIL}"'",
    "password": "'"${TEST_PASSWORD}"'"
  }' | jq '{ success, user_id: .user.id, has_token: (.access_token != null) }'

# Expected: { "success": true, "user_id": N, "has_token": true }
```

**Test 5: Get Current User (Bearer Auth)**
```bash
# First, extract token from login
TOKEN=$(curl -s -X POST "${API_BASE}/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: ${TENANT_ID}" \
  -d '{"email": "'"${TEST_EMAIL}"'", "password": "'"${TEST_PASSWORD}"'"}' \
  | jq -r '.access_token')

# Then use it
curl -s -X GET "${API_BASE}/api/v2/users/me" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq '{ id: .data.id, email: .data.email }'

# Expected: User profile data
```

**Test 6: Token Refresh**
```bash
REFRESH_TOKEN=$(curl -s -X POST "${API_BASE}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "'"${TEST_EMAIL}"'", "password": "'"${TEST_PASSWORD}"'"}' \
  | jq -r '.refresh_token')

curl -s -X POST "${API_BASE}/api/auth/refresh-token" \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "'"${REFRESH_TOKEN}"'"}' | jq '{ success, new_token: (.access_token != null) }'

# Expected: { "success": true, "new_token": true }
```

### 4.4 Core Endpoint Tests

**Test 7: List Listings**
```bash
curl -s -X GET "${API_BASE}/api/v2/listings?per_page=5" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq '{ count: (.data | length), has_more: .meta.has_more }'

# Expected: { "count": 5, "has_more": true/false }
```

**Test 8: Search Listings**
```bash
curl -s -X GET "${API_BASE}/api/v2/listings?q=guitar&type=offer" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq '.data | length'

# Expected: Number of matching listings
```

**Test 9: Get Feed (Authenticated)**
```bash
curl -s -X GET "${API_BASE}/api/v2/feed?per_page=10" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq '{ count: (.data | length), has_more: .meta.has_more }'

# Expected: { "count": N, "has_more": true/false }
```

**Test 10: Get Notifications Count**
```bash
curl -s -X GET "${API_BASE}/api/v2/notifications/counts" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-Tenant-ID: ${TENANT_ID}" | jq '.'

# Expected: { "data": { "unread": N, ... } }
```

### 4.5 CORS Preflight Test

**Test 11: OPTIONS Preflight**
```bash
curl -s -X OPTIONS "${API_BASE}/api/v2/listings" \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Authorization, X-Tenant-ID" \
  -D - -o /dev/null | grep -E "Access-Control|HTTP"

# Expected (if localhost:3000 not in ALLOWED_ORIGINS):
#   HTTP/1.1 204 No Content
#   (Missing Access-Control-Allow-Origin - CORS blocked)

# Expected (if configured):
#   Access-Control-Allow-Origin: http://localhost:3000
#   Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
```

---

## Section 5: API Gaps

### 5.1 ~~Missing:~~ Tenant Bootstrap Endpoint - IMPLEMENTED

**Status:** IMPLEMENTED (2026-02-03)
**Priority:** HIGH (Blocking for React frontend) - RESOLVED

**Route:**
```
GET /api/v2/tenant/bootstrap
```

**Controller:** `src/Controllers/Api/TenantBootstrapController.php`

**Proposed Response:**
```json
{
  "data": {
    "id": 2,
    "name": "Hour Timebank",
    "slug": "hour-timebank",
    "domain": "hour-timebank.ie",
    "tagline": "Exchange skills, build community",
    "default_layout": "modern",
    "branding": {
      "logo_url": "/uploads/tenants/2/logo.png",
      "favicon_url": "/uploads/tenants/2/favicon.ico",
      "primary_color": "#6366f1",
      "og_image_url": "/uploads/tenants/2/og.jpg"
    },
    "features": {
      "listings": true,
      "events": true,
      "groups": true,
      "wallet": true,
      "volunteering": true,
      "gamification": true,
      "federation": false,
      "blog": true,
      "resources": true
    },
    "seo": {
      "meta_title": "Hour Timebank | Community Time Exchange",
      "meta_description": "Join our community of skills exchangers"
    },
    "contact": {
      "email": "contact@hour-timebank.ie",
      "phone": null,
      "address": null
    },
    "social": {
      "facebook": "https://facebook.com/hourtimebank",
      "twitter": null,
      "instagram": null
    }
  }
}
```

**Implementation Notes:**
- Unauthenticated endpoint (public)
- Tenant resolved from `X-Tenant-ID` header or domain
- Cache aggressively (5-15 minutes) - tenant config rarely changes

### 5.2 Missing: Theme/Layout Preference API

**Status:** Partially exists (`/api/layout-switch`) but not V2-style

**Current Endpoint:**
```
POST /api/layout-switch
GET  /api/layout-switch
```

**Proposed V2 Endpoint:**
```
GET  /api/v2/users/me/preferences
PUT  /api/v2/users/me/preferences

# Response includes layout preference
{
  "data": {
    "preferred_layout": "modern",
    "notification_settings": {...},
    "privacy_settings": {...}
  }
}
```

### 5.3 Missing: Categories List Endpoint

**Status:** Not found as standalone API

**Proposed Route:**
```
GET /api/v2/categories?type=listing
GET /api/v2/categories?type=event
```

**Proposed Response:**
```json
{
  "data": [
    { "id": 1, "name": "Home & Garden", "icon": "home", "listing_count": 45 },
    { "id": 2, "name": "Technology", "icon": "laptop", "listing_count": 32 }
  ]
}
```

### 5.4 Summary of API Gaps

| Endpoint | Priority | Effort | Notes |
|----------|----------|--------|-------|
| ~~`GET /api/v2/tenant/bootstrap`~~ | ~~HIGH~~ | ~~Low~~ | **IMPLEMENTED** |
| `GET /api/v2/categories` | MEDIUM | Low | Needed for filters |
| `GET /api/v2/members` | LOW | Medium | Public member directory |
| Admin APIs | LOW | High | Admin panel stays PHP for now |

---

## Section 6: Conclusions & Next Steps

### 6.1 Feasibility Verdict

**The React frontend migration is FEASIBLE** with the existing PHP backend. Key enablers:

1. Mature API surface (150+ endpoints)
2. Stateless auth support (Bearer tokens)
3. Tenant resolution via headers/JWT
4. OpenAPI documentation exists

### 6.2 Recommended Roadmap

**Phase 1: Foundation (1-2 weeks)**
- [ ] Create `GET /api/v2/tenant/bootstrap` endpoint
- [ ] Create `GET /api/v2/categories` endpoint
- [ ] Configure CORS for React dev server (localhost:3000)
- [ ] Set up React project with Hero UI

**Phase 2: Core Features (2-4 weeks)**
- [ ] Auth flow (login, logout, 2FA, token refresh)
- [ ] Listings list/detail/search
- [ ] User profile
- [ ] Basic navigation

**Phase 3: Full Feature Parity (4-8 weeks)**
- [ ] Feed, Messages, Events, Groups
- [ ] Wallet/transactions
- [ ] Notifications (including real-time via Pusher)
- [ ] Gamification

**Phase 4: Migration (Ongoing)**
- [ ] Deploy React behind reverse proxy (same domain)
- [ ] Gradually redirect routes from PHP to React
- [ ] Monitor and fix issues
- [ ] Eventually deprecate PHP frontend

### 6.3 Files Referenced in This Report

| File | Purpose |
|------|---------|
| `src/Core/TenantContext.php` | Tenant resolution logic |
| `src/Core/ApiAuth.php` | Bearer token authentication |
| `src/Services/TokenService.php` | JWT generation/validation |
| `src/Helpers/CorsHelper.php` | CORS configuration |
| `src/Controllers/Api/AuthController.php` | Login endpoints |
| `src/Controllers/Api/ListingsApiController.php` | Listings API |
| `src/Controllers/Api/BaseApiController.php` | API response helpers |
| `httpdocs/routes.php` | Route definitions |
| `docs/openapi.yaml` | API specification |

---

*Report generated 2026-02-03*
