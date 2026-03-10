// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * A2 - Member Activity Reports
 *
 * Report type tabs/selector with:
 * - Active members list with last login, transaction count
 * - Registration trends chart (daily/weekly/monthly)
 * - Retention cohort table
 * - Engagement metrics cards
 * - Top contributors leaderboard
 * - Least active members list
 *
 * API: GET /api/v2/admin/reports/members?type=active|registrations|retention|engagement|top_contributors|least_active
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Spinner,
  Button,
  Tabs,
  Tab,
  Chip,
  Select,
  SelectItem,
  Pagination,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Avatar,
} from '@heroui/react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';
import {
  Users,
  Download,
  RefreshCw,
  TrendingUp,
  UserCheck,
  UserX,
  Activity,
  Trophy,
  BarChart3,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ActiveMember {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  last_login: string | null;
  transaction_count: number;
  hours_given: number;
  hours_received: number;
  joined_at: string;
}

interface RegistrationTrend {
  period: string;
  count: number;
  cumulative: number;
}

interface RetentionCohort {
  cohort: string;
  initial: number;
  month_1: number;
  month_2: number;
  month_3: number;
  month_6: number;
  month_12: number;
}

interface EngagementMetrics {
  login_rate: number;
  trading_rate: number;
  listing_rate: number;
  messaging_rate: number;
  event_attendance_rate: number;
  avg_sessions_per_user: number;
  avg_transactions_per_user: number;
  total_active_30d: number;
  total_active_90d: number;
  total_members: number;
}

interface TopContributor {
  id: number;
  name: string;
  avatar_url: string | null;
  hours_given: number;
  hours_received: number;
  transaction_count: number;
  listings_count: number;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type ReportData = any;

// ---------------------------------------------------------------------------
// Chart tooltip style
// ---------------------------------------------------------------------------

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

const GROUP_BY_OPTIONS = [
  { key: 'daily', label: 'Daily' },
  { key: 'weekly', label: 'Weekly' },
  { key: 'monthly', label: 'Monthly' },
];

const PERIOD_OPTIONS = [
  { key: '30', label: '30 days' },
  { key: '60', label: '60 days' },
  { key: '90', label: '90 days' },
  { key: '180', label: '180 days' },
  { key: '365', label: '365 days' },
];

// ---------------------------------------------------------------------------
// CSV Export helper
// ---------------------------------------------------------------------------

async function exportCsv(reportType: string) {
  const token = localStorage.getItem('nexus_access_token');
  const tenantId = localStorage.getItem('nexus_tenant_id');
  const headers: Record<string, string> = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const apiBase = import.meta.env.VITE_API_BASE || '/api';
  const res = await fetch(`${apiBase}/v2/admin/reports/members/export?format=csv`, { headers });
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `member-report-${reportType}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function MemberReportsPage() {
  usePageTitle('Member Reports');

  const [reportType, setReportType] = useState('active');
  const [data, setData] = useState<ReportData>(null);
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('30');
  const [groupBy, setGroupBy] = useState('monthly');
  const [page, setPage] = useState(1);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ type: reportType, period, page: String(page), limit: '20' });
      if (reportType === 'registrations') params.append('group_by', groupBy);
      const res = await api.get(`/v2/admin/reports/members?${params}`);
      if (res.data) {
        setData(res.data);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [reportType, period, groupBy, page]);

  useEffect(() => {
    setPage(1);
  }, [reportType]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // -------------------------------------------------------------------------
  // Render helpers
  // -------------------------------------------------------------------------

  const renderActiveMembers = () => {
    const members = (data?.members ?? data?.data ?? []) as ActiveMember[];
    const total = data?.total ?? data?.pagination?.total ?? members.length;
    const totalPages = Math.max(1, Math.ceil(total / 20));

    return (
      <>
        <Table aria-label="Active members" shadow="sm">
          <TableHeader>
            <TableColumn>Member</TableColumn>
            <TableColumn>Last Login</TableColumn>
            <TableColumn>Transactions</TableColumn>
            <TableColumn>Hours Given</TableColumn>
            <TableColumn>Hours Received</TableColumn>
            <TableColumn>Joined</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent="No active members found"
            isLoading={loading}
            loadingContent={<Spinner />}
          >
            {members.map((m) => (
              <TableRow key={m.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Avatar size="sm" src={m.avatar_url ?? undefined} name={m.name} />
                    <div>
                      <p className="text-sm font-medium">{m.name}</p>
                      <p className="text-xs text-default-400">{m.email}</p>
                    </div>
                  </div>
                </TableCell>
                <TableCell>
                  <span className="text-sm text-default-600">
                    {m.last_login ? new Date(m.last_login).toLocaleDateString() : 'Never'}
                  </span>
                </TableCell>
                <TableCell>
                  <Chip size="sm" variant="flat" color="primary">{m.transaction_count}</Chip>
                </TableCell>
                <TableCell className="text-sm text-success font-medium">{m.hours_given?.toFixed(1) ?? '0.0'}</TableCell>
                <TableCell className="text-sm text-warning font-medium">{m.hours_received?.toFixed(1) ?? '0.0'}</TableCell>
                <TableCell className="text-sm text-default-500">
                  {new Date(m.joined_at).toLocaleDateString()}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {totalPages > 1 && (
          <div className="flex justify-center mt-4">
            <Pagination total={totalPages} page={page} onChange={setPage} />
          </div>
        )}
      </>
    );
  };

  const renderRegistrations = () => {
    const trends = (data?.trends ?? data?.registrations ?? []) as RegistrationTrend[];

    return (
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <TrendingUp size={18} className="text-success" />
          <h3 className="font-semibold">Registration Trends</h3>
          <div className="ml-auto">
            <Select
              size="sm"
              selectedKeys={[groupBy]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) setGroupBy(String(v));
              }}
              className="w-32"
              aria-label="Group by"
            >
              {GROUP_BY_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
          </div>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[350px] items-center justify-center"><Spinner /></div>
          ) : trends.length > 0 ? (
            <ResponsiveContainer width="100%" height={350}>
              <BarChart data={trends}>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="period" tick={{ fontSize: 11 }} tickLine={false} />
                <YAxis tick={{ fontSize: 12 }} tickLine={false} allowDecimals={false} />
                <Tooltip contentStyle={tooltipStyle} />
                <Legend />
                <Bar dataKey="count" name="New Registrations" fill="#10b981" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
              No registration data available
            </p>
          )}
        </CardBody>
      </Card>
    );
  };

  const renderRetention = () => {
    const cohorts = (data?.cohorts ?? data?.retention ?? []) as RetentionCohort[];

    return (
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <UserCheck size={18} className="text-primary" />
          <h3 className="font-semibold">Retention Cohorts</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          <Table aria-label="Retention cohorts" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Cohort</TableColumn>
              <TableColumn className="text-center">Initial</TableColumn>
              <TableColumn className="text-center">Month 1</TableColumn>
              <TableColumn className="text-center">Month 2</TableColumn>
              <TableColumn className="text-center">Month 3</TableColumn>
              <TableColumn className="text-center">Month 6</TableColumn>
              <TableColumn className="text-center">Month 12</TableColumn>
            </TableHeader>
            <TableBody
              emptyContent="No retention data available"
              isLoading={loading}
              loadingContent={<Spinner />}
            >
              {cohorts.map((c) => {
                const pctCell = (val: number, key: string) => {
                  const pct = c.initial > 0 ? (val / c.initial) * 100 : 0;
                  const color = pct >= 60 ? 'text-success' : pct >= 30 ? 'text-warning' : 'text-danger';
                  return (
                    <TableCell key={key} className={`text-center font-medium ${color}`}>
                      {pct.toFixed(0)}%
                      <span className="text-xs text-default-400 ml-1">({val})</span>
                    </TableCell>
                  );
                };
                return (
                  <TableRow key={c.cohort}>
                    <TableCell className="font-medium text-foreground">{c.cohort}</TableCell>
                    <TableCell className="text-center">{c.initial}</TableCell>
                    {pctCell(c.month_1, 'm1')}
                    {pctCell(c.month_2, 'm2')}
                    {pctCell(c.month_3, 'm3')}
                    {pctCell(c.month_6, 'm6')}
                    {pctCell(c.month_12, 'm12')}
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    );
  };

  const renderEngagement = () => {
    const metrics = data as EngagementMetrics | null;

    return (
      <div className="space-y-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label="Login Rate (30d)"
            value={metrics ? `${(metrics.login_rate * 100).toFixed(1)}%` : '\u2014'}
            icon={Users}
            color="primary"
            loading={loading}
          />
          <StatCard
            label="Trading Rate"
            value={metrics ? `${(metrics.trading_rate * 100).toFixed(1)}%` : '\u2014'}
            icon={TrendingUp}
            color="success"
            loading={loading}
          />
          <StatCard
            label="Listing Rate"
            value={metrics ? `${(metrics.listing_rate * 100).toFixed(1)}%` : '\u2014'}
            icon={BarChart3}
            color="warning"
            loading={loading}
          />
          <StatCard
            label="Messaging Rate"
            value={metrics ? `${(metrics.messaging_rate * 100).toFixed(1)}%` : '\u2014'}
            icon={Activity}
            color="secondary"
            loading={loading}
          />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Card shadow="sm">
            <CardBody className="p-4">
              <p className="text-sm text-default-500">Active (30d) / Total</p>
              {loading ? (
                <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
              ) : (
                <p className="text-2xl font-bold text-foreground">
                  {metrics?.total_active_30d?.toLocaleString() ?? 0} / {metrics?.total_members?.toLocaleString() ?? 0}
                </p>
              )}
            </CardBody>
          </Card>
          <Card shadow="sm">
            <CardBody className="p-4">
              <p className="text-sm text-default-500">Avg Sessions / User</p>
              {loading ? (
                <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
              ) : (
                <p className="text-2xl font-bold text-foreground">
                  {metrics?.avg_sessions_per_user?.toFixed(1) ?? '0.0'}
                </p>
              )}
            </CardBody>
          </Card>
          <Card shadow="sm">
            <CardBody className="p-4">
              <p className="text-sm text-default-500">Avg Transactions / User</p>
              {loading ? (
                <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
              ) : (
                <p className="text-2xl font-bold text-foreground">
                  {metrics?.avg_transactions_per_user?.toFixed(1) ?? '0.0'}
                </p>
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    );
  };

  const renderTopContributors = () => {
    const contributors = (data?.contributors ?? []) as TopContributor[];

    return (
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <Trophy size={18} className="text-warning" />
          <h3 className="font-semibold">Top Contributors</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          <Table aria-label="Top contributors" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Rank</TableColumn>
              <TableColumn>Member</TableColumn>
              <TableColumn className="text-right">Given</TableColumn>
              <TableColumn className="text-right">Received</TableColumn>
              <TableColumn className="text-right">Transactions</TableColumn>
              <TableColumn className="text-right">Listings</TableColumn>
            </TableHeader>
            <TableBody
              emptyContent="No contributor data available"
              isLoading={loading}
              loadingContent={<Spinner />}
            >
              {contributors.map((c, i) => (
                <TableRow key={c.id}>
                  <TableCell>
                    <span className={`font-bold ${i < 3 ? 'text-warning' : 'text-default-400'}`}>
                      {i + 1}
                    </span>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Avatar size="sm" src={c.avatar_url ?? undefined} name={c.name} />
                      <span className="font-medium text-foreground">{c.name}</span>
                    </div>
                  </TableCell>
                  <TableCell className="text-right text-success font-medium">{c.hours_given?.toFixed(1)}</TableCell>
                  <TableCell className="text-right text-warning font-medium">{c.hours_received?.toFixed(1)}</TableCell>
                  <TableCell className="text-right text-primary">{c.transaction_count}</TableCell>
                  <TableCell className="text-right text-default-600">{c.listings_count}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    );
  };

  const renderLeastActive = () => {
    const members = (data?.members ?? data?.data ?? []) as ActiveMember[];
    const total = data?.total ?? data?.pagination?.total ?? members.length;
    const totalPages = Math.max(1, Math.ceil(total / 20));

    return (
      <>
        <Table aria-label="Least active members" shadow="sm">
          <TableHeader>
            <TableColumn>Member</TableColumn>
            <TableColumn>Last Login</TableColumn>
            <TableColumn>Transactions</TableColumn>
            <TableColumn>Joined</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent="No inactive members found"
            isLoading={loading}
            loadingContent={<Spinner />}
          >
            {members.map((m) => (
              <TableRow key={m.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Avatar size="sm" src={m.avatar_url ?? undefined} name={m.name} />
                    <div>
                      <p className="text-sm font-medium">{m.name}</p>
                      <p className="text-xs text-default-400">{m.email}</p>
                    </div>
                  </div>
                </TableCell>
                <TableCell>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={m.last_login ? 'default' : 'danger'}
                  >
                    {m.last_login ? new Date(m.last_login).toLocaleDateString() : 'Never'}
                  </Chip>
                </TableCell>
                <TableCell className="text-sm">{m.transaction_count}</TableCell>
                <TableCell className="text-sm text-default-500">
                  {new Date(m.joined_at).toLocaleDateString()}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {totalPages > 1 && (
          <div className="flex justify-center mt-4">
            <Pagination total={totalPages} page={page} onChange={setPage} />
          </div>
        )}
      </>
    );
  };

  // -------------------------------------------------------------------------
  // Main render
  // -------------------------------------------------------------------------

  return (
    <div>
      <PageHeader
        title="Member Reports"
        description="Activity, registration trends, retention, and engagement analysis"
        actions={
          <div className="flex items-center gap-2">
            <Select
              size="sm"
              selectedKeys={[period]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) setPeriod(String(v));
              }}
              className="w-32"
              aria-label="Period"
            >
              {PERIOD_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={() => exportCsv(reportType)}
              size="sm"
            >
              Export CSV
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              Refresh
            </Button>
          </div>
        }
      />

      <Tabs
        selectedKey={reportType}
        onSelectionChange={(key) => setReportType(String(key))}
        variant="underlined"
        color="primary"
        classNames={{ tabList: 'mb-4' }}
      >
        <Tab key="active" title={<span className="flex items-center gap-1.5"><Users size={14} /> Active</span>} />
        <Tab key="registrations" title={<span className="flex items-center gap-1.5"><TrendingUp size={14} /> Registrations</span>} />
        <Tab key="retention" title={<span className="flex items-center gap-1.5"><UserCheck size={14} /> Retention</span>} />
        <Tab key="engagement" title={<span className="flex items-center gap-1.5"><Activity size={14} /> Engagement</span>} />
        <Tab key="top_contributors" title={<span className="flex items-center gap-1.5"><Trophy size={14} /> Top Contributors</span>} />
        <Tab key="least_active" title={<span className="flex items-center gap-1.5"><UserX size={14} /> Least Active</span>} />
      </Tabs>

      {reportType === 'active' && renderActiveMembers()}
      {reportType === 'registrations' && renderRegistrations()}
      {reportType === 'retention' && renderRetention()}
      {reportType === 'engagement' && renderEngagement()}
      {reportType === 'top_contributors' && renderTopContributors()}
      {reportType === 'least_active' && renderLeastActive()}
    </div>
  );
}

export default MemberReportsPage;
