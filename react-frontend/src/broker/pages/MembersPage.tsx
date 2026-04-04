// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Members Page
 * Lists members with tab filtering (All / Pending / Active / Suspended),
 * search, pagination, and approve/suspend/reactivate actions.
 */

import { useEffect, useState, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Tabs,
  Tab,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
} from '@heroui/react';
import { MoreVertical } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminUsers } from '@/admin/api/adminApi';
import type { AdminUser } from '@/admin/api/types';
import { DataTable, PageHeader, ConfirmModal } from '@/admin/components';
import type { Column } from '@/admin/components';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

type StatusTab = 'all' | 'pending' | 'active' | 'suspended';

const STATUS_COLOR_MAP: Record<string, 'warning' | 'success' | 'danger' | 'default'> = {
  pending: 'warning',
  active: 'success',
  suspended: 'danger',
};

const PAGE_SIZE = 20;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function MembersPage() {
  const { t } = useTranslation('broker');
  const toast = useToast();
  const { tenantPath } = useTenant();
  usePageTitle(t('members.title') + ' - Broker');

  // Data state
  const [members, setMembers] = useState<AdminUser[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);

  // Filter / pagination state
  const [activeTab, setActiveTab] = useState<StatusTab>('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  // Action modal state
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'suspend';
    user: AdminUser;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // ─── Fetch members ────────────────────────────────────────────────────────

  const fetchMembers = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {
        page,
        limit: PAGE_SIZE,
      };
      if (activeTab !== 'all') params.status = activeTab;
      if (search.trim()) params.search = search.trim();

      const res = await adminUsers.list(params as Parameters<typeof adminUsers.list>[0]);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setMembers(payload as AdminUser[]);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: AdminUser[]; meta?: { total: number } };
          setMembers(paged.data || []);
          setTotal(paged.meta?.total ?? 0);
        }
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setLoading(false);
    }
  }, [page, activeTab, search, toast, t]);

  useEffect(() => {
    fetchMembers();
  }, [fetchMembers]);

  // ─── Tab change ───────────────────────────────────────────────────────────

  const handleTabChange = useCallback((key: React.Key) => {
    setActiveTab(key as StatusTab);
    setPage(1);
  }, []);

  // ─── Search ───────────────────────────────────────────────────────────────

  const handleSearch = useCallback((query: string) => {
    setSearch(query);
    setPage(1);
  }, []);

  // ─── Actions ──────────────────────────────────────────────────────────────

  const handleApprove = useCallback(async () => {
    if (!confirmAction || confirmAction.type !== 'approve') return;
    setActionLoading(true);
    try {
      const res = await adminUsers.approve(confirmAction.user.id);
      if (res.success) {
        toast.success(t('members.approved_success'));
        setConfirmAction(null);
        fetchMembers();
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setActionLoading(false);
    }
  }, [confirmAction, toast, t, fetchMembers]);

  const handleSuspend = useCallback(async () => {
    if (!confirmAction || confirmAction.type !== 'suspend') return;
    setActionLoading(true);
    try {
      const res = await adminUsers.suspend(confirmAction.user.id);
      if (res.success) {
        toast.success(t('members.suspended_success'));
        setConfirmAction(null);
        fetchMembers();
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setActionLoading(false);
    }
  }, [confirmAction, toast, t, fetchMembers]);

  const handleReactivate = useCallback(
    async (user: AdminUser) => {
      try {
        const res = await adminUsers.reactivate(user.id);
        if (res.success) {
          toast.success(t('members.reactivated_success'));
          fetchMembers();
        } else {
          toast.error(t('members.action_failed'));
        }
      } catch {
        toast.error(t('members.action_failed'));
      }
    },
    [toast, t, fetchMembers],
  );

  // ─── Columns ──────────────────────────────────────────────────────────────

  const columns: Column<AdminUser>[] = useMemo(
    () => [
      {
        key: 'name',
        label: t('members.col_name'),
        sortable: true,
        render: (user: AdminUser) => (
          <span className="font-medium text-foreground">{user.name}</span>
        ),
      },
      {
        key: 'email',
        label: t('members.col_email'),
        sortable: true,
      },
      {
        key: 'status',
        label: t('members.col_status'),
        render: (user: AdminUser) => (
          <Chip
            size="sm"
            variant="flat"
            color={STATUS_COLOR_MAP[user.status] ?? 'default'}
            className="capitalize"
          >
            {t(`status.${user.status}`, { defaultValue: user.status })}
          </Chip>
        ),
      },
      {
        key: 'created_at',
        label: t('members.col_joined'),
        sortable: true,
        render: (user: AdminUser) =>
          new Date(user.created_at).toLocaleDateString(),
      },
      {
        key: 'actions',
        label: t('members.col_actions'),
        render: (user: AdminUser) => (
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly variant="light" size="sm" aria-label={t('members.col_actions')}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu aria-label={t('members.col_actions')}>
              {user.status === 'pending' ? (
                <DropdownItem
                  key="approve"
                  onPress={() => setConfirmAction({ type: 'approve', user })}
                >
                  {t('members.approve')}
                </DropdownItem>
              ) : null}
              {user.status === 'active' ? (
                <DropdownItem
                  key="suspend"
                  className="text-danger"
                  onPress={() => setConfirmAction({ type: 'suspend', user })}
                >
                  {t('members.suspend')}
                </DropdownItem>
              ) : null}
              {user.status === 'suspended' ? (
                <DropdownItem
                  key="reactivate"
                  onPress={() => handleReactivate(user)}
                >
                  {t('members.reactivate')}
                </DropdownItem>
              ) : null}
              <DropdownItem
                key="view"
                onPress={() =>
                  window.open(tenantPath(`/members/${user.id}`), '_blank')
                }
              >
                {t('members.view_profile')}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        ),
      },
    ],
    [t, tenantPath, handleReactivate],
  );

  // ─── Render ───────────────────────────────────────────────────────────────

  const tabContent = (
    <Tabs
      selectedKey={activeTab}
      onSelectionChange={handleTabChange}
      variant="underlined"
      classNames={{ tabList: 'mb-4' }}
    >
      <Tab key="all" title={t('members.tab_all')} />
      <Tab key="pending" title={t('members.tab_pending')} />
      <Tab key="active" title={t('members.tab_active')} />
      <Tab key="suspended" title={t('members.tab_suspended')} />
    </Tabs>
  );

  return (
    <div className="max-w-7xl mx-auto space-y-4">
      <PageHeader
        title={t('members.title')}
        description={t('members.description')}
      />

      {tabContent}

      <DataTable<AdminUser>
        columns={columns}
        data={members}
        keyField="id"
        isLoading={loading}
        searchable
        searchPlaceholder={t('members.search_placeholder')}
        totalItems={total}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
        onSearch={handleSearch}
        onRefresh={fetchMembers}
        emptyContent={
          <div className="flex flex-col items-center py-8">
            <p className="text-default-400">{t('common.no_data')}</p>
          </div>
        }
      />

      {/* Approve confirmation */}
      <ConfirmModal
        isOpen={confirmAction?.type === 'approve'}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleApprove}
        title={t('members.confirm_approve_title')}
        message={t('members.confirm_approve_message')}
        confirmLabel={t('members.approve')}
        confirmColor="primary"
        isLoading={actionLoading}
      />

      {/* Suspend confirmation */}
      <ConfirmModal
        isOpen={confirmAction?.type === 'suspend'}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleSuspend}
        title={t('members.confirm_suspend_title')}
        message={t('members.confirm_suspend_message')}
        confirmLabel={t('members.suspend')}
        confirmColor="danger"
        isLoading={actionLoading}
      />
    </div>
  );
}
