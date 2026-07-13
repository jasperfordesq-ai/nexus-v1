// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import FileWarning from 'lucide-react/icons/file-warning';
import Gavel from 'lucide-react/icons/gavel';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldCheck from 'lucide-react/icons/shield-check';
import {
  Button,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Radio,
  RadioGroup,
  Tab,
  Tabs,
  Textarea,
} from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { getFormattingLocale } from '@/lib/helpers';
import type { MarketplaceDisputeCase, MarketplaceReportCase } from '@/types/marketplaceCases';
import { normalizeMarketplaceCasePage } from '@/types/marketplaceCases';
import { PageHeader } from '../../components/PageHeader';
import { DataTable, type Column } from '../../components/DataTable';
import { EmptyState } from '../../components/EmptyState';

const STATUS_COLORS: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'primary'> = {
  received: 'warning',
  acknowledged: 'primary',
  under_review: 'primary',
  action_taken: 'danger',
  no_action: 'success',
  appealed: 'warning',
  appeal_resolved: 'success',
  open: 'warning',
  escalated: 'danger',
  resolved_buyer: 'success',
  resolved_seller: 'success',
  closed: 'default',
};

const REPORT_RESOLUTIONS = ['none', 'warning', 'listing_removed', 'seller_suspended'] as const;
const DISPUTE_RESOLUTIONS = ['buyer', 'seller', 'closed'] as const;

function dateLabel(value: string | null | undefined, fallback: string): string {
  return value ? new Date(value).toLocaleString(getFormattingLocale()) : fallback;
}

function disputeOpener(dispute: MarketplaceDisputeCase): string {
  if (dispute.opened_by_user?.name) return dispute.opened_by_user.name;
  if (typeof dispute.opened_by === 'object' && dispute.opened_by?.name) return dispute.opened_by.name;
  return '';
}

function safeEvidenceUrl(value: string): string | null {
  try {
    const parsed = new URL(value);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.href : null;
  } catch {
    return null;
  }
}

function EvidenceLink({ url }: { url: string }) {
  const href = safeEvidenceUrl(url);
  return (
    <li>
      {href ? (
        <a className="text-accent underline" href={href} target="_blank" rel="noopener noreferrer">{url}</a>
      ) : (
        <span>{url}</span>
      )}
    </li>
  );
}

function moneyLabel(value: number | string | null | undefined, currency: string | null | undefined): string {
  const amount = Number(value ?? 0);
  const code = (currency || '').toUpperCase();
  try {
    return new Intl.NumberFormat(getFormattingLocale(), { style: 'currency', currency: code }).format(amount);
  } catch {
    return `${code} ${amount}`.trim();
  }
}

export function MarketplaceCasesPage() {
  const { t } = useTranslation('marketplace_cases');
  const toast = useToast();
  usePageTitle(t('admin.page_title'));

  const [selectedTab, setSelectedTab] = useState('reports');
  const [reports, setReports] = useState<MarketplaceReportCase[]>([]);
  const [disputes, setDisputes] = useState<MarketplaceDisputeCase[]>([]);
  const [reportTotal, setReportTotal] = useState(0);
  const [disputeTotal, setDisputeTotal] = useState(0);
  const [reportPage, setReportPage] = useState(1);
  const [disputePage, setDisputePage] = useState(1);
  const [reportsLoading, setReportsLoading] = useState(true);
  const [disputesLoading, setDisputesLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [reportTarget, setReportTarget] = useState<MarketplaceReportCase | null>(null);
  const [disputeTarget, setDisputeTarget] = useState<MarketplaceDisputeCase | null>(null);
  const [reportResolution, setReportResolution] = useState<(typeof REPORT_RESOLUTIONS)[number]>('none');
  const [disputeResolution, setDisputeResolution] = useState<(typeof DISPUTE_RESOLUTIONS)[number]>('buyer');
  const [resolutionNotes, setResolutionNotes] = useState('');
  const [refundAmount, setRefundAmount] = useState('');

  const loadReports = useCallback(async () => {
    setReportsLoading(true);
    try {
      const response = await api.get<unknown>(`/v2/admin/marketplace/reports?page=${reportPage}&per_page=20`);
      if (!response.success) {
        toast.error(response.error || t('admin.load_reports_error'));
        return;
      }
      const page = normalizeMarketplaceCasePage<MarketplaceReportCase>(response.data);
      setReports(page.items);
      setReportTotal(typeof response.meta?.total === 'number' ? response.meta.total : page.total);
    } catch {
      toast.error(t('admin.load_reports_error'));
    } finally {
      setReportsLoading(false);
    }
  }, [reportPage, t, toast]);

  const loadDisputes = useCallback(async () => {
    setDisputesLoading(true);
    try {
      const response = await api.get<unknown>(`/v2/admin/marketplace/disputes?page=${disputePage}&per_page=20`);
      if (!response.success) {
        toast.error(response.error || t('admin.load_disputes_error'));
        return;
      }
      const page = normalizeMarketplaceCasePage<MarketplaceDisputeCase>(response.data);
      setDisputes(page.items);
      setDisputeTotal(typeof response.meta?.total === 'number' ? response.meta.total : page.total);
    } catch {
      toast.error(t('admin.load_disputes_error'));
    } finally {
      setDisputesLoading(false);
    }
  }, [disputePage, t, toast]);

  useEffect(() => { void loadReports(); }, [loadReports]);
  useEffect(() => { void loadDisputes(); }, [loadDisputes]);

  const acknowledgeReport = useCallback(async (report: MarketplaceReportCase) => {
    setActionLoading(true);
    try {
      const response = await api.post(`/v2/admin/marketplace/reports/${report.id}/acknowledge`);
      if (!response.success) {
        toast.error(response.error || t('admin.acknowledge_error'));
        return;
      }
      toast.success(t('admin.acknowledged'));
      await loadReports();
    } catch {
      toast.error(t('admin.acknowledge_error'));
    } finally {
      setActionLoading(false);
    }
  }, [loadReports, t, toast]);

  const openReportResolution = useCallback((report: MarketplaceReportCase) => {
    setReportTarget(report);
    setReportResolution(report.action_taken === 'warning' || report.action_taken === 'listing_removed' || report.action_taken === 'seller_suspended'
      ? report.action_taken
      : 'none');
    setResolutionNotes(report.resolution_reason || '');
  }, []);

  const submitReportResolution = async () => {
    if (!reportTarget || resolutionNotes.trim().length < 5) return;
    setActionLoading(true);
    try {
      const suffix = reportTarget.status === 'appealed' ? 'resolve-appeal' : 'resolve';
      const response = await api.put(`/v2/admin/marketplace/reports/${reportTarget.id}/${suffix}`, {
        action_taken: reportResolution,
        resolution_reason: resolutionNotes.trim(),
      });
      if (!response.success) {
        toast.error(response.error || t('admin.resolve_error'));
        return;
      }
      toast.success(t('admin.resolve_success'));
      setReportTarget(null);
      setResolutionNotes('');
      await loadReports();
    } catch {
      toast.error(t('admin.resolve_error'));
    } finally {
      setActionLoading(false);
    }
  };

  const openDisputeResolution = (dispute: MarketplaceDisputeCase) => {
    setDisputeTarget(dispute);
    setDisputeResolution('buyer');
    setResolutionNotes(dispute.resolution_notes || '');
    setRefundAmount(dispute.refund_amount == null ? '' : String(dispute.refund_amount));
  };

  const submitDisputeResolution = async () => {
    if (!disputeTarget || resolutionNotes.trim().length < 5) return;
    const parsedRefund = refundAmount.trim() === '' ? undefined : Number(refundAmount);
    if (parsedRefund !== undefined && (!Number.isFinite(parsedRefund) || parsedRefund < 0.01)) return;

    setActionLoading(true);
    try {
      const response = await api.put(`/v2/admin/marketplace/disputes/${disputeTarget.id}/resolve`, {
        resolution: disputeResolution,
        resolution_notes: resolutionNotes.trim(),
        ...(parsedRefund === undefined ? {} : { refund_amount: parsedRefund }),
      });
      if (!response.success) {
        toast.error(response.error || t('admin.resolve_error'));
        return;
      }
      toast.success(t('admin.resolve_success'));
      setDisputeTarget(null);
      setResolutionNotes('');
      setRefundAmount('');
      await loadDisputes();
    } catch {
      toast.error(t('admin.resolve_error'));
    } finally {
      setActionLoading(false);
    }
  };

  const reportColumns = useMemo<Column<MarketplaceReportCase>[]>(() => [
    {
      key: 'id',
      label: t('admin.columns.report'),
      render: (report) => <span className="font-medium text-foreground">#{report.id}</span>,
    },
    {
      key: 'listing',
      label: t('admin.columns.listing'),
      render: (report) => <span className="text-sm text-foreground">{report.listing?.title || `#${report.marketplace_listing_id}`}</span>,
    },
    {
      key: 'reporter',
      label: t('admin.columns.reporter'),
      render: (report) => <span className="text-sm text-muted">{report.reporter?.name || t('common.not_available')}</span>,
    },
    {
      key: 'reason',
      label: t('admin.columns.reason'),
      render: (report) => <span className="text-sm text-muted">{t(`reason.${report.reason}`, { defaultValue: report.reason })}</span>,
    },
    {
      key: 'status',
      label: t('admin.columns.status'),
      render: (report) => (
        <Chip size="sm" color={STATUS_COLORS[report.status] || 'default'} variant="soft">
          {t(`status.${report.status}`, { defaultValue: report.status })}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: t('admin.columns.created'),
      render: (report) => <span className="text-sm text-muted">{dateLabel(report.created_at, t('common.not_available'))}</span>,
    },
    {
      key: 'actions',
      label: t('admin.columns.actions'),
      render: (report) => (
        <div className="flex flex-wrap gap-2">
          {report.status === 'received' && (
            <Button size="sm" variant="secondary" isDisabled={actionLoading} onPress={() => void acknowledgeReport(report)}>
              <ShieldCheck className="size-4" aria-hidden="true" />
              {t('admin.acknowledge')}
            </Button>
          )}
          {['received', 'acknowledged', 'under_review', 'appealed'].includes(report.status) && (
            <Button size="sm" variant="tertiary" isDisabled={actionLoading} onPress={() => openReportResolution(report)}>
              <Gavel className="size-4" aria-hidden="true" />
              {report.status === 'appealed' ? t('admin.resolve_appeal') : t('admin.resolve')}
            </Button>
          )}
        </div>
      ),
    },
  ], [acknowledgeReport, actionLoading, openReportResolution, t]);

  const disputeColumns = useMemo<Column<MarketplaceDisputeCase>[]>(() => [
    {
      key: 'id',
      label: t('admin.columns.dispute'),
      render: (dispute) => <span className="font-medium text-foreground">#{dispute.id}</span>,
    },
    {
      key: 'order_id',
      label: t('admin.columns.order'),
      render: (dispute) => <span className="text-sm text-foreground">{dispute.order?.order_number || `#${dispute.order_id}`}</span>,
    },
    {
      key: 'opened_by',
      label: t('admin.columns.opened_by'),
      render: (dispute) => <span className="text-sm text-muted">{disputeOpener(dispute) || t('common.not_available')}</span>,
    },
    {
      key: 'reason',
      label: t('admin.columns.reason'),
      render: (dispute) => <span className="text-sm text-muted">{t(`reason.${dispute.reason}`, { defaultValue: dispute.reason })}</span>,
    },
    {
      key: 'status',
      label: t('admin.columns.status'),
      render: (dispute) => (
        <Chip size="sm" color={STATUS_COLORS[dispute.status] || 'default'} variant="soft">
          {t(`status.${dispute.status}`, { defaultValue: dispute.status })}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: t('admin.columns.created'),
      render: (dispute) => <span className="text-sm text-muted">{dateLabel(dispute.created_at, t('common.not_available'))}</span>,
    },
    {
      key: 'actions',
      label: t('admin.columns.actions'),
      render: (dispute) => (
        ['open', 'under_review', 'escalated'].includes(dispute.status) ? (
          <Button size="sm" variant="tertiary" isDisabled={actionLoading} onPress={() => openDisputeResolution(dispute)}>
            <Gavel className="size-4" aria-hidden="true" />
            {t('admin.resolve')}
          </Button>
        ) : null
      ),
    },
  ], [actionLoading, t]);

  return (
    <div>
      <PageHeader
        title={t('admin.title')}
        description={t('admin.subtitle')}
        icon={<Gavel className="size-5" aria-hidden="true" />}
        actions={(
          <Button variant="secondary" onPress={() => void Promise.all([loadReports(), loadDisputes()])}>
            <RefreshCw className="size-4" aria-hidden="true" />
            {t('common.refresh')}
          </Button>
        )}
      />

      <Tabs aria-label={t('admin.tabs_aria')} selectedKey={selectedTab} onSelectionChange={(key) => setSelectedTab(String(key))}>
        <Tab key="reports" title={t('admin.tab_reports')}>
          <div className="pt-4">
            <DataTable
              columns={reportColumns}
              data={reports}
              isLoading={reportsLoading}
              searchable={false}
              totalItems={reportTotal}
              page={reportPage}
              pageSize={20}
              onPageChange={setReportPage}
              onRefresh={loadReports}
              emptyContent={<EmptyState icon={FileWarning} title={t('admin.empty_reports')} description={t('admin.empty_reports_description')} />}
            />
          </div>
        </Tab>
        <Tab key="disputes" title={t('admin.tab_disputes')}>
          <div className="pt-4">
            <DataTable
              columns={disputeColumns}
              data={disputes}
              isLoading={disputesLoading}
              searchable={false}
              totalItems={disputeTotal}
              page={disputePage}
              pageSize={20}
              onPageChange={setDisputePage}
              onRefresh={loadDisputes}
              emptyContent={<EmptyState icon={Gavel} title={t('admin.empty_disputes')} description={t('admin.empty_disputes_description')} />}
            />
          </div>
        </Tab>
      </Tabs>

      <Modal isOpen={Boolean(reportTarget)} onClose={() => setReportTarget(null)} size="lg">
        <ModalContent>
          <ModalHeader>{reportTarget?.status === 'appealed' ? t('admin.resolve_appeal_title') : t('admin.resolve_report_title')}</ModalHeader>
          <ModalBody className="space-y-4">
            {reportTarget && (
              <div className="space-y-3 rounded-lg border border-divider bg-surface-secondary p-4 text-sm">
                <div><span className="font-semibold">{t('common.listing')}:</span> {reportTarget.listing?.title || `#${reportTarget.marketplace_listing_id}`}</div>
                <div><span className="font-semibold">{t('common.reason')}:</span> {t(`reason.${reportTarget.reason}`, { defaultValue: reportTarget.reason })}</div>
                <div>
                  <p className="font-semibold">{t('admin.case_description')}</p>
                  <p className="whitespace-pre-wrap text-muted">{reportTarget.description || t('common.not_available')}</p>
                </div>
                {reportTarget.appeal_text && (
                  <div>
                    <p className="font-semibold">{t('admin.appeal_text')}</p>
                    <p className="whitespace-pre-wrap text-muted">{reportTarget.appeal_text}</p>
                  </div>
                )}
                {reportTarget.evidence_urls && reportTarget.evidence_urls.length > 0 && (
                  <div>
                    <p className="font-semibold">{t('admin.evidence')}</p>
                    <ul className="list-disc space-y-1 pl-5">
                      {reportTarget.evidence_urls.map((url) => <EvidenceLink key={url} url={url} />)}
                    </ul>
                  </div>
                )}
              </div>
            )}
            <RadioGroup label={t('common.action')} value={reportResolution} onValueChange={(value) => setReportResolution(value as typeof reportResolution)}>
              {REPORT_RESOLUTIONS.map((value) => <Radio key={value} value={value}>{t(`action.${value}`)}</Radio>)}
            </RadioGroup>
            <Textarea
              label={t('admin.resolution_reason')}
              placeholder={t('admin.resolution_reason_placeholder')}
              value={resolutionNotes}
              onValueChange={setResolutionNotes}
              minRows={4}
              isRequired
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" isDisabled={actionLoading} onPress={() => setReportTarget(null)}>{t('common.cancel')}</Button>
            <Button isPending={actionLoading} isDisabled={resolutionNotes.trim().length < 5} onPress={() => void submitReportResolution()}>{t('admin.resolve')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal isOpen={Boolean(disputeTarget)} onClose={() => setDisputeTarget(null)} size="lg">
        <ModalContent>
          <ModalHeader>{t('admin.resolve_dispute_title')}</ModalHeader>
          <ModalBody className="space-y-4">
            {disputeTarget && (
              <div className="space-y-3 rounded-lg border border-divider bg-surface-secondary p-4 text-sm">
                <div><span className="font-semibold">{t('admin.columns.order')}:</span> {disputeTarget.order?.order_number || `#${disputeTarget.order_id}`}</div>
                <div><span className="font-semibold">{t('admin.order_value')}:</span> {disputeTarget.order?.time_credits_used
                  ? t('admin.time_credit_value', { count: Number(disputeTarget.order.time_credits_used) })
                  : moneyLabel(disputeTarget.order?.total_price, disputeTarget.order?.currency)}</div>
                <div><span className="font-semibold">{t('admin.buyer')}:</span> {disputeTarget.order?.buyer?.name || t('common.not_available')}</div>
                <div><span className="font-semibold">{t('admin.seller')}:</span> {disputeTarget.order?.seller?.name || t('common.not_available')}</div>
                <div><span className="font-semibold">{t('admin.columns.opened_by')}:</span> {disputeOpener(disputeTarget) || t('common.not_available')}</div>
                <div>
                  <p className="font-semibold">{t('admin.case_description')}</p>
                  <p className="whitespace-pre-wrap text-muted">{disputeTarget.description || t('common.not_available')}</p>
                </div>
                {disputeTarget.evidence_urls && disputeTarget.evidence_urls.length > 0 && (
                  <div>
                    <p className="font-semibold">{t('admin.evidence')}</p>
                    <ul className="list-disc space-y-1 pl-5">
                      {disputeTarget.evidence_urls.map((url) => <EvidenceLink key={url} url={url} />)}
                    </ul>
                  </div>
                )}
              </div>
            )}
            <RadioGroup label={t('common.resolution')} value={disputeResolution} onValueChange={(value) => setDisputeResolution(value as typeof disputeResolution)}>
              {DISPUTE_RESOLUTIONS.map((value) => (
                <Radio key={value} value={value}>
                  {t(value === 'closed' ? 'status.closed' : `status.resolved_${value}`)}
                </Radio>
              ))}
            </RadioGroup>
            <Textarea
              label={t('admin.resolution_notes')}
              placeholder={t('admin.resolution_notes_placeholder')}
              value={resolutionNotes}
              onValueChange={setResolutionNotes}
              minRows={4}
              isRequired
            />
            <Input
              type="number"
              min="0.01"
              step="0.01"
              label={t('admin.refund_amount')}
              description={t('admin.refund_amount_hint')}
              value={refundAmount}
              onValueChange={setRefundAmount}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" isDisabled={actionLoading} onPress={() => setDisputeTarget(null)}>{t('common.cancel')}</Button>
            <Button isPending={actionLoading} isDisabled={resolutionNotes.trim().length < 5} onPress={() => void submitDisputeResolution()}>{t('admin.resolve')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MarketplaceCasesPage;
