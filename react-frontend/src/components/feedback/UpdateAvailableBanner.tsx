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
import { motion, AnimatePresence } from 'framer-motion';
import { RefreshCw, X } from 'lucide-react';
import { Button } from '@heroui/react';

export function UpdateAvailableBanner() {
  const [showBanner, setShowBanner] = useState(false);

  useEffect(() => {
    function handleUpdateAvailable() {
      setShowBanner(true);
    }

    window.addEventListener('nexus:sw_update_available', handleUpdateAvailable);
    return () => {
      window.removeEventListener('nexus:sw_update_available', handleUpdateAvailable);
    };
  }, []);

  function handleUpdate() {
    // Call the updateSW function stored by main.tsx to activate the new SW + reload
    const updateSW = (window as any).__nexus_updateSW;
    if (typeof updateSW === 'function') {
      updateSW(true); // true = reload page
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
          initial={{ y: -60, opacity: 0 }}
          animate={{ y: 0, opacity: 1 }}
          exit={{ y: -60, opacity: 0 }}
          transition={{ duration: 0.3, ease: 'easeOut' }}
          className="fixed top-0 left-0 right-0 z-[70] pointer-events-none"
        >
          <div className="bg-indigo-600 text-white text-center py-2 px-4 text-sm font-medium flex items-center justify-center gap-3 pointer-events-auto">
            <RefreshCw className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
            <span>A new version is available.</span>
            <Button
              size="sm"
              className="bg-white text-indigo-700 font-semibold min-w-0 h-7 px-3"
              onPress={handleUpdate}
            >
              Update now
            </Button>
            <button
              onClick={handleDismiss}
              className="ml-1 p-1 rounded hover:bg-indigo-500 transition-colors"
              aria-label="Dismiss update notification"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export default UpdateAvailableBanner;
