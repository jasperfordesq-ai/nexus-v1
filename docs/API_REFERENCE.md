# API Reference (V2)

> Extracted from root CLAUDE.md. This is the authoritative reference for API endpoints.

The React frontend uses V2 API endpoints at `/api/v2/*`. All endpoints defined in `httpdocs/routes.php`.

## Core & Auth

| Endpoint | Controller |
|----------|------------|
| `/api/v2/tenant/bootstrap` | TenantBootstrapController |
| `/api/v2/tenants` | TenantBootstrapController |
| `/api/v2/platform/stats` | TenantBootstrapController |
| `/api/v2/categories` | CoreApiController |
| `/api/v2/realtime/config` | PusherAuthController |
| `/api/auth/login` | (existing auth) |
| `/api/auth/logout` | (existing auth) |

### Registration — `POST /api/v2/auth/register`

Controller: `RegistrationController`

**Request body:**
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "email": "jane@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "phone": "+1 555 123 4567",
  "tenant_id": 2,
  "terms_accepted": true,
  "bot_timer": 6000,
  "profile_type": "individual",
  "location": "optional",
  "latitude": 0.0,
  "longitude": 0.0
}
```

**Success (201):** Returns `{ "data": { "user": {...}, "token": "...", "refresh_token": "...", "requires_verification": true } }`

**Errors:**
- `400/422` — Validation errors (missing fields, password mismatch, invalid tenant)
- `409` — Email already registered
- `429` — Rate limit exceeded (3/min burst or 5/hr volume, per IP)

**Security layers:**
1. Bot protection: `bot_timer` must be ≥ 5000ms (silent, no error message exposes threshold)
2. Redis rate limit: 3/min per IP
3. File-cache rate limit: 5/hr per IP
4. Tenant validation: tenant must exist and be active
5. Terms acceptance required
6. Argon2id password hashing

**Post-registration actions:**
- Sends email verification link (URL uses `TenantContext::getFrontendUrl()`)
- Notifies tenant admins (roles: `admin`, `super_admin`, `tenant_admin`, `tenant_super_admin`)
- Always CC's `ADMIN_NOTIFICATION_EMAIL` env var (master override for all tenants)
- Awards 50 XP if tenant has `gamification` feature enabled

**Phone validation:** International E.164 format (7–15 digits, optional `+`). No locale-specific validation.

**Tenant IDs (production):**
| ID | Name | Domain / Slug |
|----|------|---------------|
| 1 | Project NEXUS (admin) | `project-nexus.ie` |
| 2 | Timebank Ireland | `hour-timebank.ie` |
| 3 | Public Sector Demo | `nexuscivic.ie` |
| 4 | Timebank Global | `timebank.global` |
| 5 | Partner Demo | slug-only |
| 6 | Crewkerne Timebank | slug-only |

## WebAuthn / Passkeys

Controller: `WebAuthnApiController`

Endpoints for WebAuthn/FIDO2 passkey registration, authentication, and credential management. Uses `lbuchs/WebAuthn` library for CBOR parsing and signature verification.

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/webauthn/register-challenge` | POST | Required | Get registration options (creation challenge) |
| `/api/webauthn/register-verify` | POST | Required | Verify attestation and store credential |
| `/api/webauthn/auth-challenge` | POST | Optional | Get authentication options (request challenge) |
| `/api/webauthn/auth-verify` | POST | No | Verify assertion and return auth tokens |
| `/api/webauthn/credentials` | GET | Required | List user's registered passkeys |
| `/api/webauthn/status` | GET | Optional | Check if user has registered passkeys |
| `/api/webauthn/remove` | POST | Required | Remove a specific passkey |
| `/api/webauthn/remove-all` | POST | Required | Remove all passkeys for the user |

### Registration Flow

1. **Get challenge:** `POST /api/webauthn/register-challenge`
   - Returns: `{ challenge, challenge_id, rp, user, pubKeyCredParams, authenticatorSelection, timeout, attestation, excludeCredentials }`
   - Rate limited: 10 requests/minute

2. **Verify & store:** `POST /api/webauthn/register-verify`
   - Body: `{ challenge_id, id, rawId, type, response: { clientDataJSON, attestationObject }, authenticatorAttachment, device_name }`
   - Returns: `{ success: true, message: "Passkey registered successfully" }`

### Authentication Flow

1. **Get challenge:** `POST /api/webauthn/auth-challenge`
   - Body: `{ email? }` — omit email for discoverable credential (passkey autofill) flow
   - Returns: `{ challenge, challenge_id, rpId, timeout, userVerification, allowCredentials? }`
   - Rate limited: 10 requests/minute

2. **Verify & login:** `POST /api/webauthn/auth-verify`
   - Body: `{ challenge_id, id, rawId, type, response: { clientDataJSON, authenticatorData, signature } }`
   - Returns: `{ success, user: { id, first_name, last_name, email }, access_token, refresh_token, expires_in }`

### Credential Management

- **List:** `GET /api/webauthn/credentials` → `{ credentials: [{ credential_id, device_name, authenticator_type, created_at, last_used_at }], count }`
- **Status:** `GET /api/webauthn/status` → `{ registered: boolean, count: number }`
- **Remove one:** `POST /api/webauthn/remove` — Body: `{ credential_id }` — CSRF required
- **Remove all:** `POST /api/webauthn/remove-all` — CSRF required

### Challenge Storage

Challenges stored server-side via `WebAuthnChallengeStore` (Redis primary, file fallback):
- 120-second TTL
- Single-use (consumed after verification)
- Tenant-scoped
- Cryptographically random 64-char hex IDs

### Frontend Integration

- React: `src/lib/webauthn.ts` — wraps `@simplewebauthn/browser`
- Settings: `src/components/security/BiometricSettings.tsx` — passkey management UI
- Login: `src/pages/auth/LoginPage.tsx` — passkey button + conditional mediation (autofill)
- Email input uses `autocomplete="username webauthn"` for passkey autofill

### RP ID Configuration

The Relying Party ID defaults to the registrable domain extracted from the request origin. For multi-level TLDs (`.co.uk`, `.com.au`), set the `WEBAUTHN_RP_ID` environment variable.

## Users & Profiles

| Endpoint | Controller |
|----------|------------|
| `/api/v2/users/me` | UsersApiController |
| `/api/v2/users/me/preferences` | UsersApiController |
| `/api/v2/users/me/theme` | UsersApiController |
| `/api/v2/users/me/avatar` | UsersApiController |
| `/api/v2/users/me/password` | UsersApiController |
| `/api/v2/users/me/notifications` | UsersApiController |
| `/api/v2/users/{id}` | UsersApiController |
| `/api/v2/users/{id}/listings` | UsersApiController |
| `/api/v2/connections` | ConnectionsApiController |

## Content & Social

| Endpoint | Controller |
|----------|------------|
| `/api/v2/listings` | ListingsApiController |
| `/api/v2/messages` | MessagesApiController |
| `/api/v2/events` | EventsApiController |
| `/api/v2/groups` | GroupsApiController |
| `/api/v2/feed` | SocialApiController |
| `/api/v2/blog` | BlogPublicController |
| `/api/v2/resources` | ResourcePublicController |
| `/api/v2/comments` | CommentsController |
| `/api/v2/polls` | PollsApiController |
| `/api/v2/search` | SearchApiController |
| `/api/v2/notifications` | NotificationsApiController |

## Wallet & Exchanges

| Endpoint | Controller |
|----------|------------|
| `/api/v2/wallet/balance` | WalletApiController |
| `/api/v2/wallet/transactions` | WalletApiController |
| `/api/v2/wallet/transfer` | WalletApiController |
| `/api/v2/exchanges` | ExchangesApiController |
| `/api/v2/reviews` | ReviewsApiController |

## Gamification & Goals

| Endpoint | Controller |
|----------|------------|
| `/api/v2/gamification/profile` | GamificationV2ApiController |
| `/api/v2/gamification/badges` | GamificationV2ApiController |
| `/api/v2/gamification/leaderboard` | GamificationV2ApiController |
| `/api/v2/gamification/challenges` | GamificationV2ApiController |
| `/api/v2/goals` | GoalsApiController |
| `/api/v2/volunteering` | VolunteerApiController |

## Federation

### User-facing federation endpoints (`/api/v2/federation/*`)

| Endpoint | Controller |
|----------|------------|
| `/api/v2/federation/*` | `FederationV2Controller` (status, opt-in, partners, activity, members, listings, events, connections, settings) |

### Inbound protocol endpoints

All inbound protocol endpoints are authenticated by `FederationApiAuth`
middleware (API key, HMAC-SHA256, or JWT — see `app/Core/FederationApiMiddleware.php`)
and rate-limited at **200 req/min** per IP by the route group. Per-key hourly
quotas are enforced via `federation_api_keys.rate_limit` (default 1000/hour).

#### Komunitin (JSON:API) — 15 endpoints

Serves NEXUS data in the Komunitin accounting protocol format
(`application/vnd.api+json`). Spec: <https://github.com/community-exchange-network/komunitin>.

Controller: `FederationKomunitinController`. All responses are JSON:API envelopes
`{ data: { type, id, attributes, relationships, links }, included?, links?, meta? }`
and all errors use `{ errors: [{ status, code, title, detail }] }`.

| Method | Path | Purpose |
|--------|------|---------|
| GET    | `/api/v2/federation/komunitin/currencies`                 | List tenant currencies (type=`currencies`) |
| POST   | `/api/v2/federation/komunitin/currencies`                 | Create currency (NEXUS no-ops, returns 201) |
| GET    | `/api/v2/federation/komunitin/{code}/currency`            | Single currency |
| PATCH  | `/api/v2/federation/komunitin/{code}/currency`            | Update currency metadata (name, namePlural). Code is immutable |
| GET    | `/api/v2/federation/komunitin/{code}/currency/settings`   | Currency settings (credit limits, payment defaults) |
| PATCH  | `/api/v2/federation/komunitin/{code}/currency/settings`   | Update currency settings |
| GET    | `/api/v2/federation/komunitin/{code}/accounts`            | Cursor-paginated account list. Filters: `filter[code]`, `filter[tag]` |
| POST   | `/api/v2/federation/komunitin/{code}/accounts`            | Returns existing federated account or 403 (`Insufficient Scope`) |
| GET    | `/api/v2/federation/komunitin/{code}/accounts/{id}`       | Single account (type=`accounts`) |
| PATCH  | `/api/v2/federation/komunitin/{code}/accounts/{id}`       | Update attributes. `balance` is read-only (managed by transfers) |
| GET    | `/api/v2/federation/komunitin/{code}/transfers`           | Cursor-paginated transfers. Filters: `filter[account]`, `filter[state]`, `filter[after]`, `filter[before]` |
| GET    | `/api/v2/federation/komunitin/{code}/transfers/{id}`      | Single transfer (type=`transfers`) |
| POST   | `/api/v2/federation/komunitin/{code}/transfers`           | Create transfer. Requires `data.relationships.payer`, `payee`, `data.attributes.amount` (minor units, ≤99999999). Uses atomic `UPDATE … WHERE balance >= amount` to prevent overdraw. Returns 403 `Insufficient balance` otherwise |
| PATCH  | `/api/v2/federation/komunitin/{code}/transfers/{id}`      | Update state — `committed` / `pending` / `rejected` |
| DELETE | `/api/v2/federation/komunitin/{code}/transfers/{id}`      | Delete (only `pending` transfers; committed returns 400) |

**Pagination:** `page[size]` (max 100, default 25) and `page[after]` (cursor = offset).
**Sort:** `sort=-created` (default), `created`, `updated`, `amount`; prefix `-` for descending.
**Auth:** Bearer API key via `Authorization: Bearer <key>` **or** HMAC via
`X-Federation-Platform-ID`, `X-Federation-Timestamp`, `X-Federation-Nonce`,
`X-Federation-Signature` (SHA-256 of `METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY`).
Replay protection: nonce is cached for the 5-minute timestamp window; duplicate
nonce within that window returns 401.

#### Credit Commons — 11 endpoints

Controller: `FederationCreditCommonsController`. See `routes/api.php:2133` for the
full route map (`/v2/federation/cc/*`).

### Outbound federation

Outbound calls to external partners are dispatched by
`FederationExternalApiClient` (retries, circuit breaker, audit logging).
See [FEDERATION.md](FEDERATION.md) for full architecture.

## Admin API

| Route Prefix | Purpose | Stack |
|--------------|---------|-------|
| `/admin/*` | React admin panel (primary) | React 18 + HeroUI + Tailwind CSS 4 |
| `/admin-legacy/*` | PHP admin panel | PHP controllers + `views/admin/` + `views/modern/admin/` |
| `/api/v2/admin/*` | Admin API endpoints (used by React admin) | PHP API controllers |
| `/super-admin/*` | Super admin PHP views | PHP controllers + `views/super-admin/` |

See `httpdocs/routes.php` for full V2 route definitions (50+ endpoints).
