// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  View,
  ScrollView,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import Constants from 'expo-constants';
import { useTranslation } from 'react-i18next';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';

import { api } from '@/lib/api/client';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { API_V2 } from '@/lib/constants';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Toggle from '@/components/ui/Toggle';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

interface NotificationPrefs {
  email_messages: boolean;
  email_connections: boolean;
  email_transactions: boolean;
  email_reviews: boolean;
  push_messages: boolean;
  push_transactions: boolean;
  push_social: boolean;
}

type PrivacyVisibility = 'public' | 'members' | 'connections';

interface PrivacyPrefs {
  privacy_profile: PrivacyVisibility;
  privacy_search: boolean;
  privacy_contact: boolean;
}

interface PreferencesResponse {
  privacy: PrivacyPrefs;
}

function getPrefs(): Promise<{ data: NotificationPrefs }> {
  return api.get<{ data: NotificationPrefs }>(`${API_V2}/users/me/notifications`);
}

function savePrefs(prefs: Partial<NotificationPrefs>): Promise<void> {
  return api.put<void>(`${API_V2}/users/me/notifications`, prefs);
}

function getPreferences(): Promise<{ data: PreferencesResponse }> {
  return api.get<{ data: PreferencesResponse }>(`${API_V2}/users/me/preferences`);
}

function savePrivacyPrefs(prefs: PrivacyPrefs): Promise<void> {
  return api.put<void>(`${API_V2}/users/me/preferences`, { privacy: prefs });
}

export default function SettingsScreen() {
  const { t } = useTranslation(['settings', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();

  const { data, isLoading } = useApi(() => getPrefs());
  const { data: preferencesData, isLoading: isLoadingPreferences } = useApi(() => getPreferences());
  const [prefs, setPrefs] = useState<NotificationPrefs | null>(null);
  const [privacyPrefs, setPrivacyPrefs] = useState<PrivacyPrefs | null>(null);
  const [saving, setSaving] = useState(false);
  const [savingPrivacy, setSavingPrivacy] = useState(false);
  const constants = Constants as typeof Constants & { default?: typeof Constants };
  const appVersion = Constants.expoConfig?.version ?? constants.default?.expoConfig?.version ?? t('unknownVersion');

  // Use server data as initial state once loaded
  const current = prefs ?? data?.data ?? null;
  const currentPrivacy = privacyPrefs ?? preferencesData?.data?.privacy ?? null;

  async function toggle(key: keyof NotificationPrefs) {
    if (!current) return;
    // Haptic feedback is handled by the Toggle component
    const updated = { ...current, [key]: !current[key] };
    setPrefs(updated);
    setSaving(true);
    try {
      await savePrefs({ [key]: updated[key] });
    } catch {
      // Revert
      setPrefs(current);
      Alert.alert(t('common:errors.generic'), t('saveError'));
    } finally {
      setSaving(false);
    }
  }

  async function updatePrivacy(nextPrefs: PrivacyPrefs) {
    if (!currentPrivacy) return;
    setPrivacyPrefs(nextPrefs);
    setSavingPrivacy(true);
    try {
      await savePrivacyPrefs(nextPrefs);
    } catch {
      setPrivacyPrefs(currentPrivacy);
      Alert.alert(t('common:errors.generic'), t('privacy.saveError'));
    } finally {
      setSavingPrivacy(false);
    }
  }

  function cycleVisibility() {
    if (!currentPrivacy) return;
    const nextVisibility: PrivacyVisibility =
      currentPrivacy.privacy_profile === 'members'
        ? 'connections'
        : currentPrivacy.privacy_profile === 'connections'
          ? 'public'
          : 'members';
    void updatePrivacy({ ...currentPrivacy, privacy_profile: nextVisibility });
  }

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('title')} backLabel={t('common:buttons.back')} fallbackHref="/(tabs)/profile" />
        <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40, gap: 12 }}>
          <HeroCard className="overflow-hidden rounded-panel p-0">
            <View className="h-1.5" style={{ backgroundColor: primary }} />
            <HeroCard.Body className="gap-4 p-4">
              <View className="flex-row items-start gap-3">
                <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                  <Ionicons name="settings-outline" size={25} color={primary} />
                </View>
                <View className="min-w-0 flex-1">
                  <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('eyebrow')}</Text>
                  <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('title')}</Text>
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('subtitle')}</Text>
                </View>
              </View>
              <View className="flex-row flex-wrap gap-2">
                <Chip size="sm" variant="soft" color="accent">
                  <Ionicons name="person-outline" size={12} color={primary} />
                  <Chip.Label>{t('account')}</Chip.Label>
                </Chip>
                <Chip size="sm" variant="soft" color="default">
                  <Ionicons name="notifications-outline" size={12} color={theme.textMuted} />
                  <Chip.Label>{t('notifications')}</Chip.Label>
                </Chip>
                <Chip size="sm" variant="soft" color="default">
                  <Ionicons name="shield-checkmark-outline" size={12} color={theme.textMuted} />
                  <Chip.Label>{t('security')}</Chip.Label>
                </Chip>
              </View>
            </HeroCard.Body>
          </HeroCard>

          <Section
            title={t('account')}
            subtitle={t('accountHint')}
            icon="person-circle-outline"
            primary={primary}
            theme={theme}
          >
            <ActionRow
              label={t('editProfile')}
              subtitle={t('editProfileHint')}
              icon="create-outline"
              tone={primary}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/edit-profile' as Href);
              }}
              theme={theme}
            />
            <ActionRow
              label={t('identity.page_title')}
              subtitle={t('identity.hint')}
              icon="finger-print-outline"
              tone={theme.success}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/verify-identity' as Href);
              }}
              theme={theme}
            />
            <ActionRow
              label={t('changePassword')}
              subtitle={t('changePasswordHint')}
              icon="key-outline"
              tone={theme.warning ?? primary}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/change-password');
              }}
              theme={theme}
            />
          </Section>

          <Section
            title={t('privacy.title')}
            subtitle={t('privacy.hint')}
            icon="lock-closed-outline"
            primary={primary}
            theme={theme}
          >
            <PrivacyVisibilityRow
              label={t('privacy.profileVisibility')}
              value={currentPrivacy?.privacy_profile ?? 'members'}
              disabled={isLoadingPreferences || savingPrivacy || !currentPrivacy}
              onPress={cycleVisibility}
              theme={theme}
              t={t}
            />
            <SettingRow
              label={t('privacy.searchIndexing')}
              value={currentPrivacy?.privacy_search ?? true}
              onToggle={() => {
                if (!currentPrivacy) return;
                void updatePrivacy({ ...currentPrivacy, privacy_search: !currentPrivacy.privacy_search });
              }}
              disabled={isLoadingPreferences || savingPrivacy || !currentPrivacy}
            />
            <SettingRow
              label={t('privacy.contactPermission')}
              value={currentPrivacy?.privacy_contact ?? true}
              onToggle={() => {
                if (!currentPrivacy) return;
                void updatePrivacy({ ...currentPrivacy, privacy_contact: !currentPrivacy.privacy_contact });
              }}
              disabled={isLoadingPreferences || savingPrivacy || !currentPrivacy}
            />
            <ActionRow
              label={t('blockedUsers.title')}
              subtitle={t('blockedUsers.settingsHint')}
              icon="shield-outline"
              tone={theme.error}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/settings-blocked-users' as Href);
              }}
              theme={theme}
            />
            <ActionRow
              label={t('dataExport.title')}
              subtitle={t('dataExport.settingsHint')}
              icon="download-outline"
              tone={primary}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/settings-data-export' as Href);
              }}
              theme={theme}
            />
          </Section>

          <Section
            title={t('translation.preferencesTitle')}
            subtitle={t('translation.preferencesHint')}
            icon="language-outline"
            primary={primary}
            theme={theme}
          >
            <ActionRow
              label={t('translation.title')}
              subtitle={t('translation.settingsHint')}
              icon="sparkles-outline"
              tone={primary}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/settings-translation' as Href);
              }}
              theme={theme}
            />
          </Section>

          <Section
            title={t('pushNotifications')}
            subtitle={t('pushHint')}
            icon="notifications-outline"
            primary={primary}
            theme={theme}
          >
            <SettingRow
              label={t('push.messages')}
              value={current?.push_messages ?? true}
              onToggle={() => void toggle('push_messages')}
              disabled={isLoading || saving}
            />
            <SettingRow
              label={t('push.transactions')}
              value={current?.push_transactions ?? true}
              onToggle={() => void toggle('push_transactions')}
              disabled={isLoading || saving}
            />
            <SettingRow
              label={t('push.social')}
              value={current?.push_social ?? true}
              onToggle={() => void toggle('push_social')}
              disabled={isLoading || saving}
            />
          </Section>

          <Section
            title={t('emailNotifications')}
            subtitle={t('emailHint')}
            icon="mail-outline"
            primary={primary}
            theme={theme}
          >
            <SettingRow
              label={t('email.messages')}
              value={current?.email_messages ?? true}
              onToggle={() => void toggle('email_messages')}
              disabled={isLoading || saving}
            />
            <SettingRow
              label={t('email.connections')}
              value={current?.email_connections ?? true}
              onToggle={() => void toggle('email_connections')}
              disabled={isLoading || saving}
            />
            <SettingRow
              label={t('email.transactions')}
              value={current?.email_transactions ?? true}
              onToggle={() => void toggle('email_transactions')}
              disabled={isLoading || saving}
            />
            <SettingRow
              label={t('email.reviews')}
              value={current?.email_reviews ?? true}
              onToggle={() => void toggle('email_reviews')}
              disabled={isLoading || saving}
            />
          </Section>

          <Section
            title={t('about')}
            subtitle={t('aboutHint')}
            icon="information-circle-outline"
            primary={primary}
            theme={theme}
          >
            <InfoRow label={t('version')} value={appVersion} theme={theme} />
            <InfoRow label={t('license')} value="AGPL-3.0-or-later" theme={theme} />
          </Section>

          <Text className="mt-2 text-center text-[11px]" style={{ color: theme.textMuted }}>
            {t('common:attribution')}
          </Text>
        </ScrollView>
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

function Section({
  title,
  subtitle,
  icon,
  primary,
  theme,
  children,
}: {
  title: string;
  subtitle: string;
  icon: IoniconName;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  children: React.ReactNode;
}) {
  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
            <Ionicons name={icon} size={20} color={primary} />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-base font-bold" style={{ color: theme.text }}>{title}</Text>
            <Text className="text-xs leading-4" style={{ color: theme.textSecondary }}>{subtitle}</Text>
          </View>
        </View>
        <View className="gap-2">
          {children}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function InfoRow({
  label,
  value,
  theme,
}: {
  label: string;
  value: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <Surface variant="secondary" className="rounded-panel-inner px-3 py-3">
      <View className="flex-row items-center justify-between gap-3">
        <Text className="text-sm font-semibold" style={{ color: theme.text }}>{label}</Text>
        <Text className="shrink text-right text-sm" style={{ color: theme.textSecondary }}>{value}</Text>
      </View>
    </Surface>
  );
}

function ActionRow({
  label,
  subtitle,
  icon,
  tone,
  onPress,
  theme,
}: {
  label: string;
  subtitle?: string;
  icon: IoniconName;
  tone: string;
  onPress: () => void;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <HeroButton
      variant="ghost"
      feedbackVariant="scale"
      className="w-full p-0"
      onPress={onPress}
      accessibilityLabel={label}
    >
      <Surface variant="secondary" className="rounded-panel-inner px-3 py-3">
        <View className="flex-row items-center justify-between gap-3">
          <View className="size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(tone, 0.12) }}>
            <Ionicons name={icon} size={18} color={tone} />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-sm font-semibold" style={{ color: theme.text }}>{label}</Text>
            {subtitle ? (
              <Text className="text-xs leading-4" style={{ color: theme.textSecondary }} numberOfLines={2}>{subtitle}</Text>
            ) : null}
          </View>
          <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
        </View>
      </Surface>
    </HeroButton>
  );
}

function PrivacyVisibilityRow({
  label,
  value,
  disabled,
  onPress,
  theme,
  t,
}: {
  label: string;
  value: PrivacyVisibility;
  disabled: boolean;
  onPress: () => void;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  return (
    <Surface variant="secondary" className="rounded-panel-inner px-3 py-3">
      <View className="flex-row items-center justify-between gap-3">
        <View className="min-w-0 flex-1">
          <Text className="text-sm font-semibold" style={{ color: theme.text }}>{label}</Text>
          <Text className="text-xs leading-4" style={{ color: theme.textSecondary }}>{t(`privacy.visibility.${value}`)}</Text>
        </View>
        <HeroButton size="sm" variant="secondary" onPress={onPress} isDisabled={disabled}>
          <HeroButton.Label>{t('privacy.changeVisibility')}</HeroButton.Label>
        </HeroButton>
      </View>
    </Surface>
  );
}

function SettingRow({
  label,
  value,
  onToggle,
  disabled,
}: {
  label: string;
  value: boolean;
  onToggle: () => void;
  disabled: boolean;
}) {
  return (
    <Surface variant="secondary" className="rounded-panel-inner px-3 py-2.5">
      <View className="min-h-10 justify-center">
        <Toggle
          label={label}
          value={value}
          onValueChange={onToggle}
          disabled={disabled}
        />
      </View>
    </Surface>
  );
}
