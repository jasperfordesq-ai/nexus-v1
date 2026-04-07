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
import {
  Plus,
  MoreVertical,
  Edit,
  Eye,
  Trash2,
  Shield,
  ToggleLeft,
  ToggleRight,
  Network,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';
import type { SuperAdminTenant } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function TenantList() {
  const { t } = useTranslation('admin');
  usePageTitle(t('super.page_title'));
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
        toast.error(`${t('super.tenants')}: ${res.error || t('super.failed_to_load_tenant_list')}`);
      }
    } catch (err) {
      toast.error(`${t('super.tenants_error')}: ${err instanceof Error ? err.message : t('super.unknown_error')}`);
    }
    setLoading(false);
  }, [filter, search, toast]);

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
      toast.success(t('super.tenant_action_success', { action: type === 'toggle-hub' ? t('super.hub_toggled') : type }));
      loadTenants();
    } else {
      toast.error(res?.error || t('super.failed_to_action_tenant', { action: type }));
    }

    setActionLoading(false);
    setConfirmAction(null);
  };

  const confirmMessages: Record<string, { title: string; message: string; label: string }> = {
    delete: {
      title: 'Delete Tenant',
      message: 'This will permanently delete the tenant and all associated data. This cannot be undone.',
      label: 'Delete',
    },
    deactivate: {
      title: 'Deactivate Tenant',
      message: 'This tenant will be deactivated. Users will not be able to access it.',
      label: 'Deactivate',
    },
    reactivate: {
      title: 'Reactivate Tenant',
      message: 'This tenant will be reactivated and users will regain access.',
      label: 'Reactivate',
    },
    'toggle-hub': {
      title: 'Toggle Hub Status',
      message: 'This will toggle whether this tenant can have sub-tenants.',
      label: 'Toggle',
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
          <Button isIconOnly size="sm" variant="light" aria-label={t('super.label_tenant_actions')}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label={t('super.label_tenant_actions')} onAction={handleMenuAction}>
          <DropdownItem key="view" startContent={<Eye size={14} />}>
            {t('tenant_list.action_view')}
          </DropdownItem>
          <DropdownItem key="edit" startContent={<Edit size={14} />}>
            {t('tenant_list.action_edit')}
          </DropdownItem>
          <DropdownItem key="toggle-hub" startContent={tenant.allows_subtenants ? <ToggleLeft size={14} /> : <ToggleRight size={14} />}>
            {tenant.allows_subtenants ? t('tenant_list.disable_hub') : t('tenant_list.enable_hub')}
          </DropdownItem>
          {tenant.is_active ? (
            <DropdownItem key="deactivate" startContent={<Shield size={14} />} className="text-warning" color="warning">
              {t('tenant_list.deactivate')}
            </DropdownItem>
          ) : (
            <DropdownItem key="reactivate" startContent={<Shield size={14} />} className="text-success" color="success">
              {t('tenant_list.reactivate')}
            </DropdownItem>
          )}
          <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
            {t('tenant_list.action_delete')}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  const columns: Column<SuperAdminTenant>[] = [
    {
      key: 'name',
      label: t('tenant_list.col_tenant'),
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
      label: t('tenant_list.col_domain'),
      render: (tenant) => (
        <span className="text-sm text-default-500">{tenant.domain || '---'}</span>
      ),
    },
    {
      key: 'is_active',
      label: t('tenant_list.col_status'),
      sortable: true,
      render: (tenant) => (
        <Chip size="sm" variant="flat" color={tenant.is_active ? 'success' : 'default'}>
          {tenant.is_active ? 'Active' : 'Inactive'}
        </Chip>
      ),
    },
    {
      key: 'user_count',
      label: t('tenant_list.col_users'),
      sortable: true,
      render: (tenant) => <span>{tenant.user_count ?? 0}</span>,
    },
    {
      key: 'allows_subtenants',
      label: t('tenant_list.col_hub'),
      render: (tenant) =>
        tenant.allows_subtenants ? (
          <Chip size="sm" variant="flat" color="secondary">Hub</Chip>
        ) : (
          <span className="text-default-400">---</span>
        ),
    },
    {
      key: 'parent_name',
      label: t('tenant_list.col_parent'),
      render: (tenant) => (
        <span className="text-sm text-default-500">
          {tenant.parent_name || '---'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: t('tenant_list.col_created'),
      sortable: true,
      render: (tenant) => (
        <span className="text-sm text-default-500">
          {new Date(tenant.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('tenant_list.col_actions'),
      render: (tenant) => <TenantActionsMenu tenant={tenant} />,
    },
  ];

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <span className="text-foreground">Tenants</span>
      </nav>
      <PageHeader
        title={t('super.tenant_list_title')}
        description={t('super.tenant_list_desc')}
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
              {t('tenant_list.view_hierarchy')}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/super/tenants/create'))}
            >
              {t('tenant_list.create_tenant')}
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
          <Tab key="all" title={t('tenant_list.tab_all')} />
          <Tab key="active" title={t('tenant_list.tab_active')} />
          <Tab key="inactive" title={t('tenant_list.tab_inactive')} />
          <Tab key="hub" title={t('tenant_list.tab_hub')} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={tenants}
        isLoading={loading}
        searchPlaceholder="Search tenants..."
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
