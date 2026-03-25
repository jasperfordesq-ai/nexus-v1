// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  Alert,
  RefreshControl,
  ActivityIndicator,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import { getGroup, joinGroup, leaveGroup } from '@/lib/api/groups';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function GroupDetailScreen() {
  const { t } = useTranslation('groups');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.invalidId')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!group) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </TouchableOpacity>
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
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.content}
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
        <View style={styles.titleRow}>
          <Text style={styles.title}>{group.name}</Text>
          <TouchableOpacity
            onPress={() => void handleShare()}
            style={{ padding: 4 }}
            activeOpacity={0.7}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </TouchableOpacity>
          {group.is_featured && (
            <View style={[styles.badge, { backgroundColor: withAlpha(primary, 0.13) }]}>
              <Text style={[styles.badgeText, { color: primary }]}>{t('featured')}</Text>
            </View>
          )}
        </View>

        {/* Visibility badge */}
        <View style={styles.visibilityRow}>
          <Ionicons
            name={group.visibility === 'private' ? 'lock-closed-outline' : 'globe-outline'}
            size={14}
            color={theme.textSecondary}
          />
          <Text style={styles.visibilityText}>
            {group.visibility === 'private' ? t('private') : t('public')}
          </Text>
        </View>

        {/* Stats row */}
        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Ionicons name="people-outline" size={15} color={theme.textSecondary} />
            <Text style={styles.statText}>
              {t('members', { count: currentMemberCount })}
            </Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Ionicons name="document-text-outline" size={15} color={theme.textSecondary} />
            <Text style={styles.statText}>
              {t('posts', { count: group.posts_count ?? 0 })}
            </Text>
          </View>
        </View>

        {/* Join / Leave button */}
        <TouchableOpacity
          style={[
            styles.memberBtn,
            currentIsMember
              ? { backgroundColor: theme.surface, borderColor: theme.border, borderWidth: 1 }
              : { backgroundColor: primary },
            isUpdating && styles.memberBtnDisabled,
          ]}
          onPress={currentIsMember ? () => void handleLeave() : () => void handleJoin()}
          disabled={isUpdating}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel={currentIsMember ? t('leave') : t('join')}
        >
          {isUpdating ? (
            <ActivityIndicator
              color={currentIsMember ? theme.text : '#fff'} // contrast on primary
              size="small"
            />
          ) : (
            <Text
              style={[
                styles.memberBtnText,
                { color: currentIsMember ? theme.text : '#fff' }, // contrast on primary
              ]}
            >
              {currentIsMember ? t('leave') : t('join')}
            </Text>
          )}
        </TouchableOpacity>

        {/* Description */}
        {displayDescription ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.about')}</Text>
            <Text style={styles.description}>{displayDescription}</Text>
          </View>
        ) : null}

        {/* Tags */}
        {group.tags && group.tags.length > 0 ? (
          <View style={styles.section}>
            <View style={styles.tagsRow}>
              {group.tags.map((tag) => (
                <View key={tag} style={[styles.tagPill, { backgroundColor: theme.surface, borderColor: theme.border }]}>
                  <Text style={[styles.tagText, { color: theme.textSecondary }]}>{tag}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Admin */}
        {group.admin ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.members')}</Text>
            <View style={styles.adminRow}>
              <Avatar uri={group.admin.avatar_url ?? undefined} name={group.admin.name ?? '?'} size={36} />
              <Text style={styles.adminName}>{group.admin.name ?? t('common:unknown')}</Text>
            </View>
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { padding: 20, paddingBottom: 48 },
    titleRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      gap: 10,
      marginBottom: 8,
      flexWrap: 'wrap',
    },
    title: { flex: 1, ...TYPOGRAPHY.h2, color: theme.text },
    badge: {
      alignSelf: 'flex-start',
      borderRadius: SPACING.sm,
      paddingHorizontal: 10,
      paddingVertical: 4,
      marginTop: 2,
    },
    badgeText: { ...TYPOGRAPHY.caption, fontWeight: '600' },
    visibilityRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 5,
      marginBottom: SPACING.md,
    },
    visibilityText: { ...TYPOGRAPHY.bodySmall, color: theme.textSecondary },
    statsRow: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: RADIUS.lg,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      marginBottom: SPACING.md,
    },
    statItem: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 6, justifyContent: 'center' },
    statText: { ...TYPOGRAPHY.label, color: theme.textSecondary },
    statDivider: { width: 1, height: 20, backgroundColor: theme.border },
    memberBtn: {
      borderRadius: 12,
      paddingVertical: 13,
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: 24,
      minHeight: 46,
    },
    memberBtnDisabled: { opacity: 0.5 },
    memberBtnText: { ...TYPOGRAPHY.body, fontWeight: '700' },
    section: { marginBottom: SPACING.lg },
    sectionTitle: {
      ...TYPOGRAPHY.caption,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: 10,
    },
    description: { ...TYPOGRAPHY.body, color: theme.text },
    tagsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
    tagPill: {
      borderRadius: RADIUS.xl,
      borderWidth: 1,
      paddingHorizontal: 12,
      paddingVertical: 5,
    },
    tagText: { ...TYPOGRAPHY.bodySmall, fontWeight: '500' },
    adminRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    adminName: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    errorText: { ...TYPOGRAPHY.body, color: theme.textMuted },
  });
}
