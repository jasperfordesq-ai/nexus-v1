# Project NEXUS — Mobile App

React Native (Expo) mobile client for the [Project NEXUS](https://github.com/jasperfordesq-ai/nexus-v1) timebanking platform.

**License:** AGPL-3.0-or-later © 2024–2026 Jasper Ford

---

## Prerequisites

- Node.js 20+
- [Expo CLI](https://docs.expo.dev/get-started/installation/): `npm install -g expo-cli`
- For Android: Android Studio + emulator, or a physical device with Expo Go
- For iOS: Xcode + Simulator (macOS only), or a physical device with Expo Go

---

## Setup

```bash
cd mobile
npm install   # .npmrc sets legacy-peer-deps=true automatically
cp .env.example .env.local
# Edit .env.local — set EXPO_PUBLIC_API_URL to your API endpoint
```

### Asset placeholders

Before building (not required for Expo Go dev):

```bash
# Replace assets/ placeholders with real images:
#   icon.png          1024×1024
#   splash.png        1284×2778
#   adaptive-icon.png 1024×1024 (Android)
```

---

## Development

```bash
# Start Expo dev server (opens QR code)
cd mobile && npm start

# Run on Android emulator / connected device
npm run android

# Run on iOS simulator (macOS only)
npm run ios

# TypeScript check
npm run type-check
```

Scan the QR code with **Expo Go** on your phone, or press `a` / `i` in the terminal to open an emulator.

---

## API URL Configuration

The app reads `EXPO_PUBLIC_API_URL` from environment. Set this in `.env.local`:

| Target | URL |
|--------|-----|
| Production | `https://api.project-nexus.ie` |
| Android emulator → local Docker | `http://10.0.2.2:8090` |
| iOS simulator → local Docker | `http://localhost:8090` |

The default tenant (`EXPO_PUBLIC_DEFAULT_TENANT`) is `hour-timebank` — change this to test with a different tenant.

---

## Architecture

### Stack

| Concern | Solution |
|---------|----------|
| Framework | Expo SDK 52 (managed workflow) |
| Language | TypeScript strict |
| Navigation | Expo Router (file-based, like Next.js) |
| Auth storage | `expo-secure-store` (encrypted at rest) |
| HTTP | Native `fetch` with typed wrapper (`lib/api/client.ts`) |
| State | React Context + hooks (no external state library) |
| Icons | `@expo/vector-icons` (Ionicons) |
| Authentication | Password + WebAuthn / Passkeys (platform authenticator) |
| Real-time messaging | Pusher WebSockets (private channels, end-to-end encrypted transport) |
| Push notifications | Firebase Cloud Messaging (FCM) via Expo Notifications |

### Directory Layout

```
mobile/
├── app/                  # Expo Router routes (file = screen)
│   ├── _layout.tsx       # Root layout: providers + auth redirect
│   ├── index.tsx         # Loading splash while auth resolves
│   ├── (auth)/           # Unauthenticated screens (login, register)
│   ├── (tabs)/           # Main tab navigator (home, exchanges, members, messages, profile)
│   └── (modals)/         # Modal screens (new-exchange, exchange-detail)
├── components/           # Shared UI components
│   ├── ui/               # Primitives: Button, Card, Input, Avatar, LoadingSpinner
│   ├── ExchangeCard.tsx
│   ├── MemberCard.tsx
│   ├── FeedItem.tsx
│   └── TenantBanner.tsx
└── lib/
    ├── api/              # Typed API modules (client, auth, exchanges, …)
    ├── context/          # AuthContext, TenantContext
    ├── hooks/            # useAuth, useTenant, useApi
    ├── storage.ts        # Secure storage wrapper
    └── constants.ts      # API URL, storage keys
```

### Auth Flow

1. App mounts → `AuthContext` reads stored JWT from `expo-secure-store`
2. If token found → `GET /api/v2/users/me` to validate → redirect to `/(tabs)/home`
3. If no token or validation fails → redirect to `/(auth)/login`
4. On login → POST credentials → store JWT → redirect to tabs
5. On 401 from any API call → clear credentials → redirect to login (handled globally in `lib/api/client.ts`)

#### WebAuthn / Passkeys

The app supports passkey-based authentication via the device platform authenticator (Face ID, Touch ID, Windows Hello, etc.). The WebAuthn flow uses the platform's built-in credential manager — no third-party biometrics library is required. Passkeys are registered and verified through the standard NEXUS WebAuthn API endpoints (`/api/v2/webauthn/register/*` and `/api/v2/webauthn/authenticate/*`). See `lib/security/pinning.ts` for certificate-pinning applied to all WebAuthn and API calls.

### Multi-Tenancy

Every API request includes an `X-Tenant-Slug` header (set by `lib/api/client.ts` from storage). `TenantContext` loads the tenant's config and branding on startup and exposes `hasFeature(key)` for conditional UI.

To switch tenant: call `setTenantSlug(slug)` from `useTenant()`.

---

## Production Build (EAS)

```bash
npm install -g eas-cli
eas login
eas build --platform android   # APK/AAB
eas build --platform ios       # IPA
eas submit --platform android  # Submit to Play Store
eas submit --platform ios      # Submit to App Store
```

Configure `eas.json` before submitting. See [Expo EAS docs](https://docs.expo.dev/eas/).

---

## Notes

- **No business logic in the app** — all logic lives in the PHP API
- **Real-time messaging** — Pusher WebSocket channels are established after login and torn down on logout. Private channels use server-side auth (`/api/v2/pusher/auth`).
- **Push notifications** — FCM tokens are registered on login via `POST /api/v2/notifications/device-token` and deregistered on logout. Requires `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) in the project root before any EAS build.
- **AGPL-3.0 attribution** is displayed in the Profile/About screen as required by Section 7(b) and Section 13. The attribution notice must include: (a) a copyright notice (© 2024–2026 Jasper Ford), and (b) a visible link to the public source repository at <https://github.com/jasperfordesq-ai/nexus-v1>. AGPL Section 13 specifically requires that network-accessible software provide a prominent "Corresponding Source" link — the GitHub repo URL satisfies this requirement.
- Replace `assets/` placeholder images before any public build
