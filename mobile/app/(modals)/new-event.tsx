// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, ScrollView, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Text } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { createEvent } from '@/lib/api/events';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import FormActionFooter from '@/components/ui/FormActionFooter';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const eventCategoryIds = ['workshop', 'social', 'outdoor', 'online', 'meeting', 'training', 'other'] as const;
type EventCategoryId = (typeof eventCategoryIds)[number];

function tomorrowLocalValue() {
  const date = new Date(Date.now() + 24 * 60 * 60 * 1000);
  date.setMinutes(0, 0, 0);
  return date.toISOString().slice(0, 16);
}

function toApiDate(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toISOString();
}

export default function NewEventRoute() {
  return (
    <ModalErrorBoundary>
      <NewEventScreen />
    </ModalErrorBoundary>
  );
}

function NewEventScreen() {
  const { t } = useTranslation(['events', 'common']);
  const params = useLocalSearchParams<{ group_id?: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const parsedGroupId = Number(params.group_id);
  const groupId = Number.isFinite(parsedGroupId) && parsedGroupId > 0 ? parsedGroupId : null;
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [startTime, setStartTime] = useState(tomorrowLocalValue());
  const [endTime, setEndTime] = useState('');
  const [category, setCategory] = useState<EventCategoryId | ''>('');
  const [location, setLocation] = useState('');
  const [videoUrl, setVideoUrl] = useState('');
  const [maxAttendees, setMaxAttendees] = useState('');
  const [allowRemoteAttendance, setAllowRemoteAttendance] = useState(false);
  const [isFederated, setIsFederated] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function submit() {
    const start = toApiDate(startTime);
    const end = endTime.trim() ? toApiDate(endTime) : null;
    if (!title.trim() || !description.trim() || !start) {
      Alert.alert(t('create.validationTitle'), t('create.validationRequired'));
      return;
    }

    const startDate = new Date(start);
    if (startDate <= new Date()) {
      Alert.alert(t('create.validationTitle'), t('create.validationStartFuture'));
      return;
    }

    if (end && new Date(end) <= startDate) {
      Alert.alert(t('create.validationTitle'), t('create.validationEndAfterStart'));
      return;
    }

    const parsedMaxAttendees = maxAttendees.trim() ? Number(maxAttendees) : null;
    if (parsedMaxAttendees !== null && (!Number.isFinite(parsedMaxAttendees) || parsedMaxAttendees < 1 || parsedMaxAttendees > 10000)) {
      Alert.alert(t('create.validationTitle'), t('create.validationCapacity'));
      return;
    }

    setIsSubmitting(true);
    try {
      const result = await createEvent({
        title: title.trim(),
        description: description.trim(),
        start_time: start,
        end_time: end,
        group_id: groupId,
        location: location.trim() || null,
        category_name: category || null,
        is_online: allowRemoteAttendance,
        online_link: allowRemoteAttendance && videoUrl.trim() ? videoUrl.trim() : null,
        max_attendees: parsedMaxAttendees,
        federated_visibility: isFederated ? 'listed' : 'none',
      });
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      const id = result.data?.id;
      if (id) {
        router.replace({ pathname: '/(modals)/event-detail', params: { id: String(id) } });
      } else {
        router.back();
      }
    } catch (error) {
      Alert.alert(t('create.failedTitle'), error instanceof Error ? error.message : t('create.failedDescription'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('create.title')}
        backLabel={t('common:back')}
        fallbackHref={groupId ? ({ pathname: '/(modals)/group-detail', params: { id: String(groupId) } } as unknown as Href) : '/(tabs)/events'}
      />
      <ScrollView className="flex-1" contentContainerStyle={{ padding: 16, paddingBottom: 120 }}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: '#f59e0b' }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#f59e0b', 0.14) }}>
                <Ionicons name="calendar-outline" size={25} color="#f59e0b" />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('create.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('create.subtitle')}</Text>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-4 p-4">
            <FormField label={t('create.titleLabel')} value={title} onChangeText={setTitle} placeholder={t('create.titlePlaceholder')} theme={theme} />
            <FormField label={t('create.descriptionLabel')} value={description} onChangeText={setDescription} placeholder={t('create.descriptionPlaceholder')} theme={theme} multiline />
            <View className="gap-2">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.categoryLabel')}</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                {eventCategoryIds.map((value) => (
                  <HeroButton
                    key={value}
                    size="sm"
                    variant={category === value ? 'primary' : 'secondary'}
                    onPress={() => setCategory((current) => (current === value ? '' : value))}
                    style={category === value ? { backgroundColor: primary } : undefined}
                  >
                    <HeroButton.Label>{t(`category.${value}`)}</HeroButton.Label>
                  </HeroButton>
                ))}
              </ScrollView>
            </View>
            <FormField label={t('create.startLabel')} value={startTime} onChangeText={setStartTime} placeholder={t('create.datePlaceholder')} theme={theme} />
            <FormField label={t('create.endLabel')} value={endTime} onChangeText={setEndTime} placeholder={t('create.optionalDatePlaceholder')} theme={theme} />
            <FormField label={t('create.locationLabel')} value={location} onChangeText={setLocation} placeholder={t('create.locationPlaceholder')} theme={theme} />
            <ToggleChip label={t('create.remoteAttendance')} selected={allowRemoteAttendance} onPress={() => setAllowRemoteAttendance((value) => !value)} primary={primary} />
            {allowRemoteAttendance ? (
              <FormField label={t('create.videoUrlLabel')} value={videoUrl} onChangeText={setVideoUrl} placeholder={t('create.videoUrlPlaceholder')} theme={theme} />
            ) : null}
            <FormField label={t('create.maxAttendeesLabel')} value={maxAttendees} onChangeText={setMaxAttendees} placeholder={t('create.maxAttendeesPlaceholder')} theme={theme} keyboardType="number-pad" />

            <View className="flex-row flex-wrap gap-2">
              <ToggleChip label={t('create.federated')} selected={isFederated} onPress={() => setIsFederated((value) => !value)} primary={primary} />
            </View>

          </HeroCard.Body>
        </HeroCard>
      </ScrollView>
      <FormActionFooter
        title={t('create.reviewTitle')}
        subtitle={t('create.reviewSubtitle')}
        submitLabel={t('create.submit')}
        primary={primary}
        isSubmitting={isSubmitting}
        onSubmit={submit}
      />
    </SafeAreaView>
  );
}

function FormField({
  label,
  value,
  onChangeText,
  placeholder,
  theme,
  multiline = false,
  keyboardType,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  theme: ReturnType<typeof useTheme>;
  multiline?: boolean;
  keyboardType?: 'default' | 'number-pad';
}) {
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <TextInput
        className={`${multiline ? 'min-h-28 py-3' : 'min-h-12'} rounded-panel-inner border px-3 text-sm`}
        style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg, textAlignVertical: multiline ? 'top' : 'center' }}
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        value={value}
        onChangeText={onChangeText}
        multiline={multiline}
        keyboardType={keyboardType}
      />
    </View>
  );
}

function ToggleChip({ label, selected, onPress, primary }: { label: string; selected: boolean; onPress: () => void; primary: string }) {
  return (
    <HeroButton size="sm" variant={selected ? 'primary' : 'secondary'} onPress={onPress} style={selected ? { backgroundColor: primary } : undefined}>
      <HeroButton.Label>{label}</HeroButton.Label>
    </HeroButton>
  );
}
