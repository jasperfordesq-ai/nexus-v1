# Project NEXUS — Jobs Module Engine: Complete Technical Report

**Generated:** 2026-03-29
**Version:** 1.5.0
**License:** AGPL-3.0-or-later

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [Job Vacancies](#3-job-vacancies)
4. [Application Pipeline](#4-application-pipeline)
5. [Interviews](#5-interviews)
6. [Interview Self-Scheduling](#6-interview-self-scheduling)
7. [Job Offers](#7-job-offers)
8. [Skill Matching](#8-skill-matching)
9. [Candidate Scorecards](#9-candidate-scorecards)
10. [Hiring Team](#10-hiring-team)
11. [Referrals](#11-referrals)
12. [Job Templates](#12-job-templates)
13. [Pipeline Automation](#13-pipeline-automation)
14. [Talent Search](#14-talent-search)
15. [Job Alerts](#15-job-alerts)
16. [Job Feeds (RSS & Google Jobs)](#16-job-feeds-rss--google-jobs)
17. [Moderation & Spam Detection](#17-moderation--spam-detection)
18. [Bias Audit](#18-bias-audit)
19. [Analytics](#19-analytics)
20. [Saved Jobs & Profiles](#20-saved-jobs--profiles)
21. [Employer Branding](#21-employer-branding)
22. [Expiry & Renewal](#22-expiry--renewal)
23. [Donations & Giving Days](#23-donations--giving-days)
24. [GDPR Compliance](#24-gdpr-compliance)
25. [Database Schema](#25-database-schema)
26. [API Reference](#26-api-reference)
27. [Frontend Experience](#27-frontend-experience)
28. [Admin Panel](#28-admin-panel)

---

## 1. Executive Summary

The Project NEXUS Jobs Module is a complete applicant tracking system (ATS) embedded within a multi-tenant timebanking platform. It supports three job types — paid employment, volunteer positions, and timebank credit exchanges — making it unique among ATS platforms. The module covers the full hiring lifecycle from job posting through offer acceptance, with AI-powered features, bias auditing, and EU Pay Transparency compliance.

### What It Includes

| System | Purpose |
|--------|---------|
| **Job Vacancies** | Create, publish, and manage job postings with 3 types and 4 commitment levels |
| **Application Pipeline** | 10-stage ATS pipeline (applied → accepted/rejected/withdrawn) |
| **Interviews** | Propose, accept, decline, cancel interviews with type tracking |
| **Self-Scheduling** | Employer-created time slots with candidate self-booking |
| **Job Offers** | Create, accept, reject, withdraw with salary and expiry |
| **Skill Matching** | Fuzzy matching (0-100%) with qualification assessment |
| **Scorecards** | Multi-reviewer candidate evaluation with weighted criteria |
| **Hiring Team** | Role-based team management (reviewer, manager) |
| **Referrals** | Shareable referral tokens with conversion tracking |
| **Templates** | Reusable job posting templates (personal + public) |
| **Pipeline Rules** | Time-based automation (auto-move, auto-reject, notify) |
| **Talent Search** | Opt-in candidate database with keyword/skill search |
| **Job Alerts** | Subscription-based email notifications for new matches |
| **RSS & Google Jobs** | Public feeds for search engine consumption |
| **Moderation** | Admin approval workflow with spam detection scoring |
| **Bias Audit** | Hiring funnel analytics for fairness detection |
| **Analytics** | Views, conversions, time-to-fill, referral stats |
| **Saved Jobs/Profiles** | Bookmark jobs + reusable candidate profiles |
| **Employer Branding** | Tagline, video, culture photos, benefits, company size |
| **Expiry & Renewal** | Deadline tracking with 7-day warnings and renewal |
| **GDPR** | Data export and selective erasure/anonymization |
| **Kanban Board** | Drag-and-drop pipeline management UI |

### Key Metrics

| Metric | Value |
|--------|-------|
| Backend services | 18 dedicated jobs services |
| API endpoints | 85+ routes across 3 controllers |
| Database tables | 16 job-specific tables |
| Models | 15 Eloquent models |
| Pipeline stages | 10 (applied → accepted/rejected/withdrawn) |
| Job types | 3 (paid, volunteer, timebank) |
| Commitment types | 4 (full_time, part_time, flexible, one_off) |
| Frontend pages | 11 + 2 admin modules |
| Matching dimensions | Skill fuzzy matching + location + salary + commitment |

---

## 2. System Architecture

### Service Map

```
┌───────────────────────────────────────────────────────────────────────────┐
│                          JOBS MODULE ENGINE                               │
│                                                                           │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌──────────────────┐  │
│  │   CORE               │  │   PIPELINE           │  │   AI & MATCHING  │  │
│  │                      │  │                      │  │                  │  │
│  │ JobVacancyService    │  │ JobInterviewSvc      │  │ SkillMatching    │  │
│  │  - CRUD              │  │ JobInterviewSchedSvc │  │ CandidateSearch  │  │
│  │  - Applications      │  │ JobOfferSvc          │  │ JobFeedSvc       │  │
│  │  - Pipeline          │  │ JobScorecardSvc      │  │ BiasAuditSvc     │  │
│  │  - Analytics         │  │ JobPipelineRuleSvc   │  │                  │  │
│  └──────────┬──────────┘  └──────────┬───────────┘  └────────┬─────────┘  │
│             │                        │                        │            │
│  ┌──────────▼──────────┐  ┌──────────▼───────────┐  ┌────────▼─────────┐  │
│  │   TEAM & SOCIAL      │  │   COMPLIANCE         │  │   ADMIN          │  │
│  │                      │  │                      │  │                  │  │
│  │ JobTeamSvc           │  │ JobModerationSvc     │  │ JobExpiryNotifSvc│  │
│  │ JobReferralSvc       │  │ JobGdprSvc           │  │ JobAlertEmailSvc │  │
│  │ JobTemplateSvc       │  │                      │  │ JobSavedProfileSvc│ │
│  └──────────────────────┘  └──────────────────────┘  └───────────────────┘  │
└───────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| Component | File |
|-----------|------|
| Core CRUD + Applications | `app/Services/JobVacancyService.php` |
| Interviews | `app/Services/JobInterviewService.php` |
| Interview Scheduling | `app/Services/JobInterviewSchedulingService.php` |
| Offers | `app/Services/JobOfferService.php` |
| Scorecards | `app/Services/JobScorecardService.php` |
| Team Management | `app/Services/JobTeamService.php` |
| Referrals | `app/Services/JobReferralService.php` |
| Templates | `app/Services/JobTemplateService.php` |
| Pipeline Automation | `app/Services/JobPipelineRuleService.php` |
| Moderation | `app/Services/JobModerationService.php` |
| Talent Search | `app/Services/CandidateSearchService.php` |
| Bias Audit | `app/Services/JobBiasAuditService.php` |
| Feeds | `app/Services/JobFeedService.php` |
| Expiry Notifications | `app/Services/JobExpiryNotificationService.php` |
| Alert Emails | `app/Services/JobAlertEmailService.php` |
| GDPR | `app/Services/JobGdprService.php` |
| Saved Profiles | `app/Services/JobSavedProfileService.php` |
| Salary Benchmarks | `app/Services/SalaryBenchmarkService.php` |
| Main API Controller | `app/Http/Controllers/Api/JobVacanciesController.php` |
| Feed Controller | `app/Http/Controllers/Api/JobFeedController.php` |
| Admin Controller | `app/Http/Controllers/Api/AdminJobsController.php` |

---

## 3. Job Vacancies

### Job Types

| Type | Description | Payment |
|------|-------------|---------|
| `paid` | Traditional paid employment | Salary (monetary) |
| `volunteer` | Volunteer position | Unpaid |
| `timebank` | Service exchange via time credits | Time credits |

### Commitment Levels

| Level | Description |
|-------|-------------|
| `full_time` | Full-time position |
| `part_time` | Part-time position |
| `flexible` | Flexible hours |
| `one_off` | Single engagement |

### Vacancy Fields

| Field | Type | Description |
|-------|------|-------------|
| `title` | varchar(255) | Job title |
| `description` | text | Full description |
| `type` | enum | `paid`, `volunteer`, `timebank` |
| `commitment` | enum | `full_time`, `part_time`, `flexible`, `one_off` |
| `location` | varchar(255) | Location |
| `latitude/longitude` | decimal | Geolocation for radius search |
| `is_remote` | boolean | Remote work available |
| `category` | varchar(100) | Job category |
| `skills_required` | text | Required skills (comma-separated or JSON) |
| `hours_per_week` | decimal(5,1) | Weekly hours |
| `time_credits` | decimal(10,2) | Time credits offered (timebank type) |
| `salary_min/salary_max` | decimal(12,2) | Salary range |
| `salary_type` | varchar(30) | hourly, annual, etc. |
| `salary_currency` | varchar(10) | ISO currency code |
| `salary_negotiable` | boolean | Salary negotiable flag |
| `contact_email/phone` | varchar | Contact details |
| `deadline` | datetime | Application deadline |
| `status` | enum | `open`, `closed`, `filled`, `draft` |
| `is_featured` | boolean | Featured promotion flag |
| `featured_until` | datetime | Feature expiry |
| `organization_id` | FK | Linked organization |
| `tagline` | text | Employer tagline |
| `video_url` | varchar | Employer video |
| `culture_photos` | text | Culture photo URLs |
| `company_size` | varchar | Company size label |
| `benefits` | text | Benefits list |
| `blind_hiring` | boolean | Anonymize applicant identities |
| `spam_score` | int | Spam detection score |
| `spam_flags` | JSON | Spam flag details |
| `moderation_status` | enum | `pending_review`, `approved`, `rejected`, `flagged` |

### EU Pay Transparency Compliance

For paid jobs, salary range is required unless marked as negotiable:
- `salary_min` and `salary_max` must be provided
- OR `salary_negotiable` must be true
- Enforced on creation via `JobVacancyService::create()`

### Creation Flow

```
Employer submits job → Spam analysis (Agent B) →
  If moderation enabled:
    moderation_status = pending_review → Admin reviews
  Else:
    status = open → Published immediately
→ Job alert event fired → Matching subscribers notified
→ Duplicate detection check (Agent A)
```

### Search & Filtering

`JobVacancyService::getAll()` supports:
- **Text search** with boolean parser: `|` (OR), space/`+` (AND), `-word` (NOT)
- **Filters**: status, type, commitment, category, featured, is_remote, organization_id
- **Geolocation**: Haversine radius search (latitude, longitude, radius_km)
- **Sort**: newest (default), deadline, salary_desc
- **Pagination**: Cursor-based with configurable limit
- **Featured first**: Featured jobs always appear at top

---

## 4. Application Pipeline

### Pipeline Stages (10)

```
applied → screening → reviewed → shortlisted → interview → offer → accepted
                                                                  → rejected
                                                                  → withdrawn
```

| Stage | Description | Who Advances |
|-------|-------------|-------------|
| `applied` | Initial application submitted | Automatic |
| `pending` | Awaiting review | Automatic |
| `screening` | Under initial screening | Employer |
| `reviewed` | Reviewed by team | Employer |
| `shortlisted` | On shortlist | Employer |
| `interview` | Interview scheduled | Employer |
| `offer` | Offer extended | Employer |
| `accepted` | Offer accepted | Candidate |
| `rejected` | Application rejected | Employer |
| `withdrawn` | Candidate withdrew | Candidate |

### Application Submission

```
JobVacancyService::apply(jobId, userId, {
    cover_letter?: string,
    cv_path?: string,        // File path
    cv_filename?: string,    // Original filename
    cv_size?: int            // File size in bytes
}) → applicationId
```

**Validations:**
- Vacancy exists and is open (not closed/filled)
- No duplicate applications (UNIQUE constraint on vacancy_id + user_id)
- Transaction with row locking to prevent race conditions

**CV Upload:**
- Max 5MB
- Allowed formats: PDF, DOC, DOCX
- Uploaded via multipart form data
- Stored path in `cv_path`, filename in `cv_filename`

### Status Updates

```
JobVacancyService::updateApplicationStatus(applicationId, adminId, status, notes?) → bool
```

- Prevents backwards transitions from terminal states (accepted/rejected/withdrawn)
- Logs to `job_application_history` table
- Dispatches webhook
- Notifies applicant of status change

### Blind Hiring

When `blind_hiring = true` on a vacancy:
- `getApplications()` anonymizes applicant data
- Names replaced with "Candidate #1", "Candidate #2", etc.
- Avatars hidden
- Emails hidden
- Only skills and cover letter visible

### Application History

Full audit trail in `job_application_history`:

| Field | Type | Description |
|-------|------|-------------|
| `application_id` | FK | Application |
| `from_status` | varchar | Previous status |
| `to_status` | varchar | New status |
| `changed_by` | FK | Who made the change |
| `changed_at` | datetime | When |
| `notes` | text | Reviewer notes |

### Bulk Operations

```
JobVacancyService::bulkUpdateApplicationStatus(vacancyId, userId, applicationIds[], newStatus) → int
```

Updates up to 1000 applications at once. Dispatches webhook for bulk action.

---

## 5. Interviews

### Interview Types

- `video` (default)
- `phone`
- `in_person`
- Custom types supported via string field

### Interview Flow

```
Employer proposes interview → Status: proposed → Candidate notified
  → Candidate accepts → Status: accepted → Employer notified
  → Candidate declines → Status: declined → Employer notified
  → Employer cancels → Status: cancelled → Candidate notified
  → Interview completed → Status: completed
```

### Key Methods

| Method | Actor | Description |
|--------|-------|-------------|
| `propose(applicationId, employerId, data)` | Employer | Create interview with type, date, duration, location |
| `accept(interviewId, candidateId, notes?)` | Candidate | Accept proposed interview |
| `decline(interviewId, candidateId, notes?)` | Candidate | Decline proposed interview |
| `cancel(interviewId, employerId)` | Employer | Cancel interview |

### Interview Data

| Field | Type | Description |
|-------|------|-------------|
| `interview_type` | varchar(50) | video, phone, in_person |
| `scheduled_at` | datetime | Interview date/time |
| `duration_mins` | int | Duration (default 60) |
| `location_notes` | text | Location or meeting link |
| `status` | varchar(30) | proposed, accepted, declined, cancelled, completed |
| `candidate_notes` | text | Candidate's notes |
| `interviewer_notes` | text | Interviewer's notes |

---

## 6. Interview Self-Scheduling

Employers can create time slots that candidates book directly.

### Slot Creation

```
JobInterviewSchedulingService::createSlots(jobId, employerId, [
    { start: "2026-04-01 09:00", end: "2026-04-01 10:00", type: "video", meeting_link: "..." },
    { start: "2026-04-01 10:30", end: "2026-04-01 11:30" }
]) → slot records[]
```

### Bulk Slot Generation

```
JobInterviewSchedulingService::bulkCreateSlots(jobId, employerId,
    dateFrom: "2026-04-01",
    dateTo: "2026-04-05",
    durationMinutes: 45,           // clamped 15-180
    dayConfig: {
        monday: { start: "09:00", end: "17:00" },
        wednesday: { start: "10:00", end: "16:00" },
        friday: { start: "09:00", end: "12:00" }
    }
) → slot records[]
```

Generates slots for each configured day within the date range. Only creates future slots.

### Candidate Booking

```
JobInterviewSchedulingService::bookSlot(slotId, candidateUserId) → slot | null
```

**Validations:**
- Slot exists and not already booked
- Slot hasn't passed
- Candidate is not the employer
- Updates: `is_booked=true`, `booked_by_user_id`, `booked_at`

### Slot Cancellation

```
cancelSlotBooking(slotId) → bool   // Frees the slot
deleteSlot(slotId) → bool          // Removes entirely
```

---

## 7. Job Offers

### Offer Flow

```
Employer creates offer → Status: pending → Candidate notified
  → Candidate accepts → Status: accepted
    → Application status → accepted
    → Vacancy status → filled
    → Webhook: job.offer.accepted
  → Candidate rejects → Status: rejected → Employer notified
  → Employer withdraws → Status: withdrawn → Candidate notified
  → Offer expires (expires_at passed) → Cannot be accepted
```

### Offer Data

| Field | Type | Description |
|-------|------|-------------|
| `salary_offered` | decimal(12,2) | Offered salary |
| `salary_currency` | varchar | Currency code |
| `salary_type` | varchar | hourly, annual, etc. |
| `start_date` | date | Proposed start date |
| `details` / `message` | text | Offer details/message |
| `expires_at` | datetime | Offer expiry |
| `responded_at` | datetime | When candidate responded |

### Constraints

- **One offer per application** (enforced by UNIQUE constraint)
- **Expiry check**: Accept validates `expires_at > now()`
- **Cascading on accept**: Application → `accepted`, Vacancy → `filled`

---

## 8. Skill Matching

### Match Percentage

```
JobVacancyService::calculateMatchPercentage(userId, jobId) → {
    percentage: 0-100,
    matched: string[],
    missing: string[],
    user_skills: string[],
    required_skills: string[]
}
```

**Algorithm:**
- Parses user skills and job skills into keyword arrays
- **Fuzzy matching**: substring match OR `similar_text()` at 75% threshold
- `percentage = (matched / required) × 100`
- Returns 100% if job has no required skills
- Returns 0% if user has no skills

### Qualification Assessment

```
JobVacancyService::getQualificationAssessment(userId, jobId) → {
    percentage: int,
    level: 'low' | 'moderate' | 'good' | 'excellent',
    total_required: int,
    total_matched: int,
    total_missing: int,
    breakdown: [...],              // Per-skill match detail
    commitment_notes: string,
    remote_available: bool,
    location_distance_km: ?float,  // Haversine distance
    salary_disclosed: bool,
    ai_summary: string             // Human-readable match summary
}
```

### Recommendation Engine

```
JobVacancyService::getRecommended(userId, limit=10) → vacancies[]
```

- Excludes already-applied jobs and user's own postings
- Scores via fuzzy skill matching (same algorithm)
- Orders: featured first, then by match_score DESC
- Limit: 1-20

---

## 9. Candidate Scorecards

Multi-reviewer candidate evaluation system.

### Scorecard Structure

```
JobScorecardService::upsert(applicationId, reviewerId, {
    criteria: [
        { label: "Communication", score: 8, max_score: 10 },
        { label: "Technical Skills", score: 7, max_score: 10 },
        { label: "Cultural Fit", score: 9, max_score: 10 },
        { label: "Experience", score: 6, max_score: 10 },
        { label: "Motivation", score: 8, max_score: 10 }
    ],
    notes: "Strong candidate, good cultural fit"
}) → scorecard
```

- `total_score` = sum of all scores
- `max_score` = sum of all max_scores (default 100 if zero)
- Upsert on `(application_id, reviewer_id)` — one scorecard per reviewer per application
- Multiple reviewers can score the same candidate independently

---

## 10. Hiring Team

### Team Roles

| Role | Description |
|------|-------------|
| `reviewer` | Can view applications and submit scorecards |
| `manager` | Full access to pipeline management |

### Team Management

```
JobTeamService::addMember(vacancyId, ownerUserId, targetUserId, role='reviewer') → record | false
JobTeamService::removeMember(vacancyId, ownerUserId, targetUserId) → bool
JobTeamService::getMembers(vacancyId) → members[]
```

- Only vacancy owner can add/remove members
- Prevents self-add
- Validates same tenant
- Sends notification to added member
- Upsert on `(vacancy_id, user_id)` — unique constraint

---

## 11. Referrals

### Referral Token

```
JobReferralService::getOrCreate(vacancyId, referrerUserId?) → {
    ref_token: string,   // 32-char random token
    vacancy_id: int,
    referrer_user_id: ?int
}
```

### Conversion Tracking

When a referred user applies:
```
JobReferralService::markApplied(refToken, appliedUserId)
  → Sets applied=true, referred_user_id, applied_at
```

### Referral Stats

```
JobReferralService::getStats(vacancyId) → {
    total_shares: int,
    converted_applications: int
}
```

---

## 12. Job Templates

Reusable job posting templates for employers.

### Template Operations

| Method | Description |
|--------|-------------|
| `list(userId)` | User's own + public templates, ordered by use_count |
| `create(userId, data)` | Create personal/public template |
| `get(templateId, userId)` | Get template + increment use_count |
| `delete(templateId, userId)` | Delete own template |

### Template Fields

Mirrors job vacancy fields: name, description, type, commitment, category, skills_required, is_remote, salary fields, hours_per_week, time_credits, benefits, tagline, is_public.

---

## 13. Pipeline Automation

Time-based rules that automate pipeline actions.

### Rule Configuration

| Field | Description |
|-------|-------------|
| `trigger_stage` | Stage to monitor (e.g., "screening") |
| `condition_days` | Days stalled before triggering |
| `action` | `move_stage`, `reject`, or `notify_reviewer` |
| `action_target` | Target stage (for move_stage) |
| `is_active` | Enable/disable |

### Example Rules

| Rule | Trigger | Condition | Action |
|------|---------|-----------|--------|
| Auto-advance | screening | 3 days | move_stage → reviewed |
| Auto-reject | applied | 30 days | reject |
| Stale alert | interview | 7 days | notify_reviewer |

### Execution

```
JobPipelineRuleService::runForVacancy(vacancyId) → int (actioned count)
```

For each active rule:
1. Calculate cutoff = now - condition_days
2. Find applications in trigger_stage with updated_at <= cutoff
3. Execute action (move, reject, or notify)
4. Update rule.last_run_at

---

## 14. Talent Search

Opt-in candidate database for proactive employer sourcing.

### Candidate Search

```
CandidateSearchService::search({
    keywords?: string,
    skills?: string[],
    location?: string,
    limit?: int,         // max 100
    offset?: int
}) → { items, total }
```

**Search targets:**
- `bio`, `skills`, `resume_headline`, `resume_summary`, `CONCAT(first_name, last_name)`

**Requirements:**
- User must have `resume_searchable = 1` (opt-in)
- User must be `status = 'active'`

### Candidate Profile

```
CandidateSearchService::getCandidateProfile(userId) → {
    id, name, avatar_url, headline, summary,
    skills[], location, bio, last_active, member_since
}
```

### Resume Visibility Toggle

```
CandidateSearchService::updateResumeVisibility(userId, searchable: bool) → bool
```

Users table extended with: `resume_searchable`, `resume_headline`, `resume_summary`.

---

## 15. Job Alerts

Subscription-based notifications for new job matches.

### Alert Configuration

| Field | Description |
|-------|-------------|
| `keywords` | Search keywords (up to 500 chars) |
| `categories` | Category filter |
| `type` | paid, volunteer, timebank |
| `commitment` | full_time, part_time, flexible, one_off |
| `location` | Location filter |
| `is_remote_only` | Remote-only flag |
| `is_active` | Pause/resume |

### Alert Operations

| Method | Description |
|--------|-------------|
| `subscribeAlert(userId, data)` | Create alert subscription |
| `deleteAlert(id, userId)` | Permanently delete |
| `unsubscribeAlert(id, userId)` | Pause (is_active=false) |
| `resubscribeAlert(id, userId)` | Resume (is_active=true) |

### Email Notifications

`JobAlertEmailService::sendImmediateAlert()` sends responsive HTML emails with:
- Job title, location, commitment, type, deadline
- "View Job" button
- Unsubscribe link

---

## 16. Job Feeds (RSS & Google Jobs)

Public feeds for search engine consumption. Cached for 15 minutes.

### RSS 2.0 Feed

```
GET /v2/jobs/feed.xml → application/rss+xml
```

Standard RSS 2.0 with `<channel>`, `<item>` elements. Each item includes title, link, description (CDATA), pubDate, guid, category.

### Google Jobs JSON Feed

```
GET /v2/jobs/feed.json → application/json
```

Schema.org JobPosting format:

```json
{
  "jobs": [{
    "@context": "https://schema.org",
    "@type": "JobPosting",
    "title": "...",
    "description": "...",
    "datePosted": "2026-03-29",
    "hiringOrganization": { "@type": "Organization", "name": "..." },
    "employmentType": "FULL_TIME",
    "jobLocation": { ... },
    "baseSalary": { ... },
    "validThrough": "2026-04-30",
    "skills": "..."
  }]
}
```

**Commitment → Employment Type mapping:**
- full_time → FULL_TIME
- part_time → PART_TIME
- flexible → OTHER
- one_off → TEMPORARY

---

## 17. Moderation & Spam Detection

### Moderation Workflow

```
Job submitted → Spam analysis (Agent B scores content) →
  If moderation enabled for tenant:
    moderation_status = pending_review → Admin queue
      → Admin approves → moderation_status = approved, status = open
      → Admin rejects → moderation_status = rejected, status = closed
      → Admin flags → moderation_status = flagged (stays in queue)
  If moderation disabled:
    Published immediately
```

### Spam Detection

Jobs are analyzed on create and update with a spam_score (0-100):
- Content analysis for suspicious patterns
- Link density checking
- Posting frequency analysis
- Account age consideration
- Results stored in `spam_score` (int) and `spam_flags` (JSON array)

### Spam Flags

| Flag | Description |
|------|-------------|
| `duplicate_content` | Similar to existing job |
| `suspicious_links` | Excessive or suspicious URLs |
| `excessive_posting_rate` | Too many jobs in short time |
| `suspicious_patterns` | Content pattern matching |
| `new_account` | Very new account posting |

### Moderation Stats

```
JobModerationService::getModerationStats(tenantId) → {
    pending: int,
    approved_today: int,
    rejected_today: int,
    flagged: int,
    total_reviewed: int
}
```

---

## 18. Bias Audit

Process-based hiring analytics (no demographic data collected).

### Pipeline Stages Analyzed

`applied` → `screening` → `interview` → `offer` → `accepted`

### Report Generation

```
JobBiasAuditService::generateReport(tenantId, jobId?, dateFrom?, dateTo?) → {
    period: { from, to },
    total_applications: int,
    funnel: [{ stage, count, percentage }],
    rejection_rates: [{ stage, rejections, total_at_stage, rate }],
    avg_time_in_stage: [{ stage, avg_days }],
    skills_match_correlation: { accepted_count, rejected_count, acceptance_rate },
    source_effectiveness: {
        direct: { total, accepted, acceptance_rate },
        referral: { total, accepted, acceptance_rate }
    },
    hiring_velocity_days: ?float
}
```

### Metrics Explained

| Metric | What It Measures |
|--------|------------------|
| **Funnel** | Drop-off at each pipeline stage |
| **Rejection rates** | Rejections per stage as % of entries |
| **Time in stage** | Average days applications spend in each stage |
| **Skills correlation** | Acceptance rate (does skill matching predict outcomes?) |
| **Source effectiveness** | Direct vs referral application success rates |
| **Hiring velocity** | Average days from job creation to offer acceptance |

Default lookback: 12 months if no date range specified.

---

## 19. Analytics

Per-job performance analytics for employers.

```
JobVacancyService::getAnalytics(jobId, userId) → {
    job_id: int,
    total_views: int,
    unique_viewers: int,
    total_applications: int,
    conversion_rate: float,           // applications / views × 100
    avg_time_to_apply_hours: float,
    time_to_fill_days: ?float,
    views_by_day: [{ date, count }],  // 30-day rolling
    applications_by_stage: [{ stage, count }],
    weekly_trend: [{ week, count }],  // 8-week rolling
    referral_stats: { total_shares, converted },
    scorecard_avg: ?float
}
```

### View Tracking

```
JobVacancyService::incrementViews(id, userId?)
```

Logs individual views in `job_vacancy_views` with timestamp and user_id. Used for unique viewer counting and time-series analysis.

---

## 20. Saved Jobs & Profiles

### Saved Jobs

```
saveJob(id, userId)    // Bookmark (idempotent)
unsaveJob(id, userId)  // Remove bookmark
getSavedJobs(userId)   // Cursor-paginated list
```

### Saved Profiles

Reusable candidate profile for quick applications:

```
JobSavedProfileService::save(userId, {
    cv_path: string,
    cv_filename: string,
    cv_size: int,
    headline: string,
    cover_text: string
}) → profile
```

Upsert on `(tenant_id, user_id)` — one profile per user per tenant.

---

## 21. Employer Branding

Job vacancies support employer branding fields:

| Field | Description |
|-------|-------------|
| `tagline` | Short company tagline |
| `video_url` | Company video URL |
| `culture_photos` | Culture photo URLs |
| `company_size` | Company size label |
| `benefits` | Benefits list (JSON array) |

Displayed on job detail pages and employer brand pages (`/jobs/employers/:userId`).

---

## 22. Expiry & Renewal

### Expiry Notifications

`JobExpiryNotificationService::notifyExpiringSoon()` runs daily:
- Finds open jobs with deadline within 7 days
- Sends in-app notification AND HTML email to poster
- Multi-tenant safe (iterates all active tenants)

### Job Renewal

```
JobVacancyService::renewJob(id, userId, days=30) → bool
```

- Extends deadline by N days (base: current deadline if future, else now)
- Resets status to `open`
- Clears `expired_at`
- Increments `renewal_count`

---

## 23. Donations & Giving Days

Monetary donations linked to job vacancies (via Stripe):

| Feature | Description |
|---------|-------------|
| Giving day campaigns | Time-limited fundraising events |
| Donation tracking | Amount, currency, payment method, status |
| Progress tracking | Raised vs goal with percentage |
| Admin management | Create, update, export giving days |

Status flow: `pending` → `completed` (via Stripe webhook) / `refunded` / `failed`

---

## 24. GDPR Compliance

### Data Export

```
JobGdprService::exportUserData(userId) → {
    applications: [...],
    interviews: [...],
    offers: [...],
    alerts: [...],
    saved_profile: { ... }
}
```

### Data Erasure

```
JobGdprService::eraseUserData(userId) → bool
```

**Selective anonymization** (inside DB transaction):
- Applications: `message=NULL`, `reviewer_notes=NULL`, `cv_path=NULL`, `cv_filename=NULL`, `cv_size=NULL`
- Deletes: JobAlert records, JobSavedProfile records
- **Keeps structural data** (application/interview records) with no PII — required for analytics integrity

---

## 25. Database Schema

### Tables (16)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `job_vacancies` | Job postings | title, type, commitment, salary, status, moderation_status, spam_score |
| `job_vacancy_applications` | Applications | vacancy_id, user_id, status, stage, cv_path, reviewer_notes |
| `job_application_history` | Status audit trail | application_id, from_status, to_status, changed_by |
| `job_vacancy_views` | View tracking | vacancy_id, user_id, viewed_at, ip_hash |
| `job_vacancy_team` | Hiring team | vacancy_id, user_id, role |
| `job_interviews` | Interview records | application_id, interview_type, scheduled_at, status |
| `job_interview_slots` | Self-scheduling slots | job_id, slot_start, slot_end, is_booked, booked_by |
| `job_offers` | Job offers | application_id, salary_offered, status, expires_at |
| `job_scorecards` | Candidate evaluation | application_id, reviewer_id, criteria (JSON), total_score |
| `job_referrals` | Referral tokens | vacancy_id, ref_token, referrer_user_id, applied |
| `job_templates` | Reusable templates | user_id, name, all vacancy fields, is_public, use_count |
| `job_pipeline_rules` | Automation rules | vacancy_id, trigger_stage, condition_days, action |
| `job_alerts` | Alert subscriptions | user_id, keywords, type, commitment, is_active |
| `job_saved_profiles` | Candidate profiles | user_id, cv_path, headline, cover_text |
| `saved_jobs` | Bookmarked jobs | user_id, job_id, saved_at |
| `job_moderation_logs` | Moderation audit | vacancy_id, admin_id, action, previous_status |

### Key Constraints

| Table | Constraint |
|-------|-----------|
| `job_vacancy_applications` | UNIQUE (vacancy_id, user_id) — one application per user per job |
| `job_vacancy_team` | UNIQUE (vacancy_id, user_id) — one role per member per job |
| `saved_jobs` | UNIQUE (user_id, job_id) — one bookmark per user per job |
| `job_interview_slots.booked_by` | Single booking per slot |

### Indexes

Key composite indexes for performance:
- `(tenant_id, status)` on vacancies
- `(tenant_id, user_id)` on vacancies, applications
- `(tenant_id, is_featured, featured_until)` on vacancies
- `(tenant_id, deadline, status)` on vacancies
- `(latitude, longitude)` for geospatial search
- `(vacancy_id, status)` on applications
- `(tenant_id, status, created_at)` on applications

---

## 26. API Reference

### Core Job Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/jobs` | Browse jobs with filters | 60/min |
| POST | `/v2/jobs` | Create job | 5/min |
| GET | `/v2/jobs/{id}` | Job detail (increments views) | 60/min |
| PUT | `/v2/jobs/{id}` | Update job (owner) | 10/min |
| DELETE | `/v2/jobs/{id}` | Delete job (owner) | 5/min |
| GET | `/v2/jobs/recommended` | Recommended jobs | 30/min |
| POST | `/v2/jobs/{id}/renew` | Renew job deadline | 5/min |

### Application Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/v2/jobs/{id}/apply` | Apply with CV upload | 5/min |
| GET | `/v2/jobs/my-applications` | My applications | 30/min |
| GET | `/v2/jobs/{id}/applications` | Job applications (owner) | 30/min |
| PUT | `/v2/jobs/applications/{id}` | Update status | 10/min |
| GET | `/v2/jobs/applications/{id}/history` | Status history | 30/min |
| GET | `/v2/jobs/applications/{id}/cv` | Download CV | 20/min |
| POST | `/v2/jobs/{id}/applications/bulk-status` | Bulk update | — |
| GET | `/v2/jobs/{id}/applications/export-csv` | Export CSV | 10/min |

### Interview Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/v2/jobs/applications/{id}/interview` | Propose interview | 10/min |
| PUT | `/v2/jobs/interviews/{id}/accept` | Accept interview | 10/min |
| PUT | `/v2/jobs/interviews/{id}/decline` | Decline interview | 10/min |
| DELETE | `/v2/jobs/interviews/{id}` | Cancel interview | 10/min |
| GET | `/v2/jobs/{id}/interviews` | Job interviews (owner) | 30/min |
| GET | `/v2/jobs/my-interviews` | My interviews | 30/min |

### Self-Scheduling Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/jobs/{id}/interview-slots` | Available slots | 30/min |
| POST | `/v2/jobs/{id}/interview-slots` | Create slots | 10/min |
| POST | `/v2/jobs/{id}/interview-slots/bulk` | Bulk create | 5/min |
| POST | `/v2/jobs/interview-slots/{id}/book` | Book slot | 10/min |
| DELETE | `/v2/jobs/interview-slots/{id}/book` | Cancel booking | 10/min |
| DELETE | `/v2/jobs/interview-slots/{id}` | Delete slot | 10/min |

### Offer Endpoints

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| POST | `/v2/jobs/applications/{id}/offer` | Send offer | 10/min |
| PUT | `/v2/jobs/offers/{id}/accept` | Accept offer | 10/min |
| PUT | `/v2/jobs/offers/{id}/reject` | Reject offer | 10/min |
| DELETE | `/v2/jobs/offers/{id}` | Withdraw offer | 10/min |
| GET | `/v2/jobs/applications/{id}/offer` | Get offer | 30/min |
| GET | `/v2/jobs/my-offers` | My offers | 30/min |

### Matching & Assessment

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/jobs/{id}/match` | Skill match % | 30/min |
| GET | `/v2/jobs/{id}/qualified` | Qualification assessment | 20/min |
| GET | `/v2/jobs/salary-benchmark` | Salary suggestion | — |
| POST | `/v2/jobs/check-duplicate` | Duplicate detection | 30/min |
| POST | `/v2/jobs/generate-description` | AI description | 10/min |
| GET | `/v2/jobs/applications/{id}/parse-cv` | AI CV parsing | 5/min |

### Team, Referrals, Scorecards

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/DELETE | `/v2/jobs/{id}/team` | Manage hiring team |
| POST | `/v2/jobs/{id}/referral` | Get/create referral token |
| GET | `/v2/jobs/{id}/referral-stats` | Referral stats |
| PUT | `/v2/jobs/applications/{id}/scorecard` | Submit scorecard |
| GET | `/v2/jobs/applications/{id}/scorecards` | Get scorecards |

### Templates, Alerts, Saved

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/v2/jobs/templates` | List/create templates |
| GET/DELETE | `/v2/jobs/templates/{id}` | Get/delete template |
| GET/POST | `/v2/jobs/alerts` | List/create alerts |
| DELETE/PUT | `/v2/jobs/alerts/{id}` | Delete/pause/resume alert |
| POST/DELETE | `/v2/jobs/{id}/save` | Save/unsave job |
| GET | `/v2/jobs/saved` | Saved jobs list |
| GET/PUT | `/v2/jobs/saved-profile` | Saved candidate profile |

### Pipeline Rules

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/jobs/{id}/pipeline-rules` | List rules |
| POST | `/v2/jobs/{id}/pipeline-rules` | Create rule |
| DELETE | `/v2/jobs/pipeline-rules/{id}` | Delete rule |
| POST | `/v2/jobs/{id}/pipeline-rules/run` | Execute rules |

### Talent Search

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| GET | `/v2/jobs/talent-search` | Search candidates | 30/min |
| GET | `/v2/jobs/talent-search/{id}` | Candidate profile | 60/min |

### Feeds (Public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/jobs/feed.xml` | RSS 2.0 feed |
| GET | `/v2/jobs/feed.json` | Google Jobs JSON |

### GDPR

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/jobs/gdpr-export` | Export user data |
| DELETE | `/v2/jobs/gdpr-erase-me` | Anonymize/erase data |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v2/admin/jobs` | List all jobs |
| GET/DELETE | `/v2/admin/jobs/{id}` | View/delete job |
| POST | `/v2/admin/jobs/{id}/feature` | Feature job |
| POST | `/v2/admin/jobs/{id}/unfeature` | Unfeature job |
| GET | `/v2/admin/jobs/{id}/applications` | Job applications |
| PUT | `/v2/admin/jobs/applications/{id}` | Update status |
| GET | `/v2/admin/jobs/moderation-queue` | Pending jobs |
| POST | `/v2/admin/jobs/{id}/approve` | Approve job |
| POST | `/v2/admin/jobs/{id}/reject` | Reject job |
| POST | `/v2/admin/jobs/{id}/flag` | Flag job |
| GET | `/v2/admin/jobs/moderation-stats` | Moderation stats |
| GET | `/v2/admin/jobs/spam-stats` | Spam stats |
| GET | `/v2/admin/jobs/bias-audit` | Bias audit report |

---

## 27. Frontend Experience

### Pages (11)

| Page | Path | Purpose |
|------|------|---------|
| **JobsPage** | `/jobs` | Browse, search, filter jobs with 3 tabs (Browse/Saved/My Postings) |
| **JobDetailPage** | `/jobs/:id` | Full job detail with match %, applications, interviews, offers |
| **CreateJobPage** | `/jobs/create` | Job posting form with templates, AI, duplicate detection |
| **MyApplicationsPage** | `/jobs/applications` | Candidate's application history with status tracking |
| **JobKanbanPage** | `/jobs/:id/kanban` | Drag-and-drop pipeline management board |
| **JobAnalyticsPage** | `/jobs/:id/analytics` | Job performance metrics and charts |
| **JobAlertsPage** | `/jobs/alerts` | Alert subscription management |
| **TalentSearchPage** | `/jobs/talent-search` | Candidate database search |
| **BiasAuditPage** | `/jobs/bias-audit` | Admin hiring fairness analytics |
| **EmployerBrandPage** | `/jobs/employers/:userId` | Employer profile with open listings |
| **EmployerOnboardingPage** | `/jobs/employer-onboarding` | 4-step wizard for first-time employers |

### JobsPage (Browse)

- Free text search with 300ms debounce
- Filter dropdowns: type, commitment, sort
- Remote-only toggle
- Featured jobs with star badge and gradient styling
- Salary display with `Intl.NumberFormat` currency formatting
- Match percentage badges (color-coded: >=75% green, >=50% blue, >=40% amber)
- Cursor-based pagination (20 items per page)
- Three tabs: Browse, Saved Jobs, My Postings

### JobDetailPage (2,530 lines)

The most complex page in the module:
- Match percentage badge with color coding
- "Am I Qualified?" modal with skill-by-skill breakdown
- Application pipeline stage visualization
- Inline interview response cards (accept/decline)
- Inline offer response cards (accept/decline)
- Owner management bar (edit, renew, delete, analytics)
- Employer branding section (tagline, video, company size, benefits)
- Skills matching indicators per skill
- JSON-LD JobPosting schema for SEO
- CV upload with multipart form data
- Similar jobs section
- EU Pay Transparency salary display

### CreateJobPage (1,670 lines)

- Salary benchmark suggestion (600ms debounce on title change)
- Job templates (load/save/delete)
- AI-generated descriptions (Sparkles icon)
- Duplicate detection (800ms debounce)
- Hiring team management (add/remove members)
- Blind hiring toggle
- Job preview modal
- Unsaved changes warning
- EU Pay Transparency salary validation for paid jobs

### JobKanbanPage (Drag-and-Drop Pipeline)

6-column board: Applied → Screening → Interview → Offer → Accepted → Rejected

- Native HTML5 drag-and-drop (dragstart, dragover, drop)
- Optimistic card movement
- CV download per applicant
- Schedule interview modal
- Send offer modal
- Scorecard modal (5 criteria, 1-10 scale each)
- Bulk selection with checkboxes
- Bulk status update dropdown

### BiasAuditPage (Admin)

- Date range picker and job filter
- Application funnel chart (Recharts BarChart)
- Rejection rate table by stage with color chips
- Time-in-stage grid with background colors
- Skills match correlation
- Source effectiveness (direct vs referral)
- Hiring velocity metric

### Employer Onboarding (4-Step Wizard)

1. **Welcome** — Introduction with icon
2. **Organization Profile** — Name, tagline, size, website
3. **Post First Job** — Simplified job creation form
4. **Success** — Confirmation with tips

Progress persisted in localStorage (`nexus_employer_onboarding`).

### Admin Modules

**JobsAdmin** — Job listing table with feature toggle, application panel with status updates

**JobModerationQueue** — Pending jobs queue with spam score display, approve/reject/flag actions, moderation stats cards

---

## 28. Admin Panel

### Admin Capabilities

| Capability | Endpoint | Description |
|-----------|----------|-------------|
| View all jobs | `GET /admin/jobs` | Paginated list with search/status filter |
| Delete jobs | `DELETE /admin/jobs/{id}` | Remove job |
| Feature/unfeature | `POST /admin/jobs/{id}/feature` | Promote to featured (1-90 days) |
| View applications | `GET /admin/jobs/{id}/applications` | All applications for a job |
| Update status | `PUT /admin/jobs/applications/{id}` | Change application status |
| Moderation queue | `GET /admin/jobs/moderation-queue` | Pending approval queue |
| Approve job | `POST /admin/jobs/{id}/approve` | Approve and publish |
| Reject job | `POST /admin/jobs/{id}/reject` | Reject with reason |
| Flag job | `POST /admin/jobs/{id}/flag` | Flag for review |
| Moderation stats | `GET /admin/jobs/moderation-stats` | Queue metrics |
| Spam stats | `GET /admin/jobs/spam-stats` | Spam detection metrics |
| Bias audit | `GET /admin/jobs/bias-audit` | Hiring fairness report |

---

*This report documents the complete jobs module engine as implemented in Project NEXUS v1.5.0. For the most current implementation, refer to the source files listed in Section 2.*
