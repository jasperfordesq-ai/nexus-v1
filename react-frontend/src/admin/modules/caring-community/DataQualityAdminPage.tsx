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

const SEVERITY_LABEL: Record<Severity, string> = {
  ok: 'OK',
  info: 'Info',
  warning: 'Warning',
  danger: 'Danger',
};

function formatTimestamp(ts: string | null | undefined): string {
  if (!ts) return '—';
  const d = new Date(ts);
  if (Number.isNaN(d.getTime())) return ts;
  return d.toLocaleString();
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function DataQualityAdminPage() {
  usePageTitle('Pilot Data Quality');
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
      showToast('Failed to load data-quality report', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

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
        showToast('Failed to load affected rows', 'error');
        setDrilldownRows([]);
      } finally {
        setDrilldownLoading(false);
      }
    },
    [showToast],
  );

  const closeDrilldown = useCallback(() => {
    setDrilldownKey(null);
    setDrilldownLabel('');
    setDrilldownRows(null);
    setDrilldownNote(null);
  }, []);

  const totals = report?.totals;

  const sortedChecks = useMemo<DataQualityCheck[]>(() => {
    if (!report) return [];
    const order: Record<Severity, number> = { danger: 0, warning: 1, info: 2, ok: 3 };
    return [...report.checks].sort((a, b) => order[a.severity] - order[b.severity]);
  }, [report]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pilot Data Quality"
        subtitle="AG84 — readiness checks before onboarding real residents"
        icon={<ClipboardCheck size={20} />}
        actions={
          <Tooltip content="Refresh checks">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={load}
              isLoading={loading}
              aria-label="Refresh"
            >
              <RefreshCw size={15} />
            </Button>
          </Tooltip>
        }
      />

      {/* Methodology / context note */}
      <Card className="border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
        <CardBody className="flex flex-row items-start gap-3 py-3">
          <Info size={16} className="mt-0.5 shrink-0 text-default-500" />
          <p className="text-sm text-default-600">
            These checks identify issues to resolve before launching a real pilot from this tenant.
            Run repeatedly during pre-launch — every red or amber row should be reviewed and either
            fixed or knowingly accepted before residents are onboarded.
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
            <span className="text-sm text-default-500">Summary:</span>
            <Chip color="danger" variant="flat" startContent={<ShieldAlert size={12} />} size="sm">
              {totals?.danger ?? 0} danger
            </Chip>
            <Chip
              color="warning"
              variant="flat"
              startContent={<AlertTriangle size={12} />}
              size="sm"
            >
              {totals?.warning ?? 0} warning
            </Chip>
            <Chip color="default" variant="flat" startContent={<Info size={12} />} size="sm">
              {totals?.info ?? 0} info
            </Chip>
            <Chip
              color="success"
              variant="flat"
              startContent={<CheckCircle2 size={12} />}
              size="sm"
            >
              {totals?.ok ?? 0} OK
            </Chip>
            <span className="ml-auto text-xs text-default-400">
              Generated {formatTimestamp(report.generated_at)}
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
                    {SEVERITY_LABEL[check.severity]}
                  </Chip>
                </CardHeader>
                <Divider />
                <CardBody className="space-y-3 pt-3">
                  <div className="flex items-end gap-2">
                    <span className="text-3xl font-extrabold text-foreground">
                      {check.count.toLocaleString()}
                    </span>
                    <span className="pb-1 text-xs text-default-500">affected</span>
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
                      View affected rows
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
              Up to 50 affected rows — resolve or document before launch.
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
              <p className="py-6 text-center text-sm text-default-500">No affected rows to show.</p>
            )}

            {!drilldownLoading && drilldownRows && drilldownRows.length > 0 && (
              <Table aria-label="Affected rows" removeWrapper>
                <TableHeader>
                  <TableColumn>ID</TableColumn>
                  <TableColumn>Identifier</TableColumn>
                  <TableColumn>Name</TableColumn>
                  <TableColumn>Status</TableColumn>
                  <TableColumn>Created</TableColumn>
                </TableHeader>
                <TableBody emptyContent="No rows">
                  {drilldownRows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.id}</TableCell>
                      <TableCell>{row.identifier ?? '—'}</TableCell>
                      <TableCell>{row.name ?? '—'}</TableCell>
                      <TableCell>{row.status ?? '—'}</TableCell>
                      <TableCell>{formatTimestamp(row.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeDrilldown}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
