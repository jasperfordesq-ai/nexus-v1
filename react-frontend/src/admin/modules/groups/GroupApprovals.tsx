// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Group Approvals
 * Pending membership approval requests with approve/reject actions.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Chip, Spinner } from '@heroui/react';
import { Check, X, UserPlus } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, EmptyState, type Column } from '../../components';
import type { GroupApproval } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function GroupApprovals() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
  const toast = useToast();

  const [items, setItems] = useState<GroupApproval[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [confirmReject, setConfirmReject] = useState<GroupApproval | null>(null);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminGroups.getApprovals();
      if (res.success && res.data) {
        // Handle v2 envelope
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (
          payload &&
          typeof payload === 'object' &&
          'data' in (payload as Record<string, unknown>)
        ) {
          const inner = (payload as Record<string, unknown>).data;
          setItems(Array.isArray(inner) ? inner : []);
        }
      }
    } catch {
      toast.error(t('groups.failed_to_load_approvals'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleApprove = async (item: GroupApproval) => {
    setActionLoading(item.id);
    try {
      const res = await adminGroups.approveMember(item.id);
      if (res?.success) {
        toast.success(`Approved ${item.user_name} for "${item.group_name}"`);
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to approve membership');
      }
    } catch {
      toast.error(t('groups.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleReject = async () => {
    if (!confirmReject) return;
    setActionLoading(confirmReject.id);
    try {
      const res = await adminGroups.rejectMember(confirmReject.id);
      if (res?.success) {
        toast.success(`Rejected ${confirmReject.user_name} for "${confirmReject.group_name}"`);
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to reject membership');
      }
    } catch {
      toast.error(t('groups.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(null);
      setConfirmReject(null);
    }
  };

  const columns: Column<GroupApproval>[] = [
    {
      key: 'user_name',
      label: 'User',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.user_name}</span>
      ),
    },
    {
      key: 'group_name',
      label: 'Group',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.group_name}</span>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      render: () => (
        <Chip size="sm" variant="flat" color="warning" className="capitalize">
          pending
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: 'Requested',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="success"
            isLoading={actionLoading === item.id}
            onPress={() => handleApprove(item)}
            aria-label={t('groups.label_approve_membership')}
          >
            <Check size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            isDisabled={actionLoading === item.id}
            onPress={() => setConfirmReject(item)}
            aria-label={t('groups.label_reject_membership')}
          >
            <X size={14} />
          </Button>
        </div>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={t('groups.group_approvals_title')} description={t('groups.group_approvals_desc')} />
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('groups.group_approvals_title')} description={t('groups.group_approvals_desc')} />

      {items.length === 0 ? (
        <EmptyState
          icon={UserPlus}
          title="No Pending Approvals"
          description={t('groups.desc_all_membership_requests_have_been_review')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchPlaceholder="Search approvals..."
          onRefresh={loadItems}
        />
      )}

      {confirmReject && (
        <ConfirmModal
          isOpen={!!confirmReject}
          onClose={() => setConfirmReject(null)}
          onConfirm={handleReject}
          title="Reject Membership"
          message={`Are you sure you want to reject ${confirmReject.user_name}'s request to join "${confirmReject.group_name}"?`}
          confirmLabel="Reject"
          confirmColor="danger"
          isLoading={actionLoading === confirmReject.id}
        />
      )}
    </div>
  );
}

export default GroupApprovals;
