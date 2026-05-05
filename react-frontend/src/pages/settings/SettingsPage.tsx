// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Settings Page - User account settings
 *
 * Tabs:
 *  1. Profile       - name, phone, bio, location, avatar, profile type
 *  2. Notifications - email & push notification toggles
 *  3. Privacy       - visibility, search indexing, contact, GDPR
 *  4. Security      - password, 2FA, sessions, account actions
 *  5. Skills        - skill tags
 *  6. Availability  - weekly availability grid
 *  7. Linked Accounts - sub-account management
 */

import { useState, useEffect, useCallback } from 'react';
import type { Key } from 'react';
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
import User from 'lucide-react/icons/user';
import Bell from 'lucide-react/icons/bell';
import Shield from 'lucide-react/icons/shield';
import Lock from 'lucide-react/icons/lock';
import Settings from 'lucide-react/icons/settings';
import Sparkles from 'lucide-react/icons/sparkles';
import Calendar from 'lucide-react/icons/calendar';
import Users from 'lucide-react/icons/users';
import Info from 'lucide-react/icons/info';
import Languages from 'lucide-react/icons/languages';
import { sanitizeRichText } from '@/lib/sanitize';
import { GlassCard } from '@/components/ui';
import { AvailabilityGrid } from '@/components/availability/AvailabilityGrid';
import { AppearanceSettings } from '@/components/settings/AppearanceSettings';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api, tokenManager } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';
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
import { ConnectedAccountsTab } from './tabs/ConnectedAccountsTab';
import { SafeguardingTab } from './tabs/SafeguardingTab';
import { TranslationTab } from './tabs/TranslationTab';

const SETTINGS_TABS = [
  'profile',
  'notifications',
  'privacy',
  'security',
  'skills',
  'availability',
  'linked-accounts',
  'connected-accounts',
  'safeguarding',
  'translation',
] as const;

type SettingsTabKey = (typeof SETTINGS_TABS)[number];

function isSettingsTabKey(value: string | null): value is SettingsTabKey {
  return !!value && SETTINGS_TABS.includes(value as SettingsTabKey);
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SettingsPage() {
  const { t } = useTranslation('settings');
  usePageTitle(t('page_meta.title'));
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { user, logout, refreshUser } = useAuth();
  const { tenantPath, tenant, hasFeature } = useTenant();
  const toast = useToast();
  const tabParam = searchParams.get('tab');
  const initialTab: SettingsTabKey = isSettingsTabKey(tabParam) ? tabParam : 'profile';
  const [activeTab, setActiveTab] = useState<SettingsTabKey>(initialTab);
  const [pendingTab, setPendingTab] = useState<SettingsTabKey | null>(null);
  const [isDirty, setIsDirty] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);

  // Warn on browser close/refresh when form is dirty
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (isDirty) {
        e.preventDefault();
      }
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty]);

  // Modal states
  const passwordModal = useDisclosure();
  const deleteModal = useDisclosure();
  const logoutModal = useDisclosure();
  const twoFactorSetupModal = useDisclosure();
  const twoFactorDisableModal = useDisclosure();
  const backupCodesModal = useDisclosure();
  const gdprModal = useDisclosure();
  const marketingConsentModal = useDisclosure();
  const unsavedChangesModal = useDisclosure();

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

  // Identity verification status — lock name fields if verified
  interface IdentityStatusResponse {
    has_id_verified_badge: boolean;
  }
  const [isIdVerified, setIsIdVerified] = useState(false);
  useEffect(() => {
    if (!user?.id) return;
    let cancelled = false;
    api.get<IdentityStatusResponse>('/v2/identity/status').then((res) => {
      if (!cancelled && res?.data?.has_id_verified_badge) setIsIdVerified(true);
    }).catch(() => {});
    return () => { cancelled = true; };
  }, [user?.id]);

  // Profile form
  const [profileData, setProfileData] = useState<ProfileFormData>({
    first_name: '',
    last_name: '',
    name: '',
    date_of_birth: '',
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
    push_campaigns_opted_in: false,
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

  const applyTabSelection = useCallback((tab: SettingsTabKey) => {
    setActiveTab(tab);
    setSearchParams((current) => {
      const next = new URLSearchParams(current);
      if (tab === 'profile') {
        next.delete('tab');
      } else {
        next.set('tab', tab);
      }
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  const handleTabSelection = useCallback((key: Key) => {
    const nextTab = String(key);
    if (!isSettingsTabKey(nextTab) || nextTab === activeTab) return;
    if (isDirty) {
      setPendingTab(nextTab);
      unsavedChangesModal.onOpen();
      return;
    }
    applyTabSelection(nextTab);
  }, [activeTab, applyTabSelection, isDirty, unsavedChangesModal]);

  const discardChangesAndSwitchTab = useCallback(() => {
    if (pendingTab) {
      setIsDirty(false);
      applyTabSelection(pendingTab);
    }
    setPendingTab(null);
    unsavedChangesModal.onClose();
  }, [applyTabSelection, pendingTab, unsavedChangesModal]);

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
        setNotificationError(t('notification_load_failed'));
      }
    } catch (error) {
      logError('Failed to load notification settings', error);
      setNotificationError(t('notification_load_failed'));
    }
  }, [t]);

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
        setSessionsError(t('sessions_coming_soon'));
      }
    } catch (error) {
      logError('Failed to load sessions', error);
      setSessionsError(t('sessions_coming_soon'));
    } finally {
      setSessionsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    if (user) {
      setProfileData({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        name: user.name || '',
        date_of_birth: user.date_of_birth || '',
        phone: user.phone || '',
        tagline: user.tagline || '',
        bio: user.bio || '',
        location: user.location || '',
        latitude: user.latitude ?? undefined,
        longitude: user.longitude ?? undefined,
        avatar: user.avatar_url || user.avatar || null,
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

  // Re-fetch privacy settings when the privacy tab becomes active
  useEffect(() => {
    if (activeTab === 'privacy') {
      loadPrivacySettings();
    }
  }, [activeTab, loadPrivacySettings]);

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
      // Sanitize bio with the unified rich-text profile (XSS guard).
      const sanitizedBio = sanitizeRichText(profileData.bio);
      const payload: Record<string, unknown> = {
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
      // Let unverified members clear DOB; verified profiles are locked server-side.
      if (!isIdVerified) {
        payload.date_of_birth = profileData.date_of_birth || null;
      }
      const response = await api.put('/v2/users/me', payload);
      if (response.success) {
        setIsDirty(false);
        toast.success(t('toasts.profile_updated'));
        if (refreshUser) await refreshUser();
      } else {
        toast.error(response.error || t('toasts.profile_save_failed'));
      }
    } catch (error) {
      logError('Failed to save profile', error);
      toast.error(t('toasts.profile_save_failed'));
    } finally {
      setIsSaving(false);
    }
  }, [isIdVerified, profileData, refreshUser, toast, t]);

  const saveNotifications = useCallback(async () => {
    try {
      setIsSaving(true);
      // Save general notification settings and match digest frequency in parallel
      const [notifResponse, matchResponse] = await Promise.all([
        api.put('/v2/users/me/notifications', notifications),
        api.put('/v2/users/me/match-preferences', {
          notification_frequency: matchDigestFrequency,
          notify_hot_matches: notifyHotMatches,
          notify_mutual_matches: notifyMutualMatches,
        }),
      ]);
      if (notifResponse.success && matchResponse.success) {
        setIsDirty(false);
        toast.success(t('toasts.notifications_saved'));
      } else {
        toast.error(notifResponse.error || matchResponse.error || t('toasts.notifications_save_failed'));
      }
    } catch (error) {
      logError('Failed to save notifications', error);
      toast.error(t('toasts.notifications_save_failed'));
    } finally {
      setIsSaving(false);
    }
  }, [notifications, matchDigestFrequency, notifyHotMatches, notifyMutualMatches, toast, t]);

  const savePrivacy = useCallback(async () => {
    try {
      setIsSavingPrivacy(true);
      // Map React field names to PHP field names
      const response = await api.put('/v2/users/me/preferences', {
        privacy: {
          privacy_profile: privacy.profile_visibility,
          privacy_search: privacy.search_indexing,
          privacy_contact: privacy.contact_permission,
        },
      });
      if (response.success) {
        setIsDirty(false);
        toast.success(t('toasts.privacy_saved'));
      } else {
        toast.error(response.error || t('toasts.privacy_save_failed'));
      }
    } catch (error) {
      logError('Failed to save privacy settings', error);
      toast.error(t('toasts.privacy_save_failed'));
    } finally {
      setIsSavingPrivacy(false);
    }
  }, [privacy, toast, t]);

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

    // Reset input so the same file can be re-selected
    event.target.value = '';

    if (!file.type.startsWith('image/')) {
      toast.error(t('toasts.invalid_file_type'), t('toasts.invalid_file_type_desc'));
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error(t('toasts.file_too_large'), t('toasts.avatar_file_too_large_desc'));
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
        toast.success(t('toasts.avatar_updated'), t('toasts.avatar_updated_desc'));
      } else {
        toast.error(t('toasts.upload_failed'), t('toasts.avatar_upload_failed_desc'));
      }
    } catch (error) {
      logError('Failed to upload avatar', error);
      toast.error(t('toasts.upload_failed'), t('toasts.avatar_upload_failed_desc'));
    } finally {
      setIsUploading(false);
    }
  }

  // Password change handler
  async function handleChangePassword() {
    if (!passwordData.current_password || !passwordData.new_password || !passwordData.confirm_password) {
      toast.error(t('toasts.missing_fields'), t('toasts.missing_password_fields'));
      return;
    }

    if (passwordData.new_password.length < 12) {
      toast.error(t('toasts.password_too_short'), t('toasts.password_too_short_desc'));
      return;
    }

    if (passwordData.new_password !== passwordData.confirm_password) {
      toast.error(t('toasts.passwords_dont_match'), t('toasts.passwords_dont_match_desc'));
      return;
    }

    try {
      setIsChangingPassword(true);
      const response = await api.post('/v2/users/me/password', {
        current_password: passwordData.current_password,
        new_password: passwordData.new_password,
      });

      if (response.success) {
        toast.success(t('toasts.password_changed'), t('toasts.password_changed_desc'));
        passwordModal.onClose();
        setPasswordData({ current_password: '', new_password: '', confirm_password: '' });
      } else {
        toast.error(t('toasts.password_change_failed'), response.error || t('toasts.password_change_failed'));
      }
    } catch (error) {
      logError('Failed to change password', error);
      toast.error(t('toasts.password_change_failed'), t('toasts.password_incorrect_desc'));
    } finally {
      setIsChangingPassword(false);
    }
  }

  // Delete account handler
  async function handleDeleteAccount() {
    if (deleteConfirmation !== 'DELETE') {
      toast.error(t('toasts.confirmation_required'), t('toasts.type_delete_to_confirm'));
      return;
    }

    try {
      setIsDeleting(true);
      const response = await api.delete('/v2/users/me');

      if (response.success) {
        toast.success(t('toasts.account_deleted'), t('toasts.account_deleted_desc'));
        await logout();
        navigate(tenantPath('/'));
      } else {
        toast.error(t('toasts.delete_failed'), t('toasts.delete_failed_desc'));
      }
    } catch (error) {
      logError('Failed to delete account', error);
      toast.error(t('toasts.delete_failed'), t('toasts.delete_failed_desc'));
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
        toast.error(t('toasts.twofa_setup_failed'), response.error || t('toasts.twofa_setup_failed_desc'));
        twoFactorSetupModal.onClose();
      }
    } catch (error) {
      logError('Failed to setup 2FA', error);
      toast.error(t('toasts.twofa_setup_failed'), t('toasts.twofa_setup_failed_desc'));
      twoFactorSetupModal.onClose();
    }
  }

  async function handleVerify2FA() {
    if (!twoFactorVerifyCode || twoFactorVerifyCode.length < 6) {
      toast.error(t('toasts.invalid_code'), t('toasts.invalid_code_desc'));
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
        toast.success(t('toasts.twofa_enabled'), t('toasts.twofa_enabled_desc'));
        twoFactorSetupModal.onClose();
        // Refresh 2FA status from server to ensure UI is in sync
        loadTwoFactorStatus();
        // Show backup codes
        if (response.data?.backup_codes?.length) {
          backupCodesModal.onOpen();
        }
      } else {
        toast.error(t('toasts.verification_failed'), response.error || t('toasts.verification_failed'));
      }
    } catch (error) {
      logError('Failed to verify 2FA', error);
      toast.error(t('toasts.verification_failed'), t('toasts.verification_failed_desc'));
    } finally {
      setIsVerifying2FA(false);
    }
  }

  async function handleDisable2FA() {
    if (!twoFactorDisablePassword) {
      toast.error(t('toasts.password_required'), t('toasts.password_required_desc'));
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
        // Clear the trusted device token since 2FA is now disabled
        tokenManager.clearTrustedDeviceToken();
        toast.success(t('toasts.twofa_disabled'), t('toasts.twofa_disabled_desc'));
        twoFactorDisableModal.onClose();
        setTwoFactorDisablePassword('');
      } else {
        toast.error(t('toasts.twofa_disable_failed'), response.error || t('toasts.twofa_disable_failed_desc'));
      }
    } catch (error) {
      logError('Failed to disable 2FA', error);
      toast.error(t('toasts.twofa_disable_failed'), t('toasts.twofa_disable_failed_desc'));
    } finally {
      setIsDisabling2FA(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Marketing Consent Handler
  // ─────────────────────────────────────────────────────────────────────────

  async function applyMarketingConsent(checked: boolean) {
    try {
      setMarketingConsentLoading(true);
      const response = await api.put('/v2/users/me/consent', {
        slug: 'marketing_email',
        given: checked,
      });
      if (response.success) {
        setMarketingConsent(checked);
        toast.success(checked ? t('toasts.marketing_subscribed') : t('toasts.marketing_unsubscribed'));
      } else {
        toast.error(t('toasts.marketing_consent_failed'));
      }
    } catch (error) {
      logError('Failed to update marketing consent', error);
      toast.error(t('toasts.marketing_consent_failed'));
    } finally {
      setMarketingConsentLoading(false);
    }
  }

  function handleMarketingConsentToggle(checked: boolean) {
    if (checked) {
      // Turning ON — show confirmation dialog first
      marketingConsentModal.onOpen();
    } else {
      // Turning OFF — apply immediately, no confirmation needed
      applyMarketingConsent(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // GDPR Handler
  // ─────────────────────────────────────────────────────────────────────────

  async function handleGdprRequest() {
    if (!gdprRequestType) return;

    // Map UI types to GDPR API types
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
        toast.success(t('toasts.request_submitted'), t('toasts.gdpr_request_submitted_desc'));
        gdprModal.onClose();
        setGdprRequestType('');
      } else {
        toast.error(t('toasts.request_failed'), response.error || t('toasts.gdpr_request_failed_desc'));
      }
    } catch (error) {
      logError('Failed to submit GDPR request', error);
      toast.error(t('toasts.request_failed'), t('toasts.gdpr_request_failed_desc'));
    } finally {
      setIsSubmittingGdpr(false);
    }
  }

  function openGdprModal(type: string) {
    setGdprRequestType(type);
    gdprModal.onOpen();
  }

  // Insurance certificate upload handler — #7: uses shared API client instead of raw fetch
  async function handleInsuranceUpload(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;

    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
      toast.error(t('toasts.invalid_file'), t('toasts.invalid_insurance_file_desc'));
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      toast.error(t('toasts.file_too_large'), t('toasts.insurance_file_too_large_desc'));
      return;
    }

    setInsuranceUploading(true);
    try {
      const formData = new FormData();
      formData.append('certificate_file', file);
      formData.append('insurance_type', insuranceType);

      const res = await api.upload('/v2/users/me/insurance', formData);

      if (res.success) {
        toast.success(t('toasts.certificate_uploaded'), t('toasts.certificate_uploaded_desc'));
        loadInsuranceCerts();
        setInsuranceType('public_liability');
      } else {
        toast.error(t('toasts.upload_failed'), res.error || t('toasts.insurance_upload_failed_desc'));
      }
    } catch {
      toast.error(t('toasts.upload_failed'), t('toasts.insurance_upload_failed_desc'));
    } finally {
      setInsuranceUploading(false);
      event.target.value = '';
    }
  }

  // Copy backup codes to clipboard
  async function handleCopyBackupCodes() {
    try {
      await navigator.clipboard.writeText(backupCodes.join('\n'));
      toast.success(t('toasts.copied'), t('toasts.backup_codes_copied_desc'));
    } catch {
      toast.error(t('toasts.copy_failed'), t('toasts.copy_failed_desc'));
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
      <PageMeta title={t('page_meta.title')} noIndex />
      {/* Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Settings className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          {t("header.title")}
        </h1>
        <p className="text-theme-muted mt-1">{t("header.subtitle")}</p>
      </motion.div>

      {/* Tabs */}
      <motion.div
        variants={itemVariants}
        className="relative after:pointer-events-none after:absolute after:inset-y-0 after:right-0 after:w-8 after:bg-gradient-to-l after:from-[var(--background)] after:to-transparent sm:after:hidden"
      >
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={handleTabSelection}
          classNames={{
            tabList: 'bg-theme-elevated p-1 rounded-lg overflow-x-auto flex-nowrap',
            cursor: 'bg-theme-hover',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
          }}
        >
          <Tab
            key="profile"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" aria-hidden="true" />
                {t("tabs.profile")}
              </span>
            }
          />
          <Tab
            key="notifications"
            title={
              <span className="flex items-center gap-2">
                <Bell className="w-4 h-4" aria-hidden="true" />
                {t("tabs.notifications")}
              </span>
            }
          />
          <Tab
            key="privacy"
            title={
              <span className="flex items-center gap-2">
                <Shield className="w-4 h-4" aria-hidden="true" />
                {t("tabs.privacy")}
              </span>
            }
          />
          <Tab
            key="security"
            title={
              <span className="flex items-center gap-2">
                <Lock className="w-4 h-4" aria-hidden="true" />
                {t("tabs.security")}
              </span>
            }
          />
          <Tab
            key="skills"
            title={
              <span className="flex items-center gap-2">
                <Sparkles className="w-4 h-4" aria-hidden="true" />
                {t("tabs.skills")}
              </span>
            }
          />
          <Tab
            key="availability"
            title={
              <span className="flex items-center gap-2">
                <Calendar className="w-4 h-4" aria-hidden="true" />
                {t("tabs.availability")}
              </span>
            }
          />
          <Tab
            key="linked-accounts"
            title={
              <span className="flex items-center gap-2">
                <Users className="w-4 h-4" aria-hidden="true" />
                {t("tabs.linked")}
              </span>
            }
          />
          <Tab
            key="connected-accounts"
            title={
              <span className="flex items-center gap-2">
                <Lock className="w-4 h-4" aria-hidden="true" />
                {t("tabs.connected_accounts")}
              </span>
            }
          />
          <Tab
            key="safeguarding"
            title={
              <span className="flex items-center gap-2">
                <Shield className="w-4 h-4" aria-hidden="true" />
                {t("tabs.safeguarding")}
              </span>
            }
          />
          <Tab
            key="translation"
            title={
              <span className="flex items-center gap-2">
                <Languages className="w-4 h-4" aria-hidden="true" />
                {t("tabs.translation")}
              </span>
            }
          />
        </Tabs>
      </motion.div>

      {/* Tab Content */}
      <motion.div variants={itemVariants}>
        {/* PROFILE TAB */}
        {activeTab === 'profile' && (
          <>
            <ProfileTab
              profileData={profileData}
              isSaving={isSaving}
              isUploading={isUploading}
              isIdVerified={isIdVerified}
              isDirty={isDirty}
              onProfileDataChange={(updater) => {
                setProfileData(updater);
                setIsDirty(true);
              }}
              onSave={saveProfile}
              onAvatarUpload={handleAvatarUpload}
            />
            {/* Appearance Preferences (not included in ProfileTab component) */}
            <GlassCard className="p-6 mt-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-6 flex items-center gap-2">
                <Sparkles className="w-5 h-5 text-indigo-500" aria-hidden="true" />
                {t('appearance_prefs.title')}
              </h2>
              <AppearanceSettings />
            </GlassCard>
          </>
        )}

        {/* NOTIFICATIONS TAB */}
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
            onNotificationsChange={(updater) => { setNotifications(updater); setIsDirty(true); }}
            onMatchDigestFrequencyChange={(v) => { setMatchDigestFrequency(v); setIsDirty(true); }}
            onNotifyHotMatchesChange={(v) => { setNotifyHotMatches(v); setIsDirty(true); }}
            onNotifyMutualMatchesChange={(v) => { setNotifyMutualMatches(v); setIsDirty(true); }}
            onMarketingConsentToggle={handleMarketingConsentToggle}
            onSave={saveNotifications}
            onRetry={loadNotificationSettings}
          />
        )}

        {/* PRIVACY TAB */}
        {activeTab === 'privacy' && (
          <PrivacyTab
            privacy={privacy}
            isSavingPrivacy={isSavingPrivacy}
            insuranceCerts={insuranceCerts}
            insuranceLoading={insuranceLoading}
            insuranceUploading={insuranceUploading}
            insuranceType={insuranceType}
            insuranceEnabled={!!tenant?.compliance?.insurance_enabled}
            federationEnabled={!!hasFeature?.('federation')}
            onPrivacyChange={(updater) => { setPrivacy(updater); setIsDirty(true); }}
            onSavePrivacy={savePrivacy}
            onInsuranceUpload={handleInsuranceUpload}
            onInsuranceTypeChange={setInsuranceType}
            onOpenGdprModal={openGdprModal}
          />
        )}

        {/* SECURITY TAB */}
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
            onShowCurrentPasswordToggle={() => setShowCurrentPassword(!showCurrentPassword)}
            onShowNewPasswordToggle={() => setShowNewPassword(!showNewPassword)}
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

        {/* SKILLS TAB */}
        {activeTab === 'skills' && <SkillsTab />}

        {/* AVAILABILITY TAB (no separate tab component) */}
        {activeTab === 'availability' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <AvailabilityGrid editable />
            </GlassCard>
          </div>
        )}

        {/* LINKED ACCOUNTS TAB */}
        {activeTab === 'linked-accounts' && <LinkedAccountsTab />}

        {/* CONNECTED ACCOUNTS TAB (OAuth — SOC13) */}
        {activeTab === 'connected-accounts' && <ConnectedAccountsTab />}

        {/* SAFEGUARDING TAB */}
        {activeTab === 'safeguarding' && <SafeguardingTab />}
        {activeTab === 'translation' && <TranslationTab />}
      </motion.div>

      {/* Unsaved Changes Confirmation Modal */}
      <Modal
        isOpen={unsavedChangesModal.isOpen}
        onClose={() => {
          setPendingTab(null);
          unsavedChangesModal.onClose();
        }}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('unsaved_changes.title')}
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {t('unsaved_changes.message')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              onPress={() => {
                setPendingTab(null);
                unsavedChangesModal.onClose();
              }}
            >
              {t('unsaved_changes.stay')}
            </Button>
            <Button color="danger" onPress={discardChangesAndSwitchTab}>
              {t('unsaved_changes.leave')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Marketing Consent Confirmation Modal */}
      <Modal
        isOpen={marketingConsentModal.isOpen}
        onClose={marketingConsentModal.onClose}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('marketing_consent_confirm_title')}
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {t('marketing_consent_confirm_body')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={marketingConsentModal.onClose}>
              {t('cancel')}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={() => { marketingConsentModal.onClose(); applyMarketingConsent(true); }}
              isLoading={marketingConsentLoading}
            >
              {t('marketing_consent_confirm_button')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* GDPR Request Modal (triggered from PrivacyTab) */}
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
            {gdprRequestType === 'download' && t('gdpr.download_title')}
            {gdprRequestType === 'portability' && t('gdpr.portability_title')}
            {gdprRequestType === 'deletion' && t('gdpr.deletion_title')}
            {gdprRequestType === 'rectification' && t('gdpr.rectification_title')}
            {gdprRequestType === 'restriction' && t('gdpr.restriction_title')}
            {gdprRequestType === 'objection' && t('gdpr.objection_title')}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              {gdprRequestType === 'deletion' && (
                <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                  <p className="text-red-600 dark:text-red-400 font-medium">{t("gdpr.deletion_irreversible")}</p>
                  <p className="text-theme-muted text-sm mt-1">
                    {t("gdpr.deletion_modal_desc")}
                  </p>
                </div>
              )}

              {gdprRequestType === 'download' && (
                <p className="text-theme-muted">
                  {t("gdpr.download_modal_desc")}
                </p>
              )}

              {gdprRequestType === 'portability' && (
                <p className="text-theme-muted">
                  {t("gdpr.portability_modal_desc")}
                </p>
              )}

              {gdprRequestType === 'rectification' && (
                <p className="text-theme-muted">
                  {t("gdpr.rectification_modal_desc")}
                </p>
              )}

              {gdprRequestType === 'restriction' && (
                <p className="text-theme-muted">
                  {t("gdpr.restriction_modal_desc")}
                </p>
              )}

              {gdprRequestType === 'objection' && (
                <p className="text-theme-muted">
                  {t("gdpr.objection_modal_desc")}
                </p>
              )}

              <div className="p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
                <p className="text-sm text-theme-muted flex items-start gap-2">
                  <Info className="w-4 h-4 text-[var(--color-info)] flex-shrink-0 mt-0.5" aria-hidden="true" />
                  {t("gdpr.confirmation_email")}
                </p>
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={gdprModal.onClose}>
              {t("cancel")}
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
              {gdprRequestType === 'deletion' ? t('gdpr.confirm_deletion') : t('gdpr.submit_request')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default SettingsPage;
