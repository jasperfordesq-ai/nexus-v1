// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NexusScore Page — personal reputation score breakdown with 6-category detail
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import { Progress, Chip, Button, Spinner } from '@heroui/react';
import {
  Trophy,
  Users,
  Star,
  Clock,
  Medal,
  Heart,
  RefreshCw,
  AlertTriangle,
  TrendingUp,
  Info,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { GlassCard } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useAuth } from '@/contexts/AuthContext';
import { useTenant } from '@/contexts/TenantContext';
import { useToast } from '@/contexts/ToastContext';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { CHART_COLOR_MAP } from '@/lib/chartColors';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ScoreCategory {
  key: string;
  label: string;
  score: number;
  max: number;
  percentage: number;
  details: Record<string, unknown>;
}

interface NexusScoreData {
  total_score: number;
  max_score: number;
  percentage: number;
  percentile: number;
  tier: {
    name: string;
    icon: string;
    color: string;
  };
  breakdown: ScoreCategory[];
  insights: (string | Record<string, string>)[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Tier config
// ─────────────────────────────────────────────────────────────────────────────

const TIERS = [
  { name: 'Novice',        min: 0,   color: 'text-slate-400',   bar: 'bg-slate-400'   },
  { name: 'Beginner',      min: 200, color: 'text-amber-400',   bar: 'bg-amber-400'   },
  { name: 'Developing',    min: 300, color: 'text-emerald-400', bar: 'bg-emerald-400' },
  { name: 'Intermediate',  min: 400, color: 'text-cyan-400',    bar: 'bg-cyan-400'    },
  { name: 'Proficient',    min: 500, color: 'text-violet-400',  bar: 'bg-violet-400'  },
  { name: 'Advanced',      min: 600, color: 'text-indigo-400',  bar: 'bg-indigo-400'  },
  { name: 'Expert',        min: 700, color: 'text-orange-400',  bar: 'bg-orange-400'  },
  { name: 'Elite',         min: 800, color: 'text-pink-400',    bar: 'bg-pink-400'    },
  { name: 'Legendary',     min: 900, color: 'text-yellow-400',  bar: 'bg-yellow-400'  },
] as const;

function getTierConfig(name: string) {
  return TIERS.find(t => t.name === name) ?? TIERS[0];
}

// ─────────────────────────────────────────────────────────────────────────────
// Category icon map
// ─────────────────────────────────────────────────────────────────────────────

const CATEGORY_ICONS: Record<string, React.ReactNode> = {
  engagement: <Users className="w-5 h-5" />,
  quality:    <Star className="w-5 h-5" />,
  volunteer:  <Clock className="w-5 h-5" />,
  activity:   <TrendingUp className="w-5 h-5" />,
  badges:     <Medal className="w-5 h-5" />,
  impact:     <Heart className="w-5 h-5" />,
};

const CATEGORY_COLORS: Record<string, string> = {
  engagement: 'text-blue-500',
  quality:    'text-yellow-500',
  volunteer:  'text-emerald-500',
  activity:   'text-violet-500',
  badges:     'text-orange-500',
  impact:     'text-rose-500',
};

// ─────────────────────────────────────────────────────────────────────────────
// Animation variants
// ─────────────────────────────────────────────────────────────────────────────

const containerVariants = {
  hidden: { opacity: 0 },
  show: { opacity: 1, transition: { staggerChildren: 0.07 } },
};
const itemVariants = {
  hidden: { opacity: 0, y: 16 },
  show:   { opacity: 1, y: 0, transition: { duration: 0.35 } },
};

// ─────────────────────────────────────────────────────────────────────────────
// Tier ladder component
// ─────────────────────────────────────────────────────────────────────────────

function TierLadder({ currentTier, score }: { currentTier: string; score: number }) {
  return (
    <div className="flex items-end justify-between gap-1 px-1">
      {TIERS.map((tier) => {
        const isActive  = tier.name === currentTier;
        const isPassed  = score >= tier.min;
        const nextTier  = TIERS[TIERS.indexOf(tier) + 1];
        const isCurrent = isPassed && (!nextTier || score < nextTier.min);
        return (
          <div key={tier.name} className="flex flex-col items-center gap-1 flex-1">
            <div
              className={[
                'w-full rounded-sm transition-all duration-300',
                isPassed ? tier.bar : 'bg-theme-elevated',
                isCurrent || isActive ? 'ring-2 ring-white/40 ring-offset-1 ring-offset-transparent' : '',
              ].join(' ')}
              style={{ height: `${Math.max(8, (tier.min / 900) * 48 + 8)}px` }}
              title={`${tier.name} (${tier.min}+)`}
            />
            {isActive && (
              <span className={`text-[9px] font-bold leading-none ${tier.color}`}>
                {tier.name}
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main page
// ─────────────────────────────────────────────────────────────────────────────

export default function NexusScorePage() {
  const { t } = useTranslation('gamification');
  const { user }       = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  usePageTitle(t('nexus_score.title', 'NexusScore'));

  const [data, setData]         = useState<NexusScoreData | null>(null);
  const [isLoading, setLoading] = useState(true);
  const [error, setError]       = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    else setRefreshing(true);
    setError(null);

    try {
      const res = await api.get<NexusScoreData>('/v2/gamification/nexus-score');
      if (res.success && res.data) {
        setData(res.data);
      } else {
        setError(res.error ?? t('nexus_score.load_error', 'Could not load your NexusScore. Please try again.'));
      }
    } catch (err) {
      logError('NexusScorePage.load', err);
      setError(t('nexus_score.load_error', 'Could not load your NexusScore. Please try again.'));
      if (silent) toast.error(t('nexus_score.refresh_error', 'Refresh failed'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [t, toast]);

  useEffect(() => { load(); }, [load]);

  // ── Loading ────────────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Spinner size="lg" color="secondary" />
      </div>
    );
  }

  // ── Error ──────────────────────────────────────────────────────────────────

  if (error || !data) {
    return (
      <div className="max-w-lg mx-auto px-4 py-16 text-center">
        <AlertTriangle className="w-12 h-12 text-warning mx-auto mb-4" />
        <p className="text-theme-subtle mb-6">{error}</p>
        <Button color="primary" onPress={() => load()}>
          {t('common.try_again', 'Try again')}
        </Button>
      </div>
    );
  }

  const tierConfig = getTierConfig(data.tier.name);

  // Next tier threshold
  const currentTierIndex = TIERS.findIndex(t => t.name === data.tier.name);
  const nextTier = TIERS[currentTierIndex + 1];
  const pointsToNext = nextTier ? nextTier.min - data.total_score : 0;

  return (
    <motion.div
      className="max-w-3xl mx-auto px-4 py-8 space-y-6"
      variants={containerVariants}
      initial="hidden"
      animate="show"
    >
      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <motion.div variants={itemVariants} className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-2">
            <Trophy className="w-6 h-6 text-indigo-400" aria-hidden="true" />
            {t('nexus_score.title', 'NexusScore')}
          </h1>
          <p className="text-sm text-theme-subtle mt-0.5">
            {t('nexus_score.subtitle', 'Your community reputation score')}
          </p>
        </div>
        <Button
          size="sm"
          variant="flat"
          color="default"
          isLoading={refreshing}
          onPress={() => load(true)}
          startContent={!refreshing && <RefreshCw className="w-3.5 h-3.5" aria-hidden="true" />}
        >
          {t('common.refresh', 'Refresh')}
        </Button>
      </motion.div>

      {/* ── Score hero card ────────────────────────────────────────────────── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <div className="flex flex-col sm:flex-row items-center sm:items-start gap-6">
            {/* Score ring */}
            <div className="relative flex-shrink-0">
              <svg width="120" height="120" viewBox="0 0 120 120" className="-rotate-90">
                <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor"
                  className="text-theme-elevated" strokeWidth="10" />
                <circle cx="60" cy="60" r="52" fill="none"
                  stroke="url(#score-grad)" strokeWidth="10"
                  strokeDasharray={`${2 * Math.PI * 52}`}
                  strokeDashoffset={`${2 * Math.PI * 52 * (1 - data.percentage / 100)}`}
                  strokeLinecap="round" />
                <defs>
                  <linearGradient id="score-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor={CHART_COLOR_MAP.primary} />
                    <stop offset="100%" stopColor={CHART_COLOR_MAP.primaryLight} />
                  </linearGradient>
                </defs>
              </svg>
              <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-2xl font-bold text-theme-primary leading-none">
                  {data.total_score}
                </span>
                <span className="text-xs text-theme-subtle">/ 1000</span>
              </div>
            </div>

            {/* Tier + stats */}
            <div className="flex-1 min-w-0 text-center sm:text-left">
              <div className="flex items-center justify-center sm:justify-start gap-2 mb-2">
                {data.tier.icon && (
                  <span className="text-2xl leading-none">{data.tier.icon}</span>
                )}
                <span className={`text-xl font-bold ${tierConfig.color}`}>
                  {data.tier.name}
                </span>
                <Chip size="sm" variant="flat" color="secondary" className="ml-1">
                  Top {Math.round(100 - data.percentile)}%
                </Chip>
              </div>

              <p className="text-sm text-theme-subtle mb-4">
                {nextTier
                  ? t('nexus_score.points_to_next', `${pointsToNext} points to ${nextTier.name}`, {
                      count: pointsToNext,
                      tier: nextTier.name,
                    })
                  : t('nexus_score.max_tier', 'You have reached the highest tier!')}
              </p>

              {/* Tier ladder */}
              <TierLadder currentTier={data.tier.name} score={data.total_score} />
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* ── Category breakdown ─────────────────────────────────────────────── */}
      <motion.div variants={itemVariants}>
        <h2 className="text-base font-semibold text-theme-primary mb-3 flex items-center gap-2">
          <Info className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
          {t('nexus_score.breakdown_title', 'Score Breakdown')}
        </h2>
        <div className="space-y-3">
          {data.breakdown.map((cat) => (
            <motion.div key={cat.key} variants={itemVariants}>
              <GlassCard className="p-4">
                <div className="flex items-center gap-3 mb-3">
                  <span className={`${CATEGORY_COLORS[cat.key] ?? 'text-theme-subtle'} flex-shrink-0`}>
                    {CATEGORY_ICONS[cat.key]}
                  </span>
                  <span className="flex-1 text-sm font-medium text-theme-primary">
                    {cat.label}
                  </span>
                  <span className="text-sm font-semibold text-theme-primary tabular-nums">
                    {cat.score}
                    <span className="text-theme-subtle font-normal text-xs ml-0.5">
                      /{cat.max}
                    </span>
                  </span>
                </div>
                <Progress
                  value={cat.percentage}
                  size="sm"
                  color="secondary"
                  className="w-full"
                  aria-label={`${cat.label}: ${cat.score} of ${cat.max}`}
                />
                <div className="flex justify-between mt-1">
                  <span className="text-xs text-theme-subtle">{cat.percentage}%</span>
                  {cat.percentage < 100 && (
                    <span className="text-xs text-theme-subtle">
                      {cat.max - cat.score} {t('nexus_score.remaining', 'remaining')}
                    </span>
                  )}
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* ── Insights ───────────────────────────────────────────────────────── */}
      {Array.isArray(data.insights) && data.insights.length > 0 && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-5">
            <h2 className="text-base font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-indigo-400" aria-hidden="true" />
              {t('nexus_score.insights_title', 'How to improve')}
            </h2>
            <ul className="space-y-2">
              {data.insights.map((insight, i) => {
                const text = typeof insight === 'string' ? insight : (insight as Record<string, string>).message ?? (insight as Record<string, string>).title ?? '';
                return (
                  <li key={i} className="flex items-start gap-2 text-sm text-theme-subtle">
                    <span className="text-indigo-400 font-bold mt-0.5">·</span>
                    {text}
                  </li>
                );
              })}
            </ul>
          </GlassCard>
        </motion.div>
      )}

      {/* ── Footer links ───────────────────────────────────────────────────── */}
      <motion.div variants={itemVariants} className="flex flex-wrap gap-3 justify-center pt-2">
        <Button
          as={Link}
          to={tenantPath('/leaderboard?type=nexus_score')}
          variant="flat"
          color="secondary"
          size="sm"
          startContent={<Trophy className="w-4 h-4" aria-hidden="true" />}
        >
          {t('nexus_score.view_leaderboard', 'View Leaderboard')}
        </Button>
        <Button
          as={Link}
          to={tenantPath(`/profile/${user?.id}`)}
          variant="flat"
          color="default"
          size="sm"
        >
          {t('nexus_score.my_profile', 'My Profile')}
        </Button>
      </motion.div>
    </motion.div>
  );
}
