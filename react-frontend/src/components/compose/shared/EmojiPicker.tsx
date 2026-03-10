// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EmojiPicker — popover-based emoji picker for ComposeHub.
 * Displays categorized Unicode emoji with search and category navigation.
 */

import { useMemo, useRef, useState } from 'react';
import { Button, Input, Popover, PopoverContent, PopoverTrigger } from '@heroui/react';
import { Search, Smile } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { EMOJI_CATEGORIES, EMOJI_KEYWORDS } from '@/data/emoji-data';

interface EmojiPickerProps {
  onSelect: (emoji: string) => void;
}

export function EmojiPicker({ onSelect }: EmojiPickerProps) {
  const { t } = useTranslation('feed');
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [activeCategory, setActiveCategory] = useState(EMOJI_CATEGORIES[0].key);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const gridRef = useRef<HTMLDivElement>(null);
  const categoryRefs = useRef<Record<string, HTMLDivElement | null>>({});

  const filteredCategories = useMemo(() => {
    if (!search.trim()) return EMOJI_CATEGORIES;

    const query = search.toLowerCase();
    return EMOJI_CATEGORIES
      .map((cat) => {
        // Check if the category label matches
        const labelText = t(cat.label).toLowerCase();
        if (labelText.includes(query)) return cat;

        // If category key matches query, return all emoji in that category
        if (cat.key.includes(query)) return cat;

        // Filter individual emoji by direct match or keyword match
        const matchingEmojis = cat.emojis.filter((emoji) => {
          // Direct emoji match
          if (emoji.includes(query)) return true;
          // Keyword match: check if any keyword for this emoji contains the query
          const keywords = EMOJI_KEYWORDS[emoji];
          return keywords?.some((kw) => kw.includes(query)) ?? false;
        });

        if (matchingEmojis.length > 0) {
          return { ...cat, emojis: matchingEmojis };
        }

        return null;
      })
      .filter((cat): cat is NonNullable<typeof cat> => cat !== null && cat.emojis.length > 0);
  }, [search, t]);

  const handleEmojiClick = (emoji: string) => {
    onSelect(emoji);
    // Move focus back to the trigger before closing to prevent
    // aria-hidden from trapping focus inside the popover (HeroUI bug)
    triggerRef.current?.focus();
    setIsOpen(false);
    setSearch('');
  };

  const handleCategoryClick = (key: string) => {
    setActiveCategory(key);
    setSearch('');
    const el = categoryRefs.current[key];
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      // Return focus to trigger before closing to avoid aria-hidden focus trap
      triggerRef.current?.focus();
      setSearch('');
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
          aria-label={t('compose.emoji_search')}
          className="min-w-11 w-11 h-11"
        >
          <Smile className="w-4 h-4" aria-hidden="true" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-2">
        {/* Search */}
        <Input
          size="sm"
          placeholder={t('compose.emoji_search')}
          value={search}
          onValueChange={setSearch}
          startContent={<Search className="w-3.5 h-3.5 text-[var(--text-muted)]" aria-hidden="true" />}
          className="mb-2"
          aria-label={t('compose.emoji_search')}
        />

        {/* Category tabs */}
        <div className="flex gap-0.5 mb-2 overflow-x-auto">
          {EMOJI_CATEGORIES.map((cat) => (
            <Button
              key={cat.key}
              isIconOnly
              size="sm"
              variant="light"
              onPress={() => handleCategoryClick(cat.key)}
              className={`
                w-10 h-10 flex items-center justify-center rounded-lg text-base
                cursor-pointer transition-colors shrink-0
                ${activeCategory === cat.key
                  ? 'bg-[var(--surface-hover)]'
                  : 'hover:bg-[var(--surface-hover)]'
                }
              `}
              aria-label={t(cat.label)}
              aria-pressed={activeCategory === cat.key}
              title={t(cat.label)}
            >
              {cat.icon}
            </Button>
          ))}
        </div>

        {/* Emoji grid */}
        <div ref={gridRef} className="max-h-64 overflow-y-auto">
          {filteredCategories.length === 0 && (
            <p className="text-center text-xs text-[var(--text-muted)] py-4">
              {t('compose.emoji_search')}
            </p>
          )}
          {filteredCategories.map((cat) => (
            <div
              key={cat.key}
              ref={(el) => { categoryRefs.current[cat.key] = el; }}
            >
              <p className="text-xs text-[var(--text-muted)] px-1 py-1 font-medium">
                {t(cat.label)}
              </p>
              <div className="grid grid-cols-8 gap-1">
                {cat.emojis.map((emoji) => (
                  <Button
                    key={emoji}
                    isIconOnly
                    size="sm"
                    variant="light"
                    onPress={() => handleEmojiClick(emoji)}
                    className="w-11 h-11 text-lg cursor-pointer hover:bg-[var(--surface-hover)] rounded-lg flex items-center justify-center"
                    aria-label={emoji}
                  >
                    {emoji}
                  </Button>
                ))}
              </div>
            </div>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}
