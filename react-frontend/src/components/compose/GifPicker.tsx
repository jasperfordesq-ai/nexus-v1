// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GifPicker — GIF picker using the GIPHY API.
 * Shows trending GIFs on open, with debounced search.
 *
 * Presentation is responsive: a Popover on >= sm widths and a full-height
 * BottomSheet on phone widths (so the on-screen keyboard doesn't cover
 * search results). The search/grid logic is shared between both.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { Popover, PopoverContent, PopoverHeading, PopoverTrigger } from '@/components/ui';
import Film from 'lucide-react/icons/film';
import { useTranslation } from 'react-i18next';

import { featured, searchGifs, type TenorGif } from '@/lib/tenor';
import { Button, SearchField, Skeleton } from '@/components/ui';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { useMediaQuery } from '@/hooks';

interface GifPickerProps {
  onSelect: (gifUrl: string) => void;
}

interface GifPickerContentProps {
  query: string;
  gifs: TenorGif[];
  isLoading: boolean;
  onQueryChange: (value: string) => void;
  onGifClick: (gif: TenorGif) => void;
  /** 'popover' caps the grid height; 'sheet' lets the sheet body scroll. */
  variant: 'popover' | 'sheet';
}

/** Shared search + grid content rendered by both the Popover and the BottomSheet. */
function GifPickerContent({
  query,
  gifs,
  isLoading,
  onQueryChange,
  onGifClick,
  variant,
}: GifPickerContentProps) {
  const { t } = useTranslation('common');

  return (
    <>
      {/* Search input */}
      <SearchField
        size="sm"
        placeholder={t('gif.search_placeholder')}
        value={query}
        onValueChange={onQueryChange}
        className="mb-2"
        aria-label={t('gif.search_placeholder')}
      />

      {/* Trending label when no search */}
      {!query.trim() && (
        <p className="text-xs text-[var(--text-muted)] px-1 py-1 font-medium">
          {t('gif.trending')}
        </p>
      )}

      {/* GIF grid */}
      <div className={variant === 'popover' ? 'max-h-72 overflow-y-auto' : ''}>
        {isLoading ? (
          <div className="grid grid-cols-3 gap-1">
            {Array.from({ length: 9 }).map((_, i) => (
              <Skeleton key={i} className="w-full aspect-square rounded-lg" />
            ))}
          </div>
        ) : gifs.length === 0 ? (
          <p className="text-center text-xs text-[var(--text-muted)] py-8">
            {t('gif.no_results')}
          </p>
        ) : (
          <div className="grid grid-cols-3 gap-1">
            {gifs.map((gif) => (
              <Button
                key={gif.id}
                isIconOnly
                variant="ghost"
                onPress={() => onGifClick(gif)}
                className="relative aspect-square size-full min-h-20 overflow-hidden rounded-lg p-0 transition-opacity hover:opacity-80"
                aria-label={t('gif.select')}
              >
                <img
                  src={gif.preview_url}
                  alt={t('gif.preview_alt')}
                  loading="lazy"
                  className="w-full aspect-square object-cover"
                />
              </Button>
            ))}
          </div>
        )}
      </div>

      {/* Tenor attribution (required by TOS) */}
      <p className="text-[10px] text-[var(--text-muted)] text-center pt-2">
        {t('gif.powered_by')}
      </p>
    </>
  );
}

export function GifPicker({ onSelect }: GifPickerProps) {
  const { t } = useTranslation('common');
  const isMobile = useMediaQuery('(max-width: 639px)');
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [gifs, setGifs] = useState<TenorGif[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [hasFetchedTrending, setHasFetchedTrending] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  // Load trending GIFs when the picker opens
  useEffect(() => {
    if (isOpen && !hasFetchedTrending && !query) {
      setIsLoading(true);
      featured(20)
        .then((results) => {
          setGifs(results);
          setHasFetchedTrending(true);
        })
        .finally(() => setIsLoading(false));
    }
  }, [isOpen, hasFetchedTrending, query]);

  // Debounced search
  const handleQueryChange = useCallback((value: string) => {
    setQuery(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (!value.trim()) {
      // Show trending again
      if (hasFetchedTrending) {
        featured(20).then(setGifs).catch(() => setGifs([]));
      }
      return;
    }

    debounceRef.current = setTimeout(() => {
      setIsLoading(true);
      searchGifs(value.trim(), 20)
        .then(setGifs)
        .catch(() => setGifs([]))
        .finally(() => setIsLoading(false));
    }, 300);
  }, [hasFetchedTrending]);

  // Cleanup debounce on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const handleGifClick = (gif: TenorGif) => {
    onSelect(gif.url);
    triggerRef.current?.focus();
    setIsOpen(false);
    setQuery('');
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      triggerRef.current?.focus();
      setQuery('');
    }
    setIsOpen(open);
  };

  const triggerButton = (
    <Button
      ref={triggerRef}
      isIconOnly
      size="sm"
      variant="tertiary"
      aria-label={t('gif.button_label')}
      className="size-11 min-h-11"
      {...(isMobile ? { onPress: () => setIsOpen(true) } : {})}
    >
      <Film className="w-4 h-4" aria-hidden="true" />
    </Button>
  );

  const contentProps: GifPickerContentProps = {
    query,
    gifs,
    isLoading,
    onQueryChange: handleQueryChange,
    onGifClick: handleGifClick,
    variant: isMobile ? 'sheet' : 'popover',
  };

  if (isMobile) {
    return (
      <>
        {triggerButton}
        <BottomSheet
          isOpen={isOpen}
          onClose={() => handleOpenChange(false)}
          title={t('gif.button_label')}
          snapPoints={['full']}
        >
          <GifPickerContent {...contentProps} />
        </BottomSheet>
      </>
    );
  }

  return (
    <Popover isOpen={isOpen} onOpenChange={handleOpenChange} placement="top">
      <PopoverTrigger>{triggerButton}</PopoverTrigger>
      <PopoverContent className="w-80 p-2 bg-[var(--surface-dropdown)] border border-[var(--border-default)]">
        <PopoverHeading className="sr-only">{t('gif.button_label')}</PopoverHeading>
        <GifPickerContent {...contentProps} />
      </PopoverContent>
    </Popover>
  );
}
