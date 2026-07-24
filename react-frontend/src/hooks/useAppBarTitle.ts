// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * App-bar page title — lets a page surface its title inside the fixed Navbar
 * on phones, where the page's own header is hidden to reclaim vertical space.
 *
 * Module-level store + useSyncExternalStore instead of a context provider so
 * neither side needs Layout wiring and tests can render either component
 * standalone. Only one page is routed at a time, so a single slot suffices.
 */

import { useEffect, useSyncExternalStore } from 'react';

let currentTitle: string | null = null;
const listeners = new Set<() => void>();

function setAppBarTitle(title: string | null) {
  if (currentTitle === title) return;
  currentTitle = title;
  listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void) {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

function getSnapshot() {
  return currentTitle;
}

/** Read the page-provided app-bar title (consumed by Navbar). */
export function useAppBarTitle(): string | null {
  return useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
}

/** Publish a title into the app bar for the lifetime of the calling page. */
export function useSetAppBarTitle(title: string) {
  useEffect(() => {
    setAppBarTitle(title);
    return () => setAppBarTitle(null);
  }, [title]);
}
