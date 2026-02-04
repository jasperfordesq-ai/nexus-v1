# Local Development Setup

## TL;DR - Use Docker Only

```bash
# Start everything
docker compose up -d

# Access points
# React Frontend: http://localhost:5173
# PHP API:        http://localhost:8090
# phpMyAdmin:     http://localhost:8091 (run with --profile tools)
```

**Stop XAMPP** - it's not needed and causes confusion.

---

## Why Docker Instead of XAMPP?

| Aspect | XAMPP | Docker |
|--------|-------|--------|
| Database state | May be out of sync | Single source of truth |
| Migrations | Manual | Applied on container start |
| Environment | Varies by machine | Identical everywhere |
| Port conflicts | Common (80, 3306) | Isolated (8090, internal) |
| Redis | Not included | Included |
| React frontend | Separate setup | Included in compose |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Docker Compose Stack                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │    React     │    │   PHP App    │    │   MariaDB    │  │
│  │   Frontend   │───▶│   (Apache)   │───▶│   Database   │  │
│  │  Port 5173   │    │  Port 8090   │    │   Internal   │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│                             │                               │
│                             ▼                               │
│                      ┌──────────────┐                       │
│                      │    Redis     │                       │
│                      │   Internal   │                       │
│                      └──────────────┘                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Quick Start

### 1. Stop XAMPP (Windows)

Open XAMPP Control Panel and stop:
- Apache
- MySQL

Or from command line:
```cmd
C:\xampp\xampp_stop.exe
```

Or kill processes directly:
```cmd
taskkill /F /IM httpd.exe
taskkill /F /IM mysqld.exe
```

### 2. Start Docker Stack

```bash
cd c:\xampp\htdocs\staging
docker compose up -d
```

### 3. Verify Everything Works

```bash
# Check containers are running
docker compose ps

# Check PHP health
curl http://localhost:8090/health.php

# Check API
curl http://localhost:8090/api/v2/tenant/bootstrap
```

### 4. Access the Application

| Service | URL | Purpose |
|---------|-----|---------|
| React Frontend | http://localhost:5173 | New SPA frontend |
| PHP API | http://localhost:8090 | Backend API |
| Legacy PHP Views | http://localhost:8090/{tenant}/ | Traditional PHP pages |
| phpMyAdmin | http://localhost:8091 | Database admin (needs `--profile tools`) |

## Common Commands

```bash
# Start stack
docker compose up -d

# Start with phpMyAdmin
docker compose --profile tools up -d

# View logs
docker compose logs -f app
docker compose logs -f frontend

# Restart PHP app (after code changes that need Apache restart)
docker compose restart app

# Rebuild after Dockerfile changes
docker compose up -d --build

# Stop everything
docker compose down

# Stop and remove volumes (DELETES DATABASE)
docker compose down -v
```

## Database Access

### Via phpMyAdmin
```bash
docker compose --profile tools up -d
# Then visit http://localhost:8091
```

### Via CLI
```bash
docker exec -it nexus-mysql-db mysql -unexus -pnexus_secret nexus
```

### Connection Details (for external tools)
The database is NOT exposed to the host by default. To expose it, uncomment in `compose.yml`:
```yaml
ports:
  - "3307:3306"
```

Then connect to `localhost:3307` with:
- User: `nexus`
- Password: `nexus_secret`
- Database: `nexus`

## Environment Variables

Docker uses `.env.docker`, NOT `.env`:

| File | Purpose |
|------|---------|
| `.env` | Legacy XAMPP config (not used with Docker) |
| `.env.docker` | Docker environment variables |

The `compose.yml` hardcodes database credentials to prevent `.env` from accidentally overriding Docker settings.

## Troubleshooting

### Port 8090 already in use
```bash
# Find what's using it
netstat -ano | findstr :8090

# Kill by PID
taskkill /F /PID <pid>
```

### Database connection refused
```bash
# Check if db container is healthy
docker compose ps

# Check db logs
docker compose logs db
```

### PHP changes not reflecting
The code is mounted as a volume, so changes should be immediate. If not:
```bash
docker compose restart app
```

### React changes not reflecting
Vite has hot reload. If broken:
```bash
docker compose restart frontend
```

### Need to run migrations
Migrations are SQL files in `/migrations`. Apply them:
```bash
docker exec -it nexus-mysql-db mysql -unexus -pnexus_secret nexus < migrations/filename.sql
```

## File Locations

| What | Location |
|------|----------|
| Docker config | `compose.yml` |
| PHP Dockerfile | `Dockerfile` |
| React Dockerfile | `react-frontend/Dockerfile` |
| Docker env | `.env.docker` |
| PHP source | Mounted from host (`./` → `/var/www/html`) |
| React source | Mounted from host (`./react-frontend` → `/app`) |
| Uploads | Docker volume `nexus-uploads` |
| Database | Docker volume `nexus-mysql-data` |

## Backup & Restore

```bash
# Backup database
./scripts/docker-backup.sh
# or on Windows
./scripts/docker-backup.bat

# Restore database
./scripts/docker-restore.sh backups/nexus-backup-YYYYMMDD.sql
```
