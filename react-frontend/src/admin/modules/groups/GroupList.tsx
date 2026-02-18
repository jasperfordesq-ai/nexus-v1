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
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminGroup } from '../../api/types';

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
  usePageTitle('Admin - Groups');
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
          setTotal(data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminGroup[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error('Failed to load groups');
    } finally {
      setLoading(false);
    }
  }, [page, status, search]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await adminGroups.delete(confirmDelete.id);
      if (res?.success) {
        toast.success('Group deleted successfully');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to delete group');
      }
    } catch {
      toast.error('An unexpected error occurred');
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
        toast.success(`Group "${item.name}" ${newStatus === 'active' ? 'activated' : 'deactivated'}`);
        loadItems();
      } else {
        toast.error('Failed to update group status');
      }
    } catch {
      toast.error('Failed to update group status');
    }
  };

  const columns: Column<AdminGroup>[] = [
    {
      key: 'name',
      label: 'Group',
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-3">
          <Avatar
            src={item.image_url || undefined}
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
      label: 'Status',
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
      label: 'Visibility',
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
      label: 'Members',
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
      label: 'Creator',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.creator_name || 'Unknown'}</span>
      ),
    },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label="Actions">
              <MoreVertical size={16} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label="Group actions"
            onAction={(key) => {
              if (key === 'view') navigate(tenantPath(`/groups/${item.id}`));
              else if (key === 'toggle-status') handleStatusToggle(item);
              else if (key === 'delete') setConfirmDelete(item);
            }}
          >
            <DropdownItem key="view" startContent={<Eye size={14} />}>
              View Group
            </DropdownItem>
            <DropdownItem
              key="toggle-status"
              startContent={item.status === 'active' ? <PowerOff size={14} /> : <Power size={14} />}
              className={item.status === 'active' ? 'text-warning' : 'text-success'}
            >
              {item.status === 'active' ? 'Deactivate' : 'Activate'}
            </DropdownItem>
            <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
              Delete
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Groups"
        description="Manage community groups and memberships"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              size="sm"
              onPress={() => navigate(tenantPath('/admin/groups/analytics'))}
            >
              Analytics
            </Button>
            <Button
              variant="flat"
              size="sm"
              onPress={() => navigate(tenantPath('/admin/groups/approvals'))}
            >
              Approvals
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
          <Tab key="all" title="All" />
          <Tab key="active" title="Active" />
          <Tab key="pending" title="Pending" />
          <Tab key="inactive" title="Inactive" />
          <Tab key="archived" title="Archived" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search groups..."
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
          title="Delete Group"
          message={`Are you sure you want to delete "${confirmDelete.name}"? This will also remove all memberships. This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default GroupList;
