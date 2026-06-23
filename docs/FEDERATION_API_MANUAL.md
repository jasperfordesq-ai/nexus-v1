# Federation API — Instruction Manual

> A plain-English guide for anyone — technical or not — who wants to connect their timebank to another timebank using the Project NEXUS Federation API.

---

## Part 1 — The plain-English version

### What is federation?

Federation lets your timebank talk to another timebank.

Think of two separate timebanking websites — yours, and another one somewhere else in the world. On their own, they're like two islands: people on each island can trade time credits with their neighbours, but they can't trade with someone on the other island.

**Federation builds a bridge between the islands.**

Once the bridge is open:

- A member on your timebank can send time credits to a member on the other timebank
- They can message each other
- They can see each other's listings (things people are offering or looking for)
- They can leave reviews for each other
- Reputation (stars, badges, trust scores) follows a person from one timebank to the other

Both sides have to agree before the bridge opens. Nobody can force a connection. And either side can close the bridge at any time.

---

### What is an API key?

An **API key** is a very long password. Imagine a key to a front door — except instead of opening a physical door, it lets one computer ask another computer for information.

When you want to federate with another timebank:

1. You give them an API key that belongs to your platform. They keep it secret.
2. They give you one that belongs to theirs. You keep it secret.
3. From now on, whenever their platform wants to ask yours a question — "show me your public listings" — it sends the key along with the question, so yours knows the request is genuine.

Two important properties:

- **API keys are revocable.** If a key is ever leaked or stolen, an admin clicks "revoke" and that key is dead immediately. A new one gets issued.
- **Keys can be limited.** A key can be restricted to only certain actions. For example, a key might be allowed to *read* listings but not *create* transactions.

---

### What is a webhook?

A **webhook** is a doorbell.

API keys are great when one side wants to *ask* the other side for something. But what happens when something changes and the other side needs to know about it *right now*?

Example: a member on your platform sends a message to a member on the other platform. The other platform needs to know immediately so the message appears in the recipient's inbox. If we only had API keys, the other platform would have to keep checking every 30 seconds: "Any new messages? Any new messages? Any new messages?" That wastes time and bandwidth.

Instead, your platform "rings their doorbell" — it sends them a short signed message saying "a new message just arrived for one of your members." That's a webhook.

Webhooks are used for events like:

- A transfer was cancelled and the other side needs to refund the member
- A new message is waiting to be delivered
- A partnership was suspended or reinstated
- A listing was shared across the bridge

**Webhooks are signed.** Every webhook carries a cryptographic signature, like a wax seal. When the other side receives it, they check the seal. If it's tampered with or forged, they throw it out. This means webhooks are safe even though they're not protected by a password.

---

### When do I need what?

| Scenario | API key | Webhook |
|----------|---------|---------|
| Nexus talking to **another Nexus** | ✅ Required | ⚪ Optional — use if you want instant notifications instead of polling |
| Nexus talking to **TimeOverflow** | ✅ Required | ✅ Required — TimeOverflow expects to receive webhooks for events |
| Nexus talking to **Komunitin** | ✅ Required | ✅ Required |
| Nexus talking to **Credit Commons** | ✅ Required | ✅ Required |
| Reading another timebank's public listings | ✅ Required | ⚪ Not needed |
| Sending a time-credit transfer | ✅ Required | ⚪ Not needed to send. Useful on the receiving side for confirmations |
| Being notified when a transfer you sent was cancelled | ⚪ Not needed | ✅ Required (or else you have to poll) |
| Receiving messages from the other side | ⚪ Not needed | ✅ Required (or else you have to poll) |

**Rule of thumb:** API keys handle requests you make on purpose. Webhooks handle surprises that you need to know about without asking.

---

### How do I set up a connection? (6 steps, plain English)

1. **Find a partner.** Another timebank — a Nexus instance, a TimeOverflow community, anyone running compatible federation software.
2. **Both admins agree** to federate. Nothing starts without both sides saying yes.
3. **Exchange API keys.** You give them one. They give you one. Paste theirs into the admin panel at **Admin → Federation → External Partners**. Keep yours safe.
4. **Exchange webhook URLs** (if you want real-time events). Tell them where to send webhooks to you. They tell you where to send webhooks to them.
5. **Test the handshake.** One click in the admin panel sends a test request to the other side. If it comes back green, the bridge is working.
6. **Turn on the features** you want to share: listings, messages, transactions, reviews, etc. Each one is a separate switch. Start with the safest (listings and messages), and only enable transactions once you trust the partner.

---

### What could go wrong? (and what we do about it)

| Worry | What's in place |
|-------|-----------------|
| Someone steals our API key | Revoke it in one click. A new one can be issued in seconds. |
| A partner sends us a fake webhook | Every webhook is signed. Fakes are rejected automatically. |
| A partner sends the same webhook twice | Every webhook has a unique ID. Duplicates are ignored. |
| A partner's server is offline | Queued delivery retries with backoff. A circuit breaker stops hammering a dead partner. |
| A partner misbehaves (spams, fraud) | Per-partner rate limits. Per-feature kill switches. Full suspension in one click. |
| We lose trust in federation entirely | A platform-wide kill switch in the super-admin panel disables all federation everywhere, instantly. |

---

### Who does what?

- **Platform super-admin** controls the global federation kill switch and approves new tenants for federation.
- **Tenant admin** (the admin of an individual timebank) chooses which partners to federate with and which features to share.
- **Member** (an ordinary user) opts in to having their profile, listings, and messages be visible to federated partners. Nothing of theirs leaves the platform without them opting in.
- **Partner platform admin** does the same on their side.

---

### The one-sentence summary

> Federation is two timebanks agreeing to act as one network. API keys let them ask each other questions. Webhooks let them tap each other on the shoulder when something urgent happens. Everything is signed, revocable, and off by default until both sides say yes.

---

## Part 2 — The technical version

This section is for developers integrating with the Federation API.

### Endpoints overview

All federation endpoints are under `/api/v1/federation/`. Three inbound controllers:

| Controller | Purpose |
|------------|---------|
| `FederationController` (Nexus v1 protocol) | Native JSON with Bearer API key |
| `FederationKomunitinController` | JSON:API (`application/vnd.api+json`) |
| `FederationCreditCommonsController` | Credit Commons JSON |

Outbound calls go through `App\Services\FederationExternalApiClient`, which resolves a protocol adapter from `federation_external_partners.protocol_type` — one of `nexus`, `komunitin`, `timeoverflow`, `credit_commons`.

### Authentication (inbound — partners calling NEXUS)

Three supported methods, implemented in `App\Core\FederationApiMiddleware`:

#### 1. API key (Bearer)

```http
GET /api/v1/federation/listings HTTP/1.1
Host: api.project-nexus.ie
Authorization: Bearer fed_live_abc123...
```

Keys are SHA-256 hashed in `federation_api_keys.key_hash`. Scoped permissions (e.g. `listings:read`, `messages:write`). Per-key rate limits.

#### 2. HMAC-SHA256

```http
POST /api/v1/federation/webhooks/receive HTTP/1.1
Host: api.project-nexus.ie
X-Federation-Platform-ID: <partner platform id>
X-Federation-Timestamp: <unix seconds>
X-Federation-Nonce: <random per request>
X-Federation-Signature: <hex hmac-sha256>
```

String to sign: `METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY`.

5-minute timestamp window. Nonce cached (`federation_nonce:*`) to block replays.

#### 3. JWT (short-lived)

```http
POST /api/v1/federation/oauth/token HTTP/1.1
Content-Type: application/x-www-form-urlencoded
Authorization: Basic base64(client_id:client_secret)

grant_type=client_credentials&scope=members:read listings:read
```

Returns a 1-hour JWT. `sub` = partner_id, verified against `federation_api_keys` on every request.

### Webhooks (outbound — NEXUS notifying partners)

Triggered by domain events. Queued via `ShouldQueue` listeners. The authoritative event→listener wiring lives in `app/Providers/EventServiceProvider.php`:

| Event | Outbound listener |
|-------|-------------------|
| `ListingCreated`, `ListingUpdated` | `PushListingToFederatedPartners` |
| `TransactionCompleted` | `PushTransactionToFederatedPartner` |
| `MessageSent` | `PushMessageToFederatedPartner` |
| `ReviewCreated` | `PushReviewToFederatedPartner` |
| `ConnectionAccepted` | `PushConnectionAcceptedToFederatedPartner` |
| `CommunityEventCreated`, `CommunityEventUpdated` | `PushCommunityEventToFederatedPartners` |
| `GroupCreated`, `GroupUpdated` | `PushGroupToFederatedPartners` |
| `GroupMemberJoined` | `PushGroupMembershipToFederatedPartners` |
| `GroupDeleted`, `GroupMemberLeft` | `PushGroupRetractionToFederatedPartners` |
| `VolunteerOpportunityCreated`, `VolunteerOpportunityUpdated` | `PushVolunteerOpportunityToFederatedPartners` |
| `MemberProfileUpdated` | `PushMemberProfileUpdateToFederatedPartners` |
| `UserFederatedOptOut` | `PushFederationDataRetraction` (retracts previously shared data) |

Inbound federation events are handled by the matching `HandleFederated*` / `IngestFederated*` listeners (e.g. `FederatedReviewReceived` → `HandleFederatedReviewReceived`, `FederatedListingReceived` → `HandleFederatedListingReceived`).

Outbound webhook request:

```http
POST <partner webhook_url> HTTP/1.1
Content-Type: application/json
X-Federation-Platform-ID: <our platform id>
X-Federation-Timestamp: <unix>
X-Federation-Nonce: <uuid>
X-Federation-Signature: <hmac-sha256>

{ "event": "transaction.created", "data": { ... } }
```

Retries with exponential backoff. Circuit breaker disables delivery to a struggling partner.

### Three-table gating

Tenant-level federation access is NOT in `tenants.features` JSON. It uses:

| Table | Role |
|-------|------|
| `federation_system_control` | Global platform kill switch |
| `federation_tenant_whitelist` | Per-tenant opt-in (super-admin approved) |
| `federation_tenant_features` | Per-feature toggles (profiles, messaging, transactions, listings, events, groups) |

Admin toggles write to all three atomically via `FederationFeatureService`.

### Partner onboarding flow

1. Admin creates partner at `/admin/federation/external-partners` (`status = pending`)
2. Paste partner's API key (encrypted via Laravel `Crypt` before persisting)
3. Generate an inbound key via `FederationApiMiddleware::generateSigningSecret()`; stored as SHA-256 hash
4. `POST /api/v1/federation/webhooks/test` verifies reachability
5. On success, set `status = active`
6. Initial backfill: `FederationExternalPartnerService::pullMembers()` and `pullListings()`

### Member opt-in

Individual members opt in to federation via profile settings. Flag stored on `users.federation_opt_in`. Messages, transfers, and visibility to partners are all gated on this flag.

### Rate limits

All responses include:

- `X-RateLimit-Limit` — max requests per window (default 60/min)
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset` — unix timestamp

429 with `Retry-After` on overrun.

### Reputation

Federated ratings written to `exchange_ratings.is_federated = 1` with `receiver_tenant_id` scoping. `MemberRankingService` aggregates across tenants so reputation travels with the user.

### Testing

- `tests/Laravel/Feature/FederationTenantIsolationTest.php` — tenant scoping
- `tests/Laravel/Feature/Federation*Test.php` — per-protocol integration tests

### Related documentation

- `app/Providers/EventServiceProvider.php` - authoritative event→listener wiring (source of truth for the webhook table above).
- `routes/api.php` - registered federation routes.
- `app/Services/FederationExternalApiClient.php` - outbound federation client.
- `app/Core/FederationApiMiddleware.php` - inbound authentication (API key / HMAC / JWT) and `generateSigningSecret()`.
- `tests/Laravel/Feature/Federation*Test.php` - per-protocol integration tests.

---

Document version: 1.1. Last reviewed: 2026-06-23.
