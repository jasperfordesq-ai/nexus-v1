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

---

## Why Docker?

| Aspect | Docker Advantage |
|--------|------------------|
| Database state | Single source of truth |
| Migrations | Applied on container start automatically |
| Environment | Identical across all machines |
| Port conflicts | Isolated (8090, internal) |
| Redis | Included and configured |
| React frontend | Included in compose |

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

### 1. Start Docker Stack

```bash
# Navigate to project root
cd /path/to/project
docker compose up -d
```

### 2. Verify Everything Works

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
| React Frontend | http://localhost:5173 | Primary SPA frontend |
| PHP API | http://localhost:8090 | Backend API |
| Legacy PHP Views | http://localhost:8090/{tenant}/ | Traditional PHP pages (reference only) |
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
docker exec -it nexus-php-db mysql -unexus -pnexus_secret nexus
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

Docker uses `.env.docker`:

| File | Purpose |
|------|---------|
| `.env.docker` | Docker environment variables |

The `compose.yml` hardcodes database credentials for consistent Docker operation.

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
docker exec -it nexus-php-db mysql -unexus -pnexus_secret nexus < migrations/filename.sql
```

## Container Names (THIS PROJECT ONLY)

> **This project is `project-nexus.ie` (PHP + React).** The `.NET backend` (`asp.net-backend/`) is a **separate project** with its own containers. Never deploy to or modify `.NET` containers from this project.

Local and production container names are **identical** — no confusion between environments:

| Service | Container Name | Local Port | Prod Port |
|---------|---------------|------------|-----------|
| PHP App (Apache) | `nexus-php-app` | 8090 | 8090 |
| MariaDB | `nexus-php-db` | Internal | Internal |
| Redis | `nexus-php-redis` | Internal | Internal |
| React Frontend | `nexus-react-prod` | 5173 | 3000 |
| Sales Site | `nexus-sales-site` | 3001 | 3001 |
| phpMyAdmin | `nexus-phpmyadmin` | 8091 | N/A |

### Containers that are NOT this project (DO NOT TOUCH)

| Container | Project | Purpose |
|-----------|---------|---------|
| `nexus-backend-api` | asp.net-backend | .NET Core API |
| `nexus-backend-db` | asp.net-backend | PostgreSQL |
| `nexus-backend-rabbitmq` | asp.net-backend | RabbitMQ |
| `nexus-backend-llama` | asp.net-backend | Ollama AI |
| `nexus-civic-app` | nexus-civic | Node.js app |
| `nexus-civic-db` | nexus-civic | PostgreSQL |
| `nexus-frontend-dev` | nexus-modern-frontend | Next.js |
| `nexus-uk-frontend-dev` | nexus-uk-frontend | Next.js |

## Docker Data Storage

Docker Desktop stores its data on the D: drive:

| What | Path |
|------|------|
| WSL distro dir | `D:\DockerDesktopWSL\` |
| Data disk (vhdx) | `D:\DockerDesktopWSL\disk\docker_data.vhdx` |
| Docker Desktop settings | `%APPDATA%\Docker\settings-store.json` |

Settings in `settings-store.json` that control this:
- `CustomWslDistroDir`: `D:\DockerDesktopWSL`
- `DiskPath`: Points to the data vhdx location

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

### Database Backup (Local)
```bash
# Dump local database
docker exec nexus-php-db mysqldump -unexus -pnexus_secret nexus > backup.sql

# Restore local database
docker exec -i nexus-php-db mysql -unexus -pnexus_secret nexus < backup.sql
```

### Restore from Production
```bash
# Dump production database
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 \
  "sudo docker exec nexus-php-db mysqldump -unexus -p'<password>' --single-transaction --routines --triggers nexus" \
  > prod_dump.sql

# Import into local
docker exec -i nexus-php-db mysql -unexus -pnexus_secret nexus < prod_dump.sql
```

> **Note**: Get the production DB password from: `sudo docker exec nexus-php-db env | grep MARIADB_PASSWORD`
