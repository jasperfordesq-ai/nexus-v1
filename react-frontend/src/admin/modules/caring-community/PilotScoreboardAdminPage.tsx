// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Textarea,
  Tooltip,
} from '@heroui/react';
import Building2 from 'lucide-react/icons/building-2';
import CalendarClock from 'lucide-react/icons/calendar-clock';
import Camera from 'lucide-react/icons/camera';
import Clock from 'lucide-react/icons/clock';
import Coins from 'lucide-react/icons/coins';
import Flag from 'lucide-react/icons/flag';
import Heart from 'lucide-react/icons/heart';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Star from 'lucide-react/icons/star';
import TrendingDown from 'lucide-react/icons/trending-down';
import TrendingUp from 'lucide-react/icons/trending-up';
import UserCog from 'lucide-react/icons/user-cog';
import UserX from 'lucide-react/icons/user-x';
import Users from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

interface PilotMetrics {
  active_members: number;
  first_response_hours: number | null;
  approved_hours: number;
  recurring_relationships: number;
  coordinator_workload_hrs: number | null;
  satisfaction_score: number | null;
  social_isolation_pct: number | null;
  comms_reach_pct: number | null;
  business_participation: number;
  cost_offset_chf: number;
  methodology: {
    window_days: number;
    hourly_rate_chf: number;
    prevention_multiplier: number;
  };
}

interface ScoreboardBaseline {
  id: number;
  label: string;
  is_pre_pilot: boolean;
  baseline_period: { start: string; end: string };
  captured_at: string;
  metrics: Omit<PilotMetrics, 'methodology'>;
  notes: string | null;
  captured_by: number | null;
}

interface MetricComparison {
  baseline: number | null;
  current: number | null;
  delta: number | null;
  pct_change: number | null;
}

interface Scoreboard {
  current: PilotMetrics;
  pre_pilot_baseline: ScoreboardBaseline | null;
  latest_quarterly: ScoreboardBaseline | null;
  comparison: Record<string, MetricComparison> | null;
  quarterly_review: {
    next_due_at: string | null;
    is_overdue: boolean;
    cadence_months: number;
  };
}

const CHF = new Intl.NumberFormat('de-CH', {
  style: 'currency',
  currency: 'CHF',
  maximumFractionDigits: 0,
});

const METRIC_KEYS: Array<{
  key: keyof Omit<PilotMetrics, 'methodology'>;
  label: string;
  format: 'int' | 'float' | 'pct' | 'hours' | 'chf' | 'score';
  betterDirection: 'up' | 'down';
}> = [
  { key: 'active_members',          label: 'Active members (90d)',     format: 'int',   betterDirection: 'up' },
  { key: 'first_response_hours',    label: 'Median first-response',     format: 'hours', betterDirection: 'down' },
  { key: 'approved_hours',          label: 'Approved hours (90d)',      format: 'float', betterDirection: 'up' },
  { key: 'recurring_relationships', label: 'Recurring relationships',   format: 'int',   betterDirection: 'up' },
  { key: 'coordinator_workload_hrs',label: 'Coordinator workload',       format: 'float', betterDirection: 'down' },
  { key: 'satisfaction_score',      label: 'Satisfaction score (1–5)',   format: 'score', betterDirection: 'up' },
  { key: 'social_isolation_pct',    label: 'Social isolation proxy',     format: 'pct',   betterDirection: 'down' },
  { key: 'comms_reach_pct',         label: 'Comms reach',                format: 'pct',   betterDirection: 'up' },
  { key: 'business_participation',  label: 'Active businesses (90d)',    format: 'int',   betterDirection: 'up' },
  { key: 'cost_offset_chf',         label: 'Estimated cost offset (CHF)', format: 'chf',  betterDirection: 'up' },
];

function formatValue(v: number | null, fmt: string): string {
  if (v === null || v === undefined) return '—';
  switch (fmt) {
    case 'int':    return v.toLocaleString();
    case 'float':  return v.toLocaleString(undefined, { maximumFractionDigits: 1 });
    case 'pct':    return `${v.toFixed(1)}%`;
    case 'hours':  return `${v.toFixed(1)} h`;
    case 'chf':    return CHF.format(v);
    case 'score':  return `${v.toFixed(2)} / 5`;
    default:       return String(v);
  }
}

const ICONS: Record<string, typeof Users> = {
  active_members: Users,
  first_response_hours: Clock,
  approved_hours: Heart,
  recurring_relationships: Heart,
  coordinator_workload_hrs: UserCog,
  satisfaction_score: Star,
  social_isolation_pct: UserX,
  comms_reach_pct: Camera,
  business_participation: Building2,
  cost_offset_chf: Coins,
};

export default function PilotScoreboardAdminPage() {
  usePageTitle('Pilot Success Scoreboard');
  const { showToast } = useToast();

  const [data, setData] = useState<Scoreboard | null>(null);
  const [baselines, setBaselines] = useState<ScoreboardBaseline[]>([]);
  const [loading, setLoading] = useState(true);
  const [showPreModal, setShowPreModal] = useState(false);
  const [showQuarterlyModal, setShowQuarterlyModal] = useState(false);
  const [preNotes, setPreNotes] = useState('');
  const [quarterlyLabel, setQuarterlyLabel] = useState('');
  const [quarterlyNotes, setQuarterlyNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [scoreboardRes, baselinesRes] = await Promise.all([
        api.get<Scoreboard>('/v2/admin/caring-community/pilot-scoreboard'),
        api.get<{ items: ScoreboardBaseline[] }>('/v2/admin/caring-community/pilot-scoreboard/baselines'),
      ]);
      setData(scoreboardRes.data ?? null);
      setBaselines(baselinesRes.data?.items ?? []);
    } catch {
      showToast('Failed to load pilot scoreboard', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const capturePre = async () => {
    setSubmitting(true);
    try {
      await api.post('/v2/admin/caring-community/pilot-scoreboard/pre-pilot', { notes: preNotes });
      showToast('Pre-pilot baseline captured', 'success');
      setShowPreModal(false);
      setPreNotes('');
      await load();
    } catch {
      showToast('Failed to capture pre-pilot baseline', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const captureQuarterly = async () => {
    setSubmitting(true);
    try {
      await api.post('/v2/admin/caring-community/pilot-scoreboard/quarterly', {
        label: quarterlyLabel || undefined,
        notes: quarterlyNotes,
      });
      showToast('Quarterly review captured', 'success');
      setShowQuarterlyModal(false);
      setQuarterlyLabel('');
      setQuarterlyNotes('');
      await load();
    } catch {
      showToast('Failed to capture quarterly review', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const current = data?.current ?? null;
  const comp = data?.comparison ?? null;
  const prePilot = data?.pre_pilot_baseline ?? null;
  const quarterly = data?.quarterly_review;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pilot Success Scoreboard"
        subtitle="AG83 — pre-pilot baseline + 90-day rolling metrics with quarterly review cadence"
        icon={<Flag size={20} />}
        actions={
          <div className="flex gap-2">
            <Tooltip content="Refresh">
              <Button isIconOnly size="sm" variant="flat" onPress={load} isLoading={loading} aria-label="Refresh">
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              size="sm"
              color="primary"
              variant="flat"
              startContent={<Flag size={14} />}
              onPress={() => setShowPreModal(true)}
              isDisabled={prePilot !== null}
            >
              {prePilot ? 'Pre-pilot baseline set' : 'Capture pre-pilot baseline'}
            </Button>
            <Button
              size="sm"
              color="secondary"
              variant="flat"
              startContent={<CalendarClock size={14} />}
              onPress={() => setShowQuarterlyModal(true)}
              isDisabled={prePilot === null}
            >
              Capture quarterly review
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                All metrics use a 90-day rolling window. Capture a pre-pilot baseline before
                onboarding real residents, then run a quarterly review snapshot every 3 months.
                The comparison table shows how each metric has moved relative to that baseline.
              </p>
              <p className="text-default-600">
                Cost offset is calculated using the KISS/Age-Stiftung methodology: hours of informal
                care × CHF 35/hr (Swiss formal-care assistant rate) × 2 (prevention multiplier
                reflecting avoided formal care costs). This methodology is recognised by Swiss
                cantonal social services for pilot reporting.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {quarterly && quarterly.next_due_at && (
        <Card>
          <CardBody className="flex flex-row items-center justify-between py-3">
            <div className="flex items-center gap-3">
              <CalendarClock size={18} className={quarterly.is_overdue ? 'text-danger' : 'text-default-500'} />
              <span className="text-sm">
                Next quarterly review:{' '}
                <span className="font-semibold">
                  {new Date(quarterly.next_due_at).toLocaleDateString()}
                </span>
              </span>
            </div>
            {quarterly.is_overdue && (
              <Chip color="danger" variant="flat" size="sm" startContent={<ShieldAlert size={12} />}>
                Overdue
              </Chip>
            )}
          </CardBody>
        </Card>
      )}

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {current && !loading && (
        <Card>
          <CardHeader className="pb-2 flex justify-between">
            <span className="font-semibold text-sm">Pilot metrics</span>
            <span className="text-xs text-default-500">
              {prePilot
                ? `Comparing against pre-pilot baseline captured ${new Date(prePilot.captured_at).toLocaleDateString()}`
                : 'No pre-pilot baseline yet — current values shown alone'}
            </span>
          </CardHeader>
          <CardBody className="pt-0">
            <Table aria-label="Pilot metrics" removeWrapper>
              <TableHeader>
                <TableColumn>Metric</TableColumn>
                <TableColumn>Pre-pilot</TableColumn>
                <TableColumn>Current</TableColumn>
                <TableColumn>Δ</TableColumn>
                <TableColumn>Change</TableColumn>
              </TableHeader>
              <TableBody>
                {METRIC_KEYS.map(({ key, label, format, betterDirection }) => {
                  const Icon = ICONS[key];
                  const c = comp?.[key];
                  const baselineVal = c?.baseline ?? null;
                  const currentVal = current[key] as number | null;
                  const delta = c?.delta ?? null;
                  const pct = c?.pct_change ?? null;
                  let chip: React.ReactNode = '—';
                  if (pct !== null) {
                    const isImprovement =
                      (betterDirection === 'up' && pct >= 0) ||
                      (betterDirection === 'down' && pct <= 0);
                    chip = (
                      <Chip
                        size="sm"
                        variant="flat"
                        color={isImprovement ? 'success' : 'danger'}
                        startContent={
                          pct >= 0 ? <TrendingUp size={12} /> : <TrendingDown size={12} />
                        }
                      >
                        {pct > 0 ? '+' : ''}
                        {pct.toFixed(1)}%
                      </Chip>
                    );
                  }
                  return (
                    <TableRow key={key}>
                      <TableCell>
                        <span className="flex items-center gap-2">
                          {Icon && <Icon size={14} className="text-default-500" />} {label}
                        </span>
                      </TableCell>
                      <TableCell>{formatValue(baselineVal, format)}</TableCell>
                      <TableCell className="font-semibold">{formatValue(currentVal, format)}</TableCell>
                      <TableCell>
                        {delta !== null
                          ? formatValue(delta, format === 'pct' ? 'pct' : format === 'chf' ? 'chf' : 'float')
                          : '—'}
                      </TableCell>
                      <TableCell>{chip}</TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      {baselines.length > 0 && (
        <Card>
          <CardHeader className="pb-2">
            <span className="font-semibold text-sm">Captured baselines</span>
          </CardHeader>
          <CardBody className="pt-0">
            <Table aria-label="Captured baselines" removeWrapper>
              <TableHeader>
                <TableColumn>Label</TableColumn>
                <TableColumn>Captured</TableColumn>
                <TableColumn>Period</TableColumn>
                <TableColumn>Type</TableColumn>
                <TableColumn>Notes</TableColumn>
              </TableHeader>
              <TableBody>
                {baselines.map((b) => (
                  <TableRow key={b.id}>
                    <TableCell className="font-mono text-xs">{b.label}</TableCell>
                    <TableCell>{new Date(b.captured_at).toLocaleString()}</TableCell>
                    <TableCell className="text-xs">
                      {b.baseline_period.start} → {b.baseline_period.end}
                    </TableCell>
                    <TableCell>
                      {b.is_pre_pilot ? (
                        <Chip size="sm" color="primary" variant="flat">Pre-pilot</Chip>
                      ) : (
                        <Chip size="sm" variant="flat">Quarterly</Chip>
                      )}
                    </TableCell>
                    <TableCell className="text-xs text-default-600 max-w-md truncate">
                      {b.notes ?? '—'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      <Modal isOpen={showPreModal} onClose={() => setShowPreModal(false)} size="lg">
        <ModalContent>
          <ModalHeader>Capture pre-pilot baseline</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600">
              Capture this ONCE before onboarding real residents. It freezes the current 90-day
              metrics as your comparison baseline. All future quarterly reviews will calculate
              delta against this snapshot. This action cannot be undone.
            </p>
            <Textarea
              label="Notes (optional)"
              placeholder="Pilot region, participating municipality, decisions made before launch..."
              value={preNotes}
              onValueChange={setPreNotes}
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setShowPreModal(false)} isDisabled={submitting}>
              Cancel
            </Button>
            <Button color="primary" onPress={capturePre} isLoading={submitting}>
              Capture baseline
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal isOpen={showQuarterlyModal} onClose={() => setShowQuarterlyModal(false)} size="lg">
        <ModalContent>
          <ModalHeader>Capture quarterly review</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600">
              Run every 3 months after pilot launch. The snapshot label defaults to the current
              quarter (e.g. Q2_2026) but you can customise it. Add notes to record what changed
              since the last review — milestones reached, policy changes, resident feedback.
              Snapshot covers the most recent 90-day window from today.
            </p>
            <Input
              label="Label (optional)"
              placeholder="quarterly_2026_07"
              value={quarterlyLabel}
              onValueChange={setQuarterlyLabel}
              description="Use a recognisable label such as Q2_2026 or 2026_Q3. Defaults to quarterly_YYYY_MM."
            />
            <Textarea
              label="Notes (optional)"
              placeholder="What changed this quarter, milestones reached..."
              value={quarterlyNotes}
              onValueChange={setQuarterlyNotes}
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setShowQuarterlyModal(false)} isDisabled={submitting}>
              Cancel
            </Button>
            <Button color="primary" onPress={captureQuarterly} isLoading={submitting}>
              Capture quarterly review
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {!loading && current && (
        <Divider />
      )}

      {!loading && current && (
        <p className="text-xs text-default-500">
          Methodology: hours × CHF {current.methodology.hourly_rate_chf}/hr × {current.methodology.prevention_multiplier}
          {' '}prevention multiplier. Window: {current.methodology.window_days} days.
        </p>
      )}
    </div>
  );
}
