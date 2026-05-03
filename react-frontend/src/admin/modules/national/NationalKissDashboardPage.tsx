// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * National KISS Foundation Dashboard
 *
 * Cross-cooperative super-admin dashboard. Designed for the national KISS
 * foundation administrator (think: what Martin Villiger himself would use).
 *
 * Backend endpoints (all require `national.kiss_dashboard.view` permission):
 *   GET /api/v2/admin/national/kiss/summary
 *   GET /api/v2/admin/national/kiss/comparative
 *   GET /api/v2/admin/national/kiss/trend
 *   GET /api/v2/admin/national/kiss/cooperatives
 *
 * Privacy: every member count surfaces as a bracket; no PII is ever shown.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Input,
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
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
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, StatCard, Abbr } from '../../components';

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

const statusChip: Record<CoopStatus, { color: 'success' | 'warning' | 'danger'; label: string }> = {
  thriving: { color: 'success', label: 'Thriving' },
  stable: { color: 'warning', label: 'Stable' },
  struggling: { color: 'danger', label: 'Struggling' },
};

// ─────────────────────────────────────────────────────────────────────────────
// Page
// ─────────────────────────────────────────────────────────────────────────────

export function NationalKissDashboardPage() {
  usePageTitle('Fondation KISS — National Dashboard');
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
      const message = err instanceof Error ? err.message : 'Failed to load dashboard';
      showToast(message, 'error');
    } finally {
      setLoading(false);
    }
  }, [periodFrom, periodTo, showToast]);

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
        variant="flat"
        size="sm"
      >
        {positive ? '+' : ''}{pct.toFixed(1)}% YoY
      </Chip>
    );
  }, [summary]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Fondation KISS — National Dashboard"
        description="Cross-cooperative super-admin view across every KISS cooperative on the platform. Member counts are reported in privacy-preserving brackets."
        actions={
          <span className="inline-flex items-center gap-2 text-default-500">
            <Landmark size={20} />
          </span>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this dashboard</p>
              <p className="text-default-600">
                KISS (Koordination und Innovation für Soziales) is a Swiss methodology for community-based care
                coordination developed with Age-Stiftung. This super-admin dashboard aggregates metrics across every
                NEXUS cooperative running the Caring Community programme nationally — giving the Fondation <Abbr term="KISS">KISS</Abbr>
                a cross-cooperative view of hours exchanged, member participation, and cooperative health. Member
                counts are shown as privacy-preserving brackets (e.g. "50–99") rather than exact numbers.
              </p>
              <p className="text-default-500">
                Use the period selector to compare quarters. The comparative table ranks cooperatives by hours,
                members, or health status. Health status is derived from activity trends: <strong>Thriving</strong> =
                growing fast, <strong>Growing</strong> = steady increase, <strong>Stable</strong> = flat activity,{' '}
                <strong>Struggling</strong> = declining activity or very low engagement.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Health status legend */}
      <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-lg border border-default-200 bg-default-50 px-3 py-2 text-xs text-default-500">
        <span className="font-medium text-default-700">Health status key:</span>
        <span className="flex items-center gap-1.5">
          <Chip size="sm" color="success" variant="flat">Thriving</Chip>
          hours growing strongly (&gt;10% vs prior period)
        </span>
        <span className="flex items-center gap-1.5">
          <Chip size="sm" color="warning" variant="flat">Stable</Chip>
          flat activity (±10%)
        </span>
        <span className="flex items-center gap-1.5">
          <Chip size="sm" color="danger" variant="flat">Struggling</Chip>
          declining activity or fewer than 5 verified hours in period
        </span>
        <span className="ml-3 text-default-400">
          Member counts shown as privacy-preserving brackets (e.g. "50–99") — no individual data is ever surfaced here.
        </span>
      </div>

      {/* Period selector */}
      <Card>
        <CardBody className="flex flex-col gap-3 md:flex-row md:items-end">
          <Select
            label="Period"
            selectedKeys={[preset]}
            onSelectionChange={(keys) => {
              const next = Array.from(keys)[0] as PeriodPreset;
              if (next) handlePresetChange(next);
            }}
            className="md:max-w-xs"
            aria-label="Reporting period"
          >
            <SelectItem key="this_month">This month</SelectItem>
            <SelectItem key="last_quarter">Last quarter (90 days)</SelectItem>
            <SelectItem key="last_year">Last year</SelectItem>
            <SelectItem key="custom">Custom range</SelectItem>
          </Select>
          <Input
            type="date"
            label="From"
            value={periodFrom}
            onChange={(e) => { setPeriodFrom(e.target.value); setPreset('custom'); }}
            className="md:max-w-xs"
            aria-label="Period from"
          />
          <Input
            type="date"
            label="To"
            value={periodTo}
            onChange={(e) => { setPeriodTo(e.target.value); setPreset('custom'); }}
            className="md:max-w-xs"
            aria-label="Period to"
          />
          <Button color="primary" onPress={fetchAll} isLoading={loading}>
            Refresh
          </Button>
        </CardBody>
      </Card>

      {/* Summary stats */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total cooperatives"
          value={summary?.cooperatives_count ?? '—'}
          icon={Building2}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Active cooperatives"
          value={summary?.active_cooperatives_count ?? '—'}
          icon={Activity}
          color="success"
          description={summary ? `${summary.active_cooperatives_count} of ${summary.cooperatives_count} active in period` : undefined}
          loading={loading}
        />
        <StatCard
          label="Total hours (national)"
          value={summary?.total_approved_hours_national.toFixed(1) ?? '—'}
          icon={Clock}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Active tandems"
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
            <span className="text-default-500">Active members:</span>
            <Chip variant="flat">{summary.total_active_members_bucket}</Chip>
            <span className="text-default-500">Recipients reached:</span>
            <Chip variant="flat">{summary.total_recipients_reached_bucket}</Chip>
            <span className="text-default-500">Safeguarding reports:</span>
            <Chip variant="flat">{summary.safeguarding_reports_total}</Chip>
            {yoyChip}
          </CardBody>
        </Card>
      )}

      {/* National trend chart */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Activity size={18} />
          <h2 className="text-lg font-semibold">National 12-month trend</h2>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div className="flex h-72 items-center justify-center">
              <Spinner />
            </div>
          ) : trend.length === 0 ? (
            <p className="text-sm text-default-500">No trend data available.</p>
          ) : (
            <div className="h-72 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <ComposedChart data={trend} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border, #e5e7eb)" />
                  <XAxis dataKey="month" stroke="var(--color-text-muted, #6b7280)" />
                  <YAxis
                    yAxisId="left"
                    stroke="var(--color-text-muted, #6b7280)"
                    label={{ value: 'Hours', angle: -90, position: 'insideLeft', style: { fontSize: 11, fill: 'var(--color-text-muted, #6b7280)' } }}
                  />
                  <YAxis
                    yAxisId="right"
                    orientation="right"
                    stroke="var(--color-text-muted, #6b7280)"
                    label={{ value: 'Cooperatives', angle: 90, position: 'insideRight', style: { fontSize: 11, fill: 'var(--color-text-muted, #6b7280)' } }}
                  />
                  <Tooltip />
                  <Legend />
                  <Area
                    yAxisId="right"
                    type="monotone"
                    dataKey="active_cooperatives"
                    name="Active cooperatives"
                    fill="rgba(99, 102, 241, 0.15)"
                    stroke="rgba(99, 102, 241, 0.6)"
                  />
                  <Line
                    yAxisId="left"
                    type="monotone"
                    dataKey="total_hours_all_cooperatives"
                    name="Total hours (all cooperatives)"
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
          <h2 className="text-lg font-semibold">Comparative metrics</h2>
          <span className="text-xs text-default-500">Click a column to sort</span>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div className="flex h-32 items-center justify-center">
              <Spinner />
            </div>
          ) : sortedRows.length === 0 ? (
            <p className="text-sm text-default-500">
              No <Abbr term="KISS">KISS</Abbr> cooperatives configured yet. Go to <strong>Super Admin → Tenants</strong> and set the
              tenant category to <strong>kiss_cooperative</strong> on at least one tenant to populate this dashboard.
            </p>
          ) : (
            <Table
              aria-label="Comparative metrics by cooperative"
              removeWrapper
              isHeaderSticky
              classNames={{ base: 'overflow-x-auto' }}
            >
              <TableHeader>
                <TableColumn onClick={() => handleSort('name')} className="cursor-pointer">Cooperative</TableColumn>
                <TableColumn onClick={() => handleSort('hours')} className="cursor-pointer text-right"><span title="Total verified care hours in the selected period">Hours ↕</span></TableColumn>
                <TableColumn><span title="Active member count shown as a privacy-preserving bracket">Members</span></TableColumn>
                <TableColumn><span title="Care recipients reached (privacy-preserving bracket)">Recipients</span></TableColumn>
                <TableColumn onClick={() => handleSort('active_tandems')} className="cursor-pointer text-right"><span title="Recurring helper/recipient pairs with 2+ completed exchanges">Tandems ↕</span></TableColumn>
                <TableColumn onClick={() => handleSort('retention_rate_pct')} className="cursor-pointer text-right"><span title="% of members active in both this period and the prior equivalent period">Retention ↕</span></TableColumn>
                <TableColumn onClick={() => handleSort('reciprocity_pct')} className="cursor-pointer text-right"><span title="% of supporters who also received hours in the period — indicates mutual exchange health">Reciprocity ↕</span></TableColumn>
                <TableColumn>Status</TableColumn>
              </TableHeader>
              <TableBody>
                {sortedRows.map((row) => (
                  <TableRow key={row.tenant_id}>
                    <TableCell className="font-medium">{row.name}</TableCell>
                    <TableCell className="text-right">{row.hours.toFixed(1)}</TableCell>
                    <TableCell><Chip variant="flat" size="sm">{row.members_bracket}</Chip></TableCell>
                    <TableCell><Chip variant="flat" size="sm">{row.recipients_bracket}</Chip></TableCell>
                    <TableCell className="text-right">{row.active_tandems}</TableCell>
                    <TableCell className="text-right">{row.retention_rate_pct.toFixed(1)}%</TableCell>
                    <TableCell className="text-right">{row.reciprocity_pct.toFixed(1)}%</TableCell>
                    <TableCell>
                      <Chip color={statusChip[row.status].color} variant="flat" size="sm">
                        {statusChip[row.status].label}
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
            <TrendingUp size={18} className="text-success" />
            <h2 className="text-lg font-semibold">Top 5 by hours</h2>
          </CardHeader>
          <CardBody>
            {summary && summary.top_5_cooperatives_by_hours.length > 0 ? (
              <ol className="space-y-2">
                {summary.top_5_cooperatives_by_hours.map((c, idx) => (
                  <li key={c.tenant_id} className="flex items-center justify-between text-sm">
                    <span><span className="font-mono text-default-400">#{idx + 1}</span> {c.name}</span>
                    <Chip variant="flat" size="sm" color="success">{c.hours.toFixed(1)} h</Chip>
                  </li>
                ))}
              </ol>
            ) : (
              <p className="text-sm text-default-500">No data.</p>
            )}
          </CardBody>
        </Card>
        <Card>
          <CardHeader className="flex items-center gap-2">
            <TrendingDown size={18} className="text-danger" />
            <h2 className="text-lg font-semibold">Bottom 5 (active only)</h2>
          </CardHeader>
          <CardBody>
            {summary && summary.bottom_5_active_cooperatives_by_hours.length > 0 ? (
              <ol className="space-y-2">
                {summary.bottom_5_active_cooperatives_by_hours.map((c, idx) => (
                  <li key={c.tenant_id} className="flex items-center justify-between text-sm">
                    <span><span className="font-mono text-default-400">#{idx + 1}</span> {c.name}</span>
                    <Chip variant="flat" size="sm" color="danger">{c.hours.toFixed(1)} h</Chip>
                  </li>
                ))}
              </ol>
            ) : (
              <p className="text-sm text-default-500">No active cooperatives in this period.</p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Footer */}
      {summary && (
        <p className="text-xs text-default-400">
          Generated at {new Date(summary.generated_at).toLocaleString()}.
          Member counts are reported in privacy-preserving brackets — no individual member identifiers are shown anywhere on this page.
        </p>
      )}
    </div>
  );
}

export default NationalKissDashboardPage;
