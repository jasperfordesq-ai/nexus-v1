// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * A1 - Social Value / SROI Dashboard
 *
 * Displays social return on investment metrics:
 * - Total hours exchanged, monetary value, active members, skills shared, events held
 * - Monthly breakdown chart
 * - Configurable hour value (currency + amount) via settings modal
 * - Impact summary text
 *
 * API: GET /api/v2/admin/reports/social-value
 *      PUT /api/v2/admin/reports/social-value/config
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
  Divider,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
  Area,
  ComposedChart,
} from 'recharts';
import {
  Clock,
  TrendingUp,
  Users,
  Download,
  RefreshCw,
  Settings,
  Sparkles,
  Calendar,
  Lightbulb,
  Award,
  DollarSign,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface SocialValueData {
  period: {
    from: string;
    to: string;
  };
  config: {
    currency: string;
    hour_value: number;
    social_multiplier: number;
    reporting_period: string;
  };
  hours: {
    total_hours: number;
    total_transactions: number;
    unique_givers: number;
    unique_receivers: number;
    avg_hours_per_transaction: number;
    monthly: Array<{
      month: string;
      hours: number;
      transactions: number;
    }>;
  };
  valuation: {
    monetary_value: number;
    social_value: number;
    sroi_ratio: number;
    currency: string;
  };
  members: {
    total_registered: number;
    active_traders: number;
    participation_rate: number;
    new_members: number;
    logged_in: number;
  };
  skills: {
    unique_categories: number;
    total_listings: number;
    unique_skills: number;
    skills_offered: number;
    skills_requested: number;
  };
  events: {
    total_events: number;
    unique_organizers: number;
    total_attendees: number;
  };
  summary: string;
}

// ---------------------------------------------------------------------------
// Chart tooltip style (theme-aware)
// ---------------------------------------------------------------------------

const tooltipStyle = {
  borderRadius: '8px',
  border: '1px solid hsl(var(--heroui-default-200))',
  backgroundColor: 'hsl(var(--heroui-content1))',
  color: 'hsl(var(--heroui-foreground))',
};

const CURRENCY_OPTIONS = [
  { key: 'GBP', label: 'GBP (£)' },
  { key: 'EUR', label: 'EUR (€)' },
  { key: 'USD', label: 'USD ($)' },
];

const PERIOD_OPTIONS = [
  { key: 'monthly', label: 'Monthly' },
  { key: 'quarterly', label: 'Quarterly' },
  { key: 'annually', label: 'Annually' },
];

const CURRENCY_SYMBOLS: Record<string, string> = {
  GBP: '£',
  EUR: '€',
  USD: '$',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatCurrency(value: number, currency: string): string {
  const symbol = CURRENCY_SYMBOLS[currency] || currency;
  return `${symbol}${value.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatMonth(monthStr: string): string {
  if (!monthStr) return '';
  const [year, month] = monthStr.split('-');
  const date = new Date(Number(year), Number(month) - 1);
  return date.toLocaleDateString(undefined, { month: 'short', year: '2-digit' });
}

// ---------------------------------------------------------------------------
// CSV Export helper
// ---------------------------------------------------------------------------

async function exportCsv(dateFrom?: string, dateTo?: string) {
  const token = localStorage.getItem('nexus_access_token');
  const tenantId = localStorage.getItem('nexus_tenant_id');
  const headers: Record<string, string> = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const params = new URLSearchParams({ format: 'csv' });
  if (dateFrom) params.append('date_from', dateFrom);
  if (dateTo) params.append('date_to', dateTo);

  const apiBase = import.meta.env.VITE_API_BASE || '/api';
  const res = await fetch(`${apiBase}/v2/admin/reports/social_value/export?${params}`, { headers });
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'social-value-report.csv';
  a.click();
  URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function SocialValuePage() {
  usePageTitle('Social Value Dashboard');
  const toast = useToast();

  const [data, setData] = useState<SocialValueData | null>(null);
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const { isOpen, onOpen, onClose } = useDisclosure();

  // Config form state
  const [configCurrency, setConfigCurrency] = useState('GBP');
  const [configHourValue, setConfigHourValue] = useState('15.00');
  const [configMultiplier, setConfigMultiplier] = useState('3.5');
  const [configPeriod, setConfigPeriod] = useState('annually');
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (dateFrom) params.append('date_from', dateFrom);
      if (dateTo) params.append('date_to', dateTo);
      const qs = params.toString();
      const res = await api.get(`/v2/admin/reports/social-value${qs ? `?${qs}` : ''}`);
      if (res.data) {
        const d = res.data as SocialValueData;
        setData(d);
        setConfigCurrency(d.config.currency);
        setConfigHourValue(String(d.config.hour_value));
        setConfigMultiplier(String(d.config.social_multiplier));
        setConfigPeriod(d.config.reporting_period);
      }
    } catch {
      toast.error('Failed to load social value data');
    } finally {
      setLoading(false);
    }
  }, [dateFrom, dateTo, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSaveConfig = async () => {
    setSaving(true);
    try {
      await api.put('/v2/admin/reports/social-value/config', {
        hour_value_currency: configCurrency,
        hour_value_amount: parseFloat(configHourValue) || 15,
        social_multiplier: parseFloat(configMultiplier) || 3.5,
        reporting_period: configPeriod,
      });
      onClose();
      await loadData();
    } catch {
      toast.error('Failed to save configuration');
    } finally {
      setSaving(false);
    }
  };

  const chartData = (data?.hours?.monthly ?? []).map((entry) => ({
    ...entry,
    label: formatMonth(entry.month),
  }));

  const currency = data?.config.currency || 'GBP';

  return (
    <div>
      <PageHeader
        title="Social Value Dashboard"
        description="Social Return on Investment (SROI) metrics and impact valuation"
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            <Input
              type="date"
              size="sm"
              value={dateFrom}
              onValueChange={setDateFrom}
              aria-label="From date"
              className="w-36"
              variant="bordered"
            />
            <Input
              type="date"
              size="sm"
              value={dateTo}
              onValueChange={setDateTo}
              aria-label="To date"
              className="w-36"
              variant="bordered"
            />
            <Button
              variant="flat"
              startContent={<Settings size={16} />}
              onPress={onOpen}
              size="sm"
            >
              Configure
            </Button>
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={async () => {
                try { await exportCsv(dateFrom, dateTo); } catch { toast.error('Failed to export CSV'); }
              }}
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

      {/* Key Metrics */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Hours Exchanged"
          value={data ? (data.hours?.total_hours ?? 0).toFixed(1) : '\u2014'}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Monetary Value"
          value={data ? formatCurrency(data.valuation?.monetary_value ?? 0, currency) : '\u2014'}
          icon={DollarSign}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Social Value"
          value={data ? formatCurrency(data.valuation?.social_value ?? 0, currency) : '\u2014'}
          icon={Sparkles}
          color="success"
          loading={loading}
        />
        <StatCard
          label="SROI Ratio"
          value={data ? `${data.valuation?.sroi_ratio ?? 0}:1` : '\u2014'}
          icon={TrendingUp}
          color="secondary"
          loading={loading}
        />
      </div>

      {/* Secondary Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard
          label="Active Members"
          value={data?.members?.active_traders ?? '\u2014'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Skills Shared"
          value={data?.skills?.unique_skills ?? '\u2014'}
          icon={Lightbulb}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Events Held"
          value={data?.events?.total_events ?? '\u2014'}
          icon={Calendar}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Transactions"
          value={data?.hours?.total_transactions ?? '\u2014'}
          icon={TrendingUp}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Unique Categories"
          value={data?.skills?.unique_categories ?? '\u2014'}
          icon={Award}
          color="danger"
          loading={loading}
        />
      </div>

      {/* Monthly Breakdown Chart */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
          <TrendingUp size={18} className="text-primary" />
          <h3 className="font-semibold">Monthly Breakdown</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex h-[350px] items-center justify-center">
              <Spinner />
            </div>
          ) : chartData.length > 0 ? (
            <ResponsiveContainer width="100%" height={350}>
              <ComposedChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="svHoursGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="label" tick={{ fontSize: 12 }} tickLine={false} />
                <YAxis yAxisId="hours" tick={{ fontSize: 12 }} tickLine={false} />
                <YAxis yAxisId="tx" orientation="right" tick={{ fontSize: 12 }} tickLine={false} />
                <Tooltip contentStyle={tooltipStyle} labelStyle={{ fontWeight: 600 }} />
                <Legend />
                <Area
                  yAxisId="hours"
                  type="monotone"
                  dataKey="hours"
                  name="Hours"
                  stroke="#6366f1"
                  fill="url(#svHoursGrad)"
                  strokeWidth={2}
                />
                <Bar
                  yAxisId="tx"
                  dataKey="transactions"
                  name="Transactions"
                  fill="#10b981"
                  radius={[4, 4, 0, 0]}
                  fillOpacity={0.7}
                />
              </ComposedChart>
            </ResponsiveContainer>
          ) : (
            <p className="flex h-[350px] items-center justify-center text-sm text-default-400">
              No monthly data available yet
            </p>
          )}
        </CardBody>
      </Card>

      {/* Top Skills + Impact Summary */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
        {/* Skills Overview */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Lightbulb size={18} className="text-warning" />
            <h3 className="font-semibold">Skills Overview</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-48 items-center justify-center"><Spinner /></div>
            ) : data?.skills ? (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">Skills Offered</p>
                    <p className="text-2xl font-bold text-primary">{data.skills.skills_offered ?? 0}</p>
                  </div>
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">Skills Requested</p>
                    <p className="text-2xl font-bold text-secondary">{data.skills.skills_requested ?? 0}</p>
                  </div>
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">Unique Skills</p>
                    <p className="text-2xl font-bold text-success">{data.skills.unique_skills ?? 0}</p>
                  </div>
                  <div className="p-4 rounded-lg bg-default-50">
                    <p className="text-xs text-default-500 mb-1">Active Listings</p>
                    <p className="text-2xl font-bold text-warning">{data.skills.total_listings ?? 0}</p>
                  </div>
                </div>
                <Divider />
                <div className="text-xs text-default-400">
                  <p>Across <strong>{data.skills.unique_categories ?? 0}</strong> service categories</p>
                </div>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">No skill data available</p>
            )}
          </CardBody>
        </Card>

        {/* Impact Summary */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Sparkles size={18} className="text-success" />
            <h3 className="font-semibold">Impact Summary</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-48 items-center justify-center"><Spinner /></div>
            ) : data?.summary ? (
              <div className="space-y-4">
                <p className="text-sm text-foreground leading-relaxed whitespace-pre-line">
                  {data.summary}
                </p>
                <Divider />
                <div className="text-xs text-default-400 space-y-1">
                  <p>
                    <strong>Period:</strong> {data.period.from} to {data.period.to}
                  </p>
                  <p>
                    <strong>Hour Value:</strong> {formatCurrency(data.config.hour_value, currency)}/hr
                  </p>
                  <p>
                    <strong>Social Multiplier:</strong> {data.config.social_multiplier}x
                  </p>
                  <p>
                    <strong>Formula:</strong> Social Value = Hours x Hour Value x Multiplier
                  </p>
                </div>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">No summary available</p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Configuration Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>SROI Configuration</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-4">
              Configure how social value is calculated. Changes will recalculate all metrics.
            </p>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Select
                label="Currency"
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
                label="Hour Value"
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
              />
              <Input
                label="Social Multiplier"
                type="number"
                min={0.1}
                max={100}
                step={0.1}
                value={configMultiplier}
                onValueChange={setConfigMultiplier}
                variant="bordered"
                startContent={<span className="text-default-400 text-sm">x</span>}
              />
              <Select
                label="Reporting Period"
                selectedKeys={[configPeriod]}
                onSelectionChange={(keys) => {
                  const v = Array.from(keys)[0];
                  if (v) setConfigPeriod(String(v));
                }}
                variant="bordered"
              >
                {PERIOD_OPTIONS.map((opt) => (
                  <SelectItem key={opt.key}>{opt.label}</SelectItem>
                ))}
              </Select>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleSaveConfig} isLoading={saving}>
              Save Configuration
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SocialValuePage;
