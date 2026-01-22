# Members Directory API Requirements

**Created:** 2026-01-22
**Version:** 1.4.0
**Status:** Implementation Guide

---

## Overview

This document outlines the API requirements for the Members Directory enhanced with GOV.UK v1.4.0 components. Currently, the AJAX search functionality is **not implemented** and marked as TODO in the JavaScript.

---

## Required API Endpoints

### 1. Members Search API

**Endpoint:** `/api/members/search`

**Method:** `GET`

**Purpose:** Live search filtering for members based on query string

#### Request Parameters:

| Parameter | Type   | Required | Description                                    |
|-----------|--------|----------|------------------------------------------------|
| `q`       | string | Yes      | Search query (name, location, skills)          |
| `tab`     | string | No       | Filter context: 'all' or 'active' (default: 'all') |
| `page`    | int    | No       | Pagination page number (default: 1)            |
| `limit`   | int    | No       | Results per page (default: 20)                 |

#### Example Request:

```
GET /api/members/search?q=london&tab=active&page=1&limit=20
```

#### Response Format (JSON):

```json
{
    "success": true,
    "data": {
        "members": [
            {
                "id": 123,
                "username": "johndoe",
                "display_name": "John Doe",
                "name": "John Doe",
                "location": "London, UK",
                "avatar_url": "https://example.com/avatars/johndoe.jpg",
                "last_active_at": "2026-01-22 14:30:00",
                "profile_url": "/profile/123"
            }
        ],
        "showing": 5,
        "total": 47,
        "current_page": 1,
        "total_pages": 3
    },
    "message": "Search completed successfully"
}
```

#### Error Response:

```json
{
    "success": false,
    "error": "Invalid search query",
    "message": "Search query must be at least 2 characters"
}
```

---

## JavaScript Implementation

### File: `httpdocs/assets/js/civicone-members-directory.js`

#### Lines 196-219: `performSearch()` Function

**Current Status:** Placeholder only - logs to console

**Required Implementation:**

```javascript
function performSearch(query, resultsList, emptyState, resultsCount, spinner) {
    const tabPanel = resultsList.closest('.civicone-tabs__panel');
    const tabId = tabPanel ? tabPanel.id : 'all-members';
    const tab = tabId.replace('-members', '');

    fetch(`/api/members/search?q=${encodeURIComponent(query)}&tab=${tab}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Search request failed');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateResults(data.data, resultsList, emptyState, resultsCount);
            } else {
                console.error('Search error:', data.message);
                showErrorMessage(resultsList, data.message);
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            showErrorMessage(resultsList, 'Unable to complete search. Please try again.');
        })
        .finally(() => {
            if (spinner) spinner.classList.add('civicone-spinner--hidden');
        });
}
```

#### Lines 241-244: `renderMemberItem()` Function

**Current Status:** Placeholder returning hardcoded HTML

**Required Implementation:**

```javascript
function renderMemberItem(member) {
    const basePath = window.basePath || '';
    const hasAvatar = member.avatar_url && member.avatar_url.trim() !== '';
    const lastActive = member.last_active_at ? new Date(member.last_active_at) : null;
    const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000);
    const isOnline = lastActive && lastActive > fiveMinutesAgo;
    const displayName = member.display_name || member.name || member.username || 'Member';
    const location = member.location || '';

    return `
        <li class="civicone-member-item">
            <div class="civicone-member-item__avatar">
                ${hasAvatar
                    ? `<img src="${member.avatar_url}" alt="" class="civicone-avatar">`
                    : `<div class="civicone-avatar civicone-avatar--placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>`
                }
                ${isOnline ? '<span class="civicone-status-indicator civicone-status-indicator--online" title="Active now" aria-label="Currently online"></span>' : ''}
            </div>
            <div class="civicone-member-item__content">
                <h3 class="civicone-member-item__name">
                    <a href="${basePath}/profile/${member.id}" class="civicone-link">
                        ${displayName}
                    </a>
                </h3>
                ${location ? `
                    <p class="civicone-member-item__meta">
                        <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        ${location}
                    </p>
                ` : ''}
            </div>
            <div class="civicone-member-item__actions">
                <a href="${basePath}/profile/${member.id}" class="civicone-button civicone-button--secondary">
                    View profile
                </a>
            </div>
        </li>
    `;
}
```

---

## localStorage Management

### Current Usage

The Members Directory uses `localStorage` to persist user view preferences:

**Key:** `civicone-members-view`

**Values:** `'list'` or `'table'`

### Cleanup Requirements

#### 1. On User Logout

**Location:** Main logout handler (typically in authentication controller)

**Implementation:**

```javascript
// Clear Members Directory preferences on logout
function clearMembersDirectoryData() {
    localStorage.removeItem('civicone-members-view');
}

// Call in logout function
document.querySelector('#logout-button').addEventListener('click', function() {
    clearMembersDirectoryData();
    // ... rest of logout logic
});
```

#### 2. Optional: Privacy Settings

Allow users to clear their preferences in settings:

```javascript
// Settings page: Clear all stored preferences
document.querySelector('#clear-preferences-btn').addEventListener('click', function() {
    if (confirm('Clear all stored preferences? This will reset your view settings.')) {
        localStorage.removeItem('civicone-members-view');
        alert('Preferences cleared successfully');
    }
});
```

#### 3. Respect User Privacy Settings

If user opts out of local storage:

```javascript
// Check if user has disabled localStorage
function canUseLocalStorage() {
    try {
        const test = '__localStorage_test__';
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
    } catch(e) {
        return false;
    }
}

// Modify view toggle to check permission
if (canUseLocalStorage()) {
    localStorage.setItem('civicone-members-view', view);
}
```

---

## Backend Controller Requirements

### Members Controller Updates

**File:** `controllers/MembersController.php` (or equivalent)

#### Method: `search()`

```php
public function search()
{
    // Validate input
    $query = $_GET['q'] ?? '';
    $tab = $_GET['tab'] ?? 'all';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);

    // Minimum query length
    if (strlen($query) < 2) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid search query',
            'message' => 'Search query must be at least 2 characters'
        ]);
        return;
    }

    try {
        // Build search query
        $searchParams = [
            'query' => $query,
            'active_only' => ($tab === 'active'),
            'page' => $page,
            'limit' => $limit
        ];

        // Perform search (example using a repository)
        $results = $this->memberRepository->search($searchParams);

        // Format response
        $members = array_map(function($member) {
            return [
                'id' => $member->id,
                'username' => $member->username,
                'display_name' => $member->display_name,
                'name' => $member->name,
                'location' => $member->location,
                'avatar_url' => $member->avatar_url,
                'last_active_at' => $member->last_active_at,
                'profile_url' => "/profile/{$member->id}"
            ];
        }, $results['members']);

        echo json_encode([
            'success' => true,
            'data' => [
                'members' => $members,
                'showing' => count($members),
                'total' => $results['total'],
                'current_page' => $page,
                'total_pages' => ceil($results['total'] / $limit)
            ],
            'message' => 'Search completed successfully'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error',
            'message' => 'An error occurred while searching. Please try again.'
        ]);
    }
}
```

#### Security Considerations:

1. **SQL Injection Prevention:** Use parameterized queries
2. **XSS Prevention:** Escape all output in JSON response
3. **Rate Limiting:** Limit search requests per user (e.g., 60 per minute)
4. **Authentication:** Verify user is logged in
5. **Authorization:** Check user has permission to view members directory

---

## Database Query Optimization

### Recommended Indexes:

```sql
-- For name searches
CREATE INDEX idx_members_display_name ON members(display_name);
CREATE INDEX idx_members_name ON members(name);

-- For location searches
CREATE INDEX idx_members_location ON members(location);

-- For active status filtering
CREATE INDEX idx_members_last_active ON members(last_active_at);

-- Composite index for active + search
CREATE INDEX idx_members_active_search ON members(last_active_at, display_name);
```

### Example Search Query:

```sql
SELECT
    id, username, display_name, name, location, avatar_url, last_active_at
FROM members
WHERE
    (display_name LIKE ? OR name LIKE ? OR location LIKE ?)
    AND (? = 'all' OR last_active_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE))
ORDER BY
    last_active_at DESC,
    display_name ASC
LIMIT ? OFFSET ?
```

---

## Testing Checklist

### Functional Testing:

- [ ] Search returns correct results for name queries
- [ ] Search returns correct results for location queries
- [ ] "Active Now" tab filters correctly (last 5 minutes)
- [ ] Pagination works with search results
- [ ] Empty state shown when no results found
- [ ] Error messages displayed for failed requests
- [ ] Loading spinner shows during search

### Performance Testing:

- [ ] Search responds within 500ms for typical query
- [ ] Database queries optimized with proper indexes
- [ ] Rate limiting prevents abuse

### Security Testing:

- [ ] SQL injection attempts blocked
- [ ] XSS attempts in query strings escaped
- [ ] Unauthenticated requests rejected
- [ ] Rate limiting enforced

### localStorage Testing:

- [ ] View preference persists after page reload
- [ ] View preference cleared on logout
- [ ] Fallback behavior when localStorage disabled
- [ ] No errors in private browsing mode

---

## Implementation Priority

### Phase 1 (High Priority):
1. Create `/api/members/search` endpoint
2. Implement `performSearch()` function
3. Implement `renderMemberItem()` function
4. Add database indexes

### Phase 2 (Medium Priority):
5. Add localStorage cleanup on logout
6. Implement rate limiting
7. Add error handling and user feedback

### Phase 3 (Low Priority):
8. Add privacy settings for localStorage
9. Implement advanced search filters
10. Add search analytics

---

## Known Limitations

1. **AJAX Search:** Currently placeholder only - full implementation required
2. **No Advanced Filters:** Only basic text search implemented
3. **No Sort Options:** Results sorted by last_active_at only
4. **No Export:** Cannot export search results

---

## Future Enhancements

1. **Advanced Filters:**
   - Skills/interests checkboxes
   - Location radius search with Google Maps API
   - Date joined range picker
   - Sort by: Name (A-Z), Activity (Most/Least), Join date

2. **Export Functionality:**
   - Export search results as CSV
   - Export visible columns only

3. **Saved Searches:**
   - Save frequently used search queries
   - Quick access to saved searches

4. **Search Analytics:**
   - Track popular search terms
   - Monitor search performance
   - A/B test search algorithms

---

## Support & References

- **GOV.UK Design System:** https://design-system.service.gov.uk/
- **WCAG 2.2 Guidelines:** https://www.w3.org/WAI/WCAG22/quickref/
- **Fetch API:** https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API
- **localStorage Best Practices:** https://developer.mozilla.org/en-US/docs/Web/API/Window/localStorage

---

## Version History

### v1.4.0 (2026-01-22)
- Initial API requirements documentation
- Defined search endpoint specification
- Outlined localStorage management
- Provided implementation examples

---

## Summary

✅ **API endpoint specification defined**
✅ **JavaScript implementation examples provided**
✅ **localStorage cleanup strategy outlined**
✅ **Backend controller requirements documented**
✅ **Database optimization recommendations included**
✅ **Security considerations addressed**
✅ **Testing checklist provided**

**Status:** Ready for backend implementation
