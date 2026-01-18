# Build APK and Install on Your Phone

## Prerequisites (One-Time Setup)

### 1. Install Node.js
- Download from https://nodejs.org/ (LTS version)
- Run the installer, accept defaults
- Verify: `node --version`

### 2. Install Android Studio
- Download from https://developer.android.com/studio
- Run installer, accept defaults
- On first launch, let it download the Android SDK (takes 10-15 min)
- Note the SDK location (usually `C:\Users\YourName\AppData\Local\Android\Sdk`)

### 3. Install Java JDK 17
- Download from https://adoptium.net/ (Temurin 17 LTS)
- Run installer, accept defaults
- Verify: `java --version`

### 4. Set Environment Variables (if builds fail)
Add these to your System Environment Variables:
```
ANDROID_HOME = C:\Users\YourName\AppData\Local\Android\Sdk
JAVA_HOME = C:\Program Files\Eclipse Adoptium\jdk-17.x.x-hotspot
```

---

## Build the APK

### Option A: One-Click Build (Recommended)

Double-click `build-apk.bat` in this folder.

### Option B: Manual Commands

Open a terminal in this folder and run:

```bash
# 1. Install dependencies (first time only)
npm install

# 2. Add Android platform (first time only)
npx cap add android

# 3. Sync configuration
npx cap sync android

# 4. Build the APK
cd android
.\gradlew.bat assembleDebug
cd ..

# 5. Copy APK to downloads folder
node scripts/copy-apk.js
```

The APK will be at: `httpdocs/downloads/nexus-latest.apk`

---

## Upload to Your Live Site

Upload the APK file to your server:

**Source:** `httpdocs/downloads/nexus-latest.apk`

**Destination:** `https://hour-timebank.ie/downloads/nexus-latest.apk`

Upload methods:
- FTP/SFTP client (FileZilla, WinSCP)
- Hosting control panel file manager
- Git push (if downloads folder is in repo)

---

## Install on Your Android Phone

1. Open Chrome on your phone
2. Go to `https://hour-timebank.ie/mobile-download`
3. Tap **Download APK**
4. When download completes, tap the notification
5. If prompted:
   - Tap **Settings**
   - Enable **Install from unknown sources** for Chrome
   - Go back and tap **Install**
6. Open the app from your home screen

---

## Quick Command Reference

| Action | Command |
|--------|---------|
| Install dependencies | `npm install` |
| Add Android platform | `npx cap add android` |
| Sync changes | `npx cap sync android` |
| Build debug APK | `cd android && .\gradlew.bat assembleDebug` |
| Build release APK | `cd android && .\gradlew.bat assembleRelease` |
| Copy APK to downloads | `node scripts/copy-apk.js` |
| Open in Android Studio | `npx cap open android` |

---

## Troubleshooting

### "ANDROID_HOME is not set"
Set the environment variable to your SDK location:
```
C:\Users\YourName\AppData\Local\Android\Sdk
```

### "Could not find tools.jar"
Install JDK 17 from https://adoptium.net/ and set JAVA_HOME.

### "gradlew is not recognized"
Make sure you're in the `android` subfolder when running gradlew.

### Build succeeds but APK not found
Check `android/app/build/outputs/apk/debug/` for the APK file.

### App opens but shows blank/loading screen
- Check that `capacitor.config.ts` has the correct URL
- Make sure your website is accessible (not localhost)
- Check your phone has internet connection

---

## Updating the App

After making changes to your website:

1. The app will automatically show the latest version (it loads your live site)
2. No rebuild needed for website changes

To update native features or config:

```bash
npx cap sync android
cd android && .\gradlew.bat assembleDebug && cd ..
node scripts/copy-apk.js
```

Then upload the new APK to your server.

---

## File Locations

| File | Purpose |
|------|---------|
| `capacitor.config.ts` | App configuration (URL, name, plugins) |
| `package.json` | Dependencies and scripts |
| `build-apk.bat` | One-click build script |
| `scripts/copy-apk.js` | Copies built APK to downloads folder |
| `android/` | Generated Android Studio project |
| `www/index.html` | Fallback loading screen |
