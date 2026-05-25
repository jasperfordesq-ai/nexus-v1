// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  Alert,
  Linking,
  RefreshControl,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import { getEvent, rsvpEvent, removeRsvp } from '@/lib/api/events';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function EventDetailScreen() {
  return (
    <ModalErrorBoundary>
      <EventDetailScreenInner />
    </ModalErrorBoundary>
  );
}

function EventDetailScreenInner() {
  const { t } = useTranslation('events');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const eventId = Number(id);
  const safeEventId = isNaN(eventId) || eventId <= 0 ? 0 : eventId;

  const { data, isLoading, refresh } = useApi(() => getEvent(safeEventId), [safeEventId], { enabled: safeEventId > 0 });

  const event = data?.data ?? null;

  const [rsvp, setRsvp] = useState<'going' | 'interested' | 'not_going' | null>(null);
  const [rsvpCounts, setRsvpCounts] = useState<{ going: number; interested: number } | null>(null);
  const [updating, setUpdating] = useState(false);

  if (isNaN(eventId) || eventId <= 0) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.invalidId')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  // Use server data as initial RSVP state once loaded
  const currentRsvp = rsvp ?? event?.user_rsvp ?? null;
  const counts = rsvpCounts ?? event?.rsvp_counts ?? { going: 0, interested: 0 };

  async function handleShare() {
    if (!event) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${event.title} — ${WEB_URL}/events/${event.id}`,
      });
    } catch { /* ignore */ }
  }

  async function handleRsvp(status: 'going' | 'interested') {
    if (!event) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

    // Toggle off if already selected — confirm cancellation
    if (currentRsvp === status) {
      Alert.alert(
        t('common:buttons.confirm'),
        t('rsvpCancelConfirm'),
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
      if (result?.data?.rsvp) {
        setRsvp(result.data.rsvp);
      }
      if (result?.data?.rsvp_counts) {
        setRsvpCounts(result.data.rsvp_counts);
      }
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('rsvpError'));
    } finally {
      setUpdating(false);
    }
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!event) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  const start = event.start_date ? new Date(event.start_date) : null;
  const isValidDate = start && !isNaN(start.getTime());
  const dateStr = isValidDate
    ? start.toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
    : '—';
  const timeStr = isValidDate
    ? start.toLocaleTimeString('default', { hour: '2-digit', minute: '2-digit' })
    : '—';

  return (
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView
        contentContainerStyle={{ padding: 20, paddingBottom: 48 }}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Title + share */}
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 10 }}>
          <Text className="text-xl font-bold text-foreground mb-2.5" style={{ flex: 1 }}>{event.title}</Text>
          <Pressable
            onPress={() => void handleShare()}
            style={{ padding: 4 }}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </Pressable>
        </View>
        {event.category && (
          <View style={{ alignSelf: 'flex-start', borderRadius: 6, paddingHorizontal: 10, paddingVertical: 4, marginBottom: 16, backgroundColor: (event.category.color ?? primary) + '20' }}>
            <Text style={{ fontSize: 11, fontWeight: '600', color: event.category.color ?? primary }}>
              {event.category.name}
            </Text>
          </View>
        )}

        {/* Date + time */}
        <View className="bg-surface rounded-xl p-4 gap-2.5 border border-border/50 mb-4">
          <MetaRow icon="calendar-outline" text={dateStr} theme={theme} />
          <MetaRow icon="time-outline" text={timeStr} theme={theme} />
          {event.is_online ? (
            <MetaRow
              icon="videocam-outline"
              text={event.online_url ? t('onlineTapToJoin') : t('onlineEvent')}
              onPress={event.online_url ? () => void Linking.openURL(event.online_url!) : undefined}
              tint={event.online_url ? primary : undefined}
              theme={theme}
            />
          ) : event.location ? (
            <MetaRow icon="location-outline" text={event.location} theme={theme} />
          ) : null}
        </View>

        {/* Attendees */}
        <View className="flex-row items-center gap-1.5 mb-4">
          <Ionicons name="people-outline" size={16} color={theme.textSecondary} />
          <Text className="text-xs text-muted-foreground flex-1">
            {t('attendees', { going: counts.going, interested: counts.interested })}
          </Text>
          {event.is_full && (
            <View className="bg-danger/10 rounded px-2 py-0.5">
              <Text className="text-xs font-semibold text-danger">{t('full')}</Text>
            </View>
          )}
        </View>

        {/* RSVP buttons */}
        <View className="flex-row gap-3 mb-6">
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

        {/* Description */}
        {event.description ? (
          <View className="mb-6">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2.5">{t('detail.about')}</Text>
            <Text className="text-sm text-foreground">{event.description}</Text>
          </View>
        ) : null}

        {/* Organizer */}
        {event.organizer ? (
          <View className="mb-6">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2.5">{t('detail.organizer')}</Text>
            <View className="flex-row items-center gap-3">
              <Avatar uri={event.organizer.avatar ?? undefined} name={event.organizer.name ?? '?'} size={36} />
              <Text className="text-sm font-semibold text-foreground">{event.organizer.name ?? t('common:unknown')}</Text>
            </View>
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function MetaRow({
  icon,
  text,
  onPress,
  tint,
  theme,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  text: string;
  onPress?: () => void;
  tint?: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <Pressable
      style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}
      onPress={onPress}
      disabled={!onPress}
    >
      <Ionicons name={icon} size={16} color={tint ?? theme.textSecondary} />
      <Text style={{ fontSize: 13, color: tint ?? theme.text, flex: 1 }}>{text}</Text>
    </Pressable>
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
  icon: React.ComponentProps<typeof Ionicons>['name'];
  selected: boolean;
  primary: string;
  loading: boolean;
  disabled: boolean;
  onPress: () => void;
}) {
  const iconColor = selected ? '#fff' : '#8E8E93'; // contrast on primary
  return (
    <Pressable
      style={[
        {
          flex: 1,
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 6,
          borderWidth: 1,
          borderRadius: 10,
          paddingVertical: 12,
        },
        selected
          ? { backgroundColor: primary, borderColor: primary }
          : { borderColor: '#C6C6C8' },
        disabled && { opacity: 0.4 },
      ]}
      onPress={onPress}
      disabled={disabled}
      accessibilityLabel={label}
      accessibilityRole="button"
      accessibilityState={{ busy: loading, selected }}
    >
      {loading ? (
        <Spinner size="sm" />
      ) : (
        <Ionicons name={icon} size={16} color={iconColor} />
      )}
      <Text style={{ fontSize: 13, fontWeight: '600', color: selected ? '#fff' : '#8E8E93' }}>{label}</Text>
    </Pressable>
  );
}
