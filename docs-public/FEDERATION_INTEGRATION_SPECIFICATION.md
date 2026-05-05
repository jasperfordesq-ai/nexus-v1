# Multi-Tenant Federation Module
## Integration Specification Document

**Document Version:** 2.0
**Date:** 17 January 2026
**Classification:** Technical Partnership Document
**Status:** Production Ready (API Complete)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Architectural Overview](#2-architectural-overview)
3. [Key Features](#3-key-features)
4. [Technical Specifications](#4-technical-specifications)
5. [Integration Points](#5-integration-points)
6. [Complete API Reference](#6-complete-api-reference)
7. [Appendices](#7-appendices)

---

## 1. Executive Summary

### 1.1 The Vision

Imagine members from a timebank in Bristol being able to discover and exchange time credits with members in Melbourne, Berlin, or Toronto — all while maintaining local governance and privacy.

Timebanking has traditionally been confined to isolated local communities. Our Multi-Tenant Federation Module breaks down these barriers, enabling timebanks worldwide to connect whilst each community retains complete autonomy over their participation.

This document provides the technical specifications necessary for external partners to build connector APIs that integrate with our federation infrastructure.

### 1.2 Current Status: Rapid Development

> **Note:** The platform is in **Rapid Development** stage. You may experience bugs until the platform is marked as fully stable. However, the **core federation backend and REST API are fully functional and tested**.

**What's Working Now:**
- Cross-Tenant Messaging — Full inbox, threads, and read receipts
- Federated Transactions — Real-time exchange of time credits between tenants
- Global Kill-Switch — A 6-layer permission model allowing instant connection cutoff
- Privacy Controls — Granular user opt-in (Discovery, Social, or Economic levels)

**What's Complete:**
The internal logic and the **external connector API** are now fully implemented. The REST API (documented in section 1.5 under "Federation Search API") provides all the integration points needed for different timebank platforms to federate with each other.

### 1.3 Value Proposition

**For Platform Operators:**
- Expand your community's reach without losing independence
- Offer members access to a global network of skills and services
- Maintain full administrative control over federation participation
- Granular feature toggles at system, tenant, and user levels

**For Members:**
- Access services and skills beyond local geography
- Connect with like-minded individuals globally
- Complete privacy control — opt-in at every level
- Portable reputation that travels with the user

**For Integration Partners:**
- Well-documented, standards-based integration points
- Fail-safe security architecture with defence in depth
- Comprehensive audit logging for compliance
- Flexible partnership models (bilateral or network-wide)

### 1.4 Implementation Statistics

| Component | Status |
|-----------|--------|
| Database Tables | 17 fully implemented |
| PHP Services | 15 production-ready |
| Controllers | 24 with complete CRUD |
| View Templates | 68+ (responsive, dark mode) |
| Active Test Users (Opted-In) | 228 |
| Active Test Partnerships | 6 |
| Audit Log Entries | 139+ |
| Authentication Methods | 3 (API Key, HMAC, JWT) |

### 1.5 Recent Updates (January 2026)

**Notification System Enhancements:**

- In-app admin notifications for all 8 partnership lifecycle events
- Federated transaction notifications to both sender and receiver (email + push)
- User-facing federation activity feed combining messages, transactions, and new partners

**Advanced Search Capabilities:**

- Skills-based search with autocomplete across all partner timebanks
- Location filtering with city/region search
- Service reach filters (Remote OK, Will Travel)
- Availability toggles (Accepts Messages, Accepts Transactions)
- Sort options (Name, Newest, Most Active)
- Search statistics dashboard showing network-wide member availability

**Partner Timebank Profiles:**

- Detailed profile pages for each partner timebank
- Partnership statistics (members, listings, events, groups, hours exchanged)
- Feature availability matrix showing enabled/disabled capabilities
- Recent activity feed showing user's interactions with the partner
- Quick action buttons for browsing partner content
- Linked from federation hub with hover-reveal "View Details"

**Federated Events Browser:**

- Browse events from all partner timebanks with glassmorphism UI
- Search by title, description, location across all federated events
- Filter by timebank and time range (upcoming, this week, this month, all)
- RSVP registration with capacity tracking and availability display
- Event detail pages with organizer info and registration forms
- Privacy notice explaining data sharing with partner timebanks
- Audit logging for all federated event registrations

**Federated Listings Browser:**

- Browse offers and requests from all partner timebanks
- Search by title and description with real-time results
- Filter by partner timebank, type (offer/request), and category
- Infinite scroll pagination for seamless browsing
- Listing detail pages with owner profile and messaging integration
- Category badges and service reach indicators
- Partnership validation before allowing listing access

**Advanced Member Search Enhancements:**

- Skills-based autocomplete search across all partner timebanks
- Location autocomplete with city/region filtering
- Service reach filters (Remote OK, Will Travel, Local Only)
- Availability toggles (Accepts Messages, Accepts Transactions)
- Multiple sort options (Name, Newest, Most Active)
- Real-time search statistics showing network-wide member counts
- Popular skills suggestions for quick filtering

**Federated Groups Browser:**

- Browse groups from all partner timebanks with glassmorphism UI
- Search by group name with real-time results
- Filter by partner timebank
- Pagination with previous/next navigation
- Group cards showing member count and description
- Group detail pages with full description and membership info
- Join/leave functionality with approval workflow support
- My Federated Groups page for managing memberships
- Privacy notices explaining data sharing with partner timebanks
- Membership status badges (member, pending approval)
- CSRF protection on all state-changing operations

**Mobile-Optimized Views:**

- All 20+ federation views fully responsive with mobile breakpoints
- 44px minimum touch targets on all interactive elements (WCAG compliant)
- 16px font-size on inputs to prevent iOS auto-zoom
- Bottom navigation padding (100px) for native app feel
- Safe area insets for notched devices (iPhone X+, etc.)
- Offline banners with connectivity detection on all views
- Service worker integration for offline caching
- Glassmorphism backdrop-filter with vendor prefixes
- Stacked layouts at 768px and 640px breakpoints
- Disabled hover transforms on touch devices
- Focus-visible outlines for keyboard navigation

**Bug Fixes (v1.7):**

- Fixed missing hub.php view dispatcher preventing federation hub from rendering
- Verified all 20+ view dispatchers in place for proper layout routing
- Confirmed all federation routes properly registered and functional

**Quick Actions Widget (v1.8):**

- Floating Action Button (FAB) on federation hub for quick access to common tasks
- Expandable menu with staggered animation reveals
- Quick links: Messages, Send Credits, Find Members, Activity, Help
- Conditionally shows actions based on enabled partnership features
- Dark mode support with glassmorphism backdrop
- Keyboard accessible (Escape to close, focus management)
- Background overlay when expanded
- Mobile-optimized positioning above bottom navigation
- Only displays for opted-in users with active partnerships

**Federation Help & FAQ Page (v1.9):**

- Comprehensive help documentation at `/federation/help`
- Four main sections: Getting Started, Privacy & Safety, Features, Troubleshooting
- Accordion-style FAQ with smooth animations
- Quick navigation links to jump between sections
- Quick access cards linking to Settings, Hub, and Activity
- Contact support section
- Glassmorphism styling consistent with federation theme
- Full dark mode support
- Mobile-responsive layout
- Accessible with keyboard navigation and ARIA attributes

**Federation Onboarding Wizard (v2.0):**

- Step-by-step wizard at `/federation/onboarding` for new federation users
- Mobile-first design with large touch targets (54px buttons)
- 4-step flow: Welcome, Privacy Level, Fine-tune Settings, Success
- Visual progress indicator with step numbers and completion states
- Three privacy levels: Discovery, Social (recommended), Economic
- Toggle switches for granular control (location, skills, messaging, transactions)
- Profile preview showing how user appears to partner timebanks
- Confetti celebration animation on completion
- AJAX save with CSRF protection
- Automatic setting defaults based on privacy level selection
- Decline path with graceful messaging
- Hub "Get Started" button links to wizard for non-opted-in users
- Keyboard accessible with proper focus management
- Offline banner with connectivity detection

**User Federation Dashboard (v2.0):**

- Personal dashboard at `/federation/dashboard` for user's federation activity
- Profile header with avatar, name, and federation status
- Statistics grid showing:
  - Messages sent/received
  - Hours given/received
  - Federated groups joined
  - Events attended
- Quick action buttons: View Activity, Settings, Send Credits, Find Members
- Recent activity feed combining messages and transactions
- Upcoming federated events the user is attending
- Federated groups the user has joined
- Mobile-first responsive design with glassmorphism styling
- Accessible from Quick Actions FAB on hub page
- Redirects to onboarding if user hasn't opted in
- Full dark mode support

**Federation Settings Page (v2.0):**

- User-facing settings page at `/federation/settings` for managing federation preferences
- Status banner showing federation enabled/disabled state with toggle button
- Privacy level selector (Discovery, Social, Economic) with visual radio options
- Granular visibility toggles:
  - Show in federated search
  - Profile visible to partners
  - Show location
  - Show skills
  - Receive messages
  - Accept transactions
- Service reach options (Local Only, Will Travel, Remote OK)
- Activity summary showing messages exchanged, transactions, hours, and partner count
- AJAX save with toast notifications for feedback
- Enable/disable federation endpoints with confirmation dialogs
- Mobile-first responsive design with glassmorphism styling
- Accessible from Quick Actions FAB and user dashboard
- Full dark mode support

**Admin Federation Dashboard (v2.0):**

- Comprehensive admin dashboard at `/admin/federation/dashboard`
- Status header with federation enable/disable toggle
- Statistics grid showing:
  - Users opted in
  - Messages exchanged
  - Transactions completed
  - Hours exchanged
  - Active partnerships
  - Pending requests
- User adoption progress bar with percentage
- Partnership summary grid with status badges
- Top federation users leaderboard with activity stats
- Recent audit activity log with action types and timestamps
- Quick action links to Settings, Partnerships, Analytics, Directory
- Toggle federation on/off with confirmation dialogs
- Dark mode admin UI with glass morphism styling
- Responsive design for mobile admin access

**Federation Analytics (v2.0):**

- Comprehensive analytics dashboard at `/admin/federation/analytics`
- Date range filtering (7, 30, 90, 365 days)
- Interactive activity timeline chart (Chart.js)
- Stats cards: Total Activity, Active Partners, Messages Exchanged, Hours Exchanged
- Tenant activity breakdown:
  - Messages sent/received
  - Hours given/received
  - Profile views
  - Listing interactions
- Top partners by activity ranking with metrics
- Feature usage breakdown by category (messaging, transactions, profiles, listings, partnerships)
- Partnership overview (total, active, pending, suspended)
- Recent activity log with level indicators
- CSV export functionality for reports
- Dark mode admin UI styling
- Mobile-responsive design

**Offline Support/PWA (v2.0):**

- Progressive Web App (PWA) enhancements for federation features
- Service worker caching strategies for federation pages:
  - Network-first for dynamic content (messages, transactions, activity, dashboard)
  - Stale-while-revalidate for semi-static content (hub, members, listings, events, groups, help)
  - Cache-first for static assets (images, CSS, fonts)
- Precached federation pages for instant loading:
  - Federation hub (`/federation`)
  - Help page (`/federation/help`)
  - User dashboard (`/federation/dashboard`)
  - Settings page (`/federation/settings`)
  - Offline fallback page (`/federation/offline`)
- Federation offline page at `/federation/offline`:
  - Standalone HTML without database dependencies
  - Animated globe icon with pulse effect
  - "Available Offline" feature list showing cached content
  - Retry connection button with spinner animation
  - Auto-detect when connection returns with redirect
  - Online/offline status indicator
  - Dark theme matching federation UI
- PWA manifest shortcut for federation:
  - "Partner Timebanks" shortcut in app launcher
  - Links directly to `/federation` hub
- Offline UI indicators on federation hub:
  - Fixed offline banner with Wi-Fi icon
  - Body padding shift to accommodate banner
  - "Cached" overlay badges on partner, member, and listing cards
  - Disabled state for network-dependent actions (`data-requires-network`)
  - Graceful degradation of interactive features
- Connection state management:
  - `navigator.onLine` detection
  - `online`/`offline` event listeners
  - Haptic feedback (vibration) on connection loss
  - Automatic UI state restoration on reconnection
- IndexedDB offline queue integration (for future background sync)

**Real-time Notifications (v2.0):**

- Live notification system for federation events using Pusher or SSE fallback
- Dual transport support:
  - **Pusher (WebSocket)**: Primary method when configured via environment variables
  - **SSE (Server-Sent Events)**: Automatic fallback with database-backed queue
- Real-time event types:
  - `federation.new-message`: New federated message received
  - `federation.transaction`: Time credits received from partner timebank
  - `federation.partnership-update`: Partnership status changes
  - `federation.activity`: General activity events
  - `federation.member-joined`: New member opted into federation
- Toast notification UI component ([federation-realtime.php](views/modern/partials/federation-realtime.php)):
  - Glassmorphism toast cards with event-specific icons
  - Auto-dismiss after 8 seconds with manual close option
  - Click-to-navigate to relevant page
  - Mobile-responsive with bottom positioning on small screens
  - Sound notification support (optional)
  - Haptic feedback (vibration) on mobile
  - Dark mode support
- Connection status indicator:
  - Visual connection state (connecting, connected, disconnected)
  - Auto-hide when connected, visible during reconnection
  - Exponential backoff retry for SSE reconnection
- SSE stream endpoint (`/federation/stream`):
  - Long-polling with 30-second heartbeats
  - 5-minute max connection duration with reconnect
  - Last-Event-ID support for resuming streams
  - Database queue for event persistence
- Pusher channel authentication (`/federation/pusher/auth`):
  - Private channels per user+tenant combination
  - CSRF protection on authentication
  - Partnership validation for chat channels
- FederationRealtimeService enhancements:
  - `broadcastPartnershipUpdate()`: Admin notifications for partnership changes
  - `broadcastNewMember()`: Tenant-wide notification for new members
  - `broadcastActivityEvent()`: User-specific activity notifications
  - SSE queue methods: `queueSSEEvent()`, `getPendingEvents()`, `markEventsDelivered()`
  - Automatic cleanup of delivered events
- Database migration for SSE queue table:
  - `federation_realtime_queue` with tenant/user isolation
  - Indexed for efficient polling and cleanup
- Integration with federation hub for opted-in users only
- Exposed JavaScript API: `window.FedRealtime.showToast(type, data)`

**Federation Search API (v2.0):**

- External REST API for partner timebank integrations
- Base URL: `/api/v1/federation`
- Authentication:
  - API key via `Authorization: Bearer <key>` header or `X-API-Key` header
  - Keys stored as SHA-256 hashes in database
  - Per-key permission scoping
  - Rate limiting (default 1000 requests/hour)
- Available endpoints:
  - `GET /api/v1/federation` - API info and available endpoints
  - `GET /api/v1/federation/timebanks` - List partner timebanks with member counts
  - `GET /api/v1/federation/members` - Search federated members with filters
  - `GET /api/v1/federation/members/{id}` - Get member profile details
  - `GET /api/v1/federation/listings` - Search federated listings
  - `GET /api/v1/federation/listings/{id}` - Get listing details
  - `POST /api/v1/federation/messages` - Send federated message
  - `POST /api/v1/federation/transactions` - Initiate time credit transfer
- Query parameters for search endpoints:
  - `q` - Search query (name, skills, title, description)
  - `timebank_id` - Filter by specific timebank
  - `skills` - Comma-separated skill tags
  - `location` - City/region filter
  - `type` - Listing type (offer/request)
  - `category` - Category filter
  - `page`, `per_page` - Pagination (max 100 per page)
- Permission scopes:
  - `timebanks:read` - List partner timebanks
  - `members:read` - Search and view member profiles
  - `listings:read` - Search and view listings
  - `messages:write` - Send federated messages
  - `transactions:write` - Initiate time credit transfers
  - `*` - All permissions (admin)
- Response format:
  - JSON with `success`, `timestamp`, and `data` fields
  - Paginated responses include `pagination` object
  - Errors include `error`, `code`, and `message` fields
- Privacy enforcement:
  - Only returns users who opted in to federation
  - Respects privacy level settings (discovery/social/economic)
  - Validates active partnership before allowing queries
- Database tables:
  - `federation_api_keys` - API key storage with permissions and rate limits
  - `federation_api_logs` - Request logging for auditing
- FederationApiMiddleware for authentication and authorization
- FederationApiController with all endpoint implementations
- Admin interface for API key management at `/admin/federation/api-keys`:
  - List all API keys with status, permissions, and usage stats
  - Create new keys with permission selection and rate limits
  - View detailed key information and request logs
  - Suspend/reactivate keys temporarily
  - Regenerate keys (invalidates old key immediately)
  - Revoke keys permanently
  - Copy-to-clipboard for newly generated keys
  - One-time key display with security warning

**Cross-Platform Authentication (v2.0):**

The Federation API now supports three authentication methods for external platform integrations, providing defense in depth and flexibility for different integration scenarios.

- **Authentication Methods (Priority Order):**
  1. **HMAC-SHA256 Request Signing** (Highest Security)
     - Required headers: `X-Federation-Platform-ID`, `X-Federation-Timestamp`, `X-Federation-Signature`
     - Signature format: `HMAC-SHA256(secret, METHOD\nPATH\nTIMESTAMP\nBODY)`
     - 5-minute timestamp tolerance for replay attack prevention
     - Timing-safe signature comparison
  2. **JWT Bearer Token** (OAuth-style)
     - Standard `Authorization: Bearer <token>` header
     - Tokens obtained via OAuth 2.0 client_credentials grant
     - 1-hour default lifetime, configurable up to 24 hours
     - Contains platform ID, scopes, and expiration
  3. **API Key** (Simple, for trusted internal partners)
     - Existing Bearer token or X-API-Key header
     - Can require HMAC upgrade via `signing_enabled` flag

- **OAuth 2.0 Token Endpoint** (`POST /api/v1/federation/oauth/token`):
  - Supports `client_credentials` grant type
  - Request parameters: `grant_type`, `client_id` (platform_id), `client_secret` (signing_secret), `scope`
  - Returns: `access_token`, `token_type`, `expires_in`, `expires_at`, `scope`
  - CORS-enabled for browser-based integrations

- **Webhook Signature Testing** (`POST /api/v1/federation/webhooks/test`):
  - Partners can verify their HMAC signing implementation
  - Returns detailed debug info on signature mismatch
  - Validates timestamp freshness and signature correctness

- **New Database Fields** (migration: `2026_01_17_federation_hmac_signing.sql`):
  - `signing_secret`: 64-character hex-encoded HMAC secret
  - `signing_enabled`: Boolean to require HMAC for this key
  - `platform_id`: External platform identifier for lookups
  - `auth_method`: Tracks which auth method was used (api_key/hmac/jwt)
  - `signature_valid`: Logs signature verification results

- **FederationJwtService** (`src/Services/FederationJwtService.php`):
  - JWT generation with HS256 signing
  - Token validation with expiry checking
  - OAuth 2.0 token endpoint handler
  - Base64 URL-safe encoding/decoding

- **Code Examples for Partners:**

  **PHP - HMAC Signed Request:**
  ```php
  $platformId = 'your-platform-id';
  $secret = 'your-signing-secret';
  $timestamp = date('c'); // ISO 8601
  $method = 'GET';
  $path = '/api/v1/federation/members';
  $body = '';

  $stringToSign = implode("\n", [$method, $path, $timestamp, $body]);
  $signature = hash_hmac('sha256', $stringToSign, $secret);

  $response = file_get_contents($baseUrl . $path, false, stream_context_create([
      'http' => [
          'header' => implode("\r\n", [
              "X-Federation-Platform-ID: {$platformId}",
              "X-Federation-Timestamp: {$timestamp}",
              "X-Federation-Signature: {$signature}"
          ])
      ]
  ]));
  ```

  **JavaScript - OAuth Token Flow:**
  ```javascript
  // Get token
  const tokenResponse = await fetch('/api/v1/federation/oauth/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
          grant_type: 'client_credentials',
          client_id: 'your-platform-id',
          client_secret: 'your-signing-secret',
          scope: 'members:read listings:read'
      })
  });
  const { access_token } = await tokenResponse.json();

  // Use token
  const members = await fetch('/api/v1/federation/members', {
      headers: { 'Authorization': `Bearer ${access_token}` }
  });
  ```

**Bulk Import/Export (v2.0):**

- Admin data management interface at `/admin/federation/data`
- Export capabilities:
  - **Users Export** (`/admin/federation/export/users`): CSV of all opted-in members with federation settings
    - Fields: user_id, email, name, privacy_level, service_reach, show_in_search, profile_visible, show_location, show_skills, accepts_messages, accepts_transactions, opted_in_at
  - **Partnerships Export** (`/admin/federation/export/partnerships`): CSV of all partnership configurations
    - Fields: partnership_id, partner_name, partner_domain, status, federation_level, all permission flags, timestamps
  - **Transactions Export** (`/admin/federation/export/transactions`): CSV of cross-tenant time credit transfers
    - Fields: transaction_id, sender/receiver details, amount, description, status, timestamps
  - **Audit Log Export** (`/admin/federation/export/audit`): CSV of federation audit entries
    - Fields: log_id, action_type, category, level, actor/target details, timestamps
  - **Full Backup** (`/admin/federation/export/all`): ZIP archive containing all exports plus metadata.json
- Import capabilities:
  - **Bulk User Enrollment** (`/admin/federation/import/users`): CSV import for mass opt-in
    - Supports email or username as identifier
    - Optional columns: privacy_level, service_reach
    - Options: default privacy level, send notification, skip existing users
    - Validation: user existence check, column detection, row-by-row error tracking
  - **CSV Template Download** (`/admin/federation/import/template`): Pre-formatted template file
- Import/Export UI features:
  - Drag-and-drop file upload zone with visual feedback
  - File type validation (CSV only, MIME type checking)
  - Progress tracking with detailed results (enrolled, skipped, not found, errors)
  - Recent exports list with download links
  - Audit logging for all import/export operations
  - Session flash messages for success/error feedback
- Database table:
  - `federation_exports` for tracking export history with expiration and cleanup support
- Security:
  - CSRF protection on all forms
  - Admin authentication required
  - Tenant isolation for all exports
  - Input validation and sanitization

---

## 2. Architectural Overview

### 2.1 High-Level Architecture

The federation module follows a **hub-and-spoke architecture** within a multi-tenant platform. Each tenant (timebank) operates independently but can establish partnerships with other tenants through a centralised gateway.

```
┌─────────────────────────────────────────────────────────────────┐
│                    FEDERATION GATEWAY                            │
│         (Central Permission Validation & Audit Layer)           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │  Timebank A  │◄──►│  Timebank B  │◄──►│  Timebank C  │      │
│  │  (Tenant 1)  │    │  (Tenant 2)  │    │  (Tenant 3)  │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│         │                   │                   │               │
│         ▼                   ▼                   ▼               │
│  ┌──────────────────────────────────────────────────────┐      │
│  │              SHARED DATABASE LAYER                    │      │
│  │    (Tenant-Isolated with Cross-Tenant References)    │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    EXTERNAL CONNECTOR API                        │
│              (Proposed Integration Layer)                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │   Partner    │    │   Partner    │    │   Partner    │      │
│  │  Platform A  │    │  Platform B  │    │  Platform C  │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Six-Layer Permission Model

Every cross-tenant operation must pass through all six security layers. **All checks must pass** for an operation to proceed—failure at any layer results in denial.

```
┌─────────────────────────────────────────────────────────────┐
│ Layer 1: SYSTEM CONTROL                                      │
│ ├── Is federation enabled globally?                         │
│ ├── Is emergency lockdown active?                           │
│ └── Is whitelist mode enforced?                             │
├─────────────────────────────────────────────────────────────┤
│ Layer 2: TENANT WHITELIST                                    │
│ ├── Is source tenant whitelisted?                           │
│ └── Is target tenant whitelisted?                           │
├─────────────────────────────────────────────────────────────┤
│ Layer 3: TENANT FEATURES                                     │
│ ├── Is federation enabled for source tenant?                │
│ └── Is the specific feature enabled for both tenants?       │
├─────────────────────────────────────────────────────────────┤
│ Layer 4: PARTNERSHIP                                         │
│ ├── Does an active partnership exist?                       │
│ ├── What is the federation level?                           │
│ └── Is the specific permission granted?                     │
├─────────────────────────────────────────────────────────────┤
│ Layer 5: USER PRIVACY                                        │
│ ├── Has the user opted into federation?                     │
│ └── Has the user enabled this specific feature?             │
├─────────────────────────────────────────────────────────────┤
│ Layer 6: RESOURCE-SPECIFIC RULES                             │
│ ├── For listings: What is federated_visibility?             │
│ ├── For events: Is remote attendance allowed?               │
│ └── For groups: Is federated membership enabled?            │
└─────────────────────────────────────────────────────────────┘
```

### 2.3 Data Flow Architecture

#### 2.3.1 Inbound Data Flow (Receiving from Partner)

```
Partner Platform                    Our Platform
      │                                  │
      │  1. API Request                  │
      │  (Signed + Authenticated)        │
      ├─────────────────────────────────►│
      │                                  │
      │                          ┌───────▼───────┐
      │                          │ API Gateway   │
      │                          │ (Validate     │
      │                          │  Signature)   │
      │                          └───────┬───────┘
      │                                  │
      │                          ┌───────▼───────┐
      │                          │ Federation    │
      │                          │ Gateway       │
      │                          │ (6-Layer      │
      │                          │  Permission)  │
      │                          └───────┬───────┘
      │                                  │
      │                          ┌───────▼───────┐
      │                          │ Service Layer │
      │                          │ (Process      │
      │                          │  Request)     │
      │                          └───────┬───────┘
      │                                  │
      │                          ┌───────▼───────┐
      │                          │ Audit Logger  │
      │                          │ (Log Action)  │
      │                          └───────┬───────┘
      │                                  │
      │  2. Response                     │
      │  (Success/Error)                 │
      │◄─────────────────────────────────┤
      │                                  │
```

#### 2.3.2 Outbound Data Flow (Sending to Partner)

```
Our Platform                        Partner Platform
      │                                  │
      │  User Action Triggers            │
      │  Federation Request              │
      │                                  │
┌─────▼─────┐                           │
│ Federation│                           │
│ Gateway   │                           │
│ (Validate │                           │
│  Locally) │                           │
└─────┬─────┘                           │
      │                                  │
┌─────▼─────┐                           │
│ API Client│                           │
│ (Sign     │                           │
│  Request) │                           │
└─────┬─────┘                           │
      │                                  │
      │  1. Signed API Request           │
      ├─────────────────────────────────►│
      │                                  │
      │  2. Response                     │
      │◄─────────────────────────────────┤
      │                                  │
┌─────▼─────┐                           │
│ Audit     │                           │
│ Logger    │                           │
└───────────┘                           │
```

### 2.4 Service Architecture

The module comprises 13 core services:

| Service | Responsibility |
|---------|----------------|
| `FederationGateway` | Central permission validation for all cross-tenant operations |
| `FederationFeatureService` | System and tenant feature flag management |
| `FederationPartnershipService` | Partnership lifecycle and negotiation |
| `FederationAuditService` | Comprehensive audit logging |
| `FederationUserService` | User opt-in and privacy preferences |
| `FederationDirectoryService` | Tenant discovery and directory |
| `FederationEmailService` | Email notifications for partnership events |
| `FederationRealtimeService` | Real-time updates via WebSocket (Pusher) |
| `FederationActivityService` | Unified activity feed (messages, transactions, partners) |
| `FederatedMessageService` | Cross-tenant messaging |
| `FederatedTransactionService` | Cross-tenant time credit exchanges |
| `FederatedGroupService` | Cross-tenant group membership |
| `FederationSearchService` | Advanced member search with skills, location, and availability filters |

---

## 3. Key Features

### 3.1 Partnership Management

- **Partnership Requests** — Tenants can discover and request partnerships with others
- **Federation Levels** — Four progressive levels of integration (Discovery → Integrated)
- **Counter-Proposals** — Full negotiation workflow with back-and-forth proposals
- **Granular Permissions** — Toggle individual features per partnership
- **Lifecycle Management** — Suspend, reactivate, or terminate partnerships

### 3.2 Cross-Tenant Capabilities

- **Member Discovery** — Browse and search members from partner timebanks
- **Profile Viewing** — View member profiles with privacy-controlled visibility
- **Messaging** — Send and receive messages across timebank boundaries
- **Transactions** — Exchange time credits with automatic balance updates
- **Listings** — Discover offers and requests from partner communities
- **Events** — Find and attend events, including remote attendance
- **Groups** — Join interest groups from partner timebanks

### 3.3 Privacy & Control

- **Opt-In Architecture** — All features default to OFF; explicit consent required
- **Granular User Settings** — Control profile visibility, messaging, transactions individually
- **Service Reach** — Define availability: Local Only, Remote OK, or Travel OK
- **Data Minimisation** — Only share what users explicitly permit

### 3.4 Administration

- **Master Kill Switch** — Instantly disable all federation globally
- **Emergency Lockdown** — Block all cross-tenant operations with reason logging
- **Tenant Whitelist** — Control which tenants can participate in federation
- **Feature Toggles** — Enable/disable capabilities at system and tenant levels
- **Analytics Dashboard** — Activity metrics, partner breakdown, trend charts
- **Audit Log** — Complete trail of all cross-tenant operations

### 3.5 Trust & Reputation

- **Portable Reputation** — Trust scores that travel with users across timebanks
- **Multi-Dimensional Scoring** — Trust, reliability, responsiveness, and review scores
- **Verification System** — Admin-verified member status
- **Transaction History** — Track record of completed exchanges

### 3.6 Federated Reviews System (NEW)

The Federated Reviews system allows members to leave feedback after completing cross-tenant transactions, building trust across timebank boundaries.

**Key Features:**

- **Post-Transaction Reviews** — Users can leave 1-5 star reviews with optional comments after completing federated transactions
- **Privacy Controls** — Review visibility is controlled by user settings; reviewers from other timebanks are anonymized by default
- **Review Status** — Reviews can be pending, approved, rejected, or hidden by admins
- **Cross-Tenant Visibility** — Users can control whether their reviews are visible to other timebanks
- **Trust Score Calculation** — Reviews contribute to a user's overall trust score (0-100)
- **Pending Reviews Queue** — Users can view and complete pending reviews for their transactions

**Trust Score Formula:**

- Base Score: 20 points (account existence)
- Review Score: Up to 40 points (average rating × 8)
- Volume Score: Up to 20 points (1 point per review received)
- Activity Score: Up to 20 points (cross-tenant transaction history)

**Trust Levels:**

| Score | Level | Description |
| ----- | ----- | ----------- |
| 80-100 | Excellent | Outstanding community member |
| 60-79 | Trusted | Reliable, well-reviewed |
| 40-59 | Established | Building reputation |
| 25-39 | Growing | New but active |
| 0-24 | New | Just getting started |

**Database Schema:**

The reviews system extends the existing `reviews` table with federation-specific columns:

- `federation_transaction_id` — Links to federated transaction
- `reviewer_tenant_id` — Tenant of the reviewer
- `receiver_tenant_id` — Tenant of the user being reviewed
- `review_type` — 'local' or 'federated'
- `show_cross_tenant` — Whether review is visible across tenants

**Supporting Tables:**

- `review_responses` — Allows reviewed users to respond
- `review_votes` — "Was this review helpful?" voting

---

## 4. Technical Specifications

### 4.1 System Requirements

| Component | Requirement |
|-----------|-------------|
| **PHP Version** | 8.1 or higher |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Character Set** | UTF8MB4 (full Unicode support) |
| **Web Server** | Apache 2.4+ or Nginx 1.18+ |

#### Required PHP Extensions

| Extension | Purpose |
|-----------|---------|
| `pdo` | Database abstraction |
| `pdo_mysql` | MySQL driver |
| `curl` | HTTP requests |
| `json` | Data serialisation |
| `mbstring` | Unicode string handling |
| `openssl` | Encryption and signing |
| `intl` | Internationalisation |
| `bcmath` | Precise decimal arithmetic |

### 4.2 Authentication Standards

#### 4.2.1 Current Internal Authentication

Our platform supports multiple authentication methods:

| Method | Use Case | Token Lifetime |
|--------|----------|----------------|
| Session-based | Web browsers | 2 hours |
| Bearer Token | Mobile/API clients | 7 days (mobile), 2 hours (web) |
| Refresh Token | Token renewal | 30 days |
| OAuth 2.0 | Social login (Google) | Standard OAuth flow |

#### 4.2.2 Proposed External Federation Authentication

For cross-platform federation, we propose:

**HMAC-SHA256 Request Signing:**
```
Signature = HMAC-SHA256(
    SecretKey,
    HTTPMethod + "\n" +
    RequestPath + "\n" +
    Timestamp + "\n" +
    RequestBody
)
```

**Required Headers:**
```http
X-Federation-Platform-ID: {partner_platform_id}
X-Federation-Tenant-ID: {tenant_id}
X-Federation-Timestamp: {ISO8601_timestamp}
X-Federation-Signature: {hmac_signature}
Authorization: Bearer {jwt_token}
```

**JWT Token Claims:**
```json
{
  "iss": "partner_platform_id",
  "sub": "user_id",
  "aud": "our_platform_id",
  "iat": 1705500000,
  "exp": 1705503600,
  "tenant_id": "partner_tenant_id",
  "scope": ["profiles:read", "messages:write"]
}
```

### 4.3 Data Entities

#### 4.3.1 System Control

Controls global federation behaviour.

| Field | Type | Description |
|-------|------|-------------|
| `federation_enabled` | boolean | Master switch for all federation |
| `whitelist_mode_enabled` | boolean | Require explicit tenant approval |
| `max_federation_level` | integer (1-4) | Maximum allowed federation level |
| `cross_tenant_profiles_enabled` | boolean | Global profiles toggle |
| `cross_tenant_messaging_enabled` | boolean | Global messaging toggle |
| `cross_tenant_transactions_enabled` | boolean | Global transactions toggle |
| `cross_tenant_listings_enabled` | boolean | Global listings toggle |
| `cross_tenant_events_enabled` | boolean | Global events toggle |
| `cross_tenant_groups_enabled` | boolean | Global groups toggle |
| `emergency_lockdown_active` | boolean | Emergency stop flag |
| `emergency_lockdown_reason` | text | Reason for lockdown |

#### 4.3.2 Partnership

Defines relationships between tenants.

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique partnership identifier |
| `tenant_id` | integer | Requesting tenant |
| `partner_tenant_id` | integer | Target tenant |
| `status` | enum | `pending`, `active`, `suspended`, `terminated` |
| `federation_level` | integer (1-4) | Partnership integration level |
| `profiles_enabled` | boolean | Profile viewing permission |
| `messaging_enabled` | boolean | Messaging permission |
| `transactions_enabled` | boolean | Transaction permission |
| `listings_enabled` | boolean | Listings permission |
| `events_enabled` | boolean | Events permission |
| `groups_enabled` | boolean | Groups permission |
| `requested_at` | timestamp | Request timestamp |
| `approved_at` | timestamp | Approval timestamp |
| `notes` | text | Partnership notes |

**Federation Levels:**

| Level | Name | Capabilities |
|-------|------|--------------|
| 1 | Discovery | Basic visibility, profiles only |
| 2 | Social | Profiles, messaging, listings, events |
| 3 | Economic | + Cross-tenant transactions |
| 4 | Integrated | + Cross-tenant group membership |

#### 4.3.3 User Settings

Individual user federation preferences.

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | integer | User identifier |
| `federation_optin` | boolean | Master opt-in flag |
| `profile_visible_federated` | boolean | Profile visibility to partners |
| `messaging_enabled_federated` | boolean | Accept messages from partners |
| `transactions_enabled_federated` | boolean | Allow cross-tenant transactions |
| `appear_in_federated_search` | boolean | Appear in partner searches |
| `show_skills_federated` | boolean | Share skills with partners |
| `show_location_federated` | boolean | Share location with partners |
| `service_reach` | enum | `local_only`, `remote_ok`, `travel_ok` |
| `travel_radius_km` | integer | Travel radius in kilometres |
| `email_notifications` | boolean | Receive federation email alerts |

#### 4.3.4 Federated Message

Cross-tenant message structure.

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Message identifier |
| `sender_tenant_id` | integer | Sender's tenant |
| `sender_user_id` | integer | Sender's user ID |
| `receiver_tenant_id` | integer | Recipient's tenant |
| `receiver_user_id` | integer | Recipient's user ID |
| `subject` | string(255) | Message subject |
| `body` | text | Message content |
| `direction` | enum | `outbound`, `inbound` |
| `status` | enum | `pending`, `delivered`, `unread`, `read`, `failed` |
| `created_at` | datetime | Send timestamp |
| `read_at` | datetime | Read timestamp |

#### 4.3.5 Federated Transaction

Cross-tenant time credit exchange.

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Transaction identifier |
| `sender_tenant_id` | integer | Sender's tenant |
| `sender_user_id` | integer | Sender's user ID |
| `receiver_tenant_id` | integer | Recipient's tenant |
| `receiver_user_id` | integer | Recipient's user ID |
| `amount` | decimal(10,2) | Time credits (hours) |
| `description` | text | Transaction description |
| `status` | enum | `pending`, `completed`, `cancelled`, `disputed` |
| `listing_id` | integer | Related listing (optional) |
| `created_at` | datetime | Creation timestamp |
| `completed_at` | datetime | Completion timestamp |

#### 4.3.6 Reputation

Portable trust scores.

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | integer | User identifier |
| `home_tenant_id` | integer | User's home tenant |
| `trust_score` | decimal(5,2) | Overall trust (0-100) |
| `reliability_score` | decimal(5,2) | Completion rate (0-100) |
| `responsiveness_score` | decimal(5,2) | Response time (0-100) |
| `review_score` | decimal(5,2) | Average review (0-100) |
| `total_transactions` | integer | Total transaction count |
| `successful_transactions` | integer | Completed transactions |
| `hours_given` | decimal(10,2) | Total hours given |
| `hours_received` | decimal(10,2) | Total hours received |
| `is_verified` | boolean | Admin-verified status |
| `share_reputation` | boolean | User consent to share |

#### 4.3.7 Audit Log

Comprehensive activity logging.

| Field | Type | Description |
|-------|------|-------------|
| `id` | bigint | Log entry identifier |
| `action_type` | string(100) | Action performed |
| `category` | string(50) | Action category |
| `level` | enum | `debug`, `info`, `warning`, `critical` |
| `source_tenant_id` | integer | Originating tenant |
| `target_tenant_id` | integer | Target tenant |
| `actor_user_id` | integer | User who performed action |
| `actor_name` | string(200) | Actor's display name |
| `actor_email` | string(255) | Actor's email |
| `data` | JSON | Additional context data |
| `ip_address` | string(45) | Client IP (IPv4/IPv6) |
| `user_agent` | string(500) | Client user agent |
| `created_at` | timestamp | Event timestamp |

**Action Categories:**
- `system` — System-level changes
- `tenant` — Tenant configuration
- `partnership` — Partnership lifecycle
- `profile` — Profile access
- `messaging` — Message operations
- `transaction` — Financial exchanges
- `listing` — Listing interactions
- `event` — Event interactions
- `group` — Group membership
- `search` — Search operations

---

## 5. Integration Points

### 5.1 Proposed REST API Endpoints

#### 5.1.1 Discovery & Directory

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/directory` | List discoverable timebanks |
| `GET` | `/api/v1/federation/directory/{id}` | Get timebank profile |
| `GET` | `/api/v1/federation/directory/regions` | Get available regions |
| `GET` | `/api/v1/federation/directory/categories` | Get available categories |

**Example Response: Directory Listing**
```json
{
  "data": [
    {
      "id": "tb_uk_bristol",
      "name": "Bristol Timebank",
      "description": "Community timebank serving Bristol and surrounding areas",
      "region": "South West England",
      "categories": ["community", "urban"],
      "member_count": 450,
      "logo_url": "https://...",
      "contact_email": "admin@bristoltimebank.org",
      "features": {
        "profiles": true,
        "messaging": true,
        "transactions": true,
        "listings": true,
        "events": true,
        "groups": false
      },
      "partnership_status": null
    }
  ],
  "meta": {
    "total": 42,
    "page": 1,
    "per_page": 20
  }
}
```

#### 5.1.2 Partnership Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/partnerships` | List partnerships |
| `POST` | `/api/v1/federation/partnerships` | Request partnership |
| `GET` | `/api/v1/federation/partnerships/{id}` | Get partnership details |
| `PATCH` | `/api/v1/federation/partnerships/{id}` | Update partnership |
| `POST` | `/api/v1/federation/partnerships/{id}/approve` | Approve request |
| `POST` | `/api/v1/federation/partnerships/{id}/reject` | Reject request |
| `POST` | `/api/v1/federation/partnerships/{id}/counter` | Counter-propose |
| `POST` | `/api/v1/federation/partnerships/{id}/suspend` | Suspend partnership |
| `POST` | `/api/v1/federation/partnerships/{id}/terminate` | Terminate partnership |

**Example Request: Create Partnership**
```json
{
  "target_tenant_id": "tb_uk_bristol",
  "federation_level": 3,
  "message": "We'd love to partner with Bristol Timebank!",
  "requested_permissions": {
    "profiles": true,
    "messaging": true,
    "transactions": true,
    "listings": true,
    "events": true,
    "groups": false
  }
}
```

#### 5.1.3 Member Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/members` | Search federated members |
| `GET` | `/api/v1/federation/members/{tenant_id}/{user_id}` | Get member profile |
| `GET` | `/api/v1/federation/members/{tenant_id}/{user_id}/reputation` | Get reputation |

**Example Response: Member Profile**
```json
{
  "data": {
    "user_id": "usr_12345",
    "tenant_id": "tb_uk_bristol",
    "tenant_name": "Bristol Timebank",
    "name": "Jane Smith",
    "bio": "Passionate gardener and baker",
    "avatar_url": "https://...",
    "skills": ["Gardening", "Baking", "Dog Walking"],
    "location": {
      "city": "Bristol",
      "region": "South West England",
      "country": "UK"
    },
    "service_reach": "travel_ok",
    "travel_radius_km": 25,
    "reputation": {
      "trust_score": 92.5,
      "total_transactions": 47,
      "is_verified": true
    },
    "federation_settings": {
      "messaging_enabled": true,
      "transactions_enabled": true
    }
  }
}
```

#### 5.1.4 Messaging

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/messages` | Get message inbox |
| `POST` | `/api/v1/federation/messages` | Send message |
| `GET` | `/api/v1/federation/messages/thread/{tenant_id}/{user_id}` | Get conversation |
| `PATCH` | `/api/v1/federation/messages/{id}/read` | Mark as read |

**Example Request: Send Message**
```json
{
  "recipient_tenant_id": "tb_uk_bristol",
  "recipient_user_id": "usr_12345",
  "subject": "Garden help request",
  "body": "Hi Jane, I saw you offer gardening help..."
}
```

#### 5.1.5 Transactions

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/transactions` | Get transaction history |
| `POST` | `/api/v1/federation/transactions` | Create transaction |
| `GET` | `/api/v1/federation/transactions/{id}` | Get transaction details |
| `POST` | `/api/v1/federation/transactions/{id}/cancel` | Cancel transaction |

**Example Request: Create Transaction**
```json
{
  "recipient_tenant_id": "tb_uk_bristol",
  "recipient_user_id": "usr_12345",
  "amount": 2.5,
  "description": "Garden maintenance - 2.5 hours",
  "listing_id": "lst_67890",
  "listing_tenant_id": "tb_uk_bristol"
}
```

#### 5.1.6 Listings

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/listings` | Browse federated listings |
| `GET` | `/api/v1/federation/listings/{tenant_id}/{listing_id}` | Get listing details |

#### 5.1.7 Events

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/events` | Browse federated events |
| `GET` | `/api/v1/federation/events/{tenant_id}/{event_id}` | Get event details |
| `POST` | `/api/v1/federation/events/{tenant_id}/{event_id}/attend` | Register attendance |

#### 5.1.8 Groups

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/federation/groups` | Browse federated groups |
| `GET` | `/api/v1/federation/groups/{tenant_id}/{group_id}` | Get group details |
| `POST` | `/api/v1/federation/groups/{tenant_id}/{group_id}/join` | Join group |
| `DELETE` | `/api/v1/federation/groups/{tenant_id}/{group_id}/leave` | Leave group |

### 5.2 Webhook Events

We propose the following webhook events for real-time integration:

| Event | Trigger | Payload |
|-------|---------|---------|
| `partnership.requested` | New partnership request | Partnership details |
| `partnership.approved` | Partnership approved | Partnership + permissions |
| `partnership.rejected` | Partnership rejected | Partnership + reason |
| `partnership.suspended` | Partnership suspended | Partnership + reason |
| `partnership.terminated` | Partnership ended | Partnership + reason |
| `message.received` | Incoming federated message | Message summary |
| `transaction.received` | Incoming transaction | Transaction details |
| `transaction.completed` | Transaction finalised | Transaction details |
| `member.opted_in` | User enabled federation | User summary |
| `member.opted_out` | User disabled federation | User ID only |

**Webhook Signature Verification:**
```
X-Webhook-Signature: sha256={HMAC-SHA256(webhook_secret, payload)}
X-Webhook-Timestamp: {unix_timestamp}
X-Webhook-Event: {event_type}
```

### 5.3 Data Validation Rules

#### 5.3.1 General Rules

| Field Type | Validation |
|------------|------------|
| Tenant IDs | Must exist and be whitelisted |
| User IDs | Must exist within specified tenant |
| Timestamps | ISO 8601 format (UTC) |
| Monetary/Time | Decimal with max 2 decimal places |
| Text Content | UTF-8, HTML sanitised |
| Email | RFC 5322 compliant |

#### 5.3.2 Transaction Rules

| Rule | Constraint |
|------|------------|
| Minimum amount | 0.01 hours |
| Maximum amount | 100 hours per transaction |
| Balance check | Sender must have sufficient balance |
| Self-transaction | Cannot transact with self |
| Same-tenant | Must be different tenants |
| Partnership | Active partnership required |
| User settings | Both parties must enable transactions |

#### 5.3.3 Message Rules

| Rule | Constraint |
|------|------------|
| Subject length | Maximum 255 characters |
| Body length | Maximum 10,000 characters |
| Rate limit | 10 messages per hour per recipient |
| Partnership | Active partnership required |
| User settings | Recipient must enable messaging |

### 5.4 Error Response Format

All error responses follow a consistent structure:

```json
{
  "error": {
    "code": "FEDERATION_PARTNERSHIP_NOT_FOUND",
    "message": "No active partnership exists between these tenants",
    "details": {
      "source_tenant_id": "tb_uk_london",
      "target_tenant_id": "tb_uk_bristol"
    }
  },
  "meta": {
    "request_id": "req_abc123",
    "timestamp": "2026-01-17T10:30:00Z"
  }
}
```

**Common Error Codes:**

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `FEDERATION_DISABLED` | 503 | Federation globally disabled |
| `FEDERATION_LOCKDOWN` | 503 | Emergency lockdown active |
| `TENANT_NOT_WHITELISTED` | 403 | Tenant not approved |
| `PARTNERSHIP_NOT_FOUND` | 404 | No active partnership |
| `PERMISSION_DENIED` | 403 | Feature not enabled |
| `USER_NOT_OPTED_IN` | 403 | User hasn't enabled federation |
| `INSUFFICIENT_BALANCE` | 422 | Not enough time credits |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `VALIDATION_ERROR` | 400 | Invalid request data |
| `SIGNATURE_INVALID` | 401 | Request signature mismatch |

---

## 6. Complete API Reference

This section provides complete documentation for all Federation API endpoints, including authentication, request/response formats, and code examples.

### 6.1 Authentication

All API requests (except `GET /api/v1/federation`) require authentication using an API key.

#### Obtaining an API Key

API keys are issued by tenant administrators through the admin panel at `/admin/federation/api-keys`. Each key has:
- **Permissions**: Scoped access to specific endpoints
- **Rate Limit**: Maximum requests per hour (default: 1000)
- **Expiration**: Optional expiry date

#### Authentication Methods

**Method 1: Authorization Header (Recommended)**
```http
Authorization: Bearer your_api_key_here
```

**Method 2: X-API-Key Header**
```http
X-API-Key: your_api_key_here
```

**Method 3: Query Parameter (Testing Only)**
```
GET /api/v1/federation/members?api_key=your_api_key_here
```

#### Permission Scopes

| Scope | Description |
|-------|-------------|
| `timebanks:read` | List partner timebanks |
| `members:read` | Search and view member profiles |
| `listings:read` | Search and view listings |
| `messages:write` | Send federated messages |
| `transactions:write` | Initiate time credit transfers |
| `*` | All permissions (admin) |

### 6.2 Response Format

#### Success Response
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": { ... }
}
```

#### Paginated Response
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": [ ... ],
  "pagination": {
    "total": 150,
    "page": 1,
    "per_page": 20,
    "total_pages": 8,
    "has_more": true
  }
}
```

#### Error Response
```json
{
  "error": true,
  "code": "ERROR_CODE",
  "message": "Human-readable error message",
  "timestamp": "2026-01-17T10:30:00+00:00"
}
```

### 6.3 API Endpoints

---

#### GET /api/v1/federation

Returns API information and available endpoints. **No authentication required.**

**Request:**
```bash
curl https://your-domain.com/api/v1/federation
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "api": "Federation API",
  "version": "1.0",
  "documentation": "/docs/api/federation",
  "endpoints": {
    "GET /api/v1/federation/timebanks": "List partner timebanks",
    "GET /api/v1/federation/members": "Search federated members",
    "GET /api/v1/federation/members/{id}": "Get member profile",
    "GET /api/v1/federation/listings": "Search federated listings",
    "GET /api/v1/federation/listings/{id}": "Get listing details",
    "POST /api/v1/federation/messages": "Send federated message",
    "POST /api/v1/federation/transactions": "Initiate time credit transfer"
  }
}
```

---

#### GET /api/v1/federation/timebanks

List all partner timebanks with active partnerships.

**Required Permission:** `timebanks:read`

**Request:**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://your-domain.com/api/v1/federation/timebanks
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": [
    {
      "id": 2,
      "name": "Bristol Timebank",
      "tagline": "Building community through time",
      "location": {
        "city": "Bristol",
        "country": "United Kingdom",
        "timezone": "Europe/London"
      },
      "member_count": 245,
      "partnership_status": "active",
      "partnership_since": "2026-01-10 14:30:00"
    },
    {
      "id": 5,
      "name": "Dublin Time Exchange",
      "tagline": "Sharing skills across Dublin",
      "location": {
        "city": "Dublin",
        "country": "Ireland",
        "timezone": "Europe/Dublin"
      },
      "member_count": 189,
      "partnership_status": "active",
      "partnership_since": "2026-01-12 09:15:00"
    }
  ],
  "count": 2
}
```

---

#### GET /api/v1/federation/members

Search federated members across all partner timebanks.

**Required Permission:** `members:read`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search query (matches name, username, skills) |
| `timebank_id` | integer | Filter by specific timebank |
| `skills` | string | Comma-separated skill tags |
| `location` | string | City/region filter |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Results per page (default: 20, max: 100) |

**Request:**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "https://your-domain.com/api/v1/federation/members?q=gardening&location=Bristol&page=1&per_page=20"
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": [
    {
      "id": 156,
      "username": "jane_smith",
      "name": "Jane Smith",
      "avatar": "/uploads/avatars/jane_avatar.jpg",
      "bio": "Passionate gardener and community volunteer",
      "skills": ["Gardening", "Composting", "Plant Care"],
      "location": {
        "city": "Bristol",
        "region": "South West England",
        "country": "United Kingdom"
      },
      "timebank": {
        "id": 2,
        "name": "Bristol Timebank"
      },
      "service_reach": "travel_ok",
      "privacy_level": "social",
      "joined": "2025-06-15 10:30:00"
    }
  ],
  "pagination": {
    "total": 12,
    "page": 1,
    "per_page": 20,
    "total_pages": 1,
    "has_more": false
  }
}
```

---

#### GET /api/v1/federation/members/{id}

Get detailed profile for a specific federated member.

**Required Permission:** `members:read`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | User ID |

**Request:**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://your-domain.com/api/v1/federation/members/156
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": {
    "id": 156,
    "username": "jane_smith",
    "name": "Jane Smith",
    "avatar": "/uploads/avatars/jane_avatar.jpg",
    "bio": "Passionate gardener and community volunteer with 10 years of experience.",
    "skills": ["Gardening", "Composting", "Plant Care", "Landscaping"],
    "location": {
      "city": "Bristol",
      "region": "South West England",
      "country": "United Kingdom"
    },
    "timebank": {
      "id": 2,
      "name": "Bristol Timebank"
    },
    "service_reach": "travel_ok",
    "privacy_level": "social",
    "accepts_messages": true,
    "accepts_transactions": true,
    "joined": "2025-06-15 10:30:00"
  }
}
```

**Error Response (Member not found):**
```json
{
  "error": true,
  "code": "MEMBER_NOT_FOUND",
  "message": "Member not found or not accessible",
  "timestamp": "2026-01-17T10:30:00+00:00"
}
```

---

#### GET /api/v1/federation/listings

Search federated listings (offers and requests) across partner timebanks.

**Required Permission:** `listings:read`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search query (matches title, description) |
| `type` | string | Filter by type: `offer` or `request` |
| `timebank_id` | integer | Filter by specific timebank |
| `category` | string | Category filter |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Results per page (default: 20, max: 100) |

**Request:**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "https://your-domain.com/api/v1/federation/listings?type=offer&category=Home%20%26%20Garden"
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": [
    {
      "id": 342,
      "title": "Garden Maintenance Help",
      "description": "I can help with weeding, pruning, and general garden maintenance.",
      "type": "offer",
      "category": "Home & Garden",
      "rate": "1 hour",
      "owner": {
        "id": 156,
        "name": "Jane Smith",
        "avatar": "/uploads/avatars/jane_avatar.jpg"
      },
      "timebank": {
        "id": 2,
        "name": "Bristol Timebank"
      },
      "created_at": "2026-01-05 14:20:00"
    }
  ],
  "pagination": {
    "total": 28,
    "page": 1,
    "per_page": 20,
    "total_pages": 2,
    "has_more": true
  }
}
```

---

#### GET /api/v1/federation/listings/{id}

Get detailed information for a specific federated listing.

**Required Permission:** `listings:read`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Listing ID |

**Request:**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://your-domain.com/api/v1/federation/listings/342
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "data": {
    "id": 342,
    "title": "Garden Maintenance Help",
    "description": "I can help with weeding, pruning, and general garden maintenance. Available weekends and some weekday evenings.",
    "type": "offer",
    "category": "Home & Garden",
    "rate": "1 hour",
    "owner": {
      "id": 156,
      "name": "Jane Smith",
      "avatar": "/uploads/avatars/jane_avatar.jpg",
      "city": "Bristol"
    },
    "timebank": {
      "id": 2,
      "name": "Bristol Timebank"
    },
    "created_at": "2026-01-05 14:20:00",
    "updated_at": "2026-01-10 09:15:00"
  }
}
```

---

#### POST /api/v1/federation/messages

Send a federated message to a member in a partner timebank.

**Required Permission:** `messages:write`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `recipient_id` | integer | Yes | Target user ID |
| `sender_id` | integer | Yes | Sender user ID (from your timebank) |
| `subject` | string | Yes | Message subject (max 255 chars) |
| `body` | string | Yes | Message content (max 10,000 chars) |

**Request:**
```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 156,
    "sender_id": 42,
    "subject": "Garden help inquiry",
    "body": "Hi Jane, I saw your listing for garden maintenance help. I have a small garden in Dublin that needs some attention. Would you be available for a video consultation to discuss what I need?"
  }' \
  https://your-domain.com/api/v1/federation/messages
```

**Success Response (201 Created):**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "message_id": 1247,
  "status": "sent"
}
```

**Error Response (Recipient doesn't accept messages):**
```json
{
  "error": true,
  "code": "MESSAGES_DISABLED",
  "message": "Recipient does not accept federated messages",
  "timestamp": "2026-01-17T10:30:00+00:00"
}
```

---

#### POST /api/v1/federation/transactions

Initiate a time credit transfer to a member in a partner timebank.

**Required Permission:** `transactions:write`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `recipient_id` | integer | Yes | Target user ID |
| `sender_id` | integer | Yes | Sender user ID (from your timebank) |
| `amount` | decimal | Yes | Hours to transfer (0.01 - 100) |
| `description` | string | Yes | Transaction description |

**Request:**
```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 156,
    "sender_id": 42,
    "amount": 2.5,
    "description": "Garden consultation and planning session"
  }' \
  https://your-domain.com/api/v1/federation/transactions
```

**Success Response (201 Created):**
```json
{
  "success": true,
  "timestamp": "2026-01-17T10:30:00+00:00",
  "transaction_id": 892,
  "status": "pending",
  "amount": 2.5,
  "note": "Transaction requires recipient confirmation"
}
```

**Error Response (Invalid amount):**
```json
{
  "error": true,
  "code": "INVALID_AMOUNT",
  "message": "Amount must be between 0 and 100 hours",
  "timestamp": "2026-01-17T10:30:00+00:00"
}
```

**Error Response (Recipient doesn't accept transactions):**
```json
{
  "error": true,
  "code": "TRANSACTIONS_DISABLED",
  "message": "Recipient does not accept federated transactions",
  "timestamp": "2026-01-17T10:30:00+00:00"
}
```

### 6.4 Error Codes Reference

| Code | HTTP Status | Description | Resolution |
|------|-------------|-------------|------------|
| `MISSING_API_KEY` | 401 | No API key provided | Include API key in Authorization header |
| `INVALID_API_KEY` | 401 | API key not recognized | Check key is correct and not expired |
| `PARTNER_INACTIVE` | 403 | Partner account suspended | Contact administrator |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests | Wait before retrying (limit: 1000/hour) |
| `PERMISSION_DENIED` | 403 | Missing required permission | Request additional scopes |
| `MEMBER_NOT_FOUND` | 404 | Member doesn't exist or isn't accessible | Check ID and partnership status |
| `LISTING_NOT_FOUND` | 404 | Listing doesn't exist or isn't accessible | Check ID and partnership status |
| `RECIPIENT_NOT_FOUND` | 404 | Message/transaction recipient not found | Verify recipient ID |
| `MESSAGES_DISABLED` | 403 | Recipient has disabled messages | User has opted out of messaging |
| `TRANSACTIONS_DISABLED` | 403 | Recipient has disabled transactions | User hasn't enabled economic federation |
| `VALIDATION_ERROR` | 400 | Invalid request data | Check required fields and formats |
| `INVALID_AMOUNT` | 400 | Transaction amount out of range | Use amount between 0.01 and 100 |

### 6.5 Code Examples

#### PHP (cURL)

```php
<?php
class FederationApiClient {
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function searchMembers(array $params = []): array {
        return $this->get('/api/v1/federation/members', $params);
    }

    public function getMember(int $id): array {
        return $this->get("/api/v1/federation/members/{$id}");
    }

    public function sendMessage(int $recipientId, int $senderId, string $subject, string $body): array {
        return $this->post('/api/v1/federation/messages', [
            'recipient_id' => $recipientId,
            'sender_id' => $senderId,
            'subject' => $subject,
            'body' => $body
        ]);
    }

    public function createTransaction(int $recipientId, int $senderId, float $amount, string $description): array {
        return $this->post('/api/v1/federation/transactions', [
            'recipient_id' => $recipientId,
            'sender_id' => $senderId,
            'amount' => $amount,
            'description' => $description
        ]);
    }

    private function get(string $endpoint, array $params = []): array {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function post(string $endpoint, array $data): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}

// Usage
$client = new FederationApiClient('https://partner-timebank.com', 'your_api_key');

// Search for gardeners in Bristol
$members = $client->searchMembers([
    'q' => 'gardening',
    'location' => 'Bristol',
    'per_page' => 10
]);

// Send a message
$result = $client->sendMessage(
    recipientId: 156,
    senderId: 42,
    subject: 'Garden help inquiry',
    body: 'Hi, I saw your listing...'
);
```

#### JavaScript (Fetch)

```javascript
class FederationApiClient {
  constructor(baseUrl, apiKey) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.apiKey = apiKey;
  }

  async searchMembers(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.get(`/api/v1/federation/members?${query}`);
  }

  async getMember(id) {
    return this.get(`/api/v1/federation/members/${id}`);
  }

  async getListings(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.get(`/api/v1/federation/listings?${query}`);
  }

  async sendMessage(recipientId, senderId, subject, body) {
    return this.post('/api/v1/federation/messages', {
      recipient_id: recipientId,
      sender_id: senderId,
      subject,
      body
    });
  }

  async createTransaction(recipientId, senderId, amount, description) {
    return this.post('/api/v1/federation/transactions', {
      recipient_id: recipientId,
      sender_id: senderId,
      amount,
      description
    });
  }

  async get(endpoint) {
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
        'Accept': 'application/json'
      }
    });
    return response.json();
  }

  async post(endpoint, data) {
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(data)
    });
    return response.json();
  }
}

// Usage
const client = new FederationApiClient('https://partner-timebank.com', 'your_api_key');

// Search members
const members = await client.searchMembers({
  q: 'gardening',
  location: 'Bristol',
  per_page: 10
});

// Get listings
const listings = await client.getListings({
  type: 'offer',
  category: 'Home & Garden'
});

// Send message
const result = await client.sendMessage(156, 42, 'Garden help', 'Hi...');
```

#### Python (requests)

```python
import requests
from typing import Optional, Dict, Any, List

class FederationApiClient:
    def __init__(self, base_url: str, api_key: str):
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.headers = {
            'Authorization': f'Bearer {api_key}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }

    def search_members(
        self,
        q: Optional[str] = None,
        timebank_id: Optional[int] = None,
        skills: Optional[List[str]] = None,
        location: Optional[str] = None,
        page: int = 1,
        per_page: int = 20
    ) -> Dict[str, Any]:
        params = {'page': page, 'per_page': per_page}
        if q:
            params['q'] = q
        if timebank_id:
            params['timebank_id'] = timebank_id
        if skills:
            params['skills'] = ','.join(skills)
        if location:
            params['location'] = location

        return self._get('/api/v1/federation/members', params)

    def get_member(self, member_id: int) -> Dict[str, Any]:
        return self._get(f'/api/v1/federation/members/{member_id}')

    def search_listings(
        self,
        q: Optional[str] = None,
        type: Optional[str] = None,
        timebank_id: Optional[int] = None,
        category: Optional[str] = None,
        page: int = 1,
        per_page: int = 20
    ) -> Dict[str, Any]:
        params = {'page': page, 'per_page': per_page}
        if q:
            params['q'] = q
        if type:
            params['type'] = type
        if timebank_id:
            params['timebank_id'] = timebank_id
        if category:
            params['category'] = category

        return self._get('/api/v1/federation/listings', params)

    def send_message(
        self,
        recipient_id: int,
        sender_id: int,
        subject: str,
        body: str
    ) -> Dict[str, Any]:
        return self._post('/api/v1/federation/messages', {
            'recipient_id': recipient_id,
            'sender_id': sender_id,
            'subject': subject,
            'body': body
        })

    def create_transaction(
        self,
        recipient_id: int,
        sender_id: int,
        amount: float,
        description: str
    ) -> Dict[str, Any]:
        return self._post('/api/v1/federation/transactions', {
            'recipient_id': recipient_id,
            'sender_id': sender_id,
            'amount': amount,
            'description': description
        })

    def _get(self, endpoint: str, params: Optional[Dict] = None) -> Dict[str, Any]:
        response = requests.get(
            f'{self.base_url}{endpoint}',
            headers=self.headers,
            params=params
        )
        return response.json()

    def _post(self, endpoint: str, data: Dict) -> Dict[str, Any]:
        response = requests.post(
            f'{self.base_url}{endpoint}',
            headers=self.headers,
            json=data
        )
        return response.json()


# Usage
client = FederationApiClient('https://partner-timebank.com', 'your_api_key')

# Search members with gardening skills
members = client.search_members(
    q='gardening',
    location='Bristol',
    per_page=10
)

# Get specific member
member = client.get_member(156)

# Send message
result = client.send_message(
    recipient_id=156,
    sender_id=42,
    subject='Garden help inquiry',
    body='Hi Jane, I saw your listing...'
)

# Create transaction
transaction = client.create_transaction(
    recipient_id=156,
    sender_id=42,
    amount=2.5,
    description='Garden consultation'
)
```

### 6.6 Rate Limiting

- Default rate limit: **1000 requests per hour** per API key
- Rate limits can be customized per API key by administrators
- When rate limited, the API returns HTTP 429 with `RATE_LIMIT_EXCEEDED` error
- Implement exponential backoff when receiving 429 responses

### 6.7 Best Practices

1. **Cache responses** - Member and listing data doesn't change frequently
2. **Use pagination** - Don't request more than you need; default is 20 items
3. **Handle errors gracefully** - Always check for error responses
4. **Respect rate limits** - Implement backoff when rate limited
5. **Store API keys securely** - Never expose keys in client-side code
6. **Log API calls** - Track your usage for debugging and auditing

---

## 7. Appendices

### 7.1 Complete Database Schema

```sql
-- 17 Federation Tables

federation_system_control     -- Global controls (singleton)
federation_tenant_whitelist   -- Approved tenants
federation_tenant_features    -- Per-tenant feature flags
federation_partnerships       -- Tenant relationships
federation_user_settings      -- User preferences
federation_messages           -- Cross-tenant messages
federation_transactions       -- Time credit exchanges
federation_reputation         -- Portable trust scores
federation_directory_profiles -- Public tenant profiles
federation_notifications      -- Federation alerts
federation_rate_limits        -- Rate limiting data
federation_audit_log          -- Activity audit trail
federation_realtime_queue     -- SSE event queue for notifications
federation_api_keys           -- External API authentication
federation_api_logs           -- API request audit trail
federation_exports            -- Data export history tracking
```

### 7.2 Service Class Reference

| Class | Namespace | Description |
|-------|-----------|-------------|
| `FederationGateway` | `Nexus\Services` | Central permission controller |
| `FederationFeatureService` | `Nexus\Services` | Feature flag management |
| `FederationPartnershipService` | `Nexus\Services` | Partnership lifecycle |
| `FederationAuditService` | `Nexus\Services` | Audit logging |
| `FederationUserService` | `Nexus\Services` | User settings |
| `FederationDirectoryService` | `Nexus\Services` | Directory operations |
| `FederationEmailService` | `Nexus\Services` | Email notifications |
| `FederationRealtimeService` | `Nexus\Services` | WebSocket events |
| `FederationActivityService` | `Nexus\Services` | Unified activity feed |
| `FederatedMessageService` | `Nexus\Services` | Messaging |
| `FederatedTransactionService` | `Nexus\Services` | Transactions |
| `FederatedGroupService` | `Nexus\Services` | Group membership |
| `FederationSearchService` | `Nexus\Services` | Advanced member search |
| `FederationJwtService` | `Nexus\Services` | JWT token generation & validation |
| `FederationApiMiddleware` | `Nexus\Middleware` | API auth (HMAC, JWT, API Key) |

### 7.3 Glossary

| Term | Definition |
|------|------------|
| **Tenant** | An independent timebank instance within our platform |
| **Federation** | The system enabling cross-tenant connectivity |
| **Partnership** | A bilateral agreement between two tenants |
| **Federation Level** | The depth of integration (1-4) |
| **Opt-In** | Explicit user consent to participate |
| **Whitelist** | List of approved tenants for federation |
| **Kill Switch** | Emergency control to disable federation |

### 6.4 Demo Access

A live demo environment is available for exploration:

**Demo URL:** https://project-nexus.ie/partner-demo

> **Note:** The platform is in Rapid Development stage. You may experience bugs until the platform is marked as fully stable, but the core federation backend and API are fully functional.

### 6.5 Contact Information

For technical queries regarding this integration:

**Technical Contact:** Jasper
**Email:** jasper@hour-timebank.ie

---

**Document Control**

| Version | Date        | Author | Changes                                                    |
|---------|-------------|--------|------------------------------------------------------------|
| 1.0     | 17 Jan 2026 | Jasper | Initial release                                            |
| 1.1     | 17 Jan 2026 | Claude | Added FederationActivityService, notification enhancements |
| 1.2     | 17 Jan 2026 | Claude | Added FederationSearchService, advanced member search UI   |
| 1.3     | 17 Jan 2026 | Claude | Added FederatedPartnerController, partner profile pages    |
| 1.4     | 17 Jan 2026 | Claude | Documented existing Federated Events Browser with RSVP     |
| 1.5     | 17 Jan 2026 | Claude | Documented existing Federated Listings Browser             |
| 1.6     | 17 Jan 2026 | Claude | Added Offline Support/PWA with service worker caching      |
| 1.7     | 17 Jan 2026 | Claude | Added Real-time Notifications via Pusher/SSE               |
| 1.8     | 17 Jan 2026 | Claude | Added Federation Search API with API key management        |
| 1.9     | 17 Jan 2026 | Claude | Added Bulk Import/Export for federation data               |
| 2.0     | 17 Jan 2026 | Claude | Added Cross-Platform Auth: HMAC-SHA256, JWT, OAuth token   |

---

*This document is confidential and intended for the named recipient organisation. The technical specifications described herein are subject to change as the integration develops.*
