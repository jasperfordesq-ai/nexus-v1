// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useCountUp — animates a number from its previous value to the target using
 * requestAnimationFrame with an ease-out curve. Falls back to the target value
 * instantly when the user prefers reduced motion, when rAF is unavailable
 * (jsdom), or in test mode (deterministic assertions).
 */

import { useEffect, useRef, useState } from 'react';

function prefersReducedMotion(): boolean {
  if (typeof document !== 'undefined' && document.documentElement.dataset.reducedMotion === 'true') {
    return true;
  }
  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }
  return false;
}

export function useCountUp(target: number, durationMs = 700): number {
  const [display, setDisplay] = useState(target);
  const fromRef = useRef(target);
  const frameRef = useRef<number | null>(null);

  useEffect(() => {
    const from = fromRef.current;
    fromRef.current = target;

    if (
      from === target ||
      import.meta.env.MODE === 'test' ||
      prefersReducedMotion() ||
      typeof window === 'undefined' ||
      typeof window.requestAnimationFrame !== 'function'
    ) {
      setDisplay(target);
      return;
    }

    const start = performance.now();
    const step = (now: number) => {
      const t = Math.min(1, (now - start) / durationMs);
      const eased = 1 - Math.pow(1 - t, 3); // ease-out cubic
      setDisplay(Math.round(from + (target - from) * eased));
      if (t < 1) {
        frameRef.current = window.requestAnimationFrame(step);
      }
    };
    frameRef.current = window.requestAnimationFrame(step);

    return () => {
      if (frameRef.current !== null) {
        window.cancelAnimationFrame(frameRef.current);
        frameRef.current = null;
      }
    };
  }, [target, durationMs]);

  return display;
}

export default useCountUp;
