# Bug Report: Listings & Messaging Flow Issues

**Date:** 2026-02-16
**Reporter:** User Testing Feedback
**Severity:** High - Core user flow broken

## Summary

Users clicking "Send Message" or "Request Exchange" buttons on listing detail pages experience broken flows where listing context is lost, messages don't get sent properly, and the exchange workflow isn't functioning correctly.

---

## Bug #1: Listing Context Lost When Sending Messages

### Description
When a user clicks "Send Message" from a listing detail page, the listing reference is lost during the message composition flow.

### Current Flow (BROKEN)
1. User views listing at `/listings/123`
2. Clicks "Send Message" button (when exchange workflow disabled)
3. Navigates to `/messages?to=456&listing=123`
4. `MessagesPage` calls `startNewConversation(456, 123)`
5. **BUG**: Listing ID parameter is ignored (function parameter named `_listing`)
6. Redirects to `/messages/new/456` **WITHOUT** listing context
7. User composes message with NO reference to original listing
8. First message sent has no listing_id associated with it

### Impact
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

### Fix Required

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

### Files to Update
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

## Bug #2: Exchange Workflow Not Showing Up on Exchanges Page

### Description
When users complete an exchange request flow, the exchange doesn't appear on the exchanges page.

### Current Flow (NEEDS VERIFICATION)
1. User clicks "Request Exchange" from listing
2. Navigates to `/listings/:id/request-exchange`
3. Fills out exchange request form
4. Submits request
5. **Expected**: Exchange appears on `/exchanges` page
6. **Actual**: Exchange missing or not visible

### Suspected Issues
1. Exchange not being created in database
2. Exchange created but with wrong status (not visible to requestor)
3. Exchange API endpoint filtering out pending requests
4. Frontend not fetching/displaying pending exchanges

### Files to Investigate
1. **react-frontend/src/pages/exchanges/RequestExchangePage.tsx**
   - Verify POST request actually sends
   - Check response handling

2. **src/Controllers/Api/ExchangesApiController.php**
   - Verify `requestExchange()` method creates record
   - Check status values being set

3. **react-frontend/src/pages/exchanges/ExchangesPage.tsx**
   - Verify API call includes pending exchanges
   - Check filtering logic

4. **src/Services/ExchangeWorkflowService.php**
   - Verify exchange creation logic
   - Check if notifications are sent

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
