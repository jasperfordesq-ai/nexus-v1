// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MediaGrid — Facebook/Instagram-style grid layout for 2-4 images.
 *
 * - 1 image: Full-width single image (handled by FeedCard directly)
 * - 2 images: side-by-side (50/50)
 * - 3 images: one large left (60%) + two stacked right (40%)
 * - 4 images: 2x2 grid
 * - 5+ images: first 4 in grid with "+N more" overlay on 4th
 *
 * Click any image to open the full carousel lightbox starting from that image.
 */

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { resolveAssetUrl } from '@/lib/helpers';
import type { PostMedia } from './types';
import { ImageLightbox } from './ImageLightbox';

interface MediaGridProps {
  media: PostMedia[];
  className?: string;
}

export function MediaGrid({ media, className = '' }: MediaGridProps) {
  const { t } = useTranslation('feed');
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  const total = media.length;
  const displayMedia = media.slice(0, 4);
  const extraCount = total - 4;

  const openLightbox = (index: number) => {
    setLightboxIndex(index);
  };

  const renderImage = (item: PostMedia, index: number, extraOverlay = false) => (
    <button
      key={item.id}
      type="button"
      className="relative w-full h-full overflow-hidden focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-inset"
      onClick={() => openLightbox(index)}
      aria-label={item.alt_text || t('carousel.view_image', 'View image {{current}} of {{total}}', { current: index + 1, total })}
    >
      <img
        src={resolveAssetUrl(item.file_url)}
        alt={item.alt_text || t('carousel.image_of', 'Image {{current}} of {{total}}', { current: index + 1, total })}
        className="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
        loading={index === 0 ? 'eager' : 'lazy'}
        draggable={false}
      />
      {extraOverlay && extraCount > 0 && (
        <div className="absolute inset-0 bg-black/50 flex items-center justify-center" aria-label={t('carousel.more_images', '{{count}} more images', { count: extraCount })}>
          <span className="text-white text-2xl font-bold" aria-hidden="true">+{extraCount}</span>
        </div>
      )}
    </button>
  );

  const gridContent = () => {
    if (total === 2) {
      // Side-by-side 50/50
      return (
        <div className={`grid grid-cols-2 gap-1 rounded-xl overflow-hidden ${className}`}>
          <div className="aspect-square">{renderImage(displayMedia[0], 0)}</div>
          <div className="aspect-square">{renderImage(displayMedia[1], 1)}</div>
        </div>
      );
    }

    if (total === 3) {
      // One large left (60%) + two stacked right (40%)
      return (
        <div className={`grid grid-cols-5 gap-1 rounded-xl overflow-hidden ${className}`} style={{ height: '24rem' }}>
          <div className="col-span-3 h-full">{renderImage(displayMedia[0], 0)}</div>
          <div className="col-span-2 grid grid-rows-2 gap-1 h-full">
            <div>{renderImage(displayMedia[1], 1)}</div>
            <div>{renderImage(displayMedia[2], 2)}</div>
          </div>
        </div>
      );
    }

    // 4+ images: 2x2 grid, with +N overlay on 4th if 5+
    return (
      <div className={`grid grid-cols-2 gap-1 rounded-xl overflow-hidden ${className}`}>
        <div className="aspect-square">{renderImage(displayMedia[0], 0)}</div>
        <div className="aspect-square">{renderImage(displayMedia[1], 1)}</div>
        <div className="aspect-square">{renderImage(displayMedia[2], 2)}</div>
        <div className="aspect-square">
          {renderImage(displayMedia[3], 3, total > 4)}
        </div>
      </div>
    );
  };

  return (
    <>
      {gridContent()}

      {/* Lightbox — opens the full carousel starting from the clicked image */}
      {lightboxIndex !== null && (
        <ImageLightbox
          media={media}
          initialIndex={lightboxIndex}
          onClose={() => setLightboxIndex(null)}
        />
      )}
    </>
  );
}
