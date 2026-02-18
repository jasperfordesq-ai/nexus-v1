@echo off
REM =============================================================================
REM Project NEXUS - Production Deployment Script (Windows) - Enhanced
REM =============================================================================
REM Deploys PHP backend and React frontend to Azure/Plesk server via git pull.
REM
REM Usage:
REM   deploy-production.bat           - Full deployment (git pull + rebuild)
REM   deploy-production.bat quick     - Code only (git pull + restart)
REM   deploy-production.bat rollback  - Rollback to last successful deploy
REM   deploy-production.bat status    - Check deployment status
REM   deploy-production.bat nginx     - Update nginx config only
REM   deploy-production.bat logs      - View recent logs
REM
REM Prerequisites:
REM   - Push changes to GitHub first (git push origin main)
REM   - Production server has git configured with deploy key
REM
REM New Features:
REM   - Rollback capability
REM   - Pre-deploy validation
REM   - Post-deploy smoke tests
REM   - Deployment locking (prevents concurrent deploys)
REM   - Comprehensive logging
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
if "%1"=="" goto :full
if "%1"=="quick" goto :quick
if "%1"=="full" goto :full
if "%1"=="rollback" goto :rollback
if "%1"=="status" goto :status
if "%1"=="nginx" goto :nginx
if "%1"=="logs" goto :logs
echo [ERROR] Invalid argument: %1
echo Usage: deploy-production.bat [quick^|full^|rollback^|status^|nginx^|logs]
exit /b 1

:quick
echo ============================================================
echo   Quick Deployment (Git Pull + Restart)
echo ============================================================
echo.
call :push_check
echo [STEP 1/2] Running safe-deploy.sh quick on server...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo bash scripts/safe-deploy.sh quick"
if errorlevel 1 (
    echo.
    echo [FAILED] Deployment failed - check logs above
    exit /b 1
)
echo.
echo [STEP 2/2] Verifying deployment...
call :show_status
goto :end

:full
echo ============================================================
echo   Full Deployment (Git Pull + Rebuild)
echo ============================================================
echo.
call :push_check
echo [STEP 1/3] Running safe-deploy.sh full on server...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo bash scripts/safe-deploy.sh full"
if errorlevel 1 (
    echo.
    echo [FAILED] Deployment failed - check logs above
    echo [HELP] You can rollback with: deploy-production.bat rollback
    exit /b 1
)
echo.
echo [STEP 2/3] Configuring Nginx...
call :configure_nginx
echo.
echo [STEP 3/3] Final verification...
call :show_status
echo.
echo ================================================================
echo   Deployment Successful!
echo ================================================================
echo.
echo   API:        https://api.project-nexus.ie
echo   Frontend:   https://app.project-nexus.ie
echo   Sales Site: https://project-nexus.ie
echo.
goto :end

:rollback
echo ============================================================
echo   Rolling Back to Last Successful Deployment
echo ============================================================
echo.
echo [WARNING] This will revert to the last successful deployment.
echo.
set /p CONFIRM="Are you sure? (yes/no): "
if /i not "%CONFIRM%"=="yes" (
    echo [CANCELLED] Rollback cancelled
    exit /b 0
)
echo.
echo [ROLLBACK] Running safe-deploy.sh rollback on server...
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo bash scripts/safe-deploy.sh rollback"
if errorlevel 1 (
    echo.
    echo [FAILED] Rollback failed - check logs above
    exit /b 1
)
echo.
echo [SUCCESS] Rollback complete
call :show_status
goto :end

:status
echo ============================================================
echo   Deployment Status
echo ============================================================
echo.
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo bash scripts/safe-deploy.sh status"
goto :end

:nginx
echo ============================================================
echo   Nginx Configuration Update
echo ============================================================
echo.
call :configure_nginx
echo [SUCCESS] Nginx configuration updated
goto :end

:logs
echo ============================================================
echo   Recent Deployment Logs
echo ============================================================
echo.
echo Latest deployment log:
echo.
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH%/logs && ls -t deploy-*.log 2>/dev/null | head -1 | xargs tail -50 2>/dev/null || echo 'No deployment logs found'"
echo.
echo.
echo Recent container logs:
echo.
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && sudo docker compose logs --tail=20 app 2>/dev/null || echo 'Could not fetch container logs'"
goto :end

REM =============================================================================
REM Helper Functions
REM =============================================================================

:push_check
echo [CHECK] Verifying local changes pushed to GitHub...
for /f "tokens=*" %%i in ('git status --porcelain') do (
    echo [WARNING] You have uncommitted local changes:
    git status --short
    echo.
    set /p CONTINUE="Continue deployment anyway? (yes/no): "
    if /i not "!CONTINUE!"=="yes" (
        echo [CANCELLED] Deployment cancelled
        exit /b 1
    )
    goto :push_check_done
)
:push_check_done
REM Check if local branch is ahead of origin
for /f %%i in ('git rev-list --count origin/main..HEAD') do set AHEAD=%%i
if !AHEAD! GTR 0 (
    echo [WARNING] You have !AHEAD! local commit(s) not pushed to GitHub
    echo.
    set /p PUSH_NOW="Push to GitHub now? (yes/no): "
    if /i "!PUSH_NOW!"=="yes" (
        echo [PUSH] Pushing to origin/main...
        git push origin main
        if errorlevel 1 (
            echo [ERROR] Git push failed
            exit /b 1
        )
        echo [SUCCESS] Changes pushed to GitHub
    ) else (
        echo [WARNING] Deploying without pushing - server will NOT get your latest changes
        timeout /t 3 /nobreak > nul
    )
)
echo [OK] Ready to deploy
echo.
goto :eof

:configure_nginx
echo [NGINX] Configuring reverse proxy for all domains...
REM API domain
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:8090; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/api.project-nexus.ie/conf/vhost_nginx.conf > nul" 2>nul
REM Frontend domain
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:3000; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/app.project-nexus.ie/conf/vhost_nginx.conf > nul" 2>nul
REM Sales site domain
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "echo 'location / { proxy_pass http://127.0.0.1:3003; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for; proxy_set_header X-Forwarded-Proto $scheme; }' | sudo tee /var/www/vhosts/system/project-nexus.ie/conf/vhost_nginx.conf > nul" 2>nul
REM Test and reload nginx
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "sudo nginx -t && sudo systemctl reload nginx" 2>nul
if errorlevel 1 (
    echo [WARNING] Nginx configuration may have failed
) else (
    echo [OK] Nginx configured successfully
)
goto :eof

:show_status
echo.
echo Current deployment:
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "cd %REMOTE_PATH% && git log --oneline -1"
echo.
echo Container status:
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "sudo docker ps --filter 'name=nexus' --format 'table {{.Names}}\t{{.Status}}'"
echo.
echo Health checks:
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:8090/health.php > /dev/null && echo '  API:        OK' || echo '  API:        FAILED'"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:3000/ > /dev/null && echo '  Frontend:   OK' || echo '  Frontend:   FAILED'"
ssh -i "%SSH_KEY%" %SERVER_USER%@%SERVER_HOST% "curl -sf http://127.0.0.1:3003/ > /dev/null && echo '  Sales Site: OK' || echo '  Sales Site: FAILED'"
goto :eof

:end
endlocal
