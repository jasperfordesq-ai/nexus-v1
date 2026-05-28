// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, Linking, RefreshControl, ScrollView, Share, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';

import { getEvent, getEventOnlineLink, removeRsvp, rsvpEvent } from '@/lib/api/events';
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

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

export default function EventDetailScreen() {
  return (
    <ModalErrorBoundary>
      <EventDetailScreenInner />
    </ModalErrorBoundary>
  );
}

function EventDetailScreenInner() {
  const { t } = useTranslation('events');
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();

  const eventId = Number(id);
  const safeEventId = Number.isFinite(eventId) && eventId > 0 ? eventId : 0;
  const { data, isLoading, refresh } = useApi(() => getEvent(safeEventId), [safeEventId], { enabled: safeEventId > 0 });
  const event = data?.data ?? null;

  const [rsvp, setRsvp] = useState<'going' | 'interested' | 'not_going' | null>(null);
  const [rsvpCounts, setRsvpCounts] = useState<{ going: number; interested: number } | null>(null);
  const [updating, setUpdating] = useState(false);

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
  const isOrganizer = user?.id === event.organizer?.id;
  const onlineLink = getEventOnlineLink(event);

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
        refreshControl={<RefreshControl refreshing={isLoading} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />}
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

        <HeroCard variant="secondary">
          <HeroCard.Body className="gap-3 px-4 py-4">
            <SectionTitle icon="people-outline" title={t('detail.attendees')} primary={primary} theme={theme} />
            <Text className="text-sm" style={{ color: theme.textSecondary }}>
              {t('attendees', { going: counts.going, interested: counts.interested })}
            </Text>
          </HeroCard.Body>
        </HeroCard>

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
      </Surface>
    </SafeAreaView>
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
