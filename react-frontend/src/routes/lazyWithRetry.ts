// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { lazy, type ComponentType } from 'react';

export function isChunkLoadError(error: unknown): error is Error {
  if (!(error instanceof Error)) return false;

  return (
    error.message?.includes('Failed to fetch dynamically imported module') ||
    error.message?.includes('error loading dynamically imported module') ||
    error.message?.includes('Loading chunk') ||
    error.message?.includes('Loading CSS chunk') ||
    error.message?.includes('Unable to preload CSS') ||
    error.name === 'ChunkLoadError'
  );
}

interface StaleChunkRecoveryOptions {
  now?: () => number;
  reload?: () => void;
}

/**
 * Apply the app's one-reload stale-chunk recovery policy. Returns true when a
 * reload was requested. Active text entry is never discarded automatically.
 */
export function requestStaleChunkRecovery(
  error: unknown,
  options: StaleChunkRecoveryOptions = {},
): boolean {
  if (!isChunkLoadError(error)) return false;

  const active = document.activeElement;
  const isUserTyping = active instanceof HTMLInputElement ||
    active instanceof HTMLTextAreaElement ||
    active?.getAttribute('contenteditable') === 'true';

  if (isUserTyping) return false;

  const reloadKey = `chunk_reload_${window.location.pathname}`;
  const lastReload = sessionStorage.getItem(reloadKey);
  const now = (options.now ?? Date.now)();

  if (!lastReload || now - Number.parseInt(lastReload, 10) > 30000) {
    sessionStorage.setItem(reloadKey, now.toString());
    (options.reload ?? (() => window.location.reload()))();
    return true;
  }

  return false;
}

/** Load any dynamic module using the same recovery policy as React.lazy. */
export async function importWithChunkRecovery<T>(importFn: () => Promise<T>): Promise<T> {
  try {
    return await importFn();
  } catch (error) {
    requestStaleChunkRecovery(error);
    throw error;
  }
}

/**
 * Wrapper around React.lazy() that handles stale chunk errors after deployment.
 * When a new build changes chunk hashes, users with cached index.html will try
 * to load old chunk filenames that no longer exist. This catches that error and
 * forces a page reload to fetch the new index.html with correct chunk references.
 */
export function lazyWithRetry(
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  importFn: () => Promise<{ default: ComponentType<any> }>
) {
  return lazy(() => importWithChunkRecovery(importFn));
}
