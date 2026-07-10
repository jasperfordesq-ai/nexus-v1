// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef, useState, useCallback, useEffect, useId, type ReactNode } from 'react';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';
import { OverlayActionButton } from '@/components/ui/OverlayActionButton';

interface HorizontalScrollProps {
  children: ReactNode;
  className?: string;
}

/**
 * Horizontal scrollable container with scroll snap.
 * Shows left/right arrow buttons on desktop, touch swipe on mobile.
 * Fades edges to indicate more content.
 */
export function HorizontalScroll({ children, className = '' }: HorizontalScrollProps) {
  const { t } = useTranslation('common');
  const trackId = useId();
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);

  const checkScroll = useCallback(() => {
    const el = scrollRef.current;
    if (!el) return;
    setCanScrollLeft(el.scrollLeft > 4);
    setCanScrollRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
  }, []);

  useEffect(() => {
    const el = scrollRef.current;
    if (!el) return;

    checkScroll();

    // Use ResizeObserver to detect content changes
    const resizeObserver = new ResizeObserver(checkScroll);
    resizeObserver.observe(el);

    el.addEventListener('scroll', checkScroll, { passive: true });
    return () => {
      el.removeEventListener('scroll', checkScroll);
      resizeObserver.disconnect();
    };
  }, [checkScroll]);

  const scroll = (direction: 'left' | 'right') => {
    const el = scrollRef.current;
    if (!el) return;
    const scrollAmount = el.clientWidth * 0.75;
    el.scrollBy({
      left: direction === 'left' ? -scrollAmount : scrollAmount,
      behavior: 'smooth',
    });
  };

  return (
    <div className={`relative group ${className}`}>
      {/* Left fade + button */}
      {canScrollLeft && (
        <>
          <div className="absolute left-0 top-0 bottom-0 w-12 bg-gradient-to-r from-[var(--background)] to-transparent z-10 pointer-events-none" />
          <OverlayActionButton
            variant="secondary"
            className="absolute left-1 top-1/2 -translate-y-1/2 z-20 hidden rounded-full border border-[var(--border-default)] bg-[var(--surface-elevated)] shadow-md transition-opacity sm:flex"
            onPress={() => scroll('left')}
            aria-label={t('aria.scroll_left')}
            aria-controls={trackId}
          >
            <ChevronLeft className="size-4" aria-hidden="true" />
          </OverlayActionButton>
        </>
      )}

      {/* Scrollable area */}
      <div
        id={trackId}
        data-testid="horizontal-scroll-track"
        ref={scrollRef}
        className="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory scrollbar-hide [scrollbar-width:none] [-ms-overflow-style:none] pb-2 -mb-2"
      >
        {children}
      </div>

      {/* Right fade + button */}
      {canScrollRight && (
        <>
          <div className="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-[var(--background)] to-transparent z-10 pointer-events-none" />
          <OverlayActionButton
            variant="secondary"
            className="absolute right-1 top-1/2 -translate-y-1/2 z-20 hidden rounded-full border border-[var(--border-default)] bg-[var(--surface-elevated)] shadow-md transition-opacity sm:flex"
            onPress={() => scroll('right')}
            aria-label={t('aria.scroll_right')}
            aria-controls={trackId}
          >
            <ChevronRight className="size-4" aria-hidden="true" />
          </OverlayActionButton>
        </>
      )}
    </div>
  );
}
