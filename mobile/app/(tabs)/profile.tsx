// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Alert, Animated, Pressable, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import {
  launchImageLibraryAsync,
  requestMediaLibraryPermissionsAsync,
  MediaTypeOptions,
} from 'expo-image-picker';
import * as Haptics from 'expo-haptics';
import { useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { type User } from '@/lib/api/auth';
import { updateAvatar } from '@/lib/api/profile';
import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import Avatar from '@/components/ui/Avatar';
import { ProfileSkeleton } from '@/components/ui/Skeleton';

const EXPLORE_ITEMS: { labelKey: string; route: string; icon: React.ComponentProps<typeof Ionicons>['name'] }[] = [
  { labelKey: 'groups',        route: '/(modals)/groups',           icon: 'people-outline' },
  { labelKey: 'search',        route: '/(modals)/search',           icon: 'search-outline' },
  { labelKey: 'aiChat',        route: '/(modals)/chat',            icon: 'chatbubbles-outline' },
  { labelKey: 'achievements',  route: '/(modals)/gamification',    icon: 'trophy-outline' },
  { labelKey: 'myGoals',       route: '/(modals)/goals',           icon: 'flag-outline' },
  { labelKey: 'volunteering',  route: '/(modals)/volunteering',    icon: 'hand-left-outline' },
  { labelKey: 'organisations', route: '/(modals)/organisations',   icon: 'business-outline' },
  { labelKey: 'blog',          route: '/(modals)/blog',            icon: 'newspaper-outline' },
  { labelKey: 'skills',        route: '/(modals)/endorsements',    icon: 'ribbon-outline' },
  { labelKey: 'federation',    route: '/(modals)/federation',      icon: 'globe-outline' },
];

export default function ProfileScreen() {
  const { t } = useTranslation('profile');
  const { user, displayName, logout, refreshUser, isLoading: isAuthLoading } = useAuth();
  const primary = usePrimaryColor();
  const [uploading, setUploading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const scrollY = useRef(new Animated.Value(0)).current;

  const avatarScale = scrollY.interpolate({
    inputRange: [0, 150],
    outputRange: [1, 0.7],
    extrapolate: 'clamp',
  });

  const headerBgOpacity = scrollY.interpolate({
    inputRange: [0, 100],
    outputRange: [0, 1],
    extrapolate: 'clamp',
  });

  async function handleRefresh() {
    setRefreshing(true);
    try {
      const res = await api.get<{ data: User }>(`${API_V2}/users/me`);
      refreshUser(res.data);
    } catch {
      // Non-critical
    } finally {
      setRefreshing(false);
    }
  }

  async function pickAndUploadAvatar() {
    const { status } = await requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert(t('permissionNeeded'), t('permissionMessage'));
      return;
    }

    const result = await launchImageLibraryAsync({
      mediaTypes: MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });

    if (result.canceled || !result.assets?.[0]) return;

    const uri = result.assets[0].uri;
    setUploading(true);
    try {
      const response = await updateAvatar(uri);
      if (user) {
        refreshUser({ ...user, avatar_url: response.data.avatar_url });
      }
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('uploadFailed'), t('uploadFailedMessage'));
    } finally {
      setUploading(false);
    }
  }

  function confirmLogout() {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    Alert.alert(
      t('signOutConfirmTitle'),
      t('signOutConfirmMessage'),
      [
        { text: t('common:buttons.cancel'), style: 'cancel' },
        { text: t('signOut'), style: 'destructive', onPress: () => void logout() },
      ],
    );
  }

  if (!isAuthLoading && !user) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <View className="flex-1 items-center justify-center p-8">
          <Text className="text-danger text-sm text-center mb-3">{t('common:errors.generic')}</Text>
          <Pressable onPress={handleRefresh} className="px-5 py-2.5">
            <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
          </Pressable>
        </View>
      </SafeAreaView>
    );
  }

  if (!user) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <ProfileSkeleton />
      </SafeAreaView>
    );
  }

  const rawBalance = 'balance' in user ? (user as User).balance : null;
  const balance = typeof rawBalance === 'number' && Number.isFinite(rawBalance) ? rawBalance : null;
  const bio = 'bio' in user ? (user as User).bio : null;

  return (
    <SafeAreaView className="flex-1 bg-background">
      {/* Animated sticky header overlay */}
      <Animated.View
        className="absolute top-0 left-0 right-0 h-24 bg-surface z-10"
        style={{ opacity: headerBgOpacity }}
        pointerEvents="none"
      />

      <Animated.ScrollView
        contentContainerStyle={{ paddingHorizontal: 24, paddingTop: 32, paddingBottom: 48 }}
        scrollEventThrottle={16}
        onScroll={Animated.event(
          [{ nativeEvent: { contentOffset: { y: scrollY } } }],
          { useNativeDriver: true },
        )}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={primary} />
        }
      >
        {/* Avatar + name */}
        <View className="items-center mb-6">
          <Pressable
            onPress={() => void pickAndUploadAvatar()}
            disabled={uploading}
            className="relative"
            accessibilityLabel={t('changePhoto')}
            accessibilityRole="button"
          >
            <Animated.View style={{ transform: [{ scale: avatarScale }] }}>
              <Avatar uri={user.avatar_url} name={displayName} size={88} />
              <View className="absolute bottom-0 right-0 rounded-xl p-1.5 bg-black/50">
                {uploading ? (
                  <Spinner size="sm" />
                ) : (
                  <Ionicons name="camera-outline" size={20} color="#fff" />
                )}
              </View>
            </Animated.View>
          </Pressable>
          <Text className="text-xl font-bold text-foreground mt-3">{displayName}</Text>
          <Text className="text-sm text-muted-foreground mt-0.5">{user.email}</Text>
        </View>

        {/* Time balance card */}
        {balance !== null ? (
          <View
            className="border-2 rounded-xl p-5 items-center bg-surface mb-6"
            style={{ borderColor: primary }}
          >
            <Text className="text-xs text-muted-foreground mb-1">{t('timeBalance')}</Text>
            <Text className="text-[36px] font-bold" style={{ color: primary }}>
              {balance.toFixed(1)} {t('hrs')}
            </Text>
          </View>
        ) : null}

        {/* Bio */}
        {bio ? (
          <View className="mb-6">
            <Text className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-1.5" accessibilityRole="header">
              {t('about')}
            </Text>
            <Text className="text-sm text-foreground leading-5">{bio}</Text>
          </View>
        ) : null}

        {/* Actions */}
        <View className="mb-6">
          <Text className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-1.5" accessibilityRole="header">
            {t('actions')}
          </Text>
          <View className="gap-3">
            <Pressable
              className="border rounded-xl py-3.5 items-center bg-surface"
              style={{ borderColor: primary }}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/wallet');
              }}
            >
              <Text className="font-semibold" style={{ color: primary }}>{t('viewWallet')}</Text>
            </Pressable>

            <Pressable
              className="border border-border rounded-xl py-3.5 items-center bg-surface"
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/edit-profile');
              }}
            >
              <Text className="font-semibold text-foreground">{t('editProfile')}</Text>
            </Pressable>

            <Pressable
              className="border border-border rounded-xl py-3.5 items-center bg-surface"
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/members');
              }}
            >
              <Text className="font-semibold text-foreground">{t('browseMembers')}</Text>
            </Pressable>

            <Pressable
              className="border border-border rounded-xl py-3.5 items-center bg-surface"
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/settings');
              }}
            >
              <Text className="font-semibold text-foreground">{t('settings')}</Text>
            </Pressable>
          </View>
        </View>

        {/* Explore section */}
        <View className="mb-6">
          <Text className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-1.5" accessibilityRole="header">
            {t('explore')}
          </Text>
          <View className="gap-3">
            {EXPLORE_ITEMS.map(({ labelKey, route, icon }) => (
              <Pressable
                key={route}
                className="flex-row items-center gap-2.5 border border-border rounded-xl py-3 px-4 bg-surface"
                onPress={() => {
                  void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  router.push(route as Parameters<typeof router.push>[0]);
                }}
              >
                <Ionicons name={icon} size={18} color={primary} />
                <Text className="font-semibold text-foreground">{t(labelKey)}</Text>
              </Pressable>
            ))}
          </View>
        </View>

        {/* Sign out */}
        <Pressable
          className="border border-danger rounded-xl py-3.5 items-center bg-danger/10 mb-4"
          onPress={confirmLogout}
        >
          <Text className="font-semibold text-danger">{t('signOut')}</Text>
        </Pressable>

        {/* AGPL attribution — required by Section 7(b) */}
        <Text className="text-[11px] text-muted-foreground text-center mt-10">
          {t('common:attribution')}
        </Text>
      </Animated.ScrollView>
    </SafeAreaView>
  );
}
