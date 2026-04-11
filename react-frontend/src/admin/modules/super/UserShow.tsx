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
import {
  ArrowLeft, Edit, Shield, ShieldOff, ShieldAlert, Crown, CrownIcon,
  MapPin, Phone, Clock, CalendarDays, Wallet, User, Building2, ArrowRightLeft,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { SuperAdminUserDetail, SuperAdminTenant } from '../../api/types';

import { useTranslation } from 'react-i18next';
import i18n from '@/i18n';
type ConfirmActionType =
  | 'grant-sa'
  | 'revoke-sa'
  | 'grant-global'
  | 'revoke-global';

function getPrivilegeLevel(user: SuperAdminUserDetail, t: (key: string) => string) {
  if (user.is_super_admin) return { label: t('super.privilege_global_super_admin'), color: 'danger' as const, level: 4 };
  if (user.is_tenant_super_admin) return { label: t('super.privilege_tenant_super_admin'), color: 'secondary' as const, level: 3 };
  if (user.role === 'admin' || user.role === 'tenant_admin') return { label: t('super.privilege_admin'), color: 'primary' as const, level: 2 };
  return { label: t('super.privilege_regular_member'), color: 'default' as const, level: 1 };
}

function formatDate(dateStr: string | null | undefined, neverLabel = 'Never'): string {
  if (!dateStr) return neverLabel;
  return new Date(dateStr).toLocaleDateString(i18n.language, {
    year: 'numeric', month: 'long', day: 'numeric',
  });
}

export function UserShow() {
  const { t } = useTranslation('admin');
  usePageTitle(t('super.page_title'));
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

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
      toast.success(t('super.user_updated_successfully'));
      loadUser();
    } else {
      toast.error(res?.error || t('super.action_failed'));
    }
    setActionLoading(false);
    setConfirmAction(null);
  };

  const handleMoveTenant = async () => {
    if (!user || !moveTargetTenant) return;
    setMoveLoading(true);
    const res = await adminSuper.moveUserTenant(user.id, Number(moveTargetTenant));
    if (res?.success) {
      toast.success(t('super.user_moved_to_new_tenant'));
      setMoveModalOpen(false);
      setMoveTargetTenant('');
      loadUser();
    } else {
      toast.error(res?.error || t('super.failed_to_move_user'));
    }
    setMoveLoading(false);
  };

  const handleMoveAndPromote = async () => {
    if (!user || !promoteTargetTenant) return;
    setPromoteLoading(true);
    const res = await adminSuper.moveAndPromote(user.id, Number(promoteTargetTenant));
    if (res?.success) {
      toast.success(t('super.user_moved_and_promoted_to_tenant_super_'));
      setPromoteModalOpen(false);
      setPromoteTargetTenant('');
      loadUser();
    } else {
      toast.error(res?.error || t('super.failed_to_move_and_promote'));
    }
    setPromoteLoading(false);
  };

  const confirmMessages: Record<ConfirmActionType, { title: string; message: string; label: string; color: 'danger' | 'warning' | 'primary' }> = {
    'grant-sa': {
      title: t('super.confirm_grant_sa_title'),
      message: t('super.confirm_grant_sa_message_detail', { name: user?.name || 'this user' }),
      label: t('super.grant_tenant_sa'),
      color: 'primary',
    },
    'revoke-sa': {
      title: t('super.confirm_revoke_sa_title'),
      message: t('super.confirm_revoke_sa_message_detail', { name: user?.name || 'this user' }),
      label: t('super.revoke_tenant_sa'),
      color: 'danger',
    },
    'grant-global': {
      title: t('super.confirm_grant_global_title'),
      message: t('super.confirm_grant_global_message_detail', { name: user?.name || 'this user' }),
      label: t('super.grant_global_sa'),
      color: 'danger',
    },
    'revoke-global': {
      title: t('super.confirm_revoke_global_title'),
      message: t('super.confirm_revoke_global_message_detail', { name: user?.name || 'this user' }),
      label: t('super.revoke_global_sa'),
      color: 'danger',
    },
  };

  // Hub tenants for move-and-promote
  const hubTenants = tenants.filter(t => t.allows_subtenants === true);

  if (loading) {
    return (
      <div className="flex items-center justify-center p-12">
        <Spinner size="lg" label={t('super.loading_user_details')} />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="p-8 text-center">
        <p className="text-default-500">{t('super.user_not_found')}</p>
        <Button className="mt-4" variant="flat" onPress={() => navigate(tenantPath('/admin/super/users'))}>
          {t('super.back_to_users')}
        </Button>
      </div>
    );
  }

  const privilege = getPrivilegeLevel(user, t);

  return (
    <div>
      <PageHeader
        title={user.name}
        description={t('super.cross_tenant_user_details', { email: user.email })}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<Edit size={16} />}
              onPress={() => navigate(tenantPath(`/admin/super/users/${user.id}/edit`))}
            >
              {t('super.edit')}
            </Button>
            <Button
              variant="light"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/super/users'))}
            >
              {t('super.back_to_users')}
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
              {t('super.user_information')}
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
                        {t('super.privilege_global_super_admin')}
                      </Chip>
                    )}
                    {user.is_tenant_super_admin && (
                      <Chip size="sm" variant="flat" color="secondary" startContent={<Shield size={12} />}>
                        {t('super.privilege_tenant_super_admin')}
                      </Chip>
                    )}
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Profile Info */}
          <Card>
            <CardHeader className="font-semibold text-lg">{t('super.profile_info')}</CardHeader>
            <Divider />
            <CardBody>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="flex items-center gap-3">
                  <MapPin size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{t('super.label_location')}</p>
                    <p className="text-sm text-foreground">{user.location || t('super.not_set')}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Phone size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{t('super.label_phone')}</p>
                    <p className="text-sm text-foreground">{user.phone || t('super.not_set')}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <CalendarDays size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{t('super.label_member_since')}</p>
                    <p className="text-sm text-foreground">{formatDate(user.created_at, t('super.never'))}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Clock size={16} className="text-default-400 shrink-0" />
                  <div>
                    <p className="text-xs text-default-400">{t('super.label_last_login')}</p>
                    <p className="text-sm text-foreground">{formatDate(user.last_login_at, t('super.never'))}</p>
                  </div>
                </div>
                {user.balance !== undefined && (
                  <div className="flex items-center gap-3">
                    <Wallet size={16} className="text-default-400 shrink-0" />
                    <div>
                      <p className="text-xs text-default-400">{t('super.label_time_balance')}</p>
                      <p className="text-sm font-medium text-foreground">{user.balance} {t('super.hours')}</p>
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
              {t('super.tenant_association')}
            </CardHeader>
            <Divider />
            <CardBody className="flex flex-col gap-4">
              <div className="flex flex-col gap-2">
                <div>
                  <p className="text-xs text-default-400">{t('super.label_current_tenant')}</p>
                  <Link
                    to={tenantPath(`/admin/super/tenants/${user.tenant_id}`)}
                    className="text-sm font-medium text-primary hover:underline"
                  >
                    {user.tenant_name || `Tenant ${user.tenant_id}`}
                  </Link>
                </div>
                <div>
                  <p className="text-xs text-default-400">{t('super.label_tenant_i_d')}</p>
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
                  {t('super.move_to_different_tenant')}
                </Button>
                <Button
                  variant="flat"
                  color="secondary"
                  startContent={<Crown size={16} />}
                  onPress={() => setPromoteModalOpen(true)}
                >
                  {t('super.move_and_promote_to_hub')}
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
                {t('super.god_level_access')}
              </span>
            </CardHeader>
            <Divider className="bg-purple-500/20" />
            <CardBody className="flex flex-col gap-3">
              <p className="text-xs text-default-600">
                {t('super.god_level_desc')}
              </p>
              {user.is_super_admin ? (
                <Button
                  variant="flat"
                  className="bg-gradient-to-r from-red-500/10 to-pink-500/10 border border-red-500/30"
                  startContent={<ShieldOff size={16} />}
                  onPress={() => setConfirmAction('revoke-global')}
                >
                  <span className="bg-gradient-to-r from-red-600 to-pink-600 bg-clip-text text-transparent font-medium">
                    {t('super.revoke_global_sa')}
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
                      {t('super.grant_global_sa')}
                    </span>
                  </Button>
                  <p className="text-[10px] text-purple-700 dark:text-purple-400 mt-2 text-center font-medium">
                    {t('super.god_level_caution')}
                  </p>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Tenant Super Admin Toggle */}
          <Card>
            <CardHeader className="font-semibold text-lg flex items-center gap-2">
              <Shield size={18} />
              {t('super.tenant_super_admin')}
            </CardHeader>
            <Divider />
            <CardBody className="flex flex-col gap-3">
              <p className="text-xs text-default-600">
                {t('super.tenant_sa_desc')}
              </p>
              {user.is_tenant_super_admin ? (
                <Button
                  variant="flat"
                  color="warning"
                  startContent={<ShieldOff size={16} />}
                  onPress={() => setConfirmAction('revoke-sa')}
                >
                  {t('super.revoke_tenant_sa')}
                </Button>
              ) : (
                <Button
                  variant="flat"
                  color="secondary"
                  startContent={<Shield size={16} />}
                  onPress={() => setConfirmAction('grant-sa')}
                >
                  {t('super.grant_tenant_sa')}
                </Button>
              )}
            </CardBody>
          </Card>

          {/* Privilege Level */}
          <Card>
            <CardHeader className="font-semibold text-lg">{t('super.privilege_level')}</CardHeader>
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
                  <span>{t('super.privilege_member_short')}</span>
                  <span>{t('super.privilege_admin_short')}</span>
                  <span>{t('super.privilege_tsa_short')}</span>
                  <span>{t('super.privilege_gsa_short')}</span>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Quick Actions */}
          <Card>
            <CardHeader className="font-semibold text-lg">{t('super.quick_actions')}</CardHeader>
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
                  {t('super.edit_user')}
                </Button>
                <Button
                  variant="light"
                  startContent={<ArrowLeft size={16} />}
                  fullWidth
                  onPress={() => navigate(tenantPath('/admin/super/users'))}
                >
                  {t('super.back_to_users')}
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
          <ModalHeader>{t('super.move_user_to_tenant')}</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600 mb-3" dangerouslySetInnerHTML={{ __html: t('super.move_user_desc', { name: user.name }) }} />

            <Select
              label={t('super.label_target_tenant')}
              placeholder={t('super.placeholder_select_a_tenant')}
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
              {t('super.cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleMoveTenant}
              isLoading={moveLoading}
              isDisabled={!moveTargetTenant}
            >
              {t('super.move_user')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Move and Promote Modal */}
      <Modal isOpen={promoteModalOpen} onClose={() => { setPromoteModalOpen(false); setPromoteTargetTenant(''); }} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Crown size={20} className="text-secondary" />
            {t('super.move_and_promote')}
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600 mb-3" dangerouslySetInnerHTML={{ __html: t('super.move_and_promote_desc', { name: user.name }) }} />
            <div className="bg-warning-50 dark:bg-warning-50/10 border border-warning-200 dark:border-warning-200/20 rounded-lg p-3 mb-3">
              <p className="text-xs text-warning-700 dark:text-warning-400">
                {t('super.move_and_promote_warning')}
              </p>
            </div>
            <Select
              label={t('super.label_target_hub_tenant')}
              placeholder={t('super.placeholder_select_a_hub_tenant')}
              selectedKeys={promoteTargetTenant ? [promoteTargetTenant] : []}
              onSelectionChange={(keys) => setPromoteTargetTenant(String(Array.from(keys)[0] || ''))}
            >
              {hubTenants
                .filter(t => t.id !== user.tenant_id)
                .map(t => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>
            {hubTenants.filter(t => t.id !== user.tenant_id).length === 0 && (
              <p className="text-xs text-default-400 mt-1">{t('super.no_hub_tenants')}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => { setPromoteModalOpen(false); setPromoteTargetTenant(''); }} isDisabled={promoteLoading}>
              {t('super.cancel')}
            </Button>
            <Button
              color="secondary"
              onPress={handleMoveAndPromote}
              isLoading={promoteLoading}
              isDisabled={!promoteTargetTenant}
            >
              {t('super.move_and_promote')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserShow;
