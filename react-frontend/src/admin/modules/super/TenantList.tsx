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
import { useTranslation } from 'react-i18next';
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
      toast.error(`${t('super.tenants_error')}: ${err instanceof Error ? err.message : t('common.unknown')}`);
    }
    setLoading(false);
  }, [filter, search, t, toast])


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
      toast.success(t('super.tenant_action_succeeded'));
      loadTenants();
    } else {
      toast.error(res?.error || t('super.failed_to_action_tenant'));
    }

    setActionLoading(false);
    setConfirmAction(null);
  };

  const confirmMessages: Record<string, { title: string; message: string; label: string }> = {
    delete: {
      title: t('super.confirm_delete_tenant_title'),
      message: t('super.confirm_delete_tenant_message'),
      label: t('super.confirm_delete'),
    },
    deactivate: {
      title: t('super.confirm_deactivate_tenant_title'),
      message: t('super.confirm_deactivate_tenant_message'),
      label: t('super.confirm_deactivate'),
    },
    reactivate: {
      title: t('super.confirm_reactivate_tenant_title'),
      message: t('super.confirm_reactivate_tenant_message'),
      label: t('super.confirm_reactivate'),
    },
    'toggle-hub': {
      title: t('super.confirm_toggle_hub_title'),
      message: t('super.confirm_toggle_hub_message'),
      label: t('super.confirm_toggle_hub'),
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
          <Button isIconOnly size="sm" variant="light" aria-label={t('super.tenant_actions')}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label={t('super.tenant_actions')} onAction={handleMenuAction}>
          <DropdownItem key="view" startContent={<Eye size={14} />}>
            {t('super.action_view')}
          </DropdownItem>
          <DropdownItem key="edit" startContent={<Edit size={14} />}>
            {t('common.edit')}
          </DropdownItem>
          <DropdownItem key="toggle-hub" startContent={tenant.allows_subtenants ? <ToggleLeft size={14} /> : <ToggleRight size={14} />}>
            {tenant.allows_subtenants ? t('super.disable_hub') : t('super.enable_hub')}
          </DropdownItem>
          {tenant.is_active ? (
            <DropdownItem key="deactivate" startContent={<Shield size={14} />} className="text-warning" color="warning">
              {t('super.deactivate')}
            </DropdownItem>
          ) : (
            <DropdownItem key="reactivate" startContent={<Shield size={14} />} className="text-success" color="success">
              {t('super.reactivate')}
            </DropdownItem>
          )}
          <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
            {t('common.delete')}
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  const columns: Column<SuperAdminTenant>[] = [
    {
      key: 'name',
      label: t('super.label_tenant'),
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
      label: t('super.label_domain'),
      render: (tenant) => (
        <span className="text-sm text-default-500">{tenant.domain || '---'}</span>
      ),
    },
    {
      key: 'is_active',
      label: t('super.label_status'),
      sortable: true,
      render: (tenant) => (
        <Chip size="sm" variant="flat" color={tenant.is_active ? 'success' : 'default'}>
          {tenant.is_active ? t('common.active') : t('super.inactive_label')}
        </Chip>
      ),
    },
    {
      key: 'user_count',
      label: t('super.label_users'),
      sortable: true,
      render: (tenant) => <span>{tenant.user_count ?? 0}</span>,
    },
    {
      key: 'allows_subtenants',
      label: t('super.hub'),
      render: (tenant) =>
        tenant.allows_subtenants ? (
          <Chip size="sm" variant="flat" color="secondary">{t('super.hub')}</Chip>
        ) : (
          <span className="text-default-400">---</span>
        ),
    },
    {
      key: 'parent_name',
      label: t('super.parent'),
      render: (tenant) => (
        <span className="text-sm text-default-500">
          {tenant.parent_name || '---'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: t('super.created'),
      sortable: true,
      render: (tenant) => (
        <span className="text-sm text-default-500">
          {new Date(tenant.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('common.actions'),
      render: (tenant) => <TenantActionsMenu tenant={tenant} />,
    },
  ];

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">{t('super.breadcrumb_super_admin')}</Link>
        <span>/</span>
        <span className="text-foreground">{t('super.breadcrumb_tenants')}</span>
      </nav>
      <PageHeader
        title={t('super.tenant_list_title')}
        description={t('super.tenant_list_desc')}
        actions={
          <div className="flex items-center gap-2">
            {lastRefreshed && (
              <span className="text-xs text-default-400">
                {t('super.updated_at', { time: lastRefreshed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) })}
              </span>
            )}
            <Button
              as={Link}
              to={tenantPath('/admin/super/tenants/hierarchy')}
              variant="flat"
              startContent={<Network size={16} />}
              size="sm"
            >
              {t('super.view_hierarchy')}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/super/tenants/create'))}
            >
              {t('super.create_tenant')}
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
          <Tab key="all" title={t('common.all')} />
          <Tab key="active" title={t('common.active')} />
          <Tab key="inactive" title={t('super.inactive_label')} />
          <Tab key="hub" title={t('super.hub')} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={tenants}
        isLoading={loading}
        searchPlaceholder={t('super.search_tenants_placeholder')}
        onSearch={(q) => setSearch(q)}
        onRefresh={loadTenants}
      />

      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleAction}
          title={confirmMessages[confirmAction.type]?.title ?? ''}
          message={t('super.confirm_tenant_message', {
            message: confirmMessages[confirmAction.type]?.message ?? '',
            tenant: confirmAction.tenant.name,
          })}
          confirmLabel={confirmMessages[confirmAction.type]?.label ?? ''}
          confirmColor={confirmAction.type === 'reactivate' ? 'primary' : 'danger'}
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default TenantList;
