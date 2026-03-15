// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  Switch,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  SafeAreaView,
  Alert,
} from 'react-native';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import Constants from 'expo-constants';
import { useTranslation } from 'react-i18next';

import { api } from '@/lib/api/client';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { API_V2 } from '@/lib/constants';

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
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>

        <Section title={t('pushNotifications')} styles={styles}>
          <SettingRow
            label={t('push.messages')}
            value={current?.push_messages ?? true}
            onToggle={() => void toggle('push_messages')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
          <SettingRow
            label={t('push.transactions')}
            value={current?.push_transactions ?? true}
            onToggle={() => void toggle('push_transactions')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
          <SettingRow
            label={t('push.social')}
            value={current?.push_social ?? true}
            onToggle={() => void toggle('push_social')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
        </Section>

        <Section title={t('emailNotifications')} styles={styles}>
          <SettingRow
            label={t('email.messages')}
            value={current?.email_messages ?? true}
            onToggle={() => void toggle('email_messages')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
          <SettingRow
            label={t('email.connections')}
            value={current?.email_connections ?? true}
            onToggle={() => void toggle('email_connections')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
          <SettingRow
            label={t('email.transactions')}
            value={current?.email_transactions ?? true}
            onToggle={() => void toggle('email_transactions')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
          <SettingRow
            label={t('email.reviews')}
            value={current?.email_reviews ?? true}
            onToggle={() => void toggle('email_reviews')}
            primary={primary}
            theme={theme}
            disabled={isLoading || saving}
            styles={styles}
          />
        </Section>

        <Section title={t('about')} styles={styles}>
          <View style={styles.aboutRow}>
            <Text style={styles.aboutLabel}>{t('version')}</Text>
            <Text style={styles.aboutValue}>{Constants.expoConfig?.version ?? '1.0.0'}</Text>
          </View>
          <View style={styles.aboutRow}>
            <Text style={styles.aboutLabel}>{t('license')}</Text>
            <Text style={styles.aboutValue}>AGPL-3.0-or-later</Text>
          </View>
        </Section>

        <Section title={t('security')} styles={styles}>
          <TouchableOpacity
            style={styles.settingRow}
            activeOpacity={0.7}
            onPress={() => router.push('/(modals)/change-password')}
            accessibilityLabel={t('changePassword')}
            accessibilityRole="button"
          >
            <Text style={styles.settingLabel}>{t('changePassword')}</Text>
            <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
          </TouchableOpacity>
        </Section>

        <Text style={styles.attribution}>
          © 2024–2026 Jasper Ford · Project NEXUS
        </Text>
      </ScrollView>
    </SafeAreaView>
  );
}

type Styles = ReturnType<typeof makeStyles>;

function Section({
  title,
  children,
  styles,
}: {
  title: string;
  children: React.ReactNode;
  styles: Styles;
}) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <View style={styles.sectionCard}>{children}</View>
    </View>
  );
}

function SettingRow({
  label,
  value,
  onToggle,
  primary,
  theme,
  disabled,
  styles,
}: {
  label: string;
  value: boolean;
  onToggle: () => void;
  primary: string;
  theme: Theme;
  disabled: boolean;
  styles: Styles;
}) {
  return (
    <View style={styles.settingRow}>
      <Text style={styles.settingLabel}>{label}</Text>
      <Switch
        value={value}
        onValueChange={onToggle}
        disabled={disabled}
        trackColor={{ false: theme.border, true: primary + '80' }}
        thumbColor={value ? primary : theme.surface}
      />
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    content: { padding: 20, paddingBottom: 48 },
    section: { marginBottom: 24 },
    sectionTitle: {
      fontSize: 12,
      fontWeight: '700',
      color: theme.textMuted,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: 8,
      paddingHorizontal: 4,
    },
    sectionCard: {
      backgroundColor: theme.surface,
      borderRadius: 14,
      overflow: 'hidden',
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    settingRow: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingHorizontal: 16,
      paddingVertical: 13,
      borderBottomWidth: 1,
      borderBottomColor: theme.borderSubtle,
    },
    settingLabel: { fontSize: 15, color: theme.text },
    aboutRow: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      paddingHorizontal: 16,
      paddingVertical: 13,
      borderBottomWidth: 1,
      borderBottomColor: theme.borderSubtle,
    },
    aboutLabel: { fontSize: 15, color: theme.text },
    aboutValue: { fontSize: 15, color: theme.textSecondary },
    attribution: {
      fontSize: 11,
      color: theme.textMuted,
      textAlign: 'center',
      marginTop: 16,
    },
  });
}
