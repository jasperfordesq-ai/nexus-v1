import { Button, Card, CardBody, CardHeader, Chip, Input, Spinner, Select, SelectItem, Table, TableBody, TableCell, TableColumn, TableHeader, TableRow } from '@/components/ui';
import { useCallback, useEffect, useMemo, useState } from 'react';

import {
  Area,
  CartesianGrid,
  ComposedChart,
  Legend,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import Landmark from 'lucide-react/icons/landmark';
import Building2 from 'lucide-react/icons/building-2';
import Activity from 'lucide-react/icons/activity';
import Clock from 'lucide-react/icons/clock';
import Info from 'lucide-react/icons/info';
import Users from 'lucide-react/icons/users';
import TrendingUp from 'lucide-react/icons/trending-up';
import TrendingDown from 'lucide-react/icons/trending-down';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * National Caring Community Dashboard
 *
 * Cross-cooperative super-admin dashboard. Designed for the national
 * foundation administrator overseeing every cooperative on the platform.
 *
 * Backend endpoints (all require `national.kiss_dashboard.view` permission):
 *   GET /api/v2/admin/national/kiss/summary
 *   GET /api/v2/admin/national/kiss/comparative
 *   GET /api/v2/admin/national/kiss/trend
 *   GET /api/v2/admin/national/kiss/cooperatives
 *
 * Privacy: every member count surfaces as a bracket; no PII is ever shown.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface CooperativeRef {
  tenant_id: number;
  slug: string;
  name: string;
  hours: number;
}

interface NationalSummary {
  cooperatives_count: number;
  active_cooperatives_count: number;
  total_approved_hours_national: number;
  total_active_members_bucket: string;
  total_recipients_reached_bucket: string;
  top_5_cooperatives_by_hours: CooperativeRef[];
  bottom_5_active_cooperatives_by_hours: CooperativeRef[];
  hours_growth_yoy_pct: number | null;
  active_tandems_total: number;
  safeguarding_reports_total: number;
  generated_at: string;
  period: { from: string; to: string };
}

type CoopStatus = 'thriving' | 'stable' | 'struggling';

interface ComparativeRow {
  tenant_id: number;
  slug: string;
  name: string;
  hours: number;
  members_bracket: string;
  recipients_bracket: string;
  active_tandems: number;
  retention_rate_pct: number;
  reciprocity_pct: number;
  status: CoopStatus;
}

interface TrendPoint {
  month: string;
  total_hours_all_cooperatives: number;
  active_cooperatives: number;
}

type SortKey = 'hours' | 'active_tandems' | 'retention_rate_pct' | 'reciprocity_pct' | 'name';
type SortDir = 'asc' | 'desc';

type PeriodPreset = 'this_month' | 'last_quarter' | 'last_year' | 'custom';

// ─────────────────────────────────────────────────────────────────────────────
// Date helpers
// ─────────────────────────────────────────────────────────────────────────────

function isoDate(d: Date): string {
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function rangeForPreset(preset: PeriodPreset, fallback: { from: string; to: string }): { from: string; to: string } {
  const now = new Date();
  if (preset === 'this_month') {
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    return { from: isoDate(from), to: isoDate(now) };
  }
  if (preset === 'last_quarter') {
    const from = new Date(now);
    from.setDate(from.getDate() - 90);
    return { from: isoDate(from), to: isoDate(now) };
  }
  if (preset === 'last_year') {
    const from = new Date(now);
    from.setFullYear(from.getFullYear() - 1);
    return { from: isoDate(from), to: isoDate(now) };
  }
  return fallback;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status chip colour
// ─────────────────────────────────────────────────────────────────────────────

const statusChip: Record<CoopStatus, { color: 'success' | 'warning' | 'danger' }> = {
  thriving: { color: 'success' },
  stable: { color: 'warning' },
  struggling: { color: 'danger' },
};

// ─────────────────────────────────────────────────────────────────────────────
// Page
// ─────────────────────────────────────────────────────────────────────────────

export function NationalKissDashboardPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('national_kiss_dashboard.meta.page_title'));
  const { showToast } = useToast();

  const todayIso = useMemo(() => isoDate(new Date()), []);
  const ninetyDaysAgoIso = useMemo(() => {
    const d = new Date();
    d.setDate(d.getDate() - 90);
    return isoDate(d);
  }, []);

  const [preset, setPreset] = useState<PeriodPreset>('last_quarter');
  const [periodFrom, setPeriodFrom] = useState<string>(ninetyDaysAgoIso);
  const [periodTo, setPeriodTo] = useState<string>(todayIso);

  const [summary, setSummary] = useState<NationalSummary | null>(null);
  const [comparative, setComparative] = useState<ComparativeRow[]>([]);
  const [trend, setTrend] = useState<TrendPoint[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  const [sortKey, setSortKey] = useState<SortKey>('hours');
  const [sortDir, setSortDir] = useState<SortDir>('desc');

  const handlePresetChange = useCallback((next: PeriodPreset) => {
    setPreset(next);
    if (next !== 'custom') {
      const r = rangeForPreset(next, { from: periodFrom, to: periodTo });
      setPeriodFrom(r.from);
      setPeriodTo(r.to);
    }
  }, [periodFrom, periodTo]);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ period_from: periodFrom, period_to: periodTo });
      const [summaryRes, comparativeRes, trendRes] = await Promise.all([
        api.get<NationalSummary>(`/v2/admin/national/kiss/summary?${params.toString()}`),
        api.get<{ rows: ComparativeRow[] }>(`/v2/admin/national/kiss/comparative?${params.toString()}`),
        api.get<{ trend: TrendPoint[] }>('/v2/admin/national/kiss/trend'),
      ]);
      setSummary(summaryRes.data ?? null);
      setComparative(comparativeRes.data?.rows ?? []);
      setTrend(trendRes.data?.trend ?? []);
    } catch (err) {
      const message = err instanceof Error ? err.message : t('national_kiss_dashboard.toasts.load_failed');
      showToast(message, 'error');
    } finally {
      setLoading(false);
    }
  }, [periodFrom, periodTo, showToast, t]);

  useEffect(() => {
    fetchAll();
  }, [fetchAll]);

  const sortedRows = useMemo(() => {
    const rows = [...comparative];
    rows.sort((a, b) => {
      const av = a[sortKey];
      const bv = b[sortKey];
      let cmp = 0;
      if (typeof av === 'number' && typeof bv === 'number') {
        cmp = av - bv;
      } else {
        cmp = String(av).localeCompare(String(bv));
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });
    return rows;
  }, [comparative, sortKey, sortDir]);

  const handleSort = useCallback((key: SortKey) => {
    if (sortKey === key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir(key === 'name' ? 'asc' : 'desc');
    }
  }, [sortKey]);

  const yoyChip = useMemo(() => {
    if (!summary || summary.hours_growth_yoy_pct === null) return null;
    const pct = summary.hours_growth_yoy_pct;
    const positive = pct >= 0;
    return (
      <Chip
        startContent={positive ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
        color={positive ? 'success' : 'danger'}
        variant="tertiary"
        size="sm"
      >
        {t('national_kiss_dashboard.metrics.yoy', {
          value: `${positive ? '+' : ''}${pct.toFixed(1)}%`,
        })}
      </Chip>
    );
  }, [summary, t]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('national_kiss_dashboard.meta.title')}
        description={t('national_kiss_dashboard.meta.description')}
        actions={
          <span className="inline-flex items-center gap-2 text-muted">
            <Landmark size={20} aria-hidden="true" />
          </span>
        }
      />

      <Card className="border-l-4 border-l-accent bg-accent-soft dark:bg-accent-soft" >
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-accent" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-accent dark:text-accent">{t('national_kiss_dashboard.about.title')}</p>
              <p className="text-muted">
                {t('national_kiss_dashboard.about.body_prefix')} Caring Community {t('national_kiss_dashboard.about.body_suffix')}
              </p>
              <p className="text-muted">
                {t('national_kiss_dashboard.about.health_prefix')} <strong>{t('national_kiss_dashboard.status.thriving')}</strong> =
                {t('national_kiss_dashboard.about.thriving_definition')} <strong>{t('national_kiss_dashboard.status.stable')}</strong> = {t('national_kiss_dashboard.about.stable_definition')}{' '}
                <strong>{t('national_kiss_dashboard.status.struggling')}</strong> = {t('national_kiss_dashboard.about.struggling_definition')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Health status legend */}
      <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-lg border border-border bg-surface-secondary px-3 py-2 text-xs text-muted">
        <span className="font-medium text-foreground">{t('national_kiss_dashboard.health_legend.title')}</span>
        <span className="flex items-center gap-1.5">
          <Chip size="sm" color="success" variant="soft">{t('national_kiss_dashboard.status.thriving')}</Chip>
          {t('national_kiss_dashboard.health_legend.thriving')}
        </span>
        <span className="flex items-center gap-1.5">
          <Chip size="sm" color="warning" variant="soft">{t('national_kiss_dashboard.status.stable')}</Chip>
          {t('national_kiss_dashboard.health_legend.stable')}
        </span>
        <span className="flex items-center gap-1.5">
          <Chip size="sm" color="danger" variant="soft">{t('national_kiss_dashboard.status.struggling')}</Chip>
          {t('national_kiss_dashboard.health_legend.struggling')}
        </span>
        <span className="ml-3 text-muted">
          {t('national_kiss_dashboard.health_legend.privacy_note')}
        </span>
      </div>

      {/* Period selector */}
      <Card>
        <CardBody className="flex flex-col gap-3 md:flex-row md:items-end">
          <Select
            label={t('national_kiss_dashboard.filters.period')}
            selectedKeys={[preset]}
            onSelectionChange={(keys) => {
              const next = Array.from(keys)[0] as PeriodPreset;
              if (next) handlePresetChange(next);
            }}
            className="md:max-w-xs"
            aria-label={t('national_kiss_dashboard.filters.period_aria')}
          >
            <SelectItem key="this_month" id="this_month">{t('national_kiss_dashboard.periods.this_month')}</SelectItem>
            <SelectItem key="last_quarter" id="last_quarter">{t('national_kiss_dashboard.periods.last_quarter')}</SelectItem>
            <SelectItem key="last_year" id="last_year">{t('national_kiss_dashboard.periods.last_year')}</SelectItem>
            <SelectItem key="custom" id="custom">{t('national_kiss_dashboard.periods.custom')}</SelectItem>
          </Select>
          <Input
            type="date"
            label={t('national_kiss_dashboard.filters.from')}
            value={periodFrom}
            onChange={(e) => { setPeriodFrom(e.target.value); setPreset('custom'); }}
            className="md:max-w-xs"
            aria-label={t('national_kiss_dashboard.filters.from_aria')}
          />
          <Input
            type="date"
            label={t('national_kiss_dashboard.filters.to')}
            value={periodTo}
            onChange={(e) => { setPeriodTo(e.target.value); setPreset('custom'); }}
            className="md:max-w-xs"
            aria-label={t('national_kiss_dashboard.filters.to_aria')}
          />
          <Button onPress={fetchAll} isLoading={loading}>
            {t('national_kiss_dashboard.actions.refresh')}
          </Button>
        </CardBody>
      </Card>

      {/* Summary stats */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label={t('national_kiss_dashboard.stats.total_cooperatives')}
          value={summary?.cooperatives_count ?? '—'}
          icon={Building2}
          loading={loading}
        />
        <StatCard
          label={t('national_kiss_dashboard.stats.active_cooperatives')}
          value={summary?.active_cooperatives_count ?? '—'}
          icon={Activity}
          color="success"
          description={summary ? t('national_kiss_dashboard.stats.active_description', {
            active: summary.active_cooperatives_count,
            total: summary.cooperatives_count,
          }) : undefined}
          loading={loading}
        />
        <StatCard
          label={t('national_kiss_dashboard.stats.total_hours')}
          value={summary?.total_approved_hours_national.toFixed(1) ?? '—'}
          icon={Clock}
          color="default"
          loading={loading}
        />
        <StatCard
          label={t('national_kiss_dashboard.stats.active_tandems')}
          value={summary?.active_tandems_total ?? '—'}
          icon={Users}
          color="warning"
          loading={loading}
        />
      </div>

      {/* Inline secondary stats */}
      {summary && !loading && (
        <Card>
          <CardBody className="flex flex-wrap items-center gap-3 text-sm">
            <span className="text-muted">{t('national_kiss_dashboard.inline.active_members')}</span>
            <Chip variant="soft">{summary.total_active_members_bucket}</Chip>
            <span className="text-muted">{t('national_kiss_dashboard.inline.recipients_reached')}</span>
            <Chip variant="soft">{summary.total_recipients_reached_bucket}</Chip>
            <span className="text-muted">{t('national_kiss_dashboard.inline.safeguarding_reports')}</span>
            <Chip variant="soft">{summary.safeguarding_reports_total}</Chip>
            {yoyChip}
          </CardBody>
        </Card>
      )}

      {/* National trend chart */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Activity size={18} aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('national_kiss_dashboard.trend.title')}</h2>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div role="status" aria-busy="true" aria-label={t('national_kiss_dashboard.loading')} className="flex h-72 items-center justify-center">
              <Spinner />
            </div>
          ) : trend.length === 0 ? (
            <p className="text-sm text-muted">{t('national_kiss_dashboard.trend.empty')}</p>
          ) : (
            <div role="img" aria-label={t('national.trend_chart_aria')} className="h-72 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <ComposedChart data={trend} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border, #e5e7eb)" />
                  <XAxis dataKey="month" stroke="var(--color-text-muted, #6b7280)" />
                  <YAxis
                    yAxisId="left"
                    stroke="var(--color-text-muted, #6b7280)"
                    label={{ value: t('national_kiss_dashboard.trend.hours_axis'), angle: -90, position: 'insideLeft', style: { fontSize: 11, fill: 'var(--color-text-muted, #6b7280)' } }}
                  />
                  <YAxis
                    yAxisId="right"
                    orientation="right"
                    stroke="var(--color-text-muted, #6b7280)"
                    label={{ value: t('national_kiss_dashboard.trend.cooperatives_axis'), angle: 90, position: 'insideRight', style: { fontSize: 11, fill: 'var(--color-text-muted, #6b7280)' } }}
                  />
                  <Tooltip />
                  <Legend />
                  <Area
                    yAxisId="right"
                    type="monotone"
                    dataKey="active_cooperatives"
                    name={t('national_kiss_dashboard.trend.active_cooperatives')}
                    fill="rgba(99, 102, 241, 0.15)"
                    stroke="rgba(99, 102, 241, 0.6)"
                  />
                  <Line
                    yAxisId="left"
                    type="monotone"
                    dataKey="total_hours_all_cooperatives"
                    name={t('national_kiss_dashboard.trend.total_hours_all')}
                    stroke="#0ea5e9"
                    strokeWidth={2}
                    dot={false}
                  />
                </ComposedChart>
              </ResponsiveContainer>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Comparative table */}
      <Card>
        <CardHeader className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">{t('national_kiss_dashboard.comparative.title')}</h2>
          <span className="text-xs text-muted">{t('national_kiss_dashboard.comparative.sort_hint')}</span>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div role="status" aria-busy="true" aria-label={t('national_kiss_dashboard.loading')} className="flex h-32 items-center justify-center">
              <Spinner />
            </div>
          ) : sortedRows.length === 0 ? (
            <p className="text-sm text-muted">
              {t('national_kiss_dashboard.comparative.empty_prefix')} Caring Community {t('national_kiss_dashboard.comparative.empty_middle')} <strong>{t('national_kiss_dashboard.comparative.super_admin_tenants')}</strong> {t('national_kiss_dashboard.comparative.empty_suffix')}{' '}
              {t('national_kiss_dashboard.comparative.empty_tail')}
            </p>
          ) : (
            <Table
              aria-label={t('national_kiss_dashboard.comparative.aria')}
              removeWrapper
              isHeaderSticky
              classNames={{ base: 'overflow-x-auto' }}
            >
              <TableHeader>
                <TableColumn
                  aria-sort={sortKey === 'name' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  className="cursor-pointer"
                >
                  <button
                    type="button"
                    className="w-full text-left font-inherit bg-transparent border-0 p-0 cursor-pointer"
                    onClick={() => handleSort('name')}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSort('name'); } }}
                  >{t('national_kiss_dashboard.comparative.cooperative')}</button>
                </TableColumn>
                <TableColumn
                  aria-sort={sortKey === 'hours' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  className="cursor-pointer text-right"
                >
                  <button
                    type="button"
                    className="w-full text-right font-inherit bg-transparent border-0 p-0 cursor-pointer"
                    onClick={() => handleSort('hours')}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSort('hours'); } }}
                  ><span title={t('national_kiss_dashboard.comparative.hours_title')}>{t('national_kiss_dashboard.comparative.hours_sort')}</span></button>
                </TableColumn>
                <TableColumn><span title={t('national_kiss_dashboard.comparative.members_title')}>{t('national_kiss_dashboard.comparative.members')}</span></TableColumn>
                <TableColumn><span title={t('national_kiss_dashboard.comparative.recipients_title')}>{t('national_kiss_dashboard.comparative.recipients')}</span></TableColumn>
                <TableColumn
                  aria-sort={sortKey === 'active_tandems' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  className="cursor-pointer text-right"
                >
                  <button
                    type="button"
                    className="w-full text-right font-inherit bg-transparent border-0 p-0 cursor-pointer"
                    onClick={() => handleSort('active_tandems')}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSort('active_tandems'); } }}
                  ><span title={t('national_kiss_dashboard.comparative.tandems_title')}>{t('national_kiss_dashboard.comparative.tandems_sort')}</span></button>
                </TableColumn>
                <TableColumn
                  aria-sort={sortKey === 'retention_rate_pct' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  className="cursor-pointer text-right"
                >
                  <button
                    type="button"
                    className="w-full text-right font-inherit bg-transparent border-0 p-0 cursor-pointer"
                    onClick={() => handleSort('retention_rate_pct')}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSort('retention_rate_pct'); } }}
                  ><span title={t('national_kiss_dashboard.comparative.retention_title')}>{t('national_kiss_dashboard.comparative.retention_sort')}</span></button>
                </TableColumn>
                <TableColumn
                  aria-sort={sortKey === 'reciprocity_pct' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  className="cursor-pointer text-right"
                >
                  <button
                    type="button"
                    className="w-full text-right font-inherit bg-transparent border-0 p-0 cursor-pointer"
                    onClick={() => handleSort('reciprocity_pct')}
                    onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSort('reciprocity_pct'); } }}
                  ><span title={t('national_kiss_dashboard.comparative.reciprocity_title')}>{t('national_kiss_dashboard.comparative.reciprocity_sort')}</span></button>
                </TableColumn>
                <TableColumn>{t('national_kiss_dashboard.comparative.status')}</TableColumn>
              </TableHeader>
              <TableBody>
                {sortedRows.map((row) => (
                  <TableRow key={row.tenant_id}>
                    <TableCell className="font-medium">{row.name}</TableCell>
                    <TableCell className="text-right">{row.hours.toFixed(1)}</TableCell>
                    <TableCell><Chip variant="soft" size="sm">{row.members_bracket}</Chip></TableCell>
                    <TableCell><Chip variant="soft" size="sm">{row.recipients_bracket}</Chip></TableCell>
                    <TableCell className="text-right">{row.active_tandems}</TableCell>
                    <TableCell className="text-right">{row.retention_rate_pct.toFixed(1)}%</TableCell>
                    <TableCell className="text-right">{row.reciprocity_pct.toFixed(1)}%</TableCell>
                    <TableCell>
                      <Chip color={statusChip[row.status].color} variant="soft" size="sm">
                        {t(`national_kiss_dashboard.status.${row.status}`)}
                      </Chip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Top / Bottom 5 leaderboards */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="flex items-center gap-2">
            <TrendingUp size={18} className="text-success" aria-hidden="true" />
            <h2 className="text-lg font-semibold">{t('national_kiss_dashboard.leaderboards.top_title')}</h2>
          </CardHeader>
          <CardBody>
            {summary && summary.top_5_cooperatives_by_hours.length > 0 ? (
              <ol className="space-y-2">
                {summary.top_5_cooperatives_by_hours.map((c, idx) => (
                  <li key={c.tenant_id} className="flex items-center justify-between text-sm">
                    <span><span className="font-mono text-muted">#{idx + 1}</span> {c.name}</span>
                    <Chip variant="soft" size="sm" color="success">{c.hours.toFixed(1)} h</Chip>
                  </li>
                ))}
              </ol>
            ) : (
              <p className="text-sm text-muted">{t('national_kiss_dashboard.empty.no_data')}</p>
            )}
          </CardBody>
        </Card>
        <Card>
          <CardHeader className="flex items-center gap-2">
            <TrendingDown size={18} className="text-danger" aria-hidden="true" />
            <h2 className="text-lg font-semibold">{t('national_kiss_dashboard.leaderboards.bottom_title')}</h2>
          </CardHeader>
          <CardBody>
            {summary && summary.bottom_5_active_cooperatives_by_hours.length > 0 ? (
              <ol className="space-y-2">
                {summary.bottom_5_active_cooperatives_by_hours.map((c, idx) => (
                  <li key={c.tenant_id} className="flex items-center justify-between text-sm">
                    <span><span className="font-mono text-muted">#{idx + 1}</span> {c.name}</span>
                    <Chip variant="soft" size="sm" color="danger">{c.hours.toFixed(1)} h</Chip>
                  </li>
                ))}
              </ol>
            ) : (
              <p className="text-sm text-muted">{t('national_kiss_dashboard.empty.no_active_cooperatives')}</p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Footer */}
      {summary && (
        <p className="text-xs text-muted">
          {t('national_kiss_dashboard.footer.generated_at', {
            date: new Date(summary.generated_at).toLocaleString(),
          })}{' '}
          {t('national_kiss_dashboard.footer.privacy_note')}
        </p>
      )}
    </div>
  );
}

export default NationalKissDashboardPage;
