# Project NEXUS - New Production Server Documentation

> **Last Updated**: 2026-02-05
> **Server**: Azure VM (Plesk Ubuntu)

---

## Table of Contents

1. [Server Connection](#server-connection)
2. [Server Specifications](#server-specifications)
3. [⛔ DO NOT TOUCH - Existing Services](#-do-not-touch---existing-services)
4. [Architecture Overview](#architecture-overview)
5. [Domain Mapping](#domain-mapping)
6. [Directory Structure](#directory-structure)
7. [Docker Services](#docker-services)
8. [Database Configuration](#database-configuration)
9. [Redis Installation](#redis-installation)
10. [Reverse Proxy Configuration](#reverse-proxy-configuration)
11. [SSL Certificates](#ssl-certificates)
12. [Environment Variables](#environment-variables)
13. [Deployment Commands](#deployment-commands)
14. [Monitoring & Logs](#monitoring--logs)
15. [Troubleshooting](#troubleshooting)

---

## Server Connection

### SSH Access

```bash
# Primary connection
ssh -i C:\ssh-keys\project-nexus.pem azureuser@20.224.171.253

# Or with full path (Windows)
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253
```

### Connection Details

| Item | Value |
|------|-------|
| **Host** | `20.224.171.253` |
| **Hostname** | `reverent-northcutt.20-224-171-253.plesk.page` |
| **User** | `azureuser` |
| **Auth** | SSH Key (`project-nexus.pem`) |
| **SSH Key Path** | `C:\ssh-keys\project-nexus.pem` |
| **Sudo** | Yes (passwordless) |

### Plesk Panel Access

| Item | Value |
|------|-------|
| **URL** | `https://20.224.171.253:8443` |
| **Alt URL** | `https://reverent-northcutt.20-224-171-253.plesk.page:8443` |

---

## Server Specifications

| Component | Specification |
|-----------|---------------|
| **Cloud Provider** | Microsoft Azure |
| **OS** | Ubuntu 24.04.3 LTS (Noble Numbat) |
| **Kernel** | 6.14.0-1014-azure |
| **CPU** | AMD EPYC 7763 64-Core (4 vCPUs) |
| **RAM** | 16 GB |
| **Disk** | 247 GB SSD (218 GB available) |
| **Control Panel** | Plesk Obsidian 18.0.73.5 |

### Installed Software

| Software | Version | Status |
|----------|---------|--------|
| Docker | 29.2.1 | Active |
| Docker Compose | 5.0.2 | Available |
| Nginx | Latest (Plesk) | Active |
| Apache | Latest (Plesk) | Active |
| MariaDB | 10.11.13 | Active |
| PHP | 8.3.30, 8.4 | Available |
| Node.js | Via Docker | - |

---

## ⛔ DO NOT TOUCH - Existing Services

> **CRITICAL WARNING**: The following services belong to a **SEPARATE PLATFORM** (ASP.NET/.NET Core).
> **DO NOT modify, stop, restart, or interfere with these services under ANY circumstances!**

### Protected Domains (Another Platform)

| Domain | Status | Purpose |
|--------|--------|---------|
| `app.project-nexus.net` | ✅ Active | .NET Frontend (Alt) |
| `uk.project-nexus.net` | ✅ Active | UK Tenant |
| `api.uk.project-nexus.net` | ✅ Active | UK API |
| `ai.uk.project-nexus.net` | ✅ Active | UK AI Service |

### Protected Containers (NEVER TOUCH)

| Container | Image | Status | Ports | Purpose |
|-----------|-------|--------|-------|---------|
| `nexus-backend-api` | nexus-backend-api | ✅ Healthy | 5080:8080 | .NET Core API |
| `nexus-frontend-prod` | nexus-modern-frontend-frontend-prod | ✅ Running | 5171:3002 | Next.js Frontend |
| `nexus-backend-db` | postgres:16.4-bookworm | ✅ Healthy | 5432 (internal) | PostgreSQL DB |
| `nexus-backend-rabbitmq` | rabbitmq:3.13-management | ✅ Healthy | 5672, 15672 | Message Queue |
| `nexus-backend-llama` | ollama/ollama:latest | ⚠️ Unhealthy | 11434 | AI/LLM Service |
| `nexus-uk-frontend-dev` | (dev frontend) | ✅ Healthy | 5180:3001 | UK Dev Frontend |

### Protected Directories

```text
/opt/nexus-backend/           # ⛔ DO NOT TOUCH - .NET Backend
/opt/nexus-modern-frontend/   # ⛔ DO NOT TOUCH - Next.js Frontend
/opt/nexus-uk-frontend/       # ⛔ DO NOT TOUCH - UK Frontend
```

### Protected Docker Resources

```text
Networks:
  - nexus-backend-net         # ⛔ DO NOT TOUCH
  - nexus-modern-frontend_default
  - nexus-uk-frontend_default

Volumes:
  - nexus-backend-db-data     # ⛔ DO NOT TOUCH - PostgreSQL data
  - nexus-backend-rabbitmq-data
  - nexus-backend-llama-models
```

### Why This Matters

These services run a completely different platform built on:
- **ASP.NET Core 8** (not PHP)
- **PostgreSQL** (not MariaDB)
- **Next.js** (not Vite/React)
- **RabbitMQ** for messaging

**Any interference will cause downtime for production users!**

---

## Architecture Overview

```
                                    ┌─────────────────────────────────────────────┐
                                    │           Azure VM (20.224.171.253)         │
                                    │              Ubuntu 24.04 + Plesk           │
┌──────────────┐                    │                                             │
│   Internet   │                    │  ┌─────────────────────────────────────┐   │
│              │                    │  │            Nginx (Plesk)             │   │
│  Browser     │────── HTTPS ──────▶│  │         Port 443 (SSL/TLS)          │   │
│              │                    │  └──────────────┬──────────────────────┘   │
└──────────────┘                    │                 │                           │
                                    │    ┌────────────┴────────────┐              │
                                    │    ▼                         ▼              │
                                    │ ┌─────────────────┐  ┌─────────────────┐   │
                                    │ │ api.project-    │  │ app.project-    │   │
                                    │ │ nexus.ie        │  │ nexus.ie        │   │
                                    │ │                 │  │                 │   │
                                    │ │ Proxy to :8090  │  │ Proxy to :3000  │   │
                                    │ └────────┬────────┘  └────────┬────────┘   │
                                    │          │                    │             │
                                    │          ▼                    ▼             │
                                    │ ┌─────────────────────────────────────┐    │
                                    │ │           Docker Network            │    │
                                    │ │         (nexus-php-internal)        │    │
                                    │ │                                     │    │
                                    │ │  ┌───────────┐    ┌───────────┐    │    │
                                    │ │  │  PHP API  │    │  React    │    │    │
                                    │ │  │  :8090    │    │  Frontend │    │    │
                                    │ │  │           │    │  :3000    │    │    │
                                    │ │  └─────┬─────┘    └───────────┘    │    │
                                    │ │        │                           │    │
                                    │ │        ▼                           │    │
                                    │ │  ┌───────────┐    ┌───────────┐   │    │
                                    │ │  │  MariaDB  │    │   Redis   │   │    │
                                    │ │  │  :3306    │    │   :6379   │   │    │
                                    │ │  └───────────┘    └───────────┘   │    │
                                    │ └─────────────────────────────────────┘   │
                                    └─────────────────────────────────────────────┘
```

---

## Domain Mapping

### Production Domains

| Domain | Purpose | Container | Port |
|--------|---------|-----------|------|
| `api.project-nexus.ie` | PHP Backend API | nexus-php-app | 8090 |
| `app.project-nexus.ie` | React Frontend | nexus-react-prod | 3000 |

### DNS Configuration

Ensure these DNS records point to the server:

| Type | Name | Value | TTL |
|------|------|-------|-----|
| A | api.project-nexus.ie | 20.224.171.253 | 3600 |
| A | app.project-nexus.ie | 20.224.171.253 | 3600 |
| CNAME | www.api.project-nexus.ie | api.project-nexus.ie | 3600 |
| CNAME | www.app.project-nexus.ie | app.project-nexus.ie | 3600 |

---

## Directory Structure

### Deployment Directories

```
/opt/
├── nexus-php/                    # PHP Backend (NEW - to be created)
│   ├── compose.yml               # Docker Compose configuration
│   ├── compose.prod.yml          # Production overrides
│   ├── .env                      # Environment variables (secrets)
│   ├── Dockerfile                # PHP application Dockerfile
│   ├── httpdocs/                 # PHP source code
│   ├── src/                      # PHP classes (PSR-4)
│   ├── views/                    # PHP templates
│   ├── config/                   # Configuration files
│   └── react-frontend/           # React frontend source
│       ├── Dockerfile            # React production Dockerfile
│       ├── dist/                 # Built static files
│       └── ...
│
├── nexus-backend/                # Existing ASP.NET API (separate project)
├── nexus-modern-frontend/        # Existing Next.js frontend (separate project)
└── nexus-uk-frontend/            # UK tenant frontend

/var/www/vhosts/project-nexus.ie/
├── api.project-nexus.ie/         # Plesk document root (placeholder)
├── app.project-nexus.ie/         # Plesk document root (placeholder)
└── httpdocs/                     # Main site document root
```

### Plesk System Directories

```
/var/www/vhosts/system/
├── api.project-nexus.ie/
│   ├── conf/
│   │   ├── nginx.conf            # Auto-generated (DO NOT EDIT)
│   │   ├── httpd.conf            # Auto-generated (DO NOT EDIT)
│   │   ├── vhost.conf            # Custom Apache directives
│   │   ├── vhost_ssl.conf        # Custom Apache SSL directives
│   │   └── vhost_nginx.conf      # Custom Nginx directives (CREATE THIS)
│   └── logs/
│       ├── access_log
│       ├── access_ssl_log
│       └── error_log
│
└── app.project-nexus.ie/
    ├── conf/                     # Same structure as above
    └── logs/
```

---

## Docker Services

### PHP Stack Services

| Service | Container Name | Image | Port (Host:Container) |
|---------|---------------|-------|----------------------|
| PHP API | nexus-php-app | nexus-php-app:latest | 8090:80 |
| React Frontend | nexus-react-prod | nexus-react-prod:latest | 3000:80 |
| MariaDB | nexus-php-db | mariadb:10.11 | (internal only) |
| Redis | nexus-php-redis | redis:7-alpine | (internal only) |

### Production Docker Compose

**File**: `/opt/nexus-php/compose.yml`

```yaml
# =============================================================================
# Project NEXUS PHP Stack - Production Docker Compose
# =============================================================================
# Domains:
#   - api.project-nexus.ie  -> PHP API (port 8090)
#   - app.project-nexus.ie  -> React Frontend (port 3000)
# =============================================================================

name: nexus-php

services:
  # ---------------------------------------------------------------------------
  # PHP Application (Apache + PHP 8.2)
  # ---------------------------------------------------------------------------
  app:
    build:
      context: .
      dockerfile: Dockerfile
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
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=https://api.project-nexus.ie
      - DB_HOST=db
      - DB_PORT=3306
      - DB_NAME=${DB_NAME}
      - DB_USER=${DB_USER}
      - DB_PASS=${DB_PASS}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
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

  # ---------------------------------------------------------------------------
  # React Frontend (Nginx serving static build)
  # ---------------------------------------------------------------------------
  frontend:
    build:
      context: ./react-frontend
      dockerfile: Dockerfile.prod
      args:
        VITE_API_URL: https://api.project-nexus.ie
    container_name: nexus-react-prod
    restart: unless-stopped
    ports:
      - "3000:80"
    networks:
      - nexus-php-internal
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3

  # ---------------------------------------------------------------------------
  # MariaDB 10.11 Database
  # ---------------------------------------------------------------------------
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

  # ---------------------------------------------------------------------------
  # Redis 7 (Sessions & Cache)
  # ---------------------------------------------------------------------------
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

# =============================================================================
# Networks
# =============================================================================
networks:
  nexus-php-internal:
    name: nexus-php-internal
    driver: bridge

# =============================================================================
# Volumes
# =============================================================================
volumes:
  nexus-php-db-data:
    name: nexus-php-db-data
  nexus-php-redis-data:
    name: nexus-php-redis-data
  nexus-php-uploads:
    name: nexus-php-uploads
```

### Docker Commands Reference

```bash
# Navigate to project directory
cd /opt/nexus-php

# Start all services
sudo docker compose up -d

# Stop all services
sudo docker compose down

# View logs
sudo docker compose logs -f
sudo docker compose logs -f app        # PHP only
sudo docker compose logs -f frontend   # React only

# Restart a specific service
sudo docker compose restart app

# Rebuild and restart
sudo docker compose up -d --build

# Check status
sudo docker compose ps

# Execute command in container
sudo docker exec -it nexus-php-app bash
sudo docker exec -it nexus-php-db mysql -u nexus -p

# View resource usage
sudo docker stats
```

---

## Database Configuration

### Production Database (Docker MariaDB)

| Setting | Value |
|---------|-------|
| **Host** | `db` (Docker service name) |
| **External Host** | Not exposed (internal only) |
| **Port** | 3306 |
| **Database** | `nexus` |
| **User** | `nexus` |
| **Character Set** | utf8mb4 |
| **Collation** | utf8mb4_unicode_ci |

### Accessing Database

```bash
# From host (via Docker)
sudo docker exec -it nexus-php-db mysql -u nexus -p nexus

# Backup database
sudo docker exec nexus-php-db mysqldump -u nexus -p nexus > backup_$(date +%Y%m%d).sql

# Restore database
sudo docker exec -i nexus-php-db mysql -u nexus -p nexus < backup.sql

# Import from existing database
cat existing_backup.sql | sudo docker exec -i nexus-php-db mysql -u nexus -p nexus
```

### Alternative: Use System MariaDB

If you prefer to use the existing Plesk-managed MariaDB:

| Setting | Value |
|---------|-------|
| **Host** | `172.17.0.1` (Docker host) or socket |
| **Port** | 3306 |
| **Connection** | Requires firewall/network config |

---

## Redis Installation

> **IMPORTANT**: Redis is required for PHP session management and caching.
> Currently NOT installed on the server - must be set up before deployment.

### Current Status

| Item | Status |
|------|--------|
| System Redis | ❌ Not installed |
| Docker Redis | ❌ Not running |
| PHP Redis Extension | ✅ Available (php83-redis, php84-redis) |

### Option 1: Docker Redis (Recommended)

Redis is included in the `compose.prod.yml` and will be automatically started with the PHP stack.

```yaml
# Already included in compose.prod.yml
redis:
  image: redis:7-alpine
  container_name: nexus-php-redis
  restart: unless-stopped
  command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru
  volumes:
    - nexus-php-redis-data:/data
  networks:
    - nexus-php-internal
```

**Connection from PHP:**

```php
// In .env
REDIS_HOST=redis
REDIS_PORT=6379

// In PHP code
$redis = new Redis();
$redis->connect('redis', 6379);
```

### Option 2: System Redis (Alternative)

If you prefer system-wide Redis installation:

```bash
# Install Redis
sudo apt update
sudo apt install redis-server -y

# Configure Redis
sudo nano /etc/redis/redis.conf
# Set: bind 127.0.0.1 ::1
# Set: maxmemory 256mb
# Set: maxmemory-policy allkeys-lru

# Start and enable
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verify
redis-cli ping
# Should return: PONG
```

**Connection from Docker to System Redis:**

```php
// In .env (use Docker host IP)
REDIS_HOST=172.17.0.1
REDIS_PORT=6379
```

### Redis Usage in PHP Stack

Redis is used for:

1. **Session Storage** - Distributed sessions across containers
2. **Cache** - Query results, API responses, computed data
3. **Rate Limiting** - API rate limit counters
4. **Queue** - Background job processing (optional)

### Verify Redis Connection

```bash
# From inside PHP container
sudo docker exec nexus-php-app php -r "
\$redis = new Redis();
\$redis->connect('redis', 6379);
echo \$redis->ping() ? 'Redis OK' : 'Redis FAILED';
"
```

---

## Reverse Proxy Configuration

### Nginx Configuration for api.project-nexus.ie

**File**: `/var/www/vhosts/system/api.project-nexus.ie/conf/vhost_nginx.conf`

```nginx
# =============================================================================
# Project NEXUS - PHP API Reverse Proxy
# Domain: api.project-nexus.ie
# Proxies to: Docker container on port 8090
# =============================================================================

# Proxy all requests to Docker PHP container
location / {
    proxy_pass http://127.0.0.1:8090;

    # Headers
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Port $server_port;

    # Timeouts
    proxy_connect_timeout 60s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;

    # Buffering
    proxy_buffering on;
    proxy_buffer_size 4k;
    proxy_buffers 8 4k;

    # WebSocket support (if needed)
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}

# Health check endpoint (bypass proxy for monitoring)
location = /nginx-health {
    access_log off;
    return 200 "healthy\n";
    add_header Content-Type text/plain;
}
```

### Nginx Configuration for app.project-nexus.ie

**File**: `/var/www/vhosts/system/app.project-nexus.ie/conf/vhost_nginx.conf`

```nginx
# =============================================================================
# Project NEXUS - React Frontend Reverse Proxy
# Domain: app.project-nexus.ie
# Proxies to: Docker container on port 3000
# =============================================================================

# Proxy all requests to Docker React container
location / {
    proxy_pass http://127.0.0.1:3000;

    # Headers
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Timeouts
    proxy_connect_timeout 30s;
    proxy_send_timeout 30s;
    proxy_read_timeout 30s;

    # Caching for static assets
    proxy_cache_valid 200 1d;
    proxy_cache_valid 404 1m;
}

# Health check endpoint
location = /nginx-health {
    access_log off;
    return 200 "healthy\n";
    add_header Content-Type text/plain;
}
```

### Apply Nginx Configuration

```bash
# Test nginx configuration
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx

# Or via Plesk
sudo plesk sbin httpdmng --reconfigure-domain api.project-nexus.ie
sudo plesk sbin httpdmng --reconfigure-domain app.project-nexus.ie
```

---

## SSL Certificates

### Current SSL Setup

SSL is managed by Plesk using Let's Encrypt or Plesk SSL.

| Domain | Certificate Location |
|--------|---------------------|
| api.project-nexus.ie | `/opt/psa/var/certificates/scfq8daj23nhp1a54Re9rS` |
| app.project-nexus.ie | `/opt/psa/var/certificates/scfq8daj23nhp1a54Re9rS` |

### Issue/Renew SSL via Plesk CLI

```bash
# Issue Let's Encrypt certificate
sudo plesk bin extension --call sslit --exec certificate -issue \
  -domain api.project-nexus.ie \
  -registrationEmail admin@project-nexus.ie \
  -secure-domain -secure-www

sudo plesk bin extension --call sslit --exec certificate -issue \
  -domain app.project-nexus.ie \
  -registrationEmail admin@project-nexus.ie \
  -secure-domain -secure-www
```

---

## Environment Variables

### Production Environment File

**File**: `/opt/nexus-php/.env`

```bash
# =============================================================================
# Project NEXUS - Production Environment Variables
# =============================================================================
# IMPORTANT: This file contains secrets. Never commit to git!
# =============================================================================

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.project-nexus.ie
APP_KEY=<generate-32-char-random-string>

# JWT Authentication
JWT_SECRET=<generate-64-char-random-string>

# Database (Docker MariaDB)
DB_TYPE=mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=nexus
DB_USER=nexus
DB_PASS=<strong-password-here>
DB_ROOT_PASSWORD=<strong-root-password-here>

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Pusher (Real-time notifications)
PUSHER_APP_ID=<your-pusher-app-id>
PUSHER_APP_KEY=<your-pusher-key>
PUSHER_APP_SECRET=<your-pusher-secret>
PUSHER_APP_CLUSTER=eu

# OpenAI (AI features)
OPENAI_API_KEY=<your-openai-key>

# Gmail API (Email)
USE_GMAIL_API=true
GMAIL_CLIENT_ID=<your-gmail-client-id>
GMAIL_CLIENT_SECRET=<your-gmail-secret>
GMAIL_REFRESH_TOKEN=<your-gmail-refresh-token>
```

### Generate Secure Keys

```bash
# Generate APP_KEY (32 characters)
openssl rand -base64 32

# Generate JWT_SECRET (64 characters)
openssl rand -base64 64

# Generate DB passwords
openssl rand -base64 24
```

---

## Deployment Commands

### Initial Deployment

```bash
# 1. Connect to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# 2. Create deployment directory
sudo mkdir -p /opt/nexus-php
sudo chown azureuser:azureuser /opt/nexus-php

# 3. Exit and upload files from local machine (run on Windows)
# Option A: SCP
scp -i "C:\ssh-keys\project-nexus.pem" -r ./httpdocs ./src ./views ./config ./Dockerfile ./compose.yml azureuser@20.224.171.253:/opt/nexus-php/

# Option B: rsync (better for large codebases)
rsync -avz -e "ssh -i C:\ssh-keys\project-nexus.pem" \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude '.git' \
  ./ azureuser@20.224.171.253:/opt/nexus-php/

# 4. Back on server - create .env file
cd /opt/nexus-php
nano .env  # Add production variables

# 5. Install PHP dependencies
sudo docker run --rm -v $(pwd):/app composer:2 install --no-dev --optimize-autoloader

# 6. Build and start containers
sudo docker compose up -d --build

# 7. Configure Plesk nginx
sudo nano /var/www/vhosts/system/api.project-nexus.ie/conf/vhost_nginx.conf
sudo nano /var/www/vhosts/system/app.project-nexus.ie/conf/vhost_nginx.conf

# 8. Reload nginx
sudo nginx -t && sudo systemctl reload nginx

# 9. Run database migrations (if needed)
sudo docker exec nexus-php-app php /var/www/html/scripts/safe_migrate.php
```

### Update Deployment

```bash
# On local machine - sync changes
rsync -avz -e "ssh -i C:\ssh-keys\project-nexus.pem" \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude '.git' \
  --exclude '.env' \
  --exclude 'uploads' \
  ./ azureuser@20.224.171.253:/opt/nexus-php/

# On server - rebuild if Dockerfile changed
cd /opt/nexus-php
sudo docker compose up -d --build

# Or just restart if only code changed
sudo docker compose restart app
```

### Rollback

```bash
# Keep previous versions tagged
sudo docker tag nexus-php-app:latest nexus-php-app:backup-$(date +%Y%m%d)

# Rollback to previous version
sudo docker tag nexus-php-app:backup-20260205 nexus-php-app:latest
sudo docker compose up -d
```

---

## Monitoring & Logs

### View Logs

```bash
# Docker container logs
sudo docker compose logs -f app        # PHP API logs
sudo docker compose logs -f frontend   # React frontend logs
sudo docker compose logs -f db         # Database logs

# Nginx access/error logs (Plesk)
sudo tail -f /var/www/vhosts/system/api.project-nexus.ie/logs/access_ssl_log
sudo tail -f /var/www/vhosts/system/api.project-nexus.ie/logs/error_log

# PHP error log (inside container)
sudo docker exec nexus-php-app tail -f /var/log/php_errors.log

# System logs
sudo journalctl -u docker -f
```

### Health Checks

```bash
# Check Docker container health
sudo docker ps --format "table {{.Names}}\t{{.Status}}"

# Test API endpoint
curl -I https://api.project-nexus.ie/health.php
curl https://api.project-nexus.ie/api/v2/tenant/bootstrap

# Test frontend
curl -I https://app.project-nexus.ie/

# Check from inside containers
sudo docker exec nexus-php-app curl -s http://localhost/health.php
```

### Resource Monitoring

```bash
# Docker resource usage
sudo docker stats --no-stream

# System resources
htop
df -h
free -h
```

---

## Troubleshooting

### Common Issues

#### 1. Container Won't Start

```bash
# Check logs for errors
sudo docker compose logs app

# Check if port is in use
sudo ss -tlnp | grep :8090

# Rebuild from scratch
sudo docker compose down
sudo docker compose build --no-cache
sudo docker compose up -d
```

#### 2. 502 Bad Gateway

```bash
# Check if container is running
sudo docker ps | grep nexus-php

# Check container health
sudo docker inspect nexus-php-app --format='{{.State.Health.Status}}'

# Test local connection
curl http://127.0.0.1:8090/health.php

# Check nginx config
sudo nginx -t
```

#### 3. Database Connection Failed

```bash
# Check database container
sudo docker compose logs db

# Test connection from app container
sudo docker exec nexus-php-app php -r "new PDO('mysql:host=db;dbname=nexus', 'nexus', 'password');"

# Check environment variables
sudo docker exec nexus-php-app env | grep DB_
```

#### 4. Permission Issues

```bash
# Fix upload directory permissions
sudo docker exec nexus-php-app chown -R www-data:www-data /var/www/html/httpdocs/uploads

# Fix volume permissions
sudo chown -R 33:33 /var/lib/docker/volumes/nexus-php-uploads/_data
```

#### 5. SSL Certificate Issues

```bash
# Check certificate
sudo openssl s_client -connect api.project-nexus.ie:443 -servername api.project-nexus.ie

# Renew via Plesk
sudo plesk bin extension --call sslit --exec certificate -issue \
  -domain api.project-nexus.ie \
  -registrationEmail admin@project-nexus.ie \
  -secure-domain -continue
```

### Useful Diagnostic Commands

```bash
# Full system status
sudo docker compose ps
sudo docker system df
sudo nginx -t
sudo systemctl status nginx docker

# Network debugging
sudo docker network inspect nexus-php-internal
sudo docker exec nexus-php-app cat /etc/hosts

# Process inside container
sudo docker exec nexus-php-app ps aux
sudo docker exec nexus-php-app apache2ctl -S
```

---

## Quick Reference Card

### SSH Connection
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253
```

### Start/Stop Services
```bash
cd /opt/nexus-php
sudo docker compose up -d      # Start
sudo docker compose down       # Stop
sudo docker compose restart    # Restart
```

### View Logs
```bash
sudo docker compose logs -f app
```

### Deploy Update
```bash
# From local Windows machine
rsync -avz -e "ssh -i C:\ssh-keys\project-nexus.pem" \
  --exclude 'node_modules' --exclude 'vendor' --exclude '.git' --exclude '.env' \
  ./ azureuser@20.224.171.253:/opt/nexus-php/

# On server
cd /opt/nexus-php && sudo docker compose restart app
```

### URLs
- **API**: https://api.project-nexus.ie
- **Frontend**: https://app.project-nexus.ie
- **Plesk**: https://20.224.171.253:8443

---

## Appendix: Port Allocation Summary

### Ports Used by PHP Stack (This Project)

| Port | Service | Domain |
|------|---------|--------|
| 8090 | PHP API (Apache) | api.project-nexus.ie |
| 3000 | React Frontend (Nginx) | app.project-nexus.ie |
| (internal) | MariaDB 10.11 | - |
| (internal) | Redis 7 | - |

### Ports Used by .NET Stack (DO NOT USE)

| Port | Service | Owner |
|------|---------|-------|
| 5080 | .NET API | ⛔ nexus-backend-api |
| 5171 | Next.js Frontend | ⛔ nexus-frontend-prod |
| 5180 | UK Frontend Dev | ⛔ nexus-uk-frontend-dev |
| 5432 | PostgreSQL | ⛔ nexus-backend-db |
| 5672, 15672 | RabbitMQ | ⛔ nexus-backend-rabbitmq |
| 11434 | Ollama AI | ⛔ nexus-backend-llama |

### Available Ports (Safe to Use)

If you need additional services, these ports are available:

- 3001-3999 (except 3002)
- 8080-8089
- 8091-8099
- 9000-9999

---

## Appendix: Summary Checklist

### Pre-Deployment Checklist

- [ ] SSH key available at `C:\ssh-keys\project-nexus.pem`
- [ ] `.env` file prepared with production secrets
- [ ] Database backup from existing production (if migrating data)
- [ ] DNS records pointing to 20.224.171.253

### Deployment Checklist

- [ ] Create `/opt/nexus-php` directory
- [ ] Upload code files
- [ ] Create `.env` file on server
- [ ] Install composer dependencies
- [ ] Build and start Docker containers
- [ ] Configure Nginx reverse proxy for api.project-nexus.ie
- [ ] Configure Nginx reverse proxy for app.project-nexus.ie
- [ ] Reload Nginx
- [ ] Run database migrations
- [ ] Verify health checks pass

### Post-Deployment Verification

- [ ] `https://api.project-nexus.ie/health.php` returns healthy
- [ ] `https://app.project-nexus.ie/` loads React app
- [ ] Database connection works
- [ ] Redis connection works
- [ ] User login works
- [ ] SSL certificates valid

---

**Document maintained by**: Project NEXUS Team
**Last reviewed**: 2026-02-05
