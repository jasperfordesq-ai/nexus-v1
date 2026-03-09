// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Ambient type declaration for the Capacitor global injected into window
 * when the app is running inside a Capacitor native WebView.
 *
 * This avoids `(window as any).Capacitor` casts throughout the codebase.
 */

interface CapacitorGlobal {
  isNativePlatform(): boolean;
  getPlatform(): 'ios' | 'android' | 'web';
  isPluginAvailable(name: string): boolean;
}

interface Window {
  Capacitor?: CapacitorGlobal;
}
