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

import { useState, useEffect, useCallback, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';
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
      actions.push({ label: t('create.new_listing', 'New Listing'), icon: ListTodo, action: () => navigate(tenantPath('/listings/create')) });
      if (hasFeature('events')) {
        actions.push({ label: t('create.new_event', 'New Event'), icon: Calendar, action: () => navigate(tenantPath('/events/create')) });
      }
      actions.push(
        { label: t('user_menu.my_profile', 'My Profile'), icon: UserCircle, action: () => navigate(tenantPath('/profile')) },
        { label: t('user_menu.settings', 'Settings'), icon: Settings, action: () => navigate(tenantPath('/settings')) }
      );
    }
    actions.push(
      { label: resolvedTheme === 'dark' ? t('user_menu.light_mode', 'Light Mode') : t('user_menu.dark_mode', 'Dark Mode'), icon: resolvedTheme === 'dark' ? Sun : Moon, action: toggleTheme },
      { label: t('support.help_center', 'Help Center'), icon: HelpCircle, action: () => navigate(tenantPath('/help')) }
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
          goToSuggestion(suggestions[selectedIndex]);
        }
      } else {
        goToSearch();
      }
    }
  };

  // ─── Type badges ───────────────────────────────────────────────────────
  const typeLabels: Record<string, { label: string; color: string }> = {
    listing: { label: t('search.type_listing', 'Listing'), color: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' },
    user: { label: t('search.type_member', 'Member'), color: 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400' },
    event: { label: t('search.type_event', 'Event'), color: 'bg-amber-500/20 text-amber-600 dark:text-amber-400' },
    group: { label: t('search.type_group', 'Group'), color: 'bg-purple-500/20 text-purple-600 dark:text-purple-400' },
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
        className="absolute top-16 sm:top-24 left-1/2 -translate-x-1/2 w-[92vw] max-w-xl"
        onClick={e => e.stopPropagation()}
      >
        <div className="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-2xl overflow-hidden">
          {/* Search input row */}
          <div className="flex items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
            <Search className="w-5 h-5 text-zinc-400 flex-shrink-0" />
            <input
              type="text"
              value={query}
              onChange={e => setQuery(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={t('search.placeholder', 'Search...')}
              aria-label={t('search.placeholder', 'Search...')}
              autoFocus
              className="flex-1 bg-transparent text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 text-base outline-none"
            />
            {query && (
              <button
                type="button"
                onClick={() => setQuery('')}
                className="p-1 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                aria-label="Clear"
              >
                <X className="w-4 h-4" />
              </button>
            )}
            <button
              type="button"
              onClick={handleClose}
              className="flex items-center gap-1.5 px-2 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 text-xs border border-zinc-200 dark:border-zinc-600"
              aria-label="Close"
            >
              <X className="w-3.5 h-3.5" />
              <kbd className="text-[10px]">ESC</kbd>
            </button>
          </div>

          {/* Results area */}
          <div className="px-4 py-3 max-h-80 overflow-y-auto">
            {/* Action mode */}
            {isActionMode ? (
              <div>
                <p className="text-xs text-zinc-500 mb-2">{t('search.actions', 'Actions')}</p>
                {filteredActions.length > 0 ? (
                  <div className="space-y-1">
                    {filteredActions.map((action, i) => {
                      const Icon = action.icon;
                      return (
                        <button
                          key={action.label}
                          type="button"
                          onClick={() => { action.action(); handleClose(); }}
                          onMouseEnter={() => setSelectedIndex(i)}
                          onFocus={() => setSelectedIndex(i)}
                          className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left ${
                            i === selectedIndex
                              ? 'bg-indigo-50 dark:bg-indigo-500/10'
                              : 'hover:bg-zinc-50 dark:hover:bg-zinc-800'
                          }`}
                        >
                          <Icon className="w-4 h-4 text-zinc-500" />
                          <span className="text-sm text-zinc-800 dark:text-zinc-200">{action.label}</span>
                        </button>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-sm text-zinc-500">{t('search.no_actions', 'No matching actions')}</p>
                )}
              </div>
            ) : suggestions.length > 0 ? (
              /* Suggestions */
              <div>
                <p className="text-xs text-zinc-500 mb-2">{t('search.suggestions', 'Suggestions')}</p>
                <div className="space-y-1">
                  {suggestions.map((s, i) => {
                    const type = typeLabels[s.type] || { label: s.type, color: 'bg-zinc-200 text-zinc-600' };
                    return (
                      <button
                        key={`${s.type}-${s.id}`}
                        type="button"
                        onClick={() => goToSuggestion(s)}
                        onMouseEnter={() => setSelectedIndex(i)}
                        className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-left ${
                          i === selectedIndex
                            ? 'bg-indigo-50 dark:bg-indigo-500/10'
                            : 'hover:bg-zinc-50 dark:hover:bg-zinc-800'
                        }`}
                      >
                        <span className="text-sm text-zinc-800 dark:text-zinc-200 truncate">{s.title || s.name}</span>
                        <span className={`text-[10px] px-2 py-0.5 rounded-full ${type.color} ml-2 flex-shrink-0`}>{type.label}</span>
                      </button>
                    );
                  })}
                </div>
                <button
                  type="button"
                  onClick={goToSearch}
                  className="w-full flex items-center justify-center gap-2 mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                >
                  <Search className="w-4 h-4" />
                  {t('search.view_all', 'View all results')}
                  <ArrowRight className="w-3 h-3" />
                </button>
              </div>
            ) : isLoading ? (
              /* Loading */
              <div className="flex items-center gap-2 py-4">
                <div className="w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                <span className="text-sm text-zinc-500">{t('search.searching', 'Searching...')}</span>
              </div>
            ) : query.trim().length >= 2 ? (
              /* No results */
              <div className="py-4 text-center">
                <p className="text-sm text-zinc-500 mb-2">{t('search.no_suggestions', 'No quick matches')}</p>
                <button
                  type="button"
                  onClick={goToSearch}
                  className="inline-flex items-center gap-2 text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                >
                  <Search className="w-4 h-4" />
                  {t('search.search_for', 'Search for')} "{query.trim()}"
                </button>
              </div>
            ) : (
              /* Default state: recent + quick links */
              <div>
                {recentSearches.length > 0 && (
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-zinc-500">{t('search.recent', 'Recent')}</p>
                      <button
                        type="button"
                        onClick={clearRecent}
                        className="text-[10px] text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                      >
                        {t('search.clear', 'Clear')}
                      </button>
                    </div>
                    <div className="space-y-1">
                      {recentSearches.map(q => (
                        <button
                          key={q}
                          type="button"
                          onClick={() => {
                            saveRecent(q);
                            navigate(tenantPath(`/search?q=${encodeURIComponent(q)}`));
                            handleClose();
                          }}
                          className="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        >
                          <Clock className="w-3.5 h-3.5 text-zinc-400" />
                          <span className="text-sm text-zinc-600 dark:text-zinc-400">{q}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                <p className="text-xs text-zinc-500 mb-2">{t('search.quick_links', 'Quick Links')}</p>
                <div className="flex flex-wrap gap-2 mb-4">
                  {[
                    { label: t('nav.listings', 'Listings'), path: tenantPath('/listings') },
                    { label: t('nav.members', 'Members'), path: tenantPath('/members') },
                    { label: t('nav.events', 'Events'), path: tenantPath('/events') },
                    { label: t('support.help_center', 'Help'), path: tenantPath('/help') },
                  ].map(link => (
                    <button
                      key={link.path}
                      type="button"
                      onClick={() => { navigate(link.path); handleClose(); }}
                      className="px-3 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700"
                    >
                      {link.label}
                    </button>
                  ))}
                </div>

                <p className="text-[11px] text-zinc-400 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                  {t('search.actions_hint', 'Type > for quick actions')}
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
