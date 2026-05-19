// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HourTransferAdminPage — Admin console for cooperative-to-cooperative banked
 * hour transfers (K3).
 *
 * Two tabs:
 *   - Pending Outbound — transfers initiated by THIS tenant's members,
 *     awaiting admin approval. Approve debits source wallet + delivers to
 *     destination tenant atomically. Reject closes the row with no funds movement.
 *   - Recent Inbound — transfers received from other cooperatives in the
 *     last 90 days (read-only audit view).
 *
 * Admin English only — no t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
  Tab,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
  Textarea,
} from '@heroui/react';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import Check from 'lucide-react/icons/check';
import Inbox from 'lucide-react/icons/inbox';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import X from 'lucide-react/icons/x';
import { usePageTitle } from '@/hooks';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { canManageCaring } from '@/caring/access';
import { PageHeader } from '../../components';

type TransferStatus =
  | 'pending'
  | 'approved_by_source'
  | 'sent'
  | 'received'
  | 'completed'
  | 'rejected';

interface PendingItem {
  id: number;
  member_user_id: number;
  member_name: string;
  member_email: string;
  destination_tenant_slug: string;
  destination_member_email: string;
  hours: number;
  status: TransferStatus;
  reason: string;
  created_at: string;
}

interface InboundItem {
  id: number;
  member_user_id: number;
  member_name: string;
  member_email: string;
  source_tenant_slug: string;
  hours: number;
  status: TransferStatus;
  reason: string;
  created_at: string;
}

const STATUS_COLOR: Record<TransferStatus, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  pending: 'warning',
  approved_by_source: 'primary',
  sent: 'primary',
  received: 'primary',
  completed: 'success',
  rejected: 'danger',
};

function formatAdminDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

export default function HourTransferAdminPage() {
  const toast = useToast();
  const { user } = useAuth();
  const { t } = useTranslation('caring_community');
  const canManage = canManageCaring(user);
  usePageTitle(t('admin.hour_transfers.title'));

  const [tab, setTab] = useState<string>('pending');
  const [pending, setPending] = useState<PendingItem[]>([]);
  const [inbound, setInbound] = useState<InboundItem[]>([]);
  const [loadingPending, setLoadingPending] = useState(true);
  const [loadingInbound, setLoadingInbound] = useState(true);
  const [actingId, setActingId] = useState<number | null>(null);
  const [rejectTargetId, setRejectTargetId] = useState<number | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const loadPending = useCallback(async () => {
    try {
      setLoadingPending(true);
      const res = await api.get<{ items: PendingItem[] }>(
        '/v2/admin/caring-community/hour-transfer/pending',
      );
      if (res.success && res.data) {
        setPending(res.data.items ?? []);
      } else {
        toast.error(res.error || t('admin.hour_transfers.errors.load_pending'));
      }
    } catch (err) {
      logError('HourTransferAdminPage: load pending failed', err);
      toast.error(t('admin.hour_transfers.errors.load_pending'));
    } finally {
      setLoadingPending(false);
    }
  }, [t, toast]);

  const loadInbound = useCallback(async () => {
    try {
      setLoadingInbound(true);
      const res = await api.get<{ items: InboundItem[] }>(
        '/v2/admin/caring-community/hour-transfer/inbound',
      );
      if (res.success && res.data) {
        setInbound(res.data.items ?? []);
      } else {
        toast.error(res.error || t('admin.hour_transfers.errors.load_inbound'));
      }
    } catch (err) {
      logError('HourTransferAdminPage: load inbound failed', err);
      toast.error(t('admin.hour_transfers.errors.load_inbound'));
    } finally {
      setLoadingInbound(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void loadPending();
    void loadInbound();
  }, [loadPending, loadInbound]);

  const handleApprove = useCallback(
    async (id: number) => {
      if (!canManage) {
        return;
      }
      try {
        setActingId(id);
        const res = await api.post<{ status: TransferStatus }>(
          `/v2/admin/caring-community/hour-transfer/${id}/approve`,
        );
        if (res.success) {
          toast.success(t('admin.hour_transfers.messages.approved'));
          void loadPending();
        } else {
          toast.error(res.error || t('admin.hour_transfers.errors.approve'));
        }
      } catch (err) {
        logError('HourTransferAdminPage: approve failed', err);
        toast.error(t('admin.hour_transfers.errors.approve'));
      } finally {
        setActingId(null);
      }
    },
    [canManage, t, toast, loadPending],
  );

  const handleReject = useCallback(
    async () => {
      if (!canManage || rejectTargetId === null) {
        return;
      }
      try {
        setActingId(rejectTargetId);
        const res = await api.post<{ status: TransferStatus }>(
          `/v2/admin/caring-community/hour-transfer/${rejectTargetId}/reject`,
          { reason: rejectReason.trim() },
        );
        if (res.success) {
          toast.success(t('admin.hour_transfers.messages.rejected'));
          setRejectTargetId(null);
          setRejectReason('');
          void loadPending();
        } else {
          toast.error(res.error || t('admin.hour_transfers.errors.reject'));
        }
      } catch (err) {
        logError('HourTransferAdminPage: reject failed', err);
        toast.error(t('admin.hour_transfers.errors.reject'));
      } finally {
        setActingId(null);
      }
    },
    [canManage, rejectReason, rejectTargetId, t, toast, loadPending],
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('admin.hour_transfers.title')}
        description={t('admin.hour_transfers.subtitle')}
        actions={
          <Button
            size="sm"
            variant="bordered"
            startContent={<RefreshCw className="w-4 h-4" />}
            onPress={() => {
              void loadPending();
              void loadInbound();
            }}
          >
            {t('admin.common.refresh')}
          </Button>
        }
      />

      <Tabs
        selectedKey={tab}
        onSelectionChange={(k) => setTab(String(k))}
        aria-label={t('admin.hour_transfers.tabs.aria')}
      >
        <Tab
          key="pending"
          title={
            <div className="flex items-center gap-2">
              <ArrowRightLeft className="w-4 h-4" />
              <span>{t('admin.hour_transfers.tabs.pending')}</span>
              <Chip size="sm" variant="flat" color="warning">
                {pending.length}
              </Chip>
            </div>
          }
        >
          <Card className="mt-4">
            <CardHeader className="flex flex-col items-start gap-1">
              <span className="text-base font-semibold">{t('admin.hour_transfers.pending.title')}</span>
              <p className="text-sm text-default-500">
                {t('admin.hour_transfers.pending.description')}
              </p>
            </CardHeader>
            <Divider />
            <CardBody className="p-0">
              {loadingPending ? (
                <div className="flex justify-center py-12">
                  <Spinner size="md" />
                </div>
              ) : pending.length === 0 ? (
                <div className="py-12 text-center text-sm text-default-500">
                  {t('admin.hour_transfers.pending.empty')}
                </div>
              ) : (
                <Table aria-label={t('admin.hour_transfers.pending.table_aria')} removeWrapper>
                  <TableHeader>
                    <TableColumn>{t('admin.hour_transfers.table.date')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.member')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.destination')}</TableColumn>
                    <TableColumn align="end">{t('admin.hour_transfers.table.hours')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.reason')}</TableColumn>
                    <TableColumn align="end">{t('admin.hour_transfers.table.actions')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {pending.map((row) => (
                      <TableRow key={row.id}>
                        <TableCell className="whitespace-nowrap text-default-500">
                          {formatAdminDate(row.created_at)}
                        </TableCell>
                        <TableCell>
                          <div className="font-medium">{row.member_name || t('admin.common.empty_dash')}</div>
                          <div className="text-xs text-default-500">{row.member_email}</div>
                        </TableCell>
                        <TableCell>
                          <div className="font-medium">{row.destination_tenant_slug}</div>
                          <div className="text-xs text-default-500">
                            {row.destination_member_email}
                          </div>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {row.hours.toFixed(2)}
                        </TableCell>
                        <TableCell className="text-default-600">
                          {row.reason || t('admin.common.empty_dash')}
                        </TableCell>
                        <TableCell>
                          {canManage ? (
                            <div className="flex justify-end gap-2">
                              <Button
                                size="sm"
                                color="success"
                                variant="flat"
                                startContent={<Check className="w-4 h-4" />}
                                isLoading={actingId === row.id}
                                onPress={() => void handleApprove(row.id)}
                              >
                                {t('admin.hour_transfers.actions.approve')}
                              </Button>
                              <Button
                                size="sm"
                                color="danger"
                                variant="flat"
                                startContent={<X className="w-4 h-4" />}
                                isLoading={actingId === row.id}
                                onPress={() => setRejectTargetId(row.id)}
                              >
                                {t('admin.hour_transfers.actions.reject')}
                              </Button>
                            </div>
                          ) : (
                            <span className="block text-right text-default-400">{t('admin.common.empty_dash')}</span>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardBody>
          </Card>
        </Tab>

        <Tab
          key="inbound"
          title={
            <div className="flex items-center gap-2">
              <Inbox className="w-4 h-4" />
              <span>{t('admin.hour_transfers.tabs.inbound')}</span>
              <Chip size="sm" variant="flat" color="primary">
                {inbound.length}
              </Chip>
            </div>
          }
        >
          <Card className="mt-4">
            <CardHeader className="flex flex-col items-start gap-1">
              <span className="text-base font-semibold">{t('admin.hour_transfers.inbound.title')}</span>
              <p className="text-sm text-default-500">
                {t('admin.hour_transfers.inbound.description')}
              </p>
            </CardHeader>
            <Divider />
            <CardBody className="p-0">
              {loadingInbound ? (
                <div className="flex justify-center py-12">
                  <Spinner size="md" />
                </div>
              ) : inbound.length === 0 ? (
                <div className="py-12 text-center text-sm text-default-500">
                  {t('admin.hour_transfers.inbound.empty')}
                </div>
              ) : (
                <Table aria-label={t('admin.hour_transfers.inbound.table_aria')} removeWrapper>
                  <TableHeader>
                    <TableColumn>{t('admin.hour_transfers.table.date')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.source')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.member_credited')}</TableColumn>
                    <TableColumn align="end">{t('admin.hour_transfers.table.hours')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.status')}</TableColumn>
                    <TableColumn>{t('admin.hour_transfers.table.reason')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {inbound.map((row) => (
                      <TableRow key={row.id}>
                        <TableCell className="whitespace-nowrap text-default-500">
                          {formatAdminDate(row.created_at)}
                        </TableCell>
                        <TableCell className="font-medium">
                          {row.source_tenant_slug}
                        </TableCell>
                        <TableCell>
                          <div className="font-medium">{row.member_name || t('admin.common.empty_dash')}</div>
                          <div className="text-xs text-default-500">{row.member_email}</div>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {row.hours.toFixed(2)}
                        </TableCell>
                        <TableCell>
                          <Chip size="sm" variant="flat" color={STATUS_COLOR[row.status] ?? 'default'}>
                            {t(`admin.hour_transfers.status.${row.status}`)}
                          </Chip>
                        </TableCell>
                        <TableCell className="text-default-600">
                          {row.reason || t('admin.common.empty_dash')}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardBody>
          </Card>
        </Tab>
      </Tabs>

      <Modal
        isOpen={rejectTargetId !== null}
        onClose={() => {
          if (actingId !== null) return;
          setRejectTargetId(null);
          setRejectReason('');
        }}
        size="md"
      >
        <ModalContent>
          <ModalHeader>{t('admin.hour_transfers.reject_modal.title')}</ModalHeader>
          <ModalBody>
            <Textarea
              label={t('admin.hour_transfers.reject_modal.reason_label')}
              placeholder={t('admin.hour_transfers.reject_modal.reason_placeholder')}
              value={rejectReason}
              onValueChange={setRejectReason}
              variant="bordered"
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => {
                setRejectTargetId(null);
                setRejectReason('');
              }}
              isDisabled={actingId !== null}
            >
              {t('admin.common.cancel')}
            </Button>
            <Button
              color="danger"
              isLoading={actingId === rejectTargetId}
              onPress={() => void handleReject()}
            >
              {t('admin.hour_transfers.actions.reject')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
