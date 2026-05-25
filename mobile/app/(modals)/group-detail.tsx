// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  Alert,
  RefreshControl,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import { getGroup, joinGroup, leaveGroup } from '@/lib/api/groups';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function GroupDetailScreen() {
  return (
    <ModalErrorBoundary>
      <GroupDetailScreenInner />
    </ModalErrorBoundary>
  );
}

function GroupDetailScreenInner() {
  const { t } = useTranslation('groups');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const groupId = Number(id);
  const safeGroupId = isNaN(groupId) || groupId <= 0 ? 0 : groupId;

  const { data, isLoading, refresh } = useApi(
    () => getGroup(safeGroupId),
    [safeGroupId],
    { enabled: safeGroupId > 0 },
  );

  const group = data?.data ?? null;

  // Optimistic membership state
  const [isMember, setIsMember] = useState<boolean | null>(null);
  const [memberCount, setMemberCount] = useState<number | null>(null);
  const [joining, setJoining] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  // Sync local state from server data once loaded
  useEffect(() => {
    if (group) {
      setIsMember(group.is_member);
      setMemberCount(group.member_count);
    }
  }, [group]);

  const handleRefresh = useCallback(() => {
    setRefreshing(true);
    refresh();
    // isLoading will flip back to true; we watch it to clear refreshing
  }, [refresh]);

  // Clear the RefreshControl spinner once useApi finishes reloading
  useEffect(() => {
    if (!isLoading) {
      setRefreshing(false);
    }
  }, [isLoading]);

  async function handleShare() {
    if (!group) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${group.name} — ${WEB_URL}/groups/${group.id}`,
      });
    } catch { /* ignore */ }
  }

  if (isNaN(groupId) || groupId <= 0) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.invalidId')}</Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!group) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  const currentIsMember = isMember ?? group.is_member;
  const currentMemberCount = memberCount ?? group.member_count;
  const isUpdating = joining || leaving;

  async function handleJoin() {
    if (!group) return;
    const prevIsMember = isMember ?? group.is_member;
    const prevMemberCount = memberCount ?? group.member_count;
    setJoining(true);
    setIsMember(true);
    setMemberCount(prevMemberCount + 1);
    try {
      await joinGroup(group.id);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      setIsMember(prevIsMember);
      setMemberCount(prevMemberCount);
      Alert.alert(t('common:errors.alertTitle'), t('joinError'));
    } finally {
      setJoining(false);
    }
  }

  async function handleLeave() {
    if (!group) return;
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    Alert.alert(t('leaveConfirmTitle'), t('leaveConfirmMessage'), [
      { text: t('common:buttons.cancel'), style: 'cancel' },
      {
        text: t('leave'),
        style: 'destructive',
        onPress: async () => {
          const prevIsMember = isMember ?? group.is_member;
          const prevMemberCount = memberCount ?? group.member_count;
          setLeaving(true);
          setIsMember(false);
          setMemberCount(Math.max(0, prevMemberCount - 1));
          try {
            await leaveGroup(group.id);
          } catch {
            void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            setIsMember(prevIsMember);
            setMemberCount(prevMemberCount);
            Alert.alert(t('common:errors.alertTitle'), t('leaveError'));
          } finally {
            setLeaving(false);
          }
        },
      },
    ]);
  }

  const displayDescription = group.long_description ?? group.description;

  return (
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView
        contentContainerStyle={{ padding: 20, paddingBottom: 48 }}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => void handleRefresh()}
            tintColor={primary}
            colors={[primary]}
          />
        }
      >
        {/* Title row */}
        <View className="flex-row items-start gap-[10px] mb-2 flex-wrap">
          <Text className="flex-1 text-xl font-bold text-foreground">{group.name}</Text>
          <Pressable
            onPress={() => void handleShare()}
            style={{ padding: 4 }}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </Pressable>
          {group.is_featured && (
            <View
              className="self-start rounded-lg px-[10px] py-1 mt-0.5"
              style={{ backgroundColor: withAlpha(primary, 0.13) }}
            >
              <Text className="text-xs font-semibold" style={{ color: primary }}>{t('featured')}</Text>
            </View>
          )}
        </View>

        {/* Visibility badge */}
        <View className="flex-row items-center gap-[5px] mb-4">
          <Ionicons
            name={group.visibility === 'private' ? 'lock-closed-outline' : 'globe-outline'}
            size={14}
            color={theme.textSecondary}
          />
          <Text className="text-xs text-muted-foreground">
            {group.visibility === 'private' ? t('private') : t('public')}
          </Text>
        </View>

        {/* Stats row */}
        <View className="flex-row items-center bg-surface rounded-xl p-4 border border-border/50 mb-4">
          <View className="flex-1 flex-row items-center gap-[6px] justify-center">
            <Ionicons name="people-outline" size={15} color={theme.textSecondary} />
            <Text className="text-sm font-medium text-muted-foreground">
              {t('members', { count: currentMemberCount })}
            </Text>
          </View>
          <View className="w-px h-5 bg-border" />
          <View className="flex-1 flex-row items-center gap-[6px] justify-center">
            <Ionicons name="document-text-outline" size={15} color={theme.textSecondary} />
            <Text className="text-sm font-medium text-muted-foreground">
              {t('posts', { count: group.posts_count ?? 0 })}
            </Text>
          </View>
        </View>

        {/* Join / Leave button */}
        <Pressable
          className="rounded-xl py-[13px] items-center justify-center mb-6 min-h-[46px]"
          style={[
            currentIsMember
              ? { backgroundColor: theme.surface, borderColor: theme.border, borderWidth: 1 }
              : { backgroundColor: primary },
            isUpdating ? { opacity: 0.5 } : undefined,
          ]}
          onPress={currentIsMember ? () => void handleLeave() : () => void handleJoin()}
          disabled={isUpdating}
          accessibilityRole="button"
          accessibilityLabel={currentIsMember ? t('leave') : t('join')}
        >
          {isUpdating ? (
            <Spinner size="sm" />
          ) : (
            <Text
              className="text-sm font-bold"
              style={{ color: currentIsMember ? theme.text : '#fff' }}
            >
              {currentIsMember ? t('leave') : t('join')}
            </Text>
          )}
        </Pressable>

        {/* Description */}
        {displayDescription ? (
          <View className="mb-6">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wide mb-[10px]">
              {t('detail.about')}
            </Text>
            <Text className="text-sm text-foreground">{displayDescription}</Text>
          </View>
        ) : null}

        {/* Tags */}
        {group.tags && group.tags.length > 0 ? (
          <View className="mb-6">
            <View className="flex-row flex-wrap gap-2">
              {group.tags.map((tag) => (
                <View
                  key={tag}
                  className="rounded-full border px-3 py-[5px]"
                  style={{ backgroundColor: theme.surface, borderColor: theme.border }}
                >
                  <Text className="text-xs font-medium text-muted-foreground">{tag}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Admin */}
        {group.admin ? (
          <View className="mb-6">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wide mb-[10px]">
              {t('detail.members')}
            </Text>
            <View className="flex-row items-center gap-3">
              <Avatar uri={group.admin.avatar_url ?? undefined} name={group.admin.name ?? '?'} size={36} />
              <Text className="text-sm font-semibold text-foreground">
                {group.admin.name ?? t('common:unknown')}
              </Text>
            </View>
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}
