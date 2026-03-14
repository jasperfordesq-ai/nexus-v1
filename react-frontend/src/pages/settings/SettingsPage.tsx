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
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
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
  FileCheck,
  Upload,
  PenLine,
  Ban,
  Scale,
  Sparkles,
  Calendar,
  Users,
  Globe,
  ChevronRight,
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { BiometricSettings } from '@/components/security/BiometricSettings';
import { PlaceAutocompleteInput } from '@/components/location';
import { SkillSelector } from '@/components/skills/SkillSelector';
import type { UserSkill } from '@/components/skills/SkillSelector';
import { AvailabilityGrid } from '@/components/availability/AvailabilityGrid';
import { SubAccountsManager } from '@/components/subaccounts/SubAccountsManager';
import { useAuth, useToast, useTenant, useTheme } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';

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
  latitude?: number;
  longitude?: number;
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
  email_gamification_digest: boolean;
  email_gamification_milestones: boolean;
  email_org_payments: boolean;
  email_org_transfers: boolean;
  email_org_membership: boolean;
  email_org_admin: boolean;
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
  const [searchParams] = useSearchParams();
  const { user, logout, refreshUser } = useAuth();
  const { tenantPath, tenant, hasFeature } = useTenant();
  const { theme, setTheme } = useTheme();
  const { t } = useTranslation('settings');
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const validTabs = ['profile', 'notifications', 'privacy', 'security'];
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
  interface UserInsuranceCert {
    id: number;
    insurance_type: string;
    provider_name: string | null;
    status: string;
    expiry_date: string | null;
    created_at: string;
  }
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
        setNotifications((prev) => ({ ...prev, ...response.data }));
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
        setSessionsError(t('security.sessions_coming_soon', { defaultValue: 'Session management coming soon' }));
      }
    } catch (error) {
      logError('Failed to load sessions', error);
      setSessionsError(t('security.sessions_coming_soon', { defaultValue: 'Session management coming soon' }));
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
  }, [profileData, refreshUser, toast]);

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
  }, [notifications, matchDigestFrequency, notifyHotMatches, notifyMutualMatches, toast]);

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
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  }

  // Password change handler
  async function handleChangePassword() {
    if (!passwordData.current_password || !passwordData.new_password || !passwordData.confirm_password) {
      toast.error(t('toasts.missing_fields'), t('toasts.missing_password_fields'));
      return;
    }

    if (passwordData.new_password.length < 8) {
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

  async function handleMarketingConsentToggle(checked: boolean) {
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
          {t("header.title")}
        </h1>
        <p className="text-theme-muted mt-1">{t("header.subtitle")}</p>
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
        </Tabs>
      </motion.div>

      {/* Tab Content */}
      <motion.div variants={itemVariants}>
        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* PROFILE TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'profile' && (
          <div className="space-y-6">
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-6">{t('profile.section_title', 'Profile Information')}</h2>

            {/* Avatar */}
            <div className="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 mb-8">
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
                <p className="text-theme-primary font-medium">{t('profile.photo_label', 'Profile Photo')}</p>
                <p className="text-theme-subtle text-sm">{t('profile.photo_hint', 'JPG, PNG or GIF. Max 5MB.')}</p>
              </div>
            </div>

            {/* Form */}
            <div className="space-y-6">
              {/* Name fields */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label={t('profile.first_name', 'First Name')}
                  placeholder={t('profile.first_name_placeholder', 'Your first name')}
                  value={profileData.first_name}
                  onChange={(e) => setProfileData((prev) => ({ ...prev, first_name: e.target.value }))}
                  classNames={inputClassNames}
                />
                <Input
                  label={t('profile.last_name', 'Last Name')}
                  placeholder={t('profile.last_name_placeholder', 'Your last name')}
                  value={profileData.last_name}
                  onChange={(e) => setProfileData((prev) => ({ ...prev, last_name: e.target.value }))}
                  classNames={inputClassNames}
                />
              </div>

              {/* Phone */}
              <Input
                type="tel"
                label={t('profile.phone', 'Phone Number')}
                placeholder={t('profile.phone_placeholder', '+1 555 123 4567')}
                value={profileData.phone}
                onChange={(e) => setProfileData((prev) => ({ ...prev, phone: e.target.value }))}
                startContent={<Phone className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                classNames={inputClassNames}
              />

              {/* Profile Type */}
              <Select
                label={t('profile.profile_type', 'Profile Type')}
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
                <SelectItem key="individual">{t('profile.type_individual', 'Individual')}</SelectItem>
                <SelectItem key="organisation">{t('profile.type_organisation', 'Organisation')}</SelectItem>
              </Select>

              {/* Organisation Name (conditional) */}
              {profileData.profile_type === 'organisation' && (
                <Input
                  label={t('profile.org_name', 'Organisation Name')}
                  placeholder={t('profile.org_name_placeholder', 'Your organisation name')}
                  value={profileData.organization_name}
                  onChange={(e) => setProfileData((prev) => ({ ...prev, organization_name: e.target.value }))}
                  startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={inputClassNames}
                />
              )}

              <Input
                label={t('profile.tagline', 'Tagline')}
                placeholder={t('profile.tagline_placeholder', 'A short description about yourself')}
                value={profileData.tagline}
                onChange={(e) => setProfileData((prev) => ({ ...prev, tagline: e.target.value }))}
                classNames={inputClassNames}
              />

              <Textarea
                label={t('profile.bio', 'Bio')}
                placeholder={t('profile.bio_placeholder', 'Tell others about yourself...')}
                value={profileData.bio}
                onChange={(e) => setProfileData((prev) => ({ ...prev, bio: e.target.value }))}
                minRows={4}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />

              <PlaceAutocompleteInput
                label={t('profile.location', 'Location')}
                placeholder={t('profile.location_placeholder', 'City, Country')}
                value={profileData.location}
                onChange={(val) => setProfileData((prev) => ({ ...prev, location: val }))}
                onPlaceSelect={(place) => {
                  setProfileData((prev) => ({
                    ...prev,
                    location: place.formattedAddress,
                    latitude: place.lat,
                    longitude: place.lng,
                  }));
                }}
                onClear={() => {
                  setProfileData((prev) => ({
                    ...prev,
                    location: '',
                    latitude: undefined,
                    longitude: undefined,
                  }));
                }}
                classNames={inputClassNames}
              />

              <Button
                onPress={saveProfile}
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                isLoading={isSaving}
              >
                {t("save_changes")}
              </Button>
            </div>
          </GlassCard>

          {/* ── Language & Appearance ── */}
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-6 flex items-center gap-2">
              <Monitor className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              {t('language')} &amp; {t('appearance')}
            </h2>

            <div className="space-y-6">
              {/* Language preference */}
              <div>
                <p className="text-sm font-medium text-theme-primary mb-1">{t('language_preference')}</p>
                <p className="text-xs text-theme-muted mb-3">{t('select_language')}</p>
                <LanguageSwitcher compact={false} />
              </div>

              {/* Theme preference */}
              <div className="pt-4 border-t border-theme-default">
                <p className="text-sm font-medium text-theme-primary mb-3">{t('theme.title')}</p>
                <div className="flex gap-2 flex-wrap">
                  {(['light', 'dark', 'system'] as const).map((mode) => (
                    <Button
                      key={mode}
                      size="sm"
                      variant={theme === mode ? 'solid' : 'flat'}
                      className={theme === mode ? 'bg-indigo-500 text-white' : 'text-theme-secondary'}
                      onPress={() => setTheme(mode)}
                    >
                      {t(`theme.${mode}`)}
                    </Button>
                  ))}
                </div>
              </div>
            </div>
          </GlassCard>
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* NOTIFICATIONS TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'notifications' && (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-6">{t("notification_sections.title")}</h2>

            {notificationError ? (
              <div className="text-center py-8">
                <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
                <p className="text-theme-muted mb-4">{notificationError}</p>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={loadNotificationSettings}
                >
                  {t("try_again")}
                </Button>
              </div>
            ) : (
              <div className="space-y-6">
                {/* Messages & Communication */}
                <div className="space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Mail className="w-4 h-4" aria-hidden="true" />
                    {t("notification_sections.messages_communication")}
                  </h3>

                  <SettingToggle
                    label={t("notification_prefs.new_messages")}
                    description={t("notification_descriptions.new_messages")}
                    checked={notifications.email_messages}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_messages: checked }))}
                  />

                  <SettingToggle
                    label={t("notification_prefs.connection_requests")}
                    description={t("notification_descriptions.connection_requests")}
                    checked={notifications.email_connections}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_connections: checked }))}
                  />
                </div>

                {/* Activity & Listings */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <CreditCard className="w-4 h-4" aria-hidden="true" />
                    {t("notification_sections.activity_listings")}
                  </h3>

                  <SettingToggle
                    label={t("notification_prefs.listing_activity")}
                    description={t("notification_descriptions.listing_activity")}
                    checked={notifications.email_listings}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_listings: checked }))}
                  />

                  <SettingToggle
                    label={t("notification_prefs.credit_transactions")}
                    description={t("notification_descriptions.credit_transactions")}
                    checked={notifications.email_transactions}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_transactions: checked }))}
                  />

                  <SettingToggle
                    label={t("notification_prefs.new_reviews")}
                    description={t("notification_descriptions.new_reviews")}
                    checked={notifications.email_reviews}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_reviews: checked }))}
                  />
                </div>

                {/* Community & Achievements */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Trophy className="w-4 h-4" aria-hidden="true" />
                    {t("notification_sections.community_achievements")}
                  </h3>

                  <SettingToggle
                    label={t("notification_prefs.gamification_digest")}
                    description={t("notification_descriptions.gamification_digest")}
                    checked={notifications.email_gamification_digest}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_gamification_digest: checked }))}
                  />

                  <SettingToggle
                    label={t("notification_prefs.achievement_milestones")}
                    description={t("notification_descriptions.achievement_milestones")}
                    checked={notifications.email_gamification_milestones}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_gamification_milestones: checked }))}
                  />

                  <SettingToggle
                    label={t("notification_prefs.weekly_digest")}
                    description={t("notification_descriptions.weekly_digest")}
                    checked={notifications.email_digest}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, email_digest: checked }))}
                  />
                </div>

                {/* Organisation Notifications */}
                {profileData.profile_type === 'organisation' && (
                  <div className="pt-4 border-t border-theme-default space-y-4">
                    <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                      <Building2 className="w-4 h-4" aria-hidden="true" />
                      {t("notification_sections.organisation_notifications")}
                    </h3>

                    <SettingToggle
                      label={t("notification_prefs.payment_notifications")}
                      description={t("notification_descriptions.payment_notifications")}
                      checked={notifications.email_org_payments}
                      onChange={(checked) => setNotifications((prev) => ({ ...prev, email_org_payments: checked }))}
                    />

                    <SettingToggle
                      label={t("notification_prefs.transfer_notifications")}
                      description={t("notification_descriptions.transfer_notifications")}
                      checked={notifications.email_org_transfers}
                      onChange={(checked) => setNotifications((prev) => ({ ...prev, email_org_transfers: checked }))}
                    />

                    <SettingToggle
                      label={t("notification_prefs.membership_updates")}
                      description={t("notification_descriptions.membership_updates")}
                      checked={notifications.email_org_membership}
                      onChange={(checked) => setNotifications((prev) => ({ ...prev, email_org_membership: checked }))}
                    />

                    <SettingToggle
                      label={t("notification_prefs.admin_notifications")}
                      description={t("notification_descriptions.admin_notifications")}
                      checked={notifications.email_org_admin}
                      onChange={(checked) => setNotifications((prev) => ({ ...prev, email_org_admin: checked }))}
                    />
                  </div>
                )}

                {/* Match Digest */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Search className="w-4 h-4" aria-hidden="true" />
                    {t("notification_sections.match_digest")}
                  </h3>

                  <div className="p-4 rounded-lg bg-theme-elevated">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                      <div>
                        <p className="font-medium text-theme-primary">{t("match_digest.frequency")}</p>
                        <p className="text-sm text-theme-subtle">{t("match_digest.frequency_description")}</p>
                      </div>
                      <Select
                        aria-label="Match digest frequency"
                        selectedKeys={[matchDigestFrequency]}
                        onSelectionChange={(keys) => {
                          const value = Array.from(keys)[0] as string;
                          if (value) setMatchDigestFrequency(value);
                        }}
                        className="sm:max-w-[180px]"
                        classNames={{
                          trigger: 'bg-theme-elevated border-theme-default',
                          value: 'text-theme-primary',
                        }}
                      >
                        <SelectItem key="daily">{t("match_digest.daily")}</SelectItem>
                        <SelectItem key="weekly">{t("match_digest.weekly")}</SelectItem>
                        <SelectItem key="fortnightly">{t("match_digest.fortnightly")}</SelectItem>
                        <SelectItem key="never">{t("match_digest.never")}</SelectItem>
                      </Select>
                    </div>
                  </div>

                  <SettingToggle
                    label={t("notification_prefs.hot_match_alerts")}
                    description={t("notification_descriptions.hot_match_alerts")}
                    checked={notifyHotMatches}
                    onChange={setNotifyHotMatches}
                  />

                  <SettingToggle
                    label={t("notification_prefs.mutual_match_alerts")}
                    description={t("notification_descriptions.mutual_match_alerts")}
                    checked={notifyMutualMatches}
                    onChange={setNotifyMutualMatches}
                  />
                </div>

                {/* Push Notifications */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Smartphone className="w-4 h-4" aria-hidden="true" />
                    {t("notification_sections.push_notifications")}
                  </h3>

                  <SettingToggle
                    label={t("notification_prefs.enable_push")}
                    description={t("notification_descriptions.enable_push")}
                    checked={notifications.push_enabled}
                    onChange={(checked) => setNotifications((prev) => ({ ...prev, push_enabled: checked }))}
                  />
                </div>

                {/* Marketing & Communications */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Mail className="w-4 h-4" aria-hidden="true" />
                    {t("notification_sections.marketing_communications")}
                  </h3>

                  <SettingToggle
                    label={t("notification_prefs.marketing_emails")}
                    description={t("notification_descriptions.marketing_emails")}
                    checked={marketingConsent}
                    onChange={handleMarketingConsentToggle}
                    disabled={marketingConsentLoading}
                  />
                </div>

                <Button
                  onPress={saveNotifications}
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                  isLoading={isSaving}
                >
                  {t("save_preferences")}
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
              <h2 className="text-lg font-semibold text-theme-primary mb-6">{t("privacy_sections.title")}</h2>

              <div className="space-y-6">
                {/* Profile Visibility */}
                <div className="space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Eye className="w-4 h-4" aria-hidden="true" />
                    {t("privacy_sections.profile_visibility")}
                  </h3>

                  <Select
                    label={t("privacy_prefs.profile_visibility")}
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
                    <SelectItem key="public">{t("visibility_options.public")}</SelectItem>
                    <SelectItem key="members">{t("visibility_options.members")}</SelectItem>
                    <SelectItem key="connections">{t("visibility_options.connections")}</SelectItem>
                  </Select>
                </div>

                {/* Search & Discovery */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <Search className="w-4 h-4" aria-hidden="true" />
                    {t("privacy_sections.search_discovery")}
                  </h3>

                  <SettingToggle
                    label={t("privacy_prefs.search_indexing")}
                    description={t("privacy_descriptions.search_indexing")}
                    checked={privacy.search_indexing}
                    onChange={(checked) => setPrivacy((prev) => ({ ...prev, search_indexing: checked }))}
                  />
                </div>

                {/* Contact Preferences */}
                <div className="pt-4 border-t border-theme-default space-y-4">
                  <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                    <MessageSquare className="w-4 h-4" aria-hidden="true" />
                    {t("privacy_sections.contact_preferences")}
                  </h3>

                  <SettingToggle
                    label={t("privacy_prefs.allow_contact")}
                    description={t("privacy_descriptions.allow_contact")}
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
                  {t("save_privacy")}
                </Button>
              </div>
            </GlassCard>

            {/* Federation Settings Link */}
            {hasFeature('federation') && (
              <GlassCard className="p-6">
                <Button
                  variant="flat"
                  className="w-full justify-between bg-theme-elevated text-theme-primary h-auto py-4 px-4"
                  startContent={
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-lg bg-indigo-500/20">
                        <Globe className="w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                      </div>
                      <div className="text-left">
                        <p className="font-medium">{t("federation.title")}</p>
                        <p className="text-sm text-theme-subtle font-normal">{t("federation.description")}</p>
                      </div>
                    </div>
                  }
                  endContent={<ChevronRight className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
                  onPress={() => navigate(tenantPath('/federation/settings'))}
                />
              </GlassCard>
            )}

            {/* GDPR Section */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                <FileText className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                {t("gdpr.title")}
              </h2>
              <p className="text-theme-subtle text-sm mb-6">
                {t("gdpr.description")}
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
                    <p className="font-medium">{t("gdpr.download_title")}</p>
                    <p className="text-sm text-theme-subtle font-normal">{t("gdpr.download_desc")}</p>
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
                    <p className="font-medium">{t("gdpr.portability_title")}</p>
                    <p className="text-sm text-theme-subtle font-normal">{t("gdpr.portability_desc")}</p>
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
                    <p className="font-medium text-red-600 dark:text-red-400">{t("gdpr.deletion_title")}</p>
                    <p className="text-sm text-theme-subtle font-normal">{t("gdpr.deletion_desc")}</p>
                  </div>
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
                  startContent={
                    <div className="p-2 rounded-lg bg-amber-500/20">
                      <PenLine className="w-4 h-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                    </div>
                  }
                  onPress={() => openGdprModal('rectification')}
                >
                  <div className="text-left">
                    <p className="font-medium">{t("gdpr.rectification_title")}</p>
                    <p className="text-sm text-theme-subtle font-normal">{t("gdpr.rectification_desc")}</p>
                  </div>
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
                  startContent={
                    <div className="p-2 rounded-lg bg-orange-500/20">
                      <Ban className="w-4 h-4 text-orange-600 dark:text-orange-400" aria-hidden="true" />
                    </div>
                  }
                  onPress={() => openGdprModal('restriction')}
                >
                  <div className="text-left">
                    <p className="font-medium">{t("gdpr.restriction_title")}</p>
                    <p className="text-sm text-theme-subtle font-normal">{t("gdpr.restriction_desc")}</p>
                  </div>
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
                  startContent={
                    <div className="p-2 rounded-lg bg-violet-500/20">
                      <Scale className="w-4 h-4 text-violet-600 dark:text-violet-400" aria-hidden="true" />
                    </div>
                  }
                  onPress={() => openGdprModal('objection')}
                >
                  <div className="text-left">
                    <p className="font-medium">{t("gdpr.objection_title")}</p>
                    <p className="text-sm text-theme-subtle font-normal">{t("gdpr.objection_desc")}</p>
                  </div>
                </Button>
              </div>

              <div className="mt-4 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
                <p className="text-sm text-theme-muted flex items-start gap-2">
                  <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
                  {t("gdpr.info")}
                </p>
              </div>
            </GlassCard>

            {/* Insurance Certificates — gated behind compliance flag */}
            {tenant?.compliance?.insurance_enabled && (
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <FileCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                  {t("insurance.title")}
                </h2>
                <p className="text-theme-subtle text-sm mb-4">
                  {t("insurance.description")}
                </p>

                {insuranceLoading ? (
                  <div className="flex items-center gap-2 text-sm text-theme-muted">
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    {t("insurance.loading")}
                  </div>
                ) : (
                  <>
                    {insuranceCerts.length > 0 && (
                      <div className="space-y-2 mb-4">
                        {insuranceCerts.map((cert) => (
                          <div
                            key={cert.id}
                            className="flex items-center justify-between rounded-lg border border-default-200 bg-theme-elevated p-3"
                          >
                            <div>
                              <p className="text-sm font-medium text-theme-primary">
                                {cert.insurance_type.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())}
                              </p>
                              <p className="text-xs text-theme-muted">
                                {cert.provider_name || t("insurance.unknown_provider")}
                                {cert.expiry_date ? ` — Expires ${new Date(cert.expiry_date).toLocaleDateString()}` : ''}
                              </p>
                            </div>
                            <span className={`text-xs px-2 py-1 rounded-full font-medium capitalize ${
                              cert.status === 'verified' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                                : cert.status === 'pending' || cert.status === 'submitted' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                                : cert.status === 'rejected' ? 'bg-red-500/20 text-red-600 dark:text-red-400'
                                : 'bg-default-200 text-default-600'
                            }`}>
                              {cert.status}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* #4: Insurance type selector */}
                    <div className="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                      <Select
                        label={t("insurance.type_label")}
                        selectedKeys={[insuranceType]}
                        onSelectionChange={(keys) => {
                          const val = Array.from(keys)[0] as string;
                          if (val) setInsuranceType(val);
                        }}
                        variant="bordered"
                        size="sm"
                        className="max-w-xs"
                      >
                        <SelectItem key="public_liability">{t("insurance.public_liability")}</SelectItem>
                        <SelectItem key="professional_indemnity">{t("insurance.professional_indemnity")}</SelectItem>
                        <SelectItem key="employers_liability">{t("insurance.employers_liability")}</SelectItem>
                        <SelectItem key="product_liability">{t("insurance.product_liability")}</SelectItem>
                        <SelectItem key="personal_accident">{t("insurance.personal_accident")}</SelectItem>
                        <SelectItem key="other">{t("insurance.other")}</SelectItem>
                      </Select>
                      <label className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-theme-elevated hover:bg-theme-hover cursor-pointer transition-colors border border-default-200">
                        <Upload className="w-4 h-4 text-theme-primary" />
                        <span className="text-sm font-medium text-theme-primary">
                          {insuranceUploading ? t('insurance.uploading') : t('insurance.upload_certificate')}
                        </span>
                        <input
                          type="file"
                          accept=".pdf,.jpg,.jpeg,.png"
                          className="hidden"
                          onChange={handleInsuranceUpload}
                          disabled={insuranceUploading}
                        />
                      </label>
                    </div>
                  </>
                )}
              </GlassCard>
            )}
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* SECURITY TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'security' && (
          <div className="space-y-6">
            {/* Password & 2FA */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-6">{t('security_settings')}</h2>

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
                      <p className="font-medium text-theme-primary">{t('change_password')}</p>
                      <p className="text-sm text-theme-subtle">{t('change_password_subtitle')}</p>
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
                        <p className="font-medium text-theme-primary">{t('twofa_title')}</p>
                        <p className="text-sm text-theme-subtle">
                          {twoFactorLoading ? (
                            t('twofa_checking')
                          ) : twoFactorEnabled ? (
                            <span className="flex items-center gap-1">
                              <ShieldCheck className="w-3 h-3 text-emerald-500" aria-hidden="true" />
                              {t('twofa_enabled')}
                              {backupCodesRemaining > 0 && (
                                <span className="text-theme-subtle">
                                  {' '}&mdash; {t('twofa_backup_remaining', { count: backupCodesRemaining })}
                                </span>
                              )}
                            </span>
                          ) : (
                            <span className="flex items-center gap-1">
                              <ShieldOff className="w-3 h-3 text-amber-500" aria-hidden="true" />
                              {t('twofa_not_enabled')}
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
                            {t('twofa_disable')}
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                            onPress={handleSetup2FA}
                          >
                            {t('twofa_enable')}
                          </Button>
                        )}
                      </div>
                    )}
                  </div>
                </div>

                {/* Biometric / Passkey Authentication */}
                <BiometricSettings />
              </div>
            </GlassCard>

            {/* Active Sessions */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                <Monitor className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                {t('active_sessions')}
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
                            {session.browser} {t('session_on')} {session.device}
                            {session.is_current && (
                              <Chip size="sm" color="success" variant="flat">{t('session_current')}</Chip>
                            )}
                          </p>
                          <p className="text-xs text-theme-subtle">
                            {session.ip_address} &mdash; {t('session_last_active')} {new Date(session.last_active).toLocaleDateString()}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-6">
                  <Monitor className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
                  <p className="text-theme-muted">{t('sessions_coming_soon')}</p>
                </div>
              )}
            </GlassCard>

            {/* Account Actions */}
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('account_actions')}</h2>

              <div className="space-y-3">
                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary"
                  startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                  onPress={logoutModal.onOpen}
                >
                  {t('log_out')}
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-red-500/10 text-red-400"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                  onPress={deleteModal.onOpen}
                >
                  {t('delete_account')}
                </Button>
              </div>
            </GlassCard>
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* SKILLS TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'skills' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-2">{t("skills.title")}</h2>
              <p className="text-sm text-theme-muted mb-6">
                {t("skills.description")}
              </p>
              <SkillsTabContent />
            </GlassCard>
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* AVAILABILITY TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'availability' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <AvailabilityGrid editable />
            </GlassCard>
          </div>
        )}

        {/* ─────────────────────────────────────────────────────────────────── */}
        {/* LINKED ACCOUNTS TAB */}
        {/* ─────────────────────────────────────────────────────────────────── */}
        {activeTab === 'linked-accounts' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <SubAccountsManager />
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
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t("password_modal.title")}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                type={showCurrentPassword ? 'text' : 'password'}
                label={t("password.current")}
                value={passwordData.current_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, current_password: e.target.value }))}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0"
                    onPress={() => setShowCurrentPassword(!showCurrentPassword)}
                    aria-label={showCurrentPassword ? t('password.hide_current') : t('password.show_current')}
                  >
                    {showCurrentPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </Button>
                }
                classNames={inputClassNames}
              />
              <Input
                type={showNewPassword ? 'text' : 'password'}
                label={t("password.new")}
                value={passwordData.new_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, new_password: e.target.value }))}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0"
                    onPress={() => setShowNewPassword(!showNewPassword)}
                    aria-label={showNewPassword ? t('password.hide_new') : t('password.show_new')}
                  >
                    {showNewPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </Button>
                }
                classNames={inputClassNames}
              />
              <Input
                type="password"
                label={t("password.confirm")}
                value={passwordData.confirm_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, confirm_password: e.target.value }))}
                classNames={inputClassNames}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={passwordModal.onClose}>
              {t("cancel")}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleChangePassword}
              isLoading={isChangingPassword}
            >
              {t("password_modal.submit")}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Logout Confirmation Modal */}
      <Modal
        isOpen={logoutModal.isOpen}
        onClose={logoutModal.onClose}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t("logout_modal.title")}</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {t("logout_modal.confirm_message")}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={logoutModal.onClose}>
              {t("cancel")}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleLogout}
            >
              {t("logout_modal.submit")}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Account Modal */}
      <Modal
        isOpen={deleteModal.isOpen}
        onClose={deleteModal.onClose}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            {t("delete_modal.title")}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-red-600 dark:text-red-400 font-medium">{t("delete_modal.warning")}</p>
                <p className="text-theme-muted text-sm mt-1">
                  {t("delete_modal.warning_desc")}
                </p>
              </div>
              <div>
                <p className="text-theme-muted mb-2">
                  {t("delete_modal.type_confirm")}
                </p>
                <Input
                  value={deleteConfirmation}
                  onChange={(e) => setDeleteConfirmation(e.target.value)}
                  placeholder="DELETE"
                  aria-label={t('delete_modal.aria_label')}
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
              {t("cancel")}
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDeleteAccount}
              isLoading={isDeleting}
              isDisabled={deleteConfirmation !== 'DELETE'}
            >
              {t("delete_modal.submit")}
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
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <QrCode className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            {t('twofa_setup_title')}
          </ModalHeader>
          <ModalBody>
            {!twoFactorSetupData ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : (
              <div className="space-y-6">
                {/* What is an authenticator app? */}
                <div className="p-4 rounded-lg bg-indigo-500/10 border border-indigo-500/20">
                  <p className="text-sm font-medium text-theme-primary mb-2">{t('twofa_what_is')}</p>
                  <p className="text-xs text-theme-muted mb-3">
                    {t('twofa_what_is_desc')}
                  </p>
                  <p className="text-xs text-theme-muted mb-2">
                    {t('twofa_download_prompt')}
                  </p>
                  <div className="flex flex-wrap gap-2">
                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener noreferrer" className="text-xs px-2.5 py-1 rounded-full bg-theme-elevated text-indigo-500 hover:bg-theme-hover transition-colors">
                      Google Authenticator
                    </a>
                    <a href="https://www.microsoft.com/en-us/security/mobile-authenticator-app" target="_blank" rel="noopener noreferrer" className="text-xs px-2.5 py-1 rounded-full bg-theme-elevated text-indigo-500 hover:bg-theme-hover transition-colors">
                      Microsoft Authenticator
                    </a>
                    <a href="https://authy.com/download/" target="_blank" rel="noopener noreferrer" className="text-xs px-2.5 py-1 rounded-full bg-theme-elevated text-indigo-500 hover:bg-theme-hover transition-colors">
                      Authy
                    </a>
                  </div>
                </div>

                <div className="text-center">
                  <p className="text-theme-muted mb-4">
                    {t('twofa_scan_qr')}
                  </p>
                  <div className="inline-block p-4 bg-white rounded-xl">
                    <img
                      src={twoFactorSetupData.qr_code_url}
                      alt={t('twofa_qr_alt')}
                      className="w-48 h-48"
                      loading="lazy"
                    />
                  </div>
                </div>

                <div className="p-3 rounded-lg bg-theme-elevated">
                  <p className="text-xs text-theme-subtle mb-1">{t('twofa_manual_key')}</p>
                  <p className="font-mono text-sm text-theme-primary break-all select-all">
                    {twoFactorSetupData.secret}
                  </p>
                </div>

                <Input
                  label={t('twofa_verification_code')}
                  placeholder={t('twofa_enter_code')}
                  value={twoFactorVerifyCode}
                  onChange={(e) => setTwoFactorVerifyCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  maxLength={6}
                  classNames={inputClassNames}
                  description={t('twofa_code_description')}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={twoFactorSetupModal.onClose}>
              {t('twofa_cancel')}
            </Button>
            <Button
              className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
              onPress={handleVerify2FA}
              isLoading={isVerifying2FA}
              isDisabled={!twoFactorSetupData || twoFactorVerifyCode.length < 6}
            >
              {t('twofa_verify_enable')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* 2FA Disable Modal */}
      <Modal
        isOpen={twoFactorDisableModal.isOpen}
        onClose={twoFactorDisableModal.onClose}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            {t('twofa_disable_title')}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20">
                <p className="text-amber-600 dark:text-amber-400 font-medium">{t('twofa_disable_warning')}</p>
                <p className="text-theme-muted text-sm mt-1">
                  {t('twofa_disable_desc')}
                </p>
              </div>
              <Input
                type="password"
                label={t('twofa_confirm_password')}
                value={twoFactorDisablePassword}
                onChange={(e) => setTwoFactorDisablePassword(e.target.value)}
                classNames={inputClassNames}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={twoFactorDisableModal.onClose}>
              {t('twofa_cancel')}
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDisable2FA}
              isLoading={isDisabling2FA}
              isDisabled={!twoFactorDisablePassword}
            >
              {t('twofa_disable_confirm')}
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
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            {t('backup_codes_title')}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20">
                <p className="text-amber-600 dark:text-amber-400 font-medium">{t('backup_codes_warning')}</p>
                <p className="text-theme-muted text-sm mt-1">
                  {t('backup_codes_desc')}
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 rounded-lg bg-theme-elevated">
                {backupCodes.map((code) => (
                  <p key={code} className="font-mono text-sm text-theme-primary text-center py-1">
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
                {t('backup_codes_copy')}
              </Button>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={backupCodesModal.onClose}
            >
              {t('backup_codes_saved')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* GDPR Request Modal */}
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
                  <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
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

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

interface SettingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
}

function SettingToggle({ label, description, checked, onChange, disabled }: SettingToggleProps) {
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
        isDisabled={disabled}
        classNames={{
          wrapper: 'group-data-[selected=true]:bg-indigo-500',
        }}
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Skills Tab Content (lazy-loaded skills data)
// ─────────────────────────────────────────────────────────────────────────────

function SkillsTabContent() {
  const [userSkills, setUserSkills] = useState<UserSkill[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const loadSkills = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<UserSkill[]>('/v2/users/me/skills');
      if (response.success && response.data) {
        setUserSkills(response.data);
      }
    } catch (err) {
      logError('Failed to load user skills', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadSkills();
  }, [loadSkills]);

  if (isLoading) {
    return (
      <div className="flex justify-center py-8">
        <Spinner size="lg" />
      </div>
    );
  }

  return <SkillSelector userSkills={userSkills} onSkillsChange={loadSkills} />;
}

export default SettingsPage;
