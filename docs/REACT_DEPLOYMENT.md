# React Frontend Deployment Guide

## Critical Warning

**NEVER build the React frontend locally and upload the `dist/` folder to production.**

Local builds use default environment variables which break production functionality:
- Local: `VITE_API_BASE=/api` (wrong)
- Production needs: `VITE_API_BASE=https://api.project-nexus.ie/api`

This causes the **community selector dropdown** on login/register pages to disappear because API calls go to the wrong URL (`/v2/tenants` instead of `/api/v2/tenants`).

---

## Correct Deployment Process

### Step 1: Upload Source Files to Server

Upload changed source files to the server:

```bash
# Single file
scp -i "C:/ssh-keys/project-nexus.pem" \
  $PROJECT_ROOT/react-frontend/src/components/layout/MobileDrawer.tsx \
  azureuser@20.224.171.253:/opt/nexus-php/react-frontend/src/components/layout/

# Multiple files (example)
scp -i "C:/ssh-keys/project-nexus.pem" \
  $PROJECT_ROOT/react-frontend/src/pages/auth/*.tsx \
  azureuser@20.224.171.253:/opt/nexus-php/react-frontend/src/pages/auth/

# Entire directory
scp -i "C:/ssh-keys/project-nexus.pem" -r \
  $PROJECT_ROOT/react-frontend/src/ \
  azureuser@20.224.171.253:/opt/nexus-php/react-frontend/
```

### Step 2: Rebuild Docker Image on Server

SSH into the server and rebuild with the correct build args:

```bash
ssh -i "C:/ssh-keys/project-nexus.pem" azureuser@20.224.171.253

cd /opt/nexus-php/react-frontend

# IMPORTANT: Use --no-cache to ensure fresh build
sudo docker build --no-cache \
  -f Dockerfile.prod \
  --build-arg VITE_API_BASE=https://api.project-nexus.ie/api \
  -t nexus-react-prod:latest .
```

### Step 3: Restart Container

```bash
sudo docker stop nexus-react-prod
sudo docker rm nexus-react-prod
sudo docker run -d \
  --name nexus-react-prod \
  --restart unless-stopped \
  -p 3000:80 \
  --network nexus-php-internal \
  nexus-react-prod:latest
```

### Step 4: Verify Deployment

```bash
# Check container is running
sudo docker ps | grep nexus-react-prod

# Check health
curl -s http://127.0.0.1:3000/health

# Verify API base URL in bundle (from local machine)
curl -s "https://app.project-nexus.ie/" | grep -oE 'index-[^.]+\.js'
# Then check the bundle contains correct API URL:
curl -s "https://app.project-nexus.ie/assets/index-XXXXX.js" | grep -o '"https://api.project-nexus.ie/api"'
```

---

## One-Liner Deployment (After Uploading Files)

```bash
ssh -i "C:/ssh-keys/project-nexus.pem" azureuser@20.224.171.253 "\
  cd /opt/nexus-php/react-frontend && \
  sudo docker build --no-cache -f Dockerfile.prod \
    --build-arg VITE_API_BASE=https://api.project-nexus.ie/api \
    -t nexus-react-prod:latest . && \
  sudo docker stop nexus-react-prod && \
  sudo docker rm nexus-react-prod && \
  sudo docker run -d --name nexus-react-prod --restart unless-stopped \
    -p 3000:80 --network nexus-php-internal nexus-react-prod:latest"
```

---

## Environment Variables

The React frontend uses these environment variables (set via Docker build args):

| Variable | Production Value | Purpose |
|----------|------------------|---------|
| `VITE_API_BASE` | `https://api.project-nexus.ie/api` | API endpoint base URL |
| `VITE_PUSHER_KEY` | `f7af200cb94bb29afbd3` | Pusher app key (optional, has default) |
| `VITE_PUSHER_CLUSTER` | `eu` | Pusher cluster (optional, has default) |

These are baked into the build at compile time (Vite replaces `import.meta.env.VITE_*`).

---

## Troubleshooting

### Community Selector Not Showing

1. Check API base URL in bundle:
   ```bash
   curl -s "https://app.project-nexus.ie/" | grep -oE 'index-[^.]+\.js'
   curl -s "https://app.project-nexus.ie/assets/index-XXX.js" | grep -o '"https://api.project-nexus.ie[^"]*"'
   ```
   Should return: `"https://api.project-nexus.ie/api"`

2. Test tenants API directly:
   ```bash
   curl -s "https://api.project-nexus.ie/api/v2/tenants"
   ```
   Should return JSON with tenant list.

3. If wrong, rebuild with `--no-cache` flag.

### Container Won't Start

Check logs:
```bash
sudo docker logs nexus-react-prod
```

Check if port 3000 is in use:
```bash
sudo lsof -i :3000
```

### Network Issues

Ensure container is on correct network:
```bash
sudo docker network inspect nexus-php-internal
```

---

## File Locations

| Item | Path |
|------|------|
| Production Dockerfile | `react-frontend/Dockerfile.prod` |
| Nginx config | `react-frontend/nginx.conf` |
| Source code | `react-frontend/src/` |
| Build output | `react-frontend/dist/` (local only) |
| Server path | `/opt/nexus-php/react-frontend/` |

---

## Related Documentation

- [DEPLOYMENT.md](DEPLOYMENT.md) - Full deployment guide
- [new-production-server.md](new-production-server.md) - Azure server setup
- [CLAUDE.md](../CLAUDE.md) - Project conventions
