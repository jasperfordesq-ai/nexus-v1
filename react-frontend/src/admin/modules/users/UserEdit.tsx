// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin User Edit
 * Edit user details, role, status, profile info, manage badges, password, consents.
 * Parity: PHP Admin\UserController::edit() — all legacy gaps closed
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Button,
  Select,
  SelectItem,
  Textarea,
  Chip,
  Spinner,
  Avatar,
  Switch,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  ArrowLeft,
  Save,
  Trash2,
  LogIn,
  ShieldAlert,
  Coins,
  RefreshCw,
  KeyRound,
  Mail,
  Building2,
  ShieldCheck,
  FileCheck,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminUsers, adminTimebanking, adminVetting, adminInsurance } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { AdminUserDetail, AdminBadge, UpdateUserPayload, UserConsent, VettingRecord, InsuranceCertificate } from '../../api/types';

export function UserEdit() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = (currentUser as Record<string, unknown> | null)?.is_super_admin === true
    || (currentUser as Record<string, unknown> | null)?.is_tenant_super_admin === true
    || (currentUser?.role as string) === 'super_admin';
  const isGod = (currentUser as Record<string, unknown> | null)?.is_god === true;

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [user, setUser] = useState<AdminUserDetail | null>(null);

  // Form state
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [role, setRole] = useState('');
  const [status, setStatus] = useState('');
  const [bio, setBio] = useState('');
  const [tagline, setTagline] = useState('');
  const [location, setLocation] = useState('');
  const [profileType, setProfileType] = useState<'individual' | 'organisation'>('individual');
  const [organizationName, setOrganizationName] = useState('');

  // Tenant super admin toggle (hierarchy-scoped)
  const [isTenantSuperAdmin, setIsTenantSuperAdmin] = useState(false);
  const [tenantSuperAdminLoading, setTenantSuperAdminLoading] = useState(false);

  // Global super admin toggle (god-only, bypasses all tenant isolation)
  const [isGlobalSuperAdmin, setIsGlobalSuperAdmin] = useState(false);
  const [globalSuperAdminLoading, setGlobalSuperAdminLoading] = useState(false);

  // Impersonate
  const [impersonateLoading, setImpersonateLoading] = useState(false);

  // Adjust balance modal
  const [balanceModalOpen, setBalanceModalOpen] = useState(false);
  const [balanceAmount, setBalanceAmount] = useState('');
  const [balanceReason, setBalanceReason] = useState('');
  const [balanceLoading, setBalanceLoading] = useState(false);

  // Badge removal
  const [badgeToRemove, setBadgeToRemove] = useState<AdminBadge | null>(null);
  const [removingBadge, setRemovingBadge] = useState(false);
  const [recheckingBadges, setRecheckingBadges] = useState(false);

  // Password management
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);
  const [newPassword, setNewPassword] = useState('');
  const [passwordLoading, setPasswordLoading] = useState(false);
  const [resetEmailLoading, setResetEmailLoading] = useState(false);

  // Welcome email
  const [welcomeEmailLoading, setWelcomeEmailLoading] = useState(false);

  // GDPR Consents
  const [consents, setConsents] = useState<UserConsent[]>([]);
  const [consentsLoading, setConsentsLoading] = useState(false);

  // Vetting & Insurance records
  const [vettingRecords, setVettingRecords] = useState<VettingRecord[]>([]);
  const [insuranceRecords, setInsuranceRecords] = useState<InsuranceCertificate[]>([]);
  const [complianceLoading, setComplianceLoading] = useState(false);

  const [errors, setErrors] = useState<Record<string, string>>({});

  usePageTitle(user ? `Admin - Edit ${user.name}` : 'Admin - Edit User');

  const loadUser = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setLoadError(null);
    try {
      const res = await adminUsers.get(Number(id));
      if (res.success && res.data) {
        const userData = res.data as AdminUserDetail;
        setUser(userData);
        setFirstName(userData.first_name || '');
        setLastName(userData.last_name || '');
        setEmail(userData.email || '');
        setPhone((userData as unknown as Record<string, unknown>).phone as string || '');
        setRole(userData.role || 'member');
        setStatus(userData.status || 'active');
        setBio(userData.bio || '');
        setTagline(userData.tagline || '');
        setLocation(userData.location || '');
        setProfileType(
          (userData as unknown as Record<string, unknown>).profile_type as 'individual' | 'organisation'
          || 'individual'
        );
        setOrganizationName(userData.organization_name || '');
        setIsTenantSuperAdmin(userData.is_tenant_super_admin || false);
        setIsGlobalSuperAdmin(userData.is_super_admin || false);
      } else {
        setLoadError(res.error || t('user_edit.load_error'));
      }
    } catch {
      setLoadError(t('user_edit.load_error_unexpected'));
    } finally {
      setLoading(false);
    }
  }, [id]);

  const loadConsents = useCallback(async () => {
    if (!id) return;
    setConsentsLoading(true);
    try {
      const res = await adminUsers.getConsents(Number(id));
      if (res.success && Array.isArray(res.data)) {
        setConsents(res.data as UserConsent[]);
      }
    } catch {
      // Consents table may not exist — silently fail
    } finally {
      setConsentsLoading(false);
    }
  }, [id]);

  const loadComplianceRecords = useCallback(async () => {
    if (!id) return;
    setComplianceLoading(true);
    try {
      const [vRes, iRes] = await Promise.all([
        adminVetting.getUserRecords(Number(id)).catch(() => null),
        adminInsurance.getUserCertificates(Number(id)).catch(() => null),
      ]);
      if (vRes?.success && Array.isArray(vRes.data)) setVettingRecords(vRes.data as VettingRecord[]);
      if (iRes?.success && Array.isArray(iRes.data)) setInsuranceRecords(iRes.data as InsuranceCertificate[]);
    } catch {
      // Compliance tables may not exist
    } finally {
      setComplianceLoading(false);
    }
  }, [id]);

  useEffect(() => { loadUser(); loadConsents(); loadComplianceRecords(); }, [loadUser, loadConsents, loadComplianceRecords]);

  function validate(): boolean {
    const newErrors: Record<string, string> = {};
    if (!firstName.trim()) newErrors.first_name = t('user_edit.first_name_required');
    if (!lastName.trim()) newErrors.last_name = t('user_edit.last_name_required');
    if (!email.trim()) {
      newErrors.email = t('user_edit.email_required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = t('user_edit.email_invalid');
    }
    if (!role) newErrors.role = t('user_edit.role_required');
    if (!status) newErrors.status = t('user_edit.status_required');
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!validate() || !id) return;
    setSubmitting(true);
    try {
      const payload: UpdateUserPayload = {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
        phone: phone.trim(),
        role,
        status,
        bio: bio.trim(),
        tagline: tagline.trim(),
        location: location.trim(),
        profile_type: profileType,
        organization_name: organizationName.trim(),
      };
      const res = await adminUsers.update(Number(id), payload);
      if (res.success) {
        toast.success(t('user_edit.update_success'));
        loadUser();
      } else {
        toast.error(res.error || t('user_edit.update_failed'));
      }
    } catch {
      toast.error(t('error_occurred'));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleToggleTenantSuperAdmin() {
    if (!id || !user) return;
    setTenantSuperAdminLoading(true);
    try {
      const res = await adminUsers.setSuperAdmin(Number(id), !isTenantSuperAdmin);
      if (res.success) {
        setIsTenantSuperAdmin(!isTenantSuperAdmin);
        toast.success(!isTenantSuperAdmin ? t('user_edit.tenant_super_admin_granted') : t('user_edit.tenant_super_admin_revoked'));
      } else {
        toast.error(res.error || t('user_edit.tenant_super_admin_failed'));
      }
    } catch {
      toast.error(t('user_edit.tenant_super_admin_failed'));
    } finally {
      setTenantSuperAdminLoading(false);
    }
  }

  async function handleToggleGlobalSuperAdmin() {
    if (!id || !user) return;
    setGlobalSuperAdminLoading(true);
    try {
      const res = await adminUsers.setGlobalSuperAdmin(Number(id), !isGlobalSuperAdmin);
      if (res.success) {
        setIsGlobalSuperAdmin(!isGlobalSuperAdmin);
        toast.success(!isGlobalSuperAdmin ? t('user_edit.global_super_admin_granted') : t('user_edit.global_super_admin_revoked'));
      } else {
        toast.error(res.error || t('user_edit.global_super_admin_failed'));
      }
    } catch {
      toast.error(t('user_edit.global_super_admin_failed'));
    } finally {
      setGlobalSuperAdminLoading(false);
    }
  }

  async function handleImpersonate() {
    if (!id) return;
    setImpersonateLoading(true);
    try {
      const res = await adminUsers.impersonate(Number(id));
      if (res.success && res.data) {
        const token = (res.data as Record<string, unknown>).access_token as string
          || (res.data as Record<string, unknown>).token as string;
        if (token) {
          // Store token in sessionStorage instead of URL query params
          // to avoid leaking it in browser history, Referer headers, and server logs
          sessionStorage.setItem('impersonate_token', token);
          window.open(`${window.location.origin}/`, '_blank');
          toast.success(t('user_edit.impersonate_success', { name: user?.name }));
        }
      } else {
        toast.error(res.error || t('user_edit.impersonate_failed'));
      }
    } catch {
      toast.error(t('user_edit.impersonate_failed'));
    } finally {
      setImpersonateLoading(false);
    }
  }

  async function handleAdjustBalance() {
    if (!id || !balanceAmount.trim() || !balanceReason.trim()) return;
    const amount = parseFloat(balanceAmount);
    if (isNaN(amount) || amount === 0) { toast.error(t('user_edit.balance_invalid')); return; }
    setBalanceLoading(true);
    try {
      const res = await adminTimebanking.adjustBalance(Number(id), amount, balanceReason.trim());
      if (res.success) {
        toast.success(t('user_edit.balance_adjusted', { amount: `${amount > 0 ? '+' : ''}${amount}` }));
        setBalanceModalOpen(false);
        setBalanceAmount('');
        setBalanceReason('');
        loadUser();
      } else {
        toast.error(res.error || t('user_edit.balance_failed'));
      }
    } catch {
      toast.error(t('user_edit.balance_failed'));
    } finally {
      setBalanceLoading(false);
    }
  }

  async function handleRemoveBadge() {
    if (!badgeToRemove || !id) return;
    setRemovingBadge(true);
    try {
      const res = await adminUsers.removeBadge(Number(id), badgeToRemove.id);
      if (res.success) {
        toast.success(t('user_edit.remove_badge_success', { badge: badgeToRemove.name }));
        setUser((prev) =>
          prev ? { ...prev, badges: prev.badges.filter((b) => b.id !== badgeToRemove.id) } : prev
        );
      } else {
        toast.error(res.error || t('user_edit.remove_badge_failed'));
      }
    } catch {
      toast.error(t('error_occurred'));
    } finally {
      setRemovingBadge(false);
      setBadgeToRemove(null);
    }
  }

  async function handleRecheckBadges() {
    if (!id) return;
    setRecheckingBadges(true);
    try {
      const res = await adminUsers.recheckUserBadges(Number(id));
      if (res.success && res.data) {
        const data = res.data as { badges?: AdminBadge[] };
        if (data.badges) {
          setUser((prev) => prev ? { ...prev, badges: data.badges! } : prev);
        }
        toast.success(t('user_edit.recheck_complete'));
      } else {
        toast.error(res.error || t('user_edit.recheck_failed'));
      }
    } catch {
      toast.error(t('user_edit.recheck_failed'));
    } finally {
      setRecheckingBadges(false);
    }
  }

  async function handleSetPassword() {
    if (!id || !newPassword.trim()) return;
    if (newPassword.length < 8) { toast.error(t('user_edit.password_min_length')); return; }
    setPasswordLoading(true);
    try {
      const res = await adminUsers.setPassword(Number(id), newPassword);
      if (res.success) {
        toast.success(t('user_edit.password_updated'));
        setPasswordModalOpen(false);
        setNewPassword('');
      } else {
        toast.error(res.error || t('user_edit.password_failed'));
      }
    } catch {
      toast.error(t('user_edit.password_failed'));
    } finally {
      setPasswordLoading(false);
    }
  }

  async function handleSendPasswordReset() {
    if (!id) return;
    setResetEmailLoading(true);
    try {
      const res = await adminUsers.sendPasswordReset(Number(id));
      if (res.success) {
        toast.success(t('user_edit.password_reset_sent'));
      } else {
        toast.error(res.error || t('user_edit.password_reset_failed'));
      }
    } catch {
      toast.error(t('user_edit.password_reset_failed'));
    } finally {
      setResetEmailLoading(false);
    }
  }

  async function handleSendWelcomeEmail() {
    if (!id) return;
    setWelcomeEmailLoading(true);
    try {
      const res = await adminUsers.sendWelcomeEmail(Number(id));
      if (res.success) {
        toast.success(t('user_edit.welcome_email_sent'));
      } else {
        toast.error(res.error || t('user_edit.welcome_email_failed'));
      }
    } catch {
      toast.error(t('user_edit.welcome_email_failed'));
    } finally {
      setWelcomeEmailLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={t('user_edit.loading_user')} />
      </div>
    );
  }

  if (loadError || !user) {
    return (
      <div>
        <PageHeader
          title={t('user_edit.page_title')}
          actions={
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
              {t('user_edit.back_to_users')}
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">{loadError || t('user_edit.user_not_found')}</p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/users'))}>{t('user_edit.return_to_list')}</Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  const canImpersonate = isSuperAdmin && !user.is_super_admin && !user.is_god && user.id !== currentUser?.id;

  return (
    <div>
      <PageHeader
        title={`Edit User: ${user.name}`}
        description={`ID: ${user.id} | Joined: ${new Date(user.created_at).toLocaleDateString()}`}
        actions={
          <div className="flex items-center gap-2">
            {canImpersonate && (
              <Button
                variant="flat"
                color="warning"
                startContent={<LogIn size={16} />}
                onPress={handleImpersonate}
                isLoading={impersonateLoading}
                size="sm"
              >
                {t('user_edit.impersonate')}
              </Button>
            )}
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
              {t('user_edit.back_to_users')}
            </Button>
          </div>
        }
      />

      <div className="flex flex-col gap-6 max-w-2xl">
        {/* User Details Form */}
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader className="px-6 pt-5 pb-0">
              <div className="flex items-center gap-4">
                <Avatar src={resolveAvatarUrl(user.avatar_url || user.avatar) || undefined} name={user.name} size="lg" />
                <div>
                  <h3 className="text-lg font-semibold text-foreground">{user.name}</h3>
                  <p className="text-sm text-default-500">{user.email}</p>
                  {user.balance !== undefined && (
                    <p className="text-xs text-default-400 mt-0.5">Balance: {user.balance}h</p>
                  )}
                </div>
              </div>
            </CardHeader>
            <CardBody className="gap-5 p-6">
              {/* Name */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label={t('users.label_first_name')} placeholder={t('users.placeholder_enter_first_name')} value={firstName} onValueChange={setFirstName}
                  isRequired isInvalid={!!errors.first_name} errorMessage={errors.first_name} isDisabled={submitting} />
                <Input label={t('users.label_last_name')} placeholder={t('users.placeholder_enter_last_name')} value={lastName} onValueChange={setLastName}
                  isRequired isInvalid={!!errors.last_name} errorMessage={errors.last_name} isDisabled={submitting} />
              </div>

              {/* Email + Phone */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label={t('users.label_email')} type="email" placeholder="user@example.com" value={email} onValueChange={setEmail}
                  isRequired isInvalid={!!errors.email} errorMessage={errors.email} isDisabled={submitting} />
                <Input label={t('users.label_phone')} type="tel" placeholder="e.g. +1 555 123 4567" value={phone}
                  onValueChange={setPhone} isDisabled={submitting} />
              </div>

              {/* Role + Status */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select label={t('users.label_role')} placeholder={t('users.placeholder_select_a_role')} selectedKeys={role ? [role] : []}
                  onSelectionChange={(keys) => setRole(Array.from(keys)[0] as string)}
                  isRequired isInvalid={!!errors.role} errorMessage={errors.role} isDisabled={submitting}>
                  <SelectItem key="member">Member</SelectItem>
                  <SelectItem key="broker">Broker</SelectItem>
                  <SelectItem key="moderator">Moderator</SelectItem>
                  <SelectItem key="newsletter_admin">Newsletter Admin</SelectItem>
                  <SelectItem key="admin">Admin</SelectItem>
                  <SelectItem key="tenant_admin">Tenant Admin</SelectItem>
                </Select>
                <Select label={t('users.label_status')} placeholder={t('users.placeholder_select_a_status')} selectedKeys={status ? [status] : []}
                  onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
                  isRequired isInvalid={!!errors.status} errorMessage={errors.status} isDisabled={submitting}>
                  <SelectItem key="active">Active</SelectItem>
                  <SelectItem key="pending">Pending</SelectItem>
                  <SelectItem key="suspended">Suspended</SelectItem>
                  <SelectItem key="banned">Banned</SelectItem>
                </Select>
              </div>

              {/* Profile Type + Organization */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select
                  label={t('users.label_profile_type')}
                  placeholder={t('users.placeholder_select_type')}
                  selectedKeys={[profileType]}
                  onSelectionChange={(keys) => setProfileType(Array.from(keys)[0] as 'individual' | 'organisation')}
                  isDisabled={submitting}
                >
                  <SelectItem key="individual">Individual</SelectItem>
                  <SelectItem key="organisation">Organisation</SelectItem>
                </Select>
                {profileType === 'organisation' && (
                  <Input
                    label={t('users.label_organisation_name')}
                    placeholder={t('users.placeholder_eg_community_centre')}
                    value={organizationName}
                    onValueChange={setOrganizationName}
                    startContent={<Building2 size={14} className="text-default-400" />}
                    isDisabled={submitting}
                  />
                )}
              </div>

              {/* Bio */}
              <Textarea label={t('users.label_bio')} placeholder={t('users.placeholder_a_short_biography_for_this_user')} value={bio} onValueChange={setBio}
                minRows={3} maxRows={6} isDisabled={submitting} />

              {/* Tagline + Location */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label={t('users.label_tagline')} placeholder={t('users.placeholder_eg_community_volunteer')} value={tagline}
                  onValueChange={setTagline} isDisabled={submitting} />
                <Input label={t('users.label_location')} placeholder="e.g. New York, USA" value={location}
                  onValueChange={setLocation} isDisabled={submitting} />
              </div>

              {/* Submit */}
              <div className="flex justify-end gap-3 pt-2">
                <Button variant="flat" onPress={() => navigate(tenantPath('/admin/users'))} isDisabled={submitting}>
                  {t('cancel')}
                </Button>
                <Button type="submit" color="primary" startContent={!submitting ? <Save size={16} /> : undefined}
                  isLoading={submitting}>
                  {t('user_edit.save_changes')}
                </Button>
              </div>
            </CardBody>
          </Card>
        </form>

        {/* Tenant Super Admin (hierarchy-scoped — visible to super admins) */}
        {isSuperAdmin && (
          <Card>
            <CardHeader className="px-6 pt-5 pb-0">
              <div className="flex items-center gap-2">
                <ShieldAlert size={18} className="text-warning" />
                <h3 className="text-lg font-semibold text-foreground">{t('user_edit.super_admin_access')}</h3>
              </div>
            </CardHeader>
            <CardBody className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-foreground">{t('user_edit.tenant_super_admin')}</p>
                  <p className="text-sm text-default-500">
                    {t('user_edit.tenant_super_admin_description')}
                  </p>
                </div>
                <Switch
                  isSelected={isTenantSuperAdmin}
                  onValueChange={handleToggleTenantSuperAdmin}
                  isDisabled={tenantSuperAdminLoading || user.id === currentUser?.id}
                  color="warning"
                  aria-label={t('user_edit.tenant_super_admin_toggle')}
                />
              </div>
              {user.id === currentUser?.id && (
                <p className="text-xs text-default-400 mt-2">{t('user_edit.cannot_modify_own')}</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Global Super Admin (god-only — bypasses all tenant isolation) */}
        {isGod && (
          <Card className="border-2 border-danger/30">
            <CardHeader className="px-6 pt-5 pb-0">
              <div className="flex items-center gap-2">
                <ShieldAlert size={18} className="text-danger" />
                <h3 className="text-lg font-semibold text-danger">{t('user_edit.global_super_admin')}</h3>
              </div>
            </CardHeader>
            <CardBody className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-foreground">{t('user_edit.platform_wide_access')}</p>
                  <p className="text-sm text-default-500">
                    {t('user_edit.global_super_admin_description')}
                  </p>
                </div>
                <Switch
                  isSelected={isGlobalSuperAdmin}
                  onValueChange={handleToggleGlobalSuperAdmin}
                  isDisabled={globalSuperAdminLoading || user.id === currentUser?.id}
                  color="danger"
                  aria-label={t('user_edit.global_super_admin_toggle')}
                />
              </div>
              {user.id === currentUser?.id && (
                <p className="text-xs text-default-400 mt-2">{t('user_edit.cannot_modify_own')}</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Password & Email Management */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center gap-2">
              <KeyRound size={18} className="text-primary" />
              <h3 className="text-lg font-semibold text-foreground">{t('user_edit.account_actions')}</h3>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            <div className="flex flex-wrap gap-3">
              <Button
                size="sm"
                variant="flat"
                color="primary"
                startContent={<KeyRound size={14} />}
                onPress={() => setPasswordModalOpen(true)}
              >
                {t('user_edit.set_password')}
              </Button>
              <Button
                size="sm"
                variant="flat"
                startContent={<Mail size={14} />}
                onPress={handleSendPasswordReset}
                isLoading={resetEmailLoading}
              >
                {t('user_edit.send_password_reset')}
              </Button>
              <Button
                size="sm"
                variant="flat"
                startContent={<Mail size={14} />}
                onPress={handleSendWelcomeEmail}
                isLoading={welcomeEmailLoading}
              >
                {t('user_edit.resend_welcome_email')}
              </Button>
            </div>
          </CardBody>
        </Card>

        {/* Wallet Balance */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center justify-between w-full">
              <div className="flex items-center gap-2">
                <Coins size={18} className="text-primary" />
                <h3 className="text-lg font-semibold text-foreground">{t('user_edit.time_credits')}</h3>
              </div>
              <Button size="sm" variant="flat" color="primary" onPress={() => setBalanceModalOpen(true)}>
                {t('user_edit.adjust_balance')}
              </Button>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            <div className="flex items-center gap-4">
              <div className="text-3xl font-bold text-foreground">{user.balance ?? 0}h</div>
              <p className="text-sm text-default-500">{t('user_edit.current_balance')}</p>
            </div>
          </CardBody>
        </Card>

        {/* Badges Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center justify-between w-full">
              <h3 className="text-lg font-semibold text-foreground">{t('user_edit.badges')}</h3>
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={14} />}
                onPress={handleRecheckBadges}
                isLoading={recheckingBadges}
              >
                {t('user_edit.recheck_badges')}
              </Button>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            {user.badges && user.badges.length > 0 ? (
              <div className="flex flex-wrap gap-3">
                {user.badges.map((badge) => (
                  <Chip
                    key={badge.id}
                    variant="flat"
                    color="primary"
                    size="lg"
                    startContent={badge.icon ? <span className="text-sm">{badge.icon}</span> : undefined}
                    endContent={
                      <Button isIconOnly variant="light" size="sm" onPress={() => setBadgeToRemove(badge)}
                        className="ml-1 min-w-0 w-5 h-5 rounded-full text-default-400 hover:bg-danger-100 hover:text-danger"
                        aria-label={`Remove badge: ${badge.name}`}>
                        <Trash2 size={12} />
                      </Button>
                    }
                  >
                    {badge.name}
                  </Chip>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-400">{t('user_edit.no_badges')}</p>
            )}
          </CardBody>
        </Card>

        {/* GDPR Consents Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center gap-2">
              <ShieldCheck size={18} className="text-success" />
              <h3 className="text-lg font-semibold text-foreground">{t('user_edit.gdpr_consents')}</h3>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            {consentsLoading ? (
              <Spinner size="sm" label={t('user_edit.loading_consents')} />
            ) : consents.length > 0 ? (
              <div className="flex flex-col gap-3">
                {consents.map((consent) => (
                  <div
                    key={consent.consent_type}
                    className="flex items-center justify-between rounded-lg border border-default-200 p-3"
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-medium text-foreground">
                          {consent.name || consent.consent_type.replace(/_/g, ' ')}
                        </p>
                        {consent.is_required && (
                          <Chip size="sm" variant="flat" color="warning">Required</Chip>
                        )}
                      </div>
                      {consent.description && (
                        <p className="text-xs text-default-400 mt-0.5">{consent.description}</p>
                      )}
                      <p className="text-xs text-default-400 mt-1">
                        {consent.consent_given
                          ? `Consented${consent.given_at ? ` on ${new Date(consent.given_at).toLocaleDateString()}` : ''}`
                          : consent.withdrawn_at
                            ? `Withdrawn on ${new Date(consent.withdrawn_at).toLocaleDateString()}`
                            : 'Not consented'
                        }
                        {consent.consent_version && ` (v${consent.consent_version})`}
                      </p>
                    </div>
                    <Chip
                      size="sm"
                      variant="flat"
                      color={consent.consent_given ? 'success' : 'danger'}
                    >
                      {consent.consent_given ? 'Given' : 'Not Given'}
                    </Chip>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-400">{t('user_edit.no_consent_records')}</p>
            )}
          </CardBody>
        </Card>

        {/* Safeguarding & Compliance Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center gap-2">
              <ShieldAlert size={18} className="text-warning" />
              <h3 className="text-lg font-semibold text-foreground">{t('user_edit.safeguarding')}</h3>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            {complianceLoading ? (
              <Spinner size="sm" label={t('user_edit.loading_compliance')} />
            ) : (
              <div className="flex flex-col gap-6">
                {/* Vetting Status */}
                <div>
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <ShieldCheck size={16} className="text-primary" />
                      <p className="font-medium text-foreground">{t('user_edit.vetting_status')}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={
                          user?.vetting_status === 'verified' ? 'success'
                            : user?.vetting_status === 'pending' ? 'warning'
                            : user?.vetting_status === 'expired' ? 'danger'
                            : 'default'
                        }
                        className="capitalize"
                      >
                        {user?.vetting_status || 'none'}
                      </Chip>
                      <Button
                        as={Link}
                        to={tenantPath('/admin/broker-controls/vetting')}
                        size="sm"
                        variant="flat"
                        color="primary"
                      >
                        {t('manage')}
                      </Button>
                    </div>
                  </div>
                  {vettingRecords.length > 0 ? (
                    <div className="flex flex-col gap-2">
                      {vettingRecords.slice(0, 3).map((vr) => (
                        <div key={vr.id} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                          <div>
                            <p className="text-sm font-medium text-foreground">
                              {vr.vetting_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                            </p>
                            <p className="text-xs text-default-400">
                              {vr.reference_number || 'No reference'}
                              {vr.expiry_date ? ` — Expires ${new Date(vr.expiry_date).toLocaleDateString()}` : ''}
                            </p>
                          </div>
                          <Chip
                            size="sm"
                            variant="flat"
                            color={
                              vr.status === 'verified' ? 'success'
                                : vr.status === 'pending' || vr.status === 'submitted' ? 'warning'
                                : vr.status === 'rejected' ? 'danger'
                                : 'default'
                            }
                            className="capitalize"
                          >
                            {vr.status}
                          </Chip>
                        </div>
                      ))}
                      {vettingRecords.length > 3 && (
                        <p className="text-xs text-default-400">+ {vettingRecords.length - 3} more records</p>
                      )}
                    </div>
                  ) : (
                    <p className="text-sm text-default-400">{t('user_edit.no_vetting_records')}</p>
                  )}
                </div>

                {/* Insurance Status */}
                <div>
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <FileCheck size={16} className="text-primary" />
                      <p className="font-medium text-foreground">{t('user_edit.insurance_certificates')}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={
                          user?.insurance_status === 'verified' ? 'success'
                            : user?.insurance_status === 'pending' ? 'warning'
                            : user?.insurance_status === 'expired' ? 'danger'
                            : 'default'
                        }
                        className="capitalize"
                      >
                        {user?.insurance_status || 'none'}
                      </Chip>
                      <Button
                        as={Link}
                        to={tenantPath('/admin/broker-controls/insurance')}
                        size="sm"
                        variant="flat"
                        color="primary"
                      >
                        {t('manage')}
                      </Button>
                    </div>
                  </div>
                  {insuranceRecords.length > 0 ? (
                    <div className="flex flex-col gap-2">
                      {insuranceRecords.slice(0, 3).map((ic) => (
                        <div key={ic.id} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                          <div>
                            <p className="text-sm font-medium text-foreground">
                              {ic.insurance_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                            </p>
                            <p className="text-xs text-default-400">
                              {ic.provider_name || 'Unknown provider'}
                              {ic.expiry_date ? ` — Expires ${new Date(ic.expiry_date).toLocaleDateString()}` : ''}
                            </p>
                          </div>
                          <Chip
                            size="sm"
                            variant="flat"
                            color={
                              ic.status === 'verified' ? 'success'
                                : ic.status === 'pending' || ic.status === 'submitted' ? 'warning'
                                : ic.status === 'rejected' ? 'danger'
                                : 'default'
                            }
                            className="capitalize"
                          >
                            {ic.status}
                          </Chip>
                        </div>
                      ))}
                      {insuranceRecords.length > 3 && (
                        <p className="text-xs text-default-400">+ {insuranceRecords.length - 3} more certificates</p>
                      )}
                    </div>
                  ) : (
                    <p className="text-sm text-default-400">{t('user_edit.no_insurance_records')}</p>
                  )}
                </div>
              </div>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Badge Removal Confirmation */}
      {badgeToRemove && (
        <ConfirmModal
          isOpen={!!badgeToRemove}
          onClose={() => setBadgeToRemove(null)}
          onConfirm={handleRemoveBadge}
          title={t('user_edit.remove_badge')}
          message={t('user_edit.remove_badge_confirm', { badge: badgeToRemove.name, name: user.name })}
          confirmLabel={t('user_edit.remove_badge')}
          confirmColor="danger"
          isLoading={removingBadge}
        />
      )}

      {/* Adjust Balance Modal */}
      <Modal isOpen={balanceModalOpen}
        onClose={() => { setBalanceModalOpen(false); setBalanceAmount(''); setBalanceReason(''); }}
        size="sm">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Coins size={20} className="text-primary" />
            {t('user_edit.adjust_time_credits')}
          </ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-default-500">Current balance: <strong>{user.balance ?? 0}h</strong></p>
            <Input label={t('users.label_amount')} placeholder="e.g. 2 or -1.5"
              description={t('users.desc_use_negative_values_to_deduct_credits')}
              value={balanceAmount} onValueChange={setBalanceAmount}
              type="number" step="0.5" isDisabled={balanceLoading} />
            <Input label={t('users.label_reason')} placeholder="Why are you adjusting this balance?"
              value={balanceReason} onValueChange={setBalanceReason}
              isRequired isDisabled={balanceLoading} />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat"
              onPress={() => { setBalanceModalOpen(false); setBalanceAmount(''); setBalanceReason(''); }}
              isDisabled={balanceLoading}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleAdjustBalance} isLoading={balanceLoading}
              isDisabled={!balanceAmount.trim() || !balanceReason.trim()}>
              {t('user_edit.apply_adjustment')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Set Password Modal */}
      <Modal isOpen={passwordModalOpen}
        onClose={() => { setPasswordModalOpen(false); setNewPassword(''); }}
        size="sm">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <KeyRound size={20} className="text-primary" />
            {t('user_edit.set_password_title')}
          </ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-default-500">
              Set a new password for <strong>{user.name}</strong>. The user will not be notified.
            </p>
            <Input
              label={t('users.label_new_password')}
              type="password"
              placeholder="Enter new password (min 8 characters)"
              value={newPassword}
              onValueChange={setNewPassword}
              isDisabled={passwordLoading}
              description="Minimum 8 characters"
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat"
              onPress={() => { setPasswordModalOpen(false); setNewPassword(''); }}
              isDisabled={passwordLoading}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleSetPassword} isLoading={passwordLoading}
              isDisabled={newPassword.length < 8}>
              {t('user_edit.set_password')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserEdit;
