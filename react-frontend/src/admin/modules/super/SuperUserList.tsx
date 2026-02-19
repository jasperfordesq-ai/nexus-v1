import { useState, useCallback, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Button, Avatar, Chip, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
  Select, SelectItem, Switch,
} from '@heroui/react';
import { Plus, MoreVertical, Shield, ArrowRight, Eye, UserCheck, UserX } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { DataTable, PageHeader, StatusBadge, ConfirmModal, type Column } from '../../components';
import type { SuperAdminUser } from '../../api/types';

export function SuperUserList() {
  usePageTitle('Super Admin - Users');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [users, setUsers] = useState<SuperAdminUser[]>([]);
  const [tenants, setTenants] = useState<Array<{id: number; name: string}>>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [tenantFilter, setTenantFilter] = useState<number | undefined>();
  const [roleFilter, setRoleFilter] = useState<string | undefined>();
  const [superAdminsOnly, setSuperAdminsOnly] = useState(false);
  const [page, setPage] = useState(1);

  const [confirmAction, setConfirmAction] = useState<{
    type: 'grant-sa' | 'revoke-sa' | 'grant-global' | 'revoke-global' | 'move';
    user: SuperAdminUser;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadUsers = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.listUsers({
      page,
      search: search || undefined,
      tenant_id: tenantFilter,
      role: roleFilter,
      super_admins: superAdminsOnly || undefined,
      limit: 100,
    });
    if (res.success && res.data) {
      setUsers(Array.isArray(res.data) ? res.data : []);
    }
    setLoading(false);
  }, [page, search, tenantFilter, roleFilter, superAdminsOnly]);

  const loadTenants = useCallback(async () => {
    const res = await adminSuper.listTenants();
    if (res.success && res.data) {
      setTenants(Array.isArray(res.data) ? res.data.map((t) => ({ id: t.id, name: t.name })) : []);
    }
  }, []);

  useEffect(() => { loadUsers(); }, [loadUsers]);
  useEffect(() => { loadTenants(); }, [loadTenants]);

  const handleAction = async () => {
    if (!confirmAction) return;
    setActionLoading(true);
    const { type, user } = confirmAction;
    let res;
    switch (type) {
      case 'grant-sa': res = await adminSuper.grantSuperAdmin(user.id); break;
      case 'revoke-sa': res = await adminSuper.revokeSuperAdmin(user.id); break;
      case 'grant-global': res = await adminSuper.grantGlobalSuperAdmin(user.id); break;
      case 'revoke-global': res = await adminSuper.revokeGlobalSuperAdmin(user.id); break;
    }
    if (res?.success) {
      toast.success('User updated successfully');
      loadUsers();
    } else {
      toast.error(res?.error || 'Action failed');
    }
    setActionLoading(false);
    setConfirmAction(null);
  };

  const columns: Column<SuperAdminUser>[] = [
    {
      key: 'name', label: 'User', sortable: true,
      render: (user) => (
        <div className="flex items-center gap-3">
          <Avatar name={user.name} size="sm" />
          <div>
            <Link to={tenantPath(`/admin/super/users/${user.id}`)} className="font-medium text-foreground hover:text-primary">
              {user.name}
            </Link>
            <p className="text-xs text-default-400">{user.email}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'tenant', label: 'Tenant', sortable: true,
      render: (user) => (
        <Link to={tenantPath(`/admin/super/tenants/${user.tenant_id}`)} className="hover:text-primary">
          <Chip size="sm" variant="flat">{user.tenant_name || `Tenant ${user.tenant_id}`}</Chip>
        </Link>
      ),
    },
    {
      key: 'role', label: 'Role', sortable: true,
      render: (user) => (
        <Chip size="sm" variant="flat" color={user.role === 'admin' || user.role === 'tenant_admin' ? 'primary' : 'default'}>
          {user.role}
        </Chip>
      ),
    },
    {
      key: 'status', label: 'Status', sortable: true,
      render: (user) => <StatusBadge status={user.status} />,
    },
    {
      key: 'super_admin', label: 'Super Admin', sortable: true,
      render: (user) => (
        <div className="flex items-center gap-1">
          {user.is_super_admin ? (
            <Chip size="sm" variant="flat" color="danger" startContent={<Shield size={10} />}>
              Global SA
            </Chip>
          ) : user.is_tenant_super_admin ? (
            <Chip size="sm" variant="flat" color="secondary" startContent={<Shield size={10} />}>
              Tenant SA
            </Chip>
          ) : (
            <span className="text-default-400">—</span>
          )}
        </div>
      ),
    },
    {
      key: 'last_login_at', label: 'Last Login', sortable: true,
      render: (user) => (
        <span className="text-sm text-default-500">
          {user.last_login_at
            ? new Date(user.last_login_at).toLocaleDateString()
            : 'Never'}
        </span>
      ),
    },
    {
      key: 'actions', label: 'Actions',
      render: (user) => (
        <Dropdown>
          <DropdownTrigger><Button isIconOnly size="sm" variant="light"><MoreVertical size={16} /></Button></DropdownTrigger>
          <DropdownMenu aria-label="User actions" onAction={(key) => {
            if (key === 'view') navigate(tenantPath(`/admin/super/users/${user.id}`));
            else if (key === 'edit') navigate(tenantPath(`/admin/super/users/${user.id}/edit`));
            else if (key === 'grant-sa') setConfirmAction({ type: 'grant-sa', user });
            else if (key === 'revoke-sa') setConfirmAction({ type: 'revoke-sa', user });
            else if (key === 'grant-global') setConfirmAction({ type: 'grant-global', user });
            else if (key === 'revoke-global') setConfirmAction({ type: 'revoke-global', user });
          }}>
            <DropdownItem key="view" startContent={<Eye size={14} />}>View</DropdownItem>
            <DropdownItem key="edit" startContent={<ArrowRight size={14} />}>Edit</DropdownItem>
            {!user.is_tenant_super_admin
              ? <DropdownItem key="grant-sa" startContent={<UserCheck size={14} />} className="text-success">Grant Tenant SA</DropdownItem>
              : <DropdownItem key="revoke-sa" startContent={<UserX size={14} />} className="text-warning">Revoke Tenant SA</DropdownItem>
            }
            {!user.is_super_admin
              ? <DropdownItem key="grant-global" startContent={<Shield size={14} />} className="text-secondary">Grant Global SA</DropdownItem>
              : <DropdownItem key="revoke-global" startContent={<Shield size={14} />} className="text-danger">Revoke Global SA</DropdownItem>
            }
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  const confirmMessages: Record<string, { title: string; message: string; label: string }> = {
    'grant-sa': { title: 'Grant Tenant Super Admin', message: 'This user will become a tenant super admin.', label: 'Grant' },
    'revoke-sa': { title: 'Revoke Tenant Super Admin', message: 'This user will lose tenant super admin privileges.', label: 'Revoke' },
    'grant-global': { title: 'Grant Global Super Admin', message: 'This user will gain access to ALL tenants. GOD-level action.', label: 'Grant Global SA' },
    'revoke-global': { title: 'Revoke Global Super Admin', message: 'This user will lose global super admin access.', label: 'Revoke Global SA' },
  };

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <span className="text-foreground">Users</span>
      </nav>
      <PageHeader
        title="Cross-Tenant Users"
        description="Manage users across all tenants"
        actions={
          <Button color="primary" startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/super/users/create'))}>
            Create User
          </Button>
        }
      />
      <div className="mb-4 flex flex-wrap items-end gap-4">
        <Select
          label="Filter by Tenant"
          size="sm"
          className="max-w-xs"
          selectedKeys={tenantFilter ? [String(tenantFilter)] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0];
            setTenantFilter(val ? Number(val) : undefined);
            setPage(1);
          }}
        >
          {tenants.map((t) => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
        </Select>
        <Select
          label="Filter by Role"
          size="sm"
          className="max-w-xs"
          selectedKeys={roleFilter ? [roleFilter] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0];
            setRoleFilter(val ? String(val) : undefined);
            setPage(1);
          }}
        >
          <SelectItem key="member">Member</SelectItem>
          <SelectItem key="admin">Admin</SelectItem>
          <SelectItem key="tenant_admin">Tenant Admin</SelectItem>
        </Select>
        <Switch
          size="sm"
          isSelected={superAdminsOnly}
          onValueChange={(val) => {
            setSuperAdminsOnly(val);
            setPage(1);
          }}
        >
          Super Admins Only
        </Switch>
      </div>
      {users.length >= 100 && (
        <div className="mb-4 p-3 bg-warning-50 dark:bg-warning-50/10 border border-warning-200 dark:border-warning-200/20 rounded-lg">
          <p className="text-sm text-warning-700 dark:text-warning-400">
            Showing first 100 results. Use filters to narrow down your search.
          </p>
        </div>
      )}
      <DataTable
        columns={columns}
        data={users}
        isLoading={loading}
        searchPlaceholder="Search users across tenants..."
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadUsers}
        totalItems={users.length}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />
      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleAction}
          title={confirmMessages[confirmAction.type].title}
          message={`${confirmMessages[confirmAction.type].message}\n\nUser: ${confirmAction.user.name} (${confirmAction.user.email})`}
          confirmLabel={confirmMessages[confirmAction.type].label}
          confirmColor={confirmAction.type.includes('revoke') ? 'danger' : 'primary'}
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}
export default SuperUserList;
