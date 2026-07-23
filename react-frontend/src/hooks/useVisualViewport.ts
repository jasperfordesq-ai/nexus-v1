// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * useVisualViewport — soft-keyboard height tracking via the VisualViewport API.
 *
 * Returns the number of CSS pixels the visual viewport is inset from the
 * bottom of the layout viewport (`window.innerHeight - visualViewport.height
 * - visualViewport.offsetTop`, clamped to >= 0). On iOS Safari the layout
 * viewport (and therefore `100dvh`) does NOT shrink when the on-screen
 * keyboard opens — only the visual viewport does — so fixed-height chat
 * layouts must subtract this offset to keep the composer visible above the
 * keyboard. Android Chrome resizes the layout viewport with the keyboard
 * (index.html sets interactive-widget=resizes-content), so innerHeight and
 * visualViewport.height shrink together there and this offset self-corrects
 * to ~0 — no double compensation.
 *
 * Zero-cost when it doesn't matter: returns 0 when the VisualViewport API is
 * unsupported, and on desktop the value stays 0 so no re-renders occur.
 * Pinch-zoom (scale != 1) is ignored so zooming never collapses the layout.
 */

import { useEffect, useState } from 'react';

export function useVisualViewport(): number {
  const [offset, setOffset] = useState(0);

  useEffect(() => {
    const viewport = window.visualViewport;
    if (!viewport) return;

    const update = () => {
      // Pinch-zoom also shrinks visualViewport.height; that is not a keyboard.
      const scale = viewport.scale ?? 1;
      const next = Math.abs(scale - 1) > 0.01
        ? 0
        : Math.max(0, Math.round(window.innerHeight - viewport.height - viewport.offsetTop));
      setOffset((prev) => (prev === next ? prev : next));
    };

    update();
    viewport.addEventListener('resize', update);
    viewport.addEventListener('scroll', update);
    return () => {
      viewport.removeEventListener('resize', update);
      viewport.removeEventListener('scroll', update);
    };
  }, []);

  return offset;
}

export default useVisualViewport;
