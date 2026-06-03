// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// ---------------------------------------------------------------------------
// THEMING CONVENTION (read me)
//
// HeroUI Native does NOT theme through a provider prop — its tokens are uniwind
// CSS variables, overridden in `mobile/global.css` (the indigo-glow palette
// that mirrors the web app's `react-frontend/src/styles/tokens.css`). So the
// single source of truth for colours is global.css.
//
// PREFER HeroUI / Tailwind className tokens in components — they resolve to the
// values below automatically and support light/dark out of the box:
//   bg-background, bg-surface, text-foreground, text-muted-foreground,
//   border-border, and the <Surface> component for elevated panels.
//
// This `useTheme()` hex hook is the bridge for code that still needs a literal
// colour (e.g. a third-party `style={{ backgroundColor }}` such as gorhom's
// BottomSheetFlatList, or a chart). Its values are kept IN SYNC with global.css
// so token-based and inline-styled UI render identically — no more clashing
// neutral-grey vs indigo surfaces. Reserve genuinely dynamic per-tenant colours
// (the brand primary) for `usePrimaryColor()` from `@/lib/hooks/useTenant`.
//
// When touching a component, migrate its inline `theme.*` to className tokens
// where one exists; this hook is the fallback, not the default.
//
// The hook is reactive: it reads the resolved scheme from `themeStore` via
// `useSyncExternalStore`, so flipping light/dark in Settings re-renders every
// consumer alongside the Uniwind className tokens. With no startup wiring (e.g.
// in unit tests) the store defaults to 'dark', preserving prior behaviour.
// ---------------------------------------------------------------------------

import { useSyncExternalStore } from 'react';

import { themeStore, type ThemeMode } from '@/lib/theme/themeStore';

export const LIGHT = {
  bg: '#F8FAFC',           // --background (web light)
  surface: '#FFFFFF',      // --surface-solid
  border: '#E2E8F0',       // --border-default ≈ rgba(0,0,0,0.08) on white (slate-200)
  borderSubtle: '#F1F5F9', // slate-100
  text: '#1E293B',         // --foreground (slate-800)
  textSecondary: '#475569',// --foreground-muted (slate-600)
  textMuted: '#94A3B8',    // --foreground-subtle (slate-400)
  onPrimary: '#FFFFFF',
  overlay: 'rgba(0,0,0,0.5)',
  error: '#DC2626',
  errorBg: '#FEE2E2',
  success: '#16A34A',      // web light --color-success
  successBg: '#D1FAE5',
  info: '#2563EB',         // web light --color-info
  infoBg: '#DBEAFE',
  warning: '#D97706',      // web light --color-warning
} as const;

export const DARK = {
  bg: '#0A0A0F',           // --background (web dark) — matches global.css --background
  surface: '#16162A',      // --surface-dropdown (solid elevated indigo-dark)
  border: '#24242E',       // --border-default ≈ rgba(255,255,255,0.10) on bg
  borderSubtle: '#1A1A24',
  text: '#EDEDED',         // --foreground (web dark)
  textSecondary: '#A8A8B4',// --foreground-muted ≈ rgba(237,237,237,0.7)
  textMuted: '#7C7C8A',    // --foreground-subtle ≈ rgba(237,237,237,0.5)
  onPrimary: '#FFFFFF',
  overlay: 'rgba(0,0,0,0.6)',
  error: '#FF453A',
  errorBg: '#2D1B1B',
  success: '#22C55E',      // web dark --color-success
  successBg: '#052E16',
  info: '#3B82F6',         // web dark --color-info
  infoBg: '#1E3A5F',
  warning: '#F59E0B',      // web dark --color-warning
} as const;

export type Theme = { [K in keyof typeof LIGHT]: string };

export function useTheme(): Theme {
  const scheme = useSyncExternalStore(
    themeStore.subscribe,
    themeStore.getSnapshot,
    themeStore.getSnapshot,
  );
  return (scheme === 'light' ? LIGHT : DARK) as Theme;
}

/**
 * Theme controller for settings UI: the user's chosen `mode`, the resolved
 * `scheme` that is currently painting, and a `setMode` setter. Reactive via
 * `useSyncExternalStore`, so the selector reflects external/system changes too.
 */
export function useThemeController(): {
  mode: ThemeMode;
  scheme: 'light' | 'dark';
  setMode: (mode: ThemeMode) => void;
} {
  const scheme = useSyncExternalStore(
    themeStore.subscribe,
    themeStore.getSnapshot,
    themeStore.getSnapshot,
  );
  const mode = useSyncExternalStore(
    themeStore.subscribe,
    themeStore.getMode,
    themeStore.getMode,
  );
  return { mode, scheme, setMode: themeStore.setMode };
}
