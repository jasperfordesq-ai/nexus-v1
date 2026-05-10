// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE: string;
  readonly VITE_TENANT_ID: string;
  readonly VITE_GIPHY_API_KEY?: string;
  readonly VITE_GOOGLE_MAPS_API_KEY?: string;
  readonly VITE_GOOGLE_MAPS_ENABLED?: string;
  readonly VITE_SENTRY_DSN?: string;
  readonly VITE_STRIPE_PUBLISHABLE_KEY?: string;
  readonly DEV: boolean;
  readonly PROD: boolean;
  readonly MODE: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

// Build-time constants injected by vite.config.ts
declare const __BUILD_COMMIT__: string;
declare const __BUILD_TIME__: string;

// Global window extensions kept for legacy callers. The update-banner
// globals (__nexus_updateSW, __nexus_updatePending) were removed when the
// banner itself was deleted in 2026-05-10 — deploys propagate via
// NetworkFirst HTML + skipWaiting/clientsClaim + controllerchange auto-reload
// in main.tsx, with the api.ts stale-client gate as the 10-min force-recover
// fallback. No JS code path needs to "trigger an update" anymore.
interface NexusWindow extends Window {
  // Set by PusherContext when the WebSocket is connected. Currently unused
  // by app code (the banner-click handler that called it has been removed)
  // but kept exposed for debugging and for any future flow that needs to
  // release the Pusher fetch before SW activation.
  __nexus_disconnectPusher?: () => void;
}
