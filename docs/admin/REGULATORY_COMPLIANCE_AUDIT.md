# Project NEXUS - Regulatory Compliance Audit

**Date:** 2026-02-14
**Scope:** TimeBanking UK and Irish partnership regulatory compliance
**Auditor:** Automated code and database analysis

---

## Executive Summary

| Feature Area | Status | Score |
|---|---|---|
| Safeguarding / DBS Checks | **PARTIAL** | 40% |
| Broker Message Monitoring | **EXISTS** | 95% |
| GDPR Compliance Tools | **EXISTS** (some tables missing) | 75% |
| Insurance Documentation | **PARTIAL** | 35% |
| Risk Assessment | **EXISTS** | 90% |

---

## 1. Safeguarding / DBS Checks

**Status: PARTIAL**

### What Exists

#### Listing-Level DBS Flags
- **Database:** `listing_risk_tags.dbs_required` column (TINYINT(1), DEFAULT 0) -- EXISTS
- **Migration:** `migrations/2026_02_08_broker_control_features.sql` line 27 defines the column
- **PHP Backend:** `src/Controllers/Admin/BrokerControlsController.php` lines 486, 502, 512, 522, 533 -- risk tag form reads/writes `dbs_required` field
- **React Admin UI:** `react-frontend/src/admin/modules/broker/RiskTags.tsx` lines 119-125 -- displays DBS Required column with Yes/No chip
- **TypeScript Types:** `react-frontend/src/admin/api/types.ts` line 585 -- `dbs_required: boolean` in RiskTag type
- **Seed Data:** `scripts/seed-test-data.php` line 330 -- seeds test risk tags with dbs_required values

#### How It Works
When a broker/admin tags a listing with a risk assessment, they can mark it as requiring DBS/background check. This flag is visible in the Risk Tags admin table. The flag is per-listing, not per-user.

### What Is MISSING

#### User-Level DBS/Vetting Status
- **No `dbs_status` or `dbs_check_date` columns on the `users` table** -- the `DESCRIBE users` output shows no DBS-related columns
- **No DBS fields in the UserEdit admin form** -- `react-frontend/src/admin/modules/users/UserEdit.tsx` contains no DBS/safeguarding fields (grep returns no matches)
- **No Garda Vetting (Irish equivalent)** -- no `garda_vetting` references anywhere in the codebase
- **No DBS certificate upload mechanism** -- no document upload tied to DBS/vetting status
- **No DBS expiry tracking** -- DBS checks expire; there is no date field or reminder system

#### Specific Gaps for UK/Irish Compliance
1. **Users table needs:** `dbs_status` (enum: none, applied, cleared, expired), `dbs_check_date`, `dbs_certificate_number`, `dbs_expiry_date`, `garda_vetting_status`, `garda_vetting_date`
2. **Admin UserEdit page needs:** DBS/Vetting section with status dropdown, date fields, certificate number, document upload
3. **Automated expiry alerts:** DBS Enhanced checks are valid for 3 years; system needs to flag expiring checks
4. **Listing-to-user enforcement:** When a listing has `dbs_required=1`, the system should verify the provider actually has a valid DBS check before allowing an exchange
5. **Safeguarding policy page:** No dedicated safeguarding policy or training tracking

### Evidence

```
users table columns: (NO DBS columns found)
  - id, first_name, last_name, tenant_id, name, email, username, password_hash,
    totp_enabled, role, profile_type, status, balance, bio, location, phone,
    avatar_url, created_at, last_login_at, xp, level, ...
    (Full schema: 80+ columns, ZERO related to DBS/safeguarding/vetting)

listing_risk_tags table:
  - dbs_required TINYINT(1) DEFAULT 0     -- EXISTS
  - insurance_required TINYINT(1) DEFAULT 0 -- EXISTS
```

---

## 2. Broker Message Monitoring

**Status: EXISTS -- Full Pipeline Working**

### Database Evidence

**`broker_message_copies` table -- EXISTS**

| Column | Type | Purpose |
|---|---|---|
| id | int(11) PK | Auto-increment ID |
| tenant_id | int(11) | Multi-tenant scoping |
| original_message_id | int(11) | Reference to original message |
| conversation_key | varchar(100) | Conversation identifier |
| sender_id | int(11) | Message sender |
| receiver_id | int(11) | Message receiver |
| message_body | text | Copied message content |
| sent_at | timestamp | When original was sent |
| copy_reason | enum | first_contact, high_risk_listing, new_member, flagged_user, monitoring |
| reviewed_by | int(11) | Broker who reviewed |
| reviewed_at | timestamp | When reviewed |
| flagged | tinyint(1) | Flagged for concern |
| action_taken | varchar(100) | Broker action taken |

**Supporting tables -- ALL EXIST:**
- `user_first_contacts` -- tracks first contact between user pairs
- `user_messaging_restrictions` -- per-user monitoring/disable flags

### Code Evidence -- Full Pipeline

#### 1. Message Send triggers copy check
**File:** `src/Services/MessageService.php` lines 608-618
```php
// Broker visibility: Copy message for broker review if needed
$copyReason = BrokerMessageVisibilityService::shouldCopyMessage($senderId, $receiverId, $listingId);
if ($copyReason) {
    BrokerMessageVisibilityService::copyMessageForBroker($messageId, $copyReason);
}
```

#### 2. Copy decision logic (5 criteria)
**File:** `src/Services/BrokerMessageVisibilityService.php` lines 38-79
- Flagged/monitored users (highest priority)
- First contact between members
- New member messages (configurable days threshold)
- High-risk listing related messages
- Random sampling (configurable percentage)

#### 3. Messaging restrictions enforcement
**File:** `src/Services/BrokerMessageVisibilityService.php` lines 507-524
- `isMessagingDisabledForUser()` blocks sending AND receiving for restricted users
- Returns user-friendly error messages (SENDER_RESTRICTED, RECIPIENT_UNAVAILABLE)

#### 4. Broker review API
**File:** `src/Controllers/Api/AdminBrokerApiController.php`
- `GET /api/v2/admin/broker/messages` -- lists copies with filter (unreviewed/flagged/all)
- `POST /api/v2/admin/broker/messages/{id}/review` -- marks as reviewed
- `GET /api/v2/admin/broker/monitoring` -- lists monitored users
- `GET /api/v2/admin/broker/dashboard` -- aggregate stats

#### 5. React Admin UI
**File:** `react-frontend/src/admin/modules/broker/MessageReview.tsx`
- DataTable with unreviewed/flagged/all tabs
- Shows sender, receiver, listing, flag status, review status, date
- "Review" button marks message as reviewed
- Fully functional with pagination

**File:** `react-frontend/src/admin/modules/broker/BrokerDashboard.tsx`
- StatCards: Pending Exchanges, Unreviewed Messages, High Risk Listings, Monitored Users
- Quick links to all sub-pages

#### 6. Configuration
**File:** `src/Services/BrokerControlConfigService.php`
- Per-tenant configuration stored in `tenants.configuration` JSON
- Broker visibility: enable/disable, copy criteria toggles
- Retention policy (default 365 days)
- Random sampling percentage
- New member monitoring days (default 30)

#### 7. Tests
**File:** `tests/Services/BrokerMessageVisibilityServiceTest.php`
- 10+ test methods covering: shouldCopyMessage, copyMessageForBroker, getUnreviewedMessages, countUnreviewed, markAsReviewed, flagMessage, getMessages, isFirstContact, recordFirstContact, getStatistics

### Minor Gaps
- No message content preview in React UI (shows sender/receiver but body is available in API)
- No bulk review action (review one at a time)
- `action_taken` field in DB but no UI to record what action was taken after flagging

---

## 3. GDPR Compliance Tools

**Status: EXISTS (some database tables missing)**

### Database Evidence

| Table | Status | Notes |
|---|---|---|
| `gdpr_requests` | **EXISTS** | 2 records in DB, full schema with types/status/priority |
| `gdpr_audit_log` | **EXISTS** | Full audit schema with user, admin, action, entity tracking |
| `gdpr_consents` | **MISSING** | Table does not exist (code references `user_consents` instead) |
| `gdpr_breaches` | **MISSING** | Table does not exist (code uses `data_breach_log` instead) |

#### gdpr_requests schema (EXISTS):
```
request_type: ENUM(access, erasure, rectification, restriction, portability, objection)
status: ENUM(pending, processing, completed, rejected, cancelled)
priority: ENUM(normal, high, urgent)
verification_token, export_file_path, export_expires_at, notes, metadata
```

#### Users table GDPR columns (EXIST):
- `anonymized_at` datetime -- set when account is anonymized
- `gdpr_export_requested_at` datetime -- data export request timestamp
- `gdpr_deletion_requested_at` datetime -- deletion request timestamp
- `deleted_at` datetime -- soft delete timestamp

### Code Evidence

#### GdprService (Comprehensive)
**File:** `src/Services/Enterprise/GdprService.php` (1343 lines)
- **Data Subject Requests:** Create, get, process, complete requests
- **Data Export (Article 15):** Full data export (JSON + HTML + README + uploads in ZIP)
  - Collects: profile, listings, messages, transactions, events, groups, volunteering, gamification, activity log, consents, notifications, connections, login history
- **Account Deletion (Article 17):** Full erasure with anonymization
  - Anonymizes user record, deletes messages/notifications/consents/sessions
  - Soft-deletes listings, anonymizes activity logs, deletes uploads
  - Generates final export before deletion for legal retention
- **Consent Management:** Record, withdraw, check, version tracking
  - Tenant-specific consent version overrides
  - Re-consent detection for outdated versions
  - Backfill for existing users
- **Data Breach Management:** Report breaches, 72-hour deadline tracking
- **Audit Logging:** All GDPR actions logged with IP, user agent, request ID
- **Statistics:** Request counts, processing time, consent stats, overdue tracking

#### React Admin UI (5 pages)

| Page | File | Status |
|---|---|---|
| GDPR Dashboard | `react-frontend/src/admin/modules/enterprise/GdprDashboard.tsx` | FUNCTIONAL |
| Data Requests | `react-frontend/src/admin/modules/enterprise/GdprRequests.tsx` | FUNCTIONAL |
| Consent Records | `react-frontend/src/admin/modules/enterprise/GdprConsents.tsx` | UI exists, backend table mismatch |
| Data Breaches | `react-frontend/src/admin/modules/enterprise/GdprBreaches.tsx` | UI exists, backend table mismatch |
| Audit Log | `react-frontend/src/admin/modules/enterprise/GdprAuditLog.tsx` | FUNCTIONAL |

#### API Endpoints
**File:** `src/Controllers/Api/AdminEnterpriseApiController.php`
- `GET /api/v2/admin/enterprise/gdpr/dashboard` -- stats (pending, total, consents, breaches)
- `GET /api/v2/admin/enterprise/gdpr/requests` -- paginated request list
- `PUT /api/v2/admin/enterprise/gdpr/requests/{id}` -- update request status
- `GET /api/v2/admin/enterprise/gdpr/consents` -- consent records (queries `gdpr_consents` which does NOT exist)
- `GET /api/v2/admin/enterprise/gdpr/breaches` -- breach records (queries `gdpr_breaches` which does NOT exist)
- `GET /api/v2/admin/enterprise/gdpr/audit` -- audit log from `activity_log` table

### Gaps

1. **Table name mismatch:** AdminEnterpriseApiController queries `gdpr_consents` but GdprService uses `user_consents`. The `gdpr_consents` table does not exist. The Consents page will return empty data.
2. **Table name mismatch:** AdminEnterpriseApiController queries `gdpr_breaches` but GdprService uses `data_breach_log`. The `gdpr_breaches` table does not exist. The Breaches page will return empty data.
3. **No user-facing GDPR request form:** Users cannot self-service request data export or deletion from the React frontend. Only admin-initiated.
4. **No consent management in user settings:** No React page for users to view/manage their consents.
5. **No automated 30-day SLA alerts:** GDPR requires response within 30 days; GdprService tracks overdue but no automated alerts.

### Recommended Fixes
- Create `gdpr_consents` as a view or alias of `user_consents`, OR update AdminEnterpriseApiController to query `user_consents`
- Create `gdpr_breaches` as a view or alias of `data_breach_log`, OR update AdminEnterpriseApiController to query `data_breach_log`
- Add user-facing "My Data" section in Settings page

---

## 4. Insurance Documentation

**Status: PARTIAL**

### What Exists

#### Listing-Level Insurance Flag
- **Database:** `listing_risk_tags.insurance_required` column (TINYINT(1), DEFAULT 0) -- EXISTS
- **PHP Backend:** `src/Controllers/Admin/BrokerControlsController.php` -- risk tag form reads/writes `insurance_required`
- **React Admin UI:** `react-frontend/src/admin/modules/broker/RiskTags.tsx` lines 110-116 -- displays Insurance column with Yes/No chip
- **TypeScript Types:** `react-frontend/src/admin/api/types.ts` -- `insurance_required: boolean` in RiskTag type

#### How It Works
Brokers can flag listings that require insurance by marking `insurance_required=1` in the risk tag. This is displayed in the Risk Tags admin table.

### What Is MISSING

1. **No insurance certificate upload:** No mechanism for users to upload insurance certificates
2. **No insurance verification workflow:** No admin approval flow for submitted certificates
3. **No insurance expiry tracking:** No date fields for policy start/end dates
4. **No insurance types/categories:** No distinction between public liability, professional indemnity, etc.
5. **No user-level insurance status:** Only per-listing flags exist; no fields on `users` table
6. **No enforcement:** When `insurance_required=1` on a listing, there is no check that the provider actually has valid insurance before allowing exchange
7. **No document storage:** No `insurance_certificates` or similar table

### What Needs to Be Added
```sql
-- Suggested: insurance_certificates table
CREATE TABLE insurance_certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    insurance_type ENUM('public_liability', 'professional_indemnity', 'other'),
    provider_name VARCHAR(255),
    policy_number VARCHAR(100),
    coverage_amount DECIMAL(12,2),
    start_date DATE,
    expiry_date DATE,
    certificate_file_path VARCHAR(500),
    status ENUM('pending', 'verified', 'expired', 'rejected'),
    verified_by INT,
    verified_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 5. Risk Assessment

**Status: EXISTS -- Fully Functional**

### Database Evidence

**`listing_risk_tags` table -- EXISTS**

| Column | Type | Purpose |
|---|---|---|
| id | int(11) PK | Auto-increment |
| tenant_id | int(11) | Multi-tenant scoping |
| listing_id | int(11) UNIQUE | One tag per listing |
| risk_level | enum(low, medium, high, critical) | Risk severity |
| risk_category | varchar(100) | Category (safeguarding, financial, health_safety, legal, reputation, fraud, other) |
| risk_notes | text | Broker notes (internal) |
| member_visible_notes | text | Notes visible to member |
| requires_approval | tinyint(1) | Force broker approval for exchanges |
| insurance_required | tinyint(1) | Flag listing as needing insurance |
| dbs_required | tinyint(1) | Flag listing as needing DBS check |
| tagged_by | int(11) | Broker who tagged |
| created_at / updated_at | timestamp | Timestamps |

### Code Evidence

#### ListingRiskTagService (Full Implementation)
**File:** `src/Services/ListingRiskTagService.php` (395 lines)
- `tagListing()` -- Create/update risk tags with validation
- `getTagForListing()` -- Get tag for a specific listing
- `removeTag()` -- Remove risk tag with audit logging
- `getTaggedListings()` -- Paginated list with filters
- `getHighRiskListings()` -- High/critical risk listings
- `requiresApproval()` -- Check if broker approval needed
- `isHighRisk()` -- Quick high/critical check
- `getStatistics()` -- Counts by risk level
- Auto-notifies admins on high/critical tagging via `NotificationDispatcher`
- Full audit trail via `AuditLogService`

#### Risk Tag Categories
```php
const CATEGORIES = [
    'safeguarding' => 'Safeguarding Concern',
    'financial' => 'Financial Risk',
    'health_safety' => 'Health & Safety',
    'legal' => 'Legal/Regulatory',
    'reputation' => 'Reputational Risk',
    'fraud' => 'Potential Fraud',
    'other' => 'Other',
];
```

#### Exchange Integration
- **File:** `src/Services/ExchangeWorkflowService.php` -- references risk tags for exchange approval
- **File:** `exchange_requests.risk_tag_id` column -- links exchanges to their risk tag
- **File:** `exchange_requests.risk_acknowledged_at` -- tracks when risk was acknowledged
- High-risk listings auto-require broker approval when `BrokerControlConfigService::doesHighRiskRequireApproval()` returns true

#### Message Integration
- **File:** `src/Services/BrokerMessageVisibilityService.php` line 66-69 -- messages about high-risk listings are automatically copied to broker review queue

#### React Admin UI
**File:** `react-frontend/src/admin/modules/broker/RiskTags.tsx`
- DataTable with risk level tabs (All, Critical, High, Medium, Low)
- Columns: Listing, Owner, Risk Level (color-coded chips), Category, Approval Required, Insurance Required, DBS Required, Date
- Fully functional with API integration

#### Legacy PHP Admin
**Files:**
- `views/modern/admin/broker-controls/risk-tags/form.php` -- create/edit risk tags
- `views/modern/admin/broker-controls/index.php` -- risk tags list
- `views/civicone/admin/broker-controls/risk-tags/form.php` -- CivicOne theme

#### Tests
**File:** `tests/Services/ListingRiskTagServiceTest.php`

### Minor Gaps
- Risk tags are **manual only** -- no automated risk scoring based on listing content, category, or user history
- No bulk risk tag operations
- No risk tag history/changelog (only current state + audit log)

---

## Summary of Required Actions

### Priority 1 (High -- Regulatory Requirement)

| Action | Effort | Area |
|---|---|---|
| Add DBS/vetting columns to `users` table | Medium | Safeguarding |
| Add DBS fields to admin UserEdit page | Medium | Safeguarding |
| Add Garda Vetting fields (Irish compliance) | Medium | Safeguarding |
| Fix GDPR table name mismatches (consents, breaches) | Low | GDPR |
| Add DBS/insurance enforcement on exchanges | Medium | Safeguarding |

### Priority 2 (Medium -- Operational Improvement)

| Action | Effort | Area |
|---|---|---|
| Create insurance certificates table and upload flow | High | Insurance |
| Add DBS expiry tracking and automated alerts | Medium | Safeguarding |
| Add user-facing GDPR data request form | Medium | GDPR |
| Create `gdpr_consents` / `gdpr_breaches` tables or fix queries | Low | GDPR |
| Add automated risk scoring for listings | High | Risk |

### Priority 3 (Low -- Enhancement)

| Action | Effort | Area |
|---|---|---|
| Bulk message review actions | Low | Monitoring |
| Risk tag changelog/history | Low | Risk |
| Insurance verification workflow UI | Medium | Insurance |
| Consent management in user settings | Medium | GDPR |
| GDPR 30-day SLA automated alerts | Medium | GDPR |

---

## Files Referenced in This Audit

### PHP Backend
| File | Purpose |
|---|---|
| `src/Services/BrokerMessageVisibilityService.php` | Message copy/monitoring logic |
| `src/Services/BrokerControlConfigService.php` | Broker feature configuration |
| `src/Services/ListingRiskTagService.php` | Risk tag CRUD and enforcement |
| `src/Services/Enterprise/GdprService.php` | GDPR requests, export, deletion, consent |
| `src/Services/MessageService.php` | Message send with broker copy integration |
| `src/Controllers/Api/AdminBrokerApiController.php` | Broker admin API endpoints |
| `src/Controllers/Api/AdminEnterpriseApiController.php` | Enterprise/GDPR admin API endpoints |
| `src/Controllers/Admin/BrokerControlsController.php` | Legacy PHP broker admin |
| `tests/Services/BrokerMessageVisibilityServiceTest.php` | Broker monitoring tests |
| `tests/Services/ListingRiskTagServiceTest.php` | Risk tag tests |

### React Frontend
| File | Purpose |
|---|---|
| `react-frontend/src/admin/modules/broker/BrokerDashboard.tsx` | Broker controls dashboard |
| `react-frontend/src/admin/modules/broker/MessageReview.tsx` | Message review table |
| `react-frontend/src/admin/modules/broker/RiskTags.tsx` | Risk tags table |
| `react-frontend/src/admin/modules/broker/UserMonitoring.tsx` | User monitoring |
| `react-frontend/src/admin/modules/broker/ExchangeManagement.tsx` | Exchange approvals |
| `react-frontend/src/admin/modules/enterprise/GdprDashboard.tsx` | GDPR overview |
| `react-frontend/src/admin/modules/enterprise/GdprRequests.tsx` | Data requests table |
| `react-frontend/src/admin/modules/enterprise/GdprConsents.tsx` | Consent records |
| `react-frontend/src/admin/modules/enterprise/GdprBreaches.tsx` | Breach tracking |
| `react-frontend/src/admin/modules/enterprise/GdprAuditLog.tsx` | GDPR audit log |
| `react-frontend/src/admin/modules/users/UserEdit.tsx` | User edit (NO DBS fields) |

### Database Tables
| Table | Status |
|---|---|
| `broker_message_copies` | EXISTS |
| `user_first_contacts` | EXISTS |
| `user_messaging_restrictions` | EXISTS |
| `listing_risk_tags` | EXISTS |
| `exchange_requests` | EXISTS |
| `gdpr_requests` | EXISTS (2 records) |
| `gdpr_audit_log` | EXISTS |
| `gdpr_consents` | **DOES NOT EXIST** (code queries it but real table is `user_consents`) |
| `gdpr_breaches` | **DOES NOT EXIST** (code queries it but real table is `data_breach_log`) |

### Migrations
| File | Purpose |
|---|---|
| `migrations/2026_02_08_broker_control_features.sql` | Broker tables + risk tag columns |
