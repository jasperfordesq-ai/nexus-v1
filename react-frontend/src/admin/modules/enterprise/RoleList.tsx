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

export function RoleList() {
  usePageTitle('Admin - Roles & Permissions');
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
      toast.error('Failed to load roles');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadRoles();
  }, [loadRoles]);

  const handleDelete = async () => {
    if (!deleteTarget || !deleteTarget.id) return;
    setDeleting(true);
    try {
      const res = await adminEnterprise.deleteRole(deleteTarget.id);

      if (res.success) {
        toast.success('Role deleted');
        setDeleteTarget(null);
        loadRoles();
      } else {
        const error = (res as { error?: string }).error || 'Failed to delete role';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to delete role');
      console.error('Role delete error:', err);
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Role>[] = [
    {
      key: 'name',
      label: 'Name',
      sortable: true,
      render: (role) => (
        <div className="flex items-center gap-2">
          <Shield size={16} className="text-primary" />
          <span className="font-medium">{role.name}</span>
        </div>
      ),
    },
    { key: 'slug', label: 'Slug', sortable: true },
    { key: 'description', label: 'Description' },
    {
      key: 'permissions',
      label: 'Permissions',
      render: (role) => (
        <Chip size="sm" variant="flat" color="primary">
          {role.permissions?.length ?? 0} permissions
        </Chip>
      ),
    },
    {
      key: 'users_count',
      label: 'Users',
      sortable: true,
      render: (role) => (
        <span className="text-sm">{role.users_count ?? 0}</span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (role) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/enterprise/roles/${role.id}/edit`))}
            aria-label="Edit role"
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
            aria-label="Delete role"
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
        title="Roles & Permissions"
        description="Manage user roles and their permissions"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/enterprise/roles/create')}
            color="primary"
            startContent={<Plus size={16} />}
            size="sm"
          >
            Create Role
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={roles}
        isLoading={loading}
        onRefresh={loadRoles}
        searchable={false}
        emptyContent="No roles found. Create your first role to get started."
      />

      <ConfirmModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Delete Role"
        message={`Are you sure you want to delete the "${deleteTarget?.name}" role? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleting}
      />
    </div>
  );
}

export default RoleList;
