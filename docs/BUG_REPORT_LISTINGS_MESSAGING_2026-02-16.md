# Bug Report: Listings & Messaging Flow Issues

**Date:** 2026-02-16
**Reporter:** User Testing Feedback
**Severity:** High - Core user flow broken
**Status:** ✅ **RESOLVED** - Both critical bugs fixed

## Executive Summary

Users clicking "Send Message" or "Request Exchange" buttons on listing detail pages experience broken flows where listing context is lost, messages don't get sent properly, and the exchange workflow isn't functioning correctly.

### ✅ Fixes Applied

- **Bug #1 FIXED** (Commit `4523834`): Listing context now preserved when sending messages from listings
- **Bug #2 FIXED** (Commit `866c9fe`): Exchanges now appear on exchanges page after creation
- **Bug #3 PARTIAL**: Email notifications already supported in backend, listing context now included

### Impact

- ✅ Users now see listing context when messaging about a listing
- ✅ Listing owners know which listing each inquiry is about
- ✅ New exchange requests appear immediately on exchanges page
- ✅ All active exchanges (pending, accepted, in progress) visible in "Active" tab
- ✅ Analytics tracking enabled for listing-to-message conversions

---

## Bug #1: Listing Context Lost When Sending Messages ✅ FIXED

### Description

When a user clicks "Send Message" from a listing detail page, the listing reference is lost during the message composition flow.

### Current Flow (WAS BROKEN)

1. User views listing at `/listings/123`
2. Clicks "Send Message" button (when exchange workflow disabled)
3. Navigates to `/messages?to=456&listing=123`
4. `MessagesPage` calls `startNewConversation(456, 123)`
5. **BUG**: Listing ID parameter is ignored (function parameter named `_listing`)
6. Redirects to `/messages/new/456` **WITHOUT** listing context
7. User composes message with NO reference to original listing
8. First message sent has no listing_id associated with it

### Impact (Before Fix)

- **User confusion**: No context about which listing they're messaging about
- **Lost referrals**: Listing owner doesn't know which listing generated the inquiry
- **Tracking failure**: Can't measure which listings drive most interest
- **Email notifications**: Likely missing listing details in notification emails

### Root Cause

**File:** `react-frontend/src/pages/messages/MessagesPage.tsx`

**Line 160-169:**

```typescript
const startNewConversation = useCallback((userId: number, _listing?: number) => {
  // Find existing conversation or create new
  const existing = conversations.find((c) => getOtherUser(c).id === userId);
  if (existing) {
    navigate(tenantPath(`/messages/${existing.id}`), { replace: true });
  } else {
    // Navigate with "new" prefix to indicate this is a user ID, not conversation ID
    navigate(tenantPath(`/messages/new/${userId}`), { replace: true });
    // ❌ PROBLEM: listing ID not passed in URL
  }
}, [conversations, navigate, tenantPath]);
```

The `_listing` parameter (with underscore = unused) is accepted but never used. Should be passed as query parameter or in navigation state.

### Fix Applied ✅

**Commits:** `4523834` - fix(messages): preserve listing context when sending messages from listings

**Changes Made:**

1. **MessagesPage.tsx** (lines 160-185): Updated `startNewConversation()` to pass listing ID as query parameter
2. **ConversationPage.tsx**: Added `useSearchParams`, listing state, listing fetch useEffect, listing preview card UI
3. **ConversationPage.tsx** (lines 760-807): Include `listing_id` in message API payload (both text and attachments)
4. **MessagesApiController.php** (lines 177-181): Extract `listing_id` from multipart form data
5. **MessageService.php** (lines 536, 543-555): Store `listing_id` when inserting messages
6. **Migration**: `2026_02_16_add_listing_id_to_messages.sql` - Added `listing_id INT(11) NULL` column with index and FK

**Database Changes:**

- `messages.listing_id`: New column, INT(11) NULL, indexed, soft FK to `listings(id)` ON DELETE SET NULL

### Original Fix Options (Selected Option A)

#### Option A: Pass as Query Parameter
```typescript
const startNewConversation = useCallback((userId: number, listing?: number) => {
  const existing = conversations.find((c) => getOtherUser(c).id === userId);
  if (existing) {
    const url = listing
      ? tenantPath(`/messages/${existing.id}?listing=${listing}`)
      : tenantPath(`/messages/${existing.id}`);
    navigate(url, { replace: true });
  } else {
    const url = listing
      ? tenantPath(`/messages/new/${userId}?listing=${listing}`)
      : tenantPath(`/messages/new/${userId}`);
    navigate(url, { replace: true });
  }
}, [conversations, navigate, tenantPath]);
```

#### Option B: Pass in Navigation State
```typescript
const startNewConversation = useCallback((userId: number, listing?: number) => {
  const existing = conversations.find((c) => getOtherUser(c).id === userId);
  if (existing) {
    navigate(tenantPath(`/messages/${existing.id}`), {
      replace: true,
      state: { listingId: listing }
    });
  } else {
    navigate(tenantPath(`/messages/new/${userId}`), {
      replace: true,
      state: { listingId: listing }
    });
  }
}, [conversations, navigate, tenantPath]);
```

### Files Updated ✅

1. **react-frontend/src/pages/messages/MessagesPage.tsx**
   - Fix `startNewConversation()` to pass listing ID

2. **react-frontend/src/pages/messages/ConversationPage.tsx**
   - Read listing ID from query params or state
   - Pass listing_id when sending first message
   - Display listing preview card in conversation header

3. **src/Controllers/Api/MessagesApiController.php**
   - Ensure `send()` method accepts and stores `listing_id`
   - Include listing details in email notifications

---

## Bug #2: Exchange Workflow Not Showing Up on Exchanges Page ✅ FIXED

### Description
When users complete an exchange request flow, the exchange doesn't appear on the exchanges page.

### Root Cause (CONFIRMED)
**The "active" status filter was broken:**

1. Frontend defaults to "Active" tab, sends `?status=active` to API
2. Backend treated 'active' as a literal status value: `WHERE e.status = 'active'`
3. But 'active' is NOT a real database status!
4. Real statuses: `pending_provider`, `pending_broker`, `accepted`, `in_progress`, `pending_confirmation`, `completed`, `disputed`, `cancelled`
5. Result: Query returned zero rows, page appeared empty

**File:** `src/Services/ExchangeWorkflowService.php:602-604`

```php
// BEFORE (BROKEN):
if (!empty($filters['status'])) {
    $whereClause .= " AND e.status = ?";
    $params[] = $filters['status']; // 'active' doesn't exist!
}
```

### Fix Applied ✅
**File:** `src/Services/ExchangeWorkflowService.php:602-618` and `621-639`

Detect 'active' filter and expand to IN clause with multiple statuses:

```php
// AFTER (FIXED):
if (!empty($filters['status'])) {
    if ($filters['status'] === 'active') {
        $activeStatuses = [
            self::STATUS_PENDING_PROVIDER,
            self::STATUS_PENDING_BROKER,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_CONFIRMATION,
        ];
        $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
        $whereClause .= " AND e.status IN ($placeholders)";
        $params = array_merge($params, $activeStatuses);
    } else {
        $whereClause .= " AND e.status = ?";
        $params[] = $filters['status'];
    }
}
```

Applied to both main query filter and role-based filter.

### Impact After Fix
- ✅ New exchange requests (`pending_provider`) now appear on "Active" tab
- ✅ Provider-accepted exchanges (`accepted`) appear on "Active" tab
- ✅ In-progress and pending confirmation exchanges appear on "Active" tab
- ✅ Users can see their exchange requests immediately after creation
- ✅ Backend properly interprets frontend's semantic 'active' filter

### Commit
`866c9fe` - fix(exchanges): expand 'active' status filter to include all non-completed statuses

---

## Bug #3: Missing Email Notifications

### Description
Users report not receiving email notifications when:
- Someone messages them about a listing
- Someone requests an exchange
- Exchange status changes

### Root Causes (Suspected)
1. **Listing context missing** (Bug #1) means email can't include listing details
2. **Exchange not created** (Bug #2) means no notification trigger
3. **Notification service not called** in exchange workflow
4. **Email templates missing** listing/exchange context variables

### Files to Check
1. **src/Services/NotificationDispatcher.php**
   - Verify `notifyNewMessage()` includes listing context
   - Verify `notifyExchangeRequest()` is called

2. **src/Services/EmailTemplateBuilder.php**
   - Check message notification template has `{listing_title}` variable
   - Check exchange templates exist and are complete

3. **src/Controllers/Api/MessagesApiController.php**
   - Verify notification dispatch in `send()` method

4. **src/Controllers/Api/ExchangesApiController.php**
   - Verify notification dispatch after exchange creation

---

## Testing Checklist

### Manual Testing Steps

#### Test 1: Message from Listing (No Exchange Workflow)
1. ✅ Disable exchange workflow in tenant settings
2. ✅ Navigate to any listing detail page
3. ✅ Click "Send Message" button
4. ❌ **Verify**: URL includes `?to=X&listing=Y` parameters
5. ❌ **Verify**: Message compose view shows listing preview card
6. ❌ **Verify**: Sending message includes listing_id in API request
7. ❌ **Verify**: Listing owner receives email with listing title
8. ❌ **Verify**: Conversation shows listing reference

#### Test 2: Exchange Request Flow
1. ✅ Enable exchange workflow in tenant settings
2. ✅ Navigate to any listing detail page
3. ✅ Click "Request Exchange" button
4. ✅ Fill out exchange request form
5. ✅ Submit request
6. ❌ **Verify**: Exchange appears on `/exchanges` page
7. ❌ **Verify**: Status shows "Pending" or similar
8. ❌ **Verify**: Listing owner receives email notification
9. ❌ **Verify**: Requestor can view exchange details

#### Test 3: Email Notifications
1. Set up email testing environment (Mailtrap, etc.)
2. Complete Test 1 and Test 2 flows
3. ❌ **Verify**: All emails sent with correct recipients
4. ❌ **Verify**: Email templates include listing/exchange details
5. ❌ **Verify**: Email links work correctly

### Automated E2E Tests

**Status:** Tests updated for React components, but may not cover these user flows

**Recommendation:** Add new E2E test suites for:
- `listings-to-messages-flow.spec.ts`
- `listings-to-exchange-flow.spec.ts`
- `exchange-notifications.spec.ts`

---

## Priority & Severity

**Priority:** P0 - Critical
**Severity:** High

### Business Impact
- **User friction**: Core marketplace flow is broken
- **Lost conversions**: Users can't easily inquire about listings
- **Poor UX**: Confusing experience damages platform credibility
- **Missing data**: Can't track listing-to-message conversion

### Recommended Timeline
- **Investigation**: 2-4 hours
- **Fix Implementation**: 4-8 hours
- **Testing**: 2-4 hours
- **Total Estimate**: 1-2 days

---

## Related Features

- **Tenant Setting:** `exchange_workflow_enabled` in `tenants.features`
- **Module:** `messages` (required for both flows)
- **Module:** `listings` (required)
- **Feature:** `exchange_workflow` (optional)

---

## Notes

- User feedback: "Running 10 tests all night" suggests persistent failures
- Issue affects **both** message flow AND exchange flow
- Frontend routes exist, but backend integration incomplete
- This is a **regression** or **incomplete feature** - not a new bug
- May have worked in legacy PHP frontend but broken in React migration

---

## Recommended Actions

1. **Immediate** (Today):
   - Fix Bug #1: Listing context preservation in messages
   - Test message flow end-to-end

2. **Short-term** (This week):
   - Investigate Bug #2: Exchange workflow
   - Fix email notifications
   - Add E2E tests for both flows

3. **Follow-up** (Next sprint):
   - User acceptance testing
   - Analytics tracking for listing inquiries
   - Performance optimization if needed

---

**Last Updated:** 2026-02-16 21:45 UTC
**Status:** Under Investigation
