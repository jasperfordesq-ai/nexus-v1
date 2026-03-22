// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  RefreshControl,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
} from 'react-native';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import {
  getGamificationProfile,
  getBadges,
  getLeaderboard,
  type Badge,
  type GamificationProfile,
  type LeaderboardEntry,
} from '@/lib/api/gamification';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

type Tab = 'badges' | 'leaderboard';
type LeaderboardPeriod = 'weekly' | 'monthly' | 'all_time';

// ─── Sub-components ───────────────────────────────────────────────────────────

function XpBar({
  xp,
  nextLevelXp,
  primary,
  theme,
  styles,
}: {
  xp: number;
  nextLevelXp: number;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
}) {
  const percentage = nextLevelXp > 0 ? Math.min(100, Math.round((xp / nextLevelXp) * 100)) : 0;
  return (
    <View style={styles.xpBarTrack}>
      <View style={[styles.xpBarFill, { width: `${percentage}%` as `${number}%`, backgroundColor: primary }]} />
    </View>
  );
}

function ProfileSection({
  profile,
  primary,
  theme,
  styles,
  t,
}: {
  profile: GamificationProfile;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const toNextLevel = Math.max(0, profile.next_level_xp - profile.xp);
  return (
    <View style={[styles.profileCard, { borderColor: primary }]}>
      <View style={styles.profileTopRow}>
        <View style={[styles.levelBadge, { backgroundColor: primary }]}>
          <Text style={styles.levelBadgeText}>{t('gamification:level', { level: profile.level })}</Text>
        </View>
        {profile.rank !== null ? (
          <Text style={[styles.rankText, { color: primary }]}>{t('gamification:rank', { rank: profile.rank })}</Text>
        ) : (
          <Text style={styles.unrankedText}>{t('gamification:unranked')}</Text>
        )}
        {profile.streak_days > 0 && (
          <View style={styles.streakRow}>
            <Ionicons name="flame-outline" size={14} color={theme.textSecondary} />
            <Text style={styles.streakText}>{t('gamification:streak', { days: profile.streak_days })}</Text>
          </View>
        )}
      </View>
      <Text style={[styles.xpValue, { color: primary }]}>{t('gamification:xp', { xp: profile.xp })}</Text>
      <XpBar xp={profile.xp} nextLevelXp={profile.next_level_xp} primary={primary} theme={theme} styles={styles} />
      <Text style={styles.nextLevelText}>{t('gamification:nextLevel', { xp: toNextLevel })}</Text>
    </View>
  );
}

function BadgeCard({
  badge,
  primary,
  theme,
  styles,
  t,
}: {
  badge: Badge;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string) => string;
}) {
  const earnedDate = badge.earned_at
    ? new Date(badge.earned_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
    : null;

  return (
    <View style={[styles.badgeCard, { borderColor: badge.is_earned ? primary : theme.borderSubtle }]}>
      <View style={[styles.badgeIconWrap, { backgroundColor: badge.is_earned ? primary + '20' : theme.surface }]}>
        <Ionicons
          name={(badge.icon as React.ComponentProps<typeof Ionicons>['name']) ?? 'ribbon-outline'}
          size={28}
          color={badge.is_earned ? primary : theme.textMuted}
        />
      </View>
      <Text style={[styles.badgeName, { color: badge.is_earned ? theme.text : theme.textMuted }]} numberOfLines={2}>
        {badge.name}
      </Text>
      {badge.is_earned && earnedDate ? (
        <Text style={styles.badgeEarnedDate}>{t('gamification:badges.earned')}: {earnedDate}</Text>
      ) : (
        <Text style={styles.badgeLockedText}>{t('gamification:badges.locked')}</Text>
      )}
    </View>
  );
}

function LeaderboardRow({
  entry,
  isCurrentUser,
  primary,
  theme,
  styles,
  t,
}: {
  entry: LeaderboardEntry;
  isCurrentUser: boolean;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string) => string;
}) {
  return (
    <View style={[styles.lbRow, isCurrentUser && { backgroundColor: primary + '15', borderRadius: 10 }]}>
      <Text style={[styles.lbRank, { color: isCurrentUser ? primary : theme.textSecondary }]}>
        #{entry.rank}
      </Text>
      <Avatar uri={entry.user.avatar} name={entry.user.name} size={36} />
      <View style={styles.lbBody}>
        <Text style={styles.lbName} numberOfLines={1}>
          {entry.user.name}
          {isCurrentUser ? ` (${t('gamification:leaderboard.you')})` : ''}
        </Text>
        <Text style={styles.lbMeta}>
          {t('gamification:level', { level: entry.level })} · {t('gamification:leaderboard.badgesCount', { count: entry.badges_count })}
        </Text>
      </View>
      <Text style={[styles.lbXp, { color: isCurrentUser ? primary : theme.text }]}>
        {t('gamification:xp', { xp: entry.xp })}
      </Text>
    </View>
  );
}

// ─── Screen ───────────────────────────────────────────────────────────────────

export default function GamificationScreen() {
  const { t } = useTranslation('gamification');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const [activeTab, setActiveTab] = useState<Tab>('badges');
  const [period, setPeriod] = useState<LeaderboardPeriod>('monthly');

  useEffect(() => {
    navigation.setOptions({ title: t('gamification:title') });
  }, [navigation, t]);

  const [isRefreshing, setIsRefreshing] = useState(false);

  const { data: profileData, isLoading: profileLoading, refresh: refreshProfile } = useApi(
    () => getGamificationProfile(),
    [],
  );
  const { data: badgesData, isLoading: badgesLoading, refresh: refreshBadges } = useApi(
    () => getBadges(),
    [],
  );
  const { data: leaderboardData, isLoading: lbLoading, refresh: refreshLb } = useApi(
    () => getLeaderboard(period),
    [period],
  );

  useEffect(() => {
    if (isRefreshing && !profileLoading && !badgesLoading) {
      setIsRefreshing(false);
    }
  }, [isRefreshing, profileLoading, badgesLoading]);

  function handleRefresh() {
    setIsRefreshing(true);
    refreshProfile();
    refreshBadges();
    refreshLb();
  }

  const profile = profileData?.data ?? null;
  const badges = badgesData?.data ?? [];
  const leaderboardEntries = leaderboardData?.data ?? [];
  const userRank = leaderboardData?.meta.user_rank ?? null;

  const periods: { key: LeaderboardPeriod; label: string }[] = [
    { key: 'weekly', label: t('gamification:leaderboard.weekly') },
    { key: 'monthly', label: t('gamification:leaderboard.monthly') },
    { key: 'all_time', label: t('gamification:leaderboard.allTime') },
  ];

  const isLoading = profileLoading || badgesLoading;

  if (isLoading) {
    return (
      <SafeAreaView style={styles.center}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <FlatList<LeaderboardEntry | Badge | 'header' | 'tabs' | 'period-selector' | 'empty'>
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={handleRefresh}
            tintColor={primary}
            colors={[primary]}
          />
        }
        data={
          activeTab === 'badges'
            ? (['header', 'tabs', ...(badges.length === 0 ? ['empty' as const] : badges)] as (LeaderboardEntry | Badge | 'header' | 'tabs' | 'period-selector' | 'empty')[])
            : (['header', 'tabs', 'period-selector', ...(leaderboardEntries.length === 0 ? ['empty' as const] : leaderboardEntries)] as (LeaderboardEntry | Badge | 'header' | 'tabs' | 'period-selector' | 'empty')[])
        }
        keyExtractor={(item, index) => {
          if (typeof item === 'string') return item;
          if ('rank' in item) return `lb-${item.rank}`;
          return `badge-${(item as Badge).id}`;
        }}
        numColumns={1}
        contentContainerStyle={styles.listContent}
        renderItem={({ item }) => {
          if (item === 'header') {
            return profile ? (
              <ProfileSection profile={profile} primary={primary} theme={theme} styles={styles} t={t} />
            ) : null;
          }

          if (item === 'tabs') {
            return (
              <View style={styles.tabRow}>
                <TouchableOpacity
                  style={[styles.tabPill, activeTab === 'badges' && { backgroundColor: primary }]}
                  onPress={() => setActiveTab('badges')}
                  activeOpacity={0.8}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: activeTab === 'badges' }}
                >
                  <Text style={[styles.tabPillText, activeTab === 'badges' && { color: '#fff' }]}>
                    {t('gamification:badges.title')}
                  </Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.tabPill, activeTab === 'leaderboard' && { backgroundColor: primary }]}
                  onPress={() => setActiveTab('leaderboard')}
                  activeOpacity={0.8}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: activeTab === 'leaderboard' }}
                >
                  <Text style={[styles.tabPillText, activeTab === 'leaderboard' && { color: '#fff' }]}>
                    {t('gamification:leaderboard.title')}
                  </Text>
                </TouchableOpacity>
              </View>
            );
          }

          if (item === 'period-selector') {
            return (
              <View style={styles.periodRow}>
                {periods.map((p) => (
                  <TouchableOpacity
                    key={p.key}
                    style={[styles.periodPill, period === p.key && { backgroundColor: primary + '25', borderColor: primary }]}
                    onPress={() => setPeriod(p.key)}
                    activeOpacity={0.8}
                    accessibilityRole="button"
                    accessibilityState={{ selected: period === p.key }}
                  >
                    <Text style={[styles.periodPillText, period === p.key && { color: primary }]}>
                      {p.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            );
          }

          if (item === 'empty') {
            return (
              <View style={styles.emptyWrap}>
                <Text style={styles.emptyText}>
                  {activeTab === 'badges' ? t('gamification:badges.empty') : t('gamification:leaderboard.empty')}
                </Text>
              </View>
            );
          }

          if ('rank' in item) {
            const entry = item as LeaderboardEntry;
            return (
              <LeaderboardRow
                entry={entry}
                isCurrentUser={userRank !== null && entry.rank === userRank}
                primary={primary}
                theme={theme}
                styles={styles}
                t={t}
              />
            );
          }

          const badge = item as Badge;
          return (
            <BadgeCard badge={badge} primary={primary} theme={theme} styles={styles} t={t} />
          );
        }}
      />
      {lbLoading && activeTab === 'leaderboard' && (
        <View style={styles.lbLoadingOverlay}>
          <LoadingSpinner />
        </View>
      )}
    </SafeAreaView>
  );
}

// ─── Styles ───────────────────────────────────────────────────────────────────

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    listContent: { paddingHorizontal: 16, paddingBottom: 40 },

    // Profile card
    profileCard: {
      borderWidth: 2,
      borderRadius: 16,
      padding: 18,
      backgroundColor: theme.surface,
      marginTop: 20,
      marginBottom: 20,
    },
    profileTopRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      flexWrap: 'wrap',
      marginBottom: 12,
    },
    levelBadge: {
      borderRadius: 8,
      paddingHorizontal: 10,
      paddingVertical: 4,
    },
    levelBadgeText: { fontSize: 13, fontWeight: '700', color: '#fff' },
    rankText: { fontSize: 13, fontWeight: '600' },
    unrankedText: { fontSize: 13, color: theme.textMuted },
    streakRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginLeft: 'auto' },
    streakText: { fontSize: 13, color: theme.textSecondary },
    xpValue: { fontSize: 32, fontWeight: '700', marginBottom: 10 },
    xpBarTrack: {
      height: 8,
      borderRadius: 4,
      backgroundColor: theme.borderSubtle,
      overflow: 'hidden',
      marginBottom: 6,
    },
    xpBarFill: { height: 8, borderRadius: 4 },
    nextLevelText: { fontSize: 12, color: theme.textMuted, marginTop: 2 },

    // Tabs
    tabRow: {
      flexDirection: 'row',
      gap: 10,
      marginBottom: 16,
    },
    tabPill: {
      flex: 1,
      alignItems: 'center',
      paddingVertical: 10,
      borderRadius: 20,
      backgroundColor: theme.surface,
      borderWidth: 1,
      borderColor: theme.border,
    },
    tabPillText: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.textSecondary,
    },

    // Period selector
    periodRow: {
      flexDirection: 'row',
      gap: 8,
      marginBottom: 14,
    },
    periodPill: {
      flex: 1,
      alignItems: 'center',
      paddingVertical: 7,
      borderRadius: 10,
      borderWidth: 1,
      borderColor: theme.border,
      backgroundColor: theme.surface,
    },
    periodPillText: {
      fontSize: 12,
      fontWeight: '600',
      color: theme.textSecondary,
    },

    // Badge card
    badgeCard: {
      borderWidth: 1,
      borderRadius: 14,
      padding: 14,
      backgroundColor: theme.surface,
      marginBottom: 10,
      alignItems: 'center',
    },
    badgeIconWrap: {
      width: 56,
      height: 56,
      borderRadius: 28,
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: 8,
    },
    badgeName: { fontSize: 14, fontWeight: '600', textAlign: 'center', marginBottom: 4 },
    badgeEarnedDate: { fontSize: 11, color: theme.textMuted, textAlign: 'center' },
    badgeLockedText: { fontSize: 11, color: theme.textMuted, textAlign: 'center' },

    // Leaderboard row
    lbRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      paddingVertical: 10,
      paddingHorizontal: 6,
      marginBottom: 4,
    },
    lbRank: { fontSize: 14, fontWeight: '700', minWidth: 32, textAlign: 'center' },
    lbBody: { flex: 1, marginLeft: 4 },
    lbName: { fontSize: 15, fontWeight: '600', color: theme.text },
    lbMeta: { fontSize: 12, color: theme.textMuted, marginTop: 2 },
    lbXp: { fontSize: 14, fontWeight: '700' },

    // Loading overlay for leaderboard period switch
    lbLoadingOverlay: {
      position: 'absolute',
      bottom: 24,
      alignSelf: 'center',
    },

    // Empty
    emptyWrap: { paddingTop: 40, alignItems: 'center' },
    emptyText: { fontSize: 14, color: theme.textMuted },
  });
}
