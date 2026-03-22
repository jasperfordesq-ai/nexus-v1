# Project NEXUS Mobile — Security Guide

> Last updated: 2026-03-22

This document describes the mobile app's security posture, identifies known gaps, and provides implementation guidance for hardening.

---

## 1. Current Security Posture

### What is already in place

| Area | Implementation | Where |
|------|---------------|-------|
| **Secure token storage** | `expo-secure-store` (Keychain on iOS, EncryptedSharedPreferences on Android) | `lib/storage.ts` |
| **HTTPS enforcement** | `EXPO_PUBLIC_API_URL` always uses `https://` in production | `.env.example`, `lib/env.ts` |
| **Tenant isolation** | Every API request includes the tenant slug; backend enforces row-level isolation | `lib/api/` |
| **401 auto-logout** | Axios interceptor catches 401 and clears tokens, redirecting to the login screen | `lib/api/client.ts` |
| **Rate limit awareness** | 429 responses are surfaced to the user; retry logic is exponential-backoff only | `lib/api/client.ts` |
| **No secrets in code** | All credentials come from `EXPO_PUBLIC_*` env vars; `.env.local` is gitignored | `.gitignore` |
| **Input validation** | Zod schemas validate all form data before submission | `lib/` schema files |

### Known gaps / future hardening

- Certificate pinning is not yet implemented (see Section 2)
- App integrity attestation is not yet implemented (see Section 3)
- Access tokens are long-lived (24 h); short-lived tokens with refresh rotation are recommended (see Section 5)

---

## 2. Certificate Pinning

Certificate pinning prevents MITM (man-in-the-middle) attacks by refusing TLS connections to any certificate not explicitly trusted — even if it chains to a trusted root CA.

### When to implement

Implement before submitting to the app stores if the app handles sensitive financial data (time credit transfers) or health data.

### How to implement in Expo managed workflow

Expo managed workflow does not support native `NSURLSession` / `OkHttp` pinning directly. Use `expo-build-properties` to inject native network security config.

**Step 1 — Install the plugin:**

```bash
npx expo install expo-build-properties
```

**Step 2 — Add to `app.json`:**

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

**Step 3 — Create `android-network-security-config.xml` at the project root:**

```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
  <domain-config cleartextTrafficPermitted="false">
    <domain includeSubdomains="true">api.project-nexus.ie</domain>
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

**Step 4 — iOS ATS (App Transport Security):**

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

| Token | Lifetime | Storage |
|-------|----------|---------|
| Access token | 24 hours | `expo-secure-store` |
| No refresh token | — | — |

### Recommended: short-lived access tokens + refresh rotation

Short-lived access tokens (15–60 minutes) reduce the window of exposure if a token is stolen. Pair with a refresh token that is rotated on every use.

**Target lifecycle:**

| Token | Lifetime | Notes |
|-------|----------|-------|
| Access token | 15 minutes | JWT, signed by backend |
| Refresh token | 30 days | Opaque, single-use, rotated on every refresh |

**Refresh flow implementation:**

```typescript
// lib/api/client.ts — add to Axios response interceptor
import * as SecureStore from 'expo-secure-store';

axiosInstance.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      try {
        const refreshToken = await SecureStore.getItemAsync('refresh_token');
        if (!refreshToken) throw new Error('No refresh token');

        const { data } = await axiosInstance.post('/auth/refresh', { refresh_token: refreshToken });

        await SecureStore.setItemAsync('access_token', data.access_token);
        await SecureStore.setItemAsync('refresh_token', data.refresh_token); // rotated

        originalRequest.headers['Authorization'] = `Bearer ${data.access_token}`;
        return axiosInstance(originalRequest);
      } catch {
        // Refresh failed — force logout
        await SecureStore.deleteItemAsync('access_token');
        await SecureStore.deleteItemAsync('refresh_token');
        // Redirect to login via router
      }
    }
    return Promise.reject(error);
  }
);
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
