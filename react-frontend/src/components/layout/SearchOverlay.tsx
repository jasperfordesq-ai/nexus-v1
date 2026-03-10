// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Search Overlay
 * Full-screen search panel with live suggestions and keyboard navigation.
 * Extracted from Navbar for better separation of concerns.
 */

import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Input } from '@heroui/react';
import { Search, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';

interface SearchSuggestion {
  id: number;
  title?: string;
  name?: string;
  type: 'listing' | 'user' | 'event' | 'group';
}

interface SearchOverlayProps {
  isOpen: boolean;
  onClose: () => void;
}

export function SearchOverlay({ isOpen, onClose }: SearchOverlayProps) {
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();

  const [searchQuery, setSearchQuery] = useState('');
  const searchInputRef = useRef<HTMLInputElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
  const [isLoadingSuggestions, setIsLoadingSuggestions] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const suggestionsTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Auto-focus search input when opened
  useEffect(() => {
    if (isOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isOpen]);

  // Keyboard shortcut: Escape closes
  useEffect(() => {
    if (!isOpen) return;
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        closeAndReset();
      }
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen]); // eslint-disable-line react-hooks/exhaustive-deps

  // Focus trap — keep Tab cycling within the dialog
  useEffect(() => {
    if (!isOpen) return;
    function handleTab(e: KeyboardEvent) {
      if (e.key !== 'Tab' || !dialogRef.current) return;
      const focusable = dialogRef.current.querySelectorAll<HTMLElement>(
        'input, button, [tabindex]:not([tabindex="-1"])'
      );
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
    document.addEventListener('keydown', handleTab);
    return () => document.removeEventListener('keydown', handleTab);
  }, [isOpen]);

  // Debounced suggestions fetch
  useEffect(() => {
    if (suggestionsTimerRef.current) {
      clearTimeout(suggestionsTimerRef.current);
    }

    if (!isOpen || searchQuery.trim().length < 2) {
      setSuggestions([]);
      return;
    }

    suggestionsTimerRef.current = setTimeout(async () => {
      try {
        setIsLoadingSuggestions(true);
        const response = await api.get<Record<string, SearchSuggestion[]>>(
          `/v2/search/suggestions?q=${encodeURIComponent(searchQuery.trim())}&limit=5`
        );
        if (response.success && response.data) {
          const allSuggestions: SearchSuggestion[] = [];
          const data = response.data;
          if (data.listings) allSuggestions.push(...data.listings.map((s: SearchSuggestion) => ({ ...s, type: 'listing' as const })));
          if (data.users) allSuggestions.push(...data.users.map((s: SearchSuggestion) => ({ ...s, type: 'user' as const })));
          if (data.events) allSuggestions.push(...data.events.map((s: SearchSuggestion) => ({ ...s, type: 'event' as const })));
          if (data.groups) allSuggestions.push(...data.groups.map((s: SearchSuggestion) => ({ ...s, type: 'group' as const })));
          setSuggestions(allSuggestions.slice(0, 8));
        }
      } catch {
        // Silently fail — suggestions are non-critical
      } finally {
        setIsLoadingSuggestions(false);
      }
    }, 250);

    return () => {
      if (suggestionsTimerRef.current) {
        clearTimeout(suggestionsTimerRef.current);
      }
    };
  }, [searchQuery, isOpen]);

  // Reset selection when suggestions change
  useEffect(() => {
    setSelectedIndex(-1);
  }, [suggestions]);

  const closeAndReset = useCallback(() => {
    onClose();
    setSearchQuery('');
    setSuggestions([]);
    setSelectedIndex(-1);
  }, [onClose]);

  const handleSuggestionClick = useCallback((suggestion: SearchSuggestion) => {
    const pathMap: Record<string, string> = {
      listing: tenantPath(`/listings/${suggestion.id}`),
      user: tenantPath(`/profile/${suggestion.id}`),
      event: tenantPath(`/events/${suggestion.id}`),
      group: tenantPath(`/groups/${suggestion.id}`),
    };
    navigate(pathMap[suggestion.type] || tenantPath('/search'));
    closeAndReset();
  }, [navigate, tenantPath, closeAndReset]);

  const handleSearchKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (suggestions.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev + 1) % suggestions.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev <= 0 ? suggestions.length - 1 : prev - 1));
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      handleSuggestionClick(suggestions[selectedIndex]);
    }
  }, [suggestions, selectedIndex, handleSuggestionClick]);

  const handleSearchSubmit = useCallback((e?: React.FormEvent) => {
    e?.preventDefault();
    if (selectedIndex >= 0 && suggestions.length > 0) {
      handleSuggestionClick(suggestions[selectedIndex]);
      return;
    }
    if (searchQuery.trim()) {
      navigate(tenantPath(`/search?q=${encodeURIComponent(searchQuery.trim())}`));
      closeAndReset();
    }
  }, [searchQuery, navigate, tenantPath, selectedIndex, suggestions, handleSuggestionClick, closeAndReset]);

  // Respect prefers-reduced-motion
  const prefersReducedMotion = useMemo(() => {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }, []);

  const typeLabels: Record<string, { label: string; color: string }> = {
    listing: { label: t('search.type_listing'), color: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' },
    user: { label: t('search.type_member'), color: 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400' },
    event: { label: t('search.type_event'), color: 'bg-amber-500/20 text-amber-600 dark:text-amber-400' },
    group: { label: t('search.type_group'), color: 'bg-purple-500/20 text-purple-600 dark:text-purple-400' },
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={prefersReducedMotion ? { opacity: 1 } : { opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={prefersReducedMotion ? { opacity: 0 } : { opacity: 0 }}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60]"
            onClick={closeAndReset}
          />

          {/* Search Panel */}
          <motion.div
            initial={prefersReducedMotion ? { opacity: 1 } : { opacity: 0, y: -20, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={prefersReducedMotion ? { opacity: 0 } : { opacity: 0, y: -20, scale: 0.95 }}
            transition={prefersReducedMotion ? { duration: 0 } : { duration: 0.15 }}
            className="fixed top-20 sm:top-28 left-1/2 -translate-x-1/2 w-[90vw] max-w-xl z-[70]"
          >
            <div
              ref={dialogRef}
              role="dialog"
              aria-modal="true"
              aria-label={t('accessibility.search', 'Search')}
              className="bg-[var(--surface-dropdown)] rounded-xl border border-[var(--border-default)] shadow-2xl overflow-hidden"
            >
              <form onSubmit={handleSearchSubmit} className="flex items-center px-4 py-3 gap-3">
                <Input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onValueChange={setSearchQuery}
                  onKeyDown={handleSearchKeyDown}
                  placeholder={t('search.placeholder')}
                  aria-label="Search"
                  aria-autocomplete="list"
                  aria-activedescendant={selectedIndex >= 0 ? `suggestion-${selectedIndex}` : undefined}
                  startContent={<Search className="w-5 h-5 text-theme-subtle flex-shrink-0" aria-hidden="true" />}
                  endContent={
                    <div className="flex items-center gap-2 flex-shrink-0">
                      {searchQuery && (
                        <Button
                          isIconOnly
                          variant="light"
                          size="sm"
                          onPress={() => setSearchQuery('')}
                          className="text-theme-subtle hover:text-theme-primary min-w-6 w-6 h-6"
                          aria-label="Clear search"
                        >
                          <X className="w-4 h-4" aria-hidden="true" />
                        </Button>
                      )}
                      <kbd className="hidden sm:inline-flex items-center px-2 py-1 rounded bg-[var(--surface-elevated)] text-xs text-theme-subtle border border-[var(--border-default)]">
                        ESC
                      </kbd>
                    </div>
                  }
                  classNames={{
                    base: 'flex-1',
                    input: 'bg-transparent text-theme-primary text-base',
                    inputWrapper: 'bg-transparent shadow-none border-0 px-0 h-auto hover:bg-transparent focus-within:bg-transparent',
                  }}
                />
              </form>

              {/* Suggestions or Quick Links */}
              <div className="border-t border-[var(--border-default)] px-4 py-3 max-h-64 overflow-y-auto">
                {suggestions.length > 0 ? (
                  <>
                    <p className="text-xs text-theme-subtle mb-2">{t('search.suggestions')}</p>
                    <div className="space-y-1" role="listbox" aria-label="Search suggestions">
                      {suggestions.map((suggestion, index) => {
                        const typeInfo = typeLabels[suggestion.type] || { label: suggestion.type, color: 'bg-[var(--surface-elevated)] text-theme-subtle' };
                        const isSelected = index === selectedIndex;

                        return (
                          <Button
                            id={`suggestion-${index}`}
                            key={`${suggestion.type}-${suggestion.id}`}
                            variant="light"
                            fullWidth
                            role="option"
                            aria-selected={isSelected}
                            onPress={() => handleSuggestionClick(suggestion)}
                            onMouseEnter={() => setSelectedIndex(index)}
                            className={`flex items-center justify-between px-3 py-2 rounded-lg text-left h-auto min-h-0 ${
                              isSelected
                                ? 'bg-indigo-50 dark:bg-indigo-500/10'
                                : 'hover:bg-[var(--surface-hover)]'
                            }`}
                          >
                            <span className="text-sm text-theme-primary truncate">
                              {suggestion.title || suggestion.name}
                            </span>
                            <span className={`text-[10px] px-2 py-0.5 rounded-full ${typeInfo.color} ml-2 flex-shrink-0`}>
                              {typeInfo.label}
                            </span>
                          </Button>
                        );
                      })}
                    </div>
                  </>
                ) : isLoadingSuggestions ? (
                  <div className="flex items-center gap-2 py-2">
                    <div className="w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                    <span className="text-xs text-theme-subtle">{t('search.searching')}</span>
                  </div>
                ) : (
                  <>
                    <p className="text-xs text-theme-subtle mb-2">{t('search.quick_links')}</p>
                    <div className="flex flex-wrap gap-2">
                      {[
                        { label: t('nav.listings'), path: tenantPath('/listings') },
                        { label: t('nav.members'), path: tenantPath('/members') },
                        { label: t('nav.events'), path: tenantPath('/events') },
                        { label: t('support.help_center'), path: tenantPath('/help') },
                      ].map((link) => (
                        <Button
                          key={link.path}
                          variant="flat"
                          size="sm"
                          onPress={() => { navigate(link.path); closeAndReset(); }}
                          className="px-3 py-1.5 rounded-lg bg-[var(--surface-elevated)] text-sm text-theme-muted hover:text-theme-primary hover:bg-[var(--surface-hover)]"
                        >
                          {link.label}
                        </Button>
                      ))}
                    </div>
                  </>
                )}
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}

export default SearchOverlay;
