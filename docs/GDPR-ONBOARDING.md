# GDPR Consent System Documentation

## Overview

This document describes how the GDPR consent system works, including:
- How consents are recorded during registration
- Version tracking and forced re-acceptance
- Admin tools for managing consents
- Troubleshooting guide

## System Architecture

### Database Tables

#### `consent_types`
Defines the types of consent that can be collected.

| Column | Description |
|--------|-------------|
| `slug` | Unique identifier (e.g., `terms_of_service`) |
| `name` | Display name |
| `current_version` | Current version number (e.g., `1.0`, `2.0`) |
| `current_text` | Full legal text of the consent |
| `is_required` | If true, users MUST accept to use the platform |
| `is_active` | Whether this consent type is currently in use |

#### `user_consents`
Records each user's acceptance of consent types.

| Column | Description |
|--------|-------------|
| `user_id` | The user who gave consent |
| `consent_type` | Links to `consent_types.slug` |
| `consent_version` | Version user accepted (e.g., `1.0`) |
| `consent_given` | 1 = accepted, 0 = declined/pending |
| `consent_hash` | SHA-256 hash of text at time of consent |
| `ip_address` | User's IP for audit trail |
| `user_agent` | Browser info for audit trail |
| `given_at` | Timestamp when consent was given |
| `withdrawn_at` | Timestamp if consent was later withdrawn |

#### `consent_version_history`
Audit trail of consent text changes.

| Column | Description |
|--------|-------------|
| `consent_type_slug` | The consent type that changed |
| `version` | Version number |
| `text_content` | Full text of that version |
| `text_hash` | SHA-256 hash for comparison |
| `effective_from` | When this version became active |

## Flow: Registration

When a user registers:

1. User fills out registration form with GDPR checkbox
2. `AuthController::register()` creates the user
3. Three consent records are created via `GdprService::recordConsent()`:
   - `terms_of_service`
   - `privacy_policy`
   - `marketing_email`
4. Each record includes IP address, user agent, and timestamp

**File:** `src/Controllers/AuthController.php` (lines 257-274)

## Flow: Version Mismatch Detection

When a user navigates to any page:

1. `views/layouts/consent_check.php` is included in the header
2. It calls `GdprService::getOutdatedRequiredConsents()`
3. This checks if user's `consent_version` < `consent_types.current_version`
4. If outdated consents found, user is redirected to `/consent-required`
5. Admins and super admins are exempt (to prevent lockout)

**File:** `views/layouts/consent_check.php`

## Flow: Re-Consent

When user is redirected to `/consent-required`:

1. `ConsentController::required()` displays the re-consent page
2. Page shows all outdated consents with full text
3. User must check all boxes to enable submit button
4. On submit, AJAX POST to `/consent/accept`
5. `GdprService::acceptMultipleConsents()` records new consents
6. User is redirected to dashboard

**Files:**
- `src/Controllers/ConsentController.php`
- `views/modern/consent/required.php`
- `views/civicone/consent/required.php`

## Admin Workflow: Updating Terms

When you need to update terms/privacy policy:

### Step 1: Update the Consent Type

1. Go to `/admin/enterprise/gdpr/consents`
2. Click on the consent type to edit (e.g., "Terms of Service")
3. Update the `current_version` field (e.g., `1.0` → `2.0`)
4. Update the `current_text` field with new legal text
5. Save changes

### Step 2: The trigger auto-logs the change

The database trigger `consent_version_change_log` automatically creates a record in `consent_version_history` with the new version.

### Step 3: Users are prompted on next login

Any user whose `user_consents.consent_version` < new `consent_types.current_version` will be redirected to the re-consent page.

## Admin Workflow: Backfilling Existing Users

If you add a new required consent type, existing users won't have records:

1. Go to `/admin/enterprise/gdpr/consents`
2. Use the "Backfill Consents" tool
3. Select the consent type
4. Click "Backfill"

This creates records with `consent_given=0` for all users who don't have one. They'll be prompted to accept on their next login.

**Endpoint:** `POST /admin/enterprise/gdpr/consents/backfill`

## GDPR Dashboard Statistics

The dashboard at `/admin/enterprise/gdpr/consents` shows:

| Metric | Description |
|--------|-------------|
| Total Consents | Total consent records in database |
| Consent Rate | % of records where `consent_given=1` |
| Users with Consent | Distinct users who have accepted at least one consent |
| Pending Re-consent | Users with outdated required consents |

The dashboard also shows per-consent-type counts:
- **Granted Count:** Users who accepted this type
- **Denied Count:** Users who declined or haven't accepted

## Key Files Reference

### Controllers
- `src/Controllers/ConsentController.php` - User-facing consent pages
- `src/Controllers/Admin/EnterpriseController.php` - Admin GDPR dashboard

### Services
- `src/Services/Enterprise/GdprService.php` - Core consent logic

### Views
- `views/modern/consent/required.php` - Modern re-consent page
- `views/civicone/consent/required.php` - CivicOne re-consent page
- `views/modern/consent/decline.php` - Decline warning page
- `views/modern/admin/enterprise/gdpr/consents.php` - Admin dashboard

### Middleware
- `views/layouts/consent_check.php` - Version check middleware
- Included in:
  - `views/layouts/modern/header.php`
  - `views/layouts/civicone/partials/document-open.php`

### CSS
- `httpdocs/assets/css/consent-required.css` - Consent page styles

### Database
- `scripts/migrations/GDPR_COMPLIANCE.sql` - Main GDPR tables
- `scripts/migrations/add_consent_version_tracking.sql` - Version history

## Troubleshooting

### Dashboard shows 0 consents

**Cause:** No records in `user_consents` table for this tenant.

**Fix:**
1. Run the backfill script: `php scripts/backfill_gdpr_consents.php`
2. Or use admin backfill tool at `/admin/enterprise/gdpr/consents`

### Users not being prompted for re-consent

**Check:**
1. Is the consent type marked as `is_required = 1`?
2. Is the new version number higher than old? (Use semantic versioning: `1.0` < `2.0`)
3. Is `consent_check.php` included in the layout header?

**Debug:**
```sql
-- Check user's consent version vs current
SELECT uc.user_id, uc.consent_type, uc.consent_version, ct.current_version
FROM user_consents uc
JOIN consent_types ct ON uc.consent_type = ct.slug
WHERE uc.consent_version < ct.current_version AND ct.is_required = 1;
```

### Admin locked out

Admins and super admins are exempt from consent checks. If somehow locked out:

1. Direct database update:
```sql
UPDATE user_consents
SET consent_version = '2.0', consent_given = 1, given_at = NOW()
WHERE user_id = [admin_id] AND consent_type = 'terms_of_service';
```

### Consent version history not logging

**Check:** Is the trigger created?
```sql
SHOW TRIGGERS LIKE 'consent%';
```

**Fix:** Re-run the migration:
```bash
mysql -u root your_database < scripts/migrations/add_consent_version_tracking.sql
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/consent-required` | Display re-consent page |
| POST | `/consent/accept` | Accept consents (JSON body: `{consents: ['slug1', 'slug2']}`) |
| GET | `/consent/decline` | Show decline warning |
| GET | `/admin/enterprise/gdpr/consents` | Admin dashboard |
| POST | `/admin/enterprise/gdpr/consents/backfill` | Backfill tool |

## Security Considerations

1. **CSRF Protection:** All consent POST endpoints verify CSRF tokens
2. **Audit Trail:** IP address, user agent, and timestamp recorded
3. **Hash Verification:** SHA-256 hash stored to detect text tampering
4. **Admin Bypass:** Admins exempt from lockout to prevent system inaccessibility
5. **No Silent Updates:** Version changes require explicit user re-acceptance

## GDPR Compliance Notes

This system helps with GDPR Article 7 (Conditions for consent):

- **Freely given:** User can decline (with consequences)
- **Specific:** Each consent type is separate
- **Informed:** Full text shown before acceptance
- **Unambiguous:** Requires affirmative checkbox action
- **Withdrawable:** Users can withdraw via settings page
- **Documented:** Full audit trail with timestamps

For Article 30 (Records of processing), use the GDPR audit log at `/admin/enterprise/gdpr/audit`.
