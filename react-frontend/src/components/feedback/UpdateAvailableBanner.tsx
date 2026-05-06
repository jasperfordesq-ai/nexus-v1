// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * UpdateAvailableBanner - Non-intrusive banner shown when a new service worker is ready.
 *
 * Instead of automatically reloading (which interrupted users mid-typing),
 * this banner lets the user choose when to apply the update.
 * The update is applied by activating the waiting service worker and reloading.
 *
 * Detection strategy (handles mobile PWA "killed and reopened" case):
 *  1. Global flag (__nexus_updatePending) set by main.tsx onNeedRefresh — covers
 *     the case where the event fires before React mounts.
 *  2. Custom event (nexus:sw_update_available) — covers normal in-session detection.
 *  3. Direct registration.waiting check on mount and route change — covers mobile
 *     where the app is killed/reopened: sessionStorage is wiped but the waiting
 *     SW persists, and onNeedRefresh never re-fires.
 */

import { useState, useEffect, useCallback } from 'react';
import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion, AnimatePresence } from 'framer-motion';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import X from 'lucide-react/icons/x';
import { Button } from '@heroui/react';

// Stores the BUILD_COMMIT + timestamp that the user clicked "Update" from.
// If on reload we're STILL running that same commit, the update hasn't taken
// effect yet — suppress the banner for up to 10 minutes instead of looping.
// Uses localStorage (not sessionStorage) so the suppression survives mobile
// app kills, where the OS tears down the WebView between background/foreground
// cycles and sessionStorage is wiped even though the waiting SW persists.
const SW_UPDATE_FROM_COMMIT_KEY = 'nexus_sw_update_from_commit';
const SW_UPDATE_SUPPRESSION_TTL = 10 * 60 * 1000; // 10 minutes
const SW_ACTIVATION_TIMEOUT_MS = 5000;
const UPDATE_SW_TIMEOUT_MS = 3000;
const CACHE_BUST_QUERY_PARAM = 'nexus_refresh';
const SW_RESCUE_QUERY_PARAM = 'nexus_sw_rescue';

async function hasWaitingSW(): Promise<boolean> {
  try {
    const reg = await navigator.serviceWorker?.getRegistration();
    return !!reg?.waiting;
  } catch {
    return false;
  }
}

function markUpdateTriggered(): void {
  try {
    localStorage.setItem(SW_UPDATE_FROM_COMMIT_KEY, `${__BUILD_COMMIT__}:${Date.now()}`);
  } catch { /* non-blocking */ }
}

async function refreshServiceWorkerRegistration(): Promise<ServiceWorkerRegistration | undefined> {
  if (!navigator.serviceWorker) return undefined;

  const registration = await navigator.serviceWorker.getRegistration();
  try {
    await registration?.update();
  } catch {
    // A failed update check should not block the repair path below.
  }

  return navigator.serviceWorker.getRegistration();
}

async function waitForInstallingWorker(
  registration: ServiceWorkerRegistration | undefined,
): Promise<ServiceWorker | null> {
  if (!registration) return null;
  if (registration.waiting) return registration.waiting;
  if (!registration.installing) return null;

  const installingWorker = registration.installing;
  if (installingWorker.state === 'installed') return installingWorker;

  return new Promise((resolve) => {
    let settled = false;
    const settle = (worker: ServiceWorker | null) => {
      if (settled) return;
      settled = true;
      clearTimeout(timeout);
      installingWorker.removeEventListener('statechange', onStateChange);
      resolve(worker);
    };
    const onStateChange = () => {
      if (registration.waiting) {
        settle(registration.waiting);
        return;
      }
      if (installingWorker.state === 'installed') settle(installingWorker);
      if (installingWorker.state === 'activated' || installingWorker.state === 'redundant') settle(null);
    };
    const timeout = window.setTimeout(() => settle(registration.waiting ?? null), SW_ACTIVATION_TIMEOUT_MS);

    installingWorker.addEventListener('statechange', onStateChange);
  });
}

async function runWithTimeout<T>(task: Promise<T>, timeoutMs: number): Promise<T | undefined> {
  return new Promise((resolve) => {
    let settled = false;
    const timeout = window.setTimeout(() => {
      if (settled) return;
      settled = true;
      resolve(undefined);
    }, timeoutMs);

    task.then((result) => {
      if (settled) return;
      settled = true;
      clearTimeout(timeout);
      resolve(result);
    }).catch(() => {
      if (settled) return;
      settled = true;
      clearTimeout(timeout);
      resolve(undefined);
    });
  });
}

async function activateWaitingServiceWorker(): Promise<boolean> {
  const registration = await refreshServiceWorkerRegistration();
  const waitingWorker = registration?.waiting ?? await waitForInstallingWorker(registration);
  if (!waitingWorker) return false;

  return new Promise((resolve) => {
    let settled = false;
    const settle = (activated: boolean) => {
      if (settled) return;
      settled = true;
      clearTimeout(timeout);
      navigator.serviceWorker?.removeEventListener('controllerchange', onControllerChange);
      resolve(activated);
    };
    const onControllerChange = () => settle(true);
    const timeout = window.setTimeout(() => settle(false), SW_ACTIVATION_TIMEOUT_MS);

    navigator.serviceWorker?.addEventListener('controllerchange', onControllerChange, { once: true });
    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
  });
}

async function forceClearAppCaches(): Promise<void> {
  try {
    if ('caches' in window) {
      const cacheNames = await caches.keys();
      await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
    }
  } catch {
    // Cache cleanup is best-effort; unregistering below still helps.
  }

  try {
    if (navigator.serviceWorker?.getRegistrations) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map((registration) => registration.unregister()));
    } else {
      const registration = await navigator.serviceWorker?.getRegistration();
      await registration?.unregister();
    }
  } catch {
    // Reload anyway; the network may already have the fresh shell.
  }
}

function buildFreshNavigationUrl(): string {
  const url = new URL(window.location.href);
  url.searchParams.set(CACHE_BUST_QUERY_PARAM, `${Date.now()}`);
  return url.toString();
}

function navigateToFreshAppShell(): void {
  const url = buildFreshNavigationUrl();
  try {
    window.location.replace(url);
  } catch {
    window.location.href = url;
  }

  window.setTimeout(() => {
    try {
      window.location.reload();
    } catch {
      // The replace() call above is the primary navigation path.
    }
  }, 1000);
}

function removeCacheBustQueryParam(): void {
  try {
    const url = new URL(window.location.href);
    const hasCacheBustParam = url.searchParams.has(CACHE_BUST_QUERY_PARAM);
    const hasRescueParam = url.searchParams.has(SW_RESCUE_QUERY_PARAM);
    if (!hasCacheBustParam && !hasRescueParam) return;
    url.searchParams.delete(CACHE_BUST_QUERY_PARAM);
    url.searchParams.delete(SW_RESCUE_QUERY_PARAM);
    const nextUrl = `${url.pathname}${url.search}${url.hash}`;
    window.history.replaceState(window.history.state, document.title, nextUrl);
  } catch {
    // Cosmetic only; the update still applied.
  }
}

/**
 * Returns true if the user already triggered an update from this exact build
 * recently and the page is still running old code (reload hasn't taken effect).
 * Suppression expires after SW_UPDATE_SUPPRESSION_TTL so a genuinely broken
 * update doesn't permanently hide the banner.
 */
function isUpdateAlreadyTriggered(): boolean {
  try {
    const raw = localStorage.getItem(SW_UPDATE_FROM_COMMIT_KEY);
    if (!raw) return false;
    const colonIdx = raw.lastIndexOf(':');
    const commit = raw.slice(0, colonIdx);
    const ts = parseInt(raw.slice(colonIdx + 1), 10);
    if (commit !== __BUILD_COMMIT__) {
      // Different build is running — the update worked! Clean up.
      localStorage.removeItem(SW_UPDATE_FROM_COMMIT_KEY);
      return false;
    }
    if (Date.now() - ts < SW_UPDATE_SUPPRESSION_TTL) {
      // Same build, within suppression window — update in progress, suppress.
      return true;
    }
    // Suppression expired — update didn't complete in time, allow retry.
    localStorage.removeItem(SW_UPDATE_FROM_COMMIT_KEY);
    return false;
  } catch {
    return false;
  }
}

export function UpdateAvailableBanner() {
  const { t } = useTranslation('common');
  const [showBanner, setShowBanner] = useState(false);
  const [updating, setUpdating] = useState(false);
  const location = useLocation();

  useEffect(() => {
    removeCacheBustQueryParam();
  }, []);

  const checkAndShow = useCallback(() => {
    if (isUpdateAlreadyTriggered()) return;
    hasWaitingSW().then((waiting) => {
      if (waiting) setShowBanner(true);
    });
  }, []);

  useEffect(() => {
    // If we already triggered an update from this build, suppress everything.
    // The banner would just make the user click again for no reason — the SW
    // needs time to activate. We'll un-suppress on the next reload that actually
    // picks up the new code (different __BUILD_COMMIT__).
    if (isUpdateAlreadyTriggered()) {
      (window as NexusWindow).__nexus_updatePending = false;
      // Still listen for events, but only show if suppression clears
      // (e.g. a DIFFERENT update arrives while we're waiting).
      function handleUpdateAvailable() {
        if (!isUpdateAlreadyTriggered()) setShowBanner(true);
      }
      window.addEventListener('nexus:sw_update_available', handleUpdateAvailable);
      return () => window.removeEventListener('nexus:sw_update_available', handleUpdateAvailable);
    }

    // Cover race condition: onNeedRefresh fired before React mounted
    if ((window as NexusWindow).__nexus_updatePending) {
      (window as NexusWindow).__nexus_updatePending = false;
      setShowBanner(true);
    }

    // Direct SW check on mount — this is what catches the mobile "reopened after
    // being killed" case where sessionStorage is gone but waiting SW still exists.
    checkAndShow();

    function handleUpdateAvailable() {
      setShowBanner(true);
    }

    window.addEventListener('nexus:sw_update_available', handleUpdateAvailable);
    return () => {
      window.removeEventListener('nexus:sw_update_available', handleUpdateAvailable);
    };
  }, [checkAndShow]);

  // Re-check on every route change. If the user dismissed the banner but the
  // SW is still waiting, re-surface it. Uses direct SW check (not sessionStorage)
  // so it works across app restarts on mobile.
  useEffect(() => {
    checkAndShow();
  }, [location.pathname, checkAndShow]);

  // Re-check when the app comes back to the foreground (mobile app-switch).
  // main.tsx triggers registration.update() on visibilitychange; this hook
  // surfaces the banner if a waiting SW already exists at that moment.
  useEffect(() => {
    function handleVisibilityChange() {
      if (document.visibilityState === 'visible') {
        checkAndShow();
      }
    }
    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [checkAndShow]);

  function handleUpdate() {
    if (updating) return;

    // Record which build we're updating FROM so the banner stays suppressed
    // on reload if the same build is still running (update in progress).
    markUpdateTriggered();
    setUpdating(true);

    const updateSW = (window as NexusWindow).__nexus_updateSW;

    void (async () => {
      try {
        if (typeof updateSW === 'function') {
          await runWithTimeout(Promise.resolve(updateSW(false)), UPDATE_SW_TIMEOUT_MS);
        }

        const activated = await activateWaitingServiceWorker();
        if (!activated) {
          await forceClearAppCaches();
        }
      } finally {
        navigateToFreshAppShell();
      }
    })();
  }

  function handleDismiss() {
    setShowBanner(false);
  }


  return (
    <AnimatePresence>
      {showBanner && (
        <motion.div
          initial={{ height: 0, opacity: 0 }}
          animate={{ height: 'auto', opacity: 1 }}
          exit={{ height: 0, opacity: 0 }}
          transition={{ duration: 0.3, ease: 'easeOut' }}
          className="fixed top-0 left-0 right-0 z-[9999] overflow-hidden"
          style={{ paddingTop: 'env(safe-area-inset-top, 0px)' }}
          role="status"
          aria-live="polite"
        >
          <div className="bg-indigo-600 text-white text-center py-2 px-3 text-sm font-medium flex items-center justify-center gap-2 sm:gap-3">
            <button
              type="button"
              onClick={handleUpdate}
              disabled={updating}
              className="min-w-0 flex flex-1 items-center justify-center gap-2 rounded px-1 py-1 text-white touch-manipulation disabled:cursor-wait disabled:opacity-90 sm:flex-none"
              aria-label={t('update_banner.message')}
            >
              <RefreshCw className={`w-4 h-4 flex-shrink-0${updating ? ' animate-spin' : ''}`} aria-hidden="true" />
              <span className="min-w-0 truncate sm:whitespace-normal">{t('update_banner.message')}</span>
            </button>
            <Button
              size="sm"
              className="bg-white text-indigo-700 font-semibold min-w-0 h-8 px-3 touch-manipulation"
              onPress={handleUpdate}
              isDisabled={updating}
              isLoading={updating}
            >
              {t('update_banner.update_now')}
            </Button>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={handleDismiss}
              isDisabled={updating}
              className="ml-1 p-1 rounded hover:bg-indigo-500 transition-colors min-w-0 w-auto h-auto text-white"
              aria-label={t('update_banner.dismiss')}
            >
              <X className="w-4 h-4" />
            </Button>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export default UpdateAvailableBanner;
