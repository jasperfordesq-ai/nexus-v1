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
import { RefreshCw, X } from 'lucide-react';
import { Button } from '@heroui/react';

async function hasWaitingSW(): Promise<boolean> {
  try {
    const reg = await navigator.serviceWorker?.getRegistration();
    return !!reg?.waiting;
  } catch {
    return false;
  }
}

export function UpdateAvailableBanner() {
  const { t } = useTranslation('common');
  const [showBanner, setShowBanner] = useState(false);
  const [updating, setUpdating] = useState(false);
  const location = useLocation();

  const checkAndShow = useCallback(() => {
    hasWaitingSW().then((waiting) => {
      if (waiting) setShowBanner(true);
    });
  }, []);

  useEffect(() => {
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
    setUpdating(true);

    // Call the updateSW function stored by main.tsx to activate the new SW + reload.
    // The 3s timeout is a hard safety net — if *anything* hangs (postMessage lost,
    // controllerchange never fires, Promise stuck), we always reload.
    const updateSW = (window as NexusWindow).__nexus_updateSW;
    if (typeof updateSW === 'function') {
      const reloadTimeout = setTimeout(() => window.location.reload(), 3000);
      try {
        const result = updateSW(true);
        if (result && typeof (result as Promise<void>).then === 'function') {
          (result as Promise<void>).then(
            () => {
              // SW activated — but vite-plugin-pwa may not auto-reload (relies on
              // controllerchange which can miss). Give it 500ms, then force reload.
              clearTimeout(reloadTimeout);
              setTimeout(() => window.location.reload(), 500);
            },
            () => {
              clearTimeout(reloadTimeout);
              window.location.reload();
            }
          );
        }
        // If updateSW returned void (not a Promise), the 3s timeout handles it.
      } catch {
        clearTimeout(reloadTimeout);
        window.location.reload();
      }
    } else {
      window.location.reload();
    }
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
          <div className="bg-indigo-600 text-white text-center py-2 px-4 text-sm font-medium flex items-center justify-center gap-3">
            <RefreshCw className={`w-4 h-4 flex-shrink-0${updating ? ' animate-spin' : ''}`} aria-hidden="true" />
            <span>{t('update_banner.message')}</span>
            <Button
              size="sm"
              className="bg-white text-indigo-700 font-semibold min-w-0 h-7 px-3"
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
