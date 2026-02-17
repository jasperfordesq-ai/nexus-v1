@echo off
REM =============================================================================
REM Project NEXUS - Production Deployment Script (Windows)
REM =============================================================================
REM Deploys PHP backend and React frontend to Azure/Plesk server via git pull.
REM
REM Usage:
REM   deploy-production.bat           - Full deployment (git pull + rebuild)
REM   deploy-production.bat quick     - Code only (git pull + restart)
REM   deploy-production.bat status    - Check status
REM   deploy-production.bat nginx     - Update nginx config only
REM
REM Prerequisites:
REM   - Push changes to GitHub first (git push origin main)
REM   - Production server has git configured with deploy key
REM =============================================================================

setlocal EnableDelayedExpansion

REM Configuration
set SERVER_USER=azureuser
set SERVER_HOST=20.224.171.253
set SSH_KEY=C:\ssh-keys\project-nexus.pem
set REMOTE_PATH=/opt/nexus-php

REM Check for SSH key
if not exist "%SSH_KEY%" (
    echo [ERROR] SSH key not found at: %SSH_KEY%
    exit /b 1
)

REM Parse arguments
if "%1"=="quick" goto :quick
if "%1"=="status" goto :status
if "%1"=="nginx" goto :nginx
goto :full

:quick
echo [INFO] Quick deployment (git pull + restart)...
call :git_pull
echo [INFO] Restarting PHP container (OPCache clear)...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo docker restart nexus-php-app"
call :health_check
goto :end

:status
echo [INFO] Checking deployment status...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && echo '=== Git Status ===' && sudo git log --oneline -3 && echo && echo '=== Containers ===' && sudo docker ps --format 'table {{.Names}}\t{{.Status}}' | grep nexus && echo && echo '=== Recent Logs ===' && sudo docker compose logs --tail=10 app 2>/dev/null"
goto :end

:nginx
echo [INFO] Configuring Nginx...
call :configure_nginx
goto :end

:full
echo [INFO] Starting full deployment...
echo.
echo [STEP 1/5] Pulling latest code from GitHub...
call :git_pull
echo.
echo [STEP 2/5] Installing PHP dependencies...
call :install_deps
echo.
echo [STEP 3/5] Building containers (--no-cache)...
call :build_start
echo.
echo [STEP 4/5] Configuring Nginx...
call :configure_nginx
echo.
echo [STEP 5/5] Health checks...
call :health_check
echo.
echo ================================================================
echo [SUCCESS] Deployment complete!
echo ================================================================
echo.
echo   API:      https://api.project-nexus.ie
echo   Frontend: https://app.project-nexus.ie
echo.
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && echo 'Deployed commit:' && sudo git log --oneline -1"
goto :end

:git_pull
echo [INFO] Pulling latest from GitHub...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo git fetch origin main && sudo git reset --hard origin/main && echo 'Now at:' && sudo git log --oneline -1"
echo [INFO] Code synced via git
goto :eof

:install_deps
echo [INFO] Installing PHP dependencies...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo docker run --rm -v $(pwd):/app -w /app composer:2 install --no-dev --optimize-autoloader --no-interaction"
echo [INFO] Dependencies installed
goto :eof

:build_start
echo [INFO] Building and starting containers (--no-cache)...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && cp compose.prod.yml compose.yml && sudo docker compose build --no-cache && sudo docker compose up -d"
echo [INFO] Containers rebuilt and started
goto :eof

:configure_nginx
echo [INFO] Configuring Nginx reverse proxy...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:8090; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/api.project-nexus.ie/conf/vhost_nginx.conf"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:3000; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/app.project-nexus.ie/conf/vhost_nginx.conf"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "sudo nginx -t && sudo systemctl reload nginx"
echo [INFO] Nginx configured
goto :eof

:health_check
echo [INFO] Running health checks (waiting 5s for containers)...
timeout /t 5 /nobreak > nul
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:8090/health.php && echo ' - API OK' || echo ' - API FAILED'"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:3000/ > /dev/null && echo 'Frontend OK' || echo 'Frontend FAILED'"
goto :eof

:end
endlocal
