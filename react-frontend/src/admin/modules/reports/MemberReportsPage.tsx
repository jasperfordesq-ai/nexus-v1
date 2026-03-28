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
import { api, tokenManager } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { CHART_COLOR_MAP } from '@/lib/chartColors';
import { StatCard, PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';
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

interface ReportData extends Partial<EngagementMetrics> {
  members?: ActiveMember[];
  data?: ActiveMember[];
  total?: number;
  pagination?: { total?: number };
  trends?: RegistrationTrend[];
  registrations?: RegistrationTrend[];
  cohorts?: RetentionCohort[];
  retention?: RetentionCohort[];
  contributors?: TopContributor[];
}

// ---------------------------------------------------------------------------
// Chart tooltip style
// ---------------------------------------------------------------------------

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

// GROUP_BY_OPTIONS and PERIOD_OPTIONS are defined inside the component to access t()

// ---------------------------------------------------------------------------
// CSV Export helper
// ---------------------------------------------------------------------------

async function exportCsv(reportType: string) {
  const token = tokenManager.getAccessToken();
  const tenantId = tokenManager.getTenantId();
  const headers: Record<string, string> = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const apiBase = import.meta.env.VITE_API_BASE || '/api';
  const res = await fetch(`${apiBase}/v2/admin/reports/members/export?format=csv`, { headers, credentials: 'include' });
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
  const { t } = useTranslation('admin');
  usePageTitle(t('reports.page_title'));

  const GROUP_BY_OPTIONS = [
    { key: 'daily', label: t('reports.group_by_daily') },
    { key: 'weekly', label: t('reports.group_by_weekly') },
    { key: 'monthly', label: t('reports.group_by_monthly') },
  ];

  const PERIOD_OPTIONS = [
    { key: '30', label: t('reports.period_30_days') },
    { key: '60', label: t('reports.period_60_days') },
    { key: '90', label: t('reports.period_90_days') },
    { key: '180', label: t('reports.period_180_days') },
    { key: '365', label: t('reports.period_365_days') },
  ];

  const [reportType, setReportType] = useState('active');
  const [data, setData] = useState<ReportData | null>(null);
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
        <Table aria-label={t('reports.label_active_members')} shadow="sm">
          <TableHeader>
            <TableColumn>{t('reports.col_member')}</TableColumn>
            <TableColumn>{t('reports.col_last_login')}</TableColumn>
            <TableColumn>{t('reports.col_transactions')}</TableColumn>
            <TableColumn>{t('reports.col_hours_given')}</TableColumn>
            <TableColumn>{t('reports.col_hours_received')}</TableColumn>
            <TableColumn>{t('reports.col_joined')}</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent={t('reports.no_active_members_found')}
            isLoading={loading}
            loadingContent={<Spinner />}
          >
            {members.map((m) => (
              <TableRow key={m.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Avatar size="sm" src={resolveAvatarUrl(m.avatar_url) || undefined} name={m.name} />
                    <div>
                      <p className="text-sm font-medium">{m.name}</p>
                      <p className="text-xs text-default-400">{m.email}</p>
                    </div>
                  </div>
                </TableCell>
                <TableCell>
                  <span className="text-sm text-default-600">
                    {m.last_login ? new Date(m.last_login).toLocaleDateString() : t('reports.never')}
                  </span>
                </TableCell>
                <TableCell>
                  <Chip size="sm" variant="flat" color="primary">{m.transaction_count}</Chip>
                </TableCell>
                <TableCell className="text-sm text-success font-medium">{m.hours_given?.toFixed(1) ?? '0.0'}</TableCell>
                <TableCell className="text-sm text-warning font-medium">{m.hours_received?.toFixed(1) ?? '0.0'}</TableCell>
                <TableCell className="text-sm text-default-500">
                  {m.joined_at ? new Date(m.joined_at).toLocaleDateString() : '---'}
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
          <h3 className="font-semibold">{t('reports.registration_trends')}</h3>
          <div className="ml-auto">
            <Select
              size="sm"
              selectedKeys={[groupBy]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) setGroupBy(String(v));
              }}
              className="w-32"
              aria-label={t('reports.label_group_by')}
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
                <Bar dataKey="count" name={t('reports.new_registrations')} fill={CHART_COLOR_MAP.success} radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
              {t('reports.no_registration_data')}
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
          <h3 className="font-semibold">{t('reports.retention_cohorts')}</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          <Table aria-label={t('reports.label_retention_cohorts')} shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>{t('reports.col_cohort')}</TableColumn>
              <TableColumn className="text-center">{t('reports.col_initial')}</TableColumn>
              <TableColumn className="text-center">{t('reports.col_month_1')}</TableColumn>
              <TableColumn className="text-center">{t('reports.col_month_2')}</TableColumn>
              <TableColumn className="text-center">{t('reports.col_month_3')}</TableColumn>
              <TableColumn className="text-center">{t('reports.col_month_6')}</TableColumn>
              <TableColumn className="text-center">{t('reports.col_month_12')}</TableColumn>
            </TableHeader>
            <TableBody
              emptyContent={t('reports.no_retention_data')}
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
    const metrics = data;

    return (
      <div className="space-y-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label={t('reports.label_login_rate')}
            value={metrics ? `${(Number(metrics.login_rate ?? 0) * 100).toFixed(1)}%` : '\u2014'}
            icon={Users}
            color="primary"
            loading={loading}
          />
          <StatCard
            label={t('reports.label_trading_rate')}
            value={metrics ? `${(Number(metrics.trading_rate ?? 0) * 100).toFixed(1)}%` : '\u2014'}
            icon={TrendingUp}
            color="success"
            loading={loading}
          />
          <StatCard
            label={t('reports.label_listing_rate')}
            value={metrics ? `${(Number(metrics.listing_rate ?? 0) * 100).toFixed(1)}%` : '\u2014'}
            icon={BarChart3}
            color="warning"
            loading={loading}
          />
          <StatCard
            label={t('reports.label_messaging_rate')}
            value={metrics ? `${(Number(metrics.messaging_rate ?? 0) * 100).toFixed(1)}%` : '\u2014'}
            icon={Activity}
            color="secondary"
            loading={loading}
          />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Card shadow="sm">
            <CardBody className="p-4">
              <p className="text-sm text-default-500">{t('reports.active_30d_total')}</p>
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
              <p className="text-sm text-default-500">{t('reports.avg_sessions_per_user')}</p>
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
              <p className="text-sm text-default-500">{t('reports.avg_transactions_per_user')}</p>
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
          <h3 className="font-semibold">{t('reports.top_contributors')}</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          <Table aria-label={t('reports.label_top_contributors')} shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>{t('reports.col_rank')}</TableColumn>
              <TableColumn>{t('reports.col_member')}</TableColumn>
              <TableColumn className="text-right">{t('reports.col_given')}</TableColumn>
              <TableColumn className="text-right">{t('reports.col_received')}</TableColumn>
              <TableColumn className="text-right">{t('reports.col_transactions')}</TableColumn>
              <TableColumn className="text-right">{t('reports.col_listings')}</TableColumn>
            </TableHeader>
            <TableBody
              emptyContent={t('reports.no_contributor_data')}
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
                      <Avatar size="sm" src={resolveAvatarUrl(c.avatar_url) || undefined} name={c.name} />
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
        <Table aria-label={t('reports.label_least_active_members')} shadow="sm">
          <TableHeader>
            <TableColumn>{t('reports.col_member')}</TableColumn>
            <TableColumn>{t('reports.col_last_login')}</TableColumn>
            <TableColumn>{t('reports.col_transactions')}</TableColumn>
            <TableColumn>{t('reports.col_joined')}</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent={t('reports.no_inactive_members_found')}
            isLoading={loading}
            loadingContent={<Spinner />}
          >
            {members.map((m) => (
              <TableRow key={m.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Avatar size="sm" src={resolveAvatarUrl(m.avatar_url) || undefined} name={m.name} />
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
                    {m.last_login ? new Date(m.last_login).toLocaleDateString() : t('reports.never')}
                  </Chip>
                </TableCell>
                <TableCell className="text-sm">{m.transaction_count}</TableCell>
                <TableCell className="text-sm text-default-500">
                  {m.joined_at ? new Date(m.joined_at).toLocaleDateString() : '---'}
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
        title={t('reports.member_reports_page_title')}
        description={t('reports.member_reports_page_desc')}
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
              aria-label={t('reports.label_period')}
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
              {t('reports.export_csv')}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {t('reports.refresh')}
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
        <Tab key="active" title={<span className="flex items-center gap-1.5"><Users size={14} /> {t('reports.tab_active')}</span>} />
        <Tab key="registrations" title={<span className="flex items-center gap-1.5"><TrendingUp size={14} /> {t('reports.tab_registrations')}</span>} />
        <Tab key="retention" title={<span className="flex items-center gap-1.5"><UserCheck size={14} /> {t('reports.tab_retention')}</span>} />
        <Tab key="engagement" title={<span className="flex items-center gap-1.5"><Activity size={14} /> {t('reports.tab_engagement')}</span>} />
        <Tab key="top_contributors" title={<span className="flex items-center gap-1.5"><Trophy size={14} /> {t('reports.tab_top_contributors')}</span>} />
        <Tab key="least_active" title={<span className="flex items-center gap-1.5"><UserX size={14} /> {t('reports.tab_least_active')}</span>} />
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
