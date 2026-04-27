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
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import Trash2 from 'lucide-react/icons/trash-2';
import LogIn from 'lucide-react/icons/log-in';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Coins from 'lucide-react/icons/coins';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import KeyRound from 'lucide-react/icons/key-round';
import Mail from 'lucide-react/icons/mail';
import Building2 from 'lucide-react/icons/building-2';
import ShieldCheck from 'lucide-react/icons/shield-check';
import FileCheck from 'lucide-react/icons/file-check';
import Landmark from 'lucide-react/icons/landmark';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { api } from '@/lib/api';
import { adminUsers, adminTimebanking, adminVetting, adminInsurance } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { AdminUserDetail, AdminBadge, UpdateUserPayload, UserConsent, VettingRecord, InsuranceCertificate } from '../../api/types';

export function UserEdit() {
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = currentUser?.is_super_admin === true
    || currentUser?.is_tenant_super_admin === true
    || currentUser?.role === 'super_admin';
  const isGod = currentUser?.is_god === true;

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

  // Municipal Announcer role (AG14)
  const [isMunicipalityAnnouncer, setIsMunicipalityAnnouncer] = useState(false);
  const [announcerLoading, setAnnouncerLoading] = useState(false);

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

  usePageTitle(user ? `Edit ${user.name}` : "Edit User");

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
        setPhone(userData.phone || '');
        setRole(userData.role || 'member');
        setStatus(userData.status || 'active');
        setBio(userData.bio || '');
        setTagline(userData.tagline || '');
        setLocation(userData.location || '');
        setProfileType(userData.profile_type || 'individual');
        setOrganizationName(userData.organization_name || '');
        setIsTenantSuperAdmin(userData.is_tenant_super_admin || false);
        setIsGlobalSuperAdmin(userData.is_super_admin || false);
        const roles = (userData as unknown as Record<string, unknown>).roles as string[] | undefined;
        setIsMunicipalityAnnouncer(Array.isArray(roles) ? roles.includes('municipality_announcer') : false);
      } else {
        setLoadError(res.error || "Load error");
      }
    } catch {
      setLoadError("An unexpected error occurred while loading this user");
    } finally {
      setLoading(false);
    }
  }, [id])


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
    if (!firstName.trim()) newErrors.first_name = "First name is required";
    if (!lastName.trim()) newErrors.last_name = "Last name is required";
    if (!email.trim()) {
      newErrors.email = "Email Required";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = "Email Invalid";
    }
    if (!role) newErrors.role = "Role Required";
    if (!status) newErrors.status = "Status Required";
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
        toast.success("Update successfully");
        loadUser();
      } else {
        toast.error(res.error || "Update Failed");
      }
    } catch {
      toast.error("Occurred error");
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
        toast.success(!isTenantSuperAdmin ? "Tenant super admin granted" : "Tenant super admin revoked");
      } else {
        toast.error(res.error || "Failed to update tenant super admin status");
      }
    } catch {
      toast.error("Failed to update tenant super admin status");
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
        toast.success(!isGlobalSuperAdmin ? "Global super admin granted" : "Global super admin revoked");
      } else {
        toast.error(res.error || "Failed to update global super admin status");
      }
    } catch {
      toast.error("Failed to update global super admin status");
    } finally {
      setGlobalSuperAdminLoading(false);
    }
  }

  async function handleToggleMunicipalityAnnouncer() {
    if (!id) return;
    setAnnouncerLoading(true);
    try {
      if (isMunicipalityAnnouncer) {
        await api.delete(`/v2/admin/feed/revoke-announcer/${id}`);
        setIsMunicipalityAnnouncer(false);
        toast.success("Municipal Announcer role revoked");
      } else {
        await api.post('/v2/admin/feed/grant-announcer', { user_id: Number(id) });
        setIsMunicipalityAnnouncer(true);
        toast.success("Municipal Announcer role granted");
      }
    } catch {
      toast.error("Failed to update Municipal Announcer role");
    } finally {
      setAnnouncerLoading(false);
    }
  }

  async function handleImpersonate() {
    if (!id) return;
    setImpersonateLoading(true);
    try {
      const res = await adminUsers.impersonate(Number(id));
      if (res.success && res.data) {
        const data = res.data as Record<string, unknown>;
        const token = (data.access_token as string) || (data.token as string);
        const tenantSlug = (data.tenant_slug as string) || '';
        if (token) {
          // Open the new tab on the IMPERSONATED user's tenant URL — without
          // this, the new tab inherits the admin's slug from localStorage and
          // the impersonation token is rejected as a tenant_mismatch on /me.
          const targetUrl = tenantSlug
            ? `${window.location.origin}/${tenantSlug}/`
            : `${window.location.origin}/`;
          const { sendImpersonationToken } = await import('@/lib/impersonate');
          sendImpersonationToken(token, targetUrl);
          toast.success(`Impersonate successfully`);
        }
      } else {
        toast.error(res.error || "Impersonate Failed");
      }
    } catch {
      toast.error("Impersonate Failed");
    } finally {
      setImpersonateLoading(false);
    }
  }

  async function handleAdjustBalance() {
    if (!id || !balanceAmount.trim() || !balanceReason.trim()) return;
    const amount = parseFloat(balanceAmount);
    if (isNaN(amount) || amount === 0) { toast.error("Balance Invalid"); return; }
    setBalanceLoading(true);
    try {
      const res = await adminTimebanking.adjustBalance(Number(id), amount, balanceReason.trim());
      if (res.success) {
        toast.success(`Balance Adjusted`);
        setBalanceModalOpen(false);
        setBalanceAmount('');
        setBalanceReason('');
        loadUser();
      } else {
        toast.error(res.error || "Balance Failed");
      }
    } catch {
      toast.error("Balance Failed");
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
        toast.success(`Remove Badge successfully`);
        setUser((prev) =>
          prev ? { ...prev, badges: prev.badges.filter((b) => b.id !== badgeToRemove.id) } : prev
        );
      } else {
        toast.error(res.error || "Failed to remove badge");
      }
    } catch {
      toast.error("Occurred error");
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
        toast.success("Recheck Complete");
      } else {
        toast.error(res.error || "Recheck Failed");
      }
    } catch {
      toast.error("Recheck Failed");
    } finally {
      setRecheckingBadges(false);
    }
  }

  async function handleSetPassword() {
    if (!id || !newPassword.trim()) return;
    if (newPassword.length < 8) { toast.error("Password must be at least 8 characters"); return; }
    setPasswordLoading(true);
    try {
      const res = await adminUsers.setPassword(Number(id), newPassword);
      if (res.success) {
        toast.success("Password Updated");
        setPasswordModalOpen(false);
        setNewPassword('');
      } else {
        toast.error(res.error || "Password Failed");
      }
    } catch {
      toast.error("Password Failed");
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
        toast.success("Password reset email sent");
      } else {
        toast.error(res.error || "Password reset failed");
      }
    } catch {
      toast.error("Password reset failed");
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
        toast.success("Welcome email sent");
      } else {
        toast.error(res.error || "Failed to send welcome email");
      }
    } catch {
      toast.error("Failed to send welcome email");
    } finally {
      setWelcomeEmailLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label={"Loading User"} />
      </div>
    );
  }

  if (loadError || !user) {
    return (
      <div>
        <PageHeader
          title={"Edit User"}
          actions={
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
              {"Back to Users"}
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">{loadError || "User not Found"}</p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/users'))}>{"Return to List"}</Button>
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
        title={`Edit User`}
        description={`User ID & Joined`}
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
                {"Impersonate"}
              </Button>
            )}
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
              {"Back to Users"}
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
                    <p className="text-xs text-default-400 mt-0.5">{`Balance`}</p>
                  )}
                </div>
              </div>
            </CardHeader>
            <CardBody className="gap-5 p-6">
              {/* Name */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label={"First Name"} placeholder={"Enter First Name..."} value={firstName} onValueChange={setFirstName}
                  isRequired isInvalid={!!errors.first_name} errorMessage={errors.first_name} isDisabled={submitting} />
                <Input label={"Last Name"} placeholder={"Enter Last Name..."} value={lastName} onValueChange={setLastName}
                  isRequired isInvalid={!!errors.last_name} errorMessage={errors.last_name} isDisabled={submitting} />
              </div>

              {/* Email + Phone */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label={"Email"} type="email" placeholder="user@example.com" value={email} onValueChange={setEmail}
                  isRequired isInvalid={!!errors.email} errorMessage={errors.email} isDisabled={submitting} />
                <Input label={"Phone"} type="tel" placeholder="e.g. +1 555 123 4567" value={phone}
                  onValueChange={setPhone} isDisabled={submitting} />
              </div>

              {/* Role + Status */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select label={"Role"} placeholder={"Select a Role..."} selectedKeys={role ? [role] : []}
                  onSelectionChange={(keys) => setRole(Array.from(keys)[0] as string)}
                  isRequired isInvalid={!!errors.role} errorMessage={errors.role} isDisabled={submitting}>
                  <SelectItem key="member">{"Member"}</SelectItem>
                  <SelectItem key="broker">{"Broker"}</SelectItem>
                  <SelectItem key="moderator">{"Moderator"}</SelectItem>
                  <SelectItem key="newsletter_admin">{"Newsletter Admin"}</SelectItem>
                  <SelectItem key="admin">{"Admin"}</SelectItem>
                  <SelectItem key="tenant_admin">{"Tenant Admin"}</SelectItem>
                </Select>
                <Select label={"Status"} placeholder={"Select a Status..."} selectedKeys={status ? [status] : []}
                  onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
                  isRequired isInvalid={!!errors.status} errorMessage={errors.status} isDisabled={submitting}>
                  <SelectItem key="active">{"Active"}</SelectItem>
                  <SelectItem key="pending">{"Pending"}</SelectItem>
                  <SelectItem key="suspended">{"Suspended"}</SelectItem>
                  <SelectItem key="banned">{"Banned"}</SelectItem>
                </Select>
              </div>

              {/* Profile Type + Organization */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select
                  label={"Profile Type"}
                  placeholder={"Select Type..."}
                  selectedKeys={[profileType]}
                  onSelectionChange={(keys) => setProfileType(Array.from(keys)[0] as 'individual' | 'organisation')}
                  isDisabled={submitting}
                >
                  <SelectItem key="individual">{"Individual"}</SelectItem>
                  <SelectItem key="organisation">{"Organisation"}</SelectItem>
                </Select>
                {profileType === 'organisation' && (
                  <Input
                    label={"Organisation Name"}
                    placeholder={"Eg Community Centre..."}
                    value={organizationName}
                    onValueChange={setOrganizationName}
                    startContent={<Building2 size={14} className="text-default-400" />}
                    isDisabled={submitting}
                  />
                )}
              </div>

              {/* Bio */}
              <Textarea label={"Bio"} placeholder={"A Short Biography for This User..."} value={bio} onValueChange={setBio}
                minRows={3} maxRows={6} isDisabled={submitting} />

              {/* Tagline + Location */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label={"Tagline"} placeholder={"Eg Community Volunteer..."} value={tagline}
                  onValueChange={setTagline} isDisabled={submitting} />
                <Input label={"Location"} placeholder="e.g. New York, USA" value={location}
                  onValueChange={setLocation} isDisabled={submitting} />
              </div>

              {/* Submit */}
              <div className="flex justify-end gap-3 pt-2">
                <Button variant="flat" onPress={() => navigate(tenantPath('/admin/users'))} isDisabled={submitting}>
                  {"Cancel"}
                </Button>
                <Button type="submit" color="primary" startContent={!submitting ? <Save size={16} /> : undefined}
                  isLoading={submitting}>
                  {"Save Changes"}
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
                <h3 className="text-lg font-semibold text-foreground">{"Super Admin Access"}</h3>
              </div>
            </CardHeader>
            <CardBody className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-foreground">{"Tenant Super Admin"}</p>
                  <p className="text-sm text-default-500">
                    {"Grants full admin access within this tenant"}
                  </p>
                </div>
                <Switch
                  isSelected={isTenantSuperAdmin}
                  onValueChange={handleToggleTenantSuperAdmin}
                  isDisabled={tenantSuperAdminLoading || user.id === currentUser?.id}
                  color="warning"
                  aria-label={"Toggle tenant super admin"}
                />
              </div>
              {user.id === currentUser?.id && (
                <p className="text-xs text-default-400 mt-2">{"You cannot modify your own account from this panel"}</p>
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
                <h3 className="text-lg font-semibold text-danger">{"Global Super Admin"}</h3>
              </div>
            </CardHeader>
            <CardBody className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-foreground">{"Platform-wide access"}</p>
                  <p className="text-sm text-default-500">
                    {"Grants full access across all tenants on the platform"}
                  </p>
                </div>
                <Switch
                  isSelected={isGlobalSuperAdmin}
                  onValueChange={handleToggleGlobalSuperAdmin}
                  isDisabled={globalSuperAdminLoading || user.id === currentUser?.id}
                  color="danger"
                  aria-label={"Toggle global super admin"}
                />
              </div>
              {user.id === currentUser?.id && (
                <p className="text-xs text-default-400 mt-2">{"You cannot modify your own account from this panel"}</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Municipal Announcer (AG14) */}
        {isSuperAdmin && (
          <Card className="border border-indigo-400/30">
            <CardHeader className="px-6 pt-5 pb-0">
              <div className="flex items-center gap-2">
                <Landmark size={18} className="text-indigo-500" />
                <h3 className="text-lg font-semibold text-foreground">{"Municipal Announcer"}</h3>
              </div>
            </CardHeader>
            <CardBody className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-foreground">{"Official announcement channel"}</p>
                  <p className="text-sm text-default-500">
                    {"Posts by this user will display an Official badge and be pinned to the top of the feed"}
                  </p>
                </div>
                <Switch
                  isSelected={isMunicipalityAnnouncer}
                  onValueChange={handleToggleMunicipalityAnnouncer}
                  isDisabled={announcerLoading}
                  color="primary"
                  aria-label={"Toggle municipal announcer role"}
                />
              </div>
            </CardBody>
          </Card>
        )}

        {/* Password & Email Management */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center gap-2">
              <KeyRound size={18} className="text-primary" />
              <h3 className="text-lg font-semibold text-foreground">{"Account Actions"}</h3>
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
                {"Set Password"}
              </Button>
              <Button
                size="sm"
                variant="flat"
                startContent={<Mail size={14} />}
                onPress={handleSendPasswordReset}
                isLoading={resetEmailLoading}
              >
                {"Send Password Reset"}
              </Button>
              <Button
                size="sm"
                variant="flat"
                startContent={<Mail size={14} />}
                onPress={handleSendWelcomeEmail}
                isLoading={welcomeEmailLoading}
              >
                {"Resend Welcome Email"}
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
                <h3 className="text-lg font-semibold text-foreground">{"Time Credits"}</h3>
              </div>
              <Button size="sm" variant="flat" color="primary" onPress={() => setBalanceModalOpen(true)}>
                {"Adjust Balance"}
              </Button>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            <div className="flex items-center gap-4">
              <div className="text-3xl font-bold text-foreground">{user.balance ?? 0}h</div>
              <p className="text-sm text-default-500">{"Current Balance"}</p>
            </div>
          </CardBody>
        </Card>

        {/* Badges Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center justify-between w-full">
              <h3 className="text-lg font-semibold text-foreground">{"Badges"}</h3>
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={14} />}
                onPress={handleRecheckBadges}
                isLoading={recheckingBadges}
              >
                {"Recheck Badges"}
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
              <p className="text-sm text-default-400">{"No badges"}</p>
            )}
          </CardBody>
        </Card>

        {/* GDPR Consents Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center gap-2">
              <ShieldCheck size={18} className="text-success" />
              <h3 className="text-lg font-semibold text-foreground">{"GDPR Consents"}</h3>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            {consentsLoading ? (
              <Spinner size="sm" label={"Loading Consents"} />
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
                          <Chip size="sm" variant="flat" color="warning">{"Required"}</Chip>
                        )}
                      </div>
                      {consent.description && (
                        <p className="text-xs text-default-400 mt-0.5">{consent.description}</p>
                      )}
                      <p className="text-xs text-default-400 mt-1">
                        {consent.consent_given
                          ? (consent.given_at ? `Consented on` : "Consented")
                          : consent.withdrawn_at
                            ? `Withdrawn on`
                            : "Not Consented"
                        }
                        {consent.consent_version && ` (v${consent.consent_version})`}
                      </p>
                    </div>
                    <Chip
                      size="sm"
                      variant="flat"
                      color={consent.consent_given ? 'success' : 'danger'}
                    >
                      {consent.consent_given ? "Consent Given" : "Consent not given"}
                    </Chip>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-400">{"No consent records"}</p>
            )}
          </CardBody>
        </Card>

        {/* Safeguarding & Compliance Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center gap-2">
              <ShieldAlert size={18} className="text-warning" />
              <h3 className="text-lg font-semibold text-foreground">{"Safeguarding"}</h3>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            {complianceLoading ? (
              <Spinner size="sm" label={"Loading Compliance"} />
            ) : (
              <div className="flex flex-col gap-6">
                {/* Vetting Status */}
                <div>
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <ShieldCheck size={16} className="text-primary" />
                      <p className="font-medium text-foreground">{"Vetting Status"}</p>
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
                        to={tenantPath(user?.id ? `/broker/vetting?user_id=${user.id}` : '/broker/vetting')}
                        size="sm"
                        variant="flat"
                        color="primary"
                      >
                        {"Manage"}
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
                              {vr.reference_number || "No reference"}
                              {vr.expiry_date ? ` — ${`Expires`}` : ''}
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
                        <p className="text-xs text-default-400">{`More Records`}</p>
                      )}
                    </div>
                  ) : (
                    <p className="text-sm text-default-400">{"No vetting records"}</p>
                  )}
                </div>

                {/* Insurance Status */}
                <div>
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <FileCheck size={16} className="text-primary" />
                      <p className="font-medium text-foreground">{"Insurance Certificates"}</p>
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
                        to={tenantPath(user?.id ? `/broker/insurance?user_id=${user.id}` : '/broker/insurance')}
                        size="sm"
                        variant="flat"
                        color="primary"
                      >
                        {"Manage"}
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
                              {ic.provider_name || "Unknown Provider"}
                              {ic.expiry_date ? ` — ${`Expires`}` : ''}
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
                        <p className="text-xs text-default-400">{`More Certificates`}</p>
                      )}
                    </div>
                  ) : (
                    <p className="text-sm text-default-400">{"No insurance records"}</p>
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
          title={"Remove Badge"}
          message={`Are you sure you want to remove this badge from the member?`}
          confirmLabel={"Remove Badge"}
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
            {"Adjust Time Credits"}
          </ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-default-500">{`Current balance`}</p>
            <Input label={"Amount"} placeholder="e.g. 2 or -1.5"
              description={"Use negative values to deduct time credits from this member"}
              value={balanceAmount} onValueChange={setBalanceAmount}
              type="number" step="0.5" isDisabled={balanceLoading} />
            <Input label={"Reason"} placeholder="Why are you adjusting this balance?"
              value={balanceReason} onValueChange={setBalanceReason}
              isRequired isDisabled={balanceLoading} />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat"
              onPress={() => { setBalanceModalOpen(false); setBalanceAmount(''); setBalanceReason(''); }}
              isDisabled={balanceLoading}>
              {"Cancel"}
            </Button>
            <Button color="primary" onPress={handleAdjustBalance} isLoading={balanceLoading}
              isDisabled={!balanceAmount.trim() || !balanceReason.trim() || balanceLoading}>
              {"Apply Adjustment"}
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
            {"Set Password"}
          </ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-default-500">
              {`Set a new password for this user. They will be logged out of all sessions.`}
            </p>
            <Input
              label={"New Password"}
              type="password"
              placeholder={"New password..."}
              value={newPassword}
              onValueChange={setNewPassword}
              isDisabled={passwordLoading}
              description={"Password must be at least 8 characters"}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat"
              onPress={() => { setPasswordModalOpen(false); setNewPassword(''); }}
              isDisabled={passwordLoading}>
              {"Cancel"}
            </Button>
            <Button color="primary" onPress={handleSetPassword} isLoading={passwordLoading}
              isDisabled={newPassword.length < 8 || passwordLoading}>
              {"Set Password"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserEdit;
