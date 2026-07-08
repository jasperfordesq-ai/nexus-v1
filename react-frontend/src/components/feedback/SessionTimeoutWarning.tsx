// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SessionTimeoutWarning — WCAG 2.2.1 compliance
 *
 * Shows a modal ≥ 20 seconds before the user's session token expires,
 * giving them the option to extend their session or log out.
 *
 * Listens for SESSION_EXPIRING_EVENT dispatched by AuthContext after each
 * successful login/token refresh. On "Extend session" it calls the token
 * refresh endpoint and reschedules the warning timer.
 */

import { useEffect, useRef, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import Clock from 'lucide-react/icons/clock';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import LogOut from 'lucide-react/icons/log-out';

import { SESSION_EXPIRING_EVENT, tokenManager } from '@/lib/api';
import { useAuth } from '@/contexts/AuthContext';
import { useTenant } from '@/contexts/TenantContext';
import { Button, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@/components/ui';

// How long to count down in the warning (seconds shown to user)
const COUNTDOWN_SECONDS = 30;
const API_BASE = import.meta.env.VITE_API_BASE || '/api';

export function SessionTimeoutWarning() {
  const { t } = useTranslation('errors');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const { logout, scheduleSessionWarning, isAuthenticated } = useAuth();

  const [isOpen, setIsOpen] = useState(false);
  const [countdown, setCountdown] = useState(COUNTDOWN_SECONDS);
  const [isExtending, setIsExtending] = useState(false);

  const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const isAuthenticatedRef = useRef(isAuthenticated);
  isAuthenticatedRef.current = isAuthenticated;

  const stopCountdown = useCallback(() => {
    if (countdownRef.current !== null) {
      clearInterval(countdownRef.current);
      countdownRef.current = null;
    }
  }, []);

  const startCountdown = useCallback(() => {
    stopCountdown();
    setCountdown(COUNTDOWN_SECONDS);
    countdownRef.current = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          stopCountdown();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
  }, [stopCountdown]);

  const handleClose = useCallback(() => {
    stopCountdown();
    setIsOpen(false);
  }, [stopCountdown]);

  const handleExtend = useCallback(async () => {
    setIsExtending(true);
    try {
      const refreshToken = tokenManager.getRefreshToken();
      if (!refreshToken) {
        // Nothing to refresh — close the warning silently
        handleClose();
        return;
      }

      const refreshHeaders: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };
      const tenantId = tokenManager.getTenantId();
      if (tenantId) refreshHeaders['X-Tenant-ID'] = tenantId;

      const response = await fetch(`${API_BASE}/auth/refresh-token`, {
        method: 'POST',
        headers: refreshHeaders,
        body: JSON.stringify({ refresh_token: refreshToken }),
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success && data.access_token) {
          tokenManager.setAccessToken(data.access_token);
          if (data.refresh_token) {
            tokenManager.setRefreshToken(data.refresh_token);
          }
          // Reschedule the warning for the new token lifetime
          if (data.expires_in) {
            scheduleSessionWarning(data.expires_in);
          }
          handleClose();
          return;
        }
      }

      // Refresh failed — session is gone, log out gracefully
      handleClose();
      await logout();
      navigate(tenantPath('/login'));
    } finally {
      setIsExtending(false);
    }
  }, [handleClose, logout, navigate, tenantPath, scheduleSessionWarning]);

  const handleLogout = useCallback(async () => {
    handleClose();
    await logout();
    navigate(tenantPath('/login'));
  }, [handleClose, logout, navigate, tenantPath]);

  // Auto-logout when countdown reaches zero
  useEffect(() => {
    if (countdown === 0 && isOpen) {
      void handleLogout();
    }
  }, [countdown, isOpen, handleLogout]);

  // Listen for the warning event from AuthContext
  useEffect(() => {
    const handleExpiring = () => {
      // Only show if user is currently authenticated
      if (!isAuthenticatedRef.current) return;
      setIsOpen(true);
      startCountdown();
    };

    window.addEventListener(SESSION_EXPIRING_EVENT, handleExpiring);
    return () => {
      window.removeEventListener(SESSION_EXPIRING_EVENT, handleExpiring);
      stopCountdown();
    };
  }, [startCountdown, stopCountdown]);

  // Close and cancel countdown when the user is no longer authenticated
  // (e.g., they logged out in another tab)
  useEffect(() => {
    if (!isAuthenticated && isOpen) {
      handleClose();
    }
  }, [isAuthenticated, isOpen, handleClose]);

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      size="sm"
      isDismissable={false}
      classNames={{
        base: 'bg-overlay border border-theme-default',
      }}
      aria-labelledby="session-timeout-title"
      aria-describedby="session-timeout-description"
    >
      <ModalContent>
        {() => (
          <>
            <ModalHeader
              id="session-timeout-title"
              className="flex flex-col items-center gap-0 pt-6 pb-2"
            >
              <div className="flex justify-center mb-4">
                <div className="relative p-4 rounded-full bg-amber-500/20">
                  <Clock className="w-8 h-8 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                  {/* Countdown badge */}
                  <span
                    className="absolute -top-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white"
                    aria-hidden="true"
                  >
                    {countdown}
                  </span>
                </div>
              </div>
              <span className="text-xl font-semibold text-theme-primary">
                {t('session_expiring')}
              </span>
            </ModalHeader>

            <ModalBody
              id="session-timeout-description"
              className="text-center pb-4"
            >
              <p className="text-theme-muted">
                {t('session_expiring_message', { seconds: countdown })}
              </p>
              {/* Live region so screen readers announce the countdown.
                  Throttled so it doesn't speak every single second (which
                  floods the SR queue): step in 10s increments, then each of
                  the final 5 seconds. */}
              <p
                aria-live="polite"
                aria-atomic="true"
                className="sr-only"
              >
                {t('session_expiring_countdown_aria', {
                  seconds: countdown <= 5 ? countdown : Math.ceil(countdown / 10) * 10,
                })}
              </p>
            </ModalBody>

            <ModalFooter className="gap-3">
              <Button
                variant="flat"
                className="flex-1 bg-theme-elevated text-theme-primary"
                startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                onPress={() => { void handleLogout(); }}
              >
                {t('auth.log_out', { ns: 'common' })}
              </Button>
              <Button
                className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
                isLoading={isExtending}
                onPress={() => { void handleExtend(); }}
              >
                {t('extend_session')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

export default SessionTimeoutWarning;
