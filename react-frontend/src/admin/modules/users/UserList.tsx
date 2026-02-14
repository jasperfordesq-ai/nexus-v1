/**
 * Admin User List
 * Full user management with filtering, search, and bulk actions.
 * Parity: PHP Admin\UserController::index()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import {
  Button,
  Avatar,
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
  UserCheck,
  UserX,
  Ban,
  RotateCcw,
  Edit,
  Shield,
  KeyRound,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminUsers } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminUser, UserListParams } from '../../api/types';

export function UserList() {
  usePageTitle('Admin - Users');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const [users, setUsers] = useState<AdminUser[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState(searchParams.get('filter') || 'all');
  const [search, setSearch] = useState('');

  // Confirm modal state
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'suspend' | 'ban' | 'reactivate' | 'delete' | 'reset2fa';
    user: AdminUser;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadUsers = useCallback(async () => {
    setLoading(true);
    const params: UserListParams = {
      page,
      limit: 20,
      search: search || undefined,
      status: filter === 'all' ? undefined : filter as UserListParams['status'],
    };

    const res = await adminUsers.list(params);
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (Array.isArray(data)) {
        setUsers(data);
        setTotal(data.length);
      } else if (data && typeof data === 'object') {
        const paginatedData = data as { data: AdminUser[]; meta?: { total: number } };
        setUsers(paginatedData.data || []);
        setTotal(paginatedData.meta?.total || 0);
      }
    }
    setLoading(false);
  }, [page, filter, search]);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const handleFilterChange = (key: string) => {
    setFilter(key);
    setPage(1);
    if (key === 'all') {
      searchParams.delete('filter');
    } else {
      searchParams.set('filter', key);
    }
    setSearchParams(searchParams);
  };

  const handleAction = async () => {
    if (!confirmAction) return;
    setActionLoading(true);

    const { type, user } = confirmAction;
    let res;

    switch (type) {
      case 'approve':
        res = await adminUsers.approve(user.id);
        break;
      case 'suspend':
        res = await adminUsers.suspend(user.id);
        break;
      case 'ban':
        res = await adminUsers.ban(user.id);
        break;
      case 'reactivate':
        res = await adminUsers.reactivate(user.id);
        break;
      case 'delete':
        res = await adminUsers.delete(user.id);
        break;
      case 'reset2fa':
        res = await adminUsers.reset2fa(user.id, 'Admin reset');
        break;
    }

    if (res?.success) {
      toast.success(`User ${type}d successfully`);
      loadUsers();
    } else {
      toast.error(res?.error || `Failed to ${type} user`);
    }

    setActionLoading(false);
    setConfirmAction(null);
  };

  const confirmMessages: Record<string, { title: string; message: string; label: string }> = {
    approve: { title: 'Approve User', message: 'This user will gain access to the platform.', label: 'Approve' },
    suspend: { title: 'Suspend User', message: 'This user will be temporarily locked out.', label: 'Suspend' },
    ban: { title: 'Ban User', message: 'This user will be permanently banned. This action is difficult to reverse.', label: 'Ban' },
    reactivate: { title: 'Reactivate User', message: 'This user will regain access to the platform.', label: 'Reactivate' },
    delete: { title: 'Delete User', message: 'This user and all their data will be permanently deleted. This cannot be undone.', label: 'Delete' },
    reset2fa: { title: 'Reset 2FA', message: 'This will remove the user\'s two-factor authentication. They will need to set it up again.', label: 'Reset 2FA' },
  };

  function UserActionsMenu({ user }: { user: AdminUser }) {
    type ActionKey = 'edit' | 'approve' | 'suspend' | 'ban' | 'reactivate' | 'reset2fa' | 'permissions';

    const items: { key: ActionKey; label: string; icon: React.ReactNode; color?: 'success' | 'warning' | 'danger'; className?: string }[] = [
      { key: 'edit', label: 'Edit', icon: <Edit size={14} /> },
    ];

    if (user.status === 'pending') {
      items.push({ key: 'approve', label: 'Approve', icon: <UserCheck size={14} />, color: 'success', className: 'text-success' });
    }
    if (user.status === 'active') {
      items.push({ key: 'suspend', label: 'Suspend', icon: <UserX size={14} />, color: 'warning', className: 'text-warning' });
    }
    if (user.status !== 'banned') {
      items.push({ key: 'ban', label: 'Ban', icon: <Ban size={14} />, color: 'danger', className: 'text-danger' });
    }
    if (user.status === 'suspended' || user.status === 'banned') {
      items.push({ key: 'reactivate', label: 'Reactivate', icon: <RotateCcw size={14} />, color: 'success', className: 'text-success' });
    }
    if (user.has_2fa_enabled) {
      items.push({ key: 'reset2fa', label: 'Reset 2FA', icon: <KeyRound size={14} /> });
    }
    items.push({ key: 'permissions', label: 'Permissions', icon: <Shield size={14} /> });

    const handleMenuAction = (key: React.Key) => {
      const action = key as ActionKey;
      if (action === 'edit') {
        navigate(tenantPath(`/admin/users/${user.id}/edit`));
      } else if (action === 'permissions') {
        navigate(tenantPath(`/admin/users/${user.id}/permissions`));
      } else {
        setConfirmAction({ type: action, user });
      }
    };

    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light">
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label="User actions" onAction={handleMenuAction}>
          {items.map((item) => (
            <DropdownItem
              key={item.key}
              startContent={item.icon}
              className={item.className}
              color={item.color}
            >
              {item.label}
            </DropdownItem>
          ))}
        </DropdownMenu>
      </Dropdown>
    );
  }

  const columns: Column<AdminUser>[] = [
    {
      key: 'name',
      label: 'User',
      sortable: true,
      render: (user) => (
        <div className="flex items-center gap-3">
          <Avatar
            src={user.avatar_url || user.avatar || undefined}
            name={user.name}
            size="sm"
          />
          <div>
            <Link
              to={tenantPath(`/admin/users/${user.id}/edit`)}
              className="font-medium text-foreground hover:text-primary"
            >
              {user.name}
            </Link>
            <p className="text-xs text-default-400">{user.email}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'role',
      label: 'Role',
      sortable: true,
      render: (user) => (
        <Chip size="sm" variant="flat" color={user.role === 'admin' ? 'primary' : 'default'}>
          {user.role}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (user) => <StatusBadge status={user.status} />,
    },
    {
      key: 'balance',
      label: 'Balance',
      sortable: true,
      render: (user) => <span>{user.balance ?? 0}h</span>,
    },
    {
      key: 'created_at',
      label: 'Joined',
      sortable: true,
      render: (user) => (
        <span className="text-sm text-default-500">
          {new Date(user.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (user) => <UserActionsMenu user={user} />,
    },
  ];

  return (
    <div>
      <PageHeader
        title="Users"
        description="Manage platform users, roles, and permissions"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/users/create'))}
          >
            Add User
          </Button>
        }
      />

      {/* Status Filter Tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={filter}
          onSelectionChange={(key) => handleFilterChange(key as string)}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All Users" />
          <Tab key="pending" title="Pending" />
          <Tab key="active" title="Active" />
          <Tab key="suspended" title="Suspended" />
          <Tab key="banned" title="Banned" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={users}
        isLoading={loading}
        searchPlaceholder="Search by name or email..."
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadUsers}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />

      {/* Confirm Action Modal */}
      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleAction}
          title={confirmMessages[confirmAction.type].title}
          message={`${confirmMessages[confirmAction.type].message}\n\nUser: ${confirmAction.user.name} (${confirmAction.user.email})`}
          confirmLabel={confirmMessages[confirmAction.type].label}
          confirmColor={confirmAction.type === 'approve' || confirmAction.type === 'reactivate' ? 'primary' : 'danger'}
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default UserList;
