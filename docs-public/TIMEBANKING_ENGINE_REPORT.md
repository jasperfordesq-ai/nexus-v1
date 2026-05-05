# Project NEXUS — Timebanking Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Core Concept: Time Credits](#2-core-concept-time-credits)
3. [System Architecture](#3-system-architecture)
4. [Database Schema](#4-database-schema)
5. [Wallet System](#5-wallet-system)
6. [Listing Marketplace](#6-listing-marketplace)
7. [Exchange Workflow](#7-exchange-workflow)
8. [Group Exchanges](#8-group-exchanges)
9. [Smart Matching Engine](#9-smart-matching-engine)
10. [Collaborative Filtering & Recommendations](#10-collaborative-filtering--recommendations)
11. [Match Learning System](#11-match-learning-system)
12. [Listing Ranking (MatchRank Algorithm)](#12-listing-ranking-matchrank-algorithm)
13. [Community Fund](#13-community-fund)
14. [Organization Wallets](#14-organization-wallets)
15. [Credit Donations](#15-credit-donations)
16. [Federation Credits](#16-federation-credits)
17. [Broker & Moderation System](#17-broker--moderation-system)
18. [Rating & Reputation](#18-rating--reputation)
19. [Gamification Integration](#19-gamification-integration)
20. [Stripe (Monetary Donations)](#20-stripe-monetary-donations)
21. [API Reference](#21-api-reference)
22. [Frontend User Experience](#22-frontend-user-experience)
23. [Concurrency & Safety](#23-concurrency--safety)
24. [Complete User Journey](#24-complete-user-journey)

---

## 1. Executive Summary

The Project NEXUS Timebanking Engine is the core economic system that powers a multi-tenant community platform where services are exchanged using **time credits** — one hour of any service equals one time credit, regardless of the service type. A plumber's hour is worth the same as a tutor's hour.

The engine encompasses:

- **Personal wallets** — balance, transfers, transaction history, CSV export
- **Listing marketplace** — service offers/requests with skill tags, geolocation, moderation
- **Exchange workflow** — a 10-state machine with broker approval, dual-party confirmation, and dispute resolution
- **Group exchanges** — multi-participant exchanges with equal/weighted/custom splits
- **Smart matching** — 6-dimensional scoring with Porter stemming, Haversine proximity, and semantic embeddings
- **Collaborative filtering** — item-item and user-user recommendation algorithms
- **Match learning** — behavioral learning from user interactions with 30-day exponential decay
- **Community fund** — tenant-owned credit pool with admin deposit/withdraw/donate
- **Organization wallets** — per-org credit accounts with transfer approval workflows
- **Federation credits** — inter-community transfers with exchange rates
- **Rating system** — post-exchange 1-5 star ratings per party
- **Broker moderation** — risk-based gating with compliance checks (DBS, insurance)

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 30+ (wallet, exchange, matching, ranking, learning, etc.) |
| API endpoints | 75+ timebanking-related routes |
| Database tables | 20+ dedicated timebanking tables |
| Exchange states | 10 (pending_provider → completed) |
| Matching dimensions | 6 scoring signals |
| React pages | 15+ (wallet, exchanges, listings, matches) |
| Rate limits | Per-endpoint (10-60 requests/minute) |

---

## 2. Core Concept: Time Credits

### The Principle

Time credits are the currency of timebanking. The fundamental rule:

> **1 hour of service = 1 time credit, regardless of the service performed.**

A gardener's hour, a lawyer's hour, and a baker's hour are all worth exactly 1 credit. This egalitarian model promotes equity and community participation.

### Properties of Time Credits

| Property | Value |
|----------|-------|
| Unit | Hours (decimal to 2 places) |
| Minimum transfer | 0.01 hours |
| Maximum single transfer | 1,000 hours |
| Exchange range | 0.25 – 24 hours per exchange |
| Decimal precision | 2 decimal places (e.g., 1.50 hours) |
| Currency label | "hours" |
| Negative balance | Not allowed (enforced at DB level) |
| Starting balance | Configurable per tenant (default: 0) |

### How Credits Flow

```
┌──────────────┐    Exchange Completion    ┌──────────────┐
│   Requester   │ ──────────────────────► │   Provider    │
│  (receives    │     Credits transfer     │  (provides    │
│   service)    │ ◄────── time ──────────  │   service)    │
│  -1.5 hours   │                          │  +1.5 hours   │
└──────────────┘                           └──────────────┘

┌──────────────┐    Direct Transfer        ┌──────────────┐
│    Sender     │ ──────────────────────► │   Receiver    │
│  -2.0 hours   │                          │  +2.0 hours   │
└──────────────┘                           └──────────────┘

┌──────────────┐    Donation               ┌──────────────┐
│    Donor      │ ──────────────────────► │ Community     │
│  -3.0 hours   │                          │ Fund +3.0     │
└──────────────┘                           └──────────────┘

┌──────────────┐    Admin Grant            ┌──────────────┐
│   System      │ ──────────────────────► │  New Member   │
│  (sender=0)   │   Starting balance       │  +5.0 hours   │
└──────────────┘                           └──────────────┘
```

---

## 3. System Architecture

### Service Map

```
┌─────────────────────────────────────────────────────────────────────┐
│                         TIMEBANKING ENGINE                          │
│                                                                     │
│  ┌─────────────┐  ┌──────────────────┐  ┌───────────────────────┐  │
│  │   WALLET     │  │    EXCHANGE       │  │     MATCHING          │  │
│  │             │  │                  │  │                       │  │
│  │ WalletSvc   │  │ ExchangeSvc      │  │ SmartMatchingEngine   │  │
│  │ StartBal    │  │ ExchangeWorkflow │  │ CollabFilteringSvc    │  │
│  │ BalanceAlert│  │ ExchangeRating   │  │ MatchLearningSvc      │  │
│  │ TxnExport   │  │ GroupExchangeSvc │  │ MatchApprovalWorkflow │  │
│  │ TxnCategory │  │ ExchangeHistory  │  │ CrossModuleMatchSvc   │  │
│  │ CreditDonate│  │                  │  │ MatchingSvc           │  │
│  └──────┬──────┘  └────────┬─────────┘  └───────────┬───────────┘  │
│         │                  │                         │              │
│  ┌──────▼──────┐  ┌────────▼─────────┐  ┌───────────▼───────────┐  │
│  │  COMMUNITY   │  │    LISTINGS       │  │     DISCOVERY         │  │
│  │             │  │                  │  │                       │  │
│  │ CommunityFd │  │ ListingSvc       │  │ ListingRankingSvc     │  │
│  │ OrgWalletSv │  │ ListingAnalytics │  │ SearchService         │  │
│  │ FedCreditSv │  │ ListingModeration│  │ EmbeddingService      │  │
│  │             │  │ ListingExpiry    │  │                       │  │
│  │             │  │ ListingFeatured  │  │                       │  │
│  │             │  │ ListingSkillTag  │  │                       │  │
│  └─────────────┘  └──────────────────┘  └───────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Wallet | `app/Services/WalletService.php` |
| Starting Balance | `app/Services/StartingBalanceService.php` |
| Balance Alerts | `app/Services/BalanceAlertService.php` |
| Transaction Export | `app/Services/TransactionExportService.php` |
| Transaction Categories | `app/Services/TransactionCategoryService.php` |
| Credit Donations | `app/Services/CreditDonationService.php` |
| Community Fund | `app/Services/CommunityFundService.php` |
| Org Wallets | `app/Services/OrgWalletService.php` |
| Exchange (CRUD) | `app/Services/ExchangeService.php` |
| Exchange Workflow | `app/Services/ExchangeWorkflowService.php` (1,027 lines) |
| Exchange Ratings | `app/Services/ExchangeRatingService.php` |
| Group Exchanges | `app/Services/GroupExchangeService.php` |
| Listings | `app/Services/ListingService.php` |
| Listing Analytics | `app/Services/ListingAnalyticsService.php` |
| Listing Moderation | `app/Services/ListingModerationService.php` |
| Listing Ranking | `app/Services/ListingRankingService.php` |
| Smart Matching | `app/Services/SmartMatchingEngine.php` |
| Collaborative Filtering | `app/Services/CollaborativeFilteringService.php` |
| Match Learning | `app/Services/MatchLearningService.php` |
| Match Approval | `app/Services/MatchApprovalWorkflowService.php` |
| Cross-Module Matching | `app/Services/CrossModuleMatchingService.php` |
| Federation Credits | `app/Services/FederationCreditService.php` |
| Stripe Donations | `app/Services/StripeDonationService.php` |

---

## 4. Database Schema

### Entity-Relationship Overview

```
tenants ─────┬──── users ─────┬──── transactions
             │                ├──── listings ──── listing_skill_tags
             │                ├──── exchange_requests ──── exchange_history
             │                │                       └──── exchange_ratings
             │                ├──── credit_donations
             │                └──── daily_rewards
             │
             ├──── community_fund_accounts ──── community_fund_transactions
             ├──── org_wallets ──── org_transactions
             │                └──── org_transfer_requests
             ├──── transaction_categories
             ├──── group_exchanges ──── group_exchange_participants
             └──── federation_credit_agreements ──── federation_credit_transfers
                                                └──── federation_credit_balances
```

### Core Tables

#### `transactions`
The central ledger for all time credit movements.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | Auto-increment |
| `tenant_id` | int FK | Tenant scope |
| `sender_id` | int FK | User giving credits (0 = system) |
| `receiver_id` | int FK | User receiving credits |
| `amount` | int | Credits transferred |
| `description` | text | Transaction description |
| `status` | enum | `pending`, `completed`, `cancelled` |
| `transaction_type` | enum | `exchange`, `volunteer`, `donation`, `other` |
| `source_match_id` | int | Related exchange/listing ID |
| `listing_id` | int FK | Related listing |
| `category_id` | int FK | Transaction category |
| `prep_time` | decimal(5,2) | Preparation time hours |
| `is_federated` | tinyint | Cross-community flag |
| `sender_tenant_id` | int | Source tenant (federation) |
| `receiver_tenant_id` | int | Destination tenant (federation) |
| `deleted_for_sender` | tinyint | Per-user soft delete |
| `deleted_for_receiver` | tinyint | Per-user soft delete |
| `created_at` | datetime | |
| `updated_at` | datetime | |

**Indexes:** `(tenant_id, created_at)`, `(sender_id)`, `(receiver_id)`, `(status)`, `(is_federated)`

#### `exchange_requests`
The exchange workflow state machine.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `tenant_id` | int FK | |
| `listing_id` | int FK | The listing being exchanged |
| `requester_id` | int FK | User requesting the service |
| `provider_id` | int FK | User providing the service |
| `proposed_hours` | decimal(5,2) | Initially proposed hours |
| `proposed_date` | date | Optional scheduling |
| `proposed_time` | time | Optional scheduling |
| `proposed_location` | varchar(255) | Optional location |
| `requester_notes` | text | Message from requester |
| `status` | enum | 10 states (see below) |
| `broker_id` | int FK | Assigned broker |
| `broker_notes` | text | Broker review notes |
| `broker_approved_at` | timestamp | |
| `broker_conditions` | text | Conditions set by broker |
| `requester_confirmed_at` | timestamp | When requester confirmed hours |
| `requester_confirmed_hours` | decimal(5,2) | Hours requester confirmed |
| `provider_confirmed_at` | timestamp | When provider confirmed hours |
| `provider_confirmed_hours` | decimal(5,2) | Hours provider confirmed |
| `final_hours` | decimal(5,2) | Agreed final hours |
| `transaction_id` | int FK | Link to transactions table |
| `completed_at` | timestamp | |
| `risk_tag_id` | int | Associated risk tag |
| `cancelled_by` | int | Who cancelled |
| `cancelled_at` | timestamp | |
| `cancellation_reason` | text | |
| `decline_reason` | text | |
| `prep_time` | decimal(5,2) | Preparation time |
| `expires_at` | timestamp | Auto-expiry |

**Status Values:**
`pending_provider`, `pending_broker`, `accepted`, `scheduled`, `in_progress`, `pending_confirmation`, `completed`, `disputed`, `cancelled`, `expired`

#### `exchange_history`
Audit trail for every exchange state change.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `exchange_id` | int FK | |
| `action` | varchar(100) | e.g. `request_created`, `accepted`, `confirmed` |
| `actor_id` | int FK | Who performed the action |
| `actor_role` | enum | `requester`, `provider`, `broker`, `system` |
| `old_status` | varchar(50) | Previous status |
| `new_status` | varchar(50) | New status |
| `notes` | text | |
| `metadata` | JSON | Optional extra data |
| `created_at` | timestamp | |

#### `exchange_ratings`
Post-completion ratings between exchange parties.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `tenant_id` | int FK | |
| `exchange_id` | int FK | |
| `rater_id` | int FK | Who is rating |
| `rated_id` | int FK | Who is being rated |
| `rating` | tinyint | 1–5 stars |
| `comment` | text | Optional review text |
| `role` | enum | `requester` or `provider` |

**Unique constraint:** `(exchange_id, rater_id)` — one rating per person per exchange.

#### `listings`
Service offers and requests in the marketplace.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `tenant_id` | int FK | |
| `user_id` | int FK | Listing owner |
| `category_id` | int FK | |
| `title` | varchar(255) | |
| `description` | text | |
| `type` | varchar(50) | `offer` or `request` |
| `status` | varchar(50) | `active`, `draft`, `pending`, `suspended`, `expired`, `deleted`, `rejected` |
| `location` | varchar(255) | |
| `latitude` | decimal(10,8) | |
| `longitude` | decimal(11,8) | |
| `hours_estimate` | decimal(5,2) | Estimated time credits |
| `service_type` | enum | `physical_only`, `remote_only`, `hybrid`, `location_dependent` |
| `federated_visibility` | enum | `none`, `listed`, `bookable` |
| `exchange_workflow_required` | tinyint | Forces exchange workflow |
| `direct_messaging_disabled` | tinyint | |
| `image_url` | varchar(255) | |
| `sdg_goals` | JSON | UN Sustainable Development Goals tags |
| `is_featured` | tinyint | |
| `featured_until` | datetime | |
| `view_count` | int unsigned | |
| `contact_count` | int unsigned | |
| `save_count` | int unsigned | |
| `moderation_status` | enum | `pending_review`, `approved`, `rejected` |
| `expires_at` | datetime | |
| `renewed_at` | datetime | |
| `renewal_count` | int unsigned | |

**Full-text index:** `(title, description)` for SQL-based search fallback.

#### `group_exchanges`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `tenant_id` | int FK | |
| `organizer_id` | int FK | |
| `listing_id` | int FK | Optional linked listing |
| `title` | varchar(255) | |
| `description` | text | |
| `status` | enum | `draft`, `pending_participants`, `pending_broker`, `active`, `pending_confirmation`, `completed`, `cancelled`, `disputed` |
| `split_type` | enum | `equal`, `custom`, `weighted` |
| `total_hours` | decimal(10,2) | Total hours to distribute |
| `broker_id` | int FK | |
| `broker_notes` | text | |
| `completed_at` | timestamp | |

#### `group_exchange_participants`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `group_exchange_id` | int FK | |
| `user_id` | int FK | |
| `role` | enum | `provider` or `receiver` |
| `hours` | decimal(10,2) | Assigned hours (custom split) |
| `weight` | decimal(5,2) | Weight factor (weighted split) |
| `confirmed` | tinyint | 0 or 1 |
| `confirmed_at` | timestamp | |
| `notes` | text | |

**Unique:** `(group_exchange_id, user_id, role)`

#### `community_fund_accounts`
One per tenant — the community's shared credit pool.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | |
| `tenant_id` | int FK (unique) | One fund per tenant |
| `balance` | decimal(10,2) | Current balance |
| `total_deposited` | decimal(10,2) | Cumulative deposits |
| `total_withdrawn` | decimal(10,2) | Cumulative withdrawals |
| `total_donated` | decimal(10,2) | Cumulative member donations |
| `description` | varchar(500) | |

#### `community_fund_transactions`

| Column | Type | Description |
|--------|------|-------------|
| `type` | enum | `deposit`, `withdrawal`, `donation`, `starting_balance_grant` |
| `amount` | decimal(10,2) | |
| `balance_after` | decimal(10,2) | Running balance |
| `admin_id` | int FK | Admin who performed action |

#### `org_wallets`
One wallet per organization per tenant.

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | int | |
| `organization_id` | int | |
| `balance` | decimal(10,2) | |

**Unique:** `(tenant_id, organization_id)`

#### `org_transfer_requests`
Approval workflow for organization → user transfers.

| Column | Type | Description |
|--------|------|-------------|
| `status` | enum | `pending`, `approved`, `rejected`, `cancelled` |
| `approved_by` | int FK | Admin who approved |

#### `credit_donations`

| Column | Type | Description |
|--------|------|-------------|
| `donor_id` | int FK | |
| `recipient_type` | enum | `user` or `community_fund` |
| `recipient_id` | int FK | Null if community_fund |
| `amount` | decimal(10,2) | |
| `message` | varchar(500) | |
| `transaction_id` | int FK | Link to transactions table |

#### `federation_credit_agreements`

| Column | Type | Description |
|--------|------|-------------|
| `from_tenant_id` | int FK | |
| `to_tenant_id` | int FK | |
| `exchange_rate` | decimal(10,4) | Default 1.0 |
| `status` | enum | `pending`, `active`, `suspended`, `terminated` |
| `max_monthly_credits` | decimal(10,2) | Optional cap |

#### `federation_credit_transfers`

| Column | Type | Description |
|--------|------|-------------|
| `agreement_id` | int FK | |
| `amount` | decimal(10,2) | Original amount |
| `converted_amount` | decimal(10,2) | After exchange rate |
| `exchange_rate` | decimal(10,4) | Rate at transfer time |
| `status` | enum | `pending`, `completed`, `reversed` |

#### `federation_credit_balances`
Net balance tracking between federated communities.

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id_a` | int | |
| `tenant_id_b` | int | |
| `net_balance` | decimal(10,2) | Positive = A owes B |

**Unique:** `(tenant_id_a, tenant_id_b)`

#### `transaction_categories`
Per-tenant categorization of exchanges (e.g. Gardening, Tutoring, Cooking).

| Column | Type | Description |
|--------|------|-------------|
| `name` | varchar(100) | |
| `slug` | varchar(100) | Auto-generated |
| `icon` | varchar(50) | Icon identifier |
| `color` | varchar(7) | Hex color |
| `sort_order` | int | Display order |
| `is_system` | tinyint | System categories cannot be deleted |
| `is_active` | tinyint | Soft toggle |

---

## 5. Wallet System

### WalletService

The core wallet service manages personal time credit accounts.

#### Get Balance
```
WalletService::getBalance(userId) → {
    balance: float,        // Current balance from users.balance
    total_earned: float,   // Sum of completed received transactions
    total_spent: float,    // Sum of completed sent transactions
    pending_incoming: float,
    pending_outgoing: float,
    transaction_count: int,
    currency: "hours"
}
```
Uses a single optimized query with CASE statements for aggregate computation.

#### Transfer Credits
```
WalletService::transfer(senderId, {
    recipient: userId | email | username,
    amount: float,    // > 0, <= 1000, max 2 decimal places
    description: string
}) → formatted transaction
```

**Validation:**
- Recipient required (resolves ID, email, or username)
- Amount > 0 and ≤ 1,000 hours
- Amount has at most 2 decimal places
- Cannot transfer to self
- Recipient cannot be banned/suspended/inactive/deactivated
- Sender must have sufficient balance

**Atomicity:**
- `DB::transaction()` wrapper
- Pessimistic locking: locks both user rows in consistent ID order (lower ID first) to prevent deadlocks
- Deducts sender balance, increments receiver balance
- Creates transaction record with `status='completed'`

#### Transaction History
```
WalletService::getTransactions(userId, {
    limit: 20,      // max 100
    type: "all",    // "sent" | "received" | "all"
    cursor: base64  // cursor-based pagination
}) → { items, cursor, has_more }
```

Cursor-based pagination using base64-encoded IDs. Respects per-user soft-delete flags (`deleted_for_sender`, `deleted_for_receiver`).

#### Transaction Types

| Type | sender_id | receiver_id | Description |
|------|-----------|-------------|-------------|
| `transfer` | User A | User B | Direct credit transfer |
| `donation` | Donor | Recipient / null | Credit donation |
| `starting_balance` | 0 (system) | New user | Initial grant |
| `admin_grant` | 0 (system) | User | Admin-issued credits |
| `community_fund` | User / null | User / null | Fund operations |
| `exchange` | Requester | Provider | Exchange completion |
| `volunteer` | — | Volunteer | Volunteering hours |

### Starting Balance Service

New members can receive an automatic starting balance when they join:

```
StartingBalanceService::applyToNewUser(userId) → {
    success: bool,
    amount: float,
    source: 'starting_balance' | 'already_applied' | 'none' | 'error'
}
```

- Configurable per tenant via `wallet.starting_balance` setting
- Idempotent — checks if already applied before granting
- Creates transaction with `sender_id=0` (system), `transaction_type='starting_balance'`

### Transaction Export

```
TransactionExportService::exportPersonalStatementCSV(userId, {
    startDate?: 'YYYY-MM-DD',
    endDate?: 'YYYY-MM-DD',
    type?: string
}) → { success, csv, filename }
```

- UTF-8 BOM for Excel compatibility
- CSV columns: Date, Type, Description, Other Party, Debit, Credit, Status
- **CSV injection prevention**: prefixes formula characters (`=`, `+`, `-`, `@`, tab) with single quote

### Balance Alerts (Organizations)

```
BalanceAlertService::checkAllBalances() → int (alerts sent)
```

- Default thresholds: **low = 50 hours**, **critical = 10 hours**
- Custom thresholds per organization via `org_alert_settings`
- One alert per type per day (deduplication)
- Runs via cron job

### Rate Limits

| Endpoint | Limit |
|----------|-------|
| `wallet_balance` | 60/min |
| `wallet_transactions` | 30/min |
| `wallet_transfer` | 10/min |
| `wallet_delete` | 20/min |
| `wallet_user_search` | 30/min |
| `wallet_statement` | 10/min |
| `org_wallet_transfer` | 10/min |

---

## 6. Listing Marketplace

### Listing Types

| Type | Description | Credit Flow |
|------|-------------|-------------|
| **Offer** | "I can do X for you" | Provider earns credits |
| **Request** | "I need someone to do X" | Requester spends credits |

### Listing Lifecycle

```
Created (draft) → Pending Review → Active → Exchange Requested → Exchange Completed
                                          → Expired → Renewed
                                          → Suspended (moderation)
                                          → Deleted
```

### ListingService Methods

| Method | Description |
|--------|-------------|
| `getAll(userId, limit, options)` | Cursor-paginated search (Meilisearch with SQL fallback) |
| `getById(id)` | Single listing retrieval |
| `getNearby(lat, lon, radius)` | Haversine distance search |
| `getFeatured(limit)` | Featured listings (featured_until > now) |
| `create(data)` | New listing with validation + XP award |
| `update(id, data)` | Owner-verified update |
| `delete(id)` | Soft/hard delete with feed cleanup |
| `search(query, filters)` | Meilisearch with typo tolerance |
| `saveListing(userId, listingId)` | Add to favorites + increment save_count |
| `unsaveListing(userId, listingId)` | Remove from favorites |

### Listing Analytics

Tracked per listing with **1-hour deduplication** (by user_id or SHA256 IP hash):

| Metric | Description |
|--------|-------------|
| `view_count` | Page views |
| `contact_count` | Messages, phone, email, exchange requests |
| `save_count` | User favorites |
| `contact_rate` | contacts / views |
| `save_rate` | saves / views |
| `views_trend_percent` | 7-day trend |
| `views_over_time` | Time series |
| `contact_types` | Breakdown by type |

Records older than 90 days are cleaned up automatically.

### Listing Moderation

When moderation is enabled at the tenant level:

1. New listings enter `pending_review` status
2. Admins review via moderation queue
3. **Approve** → status becomes `active`, owner notified, feed activity created
4. **Reject** → status becomes `rejected`, owner notified with reason

### Listing Expiry & Renewal

- Listings can have an `expires_at` date
- `ListingExpiryService` sends reminders before expiry
- Users can renew: increments `renewal_count`, updates `renewed_at`

### Service Types

| Type | Description |
|------|-------------|
| `physical_only` | In-person service at a location |
| `remote_only` | Can be done remotely |
| `hybrid` | Either in-person or remote |
| `location_dependent` | Location varies |

### Federation Visibility

| Level | Description |
|-------|-------------|
| `none` | Only visible within own tenant |
| `listed` | Visible in federated search |
| `bookable` | Can be exchanged across tenants |

---

## 7. Exchange Workflow

The exchange workflow is a **10-state machine** implemented in `ExchangeWorkflowService.php` (1,027 lines). It manages the complete lifecycle of a time credit exchange.

### State Machine

```
                                  ┌─────────────┐
                                  │   EXPIRED    │
                                  └──────▲───────┘
                                         │ (timeout)
┌──────────────┐    accept    ┌──────────┴───────┐
│              │ ──────────►  │                   │
│   PENDING    │              │  PENDING_BROKER   │
│   PROVIDER   │ ──────────►  │                   │
│              │  (if broker   └────────┬──────────┘
└──────┬───────┘   required)            │ approve
       │                                ▼
       │ accept            ┌─────────────────────┐
       │ (no broker)       │                     │
       └──────────────────►│     ACCEPTED         │
                           │                     │
                           └──────────┬──────────┘
                                      │ start
                                      ▼
                           ┌─────────────────────┐
                           │                     │
                           │    IN_PROGRESS       │
                           │                     │
                           └──────────┬──────────┘
                                      │ ready / confirm
                                      ▼
                           ┌─────────────────────┐
                           │                     │
                           │ PENDING_CONFIRMATION │◄──── Both parties
                           │                     │      confirm hours
                           └──────┬───────┬──────┘
                                  │       │
                    hours match   │       │ hours mismatch (>0.25h)
                                  ▼       ▼
                           ┌──────────┐ ┌──────────┐
                           │COMPLETED │ │ DISPUTED  │──► broker resolves
                           └──────────┘ └──────────┘       │
                                                           ▼
                                                    ┌──────────┐
                                                    │COMPLETED │
                                                    └──────────┘

    Any non-terminal state ──────► CANCELLED (by either party or broker)
```

### Valid State Transitions

| From | To |
|------|-----|
| `pending_provider` | `pending_broker`, `accepted`, `cancelled`, `expired` |
| `pending_broker` | `accepted`, `cancelled` |
| `accepted` | `in_progress`, `cancelled` |
| `in_progress` | `pending_confirmation`, `cancelled` |
| `pending_confirmation` | `completed`, `disputed` |
| `disputed` | `completed`, `cancelled` |
| `completed` | (terminal) |
| `cancelled` | (terminal) |
| `expired` | (terminal) |

### Key Methods

#### Create Exchange Request
```
ExchangeWorkflowService::createRequest(requesterId, listingId, {
    proposed_hours: float,  // default from listing, clamped 0.25–24
    message: string
}) → exchangeId
```
- Creates with status `pending_provider`
- Logs to exchange_history
- Notifies provider

#### Accept Request (Provider)
```
ExchangeWorkflowService::acceptRequest(exchangeId, providerId) → bool
```
- **Broker check:** calls `needsBrokerApproval()` to determine next state
- If broker required → `pending_broker` (notifies admins)
- If not → `accepted` (notifies requester)

#### Broker Approval Decision

`needsBrokerApproval()` uses a **two-layer check**:

**Layer 1 — User-level safeguarding** (always applies):
- Checks `user_messaging_restrictions.requires_broker_approval = 1`
- Only if monitoring hasn't expired

**Layer 2 — Listing/tenant config** (if exchange workflow enabled):
- `require_broker_approval` — global flag
- `auto_approve_low_risk` — bypass for low-risk
- `max_hours_without_approval` — threshold (default 4 hours)
- `listing_risk_tags.risk_level = 'high'` → requires approval
- `listing_risk_tags.requires_approval = true` → requires approval

#### Dual Confirmation
```
ExchangeWorkflowService::confirmCompletion(exchangeId, userId, hours) → bool
```

Both parties independently confirm the hours worked:

1. First party confirms → sets `{role}_confirmed_at` and `{role}_confirmed_hours`
2. Second party confirms → triggers `processConfirmations()`

**Hour variance handling:**
- Hours clamped to ±25% of proposed hours: `min = proposed × 0.75`, `max = proposed × 1.25`
- Final hours = `max(min, min(max, confirmed))`

**After both confirm:**
- If difference < 0.01 hours → exact match → complete
- If difference ≤ 0.25 hours → average the two → complete
- If difference > 0.25 hours → **dispute** (broker reviews)

#### Complete Exchange (Credit Transfer)
```
ExchangeWorkflowService::completeExchange(exchangeId, finalHours) → bool
```

**Inside DB::transaction():**
1. Re-lock exchange row (`FOR UPDATE`)
2. Guard: status must be `pending_confirmation` or `disputed`
3. Check requester has sufficient balance
4. Lock both user rows (requester + provider)
5. Decrement requester.balance by `finalHours`
6. Increment provider.balance by `finalHours`
7. Insert transaction record
8. Set `final_hours`, `transaction_id`, status → `completed`
9. Log to exchange_history

**Critical:** Transaction created FIRST, notifications sent AFTER (outside transaction).

#### Compliance Checks
```
ExchangeWorkflowService::checkComplianceRequirements(listingId, providerId) → string[]
```

Before exchange acceptance, validates:
- **DBS/vetting required?** → Check `vetting_records` (status=verified, not expired)
- **Insurance required?** → Check `insurance_certificates` (status=verified, not expired)

Returns array of violation strings (empty = compliant).

### Notifications

| Event | Recipients | Trigger |
|-------|-----------|---------|
| `exchange_request_received` | Provider | New request created |
| `exchange_accepted` | Requester | Provider accepted |
| `exchange_pending_broker` | Requester + admins | Needs broker review |
| `exchange_request_declined` | Requester | Provider declined |
| `exchange_approved` | Both parties | Broker approved |
| `exchange_rejected` | Both parties | Broker rejected |
| `exchange_started` | Other party | Work started |
| `exchange_ready_confirmation` | Other party | Ready for confirmation |
| `exchange_completed` | Both parties | Credits transferred |
| `exchange_cancelled` | Other party | Exchange cancelled |

Notification failures are logged but **never rollback** financial transactions.

---

## 8. Group Exchanges

Group exchanges allow **multi-participant** time credit exchanges with configurable splits.

### Split Types

| Type | How Hours Are Distributed |
|------|---------------------------|
| `equal` | `totalHours / count(role)` per participant within each role |
| `weighted` | `(weight / totalWeight) × totalHours` proportionally within each role |
| `custom` | Each participant has manually assigned hours |

### Lifecycle

```
Draft → Add Participants → Each Participant Confirms → Organizer Completes
```

1. **Organizer creates** exchange with title, description, total_hours, split_type
2. **Add participants** — each with role (`provider` or `receiver`) and optional hours/weight
3. **Safeguarding check** — blocks participants with `requires_broker_approval` flag
4. **Each participant confirms** their participation
5. **Organizer completes** — requires ALL participants confirmed
6. **Split calculation** runs, credits transferred per participant

### Completion

```
GroupExchangeService::complete(exchangeId) → {
    success: bool,
    transaction_ids: int[],
    error?: string
}
```

Inside `DB::transaction()`:
- Calculates split based on `split_type`
- For each participant with hours > 0:
  - Providers: credit (add hours)
  - Receivers: debit (deduct hours)
  - Creates wallet_transaction record
  - Updates `user.time_balance`
- Status → `completed`

---

## 9. Smart Matching Engine

The `SmartMatchingEngine` uses a **6-dimensional scoring pipeline** to match users with relevant listings.

### Scoring Formula

```
final_score = (
    category_score × 0.25 +
    skill_score    × 0.20 +
    proximity_score× 0.25 +
    freshness_score× 0.10 +
    reciprocity    × 0.15 +
    quality_score  × 0.05
) × historical_boost × cf_boost × embedding_boost
```

### Component Details

#### 1. Category Score (weight: 25%)

| Match Type | Score |
|------------|-------|
| Exact category match | 1.0 |
| Related category (via hierarchy) | 0.5 – 0.8 |
| No match | 0.0 |

#### 2. Skill Score (weight: 20%)

Uses **Jaccard similarity** on skill tags with **Porter stemming**:

```
jaccard = |intersection(A, B)| / |union(A, B)|
```

- Applies 5-rule Porter stemming to normalize keywords
- Removes stop words before comparison
- Semantic embedding boost (1.3×) when vector similarity is available
- Cold-start boost for new users with few interactions

#### 3. Proximity Score (weight: 25%)

**Haversine formula** for distance, then **piecewise linear decay**:

| Distance | Score |
|----------|-------|
| 0 – 5 km (walking) | 1.0 |
| 5 – 15 km (local) | 1.0 → 0.8 (linear) |
| 15 – 30 km (city) | 0.8 → 0.5 (linear) |
| 30 – 50 km (regional) | 0.5 → 0.3 (linear) |
| 50 – 100 km (distant) | 0.3 → 0.0 (linear) |
| 100+ km | 0.0 |

Respects user's `max_distance_km` preference.

#### 4. Freshness Score (weight: 10%)

**Exponential decay** with 14-day half-life:

| Age | Score |
|-----|-------|
| 0 – 24 hours | 1.0 (full credit) |
| 7 days | ~0.7 |
| 14 days | 0.5 (half-life) |
| 30 days | ~0.3 |
| Minimum | 0.3 (floor) |

#### 5. Reciprocity Score (weight: 15%)

| Scenario | Multiplier |
|----------|-----------|
| **Mutual** — both parties have complementary listings in same category | 1.5× |
| **One-way** — one party benefits from the other | 1.0× |
| **None** — no compatibility detected | 0.5× |

#### 6. Quality Score (weight: 5%)

| Factor | Bonus |
|--------|-------|
| Has image | 1.2× |
| Description ≥ 100 chars | 1.1× |
| Location verified | 1.1× |
| Owner has badges/verification | 1.2× |
| Range | 0.5 – 1.5 |

### Additional Boosts

| Boost | Multiplier | Source |
|-------|-----------|--------|
| KNN member recommendation | 1.4× | CollaborativeFilteringService |
| Semantic embedding match | 1.3× | EmbeddingService (OpenAI) |
| Similar listings (item-CF) | 1.15× | CollaborativeFilteringService |
| User-suggested (user-CF) | 1.10× | CollaborativeFilteringService |

### Key Methods

| Method | Returns |
|--------|---------|
| `findMatchesForUser(userId, limit, options)` | Scored match list |
| `getHotMatches(userId, limit)` | Matches with score ≥ 80 |
| `getMutualMatches(userId, limit)` | Reciprocal matches |

---

## 10. Collaborative Filtering & Recommendations

`CollaborativeFilteringService` implements both **item-item** and **user-user** collaborative filtering using cosine similarity on implicit feedback.

### Item-Based CF: Similar Listings
```
getSimilarListings(listingId, limit) → listingId[]
```

- Builds interaction matrix from `listing_favorites` table (users who saved X also saved Y)
- Computes **cosine similarity** between listing vectors
- Returns listings most similar to the input
- Cold-start fallback: popular recent listings

### User-User CF: Suggested Listings
```
getSuggestedListingsForUser(userId, limit) → listingId[]
```

- Finds similar users via shared exchange partners (minimum 2 common partners)
- Aggregates saves from top 20 similar users
- Score: `sum(user_similarity × save_weight)`
- Excludes already-saved listings
- Cold-start fallback: popular listings

### User-User CF: Suggested Members
```
getSuggestedMembers(userId, limit) → userId[]
```

- Builds interaction matrix from completed transactions (bidirectional edges)
- Cosine similarity between user vectors
- Cold-start fallback: recent active users

### Cosine Similarity Implementation

```
similarity(A, B) = dotProduct(A, B) / (||A|| × ||B||)
```

- Sparse vector implementation (handles missing components as zero)
- Returns float in [0, 1]
- Cache TTL: 1 hour
- MAX_TRAINING_ROWS: 5,000 (performance cap)

---

## 11. Match Learning System

`MatchLearningService` learns from user behavior to improve future match quality. Uses **30-day exponential decay** to weight recent interactions more heavily.

### Action Weights

| Action | Weight | Signal |
|--------|--------|--------|
| Accept | +5.0 | Strong positive |
| Contact | +3.0 | Positive |
| Save | +2.0 | Positive |
| View | +0.5 | Weak positive |
| Impression | 0.0 | Neutral |
| Decline | -2.0 | Negative |
| Dismiss | -4.0 | Strong negative |

### Time Decay

```
decay_factor = 0.5 ^ (days_since_interaction / 30)
```

A 30-day-old interaction has half the weight of today's interaction.

### Historical Boost

```
getHistoricalBoost(userId, listingId) → float (-15 to +15)
```

Components:
- **Owner interaction boost** (-10 to +10): Have you interacted with this listing owner's other listings?
- **Category affinity boost** (-5 to +5): Do you historically like/dislike this category?

### Category Affinities

```
getCategoryAffinities(userId) → { category_id: float (-1.0 to 1.0) }
```

- Aggregates weighted scores per category with time decay
- Normalized by max absolute value
- Positive = user likes this category, negative = dislikes

### Learned Distance Preference

```
getLearnedDistancePreference(userId) → {
    preferred_km: float,   // Median of positive interactions
    max_km: float,         // 90th percentile of positive interactions
    confidence: float,     // min(1.0, sample_size / 50)
    sample_size: int
}
```

- Requires minimum 3 interactions
- Defaults: preferred=25km, max=50km, confidence=0.0

---

## 12. Listing Ranking (MatchRank Algorithm)

`ListingRankingService` computes a **composite ranking score** for listing search results.

### Formula

```
score = relevance × freshness × engagement × proximity × quality × reciprocity
```

### Components

#### Freshness
- 0–7 days: full credit (1.0), linear decay
- 7–30 days: exponential decay to minimum (0.3)

#### Engagement (Bayesian Smoothing)
```
engagement = (w_views × views + w_contacts × contacts + w_saves × saves + 10 × 0.5) / (total + 10)
```
- Bayesian strength: 10.0, mean: 0.5
- Prevents new listings from being penalized

#### Proximity
- Full credit within 50km (Haversine)
- Linear decay: 0.003 per km beyond 50km
- Zero at 100km

#### Quality Bonuses
- Description ≥ 50 chars
- Has image
- Location filled
- Verified owner
- Up to 1.5× multiplier

#### Reciprocity
- Mutual boost: 2.0× (both parties benefit)
- Match boost: 1.5× (one-way)

#### Relevance
- Category match: 1.5×
- Meilisearch boost: 2.0×
- Skill matching via Jaccard similarity
- CF similar listings: 1.15×
- CF user-suggested: 1.10×

---

## 13. Community Fund

Each tenant has a single **Community Fund** — a shared pool of time credits managed by admins.

### Operations

| Operation | Actor | Method |
|-----------|-------|--------|
| **Deposit** | Admin | `CommunityFundService::adminDeposit(adminId, amount, description)` |
| **Withdraw** (grant to member) | Admin | `CommunityFundService::adminWithdraw(adminId, recipientId, amount, description)` |
| **Donate** | Any member | `CommunityFundService::receiveDonation(donorId, amount, message)` |

### Withdraw (Grant to Member)

Inside `DB::transaction()`:
1. Lock fund row
2. Check balance ≥ amount
3. Deduct from fund
4. Credit recipient user.balance
5. Record `community_fund_transactions` (type: `withdrawal`)
6. Record `transactions` (type: fund grant, `sender_id=null`)

### Donate to Fund

Inside `DB::transaction()`:
1. Lock fund row
2. Deduct from user.balance (with `WHERE balance >= amount` check)
3. Credit fund balance and `total_donated`
4. Record `community_fund_transactions` (type: `donation`)
5. Record `credit_donations` (recipient_type: `community_fund`)
6. Record `transactions` (receiver_id=null, donation to fund)

---

## 14. Organization Wallets

Organizations have their own credit wallets, separate from personal wallets.

### Operations

| Operation | Actor | Method |
|-----------|-------|--------|
| **View balance** | Any member | `OrgWalletService::getBalance(orgId, tenantId)` |
| **User → Org deposit** | Member | `OrgWalletService::depositToOrg(userId, orgId, amount)` |
| **Org → User transfer** (direct) | Admin/Owner | `OrgWalletService::directTransferFromOrg(orgId, recipientId, amount)` |
| **Org → User request** (approval workflow) | Member | `OrgWalletService::createTransferRequest(...)` |
| **Approve request** | Admin/Owner | `OrgWalletService::approveRequest(requestId, approverId)` |
| **Reject request** | Admin/Owner | `OrgWalletService::rejectRequest(requestId, adminId, reason)` |
| **Org → Org transfer** | Admin | `OrgWalletService::transfer(fromOrgId, toOrgId, amount, tenantId)` |

### Wallet Summary
```
OrgWalletService::getWalletSummary(orgId) → {
    balance: float,
    total_received: float,
    total_paid_out: float,
    transaction_count: int,
    pending_requests: int
}
```

---

## 15. Credit Donations

Separate from transfers — donations are voluntary gifts of time credits.

### Types

| Type | Flow |
|------|------|
| **To Member** | `CreditDonationService::donateToMember(userId, recipientId, amount, message)` |
| **To Community Fund** | `CreditDonationService::donateToCommunityFund(userId, amount, message)` |

### Donation Tracking

- Creates `credit_donations` record with donor, recipient, amount, message
- Links to `transactions` table via `transaction_id`
- Separate donation history: `getDonationHistory(userId, limit, offset)`
- Cumulative total: `getTotalDonated(tenantId, userId)`

---

## 16. Federation Credits

Federated communities can exchange time credits across organizational boundaries.

### Agreement Model

```
Tenant A ←──── Agreement ────► Tenant B
                │
                ├── exchange_rate: 1.0 (default parity)
                ├── max_monthly_credits: optional cap
                └── status: pending → active → suspended → terminated
```

### How It Works

1. **Admin creates agreement** between two tenants with exchange rate
2. **Other tenant approves** — agreement becomes `active`
3. **Credits transfer** using the agreed rate:
   - `converted_amount = amount × exchange_rate`
   - Recorded in `federation_credit_transfers`
4. **Net balance tracked** in `federation_credit_balances`
5. **Federated transactions** flagged with `is_federated=1` and `sender_tenant_id`/`receiver_tenant_id`

### Key Methods

| Method | Description |
|--------|-------------|
| `createAgreementStatic(from, to, rate)` | Create pending agreement |
| `approveAgreement(id, tenantId)` | Approve from either side |
| `updateAgreementStatus(id, status, tenantId)` | Suspend or terminate |
| `getAgreement(tenantA, tenantB)` | Bidirectional lookup |

---

## 17. Broker & Moderation System

### Exchange Broker Approval

Brokers (admins) can gate exchanges for safety:

**When broker approval is required:**
1. User has `requires_broker_approval` safeguarding flag
2. Tenant config has `require_broker_approval` enabled
3. Listing has `risk_level = 'high'` risk tag
4. Exchange hours exceed `max_hours_without_approval` (default 4)

**Broker actions:**
- `approveExchange(exchangeId, brokerId, notes, conditions)` → status: `accepted`
- `rejectExchange(exchangeId, brokerId, reason)` → status: `cancelled`

### Listing Moderation

When listing moderation is enabled:
- New listings enter `pending_review`
- `getReviewQueue(limit, type)` for admin panel
- `approve(listingId, notes)` → `active`
- `reject(listingId, reason)` → `rejected`

### Risk Tags

Admins can tag listings with risk levels:
- High-risk listings automatically require broker approval
- Risk tags stored in `listing_risk_tags` table
- `risk_acknowledged_at` tracks when user acknowledged the risk

### Admin Routes

| Route | Method | Description |
|-------|--------|-------------|
| `GET /v2/admin/broker/exchanges` | GET | List pending exchanges |
| `GET /v2/admin/broker/exchanges/{id}` | GET | Exchange detail |
| `POST /v2/admin/broker/exchanges/{id}/approve` | POST | Approve |
| `POST /v2/admin/broker/exchanges/{id}/reject` | POST | Reject |
| `POST /v2/admin/broker/risk-tags/{listingId}` | POST | Add risk tag |
| `DELETE /v2/admin/broker/risk-tags/{listingId}` | DELETE | Remove risk tag |
| `POST /v2/admin/wallet/grant` | POST | Admin credit grant |
| `POST /v2/admin/timebanking/adjust-balance` | POST | Adjust user balance |

---

## 18. Rating & Reputation

### Exchange Ratings

After exchange completion, both parties can rate each other:

```
ExchangeRatingService::submitRating(exchangeId, userId, rating, comment?) → {
    success: bool,
    error?: string
}
```

- Rating: 1–5 stars
- One rating per person per exchange (enforced by unique constraint)
- Role automatically determined (requester/provider)

### User Aggregate Rating

```
ExchangeRatingService::getUserRating(userId) → {
    average: float,  // 0.0 if none, rounded to 2 decimals
    count: int
}
```

### Match Approval Statistics

```
MatchApprovalWorkflowService::getStatistics(days=30) → {
    total, pending, approved, rejected,
    approval_rate: float,
    avg_review_hours: float,
    top_reviewers: [{user_id, name, review_count, approved_count, rejected_count}]
}
```

---

## 19. Gamification Integration

The timebanking engine integrates with the gamification system:

### XP Awards

| Activity | Trigger |
|----------|---------|
| Create listing | `ListingService::create()` |
| Complete exchange | `TransactionCompleted` event → `UpdateWalletBalance` listener |
| Daily login | `DailyRewardService::claim()` (base 5 XP + streak bonuses) |

### Daily Reward Streaks

| Milestone | Bonus XP |
|-----------|----------|
| Day 3 | 10 |
| Day 7 | 25 |
| Day 14 | 50 |
| Day 30 | 75 |
| Day 60 | 100 |
| Day 90 | 150 |

Daily rewards grant **XP only**, not time credits (separate systems).

---

## 20. Stripe (Monetary Donations)

Stripe handles **real money donations** — these are completely separate from time credits.

### Flow

1. Frontend creates payment intent → `StripeDonationService::createPaymentIntent(userId, tenantId, amount, currency)`
2. Stripe processes payment
3. Webhook `payment_intent.succeeded` → marks donation as `completed`
4. Webhook `payment_intent.payment_failed` → marks as `failed`
5. Admin can refund → `StripeDonationService::createRefund(donationId, adminId)`

### Key Points

- Minimum amount: 0.50 EUR
- Creates/updates `stripe_customer_id` on users table
- Monetary donations stored in `vol_donations` table (separate from `transactions`)
- Receipt available: `getDonationReceipt(donationId, userId)`
- **Real money NEVER converts to time credits** — they are parallel systems

---

## 21. API Reference

### Wallet Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/wallet/balance` | Get wallet balance and aggregates |
| GET | `/v2/wallet/transactions` | Transaction history (cursor-paginated) |
| GET | `/v2/wallet/transactions/{id}` | Single transaction detail |
| POST | `/v2/wallet/transfer` | Transfer credits to another user |
| DELETE | `/v2/wallet/transactions/{id}` | Soft-delete transaction from history |
| GET | `/v2/wallet/user-search` | Autocomplete search for transfer recipient |
| GET | `/v2/wallet/pending-count` | Count of pending transactions |
| GET | `/v2/wallet/statement` | Download CSV statement |
| GET | `/v2/wallet/categories` | List transaction categories |
| POST | `/v2/wallet/categories` | Create category (admin) |
| PUT | `/v2/wallet/categories/{id}` | Update category (admin) |
| DELETE | `/v2/wallet/categories/{id}` | Delete category (admin) |
| GET | `/v2/wallet/starting-balance` | Get starting balance config |
| PUT | `/v2/wallet/starting-balance` | Set starting balance (admin) |

### Community Fund Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/wallet/community-fund` | Fund balance and stats |
| GET | `/v2/wallet/community-fund/transactions` | Fund transaction history |
| POST | `/v2/wallet/community-fund/deposit` | Admin deposit to fund |
| POST | `/v2/wallet/community-fund/withdraw` | Admin withdraw to member |
| POST | `/v2/wallet/community-fund/donate` | Member donate to fund |

### Donation Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v2/wallet/donate` | Donate credits to member |
| GET | `/v2/wallet/donations` | Donation history |
| POST | `/v2/donations/payment-intent` | Create Stripe payment (monetary) |
| GET | `/v2/donations/{id}/receipt` | Get donation receipt |

### Exchange Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/exchanges/config` | Exchange workflow configuration |
| GET | `/v2/exchanges/check` | Check active exchange on listing |
| GET | `/v2/exchanges` | List user's exchanges |
| POST | `/v2/exchanges` | Create exchange request |
| GET | `/v2/exchanges/{id}` | Exchange detail |
| POST | `/v2/exchanges/{id}/accept` | Provider accepts |
| POST | `/v2/exchanges/{id}/decline` | Provider declines |
| POST | `/v2/exchanges/{id}/start` | Start work |
| POST | `/v2/exchanges/{id}/complete` | Mark ready for confirmation |
| POST | `/v2/exchanges/{id}/confirm` | Confirm hours |
| DELETE | `/v2/exchanges/{id}` | Cancel exchange |
| POST | `/v2/exchanges/{id}/rate` | Rate exchange |
| GET | `/v2/exchanges/{id}/ratings` | Get exchange ratings |
| GET | `/v2/wallet/users/{userId}/rating` | Get user's aggregate rating |

### Group Exchange Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/group-exchanges` | List group exchanges |
| POST | `/v2/group-exchanges` | Create group exchange |
| GET | `/v2/group-exchanges/{id}` | Detail with calculated split |
| PUT | `/v2/group-exchanges/{id}` | Update (organizer only) |
| DELETE | `/v2/group-exchanges/{id}` | Cancel (organizer only) |
| POST | `/v2/group-exchanges/{id}/participants` | Add participant |
| DELETE | `/v2/group-exchanges/{id}/participants/{userId}` | Remove participant |
| POST | `/v2/group-exchanges/{id}/confirm` | Confirm participation |
| POST | `/v2/group-exchanges/{id}/complete` | Complete exchange (organizer) |

### Listing Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/listings` | Browse/search listings (ranked) |
| POST | `/v2/listings` | Create listing |
| GET | `/v2/listings/{id}` | Listing detail |
| PUT | `/v2/listings/{id}` | Update listing |
| DELETE | `/v2/listings/{id}` | Delete listing |
| GET | `/v2/listings/nearby` | Geospatial search |
| GET | `/v2/listings/saved` | User's saved listings |
| GET | `/v2/listings/featured` | Featured listings |
| POST | `/v2/listings/{id}/save` | Save to favorites |
| DELETE | `/v2/listings/{id}/save` | Remove from favorites |
| POST | `/v2/listings/{id}/image` | Upload image |
| DELETE | `/v2/listings/{id}/image` | Remove image |
| POST | `/v2/listings/{id}/renew` | Renew listing |
| GET | `/v2/listings/{id}/analytics` | View analytics |
| PUT | `/v2/listings/{id}/tags` | Set skill tags |
| GET | `/v2/listings/tags/popular` | Trending tags |
| GET | `/v2/listings/tags/autocomplete` | Tag search |

### Matching Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/matches/all` | Cross-module smart matches |
| POST | `/v2/matches/{id}/dismiss` | Dismiss match (negative signal) |

### Organization Wallet Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/organizations/{id}/wallet/balance` | Org wallet balance |
| GET | `/v2/organizations/{id}/wallet/transactions` | Org transaction history |
| POST | `/v2/organizations/wallet/transfer` | Org-to-org transfer |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/broker/exchanges` | Pending broker approvals |
| POST | `/v2/admin/broker/exchanges/{id}/approve` | Approve exchange |
| POST | `/v2/admin/broker/exchanges/{id}/reject` | Reject exchange |
| POST | `/v2/admin/wallet/grant` | Admin credit grant |
| POST | `/v2/admin/timebanking/adjust-balance` | Adjust balance |
| GET | `/v2/admin/timebanking/org-wallets` | All org wallets |
| GET | `/v2/admin/listings/moderation-queue` | Listing moderation |
| POST | `/v2/admin/listings/{id}/approve` | Approve listing |

### Federation Credit Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/federation/credit-agreements` | List agreements |
| POST | `/v2/admin/federation/credit-agreements` | Create agreement |
| POST | `/v2/admin/federation/credit-agreements/{id}/{action}` | Approve/suspend/terminate |
| GET | `/v2/admin/federation/credit-agreements/{id}/transactions` | Agreement transfers |
| GET | `/v2/admin/federation/credit-balances` | Net balances |

---

## 22. Frontend User Experience

### Pages

| Page | Path | Purpose |
|------|------|---------|
| WalletPage | `/wallet` | Balance, transactions, transfer button |
| TransferModal | (modal overlay) | Send credits with user search |
| ListingsPage | `/listings` | Browse/search marketplace |
| ListingDetailPage | `/listings/:id` | View listing, request exchange |
| CreateListingPage | `/listings/create` | Create offer or request |
| ExchangesPage | `/exchanges` | List exchanges with tab filters |
| ExchangeDetailPage | `/exchanges/:id` | Full workflow management |
| RequestExchangePage | `/exchanges/request/:listingId` | Submit exchange request |
| GroupExchangesPage | `/group-exchanges` | Multi-participant exchanges |
| CreateGroupExchangePage | `/group-exchanges/create` | Create group exchange |
| GroupExchangeDetailPage | `/group-exchanges/:id` | Manage group exchange |
| MatchesPage | `/matches` | Smart match suggestions |

### Wallet Dashboard

The WalletPage displays:
- **Balance card** — current time credit balance prominently displayed
- **Action buttons** — "Send Credits" (opens TransferModal) and "Donate"
- **Stats cards** — total earned, total spent, pending hours
- **Transaction history** — filterable by type (all, earned, spent, pending)
- **Cursor-based pagination** with "Load More"
- **CSV export** button for statement download

**Deep-linking:** `?to=userId` auto-opens TransferModal with pre-filled recipient.

### Transfer Flow

1. User clicks "Send Credits"
2. TransferModal opens with:
   - **Recipient search** — debounced autocomplete (name, username, email)
   - **Amount input** — validated against balance (max 1,000)
   - **Category dropdown** — optional transaction category
   - **Description** — optional message
   - **Available balance** shown for reference
3. Confirmation summary before submission
4. POST to `/v2/wallet/transfer`

### Listings Browse

- **View modes**: Grid, List, Map (Google Maps)
- **Filters**: Search query, type (offer/request), category, location radius (5-100km)
- **Cursor-based pagination** with "Load More"
- **Listing card**: Image, type badge, category, hours estimate, location, author, save button
- **URL state persistence**: query params for shareable filtered views

### Exchange Workflow UI

**ExchangeDetailPage** presents:
- **Status card** with color-coded current state
- **Exchange details**: listing, both parties, proposed hours, prep time
- **Animated timeline** from status_history array
- **Role-based action buttons**:
  - Provider: Accept, Decline, Start, Complete
  - Requester: Confirm hours, Cancel
- **Modal dialogs**: Decline reason, Confirm hours, Rating submission
- **Ratings section** shown for completed exchanges

### Complete User Journey (UI)

```
1. BROWSE    →  ListingsPage (search, filter, browse)
2. VIEW      →  ListingDetailPage (see details, check exchange status)
3. REQUEST   →  RequestExchangePage (propose hours, add message)
4. WORKFLOW  →  ExchangeDetailPage (accept → start → complete → confirm)
5. TRANSFER  →  Credits move automatically on completion
6. RATE      →  ExchangeDetailPage (1-5 stars after completion)
7. WALLET    →  WalletPage (view updated balance and transaction)
```

---

## 23. Concurrency & Safety

### Pessimistic Locking Strategy

All credit-modifying operations use pessimistic locks to prevent race conditions:

| Operation | Lock Target | Lock Order |
|-----------|-------------|------------|
| Personal transfer | Both user rows | Lower ID first (deadlock prevention) |
| Exchange completion | Both user rows + exchange row | Exchange first, then users by ID |
| Community fund operation | Fund account row | Single lock |
| Org wallet transfer | Org wallet row(s) | Single/double lock |

### Balance Verification

All deductions use atomic `WHERE balance >= amount` checks:

```sql
UPDATE users SET balance = balance - :amount
WHERE id = :userId AND balance >= :amount AND tenant_id = :tenantId
```

If the row count is 0, the operation fails (insufficient balance) without any partial state.

### Transaction Atomicity

All multi-step operations use `DB::transaction()`:
1. Begin transaction
2. Acquire locks (`lockForUpdate()`)
3. Verify preconditions
4. Perform all modifications
5. Commit (or rollback on any error)
6. Send notifications AFTER commit (never inside transaction)

### Idempotency Guards

| Operation | Guard |
|-----------|-------|
| Starting balance | Checks existing `starting_balance` transaction before granting |
| Daily reward | Unique constraint `(tenant_id, user_id, reward_date)` |
| Exchange rating | Unique constraint `(exchange_id, rater_id)` |
| Balance alerts | One alert per org per type per day |

### Per-User Soft Deletes

Transactions use `deleted_for_sender` / `deleted_for_receiver` flags instead of Laravel's soft delete. This allows each party to independently hide a transaction from their history while keeping it in the system for auditing.

---

## 24. Complete User Journey

### End-to-End: Service Exchange

```
Alice (gardener) wants to trade with Bob (tutor).

1. LISTING CREATION
   Bob creates listing: "Maths tutoring - 1 hour sessions"
   → POST /v2/listings { type: "offer", hours_estimate: 1.0, category: "Education" }
   → Bob receives XP for creating listing
   → Listing enters moderation queue (if enabled) or goes active

2. DISCOVERY
   Alice searches listings for "tutoring"
   → GET /v2/listings?q=tutoring&type=offer
   → SmartMatchingEngine ranks results:
     Category: Education (1.0) × Skill: tutoring/teaching (0.85) × Proximity: 3km (1.0)
     × Freshness: 2 days (0.95) × Reciprocity: Alice has gardening offer (1.5)
     × Quality: has image + description (1.3)
   → Bob's listing ranks highly

3. EXCHANGE REQUEST
   Alice requests exchange on Bob's listing
   → POST /v2/exchanges { listing_id: 42, proposed_hours: 1.5, message: "..." }
   → Status: pending_provider
   → Bob notified

4. PROVIDER ACCEPTS
   Bob reviews and accepts
   → POST /v2/exchanges/15/accept
   → Broker check: no safeguarding flags, hours < 4 = auto-approved
   → Status: accepted
   → Alice notified

5. WORK STARTS
   Bob starts tutoring session
   → POST /v2/exchanges/15/start
   → Status: in_progress
   → Alice notified

6. WORK COMPLETE
   Bob marks work as done
   → POST /v2/exchanges/15/complete
   → Status: pending_confirmation

7. DUAL CONFIRMATION
   Alice confirms: "1.5 hours"
   → POST /v2/exchanges/15/confirm { hours: 1.5 }
   Bob confirms: "1.5 hours"
   → POST /v2/exchanges/15/confirm { hours: 1.5 }
   → Hours match (diff < 0.01) → auto-complete

8. CREDIT TRANSFER
   Inside DB::transaction():
   → Alice.balance -= 1.5 (requester pays)
   → Bob.balance += 1.5 (provider earns)
   → Transaction record created
   → Status: completed
   → Both notified: "Exchange completed! Credits transferred."

9. RATINGS
   Alice rates Bob: 5 stars "Great tutor!"
   → POST /v2/exchanges/15/rate { rating: 5, comment: "..." }
   Bob rates Alice: 5 stars "Punctual and pleasant"
   → Ratings stored, aggregates updated

10. WALLET VIEW
    Alice checks wallet: balance decreased by 1.5
    → GET /v2/wallet/balance → { balance: 8.5, total_spent: 1.5, ... }
    Bob checks wallet: balance increased by 1.5
    → GET /v2/wallet/balance → { balance: 11.5, total_earned: 1.5, ... }

11. LEARNING
    MatchLearningService records:
    → Alice accepted tutor exchange (action weight: +5.0)
    → Category "Education" affinity increases for Alice
    → Future matches in Education will rank higher for Alice
    → CollaborativeFilteringService adds Alice↔Bob edge
    → Future user recommendations factor in this connection
```

---

*This report documents the complete timebanking engine as implemented in Project NEXUS v1.5.0. For the most current implementation details, refer to the source code files listed in Section 3.*
