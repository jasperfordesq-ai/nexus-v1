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
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import DOMPurify from 'dompurify';
import { adminUsers, type BulkActionResult } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, ConfirmModal, BulkActionToolbar, type BulkAction, type Column } from '../../components';
import type { AdminUser, UserListParams } from '../../api/types';

export function UserList() {
  const { t } = useTranslation('admin');
  usePageTitle('Admin - Users');
  const { tenantPath, tenant } = useTenant();
  const toast = useToast();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = (currentUser as Record<string, unknown> | null)?.is_super_admin === true
    || (currentUser as Record<string, unknown> | null)?.is_tenant_super_admin === true
    || (currentUser?.role as string) === 'super_admin';
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

  // Bulk selection state
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);

  const handleBulkResult = (res: { success: boolean; error?: string; data?: BulkActionResult | unknown }, actionLabel: string) => {
    if (!res.success) {
      toast.error(res.error || t('bulk.result_failed'));
      return;
    }
    const data = (res.data as BulkActionResult) || { success: 0, failed: 0 };
    if (data.failed && data.failed > 0) {
      toast.error(t('bulk.result_partial', { success: data.success, failed: data.failed }));
    } else {
      toast.success(t('bulk.result_success', { count: data.success }));
    }
    setSelectedIds(new Set());
    loadUsers();
    void actionLabel;
  };

  const loadUsers = useCallback(async () => {
    setLoading(true);
    const params: UserListParams = {
      page,
      limit: 20,
      search: search || undefined,
      status: filter === 'all' ? undefined : filter as UserListParams['status'],
      tenant_id: tenant?.id,
    };

    const res = await adminUsers.list(params);
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (Array.isArray(data)) {
        setUsers(data);
        // Use meta.total from the paginated response envelope for correct pagination.
        // Falling back to data.length only if meta is unavailable (non-paginated response).
        const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
        setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
      } else if (data && typeof data === 'object') {
        const paginatedData = data as { data: AdminUser[]; meta?: { total: number } };
        setUsers(paginatedData.data || []);
        setTotal(paginatedData.meta?.total || 0);
      }
    }
    setLoading(false);
  }, [page, filter, search, tenant?.id]);

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
        toast.success(t('users.import_success', { count: data.imported }));
        loadUsers();
      }
    } else {
      toast.error(res.error || t('users.import_failed'));
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
            // Use BroadcastChannel for memory-only token handoff — never persisted
            const { sendImpersonationToken } = await import('@/lib/impersonate');
            sendImpersonationToken(token, `${window.location.origin}${tenantPath('/dashboard')}`);
            toast.success(t('users.impersonate_success', { name: user.name }));
          } else {
            toast.success(t('users.impersonate_started'));
          }
        } else {
          toast.error(res?.error || t('users.impersonate_failed'));
        }
        setActionLoading(false);
        setConfirmAction(null);
        return;
      }
    }

    if (res?.success) {
      const data = res.data as Record<string, unknown> | undefined;
      if ((type === 'approve' || type === 'reactivate') && data?.email_sent === false) {
        const action = type === 'approve' ? 'approved' : 'reactivated';
        toast.success(t('users.user_approved_email_warning', { action }));
      } else {
        toast.success(t('users.user_action_success', { action: type }));
      }
      loadUsers();
    } else {
      toast.error(res?.error || t('users.user_action_failed', { action: type }));
    }

    setActionLoading(false);
    setConfirmAction(null);
  };

  const confirmMessages: Record<string, { title: string; message: string; label: string }> = {
    approve: { title: t('users.confirm_approve_title'), message: t('users.confirm_approve_message'), label: t('users.action_approve') },
    suspend: { title: t('users.confirm_suspend_title'), message: t('users.confirm_suspend_message'), label: t('users.action_suspend') },
    ban: { title: t('users.confirm_ban_title'), message: t('users.confirm_ban_message'), label: t('users.action_ban') },
    reactivate: { title: t('users.confirm_reactivate_title'), message: t('users.confirm_reactivate_message'), label: t('users.action_reactivate') },
    delete: { title: t('users.confirm_delete_title'), message: t('users.confirm_delete_message'), label: t('users.action_delete') },
    reset2fa: { title: t('users.confirm_reset_2fa_title'), message: t('users.confirm_reset_2fa_message'), label: t('users.action_reset_2fa') },
    impersonate: { title: t('users.confirm_impersonate_title'), message: t('users.confirm_impersonate_message'), label: t('users.action_impersonate') },
  };

  function UserActionsMenu({ user }: { user: AdminUser }) {
    type ActionKey = 'edit' | 'approve' | 'suspend' | 'ban' | 'reactivate' | 'reset2fa' | 'permissions' | 'impersonate' | 'delete';

    const items: { key: ActionKey; label: string; icon: React.ReactNode; color?: 'success' | 'warning' | 'danger'; className?: string }[] = [
      { key: 'edit', label: t('users.action_edit'), icon: <Edit size={14} /> },
    ];

    if (user.status === 'pending') {
      items.push({ key: 'approve', label: t('users.action_approve'), icon: <UserCheck size={14} />, color: 'success', className: 'text-success' });
    }
    if (user.status === 'active') {
      items.push({ key: 'suspend', label: t('users.action_suspend'), icon: <UserX size={14} />, color: 'warning', className: 'text-warning' });
    }
    if (user.status !== 'banned') {
      items.push({ key: 'ban', label: t('users.action_ban'), icon: <Ban size={14} />, color: 'danger', className: 'text-danger' });
    }
    if (user.status === 'suspended' || user.status === 'banned') {
      items.push({ key: 'reactivate', label: t('users.action_reactivate'), icon: <RotateCcw size={14} />, color: 'success', className: 'text-success' });
    }
    if (user.has_2fa_enabled) {
      items.push({ key: 'reset2fa', label: t('users.action_reset_2fa'), icon: <KeyRound size={14} /> });
    }
    items.push({ key: 'permissions', label: t('users.action_permissions'), icon: <Shield size={14} /> });
    // Super admins can impersonate other users (but not other super admins)
    if (isSuperAdmin && !user.is_super_admin && user.id !== currentUser?.id) {
      items.push({ key: 'impersonate', label: t('users.action_impersonate'), icon: <LogIn size={14} /> });
    }
    // Delete (only if not current user)
    if (user.id !== currentUser?.id) {
      items.push({ key: 'delete', label: t('users.action_delete'), icon: <Trash2 size={14} />, color: 'danger', className: 'text-danger' });
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
          <Button isIconOnly size="sm" variant="light" aria-label={t('users.actions_menu')}>
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label={t('users.actions_menu')} onAction={handleMenuAction}>
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
      label: t('users.col_user'),
      sortable: true,
      render: (user) => (
        <div className="flex items-center gap-3">
          <Avatar
            src={resolveAvatarUrl(user.avatar_url || user.avatar) || undefined}
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
      label: t('users.col_role'),
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
      label: t('users.col_status'),
      sortable: true,
      render: (user) => <StatusBadge status={user.status} />,
    },
    {
      key: 'balance',
      label: t('users.col_balance'),
      sortable: true,
      render: (user) => <span>{user.balance ?? 0}h</span>,
    },
    {
      key: 'created_at',
      label: t('users.col_joined'),
      sortable: true,
      render: (user) => (
        <span className="text-sm text-default-500">
          {new Date(user.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('users.col_actions'),
      render: (user) => <UserActionsMenu user={user} />,
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('users.title')}
        description={t('users.description')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="bordered"
              startContent={<Upload size={16} />}
              onPress={() => setImportOpen(true)}
            >
              {t('users.import_csv')}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/users/create'))}
            >
              {t('users.add_user')}
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
          <Tab key="all" title={t('users.all_users')} />
          <Tab key="pending" title={t('users.pending')} />
          <Tab key="active" title={t('users.active')} />
          <Tab key="suspended" title={t('users.suspended')} />
          <Tab key="banned" title={t('users.banned')} />
          <Tab key="never_logged_in" title={t('users.never_logged_in')} />
          <Tab key="onboarding_incomplete" title={t('users.onboarding_incomplete')} />
        </Tabs>
      </div>

      {(() => {
        const selectedIdList = Array.from(selectedIds).map((id) => Number(id)).filter((n) => Number.isFinite(n));
        const bulkActions: BulkAction[] = [
          {
            key: 'approve',
            label: t('bulk.users.approve'),
            icon: <UserCheck size={14} />,
            color: 'success',
            confirmTitle: t('bulk.users.approve_confirm_title'),
            confirmMessage: t('bulk.users.approve_confirm_message', { count: selectedIdList.length }),
            onConfirm: async () => {
              setBulkLoading(true);
              try {
                const res = await adminUsers.bulkApprove(selectedIdList);
                handleBulkResult(res, 'approve');
              } finally {
                setBulkLoading(false);
              }
            },
          },
          {
            key: 'suspend',
            label: t('bulk.users.suspend'),
            icon: <UserX size={14} />,
            color: 'warning',
            destructive: true,
            needsReason: true,
            reasonLabel: t('bulk.users.reason_label'),
            reasonPlaceholder: t('bulk.users.reason_placeholder'),
            confirmTitle: t('bulk.users.suspend_confirm_title'),
            confirmMessage: t('bulk.users.suspend_confirm_message', { count: selectedIdList.length }),
            onConfirm: async (reason) => {
              setBulkLoading(true);
              try {
                const res = await adminUsers.bulkSuspend(selectedIdList, reason);
                handleBulkResult(res, 'suspend');
              } finally {
                setBulkLoading(false);
              }
            },
          },
        ];
        return (
          <BulkActionToolbar
            selectedCount={selectedIds.size}
            actions={bulkActions}
            onClearSelection={() => setSelectedIds(new Set())}
            isLoading={bulkLoading}
          />
        );
      })()}

      <DataTable
        columns={columns}
        data={users}
        isLoading={loading}
        searchPlaceholder={t('users.search_placeholder')}
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadUsers}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        selectable
        onSelectionChange={setSelectedIds}
      />

      {/* Confirm Action Modal */}
      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleAction}
          title={confirmMessages[confirmAction.type]?.title ?? ''}
          message={`${confirmMessages[confirmAction.type]?.message ?? ''}\n\nUser: ${confirmAction.user.name} (${confirmAction.user.email})`}
          confirmLabel={confirmMessages[confirmAction.type]?.label ?? ''}
          confirmColor={confirmAction.type === 'approve' || confirmAction.type === 'reactivate' ? 'primary' : 'danger'}
          isLoading={actionLoading}
        />
      )}

      {/* Import Users Modal */}
      <Modal isOpen={importOpen} onClose={resetImportModal} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <FileUp size={20} />
            {t('users.import_title')}
          </ModalHeader>
          <ModalBody>
            {!importResults ? (
              <div className="flex flex-col gap-4">
                <p className="text-sm text-default-500" dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(t('users.import_csv_description')) }} />

                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Download size={14} />}
                    onPress={() => adminUsers.downloadImportTemplate()}
                  >
                    {t('users.import_download_template')}
                  </Button>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">{t('users.import_csv_file')}</label>
                  <input
                    type="file"
                    accept=".csv"
                    onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                    className="block w-full text-sm text-default-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                  />
                </div>

                <Select
                  label={t('users.import_default_role')}
                  selectedKeys={[importRole]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0] as string;
                    if (selected) setImportRole(selected);
                  }}
                  size="sm"
                  variant="bordered"
                >
                  <SelectItem key="member">{t('users.import_role_member')}</SelectItem>
                  <SelectItem key="broker">{t('users.import_role_broker')}</SelectItem>
                  <SelectItem key="coordinator">{t('users.import_role_coordinator')}</SelectItem>
                </Select>
              </div>
            ) : (
              <div className="flex flex-col gap-3">
                <div className="flex items-center gap-4">
                  <div className="flex items-center gap-2 text-success">
                    <CheckCircle2 size={18} />
                    <span className="font-medium">{t('users.import_imported', { count: importResults.imported })}</span>
                  </div>
                  {importResults.skipped > 0 && (
                    <div className="flex items-center gap-2 text-warning">
                      <AlertCircle size={18} />
                      <span className="font-medium">{t('users.import_skipped', { count: importResults.skipped })}</span>
                    </div>
                  )}
                  <span className="text-sm text-default-400">
                    {t('users.import_total_rows', { count: importResults.total_rows })}
                  </span>
                </div>

                {importResults.errors.length > 0 && (
                  <div className="max-h-48 overflow-y-auto rounded-lg bg-danger-50 p-3">
                    <p className="text-sm font-medium text-danger mb-1">{t('users.import_errors')}</p>
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
              {importResults ? t('close') : t('cancel')}
            </Button>
            {!importResults && (
              <Button
                color="primary"
                onPress={handleImport}
                isLoading={importLoading}
                isDisabled={!importFile}
                startContent={!importLoading ? <Upload size={16} /> : undefined}
              >
                {t('import')}
              </Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserList;
