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

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Button, Chip, Spinner } from '@heroui/react';
import {
  BarChart3,
  Eye,
  Users,
  FileText,
  TrendingUp,
  Clock,
  ArrowLeft,
  RefreshCw,
  Download,
  Share2,
  Star,
  Sparkles,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTenant } from '@/contexts';
import { api, API_BASE } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';

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
  weekly_trend: { week: string; count: number }[];
  referral_stats: { total_shares: number; referral_applications: number; referral_conversion_pct: number } | null;
  scorecard_avg: number | null;
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
  const [predictions, setPredictions] = useState<{
    expected_applications: { value: number; current: number; label: string };
    estimated_time_to_fill: { value: number | null; days_posted: number; label: string };
    conversion_rate: { yours: number; average: number; label: string };
    salary_comparison: { your_salary: number; market_avg: number; diff_percent: number; label: string } | null;
    similar_jobs_analyzed: number;
    ai_insights?: string[];
  } | null>(null);
  const [predictionsLoading, setPredictionsLoading] = useState(true);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable ref for t — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;

  const loadAnalytics = useCallback(async () => {
    if (!id) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setIsLoading(true);
    setError(null);
    try {
      const response = await api.get<AnalyticsData>(`/v2/jobs/${id}/analytics`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setAnalytics(response.data);
      } else {
        setError(response.error || tRef.current('analytics.load_error', 'Failed to load analytics'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load job analytics', err);
      setError(tRef.current('analytics.load_error', 'Failed to load analytics'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadAnalytics();
  }, [loadAnalytics]);

  const loadPredictions = useCallback(async () => {
    if (!id) return;
    setPredictionsLoading(true);
    try {
      const res = await api.get(`/v2/jobs/${id}/predictions`);
      if (res.success && res.data) setPredictions(res.data as typeof predictions);
    } catch { /* silent */ }
    finally { setPredictionsLoading(false); }
  }, [id]);

  useEffect(() => { loadPredictions(); }, [loadPredictions]);

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

  const handleExportCsv = () => {
    window.open(API_BASE + `/v2/jobs/${id}/applications/export-csv`, '_blank');
  };

  return (
    <div className="space-y-6">
      <PageMeta title="Job Analytics" noIndex />
      {/* Back nav */}
      <Link
        to={tenantPath(`/jobs/${id}`)}
        className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        {t('detail.browse_vacancies')}
      </Link>

      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <BarChart3 className="w-7 h-7 text-blue-400" aria-hidden="true" />
          {t('analytics.title')}
        </h1>
        <Button
          variant="flat"
          className="bg-theme-elevated text-theme-muted self-start sm:self-auto"
          startContent={<Download className="w-4 h-4" aria-hidden="true" />}
          onPress={handleExportCsv}
        >
          {t('analytics.export_csv', 'Export CSV')}
        </Button>
      </div>

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

      {/* Referral stats + Scorecard chips */}
      {(analytics.referral_stats || analytics.scorecard_avg !== null) && (
        <div className="flex flex-wrap gap-3">
          {analytics.referral_stats && (
            <>
              <GlassCard className="p-4 flex items-center gap-3">
                <Share2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('analytics.referral_shares', 'Total Shares')}</p>
                  <p className="text-lg font-bold text-theme-primary">{analytics.referral_stats.total_shares.toLocaleString()}</p>
                </div>
              </GlassCard>
              <GlassCard className="p-4 flex items-center gap-3">
                <Users className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('analytics.referral_apps', 'Referral Applications')}</p>
                  <p className="text-lg font-bold text-theme-primary">{analytics.referral_stats.referral_applications.toLocaleString()}</p>
                </div>
              </GlassCard>
              <GlassCard className="p-4 flex items-center gap-3">
                <TrendingUp className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                <div>
                  <p className="text-xs text-theme-subtle">{t('analytics.referral_conversion', 'Referral Conversion')}</p>
                  <p className="text-lg font-bold text-theme-primary">{analytics.referral_stats.referral_conversion_pct}%</p>
                </div>
              </GlassCard>
            </>
          )}
          {analytics.scorecard_avg !== null && (
            <GlassCard className="p-4 flex items-center gap-3">
              <Star className={`w-4 h-4 ${analytics.scorecard_avg >= 60 ? 'text-success' : 'text-warning'}`} aria-hidden="true" />
              <div>
                <p className="text-xs text-theme-subtle">{t('analytics.scorecard_avg', 'Avg Scorecard')}</p>
                <p className={`text-lg font-bold ${analytics.scorecard_avg >= 60 ? 'text-success' : 'text-warning'}`}>
                  {analytics.scorecard_avg}%
                </p>
              </div>
            </GlassCard>
          )}
        </div>
      )}

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

      {/* Weekly Application Trend */}
      {analytics.weekly_trend && analytics.weekly_trend.length > 0 && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4">
            {t('analytics.weekly_trend', 'Weekly Applications (last 8 weeks)')}
          </h2>
          {(() => {
            const maxWeeklyCount = Math.max(...analytics.weekly_trend.map((w) => Number(w.count)), 1);
            return (
              <div className="flex items-end gap-1 h-40">
                {analytics.weekly_trend.map((week) => {
                  const height = (Number(week.count) / maxWeeklyCount) * 100;
                  return (
                    <div
                      key={week.week}
                      className="flex-1 flex flex-col items-center gap-1"
                    >
                      <span className="text-[10px] text-theme-subtle">{Number(week.count)}</span>
                      <div
                        className="w-full bg-gradient-to-t from-purple-500 to-indigo-400 rounded-t min-h-[2px]"
                        style={{ height: `${Math.max(height, 2)}%` }}
                        title={`${week.week}: ${week.count} applications`}
                      />
                      <span className="text-[9px] text-theme-subtle rotate-[-45deg] origin-top-left whitespace-nowrap">
                        {week.week}
                      </span>
                    </div>
                  );
                })}
              </div>
            );
          })()}
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

      {/* AI Predictions */}
      <GlassCard className="p-6">
        <div className="flex items-center gap-2 mb-4">
          <Sparkles size={20} className="text-secondary" />
          <h2 className="text-lg font-semibold text-theme-primary">
            {t('analytics.predictions', { defaultValue: 'AI Predictions' })}
          </h2>
          {predictions && (
            <Chip size="sm" variant="flat" color="default">
              {t('analytics.based_on', { defaultValue: 'Based on {{count}} similar jobs', count: predictions.similar_jobs_analyzed })}
            </Chip>
          )}
        </div>

        {predictionsLoading ? (
          <div className="flex justify-center py-8"><Spinner size="lg" /></div>
        ) : !predictions ? (
          <p className="text-center text-default-400 py-6">{t('analytics.no_predictions', { defaultValue: 'Insufficient data for predictions.' })}</p>
        ) : (
          <div className="space-y-4">
            {/* Prediction cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div className="p-4 rounded-lg bg-default-50 border border-default-200">
                <p className="text-xs text-default-500 uppercase tracking-wide">{t('analytics.expected_apps', { defaultValue: 'Expected Applications' })}</p>
                <p className="text-2xl font-bold text-foreground mt-1">{predictions.expected_applications.value}</p>
                <p className="text-xs mt-1">
                  <span className="text-default-400">Current: {predictions.expected_applications.current}</span>
                  {' · '}
                  <Chip size="sm" variant="flat" color={predictions.expected_applications.current >= predictions.expected_applications.value ? 'success' : 'warning'}>
                    {predictions.expected_applications.label}
                  </Chip>
                </p>
              </div>

              <div className="p-4 rounded-lg bg-default-50 border border-default-200">
                <p className="text-xs text-default-500 uppercase tracking-wide">{t('analytics.time_to_fill', { defaultValue: 'Est. Time to Fill' })}</p>
                <p className="text-2xl font-bold text-foreground mt-1">{predictions.estimated_time_to_fill.value ? `${predictions.estimated_time_to_fill.value}d` : 'N/A'}</p>
                <p className="text-xs text-default-400 mt-1">Posted {predictions.estimated_time_to_fill.days_posted} days ago</p>
              </div>

              <div className="p-4 rounded-lg bg-default-50 border border-default-200">
                <p className="text-xs text-default-500 uppercase tracking-wide">{t('analytics.conversion_comparison', { defaultValue: 'Conversion Rate' })}</p>
                <p className="text-2xl font-bold text-foreground mt-1">{predictions.conversion_rate.yours}%</p>
                <p className="text-xs mt-1">
                  <span className="text-default-400">Avg: {predictions.conversion_rate.average}%</span>
                  {' · '}
                  <Chip size="sm" variant="flat" color={predictions.conversion_rate.yours >= predictions.conversion_rate.average ? 'success' : 'warning'}>
                    {predictions.conversion_rate.label}
                  </Chip>
                </p>
              </div>
            </div>

            {/* Salary comparison */}
            {predictions.salary_comparison && (
              <div className="p-4 rounded-lg bg-default-50 border border-default-200">
                <p className="text-xs text-default-500 uppercase tracking-wide mb-2">{t('analytics.salary_comparison', { defaultValue: 'Salary vs Market' })}</p>
                <div className="flex items-center gap-4">
                  <div>
                    <p className="text-sm text-default-500">Yours</p>
                    <p className="text-lg font-bold">{predictions.salary_comparison.your_salary.toLocaleString()}</p>
                  </div>
                  <div className="text-default-300">vs</div>
                  <div>
                    <p className="text-sm text-default-500">Market Avg</p>
                    <p className="text-lg font-bold">{predictions.salary_comparison.market_avg.toLocaleString()}</p>
                  </div>
                  <Chip size="sm" variant="flat" color={predictions.salary_comparison.diff_percent >= 0 ? 'success' : 'danger'}>
                    {predictions.salary_comparison.diff_percent > 0 ? '+' : ''}{predictions.salary_comparison.diff_percent}% {predictions.salary_comparison.label}
                  </Chip>
                </div>
              </div>
            )}

            {/* AI Insights */}
            {predictions.ai_insights && predictions.ai_insights.length > 0 && (
              <div className="p-4 rounded-lg bg-secondary/5 border border-secondary/20">
                <div className="flex items-center gap-2 mb-3">
                  <Sparkles size={16} className="text-secondary" />
                  <p className="text-sm font-semibold text-secondary">{t('analytics.ai_insights', { defaultValue: 'AI Insights' })}</p>
                </div>
                <ul className="space-y-2">
                  {predictions.ai_insights.map((insight, i) => (
                    <li key={i} className="text-sm text-foreground flex items-start gap-2">
                      <span className="text-secondary mt-0.5">•</span>
                      <span>{insight}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}
      </GlassCard>
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
