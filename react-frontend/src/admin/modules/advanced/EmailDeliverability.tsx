// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Alert,
  Input,
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Mail from 'lucide-react/icons/mail';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import Search from 'lucide-react/icons/search';
import Trash2 from 'lucide-react/icons/trash-2';
import { api } from '@/lib/api';
import { useToast } from '@/contexts/ToastContext';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useTranslation } from 'react-i18next';

interface EmailWarning {
  code: string;
  severity: 'info' | 'warning' | 'critical';
  message_key: string;
  params?: Record<string, unknown>;
}

interface SummaryData {
  window_days: number;
  total: number;
  by_status: Record<string, number>;
  delivered_pct: number | null;
  accepted_pct?: number | null;
  unconfirmed_sent?: number;
  bounced_pct: number | null;
  warnings?: EmailWarning[];
  trigger_audit?: {
    score: number;
    issue_count: number;
    matrix_count: number;
  };
}

interface LogRow {
  id: number;
  user_id: number | null;
  recipient_email: string;
  category: string | null;
  subject: string | null;
  status: string;
  provider_message_id: string | null;
  error: string | null;
  sent_at: string | null;
  delivered_at: string | null;
  bounced_at: string | null;
  opened_at: string | null;
  created_at: string;
}

interface SuppressionRow {
  id: number;
  email: string;
  reason: string;
  detail: string | null;
  suppressed_at: string;
}

const STATUS_COLORS: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'primary'> = {
  queued: 'default',
  sent: 'primary',
  delivered: 'success',
  failed: 'danger',
  bounced: 'danger',
  suppressed: 'warning',
};

/**
 * Admin email deliverability dashboard.
 *
 * Answers the operational question "did Joe Bloggs get his welcome email?"
 * by exposing the email_log + email_suppression tables to admins through
 * a tenant-scoped UI. Backed by AdminEmailDeliverabilityController.
 */
export default function EmailDeliverability() {
  usePageTitle('Email Deliverability');
  const toast = useToast();
  const { t } = useTranslation('admin');

  const [summary, setSummary] = useState<SummaryData | null>(null);
  const [summaryDays, setSummaryDays] = useState(7);
  const [loadingSummary, setLoadingSummary] = useState(true);

  const [logRows, setLogRows] = useState<LogRow[]>([]);
  const [logSearch, setLogSearch] = useState('');
  const [logStatus, setLogStatus] = useState('');
  const [loadingLogs, setLoadingLogs] = useState(false);

  const [suppRows, setSuppRows] = useState<SuppressionRow[]>([]);
  const [suppSearch, setSuppSearch] = useState('');
  const [suppReason, setSuppReason] = useState('');
  const [loadingSupp, setLoadingSupp] = useState(false);

  const loadSummary = async () => {
    setLoadingSummary(true);
    try {
      const r = await api.get<SummaryData>('/v2/admin/email-deliverability/summary', { params: { days: summaryDays } });
      if (r.success && r.data) setSummary(r.data);
    } finally {
      setLoadingSummary(false);
    }
  };

  const loadLogs = async () => {
    setLoadingLogs(true);
    try {
      const r = await api.get<{ rows: LogRow[]; total: number }>('/v2/admin/email-deliverability/logs', {
        params: { email: logSearch || undefined, status: logStatus || undefined, limit: 50 },
      });
      if (r.success && r.data) setLogRows(r.data.rows ?? []);
    } finally {
      setLoadingLogs(false);
    }
  };

  const loadSuppressions = async () => {
    setLoadingSupp(true);
    try {
      const r = await api.get<{ rows: SuppressionRow[]; total: number }>(
        '/v2/admin/email-deliverability/suppressions',
        { params: { email: suppSearch || undefined, reason: suppReason || undefined, limit: 50 } }
      );
      if (r.success && r.data) setSuppRows(r.data.rows ?? []);
    } finally {
      setLoadingSupp(false);
    }
  };

  useEffect(() => { loadSummary(); }, [summaryDays]);
  useEffect(() => { loadLogs(); }, []);
  useEffect(() => { loadSuppressions(); }, []);

  const removeSuppression = async (id: number, email: string) => {
    if (!confirm(`Remove ${email} from the suppression list? They will be eligible to receive email again.`)) return;
    const r = await api.delete<{ removed: boolean }>(`/v2/admin/email-deliverability/suppressions/${id}`);
    if (r.success) {
      toast.success(`Removed ${email}`);
      loadSuppressions();
    } else {
      toast.error(r.error || 'Failed to remove suppression.');
    }
  };

  const statusChip = (status: string) => (
    <Chip size="sm" color={STATUS_COLORS[status] ?? 'default'} variant="flat">
      {status}
    </Chip>
  );

  const statuses = useMemo(() => Object.keys(summary?.by_status ?? {}), [summary]);
  const warnings = summary?.warnings ?? [];

  return (
    <div className="space-y-6 p-4">
      <div className="flex items-center gap-3">
        <Mail className="w-6 h-6 text-theme-primary" />
        <h1 className="text-2xl font-bold text-theme-primary">Email Deliverability</h1>
      </div>

      {warnings.length > 0 && (
        <div className="space-y-2">
          {warnings.map((warning) => (
            <Alert
              key={`${warning.code}-${warning.severity}`}
              color={warning.severity === 'critical' ? 'danger' : warning.severity === 'warning' ? 'warning' : 'primary'}
              variant="flat"
              startContent={<AlertTriangle className="h-4 w-4" />}
              title={t(`email_deliverability.warnings.${warning.code}.title`, {
                defaultValue: t('email_deliverability.warnings.default_title'),
              })}
              description={t(`email_deliverability.warnings.${warning.code}.body`, {
                ...(warning.params ?? {}),
                defaultValue: t('email_deliverability.warnings.default_body'),
              })}
            />
          ))}
        </div>
      )}

      {/* Summary card */}
      <Card>
        <CardHeader className="flex justify-between">
          <span>Headline metrics</span>
          <div className="flex gap-2 items-center">
            <Select
              size="sm"
              className="w-32"
              selectedKeys={[String(summaryDays)]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) setSummaryDays(Number(v));
              }}
            >
              <SelectItem key="1">Last 24h</SelectItem>
              <SelectItem key="7">Last 7 days</SelectItem>
              <SelectItem key="30">Last 30 days</SelectItem>
              <SelectItem key="90">Last 90 days</SelectItem>
            </Select>
            <Button size="sm" variant="flat" onPress={loadSummary} isIconOnly aria-label="Refresh">
              <RotateCcw className="w-4 h-4" />
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          {loadingSummary ? (
            <Spinner />
          ) : summary ? (
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              <div>
                <div className="text-xs text-theme-subtle uppercase">Total</div>
                <div className="text-2xl font-bold">{summary.total}</div>
              </div>
              <div>
                <div className="text-xs text-theme-subtle uppercase">
                  {t('email_deliverability.trigger_score')}
                </div>
                <div className="text-2xl font-bold">
                  {summary.trigger_audit ? `${summary.trigger_audit.score}/1000` : '-'}
                </div>
                {summary.trigger_audit && (
                  <div className="text-xs text-theme-subtle">
                    {t('email_deliverability.trigger_coverage', {
                      issues: summary.trigger_audit.issue_count,
                      count: summary.trigger_audit.matrix_count,
                    })}
                  </div>
                )}
              </div>
              <div>
                <div className="text-xs text-theme-subtle uppercase">Delivered</div>
                <div className="text-2xl font-bold text-[var(--color-success)]">
                  {summary.delivered_pct !== null ? `${summary.delivered_pct}%` : '—'}
                </div>
              </div>
              <div>
                <div className="text-xs text-theme-subtle uppercase">Bounced</div>
                <div className="text-2xl font-bold text-[var(--color-error)]">
                  {summary.bounced_pct !== null ? `${summary.bounced_pct}%` : '—'}
                </div>
              </div>
              <div>
                <div className="text-xs text-theme-subtle uppercase">By status</div>
                <div className="flex flex-wrap gap-1 mt-1">
                  {statuses.map((s) => (
                    <Chip key={s} size="sm" color={STATUS_COLORS[s] ?? 'default'} variant="flat">
                      {s}: {summary.by_status[s]}
                    </Chip>
                  ))}
                </div>
              </div>
            </div>
          ) : (
            <div className="text-theme-subtle">No data.</div>
          )}
        </CardBody>
      </Card>

      {/* Log table */}
      <Card>
        <CardHeader className="flex justify-between gap-2 flex-wrap">
          <span>Recent emails</span>
          <div className="flex gap-2 items-center">
            <Input
              size="sm"
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              placeholder="Filter by recipient email"
              value={logSearch}
              onValueChange={setLogSearch}
              className="w-64"
            />
            <Select
              size="sm"
              placeholder="Status"
              className="w-32"
              selectedKeys={logStatus ? [logStatus] : []}
              onSelectionChange={(keys) => setLogStatus((Array.from(keys)[0] as string) ?? '')}
            >
              <SelectItem key="">All</SelectItem>
              <SelectItem key="sent">Sent</SelectItem>
              <SelectItem key="delivered">Delivered</SelectItem>
              <SelectItem key="bounced">Bounced</SelectItem>
              <SelectItem key="failed">Failed</SelectItem>
              <SelectItem key="suppressed">Suppressed</SelectItem>
            </Select>
            <Button size="sm" color="primary" onPress={loadLogs}>Search</Button>
          </div>
        </CardHeader>
        <CardBody>
          {loadingLogs ? <Spinner /> : (
            <Table aria-label="Email log" removeWrapper>
              <TableHeader>
                <TableColumn>Recipient</TableColumn>
                <TableColumn>Subject</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Sent</TableColumn>
                <TableColumn>Delivered</TableColumn>
                <TableColumn>Opened</TableColumn>
                <TableColumn>Error</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No emails match the filter.">
                {logRows.map((r) => (
                  <TableRow key={r.id}>
                    <TableCell className="font-mono text-xs">{r.recipient_email}</TableCell>
                    <TableCell className="max-w-xs truncate">{r.subject ?? '—'}</TableCell>
                    <TableCell>{statusChip(r.status)}</TableCell>
                    <TableCell className="text-xs">{r.sent_at ?? '—'}</TableCell>
                    <TableCell className="text-xs">{r.delivered_at ?? '—'}</TableCell>
                    <TableCell className="text-xs">{r.opened_at ?? '—'}</TableCell>
                    <TableCell className="text-xs text-[var(--color-error)] max-w-xs truncate">{r.error ?? ''}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Suppression list */}
      <Card>
        <CardHeader className="flex justify-between gap-2 flex-wrap">
          <span>Suppression list (platform-wide)</span>
          <div className="flex gap-2 items-center">
            <Input
              size="sm"
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              placeholder="Filter by email"
              value={suppSearch}
              onValueChange={setSuppSearch}
              className="w-64"
            />
            <Select
              size="sm"
              placeholder="Reason"
              className="w-40"
              selectedKeys={suppReason ? [suppReason] : []}
              onSelectionChange={(keys) => setSuppReason((Array.from(keys)[0] as string) ?? '')}
            >
              <SelectItem key="">All</SelectItem>
              <SelectItem key="bounce">Bounce</SelectItem>
              <SelectItem key="block">Block</SelectItem>
              <SelectItem key="invalid">Invalid</SelectItem>
              <SelectItem key="spam_report">Spam report</SelectItem>
              <SelectItem key="unsubscribe">Unsubscribe</SelectItem>
            </Select>
            <Button size="sm" color="primary" onPress={loadSuppressions}>Search</Button>
          </div>
        </CardHeader>
        <CardBody>
          {loadingSupp ? <Spinner /> : (
            <Table aria-label="Suppression list" removeWrapper>
              <TableHeader>
                <TableColumn>Email</TableColumn>
                <TableColumn>Reason</TableColumn>
                <TableColumn>Detail</TableColumn>
                <TableColumn>Suppressed at</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody emptyContent="Suppression cache is empty.">
                {suppRows.map((r) => (
                  <TableRow key={r.id}>
                    <TableCell className="font-mono text-xs">{r.email}</TableCell>
                    <TableCell><Chip size="sm" variant="flat">{r.reason}</Chip></TableCell>
                    <TableCell className="max-w-xs truncate">{r.detail ?? '—'}</TableCell>
                    <TableCell className="text-xs">{r.suppressed_at}</TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="flat"
                        color="danger"
                        startContent={<Trash2 className="w-3 h-3" />}
                        onPress={() => removeSuppression(r.id, r.email)}
                      >
                        Remove
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
