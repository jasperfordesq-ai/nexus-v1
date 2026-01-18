@echo off
echo =========================================
echo   Project NEXUS - Build Android APK
echo =========================================
echo.

REM Check if node_modules exists
if not exist "node_modules" (
    echo Installing dependencies...
    call npm install
    echo.
)

REM Check if android folder exists
if not exist "android" (
    echo Adding Android platform...
    call npx cap add android
    echo.
)

echo Syncing Capacitor...
call npx cap sync android
echo.

echo Building APK...
cd android
call gradlew.bat assembleDebug
cd ..
echo.

echo Copying APK to downloads folder...
call node scripts/copy-apk.js
echo.

echo =========================================
echo   Build Complete!
echo =========================================
echo.
echo The APK is available at:
echo   httpdocs/downloads/nexus-latest.apk
echo.
echo To install on your phone:
echo   1. Transfer the APK to your phone
echo   2. Open it and allow "Install from unknown sources"
echo   3. Install and enjoy!
echo.
pause
