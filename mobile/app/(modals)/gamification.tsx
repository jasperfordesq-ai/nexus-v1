// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  RefreshControl,
  Pressable,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
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
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type Tab = 'badges' | 'leaderboard';
type LeaderboardPeriod = 'weekly' | 'monthly' | 'all_time';

// ─── Sub-components ───────────────────────────────────────────────────────────

function XpBar({
  xp,
  nextLevelXp,
  primary,
}: {
  xp: number;
  nextLevelXp: number;
  primary: string;
}) {
  const percentage = nextLevelXp > 0 ? Math.min(100, Math.round((xp / nextLevelXp) * 100)) : 0;
  return (
    <View className="h-2 rounded-full bg-border/50 overflow-hidden">
      <View
        className="h-2 rounded-full"
        style={{ width: `${percentage}%`, backgroundColor: primary }}
      />
    </View>
  );
}

function ProfileSection({
  profile,
  primary,
  theme,
  t,
}: {
  profile: GamificationProfile;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const toNextLevel = Math.max(0, profile.next_level_xp - profile.xp);
  return (
    <View className="border-2 rounded-xl p-[18px] bg-surface mt-5 mb-5" style={{ borderColor: primary }}>
      <View className="flex-row items-center gap-2.5 flex-wrap mb-3">
        <View className="rounded-lg px-2.5 py-1" style={{ backgroundColor: primary }}>
          <Text className="text-xs font-bold text-white">{t('gamification:level', { level: profile.level })}</Text>
        </View>
        {profile.rank !== null ? (
          <Text className="text-xs font-semibold" style={{ color: primary }}>{t('gamification:rank', { rank: profile.rank })}</Text>
        ) : (
          <Text className="text-xs text-muted-foreground">{t('gamification:unranked')}</Text>
        )}
        {profile.streak_days > 0 && (
          <View className="flex-row items-center gap-1 ml-auto">
            <Ionicons name="flame-outline" size={14} color={theme.textSecondary} />
            <Text className="text-xs text-muted-foreground">{t('gamification:streak', { days: profile.streak_days })}</Text>
          </View>
        )}
      </View>
      <Text className="text-[32px] font-bold mb-2.5" style={{ color: primary }}>{t('gamification:xp', { xp: profile.xp })}</Text>
      <XpBar xp={profile.xp} nextLevelXp={profile.next_level_xp} primary={primary} />
      <Text className="text-xs text-muted-foreground mt-1.5">{t('gamification:nextLevel', { xp: toNextLevel })}</Text>
    </View>
  );
}

function BadgeCard({
  badge,
  primary,
  theme,
  t,
}: {
  badge: Badge;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
}) {
  const earnedDate = badge.earned_at
    ? new Date(badge.earned_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
    : null;

  return (
    <View
      className="border rounded-xl px-4 py-3 bg-surface mb-2 items-center"
      style={{ borderColor: badge.is_earned ? primary : theme.borderSubtle }}
    >
      <View
        className="w-14 h-14 rounded-full items-center justify-center mb-2"
        style={{ backgroundColor: badge.is_earned ? withAlpha(primary, 0.13) : theme.surface }}
      >
        <Ionicons
          name={(badge.icon as React.ComponentProps<typeof Ionicons>['name']) ?? 'ribbon-outline'}
          size={28}
          color={badge.is_earned ? primary : theme.textMuted}
        />
      </View>
      <Text
        className="text-xs font-semibold text-center mb-1"
        style={{ color: badge.is_earned ? theme.text : theme.textMuted }}
        numberOfLines={2}
      >
        {badge.name}
      </Text>
      {badge.is_earned && earnedDate ? (
        <Text className="text-[11px] text-muted-foreground text-center">{t('gamification:badges.earned')}: {earnedDate}</Text>
      ) : (
        <Text className="text-[11px] text-muted-foreground text-center">{t('gamification:badges.locked')}</Text>
      )}
    </View>
  );
}

function LeaderboardRow({
  entry,
  isCurrentUser,
  primary,
  theme,
  t,
}: {
  entry: LeaderboardEntry;
  isCurrentUser: boolean;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  return (
    <View
      className="flex-row items-center gap-2.5 py-2.5 px-1.5 mb-1 rounded-[10px]"
      style={isCurrentUser ? { backgroundColor: withAlpha(primary, 0.08) } : undefined}
    >
      <Text
        className="text-xs font-bold min-w-[28px] text-center"
        style={{ color: isCurrentUser ? primary : theme.textSecondary }}
      >
        #{entry.rank}
      </Text>
      <Avatar uri={entry.user.avatar} name={entry.user.name} size={36} />
      <View className="flex-1 ml-1">
        <Text className="text-sm font-semibold text-foreground" numberOfLines={1}>
          {entry.user.name}
          {isCurrentUser ? ` (${t('gamification:leaderboard.you')})` : ''}
        </Text>
        <Text className="text-xs text-muted-foreground mt-0.5">
          {t('gamification:level', { level: entry.level })} · {t('gamification:leaderboard.badgesCount', { count: entry.badges_count })}
        </Text>
      </View>
      <Text
        className="text-xs font-bold"
        style={{ color: isCurrentUser ? primary : theme.text }}
      >
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
      <SafeAreaView className="flex-1 items-center justify-center bg-background">
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background">
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
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 40 }}
        renderItem={({ item }) => {
          if (item === 'header') {
            return profile ? (
              <ProfileSection profile={profile} primary={primary} theme={theme} t={t} />
            ) : null;
          }

          if (item === 'tabs') {
            return (
              <View className="flex-row gap-2.5 mb-4">
                <Pressable
                  className={`flex-1 items-center py-2.5 rounded-full border border-border ${activeTab === 'badges' ? '' : 'bg-surface'}`}
                  style={activeTab === 'badges' ? { backgroundColor: primary } : undefined}
                  onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setActiveTab('badges'); }}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: activeTab === 'badges' }}
                >
                  <Text className={`text-xs font-semibold ${activeTab === 'badges' ? 'text-white' : 'text-muted-foreground'}`}>
                    {t('gamification:badges.title')}
                  </Text>
                </Pressable>
                <Pressable
                  className={`flex-1 items-center py-2.5 rounded-full border border-border ${activeTab === 'leaderboard' ? '' : 'bg-surface'}`}
                  style={activeTab === 'leaderboard' ? { backgroundColor: primary } : undefined}
                  onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setActiveTab('leaderboard'); }}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: activeTab === 'leaderboard' }}
                >
                  <Text className={`text-xs font-semibold ${activeTab === 'leaderboard' ? 'text-white' : 'text-muted-foreground'}`}>
                    {t('gamification:leaderboard.title')}
                  </Text>
                </Pressable>
              </View>
            );
          }

          if (item === 'period-selector') {
            return (
              <View className="flex-row gap-2 mb-3.5">
                {periods.map((p) => (
                  <Pressable
                    key={p.key}
                    className="flex-1 items-center py-1.5 rounded-lg border border-border bg-surface"
                    style={period === p.key ? { backgroundColor: withAlpha(primary, 0.15), borderColor: primary } : undefined}
                    onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setPeriod(p.key); }}
                    accessibilityRole="button"
                    accessibilityState={{ selected: period === p.key }}
                  >
                    <Text
                      className="text-xs font-semibold text-muted-foreground"
                      style={period === p.key ? { color: primary } : undefined}
                    >
                      {p.label}
                    </Text>
                  </Pressable>
                ))}
              </View>
            );
          }

          if (item === 'empty') {
            return (
              <View className="pt-10 items-center">
                <Text className="text-xs text-muted-foreground">
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
                t={t}
              />
            );
          }

          const badge = item as Badge;
          return (
            <BadgeCard badge={badge} primary={primary} theme={theme} t={t} />
          );
        }}
      />
      {lbLoading && activeTab === 'leaderboard' && (
        <View className="absolute bottom-6 self-center">
          <LoadingSpinner />
        </View>
      )}
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
