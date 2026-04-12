# Federation

Project NEXUS's federation layer lets tenants exchange members, listings,
messages, transactions, and reviews with other timebanks — both NEXUS-to-NEXUS
and across protocol-native partners (Komunitin, TimeOverflow, Credit Commons).
This document summarises the moving parts; see the code and tests referenced
in each section for authoritative details.

## Architecture

```
              ┌──────────────────────────────┐
              │   React frontend / admin UI  │
              └──────────────┬───────────────┘
                             │   HTTPS + Sanctum
              ┌──────────────▼───────────────┐
              │   Laravel API (this app)     │
              │  ─────────────────────────   │
              │  Inbound:                    │
              │   ├ FederationKomunitinController  (JSON:API)
              │   ├ FederationCreditCommonsController
              │   ├ FederationController            (Nexus v1)
              │   └ FederationV2Controller          (React UI)
              │                               │
              │  Outbound:                    │
              │   └ FederationExternalApiClient
              │        → NexusAdapter | KomunitinAdapter |
              │          TimeOverflowAdapter | CreditCommonsAdapter
              └──────────────┬───────────────┘
                             │
              ┌──────────────▼───────────────┐
              │   External federation peers  │
              └──────────────────────────────┘
```

### Three-table gating

Tenant-level federation access is controlled by three tables (not the
`tenants.features` JSON blob):

| Table | Role |
|-------|------|
| `federation_system_control` | Global kill switch — is federation enabled platform-wide |
| `federation_tenant_whitelist` | Per-tenant opt-in (admin-approved) |
| `federation_tenant_features` | Which federation features are enabled per tenant (profiles, messaging, transactions, listings, events, groups) |

The admin toggle in the React admin panel writes to all three tables atomically.
See `FederationFeatureService`.

### Protocol adapters

Outbound calls go through `App\Services\FederationExternalApiClient`, which
resolves a protocol adapter from `federation_external_partners.protocol_type`
and delegates endpoint mapping, payload transformation, and response unwrapping:

| Protocol | Adapter | Transport |
|----------|---------|-----------|
| `nexus`          | `NexusAdapter`         | JSON, Bearer API key |
| `komunitin`      | `KomunitinAdapter`     | JSON:API (`application/vnd.api+json`) |
| `timeoverflow`   | `TimeOverflowAdapter`  | JSON |
| `credit_commons` | `CreditCommonsAdapter` | JSON |

Adapters are cached per partner ID (`FederationExternalApiClient::resolveAdapter()`).
Clear with `FederationExternalApiClient::clearAdapterCache()` in tests.

## Reputation follows users globally

After the reputation unification work, federated ratings and reviews are
written to shared rating tables (`exchange_ratings.is_federated = 1`) with
`receiver_tenant_id` scoping. When a member views a profile from another
tenant, `MemberRankingService` aggregates across tenants so reputation
(badges, rating average, XP) travels with the user. Tenant scoping on the
`WHERE receiver_tenant_id = ?` clause keeps isolation intact — see
`tests/Laravel/Feature/FederationTenantIsolationTest.php`.

## Partner onboarding

1. **Admin creates an external partner** in the React admin UI
   (`/admin/federation/external-partners`). This writes to
   `federation_external_partners` with `status = pending`.
2. **API key generation** — for outbound NEXUS → partner calls, the admin
   pastes the partner's API key; NEXUS encrypts it via Laravel `Crypt`
   before persisting. For inbound, NEXUS generates a key with
   `FederationApiMiddleware::generateSigningSecret()` and hashes it with
   SHA-256 in `federation_api_keys.key_hash`.
3. **Handshake** — `POST /api/v1/federation/webhooks/test` verifies the
   partner is reachable and the auth round-trip works. On success the admin
   flips `status = active`.
4. **Sync** — `FederationExternalPartnerService::pullMembers()` and
   `pullListings()` kick off the initial backfill.

## Event-driven sync

Domain events push changes to federated partners in near-real-time:

| Event | Listener | What it pushes |
|-------|----------|----------------|
| `Events\ExchangeRated`         | `PushFederatedReviewToPartners`       | POST rating JSON to each active partner's `/reviews` endpoint |
| `Events\TransactionCompleted`  | `PushFederatedTransactionToPartners`  | JSON:API transfer POST to partners where `allow_transactions = 1` |
| `Events\MessageSent`           | `PushFederatedMessageToPartners`      | Message envelope with `sender_platform_id` + `recipient_federation_id` |
| `Events\ListingPublished`      | `SyncListingToFederationPartners`     | Upsert on partners where `allow_listing_search = 1` |
| `Events\UserVerified`          | `BroadcastVerifiedUserToPartners`     | Member metadata (no PII beyond username/display_name) |

Listeners are queued (`ShouldQueue`) so a slow partner doesn't block the
request cycle. Retries use Laravel's default backoff. Circuit breaker state
(see below) short-circuits delivery to a struggling partner.

## Security model

### Authentication (inbound — partners calling NEXUS)

Implemented in `App\Core\FederationApiMiddleware` and wrapped for Laravel
routes by `App\Http\Middleware\FederationApiAuth`.

| Method | Headers | Notes |
|--------|---------|-------|
| **API key**   | `Authorization: Bearer <key>` or `X-API-Key: <key>` | SHA-256 hashed, stored in `federation_api_keys.key_hash`. Per-key rate limits and permissions |
| **HMAC-SHA256** | `X-Federation-Platform-ID`, `X-Federation-Timestamp`, `X-Federation-Nonce`, `X-Federation-Signature` | String to sign: `METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY`. 5-minute timestamp window. Nonce is **required** and cached (`federation_nonce:*`) to block replays |
| **JWT**       | `Authorization: Bearer <jwt>` | Validated by `FederationJwtService::validateTokenStatic()`. `sub` = partner_id; verified against `federation_api_keys` on every request |

### Authentication (outbound — NEXUS calling partners)

`FederationExternalApiClient::buildAuthHeaders()` picks the method from
`federation_external_partners.auth_method`:

- **api_key** → `Authorization: Bearer <decrypted api_key>`
- **hmac** → generates same headers as inbound (via `generateHmacSignature()`)
- **oauth2** → client-credentials grant with 60-second token cache (`federation_oauth2_token:{partnerId}`)

### Replay prevention

HMAC requests carry a cryptographic nonce in `X-Federation-Nonce`. The
middleware uses `Cache::add()` (atomic Redis `SET NX`) on
`federation_nonce:{nonce}` with a 300-second TTL — if the nonce has been seen
within the window, `add()` returns false and the request is rejected with
`SIGNATURE_INVALID`. Outbound calls generate a fresh 16-byte hex nonce per
request.

### SSRF protection

`federation_external_partners.base_url` is admin-controlled, so partner URLs
are not user-supplied. Defence-in-depth is still advisable — see the
TODO in `FederationExternalApiClient::request()` and the admin UI's URL
validator.

### Tenant isolation

Every federated query includes `WHERE tenant_id = ?` (or
`receiver_tenant_id` for federated reviews). The
`tests/Laravel/Feature/FederationTenantIsolationTest.php` suite asserts:

- Partnership rows of tenant A are invisible to tenant B
- Federated reviews scoped by `receiver_tenant_id` never leak
- Rate-limit counters are per-API-key (not global)
- External partner rows are tenant-scoped

### Audit logs

Every outbound call is logged to `federation_external_partner_logs` with
request/response bodies (sensitive fields like `api_key`, `signing_secret`,
`password`, `access_token` are redacted by
`FederationExternalApiClient::redactSensitiveFields()`). Inbound calls are
logged to `federation_api_logs` via `FederationApiMiddleware::logApiAccess()`.

## Troubleshooting

### Circuit breaker tripped

When an outbound partner produces 5 consecutive failures, the circuit breaker
opens for 5 minutes (`federation_cb_open:{partnerId}` cache key + partner
`status = failed`). During the cooldown, new calls short-circuit with
`Circuit breaker open — partner temporarily unavailable`. Recovery is
automatic: the first successful call after the cache key expires resets
`error_count` to 0 and flips `status` back to `active`.

Manual reset:

```bash
php artisan cache:forget "federation_cb_open:<partnerId>"
php artisan cache:forget "federation_cb_failures:<partnerId>"
```

### `Circuit breaker open` on every call

The Redis cache may have stale keys. Flush them:

```bash
docker exec nexus-php-redis redis-cli --scan --pattern 'federation_cb_*' | \
  xargs -r docker exec -i nexus-php-redis redis-cli del
```

### `401 Invalid signature` on HMAC inbound

Common causes:

1. Clock skew > 5 min between client and server — check NTP.
2. Client omitted `X-Federation-Nonce` — it is **required** for HMAC.
3. Signed path doesn't match `$_SERVER['REQUEST_URI']` (path + query string, not full URL).
4. Reusing a nonce within 5 minutes — treated as replay.

### `HMAC signing required for this API key`

The partner's API key has `signing_enabled = 1` but the request used only the
Bearer token. Either add the HMAC headers or disable signing for the key in
the admin UI.

### `API-Version` header / content-type mismatch

Komunitin partners must receive `application/vnd.api+json` on both `Accept`
and `Content-Type`. `FederationExternalApiClient` sets this automatically
when `protocol_type = 'komunitin'`. Check the partner row's `protocol_type`
column if the peer rejects content.

### Logs

| Location | What |
|----------|------|
| `storage/logs/laravel.log` | App-level federation logs (`[FederationExternalApiClient]`, `[FederationKomunitin]`) |
| `federation_external_partner_logs` table | Outbound HTTP calls (status, timing, redacted bodies) |
| `federation_api_logs` table | Inbound HTTP calls (auth method, signature validity, IP) |
| `federation_audit_log` table | Admin actions (partner create/suspend, key rotation, opt-in/out) |

## Protocol endpoint matrix

All inbound protocol endpoints are tenant-scoped and gated by
`FederationApiMiddleware` (accepts API key, HMAC-SHA256, or JWT). Write
operations that mutate currency/account metadata require `admin` or `*`
scope on the authenticating API key.

### Komunitin (JSON:API, `/api/v2/federation/komunitin/...`)

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET    | `/currencies`                  | federation.api | List currencies |
| POST   | `/currencies`                  | federation.api | Create currency (no-op, NEXUS uses fixed 'hours') |
| GET    | `/{code}/currency`             | federation.api | Single currency detail |
| PATCH  | `/{code}/currency`             | federation.api | Update currency metadata |
| DELETE | `/{code}/currency`             | admin          | Soft-delete currency (marks inactive) |
| GET    | `/{code}/currency/settings`    | federation.api | Currency settings |
| PATCH  | `/{code}/currency/settings`    | federation.api | Update settings |
| GET    | `/{code}/accounts`             | federation.api | List accounts (paginated) |
| POST   | `/{code}/accounts`             | federation.api | Create external account shell |
| GET    | `/{code}/accounts/{id}`        | federation.api | Single account |
| PATCH  | `/{code}/accounts/{id}`        | federation.api | Update account |
| DELETE | `/{code}/accounts/{id}`        | admin          | Deactivate account (status=inactive) |
| GET    | `/{code}/transfers`            | federation.api | List transfers |
| GET    | `/{code}/transfers/{id}`       | federation.api | Single transfer |
| POST   | `/{code}/transfers`            | federation.api | Create transfer (atomic) |
| PATCH  | `/{code}/transfers/{id}`       | federation.api | Update transfer state |
| DELETE | `/{code}/transfers/{id}`       | federation.api | Delete pending transfer |

### Credit Commons (`/api/v2/federation/cc/...`)

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET    | `/about`                          | federation.api | Node metadata + stats |
| GET    | `/accounts`                       | federation.api | Account autocomplete |
| GET    | `/account` \| `/account/{acc_id}` | federation.api | Trading stats |
| GET    | `/account/history[/{acc_id}]`     | federation.api | Balance history |
| GET    | `/transactions`                   | federation.api | List transactions |
| POST   | `/transaction`                    | federation.api | Direct transaction (immediate complete) |
| GET    | `/transaction/{uuid}`             | federation.api | Single transaction |
| PATCH  | `/transaction/{uuid}/{state}`     | federation.api | Generic state transition |
| POST   | `/transaction/relay`              | federation.api | Hashchain-verified relay |
| GET    | `/entries` \| `/entries/{uuid}`   | federation.api | Double-entry view |
| GET    | `/forms`                          | federation.api | Available workflows |
| POST   | `/transactions/propose`           | federation.api | External node proposes pending (P) |
| POST   | `/transactions/{uuid}/validate`   | federation.api | Approve (P→V) |
| POST   | `/transactions/{uuid}/commit`     | federation.api | Finalize (V→C) + hashchain |

### Nexus Native V2 inbound (`/api/v2/federation/...`)

Thin ingest layer for partners using the Nexus protocol to push entities to us.
Authenticated via `federation.api` middleware; persistence is handled by
dedicated listeners downstream (see `FederationNativeIngestController`).

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/reviews`         | Receive a review |
| POST | `/listings`        | Receive a listing |
| POST | `/events`          | Receive a community event |
| POST | `/groups`          | Receive a group |
| POST | `/connections`     | Receive a connection request |
| POST | `/volunteering`    | Receive a volunteering opportunity |
| POST | `/members/sync`    | Receive a member profile update |

### TimeOverflow

TimeOverflow has no native REST spec; inbound traffic uses the generic
`POST /api/v2/federation/external/webhooks/receive` HMAC-authenticated
receiver. Outbound calls go through `TimeOverflowAdapter` (now in
`App\Services\Protocols\`, matching the other adapter namespaces).

## See also

- [API_REFERENCE.md](API_REFERENCE.md) — full endpoint map (15 Komunitin + 11 Credit Commons + Nexus v1/v2)
- `app/Services/FederationExternalApiClient.php` — outbound client + circuit breaker
- `app/Core/FederationApiMiddleware.php` — inbound auth + replay prevention
- `app/Services/Protocols/*.php` — protocol adapters
- `tests/Laravel/Feature/FederationKomunitinEndpointsTest.php` — 15 endpoint integration tests
- `tests/Laravel/Feature/FederationTenantIsolationTest.php` — isolation regression tests
