# Members Directory Search - Quick Testing Guide

**Purpose:** Verify the newly implemented AJAX search functionality

---

## Pre-Testing Setup

### 1. Apply Database Indexes (REQUIRED)

```bash
mysql -u your_user -p your_database < migrations/add_members_search_indexes.sql
```

**Or manually run:**
```sql
CREATE INDEX idx_users_name_tenant ON users(tenant_id, name);
CREATE INDEX idx_users_last_active ON users(tenant_id, last_active_at);
CREATE INDEX idx_users_active_search ON users(tenant_id, last_active_at, name);
```

### 2. Verify Files Deployed

- ✅ `src/Controllers/Api/CoreApiController.php` (modified)
- ✅ `httpdocs/assets/js/civicone-members-directory.min.js` (minified)

---

## Quick Functional Tests (5 minutes)

### Test 1: Basic Search
1. Navigate to `/members`
2. Type "john" in the search box
3. **Expected:** Results filter dynamically after 300ms
4. **Expected:** Loading spinner shows briefly
5. **Expected:** Results count updates (e.g., "Showing 5 of 245 members")

### Test 2: Active Members Tab
1. Click "Active now" tab
2. **Expected:** Shows only members active in last 5 minutes
3. Type search query in this tab
4. **Expected:** Searches only within active members

### Test 3: Empty Results
1. Type gibberish: "zzzzzzzz"
2. **Expected:** Empty state appears: "No members found"
3. **Expected:** Message: "Try adjusting your search..."

### Test 4: View Toggle After Search
1. Search for "volunteer"
2. Click "Table" view toggle
3. **Expected:** Results display in table format
4. **Expected:** localStorage saves preference
5. Refresh page
6. **Expected:** Table view persists

### Test 5: Error Handling
1. Disconnect from internet (or block API in DevTools)
2. Try to search
3. **Expected:** Error message: "Unable to complete search. Please try again."

---

## API Testing (Browser Console)

### Test Direct API Calls

Open browser console (F12) and run:

#### Test 1: All Members (Backward Compatible)
```javascript
fetch('/api/members')
  .then(r => r.json())
  .then(d => console.log(d));
```
**Expected Response:**
```json
{
  "data": [...],
  "meta": {
    "total": 245,
    "showing": 100,
    "limit": 100,
    "offset": 0
  }
}
```

#### Test 2: Search Query
```javascript
fetch('/api/members?q=john')
  .then(r => r.json())
  .then(d => console.log(d));
```
**Expected:** Filtered results with matching names

#### Test 3: Active Members
```javascript
fetch('/api/members?active=true')
  .then(r => r.json())
  .then(d => console.log(d));
```
**Expected:** Only members with `last_active_at` within 5 minutes

#### Test 4: Combined Search
```javascript
fetch('/api/members?q=volunteer&active=true&limit=10')
  .then(r => r.json())
  .then(d => console.log(d));
```
**Expected:** Active members matching "volunteer", max 10 results

---

## Performance Testing

### Check Response Times (Browser DevTools Network Tab)

1. Open DevTools → Network tab
2. Search for something
3. Look for `/api/members?q=...` request
4. Check "Time" column

**Expected Performance:**
- **With indexes:** <200ms
- **Without indexes:** May be slower (500ms-2s)

**If slow:** Make sure database indexes were applied!

---

## Accessibility Testing

### Keyboard Navigation
1. Tab to search box (should focus)
2. Type search query
3. Tab to results
4. **Expected:** Results announced to screen reader

### Screen Reader Testing (Optional)
1. Enable screen reader (NVDA/JAWS/VoiceOver)
2. Navigate to search box
3. Type query
4. **Expected:** "Found X members" announced

---

## Error Scenarios to Test

### Scenario 1: Very Short Query
- Type: "a" (1 character)
- **Expected:** No search triggered (minimum 2 characters)

### Scenario 2: SQL Injection Attempt
- Type: `'; DROP TABLE users; --`
- **Expected:** No error, safely escaped, returns no results

### Scenario 3: XSS Attempt
- Type: `<script>alert('xss')</script>`
- **Expected:** Safely escaped, no alert shown

### Scenario 4: Very Long Query
- Type: 150+ characters
- **Expected:** Search still works (or returns error if validation added)

---

## Backward Compatibility Tests

### Modern Theme Integration
1. Check if other pages using `/api/members` still work
2. Verify federation features still work
3. Check mobile app integration (if applicable)

**Expected:** All existing functionality unchanged

---

## Common Issues & Solutions

### Issue 1: Search Not Working
**Symptoms:** Typing doesn't trigger search
**Solution:** Check browser console for JavaScript errors

### Issue 2: Empty Results Always Show
**Symptoms:** No members display even with valid search
**Solution:** Check API response in Network tab, verify backend deployed

### Issue 3: Slow Search (>2 seconds)
**Symptoms:** Long wait for results
**Solution:** Apply database indexes (see Pre-Testing Setup)

### Issue 4: 500 Error on Search
**Symptoms:** Red error message, 500 in Network tab
**Solution:** Check PHP error logs: `tail -f /var/log/php-errors.log`

### Issue 5: Active Tab Shows All Members
**Symptoms:** "Active now" tab not filtering
**Solution:** Verify `last_active_at` column exists in users table

---

## Success Criteria

✅ Search filters results dynamically
✅ Loading spinner shows during search
✅ Results count updates correctly
✅ "Active Now" tab filters properly
✅ Empty state displays when no results
✅ Error messages show on failures
✅ View toggle works after search
✅ No JavaScript console errors
✅ API response time <500ms
✅ All existing functionality preserved

---

## Reporting Issues

If you find bugs, please report with:

1. **Browser & Version:** (e.g., Chrome 120)
2. **Steps to Reproduce:** What you did
3. **Expected Result:** What should happen
4. **Actual Result:** What actually happened
5. **Console Errors:** Screenshots of DevTools Console/Network tabs
6. **Search Query:** What you typed

---

## Performance Benchmarks

**Expected Results (with indexes):**

| Members Count | Search Time | Active Filter | Combined |
|--------------|-------------|---------------|----------|
| 100          | <10ms       | <10ms         | <15ms    |
| 1,000        | <20ms       | <15ms         | <30ms    |
| 10,000       | <50ms       | <30ms         | <80ms    |
| 100,000      | <200ms      | <100ms        | <300ms   |

**If slower than expected:** Database indexes may not be applied

---

## Quick Reference: API Parameters

| Parameter | Type   | Description | Example |
|-----------|--------|-------------|---------|
| `q`       | string | Search query (name/email/bio) | `?q=london` |
| `active`  | bool   | Filter active members only | `?active=true` |
| `limit`   | int    | Results per page (default: 100) | `?limit=20` |
| `offset`  | int    | Pagination offset (default: 0) | `?offset=40` |

---

**Testing Time:** 5-15 minutes for quick tests, 30-60 minutes for comprehensive testing

**Status:** Ready for testing - all functionality implemented
