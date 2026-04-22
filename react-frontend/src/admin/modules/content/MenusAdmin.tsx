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
import { Menu, Plus, Pencil, Trash2, Info } from 'lucide-react';
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
  usePageTitle("Content");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [data, setData] = useState<MenuItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<MenuItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [locationFilter, setLocationFilter] = useState<string | null>(null);

  const LOCATIONS: string[] = [
    'header-main',
    'header-secondary',
    'footer',
    'sidebar',
    'mobile',
  ];

  const LOCATION_LABELS: Record<string, string> = {
    'header-main': "Main Header",
    'header-secondary': "Secondary Header",
    'footer': "Footer",
    'sidebar': "Sidebar",
    'mobile': "Mobile",
  };

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
      toast.error("Failed to load menus");
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminMenus.delete(confirmDelete.id);
      if (res?.success) {
        toast.success("Menu deleted successfully");
        fetchData();
      } else {
        toast.error("Failed to delete menu");
      }
    } catch {
      toast.error("An unexpected error occurred");
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<MenuItem>[] = [
    {
      key: 'name',
      label: "Name",
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
      label: "Location",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {LOCATION_LABELS[item.location] ?? item.location ?? '--'}
        </span>
      ),
    },
    {
      key: 'item_count',
      label: "Items",
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{item.item_count ?? 0}</span>,
    },
    {
      key: 'is_active',
      label: "Active",
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.is_active ? 'success' : 'default'}>
          {item.is_active ? "Active" : "Inactive"}
        </Chip>
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
            color="primary"
            onPress={() => navigate(tenantPath(`/admin/menus/builder/${item.id}`))}
            aria-label={"Edit Menu"}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={"Delete Menu"}
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
        <PageHeader title={"Menus Admin"} description={"Create and manage custom navigation menus for your platform"} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"Menus Admin"}
        description={"Create and manage custom navigation menus for your platform"}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/menus/builder/new'))}
          >
            {"Create Menu"}
          </Button>
        }
      />

      {/* How it works notice */}
      <div className="flex items-start gap-3 p-4 mb-4 rounded-lg bg-primary-50/50 dark:bg-primary-900/10 border border-primary-200 dark:border-primary-800">
        <Info size={16} className="text-primary-500 shrink-0 mt-0.5" />
        <div className="text-sm">
          <p className="font-medium text-theme-primary">{"How Navigation Works"}</p>
          <p className="mt-0.5 text-theme-muted">{"Create custom navigation menus to replace the default platform navigation. Changes apply immediately."}</p>
        </div>
      </div>

      {/* Info notice when no custom menus exist */}
      {data.length === 0 && (
        <div className="flex items-start gap-3 p-4 mb-4 rounded-medium bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300">
          <Info size={16} className="shrink-0 mt-0.5" />
          <p className="text-sm">{"No custom menus are configured. The platform is using the built-in default navigation."}</p>
        </div>
      )}

      {/* Location filter chips */}
      {data.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-4">
          <Chip
            variant={locationFilter === null ? 'solid' : 'flat'}
            color={locationFilter === null ? 'primary' : 'default'}
            className="cursor-pointer"
            onClick={() => setLocationFilter(null)}
          >
            {`All (${data.length})`}
          </Chip>
          {LOCATIONS.map((loc) => {
            const count = data.filter((m) => m.location === loc).length;
            if (count === 0) return null;
            return (
              <Chip
                key={loc}
                variant={locationFilter === loc ? 'solid' : 'flat'}
                color={locationFilter === loc ? 'primary' : 'default'}
                className="cursor-pointer"
                onClick={() => setLocationFilter(locationFilter === loc ? null : loc)}
              >
                {LOCATION_LABELS[loc] ?? loc} ({count})
              </Chip>
            );
          })}
        </div>
      )}

      {data.length === 0 ? (
        <EmptyState
          icon={Menu}
          title={"No data available"}
          description={"Create custom navigation menus to replace or extend the default platform navigation"}
          actionLabel={"Create Menu"}
          onAction={() => navigate(tenantPath('/admin/menus/builder/new'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={filteredData}
          searchPlaceholder={"Search"}
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={"Delete Menu"}
          message={`Delete Campaign`}
          confirmLabel={"Delete"}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MenusAdmin;
