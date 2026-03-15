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
 */

import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { motion, AnimatePresence } from 'framer-motion';
import { RefreshCw, X } from 'lucide-react';
import { Button } from '@heroui/react';

export function UpdateAvailableBanner() {
  const { t } = useTranslation('common');
  const [showBanner, setShowBanner] = useState(false);
  const [updating, setUpdating] = useState(false);

  useEffect(() => {
    // Fix race condition: if onNeedRefresh fired before React mounted,
    // the custom event was missed. Check the global flag set by main.tsx.
    if ((window as NexusWindow).__nexus_updatePending) {
      setShowBanner(true);
      (window as NexusWindow).__nexus_updatePending = false;
    }

    function handleUpdateAvailable() {
      setShowBanner(true);
    }

    window.addEventListener('nexus:sw_update_available', handleUpdateAvailable);
    return () => {
      window.removeEventListener('nexus:sw_update_available', handleUpdateAvailable);
    };
  }, []);

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
