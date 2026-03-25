// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  Alert,
  Linking,
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
import { getEvent, rsvpEvent, removeRsvp } from '@/lib/api/events';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function EventDetailScreen() {
  const { t } = useTranslation('events');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.invalidId')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
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
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!event) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
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
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Title + share */}
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 10 }}>
          <Text style={[styles.title, { flex: 1 }]}>{event.title}</Text>
          <TouchableOpacity
            onPress={() => void handleShare()}
            style={{ padding: 4 }}
            activeOpacity={0.7}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </TouchableOpacity>
        </View>
        {event.category && (
          <View style={[styles.categoryPill, { backgroundColor: (event.category.color ?? primary) + '20' }]}>
            <Text style={[styles.categoryText, { color: event.category.color ?? primary }]}>
              {event.category.name}
            </Text>
          </View>
        )}

        {/* Date + time */}
        <View style={styles.metaCard}>
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
        <View style={styles.attendeesRow}>
          <Ionicons name="people-outline" size={16} color={theme.textSecondary} />
          <Text style={styles.attendeesText}>
            {t('attendees', { going: counts.going, interested: counts.interested })}
          </Text>
          {event.is_full && (
            <View style={styles.fullBadge}>
              <Text style={styles.fullBadgeText}>{t('full')}</Text>
            </View>
          )}
        </View>

        {/* RSVP buttons */}
        <View style={styles.rsvpRow}>
          <RsvpButton
            label={t('going')}
            icon="checkmark-circle"
            selected={currentRsvp === 'going'}
            primary={primary}
            theme={theme}
            loading={updating}
            disabled={updating || (event.is_full && currentRsvp !== 'going')}
            onPress={() => void handleRsvp('going')}
          />
          <RsvpButton
            label={t('interested')}
            icon="star"
            selected={currentRsvp === 'interested'}
            primary={primary}
            theme={theme}
            loading={updating}
            disabled={updating}
            onPress={() => void handleRsvp('interested')}
          />
        </View>

        {/* Description */}
        {event.description ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.about')}</Text>
            <Text style={styles.description}>{event.description}</Text>
          </View>
        ) : null}

        {/* Organizer */}
        {event.organizer ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.organizer')}</Text>
            <View style={styles.organizerRow}>
              <Avatar uri={event.organizer.avatar ?? undefined} name={event.organizer.name ?? '?'} size={36} />
              <Text style={styles.organizerName}>{event.organizer.name ?? t('common:unknown')}</Text>
            </View>
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
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
  theme: Theme;
}) {
  return (
    <TouchableOpacity
      style={metaRowStyle}
      onPress={onPress}
      disabled={!onPress}
      activeOpacity={onPress ? 0.7 : 1}
    >
      <Ionicons name={icon} size={16} color={tint ?? theme.textSecondary} />
      <Text style={[{ ...TYPOGRAPHY.label, color: theme.text, flex: 1 }, tint ? { color: tint } : null]}>{text}</Text>
    </TouchableOpacity>
  );
}

const metaRowStyle = { flexDirection: 'row' as const, alignItems: 'center' as const, gap: 10 };

function RsvpButton({
  label,
  icon,
  selected,
  primary,
  theme,
  loading,
  disabled,
  onPress,
}: {
  label: string;
  icon: React.ComponentProps<typeof Ionicons>['name'];
  selected: boolean;
  primary: string;
  theme: Theme;
  loading: boolean;
  disabled: boolean;
  onPress: () => void;
}) {
  const iconColor = selected ? '#fff' : theme.textSecondary; // contrast on primary
  return (
    <TouchableOpacity
      style={[
        rsvpBtnBase(theme),
        selected ? { backgroundColor: primary, borderColor: primary } : { borderColor: theme.border },
        disabled && rsvpBtnDisabled,
      ]}
      onPress={onPress}
      disabled={disabled}
      activeOpacity={0.8}
      accessibilityLabel={label}
      accessibilityRole="button"
      accessibilityState={{ busy: loading, selected }}
    >
      {loading ? (
        <ActivityIndicator size="small" color={iconColor} />
      ) : (
        <Ionicons name={icon} size={16} color={iconColor} />
      )}
      <Text style={[{ ...TYPOGRAPHY.label, fontWeight: '600' as const, color: theme.textSecondary }, selected && { color: '#fff' }]}>{label}</Text>{/* contrast on primary */}
    </TouchableOpacity>
  );
}

const rsvpBtnDisabled = { opacity: 0.4 };

function rsvpBtnBase(theme: Theme) {
  return {
    flex: 1,
    flexDirection: 'row' as const,
    alignItems: 'center' as const,
    justifyContent: 'center' as const,
    gap: 6,
    borderWidth: 1,
    borderRadius: RADIUS.md,
    paddingVertical: 12,
    backgroundColor: theme.surface,
  };
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { padding: 20, paddingBottom: 48 },
    title: { ...TYPOGRAPHY.h2, color: theme.text, marginBottom: 10 },
    categoryPill: { alignSelf: 'flex-start', borderRadius: SPACING.sm, paddingHorizontal: 10, paddingVertical: 4, marginBottom: SPACING.md },
    categoryText: { ...TYPOGRAPHY.caption, fontWeight: '600' },
    metaCard: {
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: RADIUS.lg,
      gap: 10,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      marginBottom: SPACING.md,
    },
    attendeesRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: SPACING.md },
    attendeesText: { ...TYPOGRAPHY.label, color: theme.textSecondary, flex: 1 },
    fullBadge: { backgroundColor: theme.errorBg, borderRadius: RADIUS.sm, paddingHorizontal: SPACING.sm, paddingVertical: 2 },
    fullBadgeText: { fontSize: 11, fontWeight: '600', color: theme.error },
    rsvpRow: { flexDirection: 'row', gap: 12, marginBottom: SPACING.lg },
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
    organizerRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    organizerName: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    errorText: { ...TYPOGRAPHY.body, color: theme.textMuted },
  });
}
