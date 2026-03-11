// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SavedSearches - Component for managing saved/bookmarked searches
 *
 * Shows a list of saved searches with ability to re-run or delete them.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Input, Tooltip } from '@heroui/react';
import { Bookmark, Trash2, Play } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast, useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { SavedSearch } from '@/types/api';

interface SavedSearchesProps {
  /** Called when a saved search is run — parent should execute the search */
  onRunSearch?: (queryParams: Record<string, string>) => void;
  /** Current search query (for the "save current" functionality) */
  currentQuery?: string;
  /** Current filters */
  currentFilters?: Record<string, string>;
}

export function SavedSearches({ onRunSearch, currentQuery, currentFilters }: SavedSearchesProps) {
  const { isAuthenticated } = useAuth();
  const toast = useToast();
  const { t } = useTranslation('search_page');
  const [savedSearches, setSavedSearches] = useState<SavedSearch[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showSaveForm, setShowSaveForm] = useState(false);
  const [saveName, setSaveName] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  const loadSavedSearches = useCallback(async () => {
    if (!isAuthenticated) {
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      const response = await api.get<SavedSearch[]>('/v2/search/saved');
      if (response.success && response.data) {
        setSavedSearches(response.data);
      }
    } catch (error) {
      logError('Failed to load saved searches', error);
    } finally {
      setIsLoading(false);
    }
  }, [isAuthenticated]);

  useEffect(() => {
    loadSavedSearches();
  }, [loadSavedSearches]);

  const handleSave = async () => {
    if (!saveName.trim() || !currentQuery) return;

    setIsSaving(true);
    try {
      const response = await api.post<SavedSearch>('/v2/search/saved', {
        name: saveName.trim(),
        query_params: {
          q: currentQuery,
          ...currentFilters,
        },
        notify_on_new: false,
      });

      if (response.success && response.data) {
        setSavedSearches((prev) => [response.data!, ...prev]);
        setSaveName('');
        setShowSaveForm(false);
        toast.success(t('toast_search_saved'));
      }
    } catch (error) {
      logError('Failed to save search', error);
      toast.error(t('toast_search_save_failed'));
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await api.delete(`/v2/search/saved/${id}`);
      setSavedSearches((prev) => prev.filter((s) => s.id !== id));
      toast.success(t('toast_search_deleted'));
    } catch (error) {
      logError('Failed to delete saved search', error);
      toast.error(t('toast_search_delete_failed'));
    }
  };

  const handleRun = (search: SavedSearch) => {
    if (onRunSearch) {
      onRunSearch(search.query_params);
    }
  };

  if (!isAuthenticated) {
    return null;
  }

  return (
    <div className="space-y-3">
      {/* Save current search button */}
      {currentQuery && (
        <div>
          {showSaveForm ? (
            <div className="flex gap-2">
              <Input
                size="sm"
                placeholder={t('save_search_name')}
                aria-label={t('save_search_name')}
                value={saveName}
                onChange={(e) => setSaveName(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') handleSave();
                }}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                }}
              />
              <Button
                size="sm"
                color="primary"
                isLoading={isSaving}
                onPress={handleSave}
                isDisabled={!saveName.trim()}
              >
                {t('save')}
              </Button>
              <Button
                size="sm"
                variant="light"
                onPress={() => {
                  setShowSaveForm(false);
                  setSaveName('');
                }}
              >
                {t('cancel')}
              </Button>
            </div>
          ) : (
            <Button
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<Bookmark className="w-4 h-4" />}
              onPress={() => setShowSaveForm(true)}
            >
              {t('save_this_search')}
            </Button>
          )}
        </div>
      )}

      {/* Saved searches list */}
      {isLoading ? (
        <div className="flex justify-center py-4">
          <Spinner size="sm" />
        </div>
      ) : savedSearches.length > 0 ? (
        <div className="space-y-2">
          <h4 className="text-sm font-medium text-theme-muted flex items-center gap-2">
            <Bookmark className="w-4 h-4" />
            {t('saved_searches', { count: savedSearches.length })}
          </h4>
          {savedSearches.map((search) => (
            <GlassCard key={search.id} className="p-3 flex items-center gap-3">
              <div className="flex-1 min-w-0">
                <div className="font-medium text-sm text-theme-primary truncate">
                  {search.name}
                </div>
                <div className="text-xs text-theme-subtle truncate">
                  {search.query_params.q || t('no_query')}
                  {search.last_result_count !== null && (
                    <span className="ml-1">
                      ({search.last_result_count} results)
                    </span>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                <Tooltip content={t('run_search')}>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    onPress={() => handleRun(search)}
                    aria-label={t('run_search')}
                  >
                    <Play className="w-4 h-4 text-emerald-500" />
                  </Button>
                </Tooltip>
                <Tooltip content={t('delete')}>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    onPress={() => handleDelete(search.id)}
                    aria-label={t('delete_saved_search')}
                  >
                    <Trash2 className="w-4 h-4 text-rose-400" />
                  </Button>
                </Tooltip>
              </div>
            </GlassCard>
          ))}
        </div>
      ) : null}
    </div>
  );
}

export default SavedSearches;
