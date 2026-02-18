#!/bin/bash
# =============================================================================
# Project NEXUS - Production Deployment Script
# =============================================================================
# Deploys PHP backend and React frontend to Azure/Plesk server
#
# Usage:
#   ./scripts/deploy-production.sh           # Full deployment
#   ./scripts/deploy-production.sh --quick   # Code only (no rebuild)
#   ./scripts/deploy-production.sh --init    # First-time setup
# =============================================================================

set -e  # Exit on error

# Configuration
SERVER_USER="azureuser"
SERVER_HOST="20.224.171.253"
SSH_KEY="C:/ssh-keys/project-nexus.pem"
REMOTE_PATH="/opt/nexus-php"
LOCAL_PATH="."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# SSH command helper
ssh_cmd() {
    ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_HOST" "$1"
}

# Check if SSH key exists
check_ssh_key() {
    if [ ! -f "$SSH_KEY" ]; then
        log_error "SSH key not found at: $SSH_KEY"
        exit 1
    fi
}

# Test server connection
test_connection() {
    log_info "Testing server connection..."
    if ssh_cmd "echo 'Connection successful'"; then
        log_info "Server connection OK"
    else
        log_error "Failed to connect to server"
        exit 1
    fi
}

# Initial setup (run once)
init_setup() {
    log_info "Running initial setup..."

    ssh_cmd "sudo mkdir -p $REMOTE_PATH && sudo chown $SERVER_USER:$SERVER_USER $REMOTE_PATH"

    log_info "Creating directory structure..."
    ssh_cmd "mkdir -p $REMOTE_PATH/{httpdocs,src,views,config,react-frontend,vendor,migrations,scripts}"

    log_info "Initial setup complete. Now run without --init to deploy."
}

# Sync files to server
sync_files() {
    log_info "Syncing files to server..."

    # Files and directories to sync
    rsync -avz --progress \
        -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" \
        --exclude 'node_modules' \
        --exclude 'vendor' \
        --exclude '.git' \
        --exclude '.env' \
        --exclude '.env.docker' \
        --exclude '.env.local' \
        --exclude 'uploads/*' \
        --exclude 'logs/*' \
        --exclude '*.log' \
        --exclude '.DS_Store' \
        --exclude 'Thumbs.db' \
        --exclude 'backups' \
        --exclude 'tests' \
        --exclude '.phpunit*' \
        --exclude '.vscode' \
        --exclude '.idea' \
        --include 'httpdocs/***' \
        --include 'src/***' \
        --include 'views/***' \
        --include 'config/***' \
        --include 'react-frontend/***' \
        --include 'migrations/***' \
        --include 'scripts/***' \
        --include 'Dockerfile' \
        --include 'Dockerfile.prod' \
        --include 'compose.prod.yml' \
        --include 'composer.json' \
        --include 'composer.lock' \
        --include '.htaccess' \
        --exclude '*' \
        "$LOCAL_PATH/" "$SERVER_USER@$SERVER_HOST:$REMOTE_PATH/"

    log_info "Files synced successfully"
}

# Install PHP dependencies
install_dependencies() {
    log_info "Installing PHP dependencies..."
    ssh_cmd "cd $REMOTE_PATH && sudo docker run --rm -v \$(pwd):/app -w /app composer:2 install --no-dev --optimize-autoloader --no-interaction"
    log_info "Dependencies installed"
}

# Build and start Docker containers
build_and_start() {
    log_info "Building and starting Docker containers..."

    # Copy production compose file
    ssh_cmd "cd $REMOTE_PATH && cp compose.prod.yml compose.yml"

    # Build and start
    ssh_cmd "cd $REMOTE_PATH && sudo docker compose build --no-cache && sudo docker compose up -d"

    log_info "Containers started"
}

# Quick restart (no rebuild)
quick_restart() {
    log_info "Restarting containers..."
    ssh_cmd "cd $REMOTE_PATH && sudo docker compose restart app frontend"
    log_info "Containers restarted"
}

# Configure Nginx reverse proxy
configure_nginx() {
    log_info "Configuring Nginx reverse proxy..."

    # API domain config
    ssh_cmd "sudo tee /var/www/vhosts/system/api.project-nexus.ie/conf/vhost_nginx.conf > /dev/null << 'NGINX'
location / {
    proxy_pass http://127.0.0.1:8090;
    proxy_set_header Host \\\$host;
    proxy_set_header X-Real-IP \\\$remote_addr;
    proxy_set_header X-Forwarded-For \\\$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \\\$scheme;
    proxy_connect_timeout 60s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;
    proxy_http_version 1.1;
    proxy_set_header Upgrade \\\$http_upgrade;
    proxy_set_header Connection \"upgrade\";
}
NGINX"

    # Frontend domain config
    ssh_cmd "sudo tee /var/www/vhosts/system/app.project-nexus.ie/conf/vhost_nginx.conf > /dev/null << 'NGINX'
location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host \\\$host;
    proxy_set_header X-Real-IP \\\$remote_addr;
    proxy_set_header X-Forwarded-For \\\$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \\\$scheme;
    proxy_connect_timeout 30s;
    proxy_send_timeout 30s;
    proxy_read_timeout 30s;
}
NGINX"

    # Reload nginx
    ssh_cmd "sudo nginx -t && sudo systemctl reload nginx"

    log_info "Nginx configured and reloaded"
}

# Health check
health_check() {
    log_info "Running health checks..."

    sleep 5  # Wait for containers to be ready

    # Check containers
    ssh_cmd "sudo docker ps --format 'table {{.Names}}\t{{.Status}}' | grep nexus-php"

    # Test API endpoint
    log_info "Testing API endpoint..."
    if ssh_cmd "curl -sf http://127.0.0.1:8090/health.php"; then
        log_info "API health check: OK"
    else
        log_warn "API health check: FAILED"
    fi

    # Test frontend
    log_info "Testing frontend..."
    if ssh_cmd "curl -sf http://127.0.0.1:3000/ > /dev/null"; then
        log_info "Frontend health check: OK"
    else
        log_warn "Frontend health check: FAILED"
    fi
}

# Show status
show_status() {
    log_info "Current deployment status:"
    ssh_cmd "cd $REMOTE_PATH && sudo docker compose ps"
}

# Main deployment
main() {
    case "${1:-}" in
        --init)
            check_ssh_key
            test_connection
            init_setup
            ;;
        --quick)
            check_ssh_key
            test_connection
            sync_files
            quick_restart
            health_check
            ;;
        --nginx)
            check_ssh_key
            test_connection
            configure_nginx
            ;;
        --status)
            check_ssh_key
            test_connection
            show_status
            ;;
        *)
            check_ssh_key
            test_connection
            sync_files
            install_dependencies
            build_and_start
            configure_nginx
            health_check
            show_status
            log_info "Deployment complete!"
            echo ""
            echo "URLs:"
            echo "  API:      https://api.project-nexus.ie"
            echo "  Frontend: https://app.project-nexus.ie"
            ;;
    esac
}

# Run main
main "$@"
