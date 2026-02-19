@echo off
set JAVA_HOME=C:\Program Files\Eclipse Adoptium\jdk-17.0.17.10-hotspot
set ANDROID_HOME=%LOCALAPPDATA%\Android\Sdk
cd /d "c:\xampp\htdocs\staging\capacitor\android"
call gradlew.bat assembleDebug
cd ..
call node scripts/copy-apk.js
echo.
echo BUILD COMPLETE!
echo APK is at: httpdocs\downloads\nexus-latest.apk
