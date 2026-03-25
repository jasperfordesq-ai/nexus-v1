// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ImageLightbox — Fullscreen image viewer overlay.
 *
 * - Left/right navigation arrows
 * - Keyboard navigation (ArrowLeft/ArrowRight, Escape to close)
 * - Swipe gestures on mobile via Framer Motion
 * - Dark backdrop with blur
 * - Image counter "3 of 7"
 * - Focus trap
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion, AnimatePresence, type PanInfo } from 'framer-motion';
import { X, ChevronLeft, ChevronRight, Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { createPortal } from 'react-dom';
import { resolveAssetUrl } from '@/lib/helpers';
import type { PostMedia } from './types';

interface ImageLightboxProps {
  media: PostMedia[];
  initialIndex?: number;
  onClose: () => void;
}

const SWIPE_THRESHOLD = 80;

export function ImageLightbox({ media, initialIndex = 0, onClose }: ImageLightboxProps) {
  const { t } = useTranslation('feed');
  const [currentIndex, setCurrentIndex] = useState(initialIndex);
  const [direction, setDirection] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  const total = media.length;
  const current = media[currentIndex];

  const goNext = useCallback(() => {
    if (currentIndex < total - 1) {
      setDirection(1);
      setCurrentIndex((i) => i + 1);
    }
  }, [currentIndex, total]);

  const goPrev = useCallback(() => {
    if (currentIndex > 0) {
      setDirection(-1);
      setCurrentIndex((i) => i - 1);
    }
  }, [currentIndex]);

  const handleDragEnd = useCallback(
    (_: MouseEvent | TouchEvent | PointerEvent, info: PanInfo) => {
      if (info.offset.x < -SWIPE_THRESHOLD) {
        goNext();
      } else if (info.offset.x > SWIPE_THRESHOLD) {
        goPrev();
      }
    },
    [goNext, goPrev],
  );

  // Keyboard navigation
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      switch (e.key) {
        case 'Escape':
          onClose();
          break;
        case 'ArrowRight':
          e.preventDefault();
          goNext();
          break;
        case 'ArrowLeft':
          e.preventDefault();
          goPrev();
          break;
      }
    };

    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose, goNext, goPrev]);

  // Lock body scroll when lightbox is open
  useEffect(() => {
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prevOverflow;
    };
  }, []);

  // Focus trap — focus the container on mount, restore on close
  const previousFocusRef = useRef<Element | null>(null);
  useEffect(() => {
    previousFocusRef.current = document.activeElement;
    containerRef.current?.focus();
    return () => {
      (previousFocusRef.current as HTMLElement)?.focus?.();
    };
  }, []);

  if (!current) return null;

  const slideVariants = {
    enter: (dir: number) => ({
      x: dir > 0 ? '50%' : '-50%',
      opacity: 0,
      scale: 0.95,
    }),
    center: {
      x: 0,
      opacity: 1,
      scale: 1,
    },
    exit: (dir: number) => ({
      x: dir > 0 ? '-50%' : '50%',
      opacity: 0,
      scale: 0.95,
    }),
  };

  const lightboxContent = (
    <motion.div
      ref={containerRef}
      className="fixed inset-0 z-[1000] bg-black/90 backdrop-blur-lg flex items-center justify-center"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ duration: 0.2 }}
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label={t('lightbox.aria_label', 'Image viewer')}
      tabIndex={-1}
    >
      {/* Close button — large, high contrast, always visible */}
      <button
        type="button"
        className="absolute top-4 right-4 z-10 bg-white/90 text-black rounded-full p-2.5 hover:bg-white transition-colors shadow-lg focus:outline-none focus:ring-2 focus:ring-white/50"
        onClick={(e) => {
          e.stopPropagation();
          onClose();
        }}
        aria-label={t('lightbox.close', 'Close image viewer')}
      >
        <X className="w-6 h-6 stroke-[2.5]" />
      </button>

      {/* Download button — top-left */}
      <a
        href={resolveAssetUrl(current.file_url)}
        download
        className="absolute top-4 left-4 z-10 bg-black/40 backdrop-blur-sm text-white rounded-full p-2.5 hover:bg-black/60 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
        onClick={(e) => e.stopPropagation()}
        aria-label={t('lightbox.download', 'Download image')}
      >
        <Download className="w-5 h-5" />
      </a>

      {/* Counter (screen reader live region) */}
      {total > 1 && (
        <div className="absolute top-4 left-1/2 -translate-x-1/2 z-10 text-white/80 text-sm font-medium" aria-live="polite" aria-atomic="true">
          {t('lightbox.counter', '{{current}} of {{total}}', { current: currentIndex + 1, total })}
        </div>
      )}

      {/* Image container */}
      <div
        className="relative w-full h-full flex items-center justify-center px-16 py-16"
        onClick={(e) => e.stopPropagation()}
      >
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
              opacity: { duration: 0.15 },
              scale: { duration: 0.15 },
            }}
            drag="x"
            dragConstraints={{ left: 0, right: 0 }}
            dragElastic={0.3}
            onDragEnd={handleDragEnd}
            className="flex items-center justify-center max-w-full max-h-full"
          >
            {current.media_type === 'video' ? (
              <video
                src={resolveAssetUrl(current.file_url)}
                poster={current.thumbnail_url ? resolveAssetUrl(current.thumbnail_url) : undefined}
                controls
                autoPlay
                playsInline
                className="max-w-full max-h-full object-contain select-none rounded-lg"
                aria-label={current.alt_text || t('carousel.video_of', 'Video {{current}} of {{total}}', { current: currentIndex + 1, total })}
                onClick={(e) => e.stopPropagation()}
              />
            ) : (
              <img
                src={resolveAssetUrl(current.file_url)}
                alt={current.alt_text || t('carousel.image_of', 'Image {{current}} of {{total}}', { current: currentIndex + 1, total })}
                className="max-w-full max-h-full object-contain select-none rounded-lg"
                draggable={false}
              />
            )}
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Alt text */}
      {current.alt_text && (
        <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 max-w-lg text-center">
          <p className="text-white/70 text-sm bg-black/40 backdrop-blur-sm px-4 py-2 rounded-lg">
            {current.alt_text}
          </p>
        </div>
      )}

      {/* Left arrow */}
      {currentIndex > 0 && (
        <button
          type="button"
          className="absolute left-4 top-1/2 -translate-y-1/2 z-10 bg-white/10 backdrop-blur-sm text-white rounded-full p-3 hover:bg-white/20 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
          onClick={(e) => {
            e.stopPropagation();
            goPrev();
          }}
          aria-label={t('carousel.previous', 'Previous image')}
        >
          <ChevronLeft className="w-6 h-6" />
        </button>
      )}

      {/* Right arrow */}
      {currentIndex < total - 1 && (
        <button
          type="button"
          className="absolute right-4 top-1/2 -translate-y-1/2 z-10 bg-white/10 backdrop-blur-sm text-white rounded-full p-3 hover:bg-white/20 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
          onClick={(e) => {
            e.stopPropagation();
            goNext();
          }}
          aria-label={t('carousel.next', 'Next image')}
        >
          <ChevronRight className="w-6 h-6" />
        </button>
      )}

      {/* Dot indicators — collapses to max 7 visible dots for 8+ images */}
      {total > 1 && (
        <div className="absolute bottom-12 left-1/2 -translate-x-1/2 z-10 flex items-center gap-2">
          {media.map((_, idx) => {
            // For 8+ images, only show dots near the current index (Instagram-style)
            if (total > 7) {
              const distance = Math.abs(idx - currentIndex);
              if (distance > 3) return null;
              const scale = distance <= 1 ? '' : distance === 2 ? 'scale-75' : 'scale-50 opacity-50';
              return (
                <button
                  key={idx}
                  type="button"
                  className={`w-2.5 h-2.5 rounded-full transition-all ${scale} ${
                    idx === currentIndex
                      ? 'bg-white scale-110'
                      : 'bg-white/40 hover:bg-white/60'
                  }`}
                  onClick={(e) => {
                    e.stopPropagation();
                    setDirection(idx > currentIndex ? 1 : -1);
                    setCurrentIndex(idx);
                  }}
                  aria-label={t('carousel.go_to_image', 'Go to image {{number}}', { number: idx + 1 })}
                  aria-current={idx === currentIndex ? 'true' : undefined}
                />
              );
            }
            return (
              <button
                key={idx}
                type="button"
                className={`w-2.5 h-2.5 rounded-full transition-all ${
                  idx === currentIndex
                    ? 'bg-white scale-110'
                    : 'bg-white/40 hover:bg-white/60'
                }`}
                onClick={(e) => {
                  e.stopPropagation();
                  setDirection(idx > currentIndex ? 1 : -1);
                  setCurrentIndex(idx);
                }}
                aria-label={t('carousel.go_to_image', 'Go to image {{number}}', { number: idx + 1 })}
                aria-current={idx === currentIndex ? 'true' : undefined}
              />
            );
          })}
        </div>
      )}
    </motion.div>
  );

  return createPortal(
    <AnimatePresence>{lightboxContent}</AnimatePresence>,
    document.body,
  );
}
