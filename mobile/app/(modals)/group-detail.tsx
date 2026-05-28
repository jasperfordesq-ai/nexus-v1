// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  Share,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  getEvents,
  type Event,
} from '@/lib/api/events';
import {
  createGroupDiscussion,
  getGroup,
  getGroupAnnouncements,
  getGroupDiscussions,
  getGroupMembers,
  joinGroup,
  leaveGroup,
  type GroupAnnouncement,
  type GroupDetail,
  type GroupDiscussion,
  type GroupMemberListItem,
} from '@/lib/api/groups';
import {
  getGroupMarketplaceListings,
  getGroupMarketplaceStats,
  marketplaceHasMore,
  marketplaceNextCursor,
  saveMarketplaceListing,
  unsaveMarketplaceListing,
  type MarketplaceCategory,
  type MarketplaceListingItem,
} from '@/lib/api/marketplace';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import MarketplaceListingCard from '@/components/marketplace/MarketplaceListingCard';

const WEB_URL = 'https://app.project-nexus.ie';
const CARD_MIN_HEIGHT = 118;

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];
type TabKey = 'overview' | 'discussion' | 'members' | 'events' | 'announcements' | 'marketplace';
type ApiGroupDetail = GroupDetail & {
  viewer_membership?: { status?: string; role?: string; is_admin?: boolean } | null;
  avatar_url?: string | null;
  image_url?: string | null;
  type?: string | null;
  location?: string | null;
};

function isGroupMember(group: ApiGroupDetail) {
  return group.is_member === true || group.viewer_membership?.status === 'active';
}

function groupImage(group: ApiGroupDetail) {
  return resolveImageUrl(group.cover_image ?? group.image_url ?? group.avatar_url ?? null);
}

function stripHtml(value?: string | null) {
  return (value ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function formatDate(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

function formatTime(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(date);
}

function formatDateParts(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return {
    day: new Intl.DateTimeFormat(undefined, { day: 'numeric' }).format(date),
    month: new Intl.DateTimeFormat(undefined, { month: 'short' }).format(date),
  };
}

function StatusChip({ icon, label, color }: { icon: IoniconName; label: string; color: string }) {
  return (
    <Chip size="sm" variant="secondary" color="default">
      <Ionicons name={icon} size={12} color={color} />
      <Chip.Label>{label}</Chip.Label>
    </Chip>
  );
}

function SectionTitle({ title, action }: { title: string; action?: React.ReactNode }) {
  const theme = useTheme();
  return (
    <View className="flex-row items-center justify-between gap-3">
      <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={1}>
        {title}
      </Text>
      {action}
    </View>
  );
}

function StatTile({
  label,
  value,
  tone,
  theme,
}: {
  label: string;
  value: string;
  tone: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 gap-1 rounded-panel-inner p-4">
      <Text className="text-[11px] font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={1}>
        {label}
      </Text>
      <View className="flex-row items-end justify-between gap-2">
        <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
          {value}
        </Text>
        <View className="h-1.5 w-10 rounded-full" style={{ backgroundColor: tone }} />
      </View>
    </Surface>
  );
}

function EmptyCard({ icon, message }: { icon: IoniconName; message: string }) {
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="min-h-[118px] items-center justify-center gap-3 p-5">
        <View className="size-11 items-center justify-center rounded-full" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
          <Ionicons name={icon} size={20} color={primary} />
        </View>
        <Text className="text-center text-sm leading-5" style={{ color: theme.textSecondary }}>
          {message}
        </Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function StateMessage({ title, action, primary }: { title: string; action: string; primary: string }) {
  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={title} backLabel={action} fallbackHref="/(tabs)/groups" />
      <View className="flex-1 items-center justify-center px-6">
        <Surface variant="secondary" className="items-center gap-4 rounded-panel p-8">
          <View className="size-12 items-center justify-center rounded-full" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
            <Ionicons name="people-outline" size={24} color={primary} />
          </View>
          <Text className="text-center text-sm text-muted-foreground">{title}</Text>
          <HeroButton variant="secondary" onPress={() => router.back()}>
            <HeroButton.Label>{action}</HeroButton.Label>
          </HeroButton>
        </Surface>
      </View>
    </SafeAreaView>
  );
}

export default function GroupDetailScreen() {
  return (
    <ModalErrorBoundary>
      <GroupDetailScreenInner />
    </ModalErrorBoundary>
  );
}

function GroupDetailScreenInner() {
  const { t } = useTranslation(['groups', 'common', 'marketplace']);
  const { hasFeature } = useTenant();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [activeTab, setActiveTab] = useState<TabKey>('overview');

  const groupId = Number(id);
  const safeGroupId = Number.isFinite(groupId) && groupId > 0 ? groupId : 0;

  const { data, isLoading, refresh } = useApi(
    () => getGroup(safeGroupId),
    [safeGroupId],
    { enabled: safeGroupId > 0 },
  );

  const group = (data?.data ?? null) as ApiGroupDetail | null;
  const [isMember, setIsMember] = useState<boolean | null>(null);
  const [memberCount, setMemberCount] = useState<number | null>(null);
  const [joining, setJoining] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [showDiscussionComposer, setShowDiscussionComposer] = useState(false);
  const [discussionTitle, setDiscussionTitle] = useState('');
  const [discussionContent, setDiscussionContent] = useState('');
  const [creatingDiscussion, setCreatingDiscussion] = useState(false);

  useEffect(() => {
    if (group) {
      setIsMember(isGroupMember(group));
      setMemberCount(group.member_count ?? 0);
    }
  }, [group]);

  const currentIsMember = group ? (isMember ?? isGroupMember(group)) : false;
  const membersApi = useApi(() => getGroupMembers(safeGroupId), [safeGroupId, currentIsMember], {
    enabled: safeGroupId > 0 && currentIsMember,
  });
  const discussionsApi = useApi(() => getGroupDiscussions(safeGroupId), [safeGroupId, currentIsMember], {
    enabled: safeGroupId > 0 && currentIsMember,
  });
  const announcementsApi = useApi(() => getGroupAnnouncements(safeGroupId), [safeGroupId, currentIsMember], {
    enabled: safeGroupId > 0 && currentIsMember,
  });
  const eventsApi = useApi(() => getEvents('upcoming', null, 20, { groupId: safeGroupId }), [safeGroupId], {
    enabled: safeGroupId > 0,
  });

  const members = useMemo<GroupMemberListItem[]>(() => membersApi.data?.data ?? [], [membersApi.data]);
  const discussions = useMemo<GroupDiscussion[]>(() => discussionsApi.data?.data ?? [], [discussionsApi.data]);
  const announcements = useMemo<GroupAnnouncement[]>(() => announcementsApi.data?.data?.items ?? [], [announcementsApi.data]);
  const events = useMemo<Event[]>(() => eventsApi.data?.data ?? [], [eventsApi.data]);

  const handleRefresh = useCallback(() => {
    setRefreshing(true);
    refresh();
    membersApi.refresh();
    discussionsApi.refresh();
    announcementsApi.refresh();
    eventsApi.refresh();
  }, [announcementsApi, discussionsApi, eventsApi, membersApi, refresh]);

  useEffect(() => {
    if (!isLoading && !membersApi.isLoading && !discussionsApi.isLoading && !announcementsApi.isLoading && !eventsApi.isLoading) {
      setRefreshing(false);
    }
  }, [announcementsApi.isLoading, discussionsApi.isLoading, eventsApi.isLoading, isLoading, membersApi.isLoading]);

  async function handleShare() {
    if (!group) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${group.name} - ${WEB_URL}/groups/${group.id}`,
      });
    } catch {
      // Native share can be cancelled; no user-facing error needed.
    }
  }

  if (!safeGroupId) {
    return <StateMessage title={t('detail.invalidId')} action={t('detail.goBack')} primary={primary} />;
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/groups" />
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      </SafeAreaView>
    );
  }

  if (!group) {
    return <StateMessage title={t('detail.notFound')} action={t('detail.goBack')} primary={primary} />;
  }

  const loadedGroup = group;
  const currentMemberCount = memberCount ?? loadedGroup.member_count ?? 0;
  const isUpdating = joining || leaving;
  const displayDescription = loadedGroup.long_description ?? loadedGroup.description;
  const image = groupImage(loadedGroup);
  const userCanSeeMemberContent = currentIsMember;

  async function handleJoin() {
    const prevIsMember = isMember ?? isGroupMember(loadedGroup);
    const prevMemberCount = memberCount ?? loadedGroup.member_count ?? 0;
    setJoining(true);
    setIsMember(true);
    setMemberCount(prevMemberCount + 1);
    try {
      await joinGroup(loadedGroup.id);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      refresh();
      membersApi.refresh();
      discussionsApi.refresh();
      announcementsApi.refresh();
      eventsApi.refresh();
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
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    Alert.alert(t('leaveConfirmTitle'), t('leaveConfirmMessage'), [
      { text: t('common:buttons.cancel'), style: 'cancel' },
      {
        text: t('leave'),
        style: 'destructive',
        onPress: async () => {
          const prevIsMember = isMember ?? isGroupMember(loadedGroup);
          const prevMemberCount = memberCount ?? loadedGroup.member_count ?? 0;
          setLeaving(true);
          setIsMember(false);
          setMemberCount(Math.max(0, prevMemberCount - 1));
          try {
            await leaveGroup(loadedGroup.id);
            refresh();
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

  async function handleCreateDiscussion() {
    const title = discussionTitle.trim();
    const content = discussionContent.trim();
    if (!title || !content) {
      Alert.alert(t('common:errors.alertTitle'), t('detail.discussionRequired'));
      return;
    }

    setCreatingDiscussion(true);
    try {
      await createGroupDiscussion(loadedGroup.id, { title, content });
      setDiscussionTitle('');
      setDiscussionContent('');
      setShowDiscussionComposer(false);
      discussionsApi.refresh();
      refresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('detail.discussionCreateError'));
    } finally {
      setCreatingDiscussion(false);
    }
  }

  const tabs: Array<{ key: TabKey; label: string; icon: IoniconName }> = [
    { key: 'overview', label: t('detail.tabs.overview'), icon: 'newspaper-outline' },
    { key: 'discussion', label: t('detail.tabs.discussion'), icon: 'chatbubble-ellipses-outline' },
    { key: 'members', label: t('detail.tabs.members'), icon: 'people-outline' },
    { key: 'events', label: t('detail.tabs.events'), icon: 'calendar-outline' },
    { key: 'announcements', label: t('detail.tabs.announcements'), icon: 'megaphone-outline' },
  ];
  if (hasFeature('marketplace')) {
    tabs.push({ key: 'marketplace', label: t('detail.tabs.marketplace'), icon: 'bag-handle-outline' });
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('detail.title')}
        backLabel={t('common:back')}
        fallbackHref="/(tabs)/groups"
        rightAction={{
          accessibilityLabel: t('share'),
          icon: 'share-outline',
          onPress: handleShare,
        }}
      />

      <ScrollView
        className="flex-1"
        contentContainerClassName="gap-4 px-4 pb-10"
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => void handleRefresh()}
            tintColor={primary}
            colors={[primary]}
          />
        }
      >
        <HeroCard className="overflow-hidden rounded-panel p-0">
          {image ? (
            <Image source={{ uri: image }} className="h-44 w-full bg-default-200" resizeMode="cover" />
          ) : (
            <View className="h-28 items-center justify-center" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
              <Ionicons name="people-outline" size={42} color={primary} />
            </View>
          )}
          <HeroCard.Body className="gap-5 p-4">
            <View className="gap-3">
              <View className="flex-row items-start gap-3">
                <View className="min-w-0 flex-1">
                  <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={2}>
                    {loadedGroup.name}
                  </Text>
                  {loadedGroup.location ? (
                    <View className="mt-2 flex-row items-center gap-1">
                      <Ionicons name="location-outline" size={14} color={theme.textSecondary} />
                      <Text className="min-w-0 flex-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                        {loadedGroup.location}
                      </Text>
                    </View>
                  ) : null}
                </View>
                {loadedGroup.is_featured ? (
                  <Chip size="sm" variant="secondary" color="warning">
                    <Ionicons name="star-outline" size={12} color="#f59e0b" />
                    <Chip.Label>{t('featured')}</Chip.Label>
                  </Chip>
                ) : null}
              </View>

              <View className="flex-row flex-wrap gap-2">
                <StatusChip
                  icon={loadedGroup.visibility === 'private' ? 'lock-closed-outline' : 'globe-outline'}
                  label={loadedGroup.visibility === 'private' ? t('private') : t('public')}
                  color={theme.textMuted}
                />
                {currentIsMember ? (
                  <StatusChip icon="checkmark-circle-outline" label={t('joined')} color={theme.success} />
                ) : null}
              </View>
            </View>

            <View className="flex-row flex-wrap gap-3">
              <StatTile label={t('detail.stats.members')} value={String(currentMemberCount)} tone={primary} theme={theme} />
              <StatTile label={t('detail.stats.posts')} value={String(loadedGroup.posts_count ?? 0)} tone="#22c55e" theme={theme} />
            </View>

            <HeroButton
              variant={currentIsMember ? 'secondary' : 'primary'}
              onPress={currentIsMember ? () => void handleLeave() : () => void handleJoin()}
              isDisabled={isUpdating}
            >
              {isUpdating ? (
                <Spinner size="sm" />
              ) : (
                <>
                  <Ionicons
                    name={currentIsMember ? 'exit-outline' : 'add-outline'}
                    size={18}
                    color={currentIsMember ? primary : '#fff'}
                  />
                  <HeroButton.Label>{currentIsMember ? t('leave') : t('join')}</HeroButton.Label>
                </>
              )}
            </HeroButton>
          </HeroCard.Body>
        </HeroCard>

        <Surface variant="secondary" className="rounded-panel p-1">
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-1">
            {tabs.map((tab) => {
              const selected = activeTab === tab.key;
              return (
                <Pressable
                  key={tab.key}
                  onPress={() => {
                    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                    setActiveTab(tab.key);
                  }}
                  className="h-11 min-w-[120px] flex-row items-center justify-center gap-2 rounded-panel-inner px-4"
                  style={{ backgroundColor: selected ? withAlpha(primary, 0.18) : 'transparent' }}
                >
                  <Ionicons name={tab.icon} size={16} color={selected ? primary : theme.textSecondary} />
                  <Text className="text-sm font-semibold" style={{ color: selected ? primary : theme.textSecondary }} numberOfLines={1}>
                    {tab.label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        </Surface>

        {activeTab === 'overview' ? (
          <View className="gap-4">
            {displayDescription ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="gap-3 p-4">
                  <SectionTitle title={t('detail.about')} />
                  <Text className="text-sm leading-6" style={{ color: theme.text }}>
                    {stripHtml(displayDescription)}
                  </Text>
                </HeroCard.Body>
              </HeroCard>
            ) : (
              <EmptyCard icon="document-text-outline" message={t('detail.emptyAbout')} />
            )}

            {loadedGroup.tags && loadedGroup.tags.length > 0 ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="gap-3 p-4">
                  <SectionTitle title={t('detail.tags')} />
                  <View className="flex-row flex-wrap gap-2">
                    {loadedGroup.tags.map((tag) => (
                      <Chip key={tag} size="sm" variant="secondary" color="default">
                        <Chip.Label>{tag}</Chip.Label>
                      </Chip>
                    ))}
                  </View>
                </HeroCard.Body>
              </HeroCard>
            ) : null}

            {loadedGroup.admin ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="gap-3 p-4">
                  <SectionTitle title={t('detail.admin')} />
                  <View className="flex-row items-center gap-3">
                    <Avatar uri={loadedGroup.admin.avatar_url ?? undefined} name={loadedGroup.admin.name ?? '?'} size={42} />
                    <View className="min-w-0 flex-1">
                      <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                        {loadedGroup.admin.name ?? t('common:unknown')}
                      </Text>
                      <Text className="text-xs" style={{ color: theme.textSecondary }}>
                        {t('detail.groupAdmin')}
                      </Text>
                    </View>
                  </View>
                </HeroCard.Body>
              </HeroCard>
            ) : null}
          </View>
        ) : null}

        {activeTab === 'discussion' ? (
          <View className="gap-3">
            {!userCanSeeMemberContent ? (
              <EmptyCard icon="lock-closed-outline" message={t('detail.joinToDiscuss')} />
            ) : (
              <>
                <HeroCard className="rounded-panel p-0">
                  <HeroCard.Body className="gap-3 p-4">
                    <View className="flex-row items-center justify-between gap-3">
                      <View className="min-w-0 flex-1">
                        <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                          {t('detail.startDiscussion')}
                        </Text>
                        <Text className="mt-1 text-xs" style={{ color: theme.textSecondary }} numberOfLines={2}>
                          {t('detail.startDiscussionHint')}
                        </Text>
                      </View>
                      <HeroButton
                        size="sm"
                        variant={showDiscussionComposer ? 'secondary' : 'primary'}
                        onPress={() => setShowDiscussionComposer((value) => !value)}
                      >
                        <HeroButton.Label>
                          {showDiscussionComposer ? t('common:buttons.cancel') : t('detail.newDiscussion')}
                        </HeroButton.Label>
                      </HeroButton>
                    </View>

                    {showDiscussionComposer ? (
                      <View className="gap-3">
                        <TextInput
                          value={discussionTitle}
                          onChangeText={setDiscussionTitle}
                          placeholder={t('detail.discussionTitlePlaceholder')}
                          placeholderTextColor={theme.textMuted}
                          className="rounded-panel-inner px-4 py-3 text-base"
                          style={{ backgroundColor: theme.surface, color: theme.text, borderColor: theme.border, borderWidth: 1 }}
                        />
                        <TextInput
                          value={discussionContent}
                          onChangeText={setDiscussionContent}
                          placeholder={t('detail.discussionContentPlaceholder')}
                          placeholderTextColor={theme.textMuted}
                          multiline
                          className="min-h-[104px] rounded-panel-inner px-4 py-3 text-base"
                          style={{ backgroundColor: theme.surface, color: theme.text, borderColor: theme.border, borderWidth: 1, textAlignVertical: 'top' }}
                        />
                        <HeroButton isDisabled={creatingDiscussion} onPress={() => void handleCreateDiscussion()}>
                          {creatingDiscussion ? <Spinner size="sm" /> : <HeroButton.Label>{t('detail.publishDiscussion')}</HeroButton.Label>}
                        </HeroButton>
                      </View>
                    ) : null}
                  </HeroCard.Body>
                </HeroCard>

                {discussionsApi.isLoading ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="min-h-[140px] items-center justify-center">
                  <Spinner size="md" />
                </HeroCard.Body>
              </HeroCard>
            ) : discussions.length === 0 ? (
              <EmptyCard icon="chatbubble-ellipses-outline" message={t('detail.emptyDiscussions')} />
            ) : (
              discussions.map((discussion) => (
                <HeroCard key={discussion.id} className="rounded-panel p-0">
                  <HeroCard.Body className="gap-3 p-4" style={{ minHeight: CARD_MIN_HEIGHT }}>
                    <View className="flex-row items-start justify-between gap-3">
                      <Text className="min-w-0 flex-1 text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                        {discussion.title}
                      </Text>
                      {discussion.is_pinned ? (
                        <Chip size="sm" variant="secondary" color="warning">
                          <Chip.Label>{t('detail.pinned')}</Chip.Label>
                        </Chip>
                      ) : null}
                    </View>
                    <View className="flex-row items-center gap-3">
                      <Avatar uri={discussion.author?.avatar_url ?? undefined} name={discussion.author?.name ?? '?'} size={32} />
                      <View className="min-w-0 flex-1">
                        <Text className="text-sm font-medium" style={{ color: theme.text }} numberOfLines={1}>
                          {discussion.author?.name ?? t('common:unknown')}
                        </Text>
                        <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                          {[
                            t('detail.replies', { count: discussion.reply_count ?? 0 }),
                            formatDate(discussion.created_at),
                          ].filter(Boolean).join(' • ')}
                        </Text>
                      </View>
                    </View>
                  </HeroCard.Body>
                </HeroCard>
              ))
            )}
              </>
            )}
          </View>
        ) : null}

        {activeTab === 'members' ? (
          <View className="gap-3">
            {!userCanSeeMemberContent ? (
              <EmptyCard icon="lock-closed-outline" message={t('detail.joinToSeeMembers')} />
            ) : membersApi.isLoading ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="min-h-[140px] items-center justify-center">
                  <Spinner size="md" />
                </HeroCard.Body>
              </HeroCard>
            ) : members.length === 0 ? (
              <EmptyCard icon="people-outline" message={t('detail.emptyMembers')} />
            ) : (
              members.map((member) => (
                <HeroCard key={member.id} className="rounded-panel p-0">
                  <HeroCard.Body className="flex-row items-center gap-3 p-4" style={{ minHeight: 86 }}>
                    <Avatar uri={member.avatar_url ?? undefined} name={member.name ?? '?'} size={44} />
                    <View className="min-w-0 flex-1">
                      <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                        {member.name || t('common:unknown')}
                      </Text>
                      <Text className="mt-1 text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                        {[
                          t(`detail.roles.${member.role}`, { defaultValue: member.role }),
                          formatDate(member.joined_at),
                        ].filter(Boolean).join(' • ')}
                      </Text>
                    </View>
                  </HeroCard.Body>
                </HeroCard>
              ))
            )}
          </View>
        ) : null}

        {activeTab === 'events' ? (
          <GroupEventsPanel groupId={loadedGroup.id} events={events} isLoading={eventsApi.isLoading} canCreate={userCanSeeMemberContent} />
        ) : null}

        {activeTab === 'announcements' ? (
          <View className="gap-3">
            {!userCanSeeMemberContent ? (
              <EmptyCard icon="lock-closed-outline" message={t('detail.joinToSeeAnnouncements')} />
            ) : announcementsApi.isLoading ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="min-h-[140px] items-center justify-center">
                  <Spinner size="md" />
                </HeroCard.Body>
              </HeroCard>
            ) : announcements.length === 0 ? (
              <EmptyCard icon="megaphone-outline" message={t('detail.emptyAnnouncements')} />
            ) : (
              announcements.map((announcement) => (
                <HeroCard key={announcement.id} className="rounded-panel p-0">
                  <HeroCard.Body className="gap-3 p-4" style={{ minHeight: CARD_MIN_HEIGHT }}>
                    <View className="flex-row items-start justify-between gap-3">
                      <Text className="min-w-0 flex-1 text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                        {announcement.title}
                      </Text>
                      {announcement.is_pinned ? (
                        <Chip size="sm" variant="secondary" color="warning">
                          <Chip.Label>{t('detail.pinned')}</Chip.Label>
                        </Chip>
                      ) : null}
                    </View>
                    <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={4}>
                      {stripHtml(announcement.content)}
                    </Text>
                    <Text className="text-xs" style={{ color: theme.textMuted }} numberOfLines={1}>
                      {[announcement.author?.name, formatDate(announcement.created_at)].filter(Boolean).join(' • ')}
                    </Text>
                  </HeroCard.Body>
                </HeroCard>
              ))
            )}
          </View>
        ) : null}

        {activeTab === 'marketplace' ? (
          <GroupMarketplacePanel groupId={loadedGroup.id} canView={userCanSeeMemberContent} />
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function GroupEventsPanel({
  groupId,
  events,
  isLoading,
  canCreate,
}: {
  groupId: number;
  events: Event[];
  isLoading: boolean;
  canCreate: boolean;
}) {
  const { t } = useTranslation(['groups', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();

  function openEvent(eventId: number) {
    router.push({ pathname: '/(modals)/event-detail', params: { id: String(eventId) } } as never);
  }

  function createGroupEvent() {
    router.push({ pathname: '/(modals)/new-event', params: { group_id: String(groupId) } } as never);
  }

  return (
    <View className="gap-3">
      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View className="flex-row items-start gap-3">
            <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#f59e0b', 0.16) }}>
              <Ionicons name="calendar-outline" size={23} color="#f59e0b" />
            </View>
            <View className="min-w-0 flex-1">
              <Text className="text-base font-semibold" style={{ color: theme.text }}>
                {t('detail.eventsHeading')}
              </Text>
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
                {t('detail.eventsSubtitle')}
              </Text>
            </View>
          </View>

          {canCreate ? (
            <HeroButton variant="secondary" onPress={createGroupEvent}>
              <Ionicons name="add-outline" size={16} color={primary} />
              <HeroButton.Label>{t('detail.createEvent')}</HeroButton.Label>
            </HeroButton>
          ) : null}
        </HeroCard.Body>
      </HeroCard>

      {isLoading ? (
        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="min-h-[140px] items-center justify-center">
            <Spinner size="md" />
          </HeroCard.Body>
        </HeroCard>
      ) : events.length === 0 ? (
        <EmptyCard icon="calendar-outline" message={t('detail.emptyEvents')} />
      ) : (
        events.map((event) => {
          const eventDate = formatDate(event.start_date) ?? t('detail.eventDateFallback');
          const eventDateParts = formatDateParts(event.start_date);
          const eventTime = formatTime(event.start_date);
          const eventLocation = event.is_online ? t('detail.eventOnline') : event.location;
          const attendeeCount = event.attendees_count ?? event.rsvp_counts?.going ?? 0;
          return (
            <Pressable key={event.id} onPress={() => openEvent(event.id)} accessibilityRole="button">
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="gap-3 p-4">
                  <View className="flex-row items-start gap-3">
                    <View
                      className="w-16 items-center rounded-3xl border px-2 py-3"
                      style={{ backgroundColor: withAlpha('#f59e0b', 0.1), borderColor: withAlpha('#f59e0b', 0.25) }}
                    >
                      <Text className="text-center text-[11px] font-semibold uppercase" style={{ color: '#f59e0b' }} numberOfLines={1}>
                        {eventDateParts?.month ?? eventDate}
                      </Text>
                      <Text className="text-center text-xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
                        {eventDateParts?.day ?? '-'}
                      </Text>
                    </View>
                    <View className="min-w-0 flex-1 gap-2">
                      <View className="flex-row items-start justify-between gap-2">
                        <View className="min-w-0 flex-1">
                          <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                            {event.title}
                          </Text>
                          <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>
                            {stripHtml(event.description)}
                          </Text>
                        </View>
                        <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
                      </View>

                      <View className="flex-row flex-wrap gap-2">
                        {eventTime ? <StatusChip icon="time-outline" label={eventTime} color="#f59e0b" /> : null}
                        {eventLocation ? <StatusChip icon={event.is_online ? 'videocam-outline' : 'location-outline'} label={eventLocation} color={primary} /> : null}
                        <StatusChip icon="people-outline" label={t('detail.eventAttending', { count: attendeeCount })} color={theme.textMuted} />
                      </View>
                    </View>
                  </View>
                </HeroCard.Body>
              </HeroCard>
            </Pressable>
          );
        })
      )}
    </View>
  );
}

function GroupMarketplacePanel({ groupId, canView }: { groupId: number; canView: boolean }) {
  const { t } = useTranslation(['groups', 'marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [items, setItems] = useState<MarketplaceListingItem[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const statsApi = useApi(() => getGroupMarketplaceStats(groupId), [groupId], { enabled: canView });
  const stats = statsApi.data?.data;

  const loadListings = useCallback(async (append = false, categoryId = selectedCategory) => {
    if (!canView) return;
    if (append) setIsLoadingMore(true);
    else setIsLoading(true);
    setError(null);

    try {
      const response = await getGroupMarketplaceListings(groupId, {
        category_id: categoryId,
        cursor: append ? cursor : null,
        limit: 20,
        sort: 'newest',
      });
      setCursor(marketplaceNextCursor(response));
      setHasMore(marketplaceHasMore(response));
      setItems((current) => append ? [...current, ...response.data] : response.data);
    } catch (err) {
      if (!append) setError(err instanceof Error ? err.message : t('detail.marketplace.loadFailed'));
      else Alert.alert(t('common:errors.alertTitle'), t('detail.marketplace.loadMoreFailed'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [canView, cursor, groupId, selectedCategory, t]);

  useEffect(() => {
    void loadListings(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [groupId, selectedCategory, canView]);

  function chooseCategory(category: MarketplaceCategory | null) {
    setCursor(null);
    setSelectedCategory(category?.id ?? null);
  }

  async function toggleSave(item: MarketplaceListingItem) {
    const nextSaved = !item.is_saved;
    setItems((current) => current.map((listing) => listing.id === item.id ? { ...listing, is_saved: nextSaved } : listing));
    try {
      if (nextSaved) await saveMarketplaceListing(item.id);
      else await unsaveMarketplaceListing(item.id);
    } catch {
      setItems((current) => current.map((listing) => listing.id === item.id ? item : listing));
      Alert.alert(t('common:errors.alertTitle'), t('marketplace:common.save_failed'));
    }
  }

  if (!canView) {
    return <EmptyCard icon="lock-closed-outline" message={t('detail.marketplace.joinToView')} />;
  }

  return (
    <View className="gap-3">
      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View className="flex-row items-start gap-3">
            <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
              <Ionicons name="bag-handle-outline" size={23} color={primary} />
            </View>
            <View className="min-w-0 flex-1">
              <Text className="text-base font-semibold" style={{ color: theme.text }}>{t('detail.marketplace.title')}</Text>
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('detail.marketplace.subtitle')}</Text>
            </View>
          </View>

          {stats ? (
            <View className="flex-row flex-wrap gap-2">
              <StatusChip icon="cube-outline" label={t('detail.marketplace.active', { count: stats.active_listings ?? 0 })} color={primary} />
              <StatusChip icon="pricetag-outline" label={t('detail.marketplace.total', { count: stats.total_listed ?? 0 })} color={theme.success} />
              <StatusChip icon="people-outline" label={t('detail.marketplace.sellers', { count: stats.total_sellers ?? 0 })} color={theme.textMuted} />
            </View>
          ) : statsApi.isLoading ? <Spinner size="sm" /> : null}

          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
            <HeroButton size="sm" variant={selectedCategory === null ? 'primary' : 'secondary'} onPress={() => chooseCategory(null)} style={selectedCategory === null ? { backgroundColor: primary } : undefined}>
              <HeroButton.Label>{t('marketplace:filters.allCategories')}</HeroButton.Label>
            </HeroButton>
            {(stats?.categories ?? []).map((category) => (
              <HeroButton key={category.id} size="sm" variant={selectedCategory === category.id ? 'primary' : 'secondary'} onPress={() => chooseCategory(category)} style={selectedCategory === category.id ? { backgroundColor: primary } : undefined}>
                <HeroButton.Label>{category.name}</HeroButton.Label>
              </HeroButton>
            ))}
          </ScrollView>

          <HeroButton variant="secondary" onPress={() => router.push({ pathname: '/(modals)/new-marketplace-listing', params: { group_id: String(groupId) } } as never)}>
            <Ionicons name="add-outline" size={16} color={primary} />
            <HeroButton.Label>{t('detail.marketplace.sellToGroup')}</HeroButton.Label>
          </HeroButton>
        </HeroCard.Body>
      </HeroCard>

      {isLoading ? (
        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="min-h-[140px] items-center justify-center">
            <Spinner size="md" />
          </HeroCard.Body>
        </HeroCard>
      ) : items.length === 0 ? (
        <EmptyCard icon="bag-handle-outline" message={error ?? t('detail.marketplace.empty')} />
      ) : (
        <>
          {items.map((item) => (
            <MarketplaceListingCard
              key={item.id}
              item={item}
              onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.id) } } as never)}
              onSavePress={() => void toggleSave(item)}
            />
          ))}
          {hasMore ? (
            <HeroButton variant="secondary" isDisabled={isLoadingMore} onPress={() => void loadListings(true)}>
              {isLoadingMore ? <Spinner size="sm" /> : <HeroButton.Label>{t('marketplace:loadMore')}</HeroButton.Label>}
            </HeroButton>
          ) : null}
        </>
      )}
    </View>
  );
}
