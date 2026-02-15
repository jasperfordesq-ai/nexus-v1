@echo off
REM =============================================================================
REM Project NEXUS - Docker Database Restore Script (Windows)
REM =============================================================================
REM Restores a MySQL dump from backups/db/
REM Usage: scripts\docker-restore.bat [backup-file.sql]
REM =============================================================================

setlocal enabledelayedexpansion

REM Configuration
set CONTAINER=nexus-php-db
set DB_NAME=nexus
set DB_USER=root
set DB_PASS=nexus_root_secret
set BACKUP_DIR=%~dp0..\backups\db

REM Check for backup file argument
if "%~1"=="" (
    echo.
    echo Usage: scripts\docker-restore.bat [backup-file.sql]
    echo.
    echo Available backups:
    echo.
    dir /b "%BACKUP_DIR%\*.sql" 2>nul
    if errorlevel 1 echo   No backups found in %BACKUP_DIR%
    echo.
    exit /b 1
)

REM Determine backup file path
set BACKUP_FILE=%~1
if not exist "%BACKUP_FILE%" (
    set BACKUP_FILE=%BACKUP_DIR%\%~1
)
if not exist "%BACKUP_FILE%" (
    echo ERROR: Backup file not found: %~1
    exit /b 1
)

echo.
echo =============================================================================
echo   NEXUS Database Restore
echo =============================================================================
echo.
echo Container:   %CONTAINER%
echo Database:    %DB_NAME%
echo Backup:      %BACKUP_FILE%
echo.
echo WARNING: This will OVERWRITE all data in the %DB_NAME% database!
echo.

REM Confirmation
set /p CONFIRM="Type YES to confirm restore: "
if /i not "%CONFIRM%"=="YES" (
    echo.
    echo Restore cancelled.
    exit /b 0
)

REM Check if container is running
docker ps --filter "name=%CONTAINER%" --filter "status=running" | findstr %CONTAINER% >nul
if errorlevel 1 (
    echo.
    echo ERROR: Container %CONTAINER% is not running.
    echo Run 'docker compose up -d' first.
    exit /b 1
)

echo.
echo Restoring database...

REM Drop and recreate database, then restore
docker compose --env-file .env.docker exec -T db mysql -u%DB_USER% -p%DB_PASS% -e "DROP DATABASE IF EXISTS %DB_NAME%; CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if errorlevel 1 (
    echo ERROR: Failed to recreate database!
    exit /b 1
)

REM Restore from backup
type "%BACKUP_FILE%" | docker compose --env-file .env.docker exec -T db mysql -u%DB_USER% -p%DB_PASS% %DB_NAME%

if errorlevel 1 (
    echo.
    echo ERROR: Restore failed!
    exit /b 1
)

echo.
echo =============================================================================
echo   Restore Complete!
echo =============================================================================
echo Database %DB_NAME% has been restored from %BACKUP_FILE%
echo.

endlocal
