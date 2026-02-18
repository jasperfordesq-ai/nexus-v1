@echo off
REM ===========================================
REM NEXUS TimeBank - Windows Deployment Script
REM ===========================================
REM Requires: WSL, Git Bash, or rsync for Windows
REM ===========================================

REM Configuration - UPDATE THESE VALUES
set SSH_USER=your-ssh-username
set SSH_HOST=your-server-ip
set SSH_PORT=22
set REMOTE_PATH=/var/www/vhosts/yourdomain.com

REM Get project root (parent of scripts folder)
set SCRIPT_DIR=%~dp0
set PROJECT_ROOT=%SCRIPT_DIR%..

echo =========================================
echo   NEXUS TimeBank Deployment
echo =========================================
echo.
echo Server:  %SSH_USER%@%SSH_HOST%:%SSH_PORT%
echo Path:    %REMOTE_PATH%
echo.

REM Check if running in Git Bash or WSL
where rsync >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo rsync not found in PATH.
    echo.
    echo Options:
    echo   1. Run this in Git Bash: bash scripts/deploy.sh
    echo   2. Run in WSL: wsl bash scripts/deploy.sh
    echo   3. Install cwRsync for Windows
    echo.
    pause
    exit /b 1
)

echo Starting deployment...
echo.

rsync -avz --progress ^
    --exclude-from="%PROJECT_ROOT%\.deployignore" ^
    -e "ssh -p %SSH_PORT%" ^
    "%PROJECT_ROOT%/" ^
    "%SSH_USER%@%SSH_HOST%:%REMOTE_PATH%/"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo =========================================
    echo   Deployment Complete!
    echo =========================================
) else (
    echo.
    echo Deployment failed!
)

pause
