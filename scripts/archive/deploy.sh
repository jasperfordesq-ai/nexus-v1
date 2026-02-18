#!/bin/bash
# ===========================================
# NEXUS TimeBank - Deployment Script
# ===========================================
# Usage: ./scripts/deploy.sh
# Or with custom server: ./scripts/deploy.sh user@server.com /path/to/webroot
# ===========================================

# Configuration
DEFAULT_SSH_USER="jasper"
DEFAULT_SSH_HOST="20.224.171.253"
DEFAULT_SSH_PORT="22"
DEFAULT_REMOTE_PATH="/opt/nexus-php"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Use arguments or defaults
SSH_USER="${1:-$DEFAULT_SSH_USER}"
SSH_HOST="${2:-$DEFAULT_SSH_HOST}"
SSH_PORT="${3:-$DEFAULT_SSH_PORT}"
REMOTE_PATH="${4:-$DEFAULT_REMOTE_PATH}"

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}  NEXUS TimeBank Deployment${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "Server:  ${YELLOW}${SSH_USER}@${SSH_HOST}:${SSH_PORT}${NC}"
echo -e "Path:    ${YELLOW}${REMOTE_PATH}${NC}"
echo -e "Source:  ${YELLOW}${PROJECT_ROOT}${NC}"
echo ""

# Check if .deployignore exists
if [ ! -f "$PROJECT_ROOT/.deployignore" ]; then
    echo -e "${RED}Error: .deployignore not found!${NC}"
    exit 1
fi

# Test SSH connection first
echo -e "${YELLOW}Testing SSH connection...${NC}"
ssh -p "$SSH_PORT" -o ConnectTimeout=10 -o BatchMode=yes "$SSH_USER@$SSH_HOST" "echo 'SSH connection successful'" 2>/dev/null
if [ $? -ne 0 ]; then
    echo -e "${RED}SSH connection failed!${NC}"
    echo ""
    echo "Troubleshooting:"
    echo "  1. Check firewall (GCP: VPC firewall rules, port 22)"
    echo "  2. Check Cloudflare (use direct server IP, not proxied domain)"
    echo "  3. Verify SSH is enabled in Plesk"
    echo "  4. Try: ssh -p $SSH_PORT $SSH_USER@$SSH_HOST"
    exit 1
fi

echo -e "${GREEN}SSH connection OK!${NC}"
echo ""

# Confirm deployment
read -p "Deploy now? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled."
    exit 0
fi

# Run rsync
echo ""
echo -e "${YELLOW}Starting deployment...${NC}"
echo ""

rsync -avz --progress \
    --exclude-from="$PROJECT_ROOT/.deployignore" \
    -e "ssh -p $SSH_PORT" \
    "$PROJECT_ROOT/" \
    "$SSH_USER@$SSH_HOST:$REMOTE_PATH/"

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${GREEN}  Deployment Complete!${NC}"
    echo -e "${GREEN}=========================================${NC}"

    # Run composer install on server (optional)
    echo ""
    read -p "Run 'composer install' on server? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Running composer install...${NC}"
        ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader"
    fi

    # Clear cache (optional)
    read -p "Clear server cache? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Clearing cache...${NC}"
        ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "cd $REMOTE_PATH && rm -rf cache/* 2>/dev/null; echo 'Cache cleared'"
    fi

else
    echo ""
    echo -e "${RED}Deployment failed!${NC}"
    exit 1
fi
