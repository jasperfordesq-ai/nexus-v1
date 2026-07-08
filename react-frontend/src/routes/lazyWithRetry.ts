// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { lazy, type ComponentType } from 'react';

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
  return lazy(() =>
    importFn().catch((error: Error) => {
      const isChunkError =
        error.message?.includes('Failed to fetch dynamically imported module') ||
        error.message?.includes('error loading dynamically imported module') ||
        error.message?.includes('Loading chunk') ||
        error.message?.includes('Loading CSS chunk') ||
        error.message?.includes('Unable to preload CSS') ||
        error.name === 'ChunkLoadError';

      if (isChunkError) {
        const active = document.activeElement;
        const isUserTyping = active instanceof HTMLInputElement ||
          active instanceof HTMLTextAreaElement ||
          active?.getAttribute('contenteditable') === 'true';

        if (isUserTyping) {
          throw error;
        }

        const reloadKey = `chunk_reload_${window.location.pathname}`;
        const lastReload = sessionStorage.getItem(reloadKey);
        const now = Date.now();

        if (!lastReload || now - parseInt(lastReload) > 30000) {
          sessionStorage.setItem(reloadKey, now.toString());
          window.location.reload();
        }
      }

      throw error;
    })
  );
}
