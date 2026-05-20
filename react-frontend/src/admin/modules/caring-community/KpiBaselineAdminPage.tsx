// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
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

const IMPACT_METRICS = new Set(['volunteer_hours', 'active_relationships', 'member_count']);
const IMPACT_THRESHOLD = 25; // % change that counts as "impact achieved"
type AdminT = (key: string, options?: Record<string, unknown>) => string;

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

function ComparisonPanel({ result, onClose, t }: { result: ComparisonResult; onClose: () => void; t: AdminT }) {
  const { baseline, comparison } = result;

  return (
    <Card className="mt-4 border border-[var(--color-border)]">
      <CardHeader className="flex items-center justify-between gap-3 pb-2">
        <div className="flex items-center gap-2">
          <BarChart2 size={18} className="text-primary" />
          <span className="font-semibold text-sm">
            {t('kpi_baselines.comparison.comparing')} <span className="text-primary">{baseline.label}</span>
            <span className="text-[var(--color-text-muted)] ml-2 font-normal">
              {t('kpi_baselines.comparison.captured', { date: fmtDate(baseline.captured_at) })}
            </span>
          </span>
        </div>
        <Button size="sm" variant="light" onPress={onClose}>
          {t('kpi_baselines.actions.close')}
        </Button>
      </CardHeader>
      <CardBody className="pt-0">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {Object.entries(comparison).map(([key, mc]) => {
            const label = t(`kpi_baselines.metrics.${key}`);
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
                      {t('kpi_baselines.comparison.impact_achieved')}
                    </Chip>
                  )}
                </div>

                {hasData ? (
                  <>
                    <div className="flex items-end gap-4">
                      <div>
                        <div className="text-[10px] text-[var(--color-text-muted)]">{t('kpi_baselines.comparison.baseline')}</div>
                        <div className="text-base font-semibold">{fmt(mc.baseline)}</div>
                      </div>
                      <div>
                        <div className="text-[10px] text-[var(--color-text-muted)]">{t('kpi_baselines.comparison.now')}</div>
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
                  <span className="text-xs text-[var(--color-text-muted)] italic">{t('kpi_baselines.empty.no_data')}</span>
                )}
              </div>
            );
          })}
        </div>

        {baseline.notes && (
          <div className="mt-3 p-3 rounded-lg bg-[var(--color-surface-alt)] text-sm">
            <span className="font-medium">{t('kpi_baselines.fields.notes_label_prefix')} </span>
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
  const { t } = useTranslation('admin');
  usePageTitle(t('kpi_baselines.meta.page_title'));
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
      showToast(t('kpi_baselines.toasts.load_failed'), 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, t]);

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
        showToast(t('kpi_baselines.toasts.comparison_failed'), 'error');
      } finally {
        setComparingId(null);
      }
    },
    [selectedBaselineId, showToast, t],
  );

  const handleCapture = useCallback(async () => {
    setCapturing(true);
    try {
      await api.post('/v2/admin/caring-community/kpi-baselines', {
        label: captureLabel.trim() || undefined,
        period: { start: capturePeriodStart, end: capturePeriodEnd },
        notes: captureNotes.trim() || undefined,
      });
      showToast(t('kpi_baselines.toasts.saved'), 'success');
      setCaptureModalOpen(false);
      setCaptureLabel('');
      setCaptureNotes('');
      await loadBaselines();
    } catch {
      showToast(t('kpi_baselines.toasts.capture_failed'), 'error');
    } finally {
      setCapturing(false);
    }
  }, [captureLabel, captureNotes, capturePeriodStart, capturePeriodEnd, loadBaselines, showToast, t]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('kpi_baselines.meta.title')}
        subtitle={<>{t('kpi_baselines.meta.subtitle_prefix')} <Abbr term="KPI" /> {t('kpi_baselines.meta.subtitle_suffix')}</>}
        icon={<Database size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Tooltip content={t('kpi_baselines.actions.refresh')}>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={loadBaselines}
                isLoading={loading}
                aria-label={t('kpi_baselines.actions.refresh_aria')}
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              color="primary"
              size="sm"
              startContent={<Plus size={15} />}
              onPress={() => setCaptureModalOpen(true)}
            >
              {t('kpi_baselines.actions.capture_now')}
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('kpi_baselines.about.title')}</p>
              <p className="text-default-600">
                <Abbr term="KPI" /> {t('kpi_baselines.about.body_prefix')} <Abbr term="KPI" /> {t('kpi_baselines.about.body_suffix')}
              </p>
              <p className="text-default-600">
                {t('kpi_baselines.about.compare_prefix')} <strong>{t('kpi_baselines.actions.compare')}</strong> {t('kpi_baselines.about.compare_suffix')}
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
              <p className="text-sm">{t('kpi_baselines.empty.no_baselines')}</p>
              <Button
                color="primary"
                size="sm"
                startContent={<Plus size={14} />}
                onPress={() => setCaptureModalOpen(true)}
              >
                {t('kpi_baselines.actions.capture_first')}
              </Button>
            </div>
          ) : (
            <Table
              aria-label={t('kpi_baselines.table.aria')}
              removeWrapper
              classNames={{ th: 'bg-[var(--color-surface-alt)] text-xs font-semibold uppercase tracking-wide' }}
            >
              <TableHeader>
                <TableColumn>{t('kpi_baselines.table.label')}</TableColumn>
                <TableColumn>{t('kpi_baselines.table.captured')}</TableColumn>
                <TableColumn>{t('kpi_baselines.table.members')}</TableColumn>
                <TableColumn>{t('kpi_baselines.table.volunteer_hours')}</TableColumn>
                <TableColumn>{t('kpi_baselines.table.active_relationships')}</TableColumn>
                <TableColumn>{t('kpi_baselines.table.total_exchanges')}</TableColumn>
                <TableColumn>{t('kpi_baselines.table.actions')}</TableColumn>
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
                        {selectedBaselineId === b.id ? t('kpi_baselines.actions.close') : t('kpi_baselines.actions.compare')}
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
          t={t}
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
                {t('kpi_baselines.modal.title_prefix')} <Abbr term="KPI" /> {t('kpi_baselines.modal.title_suffix')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <p className="text-sm text-[var(--color-text-muted)]">
                  {t('kpi_baselines.modal.body')}
                </p>
                <Input
                  label={t('kpi_baselines.fields.baseline_label')}
                  placeholder={t('kpi_baselines.fields.baseline_placeholder')}
                  value={captureLabel}
                  onValueChange={setCaptureLabel}
                  variant="bordered"
                  description={t('kpi_baselines.fields.baseline_description')}
                />
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label={t('kpi_baselines.fields.period_start')}
                    type="date"
                    value={capturePeriodStart}
                    onValueChange={setCapturePeriodStart}
                    variant="bordered"
                    description={t('kpi_baselines.fields.period_start_description')}
                  />
                  <Input
                    label={t('kpi_baselines.fields.period_end')}
                    type="date"
                    value={capturePeriodEnd}
                    onValueChange={setCapturePeriodEnd}
                    variant="bordered"
                    description={t('kpi_baselines.fields.period_end_description')}
                  />
                </div>
                <Textarea
                  label={t('kpi_baselines.fields.notes_source')}
                  placeholder={t('kpi_baselines.fields.notes_placeholder')}
                  value={captureNotes}
                  onValueChange={setCaptureNotes}
                  variant="bordered"
                  minRows={2}
                  description={t('kpi_baselines.fields.notes_description')}
                />
                <Divider />
                <p className="text-xs text-[var(--color-text-muted)]">
                  {t('kpi_baselines.modal.metrics_captured')}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} isDisabled={capturing}>
                  {t('kpi_baselines.actions.cancel')}
                </Button>
                <Button
                  color="primary"
                  onPress={handleCapture}
                  isLoading={capturing}
                  startContent={!capturing ? <Database size={15} /> : undefined}
                >
                  {t('kpi_baselines.actions.capture_now_short')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
