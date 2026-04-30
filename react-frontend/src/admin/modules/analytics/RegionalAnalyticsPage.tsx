// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG59 — Regional Analytics Product
 * Sellable analytics dashboard for municipalities and SME partners.
 * Shows geographic density, demand/supply heatmaps, demographics,
 * engagement trends, volunteer breakdowns, and help-request analysis.
 *
 * ADMIN IS ENGLISH-ONLY — NO t() CALLS.
 */

import { useState, useCallback, useRef } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Select,
  SelectItem,
  Chip,
  Tabs,
  Tab,
  Spinner,
} from '@heroui/react';
import Map from 'lucide-react/icons/map';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import Download from 'lucide-react/icons/download';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Activity from 'lucide-react/icons/activity';
import Heart from 'lucide-react/icons/heart';
import { usePageTitle } from '@/hooks';
import api from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type Period = 'last_30d' | 'last_90d' | 'last_12m' | 'all_time';

interface OverviewData {
  active_members: number;
  vol_hours_this_month: number;
  help_requests_this_month: number;
  most_needed_category: string;
}

interface HeatmapCell {
  lat: number;
  lng: number;
  count: number;
}

interface DemandSupplyRow {
  category_id: number;
  category_name: string;
  request_count: number;
  offer_count: number;
  ratio: number;
  trend: '↑' | '↓' | '→';
}

interface DemographicsData {
  age_groups: Record<string, number>;
  languages: Array<{ language: string; count: number }>;
  monthly_growth: Array<{ month: string; new_members: number; cumulative: number }>;
}

interface EngagementRow {
  month: string;
  active_members: number;
  vol_hours: number;
  new_listings: number;
  new_events: number;
  help_requests: number;
}

interface VolunteerData {
  top_orgs: Array<{ org_id: number; org_name: string; total_hours: number; volunteers: number }>;
  avg_hours_per_volunteer: number;
  total_hours: number;
  reciprocity_ratio: number;
}

interface HelpRequestCategoryRow {
  category: string;
  total: number;
  resolved_count: number;
  resolution_rate: number;
  avg_resolution_days: number | null;
}

interface HelpRequestData {
  by_category: HelpRequestCategoryRow[];
  resolution_trend: Array<{ month: string; total: number; resolved: number; resolution_rate: number }>;
}

type TabData =
  | HeatmapCell[]
  | DemandSupplyRow[]
  | DemographicsData
  | EngagementRow[]
  | VolunteerData
  | HelpRequestData
  | { error: string };

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function isError(data: unknown): data is { error: string } {
  return typeof data === 'object' && data !== null && 'error' in data;
}

function pct(value: number, total: number): string {
  if (total === 0) return '0%';
  return `${Math.round((value / total) * 100)}%`;
}

const AGE_GROUP_LABELS: Record<string, string> = {
  under_25: 'Under 25',
  '25_34': '25–34',
  '35_44': '35–44',
  '45_54': '45–54',
  '55_64': '55–64',
  '65_plus': '65+',
  unknown: 'Unknown',
};

const AGE_GROUP_ORDER = ['under_25', '25_34', '35_44', '45_54', '55_64', '65_plus', 'unknown'];

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

function TabPanel({ loading, error, children }: { loading: boolean; error: string | null; children: React.ReactNode }) {
  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }
  if (error) {
    return (
      <div className="rounded-lg bg-danger-50 p-4 text-sm text-danger">
        {error === 'data_unavailable'
          ? 'Data is not available for this section — the required tables may not be populated yet.'
          : `Error loading data: ${error}`}
      </div>
    );
  }
  return <>{children}</>;
}

/** Horizontal percentage bar row */
function PercentBar({ label, value, total, color = 'bg-primary' }: { label: string; value: number; total: number; color?: string }) {
  const width = total > 0 ? Math.round((value / total) * 100) : 0;
  return (
    <div className="flex items-center gap-3 py-1.5">
      <div className="w-28 shrink-0 text-right text-sm text-default-600">{label}</div>
      <div className="flex-1 rounded-full bg-default-100" style={{ height: 12 }}>
        <div className={`h-full rounded-full ${color}`} style={{ width: `${width}%`, minWidth: width > 0 ? 4 : 0 }} />
      </div>
      <div className="w-20 shrink-0 text-sm text-default-500">
        {value.toLocaleString()} ({width}%)
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab panels
// ─────────────────────────────────────────────────────────────────────────────

function HeatmapTab({ data }: { data: HeatmapCell[] }) {
  const top10 = data.slice(0, 10);
  const maxCount = top10[0]?.count ?? 1;

  return (
    <div className="space-y-4">
      <p className="text-sm text-default-500">
        Showing the top 10 most active grid cells (~1 km resolution). For a full interactive map, use the Export button to download GeoJSON.
      </p>
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
              <th className="pb-2 pr-4">#</th>
              <th className="pb-2 pr-4">Latitude</th>
              <th className="pb-2 pr-4">Longitude</th>
              <th className="pb-2 pr-4">Members</th>
              <th className="pb-2">Density</th>
            </tr>
          </thead>
          <tbody>
            {top10.map((cell, i) => {
              const barWidth = Math.round((cell.count / maxCount) * 100);
              return (
                <tr key={i} className="border-b border-divider/50">
                  <td className="py-2 pr-4 text-default-400">{i + 1}</td>
                  <td className="py-2 pr-4 font-mono">{cell.lat.toFixed(2)}</td>
                  <td className="py-2 pr-4 font-mono">{cell.lng.toFixed(2)}</td>
                  <td className="py-2 pr-4 font-semibold">{cell.count}</td>
                  <td className="py-2">
                    <div className="h-3 w-32 rounded-full bg-default-100">
                      <div className="h-full rounded-full bg-primary" style={{ width: `${barWidth}%` }} />
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {data.length === 0 && (
        <p className="text-sm text-default-400">No geographic data available — members may not have set their location.</p>
      )}
    </div>
  );
}

function DemographicsTab({ data }: { data: DemographicsData }) {
  const totalAge = Object.values(data.age_groups).reduce((a, b) => a + b, 0);
  const totalLang = data.languages.reduce((a, b) => a + b.count, 0);

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      {/* Age distribution */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-base font-semibold">Age Distribution</h3>
        </CardHeader>
        <CardBody>
          {AGE_GROUP_ORDER.map((key) => {
            const count = data.age_groups[key] ?? 0;
            return (
              <PercentBar
                key={key}
                label={AGE_GROUP_LABELS[key] ?? key}
                value={count}
                total={totalAge}
                color="bg-primary"
              />
            );
          })}
          <p className="mt-3 text-xs text-default-400">Total with data: {totalAge.toLocaleString()}</p>
        </CardBody>
      </Card>

      {/* Language distribution */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-base font-semibold">Language Distribution</h3>
        </CardHeader>
        <CardBody>
          {data.languages.slice(0, 12).map((l) => (
            <PercentBar
              key={l.language}
              label={l.language.toUpperCase()}
              value={l.count}
              total={totalLang}
              color="bg-secondary"
            />
          ))}
          <p className="mt-3 text-xs text-default-400">Total active members: {totalLang.toLocaleString()}</p>
        </CardBody>
      </Card>

      {/* Monthly growth table */}
      <Card shadow="sm" className="lg:col-span-2">
        <CardHeader>
          <h3 className="text-base font-semibold">Member Growth — Last 12 Months</h3>
        </CardHeader>
        <CardBody>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
                  <th className="pb-2 pr-6">Month</th>
                  <th className="pb-2 pr-6">New Members</th>
                  <th className="pb-2">Cumulative Total</th>
                </tr>
              </thead>
              <tbody>
                {data.monthly_growth.map((row) => (
                  <tr key={row.month} className="border-b border-divider/50">
                    <td className="py-2 pr-6 font-mono">{row.month}</td>
                    <td className="py-2 pr-6 font-semibold text-success">{row.new_members.toLocaleString()}</td>
                    <td className="py-2 text-default-600">{row.cumulative.toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

function DemandSupplyTab({ data }: { data: DemandSupplyRow[] }) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
            <th className="pb-2 pr-4">Category</th>
            <th className="pb-2 pr-4">Requests</th>
            <th className="pb-2 pr-4">Offers</th>
            <th className="pb-2 pr-4">Ratio (R/O)</th>
            <th className="pb-2">Trend</th>
          </tr>
        </thead>
        <tbody>
          {data.map((row) => (
            <tr key={row.category_id} className="border-b border-divider/50">
              <td className="py-2 pr-4 font-medium">{row.category_name}</td>
              <td className="py-2 pr-4">{row.request_count.toLocaleString()}</td>
              <td className="py-2 pr-4">{row.offer_count.toLocaleString()}</td>
              <td className="py-2 pr-4">
                <Chip
                  size="sm"
                  color={row.ratio >= 2 ? 'danger' : row.ratio >= 1 ? 'warning' : 'success'}
                  variant="flat"
                >
                  {row.ratio === 999 ? '∞' : row.ratio.toFixed(2)}
                </Chip>
              </td>
              <td className="py-2 text-lg">{row.trend}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {data.length === 0 && (
        <p className="mt-4 text-sm text-default-400">No listing data available for this period.</p>
      )}
    </div>
  );
}

function EngagementTab({ data }: { data: EngagementRow[] }) {
  const maxActive = Math.max(...data.map((r) => r.active_members), 1);

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
            <th className="pb-2 pr-4">Month</th>
            <th className="pb-2 pr-4">Active Members</th>
            <th className="pb-2 pr-4">Vol Hours</th>
            <th className="pb-2 pr-4">New Listings</th>
            <th className="pb-2 pr-4">New Events</th>
            <th className="pb-2">Help Requests</th>
          </tr>
        </thead>
        <tbody>
          {data.map((row) => {
            const barWidth = Math.round((row.active_members / maxActive) * 80);
            return (
              <tr key={row.month} className="border-b border-divider/50">
                <td className="py-2 pr-4 font-mono">{row.month}</td>
                <td className="py-2 pr-4">
                  <div className="flex items-center gap-2">
                    <div className="h-3 w-20 rounded-full bg-default-100">
                      <div className="h-full rounded-full bg-primary" style={{ width: `${barWidth}%` }} />
                    </div>
                    <span className="font-semibold">{row.active_members.toLocaleString()}</span>
                  </div>
                </td>
                <td className="py-2 pr-4">{row.vol_hours.toLocaleString()}</td>
                <td className="py-2 pr-4">{row.new_listings.toLocaleString()}</td>
                <td className="py-2 pr-4">{row.new_events.toLocaleString()}</td>
                <td className="py-2">{row.help_requests.toLocaleString()}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
      {data.length === 0 && (
        <p className="mt-4 text-sm text-default-400">No engagement data available for this period.</p>
      )}
    </div>
  );
}

function VolunteerTab({ data }: { data: VolunteerData }) {
  return (
    <div className="space-y-6">
      {/* Summary stats */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-xs text-default-500">Total Hours</p>
            <p className="text-2xl font-bold text-primary">{data.total_hours.toLocaleString()}</p>
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-xs text-default-500">Avg Hours / Volunteer</p>
            <p className="text-2xl font-bold text-success">{data.avg_hours_per_volunteer.toLocaleString()}</p>
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-xs text-default-500">Reciprocity Ratio</p>
            <p className="text-2xl font-bold text-secondary">
              {(data.reciprocity_ratio * 100).toFixed(1)}%
            </p>
            <p className="text-xs text-default-400">Volunteers who also post listings</p>
          </CardBody>
        </Card>
      </div>

      {/* Top orgs table */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-default-600">Top Organisations by Volunteer Hours</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
                <th className="pb-2 pr-4">#</th>
                <th className="pb-2 pr-4">Organisation</th>
                <th className="pb-2 pr-4">Total Hours</th>
                <th className="pb-2">Volunteers</th>
              </tr>
            </thead>
            <tbody>
              {data.top_orgs.map((org, i) => (
                <tr key={org.org_id} className="border-b border-divider/50">
                  <td className="py-2 pr-4 text-default-400">{i + 1}</td>
                  <td className="py-2 pr-4 font-medium">{org.org_name}</td>
                  <td className="py-2 pr-4 font-semibold">{org.total_hours.toLocaleString()}</td>
                  <td className="py-2">{org.volunteers.toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {data.top_orgs.length === 0 && (
            <p className="mt-4 text-sm text-default-400">No organisation volunteer data available.</p>
          )}
        </div>
      </div>
    </div>
  );
}

function HelpRequestsTab({ data }: { data: HelpRequestData }) {
  return (
    <div className="space-y-6">
      {/* By category */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-default-600">By Category</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
                <th className="pb-2 pr-4">Category</th>
                <th className="pb-2 pr-4">Total</th>
                <th className="pb-2 pr-4">Resolved</th>
                <th className="pb-2 pr-4">Resolution Rate</th>
                <th className="pb-2">Avg Days to Resolve</th>
              </tr>
            </thead>
            <tbody>
              {data.by_category.map((row) => (
                <tr key={row.category} className="border-b border-divider/50">
                  <td className="py-2 pr-4 font-medium capitalize">{row.category}</td>
                  <td className="py-2 pr-4">{row.total.toLocaleString()}</td>
                  <td className="py-2 pr-4">{row.resolved_count.toLocaleString()}</td>
                  <td className="py-2 pr-4">
                    <Chip
                      size="sm"
                      color={row.resolution_rate >= 70 ? 'success' : row.resolution_rate >= 40 ? 'warning' : 'danger'}
                      variant="flat"
                    >
                      {row.resolution_rate}%
                    </Chip>
                  </td>
                  <td className="py-2">
                    {row.avg_resolution_days != null ? `${row.avg_resolution_days} days` : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {data.by_category.length === 0 && (
            <p className="mt-4 text-sm text-default-400">No help request data available for this period.</p>
          )}
        </div>
      </div>

      {/* Resolution trend */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-default-600">Resolution Rate Trend — Last 6 Months</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-divider text-left text-xs font-semibold uppercase text-default-500">
                <th className="pb-2 pr-4">Month</th>
                <th className="pb-2 pr-4">Total</th>
                <th className="pb-2 pr-4">Resolved</th>
                <th className="pb-2">Resolution Rate</th>
              </tr>
            </thead>
            <tbody>
              {data.resolution_trend.map((row) => (
                <tr key={row.month} className="border-b border-divider/50">
                  <td className="py-2 pr-4 font-mono">{row.month}</td>
                  <td className="py-2 pr-4">{row.total.toLocaleString()}</td>
                  <td className="py-2 pr-4">{row.resolved.toLocaleString()}</td>
                  <td className="py-2">
                    <Chip
                      size="sm"
                      color={row.resolution_rate >= 70 ? 'success' : row.resolution_rate >= 40 ? 'warning' : 'danger'}
                      variant="flat"
                    >
                      {row.resolution_rate}%
                    </Chip>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main page
// ─────────────────────────────────────────────────────────────────────────────

export default function RegionalAnalyticsPage() {
  usePageTitle('Regional Analytics');

  const [period, setPeriod] = useState<Period>('last_30d');
  const [overviewData, setOverviewData] = useState<OverviewData | null>(null);
  const [overviewLoading, setOverviewLoading] = useState(false);
  const [overviewError, setOverviewError] = useState<string | null>(null);
  const [invalidating, setInvalidating] = useState(false);

  // Per-tab data state
  const [tabData, setTabData] = useState<Record<string, TabData | null>>({});
  const [tabLoading, setTabLoading] = useState<Record<string, boolean>>({});
  const [tabError, setTabError] = useState<Record<string, string | null>>({});

  // Track which tabs have been loaded for the current period
  const loadedTabs = useRef<Set<string>>(new Set());

  const BASE = '/v2/admin/regional-analytics';

  // Load overview on mount / period change
  const loadOverview = useCallback(async () => {
    setOverviewLoading(true);
    setOverviewError(null);
    try {
      const res = await api.get(`${BASE}/overview`);
      const data = (res.data as Record<string, unknown>)?.data ?? res.data;
      if (isError(data)) {
        setOverviewError(data.error);
      } else {
        setOverviewData(data as OverviewData);
      }
    } catch {
      setOverviewError('Failed to load overview');
    } finally {
      setOverviewLoading(false);
    }
  }, []);

  // Load a specific tab's data
  const loadTab = useCallback(async (tabKey: string) => {
    const cacheKey = `${tabKey}:${period}`;
    if (loadedTabs.current.has(cacheKey)) return;
    loadedTabs.current.add(cacheKey);

    setTabLoading((prev) => ({ ...prev, [tabKey]: true }));
    setTabError((prev) => ({ ...prev, [tabKey]: null }));

    const periodParam = `?period=${period}`;

    const endpointMap: Record<string, string> = {
      heatmap: `${BASE}/heatmap${periodParam}`,
      demand: `${BASE}/demand-supply${periodParam}`,
      demographics: `${BASE}/demographics`,
      engagement: `${BASE}/engagement-trends${periodParam}`,
      volunteer: `${BASE}/volunteer-breakdown${periodParam}`,
      help: `${BASE}/help-requests${periodParam}`,
    };

    try {
      const endpoint = endpointMap[tabKey] ?? `${BASE}/${tabKey}`;
      const res = await api.get(endpoint);
      const data = (res.data as Record<string, unknown>)?.data ?? res.data;
      if (isError(data)) {
        setTabError((prev) => ({ ...prev, [tabKey]: data.error }));
      } else {
        setTabData((prev) => ({ ...prev, [tabKey]: data as TabData }));
      }
    } catch {
      setTabError((prev) => ({ ...prev, [tabKey]: 'Failed to load data' }));
    } finally {
      setTabLoading((prev) => ({ ...prev, [tabKey]: false }));
    }
  }, [period]);

  // Initial load
  useState(() => {
    loadOverview();
  });

  // Handle period change — clear tab cache
  const handlePeriodChange = (newPeriod: Period) => {
    setPeriod(newPeriod);
    loadedTabs.current.clear();
    setTabData({});
    setTabLoading({});
    setTabError({});
    loadOverview();
  };

  // Handle tab selection
  const handleTabChange = (key: React.Key) => {
    loadTab(key as string);
  };

  // Invalidate cache
  const handleInvalidate = async () => {
    setInvalidating(true);
    try {
      await api.post(`${BASE}/invalidate-cache`);
      loadedTabs.current.clear();
      setTabData({});
      setTabLoading({});
      setTabError({});
      setOverviewData(null);
      loadOverview();
    } catch {
      // Silently fail
    } finally {
      setInvalidating(false);
    }
  };

  // Export report as JSON download
  const handleExport = async () => {
    try {
      const res = await api.get(`${BASE}/export?period=${period}`);
      const payload = res.data as { data?: unknown } | undefined;
      const data = payload?.data ?? res.data;
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `regional-analytics-${period}-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // Silently fail
    }
  };

  return (
    <div className="space-y-6 p-6">
      <PageHeader
        title="Regional Analytics"
        subtitle="Sellable analytics product for municipalities and SME partners — geographic density, engagement trends, and community insights."
        icon={<BarChart3 size={24} />}
      />

      {/* Controls bar */}
      <div className="flex flex-wrap items-center gap-3">
        <Select
          label="Period"
          selectedKeys={[period]}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as Period;
            if (val) handlePeriodChange(val);
          }}
          className="w-44"
          size="sm"
          variant="bordered"
        >
          <SelectItem key="last_30d">Last 30 days</SelectItem>
          <SelectItem key="last_90d">Last 90 days</SelectItem>
          <SelectItem key="last_12m">Last 12 months</SelectItem>
          <SelectItem key="all_time">All time</SelectItem>
        </Select>

        <Button
          size="sm"
          variant="bordered"
          startContent={<RefreshCw size={14} />}
          isLoading={invalidating}
          onPress={handleInvalidate}
        >
          Refresh Cache
        </Button>

        <Button
          size="sm"
          color="primary"
          variant="flat"
          startContent={<Download size={14} />}
          onPress={handleExport}
        >
          Export Report
        </Button>
      </div>

      {/* Hero stat cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard
          label="Active Members"
          value={overviewData?.active_members ?? '—'}
          icon={Users}
          color="primary"
          loading={overviewLoading}
          description="Last 30 days (with vol activity)"
        />
        <StatCard
          label="Vol Hours This Month"
          value={overviewData?.vol_hours_this_month ?? '—'}
          icon={Activity}
          color="success"
          loading={overviewLoading}
          description="Approved volunteer hours"
        />
        <StatCard
          label="Help Requests This Month"
          value={overviewData?.help_requests_this_month ?? '—'}
          icon={Heart}
          color="warning"
          loading={overviewLoading}
          description="New requests this calendar month"
        />
        <StatCard
          label="Most Needed Category"
          value={overviewData?.most_needed_category ?? '—'}
          icon={TrendingUp}
          color="secondary"
          loading={overviewLoading}
          description="By request volume (last 30d)"
        />
      </div>

      {/* Section tabs */}
      <Card shadow="sm">
        <CardBody className="p-0">
          <Tabs
            aria-label="Regional Analytics Sections"
            variant="underlined"
            className="px-4 pt-4"
            onSelectionChange={handleTabChange}
          >
            {/* Member Heatmap */}
            <Tab
              key="heatmap"
              title={
                <div className="flex items-center gap-1.5">
                  <Map size={14} />
                  <span>Member Heatmap</span>
                </div>
              }
            >
              <div className="p-4">
                <h2 className="mb-4 text-base font-semibold">Geographic Activity Density</h2>
                <TabPanel loading={!!tabLoading['heatmap']} error={tabError['heatmap'] ?? null}>
                  {tabData['heatmap'] && !isError(tabData['heatmap']) && (
                    <HeatmapTab data={tabData['heatmap'] as HeatmapCell[]} />
                  )}
                  {!tabData['heatmap'] && !tabLoading['heatmap'] && !tabError['heatmap'] && (
                    <p className="text-sm text-default-400">Select this tab to load data.</p>
                  )}
                </TabPanel>
              </div>
            </Tab>

            {/* Demographics */}
            <Tab
              key="demographics"
              title={
                <div className="flex items-center gap-1.5">
                  <Users size={14} />
                  <span>Demographics</span>
                </div>
              }
            >
              <div className="p-4">
                <h2 className="mb-4 text-base font-semibold">Member Demographics</h2>
                <TabPanel loading={!!tabLoading['demographics']} error={tabError['demographics'] ?? null}>
                  {tabData['demographics'] && !isError(tabData['demographics']) && (
                    <DemographicsTab data={tabData['demographics'] as DemographicsData} />
                  )}
                </TabPanel>
              </div>
            </Tab>

            {/* Demand & Supply */}
            <Tab
              key="demand"
              title={
                <div className="flex items-center gap-1.5">
                  <BarChart3 size={14} />
                  <span>Demand &amp; Supply</span>
                </div>
              }
            >
              <div className="p-4">
                <h2 className="mb-4 text-base font-semibold">Demand vs Supply by Category</h2>
                <p className="mb-4 text-sm text-default-500">
                  Ratio = Requests ÷ Offers. {'>'} 1 means more demand than supply. ↑ = ratio rose vs prior period.
                </p>
                <TabPanel loading={!!tabLoading['demand']} error={tabError['demand'] ?? null}>
                  {tabData['demand'] && !isError(tabData['demand']) && (
                    <DemandSupplyTab data={tabData['demand'] as DemandSupplyRow[]} />
                  )}
                </TabPanel>
              </div>
            </Tab>

            {/* Engagement Trends */}
            <Tab
              key="engagement"
              title={
                <div className="flex items-center gap-1.5">
                  <TrendingUp size={14} />
                  <span>Engagement Trends</span>
                </div>
              }
            >
              <div className="p-4">
                <h2 className="mb-4 text-base font-semibold">Monthly Engagement Metrics</h2>
                <TabPanel loading={!!tabLoading['engagement']} error={tabError['engagement'] ?? null}>
                  {tabData['engagement'] && !isError(tabData['engagement']) && (
                    <EngagementTab data={tabData['engagement'] as EngagementRow[]} />
                  )}
                </TabPanel>
              </div>
            </Tab>

            {/* Volunteer Breakdown */}
            <Tab
              key="volunteer"
              title={
                <div className="flex items-center gap-1.5">
                  <Activity size={14} />
                  <span>Volunteer Breakdown</span>
                </div>
              }
            >
              <div className="p-4">
                <h2 className="mb-4 text-base font-semibold">Volunteer Activity</h2>
                <TabPanel loading={!!tabLoading['volunteer']} error={tabError['volunteer'] ?? null}>
                  {tabData['volunteer'] && !isError(tabData['volunteer']) && (
                    <VolunteerTab data={tabData['volunteer'] as VolunteerData} />
                  )}
                </TabPanel>
              </div>
            </Tab>

            {/* Help Requests */}
            <Tab
              key="help"
              title={
                <div className="flex items-center gap-1.5">
                  <Heart size={14} />
                  <span>Help Requests</span>
                </div>
              }
            >
              <div className="p-4">
                <h2 className="mb-4 text-base font-semibold">Help Request Analysis</h2>
                <TabPanel loading={!!tabLoading['help']} error={tabError['help'] ?? null}>
                  {tabData['help'] && !isError(tabData['help']) && (
                    <HelpRequestsTab data={tabData['help'] as HelpRequestData} />
                  )}
                </TabPanel>
              </div>
            </Tab>
          </Tabs>
        </CardBody>
      </Card>
    </div>
  );
}
