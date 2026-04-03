// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Analytics Tab
 * Dashboard for group admins/owners showing KPIs, growth, engagement,
 * top contributors, activity breakdown, retention cohorts, and comparative stats.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Spinner,
  Card,
  CardBody,
  CardHeader,
  Avatar,
  Chip,
} from '@heroui/react';
import {
  TrendingUp,
  Users,
  BarChart3,
  Download,
  Award,
} from 'lucide-react';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from 'recharts';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { CHART_COLORS, CHART_COLOR_MAP } from '@/lib/chartColors';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface KpiData {
  total_members: number;
  active_members: number;
  participation_rate: number;
  avg_posts_per_day: number;
}

interface GrowthPoint {
  date: string;
  members: number;
  new_members: number;
}

interface EngagementPoint {
  date: string;
  posts: number;
  discussions: number;
  active_members: number;
}

interface Contributor {
  user_id: number;
  name: string;
  avatar_url: string | null;
  post_count: number;
}

interface ActivityBreakdown {
  type: string;
  count: number;
}

interface RetentionCohort {
  month: string;
  joined: number;
  still_active: number;
  retention_pct: number;
}

interface ComparativeStats {
  your_members: number;
  avg_members: number;
  your_activity: number;
  avg_activity: number;
  percentile_rank: number;
}

interface AnalyticsDashboard {
  kpi: KpiData;
  growth: GrowthPoint[];
  engagement: EngagementPoint[];
  top_contributors: Contributor[];
  activity_breakdown: ActivityBreakdown[];
  retention: RetentionCohort[];
  comparative: ComparativeStats;
}

type DaysRange = 7 | 30 | 90;

interface GroupAnalyticsTabProps {
  groupId: number;
  isAdmin: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Tooltip style (consistent with CommunityAnalytics)
// ─────────────────────────────────────────────────────────────────────────────

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupAnalyticsTab({ groupId, isAdmin }: GroupAnalyticsTabProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [days, setDays] = useState<DaysRange>(30);
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<AnalyticsDashboard | null>(null);

  const loadAnalytics = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/groups/${groupId}/analytics?days=${days}`);
      if (res.success && res.data) {
        // Map backend field names to frontend interface
        const raw = res.data as Record<string, unknown>;
        const mapped: AnalyticsDashboard = {
          kpi: (raw.overview ?? raw.kpi ?? {}) as KpiData,
          growth: (raw.member_growth ?? raw.growth ?? []) as GrowthPoint[],
          engagement: (raw.engagement ?? { timeline: [], summary: {} }) as EngagementData,
          top_contributors: (raw.top_contributors ?? []) as Contributor[],
          activity_breakdown: (raw.activity_breakdown ?? raw.activity ?? {}) as ActivityBreakdown,
          retention: (raw.retention ?? []) as RetentionCohort[],
          comparative: (raw.comparative ?? {}) as ComparativeStats,
        };
        setData(mapped);
      }
    } catch (err) {
      logError('GroupAnalyticsTab.loadAnalytics', err);
      toast.error(t('analytics.load_error', 'Failed to load analytics'));
    } finally {
      setLoading(false);
    }
  }, [groupId, days, t, toast]);

  useEffect(() => {
    if (isAdmin) {
      loadAnalytics();
    }
  }, [isAdmin, loadAnalytics]);

  // ─────────────────────────────────────────────────────────────────────
  // Admin-only gate
  // ─────────────────────────────────────────────────────────────────────

  if (!isAdmin) {
    return (
      <GlassCard className="p-6">
        <p className="text-center text-theme-subtle">
          {t('analytics.admin_only', 'Analytics are only available to group admins.')}
        </p>
      </GlassCard>
    );
  }

  // ─────────────────────────────────────────────────────────────────────
  // Loading state
  // ─────────────────────────────────────────────────────────────────────

  if (loading && !data) {
    return (
      <div
        className="flex justify-center py-12"
        aria-label={t('analytics.loading', 'Loading analytics')}
        aria-busy="true"
      >
        <Spinner size="lg" />
      </div>
    );
  }

  const kpi = data?.kpi;

  // ─────────────────────────────────────────────────────────────────────
  // Export handlers
  // ─────────────────────────────────────────────────────────────────────

  const handleExportMembers = () => {
    window.open(`/api/v2/groups/${groupId}/analytics/export/members`, '_blank');
  };

  const handleExportActivity = () => {
    window.open(`/api/v2/groups/${groupId}/analytics/export/activity`, '_blank');
  };

  // ─────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      {/* Header: Date Range + Export Buttons */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        {/* Date range selector */}
        <div className="flex items-center gap-2" role="group" aria-label={t('analytics.date_range_aria', 'Date range selector')}>
          {([7, 30, 90] as DaysRange[]).map((d) => (
            <Button
              key={d}
              size="sm"
              variant={days === d ? 'solid' : 'flat'}
              color={days === d ? 'primary' : 'default'}
              onPress={() => setDays(d)}
              aria-pressed={days === d}
            >
              {t(`analytics.days_${d}`, `${d}d`)}
            </Button>
          ))}
        </div>

        {/* Export buttons */}
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="flat"
            startContent={<Download className="w-4 h-4" aria-hidden="true" />}
            onPress={handleExportMembers}
            aria-label={t('analytics.export_members_aria', 'Export members CSV')}
          >
            {t('analytics.export_members', 'Export Members CSV')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            startContent={<Download className="w-4 h-4" aria-hidden="true" />}
            onPress={handleExportActivity}
            aria-label={t('analytics.export_activity_aria', 'Export activity CSV')}
          >
            {t('analytics.export_activity', 'Export Activity CSV')}
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <GlassCard className="p-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
              <Users className="w-5 h-5 text-primary" aria-hidden="true" />
            </div>
            <div>
              <p className="text-xs text-theme-subtle">{t('analytics.total_members', 'Total Members')}</p>
              <p className="text-xl font-bold text-theme-primary">
                {loading ? <Spinner size="sm" /> : (kpi?.total_members ?? 0)}
              </p>
            </div>
          </div>
        </GlassCard>

        <GlassCard className="p-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-success/10 flex items-center justify-center flex-shrink-0">
              <TrendingUp className="w-5 h-5 text-success" aria-hidden="true" />
            </div>
            <div>
              <p className="text-xs text-theme-subtle">{t('analytics.active_members', 'Active Members')}</p>
              <p className="text-xl font-bold text-theme-primary">
                {loading ? <Spinner size="sm" /> : (kpi?.active_members ?? 0)}
              </p>
            </div>
          </div>
        </GlassCard>

        <GlassCard className="p-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-warning/10 flex items-center justify-center flex-shrink-0">
              <BarChart3 className="w-5 h-5 text-warning" aria-hidden="true" />
            </div>
            <div>
              <p className="text-xs text-theme-subtle">{t('analytics.participation_rate', 'Participation Rate')}</p>
              <p className="text-xl font-bold text-theme-primary">
                {loading ? <Spinner size="sm" /> : `${(kpi?.participation_rate ?? 0).toFixed(1)}%`}
              </p>
            </div>
          </div>
        </GlassCard>

        <GlassCard className="p-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center flex-shrink-0">
              <Award className="w-5 h-5 text-secondary" aria-hidden="true" />
            </div>
            <div>
              <p className="text-xs text-theme-subtle">{t('analytics.avg_posts_day', 'Avg Posts/Day')}</p>
              <p className="text-xl font-bold text-theme-primary">
                {loading ? <Spinner size="sm" /> : (kpi?.avg_posts_per_day ?? 0).toFixed(1)}
              </p>
            </div>
          </div>
        </GlassCard>
      </div>

      {/* Member Growth Chart */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <Users className="w-[18px] h-[18px] text-success" aria-hidden="true" />
          <h3 className="font-semibold">{t('analytics.member_growth', 'Member Growth')}</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[300px] items-center justify-center">
              <Spinner />
            </div>
          ) : data && data.growth.length > 0 ? (
            <ResponsiveContainer width="100%" height={300}>
              <LineChart
                data={data.growth}
                margin={{ top: 10, right: 20, left: 0, bottom: 0 }}
              >
                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="currentColor"
                  className="text-default-200"
                />
                <XAxis
                  dataKey="date"
                  tick={{ fontSize: 11 }}
                  className="text-default-500"
                />
                <YAxis
                  tick={{ fontSize: 11 }}
                  className="text-default-500"
                  allowDecimals={false}
                />
                <Tooltip contentStyle={tooltipStyle} labelStyle={{ fontWeight: 600 }} />
                <Line
                  type="monotone"
                  dataKey="total_members"
                  name={t('analytics.chart_total_members', 'Total Members')}
                  stroke={CHART_COLOR_MAP.primary}
                  strokeWidth={2}
                  dot={{ r: 3 }}
                  activeDot={{ r: 5 }}
                />
                <Line
                  type="monotone"
                  dataKey="new_members"
                  name={t('analytics.chart_new_members', 'New Members')}
                  stroke={CHART_COLOR_MAP.success}
                  strokeWidth={2}
                  dot={{ r: 3 }}
                  activeDot={{ r: 5 }}
                />
              </LineChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
              {t('analytics.no_growth_data', 'No member growth data available.')}
            </p>
          )}
        </CardBody>
      </Card>

      {/* Engagement Timeline Chart */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <BarChart3 className="w-[18px] h-[18px] text-primary" aria-hidden="true" />
          <h3 className="font-semibold">{t('analytics.engagement_timeline', 'Engagement Timeline')}</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[300px] items-center justify-center">
              <Spinner />
            </div>
          ) : data && data.engagement.length > 0 ? (
            <ResponsiveContainer width="100%" height={300}>
              <BarChart
                data={data.engagement}
                margin={{ top: 10, right: 20, left: 0, bottom: 0 }}
              >
                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="currentColor"
                  className="text-default-200"
                />
                <XAxis
                  dataKey="date"
                  tick={{ fontSize: 11 }}
                  className="text-default-500"
                />
                <YAxis
                  tick={{ fontSize: 11 }}
                  className="text-default-500"
                  allowDecimals={false}
                />
                <Tooltip contentStyle={tooltipStyle} labelStyle={{ fontWeight: 600 }} />
                <Bar
                  dataKey="posts"
                  name={t('analytics.chart_posts', 'Posts')}
                  fill={CHART_COLOR_MAP.primary}
                  radius={[4, 4, 0, 0]}
                  fillOpacity={0.8}
                />
                <Bar
                  dataKey="discussions"
                  name={t('analytics.chart_discussions', 'Discussions')}
                  fill={CHART_COLOR_MAP.secondary}
                  radius={[4, 4, 0, 0]}
                  fillOpacity={0.8}
                />
                <Line
                  type="monotone"
                  dataKey="active_members"
                  name={t('analytics.chart_active_members', 'Active Members')}
                  stroke={CHART_COLOR_MAP.success}
                  strokeWidth={2}
                  dot={{ r: 3 }}
                />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
              {t('analytics.no_engagement_data', 'No engagement data available.')}
            </p>
          )}
        </CardBody>
      </Card>

      {/* Top Contributors + Activity Breakdown (side by side) */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Top Contributors */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Award className="w-[18px] h-[18px] text-warning" aria-hidden="true" />
            <h3 className="font-semibold">{t('analytics.top_contributors', 'Top Contributors')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[300px] items-center justify-center">
                <Spinner />
              </div>
            ) : data && data.top_contributors.length > 0 ? (
              <div className="space-y-3">
                {data.top_contributors.map((contributor, index) => (
                  <div
                    key={contributor.user_id}
                    className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors"
                  >
                    <span className="text-sm font-bold text-default-400 w-6 text-center">
                      {index + 1}
                    </span>
                    <Avatar
                      src={resolveAvatarUrl(contributor.avatar_url)}
                      name={contributor.name}
                      size="sm"
                      className="flex-shrink-0"
                    />
                    <span className="flex-1 text-sm font-medium text-theme-primary truncate">
                      {contributor.name}
                    </span>
                    <Chip size="sm" variant="flat" color="primary">
                      {contributor.post_count} {t('analytics.posts', 'posts')}
                    </Chip>
                  </div>
                ))}
              </div>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                {t('analytics.no_contributors', 'No contributor data available.')}
              </p>
            )}
          </CardBody>
        </Card>

        {/* Activity Breakdown (Pie Chart) */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <BarChart3 className="w-[18px] h-[18px] text-secondary" aria-hidden="true" />
            <h3 className="font-semibold">{t('analytics.activity_breakdown', 'Activity Breakdown')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[300px] items-center justify-center">
                <Spinner />
              </div>
            ) : data && data.activity_breakdown.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={data.activity_breakdown}
                    dataKey="count"
                    nameKey="type"
                    cx="50%"
                    cy="50%"
                    outerRadius={100}
                    innerRadius={50}
                    paddingAngle={2}
                    label={({ name, percent }) =>
                      `${name} (${((percent ?? 0) * 100).toFixed(0)}%)`
                    }
                    labelLine={{ strokeWidth: 1 }}
                  >
                    {data.activity_breakdown.map((_, index) => (
                      <Cell
                        key={`cell-${index}`}
                        fill={CHART_COLORS[index % CHART_COLORS.length]}
                        fillOpacity={0.85}
                      />
                    ))}
                  </Pie>
                  <Tooltip
                    contentStyle={tooltipStyle}
                    formatter={(value: number, name: string) => [
                      `${value}`,
                      name,
                    ]}
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                {t('analytics.no_activity_data', 'No activity breakdown data available.')}
              </p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Retention Cohorts */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <TrendingUp className="w-[18px] h-[18px] text-info" aria-hidden="true" />
          <h3 className="font-semibold">{t('analytics.retention_cohorts', 'Retention Cohorts')}</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[200px] items-center justify-center">
              <Spinner />
            </div>
          ) : data && data.retention.length > 0 ? (
            <div className="overflow-x-auto">
              <table
                className="w-full text-sm"
                aria-label={t('analytics.retention_table_aria', 'Retention cohorts table')}
              >
                <thead>
                  <tr className="border-b border-default-200">
                    <th className="text-left py-2 px-3 font-semibold text-theme-primary">
                      {t('analytics.col_month', 'Month')}
                    </th>
                    <th className="text-right py-2 px-3 font-semibold text-theme-primary">
                      {t('analytics.col_joined', 'Joined')}
                    </th>
                    <th className="text-right py-2 px-3 font-semibold text-theme-primary">
                      {t('analytics.col_still_active', 'Still Active')}
                    </th>
                    <th className="text-right py-2 px-3 font-semibold text-theme-primary">
                      {t('analytics.col_retention', 'Retention %')}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.retention.map((cohort) => (
                    <tr
                      key={cohort.month}
                      className="border-b border-default-100 hover:bg-theme-hover transition-colors"
                    >
                      <td className="py-2 px-3 text-theme-primary">{cohort.month}</td>
                      <td className="py-2 px-3 text-right text-theme-subtle">{cohort.joined}</td>
                      <td className="py-2 px-3 text-right text-theme-subtle">{cohort.still_active}</td>
                      <td className="py-2 px-3 text-right">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={
                            cohort.retention_pct >= 70
                              ? 'success'
                              : cohort.retention_pct >= 40
                                ? 'warning'
                                : 'danger'
                          }
                        >
                          {cohort.retention_pct.toFixed(1)}%
                        </Chip>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="flex h-[200px] items-center justify-center text-sm text-default-400">
              {t('analytics.no_retention_data', 'No retention data available.')}
            </p>
          )}
        </CardBody>
      </Card>

      {/* Comparative Stats */}
      {data?.comparative && (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <BarChart3 className="w-[18px] h-[18px] text-primary" aria-hidden="true" />
            <h3 className="font-semibold">{t('analytics.comparative_stats', 'Comparative Stats')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              {/* Members comparison */}
              <div className="p-4 rounded-lg bg-theme-elevated text-center">
                <p className="text-xs text-theme-subtle mb-1">
                  {t('analytics.your_members', 'Your Members')}
                </p>
                <p className="text-2xl font-bold text-theme-primary">
                  {data.comparative.your_members}
                </p>
                <p className="text-xs text-theme-subtle mt-1">
                  {t('analytics.vs_average', 'vs avg')}{' '}
                  <span className="font-semibold text-default-600">
                    {data.comparative.avg_members}
                  </span>
                </p>
              </div>

              {/* Activity comparison */}
              <div className="p-4 rounded-lg bg-theme-elevated text-center">
                <p className="text-xs text-theme-subtle mb-1">
                  {t('analytics.your_activity', 'Your Activity')}
                </p>
                <p className="text-2xl font-bold text-theme-primary">
                  {data.comparative.your_activity}
                </p>
                <p className="text-xs text-theme-subtle mt-1">
                  {t('analytics.vs_average', 'vs avg')}{' '}
                  <span className="font-semibold text-default-600">
                    {data.comparative.avg_activity}
                  </span>
                </p>
              </div>

              {/* Percentile rank */}
              <div className="p-4 rounded-lg bg-theme-elevated text-center">
                <p className="text-xs text-theme-subtle mb-1">
                  {t('analytics.percentile_rank', 'Percentile Rank')}
                </p>
                <p className="text-2xl font-bold text-theme-primary">
                  {t('analytics.percentile_value', 'Top {{pct}}%', {
                    pct: (100 - data.comparative.percentile_rank).toFixed(0),
                  })}
                </p>
                <Chip
                  size="sm"
                  variant="flat"
                  color={
                    data.comparative.percentile_rank >= 75
                      ? 'success'
                      : data.comparative.percentile_rank >= 50
                        ? 'primary'
                        : 'warning'
                  }
                  className="mt-2"
                >
                  {data.comparative.percentile_rank >= 75
                    ? t('analytics.excellent', 'Excellent')
                    : data.comparative.percentile_rank >= 50
                      ? t('analytics.good', 'Good')
                      : t('analytics.below_average', 'Below Average')}
                </Chip>
              </div>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default GroupAnalyticsTab;
