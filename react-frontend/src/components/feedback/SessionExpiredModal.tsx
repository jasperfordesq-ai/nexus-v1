// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Session Expired Modal
 * Displays when the user's session has expired and they need to log in again
 */

import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@heroui/react';
import { LogIn, Clock } from 'lucide-react';
import { SESSION_EXPIRED_EVENT } from '@/lib/api';
import { useTenant, useAuth } from '@/contexts';

export function SessionExpiredModal() {
  const { t } = useTranslation('errors');
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const { status } = useAuth();
  const wasAuthenticated = useRef(false);

  // Track if user was ever authenticated in this session
  useEffect(() => {
    if (status === 'authenticated') {
      wasAuthenticated.current = true;
    }
  }, [status]);

  useEffect(() => {
    function handleSessionExpired() {
      // Only show modal if user had an active session — not for stale tokens on first visit
      if (wasAuthenticated.current) {
        setIsOpen(true);
      }
    }

    window.addEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
  }, []);

  function handleLogin() {
    setIsOpen(false);
    navigate(tenantPath('/login'));
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={() => setIsOpen(false)}
      size="sm"
      classNames={{
        base: 'bg-content1 border border-theme-default',
      }}
    >
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader
              id="session-expired-title"
              className="flex flex-col items-center gap-0 pt-6 pb-2"
            >
              {/* Icon */}
              <div className="flex justify-center mb-4">
                <div className="p-4 rounded-full bg-amber-500/20">
                  <Clock className="w-8 h-8 text-amber-600 dark:text-amber-400" />
                </div>
              </div>
              <span className="text-xl font-semibold text-theme-primary">{t('session_expired')}</span>
            </ModalHeader>

            <ModalBody
              id="session-expired-description"
              className="text-center pb-4"
            >
              <p className="text-theme-muted">
                {t('session_expired_message')}
              </p>
            </ModalBody>

            <ModalFooter className="gap-3">
              <Button
                variant="flat"
                className="flex-1 bg-theme-elevated text-theme-primary"
                onPress={onClose}
              >
                {t('dismiss')}
              </Button>
              <Button
                className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<LogIn className="w-4 h-4" />}
                onPress={handleLogin}
              >
                {t('auth.log_in', { ns: 'common' })}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

export default SessionExpiredModal;
