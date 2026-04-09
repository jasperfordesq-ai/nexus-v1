// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceImageGallery - Image gallery/carousel for listing detail
 *
 * Desktop: large primary image with a thumbnail strip below.
 * Mobile: horizontal swipeable carousel using CSS scroll-snap.
 * Includes image count badge and placeholder when no images are available.
 */

import { useState, useRef, useCallback } from 'react';
import { Image } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface GalleryImage {
  id: number;
  url: string;
  thumbnail_url?: string;
  alt_text?: string;
}

interface MarketplaceImageGalleryProps {
  images: GalleryImage[];
}

export function MarketplaceImageGallery({ images }: MarketplaceImageGalleryProps) {
  const { t } = useTranslation('marketplace');
  const [activeIndex, setActiveIndex] = useState(0);
  const scrollRef = useRef<HTMLDivElement>(null);

  const handleThumbnailClick = useCallback((index: number) => {
    setActiveIndex(index);
  }, []);

  const handleScroll = useCallback(() => {
    if (!scrollRef.current) return;
    const container = scrollRef.current;
    const scrollLeft = container.scrollLeft;
    const itemWidth = container.offsetWidth;
    const newIndex = Math.round(scrollLeft / itemWidth);
    if (newIndex >= 0 && newIndex < images.length) {
      setActiveIndex(newIndex);
    }
  }, [images.length]);

  // Placeholder when no images
  if (images.length === 0) {
    return (
      <div className="flex items-center justify-center aspect-video bg-theme-elevated rounded-lg">
        <div className="text-center">
          <Image
            className="w-12 h-12 text-theme-subtle mx-auto mb-2"
            aria-hidden="true"
            strokeWidth={1.5}
          />
          <p className="text-sm text-theme-muted">
            {t('gallery.no_images', 'No images available')}
          </p>
        </div>
      </div>
    );
  }

  const activeImage = images[activeIndex] ?? images[0];

  if (!activeImage) {
    return null;
  }

  return (
    <div className="space-y-3">
      {/* Desktop: single primary image */}
      <div className="hidden sm:block relative">
        <img
          src={activeImage.url}
          alt={activeImage.alt_text || t('gallery.image_alt', 'Listing image')}
          className="w-full aspect-video object-cover rounded-lg"
        />
        {/* Image count badge */}
        {images.length > 1 && (
          <div className="absolute bottom-3 right-3 px-2.5 py-1 bg-black/60 text-white text-xs font-medium rounded-full">
            {activeIndex + 1}/{images.length}
          </div>
        )}
      </div>

      {/* Mobile: swipeable carousel */}
      <div
        ref={scrollRef}
        onScroll={handleScroll}
        className="sm:hidden flex overflow-x-auto snap-x snap-mandatory scrollbar-hide -mx-1"
      >
        {images.map((image, index) => (
          <div
            key={image.id}
            className="flex-none w-full snap-center px-1"
          >
            <div className="relative">
              <img
                src={image.url}
                alt={image.alt_text || t('gallery.image_alt', 'Listing image')}
                className="w-full aspect-video object-cover rounded-lg"
              />
              {images.length > 1 && (
                <div className="absolute bottom-3 right-3 px-2.5 py-1 bg-black/60 text-white text-xs font-medium rounded-full">
                  {index + 1}/{images.length}
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Thumbnail strip (desktop only, when multiple images) */}
      {images.length > 1 && (
        <div className="hidden sm:flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
          {images.map((image, index) => (
            <button
              key={image.id}
              onClick={() => handleThumbnailClick(index)}
              className={`shrink-0 w-16 h-16 rounded-md overflow-hidden border-2 transition-colors ${
                index === activeIndex
                  ? 'border-primary'
                  : 'border-transparent hover:border-default-300'
              }`}
              aria-label={t('gallery.select_image', 'View image {{number}}', { number: index + 1 })}
              aria-current={index === activeIndex ? 'true' : undefined}
            >
              <img
                src={image.thumbnail_url || image.url}
                alt={t('gallery.thumbnail_alt', 'Image {{number}}', { number: index + 1 })}
                className="w-full h-full object-cover"
                loading="lazy"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

export default MarketplaceImageGallery;
