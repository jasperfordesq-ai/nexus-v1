# Pilot Cutover Runbook: React Frontend via Plesk + Nginx

**Purpose**: Cut over ONE pilot tenant domain to the React frontend using same-domain reverse proxy.

**Architecture**:
- PHP backend remains at existing docroot (`httpdocs/`), serves `/api/*`
- React build deployed to separate folder (`/react-app/`)
- Nginx routes requests: React for UI, PHP for API
- Host header preserved for tenant resolution

---

## 1. Choose Pilot Domain and Pre-Flight Checklist

### 1.1 Pilot Domain Selection Criteria

| Criterion | Recommendation |
|-----------|----------------|
| Traffic volume | Low-to-medium (not your busiest tenant) |
| User base | Internal team or friendly early adopters |
| Features used | Core features only (listings, login, basic navigation) |
| Rollback tolerance | Users can tolerate 5-minute rollback window |

**Recommended pilot**: `hour-timebank.ie` (tenant ID 2) or a staging subdomain.

**Your chosen pilot domain**: `____________________`

### 1.2 Pre-Flight Checklist

#### SSL Certificate Status

1. **Plesk** → **Websites & Domains** → **`<pilot-domain>`** → **SSL/TLS Certificates**
2. Verify:
   - [ ] Let's Encrypt certificate is **active** and **not expiring within 7 days**
   - [ ] Certificate covers the exact domain (not just the primary)
   - [ ] "Redirect from http to https" is enabled

#### Existing Site Works (Legacy Baseline)

```bash
# Test legacy site loads
curl -s -o /dev/null -w "%{http_code}" "https://<pilot-domain>/"
# Expected: 200

# Test legacy login page
curl -s -o /dev/null -w "%{http_code}" "https://<pilot-domain>/login"
# Expected: 200

# Test API endpoint
curl -s "https://<pilot-domain>/api/v2/tenant/bootstrap" | jq '.data.name'
# Expected: "<Tenant Name>"
```

- [ ] Legacy homepage returns 200
- [ ] Legacy login page returns 200
- [ ] API bootstrap returns correct tenant name

#### Tenant Bootstrap Works

```bash
# Verify tenant resolves correctly by domain
curl -s -H "Host: <pilot-domain>" \
  "https://<pilot-domain>/api/v2/tenant/bootstrap" | jq '.data.id, .data.name'
```

- [ ] Returns expected tenant ID and name
- [ ] No CORS errors (same-origin request)

#### Rollback Plan Ready

- [ ] I know where the Nginx directives will be added (Section 3)
- [ ] I know how to remove them (Section 6)
- [ ] I have SSH access to the server (for emergency file fixes)
- [ ] I have noted the current time: `____:____` (for log correlation)

---

## 2. Build and Deploy React

### 2.1 Local Build Commands

```bash
# Navigate to React frontend
cd react-frontend

# Install dependencies (if not already done)
npm install

# Build for production
npm run build
```

**Output**: `react-frontend/dist/` folder containing:
```
dist/
├── index.html
├── assets/
│   ├── index-XXXXXXXX.js
│   ├── index-XXXXXXXX.css
│   └── ... (other chunks)
└── vite.svg (or other static files)
```

### 2.2 Server Destination Path

**Plesk vhost root pattern**:
```
/var/www/vhosts/<primary-domain>/
```

**React app destination** (DO NOT put in httpdocs):
```
/var/www/vhosts/<primary-domain>/react-app/
```

For example, if your primary domain is `project-nexus.ie`:
```
/var/www/vhosts/project-nexus.ie/react-app/
```

### 2.3 Upload Method A: SCP (Recommended)

```bash
# From your local machine, in the react-frontend directory
# First, create the destination folder on server
ssh user@<server-ip> "mkdir -p /var/www/vhosts/<primary-domain>/react-app"

# Upload the build
scp -r dist/* user@<server-ip>:/var/www/vhosts/<primary-domain>/react-app/

# Verify upload
ssh user@<server-ip> "ls -la /var/www/vhosts/<primary-domain>/react-app/"
```

**Expected output**:
```
total XX
drwxr-xr-x  3 user psacln 4096 Feb  3 12:00 .
drwxr-xr-x 10 user psacln 4096 Feb  3 12:00 ..
drwxr-xr-x  2 user psacln 4096 Feb  3 12:00 assets
-rw-r--r--  1 user psacln  XXX Feb  3 12:00 index.html
-rw-r--r--  1 user psacln  XXX Feb  3 12:00 vite.svg
```

### 2.4 Upload Method B: Plesk File Manager

1. **Plesk** → **Files**
2. Navigate to `/var/www/vhosts/<primary-domain>/`
3. Click **+ New** → **Directory** → Name: `react-app` → **OK**
4. Enter `react-app` folder
5. Click **Upload** → Select all files from local `dist/` folder
6. For `assets/` folder: Create directory first, then upload contents inside

### 2.5 Verify Deployment

```bash
# SSH to server and verify
ssh user@<server-ip>

# Check index.html exists
cat /var/www/vhosts/<primary-domain>/react-app/index.html | head -5

# Check assets folder has JS/CSS
ls -la /var/www/vhosts/<primary-domain>/react-app/assets/
```

- [ ] `index.html` exists and contains `<div id="root">`
- [ ] `assets/` folder contains `.js` and `.css` files with hashes in filenames

---

## 3. Plesk Nginx Directives for Pilot Domain

### 3.1 Where to Add

1. **Plesk** → **Websites & Domains**
2. Find your **`<pilot-domain>`** (the specific domain/alias, not the subscription)
3. Click **Hosting & DNS** → **Apache & nginx Settings**
4. Scroll down to **Additional nginx directives**
5. Paste the block below into the text area

### 3.2 The Nginx Directives Block

```nginx
# =============================================================
# REACT FRONTEND PILOT - <pilot-domain>
# Added: <DATE>
# Purpose: Route UI to React, API to PHP
# Rollback: Delete this entire block and click Apply
# =============================================================

# React app root (adjust path to match your vhost)
set $react_root /var/www/vhosts/<primary-domain>/react-app;

# -------------------------------------------------------------
# 1. API ROUTES → PHP (preserve existing behavior)
# -------------------------------------------------------------
# Plesk already handles PHP via its own fastcgi config.
# We just ensure /api/ goes to the docroot and hits index.php.
location /api/ {
    # Let Plesk's default PHP handling take over
    # This location block just ensures priority
    try_files $uri $uri/ /index.php?$query_string;
}

# -------------------------------------------------------------
# 2. UPLOADS → PHP docroot (user-uploaded files)
# -------------------------------------------------------------
location /uploads/ {
    alias /var/www/vhosts/<primary-domain>/httpdocs/uploads/;
    expires 7d;
    add_header Cache-Control "public";
}

# -------------------------------------------------------------
# 3. LEGACY ESCAPE HATCH → PHP docroot
# -------------------------------------------------------------
# Access old PHP frontend at /legacy/...
location /legacy/ {
    alias /var/www/vhosts/<primary-domain>/httpdocs/;
    try_files $uri $uri/ /legacy/index.php?$query_string;
}

# -------------------------------------------------------------
# 4. REACT ASSETS → Cached, immutable
# -------------------------------------------------------------
location /assets/ {
    alias $react_root/assets/;
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header X-Served-By "react-app";
}

# -------------------------------------------------------------
# 5. REACT STATIC FILES (favicon, manifest, etc.)
# -------------------------------------------------------------
location = /vite.svg {
    root $react_root;
    expires 7d;
}

location = /favicon.ico {
    root $react_root;
    expires 7d;
    log_not_found off;
}

# -------------------------------------------------------------
# 6. REACT SPA FALLBACK → index.html
# -------------------------------------------------------------
# All other routes serve React's index.html for client-side routing
location / {
    root $react_root;
    try_files $uri $uri/ /index.html;

    # Prevent caching of index.html (so deploys take effect)
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header X-Served-By "react-app";
}
```

### 3.3 Before You Paste: Customize These Values

| Placeholder | Replace With | Example |
|-------------|--------------|---------|
| `<pilot-domain>` | Your pilot domain | `hour-timebank.ie` |
| `<primary-domain>` | Plesk subscription's primary domain | `project-nexus.ie` |
| `<DATE>` | Today's date | `2026-02-03` |

### 3.4 Important Notes

1. **Plesk handles PHP automatically**: Do NOT add `fastcgi_pass` directives. Plesk's Apache/nginx integration already routes `.php` files correctly.

2. **Host header is preserved**: Nginx passes the original `Host` header to PHP by default. `TenantContext` will resolve the correct tenant.

3. **Order matters**: Nginx processes `location` blocks by specificity. `/api/` and `/assets/` are more specific than `/`, so they match first.

4. **The `$react_root` variable**: Makes the config easier to read and modify.

---

## 4. Cutover Procedure (Minute-by-Minute)

### Preparation (T-5 minutes)

- [ ] Have this runbook open
- [ ] Have Plesk open in browser, logged in
- [ ] Have terminal ready with SSH connection
- [ ] Have browser ready with pilot domain
- [ ] Note current time for log correlation: `____:____`

### T+0: Add Directives

1. **Plesk** → **Websites & Domains** → **`<pilot-domain>`**
2. **Hosting & DNS** → **Apache & nginx Settings**
3. Scroll to **Additional nginx directives**
4. **Paste** the customized block from Section 3.2
5. **DO NOT click Apply yet** — proceed to verify syntax

### T+1: Verify and Apply

1. Scroll down, click **Apply**
2. Watch for:
   - ✅ Green success message: "Settings were successfully updated"
   - ❌ Red error: Nginx syntax error — **DO NOT proceed**, fix the config

**If error**: Check for typos in paths, missing semicolons, or unclosed braces.

### T+2: Immediate Browser Tests

Open these URLs in a **private/incognito window** (to avoid cache):

| # | URL | Expected Result |
|---|-----|-----------------|
| 1 | `https://<pilot-domain>/` | React app loads (see "Timebank Ireland" or tenant name in navbar) |
| 2 | `https://<pilot-domain>/login` | React login form appears |
| 3 | `https://<pilot-domain>/listings` | React listings page loads |
| 4 | `https://<pilot-domain>/api/v2/tenant/bootstrap` | JSON response with tenant data |
| 5 | `https://<pilot-domain>/legacy/` | Old PHP homepage loads |

**Checklist**:
- [ ] Test 1: React homepage loads
- [ ] Test 2: React login page loads
- [ ] Test 3: React listings page loads
- [ ] Test 4: API returns JSON
- [ ] Test 5: Legacy escape hatch works

### T+4: Immediate Curl Tests

```bash
# 1. React index.html served
curl -s -I "https://<pilot-domain>/" | grep -E "^(HTTP|X-Served-By|Content-Type)"
# Expected: HTTP/2 200, X-Served-By: react-app, Content-Type: text/html

# 2. React assets served with cache headers
curl -s -I "https://<pilot-domain>/assets/" | head -1
# Expected: HTTP/2 200 (or 403 if directory listing disabled - that's OK)

# 3. API still works
curl -s "https://<pilot-domain>/api/v2/tenant/bootstrap" | jq '.data.id'
# Expected: Tenant ID (e.g., 2)

# 4. SPA routing works (non-existent path returns index.html)
curl -s "https://<pilot-domain>/some/fake/path" | grep -o '<div id="root">'
# Expected: <div id="root">

# 5. Legacy escape hatch
curl -s -o /dev/null -w "%{http_code}" "https://<pilot-domain>/legacy/"
# Expected: 200
```

### Success Criteria

All tests pass = **Cutover successful** ✅

### Error Diagnosis

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| 502 Bad Gateway | Nginx config error | Check Plesk error, rollback |
| 404 on `/` | `$react_root` path wrong | Fix path in directives |
| 404 on `/assets/*` | Assets not uploaded or path wrong | Verify files exist |
| API returns HTML | `/api/` location not matching | Check location block order |
| Wrong tenant in API | Host header not reaching PHP | Should not happen with this config |
| Blank page | JS error in React | Check browser console |

---

## 5. Post-Cutover Monitoring (First 60 Minutes)

### 5.1 Log Locations (Plesk)

**Via Plesk UI**:
1. **Websites & Domains** → **`<pilot-domain>`** → **Logs**
2. Filter by "nginx access" and "nginx error"

**Via SSH**:
```bash
# Nginx access log (adjust domain)
tail -f /var/www/vhosts/<primary-domain>/logs/access_ssl_log

# Nginx error log
tail -f /var/www/vhosts/<primary-domain>/logs/error_log

# Or Plesk's domain-specific logs
tail -f /var/www/vhosts/system/<pilot-domain>/logs/accesslog
tail -f /var/www/vhosts/system/<pilot-domain>/logs/error_log
```

### 5.2 What to Watch For

| Issue | Log Pattern | Action |
|-------|-------------|--------|
| 404 on assets | `GET /assets/index-XXX.js 404` | Assets not deployed or path wrong |
| 500 on API | `FastCGI sent in stderr: "PHP..."` | PHP error, check PHP logs |
| Repeated 401s | `POST /api/auth/refresh-token 401` | Token refresh loop, check React console |
| Tenant mismatch | N/A (client-side) | Check browser console for warning |
| Mixed content | N/A (browser) | Check browser console for HTTPS errors |

### 5.3 Browser Console Monitoring

1. Open `https://<pilot-domain>/` in Chrome/Firefox
2. Open Developer Tools (F12) → Console tab
3. Look for:
   - ❌ Red errors (JS crashes, network failures)
   - ⚠️ Yellow warnings (especially `[Tenant Mismatch]`)
   - 🔄 Repeated network requests (refresh loops)

### 5.4 Quick Health Check Script

```bash
#!/bin/bash
DOMAIN="<pilot-domain>"

echo "=== Health Check: $DOMAIN ==="

echo -n "Homepage: "
curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/"
echo

echo -n "API Bootstrap: "
curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/api/v2/tenant/bootstrap"
echo

echo -n "React Assets: "
curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/assets/"
echo

echo -n "Legacy Escape: "
curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/legacy/"
echo

echo "=== Done ==="
```

---

## 6. Rollback Procedure (Fast)

**Target time**: Under 2 minutes

### 6.1 Undo in Plesk

1. **Plesk** → **Websites & Domains** → **`<pilot-domain>`**
2. **Hosting & DNS** → **Apache & nginx Settings**
3. Scroll to **Additional nginx directives**
4. **Delete the entire block** you added (everything between the `# ===` markers)
5. Click **Apply**
6. Wait for success message

### 6.2 Verify Rollback

```bash
# Test 1: Homepage returns PHP (look for legacy HTML patterns)
curl -s "https://<pilot-domain>/" | grep -o "NEXUS\|timebank" | head -1
# Expected: Some text from your PHP frontend

# Test 2: Login page is PHP
curl -s "https://<pilot-domain>/login" | grep -o "form\|csrf" | head -1
# Expected: "form" or "csrf" (PHP login page elements)
```

- [ ] Homepage loads PHP version
- [ ] Login page loads PHP version

### 6.3 Client-Side Cleanup (If Users Report Issues)

If users see stale React app after rollback:

**Option A**: Tell users to hard refresh
- Windows/Linux: `Ctrl + Shift + R`
- Mac: `Cmd + Shift + R`

**Option B**: Clear service worker (if legacy PHP had one)

Tell affected users to run this in browser console:
```javascript
// Clear all service workers
navigator.serviceWorker.getRegistrations().then(function(registrations) {
  for(let registration of registrations) {
    registration.unregister();
    console.log('Unregistered:', registration);
  }
});

// Clear caches
caches.keys().then(function(names) {
  for (let name of names) {
    caches.delete(name);
    console.log('Deleted cache:', name);
  }
});

// Reload
location.reload(true);
```

---

## 7. Optional: Legacy Login Redirect

### 7.1 Recommendation

If users have bookmarked `/login` from the old site, they'll now see the React login.
This is **usually fine** — React login works.

However, if you want `/legacy/login` to redirect to `/login` (React), add this to the nginx directives:

### 7.2 Redirect Snippet (Add to Section 3.2 if desired)

```nginx
# -------------------------------------------------------------
# 7. LEGACY LOGIN REDIRECT (Optional)
# -------------------------------------------------------------
# Redirect /legacy/login to React login
location = /legacy/login {
    return 301 /login;
}

# Redirect /legacy/login/ (with trailing slash)
location = /legacy/login/ {
    return 301 /login;
}
```

### 7.3 Alternative: Banner on Legacy Pages

If you keep `/legacy/` as a full escape hatch, consider adding a banner in the PHP layout:

```php
<!-- In views/layouts/modern/header.php or similar -->
<?php if (strpos($_SERVER['REQUEST_URI'], '/legacy/') === 0): ?>
<div style="background: #fef3c7; padding: 12px; text-align: center; border-bottom: 1px solid #f59e0b;">
  <strong>You're viewing the legacy site.</strong>
  <a href="/" style="color: #d97706; text-decoration: underline;">Try the new experience →</a>
</div>
<?php endif; ?>
```

---

## 8. Quick Reference Card

### Key Paths
| Item | Path |
|------|------|
| React build | `/var/www/vhosts/<primary-domain>/react-app/` |
| PHP docroot | `/var/www/vhosts/<primary-domain>/httpdocs/` |
| Nginx access log | `/var/www/vhosts/<primary-domain>/logs/access_ssl_log` |
| Nginx error log | `/var/www/vhosts/<primary-domain>/logs/error_log` |

### Key URLs (Post-Cutover)
| URL | Should Serve |
|-----|--------------|
| `/` | React |
| `/login` | React |
| `/listings` | React |
| `/api/*` | PHP |
| `/uploads/*` | PHP (files) |
| `/legacy/*` | PHP (escape hatch) |

### Emergency Contacts
| Role | Contact |
|------|---------|
| DevOps | `_______________` |
| Backend dev | `_______________` |
| Stakeholder | `_______________` |

---

## 9. Post-Pilot Checklist (After 24-48 Hours)

- [ ] No critical errors in logs
- [ ] Users can log in successfully
- [ ] Listings page loads data
- [ ] API response times normal
- [ ] No tenant mismatch warnings reported
- [ ] Rollback plan still documented and ready
- [ ] Ready to proceed to next tenant? (repeat from Section 1)

---

## Appendix: Full Nginx Block (Copy-Paste Ready)

Replace `<primary-domain>` with your actual domain before pasting.

```nginx
# =============================================================
# REACT FRONTEND PILOT
# Added: 2026-02-03
# Purpose: Route UI to React, API to PHP
# Rollback: Delete this entire block and click Apply
# =============================================================

set $react_root /var/www/vhosts/<primary-domain>/react-app;

location /api/ {
    try_files $uri $uri/ /index.php?$query_string;
}

location /uploads/ {
    alias /var/www/vhosts/<primary-domain>/httpdocs/uploads/;
    expires 7d;
    add_header Cache-Control "public";
}

location /legacy/ {
    alias /var/www/vhosts/<primary-domain>/httpdocs/;
    try_files $uri $uri/ /legacy/index.php?$query_string;
}

location /assets/ {
    alias $react_root/assets/;
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header X-Served-By "react-app";
}

location = /vite.svg {
    root $react_root;
    expires 7d;
}

location = /favicon.ico {
    root $react_root;
    expires 7d;
    log_not_found off;
}

location / {
    root $react_root;
    try_files $uri $uri/ /index.html;
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header X-Served-By "react-app";
}
```
