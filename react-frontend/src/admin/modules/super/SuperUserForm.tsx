// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Input, Select, SelectItem, Switch, Divider,
} from '@heroui/react';
import { Save, ArrowLeft, ArrowRightLeft, Crown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SuperAdminUserDetail, SuperAdminTenant } from '../../api/types';

export function SuperUserForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams();
  const isEditing = !!id;
  usePageTitle(isEditing ? t('super_user_form.page_title_edit') : t('super_user_form.page_title_create'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [tenants, setTenants] = useState<SuperAdminTenant[]>([]);
  const [form, setForm] = useState({
    tenant_id: '', first_name: '', last_name: '', email: '', password: '',
    role: 'member', location: '', phone: '', is_tenant_super_admin: false,
  });
  const [user, setUser] = useState<SuperAdminUserDetail | null>(null);

  // Move tenant state
  const [moveTargetTenant, setMoveTargetTenant] = useState('');
  const [moveLoading, setMoveLoading] = useState(false);

  // Promote state
  const [promoteTargetTenant, setPromoteTargetTenant] = useState('');
  const [promoteLoading, setPromoteLoading] = useState(false);

  const loadTenants = useCallback(async () => {
    const res = await adminSuper.listTenants();
    if (res.success && res.data) {
      setTenants(Array.isArray(res.data) ? res.data as SuperAdminTenant[] : []);
    }
  }, []);

  const loadUser = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    const res = await adminSuper.getUser(Number(id));
    if (res.success && res.data) {
      const u = res.data as SuperAdminUserDetail;
      setUser(u);
      setForm({
        tenant_id: String(u.tenant_id), first_name: u.first_name, last_name: u.last_name,
        email: u.email, password: '', role: u.role, location: u.location || '',
        phone: u.phone || '', is_tenant_super_admin: u.is_tenant_super_admin,
      });
    }
    setLoading(false);
  }, [id]);

  useEffect(() => { loadTenants(); }, [loadTenants]);
  useEffect(() => { loadUser(); }, [loadUser]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    let res;
    if (isEditing) {
      res = await adminSuper.updateUser(Number(id), {
        first_name: form.first_name, last_name: form.last_name, email: form.email,
        role: form.role, location: form.location || null, phone: form.phone || null,
      });
    } else {
      res = await adminSuper.createUser({
        tenant_id: Number(form.tenant_id), first_name: form.first_name, last_name: form.last_name,
        email: form.email, password: form.password, role: form.role,
        location: form.location || undefined, phone: form.phone || undefined,
        is_tenant_super_admin: form.is_tenant_super_admin,
      });
    }
    if (res?.success) {
      toast.success(isEditing ? t('super_user_form.user_updated') : t('super_user_form.user_created'));
      if (isEditing) {
        navigate(tenantPath(`/admin/super/users/${id}`));
      } else {
        const newId = (res as { data?: { user_id?: number } }).data?.user_id;
        navigate(tenantPath(newId ? `/admin/super/users/${newId}` : '/admin/super/users'));
      }
    } else {
      toast.error(res?.error || t('super_user_form.failed_to_save_user'));
    }
    setSaving(false);
  };

  const handleMoveTenant = async () => {
    if (!id || !moveTargetTenant) return;
    setMoveLoading(true);
    const res = await adminSuper.moveUserTenant(Number(id), Number(moveTargetTenant));
    if (res?.success) {
      toast.success(t('super_user_form.user_moved_to_new_tenant'));
      setMoveTargetTenant('');
      loadUser();
    } else {
      toast.error(res?.error || t('super_user_form.failed_to_move_user'));
    }
    setMoveLoading(false);
  };

  const handleMoveAndPromote = async () => {
    if (!id || !promoteTargetTenant) return;
    setPromoteLoading(true);
    const res = await adminSuper.moveAndPromote(Number(id), Number(promoteTargetTenant));
    if (res?.success) {
      toast.success(t('super_user_form.user_moved_and_promoted'));
      setPromoteTargetTenant('');
      loadUser();
    } else {
      toast.error(res?.error || t('super_user_form.failed_to_move_and_promote'));
    }
    setPromoteLoading(false);
  };

  const update = (field: string, value: unknown) => setForm(prev => ({ ...prev, [field]: value }));

  if (loading) return <div className="p-8 text-center text-default-400">{t('super_user_form.loading')}</div>;

  // Hub tenants for promote
  const hubTenants = tenants.filter(t => t.allows_subtenants === true);

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <Link to={tenantPath('/admin/super/users')} className="hover:text-primary">Users</Link>
        <span>/</span>
        <span className="text-foreground">{isEditing ? t('super_user_form.breadcrumb_edit') : t('super_user_form.breadcrumb_create')}</span>
      </nav>
      <PageHeader
        title={isEditing ? t('super_user_form.title_edit') : t('super_user_form.title_create')}
        description={isEditing ? t('super_user_form.desc_edit') : t('super_user_form.desc_create')}
        actions={
          <Button variant="light" startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath(isEditing ? `/admin/super/users/${id}` : '/admin/super/users'))}>
            {t('super_user_form.back')}
          </Button>
        }
      />

      {/* CREATE MODE - Single Column Form */}
      {!isEditing && (
        <Card className="max-w-2xl">
          <CardBody>
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
              <Select label={t('super_user_form.label_tenant')} isRequired selectedKeys={form.tenant_id ? [form.tenant_id] : []}
                onSelectionChange={(keys) => update('tenant_id', Array.from(keys)[0])}>
                {tenants.map((t) => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
              </Select>
              <div className="grid grid-cols-2 gap-4">
                <Input label={t('super_user_form.label_first_name')} isRequired value={form.first_name}
                  onValueChange={(v) => update('first_name', v)} />
                <Input label={t('super_user_form.label_last_name')} value={form.last_name}
                  onValueChange={(v) => update('last_name', v)} />
              </div>
              <Input label={t('super_user_form.label_email')} type="email" isRequired value={form.email}
                onValueChange={(v) => update('email', v)} />
              <Input label={t('super_user_form.label_password')} type="password" isRequired value={form.password}
                onValueChange={(v) => update('password', v)} />
              <Select label={t('super_user_form.label_role')} selectedKeys={[form.role]}
                onSelectionChange={(keys) => update('role', Array.from(keys)[0])}>
                <SelectItem key="member">{t('super_user_form.role_member')}</SelectItem>
                <SelectItem key="admin">{t('super_user_form.role_admin')}</SelectItem>
                <SelectItem key="tenant_admin">{t('super_user_form.role_tenant_admin')}</SelectItem>
              </Select>
              <div className="grid grid-cols-2 gap-4">
                <Input label={t('super_user_form.label_location')} value={form.location} onValueChange={(v) => update('location', v)} />
                <Input label={t('super_user_form.label_phone')} value={form.phone} onValueChange={(v) => update('phone', v)} />
              </div>
              <Divider />
              <div className="bg-gradient-to-br from-purple-500/10 to-pink-500/10 border border-purple-500/20 rounded-lg p-4">
                <Switch
                  isSelected={form.is_tenant_super_admin}
                  onValueChange={(v) => update('is_tenant_super_admin', v)}
                  classNames={{
                    wrapper: 'group-data-[selected=true]:bg-gradient-to-r group-data-[selected=true]:from-purple-500 group-data-[selected=true]:to-pink-500',
                  }}
                >
                  <div>
                    <p className="font-medium">{t('super_user_form.grant_tenant_super_admin')}</p>
                    <p className="text-xs text-default-500 mt-0.5">{t('super_user_form.grant_tenant_super_admin_desc')}</p>
                  </div>
                </Switch>
              </div>
              <Button type="submit" color="primary" startContent={<Save size={16} />}
                isLoading={saving}>
                {t('super_user_form.create_user')}
              </Button>
            </form>
          </CardBody>
        </Card>
      )}

      {/* EDIT MODE - 2-Column Layout */}
      {isEditing && user && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Column (2/3 width) */}
          <div className="lg:col-span-2 flex flex-col gap-6">
            {/* User Details Form */}
            <Card>
              <CardHeader className="font-semibold text-lg">{t('super_user_form.user_details')}</CardHeader>
              <Divider />
              <CardBody>
                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                  <div className="grid grid-cols-2 gap-4">
                    <Input label={t('super_user_form.label_first_name')} isRequired value={form.first_name}
                      onValueChange={(v) => update('first_name', v)} />
                    <Input label={t('super_user_form.label_last_name')} value={form.last_name}
                      onValueChange={(v) => update('last_name', v)} />
                  </div>
                  <Input label={t('super_user_form.label_email')} type="email" isRequired value={form.email}
                    onValueChange={(v) => update('email', v)} />
                  <Select label={t('super_user_form.label_role')} selectedKeys={[form.role]}
                    onSelectionChange={(keys) => update('role', Array.from(keys)[0])}>
                    <SelectItem key="member">{t('super_user_form.role_member')}</SelectItem>
                    <SelectItem key="admin">{t('super_user_form.role_admin')}</SelectItem>
                    <SelectItem key="tenant_admin">{t('super_user_form.role_tenant_admin')}</SelectItem>
                  </Select>
                  <div className="grid grid-cols-2 gap-4">
                    <Input label={t('super_user_form.label_location')} value={form.location} onValueChange={(v) => update('location', v)} />
                    <Input label={t('super_user_form.label_phone')} value={form.phone} onValueChange={(v) => update('phone', v)} />
                  </div>
                  <Button type="submit" color="primary" startContent={<Save size={16} />}
                    isLoading={saving}>
                    {t('super_user_form.update_user')}
                  </Button>
                </form>
              </CardBody>
            </Card>

            {/* Move to Tenant Form */}
            <Card>
              <CardHeader className="font-semibold text-lg flex items-center gap-2">
                <ArrowRightLeft size={18} />
                {t('super_user_form.move_to_tenant')}
              </CardHeader>
              <Divider />
              <CardBody className="flex flex-col gap-4">
                <div className="bg-blue-50 dark:bg-blue-50/10 border border-blue-200 dark:border-blue-200/20 rounded-lg p-3">
                  <p className="text-sm text-blue-700 dark:text-blue-400 font-medium mb-2">{t('super_user_form.how_it_works')}</p>
                  <ol className="text-xs text-blue-600 dark:text-blue-300 space-y-1 list-decimal list-inside">
                    <li>{t('super_user_form.move_step_1')}</li>
                    <li>{t('super_user_form.move_step_2')}</li>
                    <li>{t('super_user_form.move_step_3')}</li>
                    <li>{t('super_user_form.move_step_4')}</li>
                  </ol>
                </div>
                <Select
                  label={t('super_user_form.label_target_tenant')}
                  placeholder={t('super_user_form.select_tenant_placeholder')}
                  selectedKeys={moveTargetTenant ? [moveTargetTenant] : []}
                  onSelectionChange={(keys) => setMoveTargetTenant(String(Array.from(keys)[0] || ''))}
                >
                  {tenants
                    .filter(t => t.id !== user.tenant_id)
                    .map(t => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
                </Select>
                <Button
                  color="default"
                  variant="flat"
                  startContent={<ArrowRightLeft size={16} />}
                  onPress={handleMoveTenant}
                  isLoading={moveLoading}
                  isDisabled={!moveTargetTenant}
                >
                  {t('super_user_form.move_user')}
                </Button>
              </CardBody>
            </Card>

            {/* Move and Promote to Regional SA */}
            <Card>
              <CardHeader className="font-semibold text-lg flex items-center gap-2">
                <Crown size={18} className="text-secondary" />
                {t('super_user_form.move_and_promote_to_regional_sa')}
              </CardHeader>
              <Divider />
              <CardBody className="flex flex-col gap-4">
                <div className="bg-purple-50 dark:bg-purple-50/10 border border-purple-200 dark:border-purple-200/20 rounded-lg p-3">
                  <p className="text-sm text-purple-700 dark:text-purple-400 font-medium mb-2">{t('super_user_form.four_step_workflow')}</p>
                  <ol className="text-xs text-purple-600 dark:text-purple-300 space-y-1 list-decimal list-inside">
                    <li>{t('super_user_form.promote_step_1')}</li>
                    <li>{t('super_user_form.promote_step_2')}</li>
                    <li>{t('super_user_form.promote_step_3')}</li>
                    <li>{t('super_user_form.promote_step_4')}</li>
                  </ol>
                  <p className="text-xs text-purple-600 dark:text-purple-300 mt-2 font-medium">
                    {t('super_user_form.hub_tenants_note')}
                  </p>
                </div>
                <Select
                  label={t('super_user_form.label_target_hub_tenant')}
                  placeholder={t('super_user_form.select_hub_tenant_placeholder')}
                  selectedKeys={promoteTargetTenant ? [promoteTargetTenant] : []}
                  onSelectionChange={(keys) => setPromoteTargetTenant(String(Array.from(keys)[0] || ''))}
                >
                  {hubTenants
                    .filter(t => t.id !== user.tenant_id)
                    .map(t => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
                </Select>
                {hubTenants.filter(t => t.id !== user.tenant_id).length === 0 && (
                  <p className="text-xs text-default-400">{t('super_user_form.no_hub_tenants')}</p>
                )}
                <Button
                  color="secondary"
                  variant="flat"
                  startContent={<Crown size={16} />}
                  onPress={handleMoveAndPromote}
                  isLoading={promoteLoading}
                  isDisabled={!promoteTargetTenant}
                >
                  {t('super_user_form.move_and_promote')}
                </Button>
              </CardBody>
            </Card>
          </div>

          {/* Sidebar Column (1/3 width) */}
          <div className="flex flex-col gap-6">
            {/* Status Card */}
            <Card>
              <CardHeader className="font-semibold text-lg">{t('super_user_form.status')}</CardHeader>
              <Divider />
              <CardBody>
                <div className="flex flex-col gap-3">
                  <div>
                    <p className="text-xs text-default-400">{t('super_user_form.account_status')}</p>
                    <p className="text-sm font-medium capitalize">{user.status}</p>
                  </div>
                  <div>
                    <p className="text-xs text-default-400">{t('super_user_form.label_tenant')}</p>
                    <Link to={tenantPath(`/admin/super/tenants/${user.tenant_id}`)} className="text-sm text-primary hover:underline">
                      {user.tenant_name || `Tenant ${user.tenant_id}`}
                    </Link>
                  </div>
                  <div>
                    <p className="text-xs text-default-400">{t('super_user_form.member_since')}</p>
                    <p className="text-sm">{new Date(user.created_at).toLocaleDateString()}</p>
                  </div>
                </div>
              </CardBody>
            </Card>

            {/* Super Admin Privileges */}
            <Card className="bg-gradient-to-br from-purple-500/5 to-pink-500/5 border border-purple-500/20">
              <CardHeader className="font-semibold text-lg">{t('super_user_form.super_admin_privileges')}</CardHeader>
              <Divider />
              <CardBody className="flex flex-col gap-3">
                <div>
                  <p className="text-xs text-default-400 mb-1">{t('super_user_form.tenant_super_admin')}</p>
                  <p className="text-sm">
                    {user.is_tenant_super_admin ? (
                      <span className="text-success">{t('super_user_form.granted')}</span>
                    ) : (
                      <span className="text-default-500">{t('super_user_form.not_granted')}</span>
                    )}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-default-400 mb-1">{t('super_user_form.global_super_admin')}</p>
                  <p className="text-sm">
                    {user.is_super_admin ? (
                      <span className="text-danger">{t('super_user_form.granted_god_level')}</span>
                    ) : (
                      <span className="text-default-500">{t('super_user_form.not_granted')}</span>
                    )}
                  </p>
                </div>
                <p className="text-xs text-default-400 mt-2">
                  {t('super_user_form.manage_sa_from')}{' '}
                  <Link to={tenantPath(`/admin/super/users/${id}`)} className="text-primary hover:underline">
                    {t('super_user_form.user_detail_page')}
                  </Link>
                </p>
              </CardBody>
            </Card>

            {/* Quick Links */}
            <Card>
              <CardHeader className="font-semibold text-lg">{t('super_user_form.quick_links')}</CardHeader>
              <Divider />
              <CardBody className="flex flex-col gap-2">
                <Button
                  variant="flat"
                  fullWidth
                  onPress={() => navigate(tenantPath(`/admin/super/users/${id}`))}
                >
                  {t('super_user_form.view_full_details')}
                </Button>
                <Button
                  variant="light"
                  fullWidth
                  onPress={() => navigate(tenantPath('/admin/super/users'))}
                >
                  {t('super_user_form.back_to_users')}
                </Button>
              </CardBody>
            </Card>
          </div>
        </div>
      )}
    </div>
  );
}
export default SuperUserForm;
