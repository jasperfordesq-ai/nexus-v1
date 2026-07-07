// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { safeLocalStorageGet, safeLocalStorageRemove, safeLocalStorageSetJSON } from '@/lib/safeStorage';

export interface CookieConsent {
  essential: true;
  analytics: boolean;
  preferences: boolean;
  timestamp: string;
}

const STORAGE_KEY = 'nexus_cookie_consent';
const CONSENT_MAX_AGE_MS = 6 * 30 * 24 * 60 * 60 * 1000;

export function readStoredConsent(): CookieConsent | null {
  try {
    const raw = safeLocalStorageGet(STORAGE_KEY);
    if (!raw) return null;

    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed.essential === 'boolean' && typeof parsed.timestamp === 'string') {
      const age = Date.now() - new Date(parsed.timestamp).getTime();
      if (age > CONSENT_MAX_AGE_MS) {
        safeLocalStorageRemove(STORAGE_KEY);
        return null;
      }
      return parsed as CookieConsent;
    }

    return null;
  } catch {
    return null;
  }
}

export function writeStoredConsent(consent: CookieConsent): void {
  safeLocalStorageSetJSON(STORAGE_KEY, consent);
}

export function clearStoredConsent(): void {
  safeLocalStorageRemove(STORAGE_KEY);
}
