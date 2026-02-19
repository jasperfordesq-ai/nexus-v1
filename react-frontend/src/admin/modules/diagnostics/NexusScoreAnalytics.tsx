// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Nexus Score Analytics
 * Analytics and insights for user Nexus Scores (composite engagement metric).
 * Wired to adminDiagnostics.getNexusScoreStats() API.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { BarChart3, TrendingUp } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminDiagnostics } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

interface NexusScoreData {
  total_badges_awarded: number;
  active_users: number;
  total_xp_awarded: number;
  active_campaigns: number;
  badge_distribution: Array<{ badge_name: string; count: number }>;
  avg_nexus_score?: number;
  top_10_threshold?: number;
  active_users_scored?: number;
  score_trend_30d?: number;
}

export function NexusScoreAnalytics() {
  usePageTitle('Admin - Nexus Score Analytics');
  const toast = useToast();

  const [data, setData] = useState<NexusScoreData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminDiagnostics.getNexusScoreStats()
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data as NexusScoreData);
        }
      })
      .catch(() => toast.error('Failed to load Nexus Score analytics'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div>
        <PageHeader title="Nexus Score Analytics" description="User engagement scoring and distribution analysis" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  const stats = data || { total_badges_awarded: 0, active_users: 0, total_xp_awarded: 0, active_campaigns: 0, badge_distribution: [] };

  return (
    <div>
      <PageHeader title="Nexus Score Analytics" description="User engagement scoring and distribution analysis" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Avg Nexus Score"
          value={stats.avg_nexus_score !== undefined ? `${Number(stats.avg_nexus_score).toFixed(1)}` : String(stats.total_xp_awarded)}
          icon={BarChart3}
          color="primary"
        />
        <StatCard
          label="Top 10% Threshold"
          value={stats.top_10_threshold !== undefined ? String(stats.top_10_threshold) : String(stats.total_badges_awarded)}
          icon={TrendingUp}
          color="success"
        />
        <StatCard
          label="Active Users Scored"
          value={stats.active_users_scored ?? stats.active_users}
          icon={BarChart3}
          color="warning"
        />
        <StatCard
          label="Score Trend (30d)"
          value={stats.score_trend_30d !== undefined ? `${stats.score_trend_30d > 0 ? '+' : ''}${stats.score_trend_30d}%` : String(stats.active_campaigns)}
          icon={TrendingUp}
          color="secondary"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Score Distribution</h3></CardHeader>
          <CardBody>
            {stats.badge_distribution && stats.badge_distribution.length > 0 ? (
              <div className="space-y-3">
                {stats.badge_distribution.map(({ badge_name, count }) => (
                  <div key={badge_name} className="flex items-center justify-between py-1 border-b border-default-100 last:border-0">
                    <span className="text-sm">{badge_name}</span>
                    <span className="text-sm font-medium text-primary">{count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center py-8 text-default-400">
                <BarChart3 size={40} className="mb-3" />
                <p>Score distribution chart will appear here once Nexus Scores are calculated for active users.</p>
              </div>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Score Factors</h3></CardHeader>
          <CardBody>
            <p className="text-xs text-default-400 mb-3">These weights are configurable from the matching configuration page.</p>
            <div className="space-y-3">
              {[
                { factor: 'Transaction Activity', weight: '25%' },
                { factor: 'Social Engagement', weight: '20%' },
                { factor: 'Profile Completeness', weight: '15%' },
                { factor: 'Login Frequency', weight: '15%' },
                { factor: 'Community Participation', weight: '15%' },
                { factor: 'Review Quality', weight: '10%' },
              ].map(({ factor, weight }) => (
                <div key={factor} className="flex items-center justify-between py-1 border-b border-default-100 last:border-0">
                  <span className="text-sm">{factor}</span>
                  <span className="text-sm font-medium text-primary">{weight}</span>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default NexusScoreAnalytics;
