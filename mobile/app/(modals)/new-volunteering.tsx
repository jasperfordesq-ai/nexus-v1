// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useState } from 'react';
import { Alert, ScrollView, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Spinner, Surface, Text } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { createOpportunity, getMyOrganisations, type VolunteeringOrganisation } from '@/lib/api/volunteering';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import FormActionFooter from '@/components/ui/FormActionFooter';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

function unwrapOrgs(response: { data?: VolunteeringOrganisation[] } | null | undefined): VolunteeringOrganisation[] {
  return Array.isArray(response?.data) ? response.data : [];
}

export default function NewVolunteeringRoute() {
  return (
    <ModalErrorBoundary>
      <NewVolunteeringScreen />
    </ModalErrorBoundary>
  );
}

function NewVolunteeringScreen() {
  const { t } = useTranslation(['volunteering', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const orgQuery = useApi(() => getMyOrganisations(), []);
  const organisations = useMemo(() => unwrapOrgs(orgQuery.data), [orgQuery.data]);
  const [organisationId, setOrganisationId] = useState<number | null>(null);
  const selectedOrg = organisations.find((org) => org.id === organisationId) ?? null;
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [location, setLocation] = useState('');
  const [skills, setSkills] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [isRemote, setIsRemote] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function submit() {
    if (!organisationId || !title.trim() || !description.trim()) {
      Alert.alert(t('create.validationTitle'), t('create.validationRequired'));
      return;
    }

    setIsSubmitting(true);
    try {
      const result = await createOpportunity({
        organization_id: organisationId,
        title: title.trim(),
        description: description.trim(),
        location: location.trim() || null,
        is_remote: isRemote,
        skills_needed: skills.trim(),
        start_date: startDate.trim() || null,
        end_date: endDate.trim() || null,
      });
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      const id = result.data?.id;
      if (id) {
        router.replace({ pathname: '/(modals)/volunteering-detail', params: { id: String(id) } });
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
      <AppTopBar title={t('create.title')} backLabel={t('common:back')} fallbackHref="/(modals)/volunteering" />
      <ScrollView className="flex-1" contentContainerStyle={{ padding: 16, paddingBottom: 120 }}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: '#e11d48' }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#e11d48', 0.14) }}>
                <Ionicons name="heart-outline" size={25} color="#e11d48" />
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
            <View className="gap-2">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.organisationLabel')}</Text>
              {orgQuery.isLoading ? <Spinner size="sm" /> : organisations.length === 0 ? (
                <Surface variant="secondary" className="rounded-panel-inner p-3">
                  <Text className="text-sm" style={{ color: theme.textSecondary }}>{t('create.noOrganisations')}</Text>
                </Surface>
              ) : (
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                  {organisations.map((org) => (
                    <HeroButton key={org.id} size="sm" variant={organisationId === org.id ? 'primary' : 'secondary'} onPress={() => setOrganisationId(org.id)} style={organisationId === org.id ? { backgroundColor: primary } : undefined}>
                      <HeroButton.Label>{org.name}</HeroButton.Label>
                    </HeroButton>
                  ))}
                </ScrollView>
              )}
              {selectedOrg ? <Text className="text-xs" style={{ color: theme.textSecondary }}>{t('create.selectedOrganisation', { name: selectedOrg.name })}</Text> : null}
            </View>

            <FormField label={t('create.titleLabel')} value={title} onChangeText={setTitle} placeholder={t('create.titlePlaceholder')} theme={theme} />
            <FormField label={t('create.descriptionLabel')} value={description} onChangeText={setDescription} placeholder={t('create.descriptionPlaceholder')} theme={theme} multiline />
            <FormField label={t('create.locationLabel')} value={location} onChangeText={setLocation} placeholder={t('create.locationPlaceholder')} theme={theme} />
            <FormField label={t('create.skillsLabel')} value={skills} onChangeText={setSkills} placeholder={t('create.skillsPlaceholder')} theme={theme} />
            <FormField label={t('create.startLabel')} value={startDate} onChangeText={setStartDate} placeholder={t('create.datePlaceholder')} theme={theme} />
            <FormField label={t('create.endLabel')} value={endDate} onChangeText={setEndDate} placeholder={t('create.datePlaceholder')} theme={theme} />

            <HeroButton variant={isRemote ? 'primary' : 'secondary'} onPress={() => setIsRemote((value) => !value)} style={isRemote ? { backgroundColor: primary } : undefined}>
              <Ionicons name="globe-outline" size={15} color={isRemote ? '#fff' : primary} />
              <HeroButton.Label>{t('create.remote')}</HeroButton.Label>
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
        isDisabled={organisations.length === 0}
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
