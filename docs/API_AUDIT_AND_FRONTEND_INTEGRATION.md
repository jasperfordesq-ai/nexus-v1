# Project NEXUS - PHP Backend API Audit & React Frontend Integration Guide

> **Document Version**: 1.0
> **Last Updated**: February 2026
> **Audience**: Frontend Developers, Full-Stack Engineers, API Consumers

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [API Architecture Overview](#2-api-architecture-overview)
3. [Authentication System](#3-authentication-system)
4. [API Endpoints Reference](#4-api-endpoints-reference)
5. [Response Formats & Patterns](#5-response-formats--patterns)
6. [Error Handling](#6-error-handling)
7. [Rate Limiting](#7-rate-limiting)
8. [Frontend Integration Guide](#8-frontend-integration-guide)
9. [TypeScript Types](#9-typescript-types)
10. [Common Patterns & Best Practices](#10-common-patterns--best-practices)
11. [Security Considerations](#11-security-considerations)
12. [Testing & Debugging](#12-testing--debugging)
13. [Migration Notes](#13-migration-notes)

---

## 1. Executive Summary

### API Status: ✅ Production Ready

Project NEXUS provides a comprehensive RESTful API designed for the React frontend and mobile applications. The API follows modern best practices with:

| Feature | Status | Notes |
|---------|--------|-------|
| **JWT Authentication** | ✅ Implemented | Custom HS256 implementation |
| **Token Refresh** | ✅ Implemented | Mobile: 1yr access, 5yr refresh |
| **Multi-tenant** | ✅ Implemented | All queries scoped by tenant |
| **Rate Limiting** | ✅ Implemented | Per-user, per-action limits |
| **Error Codes** | ✅ Standardized | 50+ error codes with HTTP mapping |
| **Cursor Pagination** | ✅ Implemented | For large result sets |
| **OpenAPI Docs** | ⚠️ Partial | Available at `/api/docs` |

### Key Numbers

- **80+ V2 API Endpoints**
- **50+ API Controllers**
- **100+ Backend Services**
- **15+ API Modules** (Listings, Events, Messages, etc.)

---

## 2. API Architecture Overview

### 2.1 Route Structure

```
/api/v2/*     - Current REST API (React frontend)
/api/v1/*     - Legacy/Federation API
/api/*        - Traditional endpoints (mixed compatibility)
```

### 2.2 Base URLs

| Environment | URL |
|------------|-----|
| **Production** | `https://project-nexus.ie` |
| **Alt Production** | `https://hour-timebank.ie` |
| **Local Docker** | `http://localhost:8090` |

### 2.3 Module Overview

| Module | V2 Endpoints | Description |
|--------|-------------|-------------|
| **Auth** | 12 | Login, tokens, password reset, 2FA |
| **Listings** | 8 | Service offers/requests marketplace |
| **Events** | 10 | Community events with RSVPs |
| **Messages** | 8 | Private messaging & conversations |
| **Groups** | 12 | Community groups & discussions |
| **Users** | 6 | Profiles & preferences |
| **Wallet** | 6 | Time credits & transactions |
| **Feed** | 8 | Social feed & posts |
| **Notifications** | 6 | In-app notifications |
| **Gamification** | 10 | Badges, XP, leaderboards |
| **Volunteering** | 10 | Opportunities & applications |
| **Reviews** | 5 | User reviews & trust scores |
| **Goals** | 6 | Personal & community goals |
| **Search** | 3 | Unified search |
| **Connections** | 4 | Friend requests |

### 2.4 File Structure Reference

```
src/Controllers/Api/
├── BaseApiController.php          # Base class (ALL controllers extend this)
├── AuthController.php             # Authentication
├── TenantBootstrapController.php  # Public tenant config
├── ListingsApiController.php      # Listings CRUD
├── MessagesApiController.php      # Messaging
├── EventsApiController.php        # Events
├── UsersApiController.php         # User profiles
├── GroupsApiController.php        # Groups
├── WalletApiController.php        # Time credits
├── SocialApiController.php        # Feed/posts
├── NotificationsApiController.php # Notifications
├── GamificationV2ApiController.php # Gamification
├── VolunteerApiController.php     # Volunteering
├── ReviewsApiController.php       # Reviews
├── GoalsApiController.php         # Goals
├── SearchApiController.php        # Search
├── ConnectionsApiController.php   # Connections
├── RegistrationApiController.php  # User registration
├── PasswordResetApiController.php # Password reset
├── PushApiController.php          # Push notifications
├── MenuApiController.php          # Navigation menus
└── [30+ more controllers]

src/Services/
├── TokenService.php               # JWT token management
└── [100+ service files]

src/Core/
├── ApiAuth.php                    # Auth trait for controllers
├── ApiErrorCodes.php              # Error code definitions
├── RateLimiter.php                # Rate limiting
└── TenantContext.php              # Multi-tenant context
```

---

## 3. Authentication System

### 3.1 Token Architecture

The API uses a custom JWT-like token implementation:

```
Token Format: base64(header).base64(payload).base64(signature)
Algorithm: HS256 (HMAC-SHA256)
Secret: APP_KEY or JWT_SECRET from environment
```

### 3.2 Token Lifetimes

| Platform | Access Token | Refresh Token |
|----------|--------------|---------------|
| **Mobile** | 1 year | 5 years |
| **Web/Desktop** | 2 hours | 2 years |

> **Mobile Detection**: User-Agent containing `Capacitor`, `nexus-mobile`, `Mobile`, `Android`, `iPhone`, `iPad` OR headers `X-Capacitor-App`, `X-Nexus-Mobile`

### 3.3 Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     LOGIN FLOW                                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. POST /api/auth/login                                        │
│     Body: { email, password }                                   │
│                                                                  │
│  2. Response:                                                    │
│     {                                                            │
│       "success": true,                                           │
│       "user": { id, first_name, email, tenant_id, ... },        │
│       "access_token": "eyJhbGci...",                            │
│       "refresh_token": "eyJhbGci...",                           │
│       "token_type": "Bearer",                                    │
│       "expires_in": 7200                                         │
│     }                                                            │
│                                                                  │
│  3. Store both tokens securely                                   │
│                                                                  │
│  4. Use access_token in all API requests:                       │
│     Authorization: Bearer <access_token>                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.4 Token Refresh Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                   REFRESH FLOW                                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  On 401 Response:                                                │
│                                                                  │
│  1. POST /api/auth/refresh-token                                │
│     Body: { refresh_token: "<stored_refresh_token>" }           │
│                                                                  │
│  2. Success Response:                                            │
│     {                                                            │
│       "success": true,                                           │
│       "access_token": "eyJhbGci...",                            │
│       "token_type": "Bearer",                                    │
│       "expires_in": 7200                                         │
│     }                                                            │
│                                                                  │
│  3. Update stored access_token                                   │
│                                                                  │
│  4. Retry original request                                       │
│                                                                  │
│  On Refresh 401:                                                 │
│  → Redirect to login (refresh token expired/revoked)             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.5 Authentication Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/auth/login` | None | Email/password login |
| `POST` | `/api/auth/logout` | Bearer | Revoke current tokens |
| `POST` | `/api/auth/refresh-token` | None | Get new access token |
| `GET` | `/api/auth/validate-token` | Bearer | Check token validity |
| `POST` | `/api/auth/revoke` | Bearer | Revoke specific refresh token |
| `POST` | `/api/auth/revoke-all` | Bearer | Log out everywhere |
| `GET` | `/api/auth/csrf-token` | Session | Get CSRF token (legacy) |
| `POST` | `/api/v2/auth/register` | None | Register new user |
| `POST` | `/api/auth/forgot-password` | None | Request password reset |
| `POST` | `/api/auth/reset-password` | None | Reset with token |
| `POST` | `/api/auth/verify-email` | None | Verify email address |
| `POST` | `/api/auth/resend-verification` | None | Resend verification email |

### 3.6 Token Storage Recommendations

```typescript
// React Frontend Storage Strategy

// Access Token: Memory or short-lived storage
// - Store in React state/context
// - Or sessionStorage (cleared on browser close)
// - Never localStorage for access tokens

// Refresh Token: Secure storage
// - HttpOnly cookie (preferred, requires backend support)
// - Or localStorage with encryption
// - Or secure mobile storage (Capacitor SecureStorage)

// Example implementation:
const TokenStorage = {
  setAccessToken: (token: string) => {
    sessionStorage.setItem('access_token', token);
  },

  getAccessToken: () => {
    return sessionStorage.getItem('access_token');
  },

  setRefreshToken: (token: string) => {
    // For web, consider httpOnly cookie instead
    localStorage.setItem('refresh_token', token);
  },

  getRefreshToken: () => {
    return localStorage.getItem('refresh_token');
  },

  clear: () => {
    sessionStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  }
};
```

---

## 4. API Endpoints Reference

### 4.1 Bootstrap & Public Endpoints

These endpoints don't require authentication:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v2/tenant/bootstrap` | Tenant config (features, branding, SEO) |
| `GET` | `/api/v2/categories` | List categories (type=listing, event, etc.) |
| `GET` | `/api/v2/search/suggestions` | Search autocomplete |
| `GET` | `/api/docs` | OpenAPI documentation |
| `GET` | `/api/docs/openapi.json` | OpenAPI schema |

#### Bootstrap Response Example

```json
{
  "data": {
    "tenant": {
      "id": 1,
      "name": "Hour Timebank",
      "slug": "hour-timebank",
      "domain": "hour-timebank.project-nexus.ie"
    },
    "branding": {
      "logo_url": "https://...",
      "primary_color": "#6366f1",
      "secondary_color": "#8b5cf6"
    },
    "features": {
      "gamification": true,
      "federation": true,
      "volunteering": true,
      "wallet": true,
      "groups": true,
      "events": true,
      "ai_chat": true
    },
    "seo": {
      "site_name": "Hour Timebank",
      "tagline": "Community Timebanking",
      "description": "..."
    },
    "contact": {
      "email": "info@hour-timebank.ie",
      "phone": null,
      "address": null
    },
    "social": {
      "facebook": "https://facebook.com/...",
      "twitter": null
    }
  },
  "meta": {
    "cached_at": "2026-02-05T12:00:00Z",
    "cache_ttl": 600
  }
}
```

### 4.2 Listings API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/listings` | Optional | List with filters |
| `GET` | `/api/v2/listings/nearby` | Optional | Geospatial search |
| `GET` | `/api/v2/listings/{id}` | Optional | Get single listing |
| `POST` | `/api/v2/listings` | Required | Create listing |
| `PUT` | `/api/v2/listings/{id}` | Required | Update listing |
| `DELETE` | `/api/v2/listings/{id}` | Required | Delete listing |
| `POST` | `/api/v2/listings/{id}/image` | Required | Upload image |

#### Query Parameters (GET /api/v2/listings)

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `q` | string | - | Search query |
| `type` | string | - | `offer` or `request` |
| `category_id` | int | - | Filter by category |
| `user_id` | int | - | Filter by user |
| `status` | string | `active` | `active`, `completed`, `expired` |
| `cursor` | string | - | Pagination cursor |
| `limit` | int | 20 | Items per page (max 100) |

#### Listing Response Example

```json
{
  "data": [
    {
      "id": 123,
      "title": "Web Development Help",
      "description": "I can help with...",
      "type": "offer",
      "category": {
        "id": 5,
        "name": "Technology",
        "icon": "💻"
      },
      "user": {
        "id": 456,
        "name": "John Doe",
        "avatar_url": "https://...",
        "trust_score": 4.8
      },
      "time_credits": 2,
      "location": {
        "city": "Dublin",
        "latitude": 53.3498,
        "longitude": -6.2603
      },
      "images": ["https://..."],
      "status": "active",
      "created_at": "2026-02-01T10:00:00Z",
      "expires_at": "2026-03-01T10:00:00Z"
    }
  ],
  "meta": {
    "base_url": "https://project-nexus.ie",
    "cursor": "eyJpZCI6MTIzfQ==",
    "per_page": 20,
    "has_more": true
  }
}
```

### 4.3 Events API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/events` | Optional | List events |
| `GET` | `/api/v2/events/{id}` | Optional | Get event details |
| `POST` | `/api/v2/events` | Required | Create event |
| `PUT` | `/api/v2/events/{id}` | Required | Update event |
| `DELETE` | `/api/v2/events/{id}` | Required | Delete event |
| `POST` | `/api/v2/events/{id}/rsvp` | Required | RSVP to event |
| `DELETE` | `/api/v2/events/{id}/rsvp` | Required | Cancel RSVP |
| `GET` | `/api/v2/events/{id}/attendees` | Optional | List attendees |

#### Event Response Example

```json
{
  "data": {
    "id": 789,
    "title": "Community Meetup",
    "description": "Monthly gathering...",
    "start_date": "2026-02-15T18:00:00Z",
    "end_date": "2026-02-15T20:00:00Z",
    "location": {
      "name": "Community Center",
      "address": "123 Main St",
      "city": "Dublin",
      "latitude": 53.3498,
      "longitude": -6.2603
    },
    "organizer": {
      "id": 456,
      "name": "John Doe",
      "avatar_url": "https://..."
    },
    "category": {
      "id": 3,
      "name": "Social",
      "icon": "🎉"
    },
    "image_url": "https://...",
    "capacity": 50,
    "rsvp_count": {
      "going": 25,
      "maybe": 10,
      "not_going": 5
    },
    "current_user_rsvp": "going",
    "is_online": false,
    "meeting_url": null,
    "status": "upcoming"
  },
  "meta": {
    "base_url": "https://project-nexus.ie"
  }
}
```

### 4.4 Messages API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/messages/conversations` | Required | List conversations |
| `GET` | `/api/v2/messages/unread-count` | Required | Unread badge count |
| `GET` | `/api/v2/messages/conversations/{id}` | Required | Get messages |
| `POST` | `/api/v2/messages` | Required | Send message |
| `POST` | `/api/v2/messages/conversations/{id}/read` | Required | Mark as read |
| `POST` | `/api/v2/messages/conversations/{id}/archive` | Required | Archive |
| `POST` | `/api/v2/messages/voice` | Required | Upload voice message |

#### Conversation Response Example

```json
{
  "data": [
    {
      "id": 101,
      "participant": {
        "id": 456,
        "name": "Jane Smith",
        "avatar_url": "https://...",
        "is_online": true
      },
      "last_message": {
        "id": 999,
        "content": "Thanks for your help!",
        "sent_at": "2026-02-05T11:30:00Z",
        "is_mine": false,
        "read_at": null
      },
      "unread_count": 2,
      "is_archived": false,
      "created_at": "2026-02-01T10:00:00Z"
    }
  ],
  "meta": {
    "cursor": "eyJpZCI6MTAxfQ==",
    "has_more": true
  }
}
```

### 4.5 Users API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/users/me` | Required | Get authenticated user |
| `GET` | `/api/v2/users/{id}` | Optional | Get public profile |
| `PUT` | `/api/v2/users/me` | Required | Update profile |
| `PUT` | `/api/v2/users/me/preferences` | Required | Update preferences |
| `POST` | `/api/v2/users/me/avatar` | Required | Upload avatar |
| `PUT` | `/api/v2/users/me/password` | Required | Change password |

#### User Profile Response Example

```json
{
  "data": {
    "id": 456,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "avatar_url": "https://...",
    "bio": "Community enthusiast...",
    "location": {
      "city": "Dublin",
      "country": "Ireland"
    },
    "member_since": "2025-06-15T00:00:00Z",
    "stats": {
      "listings_count": 12,
      "transactions_count": 45,
      "hours_given": 67,
      "hours_received": 34,
      "trust_score": 4.8,
      "reviews_count": 23
    },
    "gamification": {
      "level": 15,
      "xp": 4500,
      "badges_count": 8
    },
    "privacy": {
      "show_email": false,
      "show_location": true,
      "show_stats": true
    }
  }
}
```

### 4.6 Wallet API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/wallet/balance` | Required | Get balance |
| `GET` | `/api/v2/wallet/transactions` | Required | Transaction history |
| `GET` | `/api/v2/wallet/transactions/{id}` | Required | Transaction details |
| `POST` | `/api/v2/wallet/transfer` | Required | Transfer credits |
| `GET` | `/api/v2/wallet/stats` | Required | Wallet statistics |

#### Wallet Balance Response Example

```json
{
  "data": {
    "balance": 25.5,
    "pending_incoming": 2.0,
    "pending_outgoing": 1.5,
    "available": 24.0,
    "currency": "hours",
    "last_transaction": {
      "id": 555,
      "amount": 2.0,
      "type": "received",
      "from_user": {
        "id": 789,
        "name": "Jane Smith"
      },
      "description": "Web design help",
      "created_at": "2026-02-04T15:00:00Z"
    }
  }
}
```

### 4.7 Notifications API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/notifications` | Required | List notifications |
| `GET` | `/api/v2/notifications/counts` | Required | Summary counts |
| `GET` | `/api/v2/notifications/{id}` | Required | Get notification |
| `POST` | `/api/v2/notifications/{id}/read` | Required | Mark as read |
| `POST` | `/api/v2/notifications/read-all` | Required | Mark all read |
| `DELETE` | `/api/v2/notifications/{id}` | Required | Delete |

### 4.8 Groups API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/groups` | Optional | List groups |
| `GET` | `/api/v2/groups/{id}` | Optional | Group details |
| `POST` | `/api/v2/groups` | Required | Create group |
| `PUT` | `/api/v2/groups/{id}` | Required | Update group |
| `DELETE` | `/api/v2/groups/{id}` | Required | Delete group |
| `POST` | `/api/v2/groups/{id}/join` | Required | Join group |
| `POST` | `/api/v2/groups/{id}/leave` | Required | Leave group |
| `GET` | `/api/v2/groups/{id}/members` | Optional | List members |
| `GET` | `/api/v2/groups/{id}/discussions` | Optional | List discussions |
| `POST` | `/api/v2/groups/{id}/discussions` | Required | Post to discussion |

### 4.9 Feed API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/feed` | Optional | Get feed posts |
| `GET` | `/api/v2/feed/{id}` | Optional | Get single post |
| `POST` | `/api/v2/feed` | Required | Create post |
| `PUT` | `/api/v2/feed/{id}` | Required | Update post |
| `DELETE` | `/api/v2/feed/{id}` | Required | Delete post |
| `POST` | `/api/v2/feed/{id}/like` | Required | Like post |
| `DELETE` | `/api/v2/feed/{id}/like` | Required | Unlike post |
| `GET` | `/api/v2/feed/{id}/comments` | Optional | Get comments |
| `POST` | `/api/v2/feed/{id}/comments` | Required | Add comment |

### 4.10 Gamification API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/gamification/profile` | Required | User stats |
| `GET` | `/api/v2/gamification/badges` | Optional | All badges |
| `GET` | `/api/v2/gamification/badges/{id}` | Optional | Badge details |
| `GET` | `/api/v2/gamification/leaderboard` | Optional | Rankings |
| `GET` | `/api/v2/gamification/challenges` | Required | Active challenges |
| `GET` | `/api/v2/gamification/daily-reward` | Required | Daily reward status |
| `POST` | `/api/v2/gamification/daily-reward` | Required | Claim daily reward |
| `GET` | `/api/v2/gamification/shop` | Required | Cosmetic shop |
| `POST` | `/api/v2/gamification/shop/{id}/purchase` | Required | Buy item |

### 4.11 Search API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v2/search` | Optional | Unified search |
| `GET` | `/api/v2/search/suggestions` | Optional | Autocomplete |

#### Search Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search query (required) |
| `type` | string | Filter: `all`, `listings`, `events`, `users`, `groups` |
| `limit` | int | Results per type (default 10) |

### 4.12 Push Notifications API

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/push/vapid-key` | None | VAPID public key |
| `POST` | `/api/push/subscribe` | Required | Subscribe to push |
| `POST` | `/api/push/unsubscribe` | Required | Unsubscribe |
| `GET` | `/api/push/status` | Required | Subscription status |
| `POST` | `/api/push/register-device` | Required | Register FCM device |
| `POST` | `/api/push/unregister-device` | Required | Unregister device |

---

## 5. Response Formats & Patterns

### 5.1 V2 Response Envelope (Standard)

All V2 API endpoints return responses in this format:

```typescript
// Single Resource
interface ApiResponse<T> {
  data: T;
  meta?: {
    base_url: string;
    [key: string]: any;
  };
}

// Collection with Cursor Pagination
interface ApiCollectionResponse<T> {
  data: T[];
  meta: {
    base_url: string;
    cursor: string | null;
    per_page: number;
    has_more: boolean;
  };
}

// Collection with Offset Pagination
interface ApiPaginatedResponse<T> {
  data: T[];
  meta: {
    base_url: string;
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
    has_more: boolean;
  };
}

// Error Response
interface ApiErrorResponse {
  errors: Array<{
    code: string;
    message: string;
    field?: string;
  }>;
}
```

### 5.2 HTTP Status Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| `200` | OK | Successful GET, PUT |
| `201` | Created | Successful POST (resource created) |
| `204` | No Content | Successful DELETE |
| `400` | Bad Request | Validation error |
| `401` | Unauthorized | Missing/invalid/expired token |
| `403` | Forbidden | Insufficient permissions |
| `404` | Not Found | Resource doesn't exist |
| `409` | Conflict | Duplicate/conflict |
| `410` | Gone | Resource was deleted |
| `413` | Payload Too Large | Upload too large |
| `422` | Unprocessable Entity | Validation errors (multiple) |
| `429` | Too Many Requests | Rate limited |
| `500` | Server Error | Internal error |
| `503` | Service Unavailable | Maintenance |

### 5.3 Response Headers

Every API response includes these headers:

```
Content-Type: application/json
API-Version: 2.0
X-Tenant-ID: 1
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
X-RateLimit-Reset: 1707134400
```

---

## 6. Error Handling

### 6.1 Error Response Format

```json
{
  "errors": [
    {
      "code": "VALIDATION_REQUIRED_FIELD",
      "message": "Title is required",
      "field": "title"
    },
    {
      "code": "VALIDATION_INVALID_FORMAT",
      "message": "Email format is invalid",
      "field": "email"
    }
  ]
}
```

### 6.2 Error Codes Reference

#### Authentication Errors (401, 403)

| Code | HTTP | Description |
|------|------|-------------|
| `AUTH_TOKEN_MISSING` | 401 | No token provided |
| `AUTH_TOKEN_INVALID` | 401 | Malformed/invalid token |
| `AUTH_TOKEN_EXPIRED` | 401 | Token has expired |
| `AUTH_REFRESH_REQUIRED` | 401 | Need to refresh token |
| `AUTH_INVALID_CREDENTIALS` | 401 | Wrong email/password |
| `AUTH_INSUFFICIENT_PERMISSIONS` | 403 | Not allowed |
| `AUTH_ACCOUNT_SUSPENDED` | 403 | Account suspended |
| `AUTH_CSRF_INVALID` | 403 | Invalid CSRF token |
| `AUTH_2FA_REQUIRED` | 401 | 2FA code needed |
| `AUTH_2FA_INVALID` | 401 | Wrong 2FA code |

#### Validation Errors (400, 422)

| Code | HTTP | Description |
|------|------|-------------|
| `VALIDATION_REQUIRED_FIELD` | 400 | Field is required |
| `VALIDATION_INVALID_FORMAT` | 400 | Wrong format |
| `VALIDATION_INVALID_VALUE` | 400 | Invalid value |
| `VALIDATION_DUPLICATE` | 409 | Already exists |
| `VALIDATION_TOO_SHORT` | 400 | Below min length |
| `VALIDATION_TOO_LONG` | 400 | Above max length |
| `VALIDATION_OUT_OF_RANGE` | 400 | Number out of range |

#### Resource Errors (404, 409, 410)

| Code | HTTP | Description |
|------|------|-------------|
| `RESOURCE_NOT_FOUND` | 404 | Resource doesn't exist |
| `RESOURCE_ALREADY_EXISTS` | 409 | Duplicate resource |
| `RESOURCE_CONFLICT` | 409 | State conflict |
| `RESOURCE_DELETED` | 410 | Was deleted |
| `RESOURCE_FORBIDDEN` | 403 | No access |

#### Rate Limiting (429)

| Code | HTTP | Description |
|------|------|-------------|
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |

#### Upload Errors (400, 413)

| Code | HTTP | Description |
|------|------|-------------|
| `UPLOAD_NO_FILE` | 400 | No file provided |
| `UPLOAD_INVALID_TYPE` | 400 | Wrong file type |
| `UPLOAD_TOO_LARGE` | 413 | File too big |
| `UPLOAD_FAILED` | 500 | Upload failed |

#### Server Errors (500, 503)

| Code | HTTP | Description |
|------|------|-------------|
| `SERVER_INTERNAL_ERROR` | 500 | Internal error |
| `SERVER_MAINTENANCE` | 503 | Under maintenance |
| `SERVER_DEPENDENCY_FAILED` | 503 | External service down |

### 6.3 Frontend Error Handling Strategy

```typescript
// src/lib/api-error-handler.ts

interface ApiError {
  code: string;
  message: string;
  field?: string;
}

export function handleApiError(error: any): void {
  if (!error.response) {
    // Network error
    showToast('Network error. Please check your connection.', 'error');
    return;
  }

  const status = error.response.status;
  const errors: ApiError[] = error.response.data?.errors || [];

  switch (status) {
    case 401:
      if (errors.some(e => e.code === 'AUTH_TOKEN_EXPIRED')) {
        // Try token refresh
        return refreshAndRetry(error.config);
      }
      if (errors.some(e => e.code === 'AUTH_REFRESH_REQUIRED')) {
        // Redirect to login
        redirectToLogin();
        return;
      }
      showToast('Please log in again.', 'error');
      break;

    case 403:
      showToast('You don\'t have permission to do that.', 'error');
      break;

    case 404:
      showToast('Resource not found.', 'error');
      break;

    case 422:
      // Return errors for form handling
      return errors;

    case 429:
      const retryAfter = error.response.headers['retry-after'] || 30;
      showToast(`Too many requests. Please wait ${retryAfter} seconds.`, 'warning');
      break;

    case 503:
      showToast('Service temporarily unavailable. Please try again later.', 'error');
      break;

    default:
      showToast('An unexpected error occurred.', 'error');
  }
}
```

---

## 7. Rate Limiting

### 7.1 Rate Limits by Endpoint Type

| Endpoint Type | Requests | Window | Example |
|--------------|----------|--------|---------|
| **Read (GET)** | 120 | 1 minute | Listings, events, profiles |
| **Write (POST/PUT/DELETE)** | 60 | 1 minute | Create, update, delete |
| **Upload** | 20 | 1 minute | Images, files |
| **Auth** | 10 | 1 minute | Login, register |
| **Search** | 30 | 1 minute | Search queries |

### 7.2 Rate Limit Headers

```
X-RateLimit-Limit: 60        # Max requests in window
X-RateLimit-Remaining: 45    # Remaining requests
X-RateLimit-Reset: 1707134400  # Unix timestamp when resets
Retry-After: 30              # Seconds to wait (on 429)
```

### 7.3 Frontend Rate Limit Handling

```typescript
// src/lib/api.ts

import axios from 'axios';

const api = axios.create({
  baseURL: '/api/v2',
});

// Track rate limits
let rateLimitRemaining = 60;
let rateLimitReset = 0;

api.interceptors.response.use(
  (response) => {
    // Update rate limit tracking
    rateLimitRemaining = parseInt(response.headers['x-ratelimit-remaining'] || '60');
    rateLimitReset = parseInt(response.headers['x-ratelimit-reset'] || '0');
    return response;
  },
  async (error) => {
    if (error.response?.status === 429) {
      const retryAfter = parseInt(error.response.headers['retry-after'] || '30');

      // Option 1: Auto-retry after delay
      await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
      return api.request(error.config);

      // Option 2: Show user message and don't retry
      // throw new RateLimitError(retryAfter);
    }
    throw error;
  }
);

export { api, rateLimitRemaining, rateLimitReset };
```

---

## 8. Frontend Integration Guide

### 8.1 API Client Setup

```typescript
// src/lib/api.ts

import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

interface TokenStorage {
  getAccessToken(): string | null;
  setAccessToken(token: string): void;
  getRefreshToken(): string | null;
  setRefreshToken(token: string): void;
  clear(): void;
}

const tokenStorage: TokenStorage = {
  getAccessToken: () => sessionStorage.getItem('access_token'),
  setAccessToken: (token) => sessionStorage.setItem('access_token', token),
  getRefreshToken: () => localStorage.getItem('refresh_token'),
  setRefreshToken: (token) => localStorage.setItem('refresh_token', token),
  clear: () => {
    sessionStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  }
};

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v2',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor - add auth token
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = tokenStorage.getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor - handle token refresh
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: unknown) => void;
  reject: (reason?: any) => void;
}> = [];

const processQueue = (error: any = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve();
    }
  });
  failedQueue = [];
};

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        }).then(() => api(originalRequest));
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const refreshToken = tokenStorage.getRefreshToken();
        if (!refreshToken) {
          throw new Error('No refresh token');
        }

        const response = await axios.post('/api/auth/refresh-token', {
          refresh_token: refreshToken,
        });

        const { access_token } = response.data;
        tokenStorage.setAccessToken(access_token);

        processQueue();
        return api(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError);
        tokenStorage.clear();
        window.location.href = '/login';
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);

export { api, tokenStorage };
```

### 8.2 Authentication Context

```typescript
// src/contexts/AuthContext.tsx

import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { api, tokenStorage } from '../lib/api';

interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  avatar_url: string | null;
  tenant_id: number;
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check for existing token on mount
    const initAuth = async () => {
      const token = tokenStorage.getAccessToken();
      if (token) {
        try {
          const response = await api.get('/users/me');
          setUser(response.data.data);
        } catch (error) {
          // Token invalid, clear it
          tokenStorage.clear();
        }
      }
      setIsLoading(false);
    };
    initAuth();
  }, []);

  const login = async (email: string, password: string) => {
    const response = await api.post('/api/auth/login', { email, password });
    const { user, access_token, refresh_token } = response.data;

    tokenStorage.setAccessToken(access_token);
    tokenStorage.setRefreshToken(refresh_token);
    setUser(user);
  };

  const logout = async () => {
    try {
      await api.post('/api/auth/logout');
    } catch (error) {
      // Ignore errors during logout
    }
    tokenStorage.clear();
    setUser(null);
  };

  const refreshUser = async () => {
    const response = await api.get('/users/me');
    setUser(response.data.data);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user,
        isLoading,
        login,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}
```

### 8.3 Tenant Bootstrap

```typescript
// src/contexts/TenantContext.tsx

import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { api } from '../lib/api';

interface TenantConfig {
  tenant: {
    id: number;
    name: string;
    slug: string;
    domain: string;
  };
  branding: {
    logo_url: string | null;
    primary_color: string;
    secondary_color: string;
  };
  features: {
    gamification: boolean;
    federation: boolean;
    volunteering: boolean;
    wallet: boolean;
    groups: boolean;
    events: boolean;
    ai_chat: boolean;
    [key: string]: boolean;
  };
  seo: {
    site_name: string;
    tagline: string;
    description: string;
  };
}

interface TenantContextType {
  config: TenantConfig | null;
  isLoading: boolean;
  hasFeature: (feature: string) => boolean;
}

const TenantContext = createContext<TenantContextType | undefined>(undefined);

export function TenantProvider({ children }: { children: ReactNode }) {
  const [config, setConfig] = useState<TenantConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchConfig = async () => {
      try {
        const response = await api.get('/tenant/bootstrap');
        setConfig(response.data.data);
      } catch (error) {
        console.error('Failed to load tenant config:', error);
      } finally {
        setIsLoading(false);
      }
    };
    fetchConfig();
  }, []);

  const hasFeature = (feature: string): boolean => {
    return config?.features?.[feature] ?? false;
  };

  return (
    <TenantContext.Provider value={{ config, isLoading, hasFeature }}>
      {children}
    </TenantContext.Provider>
  );
}

export function useTenant() {
  const context = useContext(TenantContext);
  if (!context) {
    throw new Error('useTenant must be used within TenantProvider');
  }
  return context;
}
```

### 8.4 Pagination Hook

```typescript
// src/hooks/useCursorPagination.ts

import { useState, useCallback } from 'react';
import { api } from '../lib/api';

interface UseCursorPaginationOptions<T> {
  endpoint: string;
  params?: Record<string, any>;
  limit?: number;
}

interface UseCursorPaginationResult<T> {
  data: T[];
  isLoading: boolean;
  isLoadingMore: boolean;
  error: Error | null;
  hasMore: boolean;
  loadMore: () => Promise<void>;
  refresh: () => Promise<void>;
}

export function useCursorPagination<T>({
  endpoint,
  params = {},
  limit = 20,
}: UseCursorPaginationOptions<T>): UseCursorPaginationResult<T> {
  const [data, setData] = useState<T[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<Error | null>(null);

  const fetchData = useCallback(
    async (isRefresh = false) => {
      try {
        const currentCursor = isRefresh ? null : cursor;

        if (isRefresh) {
          setIsLoading(true);
        } else if (currentCursor) {
          setIsLoadingMore(true);
        } else {
          setIsLoading(true);
        }

        const response = await api.get(endpoint, {
          params: {
            ...params,
            limit,
            cursor: currentCursor,
          },
        });

        const { data: newData, meta } = response.data;

        if (isRefresh || !currentCursor) {
          setData(newData);
        } else {
          setData((prev) => [...prev, ...newData]);
        }

        setCursor(meta.cursor);
        setHasMore(meta.has_more);
        setError(null);
      } catch (err) {
        setError(err as Error);
      } finally {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    },
    [endpoint, params, limit, cursor]
  );

  const loadMore = useCallback(async () => {
    if (!hasMore || isLoadingMore) return;
    await fetchData(false);
  }, [fetchData, hasMore, isLoadingMore]);

  const refresh = useCallback(async () => {
    setCursor(null);
    await fetchData(true);
  }, [fetchData]);

  // Initial load
  useEffect(() => {
    fetchData(true);
  }, [endpoint, JSON.stringify(params)]);

  return {
    data,
    isLoading,
    isLoadingMore,
    error,
    hasMore,
    loadMore,
    refresh,
  };
}
```

### 8.5 Service Layer Examples

```typescript
// src/services/listings.ts

import { api } from '../lib/api';
import type { Listing, ListingFormData, ListingsResponse } from '../types';

export const listingsService = {
  async getAll(params?: {
    q?: string;
    type?: 'offer' | 'request';
    category_id?: number;
    cursor?: string;
    limit?: number;
  }): Promise<ListingsResponse> {
    const response = await api.get('/listings', { params });
    return response.data;
  },

  async getById(id: number): Promise<{ data: Listing }> {
    const response = await api.get(`/listings/${id}`);
    return response.data;
  },

  async create(data: ListingFormData): Promise<{ data: Listing }> {
    const response = await api.post('/listings', data);
    return response.data;
  },

  async update(id: number, data: Partial<ListingFormData>): Promise<{ data: Listing }> {
    const response = await api.put(`/listings/${id}`, data);
    return response.data;
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/listings/${id}`);
  },

  async uploadImage(id: number, file: File): Promise<{ data: { url: string } }> {
    const formData = new FormData();
    formData.append('image', file);
    const response = await api.post(`/listings/${id}/image`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  },

  async getNearby(params: {
    latitude: number;
    longitude: number;
    radius?: number;
    limit?: number;
  }): Promise<ListingsResponse> {
    const response = await api.get('/listings/nearby', { params });
    return response.data;
  },
};
```

```typescript
// src/services/messages.ts

import { api } from '../lib/api';
import type { Conversation, Message, ConversationsResponse, MessagesResponse } from '../types';

export const messagesService = {
  async getConversations(params?: {
    cursor?: string;
    limit?: number;
  }): Promise<ConversationsResponse> {
    const response = await api.get('/messages/conversations', { params });
    return response.data;
  },

  async getUnreadCount(): Promise<{ data: { count: number } }> {
    const response = await api.get('/messages/unread-count');
    return response.data;
  },

  async getMessages(conversationId: number, params?: {
    cursor?: string;
    limit?: number;
  }): Promise<MessagesResponse> {
    const response = await api.get(`/messages/conversations/${conversationId}`, { params });
    return response.data;
  },

  async send(recipientId: number, content: string): Promise<{ data: Message }> {
    const response = await api.post('/messages', {
      recipient_id: recipientId,
      content,
    });
    return response.data;
  },

  async markAsRead(conversationId: number): Promise<void> {
    await api.post(`/messages/conversations/${conversationId}/read`);
  },

  async archive(conversationId: number): Promise<void> {
    await api.post(`/messages/conversations/${conversationId}/archive`);
  },
};
```

### 8.6 Component Examples

```tsx
// src/pages/listings/ListingsPage.tsx

import React, { useState } from 'react';
import { useCursorPagination } from '../../hooks/useCursorPagination';
import { ListingCard } from '../../components/listings/ListingCard';
import { ListingFilters } from '../../components/listings/ListingFilters';
import type { Listing } from '../../types';

export function ListingsPage() {
  const [filters, setFilters] = useState({
    type: undefined as 'offer' | 'request' | undefined,
    category_id: undefined as number | undefined,
    q: '',
  });

  const {
    data: listings,
    isLoading,
    isLoadingMore,
    hasMore,
    loadMore,
    refresh,
  } = useCursorPagination<Listing>({
    endpoint: '/listings',
    params: filters,
  });

  return (
    <div className="listings-page">
      <h1>Listings</h1>

      <ListingFilters
        filters={filters}
        onChange={(newFilters) => setFilters({ ...filters, ...newFilters })}
        onSearch={() => refresh()}
      />

      {isLoading ? (
        <div className="loading">Loading...</div>
      ) : (
        <>
          <div className="listings-grid">
            {listings.map((listing) => (
              <ListingCard key={listing.id} listing={listing} />
            ))}
          </div>

          {hasMore && (
            <button
              onClick={loadMore}
              disabled={isLoadingMore}
              className="load-more-btn"
            >
              {isLoadingMore ? 'Loading...' : 'Load More'}
            </button>
          )}

          {!hasMore && listings.length > 0 && (
            <p className="end-message">No more listings</p>
          )}

          {listings.length === 0 && (
            <p className="empty-message">No listings found</p>
          )}
        </>
      )}
    </div>
  );
}
```

---

## 9. TypeScript Types

### 9.1 Core Types

```typescript
// src/types/api.ts

// Base API Response Types
export interface ApiMeta {
  base_url: string;
  [key: string]: any;
}

export interface CursorMeta extends ApiMeta {
  cursor: string | null;
  per_page: number;
  has_more: boolean;
}

export interface PaginationMeta extends ApiMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_more: boolean;
}

export interface ApiResponse<T> {
  data: T;
  meta?: ApiMeta;
}

export interface ApiCollectionResponse<T> {
  data: T[];
  meta: CursorMeta;
}

export interface ApiPaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface ApiError {
  code: string;
  message: string;
  field?: string;
}

export interface ApiErrorResponse {
  errors: ApiError[];
}
```

### 9.2 Domain Types

```typescript
// src/types/user.ts

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  avatar_url: string | null;
  bio: string | null;
  location: {
    city: string | null;
    country: string | null;
  };
  member_since: string;
  stats: {
    listings_count: number;
    transactions_count: number;
    hours_given: number;
    hours_received: number;
    trust_score: number;
    reviews_count: number;
  };
  gamification?: {
    level: number;
    xp: number;
    badges_count: number;
  };
  privacy: {
    show_email: boolean;
    show_location: boolean;
    show_stats: boolean;
  };
}

export interface PublicUser {
  id: number;
  name: string;
  avatar_url: string | null;
  trust_score?: number;
  is_online?: boolean;
}
```

```typescript
// src/types/listing.ts

export interface Category {
  id: number;
  name: string;
  icon: string;
  parent_id?: number;
}

export interface Listing {
  id: number;
  title: string;
  description: string;
  type: 'offer' | 'request';
  category: Category;
  user: PublicUser;
  time_credits: number;
  location: {
    city: string | null;
    latitude: number | null;
    longitude: number | null;
  };
  images: string[];
  status: 'active' | 'completed' | 'expired' | 'draft';
  created_at: string;
  updated_at: string;
  expires_at: string | null;
}

export interface ListingFormData {
  title: string;
  description: string;
  type: 'offer' | 'request';
  category_id: number;
  time_credits: number;
  city?: string;
  latitude?: number;
  longitude?: number;
}

export type ListingsResponse = ApiCollectionResponse<Listing>;
```

```typescript
// src/types/event.ts

export interface Event {
  id: number;
  title: string;
  description: string;
  start_date: string;
  end_date: string;
  location: {
    name: string;
    address: string;
    city: string;
    latitude: number | null;
    longitude: number | null;
  };
  organizer: PublicUser;
  category: Category;
  image_url: string | null;
  capacity: number | null;
  rsvp_count: {
    going: number;
    maybe: number;
    not_going: number;
  };
  current_user_rsvp: 'going' | 'maybe' | 'not_going' | null;
  is_online: boolean;
  meeting_url: string | null;
  status: 'upcoming' | 'ongoing' | 'past' | 'cancelled';
}

export type RsvpStatus = 'going' | 'maybe' | 'not_going';

export type EventsResponse = ApiCollectionResponse<Event>;
```

```typescript
// src/types/message.ts

export interface Message {
  id: number;
  content: string;
  sent_at: string;
  read_at: string | null;
  is_mine: boolean;
  type: 'text' | 'voice' | 'image';
  attachment_url?: string;
}

export interface Conversation {
  id: number;
  participant: PublicUser;
  last_message: Message | null;
  unread_count: number;
  is_archived: boolean;
  created_at: string;
}

export type ConversationsResponse = ApiCollectionResponse<Conversation>;
export type MessagesResponse = ApiCollectionResponse<Message>;
```

```typescript
// src/types/wallet.ts

export interface WalletBalance {
  balance: number;
  pending_incoming: number;
  pending_outgoing: number;
  available: number;
  currency: 'hours';
  last_transaction: Transaction | null;
}

export interface Transaction {
  id: number;
  amount: number;
  type: 'sent' | 'received' | 'earned' | 'bonus';
  from_user: PublicUser | null;
  to_user: PublicUser | null;
  description: string;
  listing_id: number | null;
  created_at: string;
  status: 'completed' | 'pending' | 'cancelled';
}

export type TransactionsResponse = ApiCollectionResponse<Transaction>;
```

```typescript
// src/types/notification.ts

export interface Notification {
  id: number;
  type: string;
  title: string;
  message: string;
  data: Record<string, any>;
  read_at: string | null;
  created_at: string;
  action_url: string | null;
}

export interface NotificationCounts {
  total: number;
  unread: number;
  by_type: Record<string, number>;
}

export type NotificationsResponse = ApiCollectionResponse<Notification>;
```

---

## 10. Common Patterns & Best Practices

### 10.1 Request Headers

Always include these headers:

```typescript
{
  'Content-Type': 'application/json',
  'Authorization': 'Bearer <token>',  // When authenticated
  'Accept': 'application/json',
}
```

### 10.2 Cursor Pagination Pattern

```typescript
// Initial request
GET /api/v2/listings?limit=20

// Load more (use cursor from previous response)
GET /api/v2/listings?limit=20&cursor=eyJpZCI6MTAwfQ==
```

### 10.3 Search Pattern

```typescript
// Unified search
GET /api/v2/search?q=web+development&type=all&limit=10

// Autocomplete
GET /api/v2/search/suggestions?q=web
```

### 10.4 File Upload Pattern

```typescript
// Always use multipart/form-data for uploads
const formData = new FormData();
formData.append('image', file);

await api.post('/listings/123/image', formData, {
  headers: { 'Content-Type': 'multipart/form-data' },
});
```

### 10.5 Optimistic Updates

```typescript
// Example: Like a post
const likePost = async (postId: number) => {
  // Optimistically update UI
  setLiked(true);
  setLikeCount(prev => prev + 1);

  try {
    await api.post(`/feed/${postId}/like`);
  } catch (error) {
    // Revert on error
    setLiked(false);
    setLikeCount(prev => prev - 1);
    showToast('Failed to like post', 'error');
  }
};
```

### 10.6 Real-time Updates

The API supports real-time updates via Pusher:

```typescript
// src/lib/pusher.ts
import Pusher from 'pusher-js';

const pusher = new Pusher(import.meta.env.VITE_PUSHER_KEY, {
  cluster: import.meta.env.VITE_PUSHER_CLUSTER,
});

// Subscribe to user channel for notifications
export const subscribeToNotifications = (userId: number, callback: (data: any) => void) => {
  const channel = pusher.subscribe(`private-user.${userId}`);
  channel.bind('notification', callback);

  return () => {
    channel.unbind('notification', callback);
    pusher.unsubscribe(`private-user.${userId}`);
  };
};
```

---

## 11. Security Considerations

### 11.1 Token Security

| ✅ DO | ❌ DON'T |
|-------|---------|
| Store access tokens in memory/sessionStorage | Store tokens in localStorage |
| Use short-lived access tokens | Use long-lived tokens for web |
| Refresh tokens server-side when possible | Expose refresh tokens in JS |
| Clear tokens on logout | Leave tokens after logout |

### 11.2 CSRF Protection

- **Bearer Token Auth**: CSRF protection automatically skipped (stateless)
- **Session Auth**: Must include CSRF token in all mutating requests

```typescript
// Only needed for session-based auth (legacy)
const csrfResponse = await api.get('/api/auth/csrf-token');
const csrfToken = csrfResponse.data.csrf_token;

await api.post('/some-endpoint', data, {
  headers: { 'X-CSRF-TOKEN': csrfToken }
});
```

### 11.3 Input Validation

Always validate input on both client and server:

```typescript
// Client-side validation example
const validateListingForm = (data: ListingFormData): string[] => {
  const errors: string[] = [];

  if (!data.title || data.title.length < 3) {
    errors.push('Title must be at least 3 characters');
  }
  if (data.title.length > 200) {
    errors.push('Title must be less than 200 characters');
  }
  if (!data.description || data.description.length < 10) {
    errors.push('Description must be at least 10 characters');
  }
  if (data.time_credits < 0.5 || data.time_credits > 100) {
    errors.push('Time credits must be between 0.5 and 100');
  }

  return errors;
};
```

### 11.4 XSS Prevention

Always sanitize user-generated content before rendering:

```typescript
import DOMPurify from 'dompurify';

// Sanitize HTML content
const sanitizedHtml = DOMPurify.sanitize(userContent);

// In React, use dangerouslySetInnerHTML only with sanitized content
<div dangerouslySetInnerHTML={{ __html: sanitizedHtml }} />
```

---

## 12. Testing & Debugging

### 12.1 API Testing Tools

```bash
# Using curl
curl -X GET "http://localhost:8090/api/v2/listings" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json"

# Using httpie
http GET localhost:8090/api/v2/listings \
  Authorization:"Bearer <token>"
```

### 12.2 Debug Headers

When `DEBUG=true` is set on the server, responses include:

```
X-Debug-Query-Count: 5
X-Debug-Query-Time: 0.023
X-Debug-Memory-Peak: 4.2MB
```

### 12.3 Running API Tests

```bash
# Run all API tests
php tests/run-api-tests.php

# Run specific suite
php tests/run-api-tests.php --suite=auth
php tests/run-api-tests.php --suite=listings
```

### 12.4 Common Debugging Scenarios

**401 Unauthorized**
- Check if token is included in Authorization header
- Verify token hasn't expired (decode JWT to check exp claim)
- Try refreshing the token

**403 Forbidden**
- User doesn't have permission for this action
- Resource belongs to another user
- Tenant mismatch

**422 Validation Error**
- Check `errors` array for specific field errors
- Verify all required fields are present
- Check field value constraints

**429 Rate Limited**
- Check `Retry-After` header for wait time
- Reduce request frequency
- Implement exponential backoff

---

## 13. Migration Notes

### 13.1 V1 to V2 Migration

If migrating from V1 API:

| V1 Pattern | V2 Pattern |
|------------|------------|
| `{success: true, data: {...}}` | `{data: {...}, meta: {...}}` |
| `{success: false, error: "..."}` | `{errors: [{code, message}]}` |
| Offset pagination | Cursor pagination |
| Session auth only | Bearer token preferred |

### 13.2 Breaking Changes

None currently - V2 API is additive to V1.

### 13.3 Deprecation Timeline

| Feature | Deprecated | Removed |
|---------|------------|---------|
| V1 Social API | Q2 2026 | Q4 2026 |
| Session-only auth | Q3 2026 | Q1 2027 |

---

## Appendix A: Complete Endpoint List

<details>
<summary>Click to expand full endpoint list</summary>

### Authentication
```
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/refresh-token
GET    /api/auth/validate-token
POST   /api/auth/revoke
POST   /api/auth/revoke-all
GET    /api/auth/csrf-token
POST   /api/v2/auth/register
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
POST   /api/auth/verify-email
POST   /api/auth/resend-verification
```

### Bootstrap & Public
```
GET    /api/v2/tenant/bootstrap
GET    /api/v2/categories
GET    /api/v2/search/suggestions
GET    /api/docs
GET    /api/docs/openapi.json
```

### Listings
```
GET    /api/v2/listings
GET    /api/v2/listings/nearby
GET    /api/v2/listings/{id}
POST   /api/v2/listings
PUT    /api/v2/listings/{id}
DELETE /api/v2/listings/{id}
POST   /api/v2/listings/{id}/image
```

### Events
```
GET    /api/v2/events
GET    /api/v2/events/{id}
POST   /api/v2/events
PUT    /api/v2/events/{id}
DELETE /api/v2/events/{id}
POST   /api/v2/events/{id}/rsvp
DELETE /api/v2/events/{id}/rsvp
GET    /api/v2/events/{id}/attendees
```

### Messages
```
GET    /api/v2/messages/conversations
GET    /api/v2/messages/unread-count
GET    /api/v2/messages/conversations/{id}
POST   /api/v2/messages
POST   /api/v2/messages/conversations/{id}/read
POST   /api/v2/messages/conversations/{id}/archive
POST   /api/v2/messages/voice
```

### Users
```
GET    /api/v2/users/me
GET    /api/v2/users/{id}
PUT    /api/v2/users/me
PUT    /api/v2/users/me/preferences
POST   /api/v2/users/me/avatar
PUT    /api/v2/users/me/password
```

### Wallet
```
GET    /api/v2/wallet/balance
GET    /api/v2/wallet/transactions
GET    /api/v2/wallet/transactions/{id}
POST   /api/v2/wallet/transfer
GET    /api/v2/wallet/stats
```

### Groups
```
GET    /api/v2/groups
GET    /api/v2/groups/{id}
POST   /api/v2/groups
PUT    /api/v2/groups/{id}
DELETE /api/v2/groups/{id}
POST   /api/v2/groups/{id}/join
POST   /api/v2/groups/{id}/leave
GET    /api/v2/groups/{id}/members
GET    /api/v2/groups/{id}/discussions
POST   /api/v2/groups/{id}/discussions
```

### Feed
```
GET    /api/v2/feed
GET    /api/v2/feed/{id}
POST   /api/v2/feed
PUT    /api/v2/feed/{id}
DELETE /api/v2/feed/{id}
POST   /api/v2/feed/{id}/like
DELETE /api/v2/feed/{id}/like
GET    /api/v2/feed/{id}/comments
POST   /api/v2/feed/{id}/comments
```

### Notifications
```
GET    /api/v2/notifications
GET    /api/v2/notifications/counts
GET    /api/v2/notifications/{id}
POST   /api/v2/notifications/{id}/read
POST   /api/v2/notifications/read-all
DELETE /api/v2/notifications/{id}
```

### Gamification
```
GET    /api/v2/gamification/profile
GET    /api/v2/gamification/badges
GET    /api/v2/gamification/badges/{id}
GET    /api/v2/gamification/leaderboard
GET    /api/v2/gamification/challenges
GET    /api/v2/gamification/daily-reward
POST   /api/v2/gamification/daily-reward
GET    /api/v2/gamification/shop
POST   /api/v2/gamification/shop/{id}/purchase
```

### Volunteering
```
GET    /api/v2/volunteering/opportunities
GET    /api/v2/volunteering/opportunities/{id}
POST   /api/v2/volunteering/opportunities
PUT    /api/v2/volunteering/opportunities/{id}
DELETE /api/v2/volunteering/opportunities/{id}
POST   /api/v2/volunteering/opportunities/{id}/apply
GET    /api/v2/volunteering/applications
POST   /api/v2/volunteering/hours
GET    /api/v2/volunteering/hours
```

### Reviews
```
GET    /api/v2/reviews/users/{id}
POST   /api/v2/reviews
GET    /api/v2/reviews/stats/{userId}
```

### Goals
```
GET    /api/v2/goals
GET    /api/v2/goals/{id}
POST   /api/v2/goals
PUT    /api/v2/goals/{id}
DELETE /api/v2/goals/{id}
POST   /api/v2/goals/{id}/progress
```

### Connections
```
GET    /api/v2/connections
POST   /api/v2/connections/request
POST   /api/v2/connections/{id}/accept
DELETE /api/v2/connections/{id}
```

### Search
```
GET    /api/v2/search
GET    /api/v2/search/suggestions
```

### Push Notifications
```
GET    /api/push/vapid-key
POST   /api/push/subscribe
POST   /api/push/unsubscribe
GET    /api/push/status
POST   /api/push/register-device
POST   /api/push/unregister-device
```

</details>

---

## Appendix B: Quick Reference Card

```
┌────────────────────────────────────────────────────────────────┐
│                    API QUICK REFERENCE                         │
├────────────────────────────────────────────────────────────────┤
│ Base URL:     /api/v2                                          │
│ Auth Header:  Authorization: Bearer <token>                    │
│ Content-Type: application/json                                 │
├────────────────────────────────────────────────────────────────┤
│ LOGIN:        POST /api/auth/login {email, password}           │
│ REFRESH:      POST /api/auth/refresh-token {refresh_token}     │
│ LOGOUT:       POST /api/auth/logout                            │
├────────────────────────────────────────────────────────────────┤
│ SUCCESS:      {data: {...}, meta: {...}}                       │
│ COLLECTION:   {data: [...], meta: {cursor, has_more}}          │
│ ERROR:        {errors: [{code, message, field?}]}              │
├────────────────────────────────────────────────────────────────┤
│ PAGINATION:   ?cursor=<cursor>&limit=20                        │
│ SEARCH:       ?q=<query>&type=<type>                          │
│ FILTERS:      ?category_id=1&status=active                     │
├────────────────────────────────────────────────────────────────┤
│ 200 OK        │ 201 Created │ 204 No Content                   │
│ 400 Bad Req   │ 401 Unauth  │ 403 Forbidden                    │
│ 404 Not Found │ 422 Invalid │ 429 Rate Limit                   │
└────────────────────────────────────────────────────────────────┘
```

---

*Document generated by Claude Code - February 2026*
