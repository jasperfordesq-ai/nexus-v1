// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BookmarkButton — Toggle save/bookmark on content items.
 * Filled icon when bookmarked, outline when not.
 * Supports optimistic updates.
 */

import { useState, useCallback } from 'react';
import { Button, Tooltip } from '@heroui/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

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

  const handleToggle = useCallback(async () => {
    if (isLoading) return;

    // Optimistic update
    const prev = bookmarked;
    setBookmarked(!prev);
    setIsLoading(true);

    try {
      const res = await api.post<{ bookmarked: boolean; count: number }>('/v2/bookmarks', {
        type,
        id,
      });

      if (res.success && res.data) {
        setBookmarked(res.data.bookmarked);
        onToggle?.(res.data.bookmarked);
      } else {
        // Revert on failure
        setBookmarked(prev);
      }
    } catch (err) {
      logError('Failed to toggle bookmark', err);
      setBookmarked(prev);
    } finally {
      setIsLoading(false);
    }
  }, [type, id, bookmarked, isLoading, onToggle]);

  const iconSize = size === 'sm' ? 'w-[18px] h-[18px]' : 'w-5 h-5';

  return (
    <Tooltip
      content={bookmarked ? t('bookmark.remove', 'Remove from saved') : t('bookmark.save', 'Save')}
      delay={400}
      closeDelay={0}
      size="sm"
    >
      <Button
        isIconOnly
        size={size}
        variant="light"
        className={`${
          bookmarked
            ? 'text-amber-500'
            : 'text-[var(--text-muted)] hover:text-amber-500'
        } transition-colors min-w-0 ${className}`}
        onPress={handleToggle}
        isDisabled={isLoading}
        aria-label={bookmarked ? t('bookmark.remove', 'Remove from saved') : t('bookmark.save', 'Save')}
      >
        {bookmarked ? (
          <BookmarkCheck className={`${iconSize} fill-amber-500 text-amber-500`} aria-hidden="true" />
        ) : (
          <Bookmark className={iconSize} aria-hidden="true" />
        )}
      </Button>
    </Tooltip>
  );
}
