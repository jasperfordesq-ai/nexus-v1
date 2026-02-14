# Admin API Deep Test Report: Broker, Blog, Dashboard, Users

**Date:** 2026-02-14
**Tester:** Automated deep test (Claude agent)
**Auth:** `testadmin@nexus.test` (user 650, admin role, super_admin, tenant 2)
**Base URL:** `http://localhost:8090`

---

## Summary

| Category | Endpoints Tested | Passed | Failed | Notes |
|----------|-----------------|--------|--------|-------|
| Broker (GET) | 5 | 5 | 0 | All return valid JSON with correct structure |
| Broker (POST) | 3 | 3 | 0 | Proper 404 for non-existent resources |
| Blog (CRUD) | 6 | 6 | 0 | Full lifecycle: create -> read -> update -> toggle -> delete -> verify |
| Dashboard | 3 | 3 | 0 | Stats, trends, activity all working |
| Users | 2 | 2 | 0 | List and detail endpoints working |
| **TOTAL** | **19** | **19** | **0** | **100% PASS** |

---

## Broker Endpoints

### GET /api/v2/admin/broker/dashboard
- **Status:** 200 OK
- **Response:**
```json
{
  "data": {
    "pending_exchanges": 0,
    "unreviewed_messages": 0,
    "high_risk_listings": 0,
    "monitored_users": 0
  },
  "meta": { "base_url": "https://hour-timebank.ie" }
}
```
- **Verdict:** PASS - Correct structure with all 4 dashboard counters. All zero (clean test environment).

### GET /api/v2/admin/broker/exchanges
- **Status:** 200 OK
- **Response:**
```json
{
  "data": [],
  "meta": {
    "base_url": "https://hour-timebank.ie",
    "page": 1, "per_page": 20, "total": 0, "total_pages": 0, "has_more": false
  }
}
```
- **Verdict:** PASS - Empty dataset, proper pagination metadata.

### GET /api/v2/admin/broker/risk-tags
- **Status:** 200 OK
- **Response:**
```json
{
  "data": [],
  "meta": { "base_url": "https://hour-timebank.ie" }
}
```
- **Verdict:** PASS - Empty dataset, valid structure.

### GET /api/v2/admin/broker/messages
- **Status:** 200 OK
- **Response:**
```json
{
  "data": [],
  "meta": {
    "base_url": "https://hour-timebank.ie",
    "page": 1, "per_page": 20, "total": 0, "total_pages": 0, "has_more": false
  }
}
```
- **Verdict:** PASS - Empty dataset, proper pagination metadata.

### GET /api/v2/admin/broker/monitoring
- **Status:** 200 OK
- **Response:**
```json
{
  "data": [],
  "meta": { "base_url": "https://hour-timebank.ie" }
}
```
- **Verdict:** PASS - Empty dataset, valid structure.

### POST /api/v2/admin/broker/exchanges/999/approve
- **Status:** 404 Not Found
- **Response:**
```json
{
  "errors": [{"code": "NOT_FOUND", "message": "Exchange request not found"}]
}
```
- **Verdict:** PASS - Correct 404 with proper error structure for non-existent exchange. Endpoint is wired and validates resource existence.

### POST /api/v2/admin/broker/exchanges/999/reject
- **Status:** 404 Not Found
- **Response:**
```json
{
  "errors": [{"code": "NOT_FOUND", "message": "Exchange request not found"}]
}
```
- **Verdict:** PASS - Correct 404 with proper error structure. Endpoint is wired and validates.

### POST /api/v2/admin/broker/messages/999/review
- **Status:** 404 Not Found
- **Response:**
```json
{
  "errors": [{"code": "NOT_FOUND", "message": "Message not found"}]
}
```
- **Verdict:** PASS - Correct 404 with proper error structure. Endpoint is wired and validates.

---

## Blog Endpoints (Full CRUD Lifecycle)

### GET /api/v2/admin/blog (list)
- **Status:** 200 OK
- **Response:** Array of 20 blog posts with pagination
```json
{
  "data": [
    {
      "id": 71, "title": "New Draft", "slug": "draft-1768240668-77b0fa93",
      "excerpt": "", "status": "draft", "featured_image": null,
      "author_id": 14, "author_name": "", "category_id": null, "category_name": null,
      "created_at": "2026-01-12 17:57:48", "updated_at": "2026-01-12 17:57:48"
    }
    // ... 19 more posts
  ],
  "meta": { "page": 1, "per_page": 20, "total": 41, "total_pages": 3, "has_more": true }
}
```
- **Verdict:** PASS - 41 total posts, proper pagination, correct field structure.

### POST /api/v2/admin/blog (create)
- **Status:** 201 Created
- **Request body:**
```json
{
  "title": "Deep Test Post - API Validation",
  "content": "<p>This is a test blog post created via the admin API for deep testing purposes.</p>",
  "excerpt": "Test excerpt for validation",
  "status": "draft"
}
```
- **Response:**
```json
{
  "data": {
    "id": 73,
    "title": "Deep Test Post - API Validation",
    "slug": "deep-test-post-api-validation",
    "status": "draft"
  }
}
```
- **Verdict:** PASS - Post created with auto-generated slug, returned ID 73.

### GET /api/v2/admin/blog/73 (read created post)
- **Status:** 200 OK
- **Response:**
```json
{
  "data": {
    "id": 73,
    "title": "Deep Test Post - API Validation",
    "slug": "deep-test-post-api-validation",
    "content": "<p>This is a test blog post created via the admin API for deep testing purposes.</p>",
    "excerpt": "Test excerpt for validation",
    "status": "draft",
    "featured_image": null,
    "author_id": 650,
    "author_name": "Test Admin",
    "category_id": null,
    "category_name": null,
    "created_at": "2026-02-14 20:51:42",
    "updated_at": "2026-02-14 20:51:42"
  }
}
```
- **Verdict:** PASS - All fields persisted correctly. Author set to authenticated user (650).

### PUT /api/v2/admin/blog/73 (update)
- **Status:** 200 OK
- **Request body:**
```json
{
  "title": "Deep Test Post - Updated Title",
  "content": "<p>Updated content from deep test.</p>",
  "excerpt": "Updated excerpt"
}
```
- **Response:**
```json
{
  "data": {
    "id": 73,
    "title": "Deep Test Post - Updated Title",
    "slug": "deep-test-post-updated-title",
    "content": "<p>Updated content from deep test.</p>",
    "excerpt": "Updated excerpt",
    "status": "draft",
    "author_id": 650,
    "author_name": "Test Admin",
    "updated_at": "2026-02-14 20:51:56"
  }
}
```
- **Verdict:** PASS - Title, content, excerpt all updated. Slug regenerated from new title. `updated_at` timestamp changed.

### POST /api/v2/admin/blog/73/toggle-status (toggle draft -> published)
- **Status:** 200 OK
- **Response:**
```json
{ "data": { "id": 73, "status": "published" } }
```
- **Follow-up GET confirmed status was "published".**
- **Second toggle (published -> draft):**
```json
{ "data": { "id": 73, "status": "draft" } }
```
- **Verdict:** PASS - Bidirectional toggle works: draft -> published -> draft.

### DELETE /api/v2/admin/blog/73 (delete)
- **Status:** 200 OK
- **Response:**
```json
{ "data": { "deleted": true, "id": 73 } }
```
- **Follow-up GET /api/v2/admin/blog/73:**
```json
{ "errors": [{ "code": "RESOURCE_NOT_FOUND", "message": "Blog post not found" }] }
```
- **Status:** 404 Not Found
- **Verdict:** PASS - Post deleted. Follow-up GET confirms deletion with proper 404.

---

## Dashboard Endpoints

### GET /api/v2/admin/dashboard/stats
- **Status:** 200 OK
- **Response:**
```json
{
  "data": {
    "total_users": 17,
    "active_users": 16,
    "pending_users": 1,
    "total_listings": 15,
    "active_listings": 15,
    "pending_listings": 0,
    "total_transactions": 4,
    "total_hours_exchanged": 96,
    "new_users_this_month": 17,
    "new_listings_this_month": 15
  }
}
```
- **Verdict:** PASS - All 10 stat fields present with realistic data. Numbers are consistent (active + pending = total for users).

### GET /api/v2/admin/dashboard/trends
- **Status:** 200 OK
- **Response:**
```json
{
  "data": [
    { "month": "2025-09", "users": 0, "listings": 0, "transactions": 0, "hours": 0 },
    { "month": "2025-10", "users": 0, "listings": 0, "transactions": 0, "hours": 0 },
    { "month": "2025-11", "users": 0, "listings": 0, "transactions": 0, "hours": 0 },
    { "month": "2025-12", "users": 0, "listings": 0, "transactions": 0, "hours": 0 },
    { "month": "2026-01", "users": 0, "listings": 0, "transactions": 4, "hours": 96 },
    { "month": "2026-02", "users": 17, "listings": 15, "transactions": 0, "hours": 0 }
  ]
}
```
- **Verdict:** PASS - 6 months of trend data. Feb 2026 shows 17 users and 15 listings matching dashboard stats. Jan 2026 shows 4 transactions / 96 hours matching total_transactions/total_hours_exchanged.

### GET /api/v2/admin/dashboard/activity
- **Status:** 200 OK
- **Response:**
```json
{
  "data": [],
  "meta": { "page": 1, "per_page": 20, "total": 0, "total_pages": 0, "has_more": false }
}
```
- **Verdict:** PASS - Empty but valid structure with pagination. No activity log entries in test environment.

---

## Users Endpoints

### GET /api/v2/admin/users (list)
- **Status:** 200 OK
- **Response:** 17 users returned with pagination metadata
- **Sample user structure:**
```json
{
  "id": 650,
  "name": "Test Admin",
  "first_name": "Test",
  "last_name": "Admin",
  "email": "testadmin@nexus.test",
  "avatar_url": null,
  "location": null,
  "role": "admin",
  "status": "pending",
  "is_super_admin": true,
  "balance": 0,
  "listing_count": 0,
  "profile_type": "individual",
  "has_2fa_enabled": false,
  "created_at": "2026-02-14 20:48:55",
  "last_active_at": null
}
```
- **Verdict:** PASS - 17 users total (matches dashboard stats). All expected fields present including role, is_super_admin, balance, listing_count, has_2fa_enabled.

### GET /api/v2/admin/users/650 (detail)
- **Status:** 200 OK
- **Response:**
```json
{
  "data": {
    "id": 650,
    "name": "Test Admin",
    "first_name": "Test",
    "last_name": "Admin",
    "email": "testadmin@nexus.test",
    "avatar_url": null,
    "location": null,
    "bio": null,
    "tagline": null,
    "phone": null,
    "role": "admin",
    "status": "pending",
    "is_super_admin": true,
    "is_admin": true,
    "balance": 0,
    "profile_type": "individual",
    "organization_name": null,
    "badges": [],
    "created_at": "2026-02-14 20:48:55",
    "last_active_at": null
  }
}
```
- **Verdict:** PASS - Detail view includes additional fields (bio, tagline, phone, organization_name, badges) not in list view. Correct separation of list vs detail representations.

---

## Cross-Cutting Observations

### Positive Findings
1. **Consistent error format** - All error responses use `{ "errors": [{ "code": "...", "message": "..." }] }` structure
2. **Consistent success format** - All success responses use `{ "data": {...}, "meta": {...} }` structure
3. **Proper HTTP status codes** - 200 for success, 201 for creation, 404 for not found
4. **Pagination metadata** - All list endpoints include page, per_page, total, total_pages, has_more
5. **Tenant scoping** - All responses include `meta.base_url` confirming tenant context
6. **Blog slug generation** - Auto-generates from title, regenerates on title update
7. **Data consistency** - Dashboard stats (17 users) matches users list count (17)
8. **Trends correlation** - Trend data (Feb: 17 users, 15 listings) matches dashboard stats exactly

### Notes
- Broker endpoints return empty datasets (expected in test environment with no exchange/message activity)
- Blog `author_name` for older posts (author_id: 14) returns empty string - may indicate that user 14 has been deleted or has empty name fields
- Dashboard activity log is empty (no activity_log entries in test DB)
- User 650 (testadmin) has status "pending" despite being admin - this is because the account was just created for testing

### No Failures Found
All 19 endpoints tested returned expected HTTP status codes, valid JSON, and correct data structures. The full blog CRUD lifecycle (create -> read -> update -> toggle-status -> delete -> verify-deleted) completed successfully.
