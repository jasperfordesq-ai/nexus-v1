// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import AppTopBar from '@/components/ui/AppTopBar';
import Input from '@/components/ui/Input';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import Toggle from '@/components/ui/Toggle';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import {
  approveSubAccount,
  getManagedSubAccounts,
  getManagerSubAccounts,
  requestSubAccount,
  revokeSubAccount,
  updateSubAccountPermissions,
  type SubAccountPermission,
  type SubAccountRelationship,
} from '@/lib/api/settings';

const PERMISSIONS: SubAccountPermission[] = [
  'can_view_activity',
  'can_manage_listings',
  'can_transact',
  'can_view_messages',
];

function displayName(item: SubAccountRelationship, fallback: string) {
  return [item.first_name, item.last_name].filter(Boolean).join(' ').trim() || item.email || fallback;
}

async function loadLinkedAccounts() {
  const [managed, managers] = await Promise.all([
    getManagedSubAccounts(),
    getManagerSubAccounts(),
  ]);
  return { managed, managers };
}

export default function SettingsLinkedAccountsRoute() {
  return (
    <ModalErrorBoundary>
      <SettingsLinkedAccountsScreen />
    </ModalErrorBoundary>
  );
}

function SettingsLinkedAccountsScreen() {
  const { t } = useTranslation(['settings', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [email, setEmail] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);
  const query = useApi(loadLinkedAccounts, []);

  async function sendRequest() {
    const trimmed = email.trim();
    if (!trimmed) {
      Alert.alert(t('common:errors.alertTitle'), t('linkedAccounts.emailRequired'));
      return;
    }
    try {
      setIsSending(true);
      await requestSubAccount(trimmed);
      setEmail('');
      query.refresh();
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('linkedAccounts.requestFailed'));
    } finally {
      setIsSending(false);
    }
  }

  async function approve(item: SubAccountRelationship) {
    try {
      setBusyId(item.relationship_id);
      await approveSubAccount(item.relationship_id);
      query.refresh();
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('linkedAccounts.approveFailed'));
    } finally {
      setBusyId(null);
    }
  }

  async function revoke(item: SubAccountRelationship) {
    try {
      setBusyId(item.relationship_id);
      await revokeSubAccount(item.relationship_id);
      query.refresh();
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('linkedAccounts.revokeFailed'));
    } finally {
      setBusyId(null);
    }
  }

  async function togglePermission(item: SubAccountRelationship, permission: SubAccountPermission) {
    try {
      setBusyId(item.relationship_id);
      await updateSubAccountPermissions(item.relationship_id, {
        [permission]: !Boolean(item.permissions?.[permission]),
      });
      query.refresh();
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('linkedAccounts.permissionFailed'));
    } finally {
      setBusyId(null);
    }
  }

  const managed = query.data?.managed ?? [];
  const managers = query.data?.managers ?? [];

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('linkedAccounts.title')} backLabel={t('common:buttons.back')} fallbackHref="/(modals)/settings" />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40, gap: 12 }}>
        <HeroCard className="overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-3 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-12 items-center justify-center rounded-panel-inner" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                <Ionicons name="people-circle-outline" size={24} color={primary} />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('linkedAccounts.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('linkedAccounts.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('linkedAccounts.subtitle')}</Text>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <Text className="text-base font-bold" style={{ color: theme.text }}>{t('linkedAccounts.addTitle')}</Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('linkedAccounts.addDescription')}</Text>
            <Input
              value={email}
              onChangeText={setEmail}
              label={t('linkedAccounts.emailLabel')}
              placeholder={t('linkedAccounts.emailPlaceholder')}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
            />
            <HeroButton variant="primary" style={{ backgroundColor: primary }} onPress={sendRequest} isDisabled={isSending}>
              <HeroButton.Label>{isSending ? t('linkedAccounts.sending') : t('linkedAccounts.sendRequest')}</HeroButton.Label>
            </HeroButton>
          </HeroCard.Body>
        </HeroCard>

        {query.isLoading ? (
          <View className="items-center py-8"><Spinner size="lg" /></View>
        ) : query.error ? (
          <Surface variant="secondary" className="rounded-panel p-4">
            <Text className="text-sm" style={{ color: theme.textSecondary }}>{t('linkedAccounts.loadFailed')}</Text>
          </Surface>
        ) : (
          <>
            <RelationshipSection
              title={t('linkedAccounts.managedTitle')}
              subtitle={t('linkedAccounts.managedDescription')}
              empty={t('linkedAccounts.managedEmpty')}
              items={managed}
              canManagePermissions
              busyId={busyId}
              onApprove={approve}
              onRevoke={revoke}
              onTogglePermission={togglePermission}
            />
            <RelationshipSection
              title={t('linkedAccounts.managersTitle')}
              subtitle={t('linkedAccounts.managersDescription')}
              empty={t('linkedAccounts.managersEmpty')}
              items={managers}
              canApprove
              busyId={busyId}
              onApprove={approve}
              onRevoke={revoke}
              onTogglePermission={togglePermission}
            />
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function RelationshipSection({
  title,
  subtitle,
  empty,
  items,
  canManagePermissions,
  canApprove,
  busyId,
  onApprove,
  onRevoke,
  onTogglePermission,
}: {
  title: string;
  subtitle: string;
  empty: string;
  items: SubAccountRelationship[];
  canManagePermissions?: boolean;
  canApprove?: boolean;
  busyId: number | null;
  onApprove: (item: SubAccountRelationship) => void;
  onRevoke: (item: SubAccountRelationship) => void;
  onTogglePermission: (item: SubAccountRelationship, permission: SubAccountPermission) => void;
}) {
  const { t } = useTranslation('settings');
  const theme = useTheme();
  const primary = usePrimaryColor();

  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View>
          <Text className="text-base font-bold" style={{ color: theme.text }}>{title}</Text>
          <Text className="text-xs leading-4" style={{ color: theme.textSecondary }}>{subtitle}</Text>
        </View>
        {items.length === 0 ? (
          <Text className="text-sm" style={{ color: theme.textSecondary }}>{empty}</Text>
        ) : (
          <View className="gap-3">
            {items.map((item) => {
              const name = displayName(item, t('linkedAccounts.unknownMember'));
              const isBusy = busyId === item.relationship_id;
              return (
                <Surface key={item.relationship_id} variant="secondary" className="gap-3 rounded-panel-inner p-3">
                  <View className="flex-row items-start gap-3">
                    <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
                      <Ionicons name="person-outline" size={18} color={primary} />
                    </View>
                    <View className="min-w-0 flex-1">
                      <Text className="font-semibold" style={{ color: theme.text }} numberOfLines={1}>{name}</Text>
                      <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>{item.email}</Text>
                    </View>
                    <Chip size="sm" variant="secondary">
                      <Chip.Label>{t(`linkedAccounts.status.${item.status}`, { defaultValue: item.status })}</Chip.Label>
                    </Chip>
                  </View>

                  {canManagePermissions && item.status === 'active' ? (
                    <View className="gap-2">
                      <Text className="text-xs font-semibold" style={{ color: theme.text }}>{t('linkedAccounts.permissionsTitle')}</Text>
                      {PERMISSIONS.map((permission) => (
                        <Toggle
                          key={permission}
                          label={t(`linkedAccounts.permissions.${permission}`)}
                          accessibilityLabel={t('linkedAccounts.permissionToggle', {
                            permission: t(`linkedAccounts.permissions.${permission}`),
                            name,
                          })}
                          value={Boolean(item.permissions?.[permission])}
                          onValueChange={() => onTogglePermission(item, permission)}
                          disabled={isBusy}
                        />
                      ))}
                    </View>
                  ) : null}

                  <View className="flex-row gap-2">
                    {canApprove && item.status === 'pending' ? (
                      <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => onApprove(item)} isDisabled={isBusy}>
                        <HeroButton.Label>{t('linkedAccounts.approve')}</HeroButton.Label>
                      </HeroButton>
                    ) : null}
                    <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => onRevoke(item)} isDisabled={isBusy}>
                      <HeroButton.Label>{item.status === 'pending' && canApprove ? t('linkedAccounts.decline') : t('linkedAccounts.remove')}</HeroButton.Label>
                    </HeroButton>
                  </View>
                </Surface>
              );
            })}
          </View>
        )}
      </HeroCard.Body>
    </HeroCard>
  );
}
