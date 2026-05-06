// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Service worker rescue shim.
 *
 * This file is imported by the generated Workbox /sw.js. Its job is to rescue
 * browsers that are already running an old frontend bundle, where the React
 * update button may no longer be trustworthy. Because the browser re-checks the
 * root service worker script independently of the stale app bundle, activation
 * logic here can refresh old mobile Chrome clients without requiring old
 * JavaScript to cooperate.
 */

(() => {
  const RESCUE_QUERY_PARAM = 'nexus_sw_rescue';
  const UPDATE_MESSAGE_TYPE = 'NEXUS_SW_RESCUE_RELOAD_REQUIRED';

  let isUpdateInstall = false;

  self.addEventListener('install', () => {
    isUpdateInstall = !!self.registration.active;
    self.skipWaiting();
  });

  self.addEventListener('message', (event) => {
    const messageType = event.data && event.data.type;
    if (messageType === 'SKIP_WAITING' || messageType === 'NEXUS_FORCE_ACTIVATE') {
      self.skipWaiting();
    }
  });

  self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
      await self.clients.claim();

      if (!isUpdateInstall) return;

      const windowClients = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
      });

      await Promise.all(windowClients.map(async (client) => {
        try {
          const url = new URL(client.url);
          if (url.origin !== self.location.origin) return;

          url.searchParams.set(RESCUE_QUERY_PARAM, `${Date.now()}`);

          if ('navigate' in client) {
            await client.navigate(url.href);
            return;
          }

          client.postMessage({ type: UPDATE_MESSAGE_TYPE, url: url.href });
        } catch {
          // Never fail activation because a browser tab could not be refreshed.
        }
      }));
    })());
  });
})();
