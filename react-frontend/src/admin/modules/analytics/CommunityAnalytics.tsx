// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Community Analytics Dashboard
 * Visualizes community health metrics with Recharts charts.
 * Data source: GET /api/v2/admin/community-analytics
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, CardHeader, Spinner, Button, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell } from '@heroui/react';
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
import { CHART_COLORS, CHART_COLOR_MAP } from '@/lib/chartColors';
import {
  TrendingUp,
  Users,
  Clock,
  Activity,
  Download,
  RefreshCw,
  BarChart3,
  PieChart as PieChartIcon,
  MapPin,
  Globe,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { api, tokenManager } from '@/lib/api';
import { LocationMap } from '@/components/location';
import { MAPS_ENABLED } from '@/lib/map-config';
import { StatCard, PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';
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

interface GeographyData {
  member_locations: Array<{ lat: number; lng: number; count: number; area: string }>;
  total_with_location: number;
  total_members: number;
  coverage_percentage: number;
  top_areas: Array<{ area: string; count: number; percentage: number }>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Chart color palette (works well in both light and dark modes)
// ─────────────────────────────────────────────────────────────────────────────

const PIE_COLORS = CHART_COLORS;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CommunityAnalytics() {
  const { t } = useTranslation('admin');
  usePageTitle(t('analytics.page_title'));

  const [data, setData] = useState<CommunityAnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [geoData, setGeoData] = useState<GeographyData | null>(null);
  const [geoLoading, setGeoLoading] = useState(false);

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

  const loadGeoData = useCallback(async () => {
    if (!MAPS_ENABLED) return;
    setGeoLoading(true);
    try {
      const res = await api.get('/v2/admin/community-analytics/geography');
      if (res.success && res.data) {
        setGeoData(res.data as GeographyData);
      }
    } catch {
      // Silently fail — geography section won't render
    } finally {
      setGeoLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
    loadGeoData();
  }, [loadData, loadGeoData]);

  const handleExport = async () => {
    try {
      const token = tokenManager.getAccessToken();
      const tenantId = tokenManager.getTenantId();
      const headers: Record<string, string> = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (tenantId) headers['X-Tenant-ID'] = tenantId;

      const apiBase = import.meta.env.VITE_API_BASE || '/api';
      const res = await fetch(`${apiBase}/v2/admin/community-analytics/export`, {
        headers,
        credentials: 'include',
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
        title={t('analytics.community_analytics_title')}
        description={t('analytics.community_analytics_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={handleExport}
              size="sm"
            >
              {t('analytics.export_csv')}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={() => { loadData(); loadGeoData(); }}
              isLoading={loading}
              isDisabled={loading}
              size="sm"
            >
              {t('analytics.refresh')}
            </Button>
          </div>
        }
      />

      {/* Stat Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('analytics.label_hours_exchanged_30d')}
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
          label={t('analytics.label_active_traders_30d')}
          value={data?.overview.active_traders_30d ?? '—'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={t('analytics.label_new_users_30d')}
          value={data?.overview.new_users_30d ?? '—'}
          icon={TrendingUp}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('analytics.label_engagement_rate')}
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
          <h3 className="font-semibold">{t('analytics.exchange_trends_title')}</h3>
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
                    <stop offset="5%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0.3} />
                    <stop offset="95%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0.02} />
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
                    value: t('analytics.chart_hours'),
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
                    value: t('analytics.chart_transactions'),
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
                  name={t('analytics.chart_hours_exchanged')}
                  stroke={CHART_COLOR_MAP.primary}
                  fill="url(#volumeGradient)"
                  strokeWidth={2}
                />
                <Line
                  yAxisId="count"
                  type="monotone"
                  dataKey="transaction_count"
                  name={t('analytics.chart_transactions')}
                  stroke={CHART_COLOR_MAP.success}
                  strokeWidth={2}
                  dot={{ r: 3 }}
                  activeDot={{ r: 5 }}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
              {t('analytics.no_exchange_trend_data')}
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
            <h3 className="font-semibold">{t('analytics.member_growth_title')}</h3>
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
                    name={t('analytics.chart_new_users')}
                    fill={CHART_COLOR_MAP.success}
                    radius={[4, 4, 0, 0]}
                    fillOpacity={0.8}
                  />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                {t('analytics.no_member_growth_data')}
              </p>
            )}
          </CardBody>
        </Card>

        {/* Category Demand */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <PieChartIcon size={18} className="text-secondary" />
            <h3 className="font-semibold">{t('analytics.category_demand_title')}</h3>
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
                    formatter={(value: number | undefined, name: string | undefined): [string, string] =>
                      [t('analytics.listings_count', { count: value ?? 0 }), name ?? '']
                    }
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                {t('analytics.no_category_data')}
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
            <h3 className="font-semibold">{t('analytics.top_earners_title')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <Table aria-label={t('analytics.label_top_earners')} shadow="sm" isStriped>
              <TableHeader>
                <TableColumn>{t('analytics.col_rank')}</TableColumn>
                <TableColumn>{t('analytics.col_name')}</TableColumn>
                <TableColumn className="text-right">{t('analytics.col_hours_earned')}</TableColumn>
              </TableHeader>
              <TableBody
                emptyContent={t('analytics.no_earner_data')}
                isLoading={loading}
                loadingContent={<Spinner />}
              >
                {(data?.top_earners ?? []).map((earner, index) => (
                  <TableRow key={earner.id}>
                    <TableCell className="font-medium text-default-600">{index + 1}</TableCell>
                    <TableCell className="text-foreground">{earner.name}</TableCell>
                    <TableCell className="text-right font-semibold text-success">{earner.total.toFixed(1)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>

        {/* Top Spenders */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Clock size={18} className="text-warning" />
            <h3 className="font-semibold">{t('analytics.top_spenders_title')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <Table aria-label={t('analytics.label_top_spenders')} shadow="sm" isStriped>
              <TableHeader>
                <TableColumn>{t('analytics.col_rank')}</TableColumn>
                <TableColumn>{t('analytics.col_name')}</TableColumn>
                <TableColumn className="text-right">{t('analytics.col_hours_spent')}</TableColumn>
              </TableHeader>
              <TableBody
                emptyContent={t('analytics.no_spender_data')}
                isLoading={loading}
                loadingContent={<Spinner />}
              >
                {(data?.top_spenders ?? []).map((spender, index) => (
                  <TableRow key={spender.id}>
                    <TableCell className="font-medium text-default-600">{index + 1}</TableCell>
                    <TableCell className="text-foreground">{spender.name}</TableCell>
                    <TableCell className="text-right font-semibold text-warning">{spender.total.toFixed(1)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      </div>

      {/* Geographic Distribution */}
      {MAPS_ENABLED && (
        <Card shadow="sm" className="mt-6">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Globe size={18} className="text-primary" />
            <h3 className="font-semibold">{t('analytics.geographic_distribution_title')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {geoLoading ? (
              <div className="flex h-[400px] items-center justify-center">
                <Spinner />
              </div>
            ) : geoData ? (
              <>
                {/* Stats row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                  <StatCard
                    label={t('analytics.label_members_with_location')}
                    value={`${geoData.total_with_location} / ${geoData.total_members}`}
                    icon={MapPin}
                    color="primary"
                  />
                  <StatCard
                    label={t('analytics.label_location_coverage')}
                    value={`${geoData.coverage_percentage}%`}
                    icon={Globe}
                    color={geoData.coverage_percentage > 50 ? 'success' : 'warning'}
                  />
                  <StatCard
                    label={t('analytics.label_top_area')}
                    value={geoData.top_areas[0]?.area || 'N/A'}
                    icon={MapPin}
                    color="secondary"
                  />
                </div>

                {/* Map */}
                {geoData.member_locations.length > 0 && (
                  <LocationMap
                    markers={geoData.member_locations.map((loc, i) => ({
                      id: `cluster-${i}`,
                      lat: Number(loc.lat),
                      lng: Number(loc.lng),
                      title: `${loc.area}: ${loc.count} member${loc.count > 1 ? 's' : ''}`,
                      pinGlyph: String(loc.count),
                      infoContent: (
                        <div className="p-2">
                          <h4 className="font-semibold text-sm text-gray-900">{loc.area}</h4>
                          <p className="text-xs text-gray-600">
                            {loc.count} member{loc.count > 1 ? 's' : ''}
                          </p>
                        </div>
                      ),
                    }))}
                    height="400px"
                    fitBounds
                    className="mb-6 rounded-xl"
                  />
                )}

                {/* Top Areas list */}
                {geoData.top_areas.length > 0 && (
                  <div>
                    <h4 className="text-sm font-semibold text-foreground mb-3">{t('analytics.top_areas_title')}</h4>
                    <div className="space-y-2">
                      {geoData.top_areas.map((area, i) => (
                        <div key={area.area} className="flex items-center gap-3">
                          <span className="text-xs text-default-400 w-6">{i + 1}.</span>
                          <span className="text-sm text-foreground flex-1">{area.area}</span>
                          <span className="text-xs text-default-500">
                            {area.count} member{area.count !== 1 ? 's' : ''}
                          </span>
                          <div className="w-24 h-1.5 rounded-full bg-default-100 overflow-hidden">
                            <div
                              className="h-full rounded-full bg-primary"
                              style={{ width: `${Math.min(area.percentage, 100)}%` }}
                            />
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {geoData.member_locations.length === 0 && geoData.top_areas.length === 0 && (
                  <p className="py-8 text-center text-sm text-default-400">
                    {t('analytics.no_location_data')}
                  </p>
                )}
              </>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                {t('analytics.geographic_data_unavailable')}
              </p>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default CommunityAnalytics;
