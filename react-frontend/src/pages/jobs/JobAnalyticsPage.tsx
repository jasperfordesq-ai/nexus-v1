// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Job Analytics Page (J8) - Analytics dashboard for job posters
 *
 * Features:
 * - Total views, unique viewers, applications, conversion rate
 * - Views over time chart (last 30 days)
 * - Applications by stage breakdown
 * - Average time to apply, time to fill
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Button } from '@heroui/react';
import {
  BarChart3,
  Eye,
  Users,
  FileText,
  TrendingUp,
  Clock,
  ArrowLeft,
  RefreshCw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

interface AnalyticsData {
  job_id: number;
  total_views: number;
  unique_viewers: number;
  total_applications: number;
  conversion_rate: number;
  avg_time_to_apply_hours: number | null;
  time_to_fill_days: number | null;
  views_by_day: Array<{ date: string; count: number }>;
  applications_by_stage: Array<{ stage: string; count: number }>;
  created_at: string;
  status: string;
}

const STAGE_COLORS: Record<string, string> = {
  applied: 'bg-warning/20 text-warning',
  screening: 'bg-primary/20 text-primary',
  reviewed: 'bg-primary/20 text-primary',
  interview: 'bg-secondary/20 text-secondary',
  offer: 'bg-success/20 text-success',
  accepted: 'bg-success/20 text-success',
  rejected: 'bg-danger/20 text-danger',
  withdrawn: 'bg-default/20 text-default-500',
  pending: 'bg-warning/20 text-warning',
};

export function JobAnalyticsPage() {
  const { t } = useTranslation('jobs');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  usePageTitle(t('analytics.title'));

  const [analytics, setAnalytics] = useState<AnalyticsData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadAnalytics = useCallback(async () => {
    if (!id) return;
    setIsLoading(true);
    setError(null);
    try {
      const response = await api.get<AnalyticsData>(`/v2/jobs/${id}/analytics`);
      if (response.success && response.data) {
        setAnalytics(response.data);
      } else {
        setError(response.error || t('analytics.load_error', 'Failed to load analytics'));
      }
    } catch (err) {
      logError('Failed to load job analytics', err);
      setError(t('analytics.load_error', 'Failed to load analytics'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadAnalytics();
  }, [loadAnalytics]);

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="h-8 bg-theme-hover rounded w-1/3 animate-pulse" />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-4 bg-theme-hover rounded w-1/2 mb-2" />
              <div className="h-8 bg-theme-hover rounded w-3/4" />
            </GlassCard>
          ))}
        </div>
      </div>
    );
  }

  if (error || !analytics) {
    return (
      <EmptyState
        icon={<BarChart3 className="w-12 h-12" aria-hidden="true" />}
        title={error || t('analytics.no_data')}
        action={
          <div className="flex gap-2">
            <Link to={tenantPath(`/jobs/${id}`)}>
              <Button variant="flat" className="bg-theme-elevated text-theme-muted">
                {t('detail.browse_vacancies')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadAnalytics}
            >
              {t('try_again')}
            </Button>
          </div>
        }
      />
    );
  }

  // Find the max view count for the bar chart
  const maxViews = Math.max(...analytics.views_by_day.map((d) => Number(d.count)), 1);

  return (
    <div className="space-y-6">
      {/* Back nav */}
      <Link
        to={tenantPath(`/jobs/${id}`)}
        className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        {t('detail.browse_vacancies')}
      </Link>

      <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
        <BarChart3 className="w-7 h-7 text-blue-400" aria-hidden="true" />
        {t('analytics.title')}
      </h1>

      {/* Key Metrics */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <MetricCard
          icon={<Eye className="w-5 h-5 text-blue-400" aria-hidden="true" />}
          label={t('analytics.total_views')}
          value={analytics.total_views.toLocaleString()}
        />
        <MetricCard
          icon={<Users className="w-5 h-5 text-green-400" aria-hidden="true" />}
          label={t('analytics.unique_viewers')}
          value={analytics.unique_viewers.toLocaleString()}
        />
        <MetricCard
          icon={<FileText className="w-5 h-5 text-purple-400" aria-hidden="true" />}
          label={t('analytics.total_applications')}
          value={analytics.total_applications.toLocaleString()}
        />
        <MetricCard
          icon={<TrendingUp className="w-5 h-5 text-amber-400" aria-hidden="true" />}
          label={t('analytics.conversion_rate')}
          value={`${analytics.conversion_rate}%`}
        />
      </div>

      {/* Secondary metrics */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {analytics.avg_time_to_apply_hours !== null && (
          <GlassCard className="p-5">
            <div className="flex items-center gap-3">
              <Clock className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
              <div>
                <p className="text-xs text-theme-subtle">{t('analytics.avg_time_to_apply')}</p>
                <p className="text-xl font-bold text-theme-primary">
                  {analytics.avg_time_to_apply_hours} {t('analytics.hours')}
                </p>
              </div>
            </div>
          </GlassCard>
        )}
        {analytics.time_to_fill_days !== null && (
          <GlassCard className="p-5">
            <div className="flex items-center gap-3">
              <Clock className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
              <div>
                <p className="text-xs text-theme-subtle">{t('analytics.time_to_fill')}</p>
                <p className="text-xl font-bold text-theme-primary">
                  {analytics.time_to_fill_days} {t('analytics.days')}
                </p>
              </div>
            </div>
          </GlassCard>
        )}
      </div>

      {/* Views Over Time (simple bar chart) */}
      {analytics.views_by_day.length > 0 && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4">
            {t('analytics.views_over_time')}
          </h2>
          <div className="flex items-end gap-1 h-40">
            {analytics.views_by_day.map((day) => {
              const height = (Number(day.count) / maxViews) * 100;
              return (
                <div
                  key={day.date}
                  className="flex-1 flex flex-col items-center gap-1"
                >
                  <span className="text-[10px] text-theme-subtle">{Number(day.count)}</span>
                  <div
                    className="w-full bg-gradient-to-t from-indigo-500 to-purple-400 rounded-t min-h-[2px]"
                    style={{ height: `${Math.max(height, 2)}%` }}
                    title={`${day.date}: ${day.count} views`}
                  />
                  <span className="text-[9px] text-theme-subtle rotate-[-45deg] origin-top-left whitespace-nowrap">
                    {new Date(day.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                  </span>
                </div>
              );
            })}
          </div>
        </GlassCard>
      )}

      {/* Applications by Stage */}
      {analytics.applications_by_stage.length > 0 && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4">
            {t('analytics.applications_by_stage')}
          </h2>
          <div className="space-y-3">
            {analytics.applications_by_stage.map((item) => {
              const total = analytics.total_applications || 1;
              const pct = Math.round((Number(item.count) / total) * 100);
              return (
                <div key={item.stage} className="flex items-center gap-3">
                  <div className="w-28 text-sm text-theme-muted">
                    {t(`application_status.${item.stage}`, { defaultValue: item.stage })}
                  </div>
                  <div className="flex-1 h-6 bg-theme-hover rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full ${STAGE_COLORS[item.stage] ?? 'bg-primary/20'} flex items-center px-2`}
                      style={{ width: `${Math.max(pct, 5)}%` }}
                    >
                      <span className="text-xs font-medium">{item.count}</span>
                    </div>
                  </div>
                  <span className="text-xs text-theme-subtle w-12 text-right">{pct}%</span>
                </div>
              );
            })}
          </div>
        </GlassCard>
      )}
    </div>
  );
}

function MetricCard({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
}) {
  return (
    <GlassCard className="p-5">
      <div className="flex items-center gap-3">
        {icon}
        <div>
          <p className="text-xs text-theme-subtle">{label}</p>
          <p className="text-2xl font-bold text-theme-primary">{value}</p>
        </div>
      </div>
    </GlassCard>
  );
}

export default JobAnalyticsPage;
