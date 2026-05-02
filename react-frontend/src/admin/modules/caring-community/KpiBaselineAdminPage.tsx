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
import BarChart2 from 'lucide-react/icons/bar-chart-2';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import Database from 'lucide-react/icons/database';
import Info from 'lucide-react/icons/info';
import Minus from 'lucide-react/icons/minus';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import TrendingDown from 'lucide-react/icons/trending-down';
import TrendingUp from 'lucide-react/icons/trending-up';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { Abbr, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface KpiBaseline {
  id: number;
  label: string;
  captured_at: string;
  baseline_period: { start: string; end: string };
  metrics: {
    volunteer_hours: number | null;
    member_count: number | null;
    recipient_count: number | null;
    active_relationships: number | null;
    total_exchanges: number | null;
    avg_response_hours: number | null;
    engagement_rate_pct: number | null;
  };
  notes: string | null;
  captured_by: number | null;
}

interface MetricComparison {
  baseline: number | null;
  current: number | null;
  delta: number | null;
  pct_change: number | null;
}

interface ComparisonResult {
  baseline: KpiBaseline;
  current: Record<string, number | null>;
  comparison: Record<string, MetricComparison>;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const METRIC_LABELS: Record<string, string> = {
  volunteer_hours: 'Volunteer Hours (90d)',
  member_count: 'Active Members',
  recipient_count: 'Recipients Reached',
  active_relationships: 'Active Relationships',
  total_exchanges: 'Total Exchanges',
  avg_response_hours: 'Avg Response Time (hrs)',
  engagement_rate_pct: 'Engagement Rate (%)',
};

const IMPACT_METRICS = new Set(['volunteer_hours', 'active_relationships', 'member_count']);
const IMPACT_THRESHOLD = 25; // % change that counts as "impact achieved"

function fmt(val: number | null): string {
  if (val === null || val === undefined) return '—';
  return Number.isInteger(val) ? val.toLocaleString() : val.toFixed(1);
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

// ---------------------------------------------------------------------------
// Sub-component: Comparison Panel
// ---------------------------------------------------------------------------

function ComparisonPanel({ result, onClose }: { result: ComparisonResult; onClose: () => void }) {
  const { baseline, comparison } = result;

  return (
    <Card className="mt-4 border border-[var(--color-border)]">
      <CardHeader className="flex items-center justify-between gap-3 pb-2">
        <div className="flex items-center gap-2">
          <BarChart2 size={18} className="text-primary" />
          <span className="font-semibold text-sm">
            Comparing: <span className="text-primary">{baseline.label}</span>
            <span className="text-[var(--color-text-muted)] ml-2 font-normal">
              (captured {fmtDate(baseline.captured_at)})
            </span>
          </span>
        </div>
        <Button size="sm" variant="light" onPress={onClose}>
          Close
        </Button>
      </CardHeader>
      <CardBody className="pt-0">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {Object.entries(comparison).map(([key, mc]) => {
            const label = METRIC_LABELS[key] ?? key;
            const hasData = mc.baseline !== null || mc.current !== null;
            const pct = mc.pct_change;
            const isUp = pct !== null && pct > 0;
            const isDown = pct !== null && pct < 0;
            const isFlat = pct !== null && pct === 0;
            const isImpact = IMPACT_METRICS.has(key) && pct !== null && Math.abs(pct) >= IMPACT_THRESHOLD;

            return (
              <div
                key={key}
                className={`rounded-lg border p-3 flex flex-col gap-1.5 ${
                  isImpact
                    ? 'border-success bg-success/5'
                    : 'border-[var(--color-border)] bg-[var(--color-surface)]'
                }`}
              >
                <div className="flex items-center justify-between gap-1">
                  <span className="text-xs text-[var(--color-text-muted)] font-medium">{label}</span>
                  {isImpact && (
                    <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle2 size={12} />}>
                      Impact achieved
                    </Chip>
                  )}
                </div>

                {hasData ? (
                  <>
                    <div className="flex items-end gap-4">
                      <div>
                        <div className="text-[10px] text-[var(--color-text-muted)]">Baseline</div>
                        <div className="text-base font-semibold">{fmt(mc.baseline)}</div>
                      </div>
                      <div>
                        <div className="text-[10px] text-[var(--color-text-muted)]">Now</div>
                        <div className="text-base font-semibold">{fmt(mc.current)}</div>
                      </div>
                    </div>

                    {pct !== null && (
                      <div
                        className={`flex items-center gap-1 text-sm font-medium mt-0.5 ${
                          isUp ? 'text-success' : isDown ? 'text-danger' : 'text-[var(--color-text-muted)]'
                        }`}
                      >
                        {isUp && <TrendingUp size={14} />}
                        {isDown && <TrendingDown size={14} />}
                        {isFlat && <Minus size={14} />}
                        <span>
                          {isUp ? '+' : ''}{pct.toFixed(1)}%
                          {mc.delta !== null && (
                            <span className="ml-1 font-normal text-xs">
                              ({isUp ? '+' : ''}{fmt(mc.delta)})
                            </span>
                          )}
                        </span>
                      </div>
                    )}
                  </>
                ) : (
                  <span className="text-xs text-[var(--color-text-muted)] italic">No data available</span>
                )}
              </div>
            );
          })}
        </div>

        {baseline.notes && (
          <div className="mt-3 p-3 rounded-lg bg-[var(--color-surface-alt)] text-sm">
            <span className="font-medium">Notes: </span>
            <span className="text-[var(--color-text-muted)]">{baseline.notes}</span>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function KpiBaselineAdminPage() {
  usePageTitle('Community KPI Baselines');
  const { showToast } = useToast();

  const [baselines, setBaselines] = useState<KpiBaseline[]>([]);
  const [loading, setLoading] = useState(true);
  const [comparison, setComparison] = useState<ComparisonResult | null>(null);
  const [selectedBaselineId, setSelectedBaselineId] = useState<number | null>(null);
  const [comparingId, setComparingId] = useState<number | null>(null);
  const [captureModalOpen, setCaptureModalOpen] = useState(false);

  // Capture modal state
  const [captureLabel, setCaptureLabel] = useState('');
  const [captureNotes, setCaptureNotes] = useState('');
  const [capturePeriodStart, setCapturePeriodStart] = useState(() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 1);
    return d.toISOString().slice(0, 10);
  });
  const [capturePeriodEnd, setCapturePeriodEnd] = useState(() => new Date().toISOString().slice(0, 10));
  const [capturing, setCapturing] = useState(false);

  const loadBaselines = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<KpiBaseline[]>('/v2/admin/caring-community/kpi-baselines');
      setBaselines(Array.isArray(res.data) ? res.data : []);
    } catch {
      showToast('Failed to load KPI baselines', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    loadBaselines();
  }, [loadBaselines]);

  const handleCompare = useCallback(
    async (id: number) => {
      if (selectedBaselineId === id) {
        // Toggle off
        setSelectedBaselineId(null);
        setComparison(null);
        return;
      }
      setComparingId(id);
      try {
        const res = await api.get<ComparisonResult>(`/v2/admin/caring-community/kpi-baselines/${id}/compare`);
        setComparison(res.data ?? null);
        setSelectedBaselineId(id);
      } catch {
        showToast('Failed to load comparison', 'error');
      } finally {
        setComparingId(null);
      }
    },
    [selectedBaselineId, showToast],
  );

  const handleCapture = useCallback(async () => {
    setCapturing(true);
    try {
      await api.post('/v2/admin/caring-community/kpi-baselines', {
        label: captureLabel.trim() || undefined,
        period: { start: capturePeriodStart, end: capturePeriodEnd },
        notes: captureNotes.trim() || undefined,
      });
      showToast('Baseline saved. Use it to compare future metrics against this snapshot.', 'success');
      setCaptureModalOpen(false);
      setCaptureLabel('');
      setCaptureNotes('');
      await loadBaselines();
    } catch {
      showToast('Failed to capture baseline', 'error');
    } finally {
      setCapturing(false);
    }
  }, [captureLabel, captureNotes, capturePeriodStart, capturePeriodEnd, loadBaselines, showToast]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Community KPI Baselines"
        subtitle={<>Capture snapshots of your community metrics to track before/after <Abbr term="KPI">KPI</Abbr> impact</>}
        icon={<Database size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Tooltip content="Refresh">
              <Button isIconOnly size="sm" variant="flat" onPress={loadBaselines} isLoading={loading}>
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              color="primary"
              size="sm"
              startContent={<Plus size={15} />}
              onPress={() => setCaptureModalOpen(true)}
            >
              Capture Baseline Now
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
                <Abbr term="KPI">KPI</Abbr> Baselines record the state of your community before the Caring Community
                programme begins. They are compared against current metrics at each quarterly review
                to demonstrate impact. Capture baselines for every <Abbr term="KPI">KPI</Abbr> before onboarding residents —
                once the pilot starts, the baseline is frozen and cannot be backdated.
              </p>
              <p className="text-default-600">
                Click <strong>Compare</strong> on any baseline row to see a side-by-side comparison
                against current platform data. Metrics showing 25%+ change on key impact indicators
                are highlighted as 'Impact achieved'.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Baselines Table */}
      <Card>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex justify-center py-12">
              <Spinner size="lg" />
            </div>
          ) : baselines.length === 0 ? (
            <div className="flex flex-col items-center gap-3 py-12 text-[var(--color-text-muted)]">
              <Database size={40} className="opacity-30" />
              <p className="text-sm">No baselines captured yet.</p>
              <Button
                color="primary"
                size="sm"
                startContent={<Plus size={14} />}
                onPress={() => setCaptureModalOpen(true)}
              >
                Capture your first baseline
              </Button>
            </div>
          ) : (
            <Table
              aria-label="KPI Baselines"
              removeWrapper
              classNames={{ th: 'bg-[var(--color-surface-alt)] text-xs font-semibold uppercase tracking-wide' }}
            >
              <TableHeader>
                <TableColumn>Label</TableColumn>
                <TableColumn>Captured</TableColumn>
                <TableColumn>Members</TableColumn>
                <TableColumn>Volunteer Hours</TableColumn>
                <TableColumn>Active Relationships</TableColumn>
                <TableColumn>Total Exchanges</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {baselines.map((b) => (
                  <TableRow key={b.id} className={selectedBaselineId === b.id ? 'bg-primary/5' : ''}>
                    <TableCell>
                      <div className="font-medium text-sm">{b.label}</div>
                      {b.notes && (
                        <div className="text-xs text-[var(--color-text-muted)] mt-0.5 line-clamp-1">
                          {b.notes}
                        </div>
                      )}
                    </TableCell>
                    <TableCell className="text-sm text-[var(--color-text-muted)] whitespace-nowrap">
                      {fmtDate(b.captured_at)}
                    </TableCell>
                    <TableCell className="text-sm font-mono">{fmt(b.metrics.member_count)}</TableCell>
                    <TableCell className="text-sm font-mono">{fmt(b.metrics.volunteer_hours)}</TableCell>
                    <TableCell className="text-sm font-mono">{fmt(b.metrics.active_relationships)}</TableCell>
                    <TableCell className="text-sm font-mono">{fmt(b.metrics.total_exchanges)}</TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant={selectedBaselineId === b.id ? 'solid' : 'flat'}
                        color={selectedBaselineId === b.id ? 'primary' : 'default'}
                        isLoading={comparingId === b.id}
                        onPress={() => handleCompare(b.id)}
                        startContent={<BarChart2 size={13} />}
                      >
                        {selectedBaselineId === b.id ? 'Close' : 'Compare'}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Comparison Panel */}
      {comparison && selectedBaselineId !== null && (
        <ComparisonPanel
          result={comparison}
          onClose={() => {
            setComparison(null);
            setSelectedBaselineId(null);
          }}
        />
      )}

      {/* Capture Modal */}
      <Modal
        isOpen={captureModalOpen}
        onOpenChange={setCaptureModalOpen}
        size="md"
        placement="center"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Database size={18} className="text-primary" />
                Capture <Abbr term="KPI">KPI</Abbr> Baseline
              </ModalHeader>
              <ModalBody className="gap-4">
                <p className="text-sm text-[var(--color-text-muted)]">
                  This will take a live snapshot of your community metrics right now and save it
                  as a named baseline for future comparisons.
                </p>
                <Input
                  label="Baseline Label"
                  placeholder="e.g. Before NEXUS Launch"
                  value={captureLabel}
                  onValueChange={setCaptureLabel}
                  variant="bordered"
                  description='A short name to identify this snapshot (e.g. "Q1 2025 Pre-launch")'
                />
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label="Period Start"
                    type="date"
                    value={capturePeriodStart}
                    onValueChange={setCapturePeriodStart}
                    variant="bordered"
                    description="Reference period start"
                  />
                  <Input
                    label="Period End"
                    type="date"
                    value={capturePeriodEnd}
                    onValueChange={setCapturePeriodEnd}
                    variant="bordered"
                    description="Reference period end"
                  />
                </div>
                <Textarea
                  label="Notes / source (optional)"
                  placeholder="e.g. 'GP referral data Q1 2026, member survey March 2026, captured before platform rollout to all Gemeinden'"
                  value={captureNotes}
                  onValueChange={setCaptureNotes}
                  variant="bordered"
                  minRows={2}
                  description="Document where these numbers come from (e.g. 'GP referral data Q1 2026', 'member survey March 2026'). Cited sources make the baseline defensible in grant reporting."
                />
                <Divider />
                <p className="text-xs text-[var(--color-text-muted)]">
                  Metrics captured: active member count, approved volunteer hours (last 90 days),
                  active support relationships, distinct recipients, and total exchanges.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} isDisabled={capturing}>
                  Cancel
                </Button>
                <Button
                  color="primary"
                  onPress={handleCapture}
                  isLoading={capturing}
                  startContent={!capturing ? <Database size={15} /> : undefined}
                >
                  Capture Now
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
