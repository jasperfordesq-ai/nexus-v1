# Authentication & 2FA API Contract

This document specifies the exact request/response contracts for authentication endpoints used by the React frontend.

## Backend Source Files

| File | Purpose |
|------|---------|
| [src/Controllers/Api/AuthController.php](../src/Controllers/Api/AuthController.php) | Login, logout, token refresh |
| [src/Controllers/Api/TotpApiController.php](../src/Controllers/Api/TotpApiController.php) | 2FA verification |
| [src/Services/TwoFactorChallengeManager.php](../src/Services/TwoFactorChallengeManager.php) | Stateless 2FA challenge tokens |
| [src/Services/TokenService.php](../src/Services/TokenService.php) | JWT token generation/validation |
| [src/Core/ApiErrorCodes.php](../src/Core/ApiErrorCodes.php) | Standardized error codes |

---

## 1. POST /api/auth/login

**Purpose**: Authenticate user with email/password. Returns tokens OR 2FA challenge.

### Request

```typescript
interface LoginRequest {
  email: string;
  password: string;
}
```

**Headers**:
- `Content-Type: application/json`
- `X-Tenant-ID: <tenant_id>` (required in CORS mode, optional with domain-based resolution)

### Response A: Success (No 2FA)

**HTTP 200 OK**

```typescript
interface LoginSuccessResponse {
  success: true;
  user: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    avatar_url: string | null;
    tenant_id: number;
  };
  access_token: string;      // JWT, store in memory/localStorage
  refresh_token: string;     // JWT, store securely
  token_type: "Bearer";
  expires_in: number;        // Seconds (7200 web, 31536000 mobile)
  refresh_expires_in: number; // Seconds (63072000 web, 157680000 mobile)
  is_mobile: boolean;        // Platform detection result
  token: string;             // Legacy alias for access_token
  config?: {
    modules?: Record<string, boolean>;
  };
}
```

### Response B: 2FA Required

**HTTP 200 OK** (not 401, this is expected flow)

```typescript
interface TwoFactorRequiredResponse {
  success: false;
  requires_2fa: true;
  two_factor_token: string;  // 128-char hex string, store in MEMORY only
  methods: ["totp", "backup_code"];
  code: "AUTH_2FA_REQUIRED";
  message: "Two-factor authentication required";
  user: {
    id: number;
    first_name: string;
    email_masked: string;    // e.g., "j*****@example.com"
  };
}
```

### Response C: Error

**HTTP 400/401/429**

```typescript
interface LoginErrorResponse {
  success: false;
  error: string;             // Human-readable message
  code: string;              // Machine-readable code (see below)
  retry_after?: number;      // Seconds until retry allowed (rate limit)
}
```

**Error Codes**:
| Code | HTTP | Meaning |
|------|------|---------|
| `VALIDATION_REQUIRED_FIELD` | 400 | Email or password missing |
| `AUTH_INVALID_CREDENTIALS` | 401 | Wrong email/password |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many attempts |

---

## 2. POST /api/totp/verify

**Purpose**: Complete 2FA verification and receive tokens.

### Request

```typescript
interface TwoFactorVerifyRequest {
  two_factor_token: string;  // From login response
  code: string;              // 6-digit TOTP or backup code
  use_backup_code?: boolean; // Default: false
  trust_device?: boolean;    // Default: false (skip 2FA for 30 days)
}
```

**Headers**:
- `Content-Type: application/json`
- `X-Tenant-ID: <tenant_id>` (required in CORS mode)

### Response A: Success

**HTTP 200 OK**

```typescript
interface TwoFactorVerifyResponse {
  success: true;
  user: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    avatar_url: string | null;
    tenant_id: number;
  };
  access_token: string;
  refresh_token: string;
  token_type: "Bearer";
  expires_in: number;
  refresh_expires_in: number;
  is_mobile: boolean;
  token: string;             // Legacy alias
  codes_remaining?: number;  // Only if backup code was used
}
```

### Response B: Error

**HTTP 400/401**

```typescript
interface TwoFactorErrorResponse {
  success: false;
  error: string;
  code: string;
  redirect?: string;         // "/login" if must restart
  field?: string;            // "code" for validation errors
}
```

**Error Codes**:
| Code | HTTP | Meaning | Action |
|------|------|---------|--------|
| `AUTH_2FA_TOKEN_EXPIRED` | 401 | Challenge token expired (>5min) | Restart login |
| `AUTH_2FA_MAX_ATTEMPTS` | 401 | 5+ failed attempts | Restart login |
| `AUTH_2FA_INVALID` | 401 | Wrong code | Retry (4 more attempts) |
| `VALIDATION_REQUIRED_FIELD` | 400 | Code missing | Show validation error |

---

## 3. POST /api/auth/refresh-token

**Purpose**: Get new access token using refresh token.

### Request

```typescript
interface RefreshTokenRequest {
  refresh_token: string;
}
```

**Alternative**: Send refresh token in `Authorization: Bearer <token>` header.

### Response A: Success

**HTTP 200 OK**

```typescript
interface RefreshTokenResponse {
  success: true;
  access_token: string;
  token_type: "Bearer";
  expires_in: number;
  is_mobile: boolean;
  token: string;              // Legacy alias
  refresh_token?: string;     // Only if old refresh token was near expiry
  refresh_expires_in?: number;
}
```

### Response B: Error

**HTTP 400/401**

```typescript
interface RefreshTokenErrorResponse {
  success: false;
  error: string;
  code: string;
}
```

**Error Codes**:
| Code | HTTP | Meaning |
|------|------|---------|
| `AUTH_TOKEN_MISSING` | 400 | No refresh token provided |
| `AUTH_TOKEN_EXPIRED` | 401 | Refresh token expired |
| `AUTH_TOKEN_INVALID` | 401 | Malformed or revoked token |
| `RESOURCE_NOT_FOUND` | 401 | User deleted |
| `AUTH_ACCOUNT_SUSPENDED` | 403 | Account suspended |

---

## 4. POST /api/auth/logout

**Purpose**: Clear session and optionally revoke refresh token.

### Request

```typescript
interface LogoutRequest {
  refresh_token?: string;  // Optional: revoke this token
}
```

### Response

**HTTP 200 OK**

```typescript
interface LogoutResponse {
  success: true;
  message: "Logged out successfully";
  refresh_token_revoked?: boolean;  // If token was provided
}
```

---

## 5. GET /api/totp/status

**Purpose**: Check if 2FA is enabled for current user (requires authentication).

### Request

**Headers**:
- `Authorization: Bearer <access_token>`
- `X-Tenant-ID: <tenant_id>` (CORS mode)

### Response

**HTTP 200 OK**

```typescript
interface TotpStatusResponse {
  success: true;
  enabled: boolean;
  setup_required: boolean;
  backup_codes_remaining: number;
  trusted_devices: number;
}
```

---

## Security Considerations

### Two-Factor Token Storage

The `two_factor_token` is a **short-lived challenge token** (5 minutes TTL, 5 max attempts).

**DO**:
- Store in React component state (memory)
- Clear on component unmount
- Clear if user navigates away

**DO NOT**:
- Store in localStorage (persists after close)
- Store in sessionStorage (survives refresh, extends window)
- Include in URLs

### Token Expiry Times

| Token Type | Web Expiry | Mobile Expiry |
|------------|------------|---------------|
| Access Token | 2 hours | 1 year |
| Refresh Token | 2 years | 5 years |
| 2FA Challenge | 5 minutes | 5 minutes |

### Rate Limiting

Login attempts are rate limited:
- **Per email**: 5 attempts per 15 minutes
- **Per IP**: 20 attempts per 15 minutes

Response includes `Retry-After` header with seconds until retry allowed.

---

## React Implementation Notes

### Auth State Machine

```typescript
type AuthStatus =
  | "idle"           // Not logged in, no action
  | "logging_in"     // Login request in progress
  | "requires_2fa"   // Login succeeded, awaiting 2FA
  | "authenticated"  // Fully authenticated
  | "error";         // Auth error occurred
```

### Session Expiration Handling

Listen for the `SESSION_EXPIRED_EVENT` from the API client:

```typescript
window.addEventListener(SESSION_EXPIRED_EVENT, () => {
  // Clear state and redirect to login
  signOut();
});
```

This event fires when:
- Access token refresh fails (401)
- Refresh token is expired or revoked
- User account is suspended/deleted

### TypeScript Types

All types are defined in `react-frontend/src/api/types.ts`:
- `LoginRequest`
- `LoginSuccessResponse`
- `TwoFactorRequiredResponse`
- `LoginResponse` (union type)
- `TwoFactorVerifyRequest`
- `TwoFactorVerifyResponse`
- `RefreshTokenRequest`
- `RefreshTokenResponse`
