// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef, useState, useCallback, useEffect, type ReactNode } from 'react';
import { Button } from '@heroui/react';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';

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
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            className="absolute left-1 top-1/2 -translate-y-1/2 z-20 hidden sm:flex bg-[var(--surface-elevated)] border border-[var(--border-default)] shadow-md opacity-0 group-hover:opacity-100 transition-opacity min-w-8 w-8 h-8"
            onPress={() => scroll('left')}
            aria-label={t('aria.scroll_left')}
          >
            <ChevronLeft className="w-4 h-4" />
          </Button>
        </>
      )}

      {/* Scrollable area */}
      <div
        ref={scrollRef}
        className="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory scrollbar-hide pb-2 -mb-2"
        style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
      >
        {children}
      </div>

      {/* Right fade + button */}
      {canScrollRight && (
        <>
          <div className="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-[var(--background)] to-transparent z-10 pointer-events-none" />
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            className="absolute right-1 top-1/2 -translate-y-1/2 z-20 hidden sm:flex bg-[var(--surface-elevated)] border border-[var(--border-default)] shadow-md opacity-0 group-hover:opacity-100 transition-opacity min-w-8 w-8 h-8"
            onPress={() => scroll('right')}
            aria-label={t('aria.scroll_right')}
          >
            <ChevronRight className="w-4 h-4" />
          </Button>
        </>
      )}
    </div>
  );
}
