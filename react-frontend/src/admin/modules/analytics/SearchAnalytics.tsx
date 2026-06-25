// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Search Analytics Dashboard
 *
 * Visualizes member search behaviour: volume, unique queries, zero-result
 * rate, trending queries, and content gaps (zero-result searches).
 * Backend: GET /v2/admin/search/{analytics,trending,zero-results}
 */

import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

import SearchIcon from 'lucide-react/icons/search';
import TrendingUp from 'lucide-react/icons/trending-up';
import SearchX from 'lucide-react/icons/search-x';
import ListFilter from 'lucide-react/icons/list-filter';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import BarChart3 from 'lucide-react/icons/chart-column';
import Hash from 'lucide-react/icons/hash';
import Percent from 'lucide-react/icons/percent';
import AlertTriangle from 'lucide-react/icons/triangle-alert';

import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Select,
  SelectItem,
  Spinner,
} from '@/components/ui';
import { CHART_COLOR_MAP } from '@/lib/chartColors';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useAdminPageMeta } from '../../AdminMetaContext';
import {
  adminSearchAnalytics,
  type SearchAnalyticsSummary,
  type TrendingSearch,
  type ZeroResultSearch,
} from '../../api/adminApi';
import { StatCard } from '../../components/StatCard';
import { PageHeader } from '../../components/PageHeader';

const PERIOD_OPTIONS = [7, 14, 30, 60, 90] as const;

function formatDate(dateStr: string): string {
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function SearchAnalytics() {
  const { t } = useTranslation('admin');
  usePageTitle(t('search_analytics.page_title'));
  useAdminPageMeta({ title: t('search_analytics.page_title') });
  const toast = useToast();

  const [days, setDays] = useState<number>(30);
  const [summary, setSummary] = useState<SearchAnalyticsSummary | null>(null);
  const [trending, setTrending] = useState<TrendingSearch[]>([]);
  const [zeroResults, setZeroResults] = useState<ZeroResultSearch[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const [summaryRes, trendingRes, zeroRes] = await Promise.all([
        adminSearchAnalytics.getSummary(days),
        adminSearchAnalytics.getTrending(days, 20),
        adminSearchAnalytics.getZeroResults(days, 20),
      ]);

      if (summaryRes.success && summaryRes.data) {
        setSummary(summaryRes.data);
      } else {
        setLoadError(summaryRes.error || t('search_analytics.load_failed'));
        toast.error(summaryRes.error || t('search_analytics.load_failed'));
      }

      setTrending(trendingRes.success && Array.isArray(trendingRes.data) ? trendingRes.data : []);
      setZeroResults(zeroRes.success && Array.isArray(zeroRes.data) ? zeroRes.data : []);
    } catch {
      setLoadError(t('search_analytics.load_failed'));
      toast.error(t('search_analytics.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [days, t, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const periodLabel = (d: number) => t(`search_analytics.days_${d}`);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('search_analytics.page_title')}
        description={t('search_analytics.description')}
        actions={
          <div className="flex items-center gap-2">
            <Select
              aria-label={t('search_analytics.period_label')}
              selectedKeys={new Set([String(days)])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                if (selected) setDays(parseInt(String(selected), 10));
              }}
              variant="secondary"
              className="w-44"
              size="sm"
            >
              {PERIOD_OPTIONS.map((d) => (
                <SelectItem key={String(d)} id={String(d)}>
                  {periodLabel(d)}
                </SelectItem>
              ))}
            </Select>
            <Button
              size="sm"
              variant="secondary"
              startContent={<RefreshCw size={14} />}
              onPress={loadData}
              isDisabled={loading}
            >
              {t('common.refresh')}
            </Button>
          </div>
        }
      />

      {loading ? (
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex h-48 items-center justify-center">
          <Spinner size="sm" />
        </div>
      ) : loadError ? (
        <Card role="alert">
          <CardBody className="flex flex-col items-center gap-3 py-10 text-center">
            <AlertTriangle aria-hidden="true" size={32} className="text-danger" />
            <div className="text-base font-semibold">{t('common.error_loading_data')}</div>
            <div className="text-sm text-muted">{loadError}</div>
            <Button variant="tertiary" onPress={loadData}>{t('common.retry')}</Button>
          </CardBody>
        </Card>
      ) : summary ? (
        <>
          {/* KPI cards */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
              label={t('search_analytics.total_searches')}
              value={summary.total_searches.toLocaleString()}
              icon={SearchIcon}
              color="primary"
            />
            <StatCard
              label={t('search_analytics.unique_queries')}
              value={summary.unique_queries.toLocaleString()}
              icon={Hash}
              color="secondary"
            />
            <StatCard
              label={t('search_analytics.zero_result_rate')}
              value={`${summary.zero_result_rate}%`}
              icon={Percent}
              color={summary.zero_result_rate > 25 ? 'danger' : summary.zero_result_rate > 10 ? 'warning' : 'success'}
            />
            <StatCard
              label={t('search_analytics.avg_results')}
              value={summary.avg_results.toLocaleString()}
              icon={BarChart3}
              color="default"
            />
          </div>

          {/* Daily volume chart */}
          <Card>
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <BarChart3 size={18} className="text-accent" aria-hidden="true" />
              <h3 className="font-semibold">{t('search_analytics.daily_volume')}</h3>
            </CardHeader>
            <CardBody className="px-4 pb-4">
              {summary.daily_volume.length > 0 ? (
                <div role="img" aria-label={t('search_analytics.daily_volume')}>
                  <ResponsiveContainer width="100%" height={300}>
                    <AreaChart data={summary.daily_volume} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                      <defs>
                        <linearGradient id="searchVolumeGradient" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0.5} />
                          <stop offset="95%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0.05} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" stroke="currentColor" className="text-border" />
                      <XAxis dataKey="date" tick={{ fontSize: 11 }} className="text-muted" />
                      <YAxis tick={{ fontSize: 11 }} className="text-muted" allowDecimals={false} />
                      <Tooltip
                        contentStyle={{
                          borderRadius: '8px',
                          border: '1px solid var(--color-border)',
                          backgroundColor: 'var(--color-surface)',
                          color: 'var(--color-foreground)',
                        }}
                        labelStyle={{ fontWeight: 600 }}
                      />
                      <Area
                        type="monotone"
                        dataKey="count"
                        name={t('search_analytics.searches')}
                        stroke={CHART_COLOR_MAP.primary}
                        fill="url(#searchVolumeGradient)"
                        strokeWidth={2}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              ) : (
                <p className="flex h-[300px] items-center justify-center text-sm text-muted">
                  {t('search_analytics.empty_description')}
                </p>
              )}
            </CardBody>
          </Card>

          {/* Searches by type */}
          <Card>
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <ListFilter size={18} className="text-accent" aria-hidden="true" />
              <h3 className="font-semibold">{t('search_analytics.searches_by_type')}</h3>
            </CardHeader>
            <CardBody className="px-4 pb-4">
              {summary.searches_by_type.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {summary.searches_by_type.map((entry) => (
                    <Chip key={entry.type ?? 'unknown'} size="md" variant="soft" color="primary">
                      {(entry.type || t('common.unknown'))}: {entry.count.toLocaleString()}
                    </Chip>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted">{t('search_analytics.empty_description')}</p>
              )}
            </CardBody>
          </Card>

          {/* Trending + Zero-result tables */}
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <TrendingUp size={18} className="text-success" aria-hidden="true" />
                <h3 className="font-semibold">{t('search_analytics.trending_title')}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {trending.length === 0 ? (
                  <p className="py-6 text-center text-sm text-muted">{t('search_analytics.no_trending')}</p>
                ) : (
                  <ul className="divide-y divide-divider">
                    {trending.map((row, idx) => (
                      <li key={`${row.query}-${idx}`} className="flex items-center gap-3 py-2">
                        <span className="w-6 shrink-0 text-right text-xs font-semibold text-muted">{idx + 1}</span>
                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{row.query}</span>
                        <Chip size="sm" variant="soft" color="success">
                          {t('search_analytics.count_value', { count: row.count })}
                        </Chip>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <SearchX size={18} className="text-warning" aria-hidden="true" />
                <h3 className="font-semibold">{t('search_analytics.zero_results_title')}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {zeroResults.length === 0 ? (
                  <p className="py-6 text-center text-sm text-muted">{t('search_analytics.no_zero_results')}</p>
                ) : (
                  <ul className="divide-y divide-divider">
                    {zeroResults.map((row, idx) => (
                      <li key={`${row.query}-${idx}`} className="flex items-center gap-3 py-2">
                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{row.query}</span>
                        <span className="shrink-0 text-xs text-muted">
                          {t('search_analytics.last_searched')}: {formatDate(row.last_searched)}
                        </span>
                        <Chip size="sm" variant="soft" color="warning">
                          {t('search_analytics.count_value', { count: row.count })}
                        </Chip>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>
          </div>
        </>
      ) : null}
    </div>
  );
}

export default SearchAnalytics;
