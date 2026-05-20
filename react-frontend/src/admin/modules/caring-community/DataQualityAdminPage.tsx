// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
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
  Tooltip,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import Eye from 'lucide-react/icons/eye';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type Severity = 'ok' | 'info' | 'warning' | 'danger';

interface DataQualityCheck {
  key: string;
  label: string;
  severity: Severity;
  count: number;
  message: string;
  has_drilldown: boolean;
}

interface DataQualityReport {
  generated_at: string;
  tenant_id: number;
  totals: Record<Severity, number>;
  checks: DataQualityCheck[];
}

interface AffectedRow {
  id: number;
  identifier?: string;
  name?: string;
  status?: string | null;
  created_at?: string | null;
}

interface AffectedRowsResponse {
  check_key: string;
  limit: number;
  rows: AffectedRow[];
  note?: string | null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const SEVERITY_CHIP_COLOR: Record<Severity, 'success' | 'default' | 'warning' | 'danger'> = {
  ok: 'success',
  info: 'default',
  warning: 'warning',
  danger: 'danger',
};

function formatTimestamp(ts: string | null | undefined): string {
  if (!ts) return '-';
  const d = new Date(ts);
  if (Number.isNaN(d.getTime())) return ts;
  return d.toLocaleString();
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function DataQualityAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('data_quality.meta.page_title'));
  const { showToast } = useToast();

  const [report, setReport] = useState<DataQualityReport | null>(null);
  const [loading, setLoading] = useState(true);

  const [drilldownKey, setDrilldownKey] = useState<string | null>(null);
  const [drilldownLabel, setDrilldownLabel] = useState<string>('');
  const [drilldownRows, setDrilldownRows] = useState<AffectedRow[] | null>(null);
  const [drilldownNote, setDrilldownNote] = useState<string | null>(null);
  const [drilldownLoading, setDrilldownLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<DataQualityReport>(
        '/v2/admin/caring-community/data-quality/dashboard',
      );
      setReport(res.data ?? null);
    } catch {
      showToast(t('data_quality.toasts.load_failed'), 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, t]);

  useEffect(() => {
    load();
  }, [load]);

  const openDrilldown = useCallback(
    async (check: DataQualityCheck) => {
      setDrilldownKey(check.key);
      setDrilldownLabel(check.label);
      setDrilldownRows(null);
      setDrilldownNote(null);
      setDrilldownLoading(true);
      try {
        const res = await api.get<AffectedRowsResponse>(
          `/v2/admin/caring-community/data-quality/checks/${encodeURIComponent(check.key)}/rows`,
        );
        const payload = res.data;
        if (payload) {
          setDrilldownRows(payload.rows ?? []);
          setDrilldownNote(payload.note ?? null);
        } else {
          setDrilldownRows([]);
        }
      } catch {
        showToast(t('data_quality.toasts.rows_failed'), 'error');
        setDrilldownRows([]);
      } finally {
        setDrilldownLoading(false);
      }
    },
    [showToast, t],
  );

  const closeDrilldown = useCallback(() => {
    setDrilldownKey(null);
    setDrilldownLabel('');
    setDrilldownRows(null);
    setDrilldownNote(null);
  }, []);

  const totals = report?.totals;
  const emptyValue = t('data_quality.empty.value');
  const severityLabel = useCallback((severity: Severity) => t(`data_quality.severity.${severity}`), [t]);

  const sortedChecks = useMemo<DataQualityCheck[]>(() => {
    if (!report) return [];
    const order: Record<Severity, number> = { danger: 0, warning: 1, info: 2, ok: 3 };
    return [...report.checks].sort((a, b) => order[a.severity] - order[b.severity]);
  }, [report]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('data_quality.meta.title')}
        subtitle={t('data_quality.meta.subtitle')}
        icon={<ClipboardCheck size={20} />}
        actions={
          <Tooltip content={t('data_quality.actions.refresh_checks')}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={load}
              isLoading={loading}
              aria-label={t('data_quality.actions.refresh_aria')}
            >
              <RefreshCw size={15} />
            </Button>
          </Tooltip>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('data_quality.about.title')}</p>
              <p className="text-default-600">
                {t('data_quality.about.body')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Severity legend */}
      <Card className="border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
        <CardBody className="py-3 px-4">
          <p className="text-xs font-semibold text-default-500 uppercase tracking-wide mb-2">{t('data_quality.severity_guide.title')}</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-sm">
            <div className="flex items-start gap-2">
              <Chip color="danger" variant="flat" size="sm" startContent={<ShieldAlert size={11} />}>{severityLabel('danger')}</Chip>
              <span className="text-default-600 text-xs">{t('data_quality.severity_guide.danger')}</span>
            </div>
            <div className="flex items-start gap-2">
              <Chip color="warning" variant="flat" size="sm" startContent={<AlertTriangle size={11} />}>{severityLabel('warning')}</Chip>
              <span className="text-default-600 text-xs">{t('data_quality.severity_guide.warning')}</span>
            </div>
            <div className="flex items-start gap-2">
              <Chip color="default" variant="flat" size="sm" startContent={<Info size={11} />}>{severityLabel('info')}</Chip>
              <span className="text-default-600 text-xs">{t('data_quality.severity_guide.info')}</span>
            </div>
            <div className="flex items-start gap-2">
              <Chip color="success" variant="flat" size="sm" startContent={<CheckCircle2 size={11} />}>{severityLabel('ok')}</Chip>
              <span className="text-default-600 text-xs">{t('data_quality.severity_guide.ok')}</span>
            </div>
          </div>
          <p className="text-xs text-default-500 mt-2">
            {t('data_quality.severity_guide.note_prefix')} <strong>{t('data_quality.actions.view_affected_rows')}</strong>{' '}
            {t('data_quality.severity_guide.note_suffix')}
          </p>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {report && !loading && (
        <>
          {/* Summary chip row */}
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm text-default-500">{t('data_quality.summary.label')}</span>
            <Chip color="danger" variant="flat" startContent={<ShieldAlert size={12} />} size="sm">
              {t('data_quality.summary.count', { count: totals?.danger ?? 0, label: severityLabel('danger') })}
            </Chip>
            <Chip
              color="warning"
              variant="flat"
              startContent={<AlertTriangle size={12} />}
              size="sm"
            >
              {t('data_quality.summary.count', { count: totals?.warning ?? 0, label: severityLabel('warning') })}
            </Chip>
            <Chip color="default" variant="flat" startContent={<Info size={12} />} size="sm">
              {t('data_quality.summary.count', { count: totals?.info ?? 0, label: severityLabel('info') })}
            </Chip>
            <Chip
              color="success"
              variant="flat"
              startContent={<CheckCircle2 size={12} />}
              size="sm"
            >
              {t('data_quality.summary.count', { count: totals?.ok ?? 0, label: severityLabel('ok') })}
            </Chip>
            <span className="ml-auto text-xs text-default-400">
              {t('data_quality.summary.generated', { date: formatTimestamp(report.generated_at) })}
            </span>
          </div>

          {/* Check cards grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {sortedChecks.map((check) => (
              <Card
                key={check.key}
                className="border border-[var(--color-border)] bg-[var(--color-surface)]"
              >
                <CardHeader className="flex items-start justify-between gap-3 pb-2">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-foreground">{check.label}</p>
                    <p className="mt-0.5 text-xs text-default-400">{check.key}</p>
                  </div>
                  <Chip
                    color={SEVERITY_CHIP_COLOR[check.severity]}
                    variant="flat"
                    size="sm"
                  >
                    {severityLabel(check.severity)}
                  </Chip>
                </CardHeader>
                <Divider />
                <CardBody className="space-y-3 pt-3">
                  <div className="flex items-end gap-2">
                    <span className="text-3xl font-extrabold text-foreground">
                      {check.count.toLocaleString()}
                    </span>
                    <span className="pb-1 text-xs text-default-500">{t('data_quality.summary.affected')}</span>
                  </div>
                  <p className="text-sm text-default-600">{check.message}</p>
                  {check.has_drilldown && (
                    <Button
                      size="sm"
                      variant="flat"
                      color="primary"
                      startContent={<Eye size={14} />}
                      onPress={() => openDrilldown(check)}
                    >
                      {t('data_quality.actions.view_affected_rows')}
                    </Button>
                  )}
                </CardBody>
              </Card>
            ))}
          </div>
        </>
      )}

      {/* Drill-down modal */}
      <Modal
        isOpen={drilldownKey !== null}
        onClose={closeDrilldown}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex flex-col gap-1">
            <span className="text-base font-semibold">{drilldownLabel}</span>
            <span className="text-xs text-default-400">
              {t('data_quality.drilldown.subtitle')}
            </span>
          </ModalHeader>
          <ModalBody>
            {drilldownLoading && (
              <div className="flex justify-center py-12">
                <Spinner size="lg" />
              </div>
            )}

            {!drilldownLoading && drilldownNote && (
              <div className="mb-3 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-3 text-sm text-default-600">
                {drilldownNote}
              </div>
            )}

            {!drilldownLoading && drilldownRows && drilldownRows.length === 0 && !drilldownNote && (
              <p className="py-6 text-center text-sm text-default-500">{t('data_quality.drilldown.empty')}</p>
            )}

            {!drilldownLoading && drilldownRows && drilldownRows.length > 0 && (
              <Table aria-label={t('data_quality.drilldown.table_aria')} removeWrapper>
                <TableHeader>
                  <TableColumn>{t('data_quality.table.id')}</TableColumn>
                  <TableColumn>{t('data_quality.table.identifier')}</TableColumn>
                  <TableColumn>{t('data_quality.table.name')}</TableColumn>
                  <TableColumn>{t('data_quality.table.status')}</TableColumn>
                  <TableColumn>{t('data_quality.table.created')}</TableColumn>
                </TableHeader>
                <TableBody emptyContent={t('data_quality.empty.rows')}>
                  {drilldownRows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.id}</TableCell>
                      <TableCell>{row.identifier ?? emptyValue}</TableCell>
                      <TableCell>{row.name ?? emptyValue}</TableCell>
                      <TableCell>{row.status ?? emptyValue}</TableCell>
                      <TableCell>{formatTimestamp(row.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeDrilldown}>
              {t('data_quality.actions.close')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
