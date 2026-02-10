@echo off
REM =============================================================================
REM Project NEXUS - Production Deployment Script (Windows)
REM =============================================================================
REM Deploys PHP backend and React frontend to Azure/Plesk server
REM
REM Usage:
REM   deploy-production.bat           - Full deployment
REM   deploy-production.bat quick     - Code only (no rebuild)
REM   deploy-production.bat init      - First-time setup
REM   deploy-production.bat status    - Check status
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
if "%1"=="init" goto :init
if "%1"=="quick" goto :quick
if "%1"=="status" goto :status
if "%1"=="nginx" goto :nginx
goto :full

:init
echo [INFO] Running initial setup...
ssh -i "%SSH_KEY%" -o StrictHostKeyChecking=no %SERVER_USER%@%SERVER_HOST% "sudo mkdir -p %REMOTE_PATH% && sudo chown %SERVER_USER%:%SERVER_USER% %REMOTE_PATH% && mkdir -p %REMOTE_PATH%/{httpdocs,src,views,config,react-frontend,vendor,migrations,scripts}"
echo [INFO] Initial setup complete. Now run without 'init' to deploy.
goto :end

:quick
echo [INFO] Quick deployment (code sync + restart)...
call :sync_files
echo [INFO] Restarting containers...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo docker compose restart app frontend"
call :health_check
goto :end

:status
echo [INFO] Checking deployment status...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo docker compose ps && echo && sudo docker compose logs --tail=20 app"
goto :end

:nginx
echo [INFO] Configuring Nginx...
call :configure_nginx
goto :end

:full
echo [INFO] Starting full deployment...
call :sync_files
call :install_deps
call :build_start
call :configure_nginx
call :health_check
echo.
echo [SUCCESS] Deployment complete!
echo.
echo URLs:
echo   API:      https://api.project-nexus.ie
echo   Frontend: https://app.project-nexus.ie
goto :end

:sync_files
echo [INFO] Syncing files to server...
REM Using scp for Windows compatibility (rsync not always available)
scp -i "%SSH_KEY%" -r httpdocs src views config migrations scripts Dockerfile Dockerfile.prod compose.prod.yml composer.json composer.lock %SERVER_USER%@%SERVER_HOST%:%REMOTE_PATH%/
scp -i "%SSH_KEY%" -r react-frontend\src react-frontend\public react-frontend\package.json react-frontend\package-lock.json react-frontend\Dockerfile.prod react-frontend\nginx.conf react-frontend\vite.config.ts react-frontend\tsconfig.json %SERVER_USER%@%SERVER_HOST%:%REMOTE_PATH%/react-frontend/
echo [INFO] Files synced
goto :eof

:install_deps
echo [INFO] Installing PHP dependencies...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo docker run --rm -v $(pwd):/app -w /app composer:2 install --no-dev --optimize-autoloader --no-interaction"
echo [INFO] Dependencies installed
goto :eof

:build_start
echo [INFO] Building and starting containers...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && cp compose.prod.yml compose.yml && sudo docker compose build && sudo docker compose up -d"
echo [INFO] Containers started
goto :eof

:configure_nginx
echo [INFO] Configuring Nginx reverse proxy...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:8090; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/api.project-nexus.ie/conf/vhost_nginx.conf"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:3000; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/app.project-nexus.ie/conf/vhost_nginx.conf"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "sudo nginx -t && sudo systemctl reload nginx"
echo [INFO] Nginx configured
goto :eof

:health_check
echo [INFO] Running health checks...
timeout /t 5 /nobreak > nul
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:8090/health.php && echo ' - API OK' || echo ' - API FAILED'"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:3000/ > /dev/null && echo 'Frontend OK' || echo 'Frontend FAILED'"
goto :eof

:end
endlocal
