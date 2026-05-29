// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Alert, Linking, RefreshControl, ScrollView, Share, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';

import {
  checkInEventAttendee,
  getEvent,
  getEventAttendees,
  getEventOnlineLink,
  getEventPolls,
  getEventReminders,
  getEventWaitlist,
  joinEventWaitlist,
  leaveEventWaitlist,
  removeRsvp,
  rsvpEvent,
  updateEventReminders,
  voteEventPoll,
} from '@/lib/api/events';
import type { EventAttendee, EventPoll, EventReminder, UpdateEventReminderInput } from '@/lib/api/events';
import { useAuth } from '@/lib/hooks/useAuth';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import AppTopBar from '@/components/ui/AppTopBar';

const WEB_URL = 'https://app.project-nexus.ie';
const REMINDER_OPTIONS = [60, 1440, 10080] as const;

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

export default function EventDetailScreen() {
  return (
    <ModalErrorBoundary>
      <EventDetailScreenInner />
    </ModalErrorBoundary>
  );
}

function EventDetailScreenInner() {
  const { t } = useTranslation(['events', 'common']);
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();

  const eventId = Number(id);
  const safeEventId = Number.isFinite(eventId) && eventId > 0 ? eventId : 0;
  const { data, isLoading, refresh } = useApi(() => getEvent(safeEventId), [safeEventId], { enabled: safeEventId > 0 });
  const remindersApi = useApi(() => getEventReminders(safeEventId), [safeEventId], { enabled: safeEventId > 0 && !!user });
  const event = data?.data ?? null;
  const isOrganizer = !!user && user.id === event?.organizer?.id;
  const attendeesApi = useApi(
    () => getEventAttendees(safeEventId, { perPage: 50, status: 'all' }),
    [safeEventId],
    { enabled: safeEventId > 0 },
  );
  const waitlistApi = useApi(() => getEventWaitlist(safeEventId), [safeEventId], { enabled: safeEventId > 0 && !!user });
  const pollsApi = useApi(() => getEventPolls(safeEventId), [safeEventId], { enabled: safeEventId > 0 });

  const [rsvp, setRsvp] = useState<'going' | 'interested' | 'not_going' | null>(null);
  const [rsvpCounts, setRsvpCounts] = useState<{ going: number; interested: number } | null>(null);
  const [updating, setUpdating] = useState(false);
  const [waitlistPosition, setWaitlistPosition] = useState<number | null>(null);
  const [checkingInAttendeeId, setCheckingInAttendeeId] = useState<number | null>(null);
  const [checkedInAttendeeIds, setCheckedInAttendeeIds] = useState<number[]>([]);

  useEffect(() => {
    setCheckedInAttendeeIds([]);
    setWaitlistPosition(null);
  }, [safeEventId]);

  if (safeEventId <= 0) {
    return (
      <ScreenShell title={t('detailTitle')} backLabel={t('detail.goBack')}>
        <CenteredState text={t('detail.invalidId')} />
      </ScreenShell>
    );
  }

  if (isLoading && !event) {
    return (
      <ScreenShell title={t('detailTitle')} backLabel={t('detail.goBack')}>
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      </ScreenShell>
    );
  }

  if (!event) {
    return (
      <ScreenShell title={t('detailTitle')} backLabel={t('detail.goBack')}>
        <CenteredState text={t('detail.notFound')} />
      </ScreenShell>
    );
  }

  const currentRsvp = rsvp ?? event.user_rsvp ?? null;
  const counts = rsvpCounts ?? event.rsvp_counts ?? { going: 0, interested: 0 };
  const start = event.start_date ? new Date(event.start_date) : null;
  const isValidDate = start && !Number.isNaN(start.getTime());
  const dateStr = isValidDate
    ? start.toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
    : '-';
  const timeStr = isValidDate
    ? start.toLocaleTimeString('default', { hour: '2-digit', minute: '2-digit' })
    : '-';
  const accent = event.category?.color ?? '#F59E0B';
  const coverImage = resolveImageUrl(event.cover_image);
  const onlineLink = getEventOnlineLink(event);
  const attendeeData = Array.isArray(attendeesApi.data?.data) ? attendeesApi.data.data : [];
  const attendees = attendeeData.map((attendee) => ({
    ...attendee,
    checked_in: attendee.checked_in || checkedInAttendeeIds.includes(attendee.id) || attendee.rsvp_status === 'attended' || attendee.status === 'attended',
  }));
  const currentWaitlistPosition = waitlistPosition ?? event.user_waitlist_position ?? waitlistApi.data?.meta?.user_position ?? null;

  async function handleShare() {
    if (!event) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: t('detail.shareMessage', { title: event.title, url: `${WEB_URL}/events/${event.id}` }),
      });
    } catch {
      // User cancelled native share.
    }
  }

  async function handleRsvp(status: 'going' | 'interested') {
    if (!event) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

    if (currentRsvp === status) {
      Alert.alert(
        t('common:buttons.confirm'),
        t('confirmCancelRsvp'),
        [
          { text: t('common:no'), style: 'cancel' },
          {
            text: t('common:yes'),
            onPress: async () => {
              setUpdating(true);
              try {
                await removeRsvp(event.id);
                setRsvp(null);
                setRsvpCounts({ ...counts, [status]: Math.max(0, counts[status] - 1) });
              } catch {
                Alert.alert(t('common:errors.alertTitle'), t('rsvpError'));
              } finally {
                setUpdating(false);
              }
            },
          },
        ],
      );
      return;
    }

    setUpdating(true);
    try {
      const result = await rsvpEvent(event.id, status);
      if (result?.data?.rsvp) setRsvp(result.data.rsvp);
      if (result?.data?.rsvp_counts) setRsvpCounts(result.data.rsvp_counts);
      setWaitlistPosition(null);
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('rsvpError'));
    } finally {
      setUpdating(false);
    }
  }

  function openEditEvent() {
    if (!event) return;
    router.push({ pathname: '/(modals)/edit-event', params: { id: String(event.id) } } as unknown as Href);
  }

  function handleRefresh() {
    refresh();
    remindersApi.refresh();
    attendeesApi.refresh();
    waitlistApi.refresh();
    pollsApi.refresh();
  }

  async function handleCheckIn(attendee: EventAttendee) {
    if (!event || attendee.checked_in || checkingInAttendeeId) return;

    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setCheckingInAttendeeId(attendee.id);
    try {
      await checkInEventAttendee(event.id, attendee.id);
      setCheckedInAttendeeIds((prev) => (prev.includes(attendee.id) ? prev : [...prev, attendee.id]));
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('detail.checkInError'));
    } finally {
      setCheckingInAttendeeId(null);
    }
  }

  async function handleToggleWaitlist() {
    if (!event || updating) return;

    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setUpdating(true);
    try {
      if (currentWaitlistPosition) {
        await leaveEventWaitlist(event.id);
        setWaitlistPosition(null);
        waitlistApi.refresh();
        return;
      }

      const result = await joinEventWaitlist(event.id);
      setWaitlistPosition(result.data.position ?? null);
      waitlistApi.refresh();
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t(currentWaitlistPosition ? 'detail.leaveWaitlistError' : 'detail.joinWaitlistError'));
    } finally {
      setUpdating(false);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('detailTitle')}
        backLabel={t('detail.goBack')}
        fallbackHref="/(tabs)/events"
        rightAction={{ accessibilityLabel: t('share'), icon: 'share-outline', onPress: handleShare }}
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 116, gap: 12 }}
        refreshControl={<RefreshControl refreshing={isLoading} onRefresh={handleRefresh} tintColor={primary} colors={[primary]} />}
      >
        <HeroCard variant="default" className="overflow-hidden">
          <View className="h-1 w-full" style={{ backgroundColor: accent }} />
          {coverImage ? <Image source={{ uri: coverImage }} style={{ width: '100%', height: 180 }} contentFit="cover" /> : null}
          <HeroCard.Body className="gap-4 px-4 py-4">
            <View className="flex-row flex-wrap gap-2">
              {event.category ? (
                <Chip size="sm" variant="soft" color="warning">
                  <Ionicons name="pricetag-outline" size={12} color={accent} />
                  <Chip.Label>{event.category.name}</Chip.Label>
                </Chip>
              ) : null}
              {event.is_full ? (
                <Chip size="sm" variant="soft" color="danger">
                  <Chip.Label>{t('full')}</Chip.Label>
                </Chip>
              ) : null}
              {currentRsvp ? (
                <Chip size="sm" variant="soft" color="success">
                  <Ionicons name="checkmark-circle-outline" size={12} color={theme.success} />
                  <Chip.Label>{currentRsvp === 'going' ? t('going') : t('interested')}</Chip.Label>
                </Chip>
              ) : null}
            </View>

            <HeroCard.Title className="text-2xl leading-8">{event.title}</HeroCard.Title>
            {event.description ? (
              <HeroCard.Description className="text-sm leading-6">
                {stripHtml(event.description)}
              </HeroCard.Description>
            ) : null}
          </HeroCard.Body>
        </HeroCard>

        <View className="flex-row gap-3">
          <DetailMetric icon="calendar-outline" label={t('detail.date')} value={dateStr} primary={primary} />
          <DetailMetric icon="time-outline" label={t('detail.time')} value={timeStr} primary={primary} />
        </View>

        {event.is_online ? (
          <HeroCard variant="secondary">
            <HeroCard.Body className="gap-3 px-4 py-4">
              <SectionTitle icon="videocam-outline" title={onlineLink ? t('onlineTapToJoin') : t('onlineEvent')} primary={primary} theme={theme} />
              {onlineLink ? (
                <HeroButton variant="secondary" onPress={() => void Linking.openURL(onlineLink)}>
                  <Ionicons name="open-outline" size={18} color={primary} />
                  <HeroButton.Label>{t('detail.joinOnline')}</HeroButton.Label>
                </HeroButton>
              ) : null}
            </HeroCard.Body>
          </HeroCard>
        ) : event.location ? (
          <HeroCard variant="secondary">
            <HeroCard.Body className="gap-3 px-4 py-4">
              <SectionTitle icon="location-outline" title={t('detail.location')} primary={primary} theme={theme} />
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{event.location}</Text>
            </HeroCard.Body>
          </HeroCard>
        ) : null}

        <EventPollsCard
          polls={Array.isArray(pollsApi.data?.data) ? pollsApi.data.data : []}
          isLoading={pollsApi.isLoading}
          error={pollsApi.error}
          onRefresh={pollsApi.refresh}
          primary={primary}
          theme={theme}
          t={t}
        />

        <EventAttendeesCard
          attendees={attendees}
          counts={counts}
          waitlistPosition={currentWaitlistPosition}
          waitlistCount={event.waitlist_count ?? null}
          isLoading={attendeesApi.isLoading}
          error={attendeesApi.error}
          onRefresh={attendeesApi.refresh}
          primary={primary}
          theme={theme}
          t={t}
        />

        {event.organizer ? (
          <HeroCard variant="secondary">
            <HeroCard.Body className="gap-3 px-4 py-4">
              <SectionTitle icon="person-circle-outline" title={t('detail.organizer')} primary={primary} theme={theme} />
              <View className="flex-row items-center gap-3">
                <Avatar uri={event.organizer.avatar ?? undefined} name={event.organizer.name ?? '?'} size={44} />
                <Text className="text-sm font-semibold" style={{ color: theme.text }}>{event.organizer.name ?? t('common:unknown')}</Text>
              </View>
            </HeroCard.Body>
          </HeroCard>
        ) : null}

        {isOrganizer ? (
          <HeroCard variant="secondary">
            <HeroCard.Body className="gap-3 px-4 py-4">
              <SectionTitle icon="settings-outline" title={t('detail.ownerTools')} primary={primary} theme={theme} />
              <HeroButton variant="secondary" onPress={openEditEvent}>
                <Ionicons name="create-outline" size={18} color={primary} />
                <HeroButton.Label>{t('detail.edit')}</HeroButton.Label>
              </HeroButton>
            </HeroCard.Body>
          </HeroCard>
        ) : null}

        {isOrganizer ? (
          <OrganizerAttendanceCard
            attendees={attendees}
            isLoading={attendeesApi.isLoading}
            error={attendeesApi.error}
            checkingInAttendeeId={checkingInAttendeeId}
            onCheckIn={handleCheckIn}
            onRefresh={attendeesApi.refresh}
            primary={primary}
            theme={theme}
            t={t}
          />
        ) : null}

        {user ? (
          <EventReminderCard
            eventId={event.id}
            reminders={Array.isArray(remindersApi.data?.data) ? remindersApi.data.data : []}
            isLoading={remindersApi.isLoading}
            error={remindersApi.error}
            onRefresh={remindersApi.refresh}
            primary={primary}
            theme={theme}
            t={t}
          />
        ) : null}
      </ScrollView>

      <Surface variant="default" className="absolute bottom-0 left-0 right-0 border-t border-border p-4">
        <View className="flex-row gap-3">
          <RsvpButton
            label={t('going')}
            icon="checkmark-circle"
            selected={currentRsvp === 'going'}
            primary={primary}
            loading={updating}
            disabled={updating || (event.is_full && currentRsvp !== 'going')}
            onPress={() => void handleRsvp('going')}
          />
          <RsvpButton
            label={t('interested')}
            icon="star"
            selected={currentRsvp === 'interested'}
            primary={primary}
            loading={updating}
            disabled={updating}
            onPress={() => void handleRsvp('interested')}
          />
        </View>
        {event.is_full && currentRsvp !== 'going' ? (
          <HeroButton
            className="mt-3"
            variant={currentWaitlistPosition ? 'secondary' : 'primary'}
            isDisabled={updating}
            style={!currentWaitlistPosition ? { backgroundColor: primary } : undefined}
            onPress={() => void handleToggleWaitlist()}
            accessibilityLabel={currentWaitlistPosition ? t('detail.leaveWaitlist') : t('detail.joinWaitlist')}
            accessibilityState={{ busy: updating }}
          >
            {updating ? <Spinner size="sm" /> : <Ionicons name="hourglass-outline" size={16} color={currentWaitlistPosition ? primary : '#fff'} />}
            <HeroButton.Label>
              {currentWaitlistPosition
                ? t('detail.leaveWaitlist')
                : t('detail.joinWaitlist')}
            </HeroButton.Label>
          </HeroButton>
        ) : null}
      </Surface>
    </SafeAreaView>
  );
}

function EventReminderCard({
  eventId,
  reminders,
  isLoading,
  error,
  onRefresh,
  primary,
  theme,
  t,
}: {
  eventId: number;
  reminders: EventReminder[];
  isLoading: boolean;
  error: string | null;
  onRefresh: () => void;
  primary: string;
  theme: Theme;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const selected = new Set(reminders.map((reminder) => reminder.remind_before_minutes));

  async function toggleReminder(minutes: UpdateEventReminderInput['minutes']) {
    const nextMinutes = selected.has(minutes)
      ? REMINDER_OPTIONS.filter((option) => option !== minutes && selected.has(option))
      : [...REMINDER_OPTIONS.filter((option) => selected.has(option)), minutes];

    setSaving(true);
    setMessage(null);
    try {
      await updateEventReminders(
        eventId,
        nextMinutes.map((option) => ({ minutes: option, type: 'both' })),
      );
      setMessage(t('reminders.saved'));
      onRefresh();
    } catch {
      setMessage(t('reminders.error'));
    } finally {
      setSaving(false);
    }
  }

  return (
    <HeroCard variant="secondary">
      <HeroCard.Body className="gap-3 px-4 py-4">
        <SectionTitle icon="notifications-outline" title={t('reminders.title')} primary={primary} theme={theme} />
        <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
          {t('reminders.subtitle')}
        </Text>
        {isLoading ? (
          <LoadingSpinner />
        ) : error ? (
          <View className="gap-2">
            <Text className="text-sm text-danger">{t('reminders.loadError')}</Text>
            <HeroButton variant="secondary" size="sm" onPress={onRefresh}>
              <HeroButton.Label>{t('common:actions.retry')}</HeroButton.Label>
            </HeroButton>
          </View>
        ) : (
          <View className="flex-row flex-wrap gap-2">
            {REMINDER_OPTIONS.map((minutes) => {
              const enabled = selected.has(minutes);
              return (
                <HeroButton
                  key={minutes}
                  size="sm"
                  variant={enabled ? 'primary' : 'secondary'}
                  isDisabled={saving}
                  style={enabled ? { backgroundColor: primary } : undefined}
                  onPress={() => void toggleReminder(minutes)}
                  accessibilityLabel={t(`reminders.option.${minutes}`)}
                  accessibilityState={{ selected: enabled, busy: saving }}
                >
                  <Ionicons name={enabled ? 'notifications' : 'notifications-outline'} size={15} color={enabled ? '#fff' : primary} />
                  <HeroButton.Label>{t(`reminders.option.${minutes}`)}</HeroButton.Label>
                </HeroButton>
              );
            })}
          </View>
        )}
        {message ? (
          <Text className="text-xs text-muted-foreground" accessibilityLiveRegion="polite">{message}</Text>
        ) : null}
      </HeroCard.Body>
    </HeroCard>
  );
}

function EventPollsCard({
  polls,
  isLoading,
  error,
  onRefresh,
  primary,
  theme,
  t,
}: {
  polls: EventPoll[];
  isLoading: boolean;
  error: string | null;
  onRefresh: () => void;
  primary: string;
  theme: Theme;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const [localPolls, setLocalPolls] = useState<Record<number, EventPoll>>({});
  const [votingPollId, setVotingPollId] = useState<number | null>(null);
  const mergedPolls = polls.map((poll) => localPolls[poll.id] ?? poll).filter((poll) => poll.options?.length);

  async function handleVote(poll: EventPoll, optionId: number) {
    if (votingPollId !== null || poll.has_voted || poll.voted_option_id || poll.user_vote_option_id || poll.status === 'closed' || poll.is_active === false) return;

    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setVotingPollId(poll.id);
    try {
      const result = await voteEventPoll(poll.id, optionId);
      setLocalPolls((prev) => ({ ...prev, [poll.id]: result.data }));
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('detail.pollVoteError'));
    } finally {
      setVotingPollId(null);
    }
  }

  if (!isLoading && !error && mergedPolls.length === 0) return null;

  return (
    <HeroCard variant="secondary">
      <HeroCard.Body className="gap-4 px-4 py-4">
        <SectionTitle icon="stats-chart-outline" title={t('detail.eventPolls')} primary={primary} theme={theme} />
        {isLoading ? (
          <View className="flex-row items-center gap-2">
            <Spinner size="sm" />
            <Text className="text-xs text-muted-foreground">{t('detail.loadingPolls')}</Text>
          </View>
        ) : error ? (
          <View className="gap-2">
            <Text className="text-sm text-danger">{t('detail.pollsLoadError')}</Text>
            <HeroButton variant="secondary" size="sm" onPress={onRefresh}>
              <HeroButton.Label>{t('common:actions.retry')}</HeroButton.Label>
            </HeroButton>
          </View>
        ) : (
          <View className="gap-4">
            {mergedPolls.map((poll) => {
              const selectedOptionId = poll.voted_option_id ?? poll.user_vote_option_id ?? null;
              const showResults = !!selectedOptionId || poll.has_voted || poll.status === 'closed' || poll.is_active === false;
              return (
                <Surface key={poll.id} variant="tertiary" className="rounded-lg border border-border px-3 py-3">
                  <View className="gap-3">
                    <Text className="text-sm font-semibold" style={{ color: theme.text }}>{poll.question}</Text>
                    {poll.description ? (
                      <Text className="text-xs leading-4" style={{ color: theme.textSecondary }}>{poll.description}</Text>
                    ) : null}
                    {poll.options.map((option) => {
                      const label = option.text ?? option.label ?? t('detail.pollOptionFallback');
                      const selected = selectedOptionId === option.id;
                      return (
                        <HeroButton
                          key={option.id}
                          variant={selected ? 'primary' : 'secondary'}
                          size="sm"
                          isDisabled={votingPollId !== null || showResults}
                          style={selected ? { backgroundColor: primary } : undefined}
                          onPress={() => void handleVote(poll, option.id)}
                          accessibilityLabel={label}
                          accessibilityState={{ selected, busy: votingPollId === poll.id }}
                        >
                          {votingPollId === poll.id ? <Spinner size="sm" /> : null}
                          <HeroButton.Label>
                            {showResults
                              ? t('detail.pollOptionResult', { label, percent: option.percentage ?? 0 })
                              : label}
                          </HeroButton.Label>
                        </HeroButton>
                      );
                    })}
                    <Text className="text-xs text-muted-foreground">
                      {t('detail.pollTotalVotes', { count: poll.total_votes ?? 0 })}
                    </Text>
                  </View>
                </Surface>
              );
            })}
          </View>
        )}
      </HeroCard.Body>
    </HeroCard>
  );
}

function EventAttendeesCard({
  attendees,
  counts,
  waitlistPosition,
  waitlistCount,
  isLoading,
  error,
  onRefresh,
  primary,
  theme,
  t,
}: {
  attendees: EventAttendee[];
  counts: { going: number; interested: number };
  waitlistPosition: number | null;
  waitlistCount: number | null;
  isLoading: boolean;
  error: string | null;
  onRefresh: () => void;
  primary: string;
  theme: Theme;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const visibleAttendees = attendees.slice(0, 8);
  const hiddenCount = Math.max(0, counts.going + counts.interested - visibleAttendees.length);

  return (
    <HeroCard variant="secondary">
      <HeroCard.Body className="gap-3 px-4 py-4">
        <SectionTitle icon="people-outline" title={t('detail.attendees')} primary={primary} theme={theme} />
        <Text className="text-sm" style={{ color: theme.textSecondary }}>
          {t('attendees', { going: counts.going, interested: counts.interested })}
        </Text>
        {waitlistPosition ? (
          <Chip size="sm" variant="soft" color="warning" className="self-start">
            <Ionicons name="hourglass-outline" size={12} color={theme.warning} />
            <Chip.Label>{t('detail.onWaitlistPosition', { position: waitlistPosition })}</Chip.Label>
          </Chip>
        ) : waitlistCount && waitlistCount > 0 ? (
          <Text className="text-xs text-muted-foreground">{t('detail.waitlistCount', { count: waitlistCount })}</Text>
        ) : null}

        {isLoading ? (
          <View className="flex-row items-center gap-2">
            <Spinner size="sm" />
            <Text className="text-xs text-muted-foreground">{t('detail.loadingAttendees')}</Text>
          </View>
        ) : error ? (
          <View className="gap-2">
            <Text className="text-sm text-danger">{t('detail.attendeesLoadError')}</Text>
            <HeroButton variant="secondary" size="sm" onPress={onRefresh}>
              <HeroButton.Label>{t('common:actions.retry')}</HeroButton.Label>
            </HeroButton>
          </View>
        ) : visibleAttendees.length > 0 ? (
          <View className="gap-2">
            {visibleAttendees.map((attendee) => {
              const attendeeName = getAttendeeName(attendee, t);
              return (
                <View key={attendee.id} className="flex-row items-center gap-3">
                  <Avatar uri={attendee.avatar_url ?? attendee.avatar ?? undefined} name={attendeeName} size={34} />
                  <View className="min-w-0 flex-1">
                    <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                      {attendeeName}
                    </Text>
                    <Text className="text-xs" style={{ color: theme.textSecondary }}>
                      {getAttendeeStatusLabel(attendee, t)}
                    </Text>
                  </View>
                </View>
              );
            })}
            {hiddenCount > 0 ? (
              <Text className="text-xs text-muted-foreground">{t('detail.moreAttendees', { count: hiddenCount })}</Text>
            ) : null}
          </View>
        ) : (
          <Text className="text-sm" style={{ color: theme.textSecondary }}>{t('detail.noAttendees')}</Text>
        )}
      </HeroCard.Body>
    </HeroCard>
  );
}

function OrganizerAttendanceCard({
  attendees,
  isLoading,
  error,
  checkingInAttendeeId,
  onCheckIn,
  onRefresh,
  primary,
  theme,
  t,
}: {
  attendees: EventAttendee[];
  isLoading: boolean;
  error: string | null;
  checkingInAttendeeId: number | null;
  onCheckIn: (attendee: EventAttendee) => void;
  onRefresh: () => void;
  primary: string;
  theme: Theme;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const checkInAttendees = attendees.filter((attendee) => {
    const status = attendee.rsvp_status ?? attendee.status;
    return !status || status === 'going' || status === 'attending' || status === 'attended';
  });
  const checkedInCount = checkInAttendees.filter((attendee) => attendee.checked_in).length;

  return (
    <HeroCard variant="secondary">
      <HeroCard.Body className="gap-4 px-4 py-4">
        <View className="flex-row items-center justify-between gap-3">
          <SectionTitle icon="clipboard-outline" title={t('detail.organizerAttendance')} primary={primary} theme={theme} />
          <Chip size="sm" variant="soft" color="success">
            <Chip.Label>{t('detail.checkInProgress', { checked: checkedInCount, total: checkInAttendees.length })}</Chip.Label>
          </Chip>
        </View>

        {isLoading ? (
          <View className="py-3">
            <LoadingSpinner />
          </View>
        ) : error ? (
          <View className="gap-2">
            <Text className="text-sm text-danger">{t('detail.attendanceLoadError')}</Text>
            <HeroButton variant="secondary" size="sm" onPress={onRefresh}>
              <HeroButton.Label>{t('common:actions.retry')}</HeroButton.Label>
            </HeroButton>
          </View>
        ) : checkInAttendees.length === 0 ? (
          <View className="gap-1 py-2">
            <Text className="text-sm font-semibold" style={{ color: theme.text }}>{t('detail.noCheckInAttendeesTitle')}</Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('detail.noCheckInAttendees')}</Text>
          </View>
        ) : (
          <View className="gap-3">
            {checkInAttendees.map((attendee) => {
              const attendeeName = getAttendeeName(attendee, t);
              const checkedIn = !!attendee.checked_in;
              const isChecking = checkingInAttendeeId === attendee.id;
              return (
                <Surface key={attendee.id} variant="tertiary" className="rounded-lg border border-border px-3 py-3">
                  <View className="gap-3">
                    <View className="flex-row items-center gap-3">
                      <Avatar uri={attendee.avatar_url ?? attendee.avatar ?? undefined} name={attendeeName} size={40} />
                      <View className="min-w-0 flex-1">
                        <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                          {attendeeName}
                        </Text>
                        <Text className="text-xs" style={{ color: theme.textSecondary }}>
                          {getAttendeeStatusLabel(attendee, t)}
                        </Text>
                      </View>
                      {checkedIn ? (
                        <Chip size="sm" variant="soft" color="success">
                          <Ionicons name="checkmark-circle-outline" size={12} color={theme.success} />
                          <Chip.Label>{t('detail.attendeeCheckedIn')}</Chip.Label>
                        </Chip>
                      ) : null}
                    </View>

                    {!checkedIn ? (
                      <HeroButton
                        size="sm"
                        variant="primary"
                        isDisabled={checkingInAttendeeId !== null}
                        style={{ backgroundColor: primary }}
                        onPress={() => onCheckIn(attendee)}
                        accessibilityLabel={t('detail.checkInAttendeeLabel', { name: attendeeName })}
                        accessibilityState={{ busy: isChecking }}
                      >
                        {isChecking ? <Spinner size="sm" /> : <Ionicons name="person-add-outline" size={16} color="#fff" />}
                        <HeroButton.Label>{isChecking ? t('detail.checkingIn') : t('detail.checkIn')}</HeroButton.Label>
                      </HeroButton>
                    ) : null}
                  </View>
                </Surface>
              );
            })}
          </View>
        )}
      </HeroCard.Body>
    </HeroCard>
  );
}

function ScreenShell({ title, backLabel, children }: { title: string; backLabel: string; children: React.ReactNode }) {
  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={title} backLabel={backLabel} fallbackHref="/(tabs)/events" />
      <View className="flex-1 px-4">{children}</View>
    </SafeAreaView>
  );
}

function CenteredState({ text }: { text: string }) {
  return (
    <HeroCard variant="secondary" className="my-8">
      <HeroCard.Body className="items-center gap-4">
        <Ionicons name="alert-circle-outline" size={34} color="#F59E0B" />
        <Text className="text-center text-sm text-muted-foreground">{text}</Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function DetailMetric({ icon, label, value, primary }: { icon: IoniconName; label: string; value: string; primary: string }) {
  return (
    <HeroCard variant="secondary" className="flex-1">
      <HeroCard.Body className="gap-1 px-3 py-3">
        <Ionicons name={icon} size={18} color={primary} />
        <Text className="text-[11px] font-semibold uppercase text-muted-foreground">{label}</Text>
        <Text className="text-sm font-bold text-foreground" numberOfLines={3}>{value}</Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function SectionTitle({ icon, title, primary, theme }: { icon: IoniconName; title: string; primary: string; theme: Theme }) {
  return (
    <View className="flex-row items-center gap-2">
      <Ionicons name={icon} size={18} color={primary} />
      <Text className="text-base font-semibold" style={{ color: theme.text }}>{title}</Text>
    </View>
  );
}

function RsvpButton({
  label,
  icon,
  selected,
  primary,
  loading,
  disabled,
  onPress,
}: {
  label: string;
  icon: IoniconName;
  selected: boolean;
  primary: string;
  loading: boolean;
  disabled: boolean;
  onPress: () => void;
}) {
  return (
    <HeroButton
      className="flex-1"
      variant={selected ? 'primary' : 'secondary'}
      isDisabled={disabled}
      style={selected ? { backgroundColor: primary } : undefined}
      onPress={onPress}
      accessibilityLabel={label}
      accessibilityState={{ busy: loading, selected }}
    >
      {loading ? <Spinner size="sm" /> : <Ionicons name={icon} size={16} color={selected ? '#fff' : primary} />}
      <HeroButton.Label>{label}</HeroButton.Label>
    </HeroButton>
  );
}

function stripHtml(value: string): string {
  return value.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
}

function getAttendeeName(attendee: EventAttendee, t: (key: string, opts?: Record<string, unknown>) => string): string {
  return attendee.name || `${attendee.first_name ?? ''} ${attendee.last_name ?? ''}`.trim() || t('detail.communityMember');
}

function getAttendeeStatusLabel(attendee: EventAttendee, t: (key: string, opts?: Record<string, unknown>) => string): string {
  const status = attendee.rsvp_status ?? attendee.status;
  if (status === 'interested' || status === 'maybe') return t('detail.attendeeInterested');
  if (status === 'attended' || attendee.checked_in) return t('detail.attendeeCheckedIn');
  return t('detail.attendeeGoing');
}
