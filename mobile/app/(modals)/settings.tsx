// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import Constants from 'expo-constants';
import { useTranslation } from 'react-i18next';

import { api } from '@/lib/api/client';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { API_V2 } from '@/lib/constants';
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

function getPrefs(): Promise<{ data: NotificationPrefs }> {
  return api.get<{ data: NotificationPrefs }>(`${API_V2}/users/me/notifications`);
}

function savePrefs(prefs: Partial<NotificationPrefs>): Promise<void> {
  return api.put<void>(`${API_V2}/users/me/notifications`, prefs);
}

export default function SettingsScreen() {
  const { t } = useTranslation('settings');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const { data, isLoading } = useApi(() => getPrefs());
  const [prefs, setPrefs] = useState<NotificationPrefs | null>(null);
  const [saving, setSaving] = useState(false);

  // Use server data as initial state once loaded
  const current = prefs ?? data?.data ?? null;

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

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background">
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: 40 }}>

        <Section title={t('pushNotifications')}>
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

        <Section title={t('emailNotifications')}>
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

        <Section title={t('about')}>
          <View className="flex-row justify-between px-4 py-[13px] border-b border-border/50">
            <Text className="text-sm text-foreground">{t('version')}</Text>
            <Text className="text-sm text-muted-foreground">{Constants.expoConfig?.version ?? '1.0.0'}</Text>
          </View>
          <View className="flex-row justify-between px-4 py-[13px] border-b border-border/50">
            <Text className="text-sm text-foreground">{t('license')}</Text>
            <Text className="text-sm text-muted-foreground">AGPL-3.0-or-later</Text>
          </View>
        </Section>

        <Section title={t('security')}>
          <Pressable
            className="flex-row items-center justify-between px-4 py-[13px] border-b border-border/50"
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push('/(modals)/change-password');
            }}
            accessibilityLabel={t('changePassword')}
            accessibilityRole="button"
          >
            <Text className="text-sm text-foreground">{t('changePassword')}</Text>
            <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
          </Pressable>
        </Section>

        <Text className="text-[11px] text-muted-foreground text-center mt-4">
          {t('common:attribution')}
        </Text>
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function Section({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <View className="mb-5">
      <Text className="text-xs font-bold text-muted-foreground uppercase tracking-[0.6px] mb-2 px-1">
        {title}
      </Text>
      <View className="bg-surface rounded-xl overflow-hidden border border-border/50">
        {children}
      </View>
    </View>
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
    <View className="flex-row items-center justify-between px-4 py-[13px] border-b border-border/50">
      <Toggle
        label={label}
        value={value}
        onValueChange={onToggle}
        disabled={disabled}
      />
    </View>
  );
}
