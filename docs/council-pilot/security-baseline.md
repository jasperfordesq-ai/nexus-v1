# Security Baseline for Council Pilots

**Document ID:** NEXUS-SECURITY-BASELINE
**Version:** 1.0
**Date:** February 2026
**Classification:** OFFICIAL

---

## Purpose

This document defines the minimum security configuration required for deploying Project NEXUS in a council or public sector environment. It covers infrastructure hardening, not application code changes.

---

## 1. Transport Layer Security (TLS)

### Requirements

| Setting | Minimum | Recommended |
|---------|---------|-------------|
| TLS Version | 1.2 | 1.3 |
| Certificate | Valid, trusted CA | Extended Validation (EV) |
| Key Size | RSA 2048-bit | RSA 4096-bit or ECDSA P-256 |
| Certificate Renewal | Before expiry | Automated (certbot) |

### Configuration (Nginx)

```nginx
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;
ssl_stapling on;
ssl_stapling_verify on;
```

### Verification

```bash
# Check TLS configuration
openssl s_client -connect [your-domain]:443 -tls1_2
openssl s_client -connect [your-domain]:443 -tls1_3

# Online scanner (external)
# Use SSL Labs: https://www.ssllabs.com/ssltest/
```

---

## 2. Security Headers

### Required Headers

| Header | Value | Purpose |
|--------|-------|---------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Force HTTPS |
| `X-Frame-Options` | `SAMEORIGIN` | Prevent clickjacking |
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `Content-Security-Policy` | See below | Restrict resource loading |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Control referrer leakage |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` | Disable unused APIs |

### Content Security Policy (CSP)

**Baseline CSP (adjust for your CDN/analytics):**

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';
```

**Notes:**
- `'unsafe-inline'` required for current codebase (legacy inline scripts/styles)
- Tighten CSP as codebase is modernised
- Add specific domains if using external services (analytics, CDN)

### Nginx Configuration

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self';" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
```

### Verification

Use the provided script:

```bash
./scripts/pilot/verify-headers.sh https://[your-domain]
```

---

## 3. Cookie Security

### Requirements

| Attribute | Setting | Purpose |
|-----------|---------|---------|
| `Secure` | Required | HTTPS only |
| `HttpOnly` | Required | No JavaScript access |
| `SameSite` | `Lax` or `Strict` | CSRF protection |
| `Path` | `/` | Scope limitation |

### PHP Configuration (php.ini)

```ini
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
session.use_only_cookies = 1
```

### Verification

```bash
# Check cookie attributes in browser DevTools
# Network tab > select request > Headers > Set-Cookie
```

---

## 4. Backup Configuration

### Requirements

| Aspect | Minimum | Recommended |
|--------|---------|-------------|
| Frequency | Daily | Daily + transaction log |
| Retention | 7 days | 30 days |
| Storage | Local + offsite | Encrypted offsite |
| Testing | Monthly restore test | Weekly restore test |
| Encryption | At rest | At rest + in transit |

### Backup Script

Project NEXUS includes `scripts/backup_database.php`. Configure via cron:

```bash
# Daily backup at 02:00
0 2 * * * /usr/bin/php /var/www/nexus/scripts/backup_database.php >> /var/log/nexus/backup.log 2>&1
```

See [backup-cron.md](../scripts/pilot/backup-cron.md) for full examples.

### Backup Storage

- Store backups outside the web root
- Encrypt backups at rest (GPG or filesystem encryption)
- Copy to offsite location (S3, Azure Blob, separate server)
- Test restoration monthly

### Restoration Procedure

```bash
# Verify backup integrity
gunzip -t /backups/nexus_backup_YYYYMMDD.sql.gz

# Restore (CAUTION: destructive)
php scripts/restore_database.php /backups/nexus_backup_YYYYMMDD.sql.gz
```

---

## 5. Web Application Firewall (WAF)

### Purpose

A WAF provides immediate protection while application-level controls are strengthened. Recommended for production deployments.

### Options

| Option | Type | Notes |
|--------|------|-------|
| Cloudflare | Cloud | Free tier available, easy setup |
| AWS WAF | Cloud | If hosting on AWS |
| Azure WAF | Cloud | If hosting on Azure |
| ModSecurity | Self-hosted | Open source, requires tuning |

### Recommended Rules

Enable these rule categories:

- SQL Injection protection
- Cross-Site Scripting (XSS) protection
- Remote File Inclusion protection
- Local File Inclusion protection
- Common attack patterns (OWASP Core Rule Set)

### Rate Limiting via WAF

Until application-level rate limiting is implemented across all endpoints, use WAF rules:

```
# Cloudflare example rate limiting rules
Rule 1: /api/* - 100 requests/minute per IP
Rule 2: /login - 10 requests/minute per IP
Rule 3: /admin/* - 30 requests/minute per IP
```

### Nginx Rate Limiting (Alternative)

```nginx
# Define rate limiting zones
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=login:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=admin:10m rate=30r/m;

# Apply to locations
location /api/ {
    limit_req zone=api burst=20 nodelay;
    # ... rest of config
}

location /login {
    limit_req zone=login burst=5 nodelay;
    # ... rest of config
}

location /admin/ {
    limit_req zone=admin burst=10 nodelay;
    # ... rest of config
}
```

---

## 6. Application Security Settings

### PHP Configuration (php.ini)

```ini
# Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

# Error handling (production)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

# Session security
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax

# Upload limits (adjust as needed)
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 10

# Memory and execution limits
memory_limit = 256M
max_execution_time = 30
```

### File Permissions

```bash
# Web root
chown -R www-data:www-data /var/www/nexus
find /var/www/nexus -type d -exec chmod 755 {} \;
find /var/www/nexus -type f -exec chmod 644 {} \;

# Sensitive files
chmod 600 /var/www/nexus/.env
chmod 700 /var/www/nexus/scripts/

# Upload directory (if inside web root)
chmod 755 /var/www/nexus/httpdocs/uploads
```

### Environment File Security

```bash
# .env should never be accessible via web
# Nginx: already handled by location ~ /\. { deny all; }

# Verify
curl -I https://[your-domain]/.env
# Should return 403 Forbidden or 404 Not Found
```

---

## 7. Database Security

### User Permissions

```sql
-- Application user (minimal permissions)
CREATE USER 'nexus_app'@'localhost' IDENTIFIED BY '[STRONG_PASSWORD]';
GRANT SELECT, INSERT, UPDATE, DELETE ON nexus.* TO 'nexus_app'@'localhost';

-- Migration user (separate, for schema changes only)
CREATE USER 'nexus_migrate'@'localhost' IDENTIFIED BY '[DIFFERENT_PASSWORD]';
GRANT ALL PRIVILEGES ON nexus.* TO 'nexus_migrate'@'localhost';

-- Never use root for application connections
```

### Network Security

```ini
# MySQL bind address (my.cnf)
bind-address = 127.0.0.1

# Or use Unix socket only
skip-networking
```

### Encryption

```ini
# MySQL encryption at rest (my.cnf)
innodb_encrypt_tables = ON
innodb_encrypt_log = ON

# Require SSL for connections (if network access needed)
require_secure_transport = ON
```

---

## 8. Logging and Monitoring

### Required Logs

| Log | Location | Retention |
|-----|----------|-----------|
| Application errors | `/var/log/nexus/error.log` | 90 days |
| Access logs | `/var/log/nginx/access.log` | 30 days |
| Audit logs | Database + export | 1 year minimum |
| Authentication logs | Database | 90 days |

### Log Rotation (logrotate)

```
/var/log/nexus/*.log {
    daily
    rotate 90
    compress
    delaycompress
    missingok
    notifempty
    create 640 www-data adm
}
```

### Monitoring (Optional for Pilot)

Consider monitoring:
- Disk space usage
- Memory usage
- Database connections
- HTTP error rates (5xx)
- SSL certificate expiry

Tools: Prometheus + Grafana, Datadog, New Relic, or simple cron scripts.

---

## 9. Security Verification Checklist

### Before Go-Live

- [ ] TLS 1.2+ configured and verified
- [ ] All security headers present (`verify-headers.sh` passes)
- [ ] Cookies have Secure, HttpOnly, SameSite attributes
- [ ] `.env` file not accessible via web
- [ ] PHP errors not displayed to users
- [ ] Database user has minimal permissions
- [ ] Backups configured and tested
- [ ] WAF or nginx rate limiting enabled
- [ ] An independent penetration test must be completed before go-live with real users

### Ongoing

- [ ] SSL certificate renewal automated
- [ ] Backup restoration tested monthly
- [ ] Security patches applied within 30 days
- [ ] Audit logs reviewed weekly
- [ ] Access logs reviewed for anomalies

---

## 10. Incident Response

### Contact Points

| Role | Contact | Notes |
|------|---------|-------|
| Technical Lead | TBC | First point of contact |
| Information Security | TBC | For security incidents |
| Data Protection Officer | TBC | For data breaches |

### Incident Classification

| Severity | Examples | Response Time |
|----------|----------|---------------|
| Critical | Data breach, system compromise | Immediate |
| High | Service outage, security vulnerability | 4 hours |
| Medium | Performance degradation, minor bug | 24 hours |
| Low | Cosmetic issues, feature requests | 5 days |

### Incident Response Steps

1. **Detect** — Identify the incident (monitoring, user report, audit log)
2. **Contain** — Limit damage (disable accounts, block IPs, take offline if needed)
3. **Investigate** — Determine root cause and scope
4. **Remediate** — Fix the issue and restore service
5. **Report** — Document incident, notify stakeholders, report to ICO if data breach
6. **Review** — Lessons learned, update procedures

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
