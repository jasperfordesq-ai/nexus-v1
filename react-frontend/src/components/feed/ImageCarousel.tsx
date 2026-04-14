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

import { useState, useCallback } from 'react';
import { motion, AnimatePresence, type PanInfo } from 'framer-motion';
import { Button } from '@heroui/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
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
        className={`relative overflow-hidden rounded-xl group ${className}`}
        role="region"
        aria-label={t('carousel.aria_label', 'Image carousel, {{current}} of {{total}}', { current: currentIndex + 1, total })}
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
                  aria-label={current.alt_text || t('carousel.video_of', 'Video {{current}} of {{total}}', { current: currentIndex + 1, total })}
                  onClick={(e) => e.stopPropagation()}
                />
              ) : (
                <img
                  src={resolveAssetUrl(current.file_url)}
                  alt={current.alt_text || t('carousel.image_of', 'Image {{current}} of {{total}}', { current: currentIndex + 1, total })}
                  className="w-full max-h-[500px] sm:max-h-[500px] max-sm:max-h-[400px] object-contain select-none"
                  draggable={false}
                  loading={currentIndex === 0 ? 'eager' : 'lazy'}
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
            className="absolute left-2 top-1/2 -translate-y-1/2 bg-[var(--surface-overlay)] backdrop-blur-sm text-white opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
            onPress={goPrev}
            onClick={(e) => e.stopPropagation()}
            aria-label={t('carousel.previous', 'Previous image')}
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
            className="absolute right-2 top-1/2 -translate-y-1/2 bg-[var(--surface-overlay)] backdrop-blur-sm text-white opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
            onPress={goNext}
            onClick={(e) => e.stopPropagation()}
            aria-label={t('carousel.next', 'Next image')}
          >
            <ChevronRight className="w-5 h-5" />
          </Button>
        )}

        {/* Dot indicators — collapses to max 7 visible dots for 8+ images */}
        {total > 1 && (
          <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-1.5">
            {media.map((_, idx) => {
              // For 8+ images, only show dots near the current index (Instagram-style)
              if (total > 7) {
                const distance = Math.abs(idx - currentIndex);
                if (distance > 3) return null;
                const scale = distance <= 1 ? '' : distance === 2 ? 'scale-75' : 'scale-50 opacity-50';
                return (
                  <Button
                    key={idx}
                    isIconOnly
                    variant="light"
                    size="sm"
                    className={`w-2 h-2 min-w-0 min-h-0 rounded-full p-0 transition-all ${scale} ${
                      idx === currentIndex
                        ? 'bg-white scale-110'
                        : 'bg-white/60 hover:bg-white/80'
                    }`}
                    onPress={() => goTo(idx, idx > currentIndex ? 1 : -1)}
                    onClick={(e) => e.stopPropagation()}
                    aria-label={t('carousel.go_to_image', 'Go to image {{number}}', { number: idx + 1 })}
                    aria-current={idx === currentIndex ? 'true' : undefined}
                  />
                );
              }
              return (
                <Button
                  key={idx}
                  isIconOnly
                  variant="light"
                  size="sm"
                  className={`w-2 h-2 min-w-0 min-h-0 rounded-full p-0 transition-all ${
                    idx === currentIndex
                      ? 'bg-white scale-110'
                      : 'bg-white/60 hover:bg-white/80'
                  }`}
                  onPress={() => goTo(idx, idx > currentIndex ? 1 : -1)}
                  onClick={(e) => e.stopPropagation()}
                  aria-label={t('carousel.go_to_image', 'Go to image {{number}}', { number: idx + 1 })}
                  aria-current={idx === currentIndex ? 'true' : undefined}
                />
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
