// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OfflineIndicator - Shows a banner when the user goes offline
 * Automatically appears/disappears based on navigator.onLine status
 */

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { WifiOff } from 'lucide-react';

export function OfflineIndicator() {
  const [isOffline, setIsOffline] = useState(!navigator.onLine);

  useEffect(() => {
    function handleOffline() { setIsOffline(true); }
    function handleOnline() { setIsOffline(false); }

    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);
    return () => {
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener('online', handleOnline);
    };
  }, []);

  return (
    <AnimatePresence>
      {isOffline && (
        <motion.div
          initial={{ height: 0, opacity: 0 }}
          animate={{ height: 'auto', opacity: 1 }}
          exit={{ height: 0, opacity: 0 }}
          transition={{ duration: 0.2 }}
          className="fixed top-0 left-0 right-0 z-[60] overflow-hidden"
          role="alert"
        >
          <div className="bg-amber-500 text-white text-center py-2 px-4 text-sm font-medium flex items-center justify-center gap-2">
            <WifiOff className="w-4 h-4" aria-hidden="true" />
            <span>You are offline. Some features may be unavailable.</span>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export default OfflineIndicator;
