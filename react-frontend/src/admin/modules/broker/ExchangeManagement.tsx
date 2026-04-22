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
import { adminBroker } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, type Column } from '../../components';
import type { ExchangeRequest } from '../../api/types';

type ActionType = 'approve' | 'reject';

export function ExchangeManagement() {
  usePageTitle("Broker Controls");
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
      toast.error("Failed to load exchanges");
    } finally {
      setLoading(false);
    }
  }, [page, status, toast])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleAction = async () => {
    if (!actionModal) return;
    const { type, item } = actionModal;

    if (type === 'reject' && !actionText.trim()) {
      toast.error("A reason is required to reject an exchange");
      return;
    }

    setActionLoading(true);
    try {
      const res = type === 'approve'
        ? await adminBroker.approveExchange(item.id, actionText || undefined)
        : await adminBroker.rejectExchange(item.id, actionText);

      if (res?.success) {
        toast.success(`Exchange Action succeeded`);
        loadItems();
      } else {
        toast.error(res?.error || `Exchange Action failed`);
      }
    } catch {
      toast.error(`Exchange Action failed`);
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
      label: "Requester",
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.requester_name}</span>
      ),
    },
    {
      key: 'provider_name',
      label: "Provider",
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.provider_name}</span>
      ),
    },
    {
      key: 'listing_title',
      label: "Listing",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'status',
      label: "Status",
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'final_hours',
      label: "Hours",
      sortable: true,
      render: (item) => (
        <span className="text-sm">
          {item.final_hours != null ? `${item.final_hours}h` : '—'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: "Date",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="default"
            as={Link}
            to={tenantPath(`/admin/broker-controls/exchanges/${item.id}`)}
            aria-label={"View Exchange Details"}
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
                aria-label={"Approve Exchange"}
              >
                <CheckCircle size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => openActionModal('reject', item)}
                aria-label={"Reject Exchange"}
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
        title={"Exchange Management"}
        description={"Review and approve or reject exchanges flagged for broker attention"}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {"Back"}
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
          <Tab key="all" title={"All"} />
          <Tab key="pending_broker" title={"Pending"} />
          <Tab key="accepted" title={"Approved"} />
          <Tab key="in_progress" title={"In Progress"} />
          <Tab key="completed" title={"Completed"} />
          <Tab key="cancelled" title={"Cancelled"} />
          <Tab key="disputed" title={"Disputed"} />
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
                  {"Approve Exchange"}
                </>
              ) : (
                <>
                  <XCircle size={20} className="text-danger" />
                  {"Reject Exchange"}
                </>
              )}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                {actionModal.type === 'approve'
                  ? `Are you sure you want to approve exchange?`
                  : `Are you sure you want to reject exchange?`
                }
              </p>
              <Textarea
                label={actionModal.type === 'approve' ? "Notes Optional" : "Reason Required"}
                placeholder={actionModal.type === 'approve'
                  ? "Enter approval notes..."
                  : "Enter rejection reason..."
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
                {"Cancel"}
              </Button>
              <Button
                color={actionModal.type === 'approve' ? 'success' : 'danger'}
                onPress={handleAction}
                isLoading={actionLoading}
              >
                {actionModal.type === 'approve' ? "Approve" : "Reject"}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default ExchangeManagement;
