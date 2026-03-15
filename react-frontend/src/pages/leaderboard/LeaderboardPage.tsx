// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Leaderboard Page - Community rankings with type selector and seasons
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Select, SelectItem, Chip, Progress, Skeleton } from '@heroui/react';
import {
  Trophy,
  Medal,
  Crown,
  TrendingUp,
  RefreshCw,
  AlertTriangle,
  Star,
  Clock,
  Coins,
  Zap,
  Calendar,
  ChevronRight,
  Flame,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type LeaderboardPeriod = 'all' | 'season' | 'month' | 'week';
type LeaderboardType = 'xp' | 'volunteer_hours' | 'credits_earned' | 'nexus_score';

interface LeaderboardEntry {
  position: number;
  user: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  xp: number;
  score: number;
  level: number;
  is_current_user: boolean;
}

interface LeaderboardMeta {
  period: string;
  type: string;
  your_position: number | null;
  total_entries: number;
}

interface Season {
  id: number;
  name: string;
  description: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
  rewards: {
    tier: string;
    description: string;
    min_rank: number;
  }[];
}

interface CurrentSeason extends Season {
  user_data: {
    rank: number | null;
    xp_earned: number;
    tier: string | null;
    progress_percentage: number;
  } | null;
  days_remaining: number;
  total_participants: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Season Card Component
// ─────────────────────────────────────────────────────────────────────────────

function SeasonCard() {
  const { t } = useTranslation('gamification');
  const toast = useToast();
  const [season, setSeason] = useState<CurrentSeason | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [seasonError, setSeasonError] = useState(false);
  const [showAllSeasons, setShowAllSeasons] = useState(false);
  const [allSeasons, setAllSeasons] = useState<Season[]>([]);
  const [loadingAllSeasons, setLoadingAllSeasons] = useState(false);

  const loadCurrentSeason = useCallback(async () => {
    try {
      setIsLoading(true);
      setSeasonError(false);
      const res = await api.get<CurrentSeason>('/v2/gamification/seasons/current');
      if (res.success && res.data) {
        setSeason(res.data as unknown as CurrentSeason);
      } else if (!res.success) {
        logError('Failed to load current season', res.error);
        setSeasonError(true);
      }
    } catch (err) {
      logError('Failed to load current season', err);
      setSeasonError(true);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCurrentSeason();
  }, [loadCurrentSeason]);

  const loadAllSeasons = async () => {
    if (allSeasons.length > 0) {
      setShowAllSeasons(!showAllSeasons);
      return;
    }
    try {
      setLoadingAllSeasons(true);
      const res = await api.get<Season[]>('/v2/gamification/seasons');
      if (res.success && res.data) {
        setAllSeasons(Array.isArray(res.data) ? res.data : []);
      }
      setShowAllSeasons(true);
    } catch (err) {
      logError('Failed to load all seasons', err);
      toast.error(t('leaderboard.season.load_failed'), t('leaderboard.season.load_failed_desc'));
    } finally {
      setLoadingAllSeasons(false);
    }
  };

  if (isLoading) {
    return (
      <GlassCard className="p-5" aria-label="Loading season" aria-busy="true">
        <div className="space-y-3">
          <Skeleton className="rounded-lg">
            <div className="h-5 rounded-lg bg-default-300 w-1/3" />
          </Skeleton>
          <Skeleton className="rounded-lg">
            <div className="h-3 rounded-lg bg-default-200 w-2/3" />
          </Skeleton>
          <Skeleton className="rounded-lg">
            <div className="h-2 rounded-lg bg-default-200 w-full" />
          </Skeleton>
          <Skeleton className="rounded-lg">
            <div className="h-3 rounded-lg bg-default-200 w-1/4" />
          </Skeleton>
        </div>
      </GlassCard>
    );
  }

  if (seasonError) {
    return (
      <GlassCard className="p-5">
        <div className="flex items-center gap-3 text-amber-500">
          <AlertTriangle className="w-5 h-5 flex-shrink-0" aria-hidden="true" />
          <div className="flex-1">
            <p className="text-sm font-medium text-theme-primary">{t('leaderboard.season.unavailable', 'Season data unavailable')}</p>
            <p className="text-xs text-theme-muted mt-0.5">{t('leaderboard.season.unavailable_desc', 'Could not load season information. Rankings below are still available.')}</p>
          </div>
          <Button size="sm" variant="light" onPress={loadCurrentSeason}>
            <RefreshCw className="w-4 h-4" aria-hidden="true" />
          </Button>
        </div>
      </GlassCard>
    );
  }

  if (!season) return null;

  const tierColors: Record<string, string> = {
    gold: 'text-yellow-400',
    silver: 'text-gray-300',
    bronze: 'text-amber-600',
    unranked: 'text-theme-subtle',
  };

  return (
    <div className="space-y-3">
      <GlassCard className="p-5 border-l-4 border-purple-500">
        <div className="flex items-start justify-between gap-3 mb-3">
          <div>
            <h3 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
              <Flame className="w-5 h-5 text-purple-400" aria-hidden="true" />
              {season.name}
            </h3>
            {season.description && (
              <p className="text-sm text-theme-muted mt-1">{season.description}</p>
            )}
          </div>
          <Chip size="sm" color="secondary" variant="flat" className="flex-shrink-0">
            <Calendar className="w-3 h-3 inline mr-1" aria-hidden="true" />
            {t('leaderboard.season.days_left', { days: season.days_remaining })}
          </Chip>
        </div>

        {/* Season date range */}
        <p className="text-xs text-theme-subtle mb-3">
          {new Date(season.start_date).toLocaleDateString()} &mdash; {new Date(season.end_date).toLocaleDateString()}
          {' '}&middot; {t('leaderboard.season.participants', { count: season.total_participants })}
        </p>

        {/* User's season progress */}
        {season.user_data && (
          <div className="bg-theme-hover/50 rounded-lg p-3 mb-3">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-medium text-theme-primary">{t('leaderboard.season.your_progress')}</span>
              <div className="flex items-center gap-2">
                {season.user_data.tier && (
                  <Chip size="sm" variant="flat" className={tierColors[season.user_data.tier] ?? 'text-theme-subtle'}>
                    <Crown className="w-3 h-3 inline mr-1" aria-hidden="true" />
                    {season.user_data.tier.charAt(0).toUpperCase() + season.user_data.tier.slice(1)}
                  </Chip>
                )}
                {season.user_data.rank && (
                  <span className="text-sm font-bold text-indigo-400">#{season.user_data.rank}</span>
                )}
              </div>
            </div>
            <Progress
              value={season.user_data.progress_percentage ?? 0}
              classNames={{
                indicator: 'bg-gradient-to-r from-purple-500 to-pink-500',
                track: 'bg-theme-hover',
              }}
              size="sm"
              aria-label={t('leaderboard.season.progress_aria')}
            />
            <p className="text-xs text-theme-subtle mt-1">
              {t('leaderboard.season.xp_earned', { xp: (season.user_data.xp_earned ?? 0).toLocaleString() })}
            </p>
          </div>
        )}

        {/* Reward tiers */}
        {season.rewards && season.rewards.length > 0 && (
          <div className="space-y-1">
            <p className="text-xs font-medium text-theme-muted mb-1">{t('leaderboard.season.rewards')}</p>
            <div className="flex flex-wrap gap-2">
              {season.rewards.map((reward) => (
                <Chip
                  key={reward.tier}
                  size="sm"
                  variant="flat"
                  className={tierColors[reward.tier] ?? ''}
                >
                  {reward.tier.charAt(0).toUpperCase() + reward.tier.slice(1)}: {reward.description}
                </Chip>
              ))}
            </div>
          </div>
        )}

        {/* All seasons toggle */}
        <div className="mt-3 pt-3 border-t border-white/5">
          <Button
            variant="light"
            size="sm"
            className="text-purple-400"
            endContent={
              <ChevronRight
                className={`w-4 h-4 transition-transform ${showAllSeasons ? 'rotate-90' : ''}`}
                aria-hidden="true"
              />
            }
            onPress={loadAllSeasons}
            isLoading={loadingAllSeasons}
          >
            {showAllSeasons ? t('leaderboard.season.hide_history') : t('leaderboard.season.all_seasons')}
          </Button>
        </div>
      </GlassCard>

      {/* All Seasons History */}
      {showAllSeasons && allSeasons.length > 0 && (
        <GlassCard className="p-4">
          <h4 className="font-semibold text-theme-primary mb-3 flex items-center gap-2">
            <Calendar className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
            {t('leaderboard.season.history')}
          </h4>
          <div className="space-y-2">
            {allSeasons.map((s) => (
              <div
                key={s.id}
                className={`flex items-center justify-between p-2 rounded-lg ${
                  s.is_active ? 'bg-purple-500/10 border border-purple-500/30' : 'bg-theme-hover/30'
                }`}
              >
                <div className="flex items-center gap-2 min-w-0">
                  {s.is_active ? (
                    <Flame className="w-4 h-4 text-purple-400 flex-shrink-0" aria-hidden="true" />
                  ) : (
                    <Calendar className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                  )}
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-theme-primary truncate">
                      {s.name}
                      {s.is_active && (
                        <Chip size="sm" color="secondary" variant="flat" className="ml-2">{t('leaderboard.season.active')}</Chip>
                      )}
                    </p>
                    <p className="text-xs text-theme-subtle">
                      {new Date(s.start_date).toLocaleDateString()} - {new Date(s.end_date).toLocaleDateString()}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </GlassCard>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Leaderboard Page
// ─────────────────────────────────────────────────────────────────────────────

export function LeaderboardPage() {
  const { t } = useTranslation('gamification');
  usePageTitle(t('leaderboard.page_title'));
  const { tenantPath } = useTenant();
  const [entries, setEntries] = useState<LeaderboardEntry[]>([]);
  const [meta, setMeta] = useState<LeaderboardMeta | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState<LeaderboardPeriod>('all');
  const [type, setType] = useState<LeaderboardType>('xp');

  const loadLeaderboard = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const params = new URLSearchParams();
      params.set('period', period);
      params.set('type', type);
      params.set('limit', '50');

      const response = await api.get<{ data: LeaderboardEntry[]; meta: LeaderboardMeta }>(
        `/v2/gamification/leaderboard?${params}`
      );

      if (response.success && response.data) {
        setEntries(Array.isArray(response.data) ? response.data : []);
        setMeta((response.meta as unknown as LeaderboardMeta) ?? null);
      } else {
        setError(response.code === 'SESSION_EXPIRED'
          ? t('leaderboard.session_expired', 'Your session has expired. Please log in again.')
          : t('leaderboard.load_error', 'Failed to load leaderboard.'));
      }
    } catch (err) {
      logError('Failed to load leaderboard', err);
      setError('Failed to load leaderboard. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [period, type]);

  useEffect(() => {
    loadLeaderboard();
  }, [loadLeaderboard]);

  const getRankIcon = (position: number) => {
    if (position === 1) return <Crown className="w-5 h-5 text-yellow-400" aria-hidden="true" />;
    if (position === 2) return <Medal className="w-5 h-5 text-gray-300" aria-hidden="true" />;
    if (position === 3) return <Medal className="w-5 h-5 text-amber-600" aria-hidden="true" />;
    return <span className="text-theme-subtle font-mono text-sm w-5 text-center">{position}</span>;
  };

  /**
   * Format a score value based on the current leaderboard type
   */
  const formatScore = (entry: LeaderboardEntry) => {
    // Use `score` if available, fallback to `xp` for backwards compat
    const value = entry.score ?? entry.xp;

    switch (type) {
      case 'volunteer_hours':
        return (
          <>
            <span className="font-bold text-theme-primary">{value.toLocaleString()}</span>
            <span className="text-xs text-theme-subtle ml-1">h</span>
          </>
        );
      case 'credits_earned':
        return (
          <>
            <span className="font-bold text-theme-primary">{value.toLocaleString()}</span>
            <span className="text-xs text-theme-subtle ml-1">cr</span>
          </>
        );
      case 'nexus_score':
        return (
          <>
            <span className="font-bold text-indigo-400">{value.toLocaleString()}</span>
            <span className="text-xs text-theme-subtle ml-1">/1000</span>
          </>
        );
      case 'xp':
      default:
        return (
          <>
            <span className="font-bold text-theme-primary">{value.toLocaleString()}</span>
            <span className="text-xs text-theme-subtle ml-1">XP</span>
          </>
        );
    }
  };

  const typeLabels: Record<LeaderboardType, string> = {
    xp: t('leaderboard.type.xp'),
    volunteer_hours: t('leaderboard.type.volunteer_hours'),
    credits_earned: t('leaderboard.type.credits_earned'),
    nexus_score: t('leaderboard.type.nexus_score', 'NexusScore'),
  };

  const typeIcons: Record<LeaderboardType, React.ReactNode> = {
    xp: <Zap className="w-4 h-4 text-theme-subtle" aria-hidden="true" />,
    volunteer_hours: <Clock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />,
    credits_earned: <Coins className="w-4 h-4 text-theme-subtle" aria-hidden="true" />,
    nexus_score: <Trophy className="w-4 h-4 text-indigo-400" aria-hidden="true" />,
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.03 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: { opacity: 1, x: 0 },
  };

  const periodLabels: Record<LeaderboardPeriod, string> = {
    all: t('leaderboard.period.all'),
    season: t('leaderboard.period.season'),
    month: t('leaderboard.period.month'),
    week: t('leaderboard.period.week'),
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Trophy className="w-7 h-7 text-amber-400" aria-hidden="true" />
            {t('leaderboard.title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('leaderboard.subtitle')}</p>
        </div>

        <div className="flex items-center gap-3">
          {/* Type Selector */}
          <Select
            placeholder={t('leaderboard.type_placeholder')}
            aria-label={t('leaderboard.type_aria')}
            selectedKeys={[type]}
            onChange={(e) => setType((e.target.value as LeaderboardType) || 'xp')}
            className="w-full sm:w-48"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={typeIcons[type]}
          >
            <SelectItem key="xp">{t('leaderboard.type.xp')}</SelectItem>
            <SelectItem key="volunteer_hours">{t('leaderboard.type.volunteer_hours')}</SelectItem>
            <SelectItem key="credits_earned">{t('leaderboard.type.credits_earned')}</SelectItem>
            <SelectItem key="nexus_score">{t('leaderboard.type.nexus_score', 'NexusScore')}</SelectItem>
          </Select>

          {/* Period Selector */}
          <Select
            placeholder={t('leaderboard.period_placeholder')}
            aria-label={t('leaderboard.period_aria')}
            selectedKeys={[period]}
            onChange={(e) => setPeriod(e.target.value as LeaderboardPeriod)}
            className="w-full sm:w-44"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<TrendingUp className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            <SelectItem key="all">{t('leaderboard.period.all')}</SelectItem>
            <SelectItem key="season">{t('leaderboard.period.season')}</SelectItem>
            <SelectItem key="month">{t('leaderboard.period.month')}</SelectItem>
            <SelectItem key="week">{t('leaderboard.period.week')}</SelectItem>
          </Select>
        </div>
      </div>

      {/* Season Section */}
      <SeasonCard />

      {/* Your Position Banner — prefer the entry-based position from the visible
          list over the meta fallback, which can disagree if the backend's fallback
          rank query uses different filters. */}
      {(() => {
        const myEntry = entries.find((e) => e.is_current_user);
        const displayPosition = myEntry?.position ?? meta?.your_position;
        const displayTotal = meta?.total_entries ?? entries.length;
        if (!displayPosition) return null;
        return (
          <GlassCard className="p-4 border-l-4 border-indigo-500">
            <div className="flex items-center gap-3">
              <Star className="w-5 h-5 text-indigo-400" aria-hidden="true" />
              <span className="text-theme-primary font-medium">
                {t('leaderboard.your_rank', {
                  position: displayPosition,
                  total: Math.max(displayTotal, displayPosition),
                })}
                {' '}({periodLabels[period]} &middot; {typeLabels[type]})
              </span>
            </div>
          </GlassCard>
        );
      })()}

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('leaderboard.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadLeaderboard}
          >
            {t('leaderboard.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Leaderboard Table */}
      {!error && (
        <>
          {isLoading ? (
            <GlassCard className="divide-y divide-white/5" aria-label="Loading leaderboard" aria-busy="true">
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 p-4">
                  <Skeleton className="rounded-full flex-shrink-0">
                    <div className="w-8 h-8 rounded-full bg-default-300" />
                  </Skeleton>
                  <Skeleton className="rounded-full flex-shrink-0">
                    <div className="w-10 h-10 rounded-full bg-default-300" />
                  </Skeleton>
                  <div className="flex-1 space-y-2">
                    <Skeleton className="rounded-lg">
                      <div className="h-4 rounded-lg bg-default-300 w-1/3" />
                    </Skeleton>
                    <Skeleton className="rounded-lg">
                      <div className="h-3 rounded-lg bg-default-200 w-1/5" />
                    </Skeleton>
                  </div>
                  <Skeleton className="rounded-lg">
                    <div className="h-5 rounded-lg bg-default-300 w-16" />
                  </Skeleton>
                </div>
              ))}
            </GlassCard>
          ) : entries.length === 0 ? (
            <EmptyState
              icon={<Trophy className="w-12 h-12" aria-hidden="true" />}
              title={t('leaderboard.empty_title')}
              description={t('leaderboard.empty_description', { type: typeLabels[type] })}
            />
          ) : (
            <GlassCard className="overflow-hidden">
              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="divide-y divide-white/5"
              >
                {entries.map((entry) => (
                  <motion.div key={entry.position} variants={itemVariants}>
                    <Link
                      to={tenantPath(`/profile/${entry.user.id}`)}
                      className={`flex items-center gap-3 sm:gap-4 p-3 sm:p-4 hover:bg-theme-hover transition-colors ${
                        entry.is_current_user ? 'bg-indigo-500/10 border-l-2 border-indigo-500' : ''
                      } ${entry.position <= 3 ? 'bg-gradient-to-r from-amber-500/5 to-transparent' : ''}`}
                    >
                      {/* Rank */}
                      <div className="w-8 flex justify-center flex-shrink-0">
                        {getRankIcon(entry.position)}
                      </div>

                      {/* Avatar */}
                      <Avatar
                        name={entry.user.name}
                        src={resolveAvatarUrl(entry.user.avatar_url)}
                        size="sm"
                        showFallback
                        className={entry.position <= 3 ? 'ring-2 ring-amber-400/50' : ''}
                      />

                      {/* Name & Level */}
                      <div className="flex-1 min-w-0">
                        <p className={`font-medium truncate ${entry.is_current_user ? 'text-indigo-400' : 'text-theme-primary'}`}>
                          {entry.user.name}
                          {entry.is_current_user && <span className="text-xs ml-2 text-indigo-400">{t('leaderboard.you')}</span>}
                        </p>
                        <p className="text-xs text-theme-subtle">{t('leaderboard.level', { level: entry.level })}</p>
                      </div>

                      {/* Score */}
                      <div className="text-right flex-shrink-0">
                        {formatScore(entry)}
                      </div>
                    </Link>
                  </motion.div>
                ))}
              </motion.div>
            </GlassCard>
          )}
        </>
      )}
    </div>
  );
}

export default LeaderboardPage;
