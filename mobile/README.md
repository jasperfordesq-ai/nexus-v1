# Timebank Global - Mobile App

React Native (Expo) mobile client for the [Project NEXUS](https://github.com/jasperfordesq-ai/nexus-v1) timebanking platform.

Release identity, package IDs, and website/Play Store distribution decisions are recorded in [docs/DISTRIBUTION.md](docs/DISTRIBUTION.md).

**License:** AGPL-3.0-or-later (c) 2024-2026 Jasper Ford

---

## Prerequisites

- Node.js 20+
- Expo CLI through `npx expo` or the local npm scripts
- For Android: Android Studio + emulator, or a physical device with Expo Go
- For iOS: Xcode + Simulator (macOS only), or a physical device with Expo Go

---

## Setup

```bash
cd mobile
npm install   # .npmrc sets legacy-peer-deps=true automatically
cp .env.example .env.local
# Edit .env.local and set EXPO_PUBLIC_API_URL to your API endpoint
```

### Asset Placeholders

Before building (not required for Expo Go dev):

```bash
# Replace assets/ placeholders with real images:
#   icon.png          1024x1024
#   splash.png        1284x2778
#   adaptive-icon.png 1024x1024 (Android)
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
| Android emulator -> local native API | `http://10.0.2.2:8088` |
| iOS simulator -> local native API | `http://localhost:8088` |
| Local device on LAN | `http://<your-computer-ip>:8088` |

The default tenant (`EXPO_PUBLIC_DEFAULT_TENANT`) is `hour-timebank`; change this to test with a different tenant.

---

## Architecture

### Stack

| Concern | Solution |
|---------|----------|
| Framework | Expo SDK 54 (managed workflow) |
| Language | TypeScript strict |
| Navigation | Expo Router (file-based, like Next.js) |
| UI | HeroUI Native `^1.0.4` + Uniwind + Tailwind CSS 4 |
| Auth storage | `expo-secure-store` (encrypted at rest) |
| HTTP | Native `fetch` with typed wrapper (`lib/api/client.ts`) |
| State | React Context + hooks (no external state library) |
| Icons | `@expo/vector-icons` (Ionicons) |
| Authentication | Password + WebAuthn / Passkeys (platform authenticator) |
| Real-time messaging | Pusher WebSockets (private channels, end-to-end encrypted transport) |
| Push notifications | Firebase Cloud Messaging (FCM) via Expo Notifications |

### Directory Layout

```text
mobile/
├── app/                  # Expo Router routes (file = screen)
│   ├── _layout.tsx       # Root layout: providers + auth redirect
│   ├── index.tsx         # Loading splash while auth resolves
│   ├── (auth)/           # Login, register, tenant selection, password reset, verify email
│   ├── (tabs)/           # Main tab navigator (home, exchanges, members, messages, profile)
│   └── (modals)/         # Modal workflows: exchanges, groups, jobs, marketplace, federation, settings
├── components/           # Shared UI components
│   ├── ui/               # HeroUI Native-backed primitives and app wrappers
│   ├── federation/       # Federation directory and detail helpers
│   ├── marketplace/      # Marketplace cards and shared marketplace UI
│   ├── ExchangeCard.tsx
│   ├── MemberCard.tsx
│   ├── FeedItem.tsx
│   └── TenantBanner.tsx
└── lib/
    ├── api/              # Typed API modules
    ├── context/          # AuthContext, TenantContext, RealtimeContext
    ├── hooks/            # useAuth, useTenant, useApi, usePaginatedApi
    ├── storage.ts        # Secure storage wrapper
    └── constants.ts      # API URL, storage keys
```

### HeroUI Native

This app uses HeroUI Native, not HeroUI React web APIs. The root layout imports `global.css`, wraps the app in `GestureHandlerRootView`, and mounts `HeroUINativeProvider`. `global.css` imports Tailwind CSS, Uniwind, and `heroui-native/styles`.

Prefer the shared wrappers in `components/ui` for app screens:

- `Button`, `Input`, `Card`, `Badge`/`Chip`, `Toggle`, `Checkbox`, `BottomSheet`, `ActionSheet`, `LoadingSpinner`, and `EmptyState`
- Direct `heroui-native` primitives for dense compound layouts where the wrapper would fight the native component anatomy
- Native React Native primitives for layout, lists, media, maps, gesture surfaces, and platform APIs

See `docs/HEROUI_NATIVE_PARITY_AUDIT.md` and `docs/HEROUI_NATIVE_MIGRATION_NOTES.md` for the current parity matrix, intentional native-touch exceptions, and verification log.

### Auth Flow

1. App mounts and `AuthContext` reads the stored JWT from `expo-secure-store`.
2. If a token exists, `GET /api/v2/users/me` validates it and redirects to `/(tabs)/home`.
3. If no token exists or validation fails, the app redirects to `/(auth)/login`.
4. On login, credentials are posted, the JWT is stored, and the app redirects to tabs.
5. On 401 from any API call, credentials are cleared and the app redirects to login through `lib/api/client.ts`.

#### WebAuthn / Passkeys

The app supports passkey-based authentication via the device platform authenticator (Face ID, Touch ID, Windows Hello, etc.). The WebAuthn flow uses the platform credential manager; no third-party biometrics library is required. Passkeys are registered and verified through the standard NEXUS WebAuthn API endpoints (`/api/v2/webauthn/register/*` and `/api/v2/webauthn/authenticate/*`). See `lib/security/pinning.ts` for certificate pinning applied to WebAuthn and API calls.

### Multi-Tenancy

Every API request includes an `X-Tenant-Slug` header set by `lib/api/client.ts` from storage. `TenantContext` loads tenant config and branding on startup and exposes `hasFeature(key)` for conditional UI.

To switch tenant, call `setTenantSlug(slug)` from `useTenant()`.

---

## Verification

```bash
cd mobile
npm run type-check
npm test -- --runInBand --silent
```

Focused route/component tests are useful during migration work, but run the commands above before considering a HeroUI Native or parity pass complete. The Jest suite may emit known Uniwind/HeroUI Native test-environment warnings; document any command timeout or open-handle behavior in the parity audit.

---

## Production Build (EAS)

```bash
npm install -g eas-cli
eas login
npm run prepackage             # TypeScript, Jest, Expo Doctor
npm run build:android:website  # APK for website/internal tester downloads
npm run build:android:play     # AAB for Google Play
npm run submit:android:internal # Submit latest AAB to Play internal testing
```

Configure `eas.json` before submitting. See [Expo EAS docs](https://docs.expo.dev/eas/).

---

## Notes

- **No business logic in the app**: all logic lives in the PHP API.
- **Real-time messaging**: Pusher WebSocket channels are established after login and torn down on logout. Private channels use server-side auth (`/api/v2/pusher/auth`).
- **Push notifications**: FCM tokens are registered on login via `POST /api/v2/notifications/device-token` and deregistered on logout. Requires `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) in the project root before any EAS build.
- **AGPL-3.0 attribution** is displayed in the Profile/About screen as required by Section 7(b) and Section 13. The attribution notice must include a copyright notice and visible source repository link: <https://github.com/jasperfordesq-ai/nexus-v1>.
- Replace `assets/` placeholder images before any public build. When the generated notification icon is ready, save it as `assets/notification-icon.png` (96x96, white on transparent). The dynamic Expo config will use it automatically when the file exists.
- Add `google-services.json` at the project root before push-notification production builds. It is gitignored and will be added to native config automatically when present.
