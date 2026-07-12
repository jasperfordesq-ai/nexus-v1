// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { Button as HeroButton, Card as HeroCard, TagGroup, Text } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  createEvent,
  getEvent,
  getEventCategories,
  updateEvent,
  uploadEventImage,
  type CanonicalEvent,
  type CreateEventPayload,
  type EventCategory,
} from '@/lib/api/events';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import { contrastText, withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import { useAppToast } from '@/components/ui/AppToast';
import FormActionFooter from '@/components/ui/FormActionFooter';
import Input from '@/components/ui/Input';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const eventCategoryIds = ['workshop', 'social', 'outdoor', 'online', 'meeting', 'training', 'other'] as const;
const MAX_COVER_IMAGE_SIZE = 5 * 1024 * 1024;
const ALLOWED_COVER_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

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

function toDateInputValue(value: string | null | undefined) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toISOString().slice(0, 16);
}

function toNumber(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const parsed = Number(trimmed);
  return Number.isFinite(parsed) ? parsed : null;
}

function resolveEventCategory(event: CanonicalEvent): string {
  if (event.category?.id) return String(event.category.id);
  return event.category?.slug?.trim().toLowerCase() ?? '';
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
  const params = useLocalSearchParams<{ group_id?: string; id?: string; series_id?: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { show: showToast } = useAppToast();
  const parsedGroupId = Number(params.group_id);
  const groupId = Number.isFinite(parsedGroupId) && parsedGroupId > 0 ? parsedGroupId : null;
  const eventId = Number(params.id);
  const isEditing = Number.isFinite(eventId) && eventId > 0;
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [startTime, setStartTime] = useState(tomorrowLocalValue());
  const [endTime, setEndTime] = useState('');
  const [category, setCategory] = useState('');
  const [categories, setCategories] = useState<EventCategory[]>([]);
  const [location, setLocation] = useState('');
  const [latitude, setLatitude] = useState('');
  const [longitude, setLongitude] = useState('');
  const [videoUrl, setVideoUrl] = useState('');
  const [maxAttendees, setMaxAttendees] = useState('');
  const [allowRemoteAttendance, setAllowRemoteAttendance] = useState(false);
  const [federatedVisibility, setFederatedVisibility] = useState<CreateEventPayload['federated_visibility']>(
    isEditing ? undefined : 'none',
  );
  const [selectedImageUri, setSelectedImageUri] = useState<string | null>(null);
  const [existingCoverImage, setExistingCoverImage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [hasHydratedEdit, setHasHydratedEdit] = useState(false);
  const parsedSeriesId = Number(params.series_id);
  const [seriesId, setSeriesId] = useState<number | null>(
    Number.isFinite(parsedSeriesId) && parsedSeriesId > 0 ? parsedSeriesId : null,
  );
  const fallbackHref = isEditing
    ? ({ pathname: '/(modals)/event-detail', params: { id: String(eventId) } } as unknown as Href)
    : groupId
      ? ({ pathname: '/(modals)/group-detail', params: { id: String(groupId) } } as unknown as Href)
      : '/(tabs)/events';

  useEffect(() => {
    if (!isEditing || hasHydratedEdit) return;

    let isMounted = true;
    getEvent(eventId)
      .then((response) => {
        if (!isMounted) return;
        if (!response.data.permissions.edit) throw new Error('EVENT_EDIT_NOT_ALLOWED');
        hydrateFromEvent(response.data);
        setHasHydratedEdit(true);
      })
      .catch(() => {
        if (!isMounted) return;
        showToast({ title: t('create.failedTitle'), description: t('create.loadFailed'), variant: 'danger' });
      });

    return () => {
      isMounted = false;
    };
  }, [eventId, hasHydratedEdit, isEditing, showToast, t]);

  useEffect(() => {
    let isMounted = true;
    getEventCategories()
      .then((response) => {
        if (isMounted) setCategories(response.data);
      })
      .catch(() => {
        if (isMounted) setCategories([]);
      });
    return () => {
      isMounted = false;
    };
  }, []);

  function hydrateFromEvent(event: CanonicalEvent) {
    setTitle(event.title ?? '');
    setDescription(event.description ?? '');
    setStartTime(toDateInputValue(event.schedule.start_at) || tomorrowLocalValue());
    setEndTime(toDateInputValue(event.schedule.end_at));
    setCategory(resolveEventCategory(event));
    setLocation(event.location.label ?? '');
    setLatitude(event.location.latitude !== null ? String(event.location.latitude) : '');
    setLongitude(event.location.longitude !== null ? String(event.location.longitude) : '');
    setVideoUrl(event.online_access.video_url ?? event.online_access.join_url ?? '');
    setMaxAttendees(event.relationship.capacity.limit !== null ? String(event.relationship.capacity.limit) : '');
    setAllowRemoteAttendance(event.location.mode !== 'in_person');
    setFederatedVisibility(event.federated_visibility ?? undefined);
    setExistingCoverImage(event.primary_image?.url ?? null);
    setSeriesId(event.series.named?.id ?? null);
    setSelectedImageUri(null);
  }

  async function pickCoverImage() {
    try {
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.85,
        allowsMultipleSelection: false,
      });
      if (result.canceled || !result.assets?.[0]?.uri) return;

      const asset = result.assets[0];
      if (asset.mimeType && !ALLOWED_COVER_IMAGE_TYPES.includes(asset.mimeType)) {
        showToast({ title: t('create.validationTitle'), description: t('create.imageTypeError'), variant: 'warning' });
        return;
      }
      if (asset.fileSize && asset.fileSize > MAX_COVER_IMAGE_SIZE) {
        showToast({ title: t('create.validationTitle'), description: t('create.imageSizeError'), variant: 'warning' });
        return;
      }

      setSelectedImageUri(asset.uri);
    } catch {
      showToast({ title: t('create.imagePickFailedTitle'), description: t('create.imagePickFailedDescription'), variant: 'danger' });
    }
  }

  async function submit() {
    if (isEditing && !hasHydratedEdit) {
      showToast({ title: t('create.failedTitle'), description: t('create.loadFailed'), variant: 'danger' });
      return;
    }

    const start = toApiDate(startTime);
    const end = endTime.trim() ? toApiDate(endTime) : null;
    if (!title.trim() || !description.trim() || !start) {
      showToast({ title: t('create.validationTitle'), description: t('create.validationRequired'), variant: 'warning' });
      return;
    }

    const startDate = new Date(start);
    if (startDate <= new Date()) {
      showToast({ title: t('create.validationTitle'), description: t('create.validationStartFuture'), variant: 'warning' });
      return;
    }

    if (end && new Date(end) <= startDate) {
      showToast({ title: t('create.validationTitle'), description: t('create.validationEndAfterStart'), variant: 'warning' });
      return;
    }

    const parsedMaxAttendees = maxAttendees.trim() ? Number(maxAttendees) : null;
    if (parsedMaxAttendees !== null && (!Number.isFinite(parsedMaxAttendees) || parsedMaxAttendees < 1 || parsedMaxAttendees > 10000)) {
      showToast({ title: t('create.validationTitle'), description: t('create.validationCapacity'), variant: 'warning' });
      return;
    }

    const hasLatitude = latitude.trim().length > 0;
    const hasLongitude = longitude.trim().length > 0;
    const latitudeValue = toNumber(latitude);
    const longitudeValue = toNumber(longitude);
    if (
      hasLatitude !== hasLongitude
      || (hasLatitude && (latitudeValue === null || latitudeValue < -90 || latitudeValue > 90))
      || (hasLongitude && (longitudeValue === null || longitudeValue < -180 || longitudeValue > 180))
    ) {
      showToast({ title: t('create.validationTitle'), description: t('create.invalidCoordinates'), variant: 'warning' });
      return;
    }

    setIsSubmitting(true);
    let successDestination: Parameters<typeof router.push>[0] | null = null;
    let shouldGoBack = false;
    try {
      const selectedCategory = categories.find((option) => String(option.id) === category);
      const numericCategoryId = /^\d+$/.test(category) ? Number(category) : null;
      const payload: CreateEventPayload = {
        title: title.trim(),
        description: description.trim(),
        start_time: start,
        end_time: end,
        group_id: isEditing ? undefined : groupId,
        location: location.trim() || null,
        latitude: latitudeValue,
        longitude: longitudeValue,
        category_id: selectedCategory?.id ?? numericCategoryId,
        category_name: selectedCategory || numericCategoryId ? null : category || null,
        series_id: seriesId,
        is_online: allowRemoteAttendance,
        video_url: allowRemoteAttendance && videoUrl.trim() ? videoUrl.trim() : null,
        max_attendees: parsedMaxAttendees,
        federated_visibility: federatedVisibility,
      };
      const result = isEditing ? await updateEvent(eventId, payload) : await createEvent(payload);
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      const id = result.data?.id ?? eventId;
      if (id) {
        if (selectedImageUri) {
          try {
            await uploadEventImage(id, selectedImageUri);
          } catch {
            showToast({ title: t('create.imageUploadFailedTitle'), description: t('create.imageUploadFailedDescription'), variant: 'danger' });
          }
        }
        successDestination = { pathname: '/(modals)/event-detail', params: { id: String(id) } };
      } else {
        shouldGoBack = true;
      }
    } catch {
      showToast({ title: t('create.failedTitle'), description: t('create.failedDescription'), variant: 'danger' });
    } finally {
      setIsSubmitting(false);
    }

    if (successDestination) {
      setTimeout(() => {
        if (typeof router.push === 'function') router.push(successDestination);
        else router.replace(successDestination);
      }, 0);
    } else if (shouldGoBack) {
      setTimeout(() => router.back(), 0);
    }
  }

  const categoryOptions = categories.length > 0
    ? categories.map((option) => ({
        id: String(option.id),
        label: option.name,
      }))
    : eventCategoryIds.map((id) => ({
        id,
        label: t(`category.${id}`),
      }));

  return (
    <SafeAreaView testID="new-event-screen" className="flex-1 bg-background" style={{ flex: 1, backgroundColor: theme.bg }}>
      <AppTopBar
        title={isEditing ? t('create.editTitle') : t('create.title')}
        backLabel={t('common:back')}
        fallbackHref={fallbackHref}
      />
      <KeyboardAvoidingView
        style={{ flex: 1, backgroundColor: theme.bg }}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
      <ScrollView
        testID="new-event-scroll"
        className="flex-1"
        style={{ flex: 1, backgroundColor: theme.bg }}
        contentContainerStyle={{ flexGrow: 1, padding: 16, paddingBottom: 120, backgroundColor: theme.bg }}
        keyboardShouldPersistTaps="handled"
      >
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: '#f59e0b' }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#f59e0b', 0.14) }}>
                <Ionicons name="calendar-outline" size={25} color="#f59e0b" />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{isEditing ? t('create.editTitle') : t('create.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('create.subtitle')}</Text>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-4 p-4">
            <FormField label={t('create.titleLabel')} value={title} onChangeText={setTitle} placeholder={t('create.titlePlaceholder')} theme={theme} />
            <FormField label={t('create.descriptionLabel')} value={description} onChangeText={setDescription} placeholder={t('create.descriptionPlaceholder')} theme={theme} multiline />
            <View className="gap-3 rounded-panel-inner border p-3" style={{ borderColor: theme.border, backgroundColor: theme.bg }}>
              <View className="flex-row items-start gap-3">
                <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
                  <Ionicons name="image-outline" size={18} color={primary} />
                </View>
                <View className="min-w-0 flex-1">
                  <Text className="text-sm font-bold" style={{ color: theme.text }}>{t('create.coverImageLabel')}</Text>
                  <Text className="text-xs leading-5" style={{ color: theme.textMuted }}>{t('create.coverImageHint')}</Text>
                </View>
              </View>
              {selectedImageUri || existingCoverImage ? (
                <View className="overflow-hidden rounded-panel-inner border" style={{ borderColor: theme.border }}>
                  <Image
                    source={{ uri: selectedImageUri ?? resolveImageUrl(existingCoverImage) ?? undefined }}
                    style={{ width: '100%', height: 180, backgroundColor: theme.surface }}
                    contentFit="cover"
                  />
                  <View className="flex-row gap-2 p-3" style={{ backgroundColor: theme.surface }}>
                    <HeroButton className="flex-1" variant="secondary" onPress={() => void pickCoverImage()}>
                      <Ionicons name="image-outline" size={16} color={primary} />
                      <HeroButton.Label>{t('create.replaceImage')}</HeroButton.Label>
                    </HeroButton>
                    {selectedImageUri ? (
                      <HeroButton className="flex-1" variant="danger-soft" onPress={() => setSelectedImageUri(null)}>
                        <Ionicons name="trash-outline" size={16} color={theme.error} />
                        <HeroButton.Label>{t('create.removeImage')}</HeroButton.Label>
                      </HeroButton>
                    ) : null}
                  </View>
                </View>
              ) : (
                <HeroButton variant="secondary" onPress={() => void pickCoverImage()}>
                  <Ionicons name="image-outline" size={16} color={primary} />
                  <HeroButton.Label>{t('create.addImage')}</HeroButton.Label>
                </HeroButton>
              )}
            </View>
            <View className="gap-2">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.categoryLabel')}</Text>
              <TagGroup
                size="sm"
                selectionMode="single"
                selectedKeys={category ? [category] : []}
                onSelectionChange={(keys) => {
                  const next = Array.from(keys)[0];
                  setCategory(next === undefined ? '' : String(next));
                }}
              >
                <TagGroup.List>
                  {categoryOptions.map((option) => {
                    const isSelected = category === option.id;
                    return (
                      <TagGroup.Item
                        key={option.id}
                        id={option.id}
                        style={isSelected ? { backgroundColor: primary } : undefined}
                      >
                        <TagGroup.ItemLabel style={isSelected ? { color: contrastText(primary) } : undefined}>
                          {option.label}
                        </TagGroup.ItemLabel>
                      </TagGroup.Item>
                    );
                  })}
                </TagGroup.List>
              </TagGroup>
            </View>
            <FormField label={t('create.startLabel')} value={startTime} onChangeText={setStartTime} placeholder={t('create.datePlaceholder')} theme={theme} />
            <FormField label={t('create.endLabel')} value={endTime} onChangeText={setEndTime} placeholder={t('create.optionalDatePlaceholder')} theme={theme} />
            <FormField label={t('create.locationLabel')} value={location} onChangeText={setLocation} placeholder={t('create.locationPlaceholder')} theme={theme} />
            <View className="gap-3 rounded-panel-inner border p-3" style={{ borderColor: theme.border, backgroundColor: withAlpha(primary, 0.06) }}>
              <View className="flex-row items-start gap-3">
                <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                  <Ionicons name="navigate-outline" size={18} color={primary} />
                </View>
                <View className="min-w-0 flex-1">
                  <Text className="text-sm font-bold" style={{ color: theme.text }}>{t('create.coordinatesLabel')}</Text>
                  <Text className="text-xs leading-5" style={{ color: theme.textSecondary }}>{t('create.coordinatesHint')}</Text>
                </View>
              </View>
              <View className="flex-row gap-3">
                <View className="min-w-0 flex-1">
                  <FormField label={t('create.latitudeLabel')} value={latitude} onChangeText={setLatitude} placeholder={t('create.latitudePlaceholder')} theme={theme} keyboardType="decimal-pad" />
                </View>
                <View className="min-w-0 flex-1">
                  <FormField label={t('create.longitudeLabel')} value={longitude} onChangeText={setLongitude} placeholder={t('create.longitudePlaceholder')} theme={theme} keyboardType="decimal-pad" />
                </View>
              </View>
            </View>
            <ToggleChip label={t('create.remoteAttendance')} selected={allowRemoteAttendance} onPress={() => setAllowRemoteAttendance((value) => !value)} primary={primary} />
            {allowRemoteAttendance ? (
              <FormField label={t('create.videoUrlLabel')} value={videoUrl} onChangeText={setVideoUrl} placeholder={t('create.videoUrlPlaceholder')} theme={theme} />
            ) : null}
            <FormField label={t('create.maxAttendeesLabel')} value={maxAttendees} onChangeText={setMaxAttendees} placeholder={t('create.maxAttendeesPlaceholder')} theme={theme} keyboardType="number-pad" />

            <View className="flex-row flex-wrap gap-2">
              <ToggleChip
                label={t('create.federated')}
                selected={federatedVisibility === 'listed' || federatedVisibility === 'bookable'}
                onPress={() => setFederatedVisibility((value) => (
                  value === 'listed' || value === 'bookable' ? 'none' : 'listed'
                ))}
                primary={primary}
              />
            </View>

          </HeroCard.Body>
        </HeroCard>
      </ScrollView>
        <FormActionFooter
          title={isEditing ? t('create.editReviewTitle') : t('create.reviewTitle')}
          subtitle={isEditing ? t('create.editReviewSubtitle') : t('create.reviewSubtitle')}
          submitLabel={isEditing ? t('create.updateSubmit') : t('create.submit')}
          primary={primary}
          isSubmitting={isSubmitting}
          onSubmit={submit}
        />
      </KeyboardAvoidingView>
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
  keyboardType?: 'default' | 'number-pad' | 'decimal-pad';
}) {
  return (
    <View>
      <Input
        label={label}
        style={{ color: theme.text, minHeight: multiline ? 112 : undefined, textAlignVertical: multiline ? 'top' : 'center' }}
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
