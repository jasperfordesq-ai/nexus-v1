// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Chip, Divider, Avatar, Spinner,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Select, SelectItem,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Edit from 'lucide-react/icons/square-pen';
import Shield from 'lucide-react/icons/shield';
import ShieldOff from 'lucide-react/icons/shield-off';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Crown from 'lucide-react/icons/crown';
import CrownIcon from 'lucide-react/icons/crown';
import MapPin from 'lucide-react/icons/map-pin';
import Phone from 'lucide-react/icons/phone';
import Clock from 'lucide-react/icons/clock';
import CalendarDays from 'lucide-react/icons/calendar-days';
import Wallet from 'lucide-react/icons/wallet';
import User from 'lucide-react/icons/user';
import Building2 from 'lucide-react/icons/building-2';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import UserCog from 'lucide-react/icons/user-cog';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminSuper, adminUsers } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { SuperAdminUserDetail, SuperAdminTenant } from '../../api/types';

import { useTranslation } from 'react-i18next';
import i18n from '@/i18n';
import { sanitizeInline } from '@/lib/sanitize';
type ConfirmActionType =
  | 'grant-sa'
  | 'revoke-sa'
  | 'grant-global'
  | 'revoke-global';

function getPrivilegeLevel(user: SuperAdminUserDetail, t: (key: string) => string) {
  if (user.is_super_admin) return { label: "Privilege Global Super Admin", color: 'danger' as const, level: 4 };
  if (user.is_tenant_super_admin) return { label: "Privilege Tenant Super Admin", color: 'secondary' as const, level: 3 };
  if (user.role === 'admin' || user.role === 'tenant_admin') return { label: "Privilege Admin", color: 'primary' as const, level: 2 };
  return { label: "Privilege Regular Member", color: 'default' as const, level: 1 };
}

function formatDate(dateStr: string | null | undefined, neverLabel = 'Never'): string {
  if (!dateStr) return neverLabel;
  return new Date(dateStr).toLocaleDateString(i18n.language, {
    year: 'numeric', month: 'long', day: 'numeric',
  });
}

export function UserShow() {
  const { t } = useTranslation('admin');
  usePageTitle("Super Admin");
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const currentUserRecord = currentUser as Record<string, unknown> | null;
  const isCurrentSuperAdmin =
    (currentUser?.role as string) === 'super_admin' ||
    currentUserRecord?.is_super_admin === true;

  // Impersonation modal state
  const [impersonateModalOpen, setImpersonateModalOpen] = useState(false);
  const [impersonateLoading, setImpersonateLoading] = useState(false);

  const [user, setUser] = useState<SuperAdminUserDetail | null>(null);
  const [tenants, setTenants] = useState<SuperAdminTenant[]>([]);
  const [loading, setLoading] = useState(true);

  // Confirm modal state
  const [confirmAction, setConfirmAction] = useState<ConfirmActionType | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Move to Tenant modal
  const [moveModalOpen, setMoveModalOpen] = useState(false);
  const [moveTargetTenant, setMoveTargetTenant] = useState<string>('');
  const [moveLoading, setMoveLoading] = useState(false);

  // Move and Promote modal
  const [promoteModalOpen, setPromoteModalOpen] = useState(false);
  const [promoteTargetTenant, setPromoteTargetTenant] = useState<string>('');
  const [promoteLoading, setPromoteLoading] = useState(false);

  const loadUser = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    const res = await adminSuper.getUser(Number(id));
    if (res.success && res.data) {
      setUser(res.data as SuperAdminUserDetail);
    }
    setLoading(false);
  }, [id]);

  const loadTenants = useCallback(async () => {
    const res = await adminSuper.listTenants();
    if (res.success && res.data) {
      setTenants(Array.isArray(res.data) ? res.data as SuperAdminTenant[] : []);
    }
  }, []);

  useEffect(() => { loadUser(); }, [loadUser]);
  useEffect(() => { loadTenants(); }, [loadTenants]);

  const handleConfirmAction = async () => {
    if (!confirmAction || !user) return;
    setActionLoading(true);
    let res;
    switch (confirmAction) {
      case 'grant-sa': res = await adminSuper.grantSuperAdmin(user.id); break;
      case 'revoke-sa': res = await adminSuper.revokeSuperAdmin(user.id); break;
      case 'grant-global': res = await adminSuper.grantGlobalSuperAdmin(user.id); break;
      case 'revoke-global': res = await adminSuper.revokeGlobalSuperAdmin(user.id); break;
    }
    if (res?.success) {
      toast.success("User updated successfully");
      loadUser();
    } else {
      toast.error(res?.error || "Failed");
    }
    setActionLoading(false);
    setConfirmAction(null);
  };

  const handleMoveTenant = async () => {
    if (!user || !moveTargetTenant) return;
    setMoveLoading(true);
    const res = await adminSuper.moveUserTenant(user.id, Number(moveTargetTenant));
    if (res?.success) {
      toast.success("User Moved to New Tenant");
      setMoveModalOpen(false);
      setMoveTargetTenant('');
      loadUser();
    } else {
      toast.error(res?.error || "Failed to move user");
    }
    setMoveLoading(false);
  };

  const handleMoveAndPromote = async () => {
    if (!user || !promoteTargetTenant) return;
    setPromoteLoading(true);
    const res = await adminSuper.moveAndPromote(user.id, Number(promoteTargetTenant));
    if (res?.success) {
      toast.success("User moved and promoted to tenant super admin");
      setPromoteModalOpen(false);
      setPromoteTargetTenant('');
      loadUser();
    } else {
      toast.error(res?.error || "Failed to move and promote");
    }
    setPromoteLoading(false);
  };

  const handleImpersonate = async () => {
    if (!user) return;
    setImpersonateLoading(true);
    try {
      const res = await adminUsers.impersonate(user.id);
      if (res?.success && res.data?.token) {
        toast.success(t('super.impersonation_started', 'Impersonation started'));
        // Store token & navigate to member dashboard. The backend returns a
        // short-lived token scoped to the target user.
        localStorage.setItem('impersonation_token', res.data.token);
        setImpersonateModalOpen(false);
        navigate(tenantPath('/dashboard'));
      } else {
        toast.error(
          (res as { error?: string })?.error ||
          t('super.impersonation_failed', 'Failed to start impersonation'),
        );
      }
    } catch {
      toast.error(t('super.impersonation_failed', 'Failed to start impersonation'));
    }
    setImpersonateLoading(false);
  };

  const confirmMessages: Record<ConfirmActionType, { title: string; message: string; label: string; color: 'danger' | 'warning' | 'primary' }> = {
    'grant-sa': {
      title: "Are you sure you want to grant sa title?",
      message: `Are you sure you want to grant sa message detail?`,
      label: "Grant Tenant Sa",
      color: 'primary',
    },
    'revoke-sa': {
      title: "Are you sure you want to revoke sa title?",
      message: `Are you sure you want to revoke sa message detail?`,
      label: "Revoke Tenant Sa",
      color: 'danger',
    },
    'grant-global': {
      title: "Are you sure you want to grant global title?",
      message: `Are you sure you want to grant global message detail?`,
      label: "Grant Global Sa",
      color: 'danger',
    },
    'revoke-global': {
      title: "Are you sure you want to revoke global title?",
      message: `Are you sure you want to revoke global message detail?`,
      label: "Revoke Global Sa",
      color: 'danger',
    },
  };

  // Hub tenants for move-and-promote
  const hubTenants = tenants.filter(t => t.allows_subtenants === true);

  if (loading) {
    return (
      <div className="flex items-center justify-center p-12">
        <Spinner size="lg" label={"Loading user details..."} />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="p-8 text-center">
        <p className="text-default-500">{"User Not Found"}</p>
        <Button className="mt-4" variant="flat" onPress={() => navigate(tenantPath('/admin/super/users'))}>
          {"Back to Users"}
        </Button>
      </div>
    );
  }

  const privilege = getPrivilegeLevel(user, t);

  return (
    <div>
      <PageHeader
        title={user.name}
        description={`Cross Tenant User Details`}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<Edit size={16} />}
              onPress={() => navigate(tenantPath(`/admin/super/users/${user.id}/edit`))}
            >
              {"Edit"}
            </Button>
            <Button
              variant="light"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/super/users'))}
            >
              {"Back to Users"}
            </Button>
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left column - 2/3 width */}
        <div className="lg:col-span-2 flex flex-col gap-6">
          {/* User Information */}
          <Card>
            <CardHeader className="font-semibold text-lg flex items-center gap-2">
              <User size={18} />
              {"User Information"}
            </CardHeader>
            <Divider />
            <CardBody>
              <div className="flex items-start gap-4">
                <Avatar
                  name={user.name}
                  src={resolveAvatarUrl(user.avatar) || undefined}
                  size="lg"
                  className="shrink-0"
                />
                <div className="flex flex-col gap-2 flex-1">
                  <div>
                    <h2 className="text-xl font-semibold text-foreground">{user.first_name} {user.last_name}</h2>
                    <p className="text-default-500">{user.email}</p>
                  </div>
                  <div className="flex flex-wrap items-center gap-2 mt-1">
                    <Chip size="sm" variant="flat" color={user.role === 'admin' || user.role === 'tenant_admin' ? 'primary' : 'default'}>
                      {user.role}
                    </Chip>
                    <Chip
                      size="sm"
                      variant="flat"
                      color={user.status === 'active' ? 'success' : user.status === 'pending' ? 'warning' : 'danger'}
                    >
                      {user.status}
                    </Chip>
                    {user.is_super_admin && (
                      <Chip size="sm" variant="flat" color="danger" startContent={<ShieldAlert size={12} />}>
                        {"Privilege Global Super Admin"}
                      </Chip>
                    )}
                    {user.is_tenant_super_admin && (
                      <Chip size="sm" variant="flat" color="secondary" startContent={<Shield size={12} />}>
                        {"Privilege Tenant Super Admin"}
                      </Chip>
                    )}
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Profile Info */}
          <Card>
            <CardHeader className="font-semibold text-lg">{"Profile Info"}</CardHeader>
            <Divider />
            <CardBody>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="flex items-center gap-3">
                  <MapPin size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{"Location"}</p>
                    <p className="text-sm text-foreground">{user.location || "Not Set"}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Phone size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{"Phone"}</p>
                    <p className="text-sm text-foreground">{user.phone || "Not Set"}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <CalendarDays size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{"Member Since"}</p>
                    <p className="text-sm text-foreground">{formatDate(user.created_at, "Never")}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Clock size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{"Last Login"}</p>
                    <p className="text-sm text-foreground">{formatDate(user.last_login_at, "Never")}</p>
                  </div>
                </div>
                {user.balance !== undefined && (
                  <div className="flex items-center gap-3">
                    <Wallet size={16} className="text-default-400 shrink-0" />
                    <div>
                      <p className="text-xs text-default-400">{"Time Balance"}</p>
                      <p className="text-sm font-medium text-foreground">{user.balance} {"Hours"}</p>
                    </div>
                  </div>
                )}
              </div>
            </CardBody>
          </Card>

          {/* Tenant Association */}
          <Card>
            <CardHeader className="font-semibold text-lg flex items-center gap-2">
              <Building2 size={18} />
              {"Tenant Association"}
            </CardHeader>
            <Divider />
            <CardBody className="flex flex-col gap-4">
              <div className="flex flex-col gap-2">
                <div>
                  <p className="text-xs text-default-400">{"Current Tenant"}</p>
                  <Link
                    to={tenantPath(`/admin/super/tenants/${user.tenant_id}`)}
                    className="text-sm font-medium text-primary hover:underline"
                  >
                    {user.tenant_name || `Tenant ${user.tenant_id}`}
                  </Link>
                </div>
                <div>
                  <p className="text-xs text-default-400">{"Tenant I D"}</p>
                  <p className="text-sm text-foreground">{user.tenant_id}</p>
                </div>
              </div>
              <Divider />
              <div className="flex flex-col gap-2">
                <Button
                  variant="flat"
                  color="default"
                  startContent={<ArrowRightLeft size={16} />}
                  onPress={() => setMoveModalOpen(true)}
                >
                  {"Move to Different Tenant"}
                </Button>
                <Button
                  variant="flat"
                  color="secondary"
                  startContent={<Crown size={16} />}
                  onPress={() => setPromoteModalOpen(true)}
                >
                  {"Move and Promote to Hub"}
                </Button>
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Right column - 1/3 width */}
        <div className="flex flex-col gap-6">
          {/* GOD-Level Super Admin Actions */}
          <Card className="bg-gradient-to-br from-purple-500/10 to-pink-500/10 border-2 border-purple-500/30">
            <CardHeader className="font-semibold text-lg flex items-center gap-2">
              <ShieldAlert size={18} className="text-purple-600 dark:text-purple-400" />
              <span className="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                {"God Level Access"}
              </span>
            </CardHeader>
            <Divider className="bg-purple-500/20" />
            <CardBody className="flex flex-col gap-3">
              <p className="text-xs text-default-600">
                {"God Level."}
              </p>
              {user.is_super_admin ? (
                <Button
                  variant="flat"
                  className="bg-gradient-to-r from-red-500/10 to-pink-500/10 border border-red-500/30"
                  startContent={<ShieldOff size={16} />}
                  onPress={() => setConfirmAction('revoke-global')}
                >
                  <span className="bg-gradient-to-r from-red-600 to-pink-600 bg-clip-text text-transparent font-medium">
                    {"Revoke Global Sa"}
                  </span>
                </Button>
              ) : (
                <div>
                  <Button
                    variant="flat"
                    className="bg-gradient-to-r from-purple-500/20 to-pink-500/20 border-2 border-purple-500/50 w-full"
                    startContent={<ShieldAlert size={16} />}
                    onPress={() => setConfirmAction('grant-global')}
                  >
                    <span className="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent font-semibold">
                      {"Grant Global Sa"}
                    </span>
                  </Button>
                  <p className="text-[10px] text-purple-700 dark:text-purple-400 mt-2 text-center font-medium">
                    {"God Level Caution"}
                  </p>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Tenant Super Admin Toggle */}
          <Card>
            <CardHeader className="font-semibold text-lg flex items-center gap-2">
              <Shield size={18} />
              {"Tenant Super Admin"}
            </CardHeader>
            <Divider />
            <CardBody className="flex flex-col gap-3">
              <p className="text-xs text-default-600">
                {"Tenant Sa."}
              </p>
              {user.is_tenant_super_admin ? (
                <Button
                  variant="flat"
                  color="warning"
                  startContent={<ShieldOff size={16} />}
                  onPress={() => setConfirmAction('revoke-sa')}
                >
                  {"Revoke Tenant Sa"}
                </Button>
              ) : (
                <Button
                  variant="flat"
                  color="secondary"
                  startContent={<Shield size={16} />}
                  onPress={() => setConfirmAction('grant-sa')}
                >
                  {"Grant Tenant Sa"}
                </Button>
              )}
            </CardBody>
          </Card>

          {/* Privilege Level */}
          <Card>
            <CardHeader className="font-semibold text-lg">{"Privilege Level"}</CardHeader>
            <Divider />
            <CardBody>
              <div className="flex flex-col items-center gap-3 py-2">
                <div className={`w-16 h-16 rounded-full flex items-center justify-center ${
                  privilege.level === 4 ? 'bg-gradient-to-br from-purple-500/20 to-pink-500/20' :
                  privilege.level === 3 ? 'bg-secondary/10' :
                  privilege.level === 2 ? 'bg-primary/10' :
                  'bg-default/10'
                }`}>
                  {privilege.level >= 3 ? (
                    <CrownIcon size={28} className={
                      privilege.level === 4 ? 'text-purple-600' : 'text-secondary'
                    } />
                  ) : (
                    <Shield size={28} className={
                      privilege.level === 2 ? 'text-primary' : 'text-default-400'
                    } />
                  )}
                </div>
                <Chip size="lg" variant="flat" color={privilege.color}>
                  {privilege.label}
                </Chip>
                {/* Privilege bar */}
                <div className="w-full flex gap-1 mt-1">
                  {[1, 2, 3, 4].map(level => (
                    <div
                      key={level}
                      className={`h-2 flex-1 rounded-full ${
                        level <= privilege.level
                          ? privilege.level === 4 ? 'bg-gradient-to-r from-purple-500 to-pink-500'
                            : privilege.level === 3 ? 'bg-secondary'
                            : privilege.level === 2 ? 'bg-primary'
                            : 'bg-default-300'
                          : 'bg-default-100'
                      }`}
                    />
                  ))}
                </div>
                <div className="flex justify-between w-full text-[10px] text-default-400">
                  <span>{"Privilege Member Short"}</span>
                  <span>{"Privilege Admin Short"}</span>
                  <span>{"Privilege Tsa Short"}</span>
                  <span>{"Privilege Gsa Short"}</span>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Quick Actions */}
          <Card>
            <CardHeader className="font-semibold text-lg">{"Quick Actions"}</CardHeader>
            <Divider />
            <CardBody>
              <div className="flex flex-col gap-2">
                <Button
                  color="primary"
                  variant="flat"
                  startContent={<Edit size={16} />}
                  fullWidth
                  onPress={() => navigate(tenantPath(`/admin/super/users/${user.id}/edit`))}
                >
                  {"Edit User"}
                </Button>
                {isCurrentSuperAdmin && (
                  <Button
                    color="warning"
                    variant="flat"
                    startContent={<UserCog size={16} />}
                    fullWidth
                    onPress={() => setImpersonateModalOpen(true)}
                  >
                    {t('super.impersonate_user', 'Impersonate user')}
                  </Button>
                )}
                <Button
                  variant="light"
                  startContent={<ArrowLeft size={16} />}
                  fullWidth
                  onPress={() => navigate(tenantPath('/admin/super/users'))}
                >
                  {"Back to Users"}
                </Button>
              </div>
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Confirm Action Modal */}
      {confirmAction && (
        <ConfirmModal
          isOpen={!!confirmAction}
          onClose={() => setConfirmAction(null)}
          onConfirm={handleConfirmAction}
          title={confirmMessages[confirmAction].title}
          message={confirmMessages[confirmAction].message}
          confirmLabel={confirmMessages[confirmAction].label}
          confirmColor={confirmMessages[confirmAction].color}
          isLoading={actionLoading}
        />
      )}

      {/* Move to Tenant Modal */}
      <Modal isOpen={moveModalOpen} onClose={() => { setMoveModalOpen(false); setMoveTargetTenant(''); }} size="md">
        <ModalContent>
          <ModalHeader>{"Move User to Tenant"}</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600 mb-3" dangerouslySetInnerHTML={{ __html: sanitizeInline(`Move User.`) }} />

            <Select
              label={"Target Tenant"}
              placeholder={"Select a Tenant..."}
              selectedKeys={moveTargetTenant ? [moveTargetTenant] : []}
              onSelectionChange={(keys) => setMoveTargetTenant(String(Array.from(keys)[0] || ''))}
            >
              {tenants
                .filter(t => t.id !== user.tenant_id)
                .map(t => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => { setMoveModalOpen(false); setMoveTargetTenant(''); }} isDisabled={moveLoading}>
              {"Cancel"}
            </Button>
            <Button
              color="primary"
              onPress={handleMoveTenant}
              isLoading={moveLoading}
              isDisabled={!moveTargetTenant}
            >
              {"Move User"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Impersonation Modal */}
      <Modal
        isOpen={impersonateModalOpen}
        onClose={() => setImpersonateModalOpen(false)}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <UserCog size={20} className="text-warning" />
            {t('super.impersonate_user_title', 'Impersonate user')}
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600">
              {t('super.impersonate_user_desc', {
                name: user.name,
                defaultValue:
                  'You are about to impersonate {{name}}. You will see the platform exactly as they do. This action is logged in the super-admin audit trail.',
              })}
            </p>
            <div className="bg-warning-50 dark:bg-warning-50/10 border border-warning-200 dark:border-warning-200/20 rounded-lg p-3 mt-3">
              <p className="text-xs text-warning-700 dark:text-warning-400">
                {t(
                  'super.impersonate_user_warning',
                  'Only use this feature for support and debugging. Your actions will appear as if performed by the target user.',
                )}
              </p>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setImpersonateModalOpen(false)}
              isDisabled={impersonateLoading}
            >
              {"Cancel"}
            </Button>
            <Button
              color="warning"
              onPress={handleImpersonate}
              isLoading={impersonateLoading}
              startContent={<UserCog size={16} />}
            >
              {t('super.impersonate_confirm', 'Start impersonation')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Move and Promote Modal */}
      <Modal isOpen={promoteModalOpen} onClose={() => { setPromoteModalOpen(false); setPromoteTargetTenant(''); }} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Crown size={20} className="text-secondary" />
            {"Move and Promote"}
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600 mb-3" dangerouslySetInnerHTML={{ __html: sanitizeInline(`Move and Promote.`) }} />
            <div className="bg-warning-50 dark:bg-warning-50/10 border border-warning-200 dark:border-warning-200/20 rounded-lg p-3 mb-3">
              <p className="text-xs text-warning-700 dark:text-warning-400">
                {"Move and Promote Warning"}
              </p>
            </div>
            <Select
              label={"Target Hub Tenant"}
              placeholder={"Select a Hub Tenant..."}
              selectedKeys={promoteTargetTenant ? [promoteTargetTenant] : []}
              onSelectionChange={(keys) => setPromoteTargetTenant(String(Array.from(keys)[0] || ''))}
            >
              {hubTenants
                .filter(t => t.id !== user.tenant_id)
                .map(t => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>
            {hubTenants.filter(t => t.id !== user.tenant_id).length === 0 && (
              <p className="text-xs text-default-400 mt-1">{"No hub tenants found"}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => { setPromoteModalOpen(false); setPromoteTargetTenant(''); }} isDisabled={promoteLoading}>
              {"Cancel"}
            </Button>
            <Button
              color="secondary"
              onPress={handleMoveAndPromote}
              isLoading={promoteLoading}
              isDisabled={!promoteTargetTenant}
            >
              {"Move and Promote"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserShow;
