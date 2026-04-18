#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# One-time setup: rclone + Google Drive for nightly backups
#
# Run this ON THE PRODUCTION SERVER (via SSH):
#   ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253
#   sudo bash /opt/nexus-php/scripts/setup-rclone-gdrive.sh
#
# What this does:
#   1. Installs rclone
#   2. Walks you through Google Drive OAuth (browser step on your local machine)
#   3. Tests the connection
#   4. Wires up the nightly backup cron job with RCLONE_REMOTE set
#
# After setup, every nightly backup is automatically synced to:
#   Google Drive → "nexus-backups" folder
#   (My Drive / nexus-backups/)

set -euo pipefail

REMOTE_NAME="gdrive"
DRIVE_FOLDER="nexus-backups"
RCLONE_REMOTE="${REMOTE_NAME}:${DRIVE_FOLDER}"
BACKUP_SCRIPT="/opt/nexus-php/scripts/server-nightly-backup.sh"
BACKUP_LOG="/opt/nexus-php/backups/backup.log"
CRON_SCHEDULE="0 2 * * *"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
log()     { echo -e "${CYAN}→ $1${NC}"; }
success() { echo -e "${GREEN}✓ $1${NC}"; }
warn()    { echo -e "${YELLOW}⚠ $1${NC}"; }
fail()    { echo -e "${RED}✗ ERROR: $1${NC}"; exit 1; }
header()  { echo -e "\n${BOLD}$1${NC}"; echo "$(echo "$1" | sed 's/./-/g')"; }

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║        Project NEXUS — Google Drive Backup Setup         ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""

# ---------------------------------------------------------------------------
# Step 1: Install rclone
# ---------------------------------------------------------------------------
header "Step 1: Install rclone"

if command -v rclone &>/dev/null; then
    success "rclone already installed: $(rclone version | head -1)"
else
    log "Installing rclone..."
    curl -fsSL https://rclone.org/install.sh | bash
    success "rclone installed: $(rclone version | head -1)"
fi

# ---------------------------------------------------------------------------
# Step 2: Check if already configured
# ---------------------------------------------------------------------------
header "Step 2: Google Drive authorisation"

if rclone listremotes | grep -q "^${REMOTE_NAME}:"; then
    warn "Remote '${REMOTE_NAME}' already exists in rclone config."
    read -rp "Re-configure it? (y/N): " RECONFIGURE
    if [[ ! "$RECONFIGURE" =~ ^[Yy]$ ]]; then
        log "Keeping existing remote — skipping authorisation."
        SKIP_AUTH=true
    else
        rclone config delete "$REMOTE_NAME" 2>/dev/null || true
        SKIP_AUTH=false
    fi
else
    SKIP_AUTH=false
fi

if [[ "$SKIP_AUTH" == "false" ]]; then
    echo ""
    echo -e "${YELLOW}You need to authorise rclone to access your Google Drive.${NC}"
    echo ""
    echo "  Because this is a headless server, the process is:"
    echo "  1. rclone will print a long URL below"
    echo "  2. Open that URL in a browser on your local machine"
    echo "  3. Log in with your Google Workspace account"
    echo "  4. Click 'Allow', then copy the verification code"
    echo "  5. Paste the code back here"
    echo ""
    read -rp "Press ENTER when ready to start authorisation..."

    # Run rclone config non-interactively up to the point we need browser auth,
    # then let the user handle the OAuth exchange.
    # rclone config create handles all fields; --auth-no-open-browser prints the URL.
    rclone config create "$REMOTE_NAME" drive \
        scope=drive \
        --auth-no-open-browser \
        2>&1 | tee /tmp/rclone-config-output.txt || true

    # If config create succeeded without needing auth (service account etc.), skip code prompt
    if rclone listremotes | grep -q "^${REMOTE_NAME}:"; then
        success "Remote '${REMOTE_NAME}' configured"
    else
        echo ""
        warn "If the above didn't complete automatically, run the full interactive config:"
        echo ""
        echo "  sudo rclone config"
        echo "  → n (new remote) → name: gdrive → type: drive → (blank client_id/secret)"
        echo "  → scope: drive → (blank root_folder_id / service_account_file)"
        echo "  → advanced: No → auto config: No → paste the URL into your browser"
        echo "  → paste verification code here → team drive: No → q to quit"
        echo ""
        read -rp "Press ENTER once you have completed 'sudo rclone config' manually..."

        rclone listremotes | grep -q "^${REMOTE_NAME}:" || \
            fail "Remote '${REMOTE_NAME}' not found in rclone config after setup. Please re-run."
        success "Remote '${REMOTE_NAME}' confirmed"
    fi
fi

# ---------------------------------------------------------------------------
# Step 3: Test the connection
# ---------------------------------------------------------------------------
header "Step 3: Test connection"

log "Creating '${DRIVE_FOLDER}' folder in Google Drive and writing test file..."
echo "nexus-backup-test $(date)" | rclone rcat "${RCLONE_REMOTE}/.nexus-test" 2>/dev/null || \
    fail "Could not write to Google Drive. Check the remote config and try again."

rclone deletefile "${RCLONE_REMOTE}/.nexus-test" 2>/dev/null || true
success "Google Drive connection verified — '${DRIVE_FOLDER}' folder is ready"

# ---------------------------------------------------------------------------
# Step 4: Wire up cron
# ---------------------------------------------------------------------------
header "Step 4: Configure nightly cron job"

CRON_LINE="RCLONE_REMOTE=${RCLONE_REMOTE}"
CRON_CMD="${CRON_SCHEDULE} bash ${BACKUP_SCRIPT} >> ${BACKUP_LOG} 2>&1"

# Read existing root crontab (if any)
CURRENT_CRON=$(sudo crontab -l 2>/dev/null || true)

# Check if backup cron already exists
if echo "$CURRENT_CRON" | grep -q "$BACKUP_SCRIPT"; then
    # Update the existing line — replace the whole block
    log "Backup cron job already exists — updating to include RCLONE_REMOTE..."
    NEW_CRON=$(echo "$CURRENT_CRON" \
        | grep -v "RCLONE_REMOTE=" \
        | grep -v "$BACKUP_SCRIPT")
    printf '%s\n%s\n%s\n' "$NEW_CRON" "$CRON_LINE" "$CRON_CMD" | \
        grep -v '^$' | sudo crontab -
else
    log "Adding backup cron job..."
    printf '%s\n%s\n%s\n' "$CURRENT_CRON" "$CRON_LINE" "$CRON_CMD" | \
        grep -v '^$' | sudo crontab -
fi

success "Cron job set: runs nightly at 02:00"
echo ""
log "Current root crontab:"
sudo crontab -l

# ---------------------------------------------------------------------------
# Step 5: Run backup now to verify end-to-end
# ---------------------------------------------------------------------------
header "Step 5: Run backup now (end-to-end test)"

echo ""
read -rp "Run the full backup + Google Drive sync now to verify everything works? (Y/n): " RUN_NOW
if [[ ! "$RUN_NOW" =~ ^[Nn]$ ]]; then
    log "Running backup (this may take a few minutes for the uploads volume)..."
    RCLONE_REMOTE="$RCLONE_REMOTE" bash "$BACKUP_SCRIPT"
    echo ""
    log "Files now in Google Drive (${DRIVE_FOLDER}/):"
    rclone ls "${RCLONE_REMOTE}" | sort
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║  Setup complete                                          ║${NC}"
echo -e "${BOLD}╠══════════════════════════════════════════════════════════╣${NC}"
echo -e "${BOLD}║  Schedule:  every night at 02:00 server time             ║${NC}"
echo -e "${BOLD}║  Remote:    ${RCLONE_REMOTE}                             ║${NC}"
echo -e "${BOLD}║  Backups:   DB + uploads + storage (7-day rotation)      ║${NC}"
echo -e "${BOLD}║  Log:       ${BACKUP_LOG}              ║${NC}"
echo -e "${BOLD}╠══════════════════════════════════════════════════════════╣${NC}"
echo -e "${BOLD}║  To check Drive contents at any time:                    ║${NC}"
echo -e "${BOLD}║    sudo rclone ls ${RCLONE_REMOTE}                       ║${NC}"
echo -e "${BOLD}║  To restore uploads:                                     ║${NC}"
echo -e "${BOLD}║    sudo rclone copy ${RCLONE_REMOTE}/nexus_uploads_DATE.tar.gz /tmp/${NC}"
echo -e "${BOLD}║    sudo docker run --rm -v nexus-php-uploads:/data \\     ║${NC}"
echo -e "${BOLD}║      -v /tmp:/in alpine tar xzf /in/nexus_uploads_DATE.tar.gz -C /data${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
