// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { themeStore } from '@/lib/theme/themeStore';

/**
 * Startup theme wiring, called once from the app shell. Seeds the resolved
 * scheme from the OS appearance, applies the persisted user preference (if
 * any), and keeps following the OS while the user stays on 'system'. Pushes
 * the scheme into both Uniwind (className tokens) and RN Appearance.
 */
export function configureNativeTheme() {
  themeStore.init();
}
