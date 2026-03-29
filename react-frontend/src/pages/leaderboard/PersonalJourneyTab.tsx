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

import { useState, useEffect } from 'react';
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
  badges: number;
  xp_earned: number;
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
  xp: number;
  level: number;
  level_name: string;
  total_badges: number;
  total_listings: number;
  volunteer_hours: number;
  total_connections: number;
  total_reviews: number;
  member_since: string | null;
}

interface JourneyData {
  monthly_activity: MonthlyActivity[];
  badge_progression: BadgeEntry[];
  milestones: PersonalMilestone[];
  summary: PersonalSummary;
}

export default function PersonalJourneyTab() {
  const { t } = useTranslation('gamification');
  const [data, setData] = useState<JourneyData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        setIsLoading(true);
        const res = await api.get<JourneyData>('/v2/gamification/personal-journey');
        if (res.success && res.data) {
          setData(res.data);
        } else {
          setError(res.error || 'Failed to load journey data');
        }
      } catch (err: unknown) {
        logError('PersonalJourneyTab', err);
        setError('Failed to load journey data');
      } finally {
        setIsLoading(false);
      }
    };

    load();
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
          label={summary.level_name || `Level ${summary.level}`}
          value={`${summary.xp.toLocaleString()} XP`}
          icon={<TrendingUp className="w-4 h-4 text-purple-500" />}
          isText
        />
        <SummaryCard
          label={t('journey.badges', 'Badges')}
          value={summary.total_badges}
          icon={<Award className="w-4 h-4 text-amber-500" />}
        />
        <SummaryCard
          label={t('journey.listings', 'Listings')}
          value={summary.total_listings}
          icon={<Target className="w-4 h-4 text-blue-500" />}
        />
        <SummaryCard
          label={t('journey.volunteer_hours', 'Volunteer Hours')}
          value={summary.volunteer_hours}
          icon={<Target className="w-4 h-4 text-emerald-500" />}
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
              const maxXP = Math.max(
                ...monthly_activity.map((m) => m.xp_earned),
                1
              );
              const height = Math.max(
                (month.xp_earned / maxXP) * 100,
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
                    {month.xp_earned || ''}
                  </span>
                  <div
                    className="w-full rounded-t bg-gradient-to-t from-primary-500 to-primary-300 min-h-1"
                    style={{ height: `${height}%` }}
                    title={`${month.month}: ${month.xp_earned} XP, ${month.badges} badges`}
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
