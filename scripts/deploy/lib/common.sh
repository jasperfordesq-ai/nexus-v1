#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Shared library: path constants, color codes, log functions.
# Sourced by orchestrator, phases, and subcommands. Must be idempotent.

# --- Configuration ---
: "${DEPLOY_DIR:=/opt/nexus-php}"
LOCK_FILE="$DEPLOY_DIR/.deploy.lock"
LOG_DIR="$DEPLOY_DIR/logs"
LAST_DEPLOY_FILE="$DEPLOY_DIR/.last-successful-deploy"
LAST_PRERENDER_FILE="$DEPLOY_DIR/.last-successful-prerender"
MIN_DISK_SPACE_MB=1024  # 1GB minimum free space

# --- Maintenance Mode ---
MAINTENANCE_FILE="/var/www/html/.maintenance"
PHP_CONTAINER="nexus-php-app"

# Enable BuildKit for faster parallel builds and better layer caching
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- Logging functions ---
# _log_out: in detached mode, just echo (stdout IS the log file).
# In interactive mode, tee to both terminal and log file.
_log_out() {
    if [ -n "${__NEXUS_DEPLOY_DETACHED__:-}" ]; then
        echo -e "$1"
    else
        echo -e "$1" | tee -a "$LOG_FILE"
    fi
}
log_ok()   { _log_out "${GREEN}[OK]${NC}   $1"; }
log_info() { _log_out "${CYAN}[INFO]${NC} $1"; }
log_warn() { _log_out "${YELLOW}[WARN]${NC} $1"; }
log_err()  { _log_out "${RED}[FAIL]${NC} $1"; }
log_step() { _log_out "\n${BOLD}$1${NC}"; }
