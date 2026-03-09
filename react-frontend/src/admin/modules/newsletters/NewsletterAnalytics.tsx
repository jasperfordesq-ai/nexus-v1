// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Analytics
 * Full analytics dashboard with summary stats, engagement metrics,
 * monthly performance chart, top performers table, and benchmark comparison.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Chip,
  Button,
  Divider,
} from '@heroui/react';
import {
  BarChart3,
  Mail,
  Users,
  MousePointer,
  Eye,
  RefreshCw,
  Trophy,
  TrendingUp,
  Send,
  AlertTriangle,
  Target,
} from 'lucide-react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

/* ────────────────────────────────────────────────────────────────────────── */
/*  Types                                                                    */
/* ────────────────────────────────────────────────────────────────────────── */

interface Totals {
  newsletters_sent: number;
  total_sent: number;
  total_failed: number;
  total_opens: number;
  unique_opens: number;
  total_clicks: number;
  unique_clicks: number;
}

interface MonthlyRow {
  month: string;
  newsletters: number;
  sent: number;
  opens: number;
  clicks: number;
}

interface TopPerformer {
  id: number;
  subject: string;
  sent_at: string;
  total_sent: number;
  open_rate: number;
  click_rate: number;
}

interface AnalyticsData {
  total_newsletters: number;
  total_sent: number;
  avg_open_rate: number;
  avg_click_rate: number;
  total_subscribers: number;
  totals?: Totals;
  monthly_breakdown?: MonthlyRow[];
  top_performers?: TopPerformer[];
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  Industry benchmarks (Mailchimp 2024 all-industry averages)               */
/* ────────────────────────────────────────────────────────────────────────── */

const BENCHMARK_OPEN_RATE = 21.3;
const BENCHMARK_CLICK_RATE = 2.6;

/* ────────────────────────────────────────────────────────────────────────── */
/*  Helpers                                                                  */
/* ────────────────────────────────────────────────────────────────────────── */

function formatMonth(ym: string): string {
  const [year, month] = ym.split('-');
  const date = new Date(Number(year), Number(month) - 1);
  return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function truncate(str: string, max: number): string {
  if (str.length <= max) return str;
  return str.slice(0, max) + '...';
}

function rateColor(rate: number, benchmark: number): 'success' | 'warning' | 'danger' {
  if (rate >= benchmark) return 'success';
  if (rate >= benchmark * 0.7) return 'warning';
  return 'danger';
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  Component                                                                */
/* ────────────────────────────────────────────────────────────────────────── */

export function NewsletterAnalytics() {
  usePageTitle('Admin - Newsletter Analytics');
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getAnalytics();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setData((payload as { data: AnalyticsData }).data);
        } else {
          setData(payload as AnalyticsData);
        }
      }
    } catch {
      setData(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const totals = data?.totals ?? null;
  const monthly = data?.monthly_breakdown ?? [];
  const topPerformers = data?.top_performers ?? [];

  // Derived avg rates from totals (more accurate than column average)
  const avgOpenRate = useMemo(() => {
    if (totals && totals.total_sent > 0) {
      return Math.round((totals.unique_opens / totals.total_sent) * 1000) / 10;
    }
    return data?.avg_open_rate ?? 0;
  }, [totals, data]);

  const avgClickRate = useMemo(() => {
    if (totals && totals.total_sent > 0) {
      return Math.round((totals.unique_clicks / totals.total_sent) * 1000) / 10;
    }
    return data?.avg_click_rate ?? 0;
  }, [totals, data]);

  // Monthly chart data with computed rates
  const chartData = useMemo(
    () =>
      monthly.map((m) => ({
        ...m,
        label: formatMonth(m.month),
        openRate: m.sent > 0 ? Math.round((m.opens / m.sent) * 1000) / 10 : 0,
        clickRate: m.sent > 0 ? Math.round((m.clicks / m.sent) * 1000) / 10 : 0,
      })),
    [monthly],
  );

  const hasData = (data?.total_newsletters ?? 0) > 0;

  return (
    <div>
      <PageHeader
        title="Newsletter Analytics"
        description="Performance metrics and insights across all campaigns"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
          >
            Refresh
          </Button>
        }
      />

      {/* ── Summary Stats ─────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard
          label="Campaigns Sent"
          value={data?.total_newsletters ?? 0}
          icon={Send}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Emails Delivered"
          value={totals?.total_sent ?? data?.total_sent ?? 0}
          icon={Mail}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Avg Open Rate"
          value={`${avgOpenRate}%`}
          icon={Eye}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Avg Click Rate"
          value={`${avgClickRate}%`}
          icon={MousePointer}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Subscribers"
          value={data?.total_subscribers ?? 0}
          icon={Users}
          color="primary"
          loading={loading}
        />
      </div>

      {/* ── Engagement Summary ────────────────────────────────────────── */}
      {totals && hasData && (
        <Card shadow="sm" className="mt-6">
          <CardHeader className="flex items-center gap-3 px-6 pb-0 pt-5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
              <BarChart3 size={20} />
            </div>
            <div>
              <h3 className="text-base font-semibold">Engagement Summary</h3>
              <p className="text-sm text-default-500">
                Aggregate metrics across all campaigns
              </p>
            </div>
          </CardHeader>
          <CardBody className="px-6 pb-6">
            <div className="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5">
              <EngagementStat
                label="Unique Opens"
                value={totals.unique_opens}
                color="text-primary"
              />
              <EngagementStat
                label="Total Opens"
                value={totals.total_opens}
                color="text-primary"
              />
              <EngagementStat
                label="Unique Clicks"
                value={totals.unique_clicks}
                color="text-success"
              />
              <EngagementStat
                label="Total Clicks"
                value={totals.total_clicks}
                color="text-success"
              />
              {totals.total_failed > 0 && (
                <EngagementStat
                  label="Failed"
                  value={totals.total_failed}
                  color="text-danger"
                  icon={<AlertTriangle size={14} className="text-danger" />}
                />
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Monthly Performance Chart ─────────────────────────────────── */}
      {chartData.length > 0 && (
        <Card shadow="sm" className="mt-6">
          <CardHeader className="flex items-center gap-3 px-6 pb-0 pt-5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <TrendingUp size={20} />
            </div>
            <div>
              <h3 className="text-base font-semibold">Monthly Performance</h3>
              <p className="text-sm text-default-500">
                Email volume and engagement over time
              </p>
            </div>
          </CardHeader>
          <CardBody className="px-2 pb-4 sm:px-6">
            <ResponsiveContainer width="100%" height={320}>
              <BarChart data={chartData} margin={{ top: 10, right: 10, left: 0, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                <YAxis yAxisId="left" tick={{ fontSize: 12 }} />
                <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 12 }} unit="%" />
                <Tooltip
                  contentStyle={{
                    borderRadius: 12,
                    border: '1px solid var(--heroui-default-200)',
                    background: 'var(--heroui-content1, #fff)',
                  }}
                  formatter={(value, name) => {
                    const v = value as number;
                    const n = name as string;
                    if (n === 'openRate' || n === 'clickRate') return [`${v}%`, n === 'openRate' ? 'Open Rate' : 'Click Rate'];
                    return [v.toLocaleString(), n === 'sent' ? 'Emails Sent' : n === 'opens' ? 'Opens' : 'Clicks'];
                  }}
                  labelFormatter={(label) => String(label)}
                />
                <Legend
                  formatter={(value: string) => {
                    const labels: Record<string, string> = {
                      sent: 'Emails Sent',
                      opens: 'Opens',
                      clicks: 'Clicks',
                      openRate: 'Open Rate %',
                      clickRate: 'Click Rate %',
                    };
                    return labels[value] ?? value;
                  }}
                />
                <Bar yAxisId="left" dataKey="sent" fill="hsl(var(--heroui-primary, 212 100% 48%))" radius={[4, 4, 0, 0]} />
                <Bar yAxisId="left" dataKey="opens" fill="hsl(var(--heroui-warning, 37 91% 55%))" radius={[4, 4, 0, 0]} />
                <Bar yAxisId="left" dataKey="clicks" fill="hsl(var(--heroui-success, 142 71% 45%))" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>

            {/* Monthly data table below chart */}
            <Divider className="my-4" />
            <div className="overflow-x-auto">
              <Table
                aria-label="Monthly performance breakdown"
                removeWrapper
                classNames={{ th: 'text-xs uppercase', td: 'py-2' }}
              >
                <TableHeader>
                  <TableColumn>Month</TableColumn>
                  <TableColumn className="text-center">Newsletters</TableColumn>
                  <TableColumn className="text-center">Emails Sent</TableColumn>
                  <TableColumn className="text-center">Opens</TableColumn>
                  <TableColumn className="text-center">Clicks</TableColumn>
                  <TableColumn className="text-center">Open Rate</TableColumn>
                  <TableColumn className="text-center">Click Rate</TableColumn>
                </TableHeader>
                <TableBody>
                  {chartData.map((row) => (
                    <TableRow key={row.month}>
                      <TableCell className="font-medium">{row.label}</TableCell>
                      <TableCell className="text-center">{row.newsletters}</TableCell>
                      <TableCell className="text-center">
                        {row.sent.toLocaleString()}
                      </TableCell>
                      <TableCell className="text-center">
                        {row.opens.toLocaleString()}
                      </TableCell>
                      <TableCell className="text-center">
                        {row.clicks.toLocaleString()}
                      </TableCell>
                      <TableCell className="text-center">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={rateColor(row.openRate, BENCHMARK_OPEN_RATE)}
                        >
                          {row.openRate}%
                        </Chip>
                      </TableCell>
                      <TableCell className="text-center">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={rateColor(row.clickRate, BENCHMARK_CLICK_RATE)}
                        >
                          {row.clickRate}%
                        </Chip>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Top 10 Performers ─────────────────────────────────────────── */}
      {topPerformers.length > 0 && (
        <Card shadow="sm" className="mt-6">
          <CardHeader className="flex items-center gap-3 px-6 pb-0 pt-5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
              <Trophy size={20} />
            </div>
            <div>
              <h3 className="text-base font-semibold">Top Performing Newsletters</h3>
              <p className="text-sm text-default-500">
                Ranked by open rate (minimum 10 recipients)
              </p>
            </div>
          </CardHeader>
          <CardBody className="px-0 pb-4 sm:px-2">
            <Table
              aria-label="Top performing newsletters"
              removeWrapper
              classNames={{ th: 'text-xs uppercase', td: 'py-3' }}
            >
              <TableHeader>
                <TableColumn className="w-[50px]">#</TableColumn>
                <TableColumn>Subject</TableColumn>
                <TableColumn className="text-center">Recipients</TableColumn>
                <TableColumn className="text-center">Open Rate</TableColumn>
                <TableColumn className="text-center">Click Rate</TableColumn>
                <TableColumn className="text-end">Date</TableColumn>
              </TableHeader>
              <TableBody>
                {topPerformers.map((nl, idx) => (
                  <TableRow key={nl.id}>
                    <TableCell>
                      {idx < 3 ? (
                        <RankBadge rank={idx + 1} />
                      ) : (
                        <span className="pl-2 text-default-400">{idx + 1}</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <span className="font-medium">{truncate(nl.subject, 55)}</span>
                    </TableCell>
                    <TableCell className="text-center text-default-500">
                      {nl.total_sent.toLocaleString()}
                    </TableCell>
                    <TableCell className="text-center">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={rateColor(nl.open_rate, BENCHMARK_OPEN_RATE)}
                      >
                        {nl.open_rate}%
                      </Chip>
                    </TableCell>
                    <TableCell className="text-center">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={rateColor(nl.click_rate, BENCHMARK_CLICK_RATE)}
                      >
                        {nl.click_rate}%
                      </Chip>
                    </TableCell>
                    <TableCell className="text-end text-sm text-default-500">
                      {formatDate(nl.sent_at)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      {/* ── Benchmark Comparison ──────────────────────────────────────── */}
      {hasData && (
        <Card shadow="sm" className="mt-6">
          <CardHeader className="flex items-center gap-3 px-6 pb-0 pt-5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
              <Target size={20} />
            </div>
            <div>
              <h3 className="text-base font-semibold">Industry Benchmark Comparison</h3>
              <p className="text-sm text-default-500">
                Your rates vs. all-industry averages (Mailchimp 2024)
              </p>
            </div>
          </CardHeader>
          <CardBody className="px-6 pb-6">
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              <BenchmarkCard
                label="Open Rate"
                yours={avgOpenRate}
                benchmark={BENCHMARK_OPEN_RATE}
              />
              <BenchmarkCard
                label="Click Rate"
                yours={avgClickRate}
                benchmark={BENCHMARK_CLICK_RATE}
              />
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Empty State ───────────────────────────────────────────────── */}
      {!loading && !hasData && (
        <Card shadow="sm" className="mt-6">
          <CardBody className="flex flex-col items-center justify-center py-16 text-center">
            <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-default-100">
              <BarChart3 size={40} className="text-default-300" />
            </div>
            <h3 className="text-lg font-semibold">No analytics data yet</h3>
            <p className="mt-1 text-default-500">
              Send your first newsletter to start seeing performance metrics.
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

/* ────────────────────────────────────────────────────────────────────────── */
/*  Sub-components                                                           */
/* ────────────────────────────────────────────────────────────────────────── */

function EngagementStat({
  label,
  value,
  color,
  icon,
}: {
  label: string;
  value: number;
  color: string;
  icon?: React.ReactNode;
}) {
  return (
    <div className="text-center">
      <div className="flex items-center justify-center gap-1">
        {icon}
        <span className={`text-2xl font-bold ${color}`}>{value.toLocaleString()}</span>
      </div>
      <p className="mt-1 text-xs text-default-500">{label}</p>
    </div>
  );
}

function RankBadge({ rank }: { rank: number }) {
  const colors: Record<number, string> = {
    1: 'bg-warning text-warning-foreground',
    2: 'bg-default-200 text-default-600',
    3: 'bg-orange-300 text-orange-900',
  };
  return (
    <span
      className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold ${colors[rank] ?? ''}`}
    >
      {rank}
    </span>
  );
}

function BenchmarkCard({
  label,
  yours,
  benchmark,
}: {
  label: string;
  yours: number;
  benchmark: number;
}) {
  const diff = yours - benchmark;
  const diffPct = benchmark > 0 ? Math.round((diff / benchmark) * 100) : 0;
  const isAbove = diff >= 0;

  return (
    <div className="rounded-xl border border-default-200 p-5">
      <p className="mb-3 text-sm font-semibold text-default-700">{label}</p>
      <div className="flex items-end justify-between gap-4">
        <div>
          <p className="text-xs text-default-500">Your Average</p>
          <p className="text-2xl font-bold text-foreground">{yours}%</p>
        </div>
        <div className="text-right">
          <p className="text-xs text-default-500">Industry Avg</p>
          <p className="text-2xl font-bold text-default-400">{benchmark}%</p>
        </div>
      </div>
      <Divider className="my-3" />
      <div className="flex items-center gap-2">
        <Chip
          size="sm"
          variant="flat"
          color={isAbove ? 'success' : 'danger'}
          startContent={
            isAbove ? <TrendingUp size={12} /> : <TrendingUp size={12} className="rotate-180" />
          }
        >
          {isAbove ? '+' : ''}
          {diff.toFixed(1)}pp ({isAbove ? '+' : ''}
          {diffPct}%)
        </Chip>
        <span className="text-xs text-default-500">
          {isAbove ? 'above' : 'below'} benchmark
        </span>
      </div>
    </div>
  );
}

export default NewsletterAnalytics;
