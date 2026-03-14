// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Matches Page (MA1) - Cross-Module Matches
 * Unified matches feed showing matches from all modules:
 * listings, jobs, volunteering, groups.
 * Each card shows source type, match score, title, reasons.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Spinner,
  Chip,
  Tabs,
  Tab,
  Avatar,
  Progress,
} from '@heroui/react';
import {
  Sparkles,
  ListChecks,
  Briefcase,
  Heart,
  Users,
  RefreshCw,
  TrendingUp,
  Target,
  ArrowRight,
  Filter,
  Zap,
  X,
} from 'lucide-react';
import { GlassCard, AlgorithmLabel } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Match {
  id: number;
  source_type: 'listing' | 'job' | 'volunteering' | 'group';
  source_id: number;
  match_score: number;
  title: string;
  description?: string;
  reasons: string[];
  matched_user?: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  matched_at: string;
  status?: 'pending' | 'accepted' | 'declined' | 'expired';
  metadata?: {
    category?: string;
    location?: string;
    skills?: string[];
  };
}

type SourceFilter = 'all' | 'listing' | 'job' | 'volunteering' | 'group';

const SOURCE_CONFIG: Record<string, { icon: typeof ListChecks; labelKey: string; color: string; path: string }> = {
  listing: { icon: ListChecks, labelKey: 'source_listing', color: 'text-blue-400 bg-blue-400/10', path: '/listings' },
  job: { icon: Briefcase, labelKey: 'source_job', color: 'text-amber-400 bg-amber-400/10', path: '/jobs' },
  volunteering: { icon: Heart, labelKey: 'source_volunteering', color: 'text-rose-400 bg-rose-400/10', path: '/volunteering' },
  group: { icon: Users, labelKey: 'source_group', color: 'text-emerald-400 bg-emerald-400/10', path: '/groups' },
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MatchesPage() {
  const { t } = useTranslation('matches');
  usePageTitle(t('page_title'));
  useAuth(); // ensure authenticated
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [matches, setMatches] = useState<Match[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<SourceFilter>('all');
  const [refreshing, setRefreshing] = useState(false);
  const [dismissing, setDismissing] = useState<Set<number>>(new Set());

  // ─── Load matches ───
  const loadMatches = useCallback(async (showRefresh = false) => {
    if (showRefresh) setRefreshing(true);
    else setLoading(true);

    try {
      const res = await api.get('/v2/matches/all');
      if (res.success) {
        const payload = res.data;
        const items = Array.isArray(payload)
          ? payload
          : (payload as { matches?: Match[] })?.matches ?? [];
        setMatches(items);
      }
    } catch (err) {
      logError('MatchesPage.load', err);
      toast.error(t('load_failed'));
    }

    setLoading(false);
    setRefreshing(false);
  }, [toast, t]);

  useEffect(() => { loadMatches(); }, [loadMatches]);

  // ─── Dismiss match ───
  const dismissMatch = useCallback(async (match: Match) => {

    if (match.source_type !== 'listing') return; // only listings support dismiss for now

    const listingId = match.source_id;
    setDismissing((prev) => new Set(prev).add(listingId));
    try {
      await api.post(`/v2/matches/${listingId}/dismiss`, { reason: 'not_relevant' });
      setMatches((prev) => prev.filter((m) => !(m.source_type === 'listing' && m.source_id === listingId)));
      toast.success(t('match_hidden'));
    } catch (err) {
      logError('MatchesPage.dismiss', err);
    }
    setDismissing((prev) => { const s = new Set(prev); s.delete(listingId); return s; });
  }, [toast, t]);

  // ─── Filtered matches ───
  const filteredMatches = filter === 'all'
    ? matches
    : matches.filter((m) => m.source_type === filter);

  // ─── Stats ───
  const totalMatches = matches.length;
  const avgScore = totalMatches > 0
    ? Math.round(matches.reduce((sum, m) => sum + m.match_score, 0) / totalMatches)
    : 0;
  const sourceTypeCounts = matches.reduce<Record<string, number>>((acc, m) => {
    acc[m.source_type] = (acc[m.source_type] || 0) + 1;
    return acc;
  }, {});

  // ─── Render ───
  return (
    <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <Breadcrumbs items={[{ label: t('breadcrumb_dashboard'), href: tenantPath('/dashboard') }, { label: t('breadcrumb_matches') }]} />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <div className="p-2 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20">
              <Sparkles className="w-6 h-6 text-indigo-400" />
            </div>
            {t('heading')}
          </h1>
          <div className="flex items-center gap-2 mt-1">
            <p className="text-theme-subtle">{t('subtitle')}</p>
            <AlgorithmLabel area="matching" />
          </div>
        </div>
        <Button
          variant="flat"
          startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
          onPress={() => loadMatches(true)}
          isLoading={refreshing}
          size="sm"
        >
          {t('refresh')}
        </Button>
      </div>

      {/* Stats row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <GlassCard className="p-4 text-center">
          <Target className="w-5 h-5 text-indigo-400 mx-auto mb-1" />
          <p className="text-2xl font-bold text-theme-primary">{totalMatches}</p>
          <p className="text-xs text-theme-subtle">{t('stats_total_matches')}</p>
        </GlassCard>
        <GlassCard className="p-4 text-center">
          <TrendingUp className="w-5 h-5 text-emerald-400 mx-auto mb-1" />
          <p className="text-2xl font-bold text-theme-primary">{avgScore}%</p>
          <p className="text-xs text-theme-subtle">{t('stats_avg_score')}</p>
        </GlassCard>
        <GlassCard className="p-4 text-center">
          <Zap className="w-5 h-5 text-amber-400 mx-auto mb-1" />
          <p className="text-2xl font-bold text-theme-primary">
            {matches.filter((m) => m.match_score >= 80).length}
          </p>
          <p className="text-xs text-theme-subtle">{t('stats_hot_matches')}</p>
        </GlassCard>
        <GlassCard className="p-4 text-center">
          <Filter className="w-5 h-5 text-purple-400 mx-auto mb-1" />
          <p className="text-2xl font-bold text-theme-primary">
            {Object.keys(sourceTypeCounts).length}
          </p>
          <p className="text-xs text-theme-subtle">{t('stats_source_types')}</p>
        </GlassCard>
      </div>

      {/* Source filter tabs */}
      <Tabs
        selectedKey={filter}
        onSelectionChange={(key) => setFilter(key as SourceFilter)}
        classNames={{
          tabList: 'bg-theme-elevated p-1 rounded-lg',
          cursor: 'bg-theme-hover',
          tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
        }}
      >
        <Tab
          key="all"
          title={
            <span className="flex items-center gap-2">
              <Sparkles className="w-4 h-4" />
              {t('filter_all')} ({totalMatches})
            </span>
          }
        />
        {Object.entries(SOURCE_CONFIG).map(([key, config]) => {
          const Icon = config.icon;
          const count = sourceTypeCounts[key] || 0;
          if (count === 0) return null;
          return (
            <Tab
              key={key}
              title={
                <span className="flex items-center gap-2">
                  <Icon className="w-4 h-4" />
                  {t(config.labelKey)} ({count})
                </span>
              }
            />
          );
        })}
      </Tabs>

      {/* Matches list */}
      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : filteredMatches.length === 0 ? (
        <EmptyState
          icon={<Sparkles className="w-12 h-12" />}
          title={filter === 'all' ? t('empty_title_all') : t('empty_title_filtered', { source: filter })}
          description={t('empty_description')}
          action={
            <Link to={tenantPath('/listings')}>
              <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                {t('browse_listings')}
              </Button>
            </Link>
          }
        />
      ) : (
        <AnimatePresence mode="popLayout">
          <div className="space-y-3">
            {filteredMatches.map((match, index) => {
              const config = SOURCE_CONFIG[match.source_type] || SOURCE_CONFIG.listing;
              const Icon = config.icon;
              const detailPath = `${config.path}/${match.source_id}`;

              const isDismissing = match.source_type === 'listing' && dismissing.has(match.source_id);

              return (
                <motion.div
                  key={match.id}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -20 }}
                  transition={{ delay: index * 0.05 }}
                >
                  <GlassCard className="p-4 hover:border-[var(--color-primary)]/20 transition-all group">
                    <div className="flex items-start gap-4">
                      {/* Score badge */}
                      <div className="flex-shrink-0 relative">
                        <div className={`w-14 h-14 rounded-xl flex items-center justify-center ${config.color}`}>
                          <Icon className="w-6 h-6" />
                        </div>
                        <div className={`absolute -top-1 -right-1 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold ${
                          match.match_score >= 80
                            ? 'bg-emerald-500 text-white'
                            : match.match_score >= 60
                            ? 'bg-amber-500 text-white'
                            : 'bg-default-300 text-default-700'
                        }`}>
                          {match.match_score}
                        </div>
                      </div>

                      {/* Content */}
                      <Link to={tenantPath(detailPath)} className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <h3 className="font-semibold text-theme-primary truncate group-hover:text-primary transition-colors">
                            {match.title}
                          </h3>
                          <Chip size="sm" variant="flat" className={config.color}>
                            {t(config.labelKey)}
                          </Chip>
                        </div>

                        {match.description && (
                          <p className="text-sm text-theme-secondary line-clamp-2 mb-2">
                            {match.description}
                          </p>
                        )}

                        {/* Match score bar */}
                        <div className="flex items-center gap-2 mb-2">
                          <Progress
                            value={match.match_score}
                            size="sm"
                            color={match.match_score >= 80 ? 'success' : match.match_score >= 60 ? 'warning' : 'default'}
                            className="max-w-[120px]"
                            aria-label={t('score_label', { score: match.match_score })}
                          />
                          <span className="text-xs text-theme-subtle">{t('score_percent', { score: match.match_score })}</span>
                        </div>

                        {/* Match reasons */}
                        {match.reasons.length > 0 && (
                          <div className="flex flex-wrap gap-1.5">
                            {match.reasons.slice(0, 3).map((reason, i) => (
                              <Chip key={i} size="sm" variant="dot" color="primary" className="text-xs">
                                {reason}
                              </Chip>
                            ))}
                            {match.reasons.length > 3 && (
                              <Chip size="sm" variant="flat" className="text-xs bg-theme-hover">
                                {t('reasons_more', { count: match.reasons.length - 3 })}
                              </Chip>
                            )}
                          </div>
                        )}

                        {/* Matched user & time */}
                        <div className="flex items-center gap-3 mt-2 text-xs text-theme-subtle">
                          {match.matched_user && (
                            <div className="flex items-center gap-1.5">
                              <Avatar
                                src={resolveAvatarUrl(match.matched_user.avatar_url)}
                                name={match.matched_user.name}
                                size="sm"
                                className="w-4 h-4"
                              />
                              <span>{match.matched_user.name}</span>
                            </div>
                          )}
                          <span>{formatRelativeTime(match.matched_at)}</span>
                        </div>
                      </Link>

                      {/* Actions */}
                      <div className="flex flex-col items-center gap-2 flex-shrink-0">
                        <Link to={tenantPath(detailPath)} aria-label={t('view_details')}>
                          <ArrowRight className="w-5 h-5 text-theme-subtle group-hover:text-primary transition-colors mt-2" />
                        </Link>
                        {match.source_type === 'listing' && (
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            aria-label={t('not_interested')}
                            isLoading={isDismissing}
                            onPress={() => dismissMatch(match)}
                            className="text-theme-subtle hover:text-danger opacity-0 group-hover:opacity-100 transition-opacity"
                          >
                            <X className="w-4 h-4" />
                          </Button>
                        )}
                      </div>
                    </div>
                  </GlassCard>
                </motion.div>
              );
            })}
          </div>
        </AnimatePresence>
      )}
    </div>
  );
}

export default MatchesPage;
