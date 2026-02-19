// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * API Error Handler Hook
 * Listens for API error events and displays toast notifications
 */

import { useEffect } from 'react';
import { useToast } from '@/contexts';
import { API_ERROR_EVENT, type ApiErrorEventDetail } from '@/lib/api';

/**
 * Hook to automatically display toast notifications for API errors.
 * Should be used once at the app level (e.g., in Layout component).
 */
export function useApiErrorHandler() {
  const { error } = useToast();

  useEffect(() => {
    function handleApiError(event: CustomEvent<ApiErrorEventDetail>) {
      const { message, code } = event.detail;

      // Map error codes to user-friendly messages
      const userMessage = getErrorMessage(code, message);

      // Don't show toast for session expired (modal handles that)
      if (code === 'SESSION_EXPIRED') {
        return;
      }

      error('Request Failed', userMessage);
    }

    window.addEventListener(API_ERROR_EVENT, handleApiError as EventListener);
    return () => window.removeEventListener(API_ERROR_EVENT, handleApiError as EventListener);
  }, [error]);
}

/**
 * Map error codes to user-friendly messages
 */
function getErrorMessage(code: string, fallback: string): string {
  const messages: Record<string, string> = {
    NETWORK_ERROR: 'Unable to connect to the server. Please check your internet connection.',
    PARSE_ERROR: 'Received an invalid response from the server.',
    HTTP_400: 'The request was invalid. Please check your input.',
    HTTP_403: 'You do not have permission to perform this action.',
    HTTP_404: 'The requested resource was not found.',
    HTTP_429: 'Too many requests. Please wait a moment and try again.',
    HTTP_500: 'An unexpected server error occurred. Please try again later.',
    HTTP_502: 'The server is temporarily unavailable. Please try again later.',
    HTTP_503: 'The service is temporarily unavailable. Please try again later.',
  };

  return messages[code] || fallback;
}

export default useApiErrorHandler;
