// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  Plus,
  Upload,
  Download,
  MoreVertical,
  UserCheck,
  UserX,
  Ban,
  RotateCcw,
  Edit,
  Shield,
  KeyRound,
  LogIn,
  FileUp,
  CheckCircle2,
  AlertCircle,
  Trash2,
} from 'lucide-react';
import { useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminUsers } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminUser, UserListParams } from '../../api/types';

export function UserList() {
  usePageTitle('Admin - Users');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = (currentUser as Record<string, unknown> | null)?.is_super_admin === true || (currentUser?.role as string) === 'super_admin';
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const [users, setUsers] = useState<AdminUser[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState(searchParams.get('filter') || 'all');
  const [search, setSearch] = useState('');

  // Import modal state
  const [importOpen, setImportOpen] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [importRole, setImportRole] = useState('member');
  const [importLoading, setImportLoading] = useState(false);
  const [importResults, setImportResults] = useState<{
    imported: number;
    skipped: number;
    errors: string[];
    total_rows: number;
  } | null>(null);

  // Confirm modal state
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'suspend' | 'ban' | 'reactivate' | 'delete' | 'reset2fa' | 'impersonate';
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

  const handleImport = async () => {
    if (!importFile) return;
    setImportLoading(true);
    setImportResults(null);

    const res = await adminUsers.importUsers(importFile, { default_role: importRole });
    if (res.success && res.data) {
      const data = res.data as { imported: number; skipped: number; errors: string[]; total_rows: number };
      setImportResults(data);
      if (data.imported > 0) {
        toast.success(`Successfully imported ${data.imported} users`);
        loadUsers();
      }
    } else {
      toast.error(res.error || 'Import failed');
    }
    setImportLoading(false);
  };

  const resetImportModal = () => {
    setImportOpen(false);
    setImportFile(null);
    setImportRole('member');
    setImportResults(null);
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
      case 'impersonate': {
        res = await adminUsers.impersonate(user.id);
        if (res?.success && res.data) {
          const tokenData = res.data as { token?: string; impersonation_token?: string };
          const token = tokenData.token || tokenData.impersonation_token;
          if (token) {
            toast.success(`Impersonating ${user.name}. Opening in new tab...`);
            window.open(`${window.location.origin}${tenantPath('/dashboard')}?impersonate_token=${token}`, '_blank');
          } else {
            toast.success('Impersonation started');
          }
        } else {
          toast.error(res?.error || 'Failed to impersonate user');
        }
        setActionLoading(false);
        setConfirmAction(null);
        return;
      }
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
    impersonate: { title: 'Impersonate User', message: 'You will be logged in as this user. Your admin session will be preserved so you can return.', label: 'Impersonate' },
  };

  function UserActionsMenu({ user }: { user: AdminUser }) {
    type ActionKey = 'edit' | 'approve' | 'suspend' | 'ban' | 'reactivate' | 'reset2fa' | 'permissions' | 'impersonate' | 'delete';

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
    // Super admins can impersonate other users (but not other super admins)
    if (isSuperAdmin && !user.is_super_admin && user.id !== currentUser?.id) {
      items.push({ key: 'impersonate', label: 'Impersonate', icon: <LogIn size={14} /> });
    }
    // Delete (only if not current user)
    if (user.id !== currentUser?.id) {
      items.push({ key: 'delete', label: 'Delete', icon: <Trash2 size={14} />, color: 'danger', className: 'text-danger' });
    }

    const handleMenuAction = (key: React.Key) => {
      const action = key as ActionKey;
      if (action === 'edit') {
        navigate(tenantPath(`/admin/users/${user.id}/edit`));
      } else if (action === 'permissions') {
        navigate(tenantPath(`/admin/users/${user.id}/permissions`));
      } else if (action === 'impersonate') {
        setConfirmAction({ type: 'impersonate', user });
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
        <div className="flex items-center gap-1">
          <Chip
            size="sm"
            variant="flat"
            color={user.is_super_admin || user.role === 'super_admin' ? 'secondary' : user.role === 'admin' || user.role === 'tenant_admin' ? 'primary' : 'default'}
          >
            {user.role}
          </Chip>
          {user.is_super_admin && (
            <Chip size="sm" variant="flat" color="warning" startContent={<Shield size={10} />}>
              SA
            </Chip>
          )}
        </div>
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
          <div className="flex items-center gap-2">
            <Button
              variant="bordered"
              startContent={<Upload size={16} />}
              onPress={() => setImportOpen(true)}
            >
              Import CSV
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/users/create'))}
            >
              Add User
            </Button>
          </div>
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

      {/* Import Users Modal */}
      <Modal isOpen={importOpen} onClose={resetImportModal} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <FileUp size={20} />
            Import Users from CSV
          </ModalHeader>
          <ModalBody>
            {!importResults ? (
              <div className="flex flex-col gap-4">
                <p className="text-sm text-default-500">
                  Upload a CSV file with columns: <strong>first_name</strong>, <strong>last_name</strong>, <strong>email</strong>, phone (optional), role (optional).
                </p>

                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Download size={14} />}
                    onPress={() => adminUsers.downloadImportTemplate()}
                  >
                    Download Template
                  </Button>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">CSV File</label>
                  <input
                    type="file"
                    accept=".csv"
                    onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                    className="block w-full text-sm text-default-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                  />
                </div>

                <Select
                  label="Default Role"
                  selectedKeys={[importRole]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0] as string;
                    if (selected) setImportRole(selected);
                  }}
                  size="sm"
                  variant="bordered"
                >
                  <SelectItem key="member">Member</SelectItem>
                  <SelectItem key="broker">Broker</SelectItem>
                  <SelectItem key="coordinator">Coordinator</SelectItem>
                </Select>
              </div>
            ) : (
              <div className="flex flex-col gap-3">
                <div className="flex items-center gap-4">
                  <div className="flex items-center gap-2 text-success">
                    <CheckCircle2 size={18} />
                    <span className="font-medium">{importResults.imported} imported</span>
                  </div>
                  {importResults.skipped > 0 && (
                    <div className="flex items-center gap-2 text-warning">
                      <AlertCircle size={18} />
                      <span className="font-medium">{importResults.skipped} skipped</span>
                    </div>
                  )}
                  <span className="text-sm text-default-400">
                    of {importResults.total_rows} rows
                  </span>
                </div>

                {importResults.errors.length > 0 && (
                  <div className="max-h-48 overflow-y-auto rounded-lg bg-danger-50 p-3">
                    <p className="text-sm font-medium text-danger mb-1">Errors:</p>
                    <ul className="text-xs text-danger-600 space-y-1">
                      {importResults.errors.map((err, i) => (
                        <li key={i}>{err}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={resetImportModal} isDisabled={importLoading}>
              {importResults ? 'Close' : 'Cancel'}
            </Button>
            {!importResults && (
              <Button
                color="primary"
                onPress={handleImport}
                isLoading={importLoading}
                isDisabled={!importFile}
                startContent={!importLoading ? <Upload size={16} /> : undefined}
              >
                Import
              </Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserList;
