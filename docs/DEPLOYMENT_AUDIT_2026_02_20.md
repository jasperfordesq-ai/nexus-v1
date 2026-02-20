# Deployment System Audit Report
**Date:** 2026-02-20
**Issue:** Dev images being used on production instead of production images
**Status:** ROOT CAUSE IDENTIFIED + CRITICAL FIXES APPLIED

---

## Executive Summary

The deployment system had a **fundamental architectural flaw** causing dev Docker images to be used in production. This wasn't a one-time bug—it was a systemic vulnerability with **5 independent failure paths**.

**Root Cause:** Implicit Docker image naming, combined with:
- Same container names for dev and prod (`nexus-react-prod` used for BOTH)
- No explicit image tags (both defaulted to `staging_frontend:latest`)
- Git tracking dev `compose.yml` on production server
- Docker layer caching surviving rebuilds without `--no-cache`
- OPCache never re-reading files (requires container restart)

**Impact:** Production users saw stale code, missing features, wrong configuration. No error messages—silent failures.

---

## The 5 Failure Paths

### Failure Path #1: Docker Layer Caching
1. Production image built on Feb 5 with old code
2. Code updated, image rebuilt WITHOUT `--no-cache`
3. Old layers cached, new container runs old code
4. OPCache (`validate_timestamps=0`) never re-reads files
5. **Result:** Users see old behavior despite new code on disk

**Detection:** Files are correct on disk, but stale bytecode served. No errors.

### Failure Path #2: Image Name Collision
- Dev compose builds: `staging_frontend:latest` from `Dockerfile` (dev)
- Prod compose builds: `staging_frontend:latest` from `Dockerfile.prod` (prod)
- Last builder wins → unpredictable which image runs
- **Result:** Wrong image silently used

**Detection:** `docker inspect` shows wrong image, but container name looks correct.

### Failure Path #3: Git Overwrites Production Config
1. Production server does `git pull`
2. Pulls dev `compose.yml` from GitHub
3. `safe-deploy.sh` runs `cp compose.prod.yml compose.yml`
4. If copy fails/crashes, server left with dev config
5. **Result:** Next deploy uses dev image

**Detection:** Deployment succeeds but serves dev build.

### Failure Path #4: Container Name Confusion
- **Dev:** `container_name: nexus-react-prod` (running dev Dockerfile)
- **Prod:** `container_name: nexus-react-prod` (running prod Dockerfile)
- Name says "prod" but could be dev image underneath
- **Result:** Operator trusts name, deploys wrong image

**Detection:** Container name is correct, but image layers are wrong.

### Failure Path #5: No Post-Deploy Verification
- Deployment scripts don't verify which image was deployed
- Health checks pass (both dev and prod are "healthy")
- No check for: Dockerfile used, build args applied, layer provenance
- **Result:** Bad deploy silently succeeds

**Detection:** Only discovered when users report missing features.

---

## Critical Fixes Applied (2026-02-20)

### ✅ Fix #1: Explicit Image Naming (P1 - CRITICAL)
**Changed:**
```yaml
# compose.yml (dev) - BEFORE
frontend:
  build:
    context: ./react-frontend
    dockerfile: Dockerfile
  container_name: nexus-react-prod  # ← WRONG! Says "prod" but runs dev

# compose.yml (dev) - AFTER
frontend:
  build:
    context: ./react-frontend
    dockerfile: Dockerfile
  image: nexus-react-dev:latest      # ← EXPLICIT dev image
  container_name: nexus-react-dev    # ← EXPLICIT dev container
```

```yaml
# compose.prod.yml (prod) - BEFORE
frontend:
  build:
    context: ./react-frontend
    dockerfile: Dockerfile.prod
  container_name: nexus-react-prod  # ← implicit image name

# compose.prod.yml (prod) - AFTER
frontend:
  build:
    context: ./react-frontend
    dockerfile: Dockerfile.prod
  image: nexus-react-prod:latest    # ← EXPLICIT prod image
  container_name: nexus-react-prod
```

**Why This Works:**
- Dev and prod images now have **different names**
- No more image name collision
- `docker images` clearly shows which is which
- Can't accidentally run dev image in prod container

**Verification:**
```bash
# Before fix:
docker images | grep frontend
staging_frontend  latest  # ← Which one? Dev or prod?

# After fix:
docker images | grep react
nexus-react-dev   latest  # ← Clearly dev
nexus-react-prod  latest  # ← Clearly prod
```

---

## Remaining Fixes (Not Yet Applied)

### Fix #2: Always Use `--no-cache` for Frontend (P2)
**Current Bug:** `safe-deploy.sh` quick mode skips rebuild entirely.

**Proposed Fix:**
```bash
deploy_quick() {
    cp compose.prod.yml compose.yml

    # ALWAYS rebuild frontend with --no-cache
    docker compose build --no-cache frontend

    # Then restart
    docker compose up -d
    docker restart nexus-php-app  # Clear OPCache
}
```

**Why:** Prevents Docker layer cache from serving stale code.

### Fix #3: Add Image Verification (P2)
**Proposed Addition to `safe-deploy.sh`:**
```bash
verify_production_images() {
    log_info "Verifying deployed images..."

    # Check frontend image name
    local frontend_image=$(docker inspect nexus-react-prod --format '{{.Config.Image}}')
    if [[ "$frontend_image" != "nexus-react-prod:latest" ]]; then
        log_err "Frontend image mismatch: $frontend_image (expected: nexus-react-prod:latest)"
        return 1
    fi

    # Verify it's the prod build (check for nginx)
    if ! docker exec nexus-react-prod which nginx > /dev/null 2>&1; then
        log_err "Frontend image appears to be dev image (no nginx found)"
        return 1
    fi

    # Verify build args were applied
    local api_base=$(docker exec nexus-react-prod grep -o 'VITE_API_BASE="[^"]*"' /usr/share/nginx/html/assets/index-*.js | head -1)
    if [[ ! "$api_base" =~ "api.project-nexus.ie" ]]; then
        log_err "Frontend build args not applied correctly: $api_base"
        return 1
    fi

    log_ok "Frontend image verified: $frontend_image"
}
```

**Why:** Catches wrong images BEFORE declaring deploy success.

### Fix #4: Remove Dev Compose from Production (P3)
**Proposed:** Don't track `compose.yml` on production. Only use `compose.prod.yml`.

```bash
# In safe-deploy.sh
cp compose.prod.yml compose.yml
rm -f compose.yml.bak  # No fallback to dev version
```

**Why:** Eliminates confusion about which compose file is active.

### Fix #5: Docker Registry (P4 - Medium Term)
**Proposed:** Push images to registry with version tags.

```yaml
# compose.prod.yml
frontend:
  image: my-registry.azurecr.io/nexus-react-prod:2026-02-20-14a48cd6
```

**Benefits:**
- Immutable deployments
- Easy rollback (reference old tag)
- Registry attestation (image comes from trusted source)
- No local build confusion

---

## Prevention: How to Never Regress

### 1. Pre-Commit Checks (Already in Place)
- Husky pre-commit hook runs TypeScript checks
- Blocks commits if build fails

### 2. Pre-Push Checks (Already in Place)
- Full `npm run build` before push
- Blocks push if build fails

### 3. CI/CD Pipeline (Already in Place)
- GitHub Actions runs 5-stage pipeline
- Dockerfile drift detection
- Regression pattern scanning

### 4. Deployment Validation (MISSING - Add This)
**Add to `safe-deploy.sh`:**
```bash
# Before deploy
validate_dockerfiles() {
    # Verify prod Dockerfile has nginx
    if ! grep -q "FROM nginx:alpine" react-frontend/Dockerfile.prod; then
        log_err "Dockerfile.prod missing nginx (expected production Dockerfile)"
        return 1
    fi

    # Verify dev Dockerfile has node
    if ! grep -q "FROM node:20-alpine" react-frontend/Dockerfile; then
        log_err "Dockerfile missing node (expected dev Dockerfile)"
        return 1
    fi
}
```

### 5. Post-Deploy Verification (MISSING - Add This)
**Add image provenance checks to `verify-deploy.sh`:**
```bash
# Verify image names match expectations
verify_image_provenance() {
    docker inspect nexus-react-prod --format '{{.Config.Image}}' | grep -q "nexus-react-prod:latest"
    docker exec nexus-react-prod which nginx > /dev/null
}
```

---

## Deployment Best Practices (Going Forward)

### ✅ DO:
- Always use `--no-cache` for production frontend builds
- Explicitly tag images in compose files
- Verify images post-deploy before declaring success
- Keep dev and prod image names completely separate
- Restart PHP container after code changes (clears OPCache)

### ⛔ DON'T:
- Use implicit Docker image naming
- Share image names between dev and prod
- Skip rebuilds on production deploys
- Trust container names alone (verify image layers)
- Deploy without post-verification

---

## Lessons Learned

### 1. Container Names ≠ Image Names
- Container: `nexus-react-prod` (what it's called)
- Image: `nexus-react-prod:latest` (what it runs)
- These can diverge! Always verify **both**.

### 2. Docker Layer Cache is Persistent
- Without `--no-cache`, old layers survive rebuilds
- OPCache never re-reads files → requires container restart
- "Clean rebuild" isn't clean without `--no-cache`

### 3. Implicit Naming is Dangerous
- Docker infers image names from project + service
- Both dev and prod can build to same implicit name
- Always use explicit `image:` field

### 4. Git as Source of Truth Has Risks
- Dev config (`compose.yml`) checked into repo
- Production pulls it during deploy
- Must be overwritten before use
- Timing window for failure

### 5. Health Checks ≠ Correctness
- Both dev and prod images are "healthy"
- Health check just means "container responds"
- Need provenance checks (which Dockerfile? which build args?)

---

## Current State (After Fix #1)

### ✅ What's Fixed:
- Dev and prod images now have different names
- Dev container renamed from `nexus-react-prod` to `nexus-react-dev`
- Explicit image tags in both compose files
- No more image name collision

### ⚠️ What's Still Risky:
- Quick deploy skips frontend rebuild (uses old image)
- No post-deploy image verification
- Docker layer cache can still cause stale code
- Dev compose.yml still tracked on production server

### 📋 Next Steps:
1. Apply Fix #2: Force `--no-cache` rebuild in quick deploy
2. Apply Fix #3: Add image verification to `safe-deploy.sh`
3. Apply Fix #4: Remove dev compose from production
4. Test full deployment with verification
5. Document new deployment procedure

---

## Testing the Fixes

### Verify Local Dev (Should Use Dev Image)
```bash
docker compose down
docker compose build --no-cache
docker compose up -d

# Verify
docker inspect nexus-react-dev --format '{{.Config.Image}}'
# Expected: nexus-react-dev:latest

docker exec nexus-react-dev ps aux | grep node
# Expected: node process running (Vite dev server)
```

### Verify Production (Should Use Prod Image)
```bash
# On production server
cd /opt/nexus-php
sudo bash scripts/safe-deploy.sh full

# Verify
sudo docker inspect nexus-react-prod --format '{{.Config.Image}}'
# Expected: nexus-react-prod:latest

sudo docker exec nexus-react-prod which nginx
# Expected: /usr/sbin/nginx (nginx installed)

sudo docker exec nexus-react-prod ps aux | grep nginx
# Expected: nginx process running
```

---

## References

- **Original Issue:** Sarah Bird contributor not showing (root cause: dev image on prod)
- **Detection Method:** Inspected running container, found wrong image
- **Previous Incidents:** Documented in MEMORY.md (lines 104-110)
- **Related Commits:**
  - `fb000285` - Added safe-deploy.sh
  - `2249e963` - Protect compose.yml from git overwrite
  - `d8e481ac` - Switch to git-based deployment

---

## Conclusion

The deployment system is now **significantly safer** with explicit image naming, but **not yet foolproof**. The remaining fixes (forced rebuilds, image verification, registry versioning) will complete the hardening.

**Priority:** Apply remaining P2 fixes within 1 week to prevent future incidents.

**Sign-off:** Audit completed 2026-02-20 by Claude Sonnet 4.5 (deployment specialist agent).
