// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';

/**
 * usePasswordCheck — live NIST SP 800-63B aligned password strength check.
 *
 * Modern password policy (NIST 2017+):
 *   - Length is the primary security signal (min 12 chars).
 *   - Character-class rules (must-have-uppercase / digit / symbol) push
 *     users toward predictable patterns like "P@ssw0rd1!" and are removed.
 *   - The meaningful check is against breach corpora (Have I Been Pwned)
 *     because attackers use credential-stuffing from those exact lists.
 *
 * This hook runs both checks in the browser as the user types:
 *   1. Length check — instant
 *   2. HIBP k-anonymity check — debounced 350ms after typing stops. SHA-1
 *      hashes the password locally, sends only the first 5 hex chars to
 *      api.pwnedpasswords.com/range/{prefix}, looks up the remaining
 *      suffix in the returned list. Server never learns the password.
 *
 * Failure mode: HIBP network error → treated as "not pwned" (fail-open) so
 * an HIBP outage doesn't block registration. The server-side check (which
 * fails-open too but logs the outage) is the backstop.
 */

export const PASSWORD_MIN_LENGTH = 12;

const HIBP_API = 'https://api.pwnedpasswords.com/range/';

async function sha1Hex(input: string): Promise<string> {
  const data = new TextEncoder().encode(input);
  const buf = await crypto.subtle.digest('SHA-1', data);
  return Array.from(new Uint8Array(buf))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

export interface PasswordCheckState {
  /** Current character count. */
  length: number;
  /** True when the password meets the length minimum. */
  isLongEnough: boolean;
  /** null = not yet checked, true = appears in HIBP corpus, false = clean. */
  isPwned: boolean | null;
  /** True while the HIBP request is in flight. */
  isChecking: boolean;
  /** True when the password is acceptable for submission. */
  isAcceptable: boolean;
  /** Plain-language status message for the user. */
  message: string;
  /** Severity for visual styling. */
  tone: 'idle' | 'warn' | 'error' | 'success';
}

// Cache results by SHA-1 hash so that re-typing the same password doesn't
// spam HIBP. Map is process-scoped — fine for a single registration session.
const checkCache = new Map<string, boolean>();

export function usePasswordCheck(password: string): PasswordCheckState {
  const length = password.length;
  const isLongEnough = length >= PASSWORD_MIN_LENGTH;
  const [isPwned, setIsPwned] = useState<boolean | null>(null);
  const [isChecking, setIsChecking] = useState(false);

  useEffect(() => {
    if (!isLongEnough) {
      setIsPwned(null);
      setIsChecking(false);
      return;
    }

    let cancelled = false;
    setIsChecking(true);

    const timer = window.setTimeout(async () => {
      try {
        const hash = (await sha1Hex(password)).toUpperCase();
        if (cancelled) return;
        if (checkCache.has(hash)) {
          setIsPwned(checkCache.get(hash) === true);
          setIsChecking(false);
          return;
        }
        const prefix = hash.slice(0, 5);
        const suffix = hash.slice(5);
        const resp = await fetch(`${HIBP_API}${prefix}`, {
          headers: { 'Add-Padding': 'true' },
        });
        if (cancelled) return;
        if (!resp.ok) {
          setIsPwned(false); // fail-open
          setIsChecking(false);
          return;
        }
        const body = await resp.text();
        const found = body.split('\n').some((line) => {
          const [s, c] = line.trim().split(':');
          return s === suffix && Number(c) > 0;
        });
        checkCache.set(hash, found);
        if (!cancelled) {
          setIsPwned(found);
          setIsChecking(false);
        }
      } catch {
        if (!cancelled) {
          setIsPwned(false); // fail-open on network/crypto error
          setIsChecking(false);
        }
      }
    }, 350);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [password, isLongEnough]);

  let message = '';
  let tone: PasswordCheckState['tone'] = 'idle';

  if (length === 0) {
    message = `Use ${PASSWORD_MIN_LENGTH} or more characters. A memorable passphrase is stronger than a short complex one.`;
    tone = 'idle';
  } else if (!isLongEnough) {
    const remaining = PASSWORD_MIN_LENGTH - length;
    message = `Add ${remaining} more character${remaining === 1 ? '' : 's'}.`;
    tone = 'warn';
  } else if (isChecking) {
    message = 'Checking against known data breaches…';
    tone = 'idle';
  } else if (isPwned === true) {
    message = 'This password appears in a known data breach. Please choose a different one.';
    tone = 'error';
  } else if (isPwned === false) {
    message = 'Strong enough.';
    tone = 'success';
  }

  const isAcceptable = isLongEnough && isPwned === false;

  return {
    length,
    isLongEnough,
    isPwned,
    isChecking,
    isAcceptable,
    message,
    tone,
  };
}
