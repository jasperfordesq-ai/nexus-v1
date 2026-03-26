// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Partnerships
 * Lists and manages federation partnerships with other communities.
 * Parity: PHP FederationSettingsController::partnerships() + approve/reject/terminate
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
} from '@heroui/react';
import { Handshake, RefreshCw, MoreVertical, CheckCircle, XCircle, Ban } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, ConfirmModal, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
interface Partnership {
  id: number;
  partner_name: string;
  partner_slug: string;
  status: string;
  created_at: string;
}

export function Partnerships() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.page_title'));
  const toast = useToast();
  const [items, setItems] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [terminateTarget, setTerminateTarget] = useState<Partnership | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getPartnerships();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Partnership[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleApprove = async (item: Partnership) => {
    setActionLoading(true);
    try {
      const res = await adminFederation.approvePartnership(item.id);
      if (res.success) {
        toast.success(t('federation.partnership_approved', { name: item.partner_name }));
        loadData();
      } else {
        toast.error(t('federation.failed_to_approve_partnership'));
      }
    } catch {
      toast.error(t('federation.failed_to_approve_partnership'));
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async (item: Partnership) => {
    setActionLoading(true);
    try {
      const res = await adminFederation.rejectPartnership(item.id);
      if (res.success) {
        toast.success(t('federation.partnership_rejected', { name: item.partner_name }));
        loadData();
      } else {
        toast.error(t('federation.failed_to_reject_partnership'));
      }
    } catch {
      toast.error(t('federation.failed_to_reject_partnership'));
    } finally {
      setActionLoading(false);
    }
  };

  const handleTerminate = async () => {
    if (!terminateTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.terminatePartnership(terminateTarget.id);
      if (res.success) {
        toast.success(t('federation.partnership_terminated', { name: terminateTarget.partner_name }));
        setTerminateTarget(null);
        loadData();
      } else {
        toast.error(t('federation.failed_to_terminate_partnership'));
      }
    } catch {
      toast.error(t('federation.failed_to_terminate_partnership'));
    } finally {
      setActionLoading(false);
    }
  };

  const columns: Column<Partnership>[] = [
    { key: 'partner_name', label: t('federation.col_partner_community'), sortable: true },
    { key: 'partner_slug', label: t('federation.col_slug') },
    {
      key: 'status', label: t('federation.col_status'),
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: t('federation.col_since'), sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions', label: t('federation.col_actions'),
      render: (item) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label={t('federation.label_actions')} isDisabled={actionLoading}>
              <MoreVertical size={16} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label={t('federation.label_partnership_actions')}
            onAction={(key) => {
              if (key === 'approve') handleApprove(item);
              else if (key === 'reject') handleReject(item);
              else if (key === 'terminate') setTerminateTarget(item);
            }}
            items={[
              ...(item.status === 'pending' ? [{ key: 'approve' }, { key: 'reject' }] : []),
              ...(item.status === 'active' ? [{ key: 'terminate' }] : []),
            ]}
          >
            {(action) => {
              if (action.key === 'approve') return (
                <DropdownItem key="approve" startContent={<CheckCircle size={14} />} className="text-success">
                  {t('federation.approve')}
                </DropdownItem>
              );
              if (action.key === 'reject') return (
                <DropdownItem key="reject" startContent={<XCircle size={14} />} className="text-danger" color="danger">
                  {t('federation.reject')}
                </DropdownItem>
              );
              return (
                <DropdownItem key="terminate" startContent={<Ban size={14} />} className="text-danger" color="danger">
                  {t('federation.terminate')}
                </DropdownItem>
              );
            }}
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title={t('federation.partnerships_title')} description={t('federation.partnerships_desc')} />
        <EmptyState icon={Handshake} title={t('federation.no_partnerships')} description={t('federation.desc_no_federation_partnerships_have_been_est')} />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('federation.partnerships_title')}
        description={t('federation.partnerships_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('federation.refresh')}</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />

      {terminateTarget && (
        <ConfirmModal
          isOpen={!!terminateTarget}
          onClose={() => setTerminateTarget(null)}
          onConfirm={handleTerminate}
          title={t('federation.terminate_partnership')}
          message={t('federation.terminate_partnership_confirm', { name: terminateTarget.partner_name })}
          confirmLabel={t('federation.terminate')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default Partnerships;
