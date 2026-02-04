# React Frontend Pilot Verification Guide

This document covers integration verification and pilot readiness for the React frontend rollout.

## 1. Environment Matrix

| Environment | VITE_API_BASE | VITE_TENANT_ID | Tenant Resolution | X-Tenant-ID Header | Cookies | Auth Method |
|-------------|---------------|----------------|-------------------|-------------------|---------|-------------|
| **Local Dev (CORS)** | `http://staging.timebank.local` | `2` (numeric ID) | Header-based (X-Tenant-ID) | Required | Cross-origin (`credentials: 'include'`) | Bearer tokens |
| **Local Dev (Vite Proxy)** | _(empty)_ | _(empty)_ | Domain-based (proxied to backend) | Not needed | Same-origin | Bearer tokens |
| **Production (Reverse Proxy)** | _(empty)_ | _(empty)_ | Domain-based (HTTP_HOST) | Not needed | Same-origin | Bearer tokens |

### Detailed Breakdown

#### Local Dev with CORS (`VITE_API_BASE` + `VITE_TENANT_ID`)

```env
# .env.development
VITE_API_BASE=http://staging.timebank.local
VITE_TENANT_ID=2
```

- **How it works**: React runs on `localhost:5173`, makes CORS requests to the PHP backend.
- **Tenant resolution**: Uses `X-Tenant-ID: 2` header on all requests.
- **CORS**: Backend must whitelist `http://localhost:5173` in `CorsHelper.php`.
- **Cookies**: Set `credentials: 'include'` to send cookies cross-origin (for session fallback).
- **Auth**: Bearer tokens stored in localStorage, sent via `Authorization` header.

#### Local Dev with Vite Proxy (No CORS)

```ts
// vite.config.ts
export default defineConfig({
  server: {
    proxy: {
      '/api': {
        target: 'http://staging.timebank.local',
        changeOrigin: true,
      },
    },
  },
})
```

```env
# .env.development
VITE_API_BASE=
VITE_TENANT_ID=
```

- **How it works**: Vite proxies `/api/*` to the backend. Same-origin from browser's perspective.
- **Tenant resolution**: Domain-based via `Host` header rewriting (`changeOrigin: true`).
- **CORS**: Not needed (same-origin).
- **Cookies**: Standard same-origin cookie handling.
- **Auth**: Bearer tokens.

#### Production (Same-Domain Reverse Proxy)

```env
# .env.production
VITE_API_BASE=
VITE_TENANT_ID=
```

- **How it works**: Nginx serves React at `/` and proxies `/api/*` to PHP.
- **Tenant resolution**: Domain-based (`HTTP_HOST` matches tenant's configured domain).
- **CORS**: Not needed (same-origin).
- **Cookies**: Standard same-origin.
- **Auth**: Bearer tokens (stateless, no PHP sessions).

---

## 2. Smoke Test Commands

Replace placeholders:
- `<API_BASE>` = `http://staging.timebank.local` (or production domain)
- `<TENANT_ID>` = `2` (numeric tenant ID)
- `<EMAIL>` = test user email
- `<PASSWORD>` = test user password

### 2.1 Bootstrap (Header-based tenant resolution)

```bash
curl -s -H "X-Tenant-ID: <TENANT_ID>" \
  "<API_BASE>/api/v2/tenant/bootstrap" | jq '.data.name, .data.id'
```

Expected: Returns tenant name and ID matching `<TENANT_ID>`.

### 2.2 Bootstrap (Domain-based tenant resolution)

```bash
curl -s "https://hour-timebank.ie/api/v2/tenant/bootstrap" | jq '.data.name, .data.id'
```

Expected: Returns "Timebank Ireland" and ID 2 (resolved by domain).

### 2.3 Login

```bash
curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: <TENANT_ID>" \
  -d '{"email":"<EMAIL>","password":"<PASSWORD>"}' \
  "<API_BASE>/api/auth/login" | jq '.success, .access_token[:20]'
```

Expected: `true` and first 20 chars of access token (or `requires_2fa: true` if 2FA enabled).

### 2.4 Get Current User

```bash
TOKEN="<access_token_from_login>"
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: <TENANT_ID>" \
  "<API_BASE>/api/v2/users/me" | jq '.data.email'
```

Expected: Returns the logged-in user's email.

### 2.5 Get Listings

```bash
TOKEN="<access_token_from_login>"
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: <TENANT_ID>" \
  "<API_BASE>/api/v2/listings?per_page=5" | jq '.data | length'
```

Expected: Returns number of listings (0-5).

### 2.6 Invalid Tenant

```bash
curl -s -H "X-Tenant-ID: 99999" \
  "<API_BASE>/api/v2/tenant/bootstrap"
```

Expected: Returns error (404 or "Tenant not found").

### 2.7 CORS Preflight (localhost)

```bash
curl -s -X OPTIONS \
  -H "Origin: http://localhost:5173" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization, X-Tenant-ID" \
  -i "<API_BASE>/api/auth/login" 2>&1 | grep -E "^(HTTP|Access-Control)"
```

Expected:
```
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: http://localhost:5173
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Tenant-ID, Accept, Origin
Access-Control-Allow-Credentials: true
```

---

## 3. Pilot Readiness Checklist

### Pre-Rollout Verification

#### Nginx/Reverse Proxy Configuration

- [ ] Nginx config routes `/api/*` to PHP backend (port 80/443)
- [ ] Nginx config routes `/*` to React static files or Node server
- [ ] SPA fallback: all non-API, non-asset routes return `index.html`
- [ ] Test: `curl -I https://tenant.domain.com/listings` returns HTML (not 404)
- [ ] Verify no double `/api/api/` paths in proxy config

#### SSL/TLS

- [ ] SSL certificate valid for tenant domain
- [ ] HTTPS redirect configured
- [ ] Mixed content check: no HTTP resources loaded over HTTPS

#### Caching

- [ ] Verify `cache: 'no-store'` in bootstrap fetch prevents stale tenant config
- [ ] Static assets (JS/CSS) have cache headers with content hash in filename
- [ ] `index.html` has `Cache-Control: no-cache` or short TTL
- [ ] CDN (if used) properly invalidates on deploy

#### Service Worker Conflicts

- [ ] No legacy service worker from PHP frontend interfering
- [ ] If migrating from PWA PHP app, unregister old service worker:
  ```js
  navigator.serviceWorker.getRegistrations().then(regs =>
    regs.forEach(r => r.unregister())
  );
  ```
- [ ] React app does NOT register a service worker (by design for pilot)
- [ ] Clear `Cache Storage` in browser dev tools if issues persist

#### Backend Readiness

- [ ] `TenantBootstrapController` returns correct data for tenant domain
- [ ] CORS whitelist includes production domain (if different from same-origin)
- [ ] Rate limiting configured for API endpoints
- [ ] Auth endpoints working (`/api/auth/login`, `/api/auth/refresh-token`)

#### Feature Flags

- [ ] Tenant has required features enabled in `features` JSON
- [ ] Test with feature disabled to ensure graceful degradation

---

## 4. Rollout Steps (Single Tenant Pilot)

### Step 1: Deploy React Build

```bash
cd react-frontend
npm run build
# Upload dist/ to production server
scp -r dist/* user@server:/var/www/react-frontend/
```

### Step 2: Update Nginx Config

```nginx
server {
    listen 443 ssl;
    server_name pilot-tenant.example.com;

    # SSL config...

    # API routes to PHP
    location /api/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Static assets
    location /assets/ {
        alias /var/www/react-frontend/assets/;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # SPA fallback
    location / {
        root /var/www/react-frontend;
        try_files $uri $uri/ /index.html;
        add_header Cache-Control "no-cache";
    }
}
```

### Step 3: Test Deployment

```bash
# Bootstrap
curl -s https://pilot-tenant.example.com/api/v2/tenant/bootstrap | jq '.data.name'

# SPA routing
curl -s -o /dev/null -w "%{http_code}" https://pilot-tenant.example.com/listings
# Should return 200 (HTML), not 404
```

### Step 4: Monitor

- Check browser console for errors
- Monitor backend error logs
- Watch for 401 errors (token refresh issues)
- Verify tenant branding loads correctly

---

## 5. Rollback Procedure

### Immediate Rollback (< 5 minutes)

1. Update Nginx to serve PHP frontend again:
   ```bash
   # Switch symlink or update config
   ln -sfn /var/www/php-frontend /var/www/active-frontend
   nginx -s reload
   ```

2. Or revert Nginx config:
   ```bash
   cp /etc/nginx/sites-available/tenant.conf.backup /etc/nginx/sites-available/tenant.conf
   nginx -t && nginx -s reload
   ```

### Data Rollback

- React frontend is read-only; no database changes to revert.
- LocalStorage tokens may need clearing if format changed:
  ```js
  localStorage.removeItem('nexus_access_token');
  localStorage.removeItem('nexus_refresh_token');
  localStorage.removeItem('nexus_user');
  ```

### DNS Rollback (if using separate subdomain)

- Point DNS back to PHP server
- TTL should be low (60-300s) during pilot

---

## 6. Known Limitations (Pilot Phase)

| Feature | Status | Notes |
|---------|--------|-------|
| Full module parity | Partial | Only Login, Home, Listings implemented |
| WebAuthn/Passkeys | Not implemented | Fall back to TOTP or password |
| Push notifications | Not implemented | Use PHP frontend for push |
| Offline support | Not implemented | No service worker by design |
| Real-time updates | Not implemented | No Pusher integration yet |

---

## 7. Troubleshooting

### "Failed to load tenant configuration"

1. Check browser Network tab for the `/api/v2/tenant/bootstrap` request
2. Verify status code and response body
3. If 404: route not registered or wrong API_BASE
4. If CORS error: check `CorsHelper.php` whitelist
5. If wrong tenant: check domain or `X-Tenant-ID` header

### "Session expired" after login

1. Check if refresh token exists in localStorage
2. Verify `/api/auth/refresh-token` endpoint works
3. Check for clock skew between client and server
4. Look for `SESSION_EXPIRED` event in console

### Tenant ID Mismatch Warning

```
[Tenant Mismatch] VITE_TENANT_ID is "2" but API returned tenant ID "1"
```

This means the backend resolved a different tenant than configured. Check:
- Is the correct backend being hit?
- Is domain-based resolution overriding the header?
- Is there a proxy misconfiguration?

### Blank Page / White Screen

1. Check browser console for JavaScript errors
2. Verify `index.html` is being served (not 404)
3. Check if JS bundle loaded (Network tab)
4. Verify Tailwind CSS is compiled

---

## 8. Monitoring Checklist (Post-Rollout)

- [ ] Error rate in browser console (via error tracking)
- [ ] API response times (backend APM)
- [ ] 401 error rate (indicates auth issues)
- [ ] Bootstrap success rate
- [ ] User feedback channel active
