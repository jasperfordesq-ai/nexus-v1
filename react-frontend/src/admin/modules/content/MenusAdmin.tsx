// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Menus Admin
 * Manage navigation menus for the platform.
 * Wired to adminMenus API for full CRUD.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Chip } from '@heroui/react';
import { Menu, Plus, Pencil, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMenus } from '../../api/adminApi';
import { PageHeader, DataTable, EmptyState, ConfirmModal, type Column } from '../../components';

interface MenuItem {
  id: number;
  name: string;
  slug: string;
  location: string;
  is_active: boolean;
  item_count: number;
}

export function MenusAdmin() {
  usePageTitle('Admin - Menus');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [data, setData] = useState<MenuItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<MenuItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminMenus.list();
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setData(result);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: MenuItem[] };
          setData(pd.data || []);
        }
      }
    } catch {
      toast.error('Failed to load menus');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminMenus.delete(confirmDelete.id);
      if (res?.success) {
        toast.success('Menu deleted successfully');
        fetchData();
      } else {
        toast.error('Failed to delete menu');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<MenuItem>[] = [
    {
      key: 'name',
      label: 'Name',
      sortable: true,
      render: (item) => (
        <Button
          type="button"
          variant="light"
          onPress={() => navigate(tenantPath(`/admin/menus/builder/${item.id}`))}
          className="text-left font-medium text-primary hover:underline min-w-0 h-auto p-0 justify-start"
        >
          {item.name}
        </Button>
      ),
    },
    {
      key: 'location',
      label: 'Location',
      sortable: true,
      render: (item) => <span className="text-sm text-default-500 capitalize">{item.location || '--'}</span>,
    },
    {
      key: 'item_count',
      label: 'Items',
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{item.item_count ?? 0}</span>,
    },
    {
      key: 'is_active',
      label: 'Active',
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.is_active ? 'success' : 'default'}>
          {item.is_active ? 'Active' : 'Inactive'}
        </Chip>
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
            color="primary"
            onPress={() => navigate(tenantPath(`/admin/menus/builder/${item.id}`))}
            aria-label="Edit menu"
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label="Delete menu"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title="Menus" description="Manage navigation menus" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Menus"
        description="Manage navigation menus"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/menus/builder/new'))}
          >
            Create Menu
          </Button>
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={Menu}
          title="No Custom Menus"
          description="Create custom navigation menus for your community. Menus can be used in the header, footer, or sidebar."
          actionLabel="Create Menu"
          onAction={() => navigate(tenantPath('/admin/menus/builder/new'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder="Search menus..."
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Menu"
          message={`Are you sure you want to delete "${confirmDelete.name}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MenusAdmin;
