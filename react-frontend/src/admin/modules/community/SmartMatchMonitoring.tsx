/**
 * Smart Match Monitoring
 * Monitor the smart matching engine performance and quality metrics.
 * Wired to adminMatching.getMatchingStats() API (existing module).
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { Activity, BarChart3 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';
import type { MatchingStatsResponse } from '../../api/types';

export function SmartMatchMonitoring() {
  usePageTitle('Admin - Match Monitoring');
  const toast = useToast();

  const [data, setData] = useState<MatchingStatsResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminMatching.getMatchingStats()
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data as MatchingStatsResponse);
        }
      })
      .catch(() => toast.error('Failed to load matching stats'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div>
        <PageHeader title="Match Monitoring" description="Smart matching engine performance and quality metrics" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  const overview = data?.overview;

  return (
    <div>
      <PageHeader title="Match Monitoring" description="Smart matching engine performance and quality metrics" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Matches Generated"
          value={overview?.total_matches_month ?? 0}
          icon={Activity}
          color="primary"
        />
        <StatCard
          label="Avg Match Score"
          value={overview?.avg_match_score !== undefined ? `${Number(overview.avg_match_score).toFixed(1)}%` : '--'}
          icon={BarChart3}
          color="success"
        />
        <StatCard
          label="Approval Rate"
          value={data?.approval_rate !== undefined ? `${Number(data.approval_rate).toFixed(0)}%` : '--'}
          icon={Activity}
          color="warning"
        />
        <StatCard
          label="Cache Hit Rate"
          value={overview?.cache_hit_rate !== undefined ? `${Number(overview.cache_hit_rate).toFixed(0)}%` : '--'}
          icon={Activity}
          color="secondary"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Engine Status</h3></CardHeader>
          <CardBody>
            {data ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Broker Approval</span>
                  <span className="text-sm font-medium">{data.broker_approval_enabled ? 'Enabled' : 'Disabled'}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Pending Approvals</span>
                  <span className="text-sm font-medium">{data.pending_approvals ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Approved (Total)</span>
                  <span className="text-sm font-medium">{data.approved_count ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Rejected (Total)</span>
                  <span className="text-sm font-medium">{data.rejected_count ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Matches Today</span>
                  <span className="text-sm font-medium">{overview?.total_matches_today ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Matches This Week</span>
                  <span className="text-sm font-medium">{overview?.total_matches_week ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">Hot Matches</span>
                  <span className="text-sm font-medium">{overview?.hot_matches_count ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1">
                  <span className="text-sm text-default-500">Active Matching Users</span>
                  <span className="text-sm font-medium">{overview?.active_users_matching ?? 0}</span>
                </div>
              </div>
            ) : (
              <div className="flex flex-col items-center py-8 text-default-400">
                <Activity size={40} className="mb-3" />
                <p>Matching engine monitoring data will appear here when the engine has been run.</p>
                <p className="text-xs mt-1">Configure the matching algorithm from Smart Matching settings.</p>
              </div>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Score Distribution</h3></CardHeader>
          <CardBody>
            {data?.score_distribution && Object.keys(data.score_distribution).length > 0 ? (
              <div className="space-y-3">
                {Object.entries(data.score_distribution).map(([range, count]) => (
                  <div key={range} className="flex items-center justify-between py-1 border-b border-default-100 last:border-0">
                    <span className="text-sm">{range}</span>
                    <span className="text-sm font-medium text-primary">{count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center py-8 text-default-400">
                <BarChart3 size={40} className="mb-3" />
                <p className="text-sm">Score distribution will appear here once matches are generated.</p>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default SmartMatchMonitoring;
