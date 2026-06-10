// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useToast } from '@/contexts';

/**
 * Auto-logout after a tenant-configurable period of user inactivity.
 *
 * The timeout comes from the tenant bootstrap setting
 * `inactivity_timeout_minutes` (admin-configurable, 0/absent = disabled,
 * 5–480 minutes when enabled). Activity is tracked across tabs via
 * localStorage so a user active in one tab is not logged out in another.
 */

const LAST_ACTIVITY_KEY = 'nexus_last_activity';
const CHECK_INTERVAL_MS = 30_000;
/** Throttle for localStorage writes — activity bursts only persist once per window. */
const PERSIST_THROTTLE_MS = 15_000;
const ACTIVITY_EVENTS: ReadonlyArray<keyof WindowEventMap> = [
  'pointerdown',
  'keydown',
  'wheel',
  'touchstart',
  'mousemove',
];

/** Parse the tenant setting into milliseconds; 0 = disabled. */
export function parseIdleTimeoutMs(raw: unknown): number {
  // Bootstrap coerces stored '0' to false; the validator guarantees enabled
  // values are >= 5 so a boolean can only ever mean "disabled".
  if (raw === null || raw === undefined || typeof raw === 'boolean') return 0;
  const minutes = Number(raw);
  if (!Number.isFinite(minutes) || minutes < 5) return 0;
  return Math.min(minutes, 480) * 60_000;
}

function readPersistedActivity(): number {
  try {
    const raw = localStorage.getItem(LAST_ACTIVITY_KEY);
    const ts = raw === null ? NaN : Number(raw);
    return Number.isFinite(ts) ? ts : 0;
  } catch {
    return 0;
  }
}

export function useIdleLogout(): void {
  const { user, logout } = useAuth();
  const { tenant } = useTenant();
  const toast = useToast();
  const { t } = useTranslation('errors');

  const timeoutMs = parseIdleTimeoutMs(tenant?.settings?.inactivity_timeout_minutes);
  const isActive = Boolean(user) && timeoutMs > 0;

  const lastActivityRef = useRef<number>(Date.now());
  const lastPersistRef = useRef<number>(0);
  const loggingOutRef = useRef<boolean>(false);

  useEffect(() => {
    if (!isActive) return;

    lastActivityRef.current = Date.now();
    loggingOutRef.current = false;

    const recordActivity = () => {
      const now = Date.now();
      lastActivityRef.current = now;
      if (now - lastPersistRef.current >= PERSIST_THROTTLE_MS) {
        lastPersistRef.current = now;
        try {
          localStorage.setItem(LAST_ACTIVITY_KEY, String(now));
        } catch {
          // Storage unavailable (private mode / quota) — in-tab tracking still works
        }
      }
    };

    const checkIdle = () => {
      if (loggingOutRef.current) return;
      const effectiveLast = Math.max(lastActivityRef.current, readPersistedActivity());
      if (Date.now() - effectiveLast < timeoutMs) return;

      loggingOutRef.current = true;
      try {
        localStorage.removeItem(LAST_ACTIVITY_KEY);
      } catch {
        // ignore
      }
      void logout().finally(() => {
        toast.info(t('idle_logout_title'), t('idle_logout_message'));
      });
    };

    const onVisibilityChange = () => {
      // A tab waking from sleep may be long past the deadline — check at once
      if (document.visibilityState === 'visible') {
        checkIdle();
      }
    };

    ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, recordActivity, { passive: true }));
    document.addEventListener('visibilitychange', onVisibilityChange);
    const interval = window.setInterval(checkIdle, CHECK_INTERVAL_MS);

    return () => {
      ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, recordActivity));
      document.removeEventListener('visibilitychange', onVisibilityChange);
      window.clearInterval(interval);
    };
  }, [isActive, timeoutMs, logout, toast, t]);
}
