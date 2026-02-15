/**
 * Impact Report - Admin Module
 *
 * SROI calculations, community health metrics, impact timelines,
 * and branded PDF export. Data source: GET /api/v2/admin/impact-report
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Spinner,
  Button,
  Input,
  Divider,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  BarChart,
  Bar,
  Legend,
} from 'recharts';
import {
  Clock,
  TrendingUp,
  Heart,
  Users,
  Download,
  RefreshCw,
  Activity,
  ArrowLeftRight,
  Settings,
  PoundSterling,
  Sparkles,
} from 'lucide-react';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface SROIData {
  total_hours: number;
  total_transactions: number;
  unique_givers: number;
  unique_receivers: number;
  hourly_value: number;
  monetary_value: number;
  social_multiplier: number;
  social_value: number;
  sroi_ratio: number;
  period_months: number;
}

interface HealthData {
  total_users: number;
  active_users_90d: number;
  new_users_30d: number;
  active_traders_30d: number;
  engagement_rate: number;
  retention_rate: number;
  reciprocity_score: number;
  activation_rate: number;
  network_density: number;
  total_connections: number;
}

interface TimelineEntry {
  month: string;
  hours_exchanged: number;
  transactions: number;
  new_users: number;
}

interface ReportConfig {
  tenant_name: string;
  tenant_slug: string;
  logo_url: string | null;
  hourly_value: number;
  social_multiplier: number;
}

interface ImpactReportData {
  sroi: SROIData;
  health: HealthData;
  timeline: TimelineEntry[];
  config: ReportConfig;
}

// ---------------------------------------------------------------------------
// Chart tooltip style (theme-aware, matches CommunityAnalytics)
// ---------------------------------------------------------------------------

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

// ---------------------------------------------------------------------------
// Period options
// ---------------------------------------------------------------------------

const PERIOD_OPTIONS = [
  { key: '3', label: '3 months' },
  { key: '6', label: '6 months' },
  { key: '12', label: '12 months' },
  { key: '24', label: '24 months' },
  { key: '36', label: '36 months' },
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatCurrency(value: number): string {
  return `\u00A3${value.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatPercent(rate: number): string {
  return `${(rate * 100).toFixed(1)}%`;
}

function formatMonth(monthStr: string): string {
  const [year, month] = monthStr.split('-');
  const date = new Date(Number(year), Number(month) - 1);
  return date.toLocaleDateString('en-GB', { month: 'short', year: '2-digit' });
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function ImpactReport() {
  usePageTitle('Admin - Impact Report');

  const [data, setData] = useState<ImpactReportData | null>(null);
  const [loading, setLoading] = useState(true);
  const [months, setMonths] = useState(12);
  const [saving, setSaving] = useState(false);

  // Config form state
  const [hourlyValue, setHourlyValue] = useState('15');
  const [socialMultiplier, setSocialMultiplier] = useState('3.5');
  const [configSaved, setConfigSaved] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/admin/impact-report?months=${months}`);
      if (res.data) {
        const reportData = res.data as ImpactReportData;
        setData(reportData);
        setHourlyValue(String(reportData.config.hourly_value));
        setSocialMultiplier(String(reportData.config.social_multiplier));
      }
    } catch {
      // Silently handle — cards will show loading/empty state
    } finally {
      setLoading(false);
    }
  }, [months]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // -------------------------------------------------------------------------
  // Save config
  // -------------------------------------------------------------------------

  const handleSaveConfig = async () => {
    setSaving(true);
    setConfigSaved(false);
    try {
      await api.put('/v2/admin/impact-report/config', {
        hourly_value: parseFloat(hourlyValue) || 15,
        social_multiplier: parseFloat(socialMultiplier) || 3.5,
      });
      setConfigSaved(true);
      // Reload data with new config
      await loadData();
      setTimeout(() => setConfigSaved(false), 3000);
    } catch {
      // Config save failed silently
    } finally {
      setSaving(false);
    }
  };

  // -------------------------------------------------------------------------
  // PDF export
  // -------------------------------------------------------------------------

  const exportPdf = () => {
    if (!data) return;

    const doc = new jsPDF();

    // Title
    doc.setFontSize(22);
    doc.setTextColor(99, 102, 241); // indigo
    doc.text(`${data.config.tenant_name} Impact Report`, 20, 25);

    doc.setFontSize(11);
    doc.setTextColor(100, 100, 100);
    doc.text(
      `Period: Last ${data.sroi.period_months} months | Generated: ${new Date().toLocaleDateString('en-GB')}`,
      20,
      33,
    );

    // SROI Table
    doc.setFontSize(14);
    doc.setTextColor(30, 30, 30);
    doc.text('Social Return on Investment', 20, 48);

    autoTable(doc, {
      startY: 52,
      head: [['Metric', 'Value']],
      body: [
        ['Total Hours Exchanged', data.sroi.total_hours.toFixed(1)],
        ['Total Transactions', String(data.sroi.total_transactions)],
        ['Unique Givers', String(data.sroi.unique_givers)],
        ['Unique Receivers', String(data.sroi.unique_receivers)],
        [
          'Monetary Value',
          `\u00A3${data.sroi.monetary_value.toLocaleString('en-GB', { minimumFractionDigits: 2 })}`,
        ],
        [
          'Social Value',
          `\u00A3${data.sroi.social_value.toLocaleString('en-GB', { minimumFractionDigits: 2 })}`,
        ],
        ['SROI Ratio', `${data.sroi.sroi_ratio}:1`],
      ],
      theme: 'striped',
      headStyles: { fillColor: [99, 102, 241] },
    });

    // Health Metrics Table
    const sroiTableEnd =
      (doc as unknown as Record<string, { finalY?: number }>).lastAutoTable
        ?.finalY ?? 120;
    doc.setFontSize(14);
    doc.setTextColor(30, 30, 30);
    doc.text('Community Health Metrics', 20, sroiTableEnd + 15);

    autoTable(doc, {
      startY: sroiTableEnd + 19,
      head: [['Metric', 'Value']],
      body: [
        ['Engagement Rate', `${(data.health.engagement_rate * 100).toFixed(1)}%`],
        ['Reciprocity Score', data.health.reciprocity_score.toFixed(2)],
        [
          'Retention Rate (90d)',
          `${(data.health.retention_rate * 100).toFixed(1)}%`,
        ],
        [
          'New Member Activation',
          `${(data.health.activation_rate * 100).toFixed(1)}%`,
        ],
        ['Network Density', data.health.network_density.toFixed(4)],
        ['Total Connections', String(data.health.total_connections)],
      ],
      theme: 'striped',
      headStyles: { fillColor: [16, 185, 129] },
    });

    // Timeline Table (if data available)
    if (data.timeline.length > 0) {
      const healthTableEnd =
        (doc as unknown as Record<string, { finalY?: number }>).lastAutoTable
          ?.finalY ?? 200;

      // Check if we need a new page
      if (healthTableEnd > 240) {
        doc.addPage();
        doc.setFontSize(14);
        doc.setTextColor(30, 30, 30);
        doc.text('Monthly Impact Timeline', 20, 20);

        autoTable(doc, {
          startY: 24,
          head: [['Month', 'Hours Exchanged', 'Transactions', 'New Users']],
          body: data.timeline.map((t) => [
            t.month,
            t.hours_exchanged.toFixed(1),
            String(t.transactions),
            String(t.new_users),
          ]),
          theme: 'striped',
          headStyles: { fillColor: [245, 158, 11] },
        });
      } else {
        doc.setFontSize(14);
        doc.text('Monthly Impact Timeline', 20, healthTableEnd + 15);

        autoTable(doc, {
          startY: healthTableEnd + 19,
          head: [['Month', 'Hours Exchanged', 'Transactions', 'New Users']],
          body: data.timeline.map((t) => [
            t.month,
            t.hours_exchanged.toFixed(1),
            String(t.transactions),
            String(t.new_users),
          ]),
          theme: 'striped',
          headStyles: { fillColor: [245, 158, 11] },
        });
      }
    }

    // Footer on every page
    const pageCount = doc.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      doc.setFontSize(9);
      doc.setTextColor(150, 150, 150);
      doc.text(
        'Generated by Project NEXUS | project-nexus.ie',
        20,
        285,
      );
      doc.text(`Page ${i} of ${pageCount}`, 170, 285);
    }

    doc.save(`${data.config.tenant_name.replace(/\s+/g, '_')}_Impact_Report.pdf`);
  };

  // -------------------------------------------------------------------------
  // Formatted timeline data for charts
  // -------------------------------------------------------------------------

  const chartData = (data?.timeline ?? []).map((entry) => ({
    ...entry,
    label: formatMonth(entry.month),
  }));

  // -------------------------------------------------------------------------
  // Render
  // -------------------------------------------------------------------------

  return (
    <div>
      <PageHeader
        title="Impact Report"
        description="SROI analysis, community health metrics, and impact timeline"
        actions={
          <div className="flex items-center gap-2">
            <Select
              size="sm"
              selectedKeys={[String(months)]}
              onSelectionChange={(keys) => {
                const value = Array.from(keys)[0];
                if (value) setMonths(Number(value));
              }}
              className="w-36"
              aria-label="Report period"
            >
              {PERIOD_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={exportPdf}
              isDisabled={!data || loading}
              size="sm"
            >
              Export PDF
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

      {/* ----------------------------------------------------------------- */}
      {/* SROI Section                                                      */}
      {/* ----------------------------------------------------------------- */}

      <div className="mb-2">
        <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
          <PoundSterling size={20} className="text-primary" />
          Social Return on Investment
        </h2>
        <p className="text-sm text-default-500 mt-0.5">
          Based on {data?.sroi.period_months ?? months} month period
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Hours"
          value={data ? data.sroi.total_hours.toFixed(1) : '\u2014'}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Monetary Value"
          value={data ? formatCurrency(data.sroi.monetary_value) : '\u2014'}
          icon={PoundSterling}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Social Value"
          value={data ? formatCurrency(data.sroi.social_value) : '\u2014'}
          icon={Sparkles}
          color="success"
          loading={loading}
        />
        <StatCard
          label="SROI Ratio"
          value={data ? `${data.sroi.sroi_ratio}:1` : '\u2014'}
          icon={TrendingUp}
          color="secondary"
          loading={loading}
        />
      </div>

      {/* Secondary SROI stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
        <Card shadow="sm">
          <CardBody className="flex flex-row items-center gap-4 p-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
              <ArrowLeftRight size={20} className="text-primary" />
            </div>
            <div>
              <p className="text-sm text-default-500">Transactions</p>
              {loading ? (
                <div className="mt-1 h-6 w-16 animate-pulse rounded bg-default-200" />
              ) : (
                <p className="text-xl font-bold text-foreground">
                  {data?.sroi.total_transactions.toLocaleString() ?? '\u2014'}
                </p>
              )}
            </div>
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="flex flex-row items-center gap-4 p-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success/10">
              <Users size={20} className="text-success" />
            </div>
            <div>
              <p className="text-sm text-default-500">Unique Givers</p>
              {loading ? (
                <div className="mt-1 h-6 w-16 animate-pulse rounded bg-default-200" />
              ) : (
                <p className="text-xl font-bold text-foreground">
                  {data?.sroi.unique_givers.toLocaleString() ?? '\u2014'}
                </p>
              )}
            </div>
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="flex flex-row items-center gap-4 p-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning/10">
              <Users size={20} className="text-warning" />
            </div>
            <div>
              <p className="text-sm text-default-500">Unique Receivers</p>
              {loading ? (
                <div className="mt-1 h-6 w-16 animate-pulse rounded bg-default-200" />
              ) : (
                <p className="text-xl font-bold text-foreground">
                  {data?.sroi.unique_receivers.toLocaleString() ?? '\u2014'}
                </p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* ----------------------------------------------------------------- */}
      {/* Community Health Section                                          */}
      {/* ----------------------------------------------------------------- */}

      <Divider className="mb-6" />

      <div className="mb-2">
        <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
          <Activity size={20} className="text-success" />
          Community Health
        </h2>
        <p className="text-sm text-default-500 mt-0.5">
          Current community engagement and network metrics
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <StatCard
          label="Engagement Rate"
          value={data ? formatPercent(data.health.engagement_rate) : '\u2014'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Reciprocity Score"
          value={data ? data.health.reciprocity_score.toFixed(2) : '\u2014'}
          icon={ArrowLeftRight}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Retention Rate (90d)"
          value={data ? formatPercent(data.health.retention_rate) : '\u2014'}
          icon={Heart}
          color="danger"
          loading={loading}
        />
        <StatCard
          label="Activation Rate"
          value={data ? formatPercent(data.health.activation_rate) : '\u2014'}
          icon={TrendingUp}
          color="success"
          loading={loading}
        />
      </div>

      {/* Additional health stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">Total Members</p>
            {loading ? (
              <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
            ) : (
              <p className="text-2xl font-bold text-foreground">
                {data?.health.total_users.toLocaleString() ?? '\u2014'}
              </p>
            )}
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">Active (90 days)</p>
            {loading ? (
              <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
            ) : (
              <p className="text-2xl font-bold text-foreground">
                {data?.health.active_users_90d.toLocaleString() ?? '\u2014'}
              </p>
            )}
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">New (30 days)</p>
            {loading ? (
              <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
            ) : (
              <p className="text-2xl font-bold text-foreground">
                {data?.health.new_users_30d.toLocaleString() ?? '\u2014'}
              </p>
            )}
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">Network Density</p>
            {loading ? (
              <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
            ) : (
              <p className="text-2xl font-bold text-foreground">
                {data?.health.network_density.toFixed(4) ?? '\u2014'}
              </p>
            )}
            <p className="text-xs text-default-400 mt-1">
              {data?.health.total_connections.toLocaleString() ?? 0} connections
            </p>
          </CardBody>
        </Card>
      </div>

      {/* ----------------------------------------------------------------- */}
      {/* Impact Timeline                                                   */}
      {/* ----------------------------------------------------------------- */}

      <Divider className="mb-6" />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        {/* Hours exchanged area chart */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Clock size={18} className="text-primary" />
            <h3 className="font-semibold">Hours Exchanged Over Time</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[300px] items-center justify-center">
                <Spinner />
              </div>
            ) : chartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <AreaChart data={chartData}>
                  <defs>
                    <linearGradient id="hoursGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                  <XAxis
                    dataKey="label"
                    tick={{ fontSize: 12 }}
                    tickLine={false}
                  />
                  <YAxis tick={{ fontSize: 12 }} tickLine={false} />
                  <Tooltip
                    contentStyle={tooltipStyle}
                    labelStyle={{ fontWeight: 600 }}
                  />
                  <Area
                    type="monotone"
                    dataKey="hours_exchanged"
                    name="Hours Exchanged"
                    stroke="#6366f1"
                    fill="url(#hoursGradient)"
                    strokeWidth={2}
                  />
                </AreaChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                No timeline data available yet
              </p>
            )}
          </CardBody>
        </Card>

        {/* Transactions + new users bar chart */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Activity size={18} className="text-success" />
            <h3 className="font-semibold">Activity Breakdown</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-[300px] items-center justify-center">
                <Spinner />
              </div>
            ) : chartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                  <XAxis
                    dataKey="label"
                    tick={{ fontSize: 12 }}
                    tickLine={false}
                  />
                  <YAxis tick={{ fontSize: 12 }} tickLine={false} />
                  <Tooltip
                    contentStyle={tooltipStyle}
                    labelStyle={{ fontWeight: 600 }}
                  />
                  <Legend />
                  <Bar
                    dataKey="transactions"
                    name="Transactions"
                    fill="#6366f1"
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar
                    dataKey="new_users"
                    name="New Users"
                    fill="#10b981"
                    radius={[4, 4, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                No activity data available yet
              </p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* ----------------------------------------------------------------- */}
      {/* Configuration Panel                                               */}
      {/* ----------------------------------------------------------------- */}

      <Divider className="mb-6" />

      <Card shadow="sm" className="mb-8">
        <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
          <Settings size={18} className="text-default-500" />
          <h3 className="font-semibold">SROI Configuration</h3>
        </CardHeader>
        <CardBody className="px-6 pb-6">
          <p className="text-sm text-default-500 mb-4">
            Configure the hourly value and social multiplier used for SROI calculations.
            The formula is: Social Value = Total Hours x Hourly Value x Social Multiplier.
          </p>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 max-w-xl">
            <Input
              label="Hourly Value (GBP)"
              type="number"
              min={0.01}
              max={1000}
              step={0.5}
              value={hourlyValue}
              onValueChange={setHourlyValue}
              variant="bordered"
              startContent={
                <span className="text-default-400 text-sm">\u00A3</span>
              }
              description="Value per hour of service (Timebanking UK default: 15.00)"
            />
            <Input
              label="Social Multiplier"
              type="number"
              min={0.1}
              max={100}
              step={0.1}
              value={socialMultiplier}
              onValueChange={setSocialMultiplier}
              variant="bordered"
              startContent={
                <span className="text-default-400 text-sm">x</span>
              }
              description="Multiplier for secondary social benefits (default: 3.5)"
            />
          </div>

          <div className="flex items-center gap-3 mt-4">
            <Button
              color="primary"
              onPress={handleSaveConfig}
              isLoading={saving}
              size="sm"
            >
              Update Configuration
            </Button>
            {configSaved && (
              <span className="text-sm text-success font-medium">
                Configuration saved. Report data refreshed.
              </span>
            )}
          </div>

          <Divider className="my-4" />

          <div className="text-xs text-default-400 space-y-1">
            <p>
              <strong>SROI Formula:</strong> Social Value = Total Hours x Hourly Value (
              {'\u00A3'}{hourlyValue}/hr) x Social Multiplier ({socialMultiplier}x)
            </p>
            <p>
              <strong>SROI Ratio:</strong> Social Value / Monetary Value
              (a ratio of {socialMultiplier}:1 means every {'\u00A3'}1 of direct value generates{' '}
              {'\u00A3'}{socialMultiplier} in social value)
            </p>
            <p>
              <strong>Reciprocity Score:</strong> 1.0 = perfectly balanced giving/receiving across all members;
              0.0 = completely one-directional
            </p>
            <p>
              <strong>Network Density:</strong> Ratio of actual connections to possible connections
              (higher = more interconnected community)
            </p>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default ImpactReport;
