// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * API Error Handler Hook
 * Listens for API error events and displays toast notifications
 */

import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts/ToastContext';
import { API_ERROR_EVENT, type ApiErrorEventDetail } from '@/lib/api';

/**
 * Hook to automatically display toast notifications for API errors.
 * Should be used once at the app level (e.g., in Layout component).
 */
export function useApiErrorHandler() {
  const { error } = useToast();
  const { t } = useTranslation('errors');
  const lastToastRef = useRef<{ key: string; at: number } | null>(null);

  useEffect(() => {
    function handleApiError(event: CustomEvent<ApiErrorEventDetail>) {
      const { code } = event.detail;

      // Map error codes to user-friendly messages
      const userMessage = getErrorMessage(code, t);

      // Session expiry has its own modal. Intentional caller/upload cancellation
      // is control flow, not a request failure, and must remain silent.
      if (code === 'SESSION_EXPIRED' || code === 'CANCELLED' || code === 'UPLOAD_ABORTED') {
        return;
      }

      const key = `${code}:${userMessage}`;
      const now = Date.now();
      if (lastToastRef.current?.key === key && now - lastToastRef.current.at < 4000) {
        return;
      }
      lastToastRef.current = { key, at: now };

      error(t('api.request_failed_title'), userMessage);
    }

    window.addEventListener(API_ERROR_EVENT, handleApiError as EventListener);
    return () => window.removeEventListener(API_ERROR_EVENT, handleApiError as EventListener);
  }, [error, t]);
}

/**
 * Map error codes to user-friendly messages
 */
function getErrorMessage(
  code: string,
  t: ReturnType<typeof useTranslation<'errors'>>['t'],
): string {
  const messageKeys = {
    NETWORK_ERROR: 'api.network_error',
    PARSE_ERROR: 'api.invalid_response',
    HTTP_400: 'api.invalid_request',
    HTTP_403: 'api.permission_denied',
    HTTP_404: 'api.resource_not_found',
    HTTP_429: 'api.too_many_requests',
    HTTP_500: 'api.server_error',
    HTTP_502: 'api.service_unavailable',
    HTTP_503: 'api.service_unavailable',
  } as const;

  const key = messageKeys[code as keyof typeof messageKeys];
  return key ? t(key) : t('api.request_failed');
}

export default useApiErrorHandler;
