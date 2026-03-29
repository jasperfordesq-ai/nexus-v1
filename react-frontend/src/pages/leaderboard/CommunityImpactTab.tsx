// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Skeleton, Chip } from '@heroui/react';
import {
  Users, Clock, Award, TrendingUp, TrendingDown,
  UserPlus, FileText, Handshake, Zap, Heart,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface MonthStats {
  new_members: number;
  badges_awarded: number;
  new_listings: number;
  new_connections: number;
  volunteer_hours: number;
  new_posts: number;
}

interface CommunityImpactData {
  total_members: number;
  total_xp: number;
  total_badges_awarded: number;
  total_volunteer_hours: number;
  total_listings: number;
  total_connections: number;
  total_exchanges: number;
  total_reviews: number;
  this_month: MonthStats;
  last_month: MonthStats;
  trends: Record<string, number>;
}

export default function CommunityImpactTab() {
  const { t } = useTranslation('gamification');
  const [data, setData] = useState<CommunityImpactData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        setIsLoading(true);
        const res = await api.get<CommunityImpactData>('/v2/gamification/community-dashboard');
        if (res.success && res.data) {
          setData(res.data);
        } else {
          setError(res.error || 'Failed to load community data');
        }
      } catch (err: unknown) {
        logError('CommunityImpactTab', err);
        setError('Failed to load community data');
      } finally {
        setIsLoading(false);
      }
    };

    load();
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

  if (!data) return null;

  const stats = [
    { label: t('community.total_members', 'Community Members'), value: data.total_members, icon: <Users className="w-5 h-5" />, color: 'text-blue-500', bg: 'bg-blue-500/10' },
    { label: t('community.total_badges', 'Badges Awarded'), value: data.total_badges_awarded, icon: <Award className="w-5 h-5" />, color: 'text-amber-500', bg: 'bg-amber-500/10' },
    { label: t('community.volunteer_hours', 'Volunteer Hours'), value: data.total_volunteer_hours, icon: <Clock className="w-5 h-5" />, color: 'text-emerald-500', bg: 'bg-emerald-500/10' },
    { label: t('community.total_xp', 'Total XP Earned'), value: data.total_xp.toLocaleString(), icon: <Zap className="w-5 h-5" />, color: 'text-purple-500', bg: 'bg-purple-500/10' },
  ];

  const secondaryStats = [
    { label: t('community.total_listings', 'Listings'), value: data.total_listings, icon: <FileText className="w-4 h-4" /> },
    { label: t('community.total_connections', 'Connections'), value: data.total_connections, icon: <Handshake className="w-4 h-4" /> },
    { label: t('community.total_reviews', 'Reviews'), value: data.total_reviews, icon: <Heart className="w-4 h-4" /> },
  ];

  const monthMetrics = [
    { label: t('community.new_members', 'New Members'), value: data.this_month.new_members, trend: data.trends.new_members, icon: <UserPlus className="w-4 h-4" /> },
    { label: t('community.badges_awarded', 'Badges Awarded'), value: data.this_month.badges_awarded, trend: data.trends.badges_awarded, icon: <Award className="w-4 h-4" /> },
    { label: t('community.new_listings', 'New Listings'), value: data.this_month.new_listings, trend: data.trends.new_listings, icon: <FileText className="w-4 h-4" /> },
    { label: t('community.new_connections', 'New Connections'), value: data.this_month.new_connections, trend: data.trends.new_connections, icon: <Handshake className="w-4 h-4" /> },
    { label: t('community.volunteer_hours', 'Volunteer Hours'), value: data.this_month.volunteer_hours, trend: data.trends.volunteer_hours, icon: <Clock className="w-4 h-4" /> },
    { label: t('community.new_posts', 'New Posts'), value: data.this_month.new_posts, trend: data.trends.new_posts, icon: <FileText className="w-4 h-4" /> },
  ];

  return (
    <div className="space-y-6">
      {/* Primary stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {stats.map((stat, i) => (
          <motion.div key={stat.label} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.1 }}>
            <GlassCard className="p-4">
              <div className="flex items-center gap-2 mb-2">
                <div className={`p-1.5 rounded-lg ${stat.bg} ${stat.color}`}>{stat.icon}</div>
                <span className="text-xs text-default-500">{stat.label}</span>
              </div>
              <p className="text-2xl font-bold">{typeof stat.value === 'number' ? stat.value.toLocaleString() : stat.value}</p>
            </GlassCard>
          </motion.div>
        ))}
      </div>

      {/* Secondary stats row */}
      <div className="grid grid-cols-3 gap-4">
        {secondaryStats.map((stat) => (
          <GlassCard key={stat.label} className="p-3 flex items-center gap-3">
            <span className="text-default-400">{stat.icon}</span>
            <div>
              <p className="text-lg font-semibold">{stat.value}</p>
              <p className="text-xs text-default-500">{stat.label}</p>
            </div>
          </GlassCard>
        ))}
      </div>

      {/* This month breakdown */}
      <GlassCard className="p-6">
        <h3 className="text-lg font-semibold mb-4">{t('community.this_month', 'This Month')}</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {monthMetrics.map((metric) => (
            <div key={metric.label} className="flex items-center justify-between p-3 rounded-lg bg-default-100/50">
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
  if (!trend || trend === 0) return <Chip size="sm" variant="flat">—</Chip>;
  const isPositive = trend > 0;
  const Icon = isPositive ? TrendingUp : TrendingDown;
  return (
    <Chip size="sm" variant="flat" color={isPositive ? 'success' : 'warning'} startContent={<Icon className="w-3 h-3" />}>
      {isPositive ? '+' : ''}{trend}%
    </Chip>
  );
}
