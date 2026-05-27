// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, ScrollView, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Text } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { createGroup } from '@/lib/api/groups';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import FormActionFooter from '@/components/ui/FormActionFooter';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function NewGroupRoute() {
  return (
    <ModalErrorBoundary>
      <NewGroupScreen />
    </ModalErrorBoundary>
  );
}

function NewGroupScreen() {
  const { t } = useTranslation(['groups', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [visibility, setVisibility] = useState<'public' | 'private'>('public');
  const [isFederated, setIsFederated] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function submit() {
    if (!name.trim() || !description.trim()) {
      Alert.alert(t('create.validationTitle'), t('create.validationRequired'));
      return;
    }

    setIsSubmitting(true);
    try {
      const result = await createGroup({
        name: name.trim(),
        description: description.trim(),
        location: location.trim() || null,
        visibility,
        federated_visibility: isFederated ? 'listed' : 'none',
      });
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      const id = result.data?.id;
      if (id) {
        router.replace({ pathname: '/(modals)/group-detail', params: { id: String(id) } });
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
      <AppTopBar title={t('create.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/groups" />
      <ScrollView className="flex-1" contentContainerStyle={{ padding: 16, paddingBottom: 120 }}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                <Ionicons name="people-outline" size={25} color={primary} />
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
            <FormField label={t('create.nameLabel')} value={name} onChangeText={setName} placeholder={t('create.namePlaceholder')} theme={theme} />
            <FormField label={t('create.descriptionLabel')} value={description} onChangeText={setDescription} placeholder={t('create.descriptionPlaceholder')} theme={theme} multiline />
            <FormField label={t('create.locationLabel')} value={location} onChangeText={setLocation} placeholder={t('create.locationPlaceholder')} theme={theme} />

            <View className="gap-2">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.visibilityLabel')}</Text>
              <View className="flex-row gap-2">
                <HeroButton className="flex-1" variant={visibility === 'public' ? 'primary' : 'secondary'} onPress={() => setVisibility('public')} style={visibility === 'public' ? { backgroundColor: primary } : undefined}>
                  <Ionicons name="globe-outline" size={15} color={visibility === 'public' ? '#fff' : primary} />
                  <HeroButton.Label>{t('public')}</HeroButton.Label>
                </HeroButton>
                <HeroButton className="flex-1" variant={visibility === 'private' ? 'primary' : 'secondary'} onPress={() => setVisibility('private')} style={visibility === 'private' ? { backgroundColor: primary } : undefined}>
                  <Ionicons name="lock-closed-outline" size={15} color={visibility === 'private' ? '#fff' : primary} />
                  <HeroButton.Label>{t('private')}</HeroButton.Label>
                </HeroButton>
              </View>
            </View>

            <HeroButton variant={isFederated ? 'primary' : 'secondary'} onPress={() => setIsFederated((value) => !value)} style={isFederated ? { backgroundColor: primary } : undefined}>
              <Ionicons name="git-network-outline" size={15} color={isFederated ? '#fff' : primary} />
              <HeroButton.Label>{t('create.federated')}</HeroButton.Label>
            </HeroButton>

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
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  theme: ReturnType<typeof useTheme>;
  multiline?: boolean;
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
      />
    </View>
  );
}
