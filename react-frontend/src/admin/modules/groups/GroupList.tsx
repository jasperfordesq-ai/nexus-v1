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
  Tabs, Tab, Button, Chip, Avatar, Checkbox,
  Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Input,
} from '@heroui/react';
import { Trash2, Users, Eye, EyeOff, Lock, MoreVertical, Power, PowerOff, Pencil } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { api } from '@/lib/api';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminGroup } from '../../api/types';

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
  usePageTitle("Groups");
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
  const [cloneTarget, setCloneTarget] = useState<AdminGroup | null>(null);
  const [cloneName, setCloneName] = useState('');
  const [cloneLoading, setCloneLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

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
      toast.error("Failed to load groups");
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await adminGroups.delete(confirmDelete.id);
      if (res?.success) {
        toast.success("Group deleted successfully");
        loadItems();
      } else {
        toast.error(res?.error || "Failed to delete group");
      }
    } catch {
      toast.error("An unexpected error occurred");
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
        toast.success(`Group status changed`);
        loadItems();
      } else {
        toast.error("Failed to update group status");
      }
    } catch {
      toast.error("Failed to update group status");
    }
  };

  const handleArchive = async (item: AdminGroup) => {
    const action = item.status === 'archived' ? 'unarchive' : 'archive';
    try {
      const res = await api.post(`/v2/admin/groups/${item.id}/${action}`);
      if (res?.success) {
        toast.success(`Group Action succeeded`);
        loadItems();
      }
    } catch {
      toast.error(`Failed to action group`);
    }
  };

  const handleCloneOpen = (item: AdminGroup) => {
    setCloneTarget(item);
    setCloneName(`${item.name} (Copy)`);
  };

  const handleCloneConfirm = async () => {
    if (!cloneTarget || !cloneName.trim()) return;
    setCloneLoading(true);
    try {
      const res = await api.post(`/v2/admin/groups/${cloneTarget.id}/clone`, { name: cloneName.trim(), clone_members: false });
      if (res?.success) {
        toast.success(`Group Cloned`);
        setCloneTarget(null);
        setCloneName('');
        loadItems();
      }
    } catch {
      toast.error("Failed to clone group");
    } finally {
      setCloneLoading(false);
    }
  };

  const handleBulkArchive = async () => {
    try {
      await api.post('/v2/admin/groups/bulk-archive', { group_ids: Array.from(selectedIds) });
      toast.success(`Groups Archived`);
      setSelectedIds(new Set());
      loadItems();
    } catch { toast.error("Failed to archive groups"); }
  };

  const handleBulkDelete = async () => {
    if (!confirm(`Are you sure you want to bulk delete?`)) return;
    // Delete one by one (no bulk delete endpoint)
    for (const id of selectedIds) {
      try { await adminGroups.delete(id); } catch { /* skip failures */ }
    }
    toast.success(`Groups deleted`);
    setSelectedIds(new Set());
    loadItems();
  };

  const columns: Column<AdminGroup>[] = [
    {
      key: 'select',
      label: (
        <Checkbox
          isSelected={selectedIds.size === items.length && items.length > 0}
          onValueChange={(checked) => {
            if (checked) {
              setSelectedIds(new Set(items.map(i => i.id)));
            } else {
              setSelectedIds(new Set());
            }
          }}
          aria-label={"Select All"}
        />
      ),
      render: (item) => (
        <Checkbox
          isSelected={selectedIds.has(item.id)}
          onValueChange={(checked) => {
            const next = new Set(selectedIds);
            if (checked) next.add(item.id);
            else next.delete(item.id);
            setSelectedIds(next);
          }}
          aria-label={`Select Item`}
        />
      ),
    },
    {
      key: 'name',
      label: "Group",
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
      label: "Status",
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
      label: "Visibility",
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
      label: "Members",
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
      label: "Creator",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.creator_name || "Unknown"}</span>
      ),
    },
    {
      key: 'created_at',
      label: "Created",
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
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label={"Actions"}>
              <MoreVertical size={16} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label={"Group Actions"}
            onAction={(key) => {
              if (key === 'view') navigate(tenantPath(`/groups/${item.id}`));
              else if (key === 'edit') navigate(tenantPath(`/admin/groups/${item.id}/edit`));
              else if (key === 'toggle-status') handleStatusToggle(item);
              else if (key === 'archive') handleArchive(item);
              else if (key === 'clone') handleCloneOpen(item);
              else if (key === 'audit') navigate(tenantPath(`/admin/groups/${item.id}?tab=audit`));
              else if (key === 'delete') setConfirmDelete(item);
            }}
          >
            <DropdownItem key="view" startContent={<Eye size={14} />}>
              {"View Group"}
            </DropdownItem>
            <DropdownItem key="edit" startContent={<Pencil size={14} />}>
              {"Edit Group"}
            </DropdownItem>
            <DropdownItem
              key="toggle-status"
              startContent={item.status === 'active' ? <PowerOff size={14} /> : <Power size={14} />}
              className={item.status === 'active' ? 'text-warning' : 'text-success'}
            >
              {item.status === 'active' ? "Deactivate" : "Activate"}
            </DropdownItem>
            <DropdownItem
              key="archive"
              startContent={<EyeOff size={14} />}
            >
              {item.status === 'archived' ? "Unarchive" : "Archive"}
            </DropdownItem>
            <DropdownItem key="clone" startContent={<Users size={14} />}>
              {"Clone Group"}
            </DropdownItem>
            <DropdownItem key="audit" startContent={<Eye size={14} />}>
              {"Audit Log"}
            </DropdownItem>
            <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
              {"Delete"}
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={"Group List"}
        description={"View and manage all community groups"}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              size="sm"
              onPress={() => navigate(tenantPath('/admin/groups/analytics'))}
            >
              {"Analytics"}
            </Button>
            <Button
              variant="flat"
              size="sm"
              onPress={() => navigate(tenantPath('/admin/groups/approvals'))}
            >
              {"Approvals"}
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
          <Tab key="all" title={"All"} />
          <Tab key="active" title={"Active"} />
          <Tab key="pending" title={"Pending"} />
          <Tab key="inactive" title={"Inactive"} />
          <Tab key="archived" title={"Archived"} />
        </Tabs>
      </div>

      {selectedIds.size > 0 && (
        <div className="flex items-center gap-3 p-3 mb-4 bg-primary/10 rounded-lg">
          <span className="text-sm font-medium">{`N Selected`}</span>
          <Button size="sm" variant="flat" onPress={handleBulkArchive}>{"Archive"}</Button>
          <Button size="sm" variant="flat" color="danger" onPress={handleBulkDelete}>{"Delete"}</Button>
          <Button size="sm" variant="flat" onPress={() => setSelectedIds(new Set())}>{"Clear"}</Button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={"Search groups..."}
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
          title={"Delete Group"}
          message={`Delete Group`}
          confirmLabel={"Delete"}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}

      {/* Clone Group Modal */}
      <Modal
        isOpen={!!cloneTarget}
        onClose={() => { setCloneTarget(null); setCloneName(''); }}
        size="sm"
      >
        <ModalContent>
          <ModalHeader>{"Clone Group"}</ModalHeader>
          <ModalBody>
            <Input
              label={"Clone Group Name"}
              value={cloneName}
              onValueChange={setCloneName}
              variant="bordered"
              autoFocus
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => { setCloneTarget(null); setCloneName(''); }} isDisabled={cloneLoading}>
              {"Cancel"}
            </Button>
            <Button color="primary" onPress={handleCloneConfirm} isLoading={cloneLoading} isDisabled={!cloneName.trim()}>
              {"Clone Group Confirm"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GroupList;
