// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useLegalGate - Check and manage legal document acceptance for the current user.
 *
 * Polls GET /v2/legal/acceptance/status after the user is authenticated.
 * Provides an acceptAll() function that posts to /v2/legal/acceptance/accept-all.
 *
 * If the tenant has no legal documents requiring acceptance, has_pending will
 * always be false and the gate will not appear.
 */

import { useEffect, useState, useCallback } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/contexts';

export interface PendingDocument {
  document_id: number;
  document_type: string;
  title: string;
  current_version_id: number | null;
  current_version: string | null;
  acceptance_status: 'not_accepted' | 'outdated' | 'current';
  accepted_at: string | null;
}

interface LegalStatusResponse {
  has_pending: boolean;
  documents: PendingDocument[];
}

export interface LegalGateState {
  /** True when a blocking legal document acceptance is needed */
  hasPending: boolean;
  /** Documents the user needs to accept */
  pendingDocs: PendingDocument[];
  /** Accept all pending documents */
  acceptAll: () => Promise<void>;
  /** True while fetching acceptance status */
  isLoading: boolean;
  /** True while saving acceptances */
  isAccepting: boolean;
  /** Refreshes acceptance status from the server */
  refresh: () => void;
}

export function useLegalGate(): LegalGateState {
  const { isAuthenticated, isLoading: authLoading } = useAuth();
  const [hasPending, setHasPending] = useState(false);
  const [pendingDocs, setPendingDocs] = useState<PendingDocument[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isAccepting, setIsAccepting] = useState(false);
  const [refreshTick, setRefreshTick] = useState(0);

  const refresh = useCallback(() => setRefreshTick((t) => t + 1), []);

  useEffect(() => {
    if (authLoading || !isAuthenticated) {
      setHasPending(false);
      setPendingDocs([]);
      return;
    }

    let cancelled = false;
    setIsLoading(true);

    api
      .get<LegalStatusResponse>('/v2/legal/acceptance/status')
      .then((result) => {
        if (cancelled) return;
        if (result.success && result.data) {
          const { has_pending, documents } = result.data;
          setHasPending(has_pending);
          setPendingDocs(
            documents.filter(
              (d) => d.acceptance_status !== 'current'
            )
          );
        } else {
          // Silently suppress errors — gate should not block the app if API fails
          setHasPending(false);
          setPendingDocs([]);
        }
      })
      .catch(() => {
        if (cancelled) return;
        setHasPending(false);
        setPendingDocs([]);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => { cancelled = true; };
  }, [isAuthenticated, authLoading, refreshTick]);

  const acceptAll = useCallback(async () => {
    setIsAccepting(true);
    try {
      const result = await api.post('/v2/legal/acceptance/accept-all', {});
      if (result.success) {
        setHasPending(false);
        setPendingDocs([]);
      }
    } finally {
      setIsAccepting(false);
    }
  }, []);

  return { hasPending, pendingDocs, acceptAll, isLoading, isAccepting, refresh };
}

export default useLegalGate;
