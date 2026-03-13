// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TrendingHashtags - Sidebar widget showing trending hashtags
 *
 * Fetches trending hashtags from API and displays as clickable chips.
 * API: GET /api/v2/feed/hashtags/trending
 */

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Spinner } from '@heroui/react';
import { TrendingUp } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface TrendingHashtag {
  tag: string;
  post_count: number;
  trend_direction?: 'up' | 'down' | 'stable';
}

export function TrendingHashtags({ limit = 10 }: { limit?: number }) {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('feed');
  const [hashtags, setHashtags] = useState<TrendingHashtag[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadTrending = async () => {
      try {
        setIsLoading(true);
        const response = await api.get<TrendingHashtag[]>(`/v2/feed/hashtags/trending?limit=${limit}`);
        if (response.success && response.data) {
          setHashtags(response.data);
        }
      } catch (err) {
        logError('Failed to load trending hashtags', err);
      } finally {
        setIsLoading(false);
      }
    };
    loadTrending();
  }, [limit]);

  if (isLoading) {
    return (
      <GlassCard className="p-4">
        <div className="flex items-center gap-2 mb-3">
          <TrendingUp className="w-4 h-4 text-indigo-500" aria-hidden="true" />
          <h3 className="font-semibold text-theme-primary text-sm">{t('trending.title')}</h3>
        </div>
        <div className="flex justify-center py-3">
          <Spinner size="sm" />
        </div>
      </GlassCard>
    );
  }

  if (hashtags.length === 0) return null;

  return (
    <GlassCard className="p-4">
      <div className="flex items-center gap-2 mb-3">
        <TrendingUp className="w-4 h-4 text-indigo-500" aria-hidden="true" />
        <h3 className="font-semibold text-theme-primary text-sm">{t('trending.title')}</h3>
      </div>

      <div className="space-y-2">
        {hashtags.map((hashtag, idx) => (
          <Link
            key={hashtag.tag}
            to={tenantPath(`/feed/hashtag/${hashtag.tag}`)}
            className="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-theme-hover transition-colors group"
          >
            <div className="flex items-center gap-2 min-w-0">
              <span className="text-xs font-bold text-theme-subtle w-4">{idx + 1}</span>
              <div className="min-w-0">
                <span className="text-sm font-medium text-theme-primary group-hover:text-indigo-500 transition-colors">
                  #{hashtag.tag}
                </span>
                <p className="text-xs text-theme-subtle">
                  {hashtag.post_count === 1 ? t('trending.post_count', { count: hashtag.post_count }) : t('trending.post_count_plural', { count: hashtag.post_count })}
                </p>
              </div>
            </div>
            {hashtag.trend_direction === 'up' && (
              <TrendingUp className="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" aria-hidden="true" />
            )}
          </Link>
        ))}
      </div>

      <Link
        to={tenantPath('/feed/hashtags')}
        className="block text-center text-xs text-indigo-500 hover:text-indigo-600 mt-3 pt-2 border-t border-theme-default"
      >
        {t('trending.view_all')}
      </Link>
    </GlassCard>
  );
}

export default TrendingHashtags;
