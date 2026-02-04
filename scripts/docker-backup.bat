@echo off
REM =============================================================================
REM Project NEXUS - Docker Database Backup Script (Windows)
REM =============================================================================
REM Creates a timestamped MySQL dump in backups/db/
REM Usage: scripts\docker-backup.bat
REM =============================================================================

setlocal enabledelayedexpansion

REM Configuration
set CONTAINER=nexus-mysql-db
set DB_NAME=nexus
set DB_USER=root
set DB_PASS=nexus_root_secret
set BACKUP_DIR=%~dp0..\backups\db

REM Create backup directory if it doesn't exist
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Generate timestamp
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /format:list') do set datetime=%%I
set TIMESTAMP=%datetime:~0,4%-%datetime:~4,2%-%datetime:~6,2%_%datetime:~8,2%%datetime:~10,2%%datetime:~12,2%
set BACKUP_FILE=%BACKUP_DIR%\nexus_%TIMESTAMP%.sql

echo.
echo =============================================================================
echo   NEXUS Database Backup
echo =============================================================================
echo.
echo Container:   %CONTAINER%
echo Database:    %DB_NAME%
echo Output:      %BACKUP_FILE%
echo.

REM Check if container is running
docker ps --filter "name=%CONTAINER%" --filter "status=running" | findstr %CONTAINER% >nul
if errorlevel 1 (
    echo ERROR: Container %CONTAINER% is not running.
    echo Run 'docker compose up -d' first.
    exit /b 1
)

REM Create backup
echo Creating backup...
docker compose exec -T db mysqldump -u%DB_USER% -p%DB_PASS% --single-transaction --routines --triggers %DB_NAME% > "%BACKUP_FILE%"

if errorlevel 1 (
    echo.
    echo ERROR: Backup failed!
    del "%BACKUP_FILE%" 2>nul
    exit /b 1
)

REM Get file size
for %%A in ("%BACKUP_FILE%") do set SIZE=%%~zA

echo.
echo =============================================================================
echo   Backup Complete!
echo =============================================================================
echo File: %BACKUP_FILE%
echo Size: %SIZE% bytes
echo.

endlocal
