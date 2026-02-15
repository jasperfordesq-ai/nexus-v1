# Project NEXUS - Complete Deployment Guide

> **Last Updated**: 2026-02-05
> **Primary Server**: Azure VM (`20.224.171.253`)
> **Status**: GCP server DISCONTINUED - Azure is the ONLY deployment target

---

## ⚠️ KNOWN ISSUES - ACTION REQUIRED

### Issue #1: project-nexus.ie Serving Wrong Content (CRITICAL)

**Status**: 🔴 NOT FIXED - Awaiting sales site deployment

**Problem**: `project-nexus.ie` is showing the PHP API monolith instead of the sales site.

**Root Cause**: The Apache `vhost.conf` for `project-nexus.ie` proxies to port 8090 (PHP API) instead of port 3001 (sales site).

**Current Configuration** (WRONG):
```
/var/www/vhosts/system/project-nexus.ie/conf/vhost.conf:
ProxyPass / http://127.0.0.1:8090/      ← Points to PHP API (WRONG!)
```

**Required Configuration** (after sales site container is deployed):
```
/var/www/vhosts/system/project-nexus.ie/conf/vhost.conf:
ProxyPass / http://127.0.0.1:3001/      ← Should point to Sales Site
```

**Fix Steps** (DO NOT RUN UNTIL SALES SITE CONTAINER IS READY):
```bash
# 1. SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# 2. Edit vhost.conf
sudo nano /var/www/vhosts/system/project-nexus.ie/conf/vhost.conf

# 3. Change content to:
# Project NEXUS - Sales Site Reverse Proxy
ProxyPreserveHost On
ProxyPass / http://127.0.0.1:3001/
ProxyPassReverse / http://127.0.0.1:3001/
RequestHeader set X-Forwarded-Host "%{HTTP_HOST}s"

# 4. Test and reload Apache
sudo apachectl configtest
sudo systemctl reload apache2
```

### Issue #2: Sales Site Container Does Not Exist Yet

**Status**: 🟡 IN PROGRESS - Local container created, not deployed to production

**Problem**: The sales site Docker container (`nexus-sales-site`) needs to be created and deployed.

**Location**: `sales-site/` directory (local development)

**Port**: 3001

**Fix Steps**:
1. ✅ Create `sales-site/` directory structure (DONE)
2. ✅ Create Dockerfile (DONE)
3. ✅ Add to local `compose.yml` (DONE)
4. 🔴 Build and test locally
5. 🔴 Deploy to production (AWAITING USER APPROVAL)
6. 🔴 Update Apache vhost.conf (after container is running)

---

## Table of Contents

1. [Quick Reference](#quick-reference)
2. [Architecture Overview](#architecture-overview)
3. [Server Connection](#server-connection)
4. [Production Domains](#production-domains)
5. [Docker Services](#docker-services)
6. [Local Development](#local-development)
7. [Deployment Commands](#deployment-commands)
8. [Environment Configuration](#environment-configuration)
9. [Nginx/Apache Proxy Configuration](#nginxapache-proxy-configuration)
10. [Database Management](#database-management)
11. [SSL Certificates](#ssl-certificates)
12. [Monitoring & Logs](#monitoring--logs)
13. [Troubleshooting](#troubleshooting)
14. [Protected Services (DO NOT TOUCH)](#protected-services-do-not-touch)
15. [Legacy Server Reference](#legacy-server-reference-discontinued)

---

## Quick Reference

### Production Server (Azure)

| Item | Value |
|------|-------|
| **Host** | `20.224.171.253` |
| **User** | `azureuser` |
| **SSH Key** | `C:\ssh-keys\project-nexus.pem` |
| **Deploy Path** | `/opt/nexus-php/` |
| **Plesk Panel** | https://20.224.171.253:8443 |

### Production URLs

| Domain | Purpose | Container | Port |
|--------|---------|-----------|------|
| `api.project-nexus.ie` | PHP API Backend | `nexus-php-app` | 8090 |
| `app.project-nexus.ie` | React Frontend (User App) | `nexus-react-prod` | 3000 |
| `project-nexus.ie` | Sales/Marketing Site | `nexus-sales-site` | 3001 |

### Quick Commands

```bash
# SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Deploy (Windows)
scripts\deploy-production.bat

# Deploy (Git Bash/Linux)
./scripts/deploy-production.sh

# Check container status
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker compose ps"
```

---

## Architecture Overview

```
                              ┌─────────────────────────────────────────────────────────┐
                              │              Azure VM (20.224.171.253)                  │
                              │                Ubuntu 24.04 + Plesk                     │
┌──────────────┐              │                                                         │
│   Internet   │              │  ┌───────────────────────────────────────────────────┐ │
│              │              │  │              Nginx (Plesk) - Port 443              │ │
│  Browsers    │── HTTPS ────▶│  └───────────────────┬───────────────────────────────┘ │
│              │              │                      │                                  │
└──────────────┘              │    ┌─────────────────┼─────────────────┐               │
                              │    │                 │                 │               │
                              │    ▼                 ▼                 ▼               │
                              │ ┌─────────┐    ┌─────────┐    ┌─────────────┐         │
                              │ │ api.    │    │ app.    │    │ project-    │         │
                              │ │project- │    │project- │    │ nexus.ie    │         │
                              │ │nexus.ie │    │nexus.ie │    │ (Sales)     │         │
                              │ └────┬────┘    └────┬────┘    └──────┬──────┘         │
                              │      │              │                │                 │
                              │      ▼              ▼                ▼                 │
                              │ ┌─────────────────────────────────────────────────┐   │
                              │ │            Docker Network (nexus-php-internal)   │   │
                              │ │                                                  │   │
                              │ │  ┌───────────┐ ┌───────────┐ ┌───────────────┐  │   │
                              │ │  │ PHP API   │ │  React    │ │  Sales Site   │  │   │
                              │ │  │ :8090     │ │  App      │ │  :3001        │  │   │
                              │ │  │           │ │  :3000    │ │               │  │   │
                              │ │  └─────┬─────┘ └───────────┘ └───────────────┘  │   │
                              │ │        │                                         │   │
                              │ │        ▼                                         │   │
                              │ │  ┌───────────┐    ┌───────────┐                 │   │
                              │ │  │ MariaDB   │    │   Redis   │                 │   │
                              │ │  │ :3306     │    │   :6379   │                 │   │
                              │ │  └───────────┘    └───────────┘                 │   │
                              │ └─────────────────────────────────────────────────┘   │
                              └─────────────────────────────────────────────────────────┘
```

### Three Separate Applications

| Application | Domain | Description |
|-------------|--------|-------------|
| **PHP API** | `api.project-nexus.ie` | Backend monolith - handles all API requests, authentication, database operations |
| **React App** | `app.project-nexus.ie` | User-facing SPA - the main timebanking application |
| **Sales Site** | `project-nexus.ie` | Marketing/landing page - separate from the app, for converting visitors |

**Important**: These are THREE SEPARATE Docker containers. They should NOT serve the same content.

---

## Server Connection

### SSH Access

```bash
# Primary connection (Windows)
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Primary connection (Git Bash/Linux)
ssh -i C:/ssh-keys/project-nexus.pem azureuser@20.224.171.253

# With specific commands
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker compose ps"
```

### Server Specifications

| Component | Value |
|-----------|-------|
| **Cloud Provider** | Microsoft Azure |
| **OS** | Ubuntu 24.04.3 LTS |
| **CPU** | AMD EPYC 7763 (4 vCPUs) |
| **RAM** | 16 GB |
| **Disk** | 247 GB SSD |
| **Control Panel** | Plesk Obsidian 18.0 |
| **Docker** | 29.2.1 |

---

## Production Domains

### DNS Configuration

All domains must point to `20.224.171.253`:

| Type | Name | Value |
|------|------|-------|
| A | `api.project-nexus.ie` | `20.224.171.253` |
| A | `app.project-nexus.ie` | `20.224.171.253` |
| A | `project-nexus.ie` | `20.224.171.253` |
| A | `hour-timebank.ie` | `20.224.171.253` |
| CNAME | `www.project-nexus.ie` | `project-nexus.ie` |

### Domain Aliases

Each primary domain has aliases that should serve the same content:

| Primary | Aliases |
|---------|---------|
| `api.project-nexus.ie` | `api.hour-timebank.ie` |
| `app.project-nexus.ie` | `app.hour-timebank.ie` |
| `project-nexus.ie` | `hour-timebank.ie`, `www.project-nexus.ie` |

---

## Docker Services

### Production Docker Compose

**File**: `/opt/nexus-php/compose.prod.yml`

```yaml
name: nexus-php

services:
  # ─────────────────────────────────────────────────────────────────
  # PHP API Backend (api.project-nexus.ie)
  # ─────────────────────────────────────────────────────────────────
  app:
    build:
      context: .
      dockerfile: Dockerfile.prod
    container_name: nexus-php-app
    restart: unless-stopped
    ports:
      - "8090:80"
    volumes:
      - ./httpdocs:/var/www/html/httpdocs:ro
      - ./src:/var/www/html/src:ro
      - ./views:/var/www/html/views:ro
      - ./config:/var/www/html/config:ro
      - ./vendor:/var/www/html/vendor:ro
      - nexus-php-uploads:/var/www/html/httpdocs/uploads
    env_file:
      - .env
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - nexus-php-internal
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health.php"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 30s

  # ─────────────────────────────────────────────────────────────────
  # React Frontend (app.project-nexus.ie)
  # ─────────────────────────────────────────────────────────────────
  frontend:
    build:
      context: ./react-frontend
      dockerfile: Dockerfile.prod
      args:
        VITE_API_BASE: https://api.project-nexus.ie
    container_name: nexus-react-prod
    restart: unless-stopped
    ports:
      - "3000:80"
    networks:
      - nexus-php-internal
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

  # ─────────────────────────────────────────────────────────────────
  # Sales/Marketing Site (project-nexus.ie) - TO BE CREATED
  # ─────────────────────────────────────────────────────────────────
  sales:
    build:
      context: ./sales-site
      dockerfile: Dockerfile
    container_name: nexus-sales-site
    restart: unless-stopped
    ports:
      - "3001:80"
    networks:
      - nexus-php-internal
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

  # ─────────────────────────────────────────────────────────────────
  # MariaDB Database
  # ─────────────────────────────────────────────────────────────────
  db:
    image: mariadb:10.11
    container_name: nexus-php-db
    restart: unless-stopped
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --innodb-buffer-pool-size=256M
    environment:
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MARIADB_DATABASE: ${DB_NAME}
      MARIADB_USER: ${DB_USER}
      MARIADB_PASSWORD: ${DB_PASS}
    volumes:
      - nexus-php-db-data:/var/lib/mysql
    networks:
      - nexus-php-internal
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 60s

  # ─────────────────────────────────────────────────────────────────
  # Redis (Sessions & Cache)
  # ─────────────────────────────────────────────────────────────────
  redis:
    image: redis:7-alpine
    container_name: nexus-php-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru
    volumes:
      - nexus-php-redis-data:/data
    networks:
      - nexus-php-internal
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

networks:
  nexus-php-internal:
    name: nexus-php-internal
    driver: bridge

volumes:
  nexus-php-db-data:
    name: nexus-php-db-data
  nexus-php-redis-data:
    name: nexus-php-redis-data
  nexus-php-uploads:
    name: nexus-php-uploads
```

### Container Summary

| Container | Port | Domain | Health Check |
|-----------|------|--------|--------------|
| `nexus-php-app` | 8090 | `api.project-nexus.ie` | `/health.php` |
| `nexus-react-prod` | 3000 | `app.project-nexus.ie` | `/` |
| `nexus-sales-site` | 3001 | `project-nexus.ie` | `/` |
| `nexus-php-db` | 3306 (internal) | - | healthcheck.sh |
| `nexus-php-redis` | 6379 (internal) | - | redis-cli ping |

### Docker Commands

```bash
# Navigate to deployment directory
cd /opt/nexus-php

# Start all services
sudo docker compose up -d

# Stop all services
sudo docker compose down

# Restart specific service
sudo docker compose restart app
sudo docker compose restart frontend
sudo docker compose restart sales

# View logs
sudo docker compose logs -f           # All services
sudo docker compose logs -f app       # PHP API only
sudo docker compose logs -f frontend  # React only
sudo docker compose logs -f sales     # Sales site only

# Rebuild and restart
sudo docker compose up -d --build

# Check status
sudo docker compose ps

# Execute command in container
sudo docker exec -it nexus-php-app bash
sudo docker exec -it nexus-php-db mysql -u nexus -p

# View resource usage
sudo docker stats --no-stream
```

---

## Local Development

### Docker (Recommended)

```bash
# Start local environment
docker compose up -d

# Stop (preserves data)
docker compose down

# Stop and remove volumes
docker compose down -v
```

### Local URLs

| Service | URL |
|---------|-----|
| React Frontend | http://localhost:5173 |
| PHP API | http://localhost:8090 |
| Legacy PHP Views | http://localhost:8090/{tenant}/ |
| phpMyAdmin | http://localhost:8091 (with `--profile tools`) |
| Sales Site | http://localhost:3001 (when created) |

### Local File Structure

```
project-root/
├── react-frontend/           # React app source
│   ├── src/
│   ├── Dockerfile.prod
│   └── package.json
├── sales-site/               # Sales site source (TO BE CREATED)
│   ├── src/
│   └── Dockerfile
├── httpdocs/                 # PHP public root
├── src/                      # PHP classes
├── views/                    # PHP templates
├── config/                   # Configuration
├── compose.yml               # Local Docker Compose
├── compose.prod.yml          # Production Docker Compose
├── Dockerfile.prod           # PHP production Dockerfile
└── .env.docker               # Local environment
```

---

## Deployment Commands

### Windows (Batch Script)

```bash
# Full deployment
scripts\deploy-production.bat

# Quick deployment (code sync + restart, no rebuild)
scripts\deploy-production.bat quick

# First-time setup
scripts\deploy-production.bat init

# Check status
scripts\deploy-production.bat status

# Update nginx only
scripts\deploy-production.bat nginx
```

### Git Bash / Linux

```bash
# Full deployment
./scripts/deploy-production.sh

# Quick deployment
./scripts/deploy-production.sh --quick

# First-time setup
./scripts/deploy-production.sh --init

# Check status
./scripts/deploy-production.sh --status

# Update nginx only
./scripts/deploy-production.sh --nginx
```

### Manual Deployment Steps

```bash
# 1. SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# 2. Navigate to project
cd /opt/nexus-php

# 3. Pull latest code (if using git on server)
git pull origin main

# 4. Or sync files from local (Windows rsync via Git Bash)
rsync -avz -e "ssh -i C:/ssh-keys/project-nexus.pem" \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude '.git' \
  --exclude '.env' \
  --exclude 'uploads/*' \
  ./ azureuser@20.224.171.253:/opt/nexus-php/

# 5. Rebuild containers if Dockerfile changed
sudo docker compose up -d --build

# 6. Or just restart if only code changed
sudo docker compose restart app frontend

# 7. Verify health
curl http://127.0.0.1:8090/health.php
curl http://127.0.0.1:3000/
```

### What Gets Deployed

**Deployed:**
- `httpdocs/` - PHP public files, routes, assets
- `src/` - PHP controllers, models, services
- `views/` - PHP templates
- `config/` - Configuration files
- `react-frontend/` - React source code
- `sales-site/` - Sales site source (when created)
- `migrations/` - Database migrations
- `Dockerfile.prod`, `compose.prod.yml` - Container configs

**Never Deployed:**
- `.env` - Contains secrets (create on server)
- `vendor/` - Install via Composer on server
- `node_modules/` - Build artifacts, not deployed
- `uploads/` - User uploads (persistent volume)
- `.git/` - Git history
- `backups/`, `logs/` - Local data

---

## Environment Configuration

### Production Environment File

**File**: `/opt/nexus-php/.env`

```bash
# =============================================================================
# Application
# =============================================================================
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.project-nexus.ie
APP_KEY=<generate-32-char-random-string>

# =============================================================================
# JWT Authentication
# =============================================================================
JWT_SECRET=<generate-64-char-random-string>

# =============================================================================
# Database (Docker MariaDB)
# =============================================================================
DB_TYPE=mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=nexus
DB_USER=nexus
DB_PASS=<strong-password>
DB_ROOT_PASSWORD=<strong-root-password>

# =============================================================================
# Redis
# =============================================================================
REDIS_HOST=redis
REDIS_PORT=6379

# =============================================================================
# Pusher (Real-time notifications)
# =============================================================================
PUSHER_APP_ID=<your-pusher-app-id>
PUSHER_APP_KEY=<your-pusher-key>
PUSHER_APP_SECRET=<your-pusher-secret>
PUSHER_APP_CLUSTER=eu

# =============================================================================
# OpenAI (AI features)
# =============================================================================
OPENAI_API_KEY=<your-openai-key>

# =============================================================================
# Gmail API (Email)
# =============================================================================
USE_GMAIL_API=true
GMAIL_CLIENT_ID=<your-gmail-client-id>
GMAIL_CLIENT_SECRET=<your-gmail-secret>
GMAIL_REFRESH_TOKEN=<your-gmail-refresh-token>
```

### Generate Secure Keys

```bash
# APP_KEY (32 characters)
openssl rand -base64 32

# JWT_SECRET (64 characters)
openssl rand -base64 64

# Database passwords
openssl rand -base64 24
```

---

## Nginx/Apache Proxy Configuration

### How Plesk Routing Works

Plesk uses a **Nginx → Apache** proxy chain:
1. Nginx receives HTTPS requests on port 443
2. Nginx proxies to Apache on port 7081
3. Apache processes PHP or proxies to Docker containers

**Custom configuration files** in Plesk:

| File | Purpose |
|------|---------|
| `vhost.conf` | Custom Apache directives (HTTP) |
| `vhost_ssl.conf` | Custom Apache SSL directives |
| `vhost_nginx.conf` | Custom Nginx directives |

Location: `/var/www/vhosts/system/{domain}/conf/`

### Current Configuration Issue

**Problem discovered**: Both `project-nexus.ie` and `api.project-nexus.ie` were proxying to the same PHP container (port 8090).

**Current vhost.conf files:**

```bash
# api.project-nexus.ie - CORRECT
/var/www/vhosts/system/api.project-nexus.ie/conf/vhost.conf:
ProxyPreserveHost On
ProxyPass / http://127.0.0.1:8090/
ProxyPassReverse / http://127.0.0.1:8090/

# app.project-nexus.ie - CORRECT
/var/www/vhosts/system/app.project-nexus.ie/conf/vhost.conf:
ProxyPreserveHost On
ProxyPass / http://127.0.0.1:3000/
ProxyPassReverse / http://127.0.0.1:3000/

# project-nexus.ie - NEEDS TO BE UPDATED
/var/www/vhosts/system/project-nexus.ie/conf/vhost.conf:
# Currently proxies to 8090 (PHP API) - WRONG!
# Should proxy to 3001 (Sales site) when container is ready
```

### Correct Configuration for Sales Site

**File**: `/var/www/vhosts/system/project-nexus.ie/conf/vhost.conf`

```apache
# Project NEXUS - Sales Site Reverse Proxy
# Proxies project-nexus.ie to Docker container on port 3001

ProxyPreserveHost On
ProxyPass / http://127.0.0.1:3001/
ProxyPassReverse / http://127.0.0.1:3001/

# Pass original host header
RequestHeader set X-Forwarded-Host "%{HTTP_HOST}s"
RequestHeader set X-Original-Host "%{HTTP_HOST}s"
```

### Apply Configuration Changes

```bash
# SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Edit vhost.conf
sudo nano /var/www/vhosts/system/project-nexus.ie/conf/vhost.conf

# Test Apache config
sudo apachectl configtest

# Reload Apache
sudo systemctl reload apache2

# Or via Plesk
sudo plesk sbin httpdmng --reconfigure-domain project-nexus.ie
```

---

## Database Management

### Access Database

```bash
# From inside PHP container
sudo docker exec -it nexus-php-db mysql -u nexus -p nexus

# Backup database
sudo docker exec nexus-php-db mysqldump -u nexus -p nexus > backup_$(date +%Y%m%d).sql

# Restore database
sudo docker exec -i nexus-php-db mysql -u nexus -p nexus < backup.sql
```

### Run Migrations

```bash
# From inside PHP container
sudo docker exec nexus-php-app php /var/www/html/scripts/safe_migrate.php

# Or manually
sudo docker exec -it nexus-php-app bash
cd /var/www/html
php scripts/safe_migrate.php
```

---

## SSL Certificates

SSL is managed by Plesk using Let's Encrypt.

### Issue/Renew Certificate

```bash
# Via Plesk CLI
sudo plesk bin extension --call sslit --exec certificate -issue \
  -domain api.project-nexus.ie \
  -registrationEmail admin@project-nexus.ie \
  -secure-domain -secure-www

# Check certificate
sudo openssl s_client -connect api.project-nexus.ie:443 -servername api.project-nexus.ie
```

---

## Monitoring & Logs

### View Logs

```bash
# Docker logs
sudo docker compose logs -f app        # PHP API
sudo docker compose logs -f frontend   # React app
sudo docker compose logs -f sales      # Sales site
sudo docker compose logs -f db         # Database

# Plesk/Nginx logs
sudo tail -f /var/www/vhosts/system/api.project-nexus.ie/logs/access_ssl_log
sudo tail -f /var/www/vhosts/system/api.project-nexus.ie/logs/error_log

# PHP errors
sudo docker exec nexus-php-app tail -f /var/log/php_errors.log
```

### Health Checks

```bash
# Test all services
curl -s https://api.project-nexus.ie/health.php | jq
curl -s -o /dev/null -w "%{http_code}" https://app.project-nexus.ie/
curl -s -o /dev/null -w "%{http_code}" https://project-nexus.ie/

# From server (internal)
curl http://127.0.0.1:8090/health.php
curl http://127.0.0.1:3000/
curl http://127.0.0.1:3001/

# Container health
sudo docker ps --format "table {{.Names}}\t{{.Status}}"
```

---

## Troubleshooting

### Container Won't Start

```bash
# Check logs
sudo docker compose logs app

# Check port conflicts
sudo ss -tlnp | grep :8090

# Rebuild from scratch
sudo docker compose down
sudo docker compose build --no-cache
sudo docker compose up -d
```

### 502 Bad Gateway

```bash
# Check if container is running
sudo docker ps | grep nexus

# Test internal connection
curl http://127.0.0.1:8090/health.php

# Check nginx config
sudo nginx -t
```

### Database Connection Failed

```bash
# Check database container
sudo docker compose logs db

# Test from app container
sudo docker exec nexus-php-app php -r "new PDO('mysql:host=db;dbname=nexus', 'nexus', 'password');"

# Check env vars
sudo docker exec nexus-php-app env | grep DB_
```

### Wrong Site Showing on Domain

If `project-nexus.ie` shows the PHP API instead of sales site:

1. Check `/var/www/vhosts/system/project-nexus.ie/conf/vhost.conf`
2. Ensure it proxies to port 3001 (not 8090)
3. Ensure the sales site container is running on port 3001
4. Reload Apache: `sudo systemctl reload apache2`

---

## Our Containers (project-nexus.ie)

These are the **ONLY** containers this project deploys and manages:

| Container | Port | Purpose |
|-----------|------|---------|
| `nexus-php-app` | 8090 | PHP API (Apache) |
| `nexus-php-db` | Internal | MariaDB |
| `nexus-php-redis` | Internal | Redis |
| `nexus-react-prod` | 3000 | React Frontend |
| `nexus-sales-site` | 3001 | Sales/Marketing Site |

Deploy path: `/opt/nexus-php/`

## Protected Services (DO NOT TOUCH)

> **CRITICAL**: The Azure server hosts separate projects.
> **NEVER modify, stop, or interfere with these services from this project!**

### Protected Containers

| Container | Port | Purpose |
|-----------|------|---------|
| `nexus-backend-api` | 5080 | .NET Core API |
| `nexus-frontend-prod` | 5171 | Next.js Frontend |
| `nexus-backend-db` | 5432 | PostgreSQL |
| `nexus-backend-rabbitmq` | 5672, 15672 | Message Queue |
| `nexus-backend-llama` | 11434 | AI/LLM Service |
| `nexus-uk-frontend-dev` | 5180 | UK Frontend |

### Protected Directories

```
/opt/nexus-backend/           # ⛔ DO NOT TOUCH
/opt/nexus-modern-frontend/   # ⛔ DO NOT TOUCH
/opt/nexus-uk-frontend/       # ⛔ DO NOT TOUCH
```

### Protected Networks

```
nexus-backend-net             # ⛔ DO NOT TOUCH
nexus-modern-frontend_default
```

### Safe Port Ranges

Ports you CAN use for new services:
- 3000-3999 (except 3002)
- 8080-8089
- 8091-8099
- 9000-9999

---

## Legacy Server Reference (DISCONTINUED)

> **⚠️ DO NOT DEPLOY TO THIS SERVER**
> The GCP server at `35.205.239.67` is discontinued.
> This section is for historical reference only.

<details>
<summary>Click to expand legacy GCP details</summary>

### Connection (DO NOT USE)

| Item | Value |
|------|-------|
| **Host** | `35.205.239.67` |
| **User** | `jasper` |
| **SSH Key** | `~/.ssh/id_ed25519` |
| **Path** | `/var/www/vhosts/project-nexus.ie` |

### Legacy Deploy Commands (DO NOT USE)

```bash
# These commands deploy to the OLD GCP server - DO NOT USE
npm run deploy:preview
npm run deploy
npm run deploy:changed
npm run deploy:full
bash scripts/claude-deploy.sh
```

### Legacy Path Mapping

| Local | Remote (GCP - Discontinued) |
|-------|----------------------------|
| `httpdocs/` | `/var/www/vhosts/project-nexus.ie/httpdocs/` |
| `views/` | `/var/www/vhosts/project-nexus.ie/views/` |
| `src/` | `/var/www/vhosts/project-nexus.ie/src/` |
| `config/` | `/var/www/vhosts/project-nexus.ie/config/` |

</details>

---

## TODO: Sales Site Setup

The sales site (`project-nexus.ie`) needs to be created as a separate Docker container:

### Required Steps

1. **Create sales site source directory**
   ```bash
   mkdir -p sales-site/src
   ```

2. **Create Dockerfile for sales site**
   ```dockerfile
   # sales-site/Dockerfile
   FROM node:20-alpine AS builder
   WORKDIR /app
   COPY package*.json ./
   RUN npm ci
   COPY . .
   RUN npm run build

   FROM nginx:alpine
   COPY --from=builder /app/dist /usr/share/nginx/html
   COPY nginx.conf /etc/nginx/conf.d/default.conf
   EXPOSE 80
   CMD ["nginx", "-g", "daemon off;"]
   ```

3. **Update compose.prod.yml** (already included above)

4. **Update Apache vhost.conf**
   ```bash
   sudo nano /var/www/vhosts/system/project-nexus.ie/conf/vhost.conf
   # Change proxy from 8090 to 3001
   ```

5. **Deploy and verify**
   ```bash
   sudo docker compose up -d sales
   curl http://127.0.0.1:3001/
   ```

---

## Quick Reference Card

```bash
# ═══════════════════════════════════════════════════════════════════
# SSH
# ═══════════════════════════════════════════════════════════════════
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# ═══════════════════════════════════════════════════════════════════
# Deploy
# ═══════════════════════════════════════════════════════════════════
scripts\deploy-production.bat           # Windows - full
scripts\deploy-production.bat quick     # Windows - code only

# ═══════════════════════════════════════════════════════════════════
# Docker (on server)
# ═══════════════════════════════════════════════════════════════════
cd /opt/nexus-php
sudo docker compose up -d               # Start
sudo docker compose down                # Stop
sudo docker compose restart app         # Restart PHP
sudo docker compose logs -f app         # Logs

# ═══════════════════════════════════════════════════════════════════
# Health Checks
# ═══════════════════════════════════════════════════════════════════
curl https://api.project-nexus.ie/health.php
curl https://app.project-nexus.ie/
curl https://project-nexus.ie/

# ═══════════════════════════════════════════════════════════════════
# URLs
# ═══════════════════════════════════════════════════════════════════
# API:        https://api.project-nexus.ie
# React App:  https://app.project-nexus.ie
# Sales:      https://project-nexus.ie
# Plesk:      https://20.224.171.253:8443
```

---

**Document maintained by**: Project NEXUS Team
**Last reviewed**: 2026-02-05
