// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteering Overview
 * Admin dashboard for volunteering module with stats, trends chart,
 * quick actions, and real-time activity feed.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Button, Chip, Avatar, ButtonGroup, Skeleton } from '@heroui/react';
import {
  Heart, Users, Clock, Briefcase, RefreshCw, AlertTriangle,
  ClipboardCheck, Building2, DollarSign, ChevronRight, Activity,
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

import { useTranslation } from 'react-i18next';

interface VolStats {
  total_opportunities: number;
  active_opportunities: number;
  total_applications: number;
  pending_applications: number;
  total_hours_logged: number;
  active_volunteers: number;
}

interface Opportunity {
  id: number;
  title: string;
  status: string;
  first_name: string;
  last_name: string;
  created_at: string;
}

interface TrendPeriod {
  period: string;
  hours: number;
  count: number;
}

interface AppPeriod {
  period: string;
  count: number;
  approved: number;
}

interface VolPeriod {
  period: string;
  count: number;
}

interface TrendsData {
  hours_by_period: TrendPeriod[];
  applications_by_period: AppPeriod[];
  volunteers_by_period: VolPeriod[];
}

interface ActivityItem {
  type: string;
  timestamp: string;
  user_name: string;
  avatar_url: string;
  description: string;
  entity_type: string;
  entity_id: number;
}

interface QuickAction {
  label: string;
  description: string;
  icon: typeof ClipboardCheck;
  path: string;
  color: 'primary' | 'warning' | 'secondary' | 'success';
}

const ACTIVITY_TYPE_COLORS: Record<string, 'success' | 'warning' | 'danger' | 'primary' | 'default'> = {
  hours_logged: 'success',
  application_pending: 'warning',
  application_approved: 'success',
  application_declined: 'danger',
  donation: 'primary',
};

function formatActivityType(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatTimestamp(ts: string): string {
  const date = new Date(ts);
  const now = new Date();
  const diff = now.getTime() - date.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return date.toLocaleDateString();
}

export function VolunteeringOverview() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.page_title'));
  const toast = useToast();
  const navigate = useNavigate();
  const [stats, setStats] = useState<VolStats | null>(null);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [loading, setLoading] = useState(true);

  // Trends state
  const [trends, setTrends] = useState<TrendsData | null>(null);
  const [trendPeriod, setTrendPeriod] = useState<'week' | 'month'>('week');
  const [trendsLoading, setTrendsLoading] = useState(true);

  // Activity feed state
  const [activities, setActivities] = useState<ActivityItem[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getOverview();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let d: { stats?: VolStats; recent_opportunities?: Opportunity[] };
        if (payload && typeof payload === 'object' && 'data' in payload) {
          d = (payload as { data: typeof d }).data;
        } else {
          d = payload as typeof d;
        }
        setStats(d.stats || null);
        setOpportunities(d.recent_opportunities || []);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_volunteering_data'));
      setStats(null);
      setOpportunities([]);
    }
    setLoading(false);
  }, [toast, t]);

  const loadTrends = useCallback(async (period: string) => {
    setTrendsLoading(true);
    try {
      const res = await adminVolunteering.getTrends(period);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let d: TrendsData;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          d = (payload as { data: TrendsData }).data;
        } else {
          d = payload as TrendsData;
        }
        setTrends(d);
      }
    } catch {
      setTrends(null);
    }
    setTrendsLoading(false);
  }, []);

  const loadActivityFeed = useCallback(async () => {
    setActivitiesLoading(true);
    try {
      const res = await adminVolunteering.getActivityFeed(20, 30);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let d: { activities: ActivityItem[] };
        if (payload && typeof payload === 'object' && 'data' in payload) {
          d = (payload as { data: typeof d }).data;
        } else {
          d = payload as typeof d;
        }
        setActivities(d.activities || []);
      }
    } catch {
      setActivities([]);
    }
    setActivitiesLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);
  useEffect(() => { loadTrends(trendPeriod); }, [loadTrends, trendPeriod]);
  useEffect(() => { loadActivityFeed(); }, [loadActivityFeed]);

  // Merge trend data for the chart
  const chartData = trends ? trends.hours_by_period.map((h, i) => ({
    period: h.period,
    hours: h.hours,
    applications: trends.applications_by_period[i]?.count ?? 0,
    volunteers: trends.volunteers_by_period[i]?.count ?? 0,
  })) : [];

  const quickActions: QuickAction[] = [
    { label: t('volunteering.review_applications', 'Review Applications'), description: t('volunteering.review_applications_desc', 'Review pending volunteer applications'), icon: ClipboardCheck, path: '/admin/volunteering/approvals', color: 'warning' },
    { label: t('volunteering.verify_hours', 'Verify Hours'), description: t('volunteering.verify_hours_desc', 'Approve or decline logged hours'), icon: Clock, path: '/admin/volunteering/hours', color: 'success' },
    { label: t('volunteering.manage_organizations', 'Manage Organizations'), description: t('volunteering.manage_organizations_desc', 'View and manage volunteer orgs'), icon: Building2, path: '/admin/volunteering/organizations', color: 'secondary' },
    { label: t('volunteering.view_expenses', 'View Expenses'), description: t('volunteering.view_expenses_desc', 'Review expense submissions'), icon: DollarSign, path: '/admin/volunteering/expenses', color: 'primary' },
  ];

  // Build alert banners for urgent items
  const alerts: { message: string; path?: string }[] = [];
  if (stats && stats.pending_applications > 0) {
    alerts.push({ message: t('volunteering.alert_pending_applications', '{{count}} applications pending review', { count: stats.pending_applications }), path: '/admin/volunteering/approvals' });
  }

  const handleRefresh = () => {
    loadData();
    loadTrends(trendPeriod);
    loadActivityFeed();
  };

  return (
    <div>
      <PageHeader
        title={t('volunteering.volunteering_overview_title')}
        description={t('volunteering.volunteering_overview_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={handleRefresh} isLoading={loading}>{t('common.refresh')}</Button>}
      />

      {/* Alert Banners */}
      {!loading && alerts.length > 0 && (
        <div className="flex flex-col gap-2 mb-6">
          {alerts.map((alert, idx) => (
            <div
              key={idx}
              className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center gap-3 cursor-pointer hover:bg-amber-500/15 transition-colors"
              onClick={() => alert.path && navigate(alert.path)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => { if (e.key === 'Enter' && alert.path) navigate(alert.path); }}
            >
              <AlertTriangle size={18} className="text-amber-500 shrink-0" />
              <span className="text-sm font-medium flex-1">{alert.message}</span>
              {alert.path && <ChevronRight size={16} className="text-amber-500/60" />}
            </div>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
        <StatCard label={t('volunteering.label_active_opportunities')} value={stats?.active_opportunities ?? 0} icon={Briefcase} color="primary" loading={loading} />
        <StatCard label={t('volunteering.label_pending_applications')} value={stats?.pending_applications ?? 0} icon={Users} color="warning" loading={loading} />
        <StatCard label={t('volunteering.label_total_hours_logged')} value={stats?.total_hours_logged ?? 0} icon={Clock} color="success" loading={loading} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <StatCard label={t('volunteering.label_total_opportunities')} value={stats?.total_opportunities ?? 0} icon={Heart} color="secondary" loading={loading} />
        <StatCard label={t('volunteering.label_total_applications')} value={stats?.total_applications ?? 0} icon={Users} color="primary" loading={loading} />
        <StatCard label={t('volunteering.label_active_volunteers')} value={stats?.active_volunteers ?? 0} icon={Users} color="success" loading={loading} />
      </div>

      {/* Trends Chart */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex flex-row items-center justify-between">
          <h3 className="text-lg font-semibold">{t('volunteering.trends_title', 'Trends (Last 12 Weeks)')}</h3>
          <ButtonGroup size="sm" variant="flat">
            <Button
              color={trendPeriod === 'week' ? 'primary' : 'default'}
              onPress={() => setTrendPeriod('week')}
            >
              {t('volunteering.weekly', 'Weekly')}
            </Button>
            <Button
              color={trendPeriod === 'month' ? 'primary' : 'default'}
              onPress={() => setTrendPeriod('month')}
            >
              {t('volunteering.monthly', 'Monthly')}
            </Button>
          </ButtonGroup>
        </CardHeader>
        <CardBody>
          {trendsLoading ? (
            <div className="flex flex-col gap-2">
              <Skeleton className="h-[300px] w-full rounded-lg" />
            </div>
          ) : chartData.length === 0 ? (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Activity size={40} className="mb-2" />
              <p>{t('volunteering.no_trend_data', 'No trend data available')}</p>
            </div>
          ) : (
            <ResponsiveContainer width="100%" height={300}>
              <AreaChart data={chartData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="gradHours" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#22c55e" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#22c55e" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="gradApplications" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="gradVolunteers" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#a855f7" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#a855f7" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--color-default-200, #e5e7eb)" />
                <XAxis dataKey="period" tick={{ fontSize: 12 }} stroke="var(--color-default-400, #9ca3af)" />
                <YAxis tick={{ fontSize: 12 }} stroke="var(--color-default-400, #9ca3af)" />
                <Tooltip
                  contentStyle={{
                    backgroundColor: 'var(--color-surface, #fff)',
                    border: '1px solid var(--color-default-200, #e5e7eb)',
                    borderRadius: '8px',
                    fontSize: '13px',
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="hours"
                  name={t('volunteering.chart_hours', 'Hours')}
                  stroke="var(--chart-color-success, #22c55e)"
                  fill="url(#gradHours)"
                  strokeWidth={2}
                />
                <Area
                  type="monotone"
                  dataKey="applications"
                  name={t('volunteering.chart_applications', 'Applications')}
                  stroke="var(--chart-color-info, #3b82f6)"
                  fill="url(#gradApplications)"
                  strokeWidth={2}
                />
                <Area
                  type="monotone"
                  dataKey="volunteers"
                  name={t('volunteering.chart_volunteers', 'Volunteers')}
                  stroke="var(--chart-color-accent, #a855f7)"
                  fill="url(#gradVolunteers)"
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardBody>
      </Card>

      {/* Quick Action Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {quickActions.map((action) => {
          const colorClasses: Record<string, { bg: string; text: string }> = {
            primary: { bg: 'bg-primary/10', text: 'text-primary' },
            secondary: { bg: 'bg-secondary/10', text: 'text-secondary' },
            success: { bg: 'bg-success/10', text: 'text-success' },
            warning: { bg: 'bg-warning/10', text: 'text-warning' },
            danger: { bg: 'bg-danger/10', text: 'text-danger' },
          };
          const cc = colorClasses[action.color] ?? { bg: 'bg-default/10', text: 'text-default' };
          return (
          <Card
            key={action.path}
            shadow="sm"
            isPressable
            onPress={() => navigate(action.path)}
            className="hover:scale-[1.02] transition-transform"
          >
            <CardBody className="flex flex-row items-center gap-3 p-4">
              <div className={`p-2 rounded-lg ${cc.bg}`}>
                <action.icon size={20} className={cc.text} />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-sm">{action.label}</p>
                <p className="text-xs text-default-400 truncate">{action.description}</p>
              </div>
              <ChevronRight size={16} className="text-default-300 shrink-0" />
            </CardBody>
          </Card>
          );
        })}
      </div>

      {/* Recent Opportunities */}
      <Card shadow="sm" className="mb-6">
        <CardHeader><h3 className="text-lg font-semibold">{t('volunteering.recent_opportunities')}</h3></CardHeader>
        <CardBody>
          {opportunities.length === 0 ? (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Heart size={40} className="mb-2" />
              <p>{t('volunteering.no_opportunities_yet')}</p>
            </div>
          ) : (
            <div className="space-y-3">
              {opportunities.map((opp) => (
                <div key={opp.id} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                  <div>
                    <p className="font-medium">{opp.title}</p>
                    <p className="text-xs text-default-400">{t('volunteering.by_name', { name: `${opp.first_name} ${opp.last_name}` })}</p>
                  </div>
                  <Chip size="sm" variant="flat" color={['active', 'open'].includes(opp.status) ? 'success' : 'default'} className="capitalize">{opp.status}</Chip>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Activity Feed */}
      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold">{t('volunteering.activity_feed', 'Activity Feed')}</h3></CardHeader>
        <CardBody>
          {activitiesLoading ? (
            <div className="space-y-4">
              {[...Array(5)].map((_, i) => (
                <div key={i} className="flex items-start gap-3">
                  <Skeleton className="h-10 w-10 rounded-full shrink-0" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-3/4 rounded-lg" />
                    <Skeleton className="h-3 w-1/2 rounded-lg" />
                  </div>
                </div>
              ))}
            </div>
          ) : activities.length === 0 ? (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Clock size={40} className="mb-2" />
              <p>{t('volunteering.no_recent_activity', 'No recent activity')}</p>
            </div>
          ) : (
            <div className="space-y-4">
              {activities.map((item, idx) => {
                const typeColor = ACTIVITY_TYPE_COLORS[item.type] ?? 'default';
                return (
                  <div key={`${item.type}-${item.entity_id}-${idx}`} className="flex items-start gap-3">
                    <Avatar
                      src={item.avatar_url || undefined}
                      name={item.user_name}
                      size="sm"
                      className="shrink-0"
                    />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-sm font-medium">{item.user_name}</span>
                        <Chip size="sm" variant="flat" color={typeColor} className="capitalize">
                          {formatActivityType(item.type)}
                        </Chip>
                      </div>
                      <p className="text-sm text-default-500 mt-0.5">{item.description}</p>
                      <p className="text-xs text-default-400 mt-1">{formatTimestamp(item.timestamp)}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default VolunteeringOverview;
