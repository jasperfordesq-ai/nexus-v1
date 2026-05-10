// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
//
// Web Push event handlers — imported into the generated Workbox service worker
// via `workbox.importScripts` in vite.config.ts. Lives in /public so the file
// is copied to the build root and resolvable at the SW scope (/sw-push-handler.js).
//
// The PHP backend (WebPushService) sends payloads with this shape:
//   { title, body, url, icon, badge, tag, type, ... }
// All fields except title+body are optional.

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload = {};
  try {
    payload = event.data.json();
  } catch (_e) {
    // Some senders push plain text — fall back to body-only.
    payload = { title: 'NEXUS', body: event.data.text() };
  }

  const title = payload.title || 'NEXUS';
  const options = {
    body: payload.body || '',
    icon: payload.icon || '/icons/icon-192.png',
    badge: payload.badge || '/icons/icon-192.png',
    tag: payload.tag || 'nexus-notification',
    data: { url: payload.url || '/', type: payload.type || 'general' },
    // Re-show the OS notification even if a previous one with the same tag was
    // dismissed. Without this, repeat notifications silently coalesce.
    renotify: !!payload.tag,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    // Focus an existing same-origin tab if we have one — navigate it to the target.
    for (const client of allClients) {
      try {
        const clientUrl = new URL(client.url);
        const sameOrigin = clientUrl.origin === self.location.origin;
        if (sameOrigin && 'focus' in client) {
          await client.focus();
          if ('navigate' in client && targetUrl) {
            try { await client.navigate(targetUrl); } catch (_e) { /* cross-origin or detached — ignore */ }
          }
          return;
        }
      } catch (_e) {
        // Malformed client URL — skip.
      }
    }

    // No open tab — open a new one.
    if (self.clients.openWindow) {
      await self.clients.openWindow(targetUrl);
    }
  })());
});

// Optional: respond to pushsubscriptionchange (browser-rotated subscription).
// We just clear local state — the next time the user opens the app, the
// useWebPush hook detects the missing subscription and re-subscribes.
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
      try { client.postMessage({ type: 'nexus:push_subscription_changed' }); } catch (_e) { /* ignore */ }
    }
  })());
});
