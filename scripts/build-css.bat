@echo off
REM ===========================================
REM NEXUS TimeBank - CSS Build Pipeline
REM ===========================================
REM Runs PurgeCSS to remove unused CSS
REM Usage: scripts\build-css.bat
REM ===========================================

echo.
echo ==========================================
echo   NEXUS CSS Build Pipeline
echo ==========================================
echo.

REM Check if node is available
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Node.js not found. Please install Node.js first.
    exit /b 1
)

REM Check if purgecss is installed
if not exist "node_modules\purgecss" (
    echo [ERROR] PurgeCSS not installed. Run: npm install purgecss --save-dev
    exit /b 1
)

echo [1/2] Running PurgeCSS...
echo      Scanning PHP/JS files for used CSS classes...
echo      Processing 260+ CSS files...
echo.

REM Run PurgeCSS
node node_modules\purgecss\bin\purgecss.js --config .\purgecss.config.js

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] PurgeCSS failed!
    exit /b 1
)

echo.
echo [2/2] Build complete!
echo.

REM Calculate savings (optional - requires PowerShell)
echo Calculating size savings...
powershell -Command "& {$before = (Get-ChildItem httpdocs\assets\css\*.css -Exclude purged | Measure-Object -Property Length -Sum).Sum; $after = (Get-ChildItem httpdocs\assets\css\purged\*.css | Measure-Object -Property Length -Sum).Sum; $saved = $before - $after; $percent = [math]::Round(($saved / $before) * 100, 1); Write-Host \"  Before: $([math]::Round($before/1MB, 2)) MB\" -ForegroundColor Yellow; Write-Host \"  After:  $([math]::Round($after/1MB, 2)) MB\" -ForegroundColor Green; Write-Host \"  Saved:  $([math]::Round($saved/1MB, 2)) MB ($percent%%)\" -ForegroundColor Cyan}"

echo.
echo ==========================================
echo   CSS Build Complete!
echo ==========================================
echo.
echo Purged CSS files are in: httpdocs\assets\css\purged\
echo.

exit /b 0
