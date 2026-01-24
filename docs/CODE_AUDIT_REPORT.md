# Project NEXUS - Code Audit Report

**Date:** 2026-01-24
**Auditor:** Claude Code (Automated Audit)
**Branch:** claude/code-audit-3kDLT

---

## Executive Summary

This comprehensive audit of the Project NEXUS codebase identified **22 security issues** and **50+ code quality violations**. The most critical findings involve missing CSRF protection on super-admin endpoints and SQL injection vulnerabilities in scripts.

| Severity | Count | Status |
|----------|-------|--------|
| Critical | 4 | Requires immediate fix |
| High | 8 | Fix within 1 sprint |
| Medium | 10 | Fix within 2 sprints |
| Low | 15+ | Address during normal maintenance |

---

## 1. SECURITY VULNERABILITIES

### 1.1 CSRF Token Validation Missing (CRITICAL)

**Location:** `src/Controllers/MasterController.php`

All POST endpoints in the Super Admin controller lack CSRF token validation, making them vulnerable to Cross-Site Request Forgery attacks.

**Affected Methods:**
| Line | Method | Risk |
|------|--------|------|
| 32 | `createTenant()` | Attackers can create arbitrary tenants |
| 93 | `addAdmin()` | Attackers can add admin users |
| 122 | `deleteAdmin()` | Attackers can remove admin users |
| 158 | `update()` | Attackers can modify tenant settings |
| 255 | `deleteUser()` | Attackers can delete any user |
| 274 | `approveUser()` | Attackers can approve pending users |

**Code Example (Line 32-35):**
```php
public function createTenant()
{
    $this->checkSuperAdmin();  // Only checks session - NO CSRF validation!
    $name = $_POST['name'] ?? '';
```

**Fix Required:**
```php
public function createTenant()
{
    $this->checkSuperAdmin();
    \Nexus\Core\Csrf::verifyOrDie();  // ADD THIS LINE
    $name = $_POST['name'] ?? '';
```

---

### 1.2 SQL Injection in Scripts (HIGH)

#### 1.2.1 GroupSeeder.php

**Location:** `scripts/seeders/GroupSeeder.php:120`

```php
// VULNERABLE - Direct interpolation
$this->pdo->exec("UPDATE groups SET cached_member_count = cached_member_count + 1 WHERE id = {$groupId}");
```

**Fix:**
```php
$stmt = $this->pdo->prepare("UPDATE groups SET cached_member_count = cached_member_count + 1 WHERE id = ?");
$stmt->execute([$groupId]);
```

#### 1.2.2 seed_database.php

**Location:** `scripts/seed_database.php:179`

```php
// VULNERABLE
$pdo->exec("DELETE FROM {$table} WHERE tenant_id = {$config['tenant_id']}");
```

#### 1.2.3 Migration Scripts

| File | Lines | Issue |
|------|-------|-------|
| `scripts/check_gamification_tables.php` | 43 | `SHOW TABLES LIKE '$table'` |
| `scripts/check_v2_migration.php` | 33, 45, 59 | Table/column interpolation |
| `scripts/migrations/create_federation_tables.php` | 49, 61, 448+ | ALTER TABLE with interpolation |
| `scripts/migrations/create_gamification_tables.php` | 36, 48, 579 | DDL with interpolation |

**Note:** While these scripts are admin-only and table/column names are hardcoded, the pattern is dangerous and could be exploited if ever extended.

---

### 1.3 Shell Command Execution (MEDIUM)

**Location:** `src/Controllers/Admin/EnterpriseController.php`

```php
// Line 1869 - Memory info
$freeOutput = @shell_exec('free -m 2>/dev/null');

// Line 1920 - System uptime
$uptimeOutput = @shell_exec('uptime -s 2>/dev/null');

// Line 1942 - CPU count
$nproc = @shell_exec('nproc 2>/dev/null');

// Line 2451 - Dynamic command (properly escaped)
$output = @shell_exec(escapeshellcmd($info['check_command']));
```

**Status:** Hardcoded commands are relatively safe. Line 2451 uses `escapeshellcmd()` which provides some protection.

**Recommendation:** Use `proc_open()` for better control or disable shell execution entirely.

---

### 1.4 SeedGeneratorController SQL Pattern (MEDIUM)

**Location:** `src/Controllers/Admin/SeedGeneratorController.php`

Multiple instances of table name interpolation:
- Line 162: `SELECT COUNT(*) as count FROM \`{$tableName}\``
- Line 352: `INSERT INTO \`{$tableName}\` ...`
- Lines 315, 317, 578, 580, 814, 842, 874

**Note:** Table names are validated against a whitelist, but the pattern remains risky.

---

## 2. CODE QUALITY ISSUES

### 2.1 Inline Styles in PHP/HTML (CRITICAL - RULE VIOLATION)

Per CLAUDE.md: "**NEVER** use inline `style=""` attributes except for truly dynamic values"

**Top Violating Files:**

| File | Violations |
|------|------------|
| `views/search/results.php` | 30+ inline styles |
| `views/pages/about-story.php` | 23+ inline styles |
| `views/legal/volunteer-license.php` | 17+ inline styles |
| `views/pages/mobile.php` | 15+ inline styles |
| `views/errors/private_profile.php` | 5 inline styles |
| `views/skeleton/groups/show.php` | 7 inline styles |

---

### 2.2 Inline `<style>` Blocks in PHP (CRITICAL - RULE VIOLATION)

Per CLAUDE.md: "**NEVER** write inline `<style>` blocks in PHP/HTML files"

| File | Lines of CSS | Issue |
|------|-------------|-------|
| `views/pages/dynamic.php` | 4,000+ | Massive embedded CSS |
| `views/errors/404.php` | 67 | Full stylesheet in file |
| `views/errors/403.php` | 67 | Duplicate of 404.php styles |
| `views/groups/analytics.php` | 200+ | Large inline stylesheet |
| `views/500.php` | 100+ | Inline error page styles |

---

### 2.3 Inline `<script>` Blocks in PHP (CRITICAL - RULE VIOLATION)

Per CLAUDE.md: "**NEVER** write large inline `<script>` blocks in PHP files"

| File | Lines of JS | Description |
|------|-------------|-------------|
| `views/pages/dynamic.php` | 200+ | Animation and interaction JS |
| `views/components/group-recommendations-widget.php` | 88 | Widget JavaScript |
| `views/search/results.php` | 33 | Tab switching logic |
| `views/modern/groups/show.php` | 100+ | Group page interactions |
| `views/modern/auth/login.php` | 150+ | WebAuthn and login JS |

---

### 2.4 Hardcoded Colors Instead of CSS Variables (HIGH)

Per CLAUDE.md: "Always use CSS variables from `design-tokens.css` instead of hardcoded hex colors"

**Heavily Affected Files:**

| File | Hardcoded Colors |
|------|------------------|
| `views/pages/dynamic.php` | 50+ unique colors |
| `views/groups/analytics.php` | 20+ unique colors |
| `views/500.php` | 20+ unique colors |
| `views/error/404.php` | 13+ unique colors |

**Common Violations:**
```css
/* Found in codebase - should use variables */
#6366f1   → var(--color-primary-500)
#10b981   → var(--color-success-500)
#ef4444   → var(--color-error-500)
#f59e0b   → var(--color-warning-500)
#6b7280   → var(--color-gray-500)
#111827   → var(--color-gray-900)
#f3f4f6   → var(--color-gray-100)
```

---

### 2.5 Direct Style Manipulation in JavaScript (HIGH)

Per ESLint rules: "element.style.display = 'none'" should be "element.classList.add('hidden')"

**150+ violations found across JavaScript files:**

| File | Violations |
|------|------------|
| `httpdocs/assets/js/nexus-turbo.js` | 15+ |
| `httpdocs/assets/js/mobile-interactions.js` | 8+ |
| `httpdocs/assets/js/nexus-instant-load.js` | 12+ |
| `httpdocs/assets/js/fab-polish.js` | 6+ |
| `httpdocs/assets/js/notifications.js` | 5+ |

---

### 2.6 Console.log Usage (MEDIUM)

Per ESLint rules: Use `console.warn` or `console.error` only.

**86+ console.log statements found:**

| File | Count |
|------|-------|
| `views/modern/auth/login.php` | 13 |
| `httpdocs/assets/js/notifications.js` | 11 |
| `views/modern/groups/show.php` | 8 |
| `views/modern/groups/index.php` | 7 |
| `views/layouts/admin-header.php` | 6 |
| `views/admin/test-runner/dashboard.php` | 6 |

---

### 2.7 Massive Files Requiring Refactoring (MEDIUM)

| File | Lines | Recommendation |
|------|-------|----------------|
| `views/pages/dynamic.php` | 16,325 | Split into components, extract CSS/JS |
| `src/Controllers/Api/AiApiController.php` | 3,500+ | Split by functionality |
| `src/Controllers/Api/SocialApiController.php` | 1,500+ | Split by resource type |

---

### 2.8 Duplicate Code (LOW)

**Error Pages:**
- `views/errors/404.php` and `views/errors/403.php` share 67 identical lines of CSS
- Should extract to shared `error-pages.css`

---

## 3. RECOMMENDATIONS

### Immediate Actions (Critical)

1. **Add CSRF protection to MasterController** - All 6 POST methods need `Csrf::verifyOrDie()` calls

2. **Fix SQL injection in GroupSeeder** - Use prepared statement at line 120

3. **Extract massive inline CSS** from:
   - `views/pages/dynamic.php` → `httpdocs/assets/css/dynamic-page.css`
   - `views/errors/404.php` → `httpdocs/assets/css/error-pages.css`
   - `views/groups/analytics.php` → `httpdocs/assets/css/group-analytics.css`

### Short-Term Actions (High)

4. **Extract inline JavaScript** from PHP files to:
   - `httpdocs/assets/js/search-results.js`
   - `httpdocs/assets/js/group-recommendations.js`
   - `httpdocs/assets/js/webauthn-login.js`

5. **Replace hardcoded colors** with CSS variables systematically

6. **Refactor element.style manipulations** to use CSS classes

### Medium-Term Actions

7. **Migrate all scripts to use prepared statements** - Even for DDL operations, use `$db->quote()` for identifiers

8. **Replace console.log** with console.warn/error or remove entirely

9. **Split large files** into smaller, focused modules

10. **Add purgecss tracking** for any new CSS files created

---

## 4. FILES REQUIRING IMMEDIATE ATTENTION

### Security Priority
1. `src/Controllers/MasterController.php` - CSRF missing
2. `scripts/seeders/GroupSeeder.php:120` - SQL injection
3. `scripts/seed_database.php:179` - SQL injection

### Code Quality Priority
1. `views/pages/dynamic.php` - 16,325 lines, needs complete refactoring
2. `views/search/results.php` - 30+ inline styles
3. `views/errors/404.php` & `views/errors/403.php` - Duplicate inline CSS

---

## 5. CODEBASE STATISTICS

| Metric | Value |
|--------|-------|
| PHP Files (src/) | 355 |
| Models | 59 |
| Services | 90+ |
| Controllers | 75+ |
| API Routes | 984 |
| CSS Files | 866 |
| JavaScript Files | 205 |
| Security Issues Found | 22 |
| Code Quality Violations | 50+ |

---

## 6. POSITIVE FINDINGS

The audit also identified several well-implemented security measures:

- **Database class** enforces prepared statements and validates against array parameters
- **TenantContext** provides consistent multi-tenant isolation
- **HtmlSanitizer** properly prevents XSS in user content
- **CSRF protection** is implemented in Core (just not used consistently)
- **File uploads** validate MIME types and use secure random filenames
- **Password hashing** uses `PASSWORD_DEFAULT` (bcrypt)
- **Session security** properly configured with httponly, secure, samesite flags
- **Security headers** (HSTS, CSP, X-Frame-Options) are set in index.php

---

*Report generated by automated code audit. Manual review recommended for critical findings.*
