// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// ---------------------------------------------------------------------------
// THEME STORE
//
// A tiny framework-agnostic store that owns the app's colour scheme. It is the
// single source of truth that `useTheme()` (hex bridge) and Uniwind className
// tokens both read from, so token-based and inline-styled UI always agree.
//
// Why a module store instead of React context: `useTheme()` is called by ~200
// components and by tests that render those components WITHOUT any provider.
// Backing it with `useSyncExternalStore` keeps it provider-free — it simply
// returns the current scheme (defaulting to 'dark', preserving the previous
// dark-only behaviour in tests) and re-renders when the scheme changes.
//
// `mode` is the user's choice ('system' | 'light' | 'dark'); `scheme` is the
// resolved 'light' | 'dark' that actually paints. When mode is 'system' the
// scheme follows the OS appearance.
// ---------------------------------------------------------------------------

import { Appearance } from 'react-native';
import { Uniwind } from 'uniwind';

import { storage } from '@/lib/storage';
import { STORAGE_KEYS } from '@/lib/constants';

export type ThemeMode = 'system' | 'light' | 'dark';
export type ColorScheme = 'light' | 'dark';

const VALID_MODES: readonly ThemeMode[] = ['system', 'light', 'dark'];

// Default to dark so the synchronous first render (and every test that renders
// a component using useTheme without calling configureNativeTheme) matches the
// app's historical dark-only appearance. configureNativeTheme() overrides this
// with the real system/persisted scheme at startup.
let mode: ThemeMode = 'system';
let systemScheme: ColorScheme = 'dark';

const listeners = new Set<() => void>();

function resolve(): ColorScheme {
  return mode === 'system' ? systemScheme : mode;
}

// Cached snapshot so getSnapshot() returns a referentially-stable value between
// changes — required by useSyncExternalStore to avoid render loops.
let snapshot: ColorScheme = resolve();

function recompute(): void {
  const next = resolve();
  if (next !== snapshot) {
    snapshot = next;
  }
}

function emit(): void {
  listeners.forEach((listener) => listener());
}

/**
 * Push the resolved scheme into Uniwind (className tokens) and React Native
 * Appearance (so useColorScheme()/native controls follow). When the user picks
 * 'system' we hand control back to the OS by clearing the JS override.
 */
function applyScheme(): void {
  const scheme = resolve();
  Uniwind.setTheme(scheme);
  Appearance.setColorScheme(mode === 'system' ? null : scheme);
}

export const themeStore = {
  subscribe(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  },

  /** Resolved scheme that should paint right now. Stable between changes. */
  getSnapshot(): ColorScheme {
    return snapshot;
  },

  getMode(): ThemeMode {
    return mode;
  },

  /** Change the user's preference, persist it, and apply immediately. */
  setMode(nextMode: ThemeMode): void {
    if (!VALID_MODES.includes(nextMode) || nextMode === mode) return;
    mode = nextMode;
    recompute();
    applyScheme();
    emit();
    void storage.set(STORAGE_KEYS.THEME_MODE, nextMode);
  },

  /**
   * Startup wiring. Reads the OS scheme synchronously (no flash), applies it,
   * then asynchronously loads the persisted preference and subscribes to OS
   * appearance changes. Safe to call once from the app shell.
   */
  init(): void {
    systemScheme = Appearance.getColorScheme() === 'light' ? 'light' : 'dark';
    recompute();
    applyScheme();
    emit();

    Appearance.addChangeListener(({ colorScheme }) => {
      systemScheme = colorScheme === 'light' ? 'light' : 'dark';
      if (mode === 'system') {
        recompute();
        applyScheme();
        emit();
      }
    });

    void storage.get(STORAGE_KEYS.THEME_MODE).then((stored) => {
      if (stored && VALID_MODES.includes(stored as ThemeMode) && stored !== mode) {
        themeStore.setMode(stored as ThemeMode);
      }
    });
  },

  /** Test-only reset so suites don't leak scheme state into one another. */
  __resetForTests(): void {
    mode = 'system';
    systemScheme = 'dark';
    snapshot = 'dark';
    listeners.clear();
  },
};
