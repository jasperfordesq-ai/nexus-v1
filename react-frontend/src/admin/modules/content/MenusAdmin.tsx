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
import { Menu, Plus, Pencil, Trash2, Info, AlertTriangle, CheckCircle2, Circle } from 'lucide-react';
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
  const [locationFilter, setLocationFilter] = useState<string | null>(null);

  const LOCATIONS = ['header-main', 'header-secondary', 'footer', 'sidebar', 'mobile'];
  const filteredData = locationFilter
    ? data.filter((m) => m.location === locationFilter)
    : data;

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

      {/* Developer status notice */}
      <div className="p-4 mb-4 rounded-lg bg-amber-500/5 border border-amber-500/20">
        <div className="flex items-start gap-3">
          <AlertTriangle size={18} className="text-amber-500 shrink-0 mt-0.5" />
          <div className="text-sm space-y-2">
            <p className="font-medium text-theme-primary">Menu Manager — Development Status</p>
            <p className="text-theme-muted">
              The admin builder is fully functional (create, edit, reorder, delete menus and items). However,
              custom menus do not yet appear on the live site. The menu system kill switch is currently
              <strong className="text-amber-600 dark:text-amber-400"> OFF</strong> — the frontend uses hardcoded
              navigation until custom menus are created and the switch is enabled.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 pt-1">
              <div className="flex items-center gap-2">
                <CheckCircle2 size={14} className="text-green-500 shrink-0" />
                <span className="text-theme-muted">Admin CRUD &amp; drag-drop reordering</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle2 size={14} className="text-green-500 shrink-0" />
                <span className="text-theme-muted">Icon picker &amp; visibility rules</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle2 size={14} className="text-green-500 shrink-0" />
                <span className="text-theme-muted">Tenant-scoped API (backend ready)</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle2 size={14} className="text-green-500 shrink-0" />
                <span className="text-theme-muted">Frontend integration code (ready)</span>
              </div>
              <div className="flex items-center gap-2">
                <Circle size={14} className="text-amber-500 shrink-0" />
                <span className="text-theme-muted">Kill switch OFF (enable after creating menus)</span>
              </div>
              <div className="flex items-center gap-2">
                <Circle size={14} className="text-amber-500 shrink-0" />
                <span className="text-theme-muted">Production testing pending</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Location filter chips */}
      {data.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-4">
          <Chip
            variant={locationFilter === null ? 'solid' : 'flat'}
            color={locationFilter === null ? 'primary' : 'default'}
            className="cursor-pointer"
            onClick={() => setLocationFilter(null)}
          >
            All ({data.length})
          </Chip>
          {LOCATIONS.map((loc) => {
            const count = data.filter((m) => m.location === loc).length;
            if (count === 0) return null;
            return (
              <Chip
                key={loc}
                variant={locationFilter === loc ? 'solid' : 'flat'}
                color={locationFilter === loc ? 'primary' : 'default'}
                className="cursor-pointer capitalize"
                onClick={() => setLocationFilter(locationFilter === loc ? null : loc)}
              >
                {loc} ({count})
              </Chip>
            );
          })}
        </div>
      )}

      {/* Info notice when no custom menus exist */}
      {data.length === 0 && (
        <div className="flex items-start gap-3 p-4 mb-4 rounded-lg bg-blue-500/5 border border-blue-500/20">
          <Info size={18} className="text-blue-500 shrink-0 mt-0.5" />
          <div className="text-sm">
            <p className="font-medium text-theme-primary">Default menus are active</p>
            <p className="text-theme-muted mt-0.5">
              The navigation is currently using hardcoded defaults. Create custom menus below to
              override the default navigation for your community.
            </p>
          </div>
        </div>
      )}

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
          data={filteredData}
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
