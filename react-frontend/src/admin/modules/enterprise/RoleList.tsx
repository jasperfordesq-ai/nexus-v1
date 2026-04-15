// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Role List
 * DataTable of roles with permissions count, user count. CRUD actions.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Chip } from '@heroui/react';
import { Plus, Pencil, Trash2, Shield } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable, ConfirmModal } from '../../components';
import type { Column } from '../../components';
import type { Role } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function RoleList() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteTarget, setDeleteTarget] = useState<Role | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadRoles = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getRoles();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setRoles(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_roles'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadRoles();
  }, [loadRoles]);

  const handleDelete = async () => {
    if (!deleteTarget || !deleteTarget.id) return;
    setDeleting(true);
    try {
      const res = await adminEnterprise.deleteRole(deleteTarget.id);

      if (res.success) {
        toast.success(t('enterprise.role_deleted'));
        setDeleteTarget(null);
        loadRoles();
      } else {
        const error = (res as { error?: string }).error || t('enterprise.failed_to_delete_role');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('enterprise.failed_to_delete_role'));
      console.error('Role delete error:', err);
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Role>[] = [
    {
      key: 'name',
      label: t('enterprise.col_name'),
      sortable: true,
      render: (role) => (
        <div className="flex items-center gap-2">
          <Shield size={16} className="text-primary" />
          <span className="font-medium">{role.name}</span>
        </div>
      ),
    },
    { key: 'slug', label: t('enterprise.col_slug'), sortable: true },
    { key: 'description', label: t('enterprise.col_description') },
    {
      key: 'permissions',
      label: t('enterprise.col_permissions'),
      render: (role) => (
        <Chip size="sm" variant="flat" color="primary">
          {role.permissions?.length ?? 0} {t('enterprise.permissions_count_label')}
        </Chip>
      ),
    },
    {
      key: 'users_count',
      label: t('enterprise.col_users'),
      sortable: true,
      render: (role) => (
        <span className="text-sm">{role.users_count ?? 0}</span>
      ),
    },
    {
      key: 'actions',
      label: t('enterprise.col_actions'),
      render: (role) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/enterprise/roles/${role.id}/edit`))}
            aria-label={t('enterprise.label_edit_role')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            color="danger"
            onPress={() => setDeleteTarget(role)}
            isDisabled={role.slug === 'admin' || role.slug === 'super_admin'}
            aria-label={t('enterprise.label_delete_role')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.role_list_title')}
        description={t('enterprise.role_list_desc')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/enterprise/roles/create')}
            color="primary"
            startContent={<Plus size={16} />}
            size="sm"
          >
            {t('enterprise.create_role')}
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={roles}
        isLoading={loading}
        onRefresh={loadRoles}
        searchable={false}
        emptyContent={t('enterprise.no_roles_found')}
      />

      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={t('enterprise.delete_role_title')}
        message={t('enterprise.delete_role_confirm', { name: deleteTarget?.name })}
        confirmLabel={t('common.delete')}
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default RoleList;
