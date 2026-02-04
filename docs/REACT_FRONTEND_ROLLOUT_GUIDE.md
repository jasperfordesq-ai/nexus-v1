# React Frontend Rollout Guide

## Project NEXUS - Hero UI Frontend Deployment via Same-Domain Reverse Proxy

**Date:** 2026-02-03
**Status:** Rollout Plan
**Approach:** Option B - Same-Domain Reverse Proxy

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Cloudflare DNS Setup](#2-cloudflare-dns-setup)
3. [Plesk Setup](#3-plesk-setup)
4. [Reverse Proxy Configuration](#4-reverse-proxy-configuration)
5. [Tenant Bootstrap Integration](#5-tenant-bootstrap-integration)
6. [Rollout Strategy](#6-rollout-strategy)
7. [Gotchas Checklist](#7-gotchas-checklist)

---

## 1. Architecture Overview

### 1.1 Current State (PHP Serves Everything)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              CURRENT STATE                               │
└─────────────────────────────────────────────────────────────────────────┘

  User Browser                    Cloudflare                     Plesk Server
 ┌───────────┐                  ┌───────────┐                  ┌─────────────────┐
 │           │   DNS Lookup     │           │                  │                 │
 │  Request  │ ───────────────► │   Proxy   │ ───────────────► │     Nginx       │
 │           │                  │   + SSL   │   Host Header    │       │         │
 │ hour-     │                  │           │   Preserved      │       ▼         │
 │ timebank  │                  │           │                  │  ┌──────────┐   │
 │ .ie       │ ◄─────────────── │           │ ◄─────────────── │  │   PHP    │   │
 │           │   HTML/JSON      │           │                  │  │  (all    │   │
 └───────────┘                  └───────────┘                  │  │  routes) │   │
                                                               │  └──────────┘   │
                                                               │       │         │
                                                               │       ▼         │
                                                               │  TenantContext  │
                                                               │  .php resolves  │
                                                               │  HTTP_HOST →    │
                                                               │  tenant_id      │
                                                               └─────────────────┘

Request Flow:
1. Browser → hour-timebank.ie/listings
2. Cloudflare DNS → Server IP (proxied, with SSL termination)
3. Nginx → PHP (index.php handles ALL routes)
4. TenantContext::resolve() reads HTTP_HOST → finds tenant by domain
5. PHP renders full HTML page → returns to browser
```

### 1.2 Rollout State (React + PHP API)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              ROLLOUT STATE                               │
└─────────────────────────────────────────────────────────────────────────┘

  User Browser                    Cloudflare                     Plesk Server
 ┌───────────┐                  ┌───────────┐                  ┌─────────────────────────┐
 │           │   DNS Lookup     │           │                  │         Nginx           │
 │  Request  │ ───────────────► │   Proxy   │ ───────────────► │           │             │
 │           │                  │   + SSL   │   Host Header    │     ┌─────┴─────┐       │
 │ hour-     │                  │           │   Preserved      │     │  Router   │       │
 │ timebank  │                  │           │                  │     └─────┬─────┘       │
 │ .ie       │                  │           │                  │           │             │
 │           │                  │           │                  │   ┌───────┴───────┐     │
 └───────────┘                  └───────────┘                  │   │               │     │
      │                                                        │   ▼               ▼     │
      │                                                        │ /api/*        /* (else)│
      │                                                        │   │               │     │
      │                                                        │   ▼               ▼     │
      │                                                        │ ┌─────┐      ┌────────┐│
      │                                                        │ │ PHP │      │ React  ││
      │                                                        │ │ FPM │      │ Static ││
      │                                                        │ └──┬──┘      └────────┘│
      │                                                        │    │              │     │
      │                                                        │    ▼              │     │
      │                                                        │ TenantContext    │     │
      │                                                        │ (Host header     │     │
      │                                                        │  still works!)   │     │
      └────────────────────────────────────────────────────────┴───────────────────┴─────┘

Request Flow - Page Load:
1. Browser → hour-timebank.ie/listings
2. Cloudflare → Server (Host: hour-timebank.ie preserved)
3. Nginx sees: NOT /api/* → serve React static files
4. React app loads → calls /api/v2/tenant/bootstrap
5. TenantContext::resolve() reads HTTP_HOST → tenant resolved ✓
6. React renders UI with tenant branding

Request Flow - API Call:
1. React app → fetch("/api/v2/listings")
2. Same domain = no CORS, cookies work
3. Nginx sees: /api/* → proxy to PHP-FPM
4. PHP receives request with original Host header
5. TenantContext works normally → returns JSON
```

### 1.3 Critical Requirement: Host Header Preservation

**TenantContext.php MUST receive the original Host header.**

The proxy configuration MUST include:
```nginx
proxy_set_header Host $host;
```

Without this, domain-based tenant resolution breaks. The PHP backend would see `127.0.0.1` instead of `hour-timebank.ie`.

---

## 2. Cloudflare DNS Setup

### 2.1 DNS Record Patterns

#### Master Domain (project-nexus.ie)

| Type | Name | Content | Proxy | TTL |
|------|------|---------|-------|-----|
| A | @ | `<SERVER_IP>` | Proxied (orange) | Auto |
| A | www | `<SERVER_IP>` | Proxied (orange) | Auto |
| A | staging | `<SERVER_IP>` | Proxied (orange) | Auto |

#### Tenant Custom Domains (e.g., hour-timebank.ie)

Each tenant with a custom domain needs their own Cloudflare zone OR DNS records if using subdomains.

**Option A: Tenant has their own domain (recommended)**
```
In tenant's Cloudflare zone (hour-timebank.ie):
A     @       <SERVER_IP>     Proxied     Auto
A     www     <SERVER_IP>     Proxied     Auto
```

**Option B: Tenant uses subdomain of master**
```
In project-nexus.ie zone:
A     hour-timebank     <SERVER_IP>     Proxied     Auto
```
→ Results in: `hour-timebank.project-nexus.ie`

### 2.2 SSL Configuration

**In Cloudflare Dashboard → SSL/TLS:**

| Setting | Value | Why |
|---------|-------|-----|
| SSL Mode | **Full (Strict)** | Plesk has valid SSL cert |
| Always Use HTTPS | **ON** | Enforce HTTPS |
| Automatic HTTPS Rewrites | **ON** | Fix mixed content |
| Minimum TLS Version | **1.2** | Security |

**⚠️ AVOID REDIRECT LOOPS:**

If you see infinite redirects:
1. Check SSL mode is "Full (Strict)" not "Flexible"
2. Ensure Plesk has a valid SSL certificate
3. Check `.htaccess` doesn't force HTTPS (Cloudflare handles it)

The existing `.htaccess` already has this disabled:
```apache
# Let Cloudflare handle ALL redirects (HTTPS + WWW removal)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 2.3 Staging/Testing Setup

#### Local Development
```
# In C:\Windows\System32\drivers\etc\hosts (or /etc/hosts)
127.0.0.1    staging.timebank.local
127.0.0.1    hour-timebank.local
```

#### Staging Subdomain (on server)
```
In Cloudflare (project-nexus.ie zone):
A     staging     <SERVER_IP>     Proxied     Auto
```

Then in Plesk, add `staging.project-nexus.ie` as a subdomain.

---

## 3. Plesk Setup

### 3.1 Domain Structure in Plesk

For each tenant domain, you need:
1. **Main domain** OR **domain alias** in Plesk
2. **SSL certificate** (Let's Encrypt via Plesk)
3. **Document root** pointing to React build

#### Adding a Tenant Domain as Alias

1. **Plesk → Websites & Domains → project-nexus.ie**
2. Click **"Add Domain Alias"**
3. Enter: `hour-timebank.ie`
4. Check: ✅ "Synchronize DNS zone with the primary domain"
5. Check: ✅ "Mail service" (if needed)
6. Click **OK**

#### SSL for Domain Alias

1. **Plesk → Websites & Domains → hour-timebank.ie**
2. Click **"SSL/TLS Certificates"**
3. Click **"Install" under Let's Encrypt**
4. Check: ✅ Include www
5. Click **"Get it Free"**

### 3.2 Document Root Configuration

#### Option A: React as Static Files (Recommended)

**Directory Structure:**
```
/var/www/vhosts/project-nexus.ie/
├── httpdocs/                    # PHP backend (existing)
│   ├── index.php
│   ├── routes.php
│   └── assets/
├── react-app/                   # React build (NEW)
│   ├── index.html
│   ├── assets/
│   │   ├── index-abc123.js
│   │   └── index-def456.css
│   └── favicon.ico
└── conf/                        # Nginx custom config
    └── nginx.conf               # Custom proxy rules
```

**Deploying React Build:**
```bash
# On build server / CI
npm run build

# Copy to server
scp -r dist/* jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/react-app/
```

#### Option B: React via Node.js Runtime

If you need SSR (Server-Side Rendering):

1. **Plesk → Websites & Domains → project-nexus.ie**
2. Click **"Node.js"**
3. Configure:
   - Node.js version: 18.x or 20.x
   - Document root: `/react-app`
   - Application startup file: `server.js`
4. Click **"Enable Node.js"**

**Note:** For Hero UI (Vite-based SPA), static files are simpler and faster.

### 3.3 Plesk Nginx Configuration Location

Plesk allows custom Nginx directives in:

1. **Per-domain:** `Websites & Domains → [domain] → Apache & nginx Settings`
2. **Or file-based:** `/var/www/vhosts/project-nexus.ie/conf/nginx.conf`

The custom config file is auto-included by Plesk's Nginx.

---

## 4. Reverse Proxy Configuration

### 4.1 Complete Nginx Config for Plesk

Create/edit: `/var/www/vhosts/project-nexus.ie/conf/nginx.conf`

```nginx
# ============================================
# REACT FRONTEND + PHP API REVERSE PROXY
# Project NEXUS - Multi-tenant Configuration
# ============================================

# --------------------------------------------
# 1. API ROUTES → PHP BACKEND
# All /api/* requests go to existing PHP
# --------------------------------------------
location /api/ {
    # Proxy to PHP-FPM (Plesk default socket)
    # If using TCP, change to: proxy_pass http://127.0.0.1:9000;

    # For Plesk, we use the existing PHP handler
    # This block ensures API requests hit PHP, not React

    # Preserve original headers (CRITICAL for TenantContext)
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;

    # Pass to PHP via fastcgi (Plesk's default method)
    # The actual PHP handling is done by Plesk's generated config
    # We just need to NOT intercept /api/ with React

    # Let Plesk's default PHP handler take over
    try_files $uri $uri/ /index.php?$query_string;
}

# --------------------------------------------
# 2. LEGACY PHP ROUTES (during migration)
# Keep old PHP pages accessible at /legacy/*
# --------------------------------------------
location /legacy/ {
    alias /var/www/vhosts/project-nexus.ie/httpdocs/;

    # Preserve headers
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/www/vhosts/project-nexus.ie/run/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }
}

# --------------------------------------------
# 3. STATIC ASSETS (cache aggressively)
# --------------------------------------------
location /assets/ {
    # Try React assets first, then PHP assets
    root /var/www/vhosts/project-nexus.ie/react-app;
    try_files $uri @php_assets;

    # Cache static assets
    expires 1y;
    add_header Cache-Control "public, immutable";
}

location @php_assets {
    root /var/www/vhosts/project-nexus.ie/httpdocs;
    try_files $uri =404;
}

# --------------------------------------------
# 4. UPLOADS (served from PHP backend)
# --------------------------------------------
location /uploads/ {
    root /var/www/vhosts/project-nexus.ie/httpdocs;
    expires 30d;
    add_header Cache-Control "public";
}

# --------------------------------------------
# 5. REACT APP (SPA fallback)
# Everything else → React with index.html fallback
# --------------------------------------------
location / {
    root /var/www/vhosts/project-nexus.ie/react-app;

    # SPA routing: try file, then directory, then index.html
    try_files $uri $uri/ /index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Don't cache index.html (app shell)
    location = /index.html {
        expires -1;
        add_header Cache-Control "no-store, no-cache, must-revalidate";
    }
}
```

### 4.2 Alternative: Plesk UI Configuration

If you prefer the Plesk UI over file-based config:

1. **Plesk → Websites & Domains → project-nexus.ie**
2. Click **"Apache & nginx Settings"**
3. Scroll to **"Additional nginx directives"**
4. Paste the location blocks from above
5. Click **"OK"**

### 4.3 Simplified Config (Plesk with PHP already configured)

If Plesk is already handling PHP correctly, you only need to add the React layer:

```nginx
# Add to: Additional nginx directives (in Plesk UI)

# React app for non-API, non-asset routes
location / {
    root /var/www/vhosts/project-nexus.ie/react-app;
    try_files $uri $uri/ /index.html;
}

# Ensure API goes to PHP (Plesk handles this, but explicit is safer)
location /api/ {
    try_files $uri $uri/ /index.php?$query_string;
}

# Ensure uploads are served from PHP docroot
location /uploads/ {
    root /var/www/vhosts/project-nexus.ie/httpdocs;
}
```

### 4.4 Verify Configuration

```bash
# SSH into server
ssh jasper@35.205.239.67

# Test Nginx config syntax
sudo nginx -t

# If OK, reload
sudo systemctl reload nginx

# Check error log if issues
tail -50 /var/log/nginx/error.log
```

### 4.5 Trusted Proxy for Rate Limiting

The PHP backend's `RateLimiter.php` uses `$_SERVER['REMOTE_ADDR']`. With Cloudflare in front:

**In PHP (already handled by Cloudflare):**
Cloudflare sends `CF-Connecting-IP` header with real client IP.

**To use it in PHP, add to `httpdocs/index.php` early:**
```php
// Trust Cloudflare's real IP header
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}
```

**Or in Nginx:**
```nginx
# Set real IP from Cloudflare
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
# ... (full Cloudflare IP list)
real_ip_header CF-Connecting-IP;
```

---

## 5. Tenant Bootstrap Integration

### 5.1 React App Initialization

**In your React app's main entry (e.g., `src/main.tsx` or `App.tsx`):**

```typescript
// src/services/tenant.ts
export interface TenantConfig {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  default_layout: 'modern' | 'civicone';
  branding: {
    logo_url?: string;
    favicon_url?: string;
    primary_color?: string;
    og_image_url?: string;
  };
  features: {
    listings: boolean;
    events: boolean;
    groups: boolean;
    wallet: boolean;
    messages: boolean;
    feed: boolean;
    notifications: boolean;
    search: boolean;
    connections: boolean;
    reviews: boolean;
    gamification: boolean;
    volunteering: boolean;
    federation: boolean;
    blog: boolean;
    resources: boolean;
    goals: boolean;
    polls: boolean;
  };
  seo?: {
    meta_title?: string;
    meta_description?: string;
  };
  config?: {
    footer_text?: string;
    time_unit?: string;
    time_unit_plural?: string;
  };
  contact?: {
    email?: string;
    phone?: string;
    location?: string;
  };
  social?: {
    facebook?: string;
    twitter?: string;
    instagram?: string;
  };
}

export async function fetchTenantConfig(): Promise<TenantConfig> {
  // Same-domain: no X-Tenant-ID needed, domain resolution works
  const response = await fetch('/api/v2/tenant/bootstrap', {
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to load tenant config: ${response.status}`);
  }

  const json = await response.json();
  return json.data;
}
```

**In App initialization:**

```typescript
// src/App.tsx
import { useEffect, useState } from 'react';
import { fetchTenantConfig, TenantConfig } from './services/tenant';
import { TenantProvider } from './contexts/TenantContext';

function App() {
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchTenantConfig()
      .then(config => {
        setTenant(config);

        // Apply branding
        if (config.branding.primary_color) {
          document.documentElement.style.setProperty(
            '--color-primary',
            config.branding.primary_color
          );
        }

        // Update document title
        if (config.seo?.meta_title) {
          document.title = config.seo.meta_title;
        }

        // Update favicon
        if (config.branding.favicon_url) {
          const link = document.querySelector("link[rel~='icon']") as HTMLLinkElement;
          if (link) link.href = config.branding.favicon_url;
        }
      })
      .catch(err => {
        console.error('Tenant bootstrap failed:', err);
        setError('Failed to load application configuration');
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) {
    return <div className="loading-spinner">Loading...</div>;
  }

  if (error || !tenant) {
    return <div className="error-screen">{error || 'Configuration error'}</div>;
  }

  return (
    <TenantProvider value={tenant}>
      {/* Your app routes */}
    </TenantProvider>
  );
}
```

### 5.2 Local Development (CORS Mode)

When developing locally against a remote API:

```typescript
// src/services/tenant.ts
export async function fetchTenantConfig(): Promise<TenantConfig> {
  const isDev = import.meta.env.DEV;
  const apiBase = import.meta.env.VITE_API_BASE || '';

  const headers: HeadersInit = {
    'Accept': 'application/json',
  };

  // In development, specify tenant explicitly
  if (isDev && import.meta.env.VITE_TENANT_ID) {
    headers['X-Tenant-ID'] = import.meta.env.VITE_TENANT_ID;
  }

  const response = await fetch(`${apiBase}/api/v2/tenant/bootstrap`, {
    headers,
    // Include credentials for CORS with cookies (if needed)
    credentials: isDev ? 'include' : 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Failed to load tenant config: ${response.status}`);
  }

  const json = await response.json();
  return json.data;
}
```

**`.env.development`:**
```env
VITE_API_BASE=http://staging.timebank.local
VITE_TENANT_ID=2
```

**`.env.production`:**
```env
# Empty - use same-origin
VITE_API_BASE=
VITE_TENANT_ID=
```

### 5.3 Using Tenant Config in Components

```typescript
// src/contexts/TenantContext.tsx
import { createContext, useContext } from 'react';
import { TenantConfig } from '../services/tenant';

const TenantContext = createContext<TenantConfig | null>(null);

export function TenantProvider({
  value,
  children
}: {
  value: TenantConfig;
  children: React.ReactNode;
}) {
  return (
    <TenantContext.Provider value={value}>
      {children}
    </TenantContext.Provider>
  );
}

export function useTenant(): TenantConfig {
  const context = useContext(TenantContext);
  if (!context) {
    throw new Error('useTenant must be used within TenantProvider');
  }
  return context;
}

// Feature flag hook
export function useFeature(feature: keyof TenantConfig['features']): boolean {
  const tenant = useTenant();
  return tenant.features[feature] ?? false;
}
```

**Usage in components:**
```typescript
function Sidebar() {
  const tenant = useTenant();
  const hasEvents = useFeature('events');
  const hasGroups = useFeature('groups');

  return (
    <nav>
      <img src={tenant.branding.logo_url} alt={tenant.name} />
      <NavLink to="/listings">Listings</NavLink>
      {hasEvents && <NavLink to="/events">Events</NavLink>}
      {hasGroups && <NavLink to="/groups">Groups</NavLink>}
    </nav>
  );
}
```

---

## 6. Rollout Strategy

### 6.1 Phase 0: Preparation (Before Any Changes)

**Checklist:**

- [ ] React app builds successfully (`npm run build`)
- [ ] React app tested locally against staging API
- [ ] `/api/v2/tenant/bootstrap` endpoint deployed and tested
- [ ] Backup current Nginx config
- [ ] Backup current PHP httpdocs
- [ ] Document current working state (screenshot key pages)
- [ ] Notify team of planned maintenance window

**Commands:**
```bash
# Backup existing config
ssh jasper@35.205.239.67
cp -r /var/www/vhosts/project-nexus.ie/conf /var/www/vhosts/project-nexus.ie/conf.backup.$(date +%Y%m%d)
cp -r /var/www/vhosts/project-nexus.ie/httpdocs /var/www/vhosts/project-nexus.ie/httpdocs.backup.$(date +%Y%m%d)
```

### 6.2 Phase 1: Staging Environment

**Duration:** 1-2 days

**Steps:**

1. **Create staging subdomain**
   ```
   staging-react.project-nexus.ie
   ```

2. **Deploy React to staging directory**
   ```bash
   # Local
   npm run build
   scp -r dist/* jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/staging-react/
   ```

3. **Configure Nginx for staging only**
   ```nginx
   # In Plesk: staging-react.project-nexus.ie → Apache & nginx Settings

   location / {
       root /var/www/vhosts/project-nexus.ie/staging-react;
       try_files $uri $uri/ /index.html;
   }

   location /api/ {
       proxy_pass http://127.0.0.1:80;
       proxy_set_header Host project-nexus.ie;  # Use master domain for API
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```

4. **Test thoroughly**
   - [ ] Page loads
   - [ ] Tenant bootstrap returns correct data
   - [ ] Login works
   - [ ] Listings load
   - [ ] Navigation works (no 404s on refresh)

### 6.3 Phase 2: Pilot Tenant

**Duration:** 3-7 days

**Pick ONE tenant domain as pilot** (e.g., `hour-timebank.ie`)

**Steps:**

1. **Deploy React build**
   ```bash
   scp -r dist/* jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/react-app/
   ```

2. **Update Nginx for pilot domain ONLY**

   In Plesk, for `hour-timebank.ie` domain alias:
   ```nginx
   # Apache & nginx Settings → Additional nginx directives

   # React app
   location / {
       root /var/www/vhosts/project-nexus.ie/react-app;
       try_files $uri $uri/ /index.html;
   }

   # API passthrough
   location /api/ {
       try_files $uri $uri/ /index.php?$query_string;
   }

   # Legacy PHP pages (escape hatch)
   location /legacy/ {
       alias /var/www/vhosts/project-nexus.ie/httpdocs/;
       try_files $uri $uri/ /index.php?$query_string;
   }

   # Static assets from PHP
   location /uploads/ {
       root /var/www/vhosts/project-nexus.ie/httpdocs;
   }
   ```

3. **Keep legacy escape hatch**

   Users can access old PHP at: `https://hour-timebank.ie/legacy/listings`

4. **Monitor for 3-7 days**
   - [ ] Check error logs daily
   - [ ] Monitor API response times
   - [ ] Collect user feedback
   - [ ] Track any 404 errors

### 6.4 Phase 3: Gradual Rollout

**Duration:** 1-2 weeks per batch

**Batch 1:** 2-3 more tenant domains
**Batch 2:** 5-10 tenant domains
**Batch 3:** All remaining tenants
**Batch 4:** Master domain (project-nexus.ie)

**For each batch:**
1. Copy Nginx config from pilot
2. Test each domain manually
3. Monitor for 48 hours before next batch

### 6.5 Rollback Procedure

**If something goes wrong:**

```bash
# SSH into server
ssh jasper@35.205.239.67

# Option 1: Quick rollback (remove React routing)
# In Plesk: Remove custom nginx directives for affected domain

# Option 2: Full rollback (restore backup)
rm -rf /var/www/vhosts/project-nexus.ie/conf
cp -r /var/www/vhosts/project-nexus.ie/conf.backup.YYYYMMDD /var/www/vhosts/project-nexus.ie/conf

# Reload nginx
sudo systemctl reload nginx

# Verify PHP is serving again
curl -I https://hour-timebank.ie/listings
```

**Rollback Nginx config (per domain):**

Simply remove or comment out the custom location blocks in Plesk's "Additional nginx directives" section.

---

## 7. Gotchas Checklist

### 7.1 CORS (Local Development Only)

**Symptom:** `Access-Control-Allow-Origin` errors in browser console

**When it happens:** Only during local development (`localhost:3000` → `staging.timebank.local`)

**Fix:**

1. Add localhost to `ALLOWED_ORIGINS` in PHP `.env`:
   ```env
   ALLOWED_ORIGINS=https://project-nexus.ie,http://localhost:3000,http://localhost:5173
   ```

2. Or use same-domain proxy in Vite:
   ```typescript
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
   });
   ```

**In production (same-domain):** CORS is not an issue.

### 7.2 Cookies vs Bearer Tokens

| Scenario | Use | Why |
|----------|-----|-----|
| Same-domain (production) | Either works | Session cookies work, Bearer tokens work |
| Local dev with proxy | Cookies | Proxy makes it same-origin |
| Local dev with CORS | Bearer tokens | Cookies need `SameSite=None; Secure` |
| Mobile app (Capacitor) | Bearer tokens | Different origin |

**Recommendation:** Use Bearer tokens for consistency across all environments.

**React auth flow:**
```typescript
// After login, store token
localStorage.setItem('access_token', response.access_token);

// On API calls
fetch('/api/v2/listings', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
  },
});
```

### 7.3 Cache Considerations

#### Cloudflare Cache

**Problem:** Cloudflare might cache API responses

**Fix:** API responses have `Cache-Control: no-store` (already set by PHP)

**Verify:** Check response headers include:
```
Cache-Control: no-store, no-cache, must-revalidate
```

#### React App Cache

**Problem:** Users see old React app after deploy

**Fix:**
1. Vite generates hashed filenames (`index-abc123.js`)
2. `index.html` must not be cached:
   ```nginx
   location = /index.html {
       expires -1;
       add_header Cache-Control "no-store, no-cache, must-revalidate";
   }
   ```

#### Redis Cache (Tenant Bootstrap)

**Problem:** Tenant config changes not reflected immediately

**Info:** `TenantBootstrapController` caches for 10 minutes

**Fix (if urgent):**
```bash
# Clear Redis cache for tenant
redis-cli DEL "nexus:t2:tenant_bootstrap"
```

### 7.4 SPA Routing 404s

**Problem:** Refreshing `/listings/123` shows 404

**Cause:** Nginx tries to find `/listings/123` as a file

**Fix:** `try_files $uri $uri/ /index.html;` (included in config above)

**Verify:**
```bash
curl -I https://hour-timebank.ie/listings/123
# Should return 200 with React's index.html
```

### 7.5 Mixed Content / HTTPS

**Problem:** Browser blocks HTTP resources on HTTPS page

**Common causes:**
1. Image URLs from API don't have HTTPS
2. Hardcoded `http://` in React code
3. External resources (fonts, scripts) using HTTP

**Fixes:**

1. **API URLs:** `UrlHelper::absolute()` already uses HTTPS
2. **React code:** Use relative URLs (`/uploads/...` not `http://...`)
3. **Cloudflare:** Enable "Automatic HTTPS Rewrites"

**Verify in browser:** Open DevTools → Console, look for mixed content warnings.

### 7.6 Service Worker Conflicts

**Problem:** Old PHP service worker interferes with React

**If using PHP service worker (`sw.js`):**
```javascript
// In React app, unregister old SW on load
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(registrations => {
    registrations.forEach(registration => {
      // Only unregister if it's the old PHP one
      if (registration.active?.scriptURL.includes('sw.js')) {
        registration.unregister();
      }
    });
  });
}
```

### 7.7 PHP Session vs React State

**Problem:** User logged in via PHP, but React doesn't know

**Scenario:** User visits `/legacy/login` (PHP), then navigates to `/` (React)

**Options:**

1. **Redirect legacy login to React login**
   ```nginx
   location = /legacy/login {
       return 301 /login;
   }
   ```

2. **Check session on React load**
   ```typescript
   // Call /api/auth/check-session to see if PHP session exists
   const response = await fetch('/api/auth/check-session');
   if (response.ok) {
     const { user } = await response.json();
     // User is logged in via session, get tokens
   }
   ```

3. **Use Bearer tokens exclusively** (recommended)
   - React handles all auth
   - PHP `/api/auth/login` returns tokens
   - No session dependency

---

## Quick Reference: File Paths

| Item | Path |
|------|------|
| PHP Backend | `/var/www/vhosts/project-nexus.ie/httpdocs/` |
| React Build | `/var/www/vhosts/project-nexus.ie/react-app/` |
| Nginx Custom Config | `/var/www/vhosts/project-nexus.ie/conf/nginx.conf` |
| PHP Error Log | `/var/www/vhosts/project-nexus.ie/logs/error.log` |
| Nginx Error Log | `/var/log/nginx/error.log` |
| Config Backup | `/var/www/vhosts/project-nexus.ie/conf.backup.YYYYMMDD/` |

---

## Quick Reference: Commands

```bash
# Deploy React build
npm run build && scp -r dist/* jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/react-app/

# Test Nginx config
ssh jasper@35.205.239.67 "sudo nginx -t"

# Reload Nginx
ssh jasper@35.205.239.67 "sudo systemctl reload nginx"

# Check PHP logs
ssh jasper@35.205.239.67 "tail -50 /var/www/vhosts/project-nexus.ie/logs/error.log"

# Clear tenant bootstrap cache
ssh jasper@35.205.239.67 "redis-cli DEL 'nexus:t2:tenant_bootstrap'"

# Rollback (restore backup config)
ssh jasper@35.205.239.67 "cp -r /var/www/vhosts/project-nexus.ie/conf.backup.YYYYMMDD/* /var/www/vhosts/project-nexus.ie/conf/ && sudo systemctl reload nginx"
```

---

*Guide created 2026-02-03*
