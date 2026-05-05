# Project NEXUS — Volunteering Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [Opportunities & Shifts](#3-opportunities--shifts)
4. [Applications & Approvals](#4-applications--approvals)
5. [Hours Logging & Verification](#5-hours-logging--verification)
6. [QR Check-In System](#6-qr-check-in-system)
7. [Volunteer Matching](#7-volunteer-matching)
8. [Shift Scheduling](#8-shift-scheduling)
9. [Waitlists](#9-waitlists)
10. [Shift Swaps](#10-shift-swaps)
11. [Group Reservations](#11-group-reservations)
12. [Organizations](#12-organizations)
13. [Wellbeing & Burnout Detection](#13-wellbeing--burnout-detection)
14. [Emergency Alerts](#14-emergency-alerts)
15. [Expense Management](#15-expense-management)
16. [Certificates](#16-certificates)
17. [Credentials & Training](#17-credentials--training)
18. [Safeguarding System](#18-safeguarding-system)
19. [Guardian Consent](#19-guardian-consent)
20. [Donations & Giving Days](#20-donations--giving-days)
21. [Community Projects](#21-community-projects)
22. [Custom Fields & Accessibility](#22-custom-fields--accessibility)
23. [Reminders & Webhooks](#23-reminders--webhooks)
24. [Reviews](#24-reviews)
25. [Database Schema](#25-database-schema)
26. [API Reference](#26-api-reference)
27. [Frontend Experience](#27-frontend-experience)
28. [Admin Panel](#28-admin-panel)

---

## 1. Executive Summary

The Project NEXUS Volunteering Engine is a comprehensive volunteer management system embedded within a multi-tenant timebanking platform. It goes far beyond simple sign-up tracking — it provides a full volunteer lifecycle from opportunity discovery through impact certification, with safeguarding, wellbeing, expenses, and emergency coordination built in.

### What It Includes

| System | Purpose |
|--------|---------|
| **Opportunities & Shifts** | Create, browse, and manage volunteer opportunities with time-slotted shifts |
| **Applications** | Apply, approve/decline, with org admin workflow |
| **Hours Logging** | Log, verify, and export volunteer hours |
| **QR Check-In** | Token-based attendance tracking with scan verification |
| **Smart Matching** | Skill-based volunteer-opportunity matching (0-100 scoring) |
| **Recurring Shifts** | Configurable recurring patterns (daily/weekly/monthly) |
| **Waitlists** | Position-tracked waitlists for full shifts |
| **Shift Swaps** | Peer-to-peer swap requests with optional admin approval |
| **Group Reservations** | Team/group bulk shift booking |
| **Organizations** | Volunteer org registration, approval, and management |
| **Wellbeing** | 5-indicator burnout detection with risk scoring (0-100) |
| **Emergency Alerts** | Priority-based urgent shift-fill notifications |
| **Expenses** | Claim submission, policy enforcement, approval, payment tracking |
| **Certificates** | Impact certificates with public verification codes |
| **Credentials** | Document upload with admin verification workflow |
| **Training** | Safeguarding training records with expiry tracking |
| **Safeguarding** | Incident reporting, DLP assignment, flagged messages |
| **Guardian Consent** | Token-based parental consent for minor volunteers |
| **Donations** | Monetary donations with Stripe, giving day campaigns |
| **Community Projects** | Member-proposed volunteer project ideas with voting |
| **Custom Fields** | Admin-configurable application form fields |
| **Accessibility** | Accommodation needs tracking |
| **Reminders** | 5 configurable reminder types (pre-shift, post-shift, lapsed, credential, training) |
| **Reviews** | Volunteer reviews of organizations and users |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 14 dedicated volunteering services + 4 safeguarding services |
| API endpoints | 82+ dedicated volunteering routes |
| Database tables | 35 volunteering/safeguarding tables |
| Frontend tabs | 19 in the volunteering hub |
| Models | 17 Eloquent models |
| Matching signals | 3-4 scoring dimensions per algorithm |
| Burnout indicators | 5 risk factors |
| Reminder types | 5 configurable types |
| Credential types | 9 categories |
| Expense types | 6 categories |

---

## 2. System Architecture

### Service Map

```
┌───────────────────────────────────────────────────────────────────────────┐
│                        VOLUNTEERING ENGINE                                │
│                                                                           │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌──────────────────┐  │
│  │   CORE               │  │   SCHEDULING         │  │   SAFETY          │  │
│  │                      │  │                      │  │                   │  │
│  │ VolunteerService     │  │ VolunteerCheckInSvc  │  │ SafeguardingSvc   │  │
│  │ VolunteerMatchingSvc │  │ VolunteerReminderSvc │  │ SafeguardingPref  │  │
│  │ VolunteerFormSvc     │  │                      │  │ SafeguardingTrigg │  │
│  │                      │  │                      │  │ GuardianConsentSvc│  │
│  └──────────┬──────────┘  └──────────┬───────────┘  └────────┬─────────┘  │
│             │                        │                        │            │
│  ┌──────────▼──────────┐  ┌──────────▼───────────┐  ┌────────▼─────────┐  │
│  │   ENGAGEMENT         │  │   FINANCE             │  │   RECOGNITION    │  │
│  │                      │  │                      │  │                   │  │
│  │ VolunteerWellbeingSvc│  │ VolunteerExpenseSvc  │  │ VolunteerCertSvc  │  │
│  │ VolunteerEmergencySvc│  │ VolunteerDonationSvc │  │                   │  │
│  └──────────────────────┘  └──────────────────────┘  └───────────────────┘  │
└───────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Core CRUD | `app/Services/VolunteerService.php` |
| Matching | `app/Services/VolunteerMatchingService.php` |
| Check-In | `app/Services/VolunteerCheckInService.php` |
| Reminders | `app/Services/VolunteerReminderService.php` |
| Expenses | `app/Services/VolunteerExpenseService.php` |
| Wellbeing | `app/Services/VolunteerWellbeingService.php` |
| Forms | `app/Services/VolunteerFormService.php` |
| Certificates | `app/Services/VolunteerCertificateService.php` |
| Donations | `app/Services/VolunteerDonationService.php` |
| Emergency Alerts | `app/Services/VolunteerEmergencyAlertService.php` |
| Safeguarding | `app/Services/SafeguardingService.php` |
| Safeguarding Prefs | `app/Services/SafeguardingPreferenceService.php` |
| Safeguarding Triggers | `app/Services/SafeguardingTriggerService.php` |
| Guardian Consent | `app/Services/GuardianConsentService.php` |
| API Controller | `app/Http/Controllers/Api/VolunteerController.php` |
| Community Controller | `app/Http/Controllers/Api/VolunteerCommunityController.php` |
| Check-In Controller | `app/Http/Controllers/Api/VolunteerCheckInController.php` |
| Certificate Controller | `app/Http/Controllers/Api/VolunteerCertificateController.php` |
| Wellbeing Controller | `app/Http/Controllers/Api/VolunteerWellbeingController.php` |
| Expense Controller | `app/Http/Controllers/Api/VolunteerExpenseController.php` |
| Admin Controller | `app/Http/Controllers/Api/AdminVolunteerController.php` |
| Admin Safeguarding | `app/Http/Controllers/Api/AdminSafeguardingController.php` |

---

## 3. Opportunities & Shifts

### Opportunity Model

Volunteer opportunities are the top-level entity representing a volunteering position or event.

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Opportunity name |
| `description` | text | Full description |
| `location` | varchar(255) | Physical location |
| `latitude/longitude` | decimal(10,8) / decimal(11,8) | Geolocation |
| `skills_needed` | varchar(255) | Comma-separated or JSON skills |
| `start_date` / `end_date` | date | Date range |
| `category_id` | FK | Category classification |
| `organization_id` | FK | Parent volunteer organization |
| `created_by` | FK | Creator user ID |
| `status` | varchar(20) | `open` (default) |
| `is_active` | tinyint | Active/inactive flag |
| `credits_offered` | int | Time credits for this opportunity |

### Shift Model

Each opportunity can have multiple time-slotted shifts:

| Field | Type | Description |
|-------|------|-------------|
| `opportunity_id` | FK | Parent opportunity |
| `start_time` | datetime | Shift start |
| `end_time` | datetime | Shift end |
| `capacity` | int | Max volunteers (default 1) |
| `required_skills` | JSON | Skills required for this specific shift |
| `recurring_pattern_id` | FK | Link to recurring pattern (if generated) |

### CRUD Operations

```
VolunteerService::createOpportunity(userId, data) → VolOpportunity
VolunteerService::updateOpportunity(id, userId, data) → bool
VolunteerService::deleteOpportunity(id, userId) → bool  // Soft delete (is_active=0)
VolunteerService::getOpportunities(filters) → { items, cursor, has_more }
VolunteerService::getById(id) → VolOpportunity with relationships
```

**Permission model:** Only organization owner, org admin, or site admin can manage opportunities.

---

## 4. Applications & Approvals

### Application Flow

```
Volunteer applies → Status: pending
  → Org admin approves → Status: approved → Can sign up for shifts
  → Org admin declines → Status: declined
  → Volunteer withdraws → Deleted (only if still pending)
```

### Key Methods

| Method | Description |
|--------|-------------|
| `apply(opportunityId, userId, data)` | Apply (prevents duplicates for pending/approved) |
| `handleApplication(applicationId, adminUserId, action, orgNote)` | Approve or decline |
| `withdrawApplication(applicationId, userId)` | Self-withdraw (pending only) |
| `getMyApplications(userId, filters)` | User's application history |
| `getApplicationsForOpportunity(opportunityId, adminUserId, filters)` | Org admin view |

### Application Model

| Field | Type | Description |
|-------|------|-------------|
| `opportunity_id` | FK | Target opportunity |
| `user_id` | FK | Applicant |
| `shift_id` | FK (nullable) | Preferred shift |
| `status` | enum | `pending`, `approved`, `declined` |
| `message` | text | Application message |
| `org_note` | varchar(1000) | Admin note (on approval/decline) |

---

## 5. Hours Logging & Verification

### Logging Hours

```
VolunteerService::logHours(userId, {
    organization_id: int,      // required
    date: 'YYYY-MM-DD',       // required, not in future
    hours: float,              // required, 0 < hours <= 24
    opportunity_id?: int,
    shift_id?: int,
    description?: string
}) → logId | null
```

**Validations:**
- Hours > 0 and <= 24
- Date not in the future
- No duplicate entries per org + date + opportunity

### Verification Workflow

```
Volunteer logs hours → Status: pending
  → Org admin approves → Status: approved → Counts toward stats/certificates
  → Org admin declines → Status: declined
```

**Self-approval prevention:** Org admin cannot verify their own logged hours.

### Hours Summary

```
VolunteerService::getHoursSummary(userId) → {
    total_verified: float,
    total_pending: float,
    total_declined: float,
    by_organization: [{ org_name, hours }],
    by_month: [{ month, hours }],
    total_approved_hours: float,  // legacy alias
    pending_hours: float,
    this_month_hours: float,
    total_entries: int
}
```

---

## 6. QR Check-In System

### How It Works

1. **Token Generation:** When a volunteer has an approved application, they receive a QR token:
   ```
   VolunteerCheckInService::generateToken(shiftId, volunteerId) → string (64 hex chars)
   ```
   Token = `bin2hex(random_bytes(32))`

2. **Check-In:** Shift coordinator scans QR code:
   ```
   VolunteerCheckInService::verifyCheckIn(token) → {
       status: 'checked_in',
       checked_in_at: timestamp,
       user: { id, name },
       shift: { id, start_time, end_time }
   }
   ```
   - Allows check-in up to **30 minutes before** shift start
   - Rejects if already checked out

3. **Check-Out:**
   ```
   VolunteerCheckInService::checkOut(token) → bool
   ```

### Status Flow

```
pending → checked_in → checked_out
                    → no_show (if never checked out)
```

### QR Token Lifecycle

| State | QR Token | Check-In Status |
|-------|----------|----------------|
| Applied (not approved) | Not generated | — |
| Approved, pre-shift | Generated, stored in `vol_shift_checkins` | `pending` |
| Scanned in | Same token | `checked_in` + timestamp |
| Scanned out | Same token | `checked_out` + timestamp |

---

## 7. Volunteer Matching

### Opportunity Matching (findMatches)

Finds best volunteers for an opportunity:

| Component | Max Score | Algorithm |
|-----------|-----------|-----------|
| **Skill match** | 60 | Substring matching: user skills vs required skills. Score = (matches / needed) × 60 |
| **Experience** | 30 | Based on approved hours: min(30, hours / 10 × 5) |
| **Activity bonus** | 10 | Logarithmic: 5 + log(hours + 1) × 2 |
| **Total** | 100 | min(100, skill + experience + activity) |

### Opportunity Suggestions (suggestOpportunities)

Suggests opportunities for a user:

| Component | Max Score | Algorithm |
|-----------|-----------|-----------|
| **Skill match** | 80 | Substring matching: user skills vs needed. 40 if no skills required |
| **Recency** | 20 | Within 30 days = 20, decreases by 1 per 10 days beyond |
| **Total** | 100 | min(100, skill + recency). Excludes already-applied |

### Recommended Shifts (getRecommendedShifts)

Recommends specific shifts:

| Component | Max Score | Algorithm |
|-----------|-----------|-----------|
| **Skill match** | 60 | As above. Base 30 if no skills required |
| **Urgency** | 25 | Within 48h = 25, within 168h = 15, else 5 |
| **Availability** | 15 | (1 - fill_rate) × 15. Penalizes full shifts |
| **Total** | 100 | min(100, skill + urgency + availability). Skips full shifts |

### Skill Parsing

Skills are parsed from comma-separated strings or JSON arrays into lowercase keyword arrays. Matching uses case-insensitive substring comparison.

---

## 8. Shift Scheduling

### Recurring Patterns

Organizations can define recurring shift patterns:

| Field | Description |
|-------|-------------|
| `frequency` | daily, weekly, biweekly, monthly |
| `days_of_week` | JSON array (e.g., [1, 3, 5] for Mon/Wed/Fri) |
| `start_time` / `end_time` | Time of day |
| `capacity` | Volunteers per shift |
| `start_date` / `end_date` | Pattern date range |
| `max_occurrences` | Cap on generated shifts |

### Shift Sign-Up

```
VolunteerService::signUpForShift(shiftId, userId) → bool
```

**Requirements:**
- User must have an approved application for the opportunity
- Shift must have available capacity
- Uses `DB::transaction()` with row lock to prevent capacity overflow

### Shift Cancellation

```
VolunteerService::cancelShiftSignup(shiftId, userId) → bool
```

Cannot cancel past shifts.

---

## 9. Waitlists

When a shift is full, volunteers can join a waitlist:

### Key Methods

| Method | Description |
|--------|-------------|
| `joinWaitlist(shiftId)` | Join with position tracking |
| `leaveWaitlist(shiftId)` | Remove from waitlist |
| `promoteFromWaitlist(shiftId)` | Claim spot when promoted |

### Status Flow

```
waiting → notified (spot available) → promoted (accepted) / expired / cancelled
```

### Schema

| Field | Type | Description |
|-------|------|-------------|
| `shift_id` | FK | Target shift |
| `user_id` | FK | Waiting volunteer |
| `position` | int | Queue position |
| `status` | enum | `waiting`, `notified`, `promoted`, `expired`, `cancelled` |
| `notified_at` | timestamp | When spot became available |
| `promoted_at` | timestamp | When volunteer claimed spot |

---

## 10. Shift Swaps

Volunteers can request to swap shifts with each other:

### Swap Flow

```
Volunteer A requests swap → Status: pending
  → Volunteer B accepts → Status: accepted (or admin_pending if approval required)
    → Admin approves → Status: admin_approved → Shifts exchanged
    → Admin rejects → Status: admin_rejected
  → Volunteer B rejects → Status: rejected
  → Volunteer A cancels → Status: cancelled
  → Timeout → Status: expired
```

### Swap Status Values

`pending`, `accepted`, `rejected`, `admin_pending`, `admin_approved`, `admin_rejected`, `cancelled`, `expired`

### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `from_user_id` | FK | Requester |
| `to_user_id` | FK | Target volunteer |
| `from_shift_id` | FK | Requester's current shift |
| `to_shift_id` | FK | Desired shift |
| `requires_admin_approval` | tinyint | Admin gate flag |
| `admin_id` | FK | Reviewing admin |
| `message` | text | Optional message |

---

## 11. Group Reservations

Teams/groups can reserve multiple shift slots at once:

### Reserve Flow

```
Leader reserves N slots → Status: active
  → Leader adds members → Members confirmed/cancelled
  → All filled → Group completed
  → Leader cancels → Status: cancelled
```

### Key Methods

| Method | Description |
|--------|-------------|
| `groupReserve(shiftId, group_id, reserved_slots, notes)` | Reserve multiple slots |
| `addGroupMember(reservationId, userId)` | Add member to group |
| `removeGroupMember(reservationId, userId)` | Remove member |
| `cancelGroupReservation(reservationId)` | Cancel entire reservation |

### Schema

**vol_shift_group_reservations:**

| Field | Type | Description |
|-------|------|-------------|
| `shift_id` | FK | Target shift |
| `group_id` | FK | Community group |
| `reserved_slots` | int | Total reserved |
| `filled_slots` | int | Currently filled |
| `reserved_by` | FK | Leader user ID |
| `status` | enum | `active`, `cancelled`, `completed` |

**vol_shift_group_members:**

| Field | Type | Description |
|-------|------|-------------|
| `reservation_id` | FK | Parent reservation |
| `user_id` | FK | Group member |
| `status` | enum | `confirmed`, `cancelled` |

---

## 12. Organizations

### Organization Lifecycle

```
User creates org → Status: pending
  → Admin approves → Status: approved → Can create opportunities
  → Admin rejects → Status: rejected
```

### Creation

```
VolunteerService::createOrganization(userId, {
    name: string,       // 3-200 chars
    description: string, // 20+ chars
    contact_email: string,
    website?: string
}) → orgId
```

- Generates unique slug with collision retry (3 attempts)
- Creates owner membership record
- Transaction-wrapped for atomicity

### Organization Features

| Feature | Description |
|---------|-------------|
| **DLP assignment** | Designated Liaison Person for safeguarding |
| **Deputy DLP** | Backup safeguarding contact |
| **Auto-pay** | Automatic expense reimbursement flag |
| **Logo** | Organization branding |

### Organization Stats

```
VolunteerService::getOrganizationById(id) → {
    opportunity_count, total_hours, volunteer_count,
    review_count, average_rating
}
```

---

## 13. Wellbeing & Burnout Detection

### 5-Indicator Burnout Risk Analysis

```
VolunteerWellbeingService::detectBurnoutRisk(userId) → {
    risk_score: 0-100,
    risk_level: 'low' | 'moderate' | 'high' | 'critical',
    indicators: { ... },
    recommendations: string[]
}
```

#### Indicator 1: Shift Frequency Trend

Compares recent 30 days vs previous 30 days:

| Trend | Risk Points | Status |
|-------|-------------|--------|
| Declining (<50% of previous) | +20 | declining |
| Slightly declining (<80%) | +10 | slightly_declining |
| Increasing | 0 | increasing |
| Stable | 0 | stable |

#### Indicator 2: Cancellation Rate

From `vol_shift_signups` with status='cancelled':

| Rate | Risk Points |
|------|-------------|
| > 50% | +25 |
| > 30% | +15 |
| > 15% | +5 |

#### Indicator 3: Hours Trend

Compares approved hours recent vs previous 30 days:

| Trend | Risk Points | Status |
|-------|-------------|--------|
| Declining significantly (<30%) | +25 | declining_significantly |
| Declining (<60%) | +15 | declining |
| Increasing | 0 | increasing |
| Stable | 0 | stable |

#### Indicator 4: Engagement Gap

Days since last approved activity:

| Gap | Risk Points |
|-----|-------------|
| > 60 days | +20 |
| > 30 days | +10 |
| > 14 days | +5 |

#### Indicator 5: Overcommitment

Upcoming confirmed shifts in next 7 days:

| Shifts | Risk Points |
|--------|-------------|
| > 7 shifts | +15 |
| > 5 shifts | +5 |

### Risk Scoring

```
risk_score = min(100, max(0, sum of all indicator points))

Risk Levels:
  critical: score >= 70
  high:     score >= 50
  moderate: score >= 30
  low:      score < 30
```

### Wellbeing Alerts

When risk_score >= 30, an alert is persisted to `vol_wellbeing_alerts`:

| Field | Type | Description |
|-------|------|-------------|
| `risk_level` | enum | low/moderate/high/critical |
| `risk_score` | decimal(5,2) | 0-100 |
| `indicators` | JSON | Full indicator breakdown |
| `coordinator_notified` | tinyint | Whether coordinator has been notified |
| `coordinator_notes` | text | Coordinator's notes |
| `status` | enum | `active`, `acknowledged`, `resolved`, `dismissed` |

### Mood Check-Ins

Volunteers can record daily mood (1-5 scale):

| Mood | Label | Emoji |
|------|-------|-------|
| 1 | Struggling | Sad face |
| 2 | Not great | — |
| 3 | OK | — |
| 4 | Good | — |
| 5 | Great | Happy face |

Stored in `vol_mood_checkins` with optional note.

### Tenant-Wide Assessment

```
VolunteerWellbeingService::runTenantAssessment() → {
    total_assessed: int,
    at_risk: int,
    risk_breakdown: { low, moderate, high, critical },
    at_risk_users: [{ user_id, name, risk_level, risk_score }]
}
```

---

## 14. Emergency Alerts

For last-minute shift coverage needs:

### Alert Creation

```
VolunteerEmergencyAlertService::createAlert(createdBy, {
    shift_id: int,
    message: string,
    priority: 'normal' | 'urgent' | 'critical',
    required_skills: string[],
    expires_hours: int  // default 24
}) → alertId
```

### Notification Algorithm

1. Find all volunteers with approved applications for the organization
2. Filter by required skills (case-insensitive word-boundary regex matching)
3. Notify up to 50 qualified candidates
4. Return count notified

### Response Flow

```
Alert created → Status: active → Notified to qualified volunteers
  → Volunteer accepts → Status: filled → Coordinator notified
  → Volunteer declines → Recorded (alert stays active for others)
  → Coordinator cancels → Status: cancelled
  → Time expires → Status: expired
```

### Priority Levels

| Priority | Styling | Use Case |
|----------|---------|----------|
| `normal` | Default | Standard request |
| `urgent` | Warning | Same-day need |
| `critical` | Danger | Immediate need |

---

## 15. Expense Management

### Expense Types

| Type | Description |
|------|-------------|
| `travel` | Transportation costs |
| `meals` | Food and drink |
| `supplies` | Materials and supplies |
| `equipment` | Equipment rental/purchase |
| `parking` | Parking fees |
| `other` | Miscellaneous |

### Expense Submission

```
VolunteerExpenseService::submitExpense(userId, {
    organization_id: int,
    expense_type: string,
    amount: float,          // > 0
    description: string,
    currency: string,       // 3-letter ISO, default 'EUR'
    opportunity_id?: int,
    shift_id?: int,
    receipt_path?: string,
    receipt_filename?: string
}) → expense record
```

### Policy Enforcement

Before submission, applicable policies are checked:

| Policy Field | Description |
|-------------|-------------|
| `max_amount` | Maximum per single expense |
| `max_monthly` | Monthly spending cap |
| `requires_receipt_above` | Receipt required above this amount |
| `requires_approval` | Whether admin approval needed |

Policies cascade: **org-level** overrides **tenant-wide** defaults.

### Expense Status Flow

```
submitted → pending → approved → paid
                   → rejected
```

### Review & Payment

| Method | Description |
|--------|-------------|
| `reviewExpense(id, reviewerId, status, notes)` | Approve or reject (prevents self-review) |
| `markPaid(id, adminId, paymentReference)` | Mark approved expense as paid |
| `exportExpenses(tenantId, filters)` | CSV export for accounting |

---

## 16. Certificates

### Certificate Generation

```
VolunteerCertificateService::generate(userId, {
    organization_id?: int,
    date_from?: string,
    date_to?: string
}) → {
    verification_code: string,  // 12-char uppercase
    total_hours: float,
    date_range: { start, end },
    organizations: [{ name, hours }],
    user_name: string,
    verification_url: string
}
```

- Aggregates all **approved** volunteer hours
- Generates unique 12-char verification code (retries up to 5 times on collision)
- Stored in `vol_certificates` table

### Public Verification

```
GET /v2/volunteering/certificates/verify/{code}  // No auth required
```

Returns full certificate data with `verified: true` flag. Anyone with the code can verify.

### HTML Output

```
GET /v2/volunteering/certificates/{code}/html
```

Returns printable HTML certificate with:
- Certificate title and border
- Volunteer name
- Total hours
- Date range
- Organization breakdown table
- Verification code

---

## 17. Credentials & Training

### Credential Upload

Volunteers can upload proof of qualifications:

| Credential Type | Description |
|----------------|-------------|
| `police_check` | Police/criminal record check |
| `first_aid` | First aid certification |
| `background_check` | Background check clearance |
| `safeguarding` | Safeguarding training cert |
| `manual_handling` | Manual handling cert |
| `food_hygiene` | Food hygiene cert |
| `driving_licence` | Driving licence |
| `professional_registration` | Professional registration |
| `other` | Other credential |

**File constraints:** PDF, JPEG, PNG, WebP. Max 10MB.

### Credential Status Flow

```
uploaded → pending → verified (by admin)
                  → rejected (by admin)
                  → expired (date-based)
```

### Safeguarding Training

5 training types tracked:

| Type | Description |
|------|-------------|
| `children_first` | Children First training |
| `vulnerable_adults` | Vulnerable adults training |
| `first_aid` | First aid training |
| `manual_handling` | Manual handling training |
| `other` | Other training |

### Training Record Fields

| Field | Type | Description |
|-------|------|-------------|
| `training_type` | enum | One of 5 types |
| `provider` | varchar | Training provider |
| `completed_at` | date | Completion date |
| `expires_at` | date | Expiry date |
| `certificate_url` | varchar | Certificate file URL |
| `status` | enum | `pending`, `verified`, `expired`, `rejected` |
| `verified_by` | FK | Admin who verified |

---

## 18. Safeguarding System

### Incident Reporting

```
SafeguardingService::reportIncident(reporterId, {
    title: string,
    description: string,
    severity: 'low' | 'medium' | 'high' | 'critical',
    incident_type: 'concern' | 'allegation' | 'disclosure' | 'near_miss' | 'other',
    subject_user_id?: int,
    organization_id?: int,
    opportunity_id?: int,
    shift_id?: int,
    incident_date?: date
}) → incident record
```

**Critical:** ALL incidents notify ALL admins/brokers immediately (legally required regardless of severity).

### Incident Status Flow

```
open → investigating → resolved / escalated / closed
```

### DLP Assignment

Designated Liaison Person — assigned per organization or per incident:

```
SafeguardingService::assignDlp(incidentId, dlpUserId, adminId, tenantId) → bool
```

- Bell notification sent to DLP
- **Critical email** bypasses user notification preferences
- Subject includes severity label for email triage

### Safeguarding Triggers

When volunteers select safeguarding preferences during onboarding, the system activates behavioral protections:

| Trigger | Effect |
|---------|--------|
| `requires_vetted_interaction` | Interactions require vetting verification |
| `requires_broker_approval` | Exchanges need broker approval |
| `restricts_messaging` | Messaging restrictions applied |
| `restricts_matching` | Smart matching restrictions |
| `notify_admin_on_selection` | Admin notified when preference selected |

Triggers merge via OR logic — if any selected option has `requires_broker_approval`, the user gets broker approval requirements.

### Trigger → Broker Integration

Triggers sync to `user_messaging_restrictions` table which the exchange workflow's `needsBrokerApproval()` checks. This connects safeguarding to the timebanking exchange system.

### Country Presets

Admin can apply country-specific safeguarding presets:

```
SafeguardingPreferenceService::applyCountryPreset(tenantId, 'ireland') → createdOptionKeys[]
```

Loads from `config('safeguarding_presets')` with vetting authority, help text, and pre-configured options.

### Flagged Messages

Messages flagged by keyword matching are stored in `safeguarding_flagged_messages` for admin review:

| Field | Type | Description |
|-------|------|-------------|
| `message_id` | FK | Flagged message |
| `flagged_reason` | varchar | e.g., `keyword_match` |
| `matched_keyword` | varchar | Triggering keyword |
| `reviewed_by` | FK | Reviewing admin |
| `review_notes` | text | Admin notes |

### Audit Logging

**Every safeguarding read and write is audit-logged** for compliance:
- User ID, action, entity type/ID, details JSON
- IP address, user agent
- All reads logged (not just writes) — legally required

---

## 19. Guardian Consent

For volunteers under 18 (minors):

### Consent Request

```
GuardianConsentService::requestConsent(minorUserId, {
    guardian_name: string,
    guardian_email: string,  // validated
    relationship: 'parent' | 'guardian' | 'legal_guardian' | 'carer',
    opportunity_id?: int
}) → consent record
```

- Generates 64-hex consent token
- Expires in 365 days
- Sends email to guardian with verification URL

### Consent Flow

```
Minor requests → Status: pending → Email sent to guardian
  → Guardian clicks link → Status: active (IP recorded)
  → Consent withdrawn → Status: withdrawn
  → Token expires → Status: expired
```

### Consent Checking

```
GuardianConsentService::checkConsent(minorUserId, opportunityId?) → bool
```

Returns `true` if active, non-expired consent exists. If `opportunityId` provided, checks for opportunity-specific or generic consent.

### Minor Detection

```
GuardianConsentService::isMinor(userId) → bool
```

Calculates age from `date_of_birth` using `DateTime::diff()`. Returns `true` if age < 18.

---

## 20. Donations & Giving Days

### Monetary Donations

```
VolunteerDonationService::createDonation(userId, {
    amount: float,           // > 0, <= 1,000,000
    currency: string,        // 3-letter ISO
    payment_method: string,  // card, bank_transfer, paypal
    opportunity_id?: int,
    community_project_id?: int,
    giving_day_id?: int,
    message?: string,
    is_anonymous?: bool
}) → donation record
```

**Important:** Donations start as `pending`. Only payment webhooks (Stripe) mark `completed`. This prevents user fraud.

### Giving Day Campaigns

Time-limited fundraising events:

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Campaign name |
| `description` | text | Campaign description |
| `start_date` / `end_date` | date | Campaign period |
| `goal_amount` | decimal(10,2) | Fundraising target |
| `raised_amount` | decimal(10,2) | Current total |
| `is_active` | tinyint | Active flag |

### Giving Day Stats

```
VolunteerDonationService::getGivingDayStats(givingDayId) → {
    total_raised: float,
    donor_count: int,
    goal_amount: float,
    progress_percent: float  // capped at 100
}
```

---

## 21. Community Projects

Member-proposed volunteer project ideas with community voting:

### Project Status Flow

```
proposed → under_review → approved → active → completed
                       → rejected
                                  → cancelled
```

### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `proposed_by` | FK | Proposer user ID |
| `title` | varchar(255) | Project name |
| `description` | text | Full description |
| `category` | varchar(100) | Project category |
| `location` | varchar(255) | Location with lat/lng |
| `target_volunteers` | int | Target volunteer count |
| `proposed_date` | date | Proposed start date |
| `skills_needed` | JSON | Required skills |
| `estimated_hours` | decimal(5,1) | Estimated hours |
| `supporter_count` | int | Community support count |
| `opportunity_id` | FK (nullable) | Linked opportunity (when approved) |

### Community Support

Members can support project proposals (optimistic updates in frontend):

```
POST /v2/volunteering/community-projects/{id}/support
DELETE /v2/volunteering/community-projects/{id}/support
```

---

## 22. Custom Fields & Accessibility

### Custom Application Fields

Admins can define dynamic form fields for volunteer applications:

| Field Property | Description |
|---------------|-------------|
| `field_key` | Unique key |
| `field_label` | Display label |
| `field_type` | text, textarea, select, checkbox, radio, date, file, number, email, phone |
| `applies_to` | application, opportunity, shift, profile |
| `is_required` | Required flag |
| `field_options` | JSON options for select/radio |
| `placeholder` | Input placeholder |
| `help_text` | Help text |

### Accessibility Needs

Volunteers can register accommodation needs:

| Need Type | Description |
|-----------|-------------|
| `mobility` | Physical mobility accommodations |
| `visual` | Visual impairment accommodations |
| `hearing` | Hearing impairment accommodations |
| `cognitive` | Cognitive accommodations |
| `dietary` | Dietary requirements |
| `language` | Language support needs |
| `other` | Other accommodations |

Each need includes: description, accommodations_required, emergency_contact_name, emergency_contact_phone.

---

## 23. Reminders & Webhooks

### 5 Reminder Types

| Type | Default Setting | Description |
|------|----------------|-------------|
| `pre_shift` | 24 hours before | Shift reminder |
| `post_shift_feedback` | 2 hours after | Feedback request |
| `lapsed_volunteer` | 30 days inactive | Re-engagement nudge |
| `credential_expiry` | 14 days before | Credential renewal |
| `training_expiry` | 14 days before | Training renewal |

### Reminder Channels

Each reminder type can be configured per channel:
- `push_enabled` (default: true)
- `email_enabled` (default: true)
- `sms_enabled` (default: false)

### Webhooks

Admin-configurable webhooks for external integrations:

| Operation | Description |
|-----------|-------------|
| Create webhook | URL, event types, secret |
| Update webhook | Modify configuration |
| Delete webhook | Remove webhook |
| Test webhook | Send test payload |
| View logs | Historical webhook deliveries |

---

## 24. Reviews

### Review Model

| Field | Type | Description |
|-------|------|-------------|
| `reviewer_id` | FK | Who is reviewing |
| `target_type` | enum | `organization` or `user` |
| `target_id` | FK | Organization or user ID |
| `rating` | int | 1-5 stars |
| `comment` | text | Review text |

### Validation Rules

- Rating 1-5
- No self-reviews
- No duplicate reviews (same reviewer → same target)
- Reviewer must have history with target (approved volunteer work)

---

## 25. Database Schema

### Complete Table List (35 tables)

| Table | Records |
|-------|---------|
| `vol_opportunities` | Volunteer opportunities |
| `vol_shifts` | Time-slotted shifts |
| `vol_applications` | Volunteer applications |
| `vol_shift_checkins` | QR-based check-in records |
| `vol_shift_signups` | Shift sign-ups |
| `vol_shift_waitlist` | Waitlist queue |
| `vol_shift_swap_requests` | Swap requests |
| `vol_shift_group_reservations` | Group bookings |
| `vol_shift_group_members` | Group members |
| `vol_logs` | Hours logged |
| `vol_organizations` | Volunteer organizations |
| `vol_reviews` | Reviews |
| `vol_certificates` | Impact certificates |
| `vol_credentials` | Uploaded credentials |
| `vol_custom_fields` | Dynamic form fields |
| `vol_custom_field_values` | Field values |
| `vol_accessibility_needs` | Accommodation needs |
| `vol_expenses` | Expense claims |
| `vol_expense_policies` | Expense policies |
| `vol_emergency_alerts` | Emergency alerts |
| `vol_emergency_alert_recipients` | Alert recipients |
| `vol_wellbeing_alerts` | Burnout alerts |
| `vol_mood_checkins` | Mood check-ins |
| `vol_donations` | Monetary donations |
| `vol_giving_days` | Giving campaigns |
| `vol_community_projects` | Community proposals |
| `vol_community_project_supporters` | Project votes |
| `vol_reminder_settings` | Reminder configuration |
| `vol_reminders_sent` | Sent reminders log |
| `vol_guardian_consents` | Minor consents |
| `vol_safeguarding_incidents` | Incident reports |
| `vol_safeguarding_training` | Training records |
| `safeguarding_assignments` | Guardian/ward pairs |
| `safeguarding_flagged_messages` | Flagged messages |
| `user_messaging_restrictions` | Broker protections (shared) |

---

## 26. API Reference

### Core Volunteering (24 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/volunteering/opportunities` | Browse opportunities |
| POST | `/v2/volunteering/opportunities` | Create opportunity |
| GET | `/v2/volunteering/opportunities/{id}` | Opportunity detail |
| PUT | `/v2/volunteering/opportunities/{id}` | Update opportunity |
| DELETE | `/v2/volunteering/opportunities/{id}` | Delete opportunity |
| GET | `/v2/volunteering/applications` | My applications |
| POST | `/v2/volunteering/opportunities/{id}/apply` | Apply |
| GET | `/v2/volunteering/opportunities/{id}/applications` | Org admin: view apps |
| PUT | `/v2/volunteering/applications/{id}` | Approve/decline |
| DELETE | `/v2/volunteering/applications/{id}` | Withdraw |
| GET | `/v2/volunteering/shifts` | My shifts |
| GET | `/v2/volunteering/opportunities/{id}/shifts` | Shifts for opportunity |
| POST | `/v2/volunteering/shifts/{id}/signup` | Sign up |
| DELETE | `/v2/volunteering/shifts/{id}/signup` | Cancel signup |
| POST | `/v2/volunteering/hours` | Log hours |
| GET | `/v2/volunteering/hours` | My hours |
| GET | `/v2/volunteering/hours/summary` | Hours summary |
| GET | `/v2/volunteering/hours/pending-review` | Pending review |
| PUT | `/v2/volunteering/hours/{id}/verify` | Approve/decline hours |
| GET | `/v2/volunteering/organisations` | List organizations |
| GET | `/v2/volunteering/my-organisations` | My organizations |
| GET | `/v2/volunteering/organisations/{id}` | Org detail |
| POST | `/v2/volunteering/organisations` | Create org |
| GET | `/v2/volunteering/recommended-shifts` | AI recommendations |

### Community Features (30+ endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST/DELETE | `/v2/volunteering/shifts/{id}/waitlist` | Join/leave waitlist |
| POST | `/v2/volunteering/shifts/{id}/waitlist/promote` | Claim spot |
| GET/POST | `/v2/volunteering/swaps` | List/create swaps |
| PUT/DELETE | `/v2/volunteering/swaps/{id}` | Respond/cancel |
| POST | `/v2/volunteering/shifts/{id}/group-reserve` | Group reserve |
| POST/DELETE | `/v2/volunteering/group-reservations/{id}/members` | Manage members |
| POST/PUT/DELETE | `/v2/volunteering/opportunities/{id}/recurring-patterns` | Recurring shifts |
| GET/PUT | `/v2/volunteering/accessibility-needs` | Accessibility |
| GET/POST | `/v2/volunteering/community-projects` | Projects |
| POST/DELETE | `/v2/volunteering/community-projects/{id}/support` | Vote |
| GET/POST | `/v2/volunteering/donations` | Donations |
| GET | `/v2/volunteering/giving-days` | Giving campaigns |
| GET/POST/DELETE | `/v2/volunteering/guardian-consents` | Guardian consent |
| GET | `/v2/volunteering/guardian-consents/verify/{token}` | Verify (public) |

### Check-In (4 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/volunteering/shifts/{id}/checkin` | Get QR code |
| POST | `/v2/volunteering/checkin/verify/{token}` | Scan check-in |
| POST | `/v2/volunteering/checkin/checkout/{token}` | Scan check-out |
| GET | `/v2/volunteering/shifts/{id}/checkins` | All check-ins |

### Certificates & Credentials (7 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/volunteering/certificates` | My certificates |
| POST | `/v2/volunteering/certificates` | Generate certificate |
| GET | `/v2/volunteering/certificates/verify/{code}` | Verify (public) |
| GET | `/v2/volunteering/certificates/{code}/html` | HTML for print |
| GET | `/v2/volunteering/credentials` | My credentials |
| POST | `/v2/volunteering/credentials` | Upload credential |
| DELETE | `/v2/volunteering/credentials/{id}` | Delete credential |

### Wellbeing & Safety (17 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/volunteering/wellbeing` | Wellbeing dashboard |
| POST | `/v2/volunteering/wellbeing/checkin` | Mood check-in |
| GET | `/v2/volunteering/wellbeing/my-status` | Detailed assessment |
| GET/POST | `/v2/volunteering/emergency-alerts` | Alerts |
| PUT | `/v2/volunteering/emergency-alerts/{id}` | Respond |
| DELETE | `/v2/volunteering/emergency-alerts/{id}` | Cancel |
| POST | `/v2/volunteering/incidents` | Report incident |
| GET | `/v2/volunteering/incidents` | My incidents |
| GET | `/v2/volunteering/training` | My training |
| POST | `/v2/volunteering/training` | Record training |
| POST | `/v2/volunteering/reviews` | Create review |
| GET | `/v2/volunteering/reviews/{type}/{id}` | Get reviews |

### Expenses (8 endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/volunteering/expenses` | My expenses |
| POST | `/v2/volunteering/expenses` | Submit expense |
| GET | `/v2/volunteering/expenses/{id}` | Expense detail |
| GET | `/v2/admin/volunteering/expenses` | Admin: all expenses |
| PUT | `/v2/admin/volunteering/expenses/{id}` | Admin: review |
| GET | `/v2/admin/volunteering/expenses/export` | Admin: CSV export |
| GET | `/v2/admin/volunteering/expenses/policies` | Admin: policies |
| PUT | `/v2/admin/volunteering/expenses/policies` | Admin: update policy |

### Admin (18+ endpoints)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/volunteering` | Dashboard stats |
| GET | `/v2/admin/volunteering/approvals` | Pending applications |
| POST | `/v2/admin/volunteering/approvals/{id}/approve` | Approve |
| POST | `/v2/admin/volunteering/approvals/{id}/decline` | Decline |
| POST | `/v2/admin/volunteering/hours/{id}/verify` | Verify hours |
| GET | `/v2/admin/volunteering/incidents` | All incidents |
| PUT | `/v2/admin/volunteering/incidents/{id}` | Update incident |
| PUT | `/v2/admin/volunteering/organizations/{id}/dlp` | Assign DLP |
| GET | `/v2/admin/volunteering/training` | All training |
| PUT | `/v2/admin/volunteering/training/{id}/verify` | Verify training |
| PUT | `/v2/admin/volunteering/training/{id}/reject` | Reject training |
| GET | `/v2/admin/safeguarding/dashboard` | Safeguarding stats |
| GET | `/v2/admin/safeguarding/flagged-messages` | Flagged messages |
| POST | `/v2/admin/safeguarding/flagged-messages/{id}/review` | Review message |
| GET/POST/DELETE | `/v2/admin/safeguarding/assignments` | Guardian assignments |
| GET | `/v2/admin/safeguarding/member-preferences` | Member prefs |

---

## 27. Frontend Experience

### Volunteering Hub (19 Tabs)

The main `/volunteering` page is a tabbed interface with lazy-loaded components:

| Tab | Component | Key Features |
|-----|-----------|-------------|
| **Opportunities** | Built-in | Browse, search, pagination, apply |
| **Applications** | Built-in | Status tracking, withdrawal |
| **Hours** | Built-in | Log hours, view summary stats |
| **Recommended** | RecommendedShiftsTab | Match scores (0-100), color-coded rings |
| **Certificates** | CertificatesTab | Generate, download HTML, verify online |
| **Alerts** | EmergencyAlertsTab | Priority-based, accept/decline, expiry timer |
| **Wellbeing** | WellbeingTab | Score bar, burnout risk, mood check-in |
| **Credentials** | CredentialVerificationTab | Upload (10MB max), status tracking |
| **Waitlist** | WaitlistTab | Position display, leave option |
| **Swaps** | ShiftSwapsTab | Sent/received view, accept/reject/cancel |
| **Group** | GroupSignUpTab | Add members, debounced search |
| **Hours Review** | HoursReviewTab | Admin approve/decline with optimistic updates |
| **Expenses** | ExpensesTab | Submit claims, track status |
| **Safeguarding** | SafeguardingTab | Training records + incident reporting |
| **Projects** | CommunityProjectsTab | Propose, vote/support, status filters |
| **Donations** | DonationsTab | Giving days, donation history, Stripe |
| **Accessibility** | AccessibilityTab | Register accommodation needs |

### Opportunity Detail Page

- Breadcrumb navigation
- Full description with skills needed
- Shifts panel: capacity, signup count, spots available
- QR check-in panel (for approved volunteers)
- Applications panel (for org admins)
- Apply button with application form

### Create Opportunity Page

- Organization selector (auto-selects if one org)
- Title, description, location (with Google Places autocomplete)
- Skills needed, date range, category
- Validation (title min 5 chars, required fields)

### Onboarding Integration

`SafeguardingStep.tsx` during onboarding:
- Loads admin-configured safeguarding options
- Three option types: checkbox, info, select
- GDPR consent notice
- Required field enforcement
- Triggers broker protections on submission

### Admin Pages

| Page | Features |
|------|---------|
| **VolunteeringOverview** | Stats cards (active opportunities, pending apps, total hours, volunteers) |
| **VolunteerApprovals** | DataTable with approve/decline buttons |
| **VolunteerOrganizations** | Org list with member counts and hour balances |

---

## 28. Admin Panel

### Admin Capabilities

| Capability | Description |
|-----------|-------------|
| **Dashboard** | Active opportunities, pending applications, total hours, volunteer count |
| **Application Review** | Approve/decline with notifications |
| **Hours Verification** | Approve/decline logged hours |
| **Incident Management** | View, update, assign DLP, escalate |
| **Training Verification** | Verify or reject training records |
| **Expense Review** | Approve, reject, mark paid, export CSV |
| **Safeguarding Dashboard** | Active assignments, unreviewed flags, consent stats |
| **Flagged Message Review** | Review broker-flagged messages |
| **Guardian Assignments** | Create/revoke guardian-ward relationships |
| **Member Preferences** | View onboarding safeguarding selections |
| **Badge Config** | Configure per-tenant safeguarding options |
| **Country Presets** | Apply country-specific safeguarding presets |
| **Reminder Settings** | Configure 5 reminder types with channel selection |
| **Custom Fields** | CRUD for dynamic application form fields |
| **Giving Days** | Create/manage fundraising campaigns |
| **Webhooks** | Configure, test, view logs |
| **Community Projects** | Review and approve project proposals |
| **Shift Swaps** | Admin-approve swaps when required |
| **Donation Export** | CSV export for accounting |
| **Bulk Shift Reminders** | Send reminders for all active opportunities |

---

*This report documents the complete volunteering engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
