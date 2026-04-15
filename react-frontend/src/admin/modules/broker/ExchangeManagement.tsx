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
import { Link } from 'react-router-dom';
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
import { ArrowLeft, CheckCircle, XCircle, Eye } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, type Column } from '../../components';
import type { ExchangeRequest } from '../../api/types';

import { useTranslation } from 'react-i18next';
type ActionType = 'approve' | 'reject';

export function ExchangeManagement() {
  const { t } = useTranslation('admin');
  usePageTitle(t('broker.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<ExchangeRequest[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');

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
      toast.error(t('broker.failed_to_load_exchanges'));
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
      toast.error(t('broker.a_reason_is_required_to_reject_an_exchan'));
      return;
    }

    setActionLoading(true);
    try {
      const res = type === 'approve'
        ? await adminBroker.approveExchange(item.id, actionText || undefined)
        : await adminBroker.rejectExchange(item.id, actionText);

      if (res?.success) {
        toast.success(t('broker.exchange_action_success', { type }));
        loadItems();
      } else {
        toast.error(res?.error || t('broker.exchange_action_failed', { type }));
      }
    } catch {
      toast.error(t('broker.exchange_action_failed', { type }));
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
      label: t('broker.col_requester'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.requester_name}</span>
      ),
    },
    {
      key: 'provider_name',
      label: t('broker.col_provider'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.provider_name}</span>
      ),
    },
    {
      key: 'listing_title',
      label: t('broker.col_listing'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'status',
      label: t('broker.col_status'),
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'final_hours',
      label: t('broker.col_hours'),
      sortable: true,
      render: (item) => (
        <span className="text-sm">
          {item.final_hours != null ? `${item.final_hours}h` : '—'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: t('broker.col_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('broker.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="default"
            as={Link}
            to={tenantPath(`/admin/broker-controls/exchanges/${item.id}`)}
            aria-label={t('broker.label_view_exchange_details')}
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
                aria-label={t('broker.label_approve_exchange')}
              >
                <CheckCircle size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => openActionModal('reject', item)}
                aria-label={t('broker.label_reject_exchange')}
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
        title={t('broker.exchange_management_title')}
        description={t('broker.exchange_management_desc')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {t('common.back')}
          </Button>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => { setStatus(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('broker.tab_all')} />
          <Tab key="pending_broker" title={t('broker.tab_pending')} />
          <Tab key="accepted" title={t('broker.tab_approved')} />
          <Tab key="in_progress" title={t('broker.tab_in_progress')} />
          <Tab key="completed" title={t('broker.tab_completed')} />
          <Tab key="cancelled" title={t('broker.tab_cancelled')} />
          <Tab key="disputed" title={t('broker.tab_disputed')} />
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
                  {t('broker.approve_exchange')}
                </>
              ) : (
                <>
                  <XCircle size={20} className="text-danger" />
                  {t('broker.reject_exchange')}
                </>
              )}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                {actionModal.type === 'approve'
                  ? t('broker.confirm_approve_exchange', { requester: actionModal.item.requester_name, provider: actionModal.item.provider_name })
                  : t('broker.confirm_reject_exchange', { requester: actionModal.item.requester_name, provider: actionModal.item.provider_name })
                }
              </p>
              <Textarea
                label={actionModal.type === 'approve' ? t('broker.label_notes_optional') : t('broker.label_reason_required')}
                placeholder={actionModal.type === 'approve'
                  ? t('broker.placeholder_approval_notes')
                  : t('broker.placeholder_rejection_reason')
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
                {actionModal.type === 'approve' ? t('broker.approve') : t('broker.reject')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default ExchangeManagement;
