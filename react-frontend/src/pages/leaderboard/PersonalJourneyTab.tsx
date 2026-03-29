// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PersonalJourneyTab — shows the user's own growth over time.
 *
 * Replaces competitive rankings with personal progress tracking:
 * monthly activity timeline, badge progression, skills growth, milestones.
 */

import { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Skeleton } from '@heroui/react';
import {
  Award, Calendar, Target, TrendingUp, Milestone,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface MonthlyActivity {
  month: string;
  exchanges: number;
  hours_earned: number;
  connections: number;
}

interface BadgeEntry {
  badge_key: string;
  name: string;
  icon: string;
  earned_at: string | null;
}

interface PersonalMilestone {
  type: string;
  label: string;
  date: string | null;
}

interface PersonalSummary {
  total_hours_earned: number;
  total_hours_given: number;
  total_exchanges: number;
  total_badges: number;
  total_connections: number;
  member_since: string | null;
}

interface JourneyData {
  monthly_activity: MonthlyActivity[];
  badge_progression: BadgeEntry[];
  skills_growth: Array<{ month: string; categories: number }>;
  milestones: PersonalMilestone[];
  summary: PersonalSummary;
}

export default function PersonalJourneyTab() {
  const { t } = useTranslation('gamification');
  const [data, setData] = useState<JourneyData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    abortRef.current = controller;

    const load = async () => {
      try {
        setIsLoading(true);
        const res = await api.get<JourneyData>('/v2/gamification/personal-journey', {
          signal: controller.signal,
          timeout: 60000,
        });
        if (controller.signal.aborted) return;
        if (res.success && res.data) {
          setData(res.data);
        } else {
          setError(res.error || 'Failed to load journey data');
        }
      } catch (err: unknown) {
        if (err instanceof Error && err.name !== 'AbortError') {
          logError('PersonalJourneyTab', err);
          setError('Failed to load journey data');
        }
      } finally {
        setIsLoading(false);
      }
    };

    load();
    return () => controller.abort();
  }, []);

  if (isLoading) {
    return (
      <div className="space-y-4">
        {[...Array(3)].map((_, i) => (
          <GlassCard key={i} className="p-4">
            <Skeleton className="h-4 w-32 mb-3 rounded" />
            <Skeleton className="h-20 w-full rounded" />
          </GlassCard>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <GlassCard className="p-6 text-center">
        <p className="text-danger-500">{error}</p>
      </GlassCard>
    );
  }

  if (!data) {
    return null;
  }

  const { summary, monthly_activity, badge_progression, milestones } = data;

  return (
    <div className="space-y-6">
      {/* Summary cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        <SummaryCard
          label={t('journey.hours_earned', 'Hours Earned')}
          value={summary.total_hours_earned}
          icon={<TrendingUp className="w-4 h-4 text-emerald-500" />}
        />
        <SummaryCard
          label={t('journey.hours_given', 'Hours Given')}
          value={summary.total_hours_given}
          icon={<TrendingUp className="w-4 h-4 text-blue-500" />}
        />
        <SummaryCard
          label={t('journey.exchanges', 'Exchanges')}
          value={summary.total_exchanges}
          icon={<Target className="w-4 h-4 text-purple-500" />}
        />
        <SummaryCard
          label={t('journey.badges', 'Badges')}
          value={summary.total_badges}
          icon={<Award className="w-4 h-4 text-amber-500" />}
        />
        <SummaryCard
          label={t('journey.connections', 'Connections')}
          value={summary.total_connections}
          icon={<Target className="w-4 h-4 text-pink-500" />}
        />
        {summary.member_since && (
          <SummaryCard
            label={t('journey.member_since', 'Member Since')}
            value={summary.member_since}
            icon={<Calendar className="w-4 h-4 text-default-400" />}
            isText
          />
        )}
      </div>

      {/* Monthly activity chart (simple bar representation) */}
      {monthly_activity.length > 0 && (
        <GlassCard className="p-6">
          <h3 className="text-lg font-semibold mb-4">
            {t('journey.monthly_activity', 'Monthly Activity')}
          </h3>
          <div className="flex items-end gap-1 h-32">
            {monthly_activity.map((month, i) => {
              const maxExchanges = Math.max(
                ...monthly_activity.map((m) => m.exchanges),
                1
              );
              const height = Math.max(
                (month.exchanges / maxExchanges) * 100,
                4
              );

              return (
                <motion.div
                  key={month.month}
                  className="flex-1 flex flex-col items-center gap-1"
                  initial={{ scaleY: 0 }}
                  animate={{ scaleY: 1 }}
                  transition={{ delay: i * 0.05, duration: 0.3 }}
                  style={{ transformOrigin: 'bottom' }}
                >
                  <span className="text-[10px] text-default-400 font-medium">
                    {month.exchanges || ''}
                  </span>
                  <div
                    className="w-full rounded-t bg-gradient-to-t from-primary-500 to-primary-300 min-h-1"
                    style={{ height: `${height}%` }}
                    title={`${month.month}: ${month.exchanges} exchanges, ${month.hours_earned}h earned`}
                  />
                  <span className="text-[9px] text-default-400 truncate w-full text-center">
                    {month.month.split(' ')[0]?.slice(0, 3)}
                  </span>
                </motion.div>
              );
            })}
          </div>
        </GlassCard>
      )}

      {/* Badge progression timeline */}
      {badge_progression.length > 0 && (
        <GlassCard className="p-6">
          <h3 className="text-lg font-semibold mb-4">
            {t('journey.badge_timeline', 'Badge Timeline')}
          </h3>
          <div className="space-y-3">
            {badge_progression.slice(-10).map((badge, i) => (
              <motion.div
                key={badge.badge_key}
                className="flex items-center gap-3 p-2 rounded-lg hover:bg-default-100/50 transition-colors"
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: i * 0.05 }}
              >
                <span className="text-xl">{badge.icon}</span>
                <div className="flex-1">
                  <p className="text-sm font-medium">{badge.name}</p>
                  {badge.earned_at && (
                    <p className="text-xs text-default-400">{badge.earned_at}</p>
                  )}
                </div>
              </motion.div>
            ))}
          </div>
        </GlassCard>
      )}

      {/* Milestones */}
      {milestones.length > 0 && (
        <GlassCard className="p-6">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <Milestone className="w-5 h-5 text-amber-500" />
            {t('journey.milestones', 'Milestones')}
          </h3>
          <div className="space-y-2">
            {milestones.map((milestone, i) => (
              <div
                key={`${milestone.type}-${i}`}
                className="flex items-center gap-3 p-2"
              >
                <div className="w-2 h-2 rounded-full bg-primary-500" />
                <div className="flex-1">
                  <p className="text-sm">{milestone.label}</p>
                  {milestone.date && (
                    <p className="text-xs text-default-400">{milestone.date}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </GlassCard>
      )}
    </div>
  );
}

function SummaryCard({
  label,
  value,
  icon,
  isText = false,
}: {
  label: string;
  value: number | string;
  icon: React.ReactNode;
  isText?: boolean;
}) {
  return (
    <GlassCard className="p-4">
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs text-default-500">{label}</span>
      </div>
      <p className={isText ? 'text-sm font-medium' : 'text-xl font-bold'}>
        {typeof value === 'number' ? value.toLocaleString() : value}
      </p>
    </GlassCard>
  );
}
