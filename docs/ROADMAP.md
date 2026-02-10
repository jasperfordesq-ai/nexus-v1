# Project NEXUS Roadmap

## Completed Features

### Broker Approval Workflow for Matches
**Status:** Implemented
**Requested by:** Matt (Crewkerne Timebank) - 2026-02-06
**Completed:** 2026-02-07

**What was built:**
- Database: `match_approvals` table with full workflow support
- Service: `MatchApprovalWorkflowService.php` - follows GroupApprovalWorkflowService pattern
- Integration: `SmartMatchingEngine.php` now routes all matches through approval queue
- Controller: `MatchApprovalsController.php` for broker dashboard
- Views: Admin dashboard for both modern and civicone themes
- Routes: `/admin/match-approvals`, approve/reject endpoints
- Notifications: Users notified on approval, notified with reason on rejection

**Design Decisions:**
- ALL matches require approval (no score threshold)
- Users ARE notified on rejection with reason

**Access:** `/admin/match-approvals` (requires admin or broker role)

---

### Light/Dark Mode Toggle (React Frontend)
**Status:** Implemented
**Requested by:** Matt (Crewkerne Timebank) - 2026-02-06
**Completed:** 2026-02-07

**What was built:**
- Database: `users.preferred_theme` column (light/dark/system)
- CSS: Full light mode variables in `react-frontend/src/styles/tokens.css`
- Context: `ThemeContext.tsx` with localStorage + backend persistence
- UI: Theme toggle button in Navbar (sun/moon icon)
- API: `PUT /api/v2/users/me/theme` endpoint
- Integration: Wrapped App with ThemeProvider

**Usage:**
- Click sun/moon icon in Navbar to toggle
- Preference persists across sessions
- Supports "system" mode to follow OS preference

---

## Pending Features

_No pending features at this time._

---

## Notes

- Both features were requested after positive feedback on new React frontend
- Broker approval workflow is critical for insurance/safeguarding compliance
- Light mode was a quality-of-life request from Matt

---

## Change Log

| Date | Change |
|------|--------|
| 2026-02-07 | Implemented both broker approval workflow and light mode toggle |
| 2026-02-06 | Roadmap created with broker approval workflow and light mode toggle |
