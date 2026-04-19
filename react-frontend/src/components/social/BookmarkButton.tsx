// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BookmarkButton — Toggle save/bookmark on content items.
 * Filled icon when bookmarked, outline when not.
 * Supports optimistic updates.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Button } from '@heroui/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useLongPress } from '@/hooks/useLongPress';
import { BookmarkCollectionPicker } from './BookmarkCollectionPicker';

export interface BookmarkButtonProps {
  type: string;
  id: number;
  isBookmarked?: boolean;
  onToggle?: (bookmarked: boolean) => void;
  size?: 'sm' | 'md';
  className?: string;
}

export function BookmarkButton({
  type,
  id,
  isBookmarked: initialBookmarked = false,
  onToggle,
  size = 'sm',
  className = '',
}: BookmarkButtonProps) {
  const { t } = useTranslation('social');
  const [bookmarked, setBookmarked] = useState(initialBookmarked);
  const [isLoading, setIsLoading] = useState(false);
  const [isPickerOpen, setIsPickerOpen] = useState(false);
  const [collectionId, setCollectionId] = useState<number | null>(null);

  // Sync state when parent provides updated prop (e.g. feed reload)
  useEffect(() => {
    setBookmarked(initialBookmarked);
  }, [initialBookmarked]);

  const toggleBookmark = useCallback(async (targetCollectionId?: number | null) => {
    if (isLoading) return;

    const prev = bookmarked;
    setBookmarked(!prev);
    setIsLoading(true);

    try {
      const body: Record<string, unknown> = { type, id };
      if (targetCollectionId !== undefined) {
        body.collection_id = targetCollectionId;
      }
      const res = await api.post<{ bookmarked: boolean; count: number }>('/v2/bookmarks', body);

      if (res.success && res.data) {
        setBookmarked(res.data.bookmarked);
        onToggle?.(res.data.bookmarked);
      } else {
        setBookmarked(prev);
      }
    } catch (err) {
      logError('Failed to toggle bookmark', err);
      setBookmarked(prev);
    } finally {
      setIsLoading(false);
    }
  }, [type, id, bookmarked, isLoading, onToggle]);

  // Long press: open collection picker (suppress short-press when long-press fires)
  const longPressFiredRef = useRef(false);
  const longPressHandlers = useLongPress({
    onLongPress: () => { longPressFiredRef.current = true; setIsPickerOpen(true); },
    delay: 500,
  });

  // Short press: simple toggle (skip if long-press just fired)
  const handlePress = useCallback(() => {
    if (longPressFiredRef.current) {
      // Don't reset here — let onOpenChange consume the flag
      return;
    }
    toggleBookmark();
  }, [toggleBookmark]);

  // Collection picker selection: bookmark into that collection
  const handleCollectionSelect = useCallback((colId: number | null) => {
    setCollectionId(colId);
    if (!bookmarked) {
      toggleBookmark(colId);
    } else {
      // Already bookmarked — move to collection via move endpoint
      api.post(`/v2/bookmarks/${id}/move`, { collection_id: colId }).catch((err) => {
        logError('Failed to move bookmark', err);
      });
    }
  }, [bookmarked, id, toggleBookmark]);

  const iconSize = size === 'sm' ? 'w-[18px] h-[18px]' : 'w-5 h-5';

  // Close the collection picker when clicking outside it
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  useEffect(() => {
    if (!isPickerOpen) return;
    const onDocClick = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setIsPickerOpen(false);
      }
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [isPickerOpen]);

  /*
    Previously the Button was wrapped in HeroUI's `PopoverTrigger`, which intercepted
    the press event and silently swallowed taps on the main bookmark toggle. The
    picker is now rendered as a plain absolutely-positioned panel so the Button's
    onPress fires cleanly, and the picker only appears on long-press.
  */
  return (
    <div ref={wrapperRef} className="relative inline-flex">
      <Button
        isIconOnly
        size={size}
        variant="light"
        className={`${
          bookmarked
            ? 'text-amber-500'
            : 'text-[var(--text-muted)] hover:text-amber-500'
        } transition-colors min-w-0 ${className}`}
        onPress={handlePress}
        isDisabled={isLoading}
        aria-label={bookmarked ? t('bookmark.remove', 'Remove from saved') : t('bookmark.save', 'Save')}
        onTouchStart={longPressHandlers.onTouchStart}
        onTouchMove={longPressHandlers.onTouchMove}
        onTouchEnd={longPressHandlers.onTouchEnd}
      >
        {bookmarked ? (
          <BookmarkCheck className={`${iconSize} fill-amber-500 text-amber-500`} aria-hidden="true" />
        ) : (
          <Bookmark className={iconSize} aria-hidden="true" />
        )}
      </Button>

      <AnimatePresence>
        {isPickerOpen && (
          <motion.div
            initial={{ opacity: 0, scale: 0.9, y: -4 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.9, y: -4 }}
            transition={{ type: 'spring', stiffness: 400, damping: 25 }}
            className="absolute top-full right-0 mt-2 z-50 rounded-xl bg-[var(--surface-dropdown)]/95 backdrop-blur-xl border border-[var(--border-default)] shadow-xl shadow-black/20 p-1"
          >
            <BookmarkCollectionPicker
              selectedId={collectionId}
              onSelect={handleCollectionSelect}
              onClose={() => setIsPickerOpen(false)}
            />
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
