// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState, type ChangeEvent } from 'react';
import { Button, Card, CardBody, CardHeader, Chip, Divider, Input, Select, SelectItem, Spinner, Switch, Textarea } from '@heroui/react';
import { Link } from 'react-router-dom';
import Building2 from 'lucide-react/icons/building-2';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import Clock from 'lucide-react/icons/clock';
import Copy from 'lucide-react/icons/copy';
import Download from 'lucide-react/icons/download';
import ExternalLink from 'lucide-react/icons/external-link';
import FileText from 'lucide-react/icons/file-text';
import Heart from 'lucide-react/icons/heart';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import Pause from 'lucide-react/icons/pause';
import Printer from 'lucide-react/icons/printer';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import Search from 'lucide-react/icons/search';
import Sparkles from 'lucide-react/icons/sparkles';
import BrainCircuit from 'lucide-react/icons/brain-circuit';
import TrendingUp from 'lucide-react/icons/trending-up';
import TrendingDown from 'lucide-react/icons/trending-down';
import Minus from 'lucide-react/icons/minus';
import Info from 'lucide-react/icons/info';
import ShieldCheck from 'lucide-react/icons/shield-check';
import TriangleAlert from 'lucide-react/icons/triangle-alert';
import UserPlus from 'lucide-react/icons/user-plus';
import Users from 'lucide-react/icons/users';
import XCircle from 'lucide-react/icons/circle-x';
import {
  Area,
  CartesianGrid,
  ComposedChart,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { MemberSearchPicker, PageHeader, StatCard, type MemberSearchMember } from '../../components';

type PendingReview = {
  id: number;
  member_name: string;
  organisation_name: string;
  opportunity_title: string;
  hours: number;
  date_logged: string;
  created_at: string;
  age_days: number;
  is_overdue: boolean;
  is_escalated: boolean;
  assigned_to: number | null;
  assigned_name: string | null;
  assigned_at: string | null;
  escalated_at: string | null;
  escalation_note: string | null;
};

type RecentDecision = {
  id: number;
  member_name: string;
  organisation_name: string;
  hours: number;
  status: 'approved' | 'declined';
  decided_at: string;
};

type WorkflowSummary = {
  stats: {
    pending_count: number;
    pending_hours: number;
    overdue_count: number;
    escalated_count: number;
    approved_30d_hours: number;
    declined_30d_count: number;
    coordinator_count: number;
  };
  pending_reviews: PendingReview[];
  recent_decisions: RecentDecision[];
  coordinator_signals: {
    active_requests: number;
    active_offers: number;
    trusted_organisations: number;
  };
  coordinators: Coordinator[];
  role_pack?: RolePack;
  policy?: WorkflowPolicy;
};

type Coordinator = {
  id: number;
  name: string;
  role: string;
};

type RolePack = {
  available: boolean;
  installed_count: number;
  total_count: number;
  presets: RolePresetStatus[];
};

type RolePresetStatus = {
  key: string;
  role_name: string;
  role_id: number | null;
  installed: boolean;
  permission_count: number;
  installed_permissions: number;
};

type WorkflowPolicy = {
  approval_required: boolean;
  auto_approve_trusted_reviewers: boolean;
  review_sla_days: number;
  escalation_sla_days: number;
  allow_member_self_log: boolean;
  require_organisation_for_partner_hours: boolean;
  monthly_statement_day: number;
  municipal_report_default_period: string;
  include_social_value_estimate: boolean;
  default_hour_value_chf: number;
};

type MemberStatement = {
  user: {
    id: number;
    name: string;
    email: string;
    current_balance: number;
  };
  period: {
    start: string;
    end: string;
    statement_day: number;
  };
  summary: {
    approved_support_hours: number;
    pending_support_hours: number;
    wallet_hours_earned: number;
    wallet_hours_spent: number;
    wallet_net_change: number;
    current_balance: number;
    estimated_social_value_chf: number;
  };
  support_hours_by_organisation: Array<{
    organisation_name: string;
    approved_hours: number;
    pending_hours: number;
    log_count: number;
  }>;
};

type MemberStatementCsv = {
  filename: string;
  csv: string;
  statement: MemberStatement;
};

type SupportRelationship = {
  id: number;
  supporter: { id: number; name: string };
  recipient: { id: number; name: string };
  coordinator: { id: number; name: string } | null;
  organization_name: string;
  category_name: string;
  title: string;
  description: string;
  frequency: 'weekly' | 'fortnightly' | 'monthly' | 'ad_hoc';
  expected_hours: number;
  start_date: string;
  end_date: string | null;
  status: 'active' | 'paused' | 'completed' | 'cancelled';
  last_logged_at: string | null;
  next_check_in_at: string | null;
};

type SupportRelationshipList = {
  stats: {
    active_count: number;
    paused_count: number;
    check_ins_due: number;
    expected_active_hours: number;
  };
  items: SupportRelationship[];
};

type InviteCode = {
  id: number;
  code: string;
  label: string | null;
  expires_at: string;
  used_at: string | null;
  used_by: string | null;
  status: 'active' | 'used' | 'expired';
  created_at: string;
  invite_url: string;
};

type GeneratedCode = {
  id: number;
  code: string;
  label: string | null;
  expires_at: string;
  invite_url: string;
};

type PaperOnboardingFields = {
  name?: string | null;
  date_of_birth?: string | null;
  address?: string | null;
  phone?: string | null;
  email?: string | null;
};

type PaperOnboardingIntake = {
  id: number;
  status: 'pending_review' | 'confirmed' | 'rejected';
  original_filename: string;
  extracted_fields: PaperOnboardingFields | null;
  corrected_fields: PaperOnboardingFields | null;
  ocr_provider: string;
  created_at: string;
};

type PaperOnboardingList = {
  count: number;
  items: PaperOnboardingIntake[];
};

const workflowStages = [
  { key: 'intake', icon: HeartHandshake },
  { key: 'match', icon: Users },
  { key: 'log', icon: Clock },
  { key: 'verify', icon: ClipboardCheck },
  { key: 'statement', icon: FileText },
] as const;

const rolePresets = [
  { key: 'national_admin', icon: ShieldCheck },
  { key: 'canton_admin', icon: Building2 },
  { key: 'municipality_admin', icon: Building2 },
  { key: 'cooperative_coordinator', icon: HeartHandshake },
  { key: 'organisation_coordinator', icon: Users },
  { key: 'trusted_reviewer', icon: ClipboardCheck },
] as const;

const reportPeriods = ['last_30_days', 'last_90_days', 'year_to_date', 'previous_quarter'] as const;
const relationshipFrequencies = ['weekly', 'fortnightly', 'monthly', 'ad_hoc'] as const;

const frequencyLabels: Record<'weekly' | 'fortnightly' | 'monthly' | 'ad_hoc', string> = {
  weekly: 'Weekly',
  fortnightly: 'Fortnightly',
  monthly: 'Monthly',
  ad_hoc: 'Ad hoc',
};

const reportPeriodLabels: Record<'last_30_days' | 'last_90_days' | 'year_to_date' | 'previous_quarter', string> = {
  last_30_days: 'Last 30 days',
  last_90_days: 'Last 90 days',
  year_to_date: 'Year to date',
  previous_quarter: 'Previous quarter',
};

const supportRelationshipStatusLabels: Record<'active' | 'paused' | 'completed' | 'cancelled', string> = {
  active: 'Active',
  paused: 'Paused',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

const stageContent: Record<'intake' | 'match' | 'log' | 'verify' | 'statement', { title: string; description: string }> = {
  intake: { title: 'Intake', description: 'Welcome new members and capture their support needs.' },
  match: { title: 'Match', description: 'Pair supporters and recipients based on skills and availability.' },
  log: { title: 'Log Hours', description: 'Capture support hours as they happen.' },
  verify: { title: 'Verify', description: 'Coordinators review and approve logged hours.' },
  statement: { title: 'Statement', description: 'Issue monthly statements and impact reports.' },
};

const rolePresetContent: Record<
  'national_admin' | 'canton_admin' | 'municipality_admin' | 'cooperative_coordinator' | 'organisation_coordinator' | 'trusted_reviewer',
  { title: string; description: string }
> = {
  national_admin: { title: 'National Admin', description: 'Platform-wide oversight across all cantons and organisations.' },
  canton_admin: { title: 'Canton Admin', description: 'Manage all caring community activity within a canton.' },
  municipality_admin: { title: 'Municipality Admin', description: 'Manage caring community activity within a municipality.' },
  cooperative_coordinator: { title: 'Cooperative Coordinator', description: 'Coordinate a local cooperative and its members.' },
  organisation_coordinator: { title: 'Organisation Coordinator', description: 'Coordinate volunteers and partners for one organisation.' },
  trusted_reviewer: { title: 'Trusted Reviewer', description: 'Approve hours quickly with light-touch oversight.' },
};

type FavourItem = {
  id: number;
  category: string | null;
  description: string;
  favour_date: string;
  is_anonymous: boolean;
  offerer_name: string | null;
};

type FavoursData = { count: number; items: FavourItem[] };

type TandemSuggestionMember = {
  id: number;
  name: string;
  avatar_url: string;
  languages: string[];
  skills: string[];
};

type TandemSuggestion = {
  supporter: TandemSuggestionMember;
  recipient: TandemSuggestionMember;
  score: number;
  signals: {
    distance_km?: number;
    language_overlap?: number;
    skill_complement?: number;
    availability_overlap?: number;
    interest_overlap?: number;
  };
  reason: string;
};

type TandemSuggestionsResponse = {
  suggestions: TandemSuggestion[];
  generated_at: string;
};

type ForecastTrend = 'growing' | 'stable' | 'declining';
type ForecastConfidence = 'high' | 'medium' | 'low';

type ForecastSeries = {
  history: Array<{ month: string; hours: number }>;
  forecast: Array<{ month: string; hours: number; lower: number; upper: number }>;
  trend: ForecastTrend;
  growth_rate_pct: number;
  confidence: ForecastConfidence;
};

type ForecastAlert = {
  id: string;
  severity: 'info' | 'warning' | 'critical';
  title: string;
  message: string;
  count: number;
  action_label: string | null;
  action_url: string | null;
};

type SubRegionDemandRow = {
  id: number;
  name: string;
  slug: string;
  requested_30d: number;
  fulfilled_30d: number;
  coverage_ratio_30d: number;
  requested_90d: number;
  fulfilled_90d: number;
  coverage_ratio_90d: number;
  flagged: boolean;
};

type SubRegionDemand = {
  window_days: { short: number; long: number };
  sub_regions: SubRegionDemandRow[];
  under_supplied_count: number;
};

type HelperChurnCategoryRow = {
  category_id: number | null;
  category_name: string;
  prior_active: number;
  lapsed: number;
  churn_rate: number;
};

type HelperChurn = {
  prior_window_days: { start: number; end: number };
  lapsed_threshold_days: number;
  overall: { prior_active: number; lapsed: number; churn_rate: number };
  by_category: HelperChurnCategoryRow[];
  lapsed_helper_ids: number[];
};

type CoefficientDriftRow = {
  category_id: number;
  category_name: string;
  baseline_coefficient: number;
  expected_session_hours: number;
  observed_session_hours: number;
  drift: number;
  flagged: boolean;
  sample_size: number;
};

type CoefficientDrift = {
  threshold: number;
  categories: CoefficientDriftRow[];
  drift_count: number;
};

type ForecastResponse = {
  hours: ForecastSeries;
  members: ForecastSeries;
  recipients: ForecastSeries;
  sub_region_demand?: SubRegionDemand;
  helper_churn?: HelperChurn;
  coefficient_drift?: CoefficientDrift;
  alerts: ForecastAlert[];
  generated_at: string;
};

function isForecastResponse(value: unknown): value is ForecastResponse {
  if (!value || typeof value !== 'object') return false;
  const v = value as Partial<ForecastResponse>;
  return (
    typeof v.hours === 'object' && v.hours !== null && Array.isArray(v.hours.history) &&
    typeof v.members === 'object' && v.members !== null && Array.isArray(v.members.history) &&
    typeof v.recipients === 'object' && v.recipients !== null && Array.isArray(v.recipients.history) &&
    Array.isArray(v.alerts)
  );
}

function isTandemSuggestionsResponse(value: unknown): value is TandemSuggestionsResponse {
  return Boolean(value && typeof value === 'object' && 'suggestions' in value && Array.isArray((value as TandemSuggestionsResponse).suggestions));
}

function isWorkflowSummary(value: unknown): value is WorkflowSummary {
  return Boolean(value && typeof value === 'object' && 'stats' in value && 'pending_reviews' in value);
}

function isSupportRelationshipList(value: unknown): value is SupportRelationshipList {
  return Boolean(value && typeof value === 'object' && 'stats' in value && 'items' in value);
}

type PredictiveInsightsCardProps = {
  forecast: ForecastResponse | null;
  loading: boolean;
  error: string | null;
  onRefresh: () => void;
};

type ForecastChartPoint = {
  month: string;
  history: number | null;
  predicted: number | null;
  band: [number, number] | null;
};

function buildChartData(series: ForecastSeries): ForecastChartPoint[] {
  const points: ForecastChartPoint[] = series.history.map((h) => ({
    month: h.month,
    history: h.hours,
    predicted: null,
    band: null,
  }));

  // Bridge: last history point also feeds the predicted line so the series joins.
  const lastHistory = series.history[series.history.length - 1];
  const lastPoint = points[points.length - 1];
  if (lastHistory && lastPoint) {
    lastPoint.predicted = lastHistory.hours;
    lastPoint.band = [lastHistory.hours, lastHistory.hours];
  }

  for (const f of series.forecast) {
    points.push({
      month: f.month,
      history: null,
      predicted: f.hours,
      band: [f.lower, f.upper],
    });
  }
  return points;
}

function trendChip(series: ForecastSeries): { label: string; color: 'success' | 'warning' | 'default'; icon: typeof TrendingUp } {
  if (series.trend === 'growing') {
    return { label: `Growing ${series.growth_rate_pct.toFixed(0)}%`, color: 'success', icon: TrendingUp };
  }
  if (series.trend === 'declining') {
    return { label: `Declining ${Math.abs(series.growth_rate_pct).toFixed(0)}%`, color: 'warning', icon: TrendingDown };
  }
  return { label: 'Stable', color: 'default', icon: Minus };
}

function ForecastMiniChart({ title, series, valueSuffix }: { title: string; series: ForecastSeries; valueSuffix: string }): JSX.Element {
  const data = buildChartData(series);
  const chip = trendChip(series);
  const ChipIcon = chip.icon;

  if (series.history.every((p) => p.hours === 0) && series.forecast.length === 0) {
    return (
      <div className="rounded-lg border border-default-200 p-4">
        <div className="text-sm font-semibold text-default-900">{title}</div>
        <div className="mt-3 rounded-md bg-default-100 p-3 text-xs text-default-500">
          Not enough activity yet to forecast. Come back in a few weeks.
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-default-200 p-4">
      <div className="flex items-center justify-between gap-2">
        <div className="text-sm font-semibold text-default-900">{title}</div>
        <Chip size="sm" variant="flat" color={chip.color} startContent={<ChipIcon size={14} />}>
          {chip.label}
        </Chip>
      </div>
      <div className="mt-3 h-40 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <ComposedChart data={data} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="var(--color-divider)" />
            <XAxis dataKey="month" tick={{ fontSize: 10 }} interval="preserveStartEnd" />
            <YAxis tick={{ fontSize: 10 }} />
            <Tooltip
              formatter={(value) => {
                if (value === null || value === undefined) return '—';
                const num = typeof value === 'number' ? value : Number(value);
                return Number.isFinite(num) ? `${num.toFixed(1)} ${valueSuffix}`.trim() : '—';
              }}
              labelStyle={{ fontSize: 12 }}
              contentStyle={{ fontSize: 12 }}
            />
            <Area
              type="monotone"
              dataKey="band"
              stroke="none"
              fill="hsl(var(--heroui-primary) / 0.15)"
              connectNulls
              isAnimationActive={false}
            />
            <Line
              type="monotone"
              dataKey="history"
              stroke="hsl(var(--heroui-primary))"
              strokeWidth={2}
              dot={{ r: 2 }}
              connectNulls={false}
              isAnimationActive={false}
              name="History"
            />
            <Line
              type="monotone"
              dataKey="predicted"
              stroke="hsl(var(--heroui-primary))"
              strokeDasharray="4 4"
              strokeWidth={2}
              dot={{ r: 2 }}
              connectNulls
              isAnimationActive={false}
              name="Forecast"
            />
          </ComposedChart>
        </ResponsiveContainer>
      </div>
      <div className="mt-1 text-[11px] text-default-400">
        Confidence: <span className="capitalize">{series.confidence}</span>
      </div>
    </div>
  );
}

function alertSeverityChipColor(severity: ForecastAlert['severity']): 'danger' | 'warning' | 'primary' {
  if (severity === 'critical') return 'danger';
  if (severity === 'warning') return 'warning';
  return 'primary';
}

function alertSeverityLabel(severity: ForecastAlert['severity']): string {
  if (severity === 'critical') return 'Critical';
  if (severity === 'warning') return 'Warning';
  return 'Info';
}

function PredictiveInsightsCard({ forecast, loading, error, onRefresh }: PredictiveInsightsCardProps): JSX.Element {
  const isInitialLoading = loading && !forecast;
  const hasAnyHistory = forecast
    ? [forecast.hours, forecast.members, forecast.recipients].some(
        (s) => s.history.some((h) => h.hours > 0) || s.forecast.length > 0,
      )
    : false;

  return (
    <Card shadow="sm">
      <CardHeader className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold flex items-center gap-2">
            <BrainCircuit size={18} className="text-primary" />
            Predictive Insights
          </h2>
          <p className="mt-1 text-sm text-default-500">
            Forward-looking forecasts and proactive alerts. Spot regional care deficits before they become emergencies.
          </p>
        </div>
        <Button
          size="sm"
          variant="flat"
          startContent={<RefreshCw size={16} />}
          isLoading={loading}
          onPress={onRefresh}
        >
          Refresh
        </Button>
      </CardHeader>
      <Divider />
      <CardBody className="gap-4">
        {isInitialLoading ? (
          <div className="flex items-center justify-center py-10">
            <Spinner size="md" />
          </div>
        ) : error ? (
          <div className="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 flex items-center justify-between gap-3">
            <span>{error}</span>
            <Button size="sm" variant="flat" color="danger" startContent={<RefreshCw size={14} />} onPress={onRefresh}>
              Retry
            </Button>
          </div>
        ) : !forecast ? (
          <div className="rounded-lg bg-default-100 p-4 text-sm text-default-500">
            No forecast data available yet.
          </div>
        ) : !hasAnyHistory ? (
          <div className="rounded-lg bg-default-100 p-4 text-sm text-default-500">
            Not enough activity yet to forecast. Come back in a few weeks.
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <ForecastMiniChart title="Hours forecast" series={forecast.hours} valueSuffix="h" />
              <ForecastMiniChart title="Active members" series={forecast.members} valueSuffix="" />
              <ForecastMiniChart title="Recipients reached" series={forecast.recipients} valueSuffix="" />
            </div>
            {forecast.sub_region_demand && forecast.sub_region_demand.sub_regions.length > 0 && (
              <div className="space-y-2">
                <div className="text-sm font-semibold text-default-900 flex items-center gap-2">
                  <Info size={14} />
                  Sub-region coverage (last {forecast.sub_region_demand.window_days.long} days)
                  {forecast.sub_region_demand.under_supplied_count > 0 && (
                    <Chip size="sm" variant="flat" color="warning">
                      {forecast.sub_region_demand.under_supplied_count} under-supplied
                    </Chip>
                  )}
                </div>
                <div className="overflow-x-auto rounded-lg border border-default-200">
                  <table className="w-full text-xs">
                    <thead className="bg-default-50 text-default-500">
                      <tr>
                        <th className="px-3 py-2 text-left">Sub-region</th>
                        <th className="px-3 py-2 text-right">Requested (90d)</th>
                        <th className="px-3 py-2 text-right">Fulfilled (90d)</th>
                        <th className="px-3 py-2 text-right">Coverage</th>
                        <th className="px-3 py-2 text-left">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {forecast.sub_region_demand.sub_regions.map((r) => (
                        <tr key={r.id} className="border-t border-default-200">
                          <td className="px-3 py-2">{r.name}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{r.requested_90d.toFixed(1)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{r.fulfilled_90d.toFixed(1)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{(r.coverage_ratio_90d * 100).toFixed(0)}%</td>
                          <td className="px-3 py-2">
                            <Chip size="sm" variant="flat" color={r.flagged ? 'warning' : 'default'}>
                              {r.flagged ? 'Under-supplied' : 'OK'}
                            </Chip>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {forecast.helper_churn && forecast.helper_churn.overall.prior_active > 0 && (
              <div className="space-y-2">
                <div className="text-sm font-semibold text-default-900 flex items-center gap-2">
                  <Info size={14} />
                  Helper churn (lapsed ≥ {forecast.helper_churn.lapsed_threshold_days} days)
                  <Chip
                    size="sm"
                    variant="flat"
                    color={forecast.helper_churn.overall.churn_rate > 0.3 ? 'warning' : 'default'}
                  >
                    {(forecast.helper_churn.overall.churn_rate * 100).toFixed(0)}% overall
                  </Chip>
                </div>
                <p className="text-xs text-default-500">
                  {forecast.helper_churn.overall.lapsed} of {forecast.helper_churn.overall.prior_active} previously
                  active helpers haven't logged hours recently.
                </p>
                {forecast.helper_churn.by_category.length > 0 && (
                  <div className="overflow-x-auto rounded-lg border border-default-200">
                    <table className="w-full text-xs">
                      <thead className="bg-default-50 text-default-500">
                        <tr>
                          <th className="px-3 py-2 text-left">Category</th>
                          <th className="px-3 py-2 text-right">Prior active</th>
                          <th className="px-3 py-2 text-right">Lapsed</th>
                          <th className="px-3 py-2 text-right">Churn rate</th>
                        </tr>
                      </thead>
                      <tbody>
                        {forecast.helper_churn.by_category.map((c) => (
                          <tr
                            key={`${c.category_id ?? 'none'}-${c.category_name}`}
                            className="border-t border-default-200"
                          >
                            <td className="px-3 py-2">{c.category_name}</td>
                            <td className="px-3 py-2 text-right tabular-nums">{c.prior_active}</td>
                            <td className="px-3 py-2 text-right tabular-nums">{c.lapsed}</td>
                            <td className="px-3 py-2 text-right tabular-nums">{(c.churn_rate * 100).toFixed(0)}%</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}

            {forecast.coefficient_drift && forecast.coefficient_drift.categories.length > 0 && (
              <div className="space-y-2">
                <div className="text-sm font-semibold text-default-900 flex items-center gap-2">
                  <Info size={14} />
                  Category coefficient drift
                  {forecast.coefficient_drift.drift_count > 0 && (
                    <Chip size="sm" variant="flat" color="warning">
                      {forecast.coefficient_drift.drift_count} drifting
                    </Chip>
                  )}
                </div>
                <p className="text-xs text-default-500">
                  Categories with absolute drift &gt; {(forecast.coefficient_drift.threshold * 100).toFixed(0)}% are
                  flagged. Review baseline coefficients in the category coefficient editor.
                </p>
                <div className="overflow-x-auto rounded-lg border border-default-200">
                  <table className="w-full text-xs">
                    <thead className="bg-default-50 text-default-500">
                      <tr>
                        <th className="px-3 py-2 text-left">Category</th>
                        <th className="px-3 py-2 text-right">Baseline</th>
                        <th className="px-3 py-2 text-right">Expected hrs</th>
                        <th className="px-3 py-2 text-right">Observed hrs</th>
                        <th className="px-3 py-2 text-right">Drift</th>
                        <th className="px-3 py-2 text-right">Sample</th>
                      </tr>
                    </thead>
                    <tbody>
                      {forecast.coefficient_drift.categories.map((c) => (
                        <tr key={c.category_id} className="border-t border-default-200">
                          <td className="px-3 py-2">{c.category_name}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{c.baseline_coefficient.toFixed(2)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{c.expected_session_hours.toFixed(2)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{c.observed_session_hours.toFixed(2)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">
                            <Chip size="sm" variant="flat" color={c.flagged ? 'warning' : 'default'}>
                              {(c.drift * 100).toFixed(0)}%
                            </Chip>
                          </td>
                          <td className="px-3 py-2 text-right tabular-nums">{c.sample_size}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {forecast.alerts.length > 0 && (
              <div className="space-y-2">
                <div className="text-sm font-semibold text-default-900 flex items-center gap-2">
                  <Info size={14} />
                  Proactive alerts
                </div>
                {forecast.alerts.map((alert) => (
                  <div key={alert.id} className="rounded-lg border border-default-200 p-3 flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <Chip size="sm" variant="flat" color={alertSeverityChipColor(alert.severity)}>
                          {alertSeverityLabel(alert.severity)}
                        </Chip>
                        <span className="text-sm font-semibold text-default-900">{alert.title}</span>
                        <Chip size="sm" variant="flat" color="default">
                          {alert.count.toLocaleString()}
                        </Chip>
                      </div>
                      <p className="mt-1 text-xs text-default-500">{alert.message}</p>
                    </div>
                    {alert.action_url && alert.action_label && (
                      <Button
                        as={Link}
                        size="sm"
                        variant="flat"
                        color={alertSeverityChipColor(alert.severity)}
                        to={alert.action_url}
                      >
                        {alert.action_label}
                      </Button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </>
        )}
      </CardBody>
    </Card>
  );
}

export default function CaringCommunityWorkflowPage() {
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle('Caring Community Workflow');

  const [summary, setSummary] = useState<WorkflowSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [installingRoles, setInstallingRoles] = useState(false);
  const [savingPolicy, setSavingPolicy] = useState(false);
  const [assigningReviewId, setAssigningReviewId] = useState<number | null>(null);
  const [escalatingReviewId, setEscalatingReviewId] = useState<number | null>(null);
  const [decidingReviewId, setDecidingReviewId] = useState<number | null>(null);
  const [statementMemberId, setStatementMemberId] = useState('');
  const [statementStartDate, setStatementStartDate] = useState('');
  const [statementEndDate, setStatementEndDate] = useState('');
  const [memberStatement, setMemberStatement] = useState<MemberStatement | null>(null);
  const [loadingStatement, setLoadingStatement] = useState(false);
  const [relationships, setRelationships] = useState<SupportRelationshipList | null>(null);
  const [loadingRelationships, setLoadingRelationships] = useState(false);
  const [savingRelationship, setSavingRelationship] = useState(false);
  const [relationshipSupporterId, setRelationshipSupporterId] = useState('');
  const [relationshipRecipientId, setRelationshipRecipientId] = useState('');
  const [relationshipSupporter, setRelationshipSupporter] = useState<MemberSearchMember | null>(null);
  const [relationshipRecipient, setRelationshipRecipient] = useState<MemberSearchMember | null>(null);
  const [relationshipTitle, setRelationshipTitle] = useState('');
  const [relationshipFrequency, setRelationshipFrequency] = useState<SupportRelationship['frequency']>('weekly');
  const [relationshipExpectedHours, setRelationshipExpectedHours] = useState('1');
  const [relationshipStartDate, setRelationshipStartDate] = useState('');
  const [updatingRelationshipId, setUpdatingRelationshipId] = useState<number | null>(null);
  const [loggingRelationshipId, setLoggingRelationshipId] = useState<number | null>(null);
  const [relationshipLogDate, setRelationshipLogDate] = useState('');
  const [relationshipLogHours, setRelationshipLogHours] = useState('');
  const [relationshipLogDescription, setRelationshipLogDescription] = useState('');

  // Invite code state
  const [inviteLabel, setInviteLabel] = useState('');
  const [generatingCode, setGeneratingCode] = useState(false);
  const [generatedCode, setGeneratedCode] = useState<GeneratedCode | null>(null);
  const [codeCopied, setCodeCopied] = useState(false);
  const [inviteCodes, setInviteCodes] = useState<InviteCode[]>([]);
  const [loadingInviteCodes, setLoadingInviteCodes] = useState(false);
  const [printCodeId, setPrintCodeId] = useState<number | null>(null);

  // Informal favours state
  const [favoursData, setFavoursData] = useState<FavoursData | null>(null);
  const [loadingFavours, setLoadingFavours] = useState(false);

  // Tandem suggestions state (admin English-only)
  const [tandemSuggestions, setTandemSuggestions] = useState<TandemSuggestion[]>([]);
  const [loadingTandems, setLoadingTandems] = useState(false);
  const [tandemError, setTandemError] = useState<string | null>(null);
  const [dismissingTandemKey, setDismissingTandemKey] = useState<string | null>(null);

  // Predictive insights state (admin English-only) — Tom Debus AI/Daten pillar
  const [forecast, setForecast] = useState<ForecastResponse | null>(null);
  const [loadingForecast, setLoadingForecast] = useState(false);
  const [forecastError, setForecastError] = useState<string | null>(null);

  // Safeguarding reports dashboard state (admin English-only) — K9
  type SafeguardingDashboardSummary = {
    total: number;
    open_total: number;
    open_by_severity: { critical: number; high: number; medium: number; low: number };
    by_status: Record<string, number>;
    overdue: number;
    recent: Array<{
      id: number;
      reporter_name: string;
      subject_user_name: string | null;
      subject_organisation_id: number | null;
      category: string;
      severity: 'low' | 'medium' | 'high' | 'critical';
      status: 'submitted' | 'triaged' | 'investigating' | 'resolved' | 'dismissed';
      is_overdue: boolean;
      created_at: string;
    }>;
  };
  const [safeguardingSummary, setSafeguardingSummary] = useState<SafeguardingDashboardSummary | null>(null);
  const [loadingSafeguarding, setLoadingSafeguarding] = useState(false);

  // Assisted onboarding state
  const [onboardingName, setOnboardingName] = useState('');
  const [onboardingEmail, setOnboardingEmail] = useState('');
  const [onboardingPhone, setOnboardingPhone] = useState('');
  const [onboardingNote, setOnboardingNote] = useState('');
  const [onboardingLoading, setOnboardingLoading] = useState(false);
  const [onboardingResult, setOnboardingResult] = useState<{ user: { id: number; name: string; email: string }; temp_password: string } | null>(null);
  const [onboardingCopied, setOnboardingCopied] = useState(false);
  const [paperIntakes, setPaperIntakes] = useState<PaperOnboardingIntake[]>([]);
  const [paperFile, setPaperFile] = useState<File | null>(null);
  const [paperUploading, setPaperUploading] = useState(false);
  const [paperLoading, setPaperLoading] = useState(false);
  const [paperReviewingId, setPaperReviewingId] = useState<number | null>(null);
  const [paperName, setPaperName] = useState('');
  const [paperDateOfBirth, setPaperDateOfBirth] = useState('');
  const [paperAddress, setPaperAddress] = useState('');
  const [paperPhone, setPaperPhone] = useState('');
  const [paperEmail, setPaperEmail] = useState('');
  const [paperNote, setPaperNote] = useState('');

  const loadWorkflow = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<WorkflowSummary>('/v2/admin/caring-community/workflow');
      if (isWorkflowSummary(res.data)) setSummary(res.data);
    } catch {
      toast.error('Could not load caring community workflow.');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadWorkflow();
  }, [loadWorkflow]);

  const loadSupportRelationships = useCallback(async () => {
    setLoadingRelationships(true);
    try {
      const res = await api.get<SupportRelationshipList>('/v2/admin/caring-community/support-relationships?status=all');
      if (isSupportRelationshipList(res.data)) setRelationships(res.data);
    } catch {
      toast.error('Could not load support relationships.');
    } finally {
      setLoadingRelationships(false);
    }
  }, [toast]);

  useEffect(() => {
    loadSupportRelationships();
  }, [loadSupportRelationships]);

  const loadForecast = useCallback(async () => {
    setLoadingForecast(true);
    setForecastError(null);
    try {
      const res = await api.get<ForecastResponse>('/v2/admin/caring-community/forecast');
      if (isForecastResponse(res.data)) {
        setForecast(res.data);
      } else {
        setForecast(null);
        setForecastError('Could not load predictive insights.');
      }
    } catch {
      setForecastError('Could not load predictive insights.');
    } finally {
      setLoadingForecast(false);
    }
  }, []);

  useEffect(() => {
    loadForecast();
  }, [loadForecast]);

  const loadSafeguardingSummary = useCallback(async () => {
    setLoadingSafeguarding(true);
    try {
      const res = await api.get<SafeguardingDashboardSummary>('/v2/admin/caring-community/safeguarding/dashboard');
      if (res.success && res.data) {
        setSafeguardingSummary(res.data);
      } else {
        setSafeguardingSummary(null);
      }
    } catch {
      setSafeguardingSummary(null);
    } finally {
      setLoadingSafeguarding(false);
    }
  }, []);

  useEffect(() => {
    loadSafeguardingSummary();
  }, [loadSafeguardingSummary]);

  const loadPaperIntakes = useCallback(async () => {
    setPaperLoading(true);
    try {
      const res = await api.get<PaperOnboardingList>('/v2/admin/caring-community/paper-onboarding?status=pending_review');
      if (res.data && Array.isArray(res.data.items)) {
        setPaperIntakes(res.data.items);
      }
    } catch {
      toast.error('Could not load paper onboarding intakes.');
    } finally {
      setPaperLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadPaperIntakes();
  }, [loadPaperIntakes]);

  const loadTandemSuggestions = useCallback(async () => {
    setLoadingTandems(true);
    setTandemError(null);
    try {
      const res = await api.get<TandemSuggestionsResponse>('/v2/admin/caring-community/tandem-suggestions?limit=20');
      if (isTandemSuggestionsResponse(res.data)) {
        setTandemSuggestions(res.data.suggestions);
      } else {
        setTandemSuggestions([]);
      }
    } catch {
      setTandemError('Could not load tandem suggestions.');
    } finally {
      setLoadingTandems(false);
    }
  }, []);

  useEffect(() => {
    loadTandemSuggestions();
  }, [loadTandemSuggestions]);

  const dismissTandemSuggestion = useCallback(async (suggestion: TandemSuggestion) => {
    const key = `${suggestion.supporter.id}:${suggestion.recipient.id}`;
    setDismissingTandemKey(key);
    try {
      await api.post('/v2/admin/caring-community/tandem-suggestions/dismiss', {
        supporter_id: suggestion.supporter.id,
        recipient_id: suggestion.recipient.id,
      });
      setTandemSuggestions((prev) => prev.filter((s) => `${s.supporter.id}:${s.recipient.id}` !== key));
      toast.success('Suggestion dismissed.');
    } catch {
      toast.error('Could not dismiss suggestion.');
    } finally {
      setDismissingTandemKey(null);
    }
  }, [toast]);

  const createTandemFromSuggestion = useCallback((suggestion: TandemSuggestion) => {
    const supporter: MemberSearchMember = {
      id: suggestion.supporter.id,
      name: suggestion.supporter.name,
      email: '',
      avatar_url: suggestion.supporter.avatar_url || null,
    };
    const recipient: MemberSearchMember = {
      id: suggestion.recipient.id,
      name: suggestion.recipient.name,
      email: '',
      avatar_url: suggestion.recipient.avatar_url || null,
    };
    setRelationshipSupporterId(String(suggestion.supporter.id));
    setRelationshipRecipientId(String(suggestion.recipient.id));
    setRelationshipSupporter(supporter);
    setRelationshipRecipient(recipient);
    setRelationshipTitle(`Tandem: ${suggestion.supporter.name} & ${suggestion.recipient.name}`);
    // Scroll to the support-relationships create form so the coordinator sees it.
    if (typeof window !== 'undefined') {
      const target = document.getElementById('caring-support-relationship-form');
      if (target && typeof target.scrollIntoView === 'function') {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }, []);

  useEffect(() => {
    setLoadingFavours(true);
    api.get<FavoursData>('/v2/admin/caring-community/favours')
      .then((res) => {
        if (res.data && typeof res.data === 'object' && 'count' in res.data) {
          setFavoursData(res.data);
        }
      })
      .catch(() => { /* non-critical — silently ignore */ })
      .finally(() => setLoadingFavours(false));
  }, []);

  const stats = summary?.stats;
  const signals = summary?.coordinator_signals;
  const rolePack = summary?.role_pack;
  const formatHours = (value: number) => `${Number(value.toFixed(1))} h`;
  const formatChf = (value: number) => `Estimated social value: CHF ${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;

  const roleCountLabel = useMemo(() => (
    rolePack
      ? `${rolePack.installed_count} of ${rolePack.total_count} role presets installed`
      : `${rolePresets.length} role presets available`
  ), [rolePack]);

  const roleStatusByKey = useMemo(() => {
    const statuses = rolePack?.presets ?? [];
    return new Map(statuses.map((status) => [status.key, status]));
  }, [rolePack]);
  const coordinatorOptions = useMemo(() => [
    { id: 'unassigned', label: 'Unassigned' },
    ...(summary?.coordinators ?? []).map((coordinator) => ({ id: String(coordinator.id), label: coordinator.name })),
  ], [summary?.coordinators]);
  const frequencyOptions = useMemo(() => relationshipFrequencies.map((frequency) => ({
    id: frequency,
    label: frequencyLabels[frequency],
  })), []);

  const replaceReview = useCallback((review: PendingReview) => {
    setSummary((current) => {
      if (!current) return current;
      return {
        ...current,
        pending_reviews: current.pending_reviews.map((item) => item.id === review.id ? review : item),
      };
    });
  }, []);

  const updatePolicyField = useCallback(<K extends keyof WorkflowPolicy>(key: K, value: WorkflowPolicy[K]) => {
    setSummary((current) => {
      if (!current?.policy) return current;
      return { ...current, policy: { ...current.policy, [key]: value } };
    });
  }, []);

  const installRolePack = useCallback(async () => {
    setInstallingRoles(true);
    try {
      const res = await api.post<RolePack>('/v2/admin/caring-community/role-presets/install', {});
      setSummary((current) => current ? { ...current, role_pack: res.data } : current);
      toast.success('Role pack installed.');
    } catch {
      toast.error('Could not install role pack.');
    } finally {
      setInstallingRoles(false);
    }
  }, [toast]);

  const savePolicy = useCallback(async () => {
    if (!summary?.policy) return;

    setSavingPolicy(true);
    try {
      const res = await api.put<WorkflowPolicy>('/v2/admin/caring-community/workflow/policy', summary.policy);
      setSummary((current) => current ? { ...current, policy: res.data } : current);
      toast.success('Workflow policy saved.');
    } catch {
      toast.error('Could not save workflow policy.');
    } finally {
      setSavingPolicy(false);
    }
  }, [summary?.policy, toast]);

  const assignReview = useCallback(async (reviewId: number, assignedTo: number | null) => {
    setAssigningReviewId(reviewId);
    try {
      const res = await api.put<{ review: PendingReview }>(`/v2/admin/caring-community/workflow/reviews/${reviewId}/assign`, {
        assigned_to: assignedTo,
      });
      if (res.data?.review) replaceReview(res.data.review);
      toast.success('Coordinator assigned.');
    } catch {
      toast.error('Could not assign coordinator.');
    } finally {
      setAssigningReviewId(null);
    }
  }, [replaceReview, toast]);

  const escalateReview = useCallback(async (review: PendingReview) => {
    setEscalatingReviewId(review.id);
    try {
      const res = await api.put<{ review: PendingReview }>(`/v2/admin/caring-community/workflow/reviews/${review.id}/escalate`, {
        note: `Manually escalated after ${review.age_days} day${review.age_days === 1 ? '' : 's'} pending.`,
      });
      if (res.data?.review) replaceReview(res.data.review);
      toast.success('Review escalated.');
    } catch {
      toast.error('Could not escalate review.');
    } finally {
      setEscalatingReviewId(null);
    }
  }, [replaceReview, toast]);

  const decideReview = useCallback(async (review: PendingReview, action: 'approve' | 'decline') => {
    setDecidingReviewId(review.id);
    try {
      const res = await api.put<{ review: { summary: WorkflowSummary } }>(`/v2/admin/caring-community/workflow/reviews/${review.id}/decision`, {
        action,
      });
      if (res.data?.review?.summary) setSummary(res.data.review.summary);
      toast.success(action === 'approve' ? 'Review approved.' : 'Review declined.');
    } catch {
      toast.error('Could not record decision.');
    } finally {
      setDecidingReviewId(null);
    }
  }, [toast]);

  const statementQuery = useCallback((format?: 'csv') => {
    const params = new URLSearchParams();
    if (statementStartDate) params.set('start_date', statementStartDate);
    if (statementEndDate) params.set('end_date', statementEndDate);
    if (format) params.set('format', format);
    const query = params.toString();
    return query ? `?${query}` : '';
  }, [statementEndDate, statementStartDate]);

  const loadMemberStatement = useCallback(async () => {
    const memberId = Number(statementMemberId);
    if (!Number.isInteger(memberId) || memberId <= 0) {
      toast.error('Enter a valid member ID.');
      return;
    }

    setLoadingStatement(true);
    try {
      const res = await api.get<MemberStatement>(`/v2/admin/caring-community/member-statements/${memberId}${statementQuery()}`);
      setMemberStatement(res.data ?? null);
    } catch {
      toast.error('Could not load member statement.');
    } finally {
      setLoadingStatement(false);
    }
  }, [statementMemberId, statementQuery, toast]);

  const exportMemberStatement = useCallback(async () => {
    const memberId = Number(statementMemberId);
    if (!Number.isInteger(memberId) || memberId <= 0) {
      toast.error('Enter a valid member ID.');
      return;
    }

    setLoadingStatement(true);
    try {
      const res = await api.get<MemberStatementCsv>(`/v2/admin/caring-community/member-statements/${memberId}${statementQuery('csv')}`);
      if (!res.data) throw new Error('Missing member statement CSV payload');
      setMemberStatement(res.data.statement);
      const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = res.data.filename;
      anchor.click();
      URL.revokeObjectURL(url);
      toast.success('Statement exported.');
    } catch {
      toast.error('Could not export statement.');
    } finally {
      setLoadingStatement(false);
    }
  }, [statementMemberId, statementQuery, toast]);

  const createSupportRelationship = useCallback(async () => {
    const supporterId = Number(relationshipSupporterId);
    const recipientId = Number(relationshipRecipientId);
    if (!Number.isInteger(supporterId) || !Number.isInteger(recipientId) || supporterId <= 0 || recipientId <= 0 || supporterId === recipientId) {
      toast.error('Pick two different members.');
      return;
    }

    setSavingRelationship(true);
    try {
      await api.post<SupportRelationship>('/v2/admin/caring-community/support-relationships', {
        supporter_id: supporterId,
        recipient_id: recipientId,
        title: relationshipTitle || undefined,
        frequency: relationshipFrequency,
        expected_hours: Number(relationshipExpectedHours || 1),
        start_date: relationshipStartDate || undefined,
      });
      setRelationshipSupporterId('');
      setRelationshipRecipientId('');
      setRelationshipSupporter(null);
      setRelationshipRecipient(null);
      setRelationshipTitle('');
      setRelationshipExpectedHours('1');
      setRelationshipStartDate('');
      toast.success('Support relationship created.');
      loadSupportRelationships();
    } catch {
      toast.error('Could not create support relationship.');
    } finally {
      setSavingRelationship(false);
    }
  }, [
    loadSupportRelationships,
    relationshipExpectedHours,
    relationshipFrequency,
    relationshipRecipientId,
    relationshipStartDate,
    relationshipSupporterId,
    relationshipTitle,
    toast,
  ]);

  const updateSupportRelationshipStatus = useCallback(async (relationship: SupportRelationship, status: SupportRelationship['status']) => {
    setUpdatingRelationshipId(relationship.id);
    try {
      await api.put<SupportRelationship>(`/v2/admin/caring-community/support-relationships/${relationship.id}`, { status });
      toast.success('Support relationship updated.');
      loadSupportRelationships();
    } catch {
      toast.error('Could not update support relationship.');
    } finally {
      setUpdatingRelationshipId(null);
    }
  }, [loadSupportRelationships, toast]);

  const logSupportRelationshipHours = useCallback(async (relationship: SupportRelationship) => {
    const hours = Number(relationshipLogHours || relationship.expected_hours);
    if (!relationshipLogDate || !Number.isFinite(hours) || hours <= 0 || hours > 24) {
      toast.error('Enter a valid date and 0.25–24 hours.');
      return;
    }

    setLoggingRelationshipId(relationship.id);
    try {
      await api.post(`/v2/admin/caring-community/support-relationships/${relationship.id}/hours`, {
        date: relationshipLogDate,
        hours,
        description: relationshipLogDescription || undefined,
      });
      toast.success('Support hours logged.');
      setRelationshipLogDate('');
      setRelationshipLogHours('');
      setRelationshipLogDescription('');
      loadSupportRelationships();
      loadWorkflow();
    } catch {
      toast.error('Could not log support hours.');
    } finally {
      setLoggingRelationshipId(null);
    }
  }, [
    loadSupportRelationships,
    loadWorkflow,
    relationshipLogDate,
    relationshipLogDescription,
    relationshipLogHours,
    toast,
  ]);

  const loadInviteCodes = useCallback(async () => {
    setLoadingInviteCodes(true);
    try {
      const res = await api.get<InviteCode[]>('/v2/admin/caring-community/invite-codes');
      if (Array.isArray(res.data)) setInviteCodes(res.data);
    } catch {
      toast.error('Could not load invite codes.');
    } finally {
      setLoadingInviteCodes(false);
    }
  }, [toast]);

  useEffect(() => {
    loadInviteCodes();
  }, [loadInviteCodes]);

  const generateInviteCode = useCallback(async () => {
    setGeneratingCode(true);
    setGeneratedCode(null);
    try {
      const res = await api.post<GeneratedCode>('/v2/admin/caring-community/invite-codes', {
        label: inviteLabel.trim() || undefined,
        expires_days: 30,
      });
      if (res.data) {
        setGeneratedCode(res.data);
        setInviteLabel('');
        toast.success('Invite code generated.');
        loadInviteCodes();
      }
    } catch {
      toast.error('Could not generate invite code.');
    } finally {
      setGeneratingCode(false);
    }
  }, [inviteLabel, loadInviteCodes, toast]);

  const copyInviteCode = useCallback((text: string) => {
    void navigator.clipboard.writeText(text);
    setCodeCopied(true);
    setTimeout(() => setCodeCopied(false), 2000);
  }, []);

  const printInviteCard = useCallback((code: GeneratedCode | InviteCode) => {
    setPrintCodeId(code.id);
    setTimeout(() => {
      window.print();
      setPrintCodeId(null);
    }, 50);
  }, []);

  const handlePaperFileChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    setPaperFile(event.target.files?.[0] ?? null);
  }, []);

  const uploadPaperOnboarding = useCallback(async () => {
    if (!paperFile) {
      toast.error('Choose a file to upload first.');
      return;
    }

    setPaperUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', paperFile);
      formData.append('name', paperName);
      formData.append('date_of_birth', paperDateOfBirth);
      formData.append('address', paperAddress);
      formData.append('phone', paperPhone);
      formData.append('email', paperEmail);

      const res = await api.upload<PaperOnboardingIntake>('/v2/admin/caring-community/paper-onboarding', formData);
      if (res.data) {
        setPaperIntakes((current) => [res.data as PaperOnboardingIntake, ...current]);
        setPaperReviewingId(res.data.id);
        setPaperFile(null);
        toast.success('Paper intake uploaded.');
      }
    } catch {
      toast.error('Could not upload paper intake.');
    } finally {
      setPaperUploading(false);
    }
  }, [paperAddress, paperDateOfBirth, paperEmail, paperFile, paperName, paperPhone, toast]);

  const startPaperReview = useCallback((intake: PaperOnboardingIntake) => {
    const fields = intake.corrected_fields ?? intake.extracted_fields ?? {};
    setPaperReviewingId(intake.id);
    setPaperName(fields.name ?? '');
    setPaperDateOfBirth(fields.date_of_birth ?? '');
    setPaperAddress(fields.address ?? '');
    setPaperPhone(fields.phone ?? '');
    setPaperEmail(fields.email ?? '');
    setPaperNote('');
  }, []);

  const confirmPaperOnboarding = useCallback(async () => {
    if (!paperReviewingId) return;
    if (!paperName.trim() || !paperEmail.trim()) {
      toast.error('Name and email are required.');
      return;
    }

    setOnboardingLoading(true);
    setOnboardingResult(null);
    try {
      const res = await api.post<{ success: boolean; user: { id: number; name: string; email: string }; temp_password: string }>(
        `/v2/admin/caring-community/paper-onboarding/${paperReviewingId}/confirm`,
        {
          name: paperName.trim(),
          date_of_birth: paperDateOfBirth.trim() || undefined,
          address: paperAddress.trim() || undefined,
          phone: paperPhone.trim() || undefined,
          email: paperEmail.trim(),
          note: paperNote.trim() || undefined,
        },
      );
      if (res.data) {
        setOnboardingResult({ user: res.data.user, temp_password: res.data.temp_password });
        setPaperIntakes((current) => current.filter((item) => item.id !== paperReviewingId));
        setPaperReviewingId(null);
        setPaperName('');
        setPaperDateOfBirth('');
        setPaperAddress('');
        setPaperPhone('');
        setPaperEmail('');
        setPaperNote('');
        toast.success('Member confirmed and account created.');
      }
    } catch {
      toast.error('Could not confirm paper intake.');
    } finally {
      setOnboardingLoading(false);
    }
  }, [paperAddress, paperDateOfBirth, paperEmail, paperName, paperNote, paperPhone, paperReviewingId, toast]);

  const submitAssistedOnboarding = useCallback(async () => {
    if (!onboardingName.trim() || !onboardingEmail.trim()) {
      toast.error('Name and email are required.');
      return;
    }
    setOnboardingLoading(true);
    setOnboardingResult(null);
    try {
      const res = await api.post<{ user: { id: number; name: string; email: string }; temp_password: string }>(
        '/v2/admin/caring-community/assisted-onboarding',
        {
          name: onboardingName.trim(),
          email: onboardingEmail.trim(),
          phone: onboardingPhone.trim() || undefined,
          note: onboardingNote.trim() || undefined,
        },
      );
      if (res.data) {
        setOnboardingResult(res.data);
        setOnboardingName('');
        setOnboardingEmail('');
        setOnboardingPhone('');
        setOnboardingNote('');
        toast.success('Member account created.');
      }
    } catch {
      toast.error('Could not create member account.');
    } finally {
      setOnboardingLoading(false);
    }
  }, [onboardingEmail, onboardingName, onboardingNote, onboardingPhone, toast]);

  const copyTempPassword = useCallback(() => {
    if (!onboardingResult) return;
    void navigator.clipboard.writeText(onboardingResult.temp_password);
    setOnboardingCopied(true);
    setTimeout(() => setOnboardingCopied(false), 2000);
  }, [onboardingResult]);

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <Spinner size="lg" label="Loading caring community workflow..." />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 pb-8">
      <PageHeader
        title="Caring Community Workflow"
        description="This is your daily operations dashboard. Use it to review pending hour approvals, monitor overdue items, manage coordinator assignments, generate member statements, and configure workflow policy. Pending items that have exceeded their SLA appear highlighted in amber."
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/volunteering/hours')}
              variant="flat"
              size="sm"
              startContent={<ClipboardCheck size={16} />}
            >
              Open hour review
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/reports/municipal-impact')}
              variant="flat"
              size="sm"
              startContent={<FileText size={16} />}
            >
              Open report pack
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={loadWorkflow}
            >
              Refresh
            </Button>
          </div>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <StatCard label="Pending reviews" value={(stats?.pending_count ?? 0).toLocaleString()} icon={ClipboardCheck} color="warning" />
        <StatCard label="Pending hours" value={formatHours(stats?.pending_hours ?? 0)} icon={Clock} color="primary" />
        <StatCard label="Approved (30d)" value={formatHours(stats?.approved_30d_hours ?? 0)} icon={CheckCircle2} color="success" />
        <StatCard label="Coordinators" value={(stats?.coordinator_count ?? 0).toLocaleString()} icon={Users} color="secondary" />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div className="space-y-6">
          <Card shadow="sm">
            <CardHeader className="flex items-start justify-between gap-4">
              <div>
                <h2 className="text-lg font-semibold">Pending Reviews</h2>
                <p className="mt-1 text-sm text-default-500">
                  Hours awaiting coordinator review and approval. Items marked <span className="font-medium text-warning-600">Needs review</span> have
                  exceeded the review SLA; items marked <span className="font-medium text-danger-600">Escalate now</span> have exceeded the escalation SLA.
                  Approve or decline each submission, or assign it to a specific coordinator.
                </p>
              </div>
              {(stats?.overdue_count ?? 0) > 0 && (
                <div className="flex flex-wrap gap-2">
                  {(stats?.escalated_count ?? 0) > 0 && (
                    <Chip color="danger" variant="flat">{`${stats?.escalated_count ?? 0} escalated`}</Chip>
                  )}
                  <Chip color="warning" variant="flat">{`${stats?.overdue_count ?? 0} overdue`}</Chip>
                </div>
              )}
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              {summary?.pending_reviews.length === 0 ? (
                <div className="rounded-lg bg-success/10 p-4 text-sm text-success-700">
                  Nothing pending. The review queue is clear.
                </div>
              ) : summary?.pending_reviews.map((review) => (
                <div key={review.id} className="rounded-lg border border-default-200 p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-default-900">{review.member_name}</p>
                      <p className="mt-1 text-sm text-default-500">
                        {review.organisation_name || review.opportunity_title || 'Unassigned'}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      {review.is_escalated && <Chip size="sm" color="danger" variant="flat">Escalate now</Chip>}
                      {!review.is_escalated && review.is_overdue && <Chip size="sm" color="warning" variant="flat">Needs review</Chip>}
                      <Chip size="sm" color="primary" variant="flat">{formatHours(review.hours)}</Chip>
                    </div>
                  </div>
                  <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-default-500">
                    <span>{`Logged ${review.date_logged}`}</span>
                    <span>{`Submitted ${review.created_at}`}</span>
                    <span>{`${review.age_days} day${review.age_days === 1 ? '' : 's'} old`}</span>
                    {review.assigned_name && <span>{`Assigned to ${review.assigned_name}`}</span>}
                    {review.escalated_at && <span>{`Escalated ${review.escalated_at}`}</span>}
                  </div>
                  <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
                    <Select
                      size="sm"
                      label="Assign coordinator"
                      selectedKeys={[review.assigned_to ? String(review.assigned_to) : 'unassigned']}
                      isDisabled={assigningReviewId === review.id || decidingReviewId === review.id}
                      onSelectionChange={(keys) => {
                        const selected = Array.from(keys)[0];
                        assignReview(review.id, selected && selected !== 'unassigned' ? Number(selected) : null);
                      }}
                      items={coordinatorOptions}
                    >
                      {(item) => <SelectItem key={item.id}>{item.label}</SelectItem>}
                    </Select>
                    <Button
                      size="sm"
                      variant="flat"
                      color={review.is_escalated ? 'danger' : 'warning'}
                      startContent={review.is_escalated ? <TriangleAlert size={16} /> : <UserPlus size={16} />}
                      isLoading={escalatingReviewId === review.id}
                      isDisabled={decidingReviewId === review.id}
                      onPress={() => escalateReview(review)}
                    >
                      {review.is_escalated ? 'Re-escalate' : 'Escalate'}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="success"
                      startContent={<CheckCircle2 size={16} />}
                      isLoading={decidingReviewId === review.id}
                      onPress={() => decideReview(review, 'approve')}
                    >
                      Approve
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      startContent={<XCircle size={16} />}
                      isDisabled={decidingReviewId === review.id}
                      onPress={() => decideReview(review, 'decline')}
                    >
                      Decline
                    </Button>
                  </div>
                </div>
              ))}
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">Support relationships</h2>
                <p className="mt-1 text-sm text-default-500">Ongoing supporter–recipient pairs and recurring check-ins.</p>
              </div>
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={16} />}
                isLoading={loadingRelationships}
                onPress={loadSupportRelationships}
              >
                Refresh
              </Button>
            </CardHeader>
            <Divider />
            <CardBody className="gap-4">
              <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <SignalRow label="Active" value={relationships?.stats.active_count ?? 0} />
                <SignalRow label="Paused" value={relationships?.stats.paused_count ?? 0} />
                <SignalRow label="Check-ins due" value={relationships?.stats.check_ins_due ?? 0} />
                <SignalRow label="Expected hours" value={relationships?.stats.expected_active_hours ?? 0} />
              </div>
              <div id="caring-support-relationship-form" className="rounded-lg border border-default-200 p-3">
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                  <MemberSearchPicker
                    value={relationshipSupporterId}
                    onValueChange={setRelationshipSupporterId}
                    selectedMember={relationshipSupporter}
                    onSelectedMemberChange={setRelationshipSupporter}
                    label="Supporter"
                    placeholder="Search for the supporter"
                    noResultsText="No members found"
                    clearText="Clear"
                    isRequired
                  />
                  <MemberSearchPicker
                    value={relationshipRecipientId}
                    onValueChange={setRelationshipRecipientId}
                    selectedMember={relationshipRecipient}
                    onSelectedMemberChange={setRelationshipRecipient}
                    label="Recipient"
                    placeholder="Search for the recipient"
                    noResultsText="No members found"
                    clearText="Clear"
                    isRequired
                  />
                  <Input label="Relationship title" value={relationshipTitle} onValueChange={setRelationshipTitle} />
                  <Select
                    label="Frequency"
                    selectedKeys={[relationshipFrequency]}
                    onSelectionChange={(keys) => setRelationshipFrequency((Array.from(keys)[0]?.toString() as SupportRelationship['frequency']) || 'weekly')}
                    items={frequencyOptions}
                  >
                    {(item) => <SelectItem key={item.id}>{item.label}</SelectItem>}
                  </Select>
                  <Input type="number" min={0.25} step={0.25} label="Expected hours per visit" value={relationshipExpectedHours} onValueChange={setRelationshipExpectedHours} />
                  <Input type="date" label="Start date" value={relationshipStartDate} onValueChange={setRelationshipStartDate} />
                </div>
                <div className="mt-3">
                  <Button color="primary" variant="flat" startContent={<Plus size={16} />} isLoading={savingRelationship} onPress={createSupportRelationship}>
                    Create relationship
                  </Button>
                </div>
              </div>
              {(relationships?.items.length ?? 0) === 0 ? (
                <div className="rounded-lg bg-default-100 p-4 text-sm text-default-500">
                  No support relationships yet. Create one above.
                </div>
              ) : relationships?.items.map((relationship) => (
                <div key={relationship.id} className="rounded-lg border border-default-200 p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-default-900">{relationship.title}</p>
                      <p className="mt-1 text-sm text-default-500">
                        {`${relationship.supporter.name} supporting ${relationship.recipient.name}`}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Chip size="sm" color={relationship.status === 'active' ? 'success' : 'warning'} variant="flat">
                        {supportRelationshipStatusLabels[relationship.status]}
                      </Chip>
                      <Chip size="sm" color="primary" variant="flat">{formatHours(relationship.expected_hours)}</Chip>
                    </div>
                  </div>
                  <div className="mt-3 flex flex-wrap gap-2 text-xs text-default-500">
                    <span>{frequencyLabels[relationship.frequency]}</span>
                    <span>{`Started ${relationship.start_date}`}</span>
                    {relationship.next_check_in_at && <span>{`Next check-in ${relationship.next_check_in_at}`}</span>}
                    {relationship.organization_name && <span>{relationship.organization_name}</span>}
                  </div>
                  <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      color={relationship.status === 'active' ? 'warning' : 'success'}
                      startContent={relationship.status === 'active' ? <Pause size={16} /> : <CheckCircle2 size={16} />}
                      isLoading={updatingRelationshipId === relationship.id}
                      onPress={() => updateSupportRelationshipStatus(relationship, relationship.status === 'active' ? 'paused' : 'active')}
                    >
                      {relationship.status === 'active' ? 'Pause' : 'Resume'}
                    </Button>
                  </div>
                  {relationship.status === 'active' && (
                    <div className="mt-4 rounded-lg bg-default-50 p-3">
                      <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_2fr_auto]">
                        <Input
                          type="date"
                          size="sm"
                          label="Date"
                          value={relationshipLogDate}
                          onValueChange={setRelationshipLogDate}
                        />
                        <Input
                          type="number"
                          size="sm"
                          min={0.25}
                          max={24}
                          step={0.25}
                          label="Hours"
                          placeholder={String(relationship.expected_hours)}
                          value={relationshipLogHours}
                          onValueChange={setRelationshipLogHours}
                        />
                        <Input
                          size="sm"
                          label="Note (optional)"
                          value={relationshipLogDescription}
                          onValueChange={setRelationshipLogDescription}
                        />
                        <Button
                          size="sm"
                          color="primary"
                          variant="flat"
                          className="self-end"
                          startContent={<ClipboardCheck size={16} />}
                          isLoading={loggingRelationshipId === relationship.id}
                          onPress={() => logSupportRelationshipHours(relationship)}
                        >
                          Log hours
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </CardBody>
          </Card>

          {/* Safeguarding Reports — K9 — admin English-only */}
          <Card shadow="sm">
            <CardHeader className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold flex items-center gap-2">
                  <TriangleAlert size={18} className="text-danger" />
                  Safeguarding Reports
                </h2>
                <p className="mt-1 text-sm text-default-500">
                  Member-raised concerns about other members, coordinators, or organisations.
                </p>
              </div>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="flat"
                  startContent={<RefreshCw size={16} />}
                  isLoading={loadingSafeguarding}
                  onPress={loadSafeguardingSummary}
                >
                  Refresh
                </Button>
                <Button
                  as={Link}
                  to="/admin/caring-community/safeguarding"
                  size="sm"
                  color="primary"
                  variant="flat"
                >
                  View all
                </Button>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="space-y-4">
              {loadingSafeguarding && !safeguardingSummary ? (
                <div className="flex justify-center py-6">
                  <Spinner size="sm" />
                </div>
              ) : !safeguardingSummary ? (
                <p className="text-sm text-default-500 py-4 text-center">No safeguarding data available yet.</p>
              ) : (
                <>
                  <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <div className="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3 text-center">
                      <p className="text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300">Critical</p>
                      <p className="text-2xl font-semibold">{safeguardingSummary.open_by_severity.critical}</p>
                    </div>
                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-center">
                      <p className="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">High</p>
                      <p className="text-2xl font-semibold">{safeguardingSummary.open_by_severity.high}</p>
                    </div>
                    <div className="rounded-lg border border-default-200 p-3 text-center">
                      <p className="text-xs uppercase tracking-wide text-default-600">Medium</p>
                      <p className="text-2xl font-semibold">{safeguardingSummary.open_by_severity.medium}</p>
                    </div>
                    <div className="rounded-lg border border-default-200 p-3 text-center">
                      <p className="text-xs uppercase tracking-wide text-default-600">Low</p>
                      <p className="text-2xl font-semibold">{safeguardingSummary.open_by_severity.low}</p>
                    </div>
                    <div className="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-center">
                      <p className="text-xs uppercase tracking-wide text-rose-700 dark:text-rose-300 flex items-center justify-center gap-1">
                        <TriangleAlert size={12} /> Overdue
                      </p>
                      <p className="text-2xl font-semibold">{safeguardingSummary.overdue}</p>
                    </div>
                  </div>

                  {safeguardingSummary.recent.length === 0 ? (
                    <p className="text-sm text-default-500 py-4 text-center">No reports yet.</p>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead className="text-left text-xs uppercase text-default-500">
                          <tr>
                            <th className="py-2 pr-3">Severity</th>
                            <th className="py-2 pr-3">Category</th>
                            <th className="py-2 pr-3">Subject</th>
                            <th className="py-2 pr-3">Status</th>
                            <th className="py-2 pr-3">Age</th>
                            <th className="py-2 pr-3 text-right">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          {safeguardingSummary.recent.slice(0, 10).map((r) => {
                            const severityColor: Record<typeof r.severity, 'danger' | 'warning' | 'default' | 'success'> = {
                              critical: 'danger',
                              high: 'warning',
                              medium: 'default',
                              low: 'success',
                            };
                            const statusColor: Record<typeof r.status, 'default' | 'primary' | 'warning' | 'success'> = {
                              submitted: 'primary',
                              triaged: 'primary',
                              investigating: 'warning',
                              resolved: 'success',
                              dismissed: 'default',
                            };
                            const ageHours = Math.max(
                              0,
                              Math.floor((Date.now() - new Date(r.created_at).getTime()) / (1000 * 60 * 60)),
                            );
                            const ageLabel = ageHours < 24 ? `${ageHours}h` : `${Math.floor(ageHours / 24)}d`;
                            return (
                              <tr key={r.id} className="border-t border-default-200">
                                <td className="py-2 pr-3">
                                  <Chip size="sm" color={severityColor[r.severity]} variant="flat">
                                    {r.severity}
                                  </Chip>
                                </td>
                                <td className="py-2 pr-3">{r.category}</td>
                                <td className="py-2 pr-3">
                                  {r.subject_user_name ?? (r.subject_organisation_id ? `Org #${r.subject_organisation_id}` : '—')}
                                </td>
                                <td className="py-2 pr-3">
                                  <Chip size="sm" color={statusColor[r.status]} variant="flat">
                                    {r.status}
                                  </Chip>
                                  {r.is_overdue && (
                                    <Chip size="sm" color="danger" variant="bordered" className="ml-1">
                                      Overdue
                                    </Chip>
                                  )}
                                </td>
                                <td className="py-2 pr-3">{ageLabel}</td>
                                <td className="py-2 pr-3 text-right">
                                  <Button
                                    as={Link}
                                    to={`/admin/caring-community/safeguarding`}
                                    size="sm"
                                    variant="flat"
                                  >
                                    Open
                                  </Button>
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  )}
                </>
              )}
            </CardBody>
          </Card>

          <PredictiveInsightsCard
            forecast={forecast}
            loading={loadingForecast}
            error={forecastError}
            onRefresh={loadForecast}
          />

          <Card shadow="sm">
            <CardHeader className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold flex items-center gap-2">
                  <Sparkles size={18} className="text-primary" />
                  Tandem Suggestions
                </h2>
                <p className="mt-1 text-sm text-default-500">
                  AI-suggested supporter–recipient pairs based on location, language, skills and availability.
                </p>
              </div>
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={16} />}
                isLoading={loadingTandems}
                onPress={loadTandemSuggestions}
              >
                Refresh
              </Button>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              {loadingTandems && tandemSuggestions.length === 0 ? (
                <div className="flex items-center justify-center py-8">
                  <Spinner size="md" />
                </div>
              ) : tandemError ? (
                <div className="rounded-lg bg-danger-50 p-4 text-sm text-danger-700">
                  {tandemError}
                </div>
              ) : tandemSuggestions.length === 0 ? (
                <div className="rounded-lg bg-default-100 p-4 text-sm text-default-500">
                  No suggestions right now. Add more members or adjust availability.
                </div>
              ) : (
                tandemSuggestions.map((suggestion) => {
                  const key = `${suggestion.supporter.id}:${suggestion.recipient.id}`;
                  const scoreColor: 'success' | 'warning' | 'default' =
                    suggestion.score >= 0.7 ? 'success' : suggestion.score >= 0.5 ? 'warning' : 'default';
                  const signals = suggestion.signals;
                  const chips: { key: string; label: string }[] = [];
                  if (typeof signals.distance_km === 'number') {
                    chips.push({ key: 'distance', label: `${signals.distance_km.toFixed(1)} km` });
                  }
                  if (typeof signals.language_overlap === 'number' && signals.language_overlap > 0.4) {
                    chips.push({ key: 'language', label: 'Same language' });
                  }
                  if (typeof signals.skill_complement === 'number' && signals.skill_complement > 0.4) {
                    chips.push({ key: 'skills', label: 'Complementary skills' });
                  }
                  if (typeof signals.availability_overlap === 'number' && signals.availability_overlap > 0.4) {
                    chips.push({ key: 'availability', label: 'Availability overlap' });
                  }
                  if (typeof signals.interest_overlap === 'number' && signals.interest_overlap > 0.4) {
                    chips.push({ key: 'interests', label: 'Shared interests' });
                  }
                  return (
                    <div key={key} className="rounded-lg border border-default-200 p-4">
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                          <div className="flex items-center gap-2">
                            <div className="h-9 w-9 overflow-hidden rounded-full bg-default-100 flex items-center justify-center text-xs font-semibold">
                              {suggestion.supporter.avatar_url ? (
                                <img src={suggestion.supporter.avatar_url} alt="" className="h-full w-full object-cover" />
                              ) : (
                                suggestion.supporter.name.charAt(0).toUpperCase()
                              )}
                            </div>
                            <div className="text-sm">
                              <div className="font-semibold text-default-900">{suggestion.supporter.name}</div>
                              <div className="text-xs text-default-500">Supporter</div>
                            </div>
                          </div>
                          <HeartHandshake size={20} className="text-primary" />
                          <div className="flex items-center gap-2">
                            <div className="h-9 w-9 overflow-hidden rounded-full bg-default-100 flex items-center justify-center text-xs font-semibold">
                              {suggestion.recipient.avatar_url ? (
                                <img src={suggestion.recipient.avatar_url} alt="" className="h-full w-full object-cover" />
                              ) : (
                                suggestion.recipient.name.charAt(0).toUpperCase()
                              )}
                            </div>
                            <div className="text-sm">
                              <div className="font-semibold text-default-900">{suggestion.recipient.name}</div>
                              <div className="text-xs text-default-500">Recipient</div>
                            </div>
                          </div>
                        </div>
                        <Chip size="sm" color={scoreColor} variant="flat">
                          Score {Math.round(suggestion.score * 100)}%
                        </Chip>
                      </div>
                      {chips.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                          {chips.map((chip) => (
                            <Chip key={chip.key} size="sm" variant="flat" color="primary">
                              {chip.label}
                            </Chip>
                          ))}
                        </div>
                      )}
                      {suggestion.reason && (
                        <p className="mt-2 text-xs text-default-500">{suggestion.reason}</p>
                      )}
                      <div className="mt-3 flex flex-wrap justify-end gap-2">
                        <Button
                          size="sm"
                          variant="light"
                          color="default"
                          startContent={<XCircle size={16} />}
                          isLoading={dismissingTandemKey === key}
                          onPress={() => dismissTandemSuggestion(suggestion)}
                        >
                          Dismiss
                        </Button>
                        <Button
                          size="sm"
                          color="primary"
                          variant="flat"
                          startContent={<Heart size={16} />}
                          onPress={() => createTandemFromSuggestion(suggestion)}
                        >
                          Create Tandem
                        </Button>
                      </div>
                    </div>
                  );
                })
              )}
            </CardBody>
          </Card>
        </div>

        <div className="space-y-6">
          {summary?.policy && (
            <Card shadow="sm">
              <CardHeader className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold">Workflow policy</h2>
                  <p className="mt-1 text-sm text-default-500">
                    Configure SLAs, approval rules, and reporting defaults. Changes take effect immediately on save
                    and apply to all new hour submissions from that point forward.
                  </p>
                </div>
                <Button
                  color="primary"
                  size="sm"
                  startContent={<Save size={16} />}
                  isLoading={savingPolicy}
                  onPress={savePolicy}
                >
                  Save policy
                </Button>
              </CardHeader>
              <Divider />
              <CardBody className="gap-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <Input
                    type="number"
                    min={1}
                    max={30}
                    label="Review SLA (days)"
                    description="Hours not reviewed within this many days will be flagged as overdue in the queue."
                    value={String(summary.policy.review_sla_days)}
                    onValueChange={(value) => updatePolicyField('review_sla_days', Number(value || 1))}
                  />
                  <Input
                    type="number"
                    min={1}
                    max={60}
                    label="Escalation SLA (days)"
                    description="Overdue items not actioned within this many additional days will be escalated automatically."
                    value={String(summary.policy.escalation_sla_days)}
                    onValueChange={(value) => updatePolicyField('escalation_sla_days', Number(value || 1))}
                  />
                  <Input
                    type="number"
                    min={1}
                    max={28}
                    label="Monthly statement day"
                    description="Day of the month on which member statements are generated (e.g. 1 = 1st of each month)."
                    value={String(summary.policy.monthly_statement_day)}
                    onValueChange={(value) => updatePolicyField('monthly_statement_day', Number(value || 1))}
                  />
                  <Input
                    type="number"
                    min={0}
                    max={500}
                    label="Default hour value (CHF)"
                    description="Used to calculate estimated social value on statements and municipal reports."
                    value={String(summary.policy.default_hour_value_chf)}
                    onValueChange={(value) => updatePolicyField('default_hour_value_chf', Number(value || 0))}
                  />
                </div>
                <Select
                  label="Default report period"
                  selectedKeys={[summary.policy.municipal_report_default_period]}
                  onSelectionChange={(keys) => updatePolicyField('municipal_report_default_period', Array.from(keys)[0]?.toString() ?? 'last_90_days')}
                >
                  {reportPeriods.map((period) => (
                    <SelectItem key={period}>{reportPeriodLabels[period]}</SelectItem>
                  ))}
                </Select>
                <div className="grid grid-cols-1 gap-3">
                  <PolicySwitch
                    label="Approval required"
                    description="Require coordinator approval before hours are added to wallets."
                    value={summary.policy.approval_required}
                    onChange={(value) => updatePolicyField('approval_required', value)}
                  />
                  <PolicySwitch
                    label="Auto-approve trusted reviewers"
                    description="Skip the queue for hours logged by trusted reviewers."
                    value={summary.policy.auto_approve_trusted_reviewers}
                    onChange={(value) => updatePolicyField('auto_approve_trusted_reviewers', value)}
                  />
                  <PolicySwitch
                    label="Allow member self-log"
                    description="Let members log their own support hours without coordinator entry."
                    value={summary.policy.allow_member_self_log}
                    onChange={(value) => updatePolicyField('allow_member_self_log', value)}
                  />
                  <PolicySwitch
                    label="Require organisation for partner hours"
                    description="Hours given on behalf of partners must be linked to an organisation."
                    value={summary.policy.require_organisation_for_partner_hours}
                    onChange={(value) => updatePolicyField('require_organisation_for_partner_hours', value)}
                  />
                  <PolicySwitch
                    label="Include social value estimate"
                    description="Show estimated social value (CHF) on statements and reports."
                    value={summary.policy.include_social_value_estimate}
                    onChange={(value) => updatePolicyField('include_social_value_estimate', value)}
                  />
                </div>
              </CardBody>
            </Card>
          )}

          <Card shadow="sm">
            <CardHeader>
              <div>
                <h2 className="text-lg font-semibold">Member Statements</h2>
                <p className="mt-1 text-sm text-default-500">
                  Generate a hours-and-impact statement for a single member. Use this to share a formal record of
                  their support hours, wallet balance, and estimated social value — useful for grant applications,
                  reviews, or member queries. Enter a member ID and an optional date range, then Preview or Export CSV.
                </p>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-4">
              <Input
                type="number"
                min={1}
                label="Member ID"
                value={statementMemberId}
                onValueChange={setStatementMemberId}
              />
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Input
                  type="date"
                  label="Start date"
                  value={statementStartDate}
                  onValueChange={setStatementStartDate}
                />
                <Input
                  type="date"
                  label="End date"
                  value={statementEndDate}
                  onValueChange={setStatementEndDate}
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  color="primary"
                  variant="flat"
                  startContent={<Search size={16} />}
                  isLoading={loadingStatement}
                  onPress={loadMemberStatement}
                >
                  Preview
                </Button>
                <Button
                  variant="flat"
                  startContent={<Download size={16} />}
                  isLoading={loadingStatement}
                  onPress={exportMemberStatement}
                >
                  Export CSV
                </Button>
              </div>
              {memberStatement ? (
                <div className="rounded-lg border border-default-200 p-3">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="text-sm font-semibold text-default-900">{memberStatement.user.name}</p>
                      <p className="text-xs text-default-500">{memberStatement.user.email}</p>
                    </div>
                    <Chip size="sm" color="success" variant="flat">
                      {formatHours(memberStatement.summary.current_balance)}
                    </Chip>
                  </div>
                  <div className="mt-3 grid grid-cols-2 gap-2">
                    <SignalRow label="Approved support hours" value={memberStatement.summary.approved_support_hours} />
                    <SignalRow label="Pending support hours" value={memberStatement.summary.pending_support_hours} />
                    <SignalRow label="Wallet hours earned" value={memberStatement.summary.wallet_hours_earned} />
                    <SignalRow label="Wallet hours spent" value={memberStatement.summary.wallet_hours_spent} />
                  </div>
                  <div className="mt-3 rounded-lg bg-default-100 px-3 py-2 text-sm text-default-700">
                    {formatChf(memberStatement.summary.estimated_social_value_chf)}
                  </div>
                  {memberStatement.support_hours_by_organisation.length > 0 && (
                    <div className="mt-3 space-y-2">
                      {memberStatement.support_hours_by_organisation.slice(0, 3).map((organisation) => (
                        <div key={organisation.organisation_name} className="flex items-center justify-between gap-3 text-xs">
                          <span className="truncate text-default-600">{organisation.organisation_name}</span>
                          <span className="font-semibold text-default-900">{formatHours(organisation.approved_hours)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                <div className="rounded-lg bg-default-100 p-3 text-sm text-default-500">
                  Enter a member ID and press Preview to view their statement.
                </div>
              )}
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader>
              <div>
                <h2 className="text-lg font-semibold">Coordinator signals</h2>
                <p className="mt-1 text-sm text-default-500">
                  A real-time snapshot of activity that may need coordinator attention — open requests, available
                  offers, trusted organisations, and recent declines.
                </p>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              <SignalRow label="Active support requests" value={signals?.active_requests ?? 0} />
              <SignalRow label="Active support offers" value={signals?.active_offers ?? 0} />
              <SignalRow label="Trusted organisations" value={signals?.trusted_organisations ?? 0} />
              <SignalRow label="Declined (30d)" value={stats?.declined_30d_count ?? 0} />
            </CardBody>
          </Card>

          {/* Informal Favours (AG11) */}
          <Card shadow="sm">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Heart size={18} className="text-rose-500" />
                <div>
                  <h2 className="text-lg font-semibold">Informal Favours</h2>
                  <p className="mt-0.5 text-sm text-default-500">
                    {loadingFavours ? 'Loading...' : `${favoursData?.count ?? 0} total recorded`}
                  </p>
                </div>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-2">
              {loadingFavours && (
                <p className="text-sm text-default-500">Loading favours...</p>
              )}
              {!loadingFavours && !favoursData?.items?.length && (
                <p className="text-sm text-default-500">No favours recorded yet.</p>
              )}
              {!loadingFavours && (favoursData?.items ?? []).slice(0, 5).map((f) => (
                <div key={f.id} className="rounded-lg border border-default-200 p-3">
                  <div className="flex items-center justify-between gap-2 text-xs text-default-500">
                    <span>{f.is_anonymous ? 'Anonymous' : (f.offerer_name ?? 'Unknown')}</span>
                    <span>{f.favour_date}</span>
                  </div>
                  <p className="mt-1 text-sm text-default-800 line-clamp-2">{f.description}</p>
                  {f.category && (
                    <span className="mt-1 inline-block rounded bg-default-100 px-1.5 py-0.5 text-xs text-default-600">
                      {f.category}
                    </span>
                  )}
                </div>
              ))}
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">Role presets</h2>
                <p className="mt-1 text-sm text-default-500">
                  Pre-configured permission sets for each caring community role. Install the full role pack to
                  quickly set up your coordinators with the correct access. {roleCountLabel}.
                </p>
              </div>
              <Button
                color="primary"
                size="sm"
                variant="flat"
                isLoading={installingRoles}
                onPress={installRolePack}
              >
                {installingRoles ? 'Installing...' : 'Install role pack'}
              </Button>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              {rolePresets.map((role) => {
                const Icon = role.icon;
                const status = roleStatusByKey.get(role.key);
                return (
                  <div key={role.key} className="flex items-start gap-3 rounded-lg border border-default-200 p-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Icon size={18} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-semibold text-default-900">{rolePresetContent[role.key].title}</p>
                        <Chip size="sm" color={status?.installed ? 'success' : 'default'} variant="flat">
                          {status?.installed ? 'Installed' : 'Not installed'}
                        </Chip>
                      </div>
                      <p className="mt-1 text-xs text-default-500">{rolePresetContent[role.key].description}</p>
                      {status && (
                        <p className="mt-2 text-xs text-default-400">
                          {`${status.installed_permissions} of ${status.permission_count} permissions granted`}
                        </p>
                      )}
                    </div>
                  </div>
                );
              })}
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Assisted Onboarding card */}
      <Card className="mt-6" shadow="sm">
        <CardHeader>
          <div>
            <h2 className="text-lg font-semibold">Assisted onboarding</h2>
            <p className="mt-1 text-sm text-default-500">
              Create an account on behalf of a member who can&rsquo;t self-register, then share their temporary password.
            </p>
          </div>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="rounded-lg border border-default-200 bg-default-50 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-default-900">
                  Paper onboarding (OCR)
                </h3>
                <p className="mt-1 text-xs text-default-500">
                  Upload a scanned form or photo. We&rsquo;ll extract the fields and let you review before creating the account.
                </p>
              </div>
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={14} />}
                isLoading={paperLoading}
                onPress={loadPaperIntakes}
              >
                Refresh
              </Button>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
              <Input
                type="file"
                label="Scanned form or photo"
                accept="application/pdf,image/jpeg,image/png,image/webp"
                onChange={handlePaperFileChange}
              />
              <Input
                label="Full name"
                placeholder="As written on the form"
                value={paperName}
                onValueChange={setPaperName}
              />
              <Input
                type="date"
                label="Date of birth"
                value={paperDateOfBirth}
                onValueChange={setPaperDateOfBirth}
              />
              <Input
                type="email"
                label="Email"
                placeholder="member@example.com"
                value={paperEmail}
                onValueChange={setPaperEmail}
              />
              <Input
                label="Phone"
                placeholder="+1 555 123 4567"
                value={paperPhone}
                onValueChange={setPaperPhone}
              />
              <Input
                label="Address"
                placeholder="Street, city, postcode"
                value={paperAddress}
                onValueChange={setPaperAddress}
              />
            </div>

            <div className="mt-3 flex flex-wrap gap-2">
              <Button
                color="primary"
                variant="flat"
                startContent={<FileText size={16} />}
                isLoading={paperUploading}
                onPress={uploadPaperOnboarding}
              >
                Upload &amp; extract
              </Button>
              {paperReviewingId && (
                <Button
                  color="success"
                  variant="flat"
                  startContent={<CheckCircle2 size={16} />}
                  isLoading={onboardingLoading}
                  onPress={confirmPaperOnboarding}
                >
                  Confirm &amp; create account
                </Button>
              )}
            </div>

            {paperReviewingId && (
              <Textarea
                className="mt-3"
                label="Coordinator note"
                placeholder="Optional context for this intake"
                value={paperNote}
                onValueChange={setPaperNote}
              />
            )}

            {paperIntakes.length > 0 && (
              <div className="mt-4 space-y-2">
                {paperIntakes.map((intake) => {
                  const fields = intake.corrected_fields ?? intake.extracted_fields ?? {};
                  return (
                    <div key={intake.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-background px-3 py-2">
                      <div>
                        <p className="text-sm font-medium text-default-900">
                          {fields.name || intake.original_filename}
                        </p>
                        <p className="text-xs text-default-500">
                          {fields.email || 'No email'} · {intake.original_filename}
                        </p>
                      </div>
                      <Button size="sm" variant="flat" onPress={() => startPaperReview(intake)}>
                        Review
                      </Button>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            <Input
              label="Full name"
              placeholder="Member's full name"
              value={onboardingName}
              onValueChange={setOnboardingName}
              isRequired
            />
            <Input
              type="email"
              label="Email"
              placeholder="member@example.com"
              value={onboardingEmail}
              onValueChange={setOnboardingEmail}
              isRequired
            />
            <Input
              label="Phone (optional)"
              placeholder="+1 555 123 4567"
              value={onboardingPhone}
              onValueChange={setOnboardingPhone}
            />
            <Input
              label="Coordinator note (optional)"
              placeholder="Anything the coordinator should remember"
              value={onboardingNote}
              onValueChange={setOnboardingNote}
            />
          </div>
          <Button
            color="primary"
            variant="flat"
            startContent={<UserPlus size={16} />}
            isLoading={onboardingLoading}
            onPress={submitAssistedOnboarding}
          >
            Create member account
          </Button>

          {onboardingResult && (
            <div className="rounded-lg border border-success-200 bg-success-50 p-4">
              <p className="text-sm font-semibold text-success-700">
                {`Account created for ${onboardingResult.user.name} (${onboardingResult.user.email}).`}
              </p>
              <p className="mt-2 text-xs text-default-500">
                Share this temporary password with the member. They will be asked to change it on first login.
              </p>
              <div className="mt-3 flex items-center gap-2">
                <code className="flex-1 rounded bg-default-100 px-3 py-2 text-sm font-mono text-default-900">
                  {onboardingResult.temp_password}
                </code>
                <Button
                  size="sm"
                  variant="flat"
                  startContent={<Copy size={14} />}
                  onPress={copyTempPassword}
                >
                  {onboardingCopied ? 'Copied!' : 'Copy'}
                </Button>
              </div>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Invite Codes card */}
      <Card className="mt-6" shadow="sm">
        <CardHeader className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold">Invite Codes</h2>
            <p className="mt-1 text-sm text-default-500">
              Generate a printable code that a new member can use to join without needing an email invitation.
            </p>
          </div>
          <Button
            size="sm"
            variant="flat"
            startContent={<RefreshCw size={16} />}
            isLoading={loadingInviteCodes}
            onPress={loadInviteCodes}
          >
            Refresh
          </Button>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          {/* Generate form */}
          <div className="flex flex-wrap items-end gap-3">
            <Input
              className="flex-1"
              label="Label (optional)"
              placeholder="e.g. For Mary's neighbour"
              value={inviteLabel}
              onValueChange={setInviteLabel}
            />
            <Button
              color="primary"
              variant="flat"
              startContent={<Plus size={16} />}
              isLoading={generatingCode}
              onPress={generateInviteCode}
            >
              Generate Code
            </Button>
          </div>

          {/* Result box */}
          {generatedCode && (
            <div className="rounded-lg border border-success-200 bg-success-50 p-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-xs text-default-500">New invite code</p>
                  <p className="mt-1 font-mono text-3xl font-bold tracking-widest text-success-700">
                    {generatedCode.code}
                  </p>
                  {generatedCode.label && (
                    <p className="mt-1 text-xs text-default-600">{generatedCode.label}</p>
                  )}
                  <p className="mt-1 text-xs text-default-400">
                    Expires: {new Date(generatedCode.expires_at).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Copy size={14} />}
                    onPress={() => copyInviteCode(generatedCode.invite_url)}
                  >
                    {codeCopied ? 'Copied!' : 'Copy URL'}
                  </Button>
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Printer size={14} />}
                    onPress={() => printInviteCard(generatedCode)}
                  >
                    Print Card
                  </Button>
                  <Button
                    as="a"
                    href={generatedCode.invite_url}
                    target="_blank"
                    rel="noreferrer"
                    size="sm"
                    variant="flat"
                    startContent={<ExternalLink size={14} />}
                  >
                    Open
                  </Button>
                </div>
              </div>

              {/* Print card — hidden on screen, visible when printing */}
              {printCodeId === generatedCode.id && (
                <div className="hidden print:block">
                  <PrintableInviteCard
                    code={generatedCode.code}
                    label={generatedCode.label}
                    inviteUrl={generatedCode.invite_url}
                    expiresAt={generatedCode.expires_at}
                  />
                </div>
              )}
            </div>
          )}

          {/* Recent codes table */}
          {inviteCodes.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-default-200 text-left text-xs text-default-500">
                    <th className="pb-2 pr-4 font-medium">Code</th>
                    <th className="pb-2 pr-4 font-medium">Label</th>
                    <th className="pb-2 pr-4 font-medium">Expires</th>
                    <th className="pb-2 pr-4 font-medium">Status</th>
                    <th className="pb-2 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-default-100">
                  {inviteCodes.map((ic) => (
                    <tr key={ic.id} className="text-xs">
                      <td className="py-2 pr-4 font-mono font-semibold tracking-wider text-default-900">{ic.code}</td>
                      <td className="py-2 pr-4 text-default-600">{ic.label ?? '—'}</td>
                      <td className="py-2 pr-4 text-default-500">{new Date(ic.expires_at).toLocaleDateString()}</td>
                      <td className="py-2 pr-4">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={ic.status === 'active' ? 'success' : ic.status === 'used' ? 'default' : 'warning'}
                        >
                          {ic.status === 'active' ? 'Active' : ic.status === 'used' ? `Used${ic.used_by ? ` by ${ic.used_by}` : ''}` : 'Expired'}
                        </Chip>
                      </td>
                      <td className="py-2">
                        <div className="flex gap-1">
                          <Button
                            size="sm"
                            isIconOnly
                            variant="light"
                            title="Copy URL"
                            onPress={() => copyInviteCode(ic.invite_url)}
                          >
                            <Copy size={14} />
                          </Button>
                          {ic.status === 'active' && (
                            <Button
                              size="sm"
                              isIconOnly
                              variant="light"
                              title="Print card"
                              onPress={() => printInviteCard(ic)}
                            >
                              <Printer size={14} />
                            </Button>
                          )}
                        </div>
                        {/* Print card for this row */}
                        {printCodeId === ic.id && (
                          <div className="hidden print:block">
                            <PrintableInviteCard
                              code={ic.code}
                              label={ic.label}
                              inviteUrl={ic.invite_url}
                              expiresAt={ic.expires_at}
                            />
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {inviteCodes.length === 0 && !loadingInviteCodes && (
            <div className="rounded-lg bg-default-100 p-3 text-sm text-default-500">
              No invite codes yet. Generate one above to share with a prospective member.
            </div>
          )}
        </CardBody>
      </Card>

      <Card className="mt-6" shadow="sm">
        <CardHeader>
          <div>
            <h2 className="text-lg font-semibold">Workflow stages</h2>
            <p className="mt-1 text-sm text-default-500">The five-step caring community pipeline at a glance.</p>
          </div>
        </CardHeader>
        <Divider />
        <CardBody className="grid grid-cols-1 gap-3 md:grid-cols-5">
          {workflowStages.map((stage, index) => {
            const Icon = stage.icon;
            return (
              <div key={stage.key} className="rounded-lg border border-default-200 p-3">
                <div className="mb-3 flex items-center justify-between">
                  <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary/10 text-secondary">
                    <Icon size={18} />
                  </div>
                  <Chip size="sm" variant="flat">{index + 1}</Chip>
                </div>
                <p className="text-sm font-semibold text-default-900">{stageContent[stage.key].title}</p>
                <p className="mt-1 text-xs text-default-500">{stageContent[stage.key].description}</p>
              </div>
            );
          })}
        </CardBody>
      </Card>
    </div>
  );
}

function SignalRow({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-lg bg-default-100 px-3 py-2">
      <span className="text-sm text-default-600">{label}</span>
      <span className="text-sm font-semibold text-default-900">{value.toLocaleString()}</span>
    </div>
  );
}

function PolicySwitch({ label, description, value, onChange }: { label: string; description: string; value: boolean; onChange: (value: boolean) => void }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-lg border border-default-200 p-3">
      <div>
        <p className="text-sm font-semibold text-default-900">{label}</p>
        <p className="mt-1 text-xs text-default-500">{description}</p>
      </div>
      <Switch isSelected={value} onValueChange={onChange} aria-label={label} />
    </div>
  );
}

function PrintableInviteCard({
  code,
  label,
  inviteUrl,
  expiresAt,
}: {
  code: string;
  label: string | null;
  inviteUrl: string;
  expiresAt: string;
}) {
  return (
    <div
      style={{
        fontFamily: 'Georgia, serif',
        border: '3px solid #333',
        borderRadius: '12px',
        padding: '32px',
        maxWidth: '400px',
        margin: '0 auto',
        textAlign: 'center',
      }}
    >
      <p style={{ fontSize: '13px', color: '#666', marginBottom: '4px', letterSpacing: '0.05em', textTransform: 'uppercase' }}>
        Caring Community
      </p>
      <p style={{ fontSize: '13px', color: '#888', marginBottom: '20px' }}>
        Your Invitation Code
      </p>
      <div
        style={{
          border: '2px solid #333',
          borderRadius: '8px',
          padding: '16px 24px',
          display: 'inline-block',
          marginBottom: '20px',
          background: '#f9f9f9',
        }}
      >
        <span style={{ fontSize: '40px', fontFamily: 'monospace', fontWeight: 'bold', letterSpacing: '0.25em', color: '#111' }}>
          {code}
        </span>
      </div>
      {label && (
        <p style={{ fontSize: '14px', color: '#555', marginBottom: '12px', fontStyle: 'italic' }}>{label}</p>
      )}
      <p style={{ fontSize: '12px', color: '#555', marginBottom: '8px' }}>
        Visit the link below or ask your coordinator to help you get started.
      </p>
      <p style={{ fontSize: '11px', color: '#333', wordBreak: 'break-all', fontFamily: 'monospace' }}>{inviteUrl}</p>
      <p style={{ fontSize: '11px', color: '#999', marginTop: '12px' }}>
        Valid until {new Date(expiresAt).toLocaleDateString()}
      </p>
    </div>
  );
}
