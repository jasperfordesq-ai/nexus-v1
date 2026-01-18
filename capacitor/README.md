# Project NEXUS - Capacitor Mobile App

This folder contains the Capacitor configuration to wrap your existing web app as a native mobile app.

## Prerequisites

1. **Node.js** (v18 or higher) - https://nodejs.org/
2. **Android Studio** (for Android builds) - https://developer.android.com/studio
3. **Xcode** (for iOS builds, macOS only) - App Store

## Quick Start

### 1. Install Dependencies

```bash
cd capacitor
npm install
```

### 2. Configure Your Site URL

Edit `capacitor.config.ts` and update the `server.url` to your actual domain:

```typescript
server: {
  url: 'https://your-actual-domain.com',  // <-- Change this!
  cleartext: false  // Set to false for HTTPS
}
```

### 3. Add Android Platform

```bash
npm run add:android
```

This creates the `android/` folder with an Android Studio project.

### 4. Build the APK

```bash
npm run build:apk
```

This will:
1. Sync your Capacitor config to the Android project
2. Build a debug APK
3. Copy it to `httpdocs/downloads/nexus-latest.apk`

### 5. Test on Your Phone

The APK is now available at `/downloads/nexus-latest.apk` on your website.

## Build Commands

| Command | Description |
|---------|-------------|
| `npm run add:android` | Add Android platform (first time only) |
| `npm run add:ios` | Add iOS platform (first time only) |
| `npm run sync` | Sync config changes to native projects |
| `npm run build:apk` | Build debug APK and copy to downloads |
| `npm run build:android:release` | Build release APK (for Play Store) |
| `npm run open:android` | Open project in Android Studio |
| `npm run open:ios` | Open project in Xcode |

## How It Works

```
User opens app
      |
      v
+------------------+
|  Native Shell    |  <-- Capacitor provides this
|  +------------+  |
|  |  WebView   |  |  <-- Your website loads here
|  |  (your     |  |
|  |   site)    |  |
|  +------------+  |
|                  |
|  Native APIs     |  <-- Haptics, status bar, etc.
+------------------+
```

The app is essentially a native wrapper around your existing website. Your existing `nexus-capacitor-bridge.js` automatically detects when running inside Capacitor and enables native features.

## Customization

### App Icon

Replace the icons in `android/app/src/main/res/mipmap-*` folders.

Sizes needed:
- mipmap-mdpi: 48x48
- mipmap-hdpi: 72x72
- mipmap-xhdpi: 96x96
- mipmap-xxhdpi: 144x144
- mipmap-xxxhdpi: 192x192

### Splash Screen

Configure in `capacitor.config.ts` under `plugins.SplashScreen`.

### Status Bar

Configure in `capacitor.config.ts` under `plugins.StatusBar`.

## Signing for Play Store

For release builds, you'll need to create a keystore:

```bash
keytool -genkey -v -keystore nexus-release-key.keystore -alias nexus -keyalg RSA -keysize 2048 -validity 10000
```

Then update `capacitor.config.ts`:

```typescript
android: {
  buildOptions: {
    keystorePath: 'path/to/nexus-release-key.keystore',
    keystorePassword: 'your-password',
    keystoreAlias: 'nexus',
    keystoreAliasPassword: 'your-alias-password',
    releaseType: 'APK'
  }
}
```

## Troubleshooting

### "Could not find Android SDK"
Make sure Android Studio is installed and ANDROID_HOME environment variable is set.

### Build fails with Java errors
Install JDK 17 and set JAVA_HOME environment variable.

### APK is large
The initial APK will be larger due to WebView dependencies. This is normal.
