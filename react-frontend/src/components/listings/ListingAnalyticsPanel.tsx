// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ListingAnalyticsPanel - Shows analytics for a listing owner
 *
 * Displays view counts, contact rate, save rate, and trends over time.
 */

import { useState, useEffect, useCallback } from 'react';
import { Spinner } from '@heroui/react';
import { Eye, MessageCircle, Heart, TrendingUp, TrendingDown } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { ListingAnalytics } from '@/types/api';

interface ListingAnalyticsPanelProps {
  listingId: number;
}

export function ListingAnalyticsPanel({ listingId }: ListingAnalyticsPanelProps) {
  const [analytics, setAnalytics] = useState<ListingAnalytics | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const loadAnalytics = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<ListingAnalytics>(`/v2/listings/${listingId}/analytics?days=30`);
      if (response.success && response.data) {
        setAnalytics(response.data);
      }
    } catch (error) {
      logError('Failed to load listing analytics', error);
    } finally {
      setIsLoading(false);
    }
  }, [listingId]);

  useEffect(() => {
    loadAnalytics();
  }, [loadAnalytics]);

  if (isLoading) {
    return (
      <GlassCard className="p-6">
        <div className="flex items-center justify-center py-8">
          <Spinner size="lg" />
        </div>
      </GlassCard>
    );
  }

  if (!analytics) {
    return null;
  }

  const { summary } = analytics;
  const trendPositive = summary.views_trend_percent >= 0;

  return (
    <GlassCard className="p-6">
      <h3 className="text-lg font-semibold text-theme-primary mb-4">Listing Analytics</h3>

      {/* Summary Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <StatCard
          icon={<Eye className="w-5 h-5 text-blue-500" />}
          label="Total Views"
          value={summary.total_views}
          subtext={`${summary.unique_viewers} unique`}
        />
        <StatCard
          icon={<MessageCircle className="w-5 h-5 text-green-500" />}
          label="Contacts"
          value={summary.total_contacts}
          subtext={`${summary.contact_rate}% rate`}
        />
        <StatCard
          icon={<Heart className="w-5 h-5 text-rose-500" />}
          label="Saves"
          value={summary.total_saves}
          subtext={`${summary.save_rate}% rate`}
        />
        <StatCard
          icon={trendPositive
            ? <TrendingUp className="w-5 h-5 text-emerald-500" />
            : <TrendingDown className="w-5 h-5 text-amber-500" />
          }
          label="7-Day Trend"
          value={`${trendPositive ? '+' : ''}${summary.views_trend_percent}%`}
          subtext="vs. previous week"
        />
      </div>

      {/* Simple sparkline-style visualization using bars */}
      {analytics.views_over_time.length > 0 && (
        <div>
          <h4 className="text-sm font-medium text-theme-muted mb-2">Views (Last {analytics.period_days} Days)</h4>
          <div className="flex items-end gap-1 h-16">
            {analytics.views_over_time.map((day) => {
              const maxCount = Math.max(...analytics.views_over_time.map((d) => Number(d.count)), 1);
              const height = (Number(day.count) / maxCount) * 100;
              return (
                <div
                  key={day.date}
                  className="flex-1 bg-blue-500/70 rounded-t min-w-[4px] transition-all hover:bg-blue-600"
                  style={{ height: `${Math.max(height, 4)}%` }}
                  title={`${day.date}: ${day.count} views`}
                />
              );
            })}
          </div>
          <div className="flex justify-between text-[10px] text-theme-subtle mt-1">
            <span>{analytics.views_over_time[0]?.date}</span>
            <span>{analytics.views_over_time[analytics.views_over_time.length - 1]?.date}</span>
          </div>
        </div>
      )}
    </GlassCard>
  );
}

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: number | string;
  subtext?: string;
}

function StatCard({ icon, label, value, subtext }: StatCardProps) {
  return (
    <div className="bg-theme-elevated rounded-lg p-3 text-center">
      <div className="flex justify-center mb-1">{icon}</div>
      <div className="text-xl font-bold text-theme-primary">{value}</div>
      <div className="text-xs text-theme-muted">{label}</div>
      {subtext && <div className="text-[10px] text-theme-subtle mt-0.5">{subtext}</div>}
    </div>
  );
}

export default ListingAnalyticsPanel;
