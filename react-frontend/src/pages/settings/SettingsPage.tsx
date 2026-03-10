// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Settings Page - User account settings
 *
 * Tabs:
 *  1. Profile    - name, phone, bio, location, avatar, profile type
 *  2. Notifications - email & push notification toggles
 *  3. Privacy    - visibility, search indexing, contact, GDPR
 *  4. Security   - password, 2FA, sessions, account actions
 *  5. Skills     - skill selector
 *  6. Availability - availability grid
 *  7. Linked     - sub-accounts / OAuth connections
 */

import { useState, useEffect, useCallback } from 'react';
import React from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Tabs,
  Tab,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  User,
  Bell,
  Shield,
  Lock,
  Settings,
  Info,
  Sparkles,
  Calendar,
  Users,
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { AvailabilityGrid } from '@/components/availability/AvailabilityGrid';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { useTranslation } from 'react-i18next';

// Tab components
import { ProfileTab } from './tabs/ProfileTab';
import type { ProfileFormData } from './tabs/ProfileTab';
import { NotificationsTab } from './tabs/NotificationsTab';
import type { NotificationSettings } from './tabs/NotificationsTab';
import { PrivacyTab } from './tabs/PrivacyTab';
import type { PrivacySettings, UserInsuranceCert } from './tabs/PrivacyTab';
import { SecurityTab } from './tabs/SecurityTab';
import type { SessionInfo, TwoFactorSetup } from './tabs/SecurityTab';
import { SkillsTab } from './tabs/SkillsTab';
import { LinkedAccountsTab } from './tabs/LinkedAccountsTab';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SettingsPage() {
  usePageTitle('Settings');
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { user, logout, refreshUser } = useAuth();
  const { tenantPath, tenant, hasFeature } = useTenant();
  useTranslation('settings');
  const toast = useToast();

  const validTabs = ['profile', 'notifications', 'privacy', 'security', 'skills', 'availability', 'linked-accounts'];
  const initialTab = validTabs.includes(searchParams.get('tab') || '') ? searchParams.get('tab')! : 'profile';
  const [activeTab, setActiveTab] = useState(initialTab);
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
    latitude: undefined,
    longitude: undefined,
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
    email_gamification_digest: true,
    email_gamification_milestones: true,
    email_org_payments: true,
    email_org_transfers: true,
    email_org_membership: true,
    email_org_admin: true,
    push_enabled: true,
  });

  // Match digest frequency & preferences
  const [matchDigestFrequency, setMatchDigestFrequency] = useState<string>('fortnightly');
  const [notifyHotMatches, setNotifyHotMatches] = useState(true);
  const [notifyMutualMatches, setNotifyMutualMatches] = useState(true);

  // Marketing consent
  const [marketingConsent, setMarketingConsent] = useState(false);
  const [marketingConsentLoading, setMarketingConsentLoading] = useState(false);

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

  // Insurance certificates (user-facing)
  const [insuranceCerts, setInsuranceCerts] = useState<UserInsuranceCert[]>([]);
  const [insuranceLoading, setInsuranceLoading] = useState(false);
  const [insuranceUploading, setInsuranceUploading] = useState(false);
  const [insuranceType, setInsuranceType] = useState('public_liability');

  // ─────────────────────────────────────────────────────────────────────────
  // Data Loading
  // ─────────────────────────────────────────────────────────────────────────

  const loadNotificationSettings = useCallback(async () => {
    try {
      setNotificationError(null);
      const response = await api.get<NotificationSettings>('/v2/users/me/notifications');
      if (response.success && response.data) {
        setNotifications((prev: NotificationSettings) => ({ ...prev, ...response.data }));
      } else {
        setNotificationError('Failed to load notification settings');
      }
    } catch (error) {
      logError('Failed to load notification settings', error);
      setNotificationError('Failed to load notification settings');
    }
  }, []);

  const loadMatchPreferences = useCallback(async () => {
    try {
      const response = await api.get<{ notification_frequency: string; notify_hot_matches: boolean; notify_mutual_matches: boolean }>('/v2/users/me/match-preferences');
      if (response.success && response.data) {
        setMatchDigestFrequency(response.data.notification_frequency || 'fortnightly');
        setNotifyHotMatches(response.data.notify_hot_matches ?? true);
        setNotifyMutualMatches(response.data.notify_mutual_matches ?? true);
      }
    } catch (error) {
      logError('Failed to load match preferences', error);
    }
  }, []);

  const loadMarketingConsent = useCallback(async () => {
    try {
      const response = await api.get<Array<{ consent_type_slug: string; given: boolean }>>('/v2/users/me/consent');
      if (response.success && Array.isArray(response.data)) {
        const marketing = response.data.find((c) => c.consent_type_slug === 'marketing_email');
        if (marketing) {
          setMarketingConsent(marketing.given);
        }
      }
    } catch (error) {
      logError('Failed to load marketing consent', error);
    }
  }, []);

  const loadPrivacySettings = useCallback(async () => {
    try {
      const response = await api.get<{ privacy: { privacy_profile: string; privacy_search: boolean; privacy_contact: boolean } }>('/v2/users/me/preferences');
      if (response.success && response.data?.privacy) {
        const p = response.data.privacy;
        setPrivacy({
          profile_visibility: (p.privacy_profile as 'public' | 'members' | 'connections') || 'members',
          search_indexing: p.privacy_search ?? true,
          contact_permission: p.privacy_contact ?? true,
        });
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
        latitude: user.latitude ?? undefined,
        longitude: user.longitude ?? undefined,
        avatar: user.avatar || null,
        profile_type: user.profile_type || 'individual',
        organization_name: user.organization_name || '',
      });
      setTwoFactorEnabled(user.has_2fa_enabled || false);
    }
    loadNotificationSettings();
    loadMatchPreferences();
    loadMarketingConsent();
    loadPrivacySettings();
    loadTwoFactorStatus();
    loadSessions();
  }, [user, loadNotificationSettings, loadMatchPreferences, loadMarketingConsent, loadPrivacySettings, loadTwoFactorStatus, loadSessions]);

  // Load insurance certificates when insurance is enabled
  const loadInsuranceCerts = useCallback(async () => {
    if (!tenant?.compliance?.insurance_enabled) return;
    setInsuranceLoading(true);
    try {
      const res = await api.get<UserInsuranceCert[]>('/v2/users/me/insurance');
      if (res.success && Array.isArray(res.data)) {
        setInsuranceCerts(res.data);
      }
    } catch {
      // Insurance table may not exist
    } finally {
      setInsuranceLoading(false);
    }
  }, [tenant?.compliance?.insurance_enabled]);

  useEffect(() => {
    loadInsuranceCerts();
  }, [loadInsuranceCerts]);

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
        latitude: profileData.latitude,
        longitude: profileData.longitude,
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
      const [notifResponse, matchResponse] = await Promise.all([
        api.put('/v2/users/me/notifications', notifications),
        api.put('/v2/users/me/match-preferences', {
          notification_frequency: matchDigestFrequency,
          notify_hot_matches: notifyHotMatches,
          notify_mutual_matches: notifyMutualMatches,
        }),
      ]);
      if (notifResponse.success && matchResponse.success) {
        toast.success('Notification settings saved');
      } else {
        toast.error(notifResponse.error || matchResponse.error || 'Failed to save notifications');
      }
    } catch (error) {
      logError('Failed to save notifications', error);
      toast.error('Failed to save notifications');
    } finally {
      setIsSaving(false);
    }
  }, [notifications, matchDigestFrequency, notifyHotMatches, notifyMutualMatches, toast]);

  const savePrivacy = useCallback(async () => {
    try {
      setIsSavingPrivacy(true);
      const response = await api.put('/v2/users/me/preferences', {
        privacy: {
          privacy_profile: privacy.profile_visibility,
          privacy_search: privacy.search_indexing,
          privacy_contact: privacy.contact_permission,
        },
      });
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
        setProfileData((prev: ProfileFormData) => ({ ...prev, avatar: response.data!.avatar_url }));
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
  // Marketing Consent Handler
  // ─────────────────────────────────────────────────────────────────────────

  async function handleMarketingConsentToggle(checked: boolean) {
    try {
      setMarketingConsentLoading(true);
      const response = await api.put('/v2/users/me/consent', {
        slug: 'marketing_email',
        given: checked,
      });
      if (response.success) {
        setMarketingConsent(checked);
        toast.success(checked ? 'Subscribed to marketing emails' : 'Unsubscribed from marketing emails');
      } else {
        toast.error('Failed to update marketing consent');
      }
    } catch (error) {
      logError('Failed to update marketing consent', error);
      toast.error('Failed to update marketing consent');
    } finally {
      setMarketingConsentLoading(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // GDPR Handler
  // ─────────────────────────────────────────────────────────────────────────

  async function handleGdprRequest() {
    if (!gdprRequestType) return;

    const typeMap: Record<string, string> = {
      download: 'access',
      portability: 'portability',
      deletion: 'erasure',
      rectification: 'rectification',
      restriction: 'restriction',
      objection: 'objection',
    };

    try {
      setIsSubmittingGdpr(true);
      const response = await api.post('/v2/users/me/gdpr-request', {
        type: typeMap[gdprRequestType] || gdprRequestType,
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

  // Insurance certificate upload handler
  async function handleInsuranceUpload(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;

    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
      toast.error('Invalid file', 'Only PDF, JPG, and PNG files are accepted');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      toast.error('File too large', 'File must be under 10MB');
      return;
    }

    setInsuranceUploading(true);
    try {
      const formData = new FormData();
      formData.append('certificate_file', file);
      formData.append('insurance_type', insuranceType);

      const res = await api.upload('/v2/users/me/insurance', formData);

      if (res.success) {
        toast.success('Certificate uploaded', 'Your insurance certificate has been submitted for review');
        loadInsuranceCerts();
        setInsuranceType('public_liability');
      } else {
        toast.error('Upload failed', res.error || 'Failed to upload insurance certificate');
      }
    } catch {
      toast.error('Upload failed', 'Failed to upload insurance certificate');
    } finally {
      setInsuranceUploading(false);
      event.target.value = '';
    }
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
          onSelectionChange={(key: React.Key) => setActiveTab(key as string)}
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
          <Tab
            key="skills"
            title={
              <span className="flex items-center gap-2">
                <Sparkles className="w-4 h-4" aria-hidden="true" />
                Skills
              </span>
            }
          />
          <Tab
            key="availability"
            title={
              <span className="flex items-center gap-2">
                <Calendar className="w-4 h-4" aria-hidden="true" />
                Availability
              </span>
            }
          />
          <Tab
            key="linked-accounts"
            title={
              <span className="flex items-center gap-2">
                <Users className="w-4 h-4" aria-hidden="true" />
                Linked
              </span>
            }
          />
        </Tabs>
      </motion.div>

      {/* Tab Content */}
      <motion.div variants={itemVariants}>
        {activeTab === 'profile' && (
          <ProfileTab
            profileData={profileData}
            isSaving={isSaving}
            isUploading={isUploading}
            onProfileDataChange={setProfileData}
            onSave={saveProfile}
            onAvatarUpload={handleAvatarUpload}
          />
        )}

        {activeTab === 'notifications' && (
          <NotificationsTab
            notifications={notifications}
            notificationError={notificationError}
            isSaving={isSaving}
            matchDigestFrequency={matchDigestFrequency}
            notifyHotMatches={notifyHotMatches}
            notifyMutualMatches={notifyMutualMatches}
            marketingConsent={marketingConsent}
            marketingConsentLoading={marketingConsentLoading}
            isOrganisation={profileData.profile_type === 'organisation'}
            onNotificationsChange={setNotifications}
            onMatchDigestFrequencyChange={setMatchDigestFrequency}
            onNotifyHotMatchesChange={setNotifyHotMatches}
            onNotifyMutualMatchesChange={setNotifyMutualMatches}
            onMarketingConsentToggle={handleMarketingConsentToggle}
            onSave={saveNotifications}
            onRetry={loadNotificationSettings}
          />
        )}

        {activeTab === 'privacy' && (
          <PrivacyTab
            privacy={privacy}
            isSavingPrivacy={isSavingPrivacy}
            insuranceCerts={insuranceCerts}
            insuranceLoading={insuranceLoading}
            insuranceUploading={insuranceUploading}
            insuranceType={insuranceType}
            insuranceEnabled={!!tenant?.compliance?.insurance_enabled}
            federationEnabled={hasFeature('federation')}
            onPrivacyChange={setPrivacy}
            onSavePrivacy={savePrivacy}
            onInsuranceUpload={handleInsuranceUpload}
            onInsuranceTypeChange={setInsuranceType}
            onOpenGdprModal={openGdprModal}
          />
        )}

        {activeTab === 'security' && (
          <SecurityTab
            twoFactorEnabled={twoFactorEnabled}
            twoFactorLoading={twoFactorLoading}
            twoFactorSetupData={twoFactorSetupData}
            twoFactorVerifyCode={twoFactorVerifyCode}
            isVerifying2FA={isVerifying2FA}
            twoFactorDisablePassword={twoFactorDisablePassword}
            isDisabling2FA={isDisabling2FA}
            backupCodes={backupCodes}
            backupCodesRemaining={backupCodesRemaining}
            sessions={sessions}
            sessionsLoading={sessionsLoading}
            sessionsError={sessionsError}
            passwordData={passwordData}
            showCurrentPassword={showCurrentPassword}
            showNewPassword={showNewPassword}
            isChangingPassword={isChangingPassword}
            deleteConfirmation={deleteConfirmation}
            isDeleting={isDeleting}
            passwordModalOpen={passwordModal.isOpen}
            passwordModalOnClose={passwordModal.onClose}
            passwordModalOnOpen={passwordModal.onOpen}
            logoutModalOpen={logoutModal.isOpen}
            logoutModalOnClose={logoutModal.onClose}
            logoutModalOnOpen={logoutModal.onOpen}
            deleteModalOpen={deleteModal.isOpen}
            deleteModalOnClose={deleteModal.onClose}
            deleteModalOnOpen={deleteModal.onOpen}
            twoFactorSetupModalOpen={twoFactorSetupModal.isOpen}
            twoFactorSetupModalOnClose={twoFactorSetupModal.onClose}
            twoFactorDisableModalOpen={twoFactorDisableModal.isOpen}
            twoFactorDisableModalOnClose={twoFactorDisableModal.onClose}
            twoFactorDisableModalOnOpen={twoFactorDisableModal.onOpen}
            backupCodesModalOpen={backupCodesModal.isOpen}
            backupCodesModalOnClose={backupCodesModal.onClose}
            onPasswordDataChange={setPasswordData}
            onShowCurrentPasswordToggle={() => setShowCurrentPassword((v: boolean) => !v)}
            onShowNewPasswordToggle={() => setShowNewPassword((v: boolean) => !v)}
            onChangePassword={handleChangePassword}
            onDeleteConfirmationChange={setDeleteConfirmation}
            onDeleteAccount={handleDeleteAccount}
            onLogout={handleLogout}
            onSetup2FA={handleSetup2FA}
            onVerify2FA={handleVerify2FA}
            onDisable2FA={handleDisable2FA}
            onTwoFactorVerifyCodeChange={setTwoFactorVerifyCode}
            onTwoFactorDisablePasswordChange={setTwoFactorDisablePassword}
            onCopyBackupCodes={handleCopyBackupCodes}
          />
        )}

        {activeTab === 'skills' && <SkillsTab />}

        {activeTab === 'availability' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <AvailabilityGrid editable />
            </GlassCard>
          </div>
        )}

        {activeTab === 'linked-accounts' && <LinkedAccountsTab />}
      </motion.div>

      {/* GDPR Request Modal (triggered from PrivacyTab via openGdprModal) */}
      <Modal
        isOpen={gdprModal.isOpen}
        onClose={gdprModal.onClose}
        classNames={{
          base: 'bg-content1 border border-theme-default',
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
            {gdprRequestType === 'rectification' && 'Data Rectification'}
            {gdprRequestType === 'restriction' && 'Restriction of Processing'}
            {gdprRequestType === 'objection' && 'Right to Object'}
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

              {gdprRequestType === 'rectification' && (
                <p className="text-theme-muted">
                  Request correction of any inaccurate or incomplete personal data we hold about you.
                  We will review your request and update the records within 30 days.
                </p>
              )}

              {gdprRequestType === 'restriction' && (
                <p className="text-theme-muted">
                  Request that we restrict the processing of your personal data. While restricted, we will
                  store but not actively process your data. We will respond within 30 days.
                </p>
              )}

              {gdprRequestType === 'objection' && (
                <p className="text-theme-muted">
                  Object to the processing of your personal data for specific purposes, such as
                  direct marketing or profiling. We will review your objection within 30 days.
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

export default SettingsPage;
