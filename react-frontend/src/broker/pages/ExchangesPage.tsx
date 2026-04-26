// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Exchange Management
 * List and manage exchange requests with approve/reject actions.
 * Parity: PHP BrokerControlsController::exchanges()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Tabs,
  Tab,
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import Eye from 'lucide-react/icons/eye';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { formatServerDate } from '@/lib/serverTime';
import { adminBroker } from '@/admin/api/adminApi';
import { DataTable, StatusBadge, PageHeader, type Column } from '@/admin/components';
import type { ExchangeRequest } from '@/admin/api/types';

type ActionType = 'approve' | 'reject';

export function ExchangeManagement() {
  const { t } = useTranslation('broker');
  usePageTitle(t('exchanges.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Status filter is mirrored to `?status=` so stat-card deep-links and
  // browser back/forward work as expected.
  const EXCHANGE_STATUSES = [
    'all', 'pending_broker', 'accepted', 'in_progress', 'completed', 'cancelled', 'disputed',
  ] as const;
  type ExchangeStatus = (typeof EXCHANGE_STATUSES)[number];
  const [searchParams, setSearchParams] = useSearchParams();
  const urlStatus = searchParams.get('status') as ExchangeStatus | null;
  const status: ExchangeStatus =
    urlStatus && EXCHANGE_STATUSES.includes(urlStatus) ? urlStatus : 'all';
  const setStatus = useCallback(
    (next: ExchangeStatus) => {
      setSearchParams(
        (prev) => {
          const params = new URLSearchParams(prev);
          if (next === 'all') {
            params.delete('status');
          } else {
            params.set('status', next);
          }
          return params;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );

  const [items, setItems] = useState<ExchangeRequest[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);

  // Action modal state
  const [actionModal, setActionModal] = useState<{
    type: ActionType;
    item: ExchangeRequest;
  } | null>(null);
  const [actionText, setActionText] = useState('');
  const [actionLoading, setActionLoading] = useState(false);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getExchanges({
        page,
        status: status === 'all' ? undefined : status,
      });
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as ExchangeRequest[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error(t('exchanges.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [page, status, toast, t])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleAction = async () => {
    if (!actionModal) return;
    const { type, item } = actionModal;

    if (type === 'reject' && !actionText.trim()) {
      toast.error(t('exchanges.reason_required_error'));
      return;
    }

    setActionLoading(true);
    try {
      const res = type === 'approve'
        ? await adminBroker.approveExchange(item.id, actionText || undefined)
        : await adminBroker.rejectExchange(item.id, actionText);

      if (res?.success) {
        toast.success(t('exchanges.action_succeeded'));
        loadItems();
      } else {
        toast.error(res?.error || t('exchanges.action_failed'));
      }
    } catch {
      toast.error(t('exchanges.action_failed'));
    } finally {
      setActionLoading(false);
      setActionModal(null);
      setActionText('');
    }
  };

  const openActionModal = (type: ActionType, item: ExchangeRequest) => {
    setActionModal({ type, item });
    setActionText('');
  };

  const columns: Column<ExchangeRequest>[] = [
    {
      key: 'requester_name',
      label: t('exchanges.col_requester'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.requester_name}</span>
      ),
    },
    {
      key: 'provider_name',
      label: t('exchanges.col_provider'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.provider_name}</span>
      ),
    },
    {
      key: 'listing_title',
      label: t('exchanges.col_listing'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'status',
      label: t('exchanges.col_status'),
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'final_hours',
      label: t('exchanges.col_hours'),
      sortable: true,
      render: (item) => (
        <span className="text-sm">
          {item.final_hours != null ? `${item.final_hours}h` : '—'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: t('exchanges.col_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {formatServerDate(item.created_at)}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('exchanges.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="default"
            as={Link}
            to={tenantPath(`/broker/exchanges/${item.id}`)}
            aria-label={t('exchanges.view_details_aria')}
          >
            <Eye size={14} />
          </Button>
          {item.status === 'pending_broker' && (
            <>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                onPress={() => openActionModal('approve', item)}
                aria-label={t('exchanges.approve_aria')}
              >
                <CheckCircle size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => openActionModal('reject', item)}
                aria-label={t('exchanges.reject_aria')}
              >
                <XCircle size={14} />
              </Button>
            </>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('exchanges.title')}
        description={t('exchanges.description')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/broker')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {t('exchanges.back')}
          </Button>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => { setStatus(key as ExchangeStatus); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('exchanges.tab_all')} />
          <Tab key="pending_broker" title={t('exchanges.tab_pending_broker')} />
          <Tab key="accepted" title={t('exchanges.tab_accepted')} />
          <Tab key="in_progress" title={t('exchanges.tab_in_progress')} />
          <Tab key="completed" title={t('exchanges.tab_completed')} />
          <Tab key="cancelled" title={t('exchanges.tab_cancelled')} />
          <Tab key="disputed" title={t('exchanges.tab_disputed')} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />

      {/* Approve/Reject Modal */}
      {actionModal && (
        <Modal isOpen={!!actionModal} onClose={() => { setActionModal(null); setActionText(''); }} size="md">
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              {actionModal.type === 'approve' ? (
                <>
                  <CheckCircle size={20} className="text-success" />
                  {t('exchanges.approve_modal_title')}
                </>
              ) : (
                <>
                  <XCircle size={20} className="text-danger" />
                  {t('exchanges.reject_modal_title')}
                </>
              )}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                {actionModal.type === 'approve'
                  ? t('exchanges.approve_confirm_text')
                  : t('exchanges.reject_confirm_text')
                }
              </p>
              <Textarea
                label={actionModal.type === 'approve' ? t('exchanges.notes_optional_label') : t('exchanges.reason_required_label')}
                placeholder={actionModal.type === 'approve'
                  ? t('exchanges.approval_notes_placeholder')
                  : t('exchanges.rejection_reason_placeholder')
                }
                value={actionText}
                onValueChange={setActionText}
                minRows={3}
                variant="bordered"
                isRequired={actionModal.type === 'reject'}
              />
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                onPress={() => { setActionModal(null); setActionText(''); }}
                isDisabled={actionLoading}
              >
                {t('common.cancel')}
              </Button>
              <Button
                color={actionModal.type === 'approve' ? 'success' : 'danger'}
                onPress={handleAction}
                isLoading={actionLoading}
              >
                {actionModal.type === 'approve' ? t('exchanges.approve') : t('exchanges.reject')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default ExchangeManagement;
