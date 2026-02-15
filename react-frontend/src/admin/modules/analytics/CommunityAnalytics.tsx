/**
 * Community Analytics Dashboard
 * Visualizes community health metrics with Recharts charts.
 * Data source: GET /api/v2/admin/community-analytics
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, CardHeader, Spinner, Button } from '@heroui/react';
import {
  BarChart,
  PieChart,
  Line,
  Bar,
  Pie,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
  Legend,
  Area,
  AreaChart,
} from 'recharts';
import {
  TrendingUp,
  Users,
  Clock,
  Activity,
  Download,
  RefreshCw,
  BarChart3,
  PieChart as PieChartIcon,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface CommunityAnalyticsData {
  overview: {
    total_credits_circulation: number;
    transaction_volume_30d: number;
    transaction_count_30d: number;
    active_traders_30d: number;
    new_users_30d: number;
    avg_transaction_size: number;
  };
  monthly_trends: Array<{
    month: string;
    transaction_count: number;
    total_volume: number;
    new_users: number;
  }>;
  weekly_trends: Array<{
    week: string;
    transaction_count: number;
    total_volume: number;
  }>;
  top_earners: Array<{ id: number; name: string; total: number }>;
  top_spenders: Array<{ id: number; name: string; total: number }>;
  gamification: {
    total_xp: number;
    total_badges: number;
    engagement_rate: number;
  } | null;
  matching: {
    total_matches: number;
    conversion_rate: number;
  } | null;
  category_demand: Array<{
    name: string;
    listing_count: number;
    active_count: number;
  }>;
  engagement_rate: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Chart color palette (works well in both light and dark modes)
// ─────────────────────────────────────────────────────────────────────────────

const PIE_COLORS = [
  '#6366f1', // indigo
  '#10b981', // emerald
  '#f59e0b', // amber
  '#ef4444', // red
  '#8b5cf6', // violet
  '#06b6d4', // cyan
  '#f97316', // orange
  '#ec4899', // pink
  '#14b8a6', // teal
  '#a855f7', // purple
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CommunityAnalytics() {
  usePageTitle('Community Analytics');

  const [data, setData] = useState<CommunityAnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/v2/admin/community-analytics');
      if (res.success && res.data) {
        setData(res.data as CommunityAnalyticsData);
      }
    } catch {
      // Silently handle — charts will show empty state
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleExport = async () => {
    try {
      const token = localStorage.getItem('nexus_access_token');
      const tenantId = localStorage.getItem('nexus_tenant_id');
      const headers: Record<string, string> = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (tenantId) headers['X-Tenant-ID'] = tenantId;

      const apiBase = import.meta.env.VITE_API_BASE || '/api';
      const res = await fetch(`${apiBase}/v2/admin/community-analytics/export`, {
        headers,
      });
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'community-analytics.csv';
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // Export failed silently
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title="Community Analytics"
        description="Insights into community health, engagement, and exchange activity"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={handleExport}
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

      {/* Stat Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Hours Exchanged (30d)"
          value={
            data
              ? Number(data.overview.transaction_volume_30d).toFixed(1)
              : '—'
          }
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Active Traders (30d)"
          value={data?.overview.active_traders_30d ?? '—'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="New Users (30d)"
          value={data?.overview.new_users_30d ?? '—'}
          icon={TrendingUp}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Engagement Rate"
          value={
            data
              ? `${(data.engagement_rate * 100).toFixed(1)}%`
              : '—'
          }
          icon={Activity}
          color="secondary"
          loading={loading}
        />
      </div>

      {/* Exchange Trends (12 months) */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <BarChart3 size={18} className="text-primary" />
          <h3 className="font-semibold">Exchange Trends (12 months)</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[350px] items-center justify-center">
              <Spinner />
            </div>
          ) : data && data.monthly_trends.length > 0 ? (
            <ResponsiveContainer width="100%" height={350}>
              <AreaChart
                data={data.monthly_trends}
                margin={{ top: 10, right: 20, left: 0, bottom: 0 }}
              >
                <defs>
                  <linearGradient
                    id="volumeGradient"
                    x1="0"
                    y1="0"
                    x2="0"
                    y2="1"
                  >
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="currentColor"
                  className="text-default-200"
                />
                <XAxis
                  dataKey="month"
                  tick={{ fontSize: 12 }}
                  className="text-default-500"
                />
                <YAxis
                  yAxisId="volume"
                  orientation="left"
                  tick={{ fontSize: 12 }}
                  className="text-default-500"
                  label={{
                    value: 'Hours',
                    angle: -90,
                    position: 'insideLeft',
                    style: { fontSize: 12 },
                  }}
                />
                <YAxis
                  yAxisId="count"
                  orientation="right"
                  tick={{ fontSize: 12 }}
                  className="text-default-500"
                  label={{
                    value: 'Transactions',
                    angle: 90,
                    position: 'insideRight',
                    style: { fontSize: 12 },
                  }}
                />
                <Tooltip
                  contentStyle={{
                    borderRadius: '8px',
                    border: '1px solid hsl(var(--heroui-default-200))',
                    backgroundColor: 'hsl(var(--heroui-content1))',
                    color: 'hsl(var(--heroui-foreground))',
                  }}
                  labelStyle={{ fontWeight: 600 }}
                />
                <Legend />
                <Area
                  yAxisId="volume"
                  type="monotone"
                  dataKey="total_volume"
                  name="Hours Exchanged"
                  stroke="#6366f1"
                  fill="url(#volumeGradient)"
                  strokeWidth={2}
                />
                <Line
                  yAxisId="count"
                  type="monotone"
                  dataKey="transaction_count"
                  name="Transactions"
                  stroke="#10b981"
                  strokeWidth={2}
                  dot={{ r: 3 }}
                  activeDot={{ r: 5 }}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
              No exchange trend data available yet
            </p>
          )}
        </CardBody>
      </Card>

      {/* Member Growth + Category Demand */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
        {/* Member Growth */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Users size={18} className="text-success" />
            <h3 className="font-semibold">Member Growth</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[300px] items-center justify-center">
                <Spinner />
              </div>
            ) : data && data.monthly_trends.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart
                  data={data.monthly_trends}
                  margin={{ top: 10, right: 10, left: 0, bottom: 0 }}
                >
                  <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="currentColor"
                    className="text-default-200"
                  />
                  <XAxis
                    dataKey="month"
                    tick={{ fontSize: 11 }}
                    className="text-default-500"
                  />
                  <YAxis
                    tick={{ fontSize: 11 }}
                    className="text-default-500"
                    allowDecimals={false}
                  />
                  <Tooltip
                    contentStyle={{
                      borderRadius: '8px',
                      border: '1px solid hsl(var(--heroui-default-200))',
                      backgroundColor: 'hsl(var(--heroui-content1))',
                      color: 'hsl(var(--heroui-foreground))',
                    }}
                  />
                  <Bar
                    dataKey="new_users"
                    name="New Users"
                    fill="#10b981"
                    radius={[4, 4, 0, 0]}
                    fillOpacity={0.8}
                  />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                No member growth data available yet
              </p>
            )}
          </CardBody>
        </Card>

        {/* Category Demand */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <PieChartIcon size={18} className="text-secondary" />
            <h3 className="font-semibold">Category Demand</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[300px] items-center justify-center">
                <Spinner />
              </div>
            ) : data && data.category_demand.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={data.category_demand.filter(
                      (c) => c.listing_count > 0
                    )}
                    dataKey="listing_count"
                    nameKey="name"
                    cx="50%"
                    cy="50%"
                    outerRadius={100}
                    innerRadius={50}
                    paddingAngle={2}
                    label={({ name, percent }) =>
                      `${name} (${((percent ?? 0) * 100).toFixed(0)}%)`
                    }
                    labelLine={{ strokeWidth: 1 }}
                  >
                    {data.category_demand
                      .filter((c) => c.listing_count > 0)
                      .map((_, index) => (
                        <Cell
                          key={`cell-${index}`}
                          fill={PIE_COLORS[index % PIE_COLORS.length]}
                          fillOpacity={0.85}
                        />
                      ))}
                  </Pie>
                  <Tooltip
                    contentStyle={{
                      borderRadius: '8px',
                      border: '1px solid hsl(var(--heroui-default-200))',
                      backgroundColor: 'hsl(var(--heroui-content1))',
                      color: 'hsl(var(--heroui-foreground))',
                    }}
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    formatter={((value: number, name: string) =>
                      [`${value} listings`, name]) as any}
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                No category data available yet
              </p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Top Earners + Top Spenders */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Top Earners */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <TrendingUp size={18} className="text-success" />
            <h3 className="font-semibold">Top Earners (30 days)</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-48 items-center justify-center">
                <Spinner />
              </div>
            ) : data && data.top_earners.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-divider text-left">
                      <th className="pb-2 pr-4 font-medium text-default-500">
                        Rank
                      </th>
                      <th className="pb-2 pr-4 font-medium text-default-500">
                        Name
                      </th>
                      <th className="pb-2 text-right font-medium text-default-500">
                        Hours Earned
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.top_earners.map((earner, index) => (
                      <tr
                        key={earner.id}
                        className="border-b border-divider last:border-0"
                      >
                        <td className="py-2.5 pr-4 font-medium text-default-600">
                          {index + 1}
                        </td>
                        <td className="py-2.5 pr-4 text-foreground">
                          {earner.name}
                        </td>
                        <td className="py-2.5 text-right font-semibold text-success">
                          {earner.total.toFixed(1)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                No earner data available yet
              </p>
            )}
          </CardBody>
        </Card>

        {/* Top Spenders */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Clock size={18} className="text-warning" />
            <h3 className="font-semibold">Top Spenders (30 days)</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-48 items-center justify-center">
                <Spinner />
              </div>
            ) : data && data.top_spenders.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-divider text-left">
                      <th className="pb-2 pr-4 font-medium text-default-500">
                        Rank
                      </th>
                      <th className="pb-2 pr-4 font-medium text-default-500">
                        Name
                      </th>
                      <th className="pb-2 text-right font-medium text-default-500">
                        Hours Spent
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.top_spenders.map((spender, index) => (
                      <tr
                        key={spender.id}
                        className="border-b border-divider last:border-0"
                      >
                        <td className="py-2.5 pr-4 font-medium text-default-600">
                          {index + 1}
                        </td>
                        <td className="py-2.5 pr-4 text-foreground">
                          {spender.name}
                        </td>
                        <td className="py-2.5 text-right font-semibold text-warning">
                          {spender.total.toFixed(1)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                No spender data available yet
              </p>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default CommunityAnalytics;
