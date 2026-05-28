// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
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
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type Tab = 'badges' | 'leaderboard';
type LeaderboardPeriod = 'weekly' | 'monthly' | 'all_time';
type IoniconName = React.ComponentProps<typeof Ionicons>['name'];
type ApiBadge = Badge & {
  badge_key?: string;
  earned?: boolean;
};
type ApiProfile = GamificationProfile & {
  level_progress?: {
    current_xp?: number;
    xp_for_current_level?: number;
    xp_for_next_level?: number;
    progress_percentage?: number;
  };
  badges_count?: number;
  current_streak?: number;
  streak?: number;
};
type ApiLeaderboardEntry = LeaderboardEntry & {
  position?: number;
  score?: number;
  is_current_user?: boolean;
  user: LeaderboardEntry['user'] & {
    avatar_url?: string | null;
  };
};

const BADGE_ICON_MAP: Record<string, IoniconName> = {
  achievement: 'trophy-outline',
  achievements: 'trophy-outline',
  badge: 'ribbon-outline',
  badges: 'ribbon-outline',
  calendar: 'calendar-outline',
  chat: 'chatbubble-ellipses-outline',
  connection: 'people-circle-outline',
  connections: 'people-circle-outline',
  community: 'people-outline',
  event: 'calendar-outline',
  events: 'calendar-outline',
  exchange: 'swap-horizontal-outline',
  exchanges: 'swap-horizontal-outline',
  fire: 'flame-outline',
  first_exchange: 'swap-horizontal-outline',
  gift: 'gift-outline',
  heart: 'heart-outline',
  help: 'help-buoy-outline',
  level: 'flash-outline',
  listing: 'list-outline',
  medal: 'medal-outline',
  message: 'chatbubble-ellipses-outline',
  messages: 'chatbubble-ellipses-outline',
  offer: 'gift-outline',
  profile: 'person-circle-outline',
  request: 'hand-left-outline',
  review: 'star-outline',
  reviews: 'star-outline',
  ribbon: 'ribbon-outline',
  shield: 'shield-checkmark-outline',
  star: 'star-outline',
  streak: 'flame-outline',
  time: 'time-outline',
  trophy: 'trophy-outline',
  volunteer: 'heart-outline',
  volunteering: 'heart-outline',
  wallet: 'wallet-outline',
  xp: 'sparkles-outline',
};

function badgeKey(icon: string | null | undefined) {
  return (icon ?? '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

function normalizeBadgeIcon(icon: string | null | undefined): IoniconName {
  const raw = (icon ?? '').trim();
  const key = badgeKey(raw);
  const glyphMap = (Ionicons as unknown as { glyphMap?: Record<string, number> }).glyphMap;

  if (key && BADGE_ICON_MAP[key]) {
    return BADGE_ICON_MAP[key];
  }

  const candidates = [
    raw,
    raw ? `${raw}-outline` : '',
    key.replace(/_/g, '-'),
    key ? `${key.replace(/_/g, '-')}-outline` : '',
  ].filter(Boolean);

  for (const candidate of candidates) {
    if (glyphMap?.[candidate]) {
      return candidate as IoniconName;
    }
  }

  const firstToken = key.split('_')[0];
  return BADGE_ICON_MAP[firstToken] ?? 'ribbon-outline';
}

function numberOrFallback(value: unknown, fallback = 0) {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

function getProfileXp(profile: ApiProfile) {
  return numberOrFallback(profile.xp);
}

function getProfileLevel(profile: ApiProfile) {
  return numberOrFallback(profile.level, 1);
}

function getProfileNextLevelXp(profile: ApiProfile) {
  return numberOrFallback(profile.next_level_xp, numberOrFallback(profile.level_progress?.xp_for_next_level, getProfileXp(profile)));
}

function getProfileRank(profile: ApiProfile, userRank: number | null) {
  return typeof profile.rank === 'number' && Number.isFinite(profile.rank) ? profile.rank : userRank;
}

function getProfileStreak(profile: ApiProfile) {
  return numberOrFallback(profile.streak_days, numberOrFallback(profile.current_streak, numberOrFallback(profile.streak)));
}

function isBadgeEarned(badge: ApiBadge) {
  return badge.is_earned === true || badge.earned === true || Boolean(badge.earned_at);
}

function getBadgeKey(badge: ApiBadge) {
  return String(badge.id ?? badge.badge_key ?? badge.name);
}

function getLeaderboardRank(entry: ApiLeaderboardEntry) {
  return numberOrFallback(entry.rank, numberOrFallback(entry.position));
}

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
    <View className="h-3 overflow-hidden rounded-full bg-default-200">
      <View
        className="h-3 rounded-full"
        style={{ width: `${percentage}%`, backgroundColor: primary }}
      />
    </View>
  );
}

function StatTile({
  icon,
  label,
  value,
  tone,
  theme,
}: {
  icon: IoniconName;
  label: string;
  value: string;
  tone: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 gap-2 rounded-panel-inner p-3">
      <View className="flex-row items-center gap-2">
        <View className="size-8 items-center justify-center rounded-full" style={{ backgroundColor: withAlpha(tone, 0.14) }}>
          <Ionicons name={icon} size={16} color={tone} />
        </View>
        <Text className="flex-1 text-[11px] font-semibold uppercase text-muted-foreground" numberOfLines={1}>
          {label}
        </Text>
      </View>
      <Text className="text-lg font-bold" style={{ color: theme.text }} numberOfLines={1}>
        {value}
      </Text>
    </Surface>
  );
}

function ProfileSection({
  profile,
  earnedCount,
  lockedCount,
  userRank,
  primary,
  theme,
  t,
}: {
  profile: ApiProfile;
  earnedCount: number;
  lockedCount: number;
  userRank: number | null;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const xp = getProfileXp(profile);
  const level = getProfileLevel(profile);
  const nextLevelXp = getProfileNextLevelXp(profile);
  const rank = getProfileRank(profile, userRank);
  const streak = getProfileStreak(profile);
  const toNextLevel = Math.max(0, nextLevelXp - xp);

  return (
    <HeroCard className="gap-5 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: primary }} />
      <HeroCard.Body className="gap-5 p-4 pt-0">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name="trophy-outline" size={24} color={primary} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-xs font-semibold uppercase text-muted-foreground">
              {t('heroEyebrow')}
            </Text>
            <Text className="text-2xl font-bold text-foreground" numberOfLines={1}>
              {t('title')}
            </Text>
            <Text className="text-sm text-muted-foreground">
              {t('subtitle')}
            </Text>
          </View>
        </View>

        <View className="gap-3">
          <View className="flex-row items-end justify-between gap-3">
            <View>
              <Text className="text-[11px] font-semibold uppercase text-muted-foreground">
                {t('stats.totalXp')}
              </Text>
              <Text className="text-[34px] font-bold" style={{ color: primary }}>
                {t('xp', { xp })}
              </Text>
            </View>
            <Chip size="md" variant="secondary" color="accent">
              <Ionicons name="flash-outline" size={13} color={primary} />
              <Chip.Label>{t('level', { level })}</Chip.Label>
            </Chip>
          </View>
          <XpBar xp={xp} nextLevelXp={nextLevelXp} primary={primary} />
          <Text className="text-xs text-muted-foreground">
            {t('nextLevel', { xp: toNextLevel })}
          </Text>
        </View>

        <View className="flex-row flex-wrap gap-3">
          <StatTile
            icon="ribbon-outline"
            label={t('stats.earnedBadges')}
            value={String(earnedCount)}
            tone="#22c55e"
            theme={theme}
          />
          <StatTile
            icon="lock-closed-outline"
            label={t('stats.lockedBadges')}
            value={String(lockedCount)}
            tone="#f59e0b"
            theme={theme}
          />
          <StatTile
            icon="podium-outline"
            label={t('stats.rank')}
            value={rank !== null ? t('rank', { rank }) : t('unranked')}
            tone={primary}
            theme={theme}
          />
          <StatTile
            icon="flame-outline"
            label={t('stats.streak')}
            value={streak > 0 ? t('streak', { days: streak }) : t('streakNone')}
            tone="#ef4444"
            theme={theme}
          />
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function BadgeCard({
  badge,
  primary,
  theme,
  t,
}: {
  badge: ApiBadge;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
}) {
  const earnedDate = badge.earned_at
    ? new Date(badge.earned_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
    : null;
  const iconName = normalizeBadgeIcon(badge.icon);
  const earned = isBadgeEarned(badge);
  const tone = earned ? primary : theme.textMuted;

  return (
    <HeroCard
      variant={earned ? 'default' : 'secondary'}
      className="mb-3 min-w-[47%] flex-1 rounded-panel-inner p-0"
      style={{ borderColor: earned ? withAlpha(primary, 0.4) : theme.borderSubtle, borderWidth: 1 }}
    >
      <HeroCard.Body className="items-center gap-3 p-3">
        <View className="size-14 items-center justify-center rounded-2xl" style={{ backgroundColor: earned ? withAlpha(primary, 0.14) : theme.surface }}>
          <Ionicons name={iconName} size={27} color={tone} />
        </View>
        <View className="w-full gap-1">
          <Text className="text-center text-sm font-semibold text-foreground" numberOfLines={2}>
            {badge.name}
          </Text>
          {badge.description ? (
            <Text className="text-center text-xs text-muted-foreground" numberOfLines={2}>
              {badge.description}
            </Text>
          ) : null}
        </View>
        <Chip size="sm" variant="secondary" color={earned ? 'success' : 'default'}>
          <Ionicons name={earned ? 'checkmark-circle-outline' : 'lock-closed-outline'} size={12} color={tone} />
          <Chip.Label>{earned ? t('badges.earned') : t('badges.locked')}</Chip.Label>
        </Chip>
        {earned && earnedDate ? (
          <Text className="text-[11px] text-muted-foreground">{earnedDate}</Text>
        ) : null}
      </HeroCard.Body>
    </HeroCard>
  );
}

function LeaderboardRow({
  entry,
  isCurrentUser,
  primary,
  theme,
  t,
}: {
  entry: ApiLeaderboardEntry;
  isCurrentUser: boolean;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const rank = getLeaderboardRank(entry);
  const avatar = entry.user.avatar ?? entry.user.avatar_url ?? null;
  const badgesCount = numberOrFallback(entry.badges_count);

  return (
    <HeroCard
      variant={isCurrentUser ? 'default' : 'secondary'}
      className="mb-3 rounded-panel-inner p-0"
      style={isCurrentUser ? { borderColor: withAlpha(primary, 0.45), borderWidth: 1 } : undefined}
    >
      <HeroCard.Body className="flex-row items-center gap-3 p-3">
        <View className="size-10 items-center justify-center rounded-full" style={{ backgroundColor: isCurrentUser ? withAlpha(primary, 0.14) : theme.surface }}>
          <Text className="text-xs font-bold" style={{ color: isCurrentUser ? primary : theme.textSecondary }}>
            #{rank}
          </Text>
        </View>
        <Avatar uri={avatar} name={entry.user.name} size={42} />
        <View className="min-w-0 flex-1">
          <Text className="text-sm font-semibold text-foreground" numberOfLines={1}>
            {entry.user.name}
            {isCurrentUser ? ` (${t('leaderboard.you')})` : ''}
          </Text>
          <Text className="text-xs text-muted-foreground" numberOfLines={1}>
            {t('level', { level: entry.level })} · {t('leaderboard.badgesCount', { count: badgesCount })}
          </Text>
        </View>
        <Text className="text-sm font-bold" style={{ color: isCurrentUser ? primary : theme.text }}>
          {t('xp', { xp: entry.xp })}
        </Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function EmptyState({
  icon,
  message,
  primary,
}: {
  icon: IoniconName;
  message: string;
  primary: string;
}) {
  return (
    <Surface variant="secondary" className="items-center gap-3 rounded-panel-inner p-8">
      <View className="size-12 items-center justify-center rounded-full" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
        <Ionicons name={icon} size={24} color={primary} />
      </View>
      <Text className="text-center text-sm text-muted-foreground">{message}</Text>
    </Surface>
  );
}

function SegmentButton({
  label,
  isSelected,
  onPress,
}: {
  label: string;
  isSelected: boolean;
  onPress: () => void;
}) {
  return (
    <HeroButton
      className="flex-1"
      size="sm"
      variant={isSelected ? 'primary' : 'secondary'}
      onPress={onPress}
      accessibilityRole="tab"
      accessibilityState={{ selected: isSelected }}
    >
      <HeroButton.Label>{label}</HeroButton.Label>
    </HeroButton>
  );
}

export default function GamificationScreen() {
  const { t } = useTranslation(['gamification', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();

  const [activeTab, setActiveTab] = useState<Tab>('badges');
  const [period, setPeriod] = useState<LeaderboardPeriod>('monthly');
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
    if (isRefreshing && !profileLoading && !badgesLoading && !lbLoading) {
      setIsRefreshing(false);
    }
  }, [isRefreshing, profileLoading, badgesLoading, lbLoading]);

  function handleRefresh() {
    setIsRefreshing(true);
    refreshProfile();
    refreshBadges();
    refreshLb();
  }

  function selectTab(tab: Tab) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setActiveTab(tab);
  }

  function selectPeriod(nextPeriod: LeaderboardPeriod) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setPeriod(nextPeriod);
  }

  const profile = (profileData?.data ?? null) as ApiProfile | null;
  const badges = (badgesData?.data ?? []) as ApiBadge[];
  const leaderboardEntries = (leaderboardData?.data ?? []) as ApiLeaderboardEntry[];
  const userRank = leaderboardData?.meta.user_rank ?? leaderboardData?.meta.your_position ?? null;
  const earnedCount = useMemo(() => badges.filter((badge) => isBadgeEarned(badge)).length, [badges]);
  const lockedCount = Math.max(0, badges.length - earnedCount);

  const periods: { key: LeaderboardPeriod; label: string }[] = [
    { key: 'weekly', label: t('leaderboard.weekly') },
    { key: 'monthly', label: t('leaderboard.monthly') },
    { key: 'all_time', label: t('leaderboard.allTime') },
  ];

  const isLoading = profileLoading || badgesLoading;

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/home" />

        {isLoading ? (
          <View className="flex-1 items-center justify-center">
            <LoadingSpinner />
          </View>
        ) : (
          <ScrollView
            className="flex-1"
            contentContainerClassName="gap-4 px-4 pb-10"
            refreshControl={
              <RefreshControl
                refreshing={isRefreshing}
                onRefresh={handleRefresh}
                tintColor={primary}
                colors={[primary]}
              />
            }
          >
            {profile ? (
              <ProfileSection
                profile={profile}
                earnedCount={earnedCount}
                lockedCount={lockedCount}
                userRank={userRank}
                primary={primary}
                theme={theme}
                t={t}
              />
            ) : null}

            <Surface variant="default" className="gap-3 rounded-panel p-3">
              <View className="flex-row gap-2">
                <SegmentButton
                  label={t('badges.title')}
                  isSelected={activeTab === 'badges'}
                  onPress={() => selectTab('badges')}
                />
                <SegmentButton
                  label={t('leaderboard.title')}
                  isSelected={activeTab === 'leaderboard'}
                  onPress={() => selectTab('leaderboard')}
                />
              </View>

              {activeTab === 'leaderboard' ? (
                <View className="flex-row gap-2">
                  {periods.map((periodOption) => (
                    <HeroButton
                      key={periodOption.key}
                      className="flex-1"
                      size="sm"
                      variant={period === periodOption.key ? 'primary' : 'tertiary'}
                      onPress={() => selectPeriod(periodOption.key)}
                      accessibilityState={{ selected: period === periodOption.key }}
                    >
                      <HeroButton.Label>{periodOption.label}</HeroButton.Label>
                    </HeroButton>
                  ))}
                </View>
              ) : null}
            </Surface>

            {activeTab === 'badges' ? (
              badges.length > 0 ? (
                <View className="flex-row flex-wrap gap-3">
                  {badges.map((badge) => (
                    <BadgeCard key={getBadgeKey(badge)} badge={badge} primary={primary} theme={theme} t={t} />
                  ))}
                </View>
              ) : (
                <EmptyState icon="ribbon-outline" message={t('badges.empty')} primary={primary} />
              )
            ) : leaderboardEntries.length > 0 ? (
              <View>
                {leaderboardEntries.map((entry) => (
                  <LeaderboardRow
                    key={`${getLeaderboardRank(entry)}-${entry.user.id}`}
                    entry={entry}
                    isCurrentUser={entry.is_current_user === true || (userRank !== null && getLeaderboardRank(entry) === userRank)}
                    primary={primary}
                    theme={theme}
                    t={t}
                  />
                ))}
              </View>
            ) : (
              <EmptyState icon="podium-outline" message={t('leaderboard.empty')} primary={primary} />
            )}

            {lbLoading && activeTab === 'leaderboard' ? (
              <View className="items-center py-4">
                <LoadingSpinner />
              </View>
            ) : null}
          </ScrollView>
        )}
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}
