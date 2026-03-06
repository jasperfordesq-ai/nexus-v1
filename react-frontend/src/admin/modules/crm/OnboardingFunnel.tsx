// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Onboarding Funnel Visualization
 * Shows member progression from signup to active participation.
 * Data source: GET /api/v2/admin/crm/funnel
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { Filter, TrendingDown, Users, ArrowDown } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import { PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface FunnelStage {
  name: string;
  count: number;
  color: string;
}

interface FunnelData {
  stages: FunnelStage[];
  monthly_registrations: Array<{ month: string; count: number }>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function getConversionColor(rate: number): string {
  if (rate > 50) return 'text-success';
  if (rate >= 25) return 'text-warning';
  return 'text-danger';
}

function getConversionBg(rate: number): string {
  if (rate > 50) return 'bg-success/10';
  if (rate >= 25) return 'bg-warning/10';
  return 'bg-danger/10';
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function OnboardingFunnel() {
  usePageTitle('Admin - Onboarding Funnel');
  const toast = useToast();

  const [data, setData] = useState<FunnelData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminCrm.getFunnel();
      setData(res.data as FunnelData);
    } catch {
      setError('Failed to load onboarding funnel data.');
      toast.error('Failed to load onboarding funnel data');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Spinner size="lg" label="Loading funnel data..." />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="max-w-6xl mx-auto space-y-6">
        <PageHeader
          title="Onboarding Funnel"
          description="Track member progression from signup to active participation"
        />
        <Card>
          <CardBody>
            <p className="text-danger">{error || 'No data available.'}</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  const { stages, monthly_registrations } = data;
  const maxCount = stages.length > 0 ? stages[0].count : 1;

  // Build conversion rates between consecutive stages
  const conversions = stages.slice(1).map((stage, i) => {
    const prev = stages[i];
    const rate = prev.count > 0 ? (stage.count / prev.count) * 100 : 0;
    return {
      from: prev.name,
      to: stage.name,
      rate: Math.round(rate * 10) / 10,
    };
  });

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Onboarding Funnel"
        description="Track member progression from signup to active participation"
      />

      {/* ── Hero Funnel Visualization ──────────────────────────────────── */}
      <Card className="shadow-lg">
        <CardHeader className="flex items-center gap-3 pb-0">
          <div className="p-2 rounded-lg bg-primary/10">
            <Filter className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h3 className="text-lg font-semibold">Member Funnel</h3>
            <p className="text-sm text-default-500">
              {stages.length > 0
                ? `${stages[0].count.toLocaleString()} registered members`
                : 'No stages available'}
            </p>
          </div>
        </CardHeader>
        <CardBody className="pt-6">
          <div className="flex flex-col items-center gap-1">
            {stages.map((stage, index) => {
              const widthPercent =
                maxCount > 0 ? Math.max((stage.count / maxCount) * 100, 12) : 12;
              const percentage =
                maxCount > 0
                  ? Math.round((stage.count / maxCount) * 1000) / 10
                  : 0;
              const dropOff =
                index > 0 && stages[index - 1].count > 0
                  ? stages[index - 1].count - stage.count
                  : 0;

              return (
                <div key={stage.name} className="w-full flex flex-col items-center">
                  {/* Drop-off indicator between stages */}
                  {index > 0 && (
                    <div className="flex items-center gap-2 py-1 text-xs text-default-400">
                      <ArrowDown className="w-3 h-3" />
                      <span className="flex items-center gap-1">
                        <TrendingDown className="w-3 h-3 text-danger" />
                        {dropOff.toLocaleString()} dropped off
                      </span>
                      <ArrowDown className="w-3 h-3" />
                    </div>
                  )}

                  {/* Funnel bar */}
                  <div
                    className="relative rounded-lg transition-all duration-500 ease-out cursor-default group"
                    style={{
                      width: `${widthPercent}%`,
                      minHeight: '56px',
                      backgroundColor: stage.color,
                    }}
                  >
                    {/* Glass overlay on hover */}
                    <div className="absolute inset-0 rounded-lg bg-white/0 group-hover:bg-white/10 transition-colors" />

                    {/* Content */}
                    <div className="relative flex items-center justify-between px-4 py-3 text-white">
                      <span className="font-semibold text-sm truncate">
                        {stage.name}
                      </span>
                      <div className="flex items-center gap-3 shrink-0">
                        <span className="text-lg font-bold tabular-nums">
                          {stage.count.toLocaleString()}
                        </span>
                        <span className="text-xs opacity-80 bg-black/20 px-2 py-0.5 rounded-full">
                          {percentage}%
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Overall conversion */}
          {stages.length >= 2 && (
            <div className="mt-6 pt-4 border-t border-divider text-center">
              <p className="text-sm text-default-500">
                Overall conversion (
                {stages[0].name} to {stages[stages.length - 1].name})
              </p>
              <p className="text-2xl font-bold mt-1">
                {stages[0].count > 0
                  ? (
                      (stages[stages.length - 1].count / stages[0].count) *
                      100
                    ).toFixed(1)
                  : '0'}
                %
              </p>
            </div>
          )}
        </CardBody>
      </Card>

      {/* ── Bottom Row: Conversion Rates + Monthly Trend ──────────────── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Conversion Rates */}
        <Card>
          <CardHeader className="flex items-center gap-3 pb-0">
            <div className="p-2 rounded-lg bg-warning/10">
              <TrendingDown className="w-5 h-5 text-warning" />
            </div>
            <div>
              <h3 className="text-lg font-semibold">Stage-to-Stage Conversion</h3>
              <p className="text-sm text-default-500">
                Drop-off rates between each onboarding step
              </p>
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              {conversions.map((conv) => (
                <div
                  key={`${conv.from}-${conv.to}`}
                  className={`flex items-center justify-between p-3 rounded-lg ${getConversionBg(conv.rate)}`}
                >
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">
                      {conv.from}
                    </p>
                    <div className="flex items-center gap-1 text-xs text-default-400 mt-0.5">
                      <ArrowDown className="w-3 h-3" />
                      <span>{conv.to}</span>
                    </div>
                  </div>
                  <span
                    className={`text-xl font-bold tabular-nums ${getConversionColor(conv.rate)}`}
                  >
                    {conv.rate}%
                  </span>
                </div>
              ))}

              {conversions.length === 0 && (
                <p className="text-sm text-default-400 text-center py-4">
                  Not enough stages to compute conversion rates.
                </p>
              )}
            </div>
          </CardBody>
        </Card>

        {/* Monthly Registration Trend */}
        <Card>
          <CardHeader className="flex items-center gap-3 pb-0">
            <div className="p-2 rounded-lg bg-primary/10">
              <Users className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h3 className="text-lg font-semibold">Monthly Registrations</h3>
              <p className="text-sm text-default-500">
                New member signups over the last 6 months
              </p>
            </div>
          </CardHeader>
          <CardBody>
            {monthly_registrations.length > 0 ? (
              <ResponsiveContainer width="100%" height={280}>
                <AreaChart
                  data={monthly_registrations}
                  margin={{ top: 10, right: 10, left: 0, bottom: 0 }}
                >
                  <defs>
                    <linearGradient id="regGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="hsl(var(--heroui-primary))" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="hsl(var(--heroui-primary))" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                  <XAxis
                    dataKey="month"
                    tick={{ fontSize: 12 }}
                    tickLine={false}
                    axisLine={false}
                  />
                  <YAxis
                    tick={{ fontSize: 12 }}
                    tickLine={false}
                    axisLine={false}
                    allowDecimals={false}
                  />
                  <Tooltip
                    contentStyle={{
                      borderRadius: '8px',
                      border: '1px solid hsl(var(--heroui-divider))',
                      backgroundColor: 'hsl(var(--heroui-content1))',
                      color: 'hsl(var(--heroui-foreground))',
                    }}
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    formatter={(value: any) => [
                      Number(value).toLocaleString(),
                      'Registrations',
                    ]}
                  />
                  <Area
                    type="monotone"
                    dataKey="count"
                    stroke="hsl(var(--heroui-primary))"
                    strokeWidth={2}
                    fill="url(#regGradient)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            ) : (
              <p className="text-sm text-default-400 text-center py-10">
                No registration data available.
              </p>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
