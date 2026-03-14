// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HashtagsDiscoveryPage - Browse and discover all hashtags
 *
 * Shows trending hashtags with post counts.
 * API: GET /api/v2/feed/hashtags/trending
 * API: GET /api/v2/feed/hashtags/search
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Spinner } from '@heroui/react';
import {
  Hash,
  Search,
  TrendingUp,
  RefreshCw,
  AlertTriangle,
  ArrowLeft,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface HashtagItem {
  tag: string;
  post_count: number;
  trend_direction?: 'up' | 'down' | 'stable';
}

export function HashtagsDiscoveryPage() {
  const { t } = useTranslation('feed');
  usePageTitle(t('hashtags.title'));
  const { tenantPath } = useTenant();

  const [hashtags, setHashtags] = useState<HashtagItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<HashtagItem[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    loadTrending();
  }, []);

  const loadTrending = async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<HashtagItem[]>('/v2/feed/hashtags/trending?limit=50');
      if (response.success && response.data) {
        setHashtags(response.data);
      } else {
        setError(t('hashtags.load_failed'));
      }
    } catch (err) {
      logError('Failed to load trending hashtags', err);
      setError(t('hashtags.load_failed'));
    } finally {
      setIsLoading(false);
    }
  };

  const handleSearch = useCallback((query: string) => {
    setSearchQuery(query);

    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    if (query.length < 2) {
      setSearchResults([]);
      return;
    }

    searchTimeoutRef.current = setTimeout(async () => {
      try {
        setIsSearching(true);
        const response = await api.get<HashtagItem[]>(`/v2/feed/hashtags/search?q=${encodeURIComponent(query)}`);
        if (response.success && response.data) {
          setSearchResults(response.data);
        }
      } catch (err) {
        logError('Failed to search hashtags', err);
      } finally {
        setIsSearching(false);
      }
    }, 300);
  }, []);

  const displayHashtags = searchQuery.length >= 2 ? searchResults : hashtags;

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.03 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, scale: 0.95 },
    visible: { opacity: 1, scale: 1 },
  };

  return (
    <>
    <PageMeta
      title={t("hashtags.title")}
      description={t("hashtags.subtitle")}
    />
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Link to={tenantPath('/feed')}>
          <Button
            isIconOnly
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            aria-label={t('hashtags.back_to_feed')}
          >
            <ArrowLeft className="w-5 h-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
              <Hash className="w-5 h-5 text-white" aria-hidden="true" />
            </div>
            {t('hashtags.title')}
          </h1>
          <p className="text-theme-muted mt-1 text-sm">
            {t('hashtags.subtitle')}
          </p>
        </div>
      </div>

      {/* Search */}
      <GlassCard className="p-4">
        <Input
          placeholder={t('hashtags.search_placeholder')}
          value={searchQuery}
          onChange={(e) => handleSearch(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          aria-label={t('hashtags.search_placeholder')}
          endContent={isSearching ? <Spinner size="sm" /> : undefined}
          classNames={{
            input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
            inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
          }}
        />
      </GlassCard>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">{t('hashtags.unable_to_load')}</h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadTrending}
          >
            {t('hashtags.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Hashtags Grid */}
      {!isLoading && !error && (
        <>
          {displayHashtags.length === 0 ? (
            <EmptyState
              icon={<Hash className="w-12 h-12" aria-hidden="true" />}
              title={t('hashtags.no_hashtags')}
              description={searchQuery ? t('hashtags.no_match', { query: searchQuery }) : t('hashtags.no_trending')}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3"
            >
              {displayHashtags.map((hashtag) => (
                <motion.div key={hashtag.tag} variants={itemVariants}>
                  <Link to={tenantPath(`/feed/hashtag/${hashtag.tag}`)}>
                    <GlassCard hoverable className="p-4 text-center">
                      <div className="flex items-center justify-center gap-1.5 mb-1">
                        {hashtag.trend_direction === 'up' && (
                          <TrendingUp className="w-4 h-4 text-emerald-500" aria-hidden="true" />
                        )}
                        <span className="font-semibold text-theme-primary text-sm">
                          #{hashtag.tag}
                        </span>
                      </div>
                      <p className="text-xs text-theme-subtle">
                        {t('hashtags.post_count', { count: hashtag.post_count })}
                      </p>
                    </GlassCard>
                  </Link>
                </motion.div>
              ))}
            </motion.div>
          )}
        </>
      )}
    </div>
    </>
  );
}

export default HashtagsDiscoveryPage;
