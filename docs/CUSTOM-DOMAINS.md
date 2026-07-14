# Custom Domains

Last reviewed: 2026-07-14

Project NEXUS supports separate tenant domains for the primary React frontend and the accessibility-first frontend.

| Frontend | Tenant column | Serves | Upstream family |
| --- | --- | --- | --- |
| React SPA | `tenants.domain` | Primary tenant UI | React blue/green frontend |
| Accessible frontend | `tenants.accessible_domain` | HTML-first accessible UI | PHP/Laravel blue/green app |

Both hostnames resolve a tenant from the HTTP `Host` header. The production web server must preserve the original host when proxying to the app containers, otherwise tenant detection and generated links can be wrong.

## Prerequisites

- DNS control for the requested hostname.
- TLS coverage through the site's certificate provider.
- Access to the production web server configuration.
- Super-admin access to set the tenant domain columns.

Do not publish or hardcode the production origin IP in this repository. Keep environment-specific targets in private infrastructure notes or secret-backed deployment configuration.

## Setup

1. Point DNS at the configured production ingress for the platform.
2. Ensure TLS covers the hostname at the edge and at the origin where required.
3. Attach the hostname to the correct production vhost or proxy entry.
4. In super-admin, set `Domain` for the React frontend and/or `Accessible frontend domain` for the accessible frontend.
5. Verify the hostname loads the expected tenant and branding.

## Apache Proxy Shape

Production uses Apache. Plesk-managed vhost files should not be hand-edited directly; place custom proxy directives in the supported per-vhost include file for that domain.

React frontend custom domains proxy to the active React upstream:

```apache
Include /etc/apache2/conf-enabled/nexus-active-upstreams.conf
ProxyPreserveHost On
RequestHeader set X-Forwarded-Proto "https"
ProxyPass /.well-known/acme-challenge/ !
ProxyPass / http://127.0.0.1:${NEXUS_FRONTEND_PORT}/ retry=0
ProxyPassReverse / http://127.0.0.1:${NEXUS_FRONTEND_PORT}/
```

Accessible frontend custom domains proxy to the active PHP/Laravel upstream:

```apache
Include /etc/apache2/conf-enabled/nexus-active-upstreams.conf
ProxyPreserveHost On
RequestHeader set X-Forwarded-Proto "https"
RewriteEngine On
RewriteRule ^/\.well-known/acme-challenge/ - [L]
RewriteRule ^(.*)$ http://127.0.0.1:${NEXUS_API_PORT}$1 [P,L]
```

## Verification

- `https://<react-domain>/` loads the React SPA for the correct tenant.
- `https://<accessible-domain>/` loads the accessible frontend for the correct tenant.
- The React utility link to the accessible version points to the configured accessible domain when one exists.
- Tenant bootstrap, CORS, cookies, and generated notification links still use the expected public hostnames.

## Notes

- The React and accessible domains must be distinct.
- Domain validation enforces global uniqueness across both tenant domain columns.
- Tenant bootstrap responses are cached. Clear the relevant cache or wait for expiry after changing a domain.
- On a shared platform host, tenant-scoped pages use `/{tenantSlug}/accessible/...`.
- On a dedicated accessible domain, the same route set is served without the tenant slug or `/accessible` prefix.
- Legacy `/alpha/...` bookmarks redirect permanently to the corresponding public route. Do not publish new `/alpha` links.
- `GovukAlpha`, `govuk_alpha`, and `govuk-alpha.*` remain internal code-path names until a deliberate namespace migration; they do not define the public URL.
