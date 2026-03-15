// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE: string;
  readonly VITE_TENANT_ID: string;
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

// Global window extensions for PWA service worker update
interface NexusWindow extends Window {
  __nexus_updateSW?: (reloadPage?: boolean) => void | Promise<void>;
  __nexus_updatePending?: boolean;
}
