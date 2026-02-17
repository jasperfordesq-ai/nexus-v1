/**
 * Admin User Edit
 * Edit user details, role, status, profile info, and manage badges.
 * Parity: PHP Admin\UserController::edit()
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
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
import { ArrowLeft, Save, Trash2, LogIn, ShieldAlert, Coins } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { adminUsers, adminTimebanking } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { AdminUserDetail, AdminBadge, UpdateUserPayload } from '../../api/types';

export function UserEdit() {
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = (currentUser as Record<string, unknown> | null)?.is_super_admin === true
    || (currentUser?.role as string) === 'super_admin';

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

  // Super admin toggle
  const [isSuperAdminUser, setIsSuperAdminUser] = useState(false);
  const [superAdminLoading, setSuperAdminLoading] = useState(false);

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
        setIsSuperAdminUser(userData.is_super_admin || false);
      } else {
        setLoadError(res.error || 'Failed to load user');
      }
    } catch {
      setLoadError('An unexpected error occurred while loading user data');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { loadUser(); }, [loadUser]);

  function validate(): boolean {
    const newErrors: Record<string, string> = {};
    if (!firstName.trim()) newErrors.first_name = 'First name is required';
    if (!lastName.trim()) newErrors.last_name = 'Last name is required';
    if (!email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = 'Please enter a valid email address';
    }
    if (!role) newErrors.role = 'Role is required';
    if (!status) newErrors.status = 'Status is required';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!validate() || !id) return;
    setSubmitting(true);
    try {
      const payload: UpdateUserPayload & { phone?: string } = {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
        phone: phone.trim(),
        role,
        status,
        bio: bio.trim(),
        tagline: tagline.trim(),
        location: location.trim(),
      };
      const res = await adminUsers.update(Number(id), payload);
      if (res.success) {
        toast.success('User updated successfully');
        loadUser();
      } else {
        toast.error(res.error || 'Failed to update user');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleToggleSuperAdmin() {
    if (!id || !user) return;
    setSuperAdminLoading(true);
    try {
      const res = await adminUsers.setSuperAdmin(Number(id), !isSuperAdminUser);
      if (res.success) {
        setIsSuperAdminUser(!isSuperAdminUser);
        toast.success(`Super admin ${!isSuperAdminUser ? 'granted' : 'revoked'} successfully`);
      } else {
        toast.error(res.error || 'Failed to update super admin status');
      }
    } catch {
      toast.error('Failed to update super admin status');
    } finally {
      setSuperAdminLoading(false);
    }
  }

  async function handleImpersonate() {
    if (!id) return;
    setImpersonateLoading(true);
    try {
      const res = await adminUsers.impersonate(Number(id));
      if (res.success && res.data) {
        const token = (res.data as Record<string, unknown>).access_token as string;
        if (token) {
          const url = `${window.location.origin}?impersonate_token=${token}`;
          window.open(url, '_blank');
          toast.success(`Logged in as ${user?.name}`);
        }
      } else {
        toast.error(res.error || 'Failed to impersonate user');
      }
    } catch {
      toast.error('Failed to impersonate user');
    } finally {
      setImpersonateLoading(false);
    }
  }

  async function handleAdjustBalance() {
    if (!id || !balanceAmount.trim() || !balanceReason.trim()) return;
    const amount = parseFloat(balanceAmount);
    if (isNaN(amount) || amount === 0) { toast.error('Please enter a valid non-zero amount'); return; }
    setBalanceLoading(true);
    try {
      const res = await adminTimebanking.adjustBalance(Number(id), amount, balanceReason.trim());
      if (res.success) {
        toast.success(`Balance adjusted by ${amount > 0 ? '+' : ''}${amount}h`);
        setBalanceModalOpen(false);
        setBalanceAmount('');
        setBalanceReason('');
        loadUser();
      } else {
        toast.error(res.error || 'Failed to adjust balance');
      }
    } catch {
      toast.error('Failed to adjust balance');
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
        toast.success(`Badge "${badgeToRemove.name}" removed`);
        setUser((prev) =>
          prev ? { ...prev, badges: prev.badges.filter((b) => b.id !== badgeToRemove.id) } : prev
        );
      } else {
        toast.error(res.error || 'Failed to remove badge');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setRemovingBadge(false);
      setBadgeToRemove(null);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label="Loading user..." />
      </div>
    );
  }

  if (loadError || !user) {
    return (
      <div>
        <PageHeader
          title="Edit User"
          actions={
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
              Back to Users
            </Button>
          }
        />
        <Card className="max-w-2xl">
          <CardBody className="p-6">
            <p className="text-center text-danger">{loadError || 'User not found'}</p>
            <div className="mt-4 flex justify-center">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/users'))}>Return to User List</Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  const canImpersonate = isSuperAdmin && !user.is_super_admin && user.id !== currentUser?.id;

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
                Impersonate
              </Button>
            )}
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
              Back to Users
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
                <Avatar src={user.avatar_url || user.avatar || undefined} name={user.name} size="lg" />
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
                <Input label="First Name" placeholder="Enter first name" value={firstName} onValueChange={setFirstName}
                  isRequired isInvalid={!!errors.first_name} errorMessage={errors.first_name} isDisabled={submitting} />
                <Input label="Last Name" placeholder="Enter last name" value={lastName} onValueChange={setLastName}
                  isRequired isInvalid={!!errors.last_name} errorMessage={errors.last_name} isDisabled={submitting} />
              </div>

              {/* Email + Phone */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label="Email" type="email" placeholder="user@example.com" value={email} onValueChange={setEmail}
                  isRequired isInvalid={!!errors.email} errorMessage={errors.email} isDisabled={submitting} />
                <Input label="Phone" type="tel" placeholder="e.g. +353 87 123 4567" value={phone}
                  onValueChange={setPhone} isDisabled={submitting} />
              </div>

              {/* Role + Status */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select label="Role" placeholder="Select a role" selectedKeys={role ? [role] : []}
                  onSelectionChange={(keys) => setRole(Array.from(keys)[0] as string)}
                  isRequired isInvalid={!!errors.role} errorMessage={errors.role} isDisabled={submitting}>
                  <SelectItem key="member">Member</SelectItem>
                  <SelectItem key="moderator">Moderator</SelectItem>
                  <SelectItem key="newsletter_admin">Newsletter Admin</SelectItem>
                  <SelectItem key="admin">Admin</SelectItem>
                  <SelectItem key="tenant_admin">Tenant Admin</SelectItem>
                </Select>
                <Select label="Status" placeholder="Select a status" selectedKeys={status ? [status] : []}
                  onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
                  isRequired isInvalid={!!errors.status} errorMessage={errors.status} isDisabled={submitting}>
                  <SelectItem key="active">Active</SelectItem>
                  <SelectItem key="pending">Pending</SelectItem>
                  <SelectItem key="suspended">Suspended</SelectItem>
                  <SelectItem key="banned">Banned</SelectItem>
                </Select>
              </div>

              {/* Bio */}
              <Textarea label="Bio" placeholder="A short biography for this user" value={bio} onValueChange={setBio}
                minRows={3} maxRows={6} isDisabled={submitting} />

              {/* Tagline + Location */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input label="Tagline" placeholder="e.g. Community volunteer" value={tagline}
                  onValueChange={setTagline} isDisabled={submitting} />
                <Input label="Location" placeholder="e.g. Dublin, Ireland" value={location}
                  onValueChange={setLocation} isDisabled={submitting} />
              </div>

              {/* Submit */}
              <div className="flex justify-end gap-3 pt-2">
                <Button variant="flat" onPress={() => navigate(tenantPath('/admin/users'))} isDisabled={submitting}>
                  Cancel
                </Button>
                <Button type="submit" color="primary" startContent={!submitting ? <Save size={16} /> : undefined}
                  isLoading={submitting}>
                  Save Changes
                </Button>
              </div>
            </CardBody>
          </Card>
        </form>

        {/* Super Admin Flag (only visible to super admins) */}
        {isSuperAdmin && (
          <Card>
            <CardHeader className="px-6 pt-5 pb-0">
              <div className="flex items-center gap-2">
                <ShieldAlert size={18} className="text-warning" />
                <h3 className="text-lg font-semibold text-foreground">Super Admin Access</h3>
              </div>
            </CardHeader>
            <CardBody className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-foreground">Platform-Wide Super Admin</p>
                  <p className="text-sm text-default-500">
                    Super admins can access and manage all tenants. Use with extreme caution.
                  </p>
                </div>
                <Switch
                  isSelected={isSuperAdminUser}
                  onValueChange={handleToggleSuperAdmin}
                  isDisabled={superAdminLoading || user.id === currentUser?.id}
                  color="warning"
                  aria-label="Toggle super admin status"
                />
              </div>
              {user.id === currentUser?.id && (
                <p className="text-xs text-default-400 mt-2">You cannot modify your own super admin status.</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Wallet Balance */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <div className="flex items-center justify-between w-full">
              <div className="flex items-center gap-2">
                <Coins size={18} className="text-primary" />
                <h3 className="text-lg font-semibold text-foreground">Time Credits</h3>
              </div>
              <Button size="sm" variant="flat" color="primary" onPress={() => setBalanceModalOpen(true)}>
                Adjust Balance
              </Button>
            </div>
          </CardHeader>
          <CardBody className="p-6">
            <div className="flex items-center gap-4">
              <div className="text-3xl font-bold text-foreground">{user.balance ?? 0}h</div>
              <p className="text-sm text-default-500">current balance</p>
            </div>
          </CardBody>
        </Card>

        {/* Badges Section */}
        <Card>
          <CardHeader className="px-6 pt-5 pb-0">
            <h3 className="text-lg font-semibold text-foreground">Badges</h3>
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
                      <button type="button" onClick={() => setBadgeToRemove(badge)}
                        className="ml-1 rounded-full p-0.5 text-default-400 transition-colors hover:bg-danger-100 hover:text-danger"
                        aria-label={`Remove badge: ${badge.name}`}>
                        <Trash2 size={12} />
                      </button>
                    }
                  >
                    {badge.name}
                  </Chip>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-400">This user has no badges yet.</p>
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
          title="Remove Badge"
          message={`Are you sure you want to remove the "${badgeToRemove.name}" badge from ${user.name}?`}
          confirmLabel="Remove Badge"
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
            Adjust Time Credits
          </ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-default-500">Current balance: <strong>{user.balance ?? 0}h</strong></p>
            <Input label="Amount" placeholder="e.g. 2 or -1.5"
              description="Use negative values to deduct credits"
              value={balanceAmount} onValueChange={setBalanceAmount}
              type="number" step="0.5" isDisabled={balanceLoading} />
            <Input label="Reason" placeholder="Why are you adjusting this balance?"
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
              Apply Adjustment
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserEdit;
