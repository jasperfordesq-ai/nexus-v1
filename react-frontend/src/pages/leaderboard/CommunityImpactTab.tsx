// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CommunityImpactTab — default leaderboard tab showing collective community stats.
 *
 * Replaces competitive rankings as the default view. Shows aggregate impact
 * (total hours exchanged, active members, connections) to reflect timebanking's
 * cooperative philosophy rather than individual competition.
 */

import { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Skeleton, Chip } from '@heroui/react';
import {
  Users, Clock, Handshake, TrendingUp, TrendingDown,
  UserPlus, Repeat,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface MonthStats {
  hours_exchanged: number;
  active_members: number;
  new_connections: number;
  new_exchanges: number;
  new_members: number;
}

interface CommunityImpactData {
  total_hours_exchanged: number;
  total_members: number;
  total_exchanges: number;
  total_skills_offered: number;
  this_month: MonthStats;
  last_month: MonthStats;
  trends: Record<string, number>;
}

export default function CommunityImpactTab() {
  const { t } = useTranslation('gamification');
  const [data, setData] = useState<CommunityImpactData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    abortRef.current = controller;

    const load = async () => {
      try {
        setIsLoading(true);
        const res = await api.get<CommunityImpactData>('/v2/gamification/community-dashboard', {
          signal: controller.signal,
          timeout: 60000,
        });
        if (controller.signal.aborted) return;
        if (res.success && res.data) {
          setData(res.data);
        } else {
          setError(res.error || 'Failed to load community data');
        }
      } catch (err: unknown) {
        if (err instanceof Error && err.name !== 'AbortError') {
          logError('CommunityImpactTab', err);
          setError('Failed to load community data');
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
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[...Array(4)].map((_, i) => (
            <GlassCard key={i} className="p-4">
              <Skeleton className="h-4 w-20 mb-2 rounded" />
              <Skeleton className="h-8 w-16 rounded" />
            </GlassCard>
          ))}
        </div>
        <GlassCard className="p-6">
          <Skeleton className="h-4 w-40 mb-4 rounded" />
          <Skeleton className="h-32 w-full rounded" />
        </GlassCard>
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

  const stats = [
    {
      label: t('community.total_hours', 'Total Hours Exchanged'),
      value: data.total_hours_exchanged.toLocaleString(),
      icon: <Clock className="w-5 h-5" />,
      color: 'text-amber-500',
      bgColor: 'bg-amber-500/10',
    },
    {
      label: t('community.total_members', 'Community Members'),
      value: data.total_members.toLocaleString(),
      icon: <Users className="w-5 h-5" />,
      color: 'text-blue-500',
      bgColor: 'bg-blue-500/10',
    },
    {
      label: t('community.total_exchanges', 'Exchanges Completed'),
      value: data.total_exchanges.toLocaleString(),
      icon: <Repeat className="w-5 h-5" />,
      color: 'text-emerald-500',
      bgColor: 'bg-emerald-500/10',
    },
    {
      label: t('community.skills_offered', 'Skill Categories'),
      value: data.total_skills_offered.toLocaleString(),
      icon: <Handshake className="w-5 h-5" />,
      color: 'text-purple-500',
      bgColor: 'bg-purple-500/10',
    },
  ];

  const monthMetrics = [
    {
      label: t('community.hours_this_month', 'Hours Exchanged'),
      value: data.this_month.hours_exchanged,
      trend: data.trends.hours_exchanged,
      icon: <Clock className="w-4 h-4" />,
    },
    {
      label: t('community.active_members', 'Active Members'),
      value: data.this_month.active_members,
      trend: data.trends.active_members,
      icon: <Users className="w-4 h-4" />,
    },
    {
      label: t('community.new_connections', 'New Connections'),
      value: data.this_month.new_connections,
      trend: data.trends.new_connections,
      icon: <Handshake className="w-4 h-4" />,
    },
    {
      label: t('community.new_exchanges', 'New Exchanges'),
      value: data.this_month.new_exchanges,
      trend: data.trends.new_exchanges,
      icon: <Repeat className="w-4 h-4" />,
    },
    {
      label: t('community.new_members', 'New Members'),
      value: data.this_month.new_members,
      trend: data.trends.new_members,
      icon: <UserPlus className="w-4 h-4" />,
    },
  ];

  return (
    <div className="space-y-6">
      {/* All-time stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {stats.map((stat, i) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.1 }}
          >
            <GlassCard className="p-4">
              <div className="flex items-center gap-2 mb-2">
                <div className={`p-1.5 rounded-lg ${stat.bgColor} ${stat.color}`}>
                  {stat.icon}
                </div>
                <span className="text-xs text-default-500">{stat.label}</span>
              </div>
              <p className="text-2xl font-bold">{stat.value}</p>
            </GlassCard>
          </motion.div>
        ))}
      </div>

      {/* This month vs last month */}
      <GlassCard className="p-6">
        <h3 className="text-lg font-semibold mb-4">
          {t('community.this_month', 'This Month')}
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {monthMetrics.map((metric) => (
            <div
              key={metric.label}
              className="flex items-center justify-between p-3 rounded-lg bg-default-100/50"
            >
              <div className="flex items-center gap-2">
                <span className="text-default-400">{metric.icon}</span>
                <div>
                  <p className="text-sm text-default-500">{metric.label}</p>
                  <p className="text-lg font-semibold">{metric.value}</p>
                </div>
              </div>
              <TrendChip trend={metric.trend} />
            </div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}

function TrendChip({ trend }: { trend: number }) {
  if (trend === 0) {
    return <Chip size="sm" variant="flat">—</Chip>;
  }

  const isPositive = trend > 0;
  const Icon = isPositive ? TrendingUp : TrendingDown;

  return (
    <Chip
      size="sm"
      variant="flat"
      color={isPositive ? 'success' : 'warning'}
      startContent={<Icon className="w-3 h-3" />}
    >
      {isPositive ? '+' : ''}{trend}%
    </Chip>
  );
}
