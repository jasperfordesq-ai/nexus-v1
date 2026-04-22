// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GifPicker — popover-based GIF picker using the GIPHY API.
 * Shows trending GIFs on open, with debounced search.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button, Input, Popover, PopoverContent, PopoverTrigger, Skeleton } from '@heroui/react';
import Film from 'lucide-react/icons/film';
import Search from 'lucide-react/icons/search';
import { useTranslation } from 'react-i18next';

import { featured, searchGifs, type TenorGif } from '@/lib/tenor';

interface GifPickerProps {
  onSelect: (gifUrl: string) => void;
}

export function GifPicker({ onSelect }: GifPickerProps) {
  const { t } = useTranslation('common');
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [gifs, setGifs] = useState<TenorGif[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [hasFetchedTrending, setHasFetchedTrending] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();

  // Load trending GIFs when popover opens
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

  return (
    <Popover isOpen={isOpen} onOpenChange={handleOpenChange} placement="top">
      <PopoverTrigger>
        <Button
          ref={triggerRef}
          isIconOnly
          size="sm"
          variant="light"
          aria-label={t('gif.button_label')}
          className="min-w-11 w-11 h-11"
        >
          <Film className="w-4 h-4" aria-hidden="true" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-2">
        {/* Search input */}
        <Input
          size="sm"
          placeholder={t('gif.search_placeholder')}
          value={query}
          onValueChange={handleQueryChange}
          startContent={<Search className="w-3.5 h-3.5 text-[var(--text-muted)]" aria-hidden="true" />}
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
        <div className="max-h-72 overflow-y-auto">
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
                  variant="flat"
                  onPress={() => handleGifClick(gif)}
                  className="relative overflow-hidden rounded-lg cursor-pointer hover:opacity-80 transition-opacity p-0 min-w-0 h-auto"
                  aria-label={t('gif.select', 'Select GIF')}
                >
                  <img
                    src={gif.preview_url}
                    alt={t('gif.preview_alt', 'GIF preview')}
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
      </PopoverContent>
    </Popover>
  );
}
