# ON DELETE Cascade Policy Review — Project NEXUS

**Date:** 2026-04-12
**Scope:** TD10 — FK cascade policy audit
**Source:** `database/schema/mysql-schema.sql`
**Totals:** 319 FK constraints — 277 CASCADE, 40 SET NULL, 2 NO ACTION, 0 RESTRICT.

This document categorises every FK that targets `users`, `user_profiles`, `admin_users`, `tenants`, or other identity/audit parents, and recommends a policy per FK.

Categories:
- **A** — CASCADE is correct (keep). Personal/ephemeral data that MUST vanish with the user for GDPR.
- **B** — SHOULD be SET NULL (fix). Historical / audit / financial records must outlive the user.
- **C** — SHOULD be RESTRICT (fix). Parent deletion must be blocked if children exist.
- **D** — Needs product/legal decision.

---

## Category A — CASCADE is correct (keep)

These FKs correctly CASCADE on user/tenant deletion. Data is personal to the user and carries no audit/legal obligation.

| Child table | Column | Reason |
|---|---|---|
| `push_subscriptions` | `user_id` | Personal device token — must be removed. |
| `notification_settings` | `user_id` | Personal preference. |
| `notification_queue` | `user_id` | Undelivered pending notifications. |
| `notifications` | `user_id` | Personal inbox. |
| `user_preferences` / `user_settings` / `user_distance_preference` / `user_safeguarding_preferences` / `user_messaging_restrictions` / `user_interests` / `user_categories` / `user_category_affinity` | `user_id` | Personal preference records. |
| `match_cache` / `match_preferences` / `match_history` / `match_approvals` | `user_id` / `listing_owner_id` | Ephemeral recommendation state. |
| `nexus_score_cache` / `nexus_score_history` / `nexus_score_milestones` | `user_id` | Personal score state, recomputable. |
| `webauthn_credentials` | `user_id` | Personal auth credential. |
| `social_identities` | `user_id` | OAuth binding. |
| `revoked_tokens` | `user_id` | Personal session token. |
| `identity_verification_sessions` | `user_id` | Personal KYC session (PII). |
| `user_badges` | `user_id` | Personal achievement. |
| `search_feedback` | `user_id` | Personal UX signal. |
| `notification_queue` / `event_reminders` / `event_waitlist` / `event_rsvps` / `event_attendance` | `user_id` | Personal event state. |
| `connections` | `requester_id`, `receiver_id` | Personal relationship graph — removed on deletion. |
| `likes`, `post_likes`, `reactions`, `message_reactions` | `user_id` | Personal engagement signal. |
| `mentions` | `mentioning_user_id`, `mentioned_user_id` | Personal mention record. |
| `group_members` | `user_id` | Personal group membership. |
| `user_first_contacts` | `user1_id`, `user2_id` | Personal contact graph. |
| `proposal_votes` | `user_id` | Personal vote record. |
| `insurance_certificates` | `user_id` | Personal certificate doc. |
| `vetting_records` | `user_id` | Personal vetting artefact (personal data). |
| `vol_shift_checkins` / `vol_shift_group_members` / `vol_shift_waitlist` / `vol_shift_swap_requests` / `vol_certificates` / `vol_emergency_alert_recipients` | `user_id` / from/to | Personal shift state; see Category D for `vol_logs`. |
| `tenant_invite_code_uses` | `user_id` | Personal usage record. |

---

## Category B — SHOULD be SET NULL (FIX)

Historical/audit/financial records must outlive the user with author = NULL ("deleted user").

| Child table | Column | Current | Recommended | Nullable? | Reason | Migration status |
|---|---|---|---|---|---|---|
| `admin_actions` | `admin_id` | CASCADE | **SET NULL** | NO | Admin audit trail — who did what, must survive admin deletion. | SKIP (schema change required) |
| `transactions` | `giver_id` | CASCADE | **SET NULL** | **YES** | Financial ledger — 7 year audit. Giver can become NULL. | **INCLUDED** |
| `reviews` | `reviewer_id` | CASCADE | **SET NULL** | NO | Review must persist for the reviewee; display "[deleted user]". | SKIP (NOT NULL) |
| `reviews` | `receiver_id` | CASCADE | **SET NULL** | NO | Review should persist for the reviewer. | SKIP (NOT NULL) |
| `exchange_ratings` | `rater_id` | CASCADE | **SET NULL** | NO | Rating must persist for the ratee. | SKIP (NOT NULL) |
| `exchange_ratings` | `rated_id` | CASCADE | **SET NULL** | NO | Rating should persist for the rater. | SKIP (NOT NULL) |
| `messages` | `receiver_id` | CASCADE | **SET NULL** | NO | Other party's copy of the conversation must survive. | SKIP (NOT NULL) |
| `broker_message_copies` | `sender_id` / `receiver_id` | CASCADE | **SET NULL** | NO | Broker audit copies (anti-abuse). | SKIP (NOT NULL) |
| `broker_review_archives` | `decided_by` | CASCADE | **SET NULL** | NO | Moderation audit — who decided. | SKIP (NOT NULL) |
| `credit_donations` | `donor_id` | CASCADE | **SET NULL** | NO | Financial donation record. | SKIP (NOT NULL) |
| `listings` | `user_id` | CASCADE | **SET NULL** | NO | Listings had engagement from others — decision: keep or anonymise? Leans SET NULL. | SKIP (NOT NULL) |
| `feed_posts` | `user_id` | CASCADE | **SET NULL** | NO | Public content engaged with by others. | SKIP (NOT NULL) |
| `posts` | `author_id` | CASCADE | **SET NULL** | NO | Blog posts — published content. | SKIP (NOT NULL) |
| `comments` | `user_id` | CASCADE | **SET NULL** | NO | Comments on threads engaged by others. | SKIP (NOT NULL) |
| `vol_logs` | `user_id` | CASCADE | **SET NULL** | NO | Volunteering hours log — audit trail. | SKIP (NOT NULL) |
| `newsletters` | `created_by` | CASCADE | **SET NULL** | NO | Sent newsletters — historical record. | SKIP (NOT NULL) |
| `tenant_invite_codes` | `created_by` | CASCADE | **SET NULL** | NO | Invite usage attribution. | SKIP (NOT NULL) |
| `event_series` | `created_by` | CASCADE | **SET NULL** | NO | Recurring event creator. | SKIP (NOT NULL) |
| `recurring_shift_patterns` | `created_by` | CASCADE | **SET NULL** | NO | Volunteering shift creator. | SKIP (NOT NULL) |
| `search_logs` | `user_id` | CASCADE | **SET NULL** | **YES** | Analytics history — retain anonymised. | **INCLUDED** |
| `community_fund_transactions` | `admin_id` / `user_id` | SET NULL | (already correct) | YES | Already correct — reference. | n/a |

**Summary:**
Of ~20 Category B FKs, only **2** can be migrated without schema change:
1. `transactions.giver_id` — nullable, CASCADE → SET NULL.
2. `search_logs.user_id` — nullable, CASCADE → SET NULL.

The other 18 require a product/legal decision and a NULL-ability schema change (2-step migration: ALTER column to nullable, then alter FK). These are listed explicitly so they can be tracked as a follow-up.

---

## Category C — SHOULD be RESTRICT (FIX)

Parent-table deletions should fail if children exist.

| Child table | Column | Parent | Current | Recommended | Reason |
|---|---|---|---|---|---|
| `users` | `tenant_id` | `tenants` | CASCADE | RESTRICT (or block via app) | Tenant deletion must be a managed flow; CASCADE silently deletes every user. |
| Various `*.tenant_id` | — | `tenants` | CASCADE | RESTRICT | Same rationale — deleting a tenant should require explicit sign-off. In practice the app-level flow drains children first, then CASCADE is acceptable; document that tenant deletion goes through `TenantDeletionService`. |
| `*.role_id` (if present) | — | `roles` | n/a | RESTRICT | Prevent orphaning users with deleted role. (No role FK found in current schema — N/A.) |

**Decision needed:** The product has no "delete tenant" self-service flow today — all tenant deletion is a manual, administered operation that pre-drains users. Switching `users.tenant_id` to RESTRICT would surface this to anyone trying to delete a tenant directly in the DB. Worth considering but out of scope for this pass.

---

## Category D — Needs product/legal decision

Every FK not clearly A/B/C. One-line questions:

- `deliverables.owner_id` — If a member leaves a funded project, does the deliverable transfer or vanish?
- `proposals.user_id` — Governance proposals: preserve for historical record, or remove author's vote trail entirely?
- `groups.owner_id` — Orphaned groups: auto-transfer to tenant admin, archive, or delete?
- `group_posts.user_id` / `group_discussions.user_id` — Public group content — CASCADE currently.
- `exchange_requests.*` — In-flight transactions with deleted users — current CASCADE OK?
- `event_series.created_by`, `recurring_shift_patterns.created_by` — Series should survive creator deletion.
- `identity_verification_sessions.user_id` — KYC PII — is CASCADE the correct GDPR answer or do we need a retention period?
- `vetting_records.user_id` — Safeguarding — legal retention vs GDPR right-to-erasure.
- `insurance_certificates.user_id` — Same — legal retention?
- All `*.tenant_id` cascades — deferred pending RESTRICT decision above.

---

## Recommendation summary

1. **Apply the migration** `2026_04_12_000003_fix_fk_on_delete_policies.php` for the 2 clearly-safe wins (transactions.giver_id, search_logs.user_id).
2. **Product/legal meeting** to decide on ~18 Category B FKs whose columns are NOT NULL. These need a 2-step migration (nullable + policy change).
3. **Follow-up ticket** for Category C tenant-deletion hardening.
4. **Run `scripts/audit-fk-cascade.php`** periodically to detect drift from policy.
