// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ImageCarousel — Swipeable image carousel for viewing post images.
 *
 * - Desktop: Left/right arrow buttons on hover, click to navigate.
 * - Mobile: Touch swipe with Framer Motion drag gestures, snap-to-slide.
 * - Dot indicators at bottom center.
 * - Counter "1/5" in top-right corner.
 * - Click on image opens fullscreen lightbox.
 */

import { useState, useCallback, useRef } from 'react';
import { motion, AnimatePresence, type PanInfo } from 'framer-motion';
import { Button } from '@heroui/react';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useTranslation } from 'react-i18next';
import { resolveAssetUrl } from '@/lib/helpers';
import type { PostMedia } from './types';
import { ImageLightbox } from './ImageLightbox';

interface ImageCarouselProps {
  media: PostMedia[];
  className?: string;
}

const SWIPE_THRESHOLD = 50;

export function ImageCarousel({ media, className = '' }: ImageCarouselProps) {
  const { t } = useTranslation('feed');
  const [currentIndex, setCurrentIndex] = useState(0);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [direction, setDirection] = useState(0);
  const carouselRef = useRef<HTMLDivElement>(null);

  const total = media.length;

  const goTo = useCallback(
    (index: number, dir: number) => {
      if (index < 0 || index >= total) return;
      setDirection(dir);
      setCurrentIndex(index);
    },
    [total],
  );

  const goNext = useCallback(() => {
    if (currentIndex < total - 1) goTo(currentIndex + 1, 1);
  }, [currentIndex, total, goTo]);

  const goPrev = useCallback(() => {
    if (currentIndex > 0) goTo(currentIndex - 1, -1);
  }, [currentIndex, goTo]);

  const handleDragEnd = useCallback(
    (_: MouseEvent | TouchEvent | PointerEvent, info: PanInfo) => {
      if (info.offset.x < -SWIPE_THRESHOLD && currentIndex < total - 1) {
        goNext();
      } else if (info.offset.x > SWIPE_THRESHOLD && currentIndex > 0) {
        goPrev();
      }
    },
    [currentIndex, total, goNext, goPrev],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'ArrowRight') {
        e.preventDefault();
        goNext();
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        goPrev();
      }
    },
    [goNext, goPrev],
  );

  const current = media[currentIndex];
  if (!current) return null;

  const slideVariants = {
    enter: (dir: number) => ({
      x: dir > 0 ? '100%' : '-100%',
      opacity: 0.5,
    }),
    center: {
      x: 0,
      opacity: 1,
    },
    exit: (dir: number) => ({
      x: dir > 0 ? '-100%' : '100%',
      opacity: 0.5,
    }),
  };

  return (
    <>
      <div
        ref={carouselRef}
        className={`relative overflow-hidden rounded-xl group ${className}`}
        role="region"
        aria-label={t('carousel.aria_label', { current: currentIndex + 1, total })}
        aria-roledescription="carousel"
        tabIndex={0}
        onKeyDown={handleKeyDown}
      >
        {/* Slides container */}
        <div className="relative w-full max-h-[500px] sm:max-h-[500px] max-sm:max-h-[400px] overflow-hidden bg-black/5 dark:bg-white/5">
          <AnimatePresence initial={false} custom={direction} mode="popLayout">
            <motion.div
              key={currentIndex}
              custom={direction}
              variants={slideVariants}
              initial="enter"
              animate="center"
              exit="exit"
              transition={{
                x: { type: 'spring', stiffness: 300, damping: 30 },
                opacity: { duration: 0.2 },
              }}
              drag="x"
              dragConstraints={{ left: 0, right: 0 }}
              dragElastic={0.3}
              onDragEnd={handleDragEnd}
              className="w-full cursor-pointer"
              onClick={current.media_type === 'video' ? undefined : () => setLightboxOpen(true)}
            >
              {current.media_type === 'video' ? (
                <video
                  src={resolveAssetUrl(current.file_url)}
                  poster={current.thumbnail_url ? resolveAssetUrl(current.thumbnail_url) : undefined}
                  controls
                  playsInline
                  preload="metadata"
                  className="w-full max-h-[500px] sm:max-h-[500px] max-sm:max-h-[400px] object-contain select-none"
                  aria-label={current.alt_text || t('carousel.video_of', { current: currentIndex + 1, total })}
                  onClick={(e) => e.stopPropagation()}
                />
              ) : (
                <img
                  src={resolveAssetUrl(current.file_url)}
                  alt={current.alt_text || t('carousel.image_of', { current: currentIndex + 1, total })}
                  className="w-full max-h-[500px] sm:max-h-[500px] max-sm:max-h-[400px] object-contain select-none"
                  draggable={false}
                  loading={currentIndex === 0 ? 'eager' : 'lazy'}
                  onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
              )}
            </motion.div>
          </AnimatePresence>
        </div>

        {/* Counter badge — top right */}
        {total > 1 && (
          <div
            className="absolute top-3 right-3 bg-black/50 backdrop-blur-sm text-white text-xs font-medium px-2.5 py-1 rounded-full pointer-events-none"
            aria-live="polite"
          >
            {currentIndex + 1}/{total}
          </div>
        )}

        {/* Left arrow */}
        {currentIndex > 0 && (
          <Button
            isIconOnly
            radius="full"
            size="sm"
            variant="flat"
            className="absolute left-2 top-1/2 -translate-y-1/2 bg-[var(--surface-overlay)] backdrop-blur-sm text-white min-w-[44px] min-h-[44px] opacity-100 lg:opacity-0 lg:group-hover:opacity-100 focus:opacity-100 transition-opacity"
            onPress={goPrev}
            onClick={(e) => e.stopPropagation()}
            aria-label={t('carousel.previous')}
          >
            <ChevronLeft className="w-5 h-5" />
          </Button>
        )}

        {/* Right arrow */}
        {currentIndex < total - 1 && (
          <Button
            isIconOnly
            radius="full"
            size="sm"
            variant="flat"
            className="absolute right-2 top-1/2 -translate-y-1/2 bg-[var(--surface-overlay)] backdrop-blur-sm text-white min-w-[44px] min-h-[44px] opacity-100 lg:opacity-0 lg:group-hover:opacity-100 focus:opacity-100 transition-opacity"
            onPress={goNext}
            onClick={(e) => e.stopPropagation()}
            aria-label={t('carousel.next')}
          >
            <ChevronRight className="w-5 h-5" />
          </Button>
        )}

        {/* Dot indicators — 44×44 tap target with centered 8×8 visible dot (WCAG 2.5.5) */}
        {total > 1 && (
          <div className="absolute bottom-0 left-1/2 -translate-x-1/2 flex items-center">
            {media.map((_, idx) => {
              // For 8+ images, only show dots near the current index (Instagram-style)
              const isCollapsed = total > 7;
              const distance = Math.abs(idx - currentIndex);
              if (isCollapsed && distance > 3) return null;
              const scale = !isCollapsed
                ? ''
                : distance <= 1
                  ? ''
                  : distance === 2
                    ? 'scale-75'
                    : 'scale-50 opacity-50';
              return (
                <button
                  key={idx}
                  type="button"
                  className="w-11 h-11 min-w-[44px] min-h-[44px] flex items-center justify-center bg-transparent p-0 border-0 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-full"
                  onClick={(e) => {
                    e.stopPropagation();
                    goTo(idx, idx > currentIndex ? 1 : -1);
                    setTimeout(() => carouselRef.current?.focus(), 50);
                  }}
                  aria-label={t('carousel.go_to_image', { number: idx + 1 })}
                  aria-current={idx === currentIndex ? 'true' : undefined}
                >
                  <span
                    className={`w-2 h-2 rounded-full transition-all ${scale} ${
                      idx === currentIndex
                        ? 'bg-white scale-110'
                        : 'bg-white/60 hover:bg-white/80'
                    }`}
                    aria-hidden="true"
                  />
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Lightbox */}
      {lightboxOpen && (
        <ImageLightbox
          media={media}
          initialIndex={currentIndex}
          onClose={() => setLightboxOpen(false)}
        />
      )}
    </>
  );
}
