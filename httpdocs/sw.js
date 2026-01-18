/**
 * Project NEXUS - Service Worker
 * Provides offline support, caching, and PWA functionality
 */

const CACHE_NAME = 'nexus-v24'; // Added federation offline support
const OFFLINE_URL = '/offline.html';
const FEDERATION_OFFLINE_URL = '/federation/offline';

// Assets to cache immediately on install
const PRECACHE_ASSETS = [
  // Core pages for offline access
  '/',
  '/offline.html',
  '/manifest.json',
  '/dashboard',
  '/wallet',
  '/listings',
  '/groups',
  '/help',
  '/login',

  // Federation pages for offline access
  '/federation',
  '/federation/help',
  '/federation/dashboard',
  '/federation/settings',
  '/federation/offline',

  // PWA icons and images
  '/assets/images/pwa/icon.svg',
  '/assets/images/pwa/icon-72x72.png',
  '/assets/images/pwa/icon-96x96.png',
  '/assets/images/pwa/icon-128x128.png',
  '/assets/images/pwa/icon-144x144.png',
  '/assets/images/pwa/icon-152x152.png',
  '/assets/images/pwa/icon-192x192.png',
  '/assets/images/pwa/icon-384x384.png',
  '/assets/images/pwa/icon-512x512.png',
  '/assets/images/pwa/icon-maskable-192x192.png',
  '/assets/images/pwa/icon-maskable-512x512.png',

  // Default images
  '/assets/img/defaults/default_avatar.png',

  // Core CSS (Updated 2026-01-17: Consolidated polish, v2 nav only)
  '/assets/css/nexus-phoenix.css',
  '/assets/css/nexus-mobile.css',
  '/assets/css/nexus-native-nav-v2.css',
  '/assets/css/nexus-polish.css',
  '/assets/css/nexus-interactions.css',
  '/assets/css/nexus-shared-transitions.css',
  '/assets/css/civicone-mobile.css',
  '/assets/css/civicone-native.css',
  '/assets/css/civicone-drawer.css',

  // Core JavaScript
  '/assets/js/nexus-ui.js',
  '/assets/js/nexus-ui.min.js',
  '/assets/js/nexus-mobile.js',
  '/assets/js/nexus-turbo.js',
  '/assets/js/nexus-native.js',
  '/assets/js/nexus-capacitor-bridge.js',
  '/assets/js/nexus-shared-transitions.js',
  '/assets/js/nexus-pwa.js',
  '/assets/js/nexus-mapbox.js',
  '/assets/js/notifications.js',
  '/assets/js/civicone-mobile.js',
  '/assets/js/civicone-native.js',
  '/assets/js/civicone-drawer.js',
  '/assets/js/civicone-pwa.js',
  '/assets/js/civicone-webauthn.js',

  // External CDN assets
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.woff2'
];

// Cache strategies
const CACHE_STRATEGIES = {
  // Cache first, then network (for static assets)
  cacheFirst: [
    /\.(?:css|js|woff2?|ttf|eot|ico)$/,
    /\/assets\//,
    /cdnjs\.cloudflare\.com/
  ],
  // Network first, fallback to cache (for dynamic content)
  networkFirst: [
    /\/api\//,
    /\/messages/,
    /\/wallet/,
    /\/dashboard/,
    /\/members/,   // FIXED: Members page needs fresh data
    /\/federation\/messages/,
    /\/federation\/transactions/,
    /\/federation\/activity/,
    /\/federation\/dashboard/
  ],
  // Stale while revalidate (for semi-dynamic content)
  staleWhileRevalidate: [
    /\/listings/,
    /\/events/,
    /\/groups/,
    /\/blog/,
    /\/federation$/,
    /\/federation\/members/,
    /\/federation\/listings/,
    /\/federation\/events/,
    /\/federation\/groups/,
    /\/federation\/partners/,
    /\/federation\/help/,
    /\/federation\/settings/,
    /\/federation\/onboarding/
  ]
};

// Federation-specific routes that should show federation offline page
const FEDERATION_ROUTES = /^\/federation/;

// Install event - precache assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Precaching assets');
        // Use individual caching to prevent one failure from blocking all
        return Promise.allSettled(
          PRECACHE_ASSETS.map(url => {
            return cache.add(url).catch(err => {
              console.warn(`[SW] Failed to cache: ${url}`, err.message);
              return null;
            });
          })
        );
      })
      .then(() => {
        console.log('[SW] Precaching complete, waiting for activation');
        // Don't call skipWaiting() here - let the user trigger the update
        // via the "Update Now" button to prevent unwanted page refreshes
      })
      .catch(err => {
        console.warn('[SW] Precaching failed, continuing anyway:', err.message);
        // Still don't skipWaiting - wait for user action
      })
  );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name !== CACHE_NAME)
            .map((name) => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - handle requests with appropriate strategy
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // CRITICAL: Skip ALL non-GET requests (POST, PUT, DELETE, etc.)
  // API calls use POST and must NEVER be cached
  if (request.method !== 'GET') {
    console.log('[SW] Bypassing non-GET request:', request.method, url.pathname);
    return; // Let it pass through to network
  }

  // Skip chrome-extension and other non-http(s) requests
  if (!url.protocol.startsWith('http')) return;

  // CRITICAL: Skip download.php to prevent binary corruption
  // Downloads must bypass service worker entirely
  if (url.pathname === '/download.php' || url.pathname.endsWith('/download.php')) {
    return; // Let browser handle directly
  }

  // For navigation requests, let the browser handle redirects naturally
  // This prevents the "redirected response was used" error
  if (request.mode === 'navigate') {
    event.respondWith(handleNavigationRequest(request));
    return;
  }

  // Determine cache strategy
  let strategy = 'networkFirst'; // default

  for (const [strategyName, patterns] of Object.entries(CACHE_STRATEGIES)) {
    if (patterns.some(pattern => pattern.test(url.pathname) || pattern.test(url.href))) {
      strategy = strategyName;
      break;
    }
  }

  event.respondWith(handleFetch(request, strategy));
});

// Handle navigation requests separately to avoid redirect issues
async function handleNavigationRequest(request) {
  try {
    // Use fetch with redirect: 'follow' (default) - let browser handle redirects
    const response = await fetch(request);

    // Only cache successful, non-redirected responses
    if (response.ok && response.type === 'basic' && !response.redirected) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    // Only serve offline page if truly offline (navigator.onLine check)
    // Some errors are not offline-related (e.g., server errors, timeouts)
    const cache = await caches.open(CACHE_NAME);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
      return cachedResponse;
    }

    // Only show offline page if browser reports offline
    // Otherwise, let the error propagate so the user sees the actual error
    if (!navigator.onLine) {
      // Use federation-specific offline page for federation routes
      const url = new URL(request.url);
      if (FEDERATION_ROUTES.test(url.pathname)) {
        const fedOffline = await cache.match(FEDERATION_OFFLINE_URL);
        if (fedOffline) return fedOffline;
      }
      return cache.match(OFFLINE_URL);
    }

    // For online errors, return a simple error response instead of offline page
    return new Response('Service temporarily unavailable. Please refresh the page.', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: { 'Content-Type': 'text/plain' }
    });
  }
}

async function handleFetch(request, strategy) {
  const cache = await caches.open(CACHE_NAME);

  switch (strategy) {
    case 'cacheFirst':
      return cacheFirst(request, cache);
    case 'networkFirst':
      return networkFirst(request, cache);
    case 'staleWhileRevalidate':
      return staleWhileRevalidate(request, cache);
    default:
      return networkFirst(request, cache);
  }
}

// Cache first strategy
async function cacheFirst(request, cache) {
  const cachedResponse = await cache.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }

  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    return new Response('Offline', { status: 503 });
  }
}

// Network first strategy
async function networkFirst(request, cache) {
  try {
    const networkResponse = await fetch(request);
    // Only cache full responses (not partial 206 responses which can't be cached)
    if (networkResponse.ok && networkResponse.status !== 206) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    // Return offline page for navigation requests ONLY if truly offline
    if (request.mode === 'navigate' && !navigator.onLine) {
      return cache.match(OFFLINE_URL);
    }

    return new Response('Service unavailable', { status: 503 });
  }
}

// Stale while revalidate strategy
async function staleWhileRevalidate(request, cache) {
  const cachedResponse = await cache.match(request);

  const fetchPromise = fetch(request)
    .then((networkResponse) => {
      if (networkResponse.ok) {
        cache.put(request, networkResponse.clone());
      }
      return networkResponse;
    })
    .catch(() => cachedResponse);

  return cachedResponse || fetchPromise;
}

// ===========================================
// BACKGROUND SYNC
// ===========================================

// IndexedDB for offline queue
const DB_NAME = 'nexus-offline-queue';
const DB_VERSION = 1;

function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;

      // Store for offline messages
      if (!db.objectStoreNames.contains('messages')) {
        const messageStore = db.createObjectStore('messages', { keyPath: 'id', autoIncrement: true });
        messageStore.createIndex('timestamp', 'timestamp');
      }

      // Store for offline transactions
      if (!db.objectStoreNames.contains('transactions')) {
        const transactionStore = db.createObjectStore('transactions', { keyPath: 'id', autoIncrement: true });
        transactionStore.createIndex('timestamp', 'timestamp');
      }

      // Store for generic form submissions
      if (!db.objectStoreNames.contains('forms')) {
        const formStore = db.createObjectStore('forms', { keyPath: 'id', autoIncrement: true });
        formStore.createIndex('timestamp', 'timestamp');
      }
    };
  });
}

async function getQueuedItems(storeName) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readonly');
    const store = transaction.objectStore(storeName);
    const request = store.getAll();

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function removeQueuedItem(storeName, id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.delete(id);

    request.onsuccess = () => resolve();
    request.onerror = () => reject(request.error);
  });
}

// Background sync event listeners
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync triggered:', event.tag);

  if (event.tag === 'sync-messages') {
    event.waitUntil(syncMessages());
  }
  if (event.tag === 'sync-transactions') {
    event.waitUntil(syncTransactions());
  }
  if (event.tag === 'sync-forms') {
    event.waitUntil(syncForms());
  }
});

async function syncMessages() {
  console.log('[SW] Syncing offline messages...');

  try {
    const messages = await getQueuedItems('messages');
    console.log(`[SW] Found ${messages.length} queued messages`);

    for (const msg of messages) {
      try {
        const response = await fetch('/api/messages/send', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          credentials: 'include',
          body: JSON.stringify({
            recipient_id: msg.recipientId,
            content: msg.content,
            conversation_id: msg.conversationId
          })
        });

        if (response.ok) {
          await removeQueuedItem('messages', msg.id);
          console.log('[SW] Message synced successfully:', msg.id);

          // Show notification
          await self.registration.showNotification('Message Sent', {
            body: 'Your offline message has been delivered.',
            icon: '/assets/images/pwa/icon-192x192.png',
            badge: '/assets/images/pwa/icon-72x72.png',
            tag: 'sync-success'
          });
        } else {
          console.error('[SW] Failed to sync message:', response.status);
        }
      } catch (err) {
        console.error('[SW] Error syncing message:', err);
      }
    }
  } catch (err) {
    console.error('[SW] Error in syncMessages:', err);
  }
}

async function syncTransactions() {
  console.log('[SW] Syncing offline transactions...');

  try {
    const transactions = await getQueuedItems('transactions');
    console.log(`[SW] Found ${transactions.length} queued transactions`);

    for (const tx of transactions) {
      try {
        const response = await fetch('/api/wallet/transfer', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          credentials: 'include',
          body: JSON.stringify({
            recipient_id: tx.recipientId,
            amount: tx.amount,
            description: tx.description,
            listing_id: tx.listingId
          })
        });

        if (response.ok) {
          await removeQueuedItem('transactions', tx.id);
          console.log('[SW] Transaction synced successfully:', tx.id);

          // Show notification
          await self.registration.showNotification('Time Credit Sent', {
            body: `${tx.amount} credits transferred successfully.`,
            icon: '/assets/images/pwa/icon-192x192.png',
            badge: '/assets/images/pwa/icon-72x72.png',
            tag: 'sync-success'
          });
        } else {
          console.error('[SW] Failed to sync transaction:', response.status);
        }
      } catch (err) {
        console.error('[SW] Error syncing transaction:', err);
      }
    }
  } catch (err) {
    console.error('[SW] Error in syncTransactions:', err);
  }
}

async function syncForms() {
  console.log('[SW] Syncing offline forms...');

  try {
    const forms = await getQueuedItems('forms');
    console.log(`[SW] Found ${forms.length} queued forms`);

    for (const form of forms) {
      try {
        const response = await fetch(form.url, {
          method: form.method || 'POST',
          headers: form.headers || { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(form.data)
        });

        if (response.ok) {
          await removeQueuedItem('forms', form.id);
          console.log('[SW] Form synced successfully:', form.id);
        } else {
          console.error('[SW] Failed to sync form:', response.status);
        }
      } catch (err) {
        console.error('[SW] Error syncing form:', err);
      }
    }
  } catch (err) {
    console.error('[SW] Error in syncForms:', err);
  }
}

// Listen for messages from the client
self.addEventListener('message', (event) => {
  // Handle skip waiting request from update prompt
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }

  // Queue offline actions
  if (event.data && event.data.type === 'QUEUE_MESSAGE') {
    queueOfflineAction('messages', event.data.payload, 'sync-messages');
  }
  if (event.data && event.data.type === 'QUEUE_TRANSACTION') {
    queueOfflineAction('transactions', event.data.payload, 'sync-transactions');
  }
  if (event.data && event.data.type === 'QUEUE_FORM') {
    queueOfflineAction('forms', event.data.payload, 'sync-forms');
  }
});

async function queueOfflineAction(storeName, data, syncTag) {
  try {
    const db = await openDB();
    const transaction = db.transaction(storeName, 'readwrite');
    const store = transaction.objectStore(storeName);

    const item = {
      ...data,
      timestamp: Date.now()
    };

    await new Promise((resolve, reject) => {
      const request = store.add(item);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });

    console.log(`[SW] Queued offline ${storeName} action`);

    // Register for background sync
    if ('sync' in self.registration) {
      await self.registration.sync.register(syncTag);
      console.log(`[SW] Registered background sync: ${syncTag}`);
    }
  } catch (err) {
    console.error(`[SW] Error queuing ${storeName}:`, err);
  }
}

// ===========================================
// PUSH NOTIFICATIONS
// ===========================================

// Notification type configurations
// Using icon-192x192.png as fallback since type-specific icons don't exist yet
const NOTIFICATION_TYPES = {
  message: {
    icon: '/assets/images/pwa/icon-192x192.png',
    badge: '/assets/images/pwa/icon-72x72.png',
    vibrate: [100, 50, 100],
    tag: 'message',
    renotify: true,
    actions: [
      { action: 'reply', title: 'Reply' },
      { action: 'view', title: 'View' }
    ]
  },
  transaction: {
    icon: '/assets/images/pwa/icon-192x192.png',
    badge: '/assets/images/pwa/icon-72x72.png',
    vibrate: [100, 50, 100, 50, 100],
    tag: 'transaction',
    renotify: true,
    requireInteraction: true,
    actions: [
      { action: 'accept', title: 'Accept' },
      { action: 'decline', title: 'Decline' }
    ]
  },
  event: {
    icon: '/assets/images/pwa/icon-192x192.png',
    badge: '/assets/images/pwa/icon-72x72.png',
    vibrate: [100, 50, 100],
    tag: 'event',
    actions: [
      { action: 'rsvp', title: 'RSVP' },
      { action: 'view', title: 'Details' }
    ]
  },
  reminder: {
    icon: '/assets/images/pwa/icon-192x192.png',
    badge: '/assets/images/pwa/icon-72x72.png',
    vibrate: [200, 100, 200],
    tag: 'reminder',
    requireInteraction: true,
    actions: [
      { action: 'snooze', title: 'Snooze 1h' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  },
  general: {
    icon: '/assets/images/pwa/icon-192x192.png',
    badge: '/assets/images/pwa/icon-72x72.png',
    vibrate: [100, 50, 100],
    tag: 'general',
    actions: []
  }
};

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let data;
  try {
    data = event.data.json();
  } catch (e) {
    // Plain text push
    data = {
      title: 'New Notification',
      body: event.data.text(),
      type: 'general'
    };
  }

  // Get notification type config
  const typeConfig = NOTIFICATION_TYPES[data.type] || NOTIFICATION_TYPES.general;

  const options = {
    body: data.body || '',
    icon: data.icon || typeConfig.icon,
    badge: typeConfig.badge,
    vibrate: typeConfig.vibrate,
    tag: data.tag || typeConfig.tag,
    renotify: typeConfig.renotify || false,
    requireInteraction: data.requireInteraction || typeConfig.requireInteraction || false,
    silent: data.silent || false,
    timestamp: data.timestamp || Date.now(),
    data: {
      url: data.url || '/',
      type: data.type || 'general',
      id: data.id || null,
      payload: data.payload || {}
    },
    actions: data.actions || typeConfig.actions
  };

  // Add image if provided (for rich notifications)
  if (data.image) {
    options.image = data.image;
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'NEXUS', options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  const notification = event.notification;
  const action = event.action;
  const data = notification.data;

  notification.close();

  // Handle specific actions
  if (action) {
    event.waitUntil(handleNotificationAction(action, data));
  } else {
    // Default click - open URL
    event.waitUntil(openOrFocusWindow(data.url));
  }
});

// Handle notification actions
async function handleNotificationAction(action, data) {
  const baseUrl = self.location.origin;

  switch (action) {
    case 'reply':
      // Open message reply
      return openOrFocusWindow(`${baseUrl}/messages/${data.id}?reply=true`);

    case 'view':
      return openOrFocusWindow(data.url);

    case 'accept':
      // Accept transaction via API
      try {
        await fetch(`${baseUrl}/api/transactions/${data.id}/accept`, {
          method: 'POST',
          credentials: 'include'
        });
        // Show confirmation notification
        return self.registration.showNotification('Transaction Accepted', {
          body: 'The time credit has been added to your wallet.',
          icon: '/assets/images/pwa/icon-success.png',
          badge: '/assets/images/pwa/badge-72x72.png',
          tag: 'confirmation'
        });
      } catch (e) {
        return openOrFocusWindow(`${baseUrl}/wallet`);
      }

    case 'decline':
      // Decline transaction via API
      try {
        await fetch(`${baseUrl}/api/transactions/${data.id}/decline`, {
          method: 'POST',
          credentials: 'include'
        });
        return self.registration.showNotification('Transaction Declined', {
          body: 'The request has been declined.',
          icon: '/assets/images/pwa/icon-info.png',
          badge: '/assets/images/pwa/badge-72x72.png',
          tag: 'confirmation'
        });
      } catch (e) {
        return openOrFocusWindow(`${baseUrl}/wallet`);
      }

    case 'rsvp':
      return openOrFocusWindow(`${baseUrl}/events/${data.id}?rsvp=true`);

    case 'snooze':
      // Schedule reminder for 1 hour later (would need backend support)
      console.log('[SW] Snooze requested for:', data.id);
      return;

    case 'dismiss':
      // Just close, already handled above
      return;

    default:
      return openOrFocusWindow(data.url);
  }
}

// Helper to open or focus existing window
async function openOrFocusWindow(url) {
  // Ensure URL is absolute
  const absoluteUrl = new URL(url, self.location.origin).href;

  const clientList = await clients.matchAll({
    type: 'window',
    includeUncontrolled: true
  });

  // Try to find existing PWA window to focus and navigate
  for (const client of clientList) {
    try {
      if (new URL(client.url).origin === self.location.origin) {
        // Focus the window first
        if ('focus' in client) {
          await client.focus();
        }
        // Then navigate to the URL
        if ('navigate' in client) {
          return client.navigate(absoluteUrl);
        }
        // If navigate not available, just return after focus
        return client;
      }
    } catch (e) {
      console.log('[SW] Error focusing client:', e);
    }
  }

  // No existing window found - open new window/PWA
  try {
    return await clients.openWindow(absoluteUrl);
  } catch (e) {
    console.log('[SW] Error opening window:', e);
    // Fallback - just return
    return null;
  }
}

// Notification close (user dismissed)
self.addEventListener('notificationclose', (event) => {
  const data = event.notification.data;
  console.log('[SW] Notification dismissed:', data.type, data.id);

  // Track dismissal for analytics (optional)
  // Could send to analytics endpoint
});
