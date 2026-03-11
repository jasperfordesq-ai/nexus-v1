// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Command Palette / Search Overlay
 * Full-screen search panel with live suggestions, quick actions,
 * recent searches, and keyboard navigation.
 * Triggered by Ctrl/Cmd+K or the search icon.
 */

import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Input } from '@heroui/react';
import {
  Search,
  X,
  ListTodo,
  Calendar,
  Settings,
  Sun,
  Moon,
  UserCircle,
  HelpCircle,
  Clock,
  ArrowRight,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useTheme } from '@/contexts';
import { api } from '@/lib/api';

const RECENT_SEARCHES_KEY = 'nexus_recent_searches';

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
  const { tenantPath, hasFeature } = useTenant();
  const { isAuthenticated } = useAuth();
  const { resolvedTheme, toggleTheme } = useTheme();

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
          if (data.listings) allSuggestions.push(...data.listings.filter((s) => s.id).map((s) => ({ ...s, type: 'listing' as const })));
          if (data.users) allSuggestions.push(...data.users.filter((s) => s.id).map((s) => ({ ...s, type: 'user' as const })));
          if (data.events) allSuggestions.push(...data.events.filter((s) => s.id).map((s) => ({ ...s, type: 'event' as const })));
          if (data.groups) allSuggestions.push(...data.groups.filter((s) => s.id).map((s) => ({ ...s, type: 'group' as const })));
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

  // ── Recent searches (localStorage) ──────────────────────────────────────
  const [recentSearches, setRecentSearches] = useState<string[]>([]);

  useEffect(() => {
    try {
      const stored = localStorage.getItem(RECENT_SEARCHES_KEY);
      if (stored) setRecentSearches(JSON.parse(stored));
    } catch { /* ignore */ }
  }, [isOpen]);

  const saveRecentSearch = useCallback((query: string) => {
    const trimmed = query.trim();
    if (!trimmed || trimmed.length < 2) return;
    setRecentSearches(prev => {
      const updated = [trimmed, ...prev.filter(s => s !== trimmed)].slice(0, 5);
      try { localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(updated)); } catch { /* ignore */ }
      return updated;
    });
  }, []);

  const clearRecentSearches = useCallback(() => {
    setRecentSearches([]);
    try { localStorage.removeItem(RECENT_SEARCHES_KEY); } catch { /* ignore */ }
  }, []);

  // ── Quick actions ──────────────────────────────────────────────────────
  const quickActions = useMemo(() => {
    const actions: { label: string; icon: typeof Search; action: () => void; shortcut?: string }[] = [];
    if (isAuthenticated) {
      actions.push(
        { label: t('create.new_listing', 'New Listing'), icon: ListTodo, action: () => navigate(tenantPath('/listings/create')) },
      );
      if (hasFeature('events')) {
        actions.push(
          { label: t('create.new_event', 'New Event'), icon: Calendar, action: () => navigate(tenantPath('/events/create')) },
        );
      }
      actions.push(
        { label: t('user_menu.my_profile', 'My Profile'), icon: UserCircle, action: () => navigate(tenantPath('/profile')) },
        { label: t('user_menu.settings', 'Settings'), icon: Settings, action: () => navigate(tenantPath('/settings')) },
      );
    }
    actions.push(
      {
        label: resolvedTheme === 'dark' ? t('user_menu.light_mode', 'Light Mode') : t('user_menu.dark_mode', 'Dark Mode'),
        icon: resolvedTheme === 'dark' ? Sun : Moon,
        action: toggleTheme,
      },
      { label: t('support.help_center', 'Help Center'), icon: HelpCircle, action: () => navigate(tenantPath('/help')) },
    );
    return actions;
  }, [isAuthenticated, t, navigate, tenantPath, hasFeature, resolvedTheme, toggleTheme]);

  // Filter quick actions by search query when typing starts with ">"
  const filteredActions = useMemo(() => {
    if (!searchQuery.startsWith('>')) return [];
    const q = searchQuery.slice(1).trim().toLowerCase();
    if (!q) return quickActions;
    return quickActions.filter(a => a.label.toLowerCase().includes(q));
  }, [searchQuery, quickActions]);

  const isActionMode = searchQuery.startsWith('>');

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

  // Total navigable items count for unified keyboard nav
  const navigableCount = isActionMode ? filteredActions.length : suggestions.length;

  const handleSearchKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (navigableCount === 0 && e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev + 1) % Math.max(navigableCount, 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev <= 0 ? Math.max(navigableCount - 1, 0) : prev - 1));
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      if (isActionMode && filteredActions[selectedIndex]) {
        filteredActions[selectedIndex].action();
        closeAndReset();
      } else if (suggestions[selectedIndex]) {
        handleSuggestionClick(suggestions[selectedIndex]);
      }
    }
  }, [navigableCount, selectedIndex, isActionMode, filteredActions, suggestions, handleSuggestionClick, closeAndReset]);

  const handleSearchSubmit = useCallback((e?: React.FormEvent) => {
    e?.preventDefault();
    if (isActionMode) {
      if (selectedIndex >= 0 && filteredActions[selectedIndex]) {
        filteredActions[selectedIndex].action();
        closeAndReset();
      }
      return;
    }
    if (selectedIndex >= 0 && suggestions.length > 0) {
      handleSuggestionClick(suggestions[selectedIndex]);
      return;
    }
    if (searchQuery.trim()) {
      saveRecentSearch(searchQuery);
      navigate(tenantPath(`/search?q=${encodeURIComponent(searchQuery.trim())}`));
      closeAndReset();
    }
  }, [searchQuery, navigate, tenantPath, selectedIndex, suggestions, handleSuggestionClick, closeAndReset, isActionMode, filteredActions, saveRecentSearch]);

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

              {/* Results Panel */}
              <div className="border-t border-[var(--border-default)] px-4 py-3 max-h-80 overflow-y-auto">
                {/* Action mode (query starts with ">") */}
                {isActionMode ? (
                  <>
                    <p className="text-xs text-theme-subtle mb-2">{t('search.actions', 'Actions')}</p>
                    <div className="space-y-0.5" role="listbox" aria-label="Quick actions">
                      {filteredActions.map((action, index) => {
                        const Icon = action.icon;
                        const isSelected = index === selectedIndex;
                        return (
                          <Button
                            id={`suggestion-${index}`}
                            key={action.label}
                            variant="light"
                            fullWidth
                            role="option"
                            aria-selected={isSelected}
                            onPress={() => { action.action(); closeAndReset(); }}
                            onMouseEnter={() => setSelectedIndex(index)}
                            className={`flex items-center gap-3 px-3 py-2 rounded-lg text-left h-auto min-h-0 justify-start ${
                              isSelected
                                ? 'bg-indigo-50 dark:bg-indigo-500/10'
                                : 'hover:bg-[var(--surface-hover)]'
                            }`}
                          >
                            <Icon className="w-4 h-4 text-theme-subtle shrink-0" aria-hidden="true" />
                            <span className="text-sm text-theme-primary">{action.label}</span>
                            <ArrowRight className="w-3 h-3 ml-auto text-theme-subtle opacity-0 group-hover:opacity-100" aria-hidden="true" />
                          </Button>
                        );
                      })}
                      {filteredActions.length === 0 && (
                        <p className="text-sm text-theme-subtle py-2">{t('search.no_actions', 'No matching actions')}</p>
                      )}
                    </div>
                  </>
                ) : suggestions.length > 0 ? (
                  /* Search suggestions */
                  <>
                    <p className="text-xs text-theme-subtle mb-2">{t('search.suggestions')}</p>
                    <div className="space-y-0.5" role="listbox" aria-label="Search suggestions">
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
                  /* Default: recent searches + quick links + actions hint */
                  <>
                    {/* Recent Searches */}
                    {recentSearches.length > 0 && (
                      <div className="mb-3">
                        <div className="flex items-center justify-between mb-1.5">
                          <p className="text-xs text-theme-subtle">{t('search.recent', 'Recent')}</p>
                          <Button
                            variant="light"
                            size="sm"
                            className="text-[10px] text-theme-subtle hover:text-theme-primary h-5 min-w-0 px-1"
                            onPress={clearRecentSearches}
                          >
                            {t('search.clear', 'Clear')}
                          </Button>
                        </div>
                        <div className="space-y-0.5">
                          {recentSearches.map(query => (
                            <Button
                              key={query}
                              variant="light"
                              fullWidth
                              onPress={() => {
                                setSearchQuery(query);
                              }}
                              className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-left h-auto min-h-0 justify-start hover:bg-[var(--surface-hover)]"
                            >
                              <Clock className="w-3.5 h-3.5 text-theme-subtle shrink-0" aria-hidden="true" />
                              <span className="text-sm text-theme-secondary">{query}</span>
                            </Button>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* Quick Links */}
                    <p className="text-xs text-theme-subtle mb-2">{t('search.quick_links')}</p>
                    <div className="flex flex-wrap gap-2 mb-3">
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

                    {/* Actions hint */}
                    <div className="pt-2 border-t border-[var(--border-default)]">
                      <p className="text-[11px] text-theme-subtle">
                        {t('search.actions_hint', 'Type > for quick actions')}
                      </p>
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
