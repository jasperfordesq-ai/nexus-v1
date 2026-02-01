# Security Questionnaire — Pre-completed Responses

**Document ID:** NEXUS-SEC-001
**Version:** 1.0
**Date:** February 2026
**Classification:** OFFICIAL

---

## Purpose

This document provides pre-completed responses to common security questionnaire questions asked by councils and NHS organisations during procurement. Responses are based on code review and technical assessment of Project NEXUS.

**Note:** Some responses require organisation-specific completion where marked [TO CONFIRM].

---

## 1. Hosting & Infrastructure

### 1.1 Where is the application hosted?

**Response:** Project NEXUS is self-hosted software. The deploying organisation chooses their hosting environment.

**Recommended configurations:**
- UK-based cloud provider (AWS London, Azure UK South, GCP London)
- UK-based managed hosting provider
- On-premises council data centre

**Note:** Hosting location is the deployer's responsibility. For UK public sector, UK-only hosting is recommended to avoid international transfer considerations.

### 1.2 Is data stored in the UK?

**Response:** Yes, when deployed to UK infrastructure. The application does not require any data to leave the UK.

**Components:**
- Database: Deployed locally
- File storage: Deployed locally
- Session cache: Deployed locally (Redis)
- Backups: Location chosen by deployer

**Third-party services (optional):**
- Email delivery: Configurable (can use UK-only SMTP)
- Push notifications (Pusher): US-based — disable if UK-only required
- AI features (OpenAI): US-based — disable if UK-only required

### 1.3 What is the disaster recovery capability?

**Response:** The application includes backup and restore tooling. Disaster recovery procedures are the deployer's responsibility.

**Available tooling:**
- `scripts/backup_database.php` — Full database backup
- `scripts/restore_database.php` — Database restoration
- Backup scripts support scheduled execution via cron

**Recommended DR configuration:**
- Daily automated backups
- Offsite backup replication
- Documented restoration procedure
- Regular restore testing (monthly)

**Recovery objectives (typical):**
- RPO: 24 hours (with daily backups)
- RTO: 4-8 hours (depending on infrastructure)

---

## 2. Authentication & Access Control

### 2.1 How are user credentials protected?

**Response:**
- Passwords hashed using bcrypt (cost factor 10+)
- Passwords never stored in plaintext
- Secure session management with random session IDs
- Session cookies: HttpOnly, Secure, SameSite=Lax

**Code reference:** `src/Core/Auth.php`

### 2.2 Is multi-factor authentication (MFA) supported?

**Response:** Yes. Two-factor authentication (2FA) is implemented using Time-based One-Time Passwords (TOTP).

**Features:**

- TOTP-based 2FA compatible with standard authenticator apps (Google Authenticator, Microsoft Authenticator, Authy, etc.)
- User self-service enrollment via account settings
- QR code provisioning for easy setup
- Backup/recovery codes for account recovery
- Admin ability to require 2FA for specific roles or all users
- 2FA status visible in admin user management

**Recommended configuration for pilots:**

- Require 2FA for all admin and tenant admin accounts
- Encourage 2FA for all users handling sensitive data
- Provide user guidance on authenticator app setup

### 2.3 What access control model is used?

**Response:** Role-Based Access Control (RBAC) with hierarchical privileges.

**Privilege hierarchy (highest to lowest):**
1. **God** (is_god=1) — Bypasses all permission checks
2. **Super Admin** (is_super_admin=1) — Cross-tenant access
3. **Admin** (role=admin) — Full tenant access
4. **Tenant Admin** (role=tenant_admin) — Tenant management
5. **Newsletter Admin** (role=newsletter_admin) — Newsletter only
6. **Member** (role=member) — Standard user

**Code reference:** `src/Core/AdminAuth.php`

### 2.4 How is session management handled?

**Response:**
- Sessions stored in Redis (configurable)
- Session ID regenerated on privilege change
- Configurable session timeout (default: 2 hours)
- Sessions invalidated on logout
- Concurrent session handling supported

---

## 3. Data Protection

### 3.1 How is data encrypted in transit?

**Response:** TLS 1.2+ required for all connections.

**Implementation:**
- Web traffic: HTTPS only (HTTP redirects to HTTPS)
- Database: Local socket or TLS connection
- Redis: TLS supported when network-accessible
- Email: TLS/STARTTLS for SMTP

**Deployer responsibility:** Configure TLS certificates and enforce HTTPS.

### 3.2 How is data encrypted at rest?

**Response:** Database and filesystem encryption are deployer-configured.

**Options:**
- MySQL Transparent Data Encryption (TDE)
- Filesystem-level encryption (LUKS, BitLocker)
- Cloud provider encryption (AWS EBS, Azure Disk)

**Application-level encryption:**
- Passwords: bcrypt hashed
- GDPR consent records: SHA-256 hash verification
- Backups: Can be encrypted with GPG

### 3.3 What GDPR tooling is available?

**Response:** Comprehensive GDPR compliance features built-in.

**Data Subject Request (DSR) support:**
- Access requests: Automated data export (JSON, HTML, ZIP)
- Erasure requests: Account deletion with anonymisation
- Rectification: Self-service profile editing
- Portability: Structured data export

**Consent management:**
- Versioned consent records
- SHA-256 hash of consent text for integrity
- Consent withdrawal tracking
- IP and timestamp logging

**Code reference:** `src/Services/Enterprise/GdprService.php`

### 3.4 What is the data retention policy?

**Response:** Configurable retention with automated cleanup.

**Default retention periods:**
- Active accounts: Indefinite while active
- Inactive accounts: Configurable (recommended: 2 years)
- Deleted accounts: 30-day grace period, then permanent deletion
- Audit logs: 7 years (configurable)
- Technical logs: 90 days

**Code reference:** `src/Services/AuditLogService.php:cleanup()`

---

## 4. Security Monitoring & Logging

### 4.1 What security events are logged?

**Response:** Comprehensive audit logging across multiple domains.

**Logged events:**
- Authentication: Login attempts (success/failure), logout
- Admin actions: User management, settings changes
- Data access: DSR processing, data exports
- Transactions: Time credit transfers, approvals
- Security: CSRF failures, rate limit triggers

**Log contents:**
- Timestamp
- User ID (where applicable)
- IP address
- User agent
- Action type
- Affected entity
- Before/after values (for changes)

**Code references:**
- `src/Services/AuditLogService.php`
- `src/Services/SuperAdminAuditService.php`

### 4.2 How long are logs retained?

**Response:**
- Security/audit logs: 7 years (default)
- Application logs: 90 days
- Access logs (web server): Deployer-configured

### 4.3 Can logs be exported for SIEM integration?

**Response:** Yes.

**Export options:**
- Database query access for audit tables
- CSV export via admin interface
- API access for programmatic retrieval
- Cron-based export scripts available

**Recommended:** Implement hash-chained JSONL export for tamper-evidence (see Audit Hardening Plan).

---

## 5. Vulnerability Management

### 5.1 How are security vulnerabilities handled?

**Response:**
- Responsible disclosure process via GitHub
- Security patches released as updates
- Deployers responsible for applying updates

**Recommended process:**
- Subscribe to release notifications
- Apply security patches within 30 days
- Test updates in staging before production

### 5.2 Has the application undergone penetration testing?

**Response:** An independent penetration test must be completed before go-live with real users.

**Recommendations:**
- CHECK/CREST certified tester
- Web application focus
- Annual re-testing
- Deployer arranges and funds testing

### 5.3 What security headers are implemented?

**Response:** Security headers configured via web server.

**Recommended headers:**
- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; ...`
- `Referrer-Policy: strict-origin-when-cross-origin`

**Verification:** Use provided `scripts/pilot/verify-headers.sh`

---

## 6. Input Validation & Output Encoding

### 6.1 How is SQL injection prevented?

**Response:** Prepared statements used throughout.

**Implementation:**
- PDO with prepared statements
- Parameter binding for all user input
- No dynamic SQL construction

**Code reference:** `src/Core/Database.php`

### 6.2 How is XSS prevented?

**Response:**
- Output encoding via `htmlspecialchars()`
- Content Security Policy headers
- Template-level escaping

### 6.3 How is CSRF prevented?

**Response:** Token-based CSRF protection.

**Implementation:**
- Cryptographically random tokens (`random_bytes(32)`)
- Token embedded in forms
- Token validated on POST requests
- Timing-safe comparison (`hash_equals()`)

**Code reference:** `src/Core/Csrf.php`

---

## 7. Third-Party Dependencies

### 7.1 What third-party components are used?

**Response:** Minimal external dependencies.

**PHP dependencies (Composer):**
- PSR-4 autoloading
- PHPMailer (email)
- Standard PHP extensions

**JavaScript:**
- No major frameworks required
- Optional: Chart.js for analytics

**External services (optional):**
- Pusher (real-time notifications)
- OpenAI API (AI features)
- Gmail API (email delivery)

### 7.2 How are dependencies kept up to date?

**Response:** Deployer responsibility.

**Recommended process:**
- Run `composer outdated` monthly
- Review security advisories
- Update dependencies quarterly
- Test thoroughly before production deployment

---

## 8. Business Continuity

### 8.1 What is the expected availability?

**Response:** Dependent on deployment infrastructure.

**Application characteristics:**
- Stateless PHP application (horizontal scaling possible)
- Redis for session persistence
- MySQL for data persistence
- No single points of failure with proper architecture

**Typical pilot SLA:** 99% (allows ~7 hours downtime/month)

### 8.2 What backup and restore capabilities exist?

**Response:**

**Backup tooling:**
- `scripts/backup_database.php` — mysqldump-based backup
- Supports compression
- Timestamped output files

**Restore tooling:**
- `scripts/restore_database.php` — Database restoration
- Includes safety prompts
- Verification before restore

**Deployer responsibility:**
- Schedule automated backups
- Configure offsite replication
- Test restoration regularly

---

## 9. Incident Response

### 9.1 How are security incidents reported?

**Response:**

**For open-source project:**
- GitHub Security Advisories (private disclosure)
- Maintainer contact via project repository

**For deployed instances:**
- Deployer's incident response process applies
- Recommend: Documented escalation path, contact list, severity classification

### 9.2 What forensic capabilities exist?

**Response:**

**Available evidence:**
- Audit logs (database)
- Application logs (filesystem)
- Web server access logs
- Database query logs (if enabled)

**Retention:**
- Audit logs: 7 years
- Application logs: 90 days
- Access logs: Deployer-configured

---

## 10. Compliance

### 10.1 What compliance standards does the application support?

**Response:**

**Designed to support:**
- UK GDPR (Data Protection Act 2018)
- WCAG 2.1 AA (via CivicOne theme) — CivicOne is designed to meet WCAG 2.1 AA; formal audit recommended prior to large-scale rollout
- Cyber Essentials (with appropriate deployment)

**Deployer responsibility:**
- Achieve organisational certifications
- Complete DPIA
- Implement appropriate policies

### 10.2 Is there a privacy notice template?

**Response:** Privacy notice content is deployer-specific.

**Application provides:**
- Consent capture mechanisms
- Consent version tracking
- Data export for transparency

**Deployer provides:**
- Organisation-specific privacy notice
- Cookie policy
- Terms of service

---

## 11. Accessibility

### 11.1 What accessibility standard is met?

**Response:** CivicOne is designed to meet WCAG 2.1 AA; formal audit recommended prior to large-scale rollout.

**Features:**
- GOV.UK Design System alignment
- Semantic HTML
- Keyboard navigation
- Screen reader compatibility
- High contrast support
- Focus indicators

**Code evidence:**
- 92 WCAG-related CSS rules across 20 files
- ARIA labels throughout templates
- Focus-visible styling

### 11.2 Has accessibility been independently tested?

**Response:** Formal accessibility audit recommended prior to large-scale rollout.

**Recommendation:** Commission WCAG 2.1 AA audit from certified assessor before production use with public-facing users.

---

## 12. Rate Limiting & Abuse Prevention

### 12.1 What rate limiting is implemented?

**Response:**

**Currently implemented:**
- Login endpoints: 5 attempts / 15-minute lockout
- Tracked by email address and IP

**Code reference:** `src/Core/RateLimiter.php`

**Recommended additions (infrastructure-level):**
- API endpoints: 100 requests/minute via WAF/nginx
- Search endpoints: 20 requests/minute
- Export endpoints: 3 requests/hour

See Rate Limiting Implementation Plan for application-level roadmap.

### 12.2 How is abuse reported and handled?

**Response:**

**User-level:**
- Report user functionality
- Content moderation tools
- User blocking

**System-level:**
- Failed login monitoring via audit logs
- IP-based pattern detection possible via log analysis

---

## Summary Checklist

| Category | Status | Notes |
|----------|--------|-------|
| Authentication | Implemented | TOTP-based 2FA available |
| Encryption (transit) | Deployer config | TLS 1.2+ required |
| Encryption (rest) | Deployer config | Database TDE recommended |
| Access control | Implemented | RBAC hierarchy |
| Audit logging | Implemented | 7-year retention |
| GDPR tooling | Implemented | DSR automation |
| SQL injection prevention | Implemented | Prepared statements |
| XSS prevention | Implemented | Output encoding, CSP |
| CSRF prevention | Implemented | Token-based |
| Rate limiting | Partial | Login only, infrastructure for others |
| Penetration testing | Required | Independent test before go-live |
| Accessibility | In progress | WCAG 2.1 AA design, audit recommended |
| Backup/restore | Tooling provided | Automation is deployer responsibility |

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
