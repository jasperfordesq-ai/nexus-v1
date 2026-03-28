// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * A3 - Hours Reports
 *
 * Reports on hours exchanged, grouped by category, member, or period.
 * - Hours by category pie/bar chart
 * - Hours by member table (given/received/balance)
 * - Monthly trend chart
 * - Summary stats cards (total hours, avg per member, etc.)
 *
 * API: GET /api/v2/admin/reports/hours?group_by=category|member|period|summary
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Spinner,
  Button,
  Input,
  Select,
  SelectItem,
  Tabs,
  Tab,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Avatar,
  Chip,
} from '@heroui/react';
import {
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
  AreaChart,
  Area,
} from 'recharts';
import {
  Clock,
  Download,
  RefreshCw,
  TrendingUp,
  Users,
  BarChart3,
  PieChart as PieChartIcon,
  Activity,
  ArrowLeftRight,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api, tokenManager } from '@/lib/api';
import { CHART_COLORS, CHART_COLOR_MAP } from '@/lib/chartColors';
import { StatCard, PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';
// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface CategoryHours {
  category: string;
  total_hours: number;
  transaction_count: number;
  percentage: number;
}

interface MemberHours {
  id: number;
  name: string;
  profile_image_url: string | null;
  hours_given: number;
  hours_received: number;
  total_hours: number;
  balance: number;
}

interface PeriodHours {
  month: string;
  total_hours: number;
  transaction_count: number;
  unique_givers: number;
  unique_receivers: number;
}

interface HoursReportData {
  categories?: CategoryHours[];
  members?: MemberHours[];
  periods?: PeriodHours[];
}

interface HoursSummary {
  period?: { from: string; to: string };
  total_hours: number;
  total_transactions: number;
  avg_hours_per_transaction: number;
  unique_givers: number;
  unique_receivers: number;
  min_hours: number;
  max_hours: number;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

const PIE_COLORS = CHART_COLORS;

const SORT_OPTIONS = [
  { key: 'total', label: 'Total Hours' },
  { key: 'given', label: 'Hours Given' },
  { key: 'received', label: 'Hours Received' },
];

// ---------------------------------------------------------------------------
// CSV Export helper
// ---------------------------------------------------------------------------

async function exportCsv(exportType: string, dateFrom?: string, dateTo?: string) {
  const token = tokenManager.getAccessToken();
  const tenantId = tokenManager.getTenantId();
  const headers: Record<string, string> = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const params = new URLSearchParams({ format: 'csv' });
  if (dateFrom) params.append('date_from', dateFrom);
  if (dateTo) params.append('date_to', dateTo);

  const apiBase = import.meta.env.VITE_API_BASE || '/api';
  const res = await fetch(`${apiBase}/v2/admin/reports/hours_by_category/export?${params}`, { headers, credentials: 'include' });
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `hours-report-${exportType}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function HoursReportsPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('reports.page_title'));
  const toast = useToast();

  const [groupBy, setGroupBy] = useState('category');
  const [data, setData] = useState<HoursReportData | null>(null);
  const [summary, setSummary] = useState<HoursSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [sortBy, setSortBy] = useState('total');
  const [page, setPage] = useState(1);

  // Load summary always
  const loadSummary = useCallback(async () => {
    try {
      const params = new URLSearchParams({ group_by: 'summary' });
      if (dateFrom) params.append('date_from', dateFrom);
      if (dateTo) params.append('date_to', dateTo);
      const res = await api.get(`/v2/admin/reports/hours?${params}`);
      if (res.data) {
        setSummary(res.data as HoursSummary);
      }
    } catch {
      toast.error(t('reports.failed_to_load_summary_data'));
    }
  }, [dateFrom, dateTo, toast]);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ group_by: groupBy, page: String(page), limit: '50' });
      if (dateFrom) params.append('date_from', dateFrom);
      if (dateTo) params.append('date_to', dateTo);
      if (groupBy === 'member') params.append('sort_by', sortBy);
      const res = await api.get(`/v2/admin/reports/hours?${params}`);
      if (res.data) {
        setData(res.data);
      }
    } catch {
      toast.error(t('reports.failed_to_load_report_data'));
    } finally {
      setLoading(false);
    }
  }, [groupBy, dateFrom, dateTo, sortBy, page, toast]);

  useEffect(() => {
    loadSummary();
  }, [loadSummary]);

  useEffect(() => {
    setPage(1);
  }, [groupBy]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // -------------------------------------------------------------------------
  // Render: Summary cards
  // -------------------------------------------------------------------------

  const renderSummary = () => (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
      <StatCard
        label={t('reports.label_total_hours')}
        value={summary ? (summary.total_hours ?? 0).toFixed(1) : '\u2014'}
        icon={Clock}
        color="warning"
        loading={!summary}
      />
      <StatCard
        label={t('reports.label_total_transactions')}
        value={summary?.total_transactions ?? '\u2014'}
        icon={ArrowLeftRight}
        color="primary"
        loading={!summary}
      />
      <StatCard
        label={t('reports.label_unique_givers')}
        value={summary?.unique_givers ?? '\u2014'}
        icon={Users}
        color="success"
        loading={!summary}
      />
      <StatCard
        label={t('reports.label_avg_hours_transaction')}
        value={summary ? (summary.avg_hours_per_transaction ?? 0).toFixed(1) : '\u2014'}
        icon={Activity}
        color="secondary"
        loading={!summary}
      />
    </div>
  );

  // -------------------------------------------------------------------------
  // Render: Category chart
  // -------------------------------------------------------------------------

  const renderCategory = () => {
    const categories = (data?.categories ?? []) as CategoryHours[];

    return (
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Bar Chart */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <BarChart3 size={18} className="text-primary" />
            <h3 className="font-semibold">Hours by Category</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[350px] items-center justify-center"><Spinner /></div>
            ) : categories.length > 0 ? (
              <ResponsiveContainer width="100%" height={350}>
                <BarChart data={categories} layout="vertical" margin={{ left: 80 }}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                  <XAxis type="number" tick={{ fontSize: 12 }} />
                  <YAxis type="category" dataKey="category" tick={{ fontSize: 11 }} width={80} />
                  <Tooltip contentStyle={tooltipStyle} />
                  <Bar dataKey="total_hours" name="Hours" fill={CHART_COLOR_MAP.primary} radius={[0, 4, 4, 0]} fillOpacity={0.8} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
                No category data available
              </p>
            )}
          </CardBody>
        </Card>

        {/* Pie Chart */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <PieChartIcon size={18} className="text-secondary" />
            <h3 className="font-semibold">Category Distribution</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[350px] items-center justify-center"><Spinner /></div>
            ) : categories.length > 0 ? (
              <ResponsiveContainer width="100%" height={350}>
                <PieChart>
                  <Pie
                    data={categories.filter((c) => c.total_hours > 0)}
                    dataKey="total_hours"
                    nameKey="category"
                    cx="50%"
                    cy="50%"
                    outerRadius={110}
                    innerRadius={50}
                    paddingAngle={2}
                    label={({ name, percent }) =>
                      `${name} (${((percent ?? 0) * 100).toFixed(0)}%)`
                    }
                    labelLine={{ strokeWidth: 1 }}
                  >
                    {categories
                      .filter((c) => c.total_hours > 0)
                      .map((_, index) => (
                        <Cell key={`cell-${index}`} fill={PIE_COLORS[index % PIE_COLORS.length]} fillOpacity={0.85} />
                      ))}
                  </Pie>
                  <Tooltip
                    contentStyle={tooltipStyle}
                    formatter={(value: number | undefined, name: string | undefined) =>
                      [`${(value ?? 0).toFixed(1)} hours`, name ?? '']
                    }
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
                No category data available
              </p>
            )}
          </CardBody>
        </Card>
      </div>
    );
  };

  // -------------------------------------------------------------------------
  // Render: Member table
  // -------------------------------------------------------------------------

  const renderMember = () => {
    const members = (data?.members ?? []) as MemberHours[];

    return (
      <div>
        <div className="flex items-center gap-3 mb-4">
          <Select
            size="sm"
            selectedKeys={[sortBy]}
            onSelectionChange={(keys) => {
              const v = Array.from(keys)[0];
              if (v) setSortBy(String(v));
            }}
            className="w-40"
            aria-label={t('reports.label_sort_by')}
            label={t('reports.label_sort_by')}
          >
            {SORT_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{opt.label}</SelectItem>
            ))}
          </Select>
        </div>

        <Table aria-label={t('reports.label_hours_by_member')} shadow="sm">
          <TableHeader>
            <TableColumn>Member</TableColumn>
            <TableColumn>Hours Given</TableColumn>
            <TableColumn>Hours Received</TableColumn>
            <TableColumn>Total</TableColumn>
            <TableColumn>Balance</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent="No member hours data found"
            isLoading={loading}
            loadingContent={<Spinner />}
          >
            {members.map((m) => (
              <TableRow key={m.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Avatar size="sm" src={m.profile_image_url ?? undefined} name={m.name} />
                    <span className="text-sm font-medium">{m.name}</span>
                  </div>
                </TableCell>
                <TableCell className="text-sm text-success font-medium">{(m.hours_given ?? 0).toFixed(1)}</TableCell>
                <TableCell className="text-sm text-warning font-medium">{(m.hours_received ?? 0).toFixed(1)}</TableCell>
                <TableCell className="text-sm text-default-600 font-medium">{(m.total_hours ?? 0).toFixed(1)}</TableCell>
                <TableCell>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={(m.balance ?? 0) >= 0 ? 'success' : 'danger'}
                  >
                    {(m.balance ?? 0) >= 0 ? '+' : ''}{(m.balance ?? 0).toFixed(1)}
                  </Chip>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    );
  };

  // -------------------------------------------------------------------------
  // Render: Period trend
  // -------------------------------------------------------------------------

  const renderPeriod = () => {
    const periods = (data?.periods ?? []) as PeriodHours[];

    return (
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <TrendingUp size={18} className="text-primary" />
          <h3 className="font-semibold">Monthly Hours Trend</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[350px] items-center justify-center"><Spinner /></div>
          ) : periods.length > 0 ? (
            <ResponsiveContainer width="100%" height={350}>
              <AreaChart data={periods} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="hrTotalGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0.3} />
                    <stop offset="95%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="hrTxGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={CHART_COLOR_MAP.success} stopOpacity={0.3} />
                    <stop offset="95%" stopColor={CHART_COLOR_MAP.success} stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="month" tick={{ fontSize: 11 }} tickLine={false} />
                <YAxis tick={{ fontSize: 12 }} tickLine={false} />
                <Tooltip contentStyle={tooltipStyle} labelStyle={{ fontWeight: 600 }} />
                <Legend />
                <Area
                  type="monotone"
                  dataKey="total_hours"
                  name="Total Hours"
                  stroke={CHART_COLOR_MAP.primary}
                  fill="url(#hrTotalGrad)"
                  strokeWidth={2}
                />
                <Area
                  type="monotone"
                  dataKey="transaction_count"
                  name="Transactions"
                  stroke={CHART_COLOR_MAP.success}
                  fill="url(#hrTxGrad)"
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
              No period data available
            </p>
          )}
        </CardBody>
      </Card>
    );
  };

  // -------------------------------------------------------------------------
  // Main render
  // -------------------------------------------------------------------------

  return (
    <div>
      <PageHeader
        title={t('reports.hours_reports_page_title')}
        description={t('reports.hours_reports_page_desc')}
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            <Input
              type="date"
              size="sm"
              value={dateFrom}
              onValueChange={setDateFrom}
              aria-label={t('reports.label_from_date')}
              className="w-36"
              variant="bordered"
            />
            <Input
              type="date"
              size="sm"
              value={dateTo}
              onValueChange={setDateTo}
              aria-label={t('reports.label_to_date')}
              className="w-36"
              variant="bordered"
            />
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={async () => {
                try { await exportCsv(groupBy, dateFrom, dateTo); } catch { toast.error(t('reports.failed_to_export_c_s_v')); }
              }}
              size="sm"
            >
              Export CSV
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={() => { loadSummary(); loadData(); }}
              isLoading={loading}
              isDisabled={loading}
              size="sm"
            >
              Refresh
            </Button>
          </div>
        }
      />

      {renderSummary()}

      <Tabs
        selectedKey={groupBy}
        onSelectionChange={(key) => setGroupBy(String(key))}
        variant="underlined"
        color="primary"
        classNames={{ tabList: 'mb-4' }}
      >
        <Tab key="category" title={<span className="flex items-center gap-1.5"><PieChartIcon size={14} /> By Category</span>} />
        <Tab key="member" title={<span className="flex items-center gap-1.5"><Users size={14} /> By Member</span>} />
        <Tab key="period" title={<span className="flex items-center gap-1.5"><TrendingUp size={14} /> Monthly Trend</span>} />
      </Tabs>

      {groupBy === 'category' && renderCategory()}
      {groupBy === 'member' && renderMember()}
      {groupBy === 'period' && renderPeriod()}
    </div>
  );
}

export default HoursReportsPage;
