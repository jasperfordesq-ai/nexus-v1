// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Onboarding Funnel Visualization
 * Shows member progression from signup to active participation.
 * Data source: GET /api/v2/admin/crm/funnel
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Card, CardBody, CardHeader, Chip, Progress, Spinner } from '@heroui/react';
import type { LucideIcon } from 'lucide-react';
import {
  Activity,
  ArrowDown,
  ArrowDownRight,
  ArrowRight,
  CalendarDays,
  ChevronRight,
  Filter,
  RefreshCw,
  Target,
  TrendingDown,
  TrendingUp,
  Users,
} from 'lucide-react';
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminCrm } from '../../api/adminApi';
import { useTranslation } from 'react-i18next';

interface FunnelStage {
  name: string;
  count: number;
  color: string;
}

interface FunnelData {
  stages: FunnelStage[];
  monthly_registrations: Array<{ month: string; count: number }>;
}

interface StageInsight extends FunnelStage {
  shareOfEntry: number;
  conversionFromPrevious: number | null;
  lossFromPrevious: number;
  widthPercent: number;
}

interface MetricCardProps {
  icon: LucideIcon;
  label: string;
  value: string;
  caption: string;
  accentClassName: string;
}

interface GuideCardProps {
  icon: LucideIcon;
  title: string;
  body: string;
  accentClassName: string;
}

interface SnapshotCardProps {
  eyebrow: string;
  value: string;
  body: string;
  accentClassName: string;
}

function formatPercent(value: number, maximumFractionDigits = 1): string {
  return `${Number(value.toFixed(maximumFractionDigits)).toLocaleString(undefined, {
    maximumFractionDigits,
    minimumFractionDigits: 0,
  })}%`;
}

function formatSignedPercent(value: number, maximumFractionDigits = 1): string {
  const absolute = Math.abs(value);
  const sign = value > 0 ? '+' : value < 0 ? '-' : '';

  return `${sign}${Number(absolute.toFixed(maximumFractionDigits)).toLocaleString(undefined, {
    maximumFractionDigits,
    minimumFractionDigits: 0,
  })}%`;
}

function formatMonthLabel(value: string): string {
  const trimmed = value.trim();
  if (!trimmed) {
    return value;
  }

  const normalized = /^\d{4}-\d{2}$/.test(trimmed) ? `${trimmed}-01` : trimmed;
  const parsed = new Date(normalized);

  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return parsed.toLocaleDateString(undefined, {
    month: 'short',
    year: 'numeric',
  });
}

function hexToRgba(hex: string, alpha: number): string {
  const normalized = hex.replace('#', '').trim();

  if (/^[\da-fA-F]{3}$/.test(normalized)) {
    const [r, g, b] = normalized.split('').map((part) => parseInt(`${part}${part}`, 16));
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  if (/^[\da-fA-F]{6}$/.test(normalized)) {
    const r = parseInt(normalized.slice(0, 2), 16);
    const g = parseInt(normalized.slice(2, 4), 16);
    const b = parseInt(normalized.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  return hex;
}

function getRateTone(rate: number): {
  chipColor: 'success' | 'warning' | 'danger' | 'default';
  progressColor: 'success' | 'warning' | 'danger' | 'default';
  textClassName: string;
} {
  if (rate >= 70) {
    return {
      chipColor: 'success',
      progressColor: 'success',
      textClassName: 'text-success',
    };
  }

  if (rate >= 40) {
    return {
      chipColor: 'warning',
      progressColor: 'warning',
      textClassName: 'text-warning',
    };
  }

  return {
    chipColor: 'danger',
    progressColor: 'danger',
    textClassName: 'text-danger',
  };
}

function MetricCard({ icon: Icon, label, value, caption, accentClassName }: MetricCardProps) {
  return (
    <Card className="border border-default-200/70 bg-content1/85 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-content1/75">
      <CardBody className="gap-4 p-5">
        <div className="flex items-start justify-between gap-3">
          <div className={`flex h-11 w-11 items-center justify-center rounded-2xl ${accentClassName}`}>
            <Icon size={20} />
          </div>
          <span className="text-xs font-medium uppercase tracking-[0.22em] text-default-500">
            {label}
          </span>
        </div>

        <div className="space-y-1">
          <p className="text-3xl font-semibold tracking-tight text-foreground">{value}</p>
          <p className="text-sm text-default-500">{caption}</p>
        </div>
      </CardBody>
    </Card>
  );
}

function GuideCard({ icon: Icon, title, body, accentClassName }: GuideCardProps) {
  return (
    <div className="rounded-2xl border border-default-200/70 bg-content1/70 p-4 shadow-sm">
      <div className="flex items-start gap-3">
        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl ${accentClassName}`}>
          <Icon size={18} />
        </div>
        <div className="space-y-1">
          <p className="font-medium text-foreground">{title}</p>
          <p className="text-sm leading-6 text-default-500">{body}</p>
        </div>
      </div>
    </div>
  );
}

function SnapshotCard({ eyebrow, value, body, accentClassName }: SnapshotCardProps) {
  return (
    <div className="rounded-[26px] border border-default-200/70 bg-content1/80 p-5 shadow-sm">
      <div className={`inline-flex rounded-full px-3 py-1 text-xs font-medium ${accentClassName}`}>
        {eyebrow}
      </div>
      <p className="mt-4 text-2xl font-semibold tracking-tight text-foreground">{value}</p>
      <p className="mt-3 text-sm leading-6 text-default-500">{body}</p>
    </div>
  );
}

export default function OnboardingFunnel() {
  const { t } = useTranslation('admin');
  usePageTitle("CRM");

  const toast = useToast();
  const { tenantPath } = useTenant();

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
      setError("Failed to load onboarding funnel data");
      toast.error("Failed to load onboarding funnel data");
    } finally {
      setLoading(false);
    }
  }, [toast]);


  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const insights = useMemo(() => {
    const stages = data?.stages ?? [];
    const monthlyRegistrations = data?.monthly_registrations ?? [];

    const entryStage = stages[0] ?? null;
    const finalStage = stages.length > 0 ? stages[stages.length - 1] ?? null : null;
    const maxCount = Math.max(entryStage?.count ?? 0, 1);

    const stageInsights: StageInsight[] = stages.map((stage, index) => {
      const previous = index > 0 ? stages[index - 1] ?? null : null;
      const conversionFromPrevious =
        previous && previous.count > 0 ? (stage.count / previous.count) * 100 : null;
      const lossFromPrevious =
        previous && previous.count > stage.count ? previous.count - stage.count : 0;

      return {
        ...stage,
        shareOfEntry: entryStage && entryStage.count > 0 ? (stage.count / entryStage.count) * 100 : 0,
        conversionFromPrevious,
        lossFromPrevious,
        widthPercent: Math.max((stage.count / maxCount) * 100, 24),
      };
    });

    const transitions = stageInsights
      .map((stage, index) => {
        if (index === 0) {
          return null;
        }

        const previous = stageInsights[index - 1] ?? null;
        if (!previous) {
          return null;
        }

        return {
          from: previous,
          to: stage,
          rate: stage.conversionFromPrevious ?? 0,
          loss: stage.lossFromPrevious,
        };
      })
      .filter(Boolean) as Array<{
      from: StageInsight;
      to: StageInsight;
      rate: number;
      loss: number;
    }>;

    const biggestDropoff = transitions.reduce<typeof transitions[number] | null>((largest, current) => {
      if (!largest || current.loss > largest.loss) {
        return current;
      }

      return largest;
    }, null);

    const weakestHandoff = transitions.reduce<typeof transitions[number] | null>((weakest, current) => {
      if (!weakest || current.rate < weakest.rate) {
        return current;
      }

      return weakest;
    }, null);

    const latestMonth =
      monthlyRegistrations.length > 0 ? monthlyRegistrations[monthlyRegistrations.length - 1] ?? null : null;
    const previousMonth =
      monthlyRegistrations.length > 1 ? monthlyRegistrations[monthlyRegistrations.length - 2] ?? null : null;

    const monthOverMonthChange =
      latestMonth && previousMonth && previousMonth.count > 0
        ? ((latestMonth.count - previousMonth.count) / previousMonth.count) * 100
        : null;

    return {
      entryStage,
      finalStage,
      stageInsights,
      transitions,
      biggestDropoff,
      weakestHandoff,
      latestMonth,
      previousMonth,
      monthOverMonthChange,
      overallConversion:
        entryStage && finalStage && entryStage.count > 0
          ? (finalStage.count / entryStage.count) * 100
          : 0,
      monthlyRegistrations,
    };
  }, [data]);

  if (loading) {
    return (
      <div className="flex min-h-[420px] items-center justify-center">
        <Spinner size="lg" label={"Loading Funnel"} />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="mx-auto max-w-5xl">
        <Card className="overflow-hidden border border-danger/20 bg-content1/90 shadow-lg">
          <CardBody className="gap-5 p-8">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-danger/10 text-danger">
              <TrendingDown size={24} />
            </div>
            <div className="space-y-2">
              <h1 className="text-2xl font-semibold text-foreground">
                {"Onboarding Funnel"}
              </h1>
              <p className="text-default-500">{error || "No data available"}</p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Button color="primary" onPress={fetchData} startContent={<RefreshCw size={16} />}>
                {"Refresh"}
              </Button>
              <Button
                as={Link}
                to={tenantPath('/admin/crm')}
                variant="flat"
                endContent={<ChevronRight size={16} />}
              >
                {"CRM Dashboard"}
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  const {
    entryStage,
    finalStage,
    stageInsights,
    transitions,
    biggestDropoff,
    weakestHandoff,
    latestMonth,
    previousMonth,
    monthOverMonthChange,
    overallConversion,
    monthlyRegistrations,
  } = insights;

  const heroStats = [
    {
      label: "Entry Stage",
      value: entryStage?.count.toLocaleString() ?? '0',
    },
    {
      label: "Overall Conversion",
      value: formatPercent(overallConversion),
    },
    {
      label: "Latest Month",
      value: latestMonth ? latestMonth.count.toLocaleString() : '0',
    },
  ];

  const guideCards: GuideCardProps[] = [
    {
      icon: Filter,
      title: "Stage Width",
      body: "Wider stage bars mean more members from the original entry group are still active at that point in the journey.",
      accentClassName: 'bg-primary/10 text-primary',
    },
    {
      icon: ArrowDownRight,
      title: "Conversion Rate",
      body: "Each conversion percentage compares a stage only to the stage immediately before it, not to the original starting group.",
      accentClassName: 'bg-warning/10 text-warning',
    },
    {
      icon: TrendingDown,
      title: "Drop-off",
      body: "Drop-off counts show how many members were lost before reaching the next step, helping you spot where follow-up may matter most.",
      accentClassName: 'bg-danger/10 text-danger',
    },
  ];

  const snapshotCards: SnapshotCardProps[] = [
    {
      eyebrow: "End-to-end conversion",
      value: formatPercent(overallConversion),
      body: t('crm.snapshot_conversion_body', {
        members: finalStage?.count.toLocaleString() ?? '0',
        stage: finalStage?.name ?? "No stages available",
      }),
      accentClassName: 'bg-success/10 text-success',
    },
    {
      eyebrow: "Recent acquisition",
      value: latestMonth?.count.toLocaleString() ?? '0',
      body: latestMonth
        ? `${t('crm.snapshot_volume_body', {
            members: latestMonth.count.toLocaleString(),
            month: formatMonthLabel(latestMonth.month),
          })}${
            monthOverMonthChange !== null
              ? ` ${t('crm.snapshot_volume_delta', {
                  change: formatSignedPercent(monthOverMonthChange),
                })}`
              : ''
          }`
        : "No registration data",
      accentClassName: 'bg-secondary/10 text-secondary',
    },
    {
      eyebrow: "Primary intervention point",
      value: biggestDropoff
        ? `${biggestDropoff.from.name} -> ${biggestDropoff.to.name}`
        : "Not enough stages",
      body: biggestDropoff
        ? t('crm.snapshot_priority_body', {
            loss: biggestDropoff.loss.toLocaleString(),
            rate: formatPercent(biggestDropoff.rate),
          })
        : "No stages available",
      accentClassName: 'bg-danger/10 text-danger',
    },
  ];

  return (
    <div className="mx-auto max-w-7xl space-y-6 pb-10">
      <section className="relative overflow-hidden rounded-[32px] border border-black/5 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(241,245,249,0.88))] px-6 py-7 shadow-[0_20px_60px_rgba(15,23,42,0.08)] dark:border-white/10 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.96),rgba(30,41,59,0.88))] dark:shadow-[0_24px_80px_rgba(2,6,23,0.45)] sm:px-8 sm:py-8">
        <div
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.18),transparent_36%),radial-gradient(circle_at_bottom_right,rgba(16,185,129,0.16),transparent_34%)]"
          aria-hidden="true"
        />
        <div
          className="pointer-events-none absolute right-[-10%] top-[-18%] h-56 w-56 rounded-full bg-primary/10 blur-3xl"
          aria-hidden="true"
        />

        <div className="relative flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
          <div className="max-w-3xl space-y-5">
            <Chip
              variant="flat"
              className="border border-primary/15 bg-primary/10 px-3 text-primary"
            >
              {"CRM"}
            </Chip>

            <div className="space-y-3">
              <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                {"Onboarding Funnel"}
              </h1>
              <p className="max-w-2xl text-base leading-7 text-default-600 dark:text-default-400">
                {"Track where members stall between registration and meaningful participation so follow-up work lands where it matters."}
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              {heroStats.map((stat) => (
                <div
                  key={stat.label}
                  className="rounded-2xl border border-white/40 bg-white/65 p-4 backdrop-blur supports-[backdrop-filter]:bg-white/55 dark:border-white/10 dark:bg-white/5"
                >
                  <p className="text-xs font-medium uppercase tracking-[0.22em] text-default-500">
                    {stat.label}
                  </p>
                  <p className="mt-2 text-2xl font-semibold text-foreground">{stat.value}</p>
                </div>
              ))}
            </div>
          </div>

          <div className="flex w-full flex-col gap-3 xl:w-auto xl:min-w-[320px]">
            <Button
              color="primary"
              onPress={fetchData}
              isLoading={loading}
              startContent={<RefreshCw size={16} />}
            >
              {"Refresh"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/crm')}
              variant="flat"
              endContent={<ChevronRight size={16} />}
            >
              {"CRM Dashboard"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/users')}
              variant="flat"
              endContent={<ChevronRight size={16} />}
            >
              {"All Members"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/crm/tasks')}
              variant="flat"
              endContent={<ChevronRight size={16} />}
            >
              {"CRM Tasks"}
            </Button>
          </div>
        </div>
      </section>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          icon={Users}
          label={"Entry Stage"}
          value={entryStage?.count.toLocaleString() ?? '0'}
          caption={entryStage?.name ?? "No stages available"}
          accentClassName="bg-primary/10 text-primary"
        />
        <MetricCard
          icon={Target}
          label={"Completed Journey"}
          value={finalStage?.count.toLocaleString() ?? '0'}
          caption={finalStage?.name ?? "No stages available"}
          accentClassName="bg-success/10 text-success"
        />
        <MetricCard
          icon={TrendingDown}
          label={"Biggest Drop-off"}
          value={biggestDropoff?.loss.toLocaleString() ?? '0'}
          caption={
            biggestDropoff
              ? `${biggestDropoff.from.name} -> ${biggestDropoff.to.name}`
              : "Not enough stages"
          }
          accentClassName="bg-danger/10 text-danger"
        />
        <MetricCard
          icon={CalendarDays}
          label={"Latest Month"}
          value={latestMonth?.count.toLocaleString() ?? '0'}
          caption={
            latestMonth
              ? previousMonth && monthOverMonthChange !== null
                ? `${formatSignedPercent(monthOverMonthChange)} ${"from previous month"}`
                : formatMonthLabel(latestMonth.month)
              : "No registration data"
          }
          accentClassName="bg-secondary/10 text-secondary"
        />
      </div>

      <Card className="border border-default-200/70 bg-content1/90 shadow-sm">
        <CardHeader className="flex flex-col items-start gap-2 px-6 pb-0 pt-6">
          <h2 className="text-lg font-semibold text-foreground">{"How to Read This Page"}</h2>
          <p className="text-sm text-default-500">{"A quick reference so the bars, percentages, and drop-off panels are easier to interpret at a glance."}</p>
        </CardHeader>
        <CardBody className="grid gap-4 px-6 pb-6 pt-5 md:grid-cols-3">
          {guideCards.map((card) => (
            <GuideCard key={card.title} {...card} />
          ))}
        </CardBody>
      </Card>

      <Card className="border border-default-200/70 bg-content1/90 shadow-sm">
        <CardHeader className="flex flex-col items-start gap-2 px-6 pb-0 pt-6">
          <h2 className="text-lg font-semibold text-foreground">{"What This Funnel Says Right Now"}</h2>
          <p className="text-sm text-default-500">{"A short readout of what the funnel is saying right now, before you dive into the detail below."}</p>
        </CardHeader>
        <CardBody className="grid gap-4 px-6 pb-6 pt-5 lg:grid-cols-3">
          {snapshotCards.map((card) => (
            <SnapshotCard key={card.eyebrow} {...card} />
          ))}
        </CardBody>
      </Card>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)]">
        <Card className="overflow-hidden border border-default-200/70 bg-content1/90 shadow-lg">
          <CardHeader className="flex flex-col items-start gap-3 px-6 pb-0 pt-6">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <Filter size={20} />
              </div>
              <div>
                <h2 className="text-xl font-semibold text-foreground">{"Member Journey"}</h2>
                <p className="text-sm text-default-500">{"A stage-by-stage view of how many entrants are still moving toward first exchange."}</p>
              </div>
            </div>
          </CardHeader>

          <CardBody className="gap-5 px-6 pb-6 pt-6">
            {stageInsights.length > 0 ? (
              <>
                <div className="rounded-[28px] border border-primary/10 bg-primary/5 p-4 sm:p-5">
                  <p className="text-sm leading-7 text-default-600 dark:text-default-400">
                    {"Each stage bar shows how much of the original entry group is still present. The badge on the bar shows share of entrants for the first stage, then conversion from the previous stage for each later step."}
                  </p>
                </div>

                <div className="space-y-4">
                  {stageInsights.map((stage, index) => {
                    const previous = index > 0 ? stageInsights[index - 1] ?? null : null;
                    const stageTone = stage.conversionFromPrevious !== null
                      ? getRateTone(stage.conversionFromPrevious)
                      : getRateTone(100);

                    return (
                      <div key={stage.name} className="space-y-3">
                        {previous && (
                          <div className="flex items-center gap-3 px-2 text-sm text-default-500">
                            <div className="flex items-center gap-2">
                              <ArrowDownRight size={15} className={stageTone.textClassName} />
                              <Chip
                                size="sm"
                                color={stageTone.chipColor}
                                variant="flat"
                                className="font-medium"
                              >
                                {formatPercent(stage.conversionFromPrevious ?? 0)}
                              </Chip>
                            </div>
                            <span>{`${stage.lossFromPrevious} lost between stages`}</span>
                          </div>
                        )}

                        <div className="rounded-[28px] border border-default-200/70 bg-default-50/70 p-4 dark:border-white/10 dark:bg-white/[0.03] sm:p-5">
                          <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                            <div className="space-y-2">
                              <Chip size="sm" variant="flat" className="w-fit">
                                {`Stage ${index + 1}`}
                              </Chip>
                              <p className="text-lg font-semibold text-foreground">{stage.name}</p>
                              <p className="text-sm text-default-500">
                                {formatPercent(stage.shareOfEntry)} {"Share of entrants"}
                              </p>
                            </div>
                            <div className="space-y-2 text-right">
                              {index > 0 && (
                                <Chip size="sm" color="danger" variant="flat" className="font-medium">
                                  {stage.lossFromPrevious.toLocaleString()} {"lost since previous stage"}
                                </Chip>
                              )}
                              <p className="text-2xl font-semibold tracking-tight text-foreground">
                                {stage.count.toLocaleString()}
                              </p>
                              <p className="text-sm text-default-500">{"Members"}</p>
                            </div>
                          </div>

                          <div
                            className="relative overflow-hidden rounded-[22px] border border-black/5 p-4 text-white dark:border-white/10"
                            style={{
                              width: `${stage.widthPercent}%`,
                              minWidth: '16rem',
                              background: `linear-gradient(135deg, ${hexToRgba(stage.color, 0.98)} 0%, ${hexToRgba(stage.color, 0.72)} 100%)`,
                              boxShadow: `0 18px 42px ${hexToRgba(stage.color, 0.18)}`,
                            }}
                          >
                            <div
                              className="pointer-events-none absolute inset-0 bg-[linear-gradient(120deg,rgba(255,255,255,0.16),transparent_55%)]"
                              aria-hidden="true"
                            />
                            <div className="relative flex items-center justify-between gap-4">
                              <span className="text-sm font-medium text-white/85">
                                {index === 0
                                  ? "Entry Stage"
                                  : "Conversion from previous stage"}
                              </span>
                              <span className="rounded-full bg-black/20 px-3 py-1 text-sm font-semibold text-white">
                                {index === 0
                                  ? formatPercent(stage.shareOfEntry)
                                  : formatPercent(stage.conversionFromPrevious ?? 0)}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>

                {entryStage && finalStage && (
                  <div className="flex flex-wrap items-center justify-between gap-4 rounded-[28px] border border-primary/10 bg-primary/5 px-5 py-4">
                    <div className="space-y-1">
                      <p className="text-sm font-medium text-default-500">{"Overall Conversion"}</p>
                      <div className="flex items-center gap-2 text-sm text-default-600 dark:text-default-400">
                        <span>{entryStage.name}</span>
                        <ArrowRight size={14} />
                        <span>{finalStage.name}</span>
                      </div>
                    </div>
                    <p className="text-3xl font-semibold text-foreground">{formatPercent(overallConversion)}</p>
                  </div>
                )}
              </>
            ) : (
              <p className="py-10 text-center text-sm text-default-400">{"No stages available"}</p>
            )}
          </CardBody>
        </Card>

        <div className="space-y-6">
          <Card className="border border-default-200/70 bg-content1/90 shadow-sm">
            <CardHeader className="flex flex-col items-start gap-3 px-6 pb-0 pt-6">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-warning/10 text-warning">
                  <TrendingDown size={18} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-foreground">{"Attention Needed"}</h2>
                  <p className="text-sm text-default-500">{"The handoffs most likely to benefit from coordinator attention right now."}</p>
                </div>
              </div>
            </CardHeader>

            <CardBody className="gap-4 px-6 pb-6 pt-6">
              <div className="rounded-2xl border border-danger/10 bg-danger/5 p-4">
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-medium text-default-600 dark:text-default-300">
                    {"Biggest Drop-off"}
                  </p>
                  <Chip color="danger" variant="flat">
                    {biggestDropoff?.loss.toLocaleString() ?? '0'}
                  </Chip>
                </div>
                <p className="mt-3 text-base font-semibold text-foreground">
                  {biggestDropoff
                    ? `${biggestDropoff.from.name} -> ${biggestDropoff.to.name}`
                    : "Not enough stages"}
                </p>
                <p className="mt-2 text-sm leading-6 text-default-500">
                  {"This is the point in the journey where the highest number of members stop before reaching the next stage."}
                </p>
              </div>

              <div className="rounded-2xl border border-warning/10 bg-warning/5 p-4">
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-medium text-default-600 dark:text-default-300">
                    {"Weakest Handoff"}
                  </p>
                  <Chip
                    color={weakestHandoff ? getRateTone(weakestHandoff.rate).chipColor : 'default'}
                    variant="flat"
                  >
                    {weakestHandoff ? formatPercent(weakestHandoff.rate) : '0%'}
                  </Chip>
                </div>
                <p className="mt-3 text-base font-semibold text-foreground">
                  {weakestHandoff
                    ? `${weakestHandoff.from.name} -> ${weakestHandoff.to.name}`
                    : "Not enough stages"}
                </p>
                <p className="mt-2 text-sm leading-6 text-default-500">
                  {"This is the lowest stage-to-stage conversion rate in the current funnel, even if another stage has a larger raw loss count."}
                </p>
              </div>

              <div className="rounded-2xl border border-success/10 bg-success/5 p-4">
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-medium text-default-600 dark:text-default-300">
                    {"Overall Conversion"}
                  </p>
                  <Chip color="success" variant="flat">
                    {formatPercent(overallConversion)}
                  </Chip>
                </div>
                <p className="mt-3 text-base font-semibold text-foreground">
                  {finalStage?.name ?? "No stages available"}
                </p>
                <p className="mt-2 text-sm leading-6 text-default-500">
                  {"This is the percentage of members who make it from the first recorded stage all the way to the final stage shown on this page."}
                </p>
              </div>
            </CardBody>
          </Card>

          <Card className="border border-default-200/70 bg-content1/90 shadow-sm">
            <CardHeader className="flex flex-col items-start gap-3 px-6 pb-0 pt-6">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <Activity size={18} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-foreground">
                    {"Stage Conversion"}
                  </h2>
                  <p className="text-sm text-default-500">{"Stage-by-stage handoff quality across the full onboarding journey."}</p>
                </div>
              </div>
            </CardHeader>

            <CardBody className="gap-4 px-6 pb-6 pt-6">
              {transitions.length > 0 ? (
                <>
                  <p className="text-sm leading-7 text-default-500">
                    {"These cards compare each stage directly to the next one. A weaker percentage here usually means the next action, approval, or reminder needs attention."}
                  </p>

                  {transitions.map((transition) => {
                    const tone = getRateTone(transition.rate);

                    return (
                      <div
                        key={`${transition.from.name}-${transition.to.name}`}
                        className="rounded-2xl border border-default-200/70 bg-default-50/70 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                      >
                        <div className="mb-3 flex items-start justify-between gap-3">
                          <div>
                            <p className="font-medium text-foreground">{transition.from.name}</p>
                            <div className="mt-1 flex items-center gap-2 text-sm text-default-500">
                              <ArrowDown size={13} />
                              <span>{transition.to.name}</span>
                            </div>
                          </div>
                          <p className={`text-xl font-semibold ${tone.textClassName}`}>
                            {formatPercent(transition.rate)}
                          </p>
                        </div>

                        <div className="mb-3 flex items-center justify-between gap-3 text-sm text-default-500">
                          <span>{`${transition.loss.toLocaleString()} members are lost at this step.`}</span>
                          <Chip size="sm" color={tone.chipColor} variant="flat">
                            {transition.loss.toLocaleString()} {"Dropped Off"}
                          </Chip>
                        </div>

                        <Progress
                          value={transition.rate}
                          color={tone.progressColor}
                          aria-label={`${transition.from.name} to ${transition.to.name}`}
                          classNames={{
                            track: 'h-2',
                            indicator: 'rounded-full',
                          }}
                        />
                      </div>
                    );
                  })}
                </>
              ) : (
                <p className="py-6 text-center text-sm text-default-400">{"Not enough stages"}</p>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
        <Card className="border border-default-200/70 bg-content1/90 shadow-lg">
          <CardHeader className="flex flex-col items-start gap-3 px-6 pb-0 pt-6">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-secondary/10 text-secondary">
                <CalendarDays size={20} />
              </div>
              <div>
                <h2 className="text-xl font-semibold text-foreground">
                  {"Monthly Registrations"}
                </h2>
                <p className="text-sm text-default-500">{"Recent registration volume with month-over-month context."}</p>
              </div>
            </div>
          </CardHeader>

          <CardBody className="gap-5 px-6 pb-6 pt-6">
            {monthlyRegistrations.length > 0 ? (
              <>
                <p className="text-sm leading-7 text-default-500">{"Use this trend to tell whether funnel changes are coming from weaker conversion, lower incoming registrations, or both at the same time."}</p>

                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="rounded-2xl border border-default-200/70 bg-default-50/70 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                    <p className="text-sm font-medium text-default-500">{"Latest Month"}</p>
                    <p className="mt-2 text-2xl font-semibold text-foreground">
                      {latestMonth?.count.toLocaleString() ?? '0'}
                    </p>
                    <p className="mt-1 text-sm text-default-500">
                      {latestMonth ? formatMonthLabel(latestMonth.month) : "No registration data"}
                    </p>
                  </div>

                  <div className="rounded-2xl border border-default-200/70 bg-default-50/70 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                    <p className="text-sm font-medium text-default-500">{"from previous month"}</p>
                    <p className="mt-2 text-2xl font-semibold text-foreground">
                      {monthOverMonthChange !== null ? formatSignedPercent(monthOverMonthChange) : '0%'}
                    </p>
                    <p className="mt-1 text-sm text-default-500">
                      {previousMonth ? formatMonthLabel(previousMonth.month) : "No registration data"}
                    </p>
                  </div>
                </div>

                <div className="h-[320px]">
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={monthlyRegistrations} margin={{ top: 12, right: 10, left: -12, bottom: 6 }}>
                      <defs>
                        <linearGradient id="crmRegistrationsFill" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="hsl(var(--heroui-primary))" stopOpacity={0.35} />
                          <stop offset="95%" stopColor="hsl(var(--heroui-primary))" stopOpacity={0.02} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid vertical={false} strokeDasharray="4 4" className="opacity-20" />
                      <XAxis
                        dataKey="month"
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 12 }}
                        tickFormatter={(value) => formatMonthLabel(String(value))}
                      />
                      <YAxis tickLine={false} axisLine={false} allowDecimals={false} tick={{ fontSize: 12 }} />
                      <Tooltip
                        labelFormatter={(value) => formatMonthLabel(String(value))}
                        formatter={(value: number | string | undefined) => [
                          Number(value ?? 0).toLocaleString(),
                          "Members",
                        ]}
                        contentStyle={{
                          borderRadius: '16px',
                          border: '1px solid hsl(var(--heroui-divider))',
                          backgroundColor: 'hsl(var(--heroui-content1))',
                          color: 'hsl(var(--heroui-foreground))',
                          boxShadow: '0 18px 50px rgba(15, 23, 42, 0.12)',
                        }}
                      />
                      <Area
                        type="monotone"
                        dataKey="count"
                        stroke="hsl(var(--heroui-primary))"
                        strokeWidth={3}
                        fill="url(#crmRegistrationsFill)"
                        activeDot={{ r: 5 }}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              </>
            ) : (
              <p className="py-10 text-center text-sm text-default-400">{"No registration data"}</p>
            )}
          </CardBody>
        </Card>

        <Card className="border border-default-200/70 bg-content1/90 shadow-sm">
          <CardHeader className="flex flex-col items-start gap-3 px-6 pb-0 pt-6">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-success/10 text-success">
                <TrendingUp size={20} />
              </div>
              <div>
                <h2 className="text-xl font-semibold text-foreground">
                  {"Stage Health"}
                </h2>
                <p className="text-sm text-default-500">{"Volume and conversion strength for every stage in one glance."}</p>
              </div>
            </div>
          </CardHeader>

          <CardBody className="gap-5 px-6 pb-6 pt-6">
            {stageInsights.length > 0 ? (
              <>
                <p className="text-sm leading-7 text-default-500">{"Share of entrants tells you how much of the original cohort remains. Conversion from previous stage tells you how efficiently members move through the immediate handoff."}</p>

                {stageInsights.map((stage, index) => {
                  const tone =
                    stage.conversionFromPrevious !== null
                      ? getRateTone(stage.conversionFromPrevious)
                      : getRateTone(100);

                  return (
                    <div
                      key={stage.name}
                      className="rounded-2xl border border-default-200/70 bg-default-50/70 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                    >
                      <div className="mb-4 flex items-start justify-between gap-3">
                        <div>
                          <p className="font-semibold text-foreground">{stage.name}</p>
                          <p className="text-sm text-default-500">{stage.count.toLocaleString()}</p>
                        </div>
                        <Chip
                          color={index === 0 ? 'primary' : tone.chipColor}
                          variant="flat"
                          className="font-medium"
                        >
                          {formatPercent(stage.shareOfEntry)}
                        </Chip>
                      </div>

                      <div className="space-y-3">
                        <div className="space-y-1">
                          <div className="flex items-center justify-between text-xs font-medium uppercase tracking-[0.18em] text-default-500">
                            <span>{"Share of entrants"}</span>
                            <span>{formatPercent(stage.shareOfEntry)}</span>
                          </div>
                          <Progress
                            value={stage.shareOfEntry}
                            color="primary"
                            aria-label={`${stage.name} share of entry stage`}
                            classNames={{
                              track: 'h-2',
                              indicator: 'rounded-full',
                            }}
                          />
                        </div>

                        {index > 0 && (
                          <div className="space-y-1">
                            <div className="flex items-center justify-between text-xs font-medium uppercase tracking-[0.18em] text-default-500">
                              <span>{"Conversion from previous stage"}</span>
                              <span className={tone.textClassName}>
                                {formatPercent(stage.conversionFromPrevious ?? 0)}
                              </span>
                            </div>
                            <Progress
                              value={stage.conversionFromPrevious ?? 0}
                              color={tone.progressColor}
                              aria-label={`${stage.name} conversion from previous stage`}
                              classNames={{
                                track: 'h-2',
                                indicator: 'rounded-full',
                              }}
                            />
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </>
            ) : (
              <p className="py-10 text-center text-sm text-default-400">{"No stages available"}</p>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
