/**
 * Session Expired Modal
 * Displays when the user's session has expired and they need to log in again
 */

import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button } from '@heroui/react';
import { LogIn, Clock } from 'lucide-react';
import { SESSION_EXPIRED_EVENT } from '@/lib/api';

export function SessionExpiredModal() {
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    function handleSessionExpired() {
      setIsOpen(true);
    }

    window.addEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
  }, []);

  function handleLogin() {
    setIsOpen(false);
    navigate('/login');
  }

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/50 dark:bg-black/60 backdrop-blur-sm z-[9998]"
            onClick={() => setIsOpen(false)}
            aria-hidden="true"
          />

          {/* Modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 20 }}
            className="fixed inset-0 flex items-center justify-center z-[9999] p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="session-expired-title"
            aria-describedby="session-expired-description"
          >
            <div className="bg-white dark:bg-gray-900/95 backdrop-blur-xl border border-theme-default rounded-2xl p-6 max-w-sm w-full shadow-2xl">
              {/* Icon */}
              <div className="flex justify-center mb-4">
                <div className="p-4 rounded-full bg-amber-500/20">
                  <Clock className="w-8 h-8 text-amber-600 dark:text-amber-400" />
                </div>
              </div>

              {/* Content */}
              <div className="text-center mb-6">
                <h2 id="session-expired-title" className="text-xl font-semibold text-theme-primary mb-2">
                  Session Expired
                </h2>
                <p id="session-expired-description" className="text-theme-muted">
                  Your session has expired due to inactivity. Please log in again to continue.
                </p>
              </div>

              {/* Actions */}
              <div className="flex gap-3">
                <Button
                  variant="flat"
                  className="flex-1 bg-theme-elevated text-theme-primary"
                  onClick={() => setIsOpen(false)}
                >
                  Dismiss
                </Button>
                <Button
                  className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<LogIn className="w-4 h-4" />}
                  onClick={handleLogin}
                >
                  Log In
                </Button>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}

export default SessionExpiredModal;
