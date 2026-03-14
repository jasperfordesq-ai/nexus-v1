// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * R4 - Knowledge Base Page
 *
 * Displays a searchable, categorized knowledge base with article listings.
 * Supports nested navigation (parent/children articles).
 *
 * API: GET /api/v2/kb
 *      GET /api/v2/kb/search?q=...
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  BookOpen,
  Search,
  RefreshCw,
  AlertTriangle,
  ChevronRight,
  FileText,
  Clock,
  Eye,
  Folder,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface KBArticle {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  category: string | null;
  parent_id: number | null;
  is_published: boolean;
  view_count: number;
  helpful_count: number;
  not_helpful_count: number;
  created_at: string;
  updated_at: string;
  children_count?: number;
}

/* ───────────────────────── Component ───────────────────────── */

export function KnowledgeBasePage() {
  const { t } = useTranslation('kb');
  usePageTitle(t('title'));
  const { tenantPath } = useTenant();

  const [articles, setArticles] = useState<KBArticle[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const [searchResults, setSearchResults] = useState<KBArticle[] | null>(null);

  // Load top-level articles
  const loadArticles = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<KBArticle[]>('/v2/kb');
      if (response.success && response.data) {
        setArticles(Array.isArray(response.data) ? response.data : []);
      } else {
        setError(t('error.load_articles'));
      }
    } catch (err) {
      logError('Failed to load KB articles', err);
      setError(t('error.load_retry'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadArticles();
  }, [loadArticles]);

  // Search
  const handleSearch = useCallback(async (query: string) => {
    if (!query.trim()) {
      setSearchResults(null);
      return;
    }

    try {
      setIsSearching(true);
      const response = await api.get<KBArticle[]>(`/v2/kb/search?q=${encodeURIComponent(query.trim())}`);
      if (response.success && response.data) {
        setSearchResults(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to search KB', err);
    } finally {
      setIsSearching(false);
    }
  }, []);

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      handleSearch(searchQuery);
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQuery, handleSearch]);

  // Group articles by category
  const groupedArticles = (searchResults || articles).reduce<Record<string, KBArticle[]>>((acc, article) => {
    const cat = article.category || t('general_category');
    if (!acc[cat]) acc[cat] = [];
    acc[cat].push(article);
    return acc;
  }, {});

  const displayArticles = searchResults || articles;

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <BookOpen className="w-7 h-7 text-blue-400" aria-hidden="true" />
          {t('title')}
        </h1>
        <p className="text-theme-muted mt-1">{t('description')}</p>
      </div>

      {/* Search */}
      <div className="max-w-xl">
        <Input
          placeholder={t('search_placeholder')}
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
          aria-label={t('search_placeholder')}
          endContent={isSearching ? <Spinner size="sm" /> : undefined}
          classNames={{
            input: 'bg-transparent text-theme-primary',
            inputWrapper: 'bg-theme-elevated border-theme-default',
          }}
          size="lg"
        />
      </div>

      {/* Search Results Indicator */}
      {searchResults !== null && (
        <div className="flex items-center gap-2">
          <Chip
            size="sm"
            variant="flat"
            className="bg-blue-500/10 text-blue-400"
            onClose={() => {
              setSearchQuery('');
              setSearchResults(null);
            }}
          >
            {t('search_results', { count: searchResults.length, query: searchQuery })}
          </Chip>
        </div>
      )}

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('error.title')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-blue-500 to-indigo-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadArticles}
          >
            {t('try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Content */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-4 bg-theme-hover rounded w-1/4 mb-3" />
                  <div className="space-y-2">
                    <div className="h-10 bg-theme-hover rounded" />
                    <div className="h-10 bg-theme-hover rounded" />
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : displayArticles.length === 0 ? (
            <EmptyState
              icon={<BookOpen className="w-12 h-12" aria-hidden="true" />}
              title={t('empty.title')}
              description={searchQuery ? t('empty.search_description') : t('empty.no_articles')}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-6"
            >
              {Object.entries(groupedArticles).map(([category, catArticles]) => (
                <motion.div key={category} variants={itemVariants}>
                  <GlassCard className="overflow-hidden">
                    {/* Category header */}
                    <div className="px-5 py-3 bg-theme-hover/30 border-b border-theme-default">
                      <h2 className="text-sm font-semibold text-theme-primary flex items-center gap-2">
                        <Folder className="w-4 h-4 text-blue-400" aria-hidden="true" />
                        {category}
                        <Chip size="sm" variant="flat" className="text-[10px] bg-theme-elevated text-theme-subtle">
                          {catArticles.length}
                        </Chip>
                      </h2>
                    </div>

                    {/* Article list */}
                    <div className="divide-y divide-theme-default">
                      {catArticles.map((article) => (
                        <Link
                          key={article.id}
                          to={tenantPath(`/kb/${article.id}`)}
                          className="flex items-center gap-4 px-5 py-3 hover:bg-theme-hover/30 transition-colors group"
                        >
                          <div className="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                            <FileText className="w-4 h-4 text-blue-400" aria-hidden="true" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <h3 className="text-sm font-medium text-theme-primary group-hover:text-blue-400 transition-colors truncate">
                              {article.title}
                            </h3>
                            {article.excerpt && (
                              <p className="text-xs text-theme-muted line-clamp-1 mt-0.5">{article.excerpt}</p>
                            )}
                            <div className="flex items-center gap-3 text-xs text-theme-subtle mt-1">
                              <span className="flex items-center gap-1">
                                <Clock className="w-3 h-3" aria-hidden="true" />
                                {formatRelativeTime(article.updated_at)}
                              </span>
                              {article.view_count > 0 && (
                                <span className="flex items-center gap-1">
                                  <Eye className="w-3 h-3" aria-hidden="true" />
                                  {article.view_count}
                                </span>
                              )}
                              {(article.children_count ?? 0) > 0 && (
                                <span className="flex items-center gap-1">
                                  <Folder className="w-3 h-3" aria-hidden="true" />
                                  {t('sub_articles', { count: article.children_count })}
                                </span>
                              )}
                            </div>
                          </div>
                          <ChevronRight className="w-4 h-4 text-theme-subtle group-hover:text-blue-400 transition-colors flex-shrink-0" aria-hidden="true" />
                        </Link>
                      ))}
                    </div>
                  </GlassCard>
                </motion.div>
              ))}
            </motion.div>
          )}
        </>
      )}
    </div>
  );
}

export default KnowledgeBasePage;
