# Marketplace Module Guide

Last reviewed: 2026-06-23

This guide is a how-to/reference for maintainers of the **Marketplace** module — a standalone goods/commerce surface where members list physical items for sale, swap-by-negotiation, or give away for free, and buyers order them with real money (Stripe Connect), time credits, or community delivery. It is **completely separate from the timebanking Listings module** (`ListingService` / `listings` table): different tables, different service layer, different Meilisearch index.

> **Real money is involved.** When a tenant enables Stripe, marketplace orders are charged in real currency via Stripe Connect destination charges, held in escrow, and paid out to sellers' connected accounts. Treat the payment, escrow, and order-state code with the same rigour as the wallet ledger.

## Audience & supported workflows

Use this guide when changing marketplace listings, the buyer/seller order lifecycle, payments/escrow, click-and-collect, or moderation.

Supported workflows:

- **Listing management** — a seller creates, edits, photographs, renews, promotes, and removes item listings (separate from timebanking offers/requests).
- **Browse & discovery** — public browse/search/nearby/featured/free, categories, saved listings, saved searches, and personal collections.
- **Offers / negotiation** — a buyer makes an offer on a listing; the seller accepts, declines, or counters; an accepted offer becomes an order.
- **Order lifecycle** — direct buy-now or offer-driven order, moving through `pending_payment → paid → shipped → delivered → completed`, with cancel, dispute, and rating branches.
- **Payments & escrow** — Stripe Connect payment intents / checkout sessions with a platform application fee, escrow hold, and auto/buyer-triggered release to the seller.
- **Click-and-collect** — sellers publish pickup slots; buyers reserve a slot and receive a QR collection code that the seller scans at handover.
- **Community delivery** — a peer deliverer offers to deliver an order and earns **time credits** from the buyer on confirmation.
- **Moderation & DSA reports** — optional listing moderation, user reports against listings, seller verification/suspension, and transparency stats.

## Tenant & feature-gate rules

- **Feature gate:** `marketplace` (**default OFF** — `App\Services\TenantFeatureConfig::DEFAULTS['marketplace'] => false`). The whole module is dark until a tenant explicitly enables it.
- **Backend enforcement is per-controller, not middleware.** Every marketplace controller calls `TenantContext::hasFeature('marketplace')` (via a private `ensureFeature()` helper) and returns `FEATURE_DISABLED` (HTTP 403) when the feature is off. This applies to the **public** browse/search endpoints too — `MarketplaceListingController::index()` gates before serving. The accessible (GOV.UK) frontend gates the same way with `abort_unless(TenantContext::hasFeature('marketplace'), 403)`.
- **React frontend:** routes are wrapped in `<FeatureGate feature="marketplace" …>` in `react-frontend/src/App.tsx` (the main `marketplace` route shows a "coming soon" fallback; sub-routes redirect).
- **Tenant scoping is mandatory.** Every marketplace table carries `tenant_id` and every query is scoped by `App\Core\TenantContext::getId()`. Order, escrow, and pickup operations re-pin tenant via `TenantContext::runForTenant()` / `setById()` so cron and webhook paths (which boot without a tenant) operate under the correct tenant.
- **Module-level config** lives in `App\Services\MarketplaceConfigurationService` (per-tenant key/value with `DEFAULTS`), independent of the `marketplace` feature flag. Notable defaults: Stripe `false`, escrow `false`, platform fee `5%`, escrow auto-release `14` days, moderation `false`, free items `true`, max active listings `50`, listing duration `30` days, max images `20`. A tenant can have the feature on but money off.

## Key code & data locations

Routes are defined in [`routes/api.php`](../../routes/api.php) under the "Marketplace Module — Authenticated routes" and "— Public routes" sections (`/v2/marketplace/*`), the admin section (`/v2/admin/marketplace/*`), and the Stripe webhook (`/v2/marketplace/webhooks/stripe`). Do not copy the endpoint table here — read the route file or the OpenAPI/`docs/API.md` reference for the live list. The module spans ~14 member-facing controllers, one admin controller, and ~18 services.

| Concern | Route prefix | Controller |
| --- | --- | --- |
| Listing CRUD, images/video, browse, categories, saved, bulk/CSV | `/v2/marketplace/listings/*`, `/v2/marketplace/categories/*` | `MarketplaceListingController` |
| Offers / negotiation | `/v2/marketplace/listings/{id}/offers`, `/v2/marketplace/offers/*`, `/v2/marketplace/my-offers/*` | `MarketplaceOfferController` |
| Orders lifecycle | `/v2/marketplace/orders/*` | `MarketplaceOrderController` |
| Payments / Stripe Connect / payouts | `/v2/marketplace/payments/*`, `/v2/marketplace/seller/{payouts,balance,onboard}` | `MarketplacePaymentController` |
| Seller profile, dashboard, shipping options | `/v2/marketplace/seller/*`, `/v2/marketplace/sellers/{id}` | `MarketplaceSellerController` |
| Discovery (saved searches, collections) | `/v2/marketplace/saved-searches/*`, `/v2/marketplace/collections/*` | `MarketplaceDiscoveryController` |
| Promotions (paid bump/feature) | `/v2/marketplace/promotions/*`, `/v2/marketplace/listings/{id}/promote` | `MarketplacePromotionController` |
| Click-and-collect pickup slots | `/v2/marketplace/seller/pickup-slots/*`, `/v2/marketplace/seller/pickup-scan`, `/v2/marketplace/orders/{id}/pickup-reservation`, `/v2/marketplace/me/pickups` | `MarketplacePickupSlotController` |
| Community delivery (time-credit P2P) | `/v2/marketplace/orders/{orderId}/delivery-offers/*` | `MarketplaceCommunityDeliveryController` |
| Inventory | `/v2/marketplace/seller/listings/{id}/inventory` | `MarketplaceInventoryController` |
| Group-scoped marketplace | `/v2/marketplace/groups/{groupId}/*` | `MarketplaceGroupController` |
| AI auto-reply / description | `/v2/marketplace/listings/{id}/auto-reply`, `…/generate-description` | `MarketplaceAiController`, `MarketplaceListingController` |
| DSA user reports | `/v2/marketplace/listings/{id}/report` | `MarketplaceReportController` |
| Admin moderation / sellers / reports | `/v2/admin/marketplace/*` | `AdminMarketplaceController` |
| Stripe Connect webhooks | `/v2/marketplace/webhooks/stripe` | `StripeWebhookController` |

Services (`app/Services/`):

- `MarketplaceListingService` — listing read/create/update/remove/renew, Meilisearch sync, max-active-listings enforcement, geocoding.
- `MarketplaceOrderService` — order lifecycle (create from offer / direct purchase → ship → deliver → complete / cancel), order numbers (`MKT-000001`), idempotent multi-channel notifications.
- `MarketplacePaymentService` — Stripe Connect account creation/onboarding, payment intents and checkout sessions (application fee + `transfer_data`), payment confirmation, refunds.
- `MarketplaceEscrowService` — hold funds on payment, release on buyer confirm / auto-timeout / admin override / dispute resolution, refund on dispute.
- `MarketplacePickupSlotService` — click-and-collect slot CRUD, atomic reservation, QR (ULID) collection code, seller scan.
- `MarketplaceCommunityDeliveryService` — peer delivery offers settled in **time credits** (writes to the `transactions` ledger).
- `MarketplaceOfferService`, `MarketplaceSellerService`, `MarketplaceRatingService`, `MarketplaceReportService`, `MarketplaceConfigurationService`, `MarketplaceDiscoveryService`, `MarketplacePromotionService`, `MarketplaceInventoryService`, `MarketplaceShippingOptionService`, `MarketplaceGroupService`, `MarketplaceAiService`.

Models / tables (all carry `tenant_id`):

- `marketplace_listings` — item listings (`App\Models\MarketplaceListing`).
- `marketplace_categories` — hierarchical categories (`parent_id`, per-tenant or global `tenant_id = NULL`).
- `marketplace_images` — listing photos (`is_primary`, `sort_order`); listings also have a `video_url`.
- `marketplace_offers` — buyer/seller negotiation.
- `marketplace_orders` — the order record (`order_number`, `status`, `payment_intent_id`, `escrow_released_at`).
- `marketplace_payments` — Stripe payment record (`amount`, `platform_fee`, `seller_payout`, `payout_status`).
- `marketplace_escrow` — escrow hold (`status`, `held_at`, `release_after`, `release_trigger`, `UNIQUE(order_id)`).
- `marketplace_seller_profiles` — seller profile, `stripe_account_id`, verification, `total_sales`/`total_revenue` stats.
- `marketplace_pickup_slots`, `marketplace_pickup_reservations` — click-and-collect.
- `marketplace_delivery_offers` — community delivery offers (settled in time credits).
- `marketplace_saved_listings`, `marketplace_disputes`, `marketplace_reports`, `marketplace_order_notification_deliveries` (notification idempotency ledger).

Frontend entry points:

- React: `react-frontend/src/pages/marketplace/` (`MarketplacePage`, `MarketplaceListingPage`, `CreateMarketplaceListingPage`, `BuyerOrdersPage`, `SellerOrdersPage`, `StripeOnboardingPage`, `FreeItemsPage`, `MarketplaceCollectionsPage`, seller sub-pages incl. `SellerPickupSlotsPage`, etc.), routed in `react-frontend/src/App.tsx`.
- Accessible (GOV.UK): commerce parity routes under `app/Http/Controllers/GovukAlpha/` (`CommerceParity` concern).

## Item types & pricing

There is no separate "swap" listing type. Item pricing is driven by `marketplace_listings.price_type`, an enum of:

| `price_type` | Meaning |
| --- | --- |
| `fixed` | Fixed price (default). Buy-now creates an order at `price`. |
| `negotiable` | Price negotiable — buyers make offers; an accepted offer becomes the order amount. |
| `free` | Give-away / free item (gated by the `marketplace.allow_free_items` config, default on). Surfaced by `GET /v2/marketplace/listings/free`. |
| `auction` | Auction-style listing. |
| `contact` | "Contact for price". |

Additional pricing fields: `price` (`decimal(10,2)`), `price_currency` (default `EUR`), and `time_credit_price` (`decimal(8,2)`) — an optional time-credit price so an item can be acquired with time credits instead of money. Orders record `time_credits_used` when settled that way. `condition` is `new | like_new | good | fair | poor`; `seller_type` is `private | business`; `delivery_method` is `pickup | shipping | both | community_delivery`.

## Listing lifecycle

`marketplace_listings.status` is `draft | active | sold | reserved | expired | removed`; `moderation_status` is `pending | approved | rejected | flagged`.

1. **Create** (`MarketplaceListingService::create`) — status defaults to `active` (or `draft`). `moderation_status` is `approved` immediately unless `MarketplaceConfigurationService::moderationEnabled()` is on for the tenant, in which case it is `pending`. `expires_at` is set from the tenant's listing duration (default 30 days). Max active listings per seller is enforced (default 50). The listing is best-effort synced to the `marketplace_listings` Meilisearch index.
2. **Browse/search** — only `status = active AND moderation_status = approved` listings are publicly visible (see the [Search guide](search.md)). Search uses Meilisearch with an SQL fallback; price-range and posted-within facets force the SQL path.
3. **Update / images / video / renew** — owner-only; re-indexed on save. `renew()` resets `status = active`, extends `expires_at`, and bumps `renewal_count`.
4. **Sold / reserved** — placing an order marks the listing `sold` (when inventory is untracked) or decrements `inventory_count` (AG46) under a row lock.
5. **Remove** — soft-removes (`status = removed`) and deletes the Meilisearch document.

Ownership is enforced in the controller (`ensureOwner()` → `FORBIDDEN` when `listing.user_id !== auth user`).

## Categories

`marketplace_categories` is a per-tenant (or global, `tenant_id = NULL`) hierarchy with `parent_id`, `slug` (unique per tenant), `icon`, `sort_order`, and `is_active`. Listings reference `category_id` (FK `ON DELETE SET NULL`). Category templates (`MarketplaceCategoryTemplate`, served by `categoryTemplate()`) drive per-category structured fields stored in the listing's `template_data` JSON.

## Buyer/seller order flow

`marketplace_orders.status` enum: `pending_payment | paid | shipped | delivered | completed | disputed | refunded | cancelled`.

1. **Create** — either `createFromOffer()` (from an `accepted` offer) or `createDirectPurchase()` (buy-now). Both run in a `DB::transaction`, generate an `MKT-NNNNNN` order number, snapshot `unit_price`/`total_price`/`currency`, and apply an optional merchant coupon (AG63). Direct purchase **locks the listing row** and re-checks `status = active` to prevent two buyers racing the same item; inventory is decremented atomically (AG46). Confirmation emails to both parties are sent **outside** the transaction.
2. **Pay** — `MarketplacePaymentService::confirmPayment()` (driven by the Stripe webhook / confirm endpoint) locks the order, sets `status = paid`, writes a `marketplace_payments` row, and — when escrow is enabled — calls `MarketplaceEscrowService::holdFunds()`. Paid notifications fire after commit.
3. **Ship** — seller-only (`markShipped`): `paid → shipped` with optional tracking; notifies the buyer.
4. **Confirm delivery** — buyer-only (`confirmDelivery`): → `delivered`, sets `auto_complete_at = now + 14 days`; notifies the seller.
5. **Complete** — `complete()` transitions `delivered/paid/shipped → completed` via an **atomic status-predicated `UPDATE`** so a buyer-confirm racing the auto-release cron (or a double-click) can only increment seller stats once. Sets `escrow_released_at`. Escrow release (`releaseFunds`) also calls `complete()`, so completion and payout are coordinated.
6. **Cancel** — only before shipped (`pending_payment`/`paid`); restores inventory or re-activates the listing; notifies both parties.
7. **Dispute** — `dispute()` opens a `marketplace_disputes` row; an open dispute blocks escrow auto-release (the escrow is marked `disputed` instead). Admin resolution releases or refunds.
8. **Rate** — after completion, buyer and seller can rate each other (`MarketplaceRatingService`, `rater_role`, optional anonymity).

### Click-and-collect (collection code)

For `delivery_method` involving pickup, sellers publish `marketplace_pickup_slots` (capacity, `slot_start`/`slot_end`, recurring patterns). A buyer reserves a slot for an order via `reserve()`, which under a row lock validates the slot is active/future/not-full, rejects a duplicate reservation per order, increments `booked_count`, and mints a **QR collection code** (`qr_code`, a URL-safe ULID) on the reservation. At handover the seller calls `scanQr()`, which verifies the code belongs to one of their orders, rejects already-picked-up/cancelled reservations, and marks the reservation `picked_up`. There is no separate human-typed short code — the QR/ULID **is** the collection token.

## Money & credit handling

Three distinct settlement paths exist; be precise about which one applies:

- **Real money (Stripe Connect).** Gated by `marketplace.stripe_enabled`. `MarketplacePaymentService` creates a Connect **destination charge**: the buyer is charged `total_price`, an `application_fee_amount` (platform fee, default 5%, configurable) is routed to the platform account, and the remainder is transferred to the seller's connected account (`stripe_account_id` from their seller profile). Checkout sessions use an idempotency key derived from tenant + order + amount + fee. Refunds reverse the application fee proportionally for Connect refunds. Amounts are `decimal(10,2)`; fee/amount conversions go through cents.
- **Escrow.** Gated by `marketplace.escrow_enabled`. On payment, `holdFunds()` writes a `held` escrow (`amount = seller_payout`, `release_after = now + N days`, default 14). Funds release to the seller on `buyer_confirmed`, `auto_timeout` (hourly `processAutoReleases()` cron), `admin_override`, or `dispute_resolved`; release/refund use **atomic status-predicated `UPDATE`s** so a buyer-confirm cannot race the cron into a double-payout, and a refund cannot clobber an already-released escrow (which would pay the seller **and** refund the buyer). A `UNIQUE(order_id)` constraint backstops concurrent `holdFunds()`.
- **Time credits.** Community delivery (`MarketplaceCommunityDeliveryService::confirmDelivery`) is the path that moves **time credits**: on confirmation the buyer's `users.balance` is debited and the deliverer's credited inside one `DB::transaction` (balance re-checked under lock; insufficient balance throws), writing a `transactions` ledger row with `transaction_type = 'community_delivery'`. Items can also carry a `time_credit_price` so the item itself is acquired with credits (`orders.time_credits_used`). Merchant coupons (AG63) discount the cash subtotal.

## Moderation, reports & sellers

- **Listing moderation** is optional (`marketplace.moderation_enabled`). When on, new listings are `pending` and must be approved by an admin (`AdminMarketplaceController::approveListing` / `rejectListing` / `bulkReject`) before they are searchable. With trusted auto-approve (`marketplace.auto_approve_trusted`) and DSA-compliance flags as additional config.
- **DSA user reports** — any member can report a listing (`POST /v2/marketplace/listings/{id}/report` → `marketplace_reports`, handled by `MarketplaceReportService`). Admins list/acknowledge/resolve reports and view transparency stats (`/v2/admin/marketplace/{reports,transparency}`).
- **Seller management** — admins verify (`verifySeller`) or suspend (`suspendSeller`, which also pulls the seller's live listings to `removed`/`rejected`). Seller stats (`total_sales`, `total_revenue`) are incremented atomically on order completion.

## Security & privacy invariants

- Every marketplace `SELECT`/`UPDATE`/`DELETE` must include `tenant_id`; cron/webhook entry points must set tenant context (`runForTenant` / `setById`) before any scoped query.
- **Listing mutation is owner-gated** (`ensureOwner`); **ship is seller-only**, **confirm-delivery is buyer-only** — keep these gated to prevent IDOR.
- **Money/escrow transitions must use atomic status-predicated `UPDATE`s**, never an unconditional `save()` on a status read into memory — this is what prevents double-payout, refund-after-release, and double stat increments under concurrency.
- **Direct purchase must lock the listing row** and re-check availability/inventory inside the transaction.
- Email/notification rendering must wrap in `LocaleContext::withLocale($recipient, …)` so order/payout emails render in the recipient's `preferred_language`. Order notifications are de-duplicated per (order, event, user, channel) via `marketplace_order_notification_deliveries`.
- Notifications run **outside** the financial `DB::transaction` so a notification failure can never roll back a payment, payout, or credit transfer.
- Public browse endpoints only expose `active` + `approved` listings; the feature gate is enforced even on unauthenticated reads.

## Failure modes & recovery

| Failure | How it is handled |
| --- | --- |
| **Feature disabled** | All marketplace endpoints (incl. public browse) return `FEATURE_DISABLED` (403). |
| **Two buyers race the same item** | Direct purchase locks the listing row and re-checks `status = active` / inventory inside the transaction; the loser gets "no longer available". |
| **Buyer-confirm races auto-release cron** | Atomic status-predicated completion / escrow release: exactly one caller wins; the loser is a no-op (stats untouched, no second payout). |
| **Concurrent escrow hold** | `UNIQUE(order_id)` plus an exists-check; a concurrent `holdFunds()` returns the existing escrow. |
| **Refund vs release race** | `refundEscrow()` claims `held|disputed` atomically; if the escrow already `released`, the refund throws rather than overwriting (prevents paying seller AND refunding buyer). |
| **Open dispute at auto-release time** | `processAutoReleases()` marks the escrow `disputed` (conditional on still being `held`) and skips payout. |
| **Stripe webhook / payment confirm** | `confirmPayment` is idempotent on `stripe_payment_intent_id` (UNIQUE); escrow hold is supplementary — a failed hold is logged but does not unwind a succeeded payment. |
| **Duplicate pickup reservation / full slot / past slot** | `reserve()` throws typed `DomainException` (`DUPLICATE_RESERVATION`, `SLOT_FULL`, `SLOT_PAST`, `SLOT_INACTIVE`). |
| **Community delivery, buyer underfunded** | Balance re-checked under lock at `confirmDelivery`; throws insufficient-balance and rolls back — no partial credit move. |
| **Meilisearch unavailable** | Listing index/sync no-ops; browse falls back to SQL `LIKE` (see Search guide). Backfill with `php scripts/sync_search_index.php --tenant=<id> --type=marketplace`. |
| **Notification/email failure** | Caught, logged, recorded in the delivery ledger; never rolls back a committed financial transaction. |

Recovery: financial and reservation operations are atomic, so a failed operation leaves no partial state — retry. For a stuck order, inspect `marketplace_orders.status`, the linked `marketplace_payments`/`marketplace_escrow` rows, and `marketplace_order_notification_deliveries` for what was delivered. Escrows past `release_after` are swept hourly by `processAutoReleases()`.

## Test commands & key regression tests

Run the backend suites (run heavy suites one at a time):

```bash
vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated --colors=always
```

Targeted runs:

```bash
vendor/bin/phpunit --filter=Marketplace
vendor/bin/phpunit tests/Laravel/Unit/Services/MarketplaceEscrowServiceTest.php
vendor/bin/phpunit tests/Laravel/Unit/Services/MarketplaceOrderServiceTest.php
```

Important test files:

| Test | What it locks down |
| --- | --- |
| `tests/Laravel/Unit/Services/MarketplaceOrderServiceTest.php` | Order lifecycle, atomic completion / double-complete no-op, cancellation + inventory restore. |
| `tests/Laravel/Unit/Services/MarketplaceEscrowServiceTest.php` | Hold/release/refund races, auto-release, dispute blocking, no double-payout. |
| `tests/Laravel/Unit/Services/MarketplacePaymentServiceTest.php` | Stripe Connect intents/fees, refunds. |
| `tests/Laravel/Unit/Services/MarketplaceCommunityDeliveryServiceTest.php` | Time-credit settlement, insufficient-balance rollback. |
| `tests/Laravel/Unit/Services/MarketplaceListingServiceTest.php` | Create/update/renew, moderation gating, max-active enforcement. |
| `tests/Laravel/Unit/Services/MarketplaceOfferServiceTest.php` | Offer accept/decline/counter → order. |
| `tests/Laravel/Feature/Controllers/Marketplace*ControllerTest.php`, `AdminMarketplaceControllerTest.php` | Feature gating (incl. public routes), ownership/role gates, moderation, reports, seller verify/suspend. |

React: `react-frontend/src/pages/marketplace/*.test.tsx` (e.g. `npm test -- BuyerOrdersPage`).

## Related references

- [modules/listings.md](listings.md) — the **timebanking** Listings module (distinct table/service; do not conflate).
- [modules/wallet-exchanges.md](wallet-exchanges.md) — the time-credit ledger that community delivery and `time_credit_price` settle against.
- [modules/search.md](search.md) — the `marketplace_listings` Meilisearch index and SQL fallback.
- [`routes/api.php`](../../routes/api.php) — authoritative endpoint list (do not duplicate here).
- [ARCHITECTURE.md](../ARCHITECTURE.md) — runtime boundaries.
</content>
</invoke>
