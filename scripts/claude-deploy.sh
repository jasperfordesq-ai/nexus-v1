#!/bin/bash
# ===========================================
# NEXUS TimeBank - Claude Code Deploy Script
# ===========================================
# Non-interactive deployment for use from Claude Code
# Usage: bash scripts/claude-deploy.sh [options]
#
# Options:
#   --dry-run       Show what would be deployed without deploying
#   --changed       Deploy only git-changed files (default)
#   --last-commit   Deploy files from the most recent commit
#   --folders       Deploy entire folders (src, views, httpdocs, config)
#   --file FILE     Deploy a specific file
#   --verify        Verify files after deploy
#   --skip-syntax   Skip PHP syntax checking
# ===========================================

set -e

# Configuration
SERVER="jasper@35.205.239.67"
REMOTE_PATH="/var/www/vhosts/project-nexus.ie"
LOCAL_PATH="/c/xampp/htdocs/staging"
LOG_DIR="$LOCAL_PATH/logs"

# Ensure log directory exists
mkdir -p "$LOG_DIR"

# Parse arguments
DRY_RUN=false
MODE="changed"
SPECIFIC_FILE=""
VERIFY=false
SKIP_SYNTAX=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --changed) MODE="changed"; shift ;;
        --last-commit) MODE="last-commit"; shift ;;
        --folders) MODE="folders"; shift ;;
        --file) SPECIFIC_FILE="$2"; shift 2 ;;
        --verify) VERIFY=true; shift ;;
        --skip-syntax) SKIP_SYNTAX=true; shift ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
done

# Helper functions
log() { echo "$1"; }
log_success() { echo "✓ $1"; }
log_error() { echo "✗ $1"; }
log_warn() { echo "⚠ $1"; }

# Check SSH connection
check_ssh() {
    if ! ssh -o ConnectTimeout=5 -o BatchMode=yes "$SERVER" "echo ok" &>/dev/null; then
        log_error "Cannot connect to server"
        exit 1
    fi
    log_success "SSH connection verified"
}

# PHP syntax check
check_php() {
    local file="$1"
    if [[ "$file" == *.php && "$SKIP_SYNTAX" == "false" ]]; then
        if ! php -l "$file" &>/dev/null; then
            log_error "PHP syntax error: $file"
            php -l "$file" 2>&1 | head -3
            return 1
        fi
    fi
    return 0
}

# Deploy a single file
deploy_file() {
    local file="$1"
    local local_file="$LOCAL_PATH/$file"
    local remote_file="$REMOTE_PATH/$file"
    local remote_dir=$(dirname "$remote_file")

    # Check file exists
    if [[ ! -f "$local_file" ]]; then
        log_error "File not found: $file"
        return 1
    fi

    # PHP syntax check
    if ! check_php "$local_file"; then
        return 1
    fi

    if [[ "$DRY_RUN" == "true" ]]; then
        log "  [DRY-RUN] Would deploy: $file"
        return 0
    fi

    # Create remote directory if needed
    ssh "$SERVER" "mkdir -p '$remote_dir'" 2>/dev/null

    # Deploy
    if scp -q "$local_file" "$SERVER:$remote_file" 2>/dev/null; then
        log_success "$file"
        return 0
    else
        log_error "$file - SCP failed"
        return 1
    fi
}

# Deploy a folder
deploy_folder() {
    local folder="$1"
    local local_folder="$LOCAL_PATH/$folder"

    if [[ ! -d "$local_folder" ]]; then
        log_warn "Folder not found: $folder"
        return 0
    fi

    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY-RUN] Would deploy folder: $folder"
        return 0
    fi

    log "Deploying $folder/..."
    if scp -r -q "$local_folder" "$SERVER:$REMOTE_PATH/" 2>/dev/null; then
        log_success "$folder/ deployed"
        return 0
    else
        log_error "$folder/ - SCP failed"
        return 1
    fi
}

# Get changed files from git (uncommitted)
get_changed_files() {
    cd "$LOCAL_PATH"

    # Deployable paths
    local deploy_paths="^(src|views|httpdocs|config|bootstrap\.php|migrations|scripts)"

    # Exclude patterns
    local excludes="\.env|node_modules|vendor|\.git|\.log$|\.md$"

    {
        git diff --name-only HEAD 2>/dev/null || true
        git diff --name-only --cached 2>/dev/null || true
        git ls-files --others --exclude-standard 2>/dev/null || true
    } | sort -u | grep -E "$deploy_paths" | grep -vE "$excludes" | while read -r file; do
        if [[ -f "$LOCAL_PATH/$file" ]]; then
            echo "$file"
        fi
    done
}

# Get files from last commit
get_last_commit_files() {
    cd "$LOCAL_PATH"

    # Deployable paths
    local deploy_paths="^(src|views|httpdocs|config|bootstrap\.php|migrations|scripts)"

    # Exclude patterns
    local excludes="\.env|node_modules|vendor|\.git|\.log$|\.md$"

    git diff-tree --no-commit-id --name-only -r HEAD 2>/dev/null | \
        grep -E "$deploy_paths" | grep -vE "$excludes" | while read -r file; do
        if [[ -f "$LOCAL_PATH/$file" ]]; then
            echo "$file"
        fi
    done
}

# Verify deployment
verify_file() {
    local file="$1"
    local local_size=$(stat -c%s "$LOCAL_PATH/$file" 2>/dev/null || wc -c < "$LOCAL_PATH/$file" | tr -d ' ')
    local remote_size=$(ssh "$SERVER" "stat -c%s '$REMOTE_PATH/$file'" 2>/dev/null || echo "0")

    if [[ "$local_size" == "$remote_size" ]]; then
        return 0
    else
        log_warn "Size mismatch: $file (local: $local_size, remote: $remote_size)"
        return 1
    fi
}

# Main
echo "========================================"
echo "  NEXUS Deployment"
echo "========================================"
echo ""

check_ssh
echo ""

# Handle specific file
if [[ -n "$SPECIFIC_FILE" ]]; then
    log "Deploying specific file: $SPECIFIC_FILE"
    deploy_file "$SPECIFIC_FILE"
    exit $?
fi

# Handle folder mode
if [[ "$MODE" == "folders" ]]; then
    log "Deploying all folders..."
    echo ""

    failed=0
    for folder in src views httpdocs config; do
        if ! deploy_folder "$folder"; then
            ((failed++))
        fi
    done

    if [[ -f "$LOCAL_PATH/bootstrap.php" ]]; then
        deploy_file "bootstrap.php"
    fi

    echo ""
    if [[ $failed -eq 0 ]]; then
        log_success "Deployment complete"
    else
        log_error "$failed folder(s) failed"
    fi
    exit $failed
fi

# Handle changed files mode or last-commit mode
if [[ "$MODE" == "last-commit" ]]; then
    log "Finding files from last commit..."
    mapfile -t files < <(get_last_commit_files)
else
    log "Finding changed files..."
    mapfile -t files < <(get_changed_files)
fi

if [[ ${#files[@]} -eq 0 ]]; then
    log "No changed files to deploy"
    exit 0
fi

log "Found ${#files[@]} file(s) to deploy:"
echo ""

# Check PHP syntax first
if [[ "$SKIP_SYNTAX" == "false" ]]; then
    syntax_errors=0
    for file in "${files[@]}"; do
        if ! check_php "$LOCAL_PATH/$file"; then
            ((syntax_errors++))
        fi
    done

    if [[ $syntax_errors -gt 0 ]]; then
        log_error "$syntax_errors PHP syntax error(s) - fix before deploying"
        exit 1
    fi
    log_success "PHP syntax check passed"
    echo ""
fi

# Deploy files
deployed=0
failed=0

for file in "${files[@]}"; do
    if deploy_file "$file"; then
        deployed=$((deployed + 1))
    else
        failed=$((failed + 1))
    fi
done

echo ""

# For dry-run, just show summary and exit
if [[ "$DRY_RUN" == "true" ]]; then
    log "DRY-RUN complete - ${#files[@]} file(s) would be deployed"
    exit 0
fi

log "Deployed: $deployed, Failed: $failed"

# Verify if requested
if [[ "$VERIFY" == "true" && $deployed -gt 0 ]]; then
    echo ""
    log "Verifying..."
    verify_failed=0
    for file in "${files[@]}"; do
        if ! verify_file "$file"; then
            ((verify_failed++))
        fi
    done

    if [[ $verify_failed -eq 0 ]]; then
        log_success "All files verified"
    else
        log_warn "$verify_failed file(s) may not have deployed correctly"
    fi
fi

# Log to manifest
if [[ $deployed -gt 0 ]]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deployed $deployed file(s): ${files[*]}" >> "$LOG_DIR/deploy-manifest.log"
fi

# Site check
echo ""
log "Checking site..."
http_code=$(curl -s -o /dev/null -w "%{http_code}" https://project-nexus.ie/ 2>/dev/null || echo "000")
if [[ "$http_code" == "200" ]]; then
    log_success "Site responding (HTTP $http_code)"
else
    log_warn "Site returned HTTP $http_code"
fi

echo ""
echo "========================================"
if [[ $failed -eq 0 ]]; then
    echo "  Deployment Complete"
else
    echo "  Deployment had $failed failure(s)"
fi
echo "========================================"

exit $failed
