// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef } from 'react';

export interface HeaderScrollState {
  /** Whether the page has scrolled past the threshold */
  isScrolled: boolean;
  /** Whether the utility bar should be visible (hidden on scroll-down, shown on scroll-up) */
  isUtilityBarVisible: boolean;
  /** Current scroll direction */
  scrollDirection: 'up' | 'down' | null;
}

/**
 * Tracks scroll position and direction for smart header behavior.
 * - `isScrolled`: true once scrolled past `threshold` px
 * - `isUtilityBarVisible`: false when scrolling down past threshold, true when scrolling up
 * - Uses requestAnimationFrame for performance
 */
export function useHeaderScroll(threshold = 48): HeaderScrollState {
  const [state, setState] = useState<HeaderScrollState>({
    isScrolled: false,
    isUtilityBarVisible: true,
    scrollDirection: null,
  });

  const lastScrollY = useRef(0);
  const ticking = useRef(false);

  useEffect(() => {
    const handleScroll = () => {
      if (ticking.current) return;
      ticking.current = true;

      requestAnimationFrame(() => {
        const currentY = window.scrollY;
        const delta = currentY - lastScrollY.current;

        // Require at least 5px of movement to avoid micro-jitter
        if (Math.abs(delta) < 5) {
          ticking.current = false;
          return;
        }

        const direction = delta > 0 ? 'down' : 'up';
        const isScrolled = currentY > threshold;

        setState({
          isScrolled,
          // Show utility bar when at top or scrolling up; hide when scrolling down past threshold
          isUtilityBarVisible: !isScrolled || direction === 'up',
          scrollDirection: direction,
        });

        lastScrollY.current = currentY;
        ticking.current = false;
      });
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [threshold]);

  return state;
}
