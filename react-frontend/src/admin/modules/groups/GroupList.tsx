// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Groups List
 * Full management for community groups with status filtering, search, and delete.
 * Parity: PHP Admin groups management
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Tabs, Tab, Button, Chip, Avatar,
  Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
} from '@heroui/react';
import { Trash2, Users, Eye, EyeOff, Lock, MoreVertical, Power, PowerOff } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { api } from '@/lib/api';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminGroup } from '../../api/types';

import { useTranslation } from 'react-i18next';
import { resolveAssetUrl } from '@/lib/helpers';
const statusColors: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  active: 'success',
  pending: 'warning',
  inactive: 'default',
  archived: 'default',
  suspended: 'danger',
};

const visibilityIcons: Record<string, typeof Eye> = {
  public: Eye,
  private: Lock,
  hidden: EyeOff,
};

export function GroupList() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [items, setItems] = useState<AdminGroup[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<AdminGroup | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminGroups.list({
        page,
        status: status === 'all' ? undefined : status,
        search: search || undefined,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setItems(data);
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminGroup[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error(t('groups.failed_to_load_groups'));
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await adminGroups.delete(confirmDelete.id);
      if (res?.success) {
        toast.success(t('groups.group_deleted_successfully'));
        loadItems();
      } else {
        toast.error(res?.error || t('groups.failed_to_delete_group'));
      }
    } catch {
      toast.error(t('groups.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const handleStatusToggle = async (item: AdminGroup) => {
    const newStatus = item.status === 'active' ? 'inactive' : 'active';
    try {
      const res = await adminGroups.updateStatus(item.id, newStatus);
      if (res?.success) {
        toast.success(t('groups.group_status_changed', { name: item.name, status: newStatus }));
        loadItems();
      } else {
        toast.error(t('groups.failed_to_update_group_status'));
      }
    } catch {
      toast.error(t('groups.failed_to_update_group_status'));
    }
  };

  const handleArchive = async (item: AdminGroup) => {
    const action = item.status === 'archived' ? 'unarchive' : 'archive';
    try {
      const res = await api.post(`/v2/admin/groups/${item.id}/${action}`);
      if (res?.success) {
        toast.success(`Group ${action}d: ${item.name}`);
        loadItems();
      }
    } catch {
      toast.error(`Failed to ${action} group`);
    }
  };

  const handleClone = async (item: AdminGroup) => {
    const name = prompt('Name for cloned group:', `${item.name} (Copy)`);
    if (!name) return;
    try {
      const res = await api.post(`/v2/admin/groups/${item.id}/clone`, { name, clone_members: false });
      if (res?.success) {
        toast.success(`Group cloned: ${name}`);
        loadItems();
      }
    } catch {
      toast.error('Failed to clone group');
    }
  };

  const columns: Column<AdminGroup>[] = [
    {
      key: 'name',
      label: t('groups.col_group'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-3">
          <Avatar
            src={item.image_url ? resolveAssetUrl(item.image_url) : undefined}
            name={item.name}
            size="sm"
            className="shrink-0"
          />
          <div className="min-w-0">
            <p className="font-medium text-foreground truncate">{item.name}</p>
            {item.description && (
              <p className="text-xs text-default-400 truncate max-w-xs">
                {item.description}
              </p>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'status',
      label: t('groups.col_status'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={statusColors[item.status] || 'default'}
          className="capitalize"
        >
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'visibility',
      label: t('groups.col_visibility'),
      sortable: true,
      render: (item) => {
        const Icon = visibilityIcons[item.visibility] || Eye;
        return (
          <div className="flex items-center gap-1.5">
            <Icon size={14} className="text-default-400" />
            <span className="text-sm text-default-600 capitalize">{item.visibility}</span>
          </div>
        );
      },
    },
    {
      key: 'member_count',
      label: t('groups.col_members'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1.5">
          <Users size={14} className="text-default-400" />
          <span className="text-sm text-default-600">{item.member_count}</span>
        </div>
      ),
    },
    {
      key: 'creator_name',
      label: t('groups.col_creator'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.creator_name || t('groups.unknown')}</span>
      ),
    },
    {
      key: 'created_at',
      label: t('groups.col_created'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('groups.col_actions'),
      render: (item) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label={t('groups.label_actions')}>
              <MoreVertical size={16} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label={t('groups.label_group_actions')}
            onAction={(key) => {
              if (key === 'view') navigate(tenantPath(`/groups/${item.id}`));
              else if (key === 'toggle-status') handleStatusToggle(item);
              else if (key === 'archive') handleArchive(item);
              else if (key === 'clone') handleClone(item);
              else if (key === 'audit') navigate(tenantPath(`/admin/groups/${item.id}?tab=audit`));
              else if (key === 'delete') setConfirmDelete(item);
            }}
          >
            <DropdownItem key="view" startContent={<Eye size={14} />}>
              {t('groups.view_group')}
            </DropdownItem>
            <DropdownItem
              key="toggle-status"
              startContent={item.status === 'active' ? <PowerOff size={14} /> : <Power size={14} />}
              className={item.status === 'active' ? 'text-warning' : 'text-success'}
            >
              {item.status === 'active' ? t('groups.deactivate') : t('groups.activate')}
            </DropdownItem>
            <DropdownItem
              key="archive"
              startContent={<EyeOff size={14} />}
            >
              {item.status === 'archived' ? 'Unarchive' : 'Archive'}
            </DropdownItem>
            <DropdownItem key="clone" startContent={<Users size={14} />}>
              Clone Group
            </DropdownItem>
            <DropdownItem key="audit" startContent={<Eye size={14} />}>
              Audit Log
            </DropdownItem>
            <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
              {t('common.delete')}
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('groups.group_list_title')}
        description={t('groups.group_list_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              size="sm"
              onPress={() => navigate(tenantPath('/admin/groups/analytics'))}
            >
              {t('groups.analytics')}
            </Button>
            <Button
              variant="flat"
              size="sm"
              onPress={() => navigate(tenantPath('/admin/groups/approvals'))}
            >
              {t('groups.approvals')}
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => { setStatus(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('groups.tab_all')} />
          <Tab key="active" title={t('groups.tab_active')} />
          <Tab key="pending" title={t('groups.tab_pending')} />
          <Tab key="inactive" title={t('groups.tab_inactive')} />
          <Tab key="archived" title={t('groups.tab_archived')} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={t('groups.search_groups_placeholder')}
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={t('groups.delete_group')}
          message={t('groups.confirm_delete_group', { name: confirmDelete.name })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default GroupList;
