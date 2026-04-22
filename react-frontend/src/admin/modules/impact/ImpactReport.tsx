// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Impact Report - Admin Module
 *
 * Unified SROI + community health + skills/events + impact summary view.
 * Merges the former "Social Value" page into a single Impact Report.
 *
 * Data sources (loaded in parallel):
 *  - GET /api/v2/admin/impact-report        → SROI, health, timeline (primary)
 *  - GET /api/v2/admin/reports/social-value → skills, events, impact summary, currency
 *
 * Config is currently stored in two backends (tenant JSON + social_value_config
 * table). The config modal writes to both endpoints to keep them in sync.
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
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
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
  Sparkles,
  Lightbulb,
  Calendar,
  Award,
} from 'lucide-react';

import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api, tokenManager } from '@/lib/api';
import { CHART_COLOR_MAP } from '@/lib/chartColors';
import { StatCard, PageHeader } from '../../components';

import i18n from '@/i18n';

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

interface SocialValueExtras {
  period?: { from: string; to: string };
  config: {
    currency: string;
    hour_value: number;
    social_multiplier: number;
    reporting_period: string;
  };
  members?: {
    total_registered: number;
    active_traders: number;
    participation_rate: number;
    new_members: number;
    logged_in: number;
  };
  skills?: {
    unique_categories: number;
    total_listings: number;
    unique_skills: number;
    skills_offered: number;
    skills_requested: number;
  };
  events?: {
    total_events: number;
    unique_organizers: number;
    total_attendees: number;
  };
  summary?: string;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const PERIOD_OPTIONS = [
  { key: '3', label: '3 months' },
  { key: '6', label: '6 months' },
  { key: '12', label: '12 months' },
  { key: '24', label: '24 months' },
  { key: '36', label: '36 months' },
];

const CURRENCY_OPTIONS = [
  { key: 'GBP', label: 'GBP (£)' },
  { key: 'EUR', label: 'EUR (€)' },
  { key: 'USD', label: 'USD ($)' },
];

const REPORTING_PERIOD_OPTIONS = [
  { key: 'monthly', label: 'Monthly' },
  { key: 'quarterly', label: 'Quarterly' },
  { key: 'annually', label: 'Annually' },
];

const CURRENCY_SYMBOLS: Record<string, string> = {
  GBP: '£',
  EUR: '€',
  USD: '$',
};

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatCurrency(value: number | null | undefined, currency: string): string {
  if (value == null) return '—';
  const symbol = CURRENCY_SYMBOLS[currency] || currency;
  return `${symbol}${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatPercent(rate: number): string {
  return `${(rate * 100).toFixed(1)}%`;
}

function formatMonth(monthStr: string): string {
  const [year, month] = monthStr.split('-');
  const date = new Date(Number(year), Number(month) - 1);
  return date.toLocaleDateString(undefined, { month: 'short', year: '2-digit' });
}

async function exportCsv(dateFrom?: string, dateTo?: string) {
  const token = tokenManager.getAccessToken();
  const tenantId = tokenManager.getTenantId();
  const headers: Record<string, string> = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const params = new URLSearchParams({ format: 'csv' });
  if (dateFrom) params.append('date_from', dateFrom);
  if (dateTo) params.append('date_to', dateTo);

  const apiBase = import.meta.env.VITE_API_BASE || '/api';
  const res = await fetch(`${apiBase}/v2/admin/reports/social_value/export?${params}`, { headers, credentials: 'include' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'impact-report.csv';
  a.click();
  URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function ImpactReport() {
  usePageTitle("Impact");
  const toast = useToast();

  const [data, setData] = useState<ImpactReportData | null>(null);
  const [extras, setExtras] = useState<SocialValueExtras | null>(null);
  const [loading, setLoading] = useState(true);
  const [months, setMonths] = useState(12);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [saving, setSaving] = useState(false);
  const { isOpen: configOpen, onOpen: openConfig, onClose: closeConfig } = useDisclosure();

  // Config form state
  const [configCurrency, setConfigCurrency] = useState('GBP');
  const [configHourValue, setConfigHourValue] = useState('15.00');
  const [configMultiplier, setConfigMultiplier] = useState('3.5');
  const [configPeriod, setConfigPeriod] = useState('annually');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const svParams = new URLSearchParams();
      if (dateFrom) svParams.append('date_from', dateFrom);
      if (dateTo) svParams.append('date_to', dateTo);
      const svQs = svParams.toString();

      const [impactRes, svRes] = await Promise.all([
        api.get(`/v2/admin/impact-report?months=${months}`),
        api.get(`/v2/admin/reports/social-value${svQs ? `?${svQs}` : ''}`),
      ]);

      if (impactRes.data) {
        const reportData = impactRes.data as ImpactReportData;
        setData(reportData);
      }

      if (svRes.data) {
        // The API returns config keys as hour_value_currency/hour_value_amount,
        // and summary as a stats object (not a string). Normalise to SocialValueExtras shape.
        const raw = svRes.data as Record<string, unknown>;
        const rawConfig = (raw.config ?? {}) as Record<string, unknown>;
        const rawSummary = (raw.summary ?? {}) as Record<string, unknown>;

        const sv: SocialValueExtras = {
          config: {
            currency: (rawConfig.hour_value_currency as string) ?? 'GBP',
            hour_value: (rawConfig.hour_value_amount as number) ?? 15,
            social_multiplier: (rawConfig.social_multiplier as number) ?? 3.5,
            reporting_period: (rawConfig.reporting_period as string) ?? 'annually',
          },
          members: rawSummary.active_members != null ? {
            total_registered: 0,
            active_traders: rawSummary.active_members as number,
            participation_rate: 0,
            new_members: 0,
            logged_in: 0,
          } : undefined,
          events: rawSummary.total_events != null ? {
            total_events: rawSummary.total_events as number,
            unique_organizers: 0,
            total_attendees: 0,
          } : undefined,
          // summary string (AI-generated) is not provided by this endpoint
          summary: undefined,
        };

        setExtras(sv);
        setConfigCurrency(sv.config.currency);
        setConfigHourValue(String(sv.config.hour_value));
        setConfigMultiplier(String(sv.config.social_multiplier));
        setConfigPeriod(sv.config.reporting_period);
      }
    } catch {
      // Silently handle — cards will show empty state
    } finally {
      setLoading(false);
    }
  }, [months, dateFrom, dateTo]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSaveConfig = async () => {
    setSaving(true);
    try {
      const hourValue = parseFloat(configHourValue) || 15;
      const multiplier = parseFloat(configMultiplier) || 3.5;

      // Write to both backends so Impact Report and Social Value stay in sync.
      await Promise.all([
        api.put('/v2/admin/impact-report/config', {
          hourly_value: hourValue,
          social_multiplier: multiplier,
        }),
        api.put('/v2/admin/reports/social-value/config', {
          hour_value_currency: configCurrency,
          hour_value_amount: hourValue,
          social_multiplier: multiplier,
          reporting_period: configPeriod,
        }),
      ]);
      closeConfig();
      await loadData();
    } catch {
      toast.error("Failed to save configuration");
    } finally {
      setSaving(false);
    }
  };

  const currency = extras?.config.currency || 'GBP';

  // -------------------------------------------------------------------------
  // PDF export
  // -------------------------------------------------------------------------

  const exportPdf = async () => {
    if (!data) return;

    const [{ default: jsPDF }, { default: autoTable }] = await Promise.all([
      import('jspdf'),
      import('jspdf-autotable'),
    ]);

    const doc = new jsPDF();

    doc.setFontSize(22);
    doc.setTextColor(99, 102, 241);
    doc.text(`${data.config.tenant_name} Impact Report`, 20, 25);

    doc.setFontSize(11);
    doc.setTextColor(100, 100, 100);
    doc.text(
      `Period: Last ${data.sroi.period_months} months | Generated: ${new Date().toLocaleDateString(i18n.language)}`,
      20,
      33,
    );

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
        ['Monetary Value', formatCurrency(data.sroi.monetary_value, currency)],
        ['Social Value', formatCurrency(data.sroi.social_value, currency)],
        ['SROI Ratio', `${data.sroi.sroi_ratio}:1`],
      ],
      theme: 'striped',
      headStyles: { fillColor: [99, 102, 241] },
    });

    const sroiTableEnd =
      (doc as unknown as Record<string, { finalY?: number }>).lastAutoTable?.finalY ?? 120;
    doc.setFontSize(14);
    doc.text('Community Health Metrics', 20, sroiTableEnd + 15);

    autoTable(doc, {
      startY: sroiTableEnd + 19,
      head: [['Metric', 'Value']],
      body: [
        ['Engagement Rate', formatPercent(data.health.engagement_rate)],
        ['Reciprocity Score', data.health.reciprocity_score.toFixed(2)],
        ['Retention Rate (90d)', formatPercent(data.health.retention_rate)],
        ['New Member Activation', formatPercent(data.health.activation_rate)],
        ['Network Density', data.health.network_density.toFixed(4)],
        ['Total Connections', String(data.health.total_connections)],
      ],
      theme: 'striped',
      headStyles: { fillColor: [16, 185, 129] },
    });

    if (data.timeline.length > 0) {
      const healthTableEnd =
        (doc as unknown as Record<string, { finalY?: number }>).lastAutoTable?.finalY ?? 200;
      const needNewPage = healthTableEnd > 240;
      if (needNewPage) doc.addPage();
      const timelineY = needNewPage ? 20 : healthTableEnd + 15;

      doc.setFontSize(14);
      doc.text('Monthly Impact Timeline', 20, timelineY);

      autoTable(doc, {
        startY: timelineY + 4,
        head: [['Month', 'Hours Exchanged', 'Transactions', 'New Users']],
        body: data.timeline.map((r) => [
          r.month,
          r.hours_exchanged.toFixed(1),
          String(r.transactions),
          String(r.new_users),
        ]),
        theme: 'striped',
        headStyles: { fillColor: [245, 158, 11] },
      });
    }

    const pageCount = doc.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      doc.setFontSize(9);
      doc.setTextColor(150, 150, 150);
      doc.text('Generated by Project NEXUS | project-nexus.ie', 20, 285);
      doc.text(`Page ${i} of ${pageCount}`, 170, 285);
    }

    doc.save(`${data.config.tenant_name.replace(/\s+/g, '_')}_Impact_Report.pdf`);
  };

  // -------------------------------------------------------------------------
  // Chart data
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
        title={"Impact Report"}
        description={"Measure the social value and impact of your timebanking community"}
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            <Select
              size="sm"
              selectedKeys={[String(months)]}
              onSelectionChange={(keys) => {
                const value = Array.from(keys)[0];
                if (value) setMonths(Number(value));
              }}
              className="w-36"
              aria-label={"Report Period"}
            >
              {PERIOD_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
            <Input
              type="date"
              size="sm"
              value={dateFrom}
              onValueChange={setDateFrom}
              aria-label={"From Date"}
              className="w-36"
              variant="bordered"
            />
            <Input
              type="date"
              size="sm"
              value={dateTo}
              onValueChange={setDateTo}
              aria-label={"To Date"}
              className="w-36"
              variant="bordered"
            />
            <Button
              variant="flat"
              startContent={<Settings size={16} />}
              onPress={openConfig}
              size="sm"
            >
              {"Configure"}
            </Button>
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={exportPdf}
              isDisabled={!data || loading}
              size="sm"
            >
              {"Export PDF"}
            </Button>
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={async () => {
                try { await exportCsv(dateFrom, dateTo); } catch { toast.error("Failed to export CSV"); }
              }}
              size="sm"
            >
              {"Export CSV"}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {"Refresh"}
            </Button>
          </div>
        }
      />

      {/* SROI Section --------------------------------------------------- */}

      <div className="mb-2">
        <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
          <Sparkles size={20} className="text-primary" />
          {"Sroi"}
        </h2>
        <p className="text-sm text-default-500 mt-0.5">
          {`Period.`}
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={"Total Hours"}
          value={data ? data.sroi.total_hours.toFixed(1) : '\u2014'}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={"Monetary Value"}
          value={data ? formatCurrency(data.sroi.monetary_value, currency) : '\u2014'}
          icon={TrendingUp}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={"Social Value"}
          value={data ? formatCurrency(data.sroi.social_value, currency) : '\u2014'}
          icon={Sparkles}
          color="success"
          loading={loading}
        />
        <StatCard
          label={"SROI Ratio"}
          value={data ? `${data.sroi.sroi_ratio}:1` : '\u2014'}
          icon={TrendingUp}
          color="secondary"
          loading={loading}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
        <Card shadow="sm">
          <CardBody className="flex flex-row items-center gap-4 p-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
              <ArrowLeftRight size={20} className="text-primary" />
            </div>
            <div>
              <p className="text-sm text-default-500">{"Transactions"}</p>
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
              <p className="text-sm text-default-500">{"Unique Givers"}</p>
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
              <p className="text-sm text-default-500">{"Unique Receivers"}</p>
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

      {/* Skills & Events Section --------------------------------------- */}

      {(extras?.skills || extras?.events || extras?.members) && (
        <>
          <Divider className="mb-6" />
          <div className="mb-2">
            <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
              <Lightbulb size={20} className="text-warning" />
              {"Skills Events"}
            </h2>
            <p className="text-sm text-default-500 mt-0.5">
              {"Skills Events."}
            </p>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
            <StatCard
              label={"Active Members"}
              value={extras.members?.active_traders ?? '\u2014'}
              icon={Users}
              color="primary"
              loading={loading}
            />
            <StatCard
              label={"Skills Shared"}
              value={extras.skills?.unique_skills ?? '\u2014'}
              icon={Lightbulb}
              color="secondary"
              loading={loading}
            />
            <StatCard
              label={"Events Held"}
              value={extras.events?.total_events ?? '\u2014'}
              icon={Calendar}
              color="success"
              loading={loading}
            />
            <StatCard
              label={"Unique Categories"}
              value={extras.skills?.unique_categories ?? '\u2014'}
              icon={Award}
              color="danger"
              loading={loading}
            />
            <StatCard
              label={"Active Listings Stat"}
              value={extras.skills?.total_listings ?? '\u2014'}
              icon={Sparkles}
              color="warning"
              loading={loading}
            />
          </div>

          {extras.skills && (
            <Card shadow="sm" className="mb-8">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <Lightbulb size={18} className="text-warning" />
                <h3 className="font-semibold">{"Skills Overview"}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">{"Skills Offered"}</p>
                    <p className="text-2xl font-bold text-primary">{extras.skills.skills_offered ?? 0}</p>
                  </div>
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">{"Skills Requested"}</p>
                    <p className="text-2xl font-bold text-secondary">{extras.skills.skills_requested ?? 0}</p>
                  </div>
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">{"Unique Skills"}</p>
                    <p className="text-2xl font-bold text-success">{extras.skills.unique_skills ?? 0}</p>
                  </div>
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">{"Active Listings"}</p>
                    <p className="text-2xl font-bold text-warning">{extras.skills.total_listings ?? 0}</p>
                  </div>
                </div>
              </CardBody>
            </Card>
          )}
        </>
      )}

      {/* Community Health Section -------------------------------------- */}

      <Divider className="mb-6" />

      <div className="mb-2">
        <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
          <Activity size={20} className="text-success" />
          {"Community Health"}
        </h2>
        <p className="text-sm text-default-500 mt-0.5">
          {"Current community engagement and network metrics"}
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <StatCard
          label={"Engagement Rate"}
          value={data ? formatPercent(data.health.engagement_rate) : '\u2014'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={"Reciprocity Score"}
          value={data ? data.health.reciprocity_score.toFixed(2) : '\u2014'}
          icon={ArrowLeftRight}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label={"Retention Rate"}
          value={data ? formatPercent(data.health.retention_rate) : '\u2014'}
          icon={Heart}
          color="danger"
          loading={loading}
        />
        <StatCard
          label={"Activation Rate"}
          value={data ? formatPercent(data.health.activation_rate) : '\u2014'}
          icon={TrendingUp}
          color="success"
          loading={loading}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <Card shadow="sm">
          <CardBody className="p-4">
            <p className="text-sm text-default-500">{"Total Members"}</p>
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
            <p className="text-sm text-default-500">{"Active (90 days)"}</p>
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
            <p className="text-sm text-default-500">{"New (30 days)"}</p>
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
            <p className="text-sm text-default-500">{"Network Density"}</p>
            {loading ? (
              <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
            ) : (
              <p className="text-2xl font-bold text-foreground">
                {data?.health.network_density.toFixed(4) ?? '\u2014'}
              </p>
            )}
            <p className="text-xs text-default-400 mt-1">
              {data?.health.total_connections.toLocaleString() ?? 0} {"connections"}
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Impact Timeline ----------------------------------------------- */}

      <Divider className="mb-6" />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Clock size={18} className="text-primary" />
            <h3 className="font-semibold">{"Hours Exchanged Over Time"}</h3>
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
                      <stop offset="5%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0.3} />
                      <stop offset="95%" stopColor={CHART_COLOR_MAP.primary} stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                  <XAxis dataKey="label" tick={{ fontSize: 12 }} tickLine={false} />
                  <YAxis tick={{ fontSize: 12 }} tickLine={false} />
                  <Tooltip contentStyle={tooltipStyle} labelStyle={{ fontWeight: 600 }} />
                  <Area
                    type="monotone"
                    dataKey="hours_exchanged"
                    name={"Hours Exchanged"}
                    stroke={CHART_COLOR_MAP.primary}
                    fill="url(#hoursGradient)"
                    strokeWidth={2}
                  />
                </AreaChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                {"No timeline data available yet"}
              </p>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Activity size={18} className="text-success" />
            <h3 className="font-semibold">{"Activity Breakdown"}</h3>
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
                  <XAxis dataKey="label" tick={{ fontSize: 12 }} tickLine={false} />
                  <YAxis tick={{ fontSize: 12 }} tickLine={false} />
                  <Tooltip contentStyle={tooltipStyle} labelStyle={{ fontWeight: 600 }} />
                  <Legend />
                  <Bar dataKey="transactions" name={"Transactions"} fill={CHART_COLOR_MAP.primary} radius={[4, 4, 0, 0]} />
                  <Bar dataKey="new_users" name={"New Users"} fill={CHART_COLOR_MAP.success} radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <p className="flex h-[300px] items-center justify-center text-sm text-default-400">
                {"No activity data available yet"}
              </p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Impact Summary ------------------------------------------------ */}

      {extras?.summary && (
        <Card shadow="sm" className="mb-8">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Sparkles size={18} className="text-success" />
            <h3 className="font-semibold">{"Impact Summary"}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <p className="text-sm text-foreground leading-relaxed whitespace-pre-line">
              {extras.summary}
            </p>
            <Divider className="my-4" />
            <div className="text-xs text-default-400 space-y-1">
              <p>
                <strong>{"Hour Value"}:</strong> {formatCurrency(extras.config.hour_value, currency)}/hr
              </p>
              <p>
                <strong>{"Social Multiplier"}:</strong> {extras.config.social_multiplier}x
              </p>
              <p>
                <strong>{"Formula"}:</strong> {"Social Value = Hours × Hour Value × Multiplier"}
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Configuration Modal ------------------------------------------- */}

      <Modal isOpen={configOpen} onClose={closeConfig} size="lg">
        <ModalContent>
          <ModalHeader>{"SROI Configuration"}</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-4">
              {"Configure how social value is calculated. Changes recalculate all metrics across both Impact and Social Value backends."}
            </p>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Select
                label={"Currency"}
                selectedKeys={[configCurrency]}
                onSelectionChange={(keys) => {
                  const v = Array.from(keys)[0];
                  if (v) setConfigCurrency(String(v));
                }}
                variant="bordered"
              >
                {CURRENCY_OPTIONS.map((opt) => (
                  <SelectItem key={opt.key}>{opt.label}</SelectItem>
                ))}
              </Select>
              <Input
                label={"Hour Value"}
                type="number"
                min={0.01}
                max={10000}
                step={0.5}
                value={configHourValue}
                onValueChange={setConfigHourValue}
                variant="bordered"
                startContent={
                  <span className="text-default-400 text-sm">
                    {CURRENCY_SYMBOLS[configCurrency] || configCurrency}
                  </span>
                }
                description={"Value per hour of service (Timebanking UK default: 15.00)"}
              />
              <Input
                label={"Social Multiplier"}
                type="number"
                min={0.1}
                max={100}
                step={0.1}
                value={configMultiplier}
                onValueChange={setConfigMultiplier}
                variant="bordered"
                startContent={<span className="text-default-400 text-sm">×</span>}
              />
              <Select
                label={"Reporting Period"}
                selectedKeys={[configPeriod]}
                onSelectionChange={(keys) => {
                  const v = Array.from(keys)[0];
                  if (v) setConfigPeriod(String(v));
                }}
                variant="bordered"
              >
                {REPORTING_PERIOD_OPTIONS.map((opt) => (
                  <SelectItem key={opt.key}>{opt.label}</SelectItem>
                ))}
              </Select>
            </div>
            <Divider className="my-4" />
            <div className="text-xs text-default-400 space-y-1">
              <p>
                <strong>{"SROI Formula"}:</strong> Social Value = Total Hours × Hourly Value (
                {CURRENCY_SYMBOLS[configCurrency] || configCurrency}{configHourValue}/hr) × Social Multiplier ({configMultiplier}×)
              </p>
              <p>
                <strong>{"SROI Ratio"}:</strong> Social Value / Monetary Value (a ratio of {configMultiplier}:1 means every
                {' '}{CURRENCY_SYMBOLS[configCurrency] || configCurrency}1 of direct value generates
                {' '}{CURRENCY_SYMBOLS[configCurrency] || configCurrency}{configMultiplier} in social value)
              </p>
              <p>
                <strong>{"Reciprocity Score"}:</strong> {"1.0 = perfectly balanced giving/receiving across all members; 0.0 = completely one-directional"}
              </p>
              <p>
                <strong>{"Network Density"}:</strong> {"Ratio of actual connections to possible connections (higher = more interconnected community)"}
              </p>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeConfig}>{"Cancel"}</Button>
            <Button color="primary" onPress={handleSaveConfig} isLoading={saving} isDisabled={saving}>
              {"Save Configuration"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ImpactReport;
