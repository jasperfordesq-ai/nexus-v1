// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useState, type ComponentProps } from 'react';
import { Alert, RefreshControl, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface, Tabs, Text } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  acceptFederationConnection,
  getFederationConnections,
  rejectFederationConnection,
  removeFederationConnection,
  type FederationConnection,
} from '@/lib/api/federation';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type ConnectionTab = 'accepted' | 'pending_received' | 'pending_sent';

const tabs: ConnectionTab[] = ['accepted', 'pending_received', 'pending_sent'];

function unwrapConnections(response: { data?: FederationConnection[] } | FederationConnection[] | null | undefined): FederationConnection[] {
  if (!response) return [];
  if (Array.isArray(response)) return response;
  return Array.isArray(response.data) ? response.data : [];
}

function formatDate(value?: string | null) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function FederationConnectionsRoute() {
  return (
    <ModalErrorBoundary>
      <FederationConnectionsScreen />
    </ModalErrorBoundary>
  );
}

function FederationConnectionsScreen() {
  const { t } = useTranslation(['federation', 'common']);
  const [tab, setTab] = useState<ConnectionTab>('accepted');
  const [actionId, setActionId] = useState<number | null>(null);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { data, isLoading, error, refresh } = useApi(() => getFederationConnections(tab), [tab]);
  const connections = useMemo(() => unwrapConnections(data), [data]);

  async function runAction(connection: FederationConnection, action: 'accept' | 'reject' | 'remove') {
    setActionId(connection.id);
    try {
      if (action === 'accept') await acceptFederationConnection(connection.id);
      if (action === 'reject') await rejectFederationConnection(connection.id);
      if (action === 'remove') await removeFederationConnection(connection.id);
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      refresh();
    } catch {
      Alert.alert(t('directory.connections.actionFailedTitle'), t('directory.connections.actionFailedDescription'));
    } finally {
      setActionId(null);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('directory.connections.title')} backLabel={t('common:back')} fallbackHref="/(modals)/federation" />
      <ScrollView
        refreshControl={<RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />}
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      >
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: '#14b8a6' }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#14b8a6', 0.14) }}>
                <Ionicons name="person-add-outline" size={25} color="#14b8a6" />
              </View>
              <View className="min-w-0 flex-1 gap-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('directory.connections.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('directory.connections.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('directory.connections.subtitle')}</Text>
              </View>
            </View>
            <Tabs value={tab} onValueChange={(value) => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              setTab(value as ConnectionTab);
            }} variant="secondary">
              <Tabs.List>
                <Tabs.Indicator />
                {tabs.map((item) => (
                  <Tabs.Trigger key={item} value={item}>
                    <Ionicons name={tabIcon(item)} size={15} color={tab === item ? primary : theme.textMuted} />
                    <Tabs.Label>{t(`directory.connections.tabs.${item}`)}</Tabs.Label>
                  </Tabs.Trigger>
                ))}
              </Tabs.List>
            </Tabs>
          </HeroCard.Body>
        </HeroCard>

        {isLoading ? (
          <View className="items-center py-8"><Spinner size="lg" /></View>
        ) : error ? (
          <Surface variant="secondary" className="items-center gap-3 rounded-panel p-5">
            <Ionicons name="alert-circle-outline" size={28} color={theme.error} />
            <Text className="text-center text-sm" style={{ color: theme.text }}>{error}</Text>
            <HeroButton variant="secondary" onPress={refresh}><HeroButton.Label>{t('directory.tryAgain')}</HeroButton.Label></HeroButton>
          </Surface>
        ) : connections.length === 0 ? (
          <Surface variant="secondary" className="rounded-panel p-5">
            <EmptyState icon="people-outline" title={t(`directory.connections.empty.${tab}.title`)} subtitle={t(`directory.connections.empty.${tab}.description`)} />
            {tab === 'accepted' ? (
              <HeroButton className="mt-4" variant="primary" style={{ backgroundColor: primary }} onPress={() => router.push('/(modals)/federation-members' as Href)}>
                <Ionicons name="globe-outline" size={16} color="#fff" />
                <HeroButton.Label>{t('directory.connections.browseMembers')}</HeroButton.Label>
              </HeroButton>
            ) : null}
          </Surface>
        ) : (
          <View className="gap-3">
            {connections.map((connection) => (
              <ConnectionCard
                key={connection.id}
                connection={connection}
                tab={tab}
                theme={theme}
                primary={primary}
                t={t}
                isActioning={actionId === connection.id}
                onAction={(action) => void runAction(connection, action)}
              />
            ))}
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function ConnectionCard({
  connection,
  tab,
  theme,
  primary,
  t,
  isActioning,
  onAction,
}: {
  connection: FederationConnection;
  tab: ConnectionTab;
  theme: ReturnType<typeof useTheme>;
  primary: string;
  t: (key: string, opts?: Record<string, unknown>) => string;
  isActioning: boolean;
  onAction: (action: 'accept' | 'reject' | 'remove') => void;
}) {
  function openProfile() {
    router.push({ pathname: '/(modals)/federation-member', params: { id: String(connection.user_id), tenant_id: String(connection.tenant_id) } } as unknown as Href);
  }

  function openMessage() {
    router.push({
      pathname: '/(modals)/federation-messages',
      params: {
        compose: 'true',
        to_user: String(connection.user_id),
        to_tenant: String(connection.tenant_id),
        name: connection.name,
        community: connection.tenant_name,
      },
    } as unknown as Href);
  }

  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start gap-3">
            <Avatar uri={connection.avatar_url ?? null} name={connection.name} size={50} />
            <View className="min-w-0 flex-1 gap-1">
              <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>{connection.name}</Text>
              <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>{connection.tenant_name}</Text>
              {connection.message ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>{connection.message}</Text> : null}
            </View>
            <HeroButton isIconOnly variant="secondary" accessibilityLabel={t('directory.connections.viewProfile', { name: connection.name })} onPress={openProfile}>
              <Ionicons name="chevron-forward-outline" size={18} color={primary} />
            </HeroButton>
          </View>
        <View className="flex-row flex-wrap gap-2">
          <Chip size="sm" variant="secondary"><Chip.Label>{t(`directory.connections.status.${connection.status}`)}</Chip.Label></Chip>
          <Chip size="sm" variant="secondary"><Chip.Label>{formatDate(connection.created_at)}</Chip.Label></Chip>
        </View>
        <View className="flex-row flex-wrap gap-2">
          {tab === 'accepted' ? (
            <>
              <HeroButton size="sm" variant="secondary" onPress={openMessage}>
                <Ionicons name="chatbubble-outline" size={14} color={primary} />
                <HeroButton.Label>{t('directory.connections.message')}</HeroButton.Label>
              </HeroButton>
              <HeroButton size="sm" variant="secondary" onPress={() => onAction('remove')} isDisabled={isActioning}>
                {isActioning ? <Spinner size="sm" /> : <Ionicons name="trash-outline" size={14} color={theme.error} />}
                <HeroButton.Label>{t('directory.connections.remove')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
          {tab === 'pending_received' ? (
            <>
              <HeroButton size="sm" variant="primary" onPress={() => onAction('accept')} isDisabled={isActioning} style={{ backgroundColor: primary }}>
                {isActioning ? <Spinner size="sm" /> : <Ionicons name="checkmark-outline" size={14} color="#fff" />}
                <HeroButton.Label>{t('directory.connections.accept')}</HeroButton.Label>
              </HeroButton>
              <HeroButton size="sm" variant="secondary" onPress={() => onAction('reject')} isDisabled={isActioning}>
                <Ionicons name="close-outline" size={14} color={theme.error} />
                <HeroButton.Label>{t('directory.connections.reject')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
          {tab === 'pending_sent' ? (
            <HeroButton size="sm" variant="secondary" onPress={() => onAction('remove')} isDisabled={isActioning}>
              {isActioning ? <Spinner size="sm" /> : <Ionicons name="close-circle-outline" size={14} color={theme.error} />}
              <HeroButton.Label>{t('directory.connections.cancel')}</HeroButton.Label>
            </HeroButton>
          ) : null}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function tabIcon(tab: ConnectionTab): ComponentProps<typeof Ionicons>['name'] {
  if (tab === 'accepted') return 'people-outline';
  if (tab === 'pending_received') return 'mail-unread-outline';
  return 'send-outline';
}
