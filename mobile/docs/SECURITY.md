# Project NEXUS Mobile — Security Guide

> Last updated: 2026-06-05

This document describes the mobile app's security posture, identifies known gaps, and provides implementation guidance for hardening.

---

## 1. Current Security Posture

### What is already in place

| Area | Implementation | Where |
|------|---------------|-------|
| **Secure token storage** | `expo-secure-store` (Keychain on iOS, EncryptedSharedPreferences on Android) | `lib/storage.ts` |
| **HTTPS enforcement** | `EXPO_PUBLIC_API_URL` always uses `https://` in production | `.env.example`, `lib/env.ts` |
| **Tenant isolation** | Every API request includes the tenant slug; backend enforces row-level isolation | `lib/api/` |
| **401 refresh + auto-logout** | Fetch client attempts one refresh-token rotation, retries the original request, then clears credentials and redirects to login if refresh fails | `lib/api/client.ts`, `lib/context/AuthContext.tsx` |
| **Rate limit awareness** | Non-2xx API responses, including 429, are surfaced through typed `ApiResponseError` objects | `lib/api/client.ts` |
| **No secrets in code** | All credentials come from `EXPO_PUBLIC_*` env vars; `.env.local` is gitignored | `.gitignore` |
| **Auth input validation** | Zod schemas validate login, registration, forgot-password, and reset-password forms before submission | `app/(auth)/*.tsx` |
| **Android certificate pinning** | Android release builds pin `api.project-nexus.ie` through the network security config injected by `expo-build-properties` | `android-network-security-config.xml`, `android/app/src/main/res/xml/network_security_config.xml`, `lib/security/pinning.ts` |

### Known gaps / future hardening

- iOS uses ATS HTTPS enforcement but does not yet enforce strict SHA-256 certificate pinning (see Section 2)
- Play Integrity / App Attest server-backed attestation is not yet implemented (see Section 3)
- Extend schema validation beyond auth forms where native forms accept high-risk input

---

## 2. Certificate Pinning

Certificate pinning prevents MITM (man-in-the-middle) attacks by refusing TLS connections to any certificate not explicitly trusted — even if it chains to a trusted root CA.

### Current implementation

Android pinning is implemented for `api.project-nexus.ie` through the network security config injected by `expo-build-properties`. The checked-in root config is `android-network-security-config.xml`; the generated native config is `android/app/src/main/res/xml/network_security_config.xml`.

iOS currently relies on App Transport Security (ATS) HTTPS enforcement. Strict iOS SHA-256 certificate pinning still requires TrustKit or a similar native module in a prebuild/bare workflow.

### How to maintain Android pinning

**Step 1 - Keep the plugin installed:**

```bash
npx expo install expo-build-properties
```

**Step 2 - Keep `app.json` wired to the root config:**

```json
{
  "expo": {
    "plugins": [
      [
        "expo-build-properties",
        {
          "android": {
            "networkSecurityConfig": "./android-network-security-config.xml"
          }
        }
      ]
    ]
  }
}
```

**Step 3 - Refresh `android-network-security-config.xml` before pin expiry or certificate rotation:**

```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
  <domain-config cleartextTrafficPermitted="false">
    <domain includeSubdomains="false">api.project-nexus.ie</domain>
    <pin-set expiration="2027-01-01">
      <!-- Primary certificate SHA-256 fingerprint (base64) -->
      <!-- Run: openssl s_client -connect api.project-nexus.ie:443 | openssl x509 -pubkey -noout | openssl pkey -pubin -outform DER | openssl dgst -sha256 -binary | base64 -->
      <pin digest="SHA-256">REPLACE_WITH_REAL_FINGERPRINT=</pin>
      <!-- Backup pin — a different cert you control, for rotation -->
      <pin digest="SHA-256">REPLACE_WITH_BACKUP_FINGERPRINT=</pin>
    </pin-set>
  </domain-config>
</network-security-config>
```

**Step 4 - iOS ATS (App Transport Security):**

iOS enforces HTTPS by default via ATS. To add certificate pinning on iOS you need a bare workflow or a custom native module. For managed workflow, ATS ensures HTTPS but cannot pin specific certificates. Options:

- Use `react-native-ssl-pinning` (requires bare/prebuild workflow)
- Or rely on ATS + your CA chain for managed workflow

**Important — pin rotation:**
- Always include at least two pins (primary + backup)
- Set an `expiration` date and rotate before it lapses
- A failed pin with no backup = the app cannot connect to the API

---

## 3. App Integrity Checking

App integrity attestation lets the backend verify that requests come from an unmodified, genuine copy of the app — not a rooted device, an emulator, or a repackaged APK.

### When this matters

- When you want to prevent abuse of the API from modified clients
- When regulatory requirements mandate attestation (e.g., financial services)
- When implementing anti-bot measures for registration/login

### Android — Play Integrity API

Google Play Integrity API replaces the deprecated SafetyNet.

**High-level flow:**

1. Backend generates a nonce and sends it to the mobile app
2. App calls `IntegrityManager.requestIntegrityToken(nonce)`
3. App sends the token to the backend
4. Backend verifies the token with Google's servers (server-to-server call)
5. Backend checks `deviceIntegrity`, `appIntegrity`, and `accountDetails` claims

**Adding to the app (bare/prebuild workflow):**

```bash
# Install the community module
npm install react-native-google-play-integrity
```

```typescript
import { checkPlayIntegrity } from 'react-native-google-play-integrity';

async function getIntegrityToken(nonce: string): Promise<string> {
  const result = await checkPlayIntegrity({
    nonce,
    cloudProjectNumber: process.env.EXPO_PUBLIC_GCP_PROJECT_NUMBER!,
  });
  return result.token;
}
```

**Backend verification** — POST to `https://playintegrity.googleapis.com/v1/{package}:decodeIntegrityToken` with the token using a service account.

### iOS — DeviceCheck / App Attest

Apple's App Attest (iOS 14+) is more robust than DeviceCheck.

**High-level flow:**

1. Backend generates a challenge
2. App calls `DCAppAttestService.shared.attestKey(keyId, clientDataHash: challenge)`
3. App sends the attestation object to the backend
4. Backend verifies with Apple's servers
5. On subsequent requests, app generates assertions with `generateAssertion`

**Adding to the app (bare/prebuild workflow):**

```typescript
import { NativeModules } from 'react-native';
// Use react-native-ios-appattest or a custom native module
const { AppAttest } = NativeModules;

async function attestDevice(challenge: string): Promise<string> {
  const keyId = await AppAttest.generateKey();
  const attestation = await AppAttest.attest(keyId, challenge);
  return attestation;
}
```

**Managed workflow limitation:** Both Play Integrity and App Attest require native code. You must use `expo prebuild` (bare workflow) or an EAS custom development client.

---

## 4. Sensitive Data Handling

### Secure storage — what goes where

| Data | Storage | Reason |
|------|---------|--------|
| Access token | `expo-secure-store` | Encrypted at rest, hardware-backed on supported devices |
| Refresh token | `expo-secure-store` | Same as above |
| Tenant slug | `expo-secure-store` | Avoids leaking tenant identity via logs |
| User preferences (theme, language) | `AsyncStorage` | Non-sensitive, can be cleared without security impact |
| Cached API responses | `AsyncStorage` (non-PII only) | Strip PII before caching |

### What NOT to store in AsyncStorage

AsyncStorage is **unencrypted** and accessible on rooted/jailbroken devices. Never store:

- Authentication tokens of any kind
- Passwords or PINs
- Full name, email, or government ID data
- Payment or financial information
- Any data classified as PII under GDPR

### Logging rules

- Never log auth tokens, even at `debug` level
- Strip PII from Sentry events using `beforeSend`:

```typescript
import * as Sentry from '@sentry/react-native';

Sentry.init({
  dsn: process.env.EXPO_PUBLIC_SENTRY_DSN,
  beforeSend(event) {
    // Strip authorization headers from breadcrumbs
    if (event.breadcrumbs) {
      event.breadcrumbs.values = event.breadcrumbs.values?.map((b) => {
        if (b.data?.['Authorization']) {
          b.data['Authorization'] = '[Filtered]';
        }
        return b;
      });
    }
    return event;
  },
});
```

---

## 5. Authentication Hardening

### Current token lifecycle

The mobile app stores both access and refresh tokens in `expo-secure-store`. On a 401, `lib/api/client.ts` posts the stored refresh token to `/api/auth/refresh-token`, saves the rotated token values when returned, and retries the original request once. If refresh fails, the app clears stored credentials and redirects to login through `AuthContext`.

### Recommended hardening

Keep backend access tokens short-lived (15-60 minutes) and rotate refresh tokens on every use. The client already supports a rotated `refresh_token` field in the refresh response.

**Target lifecycle:**

| Token | Lifetime | Notes |
|-------|----------|-------|
| Access token | 15 minutes | JWT, signed by backend |
| Refresh token | 30 days | Opaque, single-use, rotated on every refresh |

**Refresh flow implementation:**

```typescript
// lib/api/client.ts - existing fetch-client refresh shape
import * as SecureStore from 'expo-secure-store';
import { API_BASE_URL } from '@/lib/constants';

const refreshToken = await SecureStore.getItemAsync('nexus_refresh_token');
const response = await fetch(`${API_BASE_URL}/api/auth/refresh-token`, {
  method: 'POST',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  body: JSON.stringify({ refresh_token: refreshToken }),
});
const data = await response.json();
await SecureStore.setItemAsync('nexus_auth_token', data.access_token);
if (data.refresh_token) {
  await SecureStore.setItemAsync('nexus_refresh_token', data.refresh_token);
}
```

### Biometric lock (optional enhancement)

For high-value actions (time credit transfers), prompt the user to re-authenticate with Face ID / fingerprint before proceeding:

```typescript
import * as LocalAuthentication from 'expo-local-authentication';

async function requireBiometric(): Promise<boolean> {
  const result = await LocalAuthentication.authenticateAsync({
    promptMessage: 'Confirm your identity to transfer time credits',
    cancelLabel: 'Cancel',
    disableDeviceFallback: false,
  });
  return result.success;
}
```

Install with: `npx expo install expo-local-authentication`

---

## References

- [OWASP Mobile Security Testing Guide](https://owasp.org/www-project-mobile-security-testing-guide/)
- [Expo Security Considerations](https://docs.expo.dev/guides/security/)
- [Google Play Integrity API](https://developer.android.com/google/play/integrity)
- [Apple App Attest](https://developer.apple.com/documentation/devicecheck/establishing_your_app_s_integrity)
- [expo-build-properties](https://docs.expo.dev/versions/latest/sdk/build-properties/)
