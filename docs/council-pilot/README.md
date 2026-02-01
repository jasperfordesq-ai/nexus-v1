# Council Pilot Deployment Guide

**Document ID:** NEXUS-PILOT-README
**Version:** 1.0
**Date:** February 2026
**Classification:** OFFICIAL

---

## Purpose

This guide provides council and public sector organisations with the information needed to deploy Project NEXUS for a pilot programme. It covers prerequisites, installation, configuration, and go-live readiness.

---

## Prerequisites

### Technical Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.1 | 8.2+ |
| MySQL | 8.0 | 8.0+ |
| Redis | 7.0 | 7.0+ |
| Web Server | Apache 2.4 / Nginx 1.18 | Nginx 1.24+ |
| SSL Certificate | Valid TLS 1.2+ | TLS 1.3 |
| Memory | 2GB RAM | 4GB+ RAM |
| Storage | 20GB | 50GB+ (depends on uploads) |

### Organisational Requirements

- [ ] Named project sponsor with sign-off authority
- [ ] Technical contact for deployment and support
- [ ] Data Protection Officer (DPO) review of DPIA
- [ ] Information Security team review of security baseline
- [ ] Identified pilot user group (recommended: 20-100 users)
- [ ] Support arrangements confirmed (internal or contracted)

### Documentation to Review First

1. [Security Baseline](security-baseline.md) — infrastructure hardening
2. [Architecture Overview](architecture.md) — system design
3. [DPIA Template](dpia-template.md) — data protection assessment
4. [Security Q&A](security-qna.md) — common security questions
5. [Procurement Support](procurement-support.md) — buying support services

---

## Installation Steps

### Step 1: Environment Setup

```bash
# Clone repository (or extract release archive)
git clone https://github.com/[organisation]/project-nexus.git /var/www/nexus

# Set ownership
chown -R www-data:www-data /var/www/nexus

# Install PHP dependencies
cd /var/www/nexus
composer install --no-dev --optimize-autoloader
```

### Step 2: Database Setup

```bash
# Create database and user
mysql -u root -p <<EOF
CREATE DATABASE nexus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nexus_app'@'localhost' IDENTIFIED BY '[STRONG_PASSWORD]';
GRANT SELECT, INSERT, UPDATE, DELETE ON nexus.* TO 'nexus_app'@'localhost';
FLUSH PRIVILEGES;
EOF

# Run migrations
php scripts/safe_migrate.php
```

### Step 3: Environment Configuration

```bash
# Copy example environment file
cp .env.example .env

# Edit with your values
nano .env
```

**Required `.env` settings:**

```ini
# Database
DB_HOST=localhost
DB_NAME=nexus
DB_USER=nexus_app
DB_PASS=[STRONG_PASSWORD]

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://[your-domain]

# Session
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

# Email (configure your SMTP or Gmail API)
MAIL_DRIVER=smtp
MAIL_HOST=[smtp-server]
MAIL_PORT=587
MAIL_USERNAME=[username]
MAIL_PASSWORD=[password]
```

### Step 4: Web Server Configuration

**Nginx example:**

```nginx
server {
    listen 443 ssl http2;
    server_name [your-domain];
    root /var/www/nexus/httpdocs;
    index index.php;

    ssl_certificate /etc/ssl/certs/[your-cert].pem;
    ssl_certificate_key /etc/ssl/private/[your-key].pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # Security headers (see security-baseline.md)
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### Step 5: Initial Setup

```bash
# Build CSS assets
npm install
npm run build:css

# Create first tenant
php scripts/create_tenant.php --name="[Council Name]" --slug="[council-slug]"

# Create admin user
php scripts/create_admin.php --email="[admin@council.gov.uk]" --tenant="[council-slug]"
```

### Step 6: Verify Installation

```bash
# Run header verification
./scripts/pilot/verify-headers.sh https://[your-domain]

# Check healthcheck endpoint
curl https://[your-domain]/healthcheck
```

---

## Configuration Checklist

### Security Configuration

- [ ] TLS certificate installed and valid
- [ ] Security headers configured (run `verify-headers.sh`)
- [ ] `.env` file permissions set to 600
- [ ] `APP_DEBUG=false` in production
- [ ] Database user has minimal required permissions
- [ ] Redis authentication enabled (if network-accessible)
- [ ] File upload directory outside web root or protected
- [ ] Error logging configured (not displayed to users)

### Application Configuration

- [ ] Tenant created with correct name and branding
- [ ] Admin user created with strong password
- [ ] Email sending configured and tested
- [ ] Session timeout appropriate (default: 2 hours)
- [ ] Timezone set correctly
- [ ] Feature flags reviewed for pilot scope

### Operational Configuration

- [ ] Backup script scheduled (see [backup-cron.md](../scripts/pilot/backup-cron.md))
- [ ] Audit log export scheduled (see [audit-export-cron.md](../scripts/pilot/audit-export-cron.md))
- [ ] Log rotation configured
- [ ] Monitoring/alerting configured (optional for pilot)
- [ ] SSL certificate renewal automated

### Accessibility Configuration

- [ ] CivicOne theme enabled (GOV.UK Design System aligned)
- [ ] High contrast mode available
- [ ] Screen reader testing completed
- [ ] Keyboard navigation verified

---

## Go-Live Checklist

### Pre-Launch (T-5 days)

- [ ] DPIA completed and signed by DPO
- [ ] Security baseline review completed by InfoSec
- [ ] An independent penetration test must be completed before go-live with real users
- [ ] Pilot user list finalised
- [ ] User guidance/training materials prepared
- [ ] Support contact details published
- [ ] Rollback procedure documented and tested

### Launch Day (T-0)

- [ ] Final backup taken before user access
- [ ] Admin accounts verified working
- [ ] Pilot users invited/accounts created
- [ ] Welcome communications sent
- [ ] Support team briefed and available

### Post-Launch (T+1 to T+7)

- [ ] Daily backup verification
- [ ] Review error logs for issues
- [ ] Collect initial user feedback
- [ ] Monitor system performance
- [ ] Address any critical issues immediately

### Pilot Review (T+30)

- [ ] User feedback collected and analysed
- [ ] Usage metrics reviewed
- [ ] Security incidents reviewed (if any)
- [ ] Performance assessment completed
- [ ] Lessons learned documented
- [ ] Decision on continuation/expansion

---

## Support Resources

### Documentation

- Technical documentation: `/docs/` directory
- API documentation: `/docs/api/` (if applicable)
- User guides: TBC (organisation-specific)

### Getting Help

- **Community support:** GitHub Issues (response not guaranteed)
- **Contracted support:** See [Procurement Support](procurement-support.md)
- **Security issues:** Report via responsible disclosure process

### Useful Commands

```bash
# Check application status
php scripts/healthcheck.php

# View recent errors
tail -100 /var/log/nexus/error.log

# Run database backup
php scripts/backup_database.php

# Clear application cache
php scripts/clear_cache.php
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
