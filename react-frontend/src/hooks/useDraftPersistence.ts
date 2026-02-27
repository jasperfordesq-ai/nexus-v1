// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback, useRef, useEffect } from 'react';

/**
 * useDraftPersistence — Saves and restores compose form drafts from localStorage.
 *
 * On mount, checks localStorage for a saved draft under `key`. If found and
 * parseable, uses it as the initial value; otherwise falls back to `initialValue`.
 *
 * `setValue` updates React state immediately and debounce-saves to localStorage
 * after a 2-second delay. Supports both direct value and updater function patterns.
 *
 * `clearDraft` removes the key from localStorage and resets state to `initialValue`.
 *
 * @param key - Unique localStorage key for this draft (e.g., 'compose-draft-post')
 * @param initialValue - Default value when no draft exists
 * @returns [value, setValue, clearDraft]
 */
export function useDraftPersistence<T>(
  key: string,
  initialValue: T,
): [T, (val: T | ((prev: T) => T)) => void, () => void] {
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [value, setValueInternal] = useState<T>(() => {
    try {
      const stored = localStorage.getItem(key);
      if (stored !== null) {
        return JSON.parse(stored) as T;
      }
    } catch {
      // Corrupt data — remove it and fall back to initialValue
      try {
        localStorage.removeItem(key);
      } catch {
        // localStorage might be unavailable entirely; ignore
      }
    }
    return initialValue;
  });

  // Keep initialValue in a ref so clearDraft always uses the latest
  const initialValueRef = useRef(initialValue);
  initialValueRef.current = initialValue;

  // Keep key in a ref for the debounce callback
  const keyRef = useRef(key);
  keyRef.current = key;

  const persistToStorage = useCallback((val: T) => {
    if (debounceRef.current !== null) {
      clearTimeout(debounceRef.current);
    }
    debounceRef.current = setTimeout(() => {
      try {
        const serialized = JSON.stringify(val);
        // Skip persistence for empty/default drafts to avoid localStorage bloat.
        // Also guard against extremely large drafts (>100KB) that could cause
        // quota issues on mobile browsers with limited localStorage.
        if (serialized === JSON.stringify(initialValueRef.current)) {
          localStorage.removeItem(keyRef.current);
        } else if (serialized.length <= 100_000) {
          localStorage.setItem(keyRef.current, serialized);
        }
        // If >100KB, silently skip — the draft is still in React state
      } catch {
        // Quota exceeded or localStorage unavailable — silently fail
      }
      debounceRef.current = null;
    }, 2000);
  }, []);

  const setValue = useCallback(
    (val: T | ((prev: T) => T)) => {
      setValueInternal((prev) => {
        const next = typeof val === 'function' ? (val as (prev: T) => T)(prev) : val;
        persistToStorage(next);
        return next;
      });
    },
    [persistToStorage],
  );

  const clearDraft = useCallback(() => {
    if (debounceRef.current !== null) {
      clearTimeout(debounceRef.current);
      debounceRef.current = null;
    }
    try {
      localStorage.removeItem(keyRef.current);
    } catch {
      // localStorage unavailable — ignore
    }
    setValueInternal(initialValueRef.current);
  }, []);

  // Clean up pending timeout on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current !== null) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  return [value, setValue, clearDraft];
}
