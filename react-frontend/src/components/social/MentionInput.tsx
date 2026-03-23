// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MentionInput — Enhanced textarea/input that supports @mention autocomplete.
 *
 * When the user types `@` followed by 2+ characters, a dropdown of matching
 * users appears. Keyboard navigation (up/down/enter/escape) is fully supported.
 * API calls are debounced at 300ms.
 *
 * Can be used as a drop-in replacement for HeroUI's Input or Textarea.
 */

import { useState, useCallback, useRef, useEffect } from 'react';
import { Textarea } from '@heroui/react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { MentionAutocomplete } from './MentionAutocomplete';
import type { MentionSuggestion } from './MentionAutocomplete';

/* ─── Types ─────────────────────────────────────────────────── */

export interface MentionedUser {
  id: number;
  name: string;
  username?: string | null;
}

export interface MentionInputProps {
  /** Current text value */
  value: string;
  /** Called when the text value changes */
  onChange: (value: string) => void;
  /** Called when the set of mentioned users changes */
  onMentionsChange?: (mentions: MentionedUser[]) => void;
  /** Placeholder text */
  placeholder?: string;
  /** Minimum rows for textarea */
  minRows?: number;
  /** Maximum rows for textarea */
  maxRows?: number;
  /** Custom mention search function (overrides default API call) */
  searchMentions?: (query: string) => Promise<MentionSuggestion[]>;
  /** Additional className for the wrapper */
  className?: string;
  /** Whether to auto-focus on mount */
  autoFocus?: boolean;
  /** HeroUI classNames pass-through */
  classNames?: Record<string, string>;
  /** End content (e.g. submit button) */
  endContent?: React.ReactNode;
  /** Disabled state */
  isDisabled?: boolean;
}

/* ─── Default search function ───────────────────────────────── */

async function defaultSearchMentions(query: string): Promise<MentionSuggestion[]> {
  try {
    const res = await api.get<MentionSuggestion[]>(
      `/v2/mentions/search?q=${encodeURIComponent(query)}`,
    );
    if (res.success && res.data) {
      return Array.isArray(res.data) ? res.data : [];
    }
    return [];
  } catch (err) {
    logError('MentionInput: search failed', err);
    return [];
  }
}

/* ─── Component ─────────────────────────────────────────────── */

export function MentionInput({
  value,
  onChange,
  onMentionsChange,
  placeholder = 'Write something...',
  minRows = 2,
  maxRows = 6,
  searchMentions,
  className = '',
  autoFocus,
  classNames: inputClassNames,
  endContent,
  isDisabled,
}: MentionInputProps) {
  const [suggestions, setSuggestions] = useState<MentionSuggestion[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [mentionQuery, setMentionQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [mentionedUsers, setMentionedUsers] = useState<MentionedUser[]>([]);

  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const searchFn = searchMentions ?? defaultSearchMentions;
  const searchVersionRef = useRef(0);

  // Cache recent results to avoid redundant API calls
  const cacheRef = useRef<Map<string, MentionSuggestion[]>>(new Map());

  const handleChange = useCallback(
    (newValue: string) => {
      onChange(newValue);

      // Detect @mention pattern at cursor position or end of text
      const match = newValue.match(/@([a-zA-Z0-9_.-]{2,})$/);
      if (match) {
        const query = match[1];
        setMentionQuery(query);
        setSelectedIndex(0);

        // Check cache first
        const cached = cacheRef.current.get(query.toLowerCase());
        if (cached) {
          setSuggestions(cached);
          setShowDropdown(cached.length > 0);
          return;
        }

        if (debounceRef.current) clearTimeout(debounceRef.current);
        setIsLoading(true);
        // Clear stale suggestions while loading new results
        setSuggestions([]);

        // Version counter to discard stale responses
        const version = ++searchVersionRef.current;

        debounceRef.current = setTimeout(async () => {
          const results = await searchFn(query);
          // Only apply results if this is still the latest search
          if (version !== searchVersionRef.current) return;
          const limited = results.slice(0, 10);
          cacheRef.current.set(query.toLowerCase(), limited);
          setSuggestions(limited);
          setShowDropdown(limited.length > 0);
          setIsLoading(false);
        }, 300);
      } else {
        setShowDropdown(false);
        setSuggestions([]);
        setIsLoading(false);
      }
    },
    [onChange, searchFn],
  );

  const selectMention = useCallback(
    (user: MentionSuggestion) => {
      // Replace @query with @DisplayName
      const displayName = user.username || user.name.replace(/\s+/g, '');
      const newValue = value.replace(new RegExp(`@${mentionQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`), `@${displayName} `);
      onChange(newValue);
      setShowDropdown(false);
      setSuggestions([]);

      // Track mentioned users
      const newMentioned = [...mentionedUsers.filter((m) => m.id !== user.id), { id: user.id, name: user.name, username: user.username }];
      setMentionedUsers(newMentioned);
      onMentionsChange?.(newMentioned);

      textareaRef.current?.focus();
    },
    [value, mentionQuery, onChange, mentionedUsers, onMentionsChange],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (showDropdown && suggestions.length > 0) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          setSelectedIndex((prev) => (prev + 1) % suggestions.length);
          return;
        }
        if (e.key === 'ArrowUp') {
          e.preventDefault();
          setSelectedIndex((prev) => (prev - 1 + suggestions.length) % suggestions.length);
          return;
        }
        if (e.key === 'Enter') {
          e.preventDefault();
          selectMention(suggestions[selectedIndex]);
          return;
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          setShowDropdown(false);
          return;
        }
      }
    },
    [showDropdown, suggestions, selectedIndex, selectMention],
  );

  // Cleanup debounce on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const activeDescendant = showDropdown && suggestions.length > 0
    ? `mention-option-${suggestions[selectedIndex]?.id}`
    : undefined;

  return (
    <div className={`relative ${className}`}>
      <Textarea
        ref={textareaRef}
        value={value}
        onValueChange={handleChange}
        onKeyDown={handleKeyDown}
        onBlur={() => {
          // Delay to allow click on dropdown
          setTimeout(() => setShowDropdown(false), 200);
        }}
        placeholder={placeholder}
        minRows={minRows}
        maxRows={maxRows}
        autoFocus={autoFocus}
        isDisabled={isDisabled}
        aria-expanded={showDropdown}
        aria-haspopup="listbox"
        aria-activedescendant={activeDescendant}
        aria-autocomplete="list"
        classNames={{
          input: 'bg-transparent text-[var(--text-primary)] text-sm',
          inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
          ...inputClassNames,
        }}
        endContent={endContent}
      />

      {/* Mention autocomplete dropdown */}
      <MentionAutocomplete
        isOpen={showDropdown}
        suggestions={suggestions}
        selectedIndex={selectedIndex}
        isLoading={isLoading && suggestions.length === 0}
        query={mentionQuery}
        onSelect={selectMention}
        onHover={setSelectedIndex}
        className="left-0 right-0 top-full mt-1"
      />
    </div>
  );
}

export default MentionInput;
