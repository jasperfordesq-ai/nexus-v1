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
  acceptConnection,
  getConnections,
  removeConnection,
  type Connection,
  type ConnectionListResponse,
  type ConnectionListStatus,
} from '@/lib/api/connections';
import { displayName } from '@/lib/api/messages';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type ConnectionTab = ConnectionListStatus;

const tabs: ConnectionTab[] = ['accepted', 'pending_received', 'pending_sent'];

function unwrapConnections(response: ConnectionListResponse | Connection[] | null | undefined): Connection[] {
  if (!response) return [];
  if (Array.isArray(response)) return response;
  return Array.isArray(response.data) ? response.data : [];
}

function connectionId(connection: Connection): number {
  return connection.connection_id ?? connection.id ?? 0;
}

function formatDate(value?: string | null) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function ConnectionsRoute() {
  return (
    <ModalErrorBoundary>
      <ConnectionsScreen />
    </ModalErrorBoundary>
  );
}

function ConnectionsScreen() {
  const { t } = useTranslation(['members', 'common']);
  const [tab, setTab] = useState<ConnectionTab>('accepted');
  const [actionId, setActionId] = useState<number | null>(null);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { data, isLoading, error, refresh } = useApi(() => getConnections(tab), [tab]);
  const connections = useMemo(() => unwrapConnections(data), [data]);

  async function runAction(connection: Connection, action: 'accept' | 'remove') {
    const id = connectionId(connection);
    if (!id) return;
    setActionId(id);
    try {
      if (action === 'accept') await acceptConnection(id);
      if (action === 'remove') await removeConnection(id);
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      refresh();
    } catch {
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('connections.actionFailedTitle'), t('connections.actionFailedDescription'));
    } finally {
      setActionId(null);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('connections.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
      <ScrollView
        refreshControl={<RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />}
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      >
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                <Ionicons name="people-outline" size={25} color={primary} />
              </View>
              <View className="min-w-0 flex-1 gap-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('connections.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('connections.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('connections.subtitle')}</Text>
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
                    <Tabs.Label>{t(`connections.tabs.${item}`)}</Tabs.Label>
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
            <HeroButton variant="secondary" onPress={refresh}><HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label></HeroButton>
          </Surface>
        ) : connections.length === 0 ? (
          <Surface variant="secondary" className="rounded-panel p-5">
            <EmptyState icon="people-outline" title={t(`connections.empty.${tab}.title`)} subtitle={t(`connections.empty.${tab}.description`)} />
            {tab === 'accepted' ? (
              <HeroButton className="mt-4" variant="primary" style={{ backgroundColor: primary }} onPress={() => router.push('/(modals)/members' as Href)}>
                <Ionicons name="search-outline" size={16} color="#fff" />
                <HeroButton.Label>{t('connections.browseMembers')}</HeroButton.Label>
              </HeroButton>
            ) : null}
          </Surface>
        ) : (
          <View className="gap-3">
            {connections.map((connection) => {
              const id = connectionId(connection);
              return (
                <ConnectionCard
                  key={id || `${connection.user.id}-${connection.status}`}
                  connection={connection}
                  tab={tab}
                  theme={theme}
                  primary={primary}
                  t={t}
                  isActioning={actionId === id}
                  onAction={(action) => void runAction(connection, action)}
                />
              );
            })}
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
  connection: Connection;
  tab: ConnectionTab;
  theme: ReturnType<typeof useTheme>;
  primary: string;
  t: (key: string, opts?: Record<string, unknown>) => string;
  isActioning: boolean;
  onAction: (action: 'accept' | 'remove') => void;
}) {
  const userName = displayName(connection.user, t('connections.unknownMember'));
  const id = connectionId(connection);
  const connectedDate = formatDate(connection.created_at);

  function openProfile() {
    router.push({ pathname: '/(modals)/member-profile', params: { id: String(connection.user.id) } } as unknown as Href);
  }

  function openMessage() {
    router.push({ pathname: '/(modals)/thread', params: { recipientId: String(connection.user.id), name: userName } } as unknown as Href);
  }

  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start gap-3">
          <Avatar uri={connection.user.avatar_url ?? null} name={userName} size={50} />
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>{userName}</Text>
            {connection.user.location ? <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>{connection.user.location}</Text> : null}
            {connection.user.bio ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>{connection.user.bio}</Text> : null}
            {connectedDate ? <Text className="text-xs" style={{ color: theme.textMuted }}>{t('connections.connectedSince', { date: connectedDate })}</Text> : null}
          </View>
          <HeroButton isIconOnly variant="secondary" accessibilityLabel={t('connections.viewProfile', { name: userName })} onPress={openProfile}>
            <Ionicons name="chevron-forward-outline" size={18} color={primary} />
          </HeroButton>
        </View>
        <View className="flex-row flex-wrap gap-2">
          <Chip size="sm" variant="secondary"><Chip.Label>{t(`connections.status.${connection.status}`)}</Chip.Label></Chip>
        </View>
        <View className="flex-row flex-wrap gap-2">
          {tab === 'accepted' ? (
            <>
              <HeroButton size="sm" variant="secondary" onPress={openMessage}>
                <Ionicons name="chatbubble-outline" size={14} color={primary} />
                <HeroButton.Label>{t('connections.message')}</HeroButton.Label>
              </HeroButton>
              <HeroButton size="sm" variant="secondary" onPress={() => onAction('remove')} isDisabled={isActioning || !id}>
                {isActioning ? <Spinner size="sm" /> : <Ionicons name="trash-outline" size={14} color={theme.error} />}
                <HeroButton.Label>{t('connections.remove')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
          {tab === 'pending_received' ? (
            <>
              <HeroButton size="sm" variant="primary" onPress={() => onAction('accept')} isDisabled={isActioning || !id} style={{ backgroundColor: primary }}>
                {isActioning ? <Spinner size="sm" /> : <Ionicons name="checkmark-outline" size={14} color="#fff" />}
                <HeroButton.Label>{t('connections.accept')}</HeroButton.Label>
              </HeroButton>
              <HeroButton size="sm" variant="secondary" onPress={() => onAction('remove')} isDisabled={isActioning || !id}>
                <Ionicons name="close-outline" size={14} color={theme.error} />
                <HeroButton.Label>{t('connections.decline')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
          {tab === 'pending_sent' ? (
            <HeroButton size="sm" variant="secondary" onPress={() => onAction('remove')} isDisabled={isActioning || !id}>
              {isActioning ? <Spinner size="sm" /> : <Ionicons name="close-circle-outline" size={14} color={theme.error} />}
              <HeroButton.Label>{t('connections.cancel')}</HeroButton.Label>
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
