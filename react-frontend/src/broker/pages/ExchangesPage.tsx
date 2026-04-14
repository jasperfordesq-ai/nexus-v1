// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Exchanges Page
 * Oversight of exchange requests with approve/reject workflows.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Tabs,
  Tab,
  Chip,
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Textarea,
} from '@heroui/react';
import { MoreVertical, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminBroker } from '@/admin/api/adminApi';
import { DataTable, PageHeader, ConfirmModal, EmptyState, type Column } from '@/admin/components';
import type { ExchangeRequest } from '@/admin/api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Status chip color mapping
// ─────────────────────────────────────────────────────────────────────────────

const statusColorMap: Record<string, 'warning' | 'success' | 'danger' | 'default'> = {
  pending: 'warning',
  approved: 'success',
  disputed: 'danger',
  rejected: 'default',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function ExchangesPage() {
  const { t } = useTranslation('broker');
  usePageTitle(t('exchanges.title'));
  const toast = useToast();

  // Data state
  const [items, setItems] = useState<ExchangeRequest[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<string>('');
  const [loading, setLoading] = useState(true);

  // Action state
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [approveTarget, setApproveTarget] = useState<ExchangeRequest | null>(null);
  const [rejectTarget, setRejectTarget] = useState<ExchangeRequest | null>(null);
  const [approveNotes, setApproveNotes] = useState('');
  const [rejectReason, setRejectReason] = useState('');

  // ── Data fetching ─────────────────────────────────────────────────────────

  const loadExchanges = useCallback(async () => {
    setLoading(true);
    try {
      const params: { page?: number; status?: string } = { page };
      if (status) params.status = status;

      const res = await adminBroker.getExchanges(params);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: ExchangeRequest[]; meta?: { total: number } };
          setItems(paged.data || []);
          setTotal(paged.meta?.total ?? 0);
        }
      }
    } catch {
      toast.error(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [page, status, toast, t]);

  useEffect(() => {
    loadExchanges();
  }, [loadExchanges]);

  // ── Tab change ────────────────────────────────────────────────────────────

  const handleTabChange = useCallback((key: React.Key) => {
    const tab = String(key);
    setStatus(tab === 'all' ? '' : tab);
    setPage(1);
  }, []);

  // ── Approve ───────────────────────────────────────────────────────────────

  const handleApprove = useCallback(async () => {
    if (!approveTarget) return;
    setActionLoading(approveTarget.id);
    try {
      const res = await adminBroker.approveExchange(
        approveTarget.id,
        approveNotes || undefined,
      );
      if (res?.success) {
        toast.success(t('exchanges.approved_success'));
        loadExchanges();
      } else {
        toast.error(t('common.error'));
      }
    } catch {
      toast.error(t('common.error'));
    } finally {
      setActionLoading(null);
      setApproveTarget(null);
      setApproveNotes('');
    }
  }, [approveTarget, approveNotes, loadExchanges, toast, t]);

  // ── Reject ────────────────────────────────────────────────────────────────

  const handleReject = useCallback(async () => {
    if (!rejectTarget || !rejectReason.trim()) return;
    setActionLoading(rejectTarget.id);
    try {
      const res = await adminBroker.rejectExchange(rejectTarget.id, rejectReason);
      if (res?.success) {
        toast.success(t('exchanges.rejected_success'));
        loadExchanges();
      } else {
        toast.error(t('common.error'));
      }
    } catch {
      toast.error(t('common.error'));
    } finally {
      setActionLoading(null);
      setRejectTarget(null);
      setRejectReason('');
    }
  }, [rejectTarget, rejectReason, loadExchanges, toast, t]);

  // ── Columns ───────────────────────────────────────────────────────────────

  const columns: Column<ExchangeRequest>[] = useMemo(
    () => [
      {
        key: 'requester_name',
        label: t('exchanges.col_from'),
        sortable: true,
        render: (item) => (
          <span className="font-medium text-foreground">{item.requester_name}</span>
        ),
      },
      {
        key: 'provider_name',
        label: t('exchanges.col_to'),
        sortable: true,
        render: (item) => (
          <span className="text-sm text-default-600">{item.provider_name}</span>
        ),
      },
      {
        key: 'listing_title',
        label: t('exchanges.col_service'),
        render: (item) => (
          <span className="text-sm text-default-600">
            {item.listing_title || '--'}
          </span>
        ),
      },
      {
        key: 'final_hours',
        label: t('exchanges.col_hours'),
        sortable: true,
        render: (item) => (
          <span className="text-sm text-default-600">
            {item.final_hours ?? '--'}
          </span>
        ),
      },
      {
        key: 'status',
        label: t('exchanges.col_status'),
        render: (item) => (
          <Chip
            size="sm"
            variant="flat"
            color={statusColorMap[item.status?.toLowerCase()] || 'default'}
            className="capitalize"
          >
            {item.status}
          </Chip>
        ),
      },
      {
        key: 'created_at',
        label: t('exchanges.col_date'),
        sortable: true,
        render: (item) => (
          <span className="text-sm text-default-500">
            {item.created_at
              ? new Date(item.created_at).toLocaleDateString()
              : '--'}
          </span>
        ),
      },
      {
        key: 'actions',
        label: t('exchanges.col_actions'),
        render: (item) => (
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly size="sm" variant="light" aria-label={t('exchanges.actions_aria')}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu aria-label={t('exchanges.exchange_actions_aria')}>
              <DropdownItem
                key="approve"
                onPress={() => setApproveTarget(item)}
              >
                {t('exchanges.approve')}
              </DropdownItem>
              <DropdownItem
                key="reject"
                className="text-danger"
                onPress={() => setRejectTarget(item)}
              >
                {t('exchanges.reject')}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        ),
      },
    ],
    [t],
  );

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('exchanges.title')}
        description={t('exchanges.description')}
        actions={
          <Button
            isIconOnly
            variant="flat"
            size="sm"
            onPress={loadExchanges}
            aria-label={t('common.refresh')}
          >
            <RefreshCw size={16} />
          </Button>
        }
      />

      {/* Status tabs */}
      <Tabs
        aria-label={t('exchanges.status_filter_aria')}
        selectedKey={status || 'all'}
        onSelectionChange={handleTabChange}
        className="mb-4"
      >
        <Tab key="all" title={t('exchanges.tab_all')} />
        <Tab key="pending" title={t('exchanges.tab_pending')} />
        <Tab key="approved" title={t('exchanges.tab_approved')} />
        <Tab key="disputed" title={t('exchanges.tab_disputed')} />
      </Tabs>

      {/* Data table */}
      {!loading && items.length === 0 ? (
        <EmptyState
          title={t('exchanges.no_exchanges')}
          description={t('exchanges.description')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          totalItems={total}
          page={page}
          pageSize={20}
          onPageChange={setPage}
          onRefresh={loadExchanges}
        />
      )}

      {/* Approve confirm modal */}
      <ConfirmModal
        isOpen={!!approveTarget}
        onClose={() => {
          setApproveTarget(null);
          setApproveNotes('');
        }}
        onConfirm={handleApprove}
        title={t('exchanges.approve')}
        message={t('exchanges.approved_success')}
        confirmLabel={t('exchanges.approve')}
        confirmColor="primary"
        isLoading={actionLoading === approveTarget?.id}
      >
        <Textarea
          label={t('exchanges.notes_placeholder')}
          placeholder={t('exchanges.notes_placeholder')}
          value={approveNotes}
          onValueChange={setApproveNotes}
          minRows={2}
          className="mt-2"
        />
      </ConfirmModal>

      {/* Reject confirm modal */}
      <ConfirmModal
        isOpen={!!rejectTarget}
        onClose={() => {
          setRejectTarget(null);
          setRejectReason('');
        }}
        onConfirm={handleReject}
        title={t('exchanges.reject')}
        message={t('exchanges.reason_placeholder')}
        confirmLabel={t('exchanges.reject')}
        confirmColor="danger"
        isLoading={actionLoading === rejectTarget?.id}
      >
        <Textarea
          label={t('exchanges.reason_placeholder')}
          placeholder={t('exchanges.reason_placeholder')}
          value={rejectReason}
          onValueChange={setRejectReason}
          isRequired
          minRows={2}
          className="mt-2"
        />
      </ConfirmModal>
    </div>
  );
}
