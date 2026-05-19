// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Search Overlay / Command Palette
 *
 * SIMPLE implementation using portal + basic React.
 * No HeroUI Modal, no complex refs, no fancy abstractions.
 * Just works.
 */

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';
import { Button } from '@heroui/react';
import Search from 'lucide-react/icons/search';
import X from 'lucide-react/icons/x';
import ListTodo from 'lucide-react/icons/list-todo';
import Calendar from 'lucide-react/icons/calendar';
import Settings from 'lucide-react/icons/settings';
import Sun from 'lucide-react/icons/sun';
import Moon from 'lucide-react/icons/moon';
import UserCircle from 'lucide-react/icons/circle-user';
import HelpCircle from 'lucide-react/icons/circle-help';
import Clock from 'lucide-react/icons/clock';
import ArrowRight from 'lucide-react/icons/arrow-right';
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

  // Stable ref for t — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;

  // ─── State ─────────────────────────────────────────────────────────────
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const [recentSearches, setRecentSearches] = useState<string[]>([]);

  // ─── Close and reset everything ────────────────────────────────────────
  const handleClose = useCallback(() => {
    setQuery('');
    setSuggestions([]);
    setSelectedIndex(-1);
    onClose();
  }, [onClose]);

  // ─── ESC key handler (on document) ─────────────────────────────────────
  useEffect(() => {
    if (!isOpen) return;

    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        handleClose();
      }
    };

    document.addEventListener('keydown', handleEsc, true);
    return () => document.removeEventListener('keydown', handleEsc, true);
  }, [isOpen, handleClose]);

  // ─── Lock body scroll when open ────────────────────────────────────────
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  // ─── Load recent searches on open ──────────────────────────────────────
  useEffect(() => {
    if (!isOpen) return;
    try {
      const stored = localStorage.getItem(RECENT_SEARCHES_KEY);
      if (stored) setRecentSearches(JSON.parse(stored));
    } catch {
      // ignore
    }
  }, [isOpen]);

  // ─── Debounced search ──────────────────────────────────────────────────
  useEffect(() => {
    if (!isOpen) return;

    const trimmed = query.trim();
    if (trimmed.length < 2 || trimmed.startsWith('>')) {
      setSuggestions([]);
      return;
    }

    setIsLoading(true);
    const timer = setTimeout(async () => {
      try {
        const response = await api.get<Record<string, SearchSuggestion[]>>(
          `/v2/search/suggestions?q=${encodeURIComponent(trimmed)}&limit=5`
        );
        if (response.success && response.data) {
          const all: SearchSuggestion[] = [];
          const d = response.data;
          if (d.listings) all.push(...d.listings.map(s => ({ ...s, type: 'listing' as const })));
          if (d.users) all.push(...d.users.map(s => ({ ...s, type: 'user' as const })));
          if (d.events) all.push(...d.events.map(s => ({ ...s, type: 'event' as const })));
          if (d.groups) all.push(...d.groups.map(s => ({ ...s, type: 'group' as const })));
          setSuggestions(all.slice(0, 8));
        }
      } catch {
        // ignore
      } finally {
        setIsLoading(false);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [query, isOpen]);

  // Reset selection when suggestions change
  useEffect(() => {
    setSelectedIndex(-1);
  }, [suggestions]);

  // ─── Recent searches helpers ───────────────────────────────────────────
  const saveRecent = useCallback((q: string) => {
    const trimmed = q.trim();
    if (!trimmed || trimmed.length < 2) return;
    setRecentSearches(prev => {
      const updated = [trimmed, ...prev.filter(s => s !== trimmed)].slice(0, 5);
      try {
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(updated));
      } catch {
        // ignore
      }
      return updated;
    });
  }, []);

  const clearRecent = useCallback(() => {
    setRecentSearches([]);
    try {
      localStorage.removeItem(RECENT_SEARCHES_KEY);
    } catch {
      // ignore
    }
  }, []);

  // ─── Quick actions ─────────────────────────────────────────────────────
  const quickActions = useMemo(() => {
    const actions: { label: string; icon: typeof Search; action: () => void }[] = [];
    if (isAuthenticated) {
      actions.push({ label: t('create.new_listing'), icon: ListTodo, action: () => navigate(tenantPath('/listings/create')) });
      if (hasFeature('events')) {
        actions.push({ label: t('create.new_event'), icon: Calendar, action: () => navigate(tenantPath('/events/create')) });
      }
      actions.push(
        { label: t('user_menu.my_profile'), icon: UserCircle, action: () => navigate(tenantPath('/profile')) },
        { label: t('user_menu.settings'), icon: Settings, action: () => navigate(tenantPath('/settings')) }
      );
    }
    actions.push(
      { label: resolvedTheme === 'dark' ? t('user_menu.light_mode') : t('user_menu.dark_mode'), icon: resolvedTheme === 'dark' ? Sun : Moon, action: toggleTheme },
      { label: t('support.help_center'), icon: HelpCircle, action: () => navigate(tenantPath('/help')) }
    );
    return actions;
  }, [isAuthenticated, t, navigate, tenantPath, hasFeature, resolvedTheme, toggleTheme]);

  const isActionMode = query.startsWith('>');
  const filteredActions = useMemo(() => {
    if (!isActionMode) return [];
    const q = query.slice(1).trim().toLowerCase();
    return q ? quickActions.filter(a => a.label.toLowerCase().includes(q)) : quickActions;
  }, [query, quickActions, isActionMode]);

  // ─── Navigation helpers ────────────────────────────────────────────────
  const goToSuggestion = useCallback((s: SearchSuggestion) => {
    const paths: Record<string, string> = {
      listing: tenantPath(`/listings/${s.id}`),
      user: tenantPath(`/profile/${s.id}`),
      event: tenantPath(`/events/${s.id}`),
      group: tenantPath(`/groups/${s.id}`),
    };
    navigate(paths[s.type] || tenantPath('/search'));
    handleClose();
  }, [navigate, tenantPath, handleClose]);

  const goToSearch = useCallback(() => {
    const trimmed = query.trim();
    if (trimmed) {
      saveRecent(trimmed);
      navigate(tenantPath(`/search?q=${encodeURIComponent(trimmed)}`));
      handleClose();
    }
  }, [query, navigate, tenantPath, handleClose, saveRecent]);

  // ─── Keyboard navigation in input ──────────────────────────────────────
  const items = isActionMode ? filteredActions : suggestions;
  const itemCount = items.length;

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex(i => (i + 1) % Math.max(itemCount, 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex(i => (i <= 0 ? Math.max(itemCount - 1, 0) : i - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedIndex >= 0 && selectedIndex < itemCount) {
        if (isActionMode) {
          filteredActions[selectedIndex]?.action();
          handleClose();
        } else {
          const suggestion = suggestions[selectedIndex];
          if (suggestion) goToSuggestion(suggestion);
        }
      } else {
        goToSearch();
      }
    }
  };

  // ─── Type badges ───────────────────────────────────────────────────────
  const typeLabels: Record<string, { label: string; color: string }> = {
    listing: { label: t('search.type_listing'), color: 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-300' },
    user: { label: t('search.type_member'), color: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' },
    event: { label: t('search.type_event'), color: 'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300' },
    group: { label: t('search.type_group'), color: 'bg-secondary-100 text-secondary-700 dark:bg-secondary-900/30 dark:text-secondary-300' },
  };

  // ─── Don't render if not open ──────────────────────────────────────────
  if (!isOpen) return null;

  // ─── Render via portal ─────────────────────────────────────────────────
  return createPortal(
    <div className="fixed inset-0 z-[9999]">
      {/* Backdrop - clicking closes */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={handleClose}
        aria-hidden="true"
      />

      {/* Modal panel */}
      <div
        className="absolute top-[calc(var(--safe-area-top)+1rem)] sm:top-[calc(var(--safe-area-top)+4.5rem)] left-1/2 -translate-x-1/2 w-[calc(100dvw-var(--safe-area-left)-var(--safe-area-right)-1rem)] max-w-xl"
        onClick={e => e.stopPropagation()}
      >
        <div className="flex max-h-[calc(100dvh-var(--safe-area-top)-var(--safe-area-bottom)-2rem)] flex-col overflow-hidden rounded-xl border border-divider bg-content1 shadow-large">
          {/* Search input row */}
          <div className="flex shrink-0 items-center gap-2 px-3 sm:px-4 py-3 border-b border-divider">
            <Search className="w-5 h-5 text-default-400 flex-shrink-0" />
            <input
              type="text"
              value={query}
              onChange={e => setQuery(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={t('search.placeholder')}
              aria-label={t('search.placeholder')}
              autoFocus
              className="min-w-0 flex-1 bg-transparent text-foreground placeholder:text-default-400 text-base outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
            />
            {query && (
              <Button
                isIconOnly
                variant="light"
                size="sm"
                onPress={() => setQuery('')}
                className="p-1 h-auto min-w-0 text-default-400 hover:text-default-700"
                aria-label={t('aria.clear')}
              >
                <X className="w-4 h-4" />
              </Button>
            )}
            <Button
              variant="flat"
              size="sm"
              onPress={handleClose}
              className="hidden min-[360px]:flex items-center gap-1.5 px-2 py-1 rounded-md text-xs h-auto"
              aria-label={t('accessibility.close')}
            >
              <X className="w-3.5 h-3.5" />
              <kbd className="text-[10px]">ESC</kbd>
            </Button>
          </div>

          {/* Results area */}
          <div className="min-h-0 flex-1 overflow-y-auto px-3 sm:px-4 py-3 overscroll-contain">
            {/* Action mode */}
            {isActionMode ? (
              <div>
                <p className="text-xs text-default-500 mb-2">{t('search.actions')}</p>
                {filteredActions.length > 0 ? (
                  <div className="space-y-1">
                    {filteredActions.map((action, i) => {
                      const Icon = action.icon;
                      return (
                        <Button
                          key={action.label}
                          variant="light"
                          onPress={() => { action.action(); handleClose(); }}
                          onMouseEnter={() => setSelectedIndex(i)}
                          onFocus={() => setSelectedIndex(i)}
                          className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-start h-auto justify-start min-w-0 ${
                            i === selectedIndex
                              ? 'bg-primary-50 dark:bg-primary-500/10'
                              : 'hover:bg-default-100'
                          }`}
                        >
                          <Icon className="w-4 h-4 shrink-0 text-default-500" />
                          <span className="min-w-0 truncate text-sm text-foreground">{action.label}</span>
                        </Button>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-sm text-default-500">{t('search.no_actions')}</p>
                )}
              </div>
            ) : suggestions.length > 0 ? (
              /* Suggestions */
              <div>
                <p className="text-xs text-default-500 mb-2">{t('search.suggestions')}</p>
                <div className="space-y-1">
                  {suggestions.map((s, i) => {
                    const type = typeLabels[s.type] || { label: s.type, color: 'bg-default-100 text-default-600' };
                    return (
                      <Button
                        key={`${s.type}-${s.id}`}
                        variant="light"
                        onPress={() => goToSuggestion(s)}
                        onMouseEnter={() => setSelectedIndex(i)}
                        className={`w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-start h-auto min-w-0 ${
                          i === selectedIndex
                            ? 'bg-primary-50 dark:bg-primary-500/10'
                            : 'hover:bg-default-100'
                        }`}
                      >
                        <span className="min-w-0 truncate text-sm text-foreground">{s.title || s.name}</span>
                        <span className={`text-[10px] px-2 py-0.5 rounded-full ${type.color} ms-2 flex-shrink-0`}>{type.label}</span>
                      </Button>
                    );
                  })}
                </div>
                <Button
                  variant="light"
                  onPress={goToSearch}
                  className="w-full flex items-center justify-center gap-2 mt-3 pt-3 border-t border-divider text-primary hover:underline text-sm h-auto rounded-none"
                >
                  <Search className="w-4 h-4" />
                  {t('search.view_all')}
                  <ArrowRight className="w-3 h-3" />
                </Button>
              </div>
            ) : isLoading ? (
              /* Loading */
              <div className="flex items-center gap-2 py-4">
                <div className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <span className="text-sm text-default-500">{t('search.searching')}</span>
              </div>
            ) : query.trim().length >= 2 ? (
              /* No results */
              <div className="py-4 text-center">
                <p className="text-sm text-default-500 mb-2">{t('search.no_suggestions')}</p>
                <Button
                  variant="light"
                  onPress={goToSearch}
                  className="inline-flex items-center gap-2 text-primary hover:underline text-sm h-auto"
                >
                  <Search className="w-4 h-4" />
                  {t('search.search_for')} "{query.trim()}"
                </Button>
              </div>
            ) : (
              /* Default state: recent + quick links */
              <div>
                {recentSearches.length > 0 && (
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-default-500">{t('search.recent')}</p>
                      <Button
                        variant="light"
                        size="sm"
                        onPress={clearRecent}
                        className="text-[10px] text-default-400 hover:text-default-700 h-auto p-0 min-w-0"
                      >
                        {t('search.clear')}
                      </Button>
                    </div>
                    <div className="space-y-1">
                      {recentSearches.map(q => (
                        <Button
                          key={q}
                          variant="light"
                          onPress={() => {
                            saveRecent(q);
                            navigate(tenantPath(`/search?q=${encodeURIComponent(q)}`));
                            handleClose();
                          }}
                          className="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-start hover:bg-default-100 h-auto justify-start min-w-0"
                        >
                          <Clock className="w-3.5 h-3.5 shrink-0 text-default-400" />
                          <span className="min-w-0 truncate text-sm text-default-600">{q}</span>
                        </Button>
                      ))}
                    </div>
                  </div>
                )}

                <p className="text-xs text-default-500 mb-2">{t('search.quick_links')}</p>
                <div className="flex flex-wrap gap-2 mb-4">
                  {[
                    { label: t('nav.listings'), path: tenantPath('/listings') },
                    ...(hasFeature('connections') ? [{ label: t('nav.members'), path: tenantPath('/members') }] : []),
                    { label: t('nav.events'), path: tenantPath('/events') },
                    { label: t('support.help_center'), path: tenantPath('/help') },
                  ].map(link => (
                    <Button
                      key={link.path}
                      variant="flat"
                      size="sm"
                      onPress={() => { navigate(link.path); handleClose(); }}
                      className="px-3 py-1.5 rounded-lg text-sm h-auto"
                    >
                      {link.label}
                    </Button>
                  ))}
                </div>

                <p className="text-[11px] text-default-400 pt-2 border-t border-divider">
                  {t('search.actions_hint')}
                </p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>,
    document.body
  );
}

export default SearchOverlay;
