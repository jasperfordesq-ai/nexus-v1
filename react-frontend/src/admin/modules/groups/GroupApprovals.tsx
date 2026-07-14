// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Group Approvals
 * Pending membership approval requests with approve/reject actions.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';import Check from 'lucide-react/icons/check';
import X from 'lucide-react/icons/x';
import UserPlus from 'lucide-react/icons/user-plus';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { ConfirmModal } from '../../components/ConfirmModal';
import { EmptyState } from '../../components/EmptyState';
import type { GroupApproval } from '../../api/types';
import { Button, Chip, Spinner } from '@/components/ui';

export function GroupApprovals() {
  const { t } = useTranslation('admin_groups');
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
  }, [toast, t])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleApprove = async (item: GroupApproval) => {
    setActionLoading(item.id);
    try {
      const res = await adminGroups.approveMember(item.id);
      if (res?.success) {
        toast.success(t('groups.approved_member'));
        loadItems();
      } else {
        toast.error(t('groups.failed_to_approve_membership'));
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
        toast.success(t('groups.rejected_member'));
        loadItems();
      } else {
        toast.error(t('groups.failed_to_reject_membership'));
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
      label: t('groups.col_user'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.user_name}</span>
      ),
    },
    {
      key: 'group_name',
      label: t('groups.col_group'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">{item.group_name}</span>
      ),
    },
    {
      key: 'status',
      label: t('groups.col_status'),
      render: () => (
        <Chip size="sm" variant="soft" color="warning" className="capitalize">
          {t('groups.pending')}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: t('groups.col_requested'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.created_at ? new Date(item.created_at).toLocaleDateString(getFormattingLocale()) : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('groups.label_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="tertiary"
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
            variant="danger"
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
      <div className="space-y-6">
        <PageHeader title={t('groups.group_approvals_title')} description={t('groups.group_approvals_desc')} />
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex items-center justify-center py-20">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t('groups.group_approvals_title')} description={t('groups.group_approvals_desc')} />

      {items.length === 0 ? (
        <EmptyState
          icon={UserPlus}
          title={t('groups.no_pending_approvals')}
          description={t('groups.desc_all_membership_requests_have_been_review')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchPlaceholder={t('groups.search_approvals_placeholder')}
          onRefresh={loadItems}
        />
      )}

      {confirmReject && (
        <ConfirmModal
          isOpen={!!confirmReject}
          onClose={() => setConfirmReject(null)}
          onConfirm={handleReject}
          title={t('groups.reject_membership')}
          message={t('groups.confirm_reject_membership')}
          confirmLabel={t('groups.reject')}
          confirmColor="danger"
          isLoading={actionLoading === confirmReject.id}
        />
      )}
    </div>
  );
}

export default GroupApprovals;
