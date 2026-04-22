// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tenant List
 * Full tenant management with search, filter, and CRUD actions.
 */

import { useState, useCallback, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Button,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Tabs,
  Tab,
} from '@heroui/react';
import Plus from 'lucide-react/icons/plus';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Edit from 'lucide-react/icons/square-pen';
import Eye from 'lucide-react/icons/eye';
import Trash2 from 'lucide-react/icons/trash-2';
import Shield from 'lucide-react/icons/shield';
import ToggleLeft from 'lucide-react/icons/toggle-left';
import ToggleRight from 'lucide-react/icons/toggle-right';
import Network from 'lucide-react/icons/network';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';
import type { SuperAdminTenant } from '../../api/types';

export function TenantList() {
  usePageTitle("Super Admin");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [tenants, setTenants] = useState<SuperAdminTenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');

  const [lastRefreshed, setLastRefreshed] = useState<Date | null>(null);

  const [confirmAction, setConfirmAction] = useState<{
    type: 'delete' | 'deactivate' | 'reactivate' | 'toggle-hub';
    tenant: SuperAdminTenant;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadTenants = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {};
      if (search) params.search = search;
      if (filter === 'active') params.is_active = true;
      if (filter === 'inactive') params.is_active = false;
      if (filter === 'hub') params.hub = true;

      const res = await adminSuper.listTenants(params as { search?: string; is_active?: boolean; hub?: boolean });
      if (res.success && res.data) {
        setTenants(Array.isArray(res.data) ? res.data : []);
        setLastRefreshed(new Date());
      } else if (!res.success) {
        toast.error(`${"Tenants"}: ${res.error || "Failed to load tenant list"}`);
      }
    } catch (err) {
      toast.error(`${"Tenants error"}: ${err instanceof Error ? err.message : "Unknown error"}`);
    }
    setLoading(false);
  }, [filter, search, toast])


  useEffect(() => {
    loadTenants();
  }, [loadTenants]);

  const handleAction = async () => {
    if (!confirmAction) return;
    setActionLoading(true);

    const { type, tenant } = confirmAction;
    let res;

    switch (type) {
      case 'delete':
        res = await adminSuper.deleteTenant(tenant.id);
        break;
      case 'deactivate':
        res = await adminSuper.updateTenant(tenant.id, { is_active: false });
        break;
      case 'reactivate':
        res = await adminSuper.reactivateTenant(tenant.id);
        break;
      case 'toggle-hub':
        res = await adminSuper.toggleHub(tenant.id, !tenant.allows_subtenants);
        break;
    }

    if (res?.success) {
      toast.success(`Tenant Action succeeded`);
      loadTenants();
    } else {
      toast.error(res?.error || `Failed to action tenant`);
    }

    setActionLoading(false);
    setConfirmAction(null);
  };

  const confirmMessages: Record<string, { title: string; message: string; label: string }> = {
    delete: {
      title: "Are you sure you want to delete title?",
      message: "Confirm Delete",
      label: "Confirm Delete",
    },
    deactivate: {
      title: "Are you sure you want to deactivate title?",
      message: "Confirm Deactivate",
      label: "Confirm Deactivate",
    },
    reactivate: {
      title: "Are you sure you want to reactivate title?",
      message: "Confirm Reactivate",
      label: "Confirm Reactivate",
    },
    'toggle-hub': {
      title: "Are you sure you want to toggle hub title?",
      message: "Confirm Toggle Hub",
      label: "Confirm Toggle Hub",
    },
  };

  function TenantActionsMenu({ tenant }: { tenant: SuperAdminTenant }) {
    type ActionKey = 'view' | 'edit' | 'toggle-hub' | 'deactivate' | 'reactivate' | 'delete';

    const handleMenuAction = (key: React.Key) => {
      const action = key as ActionKey;
      if (action === 'view') {
        navigate(tenantPath(`/admin/super/tenants/${tenant.id}`));
      } else if (action === 'edit') {
        navigate(tenantPath(`/admin/super/tenants/${tenant.id}/edit`));
      } else {
        setConfirmAction({ type: action as 'delete' | 'deactivate' | 'reactivate' | 'toggle-hub', tenant });
      }
    };

    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light" aria-label={"Tenant Actions"}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label={"Tenant Actions"} onAction={handleMenuAction}>
          <DropdownItem key="view" startContent={<Eye size={14} />}>
            {"View"}
          </DropdownItem>
          <DropdownItem key="edit" startContent={<Edit size={14} />}>
            {"Edit"}
          </DropdownItem>
          <DropdownItem key="toggle-hub" startContent={tenant.allows_subtenants ? <ToggleLeft size={14} /> : <ToggleRight size={14} />}>
            {tenant.allows_subtenants ? "Disable Hub" : "Enable Hub"}
          </DropdownItem>
          {tenant.is_active ? (
            <DropdownItem key="deactivate" startContent={<Shield size={14} />} className="text-warning" color="warning">
              {"Deactivate"}
            </DropdownItem>
          ) : (
            <DropdownItem key="reactivate" startContent={<Shield size={14} />} className="text-success" color="success">
              {"Reactivate"}
            </DropdownItem>
          )}
          <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
            {"Delete"}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  const columns: Column<SuperAdminTenant>[] = [
    {
      key: 'name',
      label: "Tenant",
      sortable: true,
      render: (tenant) => (
        <div>
          <Link
            to={tenantPath(`/admin/super/tenants/${tenant.id}`)}
            className="font-medium text-foreground hover:text-primary"
          >
            {tenant.name}
          </Link>
          <p className="text-xs text-default-400">{tenant.slug}</p>
        </div>
      ),
    },
    {
      key: 'domain',
      label: "Domain",
      render: (tenant) => (
        <span className="text-sm text-default-500">{tenant.domain || '---'}</span>
      ),
    },
    {
      key: 'is_active',
      label: "Status",
      sortable: true,
      render: (tenant) => (
        <Chip size="sm" variant="flat" color={tenant.is_active ? 'success' : 'default'}>
          {tenant.is_active ? "Active" : "Inactive"}
        </Chip>
      ),
    },
    {
      key: 'user_count',
      label: "Users",
      sortable: true,
      render: (tenant) => <span>{tenant.user_count ?? 0}</span>,
    },
    {
      key: 'allows_subtenants',
      label: "Hub",
      render: (tenant) =>
        tenant.allows_subtenants ? (
          <Chip size="sm" variant="flat" color="secondary">{"Hub"}</Chip>
        ) : (
          <span className="text-default-400">---</span>
        ),
    },
    {
      key: 'parent_name',
      label: "Parent",
      render: (tenant) => (
        <span className="text-sm text-default-500">
          {tenant.parent_name || '---'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: "Created",
      sortable: true,
      render: (tenant) => (
        <span className="text-sm text-default-500">
          {new Date(tenant.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (tenant) => <TenantActionsMenu tenant={tenant} />,
    },
  ];

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">{"Super Admin"}</Link>
        <span>/</span>
        <span className="text-foreground">{"Breadcrumb Tenants"}</span>
      </nav>
      <PageHeader
        title={"Tenant List"}
        description={"View and manage all tenants on the platform"}
        actions={
          <div className="flex items-center gap-2">
            {lastRefreshed && (
              <span className="text-xs text-default-400">
                Updated {lastRefreshed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              </span>
            )}
            <Button
              as={Link}
              to={tenantPath('/admin/super/tenants/hierarchy')}
              variant="flat"
              startContent={<Network size={16} />}
              size="sm"
            >
              {"View Hierarchy"}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/super/tenants/create'))}
            >
              {"Create Tenant"}
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={filter}
          onSelectionChange={(key) => { setFilter(key as string); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={"All"} />
          <Tab key="active" title={"Active"} />
          <Tab key="inactive" title={"Inactive"} />
          <Tab key="hub" title={"Hub"} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={tenants}
        isLoading={loading}
        searchPlaceholder={"Enter search tenants..."}
        onSearch={(q) => setSearch(q)}
        onRefresh={loadTenants}
      />

      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleAction}
          title={confirmMessages[confirmAction.type]?.title ?? ''}
          message={`${confirmMessages[confirmAction.type]?.message ?? ''}\n\nTenant: ${confirmAction.tenant.name}`}
          confirmLabel={confirmMessages[confirmAction.type]?.label ?? ''}
          confirmColor={confirmAction.type === 'reactivate' ? 'primary' : 'danger'}
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default TenantList;
