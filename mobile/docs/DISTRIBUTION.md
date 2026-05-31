# Timebank Global mobile distribution

This file records the release identity and distribution decisions for the native mobile app.

## App identity

| Item | Value |
|------|-------|
| Store listing title | Timebank Global |
| Installed app name | Timebank Global |
| Publisher / developer | Jasper Ford - Project NEXUS |
| Android package ID | ie.project.nexus |
| iOS bundle ID | ie.project.nexus |
| Download site | https://mobile.project-nexus.ie |

The package and bundle IDs are permanent release identifiers. Do not change them after public distribution unless intentionally shipping a separate app.

## Domain roles

| Domain | Purpose |
|--------|---------|
| https://mobile.project-nexus.ie | Public mobile download landing page only |
| https://app.project-nexus.ie | Web app and app/deep-link target |
| https://api.project-nexus.ie | Production API |

The mobile download domain is not an app-link domain and does not need API interaction.

## Android packaging

Use both Android package formats:

| Channel | Format | EAS profile |
|---------|--------|-------------|
| Website download | Signed APK | `website` |
| Google Play | Android App Bundle (AAB) | `production` |

Commands:

```bash
cd mobile
npm run build:android:website
npm run build:android:play
```

The profile name describes the distribution channel, not the target device. The `website` profile is still an Android build; it produces a signed APK because website/direct Android installs need APK files. The `production` profile produces an AAB because Google Play expects Android App Bundles.

## EAS project and credentials

| Item | Value |
|------|-------|
| Expo/EAS account | `timebank-global` |
| EAS project | `@timebank-global/nexus-mobile` |
| EAS project ID | `90f411f3-b6b4-4251-85ad-00937bb0513d` |
| Android application identifier | `ie.project.nexus` |
| EAS build credential | `b8UXpzut1O` |
| Keystore type | EAS-managed JKS |
| Keystore SHA1 | `ED:A9:48:A0:C2:69:42:9B:8A:84:80:35:35:13:03:74:57:49:AA:79` |
| Keystore SHA256 | `02:F5:0F:55:6C:C4:1B:78:69:97:6C:D6:E5:5E:5A:CC:07:B7:07:53:C5:57:10:C8:A8:48:74:78:46:C8:94:C2` |

The EAS-managed keystore is shared by Android builds for `ie.project.nexus`. It is not specific to the `website` profile. Both the direct-download APK and the Google Play AAB use the same Android application identifier and signing identity.

Configured EAS credentials:

- Android keystore: configured and EAS-managed.
- Push Notifications (FCM V1): Google Service Account Key assigned to `ie.project.nexus`.
- Push Notifications (FCM Legacy): intentionally empty.
- Play Store submissions Google Service Account: not configured yet. Configure this later when submitting directly from EAS to Google Play.

EAS environment variables:

| Environment | Variable | Purpose |
|-------------|----------|---------|
| `preview` | `GOOGLE_SERVICES_JSON` secret file | Supplies Firebase config for `website` APK builds |
| `production` | `GOOGLE_SERVICES_JSON` secret file | Supplies Firebase config for Play Store AAB builds |

Credential setup commands used:

```bash
cd mobile
npx eas-cli@latest credentials -p android
npx eas-cli@latest env:create --environment production --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json
npx eas-cli@latest env:create --environment preview --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json
```

## Push notification prerequisites

Before release builds:

1. Create or update the Firebase Android app for package `ie.project.nexus`.
2. Download the matching `google-services.json`.
3. Place it at `mobile/google-services.json` for local/EAS builds.
4. Configure EAS Android FCM credentials.
5. Confirm the EAS project ID is available through `EAS_PROJECT_ID` or `EXPO_PUBLIC_EAS_PROJECT_ID`.
6. Build a new native binary; push, camera, and location changes cannot be delivered by OTA alone.

## Sentry source maps

The EAS build profiles currently set `SENTRY_DISABLE_AUTO_UPLOAD=true` so Sentry source-map upload cannot block APK/AAB packaging while Sentry organization/project/token values are not configured. Runtime Sentry reporting can still be configured separately. Re-enable source-map upload only after setting `SENTRY_ORG`, `SENTRY_PROJECT`, and `SENTRY_AUTH_TOKEN` in EAS.

## Website download page contents

The page at `https://mobile.project-nexus.ie` should include:

- Android APK download link.
- Version number and release date.
- File size and SHA-256 checksum.
- Short install instructions for Android "install unknown apps".
- Link to the Google Play listing when available.
- Privacy policy, support contact, and source/license links.
- A clear note that direct website download is Android-only.
