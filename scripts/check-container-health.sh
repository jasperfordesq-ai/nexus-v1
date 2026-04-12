#!/bin/bash
# =============================================================================
# Project NEXUS - Production Container Health Check (TD14)
# =============================================================================
# Purpose: Detect container memory pressure and OOMKill events on production.
# After bumping the PHP container memory limit from 512M → 768M, we need a
# fast signal that containers are being reaped by Docker/kernel, which would
# otherwise surface only as sporadic 502s.
#
# Usage:
#   # Run FROM the local workstation (SSHes into prod):
#   bash scripts/check-container-health.sh
#
#   # Run ON the production server directly (no SSH needed):
#   sudo LOCAL_MODE=1 bash scripts/check-container-health.sh
#
# Exit codes:
#   0 = all containers healthy
#   1 = at least one container OOMKilled in last hour OR memory > 90% of limit
#   2 = SSH/docker failure (unable to collect data)
#
# Called by:
#   - scripts/safe-deploy.sh (post-deploy-check, 5 min after deploy)
#   - Manual ops / runbook
#   - Can be wired to cron (e.g. every 15 min) for continuous monitoring
# =============================================================================

set -u

# --- Config ---
SSH_KEY="${SSH_KEY:-C:\\ssh-keys\\project-nexus.pem}"
SSH_HOST="${SSH_HOST:-azureuser@20.224.171.253}"
SSH_OPTS="-i \"$SSH_KEY\" -o RequestTTY=force -o StrictHostKeyChecking=accept-new"
MEM_THRESHOLD_PCT="${MEM_THRESHOLD_PCT:-90}"   # percent of limit → WARN/FAIL
OOM_LOOKBACK="${OOM_LOOKBACK:-1h}"             # docker events window
CONTAINER_FILTER="${CONTAINER_FILTER:-nexus-}" # only our containers

# --- Colors ---
if [ -t 1 ]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
    CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
else
    RED=''; GREEN=''; YELLOW=''; CYAN=''; BOLD=''; NC=''
fi

log_ok()   { echo -e "${GREEN}[PASS]${NC} $1"; }
log_info() { echo -e "${CYAN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $1"; }

# --- Runner ---
# run_remote: run a shell snippet either directly (LOCAL_MODE=1) or via SSH
run_remote() {
    local cmd="$1"
    if [ "${LOCAL_MODE:-0}" = "1" ]; then
        bash -c "$cmd"
    else
        # shellcheck disable=SC2086
        eval ssh $SSH_OPTS "$SSH_HOST" "'$cmd'"
    fi
}

FAIL=0
WARN=0

echo -e "${BOLD}Project NEXUS - Container Health Check${NC}"
echo "Host: ${SSH_HOST}  |  Mode: ${LOCAL_MODE:+local}${LOCAL_MODE:-ssh}"
echo "Mem threshold: ${MEM_THRESHOLD_PCT}%  |  OOM lookback: ${OOM_LOOKBACK}"
echo "============================================================"

# ------------------------------------------------------------
# 1. Current memory / CPU usage per container
# ------------------------------------------------------------
echo ""
echo -e "${BOLD}1. Container resource usage${NC}"

STATS_RAW=$(run_remote "sudo docker stats --no-stream --format '{{.Name}}|{{.MemUsage}}|{{.MemPerc}}|{{.CPUPerc}}' | grep '^${CONTAINER_FILTER}' || true") || {
    log_err "Could not collect docker stats (SSH or docker failed)"
    exit 2
}

if [ -z "$STATS_RAW" ]; then
    log_err "No containers matching '${CONTAINER_FILTER}*' found on host"
    exit 2
fi

printf "  %-28s %-22s %-10s %-10s %s\n" "CONTAINER" "MEM USAGE" "MEM%" "CPU%" "STATUS"
while IFS='|' read -r NAME MEM_USAGE MEM_PCT CPU_PCT; do
    [ -z "$NAME" ] && continue
    MEM_NUM="${MEM_PCT%\%}"
    MEM_INT=$(printf '%.0f' "${MEM_NUM:-0}" 2>/dev/null || echo 0)
    STATUS="${GREEN}OK${NC}"
    if [ "$MEM_INT" -ge "$MEM_THRESHOLD_PCT" ]; then
        STATUS="${RED}HIGH${NC}"
        FAIL=$((FAIL + 1))
    elif [ "$MEM_INT" -ge $((MEM_THRESHOLD_PCT - 15)) ]; then
        STATUS="${YELLOW}WARN${NC}"
        WARN=$((WARN + 1))
    fi
    printf "  %-28s %-22s %-10s %-10s ${STATUS}\n" "$NAME" "$MEM_USAGE" "$MEM_PCT" "$CPU_PCT"
done <<< "$STATS_RAW"

# ------------------------------------------------------------
# 2. OOMKill / die events in lookback window
# ------------------------------------------------------------
echo ""
echo -e "${BOLD}2. OOMKill / die events (last ${OOM_LOOKBACK})${NC}"

OOM_EVENTS=$(run_remote "sudo docker events --since ${OOM_LOOKBACK} --until 0s --filter event=oom --filter event=die --format '{{.Time}} {{.Type}} {{.Action}} {{.Actor.Attributes.name}}' 2>/dev/null | grep '${CONTAINER_FILTER}' || true")

if [ -z "$OOM_EVENTS" ]; then
    log_ok "No OOM or die events in last ${OOM_LOOKBACK}"
else
    # die events for clean restarts are not necessarily fatal; oom is.
    OOM_COUNT=$(echo "$OOM_EVENTS" | grep -c ' oom ' || true)
    DIE_COUNT=$(echo "$OOM_EVENTS" | grep -c ' die ' || true)
    if [ "$OOM_COUNT" -gt 0 ]; then
        log_err "Detected ${OOM_COUNT} OOMKill event(s):"
        echo "$OOM_EVENTS" | grep ' oom ' | sed 's/^/    /'
        FAIL=$((FAIL + 1))
    fi
    if [ "$DIE_COUNT" -gt 0 ]; then
        log_warn "Detected ${DIE_COUNT} die event(s) (may be routine restarts):"
        echo "$OOM_EVENTS" | grep ' die ' | sed 's/^/    /'
        WARN=$((WARN + 1))
    fi
fi

# ------------------------------------------------------------
# 3. Per-container OOMKilled flag + restart policy
# ------------------------------------------------------------
echo ""
echo -e "${BOLD}3. Container state (OOMKilled / RestartCount / Policy)${NC}"

CONTAINERS=$(run_remote "sudo docker ps --format '{{.Names}}' | grep '^${CONTAINER_FILTER}' || true")
while read -r CNAME; do
    [ -z "$CNAME" ] && continue
    INSPECT=$(run_remote "sudo docker inspect $CNAME --format '{{.State.OOMKilled}}|{{.State.RestartCount}}|{{.HostConfig.RestartPolicy.Name}}|{{.State.Status}}' 2>/dev/null || echo 'ERR|0|none|unknown'")
    IFS='|' read -r OOMK RCOUNT POLICY STATE <<< "$INSPECT"
    LINE=$(printf "  %-28s OOMKilled=%-5s RestartCount=%-3s Policy=%-10s Status=%s" "$CNAME" "$OOMK" "$RCOUNT" "$POLICY" "$STATE")
    if [ "$OOMK" = "true" ]; then
        echo -e "${RED}${LINE}${NC}"
        FAIL=$((FAIL + 1))
    elif [ "${RCOUNT:-0}" -gt 3 ]; then
        echo -e "${YELLOW}${LINE}  (high restart count)${NC}"
        WARN=$((WARN + 1))
    else
        echo -e "${GREEN}${LINE}${NC}"
    fi
done <<< "$CONTAINERS"

# ------------------------------------------------------------
# Summary
# ------------------------------------------------------------
echo ""
echo "============================================================"
if [ $FAIL -gt 0 ]; then
    log_err "Health check FAILED: ${FAIL} critical issue(s), ${WARN} warning(s)"
    echo ""
    echo "Runbook:"
    echo "  1. Inspect logs:   sudo docker logs --tail 200 nexus-php-app"
    echo "  2. Check limits:   grep -A3 'deploy:' compose.prod.yml"
    echo "  3. Raise limit:    edit compose.prod.yml → mem_limit / memory"
    echo "  4. Redeploy:       sudo bash scripts/safe-deploy.sh full --detach"
    exit 1
elif [ $WARN -gt 0 ]; then
    log_warn "Health check passed with ${WARN} warning(s)"
    exit 0
else
    log_ok "All containers healthy"
    exit 0
fi
