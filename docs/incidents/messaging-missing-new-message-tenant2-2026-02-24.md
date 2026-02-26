# Incident: Messages Not Appearing in React Frontend (Tenant 2)

**Date:** 2026-02-24
**Severity:** P0 — Messages received via email notification but invisible in React frontend
**Tenant:** 2 (hour-timebank)
**Status:** Fixed (pending deploy)

---

## Symptom

User received an **email notification** about a new message in Tenant 2 but sees **nothing** in the React frontend Messages page. The conversation list shows no mention of the message, and unread indicators do not reflect it.

## Root Causes (7 bugs found)

### CRITICAL #1: Pusher `new-message` event payload mismatch

**File:** `src/Services/RealtimeService.php` (line 79)
**Impact:** Real-time message updates on MessagesPage are **completely non-functional** for all messages.

The backend `broadcastMessage()` sends two Pusher events:
1. **Chat channel** (`private-tenant.{tid}.chat.{chatId}`): Event `message` with `{id, sender_id, receiver_id, body, created_at, ...}` — correct payload.
2. **User channel** (`private-tenant.{tid}.user.{receiverId}`): Event `new-message` with `{from_user_id, preview, timestamp}` — **missing** `sender_id`, `id`, `body`, `created_at`.

The frontend `PusherContext.tsx` binds to the user channel's `new-message` event and types it as `NewMessageEvent {id, sender_id, receiver_id, body, created_at, timestamp}`. Since the backend sends `from_user_id` instead of `sender_id`, the field is `undefined`, causing:
- `event.sender_id` → `undefined` → `findIndex()` returns -1 → falls through to "new conversation" branch
- Both existing and new conversation updates silently fail

### CRITICAL #2: New conversation handler returns stale state (no reload)

**File:** `react-frontend/src/pages/messages/MessagesPage.tsx` (line 115-117, pre-fix)
**Impact:** Messages from first-time senders never appear until manual page refresh.

When `handleNewMessage` can't find an existing conversation (either due to bug #1 or genuinely new sender), it returns `prev` unchanged with a comment saying "This triggers a refresh" — but no refresh is triggered. The conversation list stays stale.

### CRITICAL #3: `getMessages()` missing soft-delete filter

**File:** `src/Services/MessageService.php` — `getMessages()` method
**Impact:** Soft-deleted messages (body = "[Message deleted]") still appear in conversation threads.

The query had no `AND m.is_deleted = 0` clause. Deleted messages were visible to both sender and receiver.

### MEDIUM #4: `getUnreadCount()` counts soft-deleted messages

**File:** `src/Services/MessageService.php` — `getUnreadCount()` method
**Impact:** Unread badge count inflated by deleted messages.

### MEDIUM #5: `getConversations()` unread subquery counts deleted messages

**File:** `src/Services/MessageService.php` — `getConversations()` unread_count subquery
**Impact:** Per-conversation unread badges inflated by deleted messages.

### MEDIUM #6: ConversationPage polling disabled when Pusher connected

**File:** `react-frontend/src/pages/messages/ConversationPage.tsx` (line 282, pre-fix)
**Impact:** If Pusher silently drops messages, there is zero fallback — messages never appear.

The condition `if (!isDocumentVisible || isNewConversation || !lastMessageIdRef.current || pusher?.isConnected) return;` completely disables polling when Pusher reports connected, even though Pusher can silently fail to deliver messages.

### MINOR #7: NotificationsContext toast shows fallback text

**File:** `react-frontend/src/contexts/NotificationsContext.tsx` (line 292, pre-fix)
**Impact:** Toast notification always says "You have a new message" instead of showing message preview.

Handler expects `data.message` but backend sends `data.preview` and `data.body`. The field `data.message` is always `undefined`, triggering the fallback string.

## Fixes Applied

| Bug | File | Fix |
|-----|------|-----|
| #1 | `src/Services/RealtimeService.php` | Enriched user channel `new-message` payload with `id`, `sender_id`, `receiver_id`, `body`, `created_at` (kept `from_user_id`/`preview` for backward compat) |
| #2 | `react-frontend/src/pages/messages/MessagesPage.tsx` | Moved `loadConversations` before `handleNewMessage`; handler now normalizes `sender_id`/`from_user_id`, does optimistic update for existing convos, and **always** calls `loadConversations()` to catch new conversations |
| #3 | `src/Services/MessageService.php` `getMessages()` | Added conditional `AND m.is_deleted = 0` filter (gated by `hasColumn` check) |
| #4 | `src/Services/MessageService.php` `getUnreadCount()` | Added conditional `AND is_deleted = 0` filter |
| #5 | `src/Services/MessageService.php` `getConversations()` | Added conditional `is_deleted = 0` to unread_count subquery |
| #6 | `react-frontend/src/pages/messages/ConversationPage.tsx` | Changed polling to use 30s interval when Pusher connected (was disabled entirely), 5s when disconnected |
| #7 | `react-frontend/src/contexts/NotificationsContext.tsx` | Fixed toast to read `data.body \|\| data.preview \|\| data.message` |

## Verification

- [x] PHP syntax check passes (`php -l` on all modified files)
- [x] TypeScript compiles cleanly (`tsc --noEmit`)
- [x] Vite production build succeeds
- [ ] Manual test: Send message from User A to User B (first time) in Tenant 2 — verify conversation appears
- [ ] Manual test: Verify unread badge increments on new message
- [ ] Manual test: Verify toast shows message preview (not fallback text)
- [ ] Manual test: Delete a message and verify it disappears from thread and unread count adjusts
- [ ] Manual test: Open ConversationPage, disconnect network briefly, reconnect — verify polling recovers messages

## Prevention

1. **Type-safe Pusher events**: Backend and frontend should share event type definitions (or at minimum have tests that assert field names match)
2. **Integration test**: Send message → assert it appears in inbox API response within 5 seconds
3. **Monitoring**: Add structured logging for Pusher broadcast failures and API inbox queries returning empty for users with known unread messages
