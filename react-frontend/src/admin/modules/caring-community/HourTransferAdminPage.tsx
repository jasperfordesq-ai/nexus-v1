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
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Spinner,
  Tab,
  Tabs,
} from '@heroui/react';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import Check from 'lucide-react/icons/check';
import Inbox from 'lucide-react/icons/inbox';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import X from 'lucide-react/icons/x';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
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

export default function HourTransferAdminPage() {
  const toast = useToast();
  usePageTitle('Hour Transfers');

  const [tab, setTab] = useState<string>('pending');
  const [pending, setPending] = useState<PendingItem[]>([]);
  const [inbound, setInbound] = useState<InboundItem[]>([]);
  const [loadingPending, setLoadingPending] = useState(true);
  const [loadingInbound, setLoadingInbound] = useState(true);
  const [actingId, setActingId] = useState<number | null>(null);

  const loadPending = useCallback(async () => {
    try {
      setLoadingPending(true);
      const res = await api.get<{ items: PendingItem[] }>(
        '/v2/admin/caring-community/hour-transfer/pending',
      );
      if (res.success && res.data) {
        setPending(res.data.items ?? []);
      } else {
        toast.error(res.error || 'Failed to load pending transfers');
      }
    } catch (err) {
      logError('HourTransferAdminPage: load pending failed', err);
      toast.error('Failed to load pending transfers');
    } finally {
      setLoadingPending(false);
    }
  }, [toast]);

  const loadInbound = useCallback(async () => {
    try {
      setLoadingInbound(true);
      const res = await api.get<{ items: InboundItem[] }>(
        '/v2/admin/caring-community/hour-transfer/inbound',
      );
      if (res.success && res.data) {
        setInbound(res.data.items ?? []);
      } else {
        toast.error(res.error || 'Failed to load inbound transfers');
      }
    } catch (err) {
      logError('HourTransferAdminPage: load inbound failed', err);
      toast.error('Failed to load inbound transfers');
    } finally {
      setLoadingInbound(false);
    }
  }, [toast]);

  useEffect(() => {
    void loadPending();
    void loadInbound();
  }, [loadPending, loadInbound]);

  const handleApprove = useCallback(
    async (id: number) => {
      if (!window.confirm('Approve this transfer? This debits the source wallet and credits the destination immediately.')) {
        return;
      }
      try {
        setActingId(id);
        const res = await api.post<{ status: TransferStatus }>(
          `/v2/admin/caring-community/hour-transfer/${id}/approve`,
        );
        if (res.success) {
          toast.success('Transfer approved and delivered.');
          void loadPending();
        } else {
          toast.error(res.error || 'Approval failed');
        }
      } catch (err) {
        logError('HourTransferAdminPage: approve failed', err);
        toast.error('Approval failed');
      } finally {
        setActingId(null);
      }
    },
    [toast, loadPending],
  );

  const handleReject = useCallback(
    async (id: number) => {
      const reason = window.prompt('Reason for rejection (optional)') ?? '';
      try {
        setActingId(id);
        const res = await api.post<{ status: TransferStatus }>(
          `/v2/admin/caring-community/hour-transfer/${id}/reject`,
          { reason },
        );
        if (res.success) {
          toast.success('Transfer rejected.');
          void loadPending();
        } else {
          toast.error(res.error || 'Rejection failed');
        }
      } catch (err) {
        logError('HourTransferAdminPage: reject failed', err);
        toast.error('Rejection failed');
      } finally {
        setActingId(null);
      }
    },
    [toast, loadPending],
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Cooperative Hour Transfers"
        description="Members moving between cooperatives can transfer their banked hours. Review pending outbound requests and approve once you've confirmed the member has registered at the destination."
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
            Refresh
          </Button>
        }
      />

      <Tabs
        selectedKey={tab}
        onSelectionChange={(k) => setTab(String(k))}
        aria-label="Hour transfer tabs"
      >
        <Tab
          key="pending"
          title={
            <div className="flex items-center gap-2">
              <ArrowRightLeft className="w-4 h-4" />
              <span>Pending Outbound</span>
              <Chip size="sm" variant="flat" color="warning">
                {pending.length}
              </Chip>
            </div>
          }
        >
          <Card className="mt-4">
            <CardHeader className="text-base font-semibold">
              Awaiting your approval
            </CardHeader>
            <Divider />
            <CardBody className="p-0">
              {loadingPending ? (
                <div className="flex justify-center py-12">
                  <Spinner size="md" />
                </div>
              ) : pending.length === 0 ? (
                <div className="py-12 text-center text-sm text-default-500">
                  No pending transfers.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-default-50">
                      <tr className="text-left text-xs uppercase tracking-wide text-default-500">
                        <th className="px-4 py-3">Date</th>
                        <th className="px-4 py-3">Member</th>
                        <th className="px-4 py-3">Destination</th>
                        <th className="px-4 py-3 text-right">Hours</th>
                        <th className="px-4 py-3">Reason</th>
                        <th className="px-4 py-3 text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pending.map((row) => (
                        <tr
                          key={row.id}
                          className="border-t border-default-200 hover:bg-default-50"
                        >
                          <td className="px-4 py-3 whitespace-nowrap text-default-500">
                            {new Date(row.created_at).toLocaleDateString()}
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium">{row.member_name || '—'}</div>
                            <div className="text-xs text-default-500">{row.member_email}</div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium">{row.destination_tenant_slug}</div>
                            <div className="text-xs text-default-500">
                              {row.destination_member_email}
                            </div>
                          </td>
                          <td className="px-4 py-3 text-right tabular-nums">
                            {row.hours.toFixed(2)}
                          </td>
                          <td className="px-4 py-3 text-default-600">
                            {row.reason || '—'}
                          </td>
                          <td className="px-4 py-3 text-right">
                            <div className="flex justify-end gap-2">
                              <Button
                                size="sm"
                                color="success"
                                variant="flat"
                                startContent={<Check className="w-4 h-4" />}
                                isLoading={actingId === row.id}
                                onPress={() => void handleApprove(row.id)}
                              >
                                Approve
                              </Button>
                              <Button
                                size="sm"
                                color="danger"
                                variant="flat"
                                startContent={<X className="w-4 h-4" />}
                                isLoading={actingId === row.id}
                                onPress={() => void handleReject(row.id)}
                              >
                                Reject
                              </Button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardBody>
          </Card>
        </Tab>

        <Tab
          key="inbound"
          title={
            <div className="flex items-center gap-2">
              <Inbox className="w-4 h-4" />
              <span>Recent Inbound</span>
              <Chip size="sm" variant="flat" color="primary">
                {inbound.length}
              </Chip>
            </div>
          }
        >
          <Card className="mt-4">
            <CardHeader className="text-base font-semibold">
              Received from other cooperatives (last 90 days)
            </CardHeader>
            <Divider />
            <CardBody className="p-0">
              {loadingInbound ? (
                <div className="flex justify-center py-12">
                  <Spinner size="md" />
                </div>
              ) : inbound.length === 0 ? (
                <div className="py-12 text-center text-sm text-default-500">
                  No transfers received in the last 90 days.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-default-50">
                      <tr className="text-left text-xs uppercase tracking-wide text-default-500">
                        <th className="px-4 py-3">Date</th>
                        <th className="px-4 py-3">Source</th>
                        <th className="px-4 py-3">Member credited</th>
                        <th className="px-4 py-3 text-right">Hours</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3">Reason</th>
                      </tr>
                    </thead>
                    <tbody>
                      {inbound.map((row) => (
                        <tr
                          key={row.id}
                          className="border-t border-default-200 hover:bg-default-50"
                        >
                          <td className="px-4 py-3 whitespace-nowrap text-default-500">
                            {new Date(row.created_at).toLocaleDateString()}
                          </td>
                          <td className="px-4 py-3 font-medium">
                            {row.source_tenant_slug}
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium">{row.member_name || '—'}</div>
                            <div className="text-xs text-default-500">{row.member_email}</div>
                          </td>
                          <td className="px-4 py-3 text-right tabular-nums">
                            {row.hours.toFixed(2)}
                          </td>
                          <td className="px-4 py-3">
                            <Chip size="sm" variant="flat" color={STATUS_COLOR[row.status] ?? 'default'}>
                              {row.status}
                            </Chip>
                          </td>
                          <td className="px-4 py-3 text-default-600">
                            {row.reason || '—'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardBody>
          </Card>
        </Tab>
      </Tabs>
    </div>
  );
}
