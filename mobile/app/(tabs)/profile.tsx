// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  ActivityIndicator,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  Animated,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import {
  launchImageLibraryAsync,
  requestMediaLibraryPermissionsAsync,
  MediaTypeOptions,
} from 'expo-image-picker';
import * as Haptics from 'expo-haptics';
import { useState, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { type User } from '@/lib/api/auth';
import { updateAvatar } from '@/lib/api/profile';
import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import Avatar from '@/components/ui/Avatar';
import { ProfileSkeleton } from '@/components/ui/Skeleton';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
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
      Alert.alert(
        t('permissionNeeded'),
        t('permissionMessage'),
      );
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

  // Error state: user fetch failed (not loading, no user)
  if (!isAuthLoading && !user) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>{t('common:errors.generic')}</Text>
          <TouchableOpacity onPress={handleRefresh} style={styles.retryBtn}>
            <Text style={[styles.retryText, { color: primary }]}>{t('common:buttons.retry')}</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  // Loading state
  if (!user) {
    return (
      <SafeAreaView style={styles.container}>
        <ProfileSkeleton />
      </SafeAreaView>
    );
  }

  // balance is only present on the full User (from /users/me), not the LoginUser
  const rawBalance = 'balance' in user ? (user as User).balance : null;
  const balance = typeof rawBalance === 'number' && Number.isFinite(rawBalance) ? rawBalance : null;
  const bio = 'bio' in user ? (user as User).bio : null;

  return (
    <SafeAreaView style={styles.container}>
      <Animated.View style={[styles.stickyHeader, { opacity: headerBgOpacity }]} pointerEvents="none" />
      <Animated.ScrollView
        contentContainerStyle={styles.content}
        scrollEventThrottle={16}
        onScroll={Animated.event(
          [{ nativeEvent: { contentOffset: { y: scrollY } } }],
          { useNativeDriver: true },
        )}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={handleRefresh}
            tintColor={primary}
          />
        }
      >
        {/* Avatar + name */}
        <View style={styles.avatarSection}>
          <TouchableOpacity
            onPress={() => void pickAndUploadAvatar()}
            activeOpacity={0.8}
            disabled={uploading}
            style={styles.avatarWrapper}
            accessibilityLabel={t('changePhoto')}
            accessibilityRole="button"
          >
            <Animated.View style={{ transform: [{ scale: avatarScale }] }}>
            <Avatar uri={user.avatar_url} name={displayName} size={88} />
            <View style={styles.cameraOverlay}>
              {uploading ? (
                <ActivityIndicator size="small" color="#fff" />
              ) : (
                // contrast on primary
                <Ionicons name="camera-outline" size={20} color="#fff" />
              )}
            </View>
            </Animated.View>
          </TouchableOpacity>
          <Text style={styles.name}>{displayName}</Text>
          <Text style={styles.email}>{user.email}</Text>
        </View>

        {/* Time balance card — only shown once full profile loads */}
        {balance !== null && (
          <View style={[styles.balanceCard, { borderColor: primary }]}>
            <Text style={styles.balanceLabel}>{t('timeBalance')}</Text>
            <Text style={[styles.balanceValue, { color: primary }]}>
              {balance.toFixed(1)} {t('hrs')}
            </Text>
          </View>
        )}

        {/* Bio */}
        {bio && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle} accessibilityRole="header">{t('about')}</Text>
            <Text style={styles.bio}>{bio}</Text>
          </View>
        )}

        {/* Actions */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle} accessibilityRole="header">{t('actions')}</Text>
        <View style={styles.actions}>
          <TouchableOpacity
            style={[styles.actionButton, { borderColor: primary }]}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push('/(modals)/wallet');
            }}
            activeOpacity={0.7}
          >
            <Text style={[styles.actionButtonText, { color: primary }]}>{t('viewWallet')}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, { borderColor: theme.border }]}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push('/(modals)/edit-profile');
            }}
            activeOpacity={0.7}
          >
            <Text style={styles.actionButtonText}>{t('editProfile')}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, { borderColor: theme.border }]}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push('/(modals)/members');
            }}
            activeOpacity={0.7}
          >
            <Text style={styles.actionButtonText}>{t('browseMembers')}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, { borderColor: theme.border }]}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push('/(modals)/settings');
            }}
            activeOpacity={0.7}
          >
            <Text style={styles.actionButtonText}>{t('settings')}</Text>
          </TouchableOpacity>
        </View>
        </View>

        {/* Explore section */}
        <View style={styles.exploreSection}>
          <Text style={styles.sectionTitle} accessibilityRole="header">{t('explore')}</Text>
          <View style={styles.actions}>
            {EXPLORE_ITEMS.map(({ labelKey, route, icon }) => (
              <TouchableOpacity
                key={route}
                style={[styles.exploreButton, { borderColor: theme.border }]}
                onPress={() => {
                  void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  router.push(route as Parameters<typeof router.push>[0]);
                }}
                activeOpacity={0.7}
              >
                <Ionicons name={icon} size={18} color={primary} />
                <Text style={[styles.exploreButtonText, { color: theme.text }]}>{t(labelKey)}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        <View style={styles.actions}>
          <TouchableOpacity
            style={[styles.actionButton, styles.logoutButton]}
            onPress={confirmLogout}
            activeOpacity={0.7}
          >
            <Text style={styles.logoutText}>{t('signOut')}</Text>
          </TouchableOpacity>
        </View>

        {/* AGPL attribution — required by Section 7(b) */}
        <Text style={styles.attribution}>
          {t('common:attribution')}
        </Text>
      </Animated.ScrollView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    stickyHeader: {
      position: 'absolute',
      top: 0,
      left: 0,
      right: 0,
      height: 100,
      backgroundColor: theme.surface,
      zIndex: 1,
    },
    content: { paddingHorizontal: SPACING.lg, paddingTop: SPACING.xl, paddingBottom: SPACING.xxl },
    avatarSection: { alignItems: 'center', marginBottom: SPACING.lg },
    avatarWrapper: { position: 'relative' },
    cameraOverlay: {
      position: 'absolute',
      bottom: 0,
      right: 0,
      backgroundColor: 'rgba(0,0,0,0.5)', // overlay
      borderRadius: 12,
      padding: SPACING.xs,
    },
    name: { ...TYPOGRAPHY.h2, color: theme.text, marginTop: 12 },
    email: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.textSecondary, marginTop: SPACING.xxs },
    balanceCard: {
      borderWidth: 2,
      borderRadius: RADIUS.lg,
      padding: 20,
      alignItems: 'center',
      backgroundColor: theme.surface,
      marginBottom: SPACING.lg,
    },
    balanceLabel: { ...TYPOGRAPHY.bodySmall, color: theme.textSecondary, marginBottom: SPACING.xs },
    balanceValue: { fontSize: 36, fontWeight: '700' },
    section: { marginBottom: SPACING.lg },
    sectionTitle: { ...TYPOGRAPHY.bodySmall, fontWeight: '600', color: theme.textSecondary, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 },
    bio: { ...TYPOGRAPHY.body, color: theme.text },
    actions: { gap: 12 },
    actionButton: {
      borderWidth: 1,
      borderRadius: RADIUS.md,
      paddingVertical: 13,
      alignItems: 'center',
      backgroundColor: theme.surface,
    },
    actionButtonText: { ...TYPOGRAPHY.button, color: theme.text },
    logoutButton: { borderColor: theme.error, backgroundColor: theme.errorBg },
    logoutText: { ...TYPOGRAPHY.button, color: theme.error },
    exploreSection: { marginTop: SPACING.lg, marginBottom: SPACING.lg },
    exploreButton: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      borderWidth: 1,
      borderRadius: RADIUS.md,
      paddingVertical: 12,
      paddingHorizontal: SPACING.md,
      backgroundColor: theme.surface,
    },
    exploreButtonText: { ...TYPOGRAPHY.button },
    attribution: {
      fontSize: 11,
      color: theme.textMuted,
      textAlign: 'center',
      marginTop: 40,
    },
    errorContainer: {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
      padding: SPACING.xl,
    },
    errorText: { ...TYPOGRAPHY.body, color: theme.error, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    retryText: { ...TYPOGRAPHY.button },
  });
}
