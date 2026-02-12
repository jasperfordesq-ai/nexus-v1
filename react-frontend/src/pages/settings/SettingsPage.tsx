/**
 * Settings Page - User account settings
 *
 * Tabs:
 *  1. Profile    - name, phone, bio, location, avatar, profile type
 *  2. Notifications - email & push notification toggles
 *  3. Privacy    - visibility, search indexing, contact, GDPR
 *  4. Security   - password, 2FA, sessions, account actions
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Textarea,
  Switch,
  Avatar,
  Tabs,
  Tab,
  Select,
  SelectItem,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Chip,
  Spinner,
  useDisclosure,
} from '@heroui/react';
import {
  User,
  Bell,
  Shield,
  Save,
  Camera,
  Mail,
  Lock,
  Smartphone,
  Key,
  LogOut,
  Trash2,
  Settings,
  AlertTriangle,
  Eye,
  EyeOff,
  Phone,
  Building2,
  Search,
  MessageSquare,
  Trophy,
  CreditCard,
  Download,
  FileText,
  RefreshCw,
  Monitor,
  QrCode,
  ShieldCheck,
  ShieldOff,
  Copy,
  CheckCircle,
  Info,
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ProfileFormData {
  first_name: string;
  last_name: string;
  name: string;
  phone: string;
  tagline: string;
  bio: string;
  location: string;
  avatar: string | null;
  profile_type: 'individual' | 'organisation';
  organization_name: string;
}

interface NotificationSettings {
  email_messages: boolean;
  email_listings: boolean;
  email_digest: boolean;
  email_connections: boolean;
  email_transactions: boolean;
  email_reviews: boolean;
  email_gamification: boolean;
  push_enabled: boolean;
}

interface PrivacySettings {
  profile_visibility: 'public' | 'members' | 'connections';
  search_indexing: boolean;
  contact_permission: boolean;
}

interface SessionInfo {
  id: string;
  device: string;
  browser: string;
  ip_address: string;
  last_active: string;
  is_current: boolean;
}

interface TwoFactorSetup {
  qr_code_url: string;
  secret: string;
  backup_codes: string[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SettingsPage() {
  usePageTitle('Settings');
  const navigate = useNavigate();
  const { user, logout, refreshUser } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [activeTab, setActiveTab] = useState('profile');
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);

  // Modal states
  const passwordModal = useDisclosure();
  const deleteModal = useDisclosure();
  const logoutModal = useDisclosure();
  const twoFactorSetupModal = useDisclosure();
  const twoFactorDisableModal = useDisclosure();
  const backupCodesModal = useDisclosure();
  const gdprModal = useDisclosure();

  // Password form
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    new_password: '',
    confirm_password: '',
  });
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [isChangingPassword, setIsChangingPassword] = useState(false);

  // Delete confirmation
  const [deleteConfirmation, setDeleteConfirmation] = useState('');
  const [isDeleting, setIsDeleting] = useState(false);

  // Error state for notification settings
  const [notificationError, setNotificationError] = useState<string | null>(null);

  // Profile form
  const [profileData, setProfileData] = useState<ProfileFormData>({
    first_name: '',
    last_name: '',
    name: '',
    phone: '',
    tagline: '',
    bio: '',
    location: '',
    avatar: null,
    profile_type: 'individual',
    organization_name: '',
  });

  // Notification settings
  const [notifications, setNotifications] = useState<NotificationSettings>({
    email_messages: true,
    email_listings: true,
    email_digest: false,
    email_connections: true,
    email_transactions: true,
    email_reviews: true,
    email_gamification: false,
    push_enabled: true,
  });

  // Privacy settings
  const [privacy, setPrivacy] = useState<PrivacySettings>({
    profile_visibility: 'members',
    search_indexing: true,
    contact_permission: true,
  });
  const [isSavingPrivacy, setIsSavingPrivacy] = useState(false);

  // 2FA state
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(false);
  const [twoFactorLoading, setTwoFactorLoading] = useState(true);
  const [twoFactorSetupData, setTwoFactorSetupData] = useState<TwoFactorSetup | null>(null);
  const [twoFactorVerifyCode, setTwoFactorVerifyCode] = useState('');
  const [isVerifying2FA, setIsVerifying2FA] = useState(false);
  const [twoFactorDisablePassword, setTwoFactorDisablePassword] = useState('');
  const [isDisabling2FA, setIsDisabling2FA] = useState(false);
  const [backupCodes, setBackupCodes] = useState<string[]>([]);
  const [backupCodesRemaining, setBackupCodesRemaining] = useState(0);

  // Sessions state
  const [sessions, setSessions] = useState<SessionInfo[]>([]);
  const [sessionsLoading, setSessionsLoading] = useState(true);
  const [sessionsError, setSessionsError] = useState<string | null>(null);

  // GDPR state
  const [gdprRequestType, setGdprRequestType] = useState<string>('');
  const [isSubmittingGdpr, setIsSubmittingGdpr] = useState(false);

  // ─────────────────────────────────────────────────────────────────────────
  // Data Loading
  // ─────────────────────────────────────────────────────────────────────────

  const loadNotificationSettings = useCallback(async () => {
    try {
      setNotificationError(null);
      const response = await api.get<NotificationSettings>('/v2/users/me/notifications');
      if (response.success && response.data) {
        setNotifications((prev) => ({ ...prev, ...response.data }));
      } else {
        setNotificationError('Failed to load notification settings');
      }
    } catch (error) {
      logError('Failed to load notification settings', error);
      setNotificationError('Failed to load notification settings');
    }
  }, []);

  const loadPrivacySettings = useCallback(async () => {
    try {
      const response = await api.get<{ privacy: PrivacySettings }>('/v2/users/me/preferences');
      if (response.success && response.data?.privacy) {
        setPrivacy((prev) => ({ ...prev, ...response.data!.privacy }));
      }
    } catch (error) {
      logError('Failed to load privacy settings', error);
    }
  }, []);

  const loadTwoFactorStatus = useCallback(async () => {
    try {
      setTwoFactorLoading(true);
      const response = await api.get<{
        enabled: boolean;
        backup_codes_remaining: number;
      }>('/v2/auth/2fa/status');
      if (response.success && response.data) {
        setTwoFactorEnabled(response.data.enabled);
        setBackupCodesRemaining(response.data.backup_codes_remaining);
      }
    } catch (error) {
      logError('Failed to load 2FA status', error);
    } finally {
      setTwoFactorLoading(false);
    }
  }, []);

  const loadSessions = useCallback(async () => {
    try {
      setSessionsLoading(true);
      setSessionsError(null);
      const response = await api.get<SessionInfo[]>('/v2/users/me/sessions');
      if (response.success && response.data) {
        setSessions(Array.isArray(response.data) ? response.data : []);
      } else {
        setSessionsError('Session management coming soon');
      }
    } catch (error) {
      logError('Failed to load sessions', error);
      setSessionsError('Session management coming soon');
    } finally {
      setSessionsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (user) {
      setProfileData({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        name: user.name || '',
        phone: user.phone || '',
        tagline: user.tagline || '',
        bio: user.bio || '',
        location: user.location || '',
        avatar: user.avatar || null,
        profile_type: user.profile_type || 'individual',
        organization_name: user.organization_name || '',
      });
      setTwoFactorEnabled(user.has_2fa_enabled || false);
    }
    loadNotificationSettings();
    loadPrivacySettings();
    loadTwoFactorStatus();
    loadSessions();
  }, [user, loadNotificationSettings, loadPrivacySettings, loadTwoFactorStatus, loadSessions]);

  // ─────────────────────────────────────────────────────────────────────────
  // Save Handlers
  // ─────────────────────────────────────────────────────────────────────────

  const saveProfile = useCallback(async () => {
    try {
      setIsSaving(true);
      // Sanitize bio to prevent XSS - allow only safe HTML tags
      const sanitizedBio = DOMPurify.sanitize(profileData.bio, {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'],
      });
      const payload = {
        first_name: profileData.first_name,
        last_name: profileData.last_name,
        name: profileData.first_name && profileData.last_name
          ? `${profileData.first_name} ${profileData.last_name}`
          : profileData.name,
        phone: profileData.phone,
        tagline: profileData.tagline,
        bio: sanitizedBio,
        location: profileData.location,
        profile_type: profileData.profile_type,
        organization_name: profileData.profile_type === 'organisation' ? profileData.organization_name : '',
      };
      const response = await api.put('/v2/users/me', payload);
      if (response.success) {
        toast.success('Profile updated successfully');
        if (refreshUser) await refreshUser();
      } else {
        toast.error(response.error || 'Failed to save profile');
      }
    } catch (error) {
      logError('Failed to save profile', error);
      toast.error('Failed to save profile');
    } finally {
      setIsSaving(false);
    }
  }, [profileData, refreshUser, toast]);

  const saveNotifications = useCallback(async () => {
    try {
      setIsSaving(true);
      const response = await api.put('/v2/users/me/notifications', notifications);
      if (response.success) {
        toast.success('Notification settings saved');
      } else {
        toast.error(response.error || 'Failed to save notifications');
      }
    } catch (error) {
      logError('Failed to save notifications', error);
      toast.error('Failed to save notifications');
    } finally {
      setIsSaving(false);
    }
  }, [notifications, toast]);

  const savePrivacy = useCallback(async () => {
    try {
      setIsSavingPrivacy(true);
      const response = await api.put('/v2/users/me/preferences', { privacy });
      if (response.success) {
        toast.success('Privacy settings saved');
      } else {
        toast.error(response.error || 'Failed to save privacy settings');
      }
    } catch (error) {
      logError('Failed to save privacy settings', error);
      toast.error('Failed to save privacy settings');
    } finally {
      setIsSavingPrivacy(false);
    }
  }, [privacy, toast]);

  // ─────────────────────────────────────────────────────────────────────────
  // Auth Handlers
  // ─────────────────────────────────────────────────────────────────────────

  function handleLogout() {
    logout();
    logoutModal.onClose();
  }

  // Avatar upload handler
  async function handleAvatarUpload(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      toast.error('Invalid file type', 'Please upload an image file (JPG, PNG, or GIF)');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('File too large', 'Please upload an image smaller than 5MB');
      return;
    }

    try {
      setIsUploading(true);
      const formData = new FormData();
      formData.append('avatar', file);

      const response = await api.upload<{ avatar_url: string }>('/v2/users/me/avatar', formData);

      if (response.success && response.data) {
        setProfileData((prev) => ({ ...prev, avatar: response.data!.avatar_url }));
        if (refreshUser) await refreshUser();
        toast.success('Avatar updated', 'Your profile photo has been updated');
      } else {
        toast.error('Upload failed', 'Failed to upload avatar. Please try again.');
      }
    } catch (error) {
      logError('Failed to upload avatar', error);
      toast.error('Upload failed', 'Failed to upload avatar. Please try again.');
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  }

  // Password change handler
  async function handleChangePassword() {
    if (!passwordData.current_password || !passwordData.new_password || !passwordData.confirm_password) {
      toast.error('Missing fields', 'Please fill in all password fields');
      return;
    }

    if (passwordData.new_password.length < 8) {
      toast.error('Password too short', 'New password must be at least 8 characters');
      return;
    }

    if (passwordData.new_password !== passwordData.confirm_password) {
      toast.error('Passwords don\'t match', 'New password and confirmation must match');
      return;
    }

    try {
      setIsChangingPassword(true);
      const response = await api.post('/v2/users/me/password', {
        current_password: passwordData.current_password,
        new_password: passwordData.new_password,
      });

      if (response.success) {
        toast.success('Password changed', 'Your password has been updated successfully');
        passwordModal.onClose();
        setPasswordData({ current_password: '', new_password: '', confirm_password: '' });
      } else {
        toast.error('Password change failed', response.error || 'Failed to change password');
      }
    } catch (error) {
      logError('Failed to change password', error);
      toast.error('Password change failed', 'Current password may be incorrect');
    } finally {
      setIsChangingPassword(false);
    }
  }

  // Delete account handler
  async function handleDeleteAccount() {
    if (deleteConfirmation !== 'DELETE') {
      toast.error('Confirmation required', 'Please type DELETE to confirm');
      return;
    }

    try {
      setIsDeleting(true);
      const response = await api.delete('/v2/users/me');

      if (response.success) {
        toast.success('Account deleted', 'Your account has been permanently deleted');
        await logout();
        navigate(tenantPath('/'));
      } else {
        toast.error('Delete failed', 'Failed to delete account. Please try again.');
      }
    } catch (error) {
      logError('Failed to delete account', error);
      toast.error('Delete failed', 'Failed to delete account. Please try again.');
    } finally {
      setIsDeleting(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // 2FA Handlers
  // ─────────────────────────────────────────────────────────────────────────

  async function handleSetup2FA() {
    try {
      setTwoFactorSetupData(null);
      setTwoFactorVerifyCode('');
      twoFactorSetupModal.onOpen();

      const response = await api.post<TwoFactorSetup>('/v2/auth/2fa/setup');
      if (response.success && response.data) {
        setTwoFactorSetupData(response.data);
      } else {
        toast.error('2FA setup failed', response.error || 'Unable to start 2FA setup');
        twoFactorSetupModal.onClose();
      }
    } catch (error) {
      logError('Failed to setup 2FA', error);
      toast.error('2FA setup failed', 'Unable to start 2FA setup');
      twoFactorSetupModal.onClose();
    }
  }

  async function handleVerify2FA() {
    if (!twoFactorVerifyCode || twoFactorVerifyCode.length < 6) {
      toast.error('Invalid code', 'Please enter a valid 6-digit code');
      return;
    }

    try {
      setIsVerifying2FA(true);
      const response = await api.post<{ backup_codes: string[] }>('/v2/auth/2fa/verify', {
        code: twoFactorVerifyCode,
      });

      if (response.success) {
        setTwoFactorEnabled(true);
        if (response.data?.backup_codes) {
          setBackupCodes(response.data.backup_codes);
          setBackupCodesRemaining(response.data.backup_codes.length);
        }
        toast.success('2FA enabled', 'Two-factor authentication is now active');
        twoFactorSetupModal.onClose();
        // Show backup codes
        if (response.data?.backup_codes?.length) {
          backupCodesModal.onOpen();
        }
      } else {
        toast.error('Verification failed', response.error || 'Invalid verification code');
      }
    } catch (error) {
      logError('Failed to verify 2FA', error);
      toast.error('Verification failed', 'Please try again with a new code');
    } finally {
      setIsVerifying2FA(false);
    }
  }

  async function handleDisable2FA() {
    if (!twoFactorDisablePassword) {
      toast.error('Password required', 'Please enter your password to disable 2FA');
      return;
    }

    try {
      setIsDisabling2FA(true);
      const response = await api.post('/v2/auth/2fa/disable', {
        password: twoFactorDisablePassword,
      });

      if (response.success) {
        setTwoFactorEnabled(false);
        setBackupCodesRemaining(0);
        toast.success('2FA disabled', 'Two-factor authentication has been disabled');
        twoFactorDisableModal.onClose();
        setTwoFactorDisablePassword('');
      } else {
        toast.error('Failed to disable 2FA', response.error || 'Password may be incorrect');
      }
    } catch (error) {
      logError('Failed to disable 2FA', error);
      toast.error('Failed to disable 2FA', 'Password may be incorrect');
    } finally {
      setIsDisabling2FA(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // GDPR Handler
  // ─────────────────────────────────────────────────────────────────────────

  async function handleGdprRequest() {
    if (!gdprRequestType) return;

    try {
      setIsSubmittingGdpr(true);
      const response = await api.post('/v2/users/me/gdpr-request', {
        type: gdprRequestType,
      });

      if (response.success) {
        toast.success('Request submitted', 'Your data request has been submitted. We will contact you via email.');
        gdprModal.onClose();
        setGdprRequestType('');
      } else {
        toast.error('Request failed', response.error || 'Failed to submit GDPR request');
      }
    } catch (error) {
      logError('Failed to submit GDPR request', error);
      toast.error('Request failed', 'Failed to submit your data request. Please try again.');
    } finally {
      setIsSubmittingGdpr(false);
    }
  }

  function openGdprModal(type: string) {
    setGdprRequestType(type);
    gdprModal.onOpen();
  }

  // Copy backup codes to clipboard
  async function handleCopyBackupCodes() {
    try {
      await navigator.clipboard.writeText(backupCodes.join('\n'));
      toast.success('Copied', 'Backup codes copied to clipboard');
    } catch {
      toast.error('Copy failed', 'Unable to copy to clipboard');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Animation Variants
  // ─────────────────────────────────────────────────────────────────────────

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  // Common classNames for HeroUI inputs
  const inputClassNames = {
    input: 'bg-transparent text-theme-primary',
    inputWrapper: 'bg-theme-elevated border-theme-default',
    label: 'text-theme-muted',
  };

  const selectClassNames = {
    trigger: 'bg-theme-elevated border-theme-default',
    value: 'text-theme-primary',
    label: 'text-theme-muted',
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-3xl mx-auto space-y-6"
    >
      {/* Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Settings className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          Settings
        </h1>
        <p className="text-theme-muted mt-1">Manage your account preferences</p>
      </motion.div>

      {/* Tabs */}
      <motion.div variants={itemVariants}>
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          classNames={{
            tabList: 'bg-theme-elevated p-1 rounded-lg flex-wrap',
            cursor: 'bg-theme-hover',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
          }}
        >
          <Tab
            key="profile"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" aria-hidden="true" />
                Profile
              </span>
            }
          />
          <Tab
            key="notifications"
            title={
              <span className="flex items-center gap-2">
                <Bell className="w-4 h-4" aria-hidden="true" />
                Notifications
              </span>
            }
          />
          <Tab
            key="privacy"
            title={
              <span className="flex items-center gap-2">
                <Shield className="w-4 h-4" aria-hidden="true" />
                Privacy
              </span>
            }
          />
          <Tab
            key="security"
            title={
              <span className="flex items-center gap-2">
                <Lock className="w-4 h-4" aria-hidden="true" />
                Security
              </span>
            }
          />
        </Tabs>
      </motion.div>

      {/* Tab Content */}
      <motion.div variants={itemVariants}>
        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* PROFILE TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'profile' && (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-6">Profile Information</h2>

            {/* Avatar */}
            <div className="flex items-center gap-6 mb-8">
              <div className="relative">
                <Avatar
                  src={resolveAvatarUrl(profileData.avatar)}
                  name={profileData.first_name || profileData.name}
                  className="w-20 h-20 ring-4 ring-theme-default"
                />
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  onChange={handleAvatarUpload}
                  className="hidden"
                  aria-label="Upload profile photo"
                />
                <Button
                  isIconOnly
                  size="sm"
                  className="absolute bottom-0 right-0 rounded-full bg-indigo-500 text-white hover:bg-indigo-600 min-w-0 w-8 h-8"
                  onPress={() => fileInputRef.current?.click()}
                  isDisabled={isUploading}
                  isLoading={isUploading}
                  aria-label="Change profile photo"
                >
                  <Camera className="w-4 h-4" aria-hidden="true" />
                </Button>
              </div>
              <div>
                <p className="text-theme-primary font-medium">Profile Photo</p>
                <p className="text-theme-subtle text-sm">JPG, PNG or GIF. Max 5MB.</p>
              </div>
            </div>

            {/* Form */}
            <div className="space-y-6">
              {/* Name fields */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="First Name"
                  placeholder="Your first name"
                  value={profileData.first_name}
                  onChange={(e) => setProfileData((prev) => ({ ...prev, first_name: e.target.value }))}
                  classNames={inputClassNames}
                />
                <Input
                  label="Last Name"
                  placeholder="Your last name"
                  value={profileData.last_name}
                  onChange={(e) => setProfileData((prev) => ({ ...prev, last_name: e.target.value }))}
                  classNames={inputClassNames}
                />
              </div>

              {/* Phone */}
              <Input
                type="tel"
                label="Phone Number"
                placeholder="+353 1 234 5678"
                value={profileData.phone}
                onChange={(e) => setProfileData((prev) => ({ ...prev, phone: e.target.value }))}
                startContent={<Phone className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={inputClassNames}
              />

              {/* Profile Type */}
              <Select
                label="Profile Type"
                selectedKeys={[profileData.profile_type]}
                onSelectionChange={(keys) => {
                  const value = Array.from(keys)[0] as string;
                  if (value) {
                    setProfileData((prev) => ({
                      ...prev,
                      profile_type: value as 'individual' | 'organisation',
                    }));
                  }
                }}
                classNames={selectClassNames}
              >
                <SelectItem key="individual">Individual</SelectItem>
                <SelectItem key="organisation">Organisation</SelectItem>
              </Select>

              {/* Organisation Name (conditional) */}
              {profileData.profile_type === 'organisation' && (
                <Input
                  label="Organisation Name"
                  placeholder="Your organisation name"
                  value={profileData.organization_name}
                  onChange={(e) => setProfileData((prev) => ({ ...prev, organization_name: e.target.value }))}
                  startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={inputClassNames}
                />
              )}

              <Input
                label="Tagline"
                placeholder="A short description about yourself"
                value={profileData.tagline}
                onChange={(e) => setProfileData((prev) => ({ ...prev, tagline: e.target.value }))}
                classNames={inputClassNames}
              />

              <Textarea
                label="Bio"
                placeholder="Tell others about yourself..."
                value={profileData.bio}
                onChange={(e) => setProfileData((prev) => ({ ...prev, bio: e.target.value }))}
                minRows={4}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />

              <Input
                label="Location"
                placeholder="City, Country"
                value={profileData.location}
                onChange={(e) => setProfileData((prev) => ({ ...prev, location: e.target.value }))}
                classNames={inputClassNames}
              />

              <Button
                onPress={saveProfile}
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                isLoading={isSaving}
              >
                Save Changes
              </Button>
            </div>
          </GlassCard>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* NOTIFICATIONS TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'notifications' && (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-6">Notification Preferences</h2>

            {notificationError ? (
              <div className="text-center py-8">
                <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
                <p className="text-theme-muted mb-4">{notificationError}</p>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={loadNotificationSettings}
                >
                  Try Again
                </Button>
              </div>
            ) : (
              <div className="space-y-6">
                {/* Messages & Communication */}
                <div className="space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Mail className="w-4 h-4" aria-hidden="true" />
                    Messages & Communication
                  </h3>

                  <SettingToggle
                    label="New Messages"
                    description="Get notified when you receive a new message"
                    checked={notifications.email_messages}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_messages: checked }))}
                  />

                  <SettingToggle
                    label="Connection Requests"
                    description="Connection requests and updates"
                    checked={notifications.email_connections}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_connections: checked }))}
                  />
                </div>

                {/* Activity & Listings */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <CreditCard className="w-4 h-4" aria-hidden="true" />
                    Activity & Listings
                  </h3>

                  <SettingToggle
                    label="Listing Activity"
                    description="Updates about your listings (new responses, etc.)"
                    checked={notifications.email_listings}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_listings: checked }))}
                  />

                  <SettingToggle
                    label="Credit Transactions"
                    description="Notifications for credit transactions"
                    checked={notifications.email_transactions}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_transactions: checked }))}
                  />

                  <SettingToggle
                    label="New Reviews"
                    description="New reviews received on your profile or listings"
                    checked={notifications.email_reviews}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_reviews: checked }))}
                  />
                </div>

                {/* Community & Achievements */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Trophy className="w-4 h-4" aria-hidden="true" />
                    Community & Achievements
                  </h3>

                  <SettingToggle
                    label="Achievement Milestones"
                    description="Badge unlocks, level ups, and achievement notifications"
                    checked={notifications.email_gamification}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_gamification: checked }))}
                  />

                  <SettingToggle
                    label="Weekly Digest"
                    description="A weekly summary of community activity"
                    checked={notifications.email_digest}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_digest: checked }))}
                  />
                </div>

                {/* Push Notifications */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Smartphone className="w-4 h-4" aria-hidden="true" />
                    Push Notifications
                  </h3>

                  <SettingToggle
                    label="Enable Push Notifications"
                    description="Receive real-time notifications on your device"
                    checked={notifications.push_enabled}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, push_enabled: checked }))}
                  />
                </div>

                <Button
                  onPress={saveNotifications}
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                  isLoading={isSaving}
                >
                  Save Preferences
                </Button>
              </div>
            )}
          </GlassCard>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* PRIVACY TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'privacy' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-6">Privacy Settings</h2>

              <div className="space-y-6">
                {/* Profile Visibility */}
                <div className="space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Eye className="w-4 h-4" aria-hidden="true" />
                    Profile Visibility
                  </h3>

                  <Select
                    label="Who can see your profile"
                    selectedKeys={[privacy.profile_visibility]}
                    onSelectionChange={(keys) => {
                      const value = Array.from(keys)[0] as string;
                      if (value) {
                        setPrivacy((prev) => ({
                          ...prev,
                          profile_visibility: value as 'public' | 'members' | 'connections',
                        }));
                      }
                    }}
                    classNames={selectClassNames}
                  >
                    <SelectItem key="public">Public - Anyone can view</SelectItem>
                    <SelectItem key="members">Members Only - Community members</SelectItem>
                    <SelectItem key="connections">Connections Only - Your connections</SelectItem>
                  </Select>
                </div>

                {/* Search & Discovery */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Search className="w-4 h-4" aria-hidden="true" />
                    Search & Discovery
                  </h3>

                  <SettingToggle
                    label="Search Engine Indexing"
                    description="Allow search engines to index your profile"
                    checked={privacy.search_indexing}
                    onChange={(checked) => setPrivacy((prev) => ({ ...prev, search_indexing: checked }))}
                  />
                </div>

                {/* Contact Preferences */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <MessageSquare className="w-4 h-4" aria-hidden="true" />
                    Contact Preferences
                  </h3>

                  <SettingToggle
                    label="Allow Contact"
                    description="Allow other members to contact me"
                    checked={privacy.contact_permission}
                    onChange={(checked) => setPrivacy((prev) => ({ ...prev, contact_permission: checked }))}
                  />
                </div>

                <Button
                  onPress={savePrivacy}
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                  isLoading={isSavingPrivacy}
                >
                  Save Privacy Settings
                </Button>
              </div>
            </GlassCard>

            {/* GDPR Section */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                <FileText className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                Data & Privacy Rights
              </h2>
              <p className="text-theme-subtle text-sm mb-6">
                Under the General Data Protection Regulation (GDPR), you have the right to access, export, and request
                deletion of your personal data. These requests are processed within 30 days.
              </p>

              <div className="space-y-3">
                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
                  startContent={
                    <div className="p-2 rounded-lg bg-blue-500/20">
                      <Download className="w-4 h-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                    </div>
                  }
                  onPress={() => openGdprModal('download')}
                >
                  <div className="text-left">
                    <p className="font-medium">Download My Data</p>
                    <p className="text-sm text-theme-subtle font-normal">Get a copy of all your personal data</p>
                  </div>
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
                  startContent={
                    <div className="p-2 rounded-lg bg-emerald-500/20">
                      <RefreshCw className="w-4 h-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    </div>
                  }
                  onPress={() => openGdprModal('portability')}
                >
                  <div className="text-left">
                    <p className="font-medium">Data Portability Request</p>
                    <p className="text-sm text-theme-subtle font-normal">Export data in a machine-readable format</p>
                  </div>
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-red-500/10 text-theme-primary h-auto py-3 px-4"
                  startContent={
                    <div className="p-2 rounded-lg bg-red-500/20">
                      <Trash2 className="w-4 h-4 text-red-600 dark:text-red-400" aria-hidden="true" />
                    </div>
                  }
                  onPress={() => openGdprModal('deletion')}
                >
                  <div className="text-left">
                    <p className="font-medium text-red-600 dark:text-red-400">Request Data Deletion</p>
                    <p className="text-sm text-theme-subtle font-normal">Request permanent deletion of your data</p>
                  </div>
                </Button>
              </div>

              <div className="mt-4 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
                <p className="text-sm text-theme-muted flex items-start gap-2">
                  <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
                  Your rights include access to your data, rectification, erasure, restriction of processing,
                  data portability, and the right to object. Contact our Data Protection Officer for any concerns.
                </p>
              </div>
            </GlassCard>
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* SECURITY TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'security' && (
          <div className="space-y-6">
            {/* Password & 2FA */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-6">Security Settings</h2>

              <div className="space-y-4">
                {/* Change Password */}
                <Button
                  variant="light"
                  className="w-full flex items-center justify-between p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover h-auto text-left"
                  onPress={passwordModal.onOpen}
                >
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-indigo-500/20">
                      <Lock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-medium text-theme-primary">Change Password</p>
                      <p className="text-sm text-theme-subtle">Update your account password</p>
                    </div>
                  </div>
                </Button>

                {/* Two-Factor Authentication */}
                <div className="w-full p-4 rounded-lg bg-theme-elevated text-left">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-lg bg-emerald-500/20">
                        <Key className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                      </div>
                      <div>
                        <p className="font-medium text-theme-primary">Two-Factor Authentication</p>
                        <p className="text-sm text-theme-subtle">
                          {twoFactorLoading ? (
                            'Checking status...'
                          ) : twoFactorEnabled ? (
                            <span className="flex items-center gap-1">
                              <ShieldCheck className="w-3 h-3 text-emerald-500" aria-hidden="true" />
                              Enabled
                              {backupCodesRemaining > 0 && (
                                <span className="text-theme-subtle">
                                  {' '}-- {backupCodesRemaining} backup codes remaining
                                </span>
                              )}
                            </span>
                          ) : (
                            <span className="flex items-center gap-1">
                              <ShieldOff className="w-3 h-3 text-amber-500" aria-hidden="true" />
                              Not enabled
                            </span>
                          )}
                        </p>
                      </div>
                    </div>
                    {!twoFactorLoading && (
                      <div>
                        {twoFactorEnabled ? (
                          <Button
                            size="sm"
                            variant="flat"
                            className="bg-red-500/10 text-red-500"
                            onPress={twoFactorDisableModal.onOpen}
                          >
                            Disable
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                            onPress={handleSetup2FA}
                          >
                            Enable
                          </Button>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </GlassCard>

            {/* Active Sessions */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                <Monitor className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                Active Sessions
              </h2>

              {sessionsLoading ? (
                <div className="flex items-center justify-center py-8">
                  <Spinner size="lg" />
                </div>
              ) : sessionsError ? (
                <div className="text-center py-6">
                  <Monitor className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                  <p className="text-theme-muted">{sessionsError}</p>
                </div>
              ) : sessions.length > 0 ? (
                <div className="space-y-3">
                  {sessions.map((session) => (
                    <div
                      key={session.id}
                      className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated"
                    >
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-theme-hover">
                          <Monitor className="w-4 h-4 text-theme-muted" aria-hidden="true" />
                        </div>
                        <div>
                          <p className="text-sm font-medium text-theme-primary flex items-center gap-2">
                            {session.browser} on {session.device}
                            {session.is_current && (
                              <Chip size="sm" color="success" variant="flat">Current</Chip>
                            )}
                          </p>
                          <p className="text-xs text-theme-subtle">
                            {session.ip_address} -- Last active: {new Date(session.last_active).toLocaleDateString()}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-6">
                  <Monitor className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                  <p className="text-theme-muted">Session management coming soon</p>
                </div>
              )}
            </GlassCard>

            {/* Account Actions */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4">Account Actions</h2>

              <div className="space-y-3">
                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary"
                  startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                  onPress={logoutModal.onOpen}
                >
                  Log Out
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-red-500/10 text-red-400"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                  onPress={deleteModal.onOpen}
                >
                  Delete Account
                </Button>
              </div>
            </GlassCard>
          </div>
        )}
      </motion.div>

      {/* ═══════════════════════════════════════════════════════════════════════ */}
      {/* MODALS                                                                */}
      {/* ═══════════════════════════════════════════════════════════════════════ */}

      {/* Change Password Modal */}
      <Modal
        isOpen={passwordModal.isOpen}
        onClose={passwordModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Change Password</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                type={showCurrentPassword ? 'text' : 'password'}
                label="Current Password"
                value={passwordData.current_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, current_password: e.target.value }))}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0"
                    onPress={() => setShowCurrentPassword(!showCurrentPassword)}
                    aria-label={showCurrentPassword ? 'Hide current password' : 'Show current password'}
                  >
                    {showCurrentPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </Button>
                }
                classNames={inputClassNames}
              />
              <Input
                type={showNewPassword ? 'text' : 'password'}
                label="New Password"
                value={passwordData.new_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, new_password: e.target.value }))}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0"
                    onPress={() => setShowNewPassword(!showNewPassword)}
                    aria-label={showNewPassword ? 'Hide new password' : 'Show new password'}
                  >
                    {showNewPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </Button>
                }
                classNames={inputClassNames}
              />
              <Input
                type="password"
                label="Confirm New Password"
                value={passwordData.confirm_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, confirm_password: e.target.value }))}
                classNames={inputClassNames}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={passwordModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleChangePassword}
              isLoading={isChangingPassword}
            >
              Change Password
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Logout Confirmation Modal */}
      <Modal
        isOpen={logoutModal.isOpen}
        onClose={logoutModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Log Out</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to log out of your account?
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={logoutModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleLogout}
            >
              Log Out
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Account Modal */}
      <Modal
        isOpen={deleteModal.isOpen}
        onClose={deleteModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            Delete Account
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-red-600 dark:text-red-400 font-medium">Warning: This action cannot be undone</p>
                <p className="text-theme-muted text-sm mt-1">
                  All your data, including listings, messages, and transaction history will be permanently deleted.
                </p>
              </div>
              <div>
                <p className="text-theme-muted mb-2">
                  Type <span className="font-mono text-red-600 dark:text-red-400">DELETE</span> to confirm:
                </p>
                <Input
                  value={deleteConfirmation}
                  onChange={(e) => setDeleteConfirmation(e.target.value)}
                  placeholder="DELETE"
                  classNames={{
                    input: 'bg-transparent text-theme-primary font-mono',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                />
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={deleteModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDeleteAccount}
              isLoading={isDeleting}
              isDisabled={deleteConfirmation !== 'DELETE'}
            >
              Delete My Account
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* 2FA Setup Modal */}
      <Modal
        isOpen={twoFactorSetupModal.isOpen}
        onClose={twoFactorSetupModal.onClose}
        size="lg"
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <QrCode className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            Set Up Two-Factor Authentication
          </ModalHeader>
          <ModalBody>
            {!twoFactorSetupData ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : (
              <div className="space-y-6">
                <div className="text-center">
                  <p className="text-theme-muted mb-4">
                    Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                  </p>
                  <div className="inline-block p-4 bg-white rounded-xl">
                    <img
                      src={twoFactorSetupData.qr_code_url}
                      alt="2FA QR Code"
                      className="w-48 h-48"
                    />
                  </div>
                </div>

                <div className="p-3 rounded-lg bg-theme-elevated">
                  <p className="text-xs text-theme-subtle mb-1">Manual entry key:</p>
                  <p className="font-mono text-sm text-theme-primary break-all select-all">
                    {twoFactorSetupData.secret}
                  </p>
                </div>

                <Input
                  label="Verification Code"
                  placeholder="Enter 6-digit code"
                  value={twoFactorVerifyCode}
                  onChange={(e) => setTwoFactorVerifyCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  maxLength={6}
                  classNames={inputClassNames}
                  description="Enter the 6-digit code from your authenticator app"
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={twoFactorSetupModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
              onPress={handleVerify2FA}
              isLoading={isVerifying2FA}
              isDisabled={!twoFactorSetupData || twoFactorVerifyCode.length < 6}
            >
              Verify & Enable
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* 2FA Disable Modal */}
      <Modal
        isOpen={twoFactorDisableModal.isOpen}
        onClose={twoFactorDisableModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            Disable Two-Factor Authentication
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20">
                <p className="text-amber-600 dark:text-amber-400 font-medium">This will reduce your account security</p>
                <p className="text-theme-muted text-sm mt-1">
                  Without 2FA, your account will only be protected by your password.
                </p>
              </div>
              <Input
                type="password"
                label="Confirm Your Password"
                value={twoFactorDisablePassword}
                onChange={(e) => setTwoFactorDisablePassword(e.target.value)}
                classNames={inputClassNames}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={twoFactorDisableModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDisable2FA}
              isLoading={isDisabling2FA}
              isDisabled={!twoFactorDisablePassword}
            >
              Disable 2FA
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Backup Codes Modal */}
      <Modal
        isOpen={backupCodesModal.isOpen}
        onClose={backupCodesModal.onClose}
        size="lg"
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            Your Backup Codes
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20">
                <p className="text-amber-600 dark:text-amber-400 font-medium">Save these codes in a safe place</p>
                <p className="text-theme-muted text-sm mt-1">
                  Each backup code can only be used once. If you lose access to your authenticator app,
                  you can use these codes to sign in.
                </p>
              </div>

              <div className="grid grid-cols-2 gap-2 p-4 rounded-lg bg-theme-elevated">
                {backupCodes.map((code, index) => (
                  <p key={index} className="font-mono text-sm text-theme-primary text-center py-1">
                    {code}
                  </p>
                ))}
              </div>

              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-primary"
                startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
                onPress={handleCopyBackupCodes}
              >
                Copy All Codes
              </Button>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={backupCodesModal.onClose}
            >
              I&apos;ve Saved My Codes
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* GDPR Request Modal */}
      <Modal
        isOpen={gdprModal.isOpen}
        onClose={gdprModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {gdprRequestType === 'download' && 'Download My Data'}
            {gdprRequestType === 'portability' && 'Data Portability Request'}
            {gdprRequestType === 'deletion' && 'Request Data Deletion'}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              {gdprRequestType === 'deletion' && (
                <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                  <p className="text-red-600 dark:text-red-400 font-medium">This request is irreversible</p>
                  <p className="text-theme-muted text-sm mt-1">
                    Once your data is deleted, it cannot be recovered. Your account and all associated
                    data will be permanently removed.
                  </p>
                </div>
              )}

              {gdprRequestType === 'download' && (
                <p className="text-theme-muted">
                  We will prepare a downloadable archive of all your personal data, including your profile,
                  listings, messages, and transaction history. You will receive an email with a download link
                  within 30 days.
                </p>
              )}

              {gdprRequestType === 'portability' && (
                <p className="text-theme-muted">
                  We will export your data in a structured, commonly used, and machine-readable format (JSON/CSV).
                  This allows you to transfer your data to another service. You will receive an email within 30 days.
                </p>
              )}

              <div className="p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
                <p className="text-sm text-theme-muted flex items-start gap-2">
                  <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
                  A confirmation email will be sent to your registered email address.
                </p>
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={gdprModal.onClose}>
              Cancel
            </Button>
            <Button
              className={
                gdprRequestType === 'deletion'
                  ? 'bg-red-500 text-white'
                  : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
              }
              onPress={handleGdprRequest}
              isLoading={isSubmittingGdpr}
            >
              {gdprRequestType === 'deletion' ? 'Confirm Deletion Request' : 'Submit Request'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

interface SettingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}

function SettingToggle({ label, description, checked, onChange }: SettingToggleProps) {
  return (
    <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated">
      <div>
        <p className="font-medium text-theme-primary">{label}</p>
        <p className="text-sm text-theme-subtle">{description}</p>
      </div>
      <Switch
        aria-label={label}
        isSelected={checked}
        onValueChange={onChange}
        classNames={{
          wrapper: 'group-data-[selected=true]:bg-indigo-500',
        }}
      />
    </div>
  );
}

export default SettingsPage;
