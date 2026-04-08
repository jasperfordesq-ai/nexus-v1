// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useLongPress — Touch-based long-press handler for mobile.
 *
 * Returns event handlers to attach to a target element.
 * Cancels if the finger moves more than the move threshold (prevents
 * false triggers during scrolling). Desktop-safe: only binds touch events.
 */

import { useRef, useCallback } from 'react';

interface UseLongPressOptions {
  /** Callback fired on long-press */
  onLongPress: () => void;
  /** Hold duration in ms (default: 500) */
  delay?: number;
  /** Max finger movement before cancelling (px, default: 10) */
  moveThreshold?: number;
}

export function useLongPress({
  onLongPress,
  delay = 500,
  moveThreshold = 10,
}: UseLongPressOptions) {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const startPos = useRef({ x: 0, y: 0 });
  const onLongPressRef = useRef(onLongPress);
  onLongPressRef.current = onLongPress;

  const clear = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const onTouchStart = useCallback((e: React.TouchEvent) => {
    // Don't trigger long-press when user is interacting with form inputs
    const target = e.target as HTMLElement;
    const tag = target.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable || target.closest('[role="textbox"]')) {
      return;
    }

    const touch = e.touches[0];
    if (!touch) return;
    startPos.current = { x: touch.clientX, y: touch.clientY };
    clear();
    timerRef.current = setTimeout(() => {
      onLongPressRef.current();
      timerRef.current = null;
    }, delay);
  }, [delay, clear]);

  const onTouchMove = useCallback((e: React.TouchEvent) => {
    if (!timerRef.current) return;
    const touch = e.touches[0];
    if (!touch) return;
    const dx = Math.abs(touch.clientX - startPos.current.x);
    const dy = Math.abs(touch.clientY - startPos.current.y);
    if (dx > moveThreshold || dy > moveThreshold) {
      clear();
    }
  }, [moveThreshold, clear]);

  const onTouchEnd = useCallback(() => {
    clear();
  }, [clear]);

  return { onTouchStart, onTouchMove, onTouchEnd };
}
