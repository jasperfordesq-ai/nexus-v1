# Codebase Error Scan Report
**Generated**: 2026-01-23
**Branch**: claude/scan-codebase-errors-R8QP5

## Summary

| Category | Status | Count |
|----------|--------|-------|
| PHP Syntax | ✅ Pass | 0 errors |
| JavaScript (ESLint) | ⚠️ Warnings | 698 warnings, 0 errors |
| CSS (Stylelint) | ❌ Errors | 883 issues (49 syntax errors) |
| Security | ✅ Pass | No critical issues |
| NPM Audit | ⚠️ Warnings | 2 high severity |

## PHP Syntax Check
**Result**: No syntax errors found

Checked directories:
- `src/` (all PHP files)
- `views/` (all PHP files)
- `httpdocs/` (all PHP files)

## JavaScript Linting (ESLint)

**Result**: 698 warnings, 0 errors

### Common Issues:
1. **`no-var`** - Many files still use `var` instead of `let`/`const`
2. **`no-unused-vars`** - Functions defined but called from HTML onclick handlers
3. **`no-console`** - Console statements that should be warn/error only

### Most Affected Files:
- `admin-sidebar.js` - 45+ warnings (no-var)
- `admin-federation.js` - 17 warnings (unused vars)
- `civicone-auth-reset-password.js` - 8 warnings (no-var)
- `civicone-achievements.js` - 5 warnings
- `civicone-auth-login.js` - 2 warnings

### Auto-fixable:
339 warnings can be automatically fixed with `npm run lint:js:fix`

## CSS Linting (Stylelint)

**Result**: 883 issues including 49 critical syntax errors

### Critical: PHP File Saved as CSS
**File**: `httpdocs/assets/css/civicone-consent-required.css`
**Issue**: This is a PHP template file incorrectly saved with .css extension
**Fix Required**: Delete this file (it's a view file, not CSS)

### Critical: Unclosed Block
**File**: `httpdocs/assets/css/federation.css` (line 10922)
**Issue**: CSS block not properly closed
**Fix Required**: Review and close the block

### Orphaned Keyframe Blocks (40 files)
When animations were removed for "GOV.UK compliance", only partial removal was done.
The `@keyframes name { from { ... }` was removed but `to { ... } }` was left behind.

**Pattern causing errors:**
```css
/* Animation removed for GOV.UK compliance */
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

**Affected files:**
- civicone-ai-index.css (line 397)
- civicone-blog.css (line 76)
- civicone-dev-banner.css (line 28)
- civicone-events-calendar.css (line 46)
- civicone-events-edit.css (line 36)
- civicone-feed-show.css (line 97)
- civicone-goals-delete.css (line 38)
- civicone-goals-edit.css (line 75)
- civicone-goals-show.css (line 40)
- civicone-govuk-buttons.css (line 373)
- civicone-groups-discussions-create.css (line 31)
- civicone-groups-discussions-show.css (line 31)
- civicone-groups-edit.css (line 36)
- civicone-groups-invite.css (line 39)
- civicone-groups-my-groups.css (line 35)
- civicone-groups.css (line 578)
- civicone-header.css (line 190)
- civicone-impersonation-banner.css (line 29)
- civicone-legal-volunteer-license.css (line 75)
- civicone-matches.css (line 87)
- civicone-members-directory.css (line 305)
- civicone-mobile-about.css (line 157)
- civicone-mobile.css (line 171)
- civicone-nexus-impact-report.css (line 53)
- civicone-nexus-score-dashboard.css (line 37)
- civicone-org-ui-components.css (line 228)
- civicone-organizations-members.css (line 42)
- civicone-organizations-transfer-requests.css (line 42)
- civicone-organizations-wallet.css (line 38)
- civicone-pages-our-story.css (line 27)
- civicone-pages-privacy.css (line 58)
- civicone-polls-edit.css (line 51)
- civicone-privacy.css (line 497)
- civicone-profile-header.css (line 70)
- civicone-profile-social.css (line 565)
- civicone-profile.css (line 117)
- civicone-resources-form.css (line 70)
- civicone-volunteering-certificate.css (line 75)
- civicone-volunteering-edit-opp.css (line 35)
- civicone-volunteering-edit-org.css (line 42)
- civicone-volunteering-show-org.css (line 41)
- civicone-wallet.css (line 408)

**Fix Required**: Delete the orphaned `to { ... } }` blocks

### Other CSS Issues:

#### Invalid `-var()` Syntax (achievements.css, civicone-events.css)
```css
/* Wrong */
transform: translateY(-var(--space-1));

/* Correct */
transform: translateY(calc(-1 * var(--space-1)));
```

#### Decimal CSS Variables (civicone-federation.css)
CSS variables with decimals like `var(--space-0.5)` are invalid.
These need to be defined or replaced with valid tokens.

#### Unknown Property (auth.css line 323)
```css
border-opacity: 0.5; /* Invalid property */
```

#### Pseudo-element Notation (admin-federation.css, admin-settings.css)
Using `:before` instead of `::before` (single vs double colon)

#### Unknown Word (civicone-onboarding-index.css line 90)
Vendor prefix issue with `-webkit-` usage

## Security Scan

**Result**: No critical vulnerabilities found

### Checked Patterns:
- ✅ No raw SQL injection (uses prepared statements)
- ✅ No direct echo of `$_GET`/`$_POST`/`$_REQUEST`
- ✅ `eval()` only in CSSSanitizer.php (legitimate use)
- ✅ No `mysqli_query` with concatenated strings

## NPM Audit

**Result**: 2 high severity vulnerabilities

Run `npm audit` for details and `npm audit fix` to attempt fixes.

## Recommended Actions

### Priority 1 (Critical)
1. Delete `httpdocs/assets/css/civicone-consent-required.css` (PHP file with wrong extension)
2. Fix unclosed block in `federation.css` at line 10922
3. Remove orphaned keyframe blocks from 40 CSS files

### Priority 2 (Should Fix)
1. Run `npm run lint:js:fix` to auto-fix 339 JS warnings
2. Fix `-var()` syntax in CSS files
3. Fix invalid `border-opacity` property
4. Update pseudo-element notation to use `::`

### Priority 3 (Nice to Have)
1. Replace `var` with `let`/`const` in older JS files
2. Add missing CSS variables for decimal spacing values
3. Run `npm audit fix` for dependency vulnerabilities
