// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Command Palette / Search Overlay
 *
 * Full-screen search panel with live suggestions, quick actions,
 * recent searches, and keyboard navigation.
 * Triggered by Ctrl/Cmd+K or the search icon.
 *
 * Built on HeroUI Modal which handles ESC, backdrop click, focus trap,
 * body scroll lock, and accessibility out of the box.
 */

import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Modal, ModalContent, Button, Input } from '@heroui/react';
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
  const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
  const [isLoadingSuggestions, setIsLoadingSuggestions] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const suggestionsTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Reset state when modal closes
  const handleClose = useCallback(() => {
    onClose();
    setSearchQuery('');
    setSuggestions([]);
    setSelectedIndex(-1);
  }, [onClose]);

  // Auto-focus search input when opened
  useEffect(() => {
    if (isOpen) {
      // Small delay to let HeroUI Modal finish mounting
      const timer = setTimeout(() => searchInputRef.current?.focus(), 50);
      return () => clearTimeout(timer);
    }
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
    if (!isOpen) return;
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
    const actions: { label: string; icon: typeof Search; action: () => void }[] = [];
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

  const filteredActions = useMemo(() => {
    if (!searchQuery.startsWith('>')) return [];
    const q = searchQuery.slice(1).trim().toLowerCase();
    if (!q) return quickActions;
    return quickActions.filter(a => a.label.toLowerCase().includes(q));
  }, [searchQuery, quickActions]);

  const isActionMode = searchQuery.startsWith('>');

  // ── Navigation helpers ──────────────────────────────────────────────────
  const handleSuggestionClick = useCallback((suggestion: SearchSuggestion) => {
    const pathMap: Record<string, string> = {
      listing: tenantPath(`/listings/${suggestion.id}`),
      user: tenantPath(`/profile/${suggestion.id}`),
      event: tenantPath(`/events/${suggestion.id}`),
      group: tenantPath(`/groups/${suggestion.id}`),
    };
    navigate(pathMap[suggestion.type] || tenantPath('/search'));
    handleClose();
  }, [navigate, tenantPath, handleClose]);

  const navigateToSearchPage = useCallback(() => {
    if (searchQuery.trim()) {
      saveRecentSearch(searchQuery);
      navigate(tenantPath(`/search?q=${encodeURIComponent(searchQuery.trim())}`));
      handleClose();
    }
  }, [searchQuery, navigate, tenantPath, handleClose, saveRecentSearch]);

  // ── Keyboard navigation ─────────────────────────────────────────────────
  const navigableCount = isActionMode ? filteredActions.length : suggestions.length;

  const handleSearchKeyDown = useCallback((e: React.KeyboardEvent) => {
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
        handleClose();
      } else if (suggestions[selectedIndex]) {
        handleSuggestionClick(suggestions[selectedIndex]);
      }
    }
  }, [navigableCount, selectedIndex, isActionMode, filteredActions, suggestions, handleSuggestionClick, handleClose]);

  const handleSearchSubmit = useCallback((e?: React.FormEvent) => {
    e?.preventDefault();
    if (isActionMode) {
      if (selectedIndex >= 0 && filteredActions[selectedIndex]) {
        filteredActions[selectedIndex].action();
        handleClose();
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
      handleClose();
    }
  }, [searchQuery, navigate, tenantPath, selectedIndex, suggestions, handleSuggestionClick, handleClose, isActionMode, filteredActions, saveRecentSearch]);

  // ── Type labels for suggestion badges ───────────────────────────────────
  const typeLabels: Record<string, { label: string; color: string }> = {
    listing: { label: t('search.type_listing'), color: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' },
    user: { label: t('search.type_member'), color: 'bg-indigo-500/20 text-[var(--color-primary)]' },
    event: { label: t('search.type_event'), color: 'bg-amber-500/20 text-amber-600 dark:text-amber-400' },
    group: { label: t('search.type_group'), color: 'bg-purple-500/20 text-purple-600 dark:text-purple-400' },
  };

  const selectedClass = 'bg-[color-mix(in_srgb,var(--color-primary)_10%,transparent)]';

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      placement="top"
      backdrop="blur"
      hideCloseButton
      size="xl"
      classNames={{
        backdrop: 'bg-black/50',
        base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-2xl mt-16 sm:mt-24',
        body: 'p-0',
      }}
    >
      <ModalContent>
        {/* Search Input */}
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
              <div className="flex items-center gap-1 flex-shrink-0">
                {searchQuery && (
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    onPress={() => setSearchQuery('')}
                    className="text-theme-subtle hover:text-theme-primary min-w-6 w-6 h-6"
                    aria-label={t('search.clear')}
                  >
                    <X className="w-4 h-4" aria-hidden="true" />
                  </Button>
                )}
                <Button
                  variant="light"
                  size="sm"
                  onPress={handleClose}
                  className="hidden sm:inline-flex items-center gap-1 px-2 py-1 min-w-0 h-7 rounded-md bg-[var(--surface-elevated)] text-xs text-theme-subtle border border-[var(--border-default)] hover:text-theme-primary hover:bg-[var(--surface-hover)]"
                  aria-label={t('accessibility.close', 'Close (ESC)')}
                >
                  <X className="w-3.5 h-3.5" aria-hidden="true" />
                  <kbd className="text-[11px] leading-none select-none">ESC</kbd>
                </Button>
                <Button
                  isIconOnly
                  variant="light"
                  size="sm"
                  onPress={handleClose}
                  className="sm:hidden text-theme-subtle hover:text-theme-primary min-w-7 w-7 h-7 rounded-full"
                  aria-label={t('accessibility.close', 'Close')}
                >
                  <X className="w-5 h-5" aria-hidden="true" />
                </Button>
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
                      onPress={() => { action.action(); handleClose(); }}
                      onMouseEnter={() => setSelectedIndex(index)}
                      className={`flex items-center gap-3 px-3 py-2 rounded-lg text-left h-auto min-h-0 justify-start ${
                        isSelected ? selectedClass : 'hover:bg-[var(--surface-hover)]'
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
                        isSelected ? selectedClass : 'hover:bg-[var(--surface-hover)]'
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
              <Button
                variant="light"
                fullWidth
                onPress={navigateToSearchPage}
                className="mt-2 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-left h-auto min-h-0 hover:bg-[var(--surface-hover)] border-t border-[var(--border-default)] pt-3"
              >
                <Search className="w-4 h-4 text-[var(--color-primary)]" aria-hidden="true" />
                <span className="text-sm text-[var(--color-primary)] font-medium">
                  {t('search.view_all', 'View all results')}
                </span>
                <ArrowRight className="w-3 h-3 text-[var(--color-primary)] ml-auto" aria-hidden="true" />
              </Button>
            </>
          ) : isLoadingSuggestions ? (
            <div className="flex items-center gap-2 py-2">
              <div className="w-4 h-4 border-2 border-[var(--color-primary)] border-t-transparent rounded-full animate-spin" />
              <span className="text-xs text-theme-subtle">{t('search.searching')}</span>
            </div>
          ) : searchQuery.trim().length >= 2 ? (
            /* No suggestions found - prompt full search */
            <div className="space-y-2">
              <p className="text-sm text-theme-subtle py-1">{t('search.no_suggestions', 'No quick matches')}</p>
              <Button
                variant="light"
                fullWidth
                onPress={navigateToSearchPage}
                className="flex items-center justify-center gap-2 px-3 py-2 rounded-lg h-auto min-h-0 hover:bg-[var(--surface-hover)]"
              >
                <Search className="w-4 h-4 text-[var(--color-primary)]" aria-hidden="true" />
                <span className="text-sm text-[var(--color-primary)] font-medium">
                  {t('search.search_for', 'Search for')} &ldquo;{searchQuery.trim()}&rdquo;
                </span>
                <ArrowRight className="w-3 h-3 text-[var(--color-primary)] ml-auto" aria-hidden="true" />
              </Button>
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
                          saveRecentSearch(query);
                          navigate(tenantPath(`/search?q=${encodeURIComponent(query)}`));
                          handleClose();
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
                    onPress={() => { navigate(link.path); handleClose(); }}
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
      </ModalContent>
    </Modal>
  );
}

export default SearchOverlay;
