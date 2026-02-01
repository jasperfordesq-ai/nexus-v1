# Architecture Overview

**Document ID:** NEXUS-ARCHITECTURE
**Version:** 1.0
**Date:** February 2026
**Classification:** OFFICIAL

---

## Purpose

This document provides a high-level overview of Project NEXUS architecture for technical reviewers and infrastructure teams deploying the system.

---

## System Overview

Project NEXUS is a multi-tenant PHP application for community timebanking and engagement. It follows a traditional server-rendered architecture with optional real-time features.

### Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| Web Server | Nginx / Apache | HTTP handling, TLS termination |
| Application | PHP 8.1+ | Business logic, routing |
| Database | MySQL 8.0 | Persistent data storage |
| Cache | Redis 7+ | Session storage, caching |
| Email | SMTP / Gmail API | Transactional email |
| Push (Optional) | Pusher / WebSockets | Real-time notifications |

---

## Architecture Diagram

```
                                    ┌─────────────────────────────────────────┐
                                    │              INTERNET                   │
                                    └─────────────────┬───────────────────────┘
                                                      │
                                                      ▼
                                    ┌─────────────────────────────────────────┐
                                    │         WAF / CDN (Optional)            │
                                    │    (Cloudflare, AWS CloudFront, etc.)   │
                                    └─────────────────┬───────────────────────┘
                                                      │
                                                      ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                                    DEMILITARISED ZONE (DMZ)                           │
│  ┌─────────────────────────────────────────────────────────────────────────────────┐  │
│  │                           LOAD BALANCER / REVERSE PROXY                         │  │
│  │                              (Nginx / HAProxy)                                  │  │
│  │                         TLS Termination, Security Headers                       │  │
│  └─────────────────────────────────┬───────────────────────────────────────────────┘  │
└────────────────────────────────────┼──────────────────────────────────────────────────┘
                                     │
                                     ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                                  APPLICATION TIER                                     │
│                                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────────┐  │
│  │                              WEB SERVER (Nginx)                                 │  │
│  │                          PHP-FPM 8.1+ Application                               │  │
│  │  ┌───────────────────────────────────────────────────────────────────────────┐  │  │
│  │  │                         PROJECT NEXUS APPLICATION                         │  │  │
│  │  │                                                                           │  │  │
│  │  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │  │  │
│  │  │  │   Router    │  │ Controllers │  │  Services   │  │   Models    │      │  │  │
│  │  │  │ (routes.php)│  │  (src/)     │  │  (src/)     │  │  (src/)     │      │  │  │
│  │  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘      │  │  │
│  │  │         │                │                │                │             │  │  │
│  │  │         └────────────────┴────────────────┴────────────────┘             │  │  │
│  │  │                                   │                                       │  │  │
│  │  │  ┌────────────────────────────────┴────────────────────────────────────┐  │  │  │
│  │  │  │                        CORE FRAMEWORK                               │  │  │  │
│  │  │  │  Database | Auth | TenantContext | CSRF | RateLimiter | Mailer     │  │  │  │
│  │  │  └─────────────────────────────────────────────────────────────────────┘  │  │  │
│  │  └───────────────────────────────────────────────────────────────────────────┘  │  │
│  └─────────────────────────────────────────────────────────────────────────────────┘  │
│                                                                                       │
└───────────────────────────────────┬───────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
                    ▼               ▼               ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                                    DATA TIER                                          │
│                                                                                       │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐           │
│  │     MySQL 8.0       │  │      Redis 7+       │  │   File Storage      │           │
│  │                     │  │                     │  │                     │           │
│  │  - User data        │  │  - Sessions         │  │  - User uploads     │           │
│  │  - Transactions     │  │  - Cache            │  │  - Profile images   │           │
│  │  - Audit logs       │  │  - Rate limiting    │  │  - Documents        │           │
│  │  - Tenant config    │  │  - Job queues       │  │  - Backups          │           │
│  │                     │  │                     │  │                     │           │
│  └─────────────────────┘  └─────────────────────┘  └─────────────────────┘           │
│                                                                                       │
└───────────────────────────────────────────────────────────────────────────────────────┘

                                    │
                                    ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              EXTERNAL SERVICES (Optional)                             │
│                                                                                       │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐           │
│  │    Email Service    │  │   Push Service      │  │   AI Service        │           │
│  │  (SMTP/Gmail API)   │  │  (Pusher/WebSocket) │  │  (OpenAI API)       │           │
│  └─────────────────────┘  └─────────────────────┘  └─────────────────────┘           │
│                                                                                       │
└───────────────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow

### User Request Flow

```
User Browser
     │
     │ HTTPS Request
     ▼
┌─────────────┐
│    WAF      │ ─── Block malicious requests
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   Nginx     │ ─── TLS termination, security headers, static files
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  PHP-FPM    │ ─── Application processing
│             │
│  1. Router  │ ─── Match URL to controller
│  2. Middleware ── Auth, CSRF, tenant context
│  3. Controller ── Handle request logic
│  4. Service │ ─── Business logic
│  5. Model   │ ─── Database queries (tenant-scoped)
│  6. View    │ ─── Render HTML (theme-aware)
│             │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Response   │ ─── HTML/JSON back to browser
└─────────────┘
```

### Authentication Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Browser   │────▶│  /login     │────▶│ Rate Limit  │────▶│  Validate   │
│             │     │  POST       │     │  Check      │     │  Credentials│
└─────────────┘     └─────────────┘     └──────┬──────┘     └──────┬──────┘
                                               │                   │
                                               │ Blocked?          │ Valid?
                                               ▼                   ▼
                                        ┌─────────────┐     ┌─────────────┐
                                        │  429 Error  │     │  Create     │
                                        │  "Too many  │     │  Session    │
                                        │  attempts"  │     │  (Redis)    │
                                        └─────────────┘     └──────┬──────┘
                                                                   │
                                                                   ▼
                                                            ┌─────────────┐
                                                            │  Set Cookie │
                                                            │  Redirect   │
                                                            │  Dashboard  │
                                                            └─────────────┘
```

### Multi-Tenant Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                         SINGLE DATABASE                             │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                        tenants table                         │   │
│  │  id │ name          │ slug         │ settings (JSON)         │   │
│  │  1  │ Council A     │ council-a    │ {...}                   │   │
│  │  2  │ Council B     │ council-b    │ {...}                   │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                              │                                       │
│                              │ tenant_id foreign key                 │
│                              ▼                                       │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                        users table                           │   │
│  │  id │ tenant_id │ email              │ name                  │   │
│  │  1  │ 1         │ user@council-a.gov │ Alice                 │   │
│  │  2  │ 2         │ user@council-b.gov │ Bob                   │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  Every query includes: WHERE tenant_id = ?                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Component Details

### Core Framework (`src/Core/`)

| Component | File | Purpose |
|-----------|------|---------|
| Database | `Database.php` | PDO wrapper, prepared statements, transactions |
| Auth | `Auth.php` | User authentication, session management |
| AdminAuth | `AdminAuth.php` | Admin authentication, RBAC hierarchy |
| TenantContext | `TenantContext.php` | Multi-tenant context, settings, features |
| Router | `Router.php` | URL routing to controllers |
| CSRF | `Csrf.php` | Cross-site request forgery protection |
| RateLimiter | `RateLimiter.php` | Login attempt rate limiting |
| Mailer | `Mailer.php` | Email sending (SMTP/Gmail) |

### Services (`src/Services/`)

Business logic is encapsulated in service classes:

- `WalletService` — Time credit transactions
- `GamificationService` — Badges, XP, achievements
- `MatchingService` — User/listing matching
- `AuditLogService` — Action audit trail
- `GdprService` — GDPR compliance (DSR, consent)
- `DigestService` — Email digest generation

### Controllers (`src/Controllers/`)

Request handling organised by domain:

- `Api/` — JSON API endpoints
- `Admin/` — Admin panel controllers
- `Admin/Enterprise/` — Enterprise admin features

### Views (`views/`)

Theme-aware templates:

- `modern/` — Modern responsive theme
- `civicone/` — GOV.UK Design System theme (accessibility-focused)
- `layouts/` — Shared layout templates
- `partials/` — Reusable components

---

## Security Architecture

### Authentication Hierarchy

```
┌─────────────────────────────────────────────────────────────────────┐
│                        PRIVILEGE LEVELS                             │
│                                                                     │
│  ┌─────────────┐                                                    │
│  │    God      │  Bypasses ALL permission checks                    │
│  │  (is_god=1) │  Can manage everything                             │
│  └──────┬──────┘                                                    │
│         │                                                           │
│         ▼                                                           │
│  ┌─────────────┐                                                    │
│  │ Super Admin │  Cross-tenant access                               │
│  │ (is_super)  │  Cannot manage God users                           │
│  └──────┬──────┘                                                    │
│         │                                                           │
│         ▼                                                           │
│  ┌─────────────┐                                                    │
│  │   Admin     │  Full tenant access                                │
│  │(role=admin) │  Cannot manage Super/God                           │
│  └──────┬──────┘                                                    │
│         │                                                           │
│         ▼                                                           │
│  ┌─────────────┐                                                    │
│  │Tenant Admin │  Tenant management                                 │
│  │             │  Limited admin functions                           │
│  └──────┬──────┘                                                    │
│         │                                                           │
│         ▼                                                           │
│  ┌─────────────┐                                                    │
│  │   Member    │  Standard user access                              │
│  │(role=member)│  Own data only                                     │
│  └─────────────┘                                                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Data Protection Layers

```
┌─────────────────────────────────────────────────────────────────────┐
│                      SECURITY CONTROLS                              │
│                                                                     │
│  Layer 1: Network                                                   │
│  ├── TLS 1.2+ encryption                                            │
│  ├── WAF / Rate limiting                                            │
│  └── Firewall (ports 80, 443 only)                                  │
│                                                                     │
│  Layer 2: Application                                               │
│  ├── CSRF tokens on forms                                           │
│  ├── Session validation                                             │
│  ├── Input validation/sanitisation                                  │
│  └── Output encoding (XSS prevention)                               │
│                                                                     │
│  Layer 3: Data                                                      │
│  ├── Tenant isolation (tenant_id scoping)                           │
│  ├── Prepared statements (SQL injection prevention)                 │
│  ├── Password hashing (bcrypt)                                      │
│  └── Encrypted backups                                              │
│                                                                     │
│  Layer 4: Audit                                                     │
│  ├── Authentication logging                                         │
│  ├── Admin action logging                                           │
│  ├── GDPR consent logging                                           │
│  └── Transaction audit trail                                        │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Deployment Options

### Single Server (Pilot)

Suitable for pilots up to ~500 users:

```
┌─────────────────────────────────────────────┐
│              Single Server                  │
│                                             │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐     │
│  │  Nginx  │  │   PHP   │  │  MySQL  │     │
│  │         │  │   FPM   │  │         │     │
│  └─────────┘  └─────────┘  └─────────┘     │
│                    │                        │
│               ┌────┴────┐                   │
│               │  Redis  │                   │
│               └─────────┘                   │
│                                             │
│  Minimum: 2 CPU, 4GB RAM, 50GB SSD          │
│                                             │
└─────────────────────────────────────────────┘
```

### High Availability (Production)

For larger deployments with redundancy:

```
┌─────────────────────────────────────────────────────────────────────┐
│                                                                     │
│  ┌─────────────┐                                                    │
│  │ Load Balancer│                                                   │
│  └──────┬──────┘                                                    │
│         │                                                           │
│    ┌────┴────┐                                                      │
│    │         │                                                      │
│    ▼         ▼                                                      │
│  ┌─────┐  ┌─────┐     ┌─────────────┐     ┌─────────────┐          │
│  │App 1│  │App 2│────▶│MySQL Primary│────▶│MySQL Replica│          │
│  └──┬──┘  └──┬──┘     └─────────────┘     └─────────────┘          │
│     │        │                                                      │
│     └────┬───┘        ┌─────────────┐                               │
│          │            │Redis Cluster│                               │
│          └───────────▶│(3 nodes)    │                               │
│                       └─────────────┘                               │
│                                                                     │
│  Shared: File storage (NFS/S3), Backups, Monitoring                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Integration Points

### Inbound

| Integration | Protocol | Purpose |
|-------------|----------|---------|
| Web browsers | HTTPS | User interface |
| Mobile app (PWA) | HTTPS | Mobile access |
| API clients | HTTPS + Bearer token | Programmatic access |

### Outbound

| Integration | Protocol | Purpose |
|-------------|----------|---------|
| SMTP server | SMTP/TLS | Email delivery |
| Gmail API | HTTPS | Email delivery (alternative) |
| Pusher | WebSocket | Real-time notifications (optional) |
| OpenAI | HTTPS | AI chat features (optional) |

---

## Performance Characteristics

### Typical Resource Usage (Pilot Scale)

| Metric | Idle | Light Load | Peak |
|--------|------|------------|------|
| CPU | 5% | 20% | 60% |
| Memory | 500MB | 1GB | 2GB |
| Database connections | 5 | 20 | 50 |
| Requests/second | - | 10 | 50 |

### Scaling Considerations

- **Horizontal:** Add application servers behind load balancer
- **Database:** Read replicas for read-heavy workloads
- **Cache:** Redis cluster for session sharing
- **Storage:** Move to S3/Azure Blob for file uploads

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
