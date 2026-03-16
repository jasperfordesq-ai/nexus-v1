// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cookie Consent Context
 *
 * Manages GDPR cookie consent state, persisted in localStorage AND
 * synced to the server for authenticated users (survives device changes).
 *
 * Three consent categories matching the Cookie Policy page:
 * - Essential (always on — cannot be toggled)
 * - Analytics (Sentry error tracking)
 * - Preferences (theme, locale)
 *
 * Server sync:
 * - On save → fire-and-forget POST /api/cookie-consent
 * - On mount (if auth token exists) → fetch server consent, prefer most recent
 */

import { createContext, useContext, useState, useCallback, useMemo, useEffect, useRef, type ReactNode } from 'react';
import { api, tokenManager } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface CookieConsent {
  essential: true; // Always on
  analytics: boolean;
  preferences: boolean;
  timestamp: string; // ISO 8601
}

interface CookieConsentContextValue {
  /** Current consent state, or null if user hasn't decided yet */
  consent: CookieConsent | null;
  /** Whether the banner should be shown */
  showBanner: boolean;
  /** Accept all cookie categories */
  acceptAll: () => void;
  /** Accept essential only (reject optional) */
  acceptEssentialOnly: () => void;
  /** Save custom preferences */
  savePreferences: (analytics: boolean, preferences: boolean) => void;
  /** Check if a specific category is consented */
  hasConsent: (category: 'analytics' | 'preferences') => boolean;
  /** Reset consent — re-shows the banner so user can change preferences */
  resetConsent: () => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const STORAGE_KEY = 'nexus_cookie_consent';
/** GDPR recommends re-asking consent every 6–12 months */
const CONSENT_MAX_AGE_MS = 6 * 30 * 24 * 60 * 60 * 1000; // ~6 months

// ─────────────────────────────────────────────────────────────────────────────
// Helpers (also used by sentry.ts before React mounts)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Read consent from localStorage (works outside React).
 * Used by sentry.ts to check consent before initialization.
 * Returns null if consent is expired (older than 6 months).
 */
export function readStoredConsent(): CookieConsent | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed.essential === 'boolean' && typeof parsed.timestamp === 'string') {
      // Check expiry — re-prompt after 6 months
      const age = Date.now() - new Date(parsed.timestamp).getTime();
      if (age > CONSENT_MAX_AGE_MS) {
        localStorage.removeItem(STORAGE_KEY);
        return null;
      }
      return parsed as CookieConsent;
    }
    return null;
  } catch {
    return null;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Server sync helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fire-and-forget POST to persist consent on the server.
 * Maps frontend categories to backend schema:
 *   preferences → functional, analytics → analytics, marketing always false
 */
function syncConsentToServer(consent: CookieConsent): void {
  if (!tokenManager.getAccessToken()) return; // Only sync for authenticated users

  api.post('/cookie-consent', {
    functional: consent.preferences,
    analytics: consent.analytics,
    marketing: false,
    source: 'web',
  });
}

/**
 * Fetch consent from server for authenticated users.
 * Returns mapped CookieConsent or null if no server record.
 */
async function fetchServerConsent(): Promise<CookieConsent | null> {
  if (!tokenManager.getAccessToken()) return null;

  try {
    const response = await api.get<{
      consent: {
        analytics: boolean;
        functional: boolean;
        created_at: string;
      } | null;
    }>('/cookie-consent');

    if (response.success && response.data?.consent) {
      return {
        essential: true,
        analytics: !!response.data.consent.analytics,
        preferences: !!response.data.consent.functional,
        timestamp: response.data.consent.created_at,
      };
    }
    return null;
  } catch {
    return null;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const CookieConsentContext = createContext<CookieConsentContextValue | null>(null);

export function CookieConsentProvider({ children }: { children: ReactNode }) {
  const [consent, setConsent] = useState<CookieConsent | null>(() => readStoredConsent());

  const prevAnalytics = useRef(consent?.analytics ?? false);
  const serverSyncDone = useRef(false);

  const persist = useCallback((newConsent: CookieConsent) => {
    setConsent(newConsent);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(newConsent));
    syncConsentToServer(newConsent);
  }, []);

  // On mount: if authenticated and no local consent, try to restore from server
  useEffect(() => {
    if (serverSyncDone.current) return;
    serverSyncDone.current = true;

    if (!tokenManager.getAccessToken()) return;

    const localConsent = readStoredConsent();
    fetchServerConsent().then((serverConsent) => {
      if (!serverConsent) return;

      // If no local consent, use server consent (e.g. new device)
      if (!localConsent) {
        setConsent(serverConsent);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(serverConsent));
        return;
      }

      // If server consent is newer, prefer it
      const localTime = new Date(localConsent.timestamp).getTime();
      const serverTime = new Date(serverConsent.timestamp).getTime();
      if (serverTime > localTime) {
        setConsent(serverConsent);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(serverConsent));
      }
    });
  }, []);

  // If user grants analytics consent mid-session, initialize Sentry
  useEffect(() => {
    if (consent?.analytics && !prevAnalytics.current) {
      import('@/lib/sentry').then(({ initSentry }) => initSentry()).catch(() => {
        // Sentry initialization is optional
      });
    }
    prevAnalytics.current = consent?.analytics ?? false;
  }, [consent?.analytics]);

  const acceptAll = useCallback(() => {
    persist({
      essential: true,
      analytics: true,
      preferences: true,
      timestamp: new Date().toISOString(),
    });
  }, [persist]);

  const acceptEssentialOnly = useCallback(() => {
    persist({
      essential: true,
      analytics: false,
      preferences: false,
      timestamp: new Date().toISOString(),
    });
  }, [persist]);

  const savePreferences = useCallback((analytics: boolean, preferences: boolean) => {
    persist({
      essential: true,
      analytics,
      preferences,
      timestamp: new Date().toISOString(),
    });
  }, [persist]);

  const hasConsent = useCallback(
    (category: 'analytics' | 'preferences') => consent?.[category] ?? false,
    [consent]
  );

  const resetConsent = useCallback(() => {
    setConsent(null);
    localStorage.removeItem(STORAGE_KEY);
  }, []);

  const showBanner = consent === null;

  const value = useMemo<CookieConsentContextValue>(
    () => ({ consent, showBanner, acceptAll, acceptEssentialOnly, savePreferences, hasConsent, resetConsent }),
    [consent, showBanner, acceptAll, acceptEssentialOnly, savePreferences, hasConsent, resetConsent]
  );

  return (
    <CookieConsentContext.Provider value={value}>
      {children}
    </CookieConsentContext.Provider>
  );
}

export function useCookieConsent(): CookieConsentContextValue {
  const ctx = useContext(CookieConsentContext);
  if (!ctx) {
    throw new Error('useCookieConsent must be used within CookieConsentProvider');
  }
  return ctx;
}
