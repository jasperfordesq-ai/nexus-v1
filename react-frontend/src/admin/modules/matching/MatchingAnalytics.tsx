/**
 * Matching Analytics Page
 * Displays smart matching statistics, score distribution,
 * distance distribution, and approval metrics.
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Progress,
  Spinner,
  Divider,
} from '@heroui/react';
import {
  ArrowLeft,
  BarChart3,
  Target,
  MapPin,
  TrendingUp,
  Users,
  Zap,
  CheckCircle,
  RefreshCw,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { StatCard, PageHeader, EmptyState } from '../../components';
import type { MatchingStatsResponse } from '../../api/types';

/** Color map for score distribution bars */
const SCORE_COLORS: Record<string, 'danger' | 'warning' | 'primary' | 'success'> = {
  '0-40': 'danger',
  '40-60': 'warning',
  '60-80': 'primary',
  '80-100': 'success',
};

const SCORE_LABELS: Record<string, string> = {
  '0-40': 'Low (0-40)',
  '40-60': 'Medium (40-60)',
  '60-80': 'Good (60-80)',
  '80-100': 'Hot (80-100)',
};

/** Color map for distance distribution bars */
const DIST_COLORS: Record<string, 'success' | 'primary' | 'secondary' | 'warning' | 'danger'> = {
  walking: 'success',
  local: 'primary',
  city: 'secondary',
  regional: 'warning',
  distant: 'danger',
};

const DIST_LABELS: Record<string, string> = {
  walking: 'Walking (0-5km)',
  local: 'Local (5-15km)',
  city: 'City (15-30km)',
  regional: 'Regional (30-50km)',
  distant: 'Distant (50+km)',
};

export function MatchingAnalytics() {
  usePageTitle('Admin - Matching Analytics');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [stats, setStats] = useState<MatchingStatsResponse | null>(null);
  const [loading, setLoading] = useState(true);

  const loadStats = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminMatching.getMatchingStats();
      if (res.success && res.data) {
        setStats(res.data);
      }
    } catch {
      toast.error('Failed to load matching analytics');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  const overview = stats?.overview;

  // Check if there is any data at all
  const hasData =
    overview &&
    (overview.cache_entries > 0 ||
      overview.total_matches_month > 0 ||
      overview.total_matches_week > 0);

  return (
    <div>
      <PageHeader
        title="Matching Analytics"
        description="Smart matching performance metrics and distribution analysis"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/smart-matching'))}
              size="sm"
            >
              Back
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadStats}
              isLoading={loading}
              size="sm"
            >
              Refresh
            </Button>
          </div>
        }
      />

      {loading ? (
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      ) : !hasData ? (
        <EmptyState
          icon={BarChart3}
          title="No Matching Data Yet"
          description="Matching analytics will appear here once the smart matching algorithm has generated matches. Ensure matching is enabled in your configuration."
          actionLabel="Configure Matching"
          onAction={() => navigate(tenantPath('/admin/smart-matching/configuration'))}
        />
      ) : (
        <>
          {/* Stats Row */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <StatCard
              label="Total Matches"
              value={overview?.total_matches_month ?? 0}
              icon={Target}
              color="primary"
              loading={loading}
            />
            <StatCard
              label="Approval Rate"
              value={`${stats?.approval_rate ?? 0}%`}
              icon={CheckCircle}
              color="success"
              loading={loading}
            />
            <StatCard
              label="Average Score"
              value={overview?.avg_match_score !== undefined
                ? `${overview.avg_match_score}%`
                : '---'}
              icon={TrendingUp}
              color="primary"
              loading={loading}
            />
            <StatCard
              label="Avg Distance"
              value={overview?.avg_distance_km !== undefined
                ? `${overview.avg_distance_km} km`
                : '---'}
              icon={MapPin}
              color="warning"
              loading={loading}
            />
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Score Distribution */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <Zap size={18} className="text-primary" />
                <h3 className="font-semibold">Score Distribution</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {stats?.score_distribution ? (
                  <div className="space-y-4">
                    {Object.entries(stats.score_distribution).map(([range, count]) => {
                      const total = Object.values(stats.score_distribution).reduce(
                        (a, b) => a + b,
                        0
                      );
                      const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                      return (
                        <div key={range}>
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-sm text-default-600">
                              {SCORE_LABELS[range] ?? range}
                            </span>
                            <span className="text-sm font-medium tabular-nums">
                              {count} ({pct}%)
                            </span>
                          </div>
                          <Progress
                            value={pct}
                            color={SCORE_COLORS[range] ?? 'primary'}
                            size="sm"
                            aria-label={`${SCORE_LABELS[range] ?? range}: ${count} matches (${pct}%)`}
                          />
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    No score data available
                  </p>
                )}
              </CardBody>
            </Card>

            {/* Distance Distribution */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <MapPin size={18} className="text-primary" />
                <h3 className="font-semibold">Distance Distribution</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {stats?.distance_distribution ? (
                  <div className="space-y-4">
                    {Object.entries(stats.distance_distribution).map(
                      ([band, count]) => {
                        const total = Object.values(
                          stats.distance_distribution
                        ).reduce((a, b) => a + b, 0);
                        const pct =
                          total > 0 ? Math.round((count / total) * 100) : 0;
                        return (
                          <div key={band}>
                            <div className="flex items-center justify-between mb-1">
                              <span className="text-sm text-default-600">
                                {DIST_LABELS[band] ?? band}
                              </span>
                              <span className="text-sm font-medium tabular-nums">
                                {count} ({pct}%)
                              </span>
                            </div>
                            <Progress
                              value={pct}
                              color={DIST_COLORS[band] ?? 'primary'}
                              size="sm"
                              aria-label={`${DIST_LABELS[band] ?? band}: ${count} matches (${pct}%)`}
                            />
                          </div>
                        );
                      }
                    )}
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    No distance data available
                  </p>
                )}
              </CardBody>
            </Card>

            {/* Matching Activity */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <Users size={18} className="text-primary" />
                <h3 className="font-semibold">Matching Activity</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {overview ? (
                  <div className="space-y-3">
                    <ActivityRow
                      label="Matches Today"
                      value={overview.total_matches_today}
                    />
                    <Divider />
                    <ActivityRow
                      label="Matches This Week"
                      value={overview.total_matches_week}
                    />
                    <Divider />
                    <ActivityRow
                      label="Matches This Month"
                      value={overview.total_matches_month}
                    />
                    <Divider />
                    <ActivityRow
                      label="Hot Matches (Score >= 85)"
                      value={overview.hot_matches_count}
                    />
                    <Divider />
                    <ActivityRow
                      label="Mutual Matches"
                      value={overview.mutual_matches_count}
                    />
                    <Divider />
                    <ActivityRow
                      label="Active Users in Matching"
                      value={overview.active_users_matching}
                    />
                    <Divider />
                    <ActivityRow
                      label="Cache Entries"
                      value={overview.cache_entries}
                    />
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    No activity data
                  </p>
                )}
              </CardBody>
            </Card>

            {/* Approval Metrics */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <CheckCircle size={18} className="text-primary" />
                <h3 className="font-semibold">Approval Metrics</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {stats ? (
                  <div className="space-y-3">
                    <ActivityRow
                      label="Pending Approvals"
                      value={stats.pending_approvals}
                      color="text-warning"
                    />
                    <Divider />
                    <ActivityRow
                      label="Approved Matches"
                      value={stats.approved_count}
                      color="text-success"
                    />
                    <Divider />
                    <ActivityRow
                      label="Rejected Matches"
                      value={stats.rejected_count}
                      color="text-danger"
                    />
                    <Divider />
                    <div className="flex items-center justify-between py-1">
                      <span className="text-sm text-default-600">Approval Rate</span>
                      <span className="text-sm font-bold text-success">
                        {stats.approval_rate}%
                      </span>
                    </div>
                    <Progress
                      value={stats.approval_rate}
                      color="success"
                      size="sm"
                      aria-label={`Approval rate: ${stats.approval_rate}%`}
                    />
                    <Divider />
                    <div className="flex items-center justify-between py-1">
                      <span className="text-sm text-default-600">
                        Broker Approval
                      </span>
                      <span
                        className={`text-sm font-medium ${
                          stats.broker_approval_enabled
                            ? 'text-success'
                            : 'text-default-400'
                        }`}
                      >
                        {stats.broker_approval_enabled ? 'Enabled' : 'Disabled'}
                      </span>
                    </div>
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    No approval data
                  </p>
                )}
              </CardBody>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}

/** Simple key-value row for activity/metrics cards */
function ActivityRow({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color?: string;
}) {
  return (
    <div className="flex items-center justify-between py-1">
      <span className="text-sm text-default-600">{label}</span>
      <span className={`text-sm font-bold tabular-nums ${color ?? 'text-foreground'}`}>
        {value.toLocaleString()}
      </span>
    </div>
  );
}

export default MatchingAnalytics;
